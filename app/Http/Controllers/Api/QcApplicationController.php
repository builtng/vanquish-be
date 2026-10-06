<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\QcApplication;
use App\Models\TrainingCounsellor;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class QcApplicationController extends Controller
{
    /**
     * List all Qualified Counsellor applications.
     */
    public function index(Request $request)
    {
        $user = $request->user();
        $isStaffOrAdmin = $user && in_array($user->role, ['admin', 'super_admin', 'staff', 'manager']);
        $includeArchived = $isStaffOrAdmin && $request->boolean('include_archived');

        $query = ($includeArchived ? QcApplication::withTrashed() : QcApplication::whereNull('archived_at'))
            ->with(['suggestedTrainingCounsellor', 'trainingCounsellor', 'person']);

        // Filter by status
        if ($request->filled('status') && $request->status !== 'all') {
            $query->where('status', $request->status);
        }

        // Search by name, email, phone
        if ($request->filled('search')) {
            $s = trim($request->search);
            $query->where(function ($q) use ($s) {
                $q->where('name', 'LIKE', "%{$s}%")
                    ->orWhere('email', 'LIKE', "%{$s}%")
                    ->orWhere('phone', 'LIKE', "%{$s}%")
                    ->orWhere('legal_first_name', 'LIKE', "%{$s}%")
                    ->orWhere('legal_last_name', 'LIKE', "%{$s}%");
            });
        }

        $query->orderBy('created_at', 'desc');

        if ($request->has('page')) {
            $perPage = min(50, max(1, (int) $request->input('per_page', 15)));
            return response()->json($query->paginate($perPage));
        }

        return response()->json($query->get());
    }

    /**
     * Show a specific Qualified Counsellor application with suggested match details.
     */
    public function show($id)
    {
        $app = is_numeric($id)
            ? QcApplication::with(['suggestedTrainingCounsellor', 'trainingCounsellor', 'person.trainingCounsellors'])->find($id)
            : null;

        if (!$app) {
            $app = QcApplication::with(['suggestedTrainingCounsellor', 'trainingCounsellor', 'person.trainingCounsellors'])
                ->where('uuid', $id)
                ->firstOrFail();
        }

        // If no suggested TC is stored yet, search for active counsellor matching email
        $suggestedTc = $app->suggestedTrainingCounsellor;
        if (!$suggestedTc && !empty($app->email)) {
            $suggestedTc = TrainingCounsellor::whereNull('archived_at')
                ->where('email', strtolower(trim($app->email)))
                ->first();
        }

        $response = $app->toArray();
        $response['suggested_match'] = $suggestedTc ? [
            'id' => $suggestedTc->id,
            'tc_id' => $suggestedTc->tc_id,
            'name' => $suggestedTc->name,
            'email' => $suggestedTc->email,
            'counsellor_type' => $suggestedTc->counsellor_type,
            'phone' => $suggestedTc->phone,
            'address' => $suggestedTc->address,
            'registered_address' => $suggestedTc->registered_address,
            'modality' => $suggestedTc->modality,
        ] : null;

        return response()->json($response);
    }

    /**
     * Accept a QC application:
     * Creates a new TrainingCounsellor row with QC prefix and counsellor User account.
     * Preserves admin/staff user roles without creating duplicate accounts.
     */
    public function accept(Request $request, $id)
    {
        $qcApp = is_numeric($id)
            ? QcApplication::findOrFail($id)
            : QcApplication::where('uuid', $id)->firstOrFail();

        if ($qcApp->status === 'Accepted' && $qcApp->training_counsellor_id) {
            return response()->json([
                'message' => 'Application has already been accepted.',
                'application' => $qcApp,
                'tc' => $qcApp->trainingCounsellor,
            ], 400);
        }

        // 1. Refuse application if rejected or archived_at set
        if ($qcApp->status === 'Rejected' || !empty($qcApp->archived_at)) {
            return response()->json([
                'message' => 'This application was rejected. Restore it before accepting.',
            ], 422);
        }

        // 2. Check if an active practitioner already has this email or there is a suggested match
        $normalizedEmail = strtolower(trim($qcApp->email ?? ''));
        $existingTc = null;
        if (!empty($normalizedEmail)) {
            $existingTc = TrainingCounsellor::whereNull('archived_at')
                ->where('email', $normalizedEmail)
                ->first();
        }
        if (!$existingTc && $qcApp->suggested_training_counsellor_id) {
            $existingTc = $qcApp->suggestedTrainingCounsellor
                ?? TrainingCounsellor::whereNull('archived_at')->find($qcApp->suggested_training_counsellor_id);
        }

        $forceNew = $request->boolean('force_new');
        if ($existingTc && !$forceNew) {
            return response()->json([
                'message' => "A practitioner with this email already exists ({$existingTc->name}, {$existingTc->tc_id}). Use Link to existing practitioner instead.",
                'existing_tc' => [
                    'id' => $existingTc->id,
                    'name' => $existingTc->name,
                    'tc_id' => $existingTc->tc_id,
                    'email' => $existingTc->email,
                ],
            ], 409);
        }

        // Generate unique QC id
        $maxQc = TrainingCounsellor::withTrashed()
            ->where('tc_id', 'LIKE', 'QC%')
            ->get()
            ->map(function ($c) {
                return (int) substr($c->tc_id, 2);
            })
            ->max();
        $nextQc = max(1, ($maxQc ?? 0) + 1);
        $qcId = 'QC' . str_pad($nextQc, 3, '0', STR_PAD_LEFT);

        $answers = $qcApp->answers ?? [];
        $regAddress = $answers['registered_address'] ?? null;
        $regCity = $answers['registered_city'] ?? null;
        $regPostcode = $answers['registered_postcode'] ?? null;
        $fullAddress = trim(($regAddress ?? '') . ', ' . ($regCity ?? '') . ' ' . ($regPostcode ?? ''));

        $tc = TrainingCounsellor::create([
            'person_id' => $qcApp->person_id,
            'uuid' => (string) Str::uuid(),
            'tc_id' => $qcId,
            'name' => $qcApp->name,
            'legal_first_name' => $qcApp->legal_first_name,
            'legal_last_name' => $qcApp->legal_last_name,
            'email' => $qcApp->email,
            'phone' => $qcApp->phone ?? ($answers['phone'] ?? null),
            'address' => $fullAddress,
            'registered_address' => $regAddress,
            'registered_city' => $regCity,
            'registered_postcode' => $regPostcode,
            'has_supervisor' => $answers['has_supervisor'] ?? null,
            'previous_vanquish_work' => $answers['previous_vanquish_work'] ?? null,
            'areas_to_improve' => $answers['areas_to_improve'] ?? null,
            'unique_trait' => $answers['unique_trait'] ?? null,
            'counsellor_training_details' => $answers['counsellor_training_details'] ?? null,
            'qualified_to_work_with' => $answers['qualified_to_work_with'] ?? [],
            'challenging_cases' => $answers['challenging_cases'] ?? null,
            'qualification_document' => $qcApp->qualification_document,
            'dbs_certificate_qualified' => $qcApp->dbs_certificate_qualified,
            'insurance_qualified' => $qcApp->insurance_qualified,
            'self_employment_proof' => $qcApp->self_employment_proof,
            'professional_membership' => $qcApp->professional_membership,
            'signature' => $qcApp->signature,
            'signature_date' => $qcApp->signature_date,
            'gender' => $answers['gender'] ?? null,
            'ethnicity' => $answers['ethnicity'] ?? null,
            'sexual_orientation' => $answers['sexual_orientation'] ?? null,
            'date_of_birth' => $answers['date_of_birth'] ?? null,
            'modality' => is_array($answers['modalities'] ?? null) ? implode(', ', $answers['modalities']) : ($answers['modalities'] ?? null),
            'topics_with_experience' => $answers['experience_areas'] ?? [],
            'availability' => $answers['availability'] ?? null,
            'qualified_form_completed' => true,
            'counsellor_type' => 'Qualified',
            'status' => 'Active',
            'joined_date' => now(),
            'last_activity' => now(),
        ]);

        // Manage User account
        $user = User::where('email', $qcApp->email)->first();
        $userMessage = null;

        if ($user) {
            if (in_array($user->role, ['admin', 'staff', 'super_admin'])) {
                // Rule 3: Do not change the role or create a second account
                $userMessage = "Existing user account with role '{$user->role}' preserved. No new account created.";
            } else {
                $user->update([
                    'name' => $tc->name,
                    'role' => 'counsellor',
                    'training_counsellor_id' => $tc->id,
                ]);
                $userMessage = "Linked to existing counsellor user account.";
            }
        } else {
            $temporaryPassword = Str::random(10) . '!1Aa';
            User::create([
                'name' => $tc->name,
                'email' => $tc->email,
                'password' => Hash::make($temporaryPassword),
                'role' => 'counsellor',
                'training_counsellor_id' => $tc->id,
            ]);
            $userMessage = "New counsellor portal account created.";
        }

        $qcApp->update([
            'status' => 'Accepted',
            'training_counsellor_id' => $tc->id,
        ]);

        ActivityLog::create([
            'user_id' => $request->user()->id ?? null,
            'action' => 'qc_application_accepted',
            'model_type' => QcApplication::class,
            'model_id' => $qcApp->id,
            'description' => "Accepted QC application #{$qcApp->id} for {$qcApp->name}. Created practitioner {$tc->name} ({$tc->tc_id}). {$userMessage}",
            'ip_address' => $request->ip(),
        ]);

        if ($existingTc && $forceNew) {
            ActivityLog::create([
                'user_id' => $request->user()->id ?? null,
                'action' => 'qc_application_accept_forced_new',
                'model_type' => QcApplication::class,
                'model_id' => $qcApp->id,
                'description' => "Admin override (force_new=true): created separate practitioner {$tc->name} ({$tc->tc_id}) for {$qcApp->name} despite existing match ({$existingTc->name}, {$existingTc->tc_id})",
                'ip_address' => $request->ip(),
            ]);
        }

        return response()->json([
            'message' => 'Qualified Counsellor application accepted successfully. ' . $userMessage,
            'tc' => $tc,
            'application' => $qcApp->fresh(),
            'user_note' => $userMessage,
        ]);
    }

    /**
     * Link application to an existing practitioner:
     * Does NOT automatically change counsellor_type, tc_id, or profile fields.
     * Admin chooses which specific fields (if any) to copy.
     */
    public function link(Request $request, $id)
    {
        $qcApp = is_numeric($id)
            ? QcApplication::findOrFail($id)
            : QcApplication::where('uuid', $id)->firstOrFail();

        $validated = $request->validate([
            'training_counsellor_id' => 'required',
            'copy_fields' => 'nullable|array',
            'copy_fields.*' => 'string',
        ]);

        $tcIdParam = $validated['training_counsellor_id'];
        $tc = is_numeric($tcIdParam)
            ? TrainingCounsellor::find($tcIdParam)
            : null;

        if (!$tc) {
            $tc = TrainingCounsellor::where('tc_id', $tcIdParam)
                ->orWhere('uuid', $tcIdParam)
                ->orWhere('id', $tcIdParam)
                ->firstOrFail();
        }

        $answers = $qcApp->answers ?? [];

        // Field map for selective copying
        $availableFields = [
            'name' => $qcApp->name,
            'legal_first_name' => $qcApp->legal_first_name,
            'legal_last_name' => $qcApp->legal_last_name,
            'phone' => $qcApp->phone ?? ($answers['phone'] ?? null),
            'registered_address' => $answers['registered_address'] ?? null,
            'registered_city' => $answers['registered_city'] ?? null,
            'registered_postcode' => $answers['registered_postcode'] ?? null,
            'has_supervisor' => $answers['has_supervisor'] ?? null,
            'qualification_document' => $qcApp->qualification_document,
            'dbs_certificate_qualified' => $qcApp->dbs_certificate_qualified,
            'insurance_qualified' => $qcApp->insurance_qualified,
            'self_employment_proof' => $qcApp->self_employment_proof,
            'professional_membership' => $qcApp->professional_membership,
            'valid_id_document' => $qcApp->valid_id_document,
            'gender' => $answers['gender'] ?? null,
            'ethnicity' => $answers['ethnicity'] ?? null,
            'sexual_orientation' => $answers['sexual_orientation'] ?? null,
            'date_of_birth' => $answers['date_of_birth'] ?? null,
            'modality' => is_array($answers['modalities'] ?? null) ? implode(', ', $answers['modalities']) : ($answers['modalities'] ?? null),
            'topics_with_experience' => $answers['experience_areas'] ?? null,
            'availability' => $answers['availability'] ?? null,
            'previous_vanquish_work' => $answers['previous_vanquish_work'] ?? null,
            'areas_to_improve' => $answers['areas_to_improve'] ?? null,
            'unique_trait' => $answers['unique_trait'] ?? null,
            'counsellor_training_details' => $answers['counsellor_training_details'] ?? null,
            'qualified_to_work_with' => $answers['qualified_to_work_with'] ?? null,
            'challenging_cases' => $answers['challenging_cases'] ?? null,
        ];

        $requestedCopies = $validated['copy_fields'] ?? [];
        $updates = [];
        foreach ($requestedCopies as $fieldName) {
            if (array_key_exists($fieldName, $availableFields) && $availableFields[$fieldName] !== null) {
                $updates[$fieldName] = $availableFields[$fieldName];
            }
        }

        if (!empty($updates)) {
            $tc->update($updates);
        }

        $qcApp->update([
            'training_counsellor_id' => $tc->id,
            'status' => 'Linked',
        ]);

        $copiedSummary = !empty($updates) ? implode(', ', array_keys($updates)) : 'None';

        ActivityLog::create([
            'user_id' => $request->user()->id ?? null,
            'action' => 'qc_application_linked',
            'model_type' => QcApplication::class,
            'model_id' => $qcApp->id,
            'description' => "Linked QC application #{$qcApp->id} to practitioner {$tc->name} ({$tc->tc_id}). Copied fields: {$copiedSummary}",
            'ip_address' => $request->ip(),
        ]);

        return response()->json([
            'message' => "Application successfully linked to {$tc->name} ({$tc->tc_id}).",
            'tc' => $tc->fresh(),
            'application' => $qcApp->fresh(),
            'copied_fields' => array_keys($updates),
        ]);
    }

    /**
     * Reject a QC application:
     * Sets status = 'Rejected' and archived_at = now().
     * Nothing is deleted.
     */
    public function reject(Request $request, $id)
    {
        $qcApp = is_numeric($id)
            ? QcApplication::findOrFail($id)
            : QcApplication::where('uuid', $id)->firstOrFail();

        $qcApp->update([
            'status' => 'Rejected',
            'archived_at' => now(),
            'notes' => $request->input('notes') ?? $request->input('reason') ?? $qcApp->notes,
        ]);

        ActivityLog::create([
            'user_id' => $request->user()->id ?? null,
            'action' => 'qc_application_rejected',
            'model_type' => QcApplication::class,
            'model_id' => $qcApp->id,
            'description' => "Rejected QC application #{$qcApp->id} for {$qcApp->name}",
            'ip_address' => $request->ip(),
        ]);

        return response()->json([
            'message' => 'Qualified Counsellor application rejected and archived.',
            'application' => $qcApp->fresh(),
        ]);
    }

    /**
     * Restore a rejected / archived QC application
     */
    public function restore(Request $request, $id)
    {
        $user = $request->user();
        if ($user && !in_array($user->role, ['admin', 'super_admin'])) {
            return response()->json(['message' => 'Unauthorized. Admin access required.'], 403);
        }

        $qcApp = is_numeric($id)
            ? QcApplication::withTrashed()->findOrFail($id)
            : QcApplication::withTrashed()->where('uuid', $id)->firstOrFail();

        $qcApp->update([
            'status' => 'Submitted',
            'archived_at' => null,
        ]);
        $qcApp->restore();

        ActivityLog::create([
            'user_id' => $user->id ?? null,
            'action' => 'qc_application_restored',
            'model_type' => QcApplication::class,
            'model_id' => $qcApp->id,
            'description' => "Restored QC application #{$qcApp->id} for {$qcApp->name}",
            'ip_address' => $request->ip(),
        ]);

        return response()->json([
            'message' => 'Application restored successfully.',
            'application' => $qcApp->fresh(),
        ]);
    }

    /**
     * Archive a QC application (never hard delete)
     */
    public function destroy(Request $request, $id)
    {
        $qcApp = is_numeric($id)
            ? QcApplication::findOrFail($id)
            : QcApplication::where('uuid', $id)->firstOrFail();

        $qcApp->archived_at = now();
        $qcApp->status = 'Archived';
        $qcApp->save();
        $qcApp->delete();

        ActivityLog::create([
            'user_id' => $request->user()->id ?? null,
            'action' => 'qc_application_archived',
            'model_type' => QcApplication::class,
            'model_id' => $qcApp->id,
            'description' => "Qualified counsellor application #{$qcApp->id} archived for {$qcApp->name}",
            'ip_address' => $request->ip(),
        ]);

        return response()->json(['message' => 'Application archived successfully']);
    }
}
