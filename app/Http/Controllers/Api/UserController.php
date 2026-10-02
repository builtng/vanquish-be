<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\ActivityLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class UserController extends Controller
{
    /**
     * Display a listing of users (admin only)
     */
    public function index(Request $request)
    {
        // Only admin can view users
        if (!$request->user() || $request->user()->role !== 'admin') {
            return response()->json(['message' => 'Unauthorized. Admin access required.'], 403);
        }

        $users = User::select('id', 'name', 'email', 'role', 'is_active', 'deactivated_at', 'two_factor_enabled', 'created_at', 'updated_at')
            ->orderBy('id', 'desc')
            ->get();

        return response()->json($users);
    }

    /**
     * Store a newly created user (admin only)
     */
    public function store(Request $request)
    {
        // Only admin can create users
        if (!$request->user() || $request->user()->role !== 'admin') {
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
            'role' => 'required|string|in:admin,staff',
        ], [
            'password.regex' => 'Password must contain at least one uppercase letter, one lowercase letter, and one number.',
            'password.min' => 'Password must be at least 12 characters.',
        ]);

        $user = User::create([
            'name' => $validated['name'],
            'email' => strtolower(trim($validated['email'])),
            'password' => Hash::make($validated['password']),
            'role' => $validated['role'],
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
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->role,
                'is_active' => (bool) $user->is_active,
            ],
        ], 201);
    }

    /**
     * Display the specified user (admin only)
     */
    public function show(Request $request, $id)
    {
        // Only admin can view users
        if (!$request->user() || $request->user()->role !== 'admin') {
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
        if (!$request->user() || $request->user()->role !== 'admin') {
            return response()->json(['message' => 'Unauthorized. Admin access required.'], 403);
        }

        $user = User::findOrFail($id);

        $validated = $request->validate([
            'name' => 'sometimes|string|max:255|regex:/^[a-zA-Z\s\-\'\.]+$/',
            'email' => 'sometimes|email|unique:users,email,' . $id,
            'role' => 'sometimes|string|in:admin,staff',
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

        $oldData = [
            'name' => $user->name,
            'email' => $user->email,
            'role' => $user->role,
        ];

        if (isset($validated['password'])) {
            $validated['password'] = Hash::make($validated['password']);
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
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->role,
                'is_active' => (bool) $user->is_active,
            ],
        ]);
    }

    /**
     * Toggle user active/deactivated status (admin only)
     */
    public function toggleActive(Request $request, $id)
    {
        if (!$request->user() || $request->user()->role !== 'admin') {
            return response()->json(['message' => 'Unauthorized. Admin access required.'], 403);
        }

        $user = User::findOrFail($id);

        if ($user->isActive()) {
            // Attempting to deactivate
            if ($user->id === $request->user()->id) {
                return response()->json([
                    'message' => 'You cannot deactivate your own account.',
                ], 422);
            }

            if ($user->role === 'admin') {
                $activeAdminCount = User::where('role', 'admin')
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
        if (!$request->user() || $request->user()->role !== 'admin') {
            return response()->json(['message' => 'Unauthorized. Admin access required.'], 403);
        }

        $user = User::findOrFail($id);

        // Prevent deleting the only remaining admin account
        if ($user->role === 'admin' && User::where('role', 'admin')->count() <= 1) {
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
        if (!$request->user() || $request->user()->role !== 'admin') {
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
