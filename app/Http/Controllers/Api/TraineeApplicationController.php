<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\TraineeApplication;
use App\Models\TraineeApplicationSetting;
use App\Models\TrainingCounsellor;
use App\Models\Person;
use App\Models\User;
use App\Models\ActivityLog;
use App\Models\ConsultationSlot;
use App\Mail\DynamicEmail;
use App\Jobs\SendTraineeStageTwoInvite;
use App\Jobs\SendInterviewReminder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Carbon\Carbon;
use App\Services\EmailService;

class TraineeApplicationController extends Controller
{
    protected $emailService;

    public function __construct(EmailService $emailService)
    {
        $this->emailService = $emailService;
    }
    /**
     * Display a listing of applications.
     */
    public function index(Request $request)
    {
        $query = TraineeApplication::orderBy('created_at', 'desc');

        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('first_name', 'like', "%{$search}%")
                  ->orWhere('last_name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%");
            });
        }

        if ($request->has('status') && $request->status !== 'all') {
            $query->where('status', $request->status);
        }

        if ($request->has('source') && $request->source !== 'all') {
            $query->where('source', $request->source);
        }

        return response()->json($query->paginate($request->input('per_page', 20)));
    }

    /**
     * Get count of new trainee applications.
     */
    public function pendingCount()
    {
        $count = TraineeApplication::where('status', 'New Application')->count();
        return response()->json($count);
    }

    /**
     * Store a newly created application (Internal Form).
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'first_name' => 'required|string|max:255',
            'last_name' => 'required|string|max:255',
            'email' => 'required|email|max:255',
            'phone' => 'nullable|string|max:20',
            'address' => 'nullable|string',
            'institution' => 'nullable|string|max:255',
            'course_name' => 'nullable|string|max:255',
            'course_duration' => 'nullable|string|max:255',
            'experience_background' => 'nullable|string',
        ]);

        $normalizedEmail = strtolower(trim($validated['email']));
        $fullName = trim($validated['first_name'] . ' ' . $validated['last_name']);

        // Find or create permanent Person identity
        $person = Person::findOrCreateByEmail($normalizedEmail, $fullName, $validated['phone'] ?? null);

        $validated['person_id'] = $person->id;
        $validated['email'] = $normalizedEmail;
        $validated['source'] = 'internal_form';
        $validated['status'] = 'New Application';

        // Every submission is stored as its OWN separate record with unique ID
        $application = TraineeApplication::create($validated);

        ActivityLog::create([
            'user_id' => $request->user()->id ?? null,
            'action' => 'trainee_application_submitted_internal',
            'model_type' => TraineeApplication::class,
            'model_id' => $application->id,
            'description' => "Trainee application submitted internally for {$application->first_name} {$application->last_name}",
            'changes' => $validated,
            'ip_address' => $request->ip(),
        ]);

        // Send Email #1 Immediately
        $this->emailService->sendAndLog($application->email, 'trainee_application_received', [
            'first_name' => $application->first_name,
            'last_name' => $application->last_name,
            'email' => $application->email,
        ], $application);

        // Send Email #2 instantly
        try {
            SendTraineeStageTwoInvite::dispatch($application);
        } catch (\Exception $e) {
            Log::error("Failed to dispatch trainee stage two invite: " . $e->getMessage());
        }

        return response()->json([
            'message' => 'Application submitted successfully',
            'application' => $application,
        ], 201);
    }

    /**
     * Send initial invitation to apply (before application exists).
     */
    public function inviteEmail(Request $request)
    {
        $validated = $request->validate([
            'email'      => 'required|email',
            'first_name' => 'required|string|max:255',
        ]);

        try {
            $this->emailService->sendAndLog($validated['email'], 'trainee_initial_invite', [
                'first_name' => $validated['first_name'],
                'trainee_application_url' => config('app.frontend_url') . "/counsellor/apply"
            ]);

            return response()->json(['message' => 'Invitation sent successfully']);
        } catch (\Exception $e) {
            Log::error("Failed to send initial trainee invite: " . $e->getMessage());
            return response()->json(['message' => 'Failed to send invitation'], 500);
        }
    }

    /**
     * Display the specified application.
     */
    public function show(TraineeApplication $traineeApplication)
    {
        $normalizedEmail = strtolower(trim($traineeApplication->email));
        $previousSubmissions = TraineeApplication::where('id', '!=', $traineeApplication->id)
            ->where(function ($q) use ($traineeApplication, $normalizedEmail) {
                if ($traineeApplication->person_id) {
                    $q->where('person_id', $traineeApplication->person_id);
                }
                if ($normalizedEmail) {
                    $q->orWhere('email', $normalizedEmail);
                }
            })
            ->orderBy('created_at', 'desc')
            ->get();

        $traineeApplication->setAttribute('previous_submissions', $previousSubmissions);

        return response()->json($traineeApplication);
    }

    public function updateStatus(Request $request, TraineeApplication $traineeApplication)
    {
        $validated = $request->validate([
            'status' => 'required|string', // Support full status workflow
            'induction_date' => 'nullable|string', // Optional date for acceptance
        ]);

        $oldStatus = $traineeApplication->status;
        $traineeApplication->update([
            'status' => $validated['status'],
            'induction_date' => $validated['induction_date'] ?? $traineeApplication->induction_date
        ]);

        try {
            ActivityLog::create([
                'user_id' => optional($request->user())->id,
                'action' => 'trainee_application_status_updated',
                'model_type' => TraineeApplication::class,
                'model_id' => $traineeApplication->id,
                'description' => "Application status updated from {$oldStatus} to {$validated['status']} for " . ($traineeApplication->name ?? 'applicant'),
                'changes' => ['old_status' => $oldStatus, 'new_status' => $validated['status']],
                'ip_address' => $request->ip(),
            ]);
        } catch (\Exception $e) {
            Log::error("Failed to log trainee status update: " . $e->getMessage());
        }
 
        // If manually moved to Stage 3 Interview Booked and no data exists, set defaults
        if ($validated['status'] === 'Stage 3 Interview Booked' && empty($traineeApplication->interview_data)) {
            $zoomLink = TraineeApplicationSetting::getByKey('default_zoom_link', config('services.trafft.default_zoom_link', 'https://zoom.us/j/vanquishtherapies'));
            
            $traineeApplication->update([
                'interview_data' => [
                    'trafft' => [
                        'appointment_id' => 'MANUAL-' . strtoupper(\Illuminate\Support\Str::random(6)),
                        'meeting_id' => rand(100000000, 999999999),
                        'date' => 'TBC',
                        'time' => 'TBC',
                        'host_name' => 'Vanquish Admin',
                        'zoom_link' => $zoomLink,
                    ]
                ]
            ]);
        }
 
        // TRIGGER EMAIL: Stage 2 Invitation (HireVire link)
        if ($validated['status'] === 'Stage 2 Invited') {
            $hirevireUrl = config('services.hirevire.interview_url', 'https://app.hirevire.com/applications/091820fa-6fef-45e0-97e8-d714fc0b27cf');
            $this->emailService->sendAndLog($traineeApplication->email, 'trainee_stage_two_invite', [
                'first_name' => $traineeApplication->first_name,
                'full_name' => $traineeApplication->first_name . ' ' . $traineeApplication->last_name,
                'interview_url' => $hirevireUrl,
                'interview_link' => $hirevireUrl, // Keep both for template compatibility
                'deadline_date' => now()->addDays(3)->format('l, j F Y'),
            ], $traineeApplication);
        }

        // TRIGGER EMAIL: Video interview received (Email #3) - covers the case
        // where an admin manually marks this stage (e.g. video was received
        // via another channel) rather than it coming through the HireVire
        // webhook, which sends this same email itself.
        if ($validated['status'] === 'Stage 2 Video Submitted' && $oldStatus !== 'Stage 2 Video Submitted') {
            $this->emailService->sendAndLog($traineeApplication->email, 'trainee_video_interview_received', [
                'first_name' => $traineeApplication->first_name,
            ], $traineeApplication);
        }

        // Note: the Stage 3 (face-to-face interview) invitation email is sent
        // exclusively by sendStageThreeInvite() below, which is the dedicated
        // action for that step. It used to also fire here on "Stage 2
        // Approved", which duplicated the email every time an admin clicked
        // through the normal Stage 2 Approved -> Stage 3 Interview Booked
        // sequence.

        // TRIGGER EMAIL: If Accepted
        if ($validated['status'] === 'Accepted') {
            $companySettings = \Illuminate\Support\Facades\DB::table('company_settings')->pluck('value', 'key')->toArray();

            $therapyFormUrl = ($companySettings['use_internal_agreement_form'] ?? '0') === '1'
                                ? rtrim(config('app.frontend_url'), '/') . '/therapy-form'
                                : ($companySettings['jotform_therapy_form_url'] ?? 'https://form.jotform.com/241002800146035');

            if (empty($traineeApplication->placement_response_token)) {
                $traineeApplication->update(['placement_response_token' => \Illuminate\Support\Str::random(40)]);
            }
            $responseUrl = rtrim(config('app.frontend_url'), '/') . '/placement-response/' . $traineeApplication->placement_response_token;

            $this->emailService->sendAndLog($traineeApplication->email, 'trainee_placement_acceptance', [
                'first_name'          => $traineeApplication->first_name,
                'induction_date'      => $validated['induction_date'] ?? 'To be confirmed',
                'induction_zoom_link' => config('services.trafft.default_zoom_link', 'https://zoom.us/j/vanquish-induction'),
                'meeting_id'          => \App\Models\TraineeApplicationSetting::getByKey('induction_meeting_id', config('services.trafft.induction_meeting_id', '216 124 5208')),
                'passcode'            => \App\Models\TraineeApplicationSetting::getByKey('induction_passcode', config('services.trafft.induction_passcode', '')),
                'therapy_form_url'    => $therapyFormUrl,
            ], $traineeApplication);

            $frontendUrl = rtrim(config('app.frontend_url'), '/');
            $this->emailService->sendAndLog($traineeApplication->email, 'trainee_onboarding_paperwork', [
                'first_name' => $traineeApplication->first_name,
                'agreement_download_link' => $frontendUrl . '/templates/4-way-agreement-trainee.docx',
                'therapy_form_url' => $therapyFormUrl,
            ], $traineeApplication);
        }

        // TRIGGER EMAIL: If Rejected (from ANY stage)
        $postStage1Statuses = ['New Application', 'Stage 2 Invited', 'Stage 2 Video Submitted', 'Stage 2 Approved', 'Stage 3 Interview Booked', 'Interview Attended'];
        if ($validated['status'] === 'Rejected' && in_array($oldStatus, $postStage1Statuses)) {
            $this->emailService->sendAndLog($traineeApplication->email, 'trainee_placement_rejection', [
                'first_name' => $traineeApplication->first_name,
            ], $traineeApplication);
        }

        // TRIGGER EMAIL: If Interview No Show
        if ($validated['status'] === 'Interview No Show' && $oldStatus !== 'Interview No Show') {
            $this->emailService->sendAndLog($traineeApplication->email, 'trainee_interview_not_attended', [
                'first_name' => $traineeApplication->first_name,
            ], $traineeApplication);
        }

        // TRIGGER EMAIL: If Induction No-Show
        if ($validated['status'] === 'Induction No-Show' && $oldStatus !== 'Induction No-Show') {
            $this->emailService->sendAndLog($traineeApplication->email, 'trainee_induction_not_attended', [
                'first_name' => $traineeApplication->first_name,
            ], $traineeApplication);
        }

        return response()->json($traineeApplication);
    }

    /**
     * Remove the specified application.
     */
    public function destroy(TraineeApplication $traineeApplication)
    {
        $traineeApplication->archived_at = now();
        $traineeApplication->save();
        $traineeApplication->delete();

        ActivityLog::create([
            'user_id' => request()->user()->id ?? null,
            'action' => 'trainee_application_archived',
            'model_type' => TraineeApplication::class,
            'model_id' => $traineeApplication->id,
            'description' => "Trainee application archived for {$traineeApplication->first_name} {$traineeApplication->last_name}",
            'ip_address' => request()->ip(),
        ]);

        return response()->json(['message' => 'Application archived successfully']);
    }

    /**
     * Generate Zoom Meeting SDK Signature.
     */
    public function getZoomSignature(Request $request, $id)
    {
        $application = TraineeApplication::findOrFail($id);
        $meetingNumber = $application->interview_data['trafft']['meeting_id'] ?? null;
        
        if (!$meetingNumber) {
            return response()->json(['error' => 'No meeting ID associated with this application'], 422);
        }

        $sdkKey = TraineeApplicationSetting::getByKey('zoom_sdk_key', config('services.zoom.sdk_key'));
        $sdkSecret = TraineeApplicationSetting::getByKey('zoom_sdk_secret', config('services.zoom.sdk_secret'));

        if (!$sdkKey || !$sdkSecret) {
            return response()->json(['error' => 'Zoom SDK credentials not configured'], 500);
        }

        $role = 1; // 1 for host/admin, 0 for participant
        $iat = time() - 30; // issued at
        $exp = $iat + 60 * 60 * 2; // expires in 2 hours

        $header = base64_encode(json_encode(['alg' => 'HS256', 'typ' => 'JWT']));
        
        $payload = base64_encode(json_encode([
            'sdkKey' => $sdkKey,
            'mn' => (int)$meetingNumber,
            'role' => $role,
            'iat' => $iat,
            'exp' => $exp,
            'appKey' => $sdkKey,
            'tokenExp' => $exp
        ]));

        $signature = hash_hmac('sha256', "$header.$payload", $sdkSecret, true);
        $base64Signature = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($signature));
        
        $token = "$header.$payload.$base64Signature";

        return response()->json([
            'signature' => $token,
            'sdk_key' => $sdkKey,
            'meeting_number' => $meetingNumber,
            'password' => '', // Meetings might require password, usually empty for Trafft unless set
        ]);
    }

    /**
     * Settings for Trainee Applications.
     */
     public function getSettings()
    {
        $priorityQuestions = TraineeApplicationSetting::getByKey('priority_questions', []);
        $zoomLink = TraineeApplicationSetting::getByKey('default_zoom_link', config('services.trafft.default_zoom_link'));
        $inductionDate = TraineeApplicationSetting::getByKey('next_induction_date', config('services.trafft.next_induction_date'));
        $interviewLink = TraineeApplicationSetting::getByKey('placement_interview_link', config('services.trafft.booking_url'));
        $zoomMode = TraineeApplicationSetting::getByKey('zoom_mode', 'embedded');
        $sdkKey = TraineeApplicationSetting::getByKey('zoom_sdk_key', '');
        $sdkSecret = TraineeApplicationSetting::getByKey('zoom_sdk_secret', '');

        return response()->json([
            'priority_questions' => $priorityQuestions,
            'default_zoom_link' => $zoomLink,
            'next_induction_date' => $inductionDate,
            'placement_interview_link' => $interviewLink,
            'zoom_mode' => $zoomMode,
            'zoom_sdk_key' => $sdkKey,
            'zoom_sdk_secret' => $sdkSecret ? '********' : '' // Mask secret
        ]);
    }

    public function updateSettings(Request $request)
    {
        $validated = $request->validate([
            'priority_questions' => 'sometimes|array',
            'default_zoom_link' => 'sometimes|string|nullable',
            'next_induction_date' => 'sometimes|string|nullable',
            'placement_interview_link' => 'sometimes|string|nullable',
            'zoom_mode' => 'sometimes|string|in:embedded,web_client',
            'zoom_sdk_key' => 'sometimes|string|nullable',
            'zoom_sdk_secret' => 'sometimes|string|nullable',
        ]);

        if (isset($validated['priority_questions'])) {
            TraineeApplicationSetting::setByKey('priority_questions', $validated['priority_questions']);
        }

        if (isset($validated['default_zoom_link'])) {
            TraineeApplicationSetting::setByKey('default_zoom_link', $validated['default_zoom_link']);
        }

        if (isset($validated['next_induction_date'])) {
            TraineeApplicationSetting::setByKey('next_induction_date', $validated['next_induction_date']);
        }

        if (isset($validated['placement_interview_link'])) {
            TraineeApplicationSetting::setByKey('placement_interview_link', $validated['placement_interview_link']);
        }

        if (isset($validated['zoom_mode'])) {
            TraineeApplicationSetting::setByKey('zoom_mode', $validated['zoom_mode']);
        }

        if (isset($validated['zoom_sdk_key'])) {
            TraineeApplicationSetting::setByKey('zoom_sdk_key', $validated['zoom_sdk_key']);
        }

        if (isset($validated['zoom_sdk_secret']) && $validated['zoom_sdk_secret'] !== '********') {
            TraineeApplicationSetting::setByKey('zoom_sdk_secret', $validated['zoom_sdk_secret']);
        }
        
        return response()->json([
            'message' => 'Trainee settings updated successfully',
            'priority_questions' => TraineeApplicationSetting::getByKey('priority_questions', []),
            'default_zoom_link' => TraineeApplicationSetting::getByKey('default_zoom_link'),
            'next_induction_date' => TraineeApplicationSetting::getByKey('next_induction_date'),
            'zoom_mode' => TraineeApplicationSetting::getByKey('zoom_mode', 'embedded'),
            'zoom_sdk_key' => TraineeApplicationSetting::getByKey('zoom_sdk_key'),
            'zoom_sdk_secret' => '********'
        ]);
    }

    /**
     * Send Stage 2 Invite manually (Admin override).
     */
    public function sendInvite(TraineeApplication $traineeApplication)
    {
        if ($traineeApplication->status === 'Rejected') {
            return response()->json(['message' => 'Cannot invite a rejected applicant.'], 400);
        }

        SendTraineeStageTwoInvite::dispatchSync($traineeApplication);

        return response()->json([
            'message' => 'Stage 2 invitation sent successfully',
            'status'  => $traineeApplication->fresh()->status,
        ]);
    }

    /**
     * Send Stage 3 Interview Invite manually (Admin override).
     */
    public function sendStageThreeInvite(TraineeApplication $traineeApplication)
    {
        if ($traineeApplication->status === 'Rejected') {
            return response()->json(['message' => 'Cannot invite a rejected applicant.'], 400);
        }

        try {
            $this->emailService->sendAndLog($traineeApplication->email, 'trainee_stage_three_invite', [
                'first_name'     => $traineeApplication->first_name,
                'full_name'      => $traineeApplication->first_name . ' ' . $traineeApplication->last_name,
                'booking_link'   => TraineeApplicationSetting::getByKey('placement_interview_link', config('services.trafft.booking_url', 'https://vanquishtherapies.co.uk/placement-interview/')),
                'induction_date' => TraineeApplicationSetting::getByKey('next_induction_date', config('services.trafft.next_induction_date', 'Monday, 19th January, 10:00am')),
            ], $traineeApplication);

            $oldStatus = $traineeApplication->status;
            $zoomLink = TraineeApplicationSetting::getByKey('default_zoom_link', config('services.trafft.default_zoom_link', 'https://zoom.us/j/vanquishtherapies'));
            
            $traineeApplication->update([
                'status' => 'Stage 3 Interview Booked',
                'interview_data' => empty($traineeApplication->interview_data) ? [
                    'trafft' => [
                        'appointment_id' => 'MANUAL-' . strtoupper(\Illuminate\Support\Str::random(6)),
                        'meeting_id' => rand(100000000, 999999999),
                        'date' => 'TBC',
                        'time' => 'TBC',
                        'host_name' => 'Vanquish Admin',
                        'zoom_link' => $zoomLink,
                    ]
                ] : $traineeApplication->interview_data
            ]);

            try {
                ActivityLog::create([
                    'user_id' => optional(request()->user())->id,
                    'action' => 'trainee_application_status_updated',
                    'model_type' => TraineeApplication::class,
                    'model_id' => $traineeApplication->id,
                    'description' => "Application status updated from {$oldStatus} to Stage 3 Interview Booked for " . ($traineeApplication->first_name . ' ' . $traineeApplication->last_name),
                    'changes' => ['old_status' => $oldStatus, 'new_status' => 'Stage 3 Interview Booked'],
                    'ip_address' => request()->ip(),
                ]);
            } catch (\Exception $e) {
                Log::error("Failed to log trainee status update in sendStageThreeInvite: " . $e->getMessage());
            }
        } catch (\Exception $e) {
            Log::error("Failed to manual send Stage 3 invite: " . $e->getMessage());
            return response()->json(['message' => 'Failed to send invitation.'], 500);
        }

        return response()->json([
            'message' => 'Stage 3 invitation sent successfully',
            'status'  => $traineeApplication->fresh()->status,
        ]);
    }

    /**
     * Step 8 — Record interview attendance (admin marks attended or no-show).
     *
     * POST /api/trainee-applications/{id}/attendance
     */
    public function recordAttendance(Request $request, TraineeApplication $traineeApplication)
    {
        $validated = $request->validate([
            'attended'      => 'required|boolean',
            'notes'         => 'nullable|string|max:2000',
            'attended_at'   => 'nullable|date',
        ]);

        $status = $validated['attended'] ? 'Interview Attended' : 'Interview No Show';

        $interviewData = is_array($traineeApplication->interview_data)
            ? $traineeApplication->interview_data
            : [];

        $traineeApplication->update([
            'status'         => $status,
            'interview_data' => array_merge($interviewData, [
                'attendance' => [
                    'attended'    => $validated['attended'],
                    'attended_at' => $validated['attended_at'] ?? now()->toIso8601String(),
                    'notes'       => $validated['notes'] ?? null,
                    'recorded_by' => $request->user()->id,
                ]
            ]),
        ]);

        try {
            ActivityLog::create([
                'user_id'     => optional($request->user())->id,
                'action'      => 'trainee_interview_attendance_recorded',
                'model_type'  => TraineeApplication::class,
                'model_id'    => $traineeApplication->id,
                'description' => "Interview attendance recorded ({$status}) for " . ($traineeApplication->name ?? 'applicant'),
                'changes'     => ['status' => $status, 'attended' => $validated['attended']],
                'ip_address'  => $request->ip(),
            ]);
        } catch (\Exception $e) {
            Log::error("Failed to log trainee interview attendance: " . $e->getMessage());
        }

        if (!$validated['attended']) {
            $this->emailService->sendAndLog($traineeApplication->email, 'trainee_interview_not_attended', [
                'first_name' => $traineeApplication->first_name,
            ], $traineeApplication);
        }

        return response()->json([
            'message'     => 'Attendance recorded',
            'status'      => $status,
            'application' => $traineeApplication->fresh(),
        ]);
    }

    /**
     * Step 8 — Admin makes final placement decision.
     *
     * POST /api/trainee-applications/{id}/decision
     */
    public function makeDecision(Request $request, TraineeApplication $traineeApplication)
    {
        $validated = $request->validate([
            'decision'       => 'required|in:Accepted,Rejected,Pending,Hold,Accept,Reject',
            'induction_date' => 'nullable|string',
            'notes'          => 'nullable|string|max:2000',
        ]);

        $decision = $validated['decision'];
        if ($decision === 'Accept') {
            $decision = 'Accepted';
        } elseif ($decision === 'Reject') {
            $decision = 'Rejected';
        } elseif ($decision === 'Hold') {
            $decision = 'Pending';
        }

        $oldStatus = $traineeApplication->status;
        $newStatus = $decision === 'Pending' ? 'Hold' : $decision;

        $interviewData = is_array($traineeApplication->interview_data) ? $traineeApplication->interview_data : [];
        $interviewData['decision'] = [
            'outcome'        => $decision,
            'decided_at'     => now()->toIso8601String(),
            'decided_by'     => $request->user()->id,
            'induction_date' => $validated['induction_date'] ?? null,
            'notes'          => $validated['notes'] ?? null,
        ];

        $traineeApplication->update([
            'status'         => $newStatus,
            'interview_data' => $interviewData,
        ]);

        try {
            ActivityLog::create([
                'user_id'     => optional($request->user())->id,
                'action'      => 'trainee_placement_decision',
                'model_type'  => TraineeApplication::class,
                'model_id'    => $traineeApplication->id,
                'description' => "Placement decision '{$decision}' recorded for " . ($traineeApplication->name ?? 'applicant'),
                'changes'     => ['decision' => $decision, 'old_status' => $oldStatus, 'new_status' => $newStatus],
                'ip_address'  => $request->ip(),
            ]);
        } catch (\Exception $e) {
            Log::error("Failed to log trainee placement decision: " . $e->getMessage());
        }

        // Initialize onboarding data if accepted
        if ($decision === 'Accepted') {
            $traineeApplication->update([
                'onboarding_data' => [
                    'checklist' => [
                        'four_way_agreement' => false,
                        'therapy_form'       => false,
                        'induction_attended' => false,
                        'portal_setup'       => false,
                        'policies_signed'    => false,
                    ],
                    'induction_date' => $validated['induction_date'] ?? 'To be confirmed',
                ]
            ]);
        }

        // Send emails (kept from previous step)
        if ($decision === 'Accepted') {
            $companySettings = \Illuminate\Support\Facades\DB::table('company_settings')->pluck('value', 'key')->toArray();
            
            $therapyFormUrl = ($companySettings['use_internal_agreement_form'] ?? '0') === '1'
                                ? rtrim(config('app.frontend_url'), '/') . '/therapy-form'
                                : ($companySettings['jotform_therapy_form_url'] ?? 'https://form.jotform.com/241002800146035');

            if (empty($traineeApplication->placement_response_token)) {
                $traineeApplication->update(['placement_response_token' => \Illuminate\Support\Str::random(40)]);
            }
            $responseUrl = rtrim(config('app.frontend_url'), '/') . '/placement-response/' . $traineeApplication->placement_response_token;

            $this->emailService->sendAndLog($traineeApplication->email, 'trainee_placement_acceptance', [
                'first_name'          => $traineeApplication->first_name,
                'induction_date'      => $validated['induction_date'] ?? 'Monday, 19th January, 10:00am',
                'induction_zoom_link' => config('services.trafft.default_zoom_link', 'https://zoom.us/j/vanquish-induction'),
                'meeting_id'          => \App\Models\TraineeApplicationSetting::getByKey('induction_meeting_id', config('services.trafft.induction_meeting_id', '216 124 5208')),
                'passcode'            => \App\Models\TraineeApplicationSetting::getByKey('induction_passcode', config('services.trafft.induction_passcode', '')),
                'therapy_form_url'    => $therapyFormUrl,
            ], $traineeApplication);

            $frontendUrl = rtrim(config('app.frontend_url'), '/');
            $this->emailService->sendAndLog($traineeApplication->email, 'trainee_onboarding_paperwork', [
                'first_name'              => $traineeApplication->first_name,
                'agreement_download_link' => $frontendUrl . '/templates/4-way-agreement-trainee.docx',
                'therapy_form_url'        => $therapyFormUrl,
            ], $traineeApplication);
        } elseif ($decision === 'Rejected') {
            $this->emailService->sendAndLog($traineeApplication->email, 'trainee_placement_rejection', [
                'first_name' => $traineeApplication->first_name,
            ], $traineeApplication);
        }

        return response()->json([
            'message'     => "Decision recorded: {$decision}",
            'status'      => $newStatus,
            'application' => $traineeApplication->fresh(),
        ]);
    }

    /**
     * Step 10 — Update paperwork status (agreement, therapy form).
     */
    public function updatePaperwork(Request $request, TraineeApplication $traineeApplication)
    {
        $validated = $request->validate([
            'document_key' => 'required|in:four_way_agreement,therapy_form',
            'status'       => 'required|boolean',
        ]);

        $onboarding = is_array($traineeApplication->onboarding_data) 
            ? $traineeApplication->onboarding_data 
            : ['checklist' => []];

        $onboarding['checklist'][$validated['document_key']] = $validated['status'];
        $onboarding['history'][] = [
            'action' => "paperwork_{$validated['document_key']}_updated",
            'status' => $validated['status'],
            'at'     => now()->toIso8601String(),
            'by'     => $request->user()->id,
        ];

        $traineeApplication->update(['onboarding_data' => $onboarding]);

        return response()->json([
            'message' => 'Paperwork status updated',
            'application' => $traineeApplication->fresh()
        ]);
    }

    /**
     * Step 11 — Record induction attendance.
     */
    public function recordInductionAttendance(Request $request, TraineeApplication $traineeApplication)
    {
        $validated = $request->validate([
            'attended' => 'required|boolean',
            'notes'    => 'nullable|string',
        ]);

        $wasAlreadyAttended = $traineeApplication->status === 'Induction Attended';

        $onboarding = $traineeApplication->onboarding_data ?? [];
        $onboarding['checklist']['induction_attended'] = $validated['attended'];
        $onboarding['induction_notes'] = $validated['notes'];

        $traineeApplication->update([
            'status'          => $validated['attended'] ? 'Induction Attended' : 'Induction No-Show',
            'onboarding_data' => $onboarding
        ]);

        try {
            ActivityLog::create([
                'user_id'     => optional($request->user())->id,
                'action'      => 'trainee_induction_attendance',
                'model_type'  => TraineeApplication::class,
                'model_id'    => $traineeApplication->id,
                'description' => "Induction attendance recorded for " . ($traineeApplication->name ?? 'applicant'),
                'ip_address'  => $request->ip(),
            ]);
        } catch (\Exception $e) {
            Log::error("Failed to log trainee induction attendance: " . $e->getMessage());
        }

        if ($validated['attended'] && !$wasAlreadyAttended) {
            $this->emailService->sendAndLog($traineeApplication->email, 'trainee_induction_completed', [
                'first_name' => $traineeApplication->first_name,
            ], $traineeApplication);
        } elseif (!$validated['attended']) {
            $this->emailService->sendAndLog($traineeApplication->email, 'trainee_induction_not_attended', [
                'first_name' => $traineeApplication->first_name,
            ], $traineeApplication);
        }

        return response()->json(['message' => 'Induction status recorded', 'application' => $traineeApplication->fresh()]);
    }

    /**
     * Step 12 — Send Portal Invitation.
     *
     * Creates a TrainingCounsellor profile and a counsellor User account (if they
     * don't already exist), then emails the trainee their login credentials for
     * the internal Vanquish counsellor portal.
     */
    public function sendPortalInvite(Request $request, TraineeApplication $traineeApplication)
    {
        try {
            $portalUrl = rtrim(config('app.frontend_url', 'https://vqtmanagement.com'), '/') . '/counsellor-login';

            // ── 1. Find or create TrainingCounsellor record ──────────────────────
            // Check withTrashed() so we don't duplicate a soft-deleted counsellor
            $tc = TrainingCounsellor::withTrashed()->where('email', $traineeApplication->email)->first();

            if ($tc && $tc->trashed()) {
                $tc->restore();
            }

            if (!$tc) {
                $tc = TrainingCounsellor::create([
                    'tc_id'       => TrainingCounsellor::generateUniqueTcId(),
                    'name'        => trim($traineeApplication->first_name . ' ' . $traineeApplication->last_name),
                    'email'       => $traineeApplication->email,
                    'phone'       => $traineeApplication->phone ?? null,
                    'gender'      => $traineeApplication->gender ?? null,
                    'ethnicity'   => $traineeApplication->ethnicity ?? null,
                    'sexual_orientation' => $traineeApplication->sexual_orientation ?? null,
                    'date_of_birth' => $traineeApplication->date_of_birth ?? null,
                    'address'     => $traineeApplication->address ?? null,
                    'institution' => $traineeApplication->institution ?? null,
                    'course'      => $traineeApplication->course_name ?? $traineeApplication->course_title ?? null,
                    'training_org_address' => $traineeApplication->college_address ?? null,
                    'tutor_name'  => $traineeApplication->tutor_name ?? null,
                    'tutor_email' => $traineeApplication->tutor_email ?? null,
                    'tutor_phone' => $traineeApplication->tutor_phone ?? null,
                    'placement_lead_name' => $traineeApplication->placement_lead_name ?? null,
                    'placement_lead_email' => $traineeApplication->placement_lead_email ?? null,
                    'placement_lead_phone' => $traineeApplication->placement_lead_phone ?? null,
                    'status'      => 'Active',
                    'joined_date' => now(),
                    'last_activity' => now(),
                ]);
            }

            // ── 2. Find or create User account (role = counsellor) ──────────────
            $existingUser = User::where('email', $traineeApplication->email)->first();
            $temporaryPassword = null;

            if (!$existingUser) {
                $temporaryPassword = \Illuminate\Support\Str::random(10) . '!1Aa';
                $existingUser = User::create([
                    'name'                    => trim($traineeApplication->first_name . ' ' . $traineeApplication->last_name),
                    'email'                   => strtolower(trim($traineeApplication->email)),
                    'password'                => Hash::make($temporaryPassword),
                    'role'                    => 'counsellor',
                    'training_counsellor_id'  => $tc->id,
                ]);
            } elseif ($existingUser->role !== 'counsellor') {
                // Existing user with a different role — update to counsellor and link TC
                $existingUser->update([
                    'role'                   => 'counsellor',
                    'training_counsellor_id' => $tc->id,
                ]);
            }

            // ── 3. Send portal invite email with internal URL ────────────────────
            $this->emailService->sendAndLog($traineeApplication->email, 'trainee_portal_invite', [
                'first_name'         => $traineeApplication->first_name,
                'portal_link'        => $portalUrl,
                'email'              => $traineeApplication->email,
                'temporary_password' => $temporaryPassword ?? '(use your existing password)',
            ], $traineeApplication);

            // ── 4. Update application status ─────────────────────────────────────
            $onboarding = $traineeApplication->onboarding_data ?? [];
            $onboarding['portal_invite_sent_at'] = now()->toIso8601String();
            $onboarding['checklist']['portal_setup'] = true;

            $traineeApplication->update([
                'status'                => 'Onboarding',
                'onboarding_data'       => $onboarding,
                'portal_access_granted' => true,
            ]);

            ActivityLog::create([
                'user_id'     => optional($request->user())->id,
                'action'      => 'trainee_portal_invite_sent',
                'model_type'  => TraineeApplication::class,
                'model_id'    => $traineeApplication->id,
                'description' => "Portal invite sent and counsellor account created for {$traineeApplication->first_name} {$traineeApplication->last_name}",
                'ip_address'  => $request->ip(),
            ]);

            return response()->json([
                'message'            => 'Portal invitation sent and counsellor account created',
                'tc_id'              => $tc->tc_id,
                'account_created'    => $temporaryPassword !== null,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to send portal invite: ' . $e->getMessage(), [
                'exception' => $e,
                'application_id' => $traineeApplication->id,
            ]);

            // Show clean, exact error without exposing DB name, credentials, or raw SQL queries
            $errorMessage = 'Failed to send portal invite.';
            if ($e instanceof \Illuminate\Database\QueryException) {
                if (isset($e->errorInfo[1]) && $e->errorInfo[1] == 1062) {
                    if (preg_match("/Duplicate entry '([^']+)' for key '([^']+)'/", $e->getMessage(), $matches)) {
                        $errorMessage = "Duplicate record detected: The value '{$matches[1]}' is already taken.";
                    } else {
                        $errorMessage = "A counsellor or user with this ID or email already exists.";
                    }
                } else {
                    $errorMessage = "A database error occurred while creating the counsellor account.";
                }
            } else {
                $errorMessage = $e->getMessage();
            }

            return response()->json(['message' => $errorMessage], 500);
        }
    }

    /**
     * Step 14 — Mark as Active Placement.
     */
    public function finalizePlacement(Request $request, TraineeApplication $traineeApplication)
    {
        $traineeApplication->update(['status' => 'Active Placement']);

        try {
            ActivityLog::create([
                'user_id'     => optional($request->user())->id,
                'action'      => 'trainee_active_placement',
                'model_type'  => TraineeApplication::class,
                'model_id'    => $traineeApplication->id,
                'description' => ($traineeApplication->name ?? 'Trainee') . " is now an ACTIVE PLACEMENT",
                'ip_address'  => $request->ip(),
            ]);
        } catch (\Exception $e) {
            Log::error("Failed to log trainee active placement: " . $e->getMessage());
        }

        return response()->json(['message' => 'Trainee is now ACTIVE']);
    }

    /**
     * Handle Stage 2 Video Interview Callback.
     */
    public function handleStageTwoSubmission(Request $request)
    {
        $validated = $request->validate([
            'uuid' => 'required|exists:trainee_applications,uuid',
            'video_url' => 'nullable|string',
        ]);

        $application = TraineeApplication::where('uuid', $validated['uuid'])->firstOrFail();

        $application->update([
            'status' => 'Stage 2 Video Submitted',
            'video_url' => $validated['video_url'] ?? $application->video_url,
        ]);
        
        ActivityLog::create([
            'user_id' => null,
            'action' => 'trainee_video_interview_submitted',
            'model_type' => TraineeApplication::class,
            'model_id' => $application->id,
            'description' => "Video interview submitted for {$application->first_name} {$application->last_name}",
            'ip_address' => $request->ip(),
        ]);

        // Send Email #3
        $this->emailService->sendAndLog($application->email, 'trainee_video_interview_received', [
            'first_name' => $application->first_name,
        ], $application);

        return response()->json(['message' => 'Submission received successfully']);
    }

    /**
     * Get available placement interview slots created by admin.
     */
    public function getAvailableInterviewSlots()
    {
        $slots = ConsultationSlot::where('type', 'placement_interview')
            ->where('status', 'available')
            ->where('consultation_datetime', '>=', Carbon::now())
            ->where(function ($query) {
                $query->whereNull('max_slots')
                    ->orWhereRaw('booked_slots < max_slots');
            })
            ->orderBy('consultation_datetime', 'asc')
            ->get();

        return response()->json($slots);
    }

    /**
     * Book a placement interview slot for a trainee applicant.
     */
    public function bookInterview(Request $request)
    {
        $validated = $request->validate([
            'uuid' => 'required|exists:trainee_applications,uuid',
            'slot_id' => 'required|exists:consultation_slots,id',
        ]);

        $application = TraineeApplication::where('uuid', $validated['uuid'])->firstOrFail();

        return DB::transaction(function () use ($application, $validated, $request) {
            $slot = ConsultationSlot::where('type', 'placement_interview')
                ->lockForUpdate()
                ->findOrFail($validated['slot_id']);

            if ($slot->status === 'closed') {
                return response()->json(['message' => 'This slot is closed.'], 400);
            }

            if ($slot->max_slots && $slot->booked_slots >= $slot->max_slots) {
                return response()->json(['message' => 'This slot is already fully booked.'], 400);
            }

            if (Carbon::parse($slot->consultation_datetime)->isPast()) {
                return response()->json(['message' => 'Cannot book a slot in the past.'], 400);
            }

            $interviewData = is_array($application->interview_data) ? $application->interview_data : [];
            if (isset($interviewData['trafft']['consultation_slot_id']) && $interviewData['trafft']['consultation_slot_id'] === $slot->id) {
                return response()->json(['message' => 'You have already booked this slot.'], 400);
            }

            $slotStart = Carbon::parse($slot->consultation_datetime);
            $booking = [
                'consultation_slot_id' => $slot->id,
                'date' => $slotStart->format('l, M j, Y'),
                'time' => $slotStart->format('g:i A'),
                'formatted_datetime' => $slotStart->format('l, M j, Y \a\t g:i A'),
                'zoom_link' => $slot->zoom_link,
                'employee_name' => $slot->host_name,
            ];

            $application->update([
                'status' => 'Stage 3 Interview Booked',
                'interview_data' => array_merge($interviewData, ['trafft' => $booking]),
            ]);

            $slot->increment('booked_slots');
            $slot->refresh();
            if ($slot->max_slots && $slot->booked_slots >= $slot->max_slots) {
                $slot->update(['status' => 'full']);
            }

            ActivityLog::create([
                'user_id' => null,
                'action' => 'trainee_interview_booked',
                'model_type' => TraineeApplication::class,
                'model_id' => $application->id,
                'description' => "Placement interview booked for {$application->first_name} {$application->last_name}",
                'ip_address' => $request->ip(),
            ]);

            $this->emailService->sendAndLog($application->email, 'trainee_interview_confirmed', [
                'first_name' => $application->first_name,
                'full_name' => $application->first_name . ' ' . $application->last_name,
                'date' => $booking['date'],
                'time' => $booking['time'],
                'scheduled_at' => $booking['formatted_datetime'],
                'zoom_link' => $booking['zoom_link'] ?? '',
                'meeting_id' => '',
                'host_name' => $booking['employee_name'] ?? 'A member of the Vanquish team',
                'service_name' => 'Placement Interview',
            ], $application);

            SendInterviewReminder::dispatch($application)
                ->delay($slotStart->copy()->subHours(48));

            return response()->json([
                'message' => 'Interview scheduled successfully',
                'slot' => $slot,
            ]);
        });
    }

    /**
     * Public: look up a placement-acceptance response link (accept/decline placement
     * + induction RSVP) by its token, for the candidate-facing response page.
     */
    public function getPlacementResponse($token)
    {
        $application = TraineeApplication::where('placement_response_token', $token)->first();

        if (!$application) {
            return response()->json(['message' => 'This link is invalid or has expired.'], 404);
        }

        return response()->json([
            'first_name' => $application->first_name,
            'induction_date' => $application->onboarding_data['induction_date'] ?? $application->induction_date,
            'placement_accepted' => $application->placement_accepted,
            'induction_rsvp' => $application->induction_rsvp,
            'responded_at' => $application->placement_responded_at,
        ]);
    }

    /**
     * Public: record the candidate's response to the placement offer
     * (accept/decline) and their induction attendance RSVP.
     */
    public function submitPlacementResponse(Request $request, $token)
    {
        $application = TraineeApplication::where('placement_response_token', $token)->first();

        if (!$application) {
            return response()->json(['message' => 'This link is invalid or has expired.'], 404);
        }

        $validated = $request->validate([
            'placement_accepted' => 'required|boolean',
            'induction_rsvp' => 'required|boolean',
        ]);

        $application->update([
            'placement_accepted' => $validated['placement_accepted'],
            'induction_rsvp' => $validated['induction_rsvp'],
            'placement_responded_at' => now(),
            'status' => $validated['placement_accepted'] ? $application->status : 'Rejected',
        ]);

        try {
            ActivityLog::create([
                'user_id' => null,
                'action' => 'trainee_placement_response_recorded',
                'model_type' => TraineeApplication::class,
                'model_id' => $application->id,
                'description' => "{$application->first_name} {$application->last_name} " .
                    ($validated['placement_accepted'] ? 'accepted' : 'declined') . ' the placement offer, and ' .
                    ($validated['induction_rsvp'] ? 'confirmed' : 'declined') . ' induction attendance',
                'changes' => $validated,
                'ip_address' => $request->ip(),
            ]);
        } catch (\Exception $e) {
            Log::error("Failed to log trainee placement response: " . $e->getMessage());
        }

        try {
            $dashboardUrl = rtrim(config('app.frontend_url'), '/') . '/dashboard/trainee-applications/' . $application->id;
            $this->emailService->sendAndLog('compliance@vanquishtherapies.co.uk', 'admin_placement_response_notification', [
                'applicant_name' => $application->first_name . ' ' . $application->last_name,
                'applicant_email' => $application->email,
                'placement_accepted' => $validated['placement_accepted'] ? 'Accepted' : 'Declined',
                'induction_rsvp' => $validated['induction_rsvp'] ? 'Attending' : 'Not attending',
                'dashboard_url' => $dashboardUrl,
            ]);
        } catch (\Exception $e) {
            Log::error("Failed to send admin placement response notification: " . $e->getMessage());
        }

        return response()->json([
            'message' => 'Response recorded, thank you.',
            'application' => $application->fresh(),
        ]);
    }
}
