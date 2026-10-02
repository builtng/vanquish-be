<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\TrainingCounsellor;
use App\Models\ActivityLog;
use App\Mail\DynamicEmail;
use App\Models\User;
use App\Models\Person;
use App\Models\QcApplication;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Barryvdh\DomPDF\Facade\Pdf;
use App\Services\CompanySettingsService;

class TrainingCounsellorController extends Controller
{
    public function index(Request $request)
    {
        $query = TrainingCounsellor::with(['clients', 'intakeForm'])->orderBy('id', 'desc');

        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            });
        }

        if ($request->has('status') && $request->status !== 'all') {
            $query->where('status', $request->status);
        }

        if ($request->has('modality') && $request->modality !== 'all') {
            $query->where('modality', $request->modality);
        }

        $tcs = $query->get();

        // Transform to include intake form data in a more accessible format
        $tcs->transform(function ($tc) {
            $intakeForm = $tc->intakeForm->first();
            if ($intakeForm) {
                // Try to extract training provider details from additional_info JSON
                $trainingProviderDetails = [];
                if ($intakeForm->additional_info) {
                    try {
                        $decoded = json_decode($intakeForm->additional_info, true);
                        if (is_array($decoded)) {
                            $trainingProviderDetails = $decoded;
                        }
                    } catch (\Exception $e) {
                        // If not JSON, ignore
                    }
                }

                // Add intake form fields to TC object for easier access
                // Add intake form fields to TC object for easier access (prioritize DB columns if set)
                $tc->training_org_name = $tc->institution ?? $trainingProviderDetails['training_org_name'] ?? $intakeForm->institution ?? null;
                $tc->training_org_address = $tc->training_org_address ?? $trainingProviderDetails['training_org_address'] ?? null;
                $tc->course_title = $tc->course ?? $trainingProviderDetails['course_title'] ?? $intakeForm->course ?? null;
                $tc->tutor_name = $tc->tutor_name ?? $trainingProviderDetails['tutor_name'] ?? null;
                $tc->tutor_email = $tc->tutor_email ?? $trainingProviderDetails['tutor_email'] ?? null;
                $tc->tutor_phone = $tc->tutor_phone ?? $trainingProviderDetails['tutor_phone'] ?? null;
                $tc->placement_lead_name = $tc->placement_lead_name ?? $trainingProviderDetails['placement_lead_name'] ?? null;
                $tc->placement_lead_email = $tc->placement_lead_email ?? $trainingProviderDetails['placement_lead_email'] ?? null;
                $tc->placement_lead_phone = $tc->placement_lead_phone ?? $trainingProviderDetails['placement_lead_phone'] ?? null;
            } else {
                // Fallback to TC fields if no intake form
                $tc->training_org_name = $tc->institution ?? null;
                $tc->training_org_address = $tc->training_org_address ?? null;
                $tc->course_title = $tc->course ?? null;
                // These are now on the model, so we don't need to manually set them to null if they are already null on the model, 
                // but we should ensure they are accessible via these keys if the frontend expects them.
                // Since they are columns, they are already on $tc. But let's be explicit if needed or just leave them.
                // The frontend might expect snake_case properties that match the column names, which match what we added.
                // But let's keep the assignments to be safe and consistent with the transformation above.
                // Actually, since they are columns, $tc->tutor_name ALREADY accesses the column.
                // The code below was forcing them to null when no intake form existed. 
                // Now that we have columns, we should RESPECT the columns.
                // So we don't need to do anything here really, except maybe map institution -> training_org_name alias if needed.
                $tc->training_org_name = $tc->institution ?? null; // Alias
                $tc->course_title = $tc->course ?? null; // Alias
            }
            return $tc;
        });

        return response()->json($tcs);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => [
                'required',
                'email',
                Rule::unique('training_counsellors', 'email')->whereNull('deleted_at')->whereNull('archived_at'),
            ],
            'phone' => 'nullable|string',
            'gender' => 'nullable|string|max:50',
            'ethnicity' => 'nullable|string|max:100',
            'sexual_orientation' => 'nullable|string|max:100',
            'age' => 'nullable|integer|min:1|max:120',
            'date_of_birth' => 'nullable|string|max:50',
            'address' => 'nullable|string',
            'modality' => 'nullable|string|max:255',
            'status' => 'nullable|in:Active,At Capacity,On Leave,Inactive',
            'counsellor_type' => 'nullable|in:Trainee,Qualified',
            'session_price' => 'nullable|numeric|min:0',
            'bio' => 'nullable|string|max:2000',
            'offers_mid_range' => 'nullable|boolean',
            'offers_coaching' => 'nullable|boolean',
            'availability' => 'nullable|array',
            'topics_with_experience' => 'nullable|array',
            'topics_not_ready_for' => 'nullable|array',
            'course' => 'nullable|string',
            'institution' => 'nullable|string',
            'training_org_address' => 'nullable|string',
            'tutor_name' => 'nullable|string',
            'tutor_email' => 'nullable|string|email',
            'tutor_phone' => 'nullable|string',
            'placement_lead_name' => 'nullable|string',
            'placement_lead_email' => 'nullable|string|email',
            'placement_lead_phone' => 'nullable|string',
            // Qualified Counsellor fields
            'legal_first_name' => 'nullable|string|max:255',
            'legal_last_name' => 'nullable|string|max:255',
            'registered_address' => 'nullable|string',
            'registered_city' => 'nullable|string|max:255',
            'registered_postcode' => 'nullable|string|max:50',
            'has_supervisor' => 'nullable|string|max:50',
            'previous_vanquish_work' => 'nullable|string',
            'areas_to_improve' => 'nullable|string',
            'unique_trait' => 'nullable|string',
            'counsellor_training_details' => 'nullable|string',
            'qualified_to_work_with' => 'nullable|array',
            'challenging_cases' => 'nullable|string',
            // Onboarding documents - lets an admin upload these on the TC's behalf
            // instead of the TC submitting them via the qualified-counsellor self-service form.
            'qualification_document_file' => 'nullable|file|mimes:pdf,doc,docx,jpg,jpeg,png|max:10240',
            'dbs_certificate_file' => 'nullable|file|mimes:pdf,doc,docx,jpg,jpeg,png|max:10240',
            'insurance_file' => 'nullable|file|mimes:pdf,doc,docx,jpg,jpeg,png|max:10240',
            'self_employment_proof_file' => 'nullable|file|mimes:pdf,doc,docx,jpg,jpeg,png|max:10240',
            'professional_membership_file' => 'nullable|file|mimes:pdf,doc,docx,jpg,jpeg,png|max:10240',
        ]);

        $documentFields = [
            'qualification_document_file' => 'qualification_document',
            'dbs_certificate_file' => 'dbs_certificate_qualified',
            'insurance_file' => 'insurance_qualified',
            'self_employment_proof_file' => 'self_employment_proof',
            'professional_membership_file' => 'professional_membership',
        ];
        foreach (array_keys($documentFields) as $fileField) {
            unset($validated[$fileField]);
        }

        // Generate safe unique ID based on counsellor_type: QC for Qualified Counsellor, TC for Trainee Counsellor
        $isQualified = ($validated['counsellor_type'] ?? 'Trainee') === 'Qualified';
        $prefix = $isQualified ? 'QC' : 'TC';

        $maxNum = TrainingCounsellor::withTrashed()
            ->where('tc_id', 'LIKE', "{$prefix}%")
            ->get()
            ->map(function ($counsellor) use ($prefix) {
                return (int) substr($counsellor->tc_id, strlen($prefix));
            })
            ->max();

        $nextNumber = max(1, ($maxNum ?? 0) + 1);
        $validated['tc_id'] = $prefix . str_pad($nextNumber, 3, '0', STR_PAD_LEFT);
        $validated['joined_date'] = now();
        $validated['last_activity'] = now();

        $person = Person::findOrCreateByEmail($validated['email'], $validated['name'], $validated['phone'] ?? null);
        $validated['person_id'] = $person->id;
        $validated['email'] = strtolower(trim($validated['email']));

        $tc = TrainingCounsellor::create($validated);

        // Store any onboarding documents the admin is uploading on the counsellor's behalf
        $documentUpdates = [];
        foreach ($documentFields as $fileField => $column) {
            if ($request->hasFile($fileField)) {
                $file = $request->file($fileField);
                $sanitizedName = preg_replace('/[^a-zA-Z0-9._-]/', '_', $file->getClientOriginalName());
                $filename = time() . '_' . uniqid() . '_' . substr($sanitizedName, 0, 255);
                $documentUpdates[$column] = $file->storeAs("qualified_counsellors/{$tc->id}/{$column}", $filename, 'public');
            }
        }
        if (!empty($documentUpdates)) {
            $tc->update($documentUpdates);
        }

        $typeLabel = $isQualified ? 'Qualified Counsellor' : 'Trainee Counsellor';

        if (!$isQualified) {
            // Send welcome email to new trainee counsellor
            app(\App\Services\EmailService::class)->sendAndLog(
                $tc->email,
                'tc_welcome',
                [
                    'tc_name' => $tc->name,
                    'tc_id' => $tc->tc_id,
                    'email' => $tc->email,
                    'modality' => $tc->modality ?? 'Not specified'
                ]
            );
        }

        ActivityLog::create([
            'user_id' => $request->user()->id,
            'action' => $isQualified ? 'qc_created' : 'tc_created',
            'model_type' => TrainingCounsellor::class,
            'model_id' => $tc->id,
            'description' => "{$typeLabel} {$tc->name} created ({$tc->tc_id})",
            'ip_address' => $request->ip(),
        ]);

        return response()->json($tc, 201);
    }

    public function show($id)
    {
        // Find by numeric ID, UUID, or tc_id
        $tc = is_numeric($id)
            ? TrainingCounsellor::with(['clients', 'consultations', 'matches.client', 'intakeForm'])->find($id)
            : null;

        if (!$tc) {
            $tc = TrainingCounsellor::where('uuid', $id)
                ->orWhere('tc_id', $id)
                ->orWhere('id', $id)
                ->with(['clients', 'consultations', 'matches.client', 'intakeForm'])
                ->firstOrFail();
        }

        if ($tc->intakeForm && $tc->intakeForm->count() > 0) {
            $intakeForm = $tc->intakeForm->first();
            $tc->gender = $tc->gender ?: $intakeForm->gender;
            $tc->ethnicity = $tc->ethnicity ?: $intakeForm->ethnicity;
            $tc->sexual_orientation = $tc->sexual_orientation ?: $intakeForm->sexual_orientation;
            $tc->date_of_birth = $tc->date_of_birth ?: $intakeForm->date_of_birth;
            $tc->address = $tc->address ?: $intakeForm->address;
            $tc->modality = $tc->modality ?: $intakeForm->modality;
            if (empty($tc->topics_with_experience) && !empty($intakeForm->topics_with_experience)) {
                $tc->topics_with_experience = $intakeForm->topics_with_experience;
            }
            if (empty($tc->topics_not_ready_for) && !empty($intakeForm->topics_not_ready_for)) {
                $tc->topics_not_ready_for = $intakeForm->topics_not_ready_for;
            }
            if (empty($tc->availability) && !empty($intakeForm->availability)) {
                $tc->availability = $intakeForm->availability;
            }
        }

        // For Qualified Counsellors (QC), fallback address from registered address
        if ($tc->counsellor_type === 'Qualified') {
            if (empty($tc->address) && !empty($tc->registered_address)) {
                $tc->address = trim("{$tc->registered_address}, {$tc->registered_city} {$tc->registered_postcode}");
            }
        }

        // Load complete submission history for this person (QC applications, Trainee applications, and other counsellor records)
        $normalizedEmail = strtolower(trim($tc->email ?? ''));
        $historyScope = function ($q) use ($tc, $normalizedEmail) {
            if ($tc->person_id) {
                $q->where('person_id', $tc->person_id);
            }
            if ($normalizedEmail) {
                $q->orWhere('email', $normalizedEmail);
            }
        };

        $qcApps = QcApplication::where($historyScope)->orderBy('created_at', 'desc')->get();
        $traineeApps = \App\Models\TraineeApplication::withTrashed()->where($historyScope)->orderBy('created_at', 'desc')->get();
        $relatedTcs = TrainingCounsellor::withTrashed()
            ->where('id', '!=', $tc->id)
            ->where($historyScope)
            ->orderBy('created_at', 'desc')
            ->get();

        $tc->setAttribute('submission_history', [
            'qc_applications' => $qcApps,
            'trainee_applications' => $traineeApps,
            'counsellor_profiles' => $relatedTcs,
        ]);

        return response()->json($tc);
    }

    public function update(Request $request, $id)
    {
        // Find by UUID, tc_id, or numeric ID
        $tc = is_numeric($id)
            ? TrainingCounsellor::find($id)
            : null;

        if (!$tc) {
            $tc = TrainingCounsellor::where('uuid', $id)
                ->orWhere('tc_id', $id)
                ->orWhere('id', $id)
                ->firstOrFail();
        }

        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'email' => 'sometimes|email|unique:training_counsellors,email,' . $tc->id,
            'phone' => 'nullable|string',
            'send_portal_invite' => 'nullable|boolean',
            'gender' => 'nullable|string|max:50',
            'ethnicity' => 'nullable|string|max:100',
            'sexual_orientation' => 'nullable|string|max:100',
            'age' => 'nullable|integer|min:1|max:120',
            'date_of_birth' => 'nullable|string|max:50',
            'address' => 'nullable|string',
            'modality' => 'nullable|string|max:255',
            'session_price' => 'nullable|numeric|min:0',
            'bio' => 'nullable|string|max:2000',
            'offers_mid_range' => 'nullable|boolean',
            'offers_coaching' => 'nullable|boolean',
            'status' => 'sometimes|in:Active,At Capacity,On Leave,Away,Inactive',
            'counsellor_type' => 'sometimes|in:Trainee,Qualified',
            'availability' => 'nullable|array',
            'consultation_availability' => 'nullable|array',
            'topics_with_experience' => 'nullable|array',
            'topics_not_ready_for' => 'nullable|array',
            'course' => 'nullable|string',
            'institution' => 'nullable|string',
            'training_org_address' => 'nullable|string',
            'tutor_name' => 'nullable|string',
            'tutor_email' => 'nullable|string|email',
            'tutor_phone' => 'nullable|string',
            'placement_lead_name' => 'nullable|string',
            'placement_lead_email' => 'nullable|string|email',
            'placement_lead_phone' => 'nullable|string',
            // Qualified Counsellor fields
            'legal_first_name' => 'nullable|string|max:255',
            'legal_last_name' => 'nullable|string|max:255',
            'registered_address' => 'nullable|string',
            'registered_city' => 'nullable|string|max:255',
            'registered_postcode' => 'nullable|string|max:50',
            'has_supervisor' => 'nullable|string|max:50',
            'previous_vanquish_work' => 'nullable|string',
            'areas_to_improve' => 'nullable|string',
            'unique_trait' => 'nullable|string',
            'counsellor_training_details' => 'nullable|string',
            'qualified_to_work_with' => 'nullable|array',
            'challenging_cases' => 'nullable|string',
        ]);

        // Check if transitioning from Trainee to Qualified
        $wasTrainee = $tc->counsellor_type === 'Trainee';
        $becomingQualified = isset($validated['counsellor_type']) && $validated['counsellor_type'] === 'Qualified';

        // When transitioning to Qualified, assign QC ID prefix if still TC
        if ($wasTrainee && $becomingQualified && str_starts_with($tc->tc_id ?? '', 'TC')) {
            $maxQc = TrainingCounsellor::withTrashed()
                ->where('tc_id', 'LIKE', 'QC%')
                ->get()
                ->map(function ($c) {
                    return (int) substr($c->tc_id, 2);
                })
                ->max();
            $nextQc = max(1, ($maxQc ?? 0) + 1);
            $validated['tc_id'] = 'QC' . str_pad($nextQc, 3, '0', STR_PAD_LEFT);
        }

        $sendInvite = !empty($validated['send_portal_invite']);
        unset($validated['send_portal_invite']);

        $tc->update($validated);
        $tc->update(['last_activity' => now()]);

        if ($sendInvite) {
            try {
                $this->sendPortalInvite($request, $tc->uuid ?: $tc->id);
            } catch (\Exception $e) {
                \Illuminate\Support\Facades\Log::warning("Could not send portal invite during TC update: " . $e->getMessage());
            }
        }

        $typeLabel = $tc->counsellor_type === 'Qualified' ? 'Qualified Counsellor' : 'Trainee Counsellor';
        $description = "{$typeLabel} {$tc->name} updated";
        if ($wasTrainee && $becomingQualified) {
            $description = "Counsellor {$tc->name} transitioned to Qualified Counsellor ({$tc->tc_id})";
        }

        ActivityLog::create([
            'user_id' => $request->user()->id,
            'action' => $tc->counsellor_type === 'Qualified' ? 'qc_updated' : 'tc_updated',
            'model_type' => TrainingCounsellor::class,
            'model_id' => $tc->id,
            'description' => $description,
            'ip_address' => $request->ip(),
        ]);

        return response()->json($tc);
    }

    public function destroy($id)
    {
        // Find by UUID, tc_id, or numeric ID
        $tc = is_numeric($id)
            ? TrainingCounsellor::find($id)
            : null;

        if (!$tc) {
            $tc = TrainingCounsellor::where('uuid', $id)
                ->orWhere('tc_id', $id)
                ->orWhere('id', $id)
                ->firstOrFail();
        }

        $typeLabel = $tc->counsellor_type === 'Qualified' ? 'Qualified Counsellor' : 'Trainee Counsellor';
        
        $tc->archived_at = now();
        $tc->save();

        // Unlink user association so login/email conflicts don't block fresh accounts
        User::where('training_counsellor_id', $tc->id)->update([
            'training_counsellor_id' => null,
        ]);

        $tc->delete();

        ActivityLog::create([
            'user_id' => request()->user()->id ?? null,
            'action' => 'counsellor_archived',
            'model_type' => TrainingCounsellor::class,
            'model_id' => $tc->id,
            'description' => "{$typeLabel} {$tc->name} ({$tc->tc_id}) archived",
            'ip_address' => request()->ip(),
        ]);

        return response()->json(['message' => "{$typeLabel} archived successfully"]);
    }

    /**
     * Get counsellor's own data (for counsellor portal)
     */
    public function getOwnData(Request $request)
    {
        $user = $request->user();

        if (!$user || $user->role !== 'counsellor' || !$user->training_counsellor_id) {
            return response()->json(['message' => 'Unauthorized. Counsellor access required.'], 403);
        }

        $tc = TrainingCounsellor::with(['clients', 'consultations.client', 'attendanceGroup'])
            ->findOrFail($user->training_counsellor_id);

        // Get upcoming consultations/sessions
        $upcomingConsultations = $tc->consultations()
            ->where('status', 'scheduled')
            ->where('scheduled_at', '>=', now())
            ->with('client')
            ->orderBy('scheduled_at')
            ->get();

        return response()->json([
            'tc'                     => $tc,
            'clients'                => $tc->clients,
            'upcoming_consultations' => $upcomingConsultations,
            'total_clients'          => $tc->clients()->count(),
            'attendance_group'       => $tc->attendanceGroup,
        ]);
    }

    /**
     * Self-service toggle (Qualified counsellors only) for whether the
     * client-facing Mid Range / Coaching & Counselling consultation booking
     * step shows this counsellor's own consultation availability and/or
     * falls back to Vanquish Therapies' generic consultation slots. The
     * underlying `consultation_availability` time values themselves remain
     * admin-only (edited via the staff TC edit screen), unaffected here.
     */
    public function updateConsultationDelegationPreferences(Request $request)
    {
        $user = $request->user();

        if (!$user || $user->role !== 'counsellor' || !$user->training_counsellor_id) {
            return response()->json(['message' => 'Unauthorized. Counsellor access required.'], 403);
        }

        $tc = TrainingCounsellor::findOrFail($user->training_counsellor_id);

        if ($tc->counsellor_type !== 'Qualified') {
            return response()->json(['message' => 'Only available to Qualified counsellors.'], 403);
        }

        $validated = $request->validate([
            'show_own_consultation_availability' => 'required|boolean',
            'show_vanquish_consultation_availability' => 'required|boolean',
        ]);

        $tc->update($validated);

        return response()->json(['tc' => $tc->fresh()]);
    }

    /**
     * Return a counsellor's clients and their upcoming session dates,
     * for admin review (e.g. before approving a time-off request).
     */
    public function caseload(Request $request, $id)
    {
        $tc = is_numeric($id)
            ? TrainingCounsellor::find($id)
            : null;

        if (!$tc) {
            $tc = TrainingCounsellor::where('uuid', $id)
                ->orWhere('tc_id', $id)
                ->orWhere('id', $id)
                ->firstOrFail();
        }

        $upcomingConsultations = $tc->consultations()
            ->where('status', 'scheduled')
            ->where('scheduled_at', '>=', now())
            ->with('client')
            ->orderBy('scheduled_at')
            ->get();

        return response()->json([
            'clients' => $tc->clients,
            'upcoming_consultations' => $upcomingConsultations,
        ]);
    }

    public function details(Request $request, $id)
    {
        // Find by numeric ID, UUID, or tc_id (supports both TC and QC)
        $tc = is_numeric($id)
            ? TrainingCounsellor::with([
                'clients',
                'consultations.client',
                'matches.client',
                'intakeForm'
            ])->find($id)
            : null;

        if (!$tc) {
            $tc = TrainingCounsellor::where('uuid', $id)
                ->orWhere('tc_id', $id)
                ->orWhere('id', $id)
                ->with([
                    'clients',
                    'consultations.client',
                    'matches.client',
                    'intakeForm'
                ])
                ->firstOrFail();
        }

        // Extract training provider and demographic details from intake form
        if ($tc->intakeForm && $tc->intakeForm->count() > 0) {
            $intakeForm = $tc->intakeForm->first();

            // Try to extract training provider details from additional_info JSON
            $trainingProviderDetails = [];
            if ($intakeForm->additional_info) {
                try {
                    $decoded = json_decode($intakeForm->additional_info, true);
                    if (is_array($decoded)) {
                        $trainingProviderDetails = $decoded;
                    }
                } catch (\Exception $e) {
                    // If not JSON, ignore
                }
            }

            // Fallback demographic fields from intake form
            $tc->gender = $tc->gender ?: ($intakeForm->gender ?? $trainingProviderDetails['gender'] ?? null);
            $tc->ethnicity = $tc->ethnicity ?: ($intakeForm->ethnicity ?? $trainingProviderDetails['ethnicity'] ?? null);
            $tc->sexual_orientation = $tc->sexual_orientation ?: ($intakeForm->sexual_orientation ?? $trainingProviderDetails['sexual_orientation'] ?? null);
            $tc->date_of_birth = $tc->date_of_birth ?: ($intakeForm->date_of_birth ?? $trainingProviderDetails['date_of_birth'] ?? null);
            $tc->address = $tc->address ?: ($intakeForm->address ?? $trainingProviderDetails['address'] ?? null);
            $tc->modality = $tc->modality ?: ($intakeForm->modality ?? null);
            
            if (empty($tc->topics_with_experience) && !empty($intakeForm->topics_with_experience)) {
                $tc->topics_with_experience = $intakeForm->topics_with_experience;
            }
            if (empty($tc->topics_not_ready_for) && !empty($intakeForm->topics_not_ready_for)) {
                $tc->topics_not_ready_for = $intakeForm->topics_not_ready_for;
            }
            if (empty($tc->availability) && !empty($intakeForm->availability)) {
                $tc->availability = $intakeForm->availability;
            }

            // Add intake form fields to TC object (prioritize DB columns if set)
            $tc->training_org_name = $tc->institution ?? $trainingProviderDetails['training_org_name'] ?? $intakeForm->institution ?? $tc->institution ?? null;
            $tc->training_org_address = $tc->training_org_address ?? $trainingProviderDetails['training_org_address'] ?? null;
            $tc->course_title = $tc->course ?? $trainingProviderDetails['course_title'] ?? $intakeForm->course ?? $tc->course ?? null;
            $tc->tutor_name = $tc->tutor_name ?? $trainingProviderDetails['tutor_name'] ?? null;
            $tc->tutor_email = $tc->tutor_email ?? $trainingProviderDetails['tutor_email'] ?? null;
            $tc->tutor_phone = $tc->tutor_phone ?? $trainingProviderDetails['tutor_phone'] ?? null;
            $tc->placement_lead_name = $tc->placement_lead_name ?? $trainingProviderDetails['placement_lead_name'] ?? null;
            $tc->placement_lead_email = $tc->placement_lead_email ?? $trainingProviderDetails['placement_lead_email'] ?? null;
            $tc->placement_lead_phone = $tc->placement_lead_phone ?? $trainingProviderDetails['placement_lead_phone'] ?? null;
        } else {
            // Check TraineeApplication as fallback
            $app = \App\Models\TraineeApplication::where('email', $tc->email)->orderBy('id', 'desc')->first();
            if ($app) {
                $tc->gender = $tc->gender ?: $app->gender;
                $tc->ethnicity = $tc->ethnicity ?: $app->ethnicity;
                $tc->sexual_orientation = $tc->sexual_orientation ?: $app->sexual_orientation;
                $tc->date_of_birth = $tc->date_of_birth ?: $app->date_of_birth;
                $tc->address = $tc->address ?: $app->address;
                $tc->institution = $tc->institution ?: $app->institution;
                $tc->course = $tc->course ?: ($app->course_name ?? $app->course_title);
                $tc->training_org_address = $tc->training_org_address ?: $app->college_address;
                $tc->tutor_name = $tc->tutor_name ?: $app->tutor_name;
                $tc->tutor_email = $tc->tutor_email ?: $app->tutor_email;
                $tc->tutor_phone = $tc->tutor_phone ?: $app->tutor_phone;
                $tc->placement_lead_name = $tc->placement_lead_name ?: $app->placement_lead_name;
                $tc->placement_lead_email = $tc->placement_lead_email ?: $app->placement_lead_email;
                $tc->placement_lead_phone = $tc->placement_lead_phone ?: $app->placement_lead_phone;
            }

            // Fallback to TC fields if no intake form
            $tc->training_org_name = $tc->institution ?? null;
            $tc->training_org_address = $tc->training_org_address ?? null;
            $tc->course_title = $tc->course ?? null;
        }

        // For Qualified Counsellors (QC), fallback address from registered address if not set
        if ($tc->counsellor_type === 'Qualified') {
            if (empty($tc->address) && !empty($tc->registered_address)) {
                $tc->address = trim("{$tc->registered_address}, {$tc->registered_city} {$tc->registered_postcode}");
            }
        }

        // Format documents from file path columns
        $documents = [];
        $documentFields = [
            'qualification_document' => 'Course Certificate',
            'dbs_certificate_qualified' => 'DBS Certificate',
            'insurance_qualified' => 'Professional Indemnity Insurance',
            'self_employment_proof' => 'Self Employment Proof',
            'professional_membership' => 'Professional Membership',
        ];

        // Also check for regular DBS and insurance from intake form
        if ($tc->intakeForm && $tc->intakeForm->count() > 0) {
            $intakeForm = $tc->intakeForm->first();
            if ($intakeForm->dbs_certificate) {
                $documents[] = [
                    'id' => 'dbs_intake',
                    'name' => 'DBS Certificate',
                    'type' => 'DBS',
                    'uploadDate' => $intakeForm->created_at ? $intakeForm->created_at->format('Y-m-d') : null,
                    'status' => 'Verified',
                    'url' => $intakeForm->dbs_certificate,
                ];
            }
            if ($intakeForm->insurance_certificate) {
                $documents[] = [
                    'id' => 'insurance_intake',
                    'name' => 'Professional Indemnity Insurance',
                    'type' => 'Insurance',
                    'uploadDate' => $intakeForm->created_at ? $intakeForm->created_at->format('Y-m-d') : null,
                    'status' => 'Verified',
                    'url' => $intakeForm->insurance_certificate,
                ];
            }
            if ($intakeForm->student_id) {
                $documents[] = [
                    'id' => 'student_id',
                    'name' => 'Student ID',
                    'type' => 'ID',
                    'uploadDate' => $intakeForm->created_at ? $intakeForm->created_at->format('Y-m-d') : null,
                    'status' => 'Verified',
                    'url' => $intakeForm->student_id,
                ];
            }
            if ($intakeForm->supervisor_agreement) {
                $documents[] = [
                    'id' => 'supervisor_agreement',
                    'name' => 'Supervisor Agreement',
                    'type' => 'Agreement',
                    'uploadDate' => $intakeForm->created_at ? $intakeForm->created_at->format('Y-m-d') : null,
                    'status' => 'Verified',
                    'url' => $intakeForm->supervisor_agreement,
                ];
            }
            if ($intakeForm->fitness_to_practice) {
                $documents[] = [
                    'id' => 'fitness_to_practice',
                    'name' => 'Fitness to Practice Certificate',
                    'type' => 'Fitness',
                    'uploadDate' => $intakeForm->created_at ? $intakeForm->created_at->format('Y-m-d') : null,
                    'status' => 'Verified',
                    'url' => $intakeForm->fitness_to_practice,
                ];
            }
        }

        // Add qualified counsellor documents
        foreach ($documentFields as $field => $name) {
            if ($tc->$field) {
                $documents[] = [
                    'id' => $field,
                    'name' => $name,
                    'type' => str_replace('_qualified', '', $field),
                    'uploadDate' => $tc->created_at ? $tc->created_at->format('Y-m-d') : null,
                    'status' => 'Verified',
                    'url' => $tc->$field,
                ];
            }
        }

        // Get admin notes from activity logs
        $adminNotes = ActivityLog::where('model_type', TrainingCounsellor::class)
            ->where('model_id', $tc->id)
            ->with('user')
            ->orderBy('id', 'desc')
            ->get()
            ->map(function ($log) {
                return [
                    'id' => $log->id,
                    'author' => $log->user ? $log->user->name . ' (Admin)' : 'System',
                    'date' => $log->created_at ? $log->created_at->format('Y-m-d h:i A') : null,
                    'content' => $log->description,
                ];
            })
            ->toArray();

        // Format response with documents and admin notes
        $response = $tc->toArray();
        $response['documents'] = $documents;
        $response['current_clients'] = $tc->clients->count();
        $response['admin_notes'] = $adminNotes;

        // Add performance metrics
        $totalSessions = $tc->consultations()->where('status', 'completed')->count();
        $totalConsultations = $tc->consultations()->count();
        $attendanceRate = $totalConsultations > 0 ? round(($totalSessions / $totalConsultations) * 100, 1) : 0;

        $response['performance_metrics'] = [
            'client_satisfaction' => 0, // Placeholder
            'session_attendance_rate' => $attendanceRate,
            'dna_rate' => $totalConsultations > 0 ? round(($tc->consultations()->where('status', 'no_show')->count() / $totalConsultations) * 100, 1) : 0,
            'response_time' => 'N/A',
            'total_clients' => $tc->clients()->count(),
            'active_clients' => $tc->clients()->whereNull('deleted_at')->count(),
            'total_sessions' => $totalSessions,
        ];

        return response()->json($response);
    }

    public function transitionToQualified(Request $request, $id)
    {
        // Find by numeric ID, UUID, or tc_id
        $tc = is_numeric($id)
            ? TrainingCounsellor::find($id)
            : null;

        if (!$tc) {
            $tc = TrainingCounsellor::where('uuid', $id)
                ->orWhere('tc_id', $id)
                ->orWhere('id', $id)
                ->firstOrFail();
        }

        if ($tc->counsellor_type === 'Qualified') {
            return response()->json(['message' => 'Counsellor is already qualified'], 400);
        }

        $updates = [
            'counsellor_type' => 'Qualified',
            'last_activity' => now(),
        ];

        // When transitioning from TC to QC, assign QC prefix if currently TC
        if (str_starts_with($tc->tc_id ?? '', 'TC')) {
            $maxQc = TrainingCounsellor::withTrashed()
                ->where('tc_id', 'LIKE', 'QC%')
                ->get()
                ->map(function ($c) {
                    return (int) substr($c->tc_id, 2);
                })
                ->max();
            $nextQc = max(1, ($maxQc ?? 0) + 1);
            $updates['tc_id'] = 'QC' . str_pad($nextQc, 3, '0', STR_PAD_LEFT);
        }

        $tc->update($updates);

        ActivityLog::create([
            'user_id' => $request->user()->id,
            'action' => 'tc_transitioned_to_qualified',
            'model_type' => TrainingCounsellor::class,
            'model_id' => $tc->id,
            'description' => "Counsellor {$tc->name} transitioned to Qualified Counsellor ({$tc->tc_id})",
            'ip_address' => $request->ip(),
        ]);

        return response()->json([
            'message' => 'Counsellor status updated to Qualified. Please complete the qualified counsellor form.',
            'tc' => $tc,
            'requires_form' => !$tc->qualified_form_completed,
        ]);
    }

    /**
     * GET /qualified-counsellor/prefill/{uuid}  (public — no auth required)
     *
     * Returns the safe, non-sensitive prefill data for a TC who is completing
     * the qualified counsellor upgrade form via their unique link. Only the
     * fields needed to pre-populate the form are returned; no internal notes,
     * pay rates, or document paths are ever exposed.
     */
    public function publicPrefill(string $uuid)
    {
        $tc = TrainingCounsellor::where('uuid', $uuid)->first();

        if (!$tc) {
            return response()->json(['message' => 'Counsellor not found'], 404);
        }

        // Only counsellors whose form has not yet been completed should be able
        // to prefill. If already completed, surface a friendly signal.
        $alreadyCompleted = (bool) $tc->qualified_form_completed;

        $nameParts = explode(' ', trim($tc->name ?? ''));
        $fallbackFirst = $nameParts[0] ?? '';
        $fallbackLast = count($nameParts) > 1 ? implode(' ', array_slice($nameParts, 1)) : '';

        return response()->json([
            'uuid'                       => $tc->uuid,
            'name'                       => $tc->name,
            'email'                      => $tc->email,
            'phone'                      => $tc->phone,
            'legal_first_name'           => $tc->legal_first_name ?: $fallbackFirst,
            'legal_last_name'            => $tc->legal_last_name  ?: $fallbackLast,
            'registered_address'         => $tc->registered_address ?: $tc->address,
            'registered_city'            => $tc->registered_city,
            'registered_postcode'        => $tc->registered_postcode,
            'has_supervisor'             => $tc->has_supervisor,
            'previous_vanquish_work'     => $tc->previous_vanquish_work,
            'areas_to_improve'           => $tc->areas_to_improve,
            'unique_trait'               => $tc->unique_trait,
            'counsellor_training_details'=> $tc->counsellor_training_details,
            'qualified_to_work_with'     => $tc->qualified_to_work_with ?: [],
            'challenging_cases'          => $tc->challenging_cases,
            'signature'                  => $tc->signature ?: $tc->name,
            'signature_date'             => $tc->signature_date
                ? \Carbon\Carbon::parse($tc->signature_date)->toDateString()
                : null,
            'already_completed'          => $alreadyCompleted,
        ]);
    }

    public function submitQualifiedForm(Request $request)
    {
        $validated = $request->validate([
            'tc_id' => 'nullable', // Optional: if provided, updates existing TC/QC. If null, creates new QC.
            'email' => 'required_without:tc_id|nullable|email|max:255',
            'phone' => 'nullable|string|max:50',
            'legal_first_name' => 'required|string|max:255',
            'legal_last_name' => 'required|string|max:255',
            'registered_address' => 'required|string|max:500',
            'registered_city' => 'required|string|max:255',
            'registered_postcode' => 'required|string|max:20',
            'has_supervisor' => 'nullable|string',
            'previous_vanquish_work' => 'nullable|string',
            'areas_to_improve' => 'nullable|string',
            'unique_trait' => 'nullable|string',
            'counsellor_training_details' => 'nullable|string',
            'qualified_to_work_with' => 'nullable|array',
            'qualified_to_work_with.*' => 'string',
            'challenging_cases' => 'nullable|string',
            'qualification_document' => 'nullable|string',
            'dbs_certificate_qualified' => 'nullable|string',
            'insurance_qualified' => 'nullable|string',
            'self_employment_proof' => 'nullable|string',
            'professional_membership' => 'nullable|string',
            'valid_id_document' => 'nullable|string',
            'signature' => 'required|string',
            'signature_date' => 'required|string',
            'availability' => 'nullable|array',
            'date_of_birth' => 'nullable|string',
            'gender' => 'nullable|string',
            'ethnicity' => 'nullable|string',
            'sexual_orientation' => 'nullable|string',
            'beliefs' => 'nullable|string',
            'disabilities' => 'nullable|string',
            'medical_conditions' => 'nullable|string',
            'has_indemnity_insurance' => 'nullable|string',
            'professional_body_details' => 'nullable|string',
            'dbs_status' => 'nullable|string',
            'familiar_with_online_counselling' => 'nullable|string',
            'modalities' => 'nullable|array',
            'experience_areas' => 'nullable|array',
            'availability_schedule' => 'nullable|string',
        ]);

        $tcIdParam = $validated['tc_id'] ?? null;
        $tc = null;

        if (!empty($tcIdParam)) {
            // Find TC by numeric ID, tc_id string, or uuid (active only)
            $tc = is_numeric($tcIdParam)
                ? TrainingCounsellor::whereNull('archived_at')->find($tcIdParam)
                : null;

            if (!$tc) {
                $tc = TrainingCounsellor::whereNull('archived_at')
                    ->where(function ($q) use ($tcIdParam) {
                        $q->where('tc_id', $tcIdParam)
                            ->orWhere('uuid', $tcIdParam)
                            ->orWhere('id', $tcIdParam);
                    })
                    ->first();
            }
        }

        $normalizedEmail = strtolower(trim($validated['email']));
        $fullName = trim($validated['legal_first_name'] . ' ' . $validated['legal_last_name']);

        // 1. Always find or create a permanent Person identity record
        $person = Person::findOrCreateByEmail($normalizedEmail, $fullName, $validated['phone'] ?? null);

        // 2. Always record each submission as its OWN separate, immutable QcApplication record
        $qcApp = QcApplication::create([
            'person_id' => $person->id,
            'name' => $fullName,
            'legal_first_name' => $validated['legal_first_name'],
            'legal_last_name' => $validated['legal_last_name'],
            'email' => $normalizedEmail,
            'phone' => $validated['phone'] ?? null,
            'registered_address' => $validated['registered_address'],
            'registered_city' => $validated['registered_city'],
            'registered_postcode' => $validated['registered_postcode'],
            'has_supervisor' => $validated['has_supervisor'] ?? null,
            'previous_vanquish_work' => $validated['previous_vanquish_work'] ?? null,
            'areas_to_improve' => $validated['areas_to_improve'] ?? null,
            'unique_trait' => $validated['unique_trait'] ?? null,
            'counsellor_training_details' => $validated['counsellor_training_details'] ?? null,
            'qualified_to_work_with' => $validated['qualified_to_work_with'] ?? [],
            'challenging_cases' => $validated['challenging_cases'] ?? null,
            'qualification_document' => $validated['qualification_document'] ?? null,
            'dbs_certificate_qualified' => $validated['dbs_certificate_qualified'] ?? null,
            'insurance_qualified' => $validated['insurance_qualified'] ?? null,
            'self_employment_proof' => $validated['self_employment_proof'] ?? null,
            'professional_membership' => $validated['professional_membership'] ?? null,
            'signature' => $validated['signature'],
            'signature_date' => $validated['signature_date'],
            'gender' => $validated['gender'] ?? null,
            'ethnicity' => $validated['ethnicity'] ?? null,
            'sexual_orientation' => $validated['sexual_orientation'] ?? null,
            'date_of_birth' => $validated['date_of_birth'] ?? null,
            'modalities' => is_array($validated['modalities'] ?? null) ? implode(', ', $validated['modalities']) : ($validated['modalities'] ?? null),
            'experience_areas' => $validated['experience_areas'] ?? null,
            'availability' => $validated['availability'] ?? null,
            'answers' => $validated,
            'raw_submission' => $validated,
        ]);

        // 3. If no existing TC found by tc_id, check for an ACTIVE (non-archived, non-deleted) TC with this email
        // IMPORTANT: Archived/soft-deleted records are NEVER auto-restored or merged!
        if (!$tc && !empty($normalizedEmail)) {
            $tc = TrainingCounsellor::whereNull('archived_at')
                ->where('email', $normalizedEmail)
                ->first();
        }

        $formFields = [
            'person_id' => $person->id,
            'name' => $fullName,
            'legal_first_name' => $validated['legal_first_name'],
            'legal_last_name' => $validated['legal_last_name'],
            'registered_address' => $validated['registered_address'],
            'registered_city' => $validated['registered_city'],
            'registered_postcode' => $validated['registered_postcode'],
            'has_supervisor' => $validated['has_supervisor'] ?? null,
            'previous_vanquish_work' => $validated['previous_vanquish_work'] ?? null,
            'areas_to_improve' => $validated['areas_to_improve'] ?? null,
            'unique_trait' => $validated['unique_trait'] ?? null,
            'counsellor_training_details' => $validated['counsellor_training_details'] ?? null,
            'qualified_to_work_with' => $validated['qualified_to_work_with'] ?? [],
            'challenging_cases' => $validated['challenging_cases'] ?? null,
            'qualification_document' => $validated['qualification_document'] ?? null,
            'dbs_certificate_qualified' => $validated['dbs_certificate_qualified'] ?? null,
            'insurance_qualified' => $validated['insurance_qualified'] ?? null,
            'self_employment_proof' => $validated['self_employment_proof'] ?? null,
            'professional_membership' => $validated['professional_membership'] ?? null,
            'signature' => $validated['signature'],
            'signature_date' => $validated['signature_date'],
            'qualified_form_completed' => true,
            'counsellor_type' => 'Qualified',
            'last_activity' => now(),
        ];

        if (!empty($validated['gender'])) {
            $formFields['gender'] = $validated['gender'];
        }
        if (!empty($validated['ethnicity'])) {
            $formFields['ethnicity'] = $validated['ethnicity'];
        }
        if (!empty($validated['sexual_orientation'])) {
            $formFields['sexual_orientation'] = $validated['sexual_orientation'];
        }
        if (!empty($validated['date_of_birth'])) {
            $formFields['date_of_birth'] = $validated['date_of_birth'];
        }
        if (!empty($validated['modalities'])) {
            $formFields['modality'] = is_array($validated['modalities']) ? implode(', ', $validated['modalities']) : $validated['modalities'];
        }
        if (!empty($validated['experience_areas'])) {
            $formFields['topics_with_experience'] = $validated['experience_areas'];
        }

        if (isset($validated['availability'])) {
            $formFields['availability'] = $validated['availability'];
        }

        if ($tc) {
            // Updating existing active counsellor
            if (str_starts_with($tc->tc_id ?? '', 'TC')) {
                $maxQc = TrainingCounsellor::withTrashed()
                    ->where('tc_id', 'LIKE', 'QC%')
                    ->get()
                    ->map(function ($c) {
                        return (int) substr($c->tc_id, 2);
                    })
                    ->max();
                $nextQc = max(1, ($maxQc ?? 0) + 1);
                $formFields['tc_id'] = 'QC' . str_pad($nextQc, 3, '0', STR_PAD_LEFT);
            }

            if (!empty($validated['phone']) && empty($tc->phone)) {
                $formFields['phone'] = $validated['phone'];
            }

            $tc->update($formFields);

            // Ensure portal user account matches the active counsellor
            $user = User::where('email', $tc->email)->first();
            if ($user) {
                $user->update([
                    'name' => $tc->name,
                    'role' => 'counsellor',
                    'training_counsellor_id' => $tc->id,
                ]);
            }

            ActivityLog::create([
                'user_id' => $request->user()->id ?? null,
                'action' => 'qualified_form_submitted',
                'model_type' => TrainingCounsellor::class,
                'model_id' => $tc->id,
                'description' => "Qualified Counsellor form submitted for {$tc->name} ({$tc->tc_id})",
                'ip_address' => $request->ip(),
            ]);
        } else {
            // Creating brand new Qualified Counsellor record
            $maxQc = TrainingCounsellor::withTrashed()
                ->where('tc_id', 'LIKE', 'QC%')
                ->get()
                ->map(function ($c) {
                    return (int) substr($c->tc_id, 2);
                })
                ->max();
            $nextQc = max(1, ($maxQc ?? 0) + 1);
            $qcId = 'QC' . str_pad($nextQc, 3, '0', STR_PAD_LEFT);

            $fullAddress = trim($validated['registered_address'] . ', ' . $validated['registered_city'] . ' ' . $validated['registered_postcode']);

            $newRecord = array_merge($formFields, [
                'uuid' => \Illuminate\Support\Str::uuid()->toString(),
                'tc_id' => $qcId,
                'name' => $fullName,
                'email' => $normalizedEmail,
                'phone' => $validated['phone'] ?? null,
                'address' => $fullAddress,
                'status' => 'Active',
                'joined_date' => now(),
            ]);

            $tc = TrainingCounsellor::create($newRecord);

            // Find or create User account for counsellor portal access
            $user = User::where('email', $tc->email)->first();
            if (!$user) {
                $temporaryPassword = \Illuminate\Support\Str::random(10) . '!1Aa';
                $user = User::create([
                    'name' => $tc->name,
                    'email' => $tc->email,
                    'password' => Hash::make($temporaryPassword),
                    'role' => 'counsellor',
                    'training_counsellor_id' => $tc->id,
                ]);
            } else {
                $user->update([
                    'name' => $tc->name,
                    'role' => 'counsellor',
                    'training_counsellor_id' => $tc->id,
                ]);
            }

            ActivityLog::create([
                'user_id' => $request->user()->id ?? null,
                'action' => 'qc_onboarded',
                'model_type' => TrainingCounsellor::class,
                'model_id' => $tc->id,
                'description' => "New Qualified Counsellor {$tc->name} ({$tc->tc_id}) registered via onboarding form",
                'ip_address' => $request->ip(),
            ]);
        }

        // Send confirmation email to counsellor (guaranteed to reflect current applicant's name)
        if (!empty($normalizedEmail)) {
            try {
                $counsellorName = $fullName;
                $firstName = !empty($validated['legal_first_name']) ? $validated['legal_first_name'] : explode(' ', $counsellorName)[0];
                $submissionDate = now()->format('d M Y, H:i T');

                app(\App\Services\EmailService::class)->sendAndLog(
                    $normalizedEmail,
                    'qualified_counsellor_submission',
                    [
                        'first_name' => $firstName,
                        'counsellor_name' => $counsellorName,
                        'email' => $normalizedEmail,
                        'tc_id' => $tc->tc_id ?? 'Pending',
                        'submission_date' => $submissionDate,
                    ],
                    $tc
                );
            } catch (\Throwable $e) {
                Log::error("Failed to send qualified counsellor submission confirmation email to {$normalizedEmail}: " . $e->getMessage());
            }
        }

        return response()->json([
            'message' => 'Qualified Counsellor form submitted successfully',
            'tc' => $tc,
            'application_id' => $qcApp->id,
        ]);
    }

    public function uploadDocument(Request $request)
    {
        $request->validate([
            'file' => 'required|file|mimes:pdf,doc,docx,jpg,jpeg,png|max:10240',
            'field' => 'required|string|max:100',
            'tc_id' => 'nullable',
        ]);

        $file = $request->file('file');
        $field = $request->input('field');
        $tcIdParam = $request->input('tc_id');

        // Additional security checks
        $originalName = $file->getClientOriginalName();
        $extension = strtolower($file->getClientOriginalExtension());
        $mimeType = $file->getMimeType();

        // Validate file extension
        $allowedExtensions = ['pdf', 'doc', 'docx', 'jpg', 'jpeg', 'png'];
        if (!in_array($extension, $allowedExtensions)) {
            return response()->json([
                'message' => 'Invalid file type. Allowed types: PDF, DOC, DOCX, JPG, JPEG, PNG',
            ], 422);
        }

        // Validate MIME type
        $allowedMimeTypes = [
            'application/pdf',
            'application/msword',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'image/jpeg',
            'image/png',
        ];
        if (!in_array($mimeType, $allowedMimeTypes)) {
            return response()->json([
                'message' => 'Invalid file MIME type.',
            ], 422);
        }

        // Sanitize field name to prevent directory traversal
        $field = preg_replace('/[^a-zA-Z0-9_-]/', '_', $field);
        $field = substr($field, 0, 100);

        // Sanitize filename
        $sanitizedName = preg_replace('/[^a-zA-Z0-9._-]/', '_', $originalName);
        $sanitizedName = substr($sanitizedName, 0, 255);
        $filename = time() . '_' . uniqid() . '_' . $sanitizedName;

        if (!empty($tcIdParam)) {
            // Find TC or QC by ID, tc_id, or uuid
            $tc = is_numeric($tcIdParam)
                ? TrainingCounsellor::find($tcIdParam)
                : null;

            if (!$tc) {
                $tc = TrainingCounsellor::where('tc_id', $tcIdParam)
                    ->orWhere('uuid', $tcIdParam)
                    ->orWhere('id', $tcIdParam)
                    ->first();
            }

            $tcId = $tc ? $tc->id : 'uploads';
            $path = $file->storeAs("qualified_counsellors/{$tcId}/{$field}", $filename, 'public');
        } else {
            $path = $file->storeAs("qualified_counsellors/onboarding/{$field}", $filename, 'public');
        }

        return response()->json([
            'file_path' => $path,
            'file_name' => $originalName,
        ]);
    }

    public function sendEmail(Request $request, $id)
    {
        // Find by numeric ID, UUID, or tc_id
        $tc = is_numeric($id)
            ? TrainingCounsellor::find($id)
            : null;

        if (!$tc) {
            $tc = TrainingCounsellor::where('uuid', $id)
                ->orWhere('tc_id', $id)
                ->orWhere('id', $id)
                ->firstOrFail();
        }

        $validated = $request->validate([
            'subject' => 'required|string|max:255',
            'message' => 'required|string',
        ]);

        // Check if email exists
        if (!$tc->email) {
            return response()->json([
                'message' => 'Counsellor does not have an email address',
            ], 400);
        }

        // Send email
        try {
            /*
            Mail::to($tc->email)->send(new DynamicEmail(
                'generic_tc_email',
                [
                    'tc_name' => $tc->name,
                    'message' => $validated['message'],
                    'subject' => $validated['subject']
                ]
            ));
            */

            // Log activity but inform that email send was skipped/disabled
            ActivityLog::create([
                'user_id' => $request->user()->id,
                'action' => 'email_skipped',
                'model_type' => TrainingCounsellor::class,
                'model_id' => $tc->id,
                'description' => "External email message to {$tc->name} was blocked - MESSAGES MUST STAY ON PLATFORM: {$validated['subject']}",
                'changes' => ['subject' => $validated['subject'], 'message' => '[REDACTED - STAY ON PLATFORM]'],
                'ip_address' => $request->ip(),
            ]);

            return response()->json([
                'message' => 'Manual external emails are disabled. Please use the on-platform messaging system to communicate with ' . $tc->name,
                'to' => $tc->email,
                'subject' => $validated['subject'],
                'tc_uuid' => $tc->uuid,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to process request: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function sendQualifiedFormEmail(Request $request, $id)
    {
        // Find by numeric ID, UUID, or tc_id
        $tc = is_numeric($id)
            ? TrainingCounsellor::find($id)
            : null;

        if (!$tc) {
            $tc = TrainingCounsellor::where('uuid', $id)
                ->orWhere('tc_id', $id)
                ->orWhere('id', $id)
                ->firstOrFail();
        }

        // Check if email exists
        if (!$tc->email) {
            return response()->json([
                'message' => 'Counsellor does not have an email address',
            ], 400);
        }

        // Send email
        try {
            $baseUrl = rtrim(config('app.frontend_url'), '/');
            app(\App\Services\EmailService::class)->sendAndLog(
                $tc->email,
                'qualified_form',
                [
                    'tc_name' => $tc->name,
                    'form_url' => $baseUrl . '/qualified-counsellor-form?' . http_build_query(['uuid' => $tc->uuid])
                ]
            );

            ActivityLog::create([
                'user_id' => $request->user()->id,
                'action' => 'qualified_form_email_sent',
                'model_type' => TrainingCounsellor::class,
                'model_id' => $tc->id,
                'description' => "Qualified Counsellor form email sent to {$tc->name} ({$tc->email})",
                'ip_address' => $request->ip(),
            ]);

            return response()->json([
                'message' => 'Email sent successfully to ' . $tc->email,
                'tc_uuid' => $tc->uuid,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to send email: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Upload photo for authenticated counsellor
     */
    public function uploadOwnPhoto(Request $request)
    {
        $user = $request->user();
        if (!$user || $user->role !== 'counsellor' || !$user->training_counsellor_id) {
            return response()->json(['message' => 'Unauthorized. Counsellor access required.'], 403);
        }

        return $this->handlePhotoUpload($request, $user->training_counsellor_id);
    }

    /**
     * Delete photo for authenticated counsellor
     */
    public function deleteOwnPhoto(Request $request)
    {
        $user = $request->user();
        if (!$user || $user->role !== 'counsellor' || !$user->training_counsellor_id) {
            return response()->json(['message' => 'Unauthorized. Counsellor access required.'], 403);
        }

        return $this->handlePhotoDelete($request, $user->training_counsellor_id);
    }

    /**
     * Upload photo for training counsellor (admin or owning counsellor)
     */
    public function uploadPhoto(Request $request, $id)
    {
        $tc = TrainingCounsellor::where('uuid', $id)->orWhere('tc_id', $id)->orWhere('id', $id)->firstOrFail();

        $user = $request->user();
        $isAdmin = $user && in_array($user->role, ['admin', 'staff']);
        $isSelf = $user && $user->role === 'counsellor' && (int)$user->training_counsellor_id === (int)$tc->id;

        if (!$isAdmin && !$isSelf) {
            return response()->json(['message' => 'Unauthorized access.'], 403);
        }

        return $this->handlePhotoUpload($request, $tc->id);
    }

    /**
     * Delete photo for training counsellor (admin or owning counsellor)
     */
    public function deletePhoto(Request $request, $id)
    {
        $tc = TrainingCounsellor::where('uuid', $id)->orWhere('tc_id', $id)->orWhere('id', $id)->firstOrFail();

        $user = $request->user();
        $isAdmin = $user && in_array($user->role, ['admin', 'staff']);
        $isSelf = $user && $user->role === 'counsellor' && (int)$user->training_counsellor_id === (int)$tc->id;

        if (!$isAdmin && !$isSelf) {
            return response()->json(['message' => 'Unauthorized access.'], 403);
        }

        return $this->handlePhotoDelete($request, $tc->id);
    }

    /**
     * Internal handler for photo upload
     */
    protected function handlePhotoUpload(Request $request, $tcId)
    {
        $tc = TrainingCounsellor::findOrFail($tcId);

        $request->validate([
            'photo' => 'required|image|mimes:jpeg,jpg,png,webp,gif|max:10240', // Max 10MB
        ]);

        $file = $request->file('photo');

        // Additional security checks
        $originalName = $file->getClientOriginalName();
        $extension = strtolower($file->getClientOriginalExtension());
        $mimeType = $file->getMimeType();

        // Validate file extension
        $allowedExtensions = ['jpg', 'jpeg', 'png', 'webp', 'gif'];
        if (!in_array($extension, $allowedExtensions)) {
            return response()->json([
                'message' => 'Invalid file type. Allowed types: JPG, JPEG, PNG, WEBP, GIF',
            ], 422);
        }

        // Validate MIME type
        $allowedMimeTypes = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];
        if (!in_array($mimeType, $allowedMimeTypes)) {
            return response()->json([
                'message' => 'Invalid file MIME type.',
            ], 422);
        }

        // Sanitize filename
        $sanitizedName = preg_replace('/[^a-zA-Z0-9._-]/', '_', $originalName);
        $sanitizedName = substr($sanitizedName, 0, 255);

        // Generate unique filename to prevent overwrites
        $filename = time() . '_' . uniqid() . '_' . $sanitizedName;

        // Delete old photo if exists and local
        if ($tc->photo && !str_starts_with($tc->photo, 'http')) {
            $oldPhotoPath = storage_path('app/public/' . $tc->photo);
            if (file_exists($oldPhotoPath)) {
                @unlink($oldPhotoPath);
            }
        }

        $path = $file->storeAs("tc_photos/{$tc->id}", $filename, 'public');

        // Update TC with photo path
        $tc->update(['photo' => $path]);

        // Log activity
        ActivityLog::create([
            'user_id' => $request->user()->id,
            'action' => 'tc_photo_uploaded',
            'model_type' => TrainingCounsellor::class,
            'model_id' => $tc->id,
            'description' => "Photo uploaded for practitioner {$tc->name}",
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        $photoUrl = $this->getStorageUrl($request, $path);

        return response()->json([
            'message' => 'Photo uploaded successfully',
            'photo' => $path,
            'photo_url' => $photoUrl,
            'tc' => $tc->fresh(),
        ]);
    }

    /**
     * Internal handler for photo delete
     */
    protected function handlePhotoDelete(Request $request, $tcId)
    {
        $tc = TrainingCounsellor::findOrFail($tcId);

        if (!$tc->photo) {
            return response()->json([
                'message' => 'No photo to delete',
            ], 404);
        }

        // Delete file from storage if local
        if (!str_starts_with($tc->photo, 'http')) {
            $photoPath = storage_path('app/public/' . $tc->photo);
            if (file_exists($photoPath)) {
                @unlink($photoPath);
            }
        }

        // Update TC
        $tc->update(['photo' => null]);

        // Log activity
        ActivityLog::create([
            'user_id' => $request->user()->id,
            'action' => 'tc_photo_deleted',
            'model_type' => TrainingCounsellor::class,
            'model_id' => $tc->id,
            'description' => "Photo deleted for practitioner {$tc->name}",
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        return response()->json([
            'message' => 'Photo deleted successfully',
            'tc' => $tc->fresh(),
        ]);
    }

    /**
     * Generate storage URL using request's base URL
     */
    private function getStorageUrl(Request $request, $path)
    {
        if (!$path) return null;
        if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://')) {
            return $path;
        }
        $baseUrl = $request->getSchemeAndHttpHost();
        return $baseUrl . '/storage/' . ltrim($path, '/');
    }

    /**
     * Download training counsellor report as PDF
     */
    public function downloadReport(Request $request, $id)
    {
        // Find by UUID or fall back to tc_id for backward compatibility
        $tc = TrainingCounsellor::where('uuid', $id)
            ->orWhere('tc_id', $id)
            ->with(['clients', 'consultations', 'intakeForm'])
            ->firstOrFail();

        // Extract training provider details from intake form (same logic as details method)
        if ($tc->intakeForm && $tc->intakeForm->count() > 0) {
            $intakeForm = $tc->intakeForm->first();

            $trainingProviderDetails = [];
            if ($intakeForm->additional_info) {
                try {
                    $decoded = json_decode($intakeForm->additional_info, true);
                    if (is_array($decoded)) {
                        $trainingProviderDetails = $decoded;
                    }
                } catch (\Exception $e) {
                    // If not JSON, ignore
                }
            }

            $tc->training_org_name = $tc->institution ?? $trainingProviderDetails['training_org_name'] ?? $intakeForm->institution ?? null;
            $tc->training_org_address = $tc->training_org_address ?? $trainingProviderDetails['training_org_address'] ?? null;
            $tc->course_title = $tc->course ?? $trainingProviderDetails['course_title'] ?? $intakeForm->course ?? null;
            $tc->tutor_name = $tc->tutor_name ?? $trainingProviderDetails['tutor_name'] ?? null;
            $tc->tutor_email = $tc->tutor_email ?? $trainingProviderDetails['tutor_email'] ?? null;
            $tc->tutor_phone = $tc->tutor_phone ?? $trainingProviderDetails['tutor_phone'] ?? null;
            $tc->placement_lead_name = $tc->placement_lead_name ?? $trainingProviderDetails['placement_lead_name'] ?? null;
            $tc->placement_lead_email = $tc->placement_lead_email ?? $trainingProviderDetails['placement_lead_email'] ?? null;
            $tc->placement_lead_phone = $tc->placement_lead_phone ?? $trainingProviderDetails['placement_lead_phone'] ?? null;
        }

        // Format documents
        $documents = collect();
        $documentFields = [
            'qualification_document' => 'Course Certificate',
            'dbs_certificate_qualified' => 'DBS Certificate',
            'insurance_qualified' => 'Professional Indemnity Insurance',
            'self_employment_proof' => 'Self Employment Proof',
            'professional_membership' => 'Professional Membership',
        ];

        // Add intake form documents
        if ($tc->intakeForm && $tc->intakeForm->count() > 0) {
            $intakeForm = $tc->intakeForm->first();
            if ($intakeForm->dbs_certificate) {
                $documents->push([
                    'id' => 'dbs_intake',
                    'name' => 'DBS Certificate',
                    'type' => 'DBS',
                    'uploadDate' => $intakeForm->created_at ? $intakeForm->created_at->format('Y-m-d') : null,
                    'status' => 'Verified',
                ]);
            }
            if ($intakeForm->insurance_certificate) {
                $documents->push([
                    'id' => 'insurance_intake',
                    'name' => 'Professional Indemnity Insurance',
                    'type' => 'Insurance',
                    'uploadDate' => $intakeForm->created_at ? $intakeForm->created_at->format('Y-m-d') : null,
                    'status' => 'Verified',
                ]);
            }
            if ($intakeForm->student_id) {
                $documents->push([
                    'id' => 'student_id',
                    'name' => 'Student ID',
                    'type' => 'ID',
                    'uploadDate' => $intakeForm->created_at ? $intakeForm->created_at->format('Y-m-d') : null,
                    'status' => 'Verified',
                ]);
            }
        }

        // Add qualified counsellor documents
        foreach ($documentFields as $field => $name) {
            if ($tc->$field) {
                $documents->push([
                    'id' => $field,
                    'name' => $name,
                    'type' => str_replace('_qualified', '', $field),
                    'uploadDate' => $tc->created_at ? $tc->created_at->format('Y-m-d') : null,
                    'status' => 'Verified',
                ]);
            }
        }

        // Get clients
        $clients = $tc->clients;

        // Calculate statistics
        $totalSessions = $tc->consultations->count();
        $completedSessions = $tc->consultations->where('status', 'completed')->count();

        // Fetch company settings (with logo inlined as base64 for PDF rendering)
        $companySettings = CompanySettingsService::getForPdf();

        // Generate PDF
        $pdf = Pdf::loadView('pdf.training-counsellor-report', [
            'tc' => $tc,
            'documents' => $documents,
            'clients' => $clients,
            'totalSessions' => $totalSessions,
            'completedSessions' => $completedSessions,
            'companySettings' => $companySettings,
        ]);

        // Set paper size and orientation
        $pdf->setPaper('A4', 'portrait');

        // Log activity
        ActivityLog::create([
            'user_id' => $request->user()->id,
            'action' => 'report_downloaded',
            'model_type' => TrainingCounsellor::class,
            'model_id' => $tc->id,
            'description' => "Report downloaded for {$tc->name}",
            'ip_address' => $request->ip(),
        ]);

        // Return PDF download
        $sanitizedName = strtoupper(preg_replace('/[^a-zA-Z0-9]/', '_', $tc->name));
        return $pdf->download("TC_{$sanitizedName}_REPORT.pdf");
    }

    public function sendPortalInvite(Request $request, $id)
    {
        try {
            $tc = is_numeric($id)
                ? TrainingCounsellor::find($id)
                : null;

            if (!$tc) {
                $tc = TrainingCounsellor::where('uuid', $id)
                    ->orWhere('tc_id', $id)
                    ->orWhere('id', $id)
                    ->firstOrFail();
            }
            $portalUrl = rtrim(config('app.frontend_url', 'https://vqtmanagement.com'), '/') . '/counsellor-login';

            // 1. Find or create User account (role = counsellor)
            $existingUser = User::where('email', $tc->email)->first();
            $temporaryPassword = null;

            if (!$existingUser) {
                $temporaryPassword = \Illuminate\Support\Str::random(10) . '!1Aa';
                $existingUser = User::create([
                    'name'                    => $tc->name,
                    'email'                   => strtolower(trim($tc->email)),
                    'password'                => Hash::make($temporaryPassword),
                    'role'                    => 'counsellor',
                    'training_counsellor_id'  => $tc->id,
                ]);
            } else {
                $existingUser->update([
                    'name'                   => $tc->name,
                    'role'                   => 'counsellor',
                    'training_counsellor_id' => $tc->id,
                ]);
            }

            // 2. Send portal invite email
            app(\App\Services\EmailService::class)->sendAndLog($tc->email, 'counsellor_portal_invite', [
                'first_name'         => explode(' ', $tc->name)[0] ?? $tc->name,
                'portal_link'        => $portalUrl,
                'email'              => $tc->email,
                'temporary_password' => $temporaryPassword ?? '(use your existing password)',
            ]);

            ActivityLog::create([
                'user_id'     => optional($request->user())->id,
                'action'      => 'tc_portal_invite_sent',
                'model_type'  => TrainingCounsellor::class,
                'model_id'    => $tc->id,
                'description' => "Portal invite sent and counsellor account created for {$tc->name}",
                'ip_address'  => $request->ip(),
            ]);

            return response()->json([
                'message'            => 'Portal invitation sent and counsellor account created',
                'account_created'    => $temporaryPassword !== null,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to send TC portal invite: ' . $e->getMessage(), ['exception' => $e]);
            $errorMessage = 'Failed to send invite.';
            if ($e instanceof \Illuminate\Database\QueryException) {
                if (isset($e->errorInfo[1]) && $e->errorInfo[1] == 1062) {
                    $errorMessage = 'A user account with this email already exists.';
                } else {
                    $errorMessage = 'A database error occurred while creating the account.';
                }
            } else {
                $errorMessage = $e->getMessage();
            }
            return response()->json(['message' => $errorMessage], 500);
        }
    }

    /**
     * Bulk send portal invites to multiple counsellors
     */
    public function bulkPortalInvite(Request $request)
    {
        $validated = $request->validate([
            'ids' => 'required|array',
            'ids.*' => 'required|string', // UUIDs or tc_ids
        ]);

        $results = [
            'success' => [],
            'failed' => [],
        ];

        $portalUrl = rtrim(config('app.frontend_url', 'https://vqtmanagement.com'), '/') . '/counsellor-login';

        foreach ($validated['ids'] as $id) {
            try {
                $tc = is_numeric($id)
                    ? TrainingCounsellor::find($id)
                    : TrainingCounsellor::where('uuid', $id)->orWhere('tc_id', $id)->orWhere('id', $id)->first();
                
                if (!$tc) {
                    $results['failed'][] = ['id' => $id, 'reason' => 'Counsellor not found'];
                    continue;
                }

                if (!$tc->email) {
                    $results['failed'][] = ['id' => $id, 'name' => $tc->name, 'reason' => 'No email address'];
                    continue;
                }

                // 1. Find or create User account
                $existingUser = User::where('email', $tc->email)->first();
                $temporaryPassword = null;

                if (!$existingUser) {
                    $temporaryPassword = \Illuminate\Support\Str::random(10) . '!1Aa';
                    $existingUser = User::create([
                        'name'                    => $tc->name,
                        'email'                   => strtolower(trim($tc->email)),
                        'password'                => Hash::make($temporaryPassword),
                        'role'                    => 'counsellor',
                        'training_counsellor_id'  => $tc->id,
                    ]);
                } else {
                    $existingUser->update([
                        'name'                   => $tc->name,
                        'role'                   => 'counsellor',
                        'training_counsellor_id' => $tc->id,
                    ]);
                }

                // 2. Send portal invite email
                app(\App\Services\EmailService::class)->sendAndLog($tc->email, 'counsellor_portal_invite', [
                    'first_name'         => explode(' ', $tc->name)[0] ?? $tc->name,
                    'portal_link'        => $portalUrl,
                    'email'              => $tc->email,
                    'temporary_password' => $temporaryPassword ?? '(use your existing password)',
                ]);

                ActivityLog::create([
                    'user_id'     => optional($request->user())->id,
                    'action'      => 'tc_portal_invite_sent',
                    'model_type'  => TrainingCounsellor::class,
                    'model_id'    => $tc->id,
                    'description' => "Bulk portal invite sent for {$tc->name}",
                    'ip_address'  => $request->ip(),
                ]);

                $results['success'][] = ['id' => $id, 'name' => $tc->name];

            } catch (\Exception $e) {
                $results['failed'][] = ['id' => $id, 'reason' => $e->getMessage()];
            }
        }

        return response()->json([
            'message' => 'Bulk invitation process completed',
            'results' => $results,
        ]);
    }

    public function downloadDocument(Request $request, TrainingCounsellor $tc, $field)
    {
        $allowedFields = [
            'qualification_document',
            'dbs_certificate_qualified',
            'insurance_qualified',
            'self_employment_proof',
            'professional_membership',
        ];

        if (!in_array($field, $allowedFields)) {
            return response()->json(['message' => 'Invalid document field'], 400);
        }

        $path = $tc->$field;

        if (!$path) {
            return response()->json(['message' => 'Document not found'], 404);
        }

        // If the path is stored as a full URL, extract the relative path
        if (filter_var($path, FILTER_VALIDATE_URL)) {
            $parsedUrl = parse_url($path);
            $urlPath = $parsedUrl['path'] ?? '';
            if (str_contains($urlPath, '/storage/')) {
                $path = substr($urlPath, strpos($urlPath, '/storage/') + 9);
            }
        }

        // Clean up any leading slash or 'storage/' from the path
        $path = ltrim($path, '/');
        if (str_starts_with($path, 'storage/')) {
            $path = substr($path, 8);
        }

        if (!Storage::disk('public')->exists($path)) {
            return response()->json(['message' => 'File does not exist in storage'], 404);
        }

        $filename = basename($path);
        // Strip out the timestamp/uniqid prefix (e.g. 169123456_654321_filename.pdf) if present
        $cleanFilename = preg_replace('/^\d+_[a-f0-9]+_/', '', $filename);

        return Storage::disk('public')->download($path, $cleanFilename);
    }

    /**
     * Get active bookings and caseload by slot for a counsellor.
     */
    public function getSlotBookings($id)
    {
        $tc = TrainingCounsellor::where('uuid', $id)
            ->orWhere('tc_id', $id)
            ->orWhere('id', $id)
            ->firstOrFail();

        // Get all active / matched clients with allocated day & time
        $clients = \App\Models\Client::where('matched_tc_id', $tc->id)
            ->whereNotIn('status', ['Inactive', 'Discharged', 'Cancelled', 'terminated'])
            ->get(['id', 'uuid', 'client_id', 'name', 'service_type', 'status', 'stage', 'allocated_day', 'allocated_time']);

        // Group by day and time
        $slotMap = [];

        foreach ($clients as $client) {
            if (!$client->allocated_day || !$client->allocated_time) {
                continue;
            }

            $day = strtolower($client->allocated_day);
            $timeSlot = $this->normalizeTimeSlot($client->allocated_time);

            if (!isset($slotMap[$day])) {
                $slotMap[$day] = [];
            }
            if (!isset($slotMap[$day][$timeSlot])) {
                $slotMap[$day][$timeSlot] = [];
            }

            $slotMap[$day][$timeSlot][] = [
                'id' => $client->id,
                'uuid' => $client->uuid,
                'client_id' => $client->client_id,
                'name' => $client->name,
                'service_type' => $client->service_type,
                'status' => $client->status,
                'stage' => $client->stage,
                'allocated_day' => $client->allocated_day,
                'allocated_time' => $client->allocated_time,
            ];
        }

        // Get TC raw availability
        $rawAvailability = $tc->availability;
        if (is_string($rawAvailability)) {
            $rawAvailability = json_decode($rawAvailability, true);
        }
        $availability = is_array($rawAvailability) ? array_change_key_case($rawAvailability, CASE_LOWER) : [];

        return response()->json([
            'tc' => [
                'id' => $tc->id,
                'uuid' => $tc->uuid,
                'name' => $tc->name,
                'counsellor_type' => $tc->counsellor_type,
                'current_clients' => $tc->current_clients,
                'max_clients' => $tc->max_clients ?? 6,
                'availability' => $availability,
            ],
            'slots' => $slotMap,
            'active_clients_count' => $clients->count(),
        ]);
    }

    private function normalizeTimeSlot($timeStr)
    {
        if (!$timeStr) return '';
        $clean = strtolower(trim(str_replace(' ', '', $timeStr)));

        $mapping = [
            'morning-early' => '10am-1050am',
            'morning-late' => '11am-1150am',
            'afternoon-early' => '1pm-150pm',
            'afternoon-late' => '4pm-450pm',
            'evening' => '5pm-550pm',
            '10:00' => '10am-1050am',
            '11:00' => '11am-1150am',
            '12:00' => '12pm-1250pm',
            '13:00' => '1pm-150pm',
            '14:00' => '2pm-250pm',
            '15:00' => '3pm-350pm',
            '16:00' => '4pm-450pm',
            '17:00' => '5pm-550pm',
            '18:00' => '6pm-650pm',
        ];

        if (isset($mapping[$clean])) {
            return $mapping[$clean];
        }

        return $clean;
    }
}

