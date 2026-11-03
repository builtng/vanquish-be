<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\TrainingCounsellor;
use App\Models\User;
use App\Models\ActivityLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class UserController extends Controller
{
    /**
     * Roles an admin can pick on the Users screen. The two counsellor choices are stored
     * as role "counsellor" linked to a practitioner record of the matching counsellor_type.
     */
    private const ROLE_CHOICES = [
        'super_admin',
        'admin',
        'staff',
        'consultation_staff',
        'compliance_officer',
        'trainee_counsellor',
        'qualified_counsellor',
    ];

    /**
     * Only a Super Admin may create a Super Admin, or change or remove an existing one.
     */
    private function guardSuperAdmin(Request $request, ?User $target, ?string $requestedRole)
    {
        if ($request->user()->role === 'super_admin') {
            return null;
        }

        if ($requestedRole === 'super_admin' || ($target && $target->role === 'super_admin')) {
            return response()->json(['message' => 'Only a Super Admin can create or change a Super Admin account.'], 403);
        }

        return null;
    }

    private const COUNSELLOR_ROLE_TYPES = [
        'trainee_counsellor' => 'Trainee',
        'qualified_counsellor' => 'Qualified',
    ];

    /**
     * Shape a user for the admin Users screen, including which kind of counsellor they are.
     */
    private function presentUser(User $user): array
    {
        $type = $user->role === 'counsellor' ? $user->trainingCounsellor?->counsellor_type : null;

        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'role' => $user->role,
            'role_choice' => $type === 'Qualified'
                ? 'qualified_counsellor'
                : ($user->role === 'counsellor' ? 'trainee_counsellor' : $user->role),
            'counsellor_type' => $user->role === 'counsellor' ? ($type ?? 'Trainee') : null,
            'practitioner_id' => $user->trainingCounsellor?->tc_id,
            'is_active' => (bool) $user->is_active,
            'deactivated_at' => $user->deactivated_at,
            'two_factor_enabled' => (bool) $user->two_factor_enabled,
            'created_at' => $user->created_at,
            'updated_at' => $user->updated_at,
        ];
    }

    /**
     * Find the active practitioner with this email, or create one of the given type.
     */
    private function findOrCreatePractitioner(string $name, string $email, string $counsellorType): TrainingCounsellor
    {
        $practitioner = TrainingCounsellor::whereNull('archived_at')->where('email', $email)->first();

        if ($practitioner) {
            $practitioner->update(['counsellor_type' => $counsellorType]);
            return $practitioner;
        }

        $person = \App\Models\Person::findOrCreateByEmail($email, $name, null);

        return TrainingCounsellor::create([
            'person_id' => $person->id,
            'tc_id' => $counsellorType === 'Qualified'
                ? $this->nextQualifiedId()
                : TrainingCounsellor::generateUniqueTcId(),
            'name' => $name,
            'email' => $email,
            'counsellor_type' => $counsellorType,
            'status' => 'Active',
            'joined_date' => now(),
            'last_activity' => now(),
        ]);
    }

    /**
     * Next QC### practitioner id, matching how accepted QC applications are numbered.
     */
    private function nextQualifiedId(): string
    {
        $max = TrainingCounsellor::withTrashed()
            ->where('tc_id', 'LIKE', 'QC%')
            ->pluck('tc_id')
            ->map(fn ($id) => (int) substr($id, 2))
            ->max();

        return 'QC' . str_pad(max(1, ($max ?? 0) + 1), 3, '0', STR_PAD_LEFT);
    }

    /**
     * Display a listing of users (admin only)
     */
    public function index(Request $request)
    {
        // Only admin can view users
        if (!$request->user() || !$request->user()->isAdmin()) {
            return response()->json(['message' => 'Unauthorized. Admin access required.'], 403);
        }

        $users = User::select('id', 'name', 'email', 'role', 'training_counsellor_id', 'is_active', 'deactivated_at', 'two_factor_enabled', 'created_at', 'updated_at')
            ->with('trainingCounsellor:id,tc_id,counsellor_type')
            ->orderBy('id', 'desc')
            ->get()
            ->map(fn (User $user) => $this->presentUser($user));

        return response()->json($users);
    }

    /**
     * Store a newly created user (admin only)
     */
    public function store(Request $request)
    {
        // Only admin can create users
        if (!$request->user() || !$request->user()->isAdmin()) {
            return response()->json(['message' => 'Unauthorized. Admin access required.'], 403);
        }

        $validated = $request->validate([
            'name' => 'required|string|max:255|regex:/^[a-zA-Z\s\-\'\.]+$/',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => [
                'required',
                'string',
                'min:12',
                'confirmed',
                'regex:/[a-z]/',
                'regex:/[A-Z]/',
                'regex:/[0-9]/',
            ],
            'role' => 'required|string|in:' . implode(',', self::ROLE_CHOICES),
        ], [
            'password.regex' => 'Password must contain at least one uppercase letter, one lowercase letter, and one number.',
            'password.min' => 'Password must be at least 12 characters.',
        ]);

        if ($denied = $this->guardSuperAdmin($request, null, $validated['role'])) {
            return $denied;
        }

        $email = strtolower(trim($validated['email']));
        $counsellorType = self::COUNSELLOR_ROLE_TYPES[$validated['role']] ?? null;
        $practitioner = $counsellorType
            ? $this->findOrCreatePractitioner($validated['name'], $email, $counsellorType)
            : null;

        $user = User::create([
            'name' => $validated['name'],
            'email' => $email,
            'password' => Hash::make($validated['password']),
            'role' => $counsellorType ? 'counsellor' : $validated['role'],
            'training_counsellor_id' => $practitioner?->id,
            'is_active' => true,
        ]);

        // Log activity
        ActivityLog::create([
            'user_id' => $request->user()->id,
            'action' => 'user_created',
            'model_type' => User::class,
            'model_id' => $user->id,
            'description' => "User {$user->name} ({$user->email}) created with role: {$user->role}",
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        return response()->json([
            'message' => 'User created successfully',
            'user' => $this->presentUser($user->fresh('trainingCounsellor')),
        ], 201);
    }

    /**
     * Display the specified user (admin only)
     */
    public function show(Request $request, $id)
    {
        // Only admin can view users
        if (!$request->user() || !$request->user()->isAdmin()) {
            return response()->json(['message' => 'Unauthorized. Admin access required.'], 403);
        }

        $user = User::select('id', 'name', 'email', 'role', 'is_active', 'deactivated_at', 'two_factor_enabled', 'created_at', 'updated_at')
            ->findOrFail($id);

        return response()->json($user);
    }

    /**
     * Update the specified user (admin only)
     */
    public function update(Request $request, $id)
    {
        // Only admin can update users
        if (!$request->user() || !$request->user()->isAdmin()) {
            return response()->json(['message' => 'Unauthorized. Admin access required.'], 403);
        }

        $user = User::findOrFail($id);

        $validated = $request->validate([
            'name' => 'sometimes|string|max:255|regex:/^[a-zA-Z\s\-\'\.]+$/',
            'email' => 'sometimes|email|unique:users,email,' . $id,
            'role' => 'sometimes|string|in:' . implode(',', self::ROLE_CHOICES),
            'password' => [
                'sometimes',
                'string',
                'min:12',
                'confirmed',
                'regex:/[a-z]/',
                'regex:/[A-Z]/',
                'regex:/[0-9]/',
            ],
        ], [
            'password.regex' => 'Password must contain at least one uppercase letter, one lowercase letter, and one number.',
            'password.min' => 'Password must be at least 12 characters.',
        ]);

        if ($denied = $this->guardSuperAdmin($request, $user, $validated['role'] ?? null)) {
            return $denied;
        }

        $oldData = [
            'name' => $user->name,
            'email' => $user->email,
            'role' => $user->role,
        ];

        if (isset($validated['password'])) {
            $validated['password'] = Hash::make($validated['password']);
        }

        if (isset($validated['role']) && isset(self::COUNSELLOR_ROLE_TYPES[$validated['role']])) {
            $counsellorType = self::COUNSELLOR_ROLE_TYPES[$validated['role']];
            $practitioner = $user->training_counsellor_id
                ? TrainingCounsellor::find($user->training_counsellor_id)
                : null;

            if ($practitioner) {
                $practitioner->update(['counsellor_type' => $counsellorType]);
            } else {
                $practitioner = $this->findOrCreatePractitioner(
                    $validated['name'] ?? $user->name,
                    strtolower(trim($validated['email'] ?? $user->email)),
                    $counsellorType
                );
            }

            $validated['role'] = 'counsellor';
            $validated['training_counsellor_id'] = $practitioner->id;
        } elseif (isset($validated['role'])) {
            // Moving a counsellor to a staff role removes the practitioner link from the login.
            $validated['training_counsellor_id'] = null;
        }

        $user->update($validated);

        // Log activity
        $changes = array_diff_assoc($validated, $oldData);
        ActivityLog::create([
            'user_id' => $request->user()->id,
            'action' => 'user_updated',
            'model_type' => User::class,
            'model_id' => $user->id,
            'description' => "User {$user->name} ({$user->email}) updated",
            'changes' => $changes,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        return response()->json([
            'message' => 'User updated successfully',
            'user' => $this->presentUser($user->fresh('trainingCounsellor')),
        ]);
    }

    /**
     * Toggle user active/deactivated status (admin only)
     */
    public function toggleActive(Request $request, $id)
    {
        if (!$request->user() || !$request->user()->isAdmin()) {
            return response()->json(['message' => 'Unauthorized. Admin access required.'], 403);
        }

        $user = User::findOrFail($id);

        if ($denied = $this->guardSuperAdmin($request, $user, null)) {
            return $denied;
        }

        if ($user->isActive()) {
            // Attempting to deactivate
            if ($user->id === $request->user()->id) {
                return response()->json([
                    'message' => 'You cannot deactivate your own account.',
                ], 422);
            }

            if ($user->isAdmin()) {
                $activeAdminCount = User::whereIn('role', ['admin', 'super_admin'])
                    ->where('is_active', true)
                    ->whereNull('deactivated_at')
                    ->count();

                if ($activeAdminCount <= 1) {
                    return response()->json([
                        'message' => 'Cannot deactivate the only active administrator account in the system.',
                    ], 422);
                }
            }

            $user->update([
                'is_active' => false,
                'deactivated_at' => now(),
            ]);

            // Immediately revoke all access tokens so deactivated user cannot make API calls
            $user->tokens()->delete();

            ActivityLog::create([
                'user_id' => $request->user()->id,
                'action' => 'user_deactivated',
                'model_type' => User::class,
                'model_id' => $user->id,
                'description' => "User {$user->name} ({$user->email}) deactivated by {$request->user()->name}",
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);

            $message = "User {$user->name} has been deactivated.";
        } else {
            // Reactivate
            $user->update([
                'is_active' => true,
                'deactivated_at' => null,
            ]);

            ActivityLog::create([
                'user_id' => $request->user()->id,
                'action' => 'user_reactivated',
                'model_type' => User::class,
                'model_id' => $user->id,
                'description' => "User {$user->name} ({$user->email}) reactivated by {$request->user()->name}",
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);

            $message = "User {$user->name} has been reactivated.";
        }

        return response()->json([
            'message' => $message,
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->role,
                'is_active' => (bool) $user->is_active,
                'deactivated_at' => $user->deactivated_at,
            ],
        ]);
    }

    /**
     * Remove the specified user (admin only)
     */
    public function destroy(Request $request, $id)
    {
        // Only admin can delete users
        if (!$request->user() || !$request->user()->isAdmin()) {
            return response()->json(['message' => 'Unauthorized. Admin access required.'], 403);
        }

        $user = User::findOrFail($id);

        if ($denied = $this->guardSuperAdmin($request, $user, null)) {
            return $denied;
        }

        // Prevent deleting the only remaining admin account
        if ($user->isAdmin() && User::whereIn('role', ['admin', 'super_admin'])->count() <= 1) {
            return response()->json([
                'message' => 'Cannot delete the only remaining admin account in the system.',
            ], 422);
        }

        $userName = $user->name;
        $userEmail = $user->email;
        $isSelf = ($user->id === $request->user()->id);

        // Revoke tokens if deleting self
        if ($isSelf) {
            $user->tokens()->delete();
        }

        $user->delete();

        // Log activity
        ActivityLog::create([
            'user_id' => $request->user()->id,
            'action' => 'user_deleted',
            'model_type' => User::class,
            'model_id' => $id,
            'description' => "User {$userName} ({$userEmail}) deleted" . ($isSelf ? " (self-deleted)" : ""),
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        return response()->json([
            'message' => 'User deleted successfully',
        ]);
    }

    /**
     * Get user count (admin only)
     */
    public function count(Request $request)
    {
        // Only admin can view user count
        if (!$request->user() || !$request->user()->isAdmin()) {
            return response()->json(['message' => 'Unauthorized. Admin access required.'], 403);
        }

        $count = User::count();

        return response()->json([
            'count' => $count,
            'can_add_more' => true,
        ]);
    }

    /**
     * Get a list of users for dropdown selection (name and id only)
     */
    public function publicList(Request $request)
    {
        if (!$request->user() || !in_array($request->user()->role, ['admin', 'super_admin', 'staff', 'consultation_staff', 'compliance_officer'])) {
            return response()->json(['message' => 'Unauthorized.'], 403);
        }

        $users = User::where('is_active', true)
            ->whereNull('deactivated_at')
            ->select('id', 'name', 'role')
            ->get();

        return response()->json($users);
    }
}
