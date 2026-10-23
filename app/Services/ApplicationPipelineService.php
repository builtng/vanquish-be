<?php

namespace App\Services;

use App\Models\ActivityLog;
use App\Models\TraineeApplicationSetting;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class ApplicationPipelineService
{
    protected EmailService $emailService;

    public function __construct(EmailService $emailService)
    {
        $this->emailService = $emailService;
    }

    /**
     * Send interview invitation link (Stage 3).
     */
    public function sendInterviewLink(Model $application, ?User $user = null): array
    {
        if ($application->status === 'Rejected') {
            throw new \InvalidArgumentException('Cannot invite a rejected applicant.');
        }

        $bookingLink = TraineeApplicationSetting::getByKey(
            'placement_interview_link',
            config('services.trafft.booking_url', 'https://vanquishtherapies.co.uk/placement-interview/')
        );
        $inductionDate = TraineeApplicationSetting::getByKey(
            'next_induction_date',
            config('services.trafft.next_induction_date', 'Monday, 19th January, 10:00am')
        );

        $this->emailService->sendAndLog($application->email, $this->getTemplate($application, 'trainee_stage_three_invite'), [
            'first_name'     => $application->first_name,
            'full_name'      => trim($application->first_name . ' ' . $application->last_name),
            'booking_link'   => $bookingLink,
            'induction_date' => $inductionDate,
        ], $application);

        $oldStatus = $application->status;
        $zoomLink = TraineeApplicationSetting::getByKey(
            'default_zoom_link',
            config('services.trafft.default_zoom_link', 'https://zoom.us/j/vanquishtherapies')
        );

        $interviewData = is_array($application->interview_data) ? $application->interview_data : [];
        if (empty($interviewData['trafft'])) {
            $interviewData['trafft'] = [
                'appointment_id' => 'MANUAL-' . strtoupper(Str::random(6)),
                'meeting_id'     => rand(100000000, 999999999),
                'date'           => 'TBC',
                'time'           => 'TBC',
                'host_name'      => 'Vanquish Admin',
                'zoom_link'      => $zoomLink,
            ];
        }

        $application->update([
            'status'         => 'Stage 3 Interview Booked',
            'interview_data' => $interviewData,
        ]);

        $this->logActivity(
            $application,
            'application_interview_link_sent',
            "Interview link sent. Status updated from {$oldStatus} to Stage 3 Interview Booked for " . ($application->name ?? $application->first_name),
            ['old_status' => $oldStatus, 'new_status' => 'Stage 3 Interview Booked'],
            $user
        );

        return [
            'message'     => 'Interview link sent successfully',
            'status'      => $application->fresh()->status,
            'application' => $application->fresh(),
        ];
    }

    /**
     * Record interview attendance (attended or no-show).
     */
    public function recordAttendance(Model $application, bool $attended, ?string $notes = null, ?string $attendedAt = null, ?User $user = null): array
    {
        $status = $attended ? 'Interview Attended' : 'Interview No Show';
        $oldStatus = $application->status;

        $interviewData = is_array($application->interview_data) ? $application->interview_data : [];
        $interviewData['attendance'] = [
            'attended'    => $attended,
            'attended_at' => $attendedAt ?? now()->toIso8601String(),
            'notes'       => $notes,
            'recorded_by' => $user?->id,
        ];

        $application->update([
            'status'         => $status,
            'interview_data' => $interviewData,
        ]);

        $this->logActivity(
            $application,
            'application_interview_attendance_recorded',
            "Interview attendance recorded ({$status}) for " . ($application->name ?? $application->first_name),
            ['old_status' => $oldStatus, 'status' => $status, 'attended' => $attended],
            $user
        );

        if (!$attended) {
            $this->emailService->sendAndLog($application->email, $this->getTemplate($application, 'trainee_interview_not_attended'), [
                'first_name' => $application->first_name,
            ], $application);
        }

        return [
            'message'     => 'Attendance recorded',
            'status'      => $status,
            'application' => $application->fresh(),
        ];
    }

    /**
     * Make final placement decision (Accepted, Rejected, Hold/Pending).
     */
    public function makeDecision(Model $application, string $decision, ?string $inductionDate = null, ?string $notes = null, ?User $user = null): array
    {
        if ($decision === 'Accept') {
            $decision = 'Accepted';
        } elseif ($decision === 'Reject') {
            $decision = 'Rejected';
        } elseif ($decision === 'Hold') {
            $decision = 'Pending';
        }

        $oldStatus = $application->status;
        $newStatus = $decision === 'Pending' ? 'Hold' : $decision;

        $interviewData = is_array($application->interview_data) ? $application->interview_data : [];
        $interviewData['decision'] = [
            'outcome'        => $decision,
            'decided_at'     => now()->toIso8601String(),
            'decided_by'     => $user?->id,
            'induction_date' => $inductionDate,
            'notes'          => $notes,
        ];

        $updates = [
            'status'         => $newStatus,
            'interview_data' => $interviewData,
        ];

        if ($inductionDate) {
            $updates['induction_date'] = $inductionDate;
        }

        if ($decision === 'Accepted') {
            $onboarding = is_array($application->onboarding_data) ? $application->onboarding_data : [];
            $onboarding['checklist'] = array_merge([
                'four_way_agreement' => false,
                'therapy_form'       => false,
                'induction_attended' => false,
                'portal_setup'       => false,
                'policies_signed'    => false,
            ], $onboarding['checklist'] ?? []);
            $onboarding['induction_date'] = $inductionDate ?? $application->induction_date ?? 'To be confirmed';

            $updates['onboarding_data'] = $onboarding;

            if (empty($application->placement_response_token)) {
                $updates['placement_response_token'] = Str::random(40);
            }
        }

        $application->update($updates);

        $this->logActivity(
            $application,
            'application_placement_decision',
            "Placement decision '{$decision}' recorded for " . ($application->name ?? $application->first_name),
            ['decision' => $decision, 'old_status' => $oldStatus, 'new_status' => $newStatus],
            $user
        );

        if ($decision === 'Accepted') {
            $this->sendAcceptanceAndPaperworkEmails($application, $inductionDate);
        } elseif ($decision === 'Rejected') {
            $this->emailService->sendAndLog($application->email, $this->getTemplate($application, 'trainee_placement_rejection'), [
                'first_name' => $application->first_name,
            ], $application);
        }

        return [
            'message'     => "Decision recorded: {$decision}",
            'status'      => $newStatus,
            'application' => $application->fresh(),
        ];
    }

    /**
     * Send induction invitation specifically.
     */
    public function sendInductionInvite(Model $application, ?string $inductionDate = null, ?User $user = null): array
    {
        $inductionDate = $inductionDate
            ?? $application->induction_date
            ?? TraineeApplicationSetting::getByKey('next_induction_date', config('services.trafft.next_induction_date', 'Monday, 19th January, 10:00am'));

        return $this->makeDecision($application, 'Accepted', $inductionDate, 'Induction invite dispatched', $user);
    }

    /**
     * Record induction attendance (attended or no-show).
     */
    public function recordInductionAttendance(Model $application, bool $attended, ?string $notes = null, ?User $user = null): array
    {
        $wasAlreadyAttended = $application->status === 'Induction Attended';
        $newStatus = $attended ? 'Induction Attended' : 'Induction No-Show';
        $oldStatus = $application->status;

        $onboarding = is_array($application->onboarding_data) ? $application->onboarding_data : [];
        if (!isset($onboarding['checklist'])) {
            $onboarding['checklist'] = [];
        }
        $onboarding['checklist']['induction_attended'] = $attended;
        $onboarding['induction_notes'] = $notes;

        $application->update([
            'status'          => $newStatus,
            'onboarding_data' => $onboarding,
        ]);

        $this->logActivity(
            $application,
            'application_induction_attendance',
            "Induction attendance recorded ({$newStatus}) for " . ($application->name ?? $application->first_name),
            ['old_status' => $oldStatus, 'new_status' => $newStatus, 'attended' => $attended],
            $user
        );

        if ($attended && !$wasAlreadyAttended) {
            $this->emailService->sendAndLog($application->email, $this->getTemplate($application, 'trainee_induction_completed'), [
                'first_name' => $application->first_name,
            ], $application);
        } elseif (!$attended) {
            $this->emailService->sendAndLog($application->email, $this->getTemplate($application, 'trainee_induction_not_attended'), [
                'first_name' => $application->first_name,
            ], $application);
        }

        return [
            'message'     => 'Induction status recorded',
            'status'      => $newStatus,
            'application' => $application->fresh(),
        ];
    }

    /**
     * Update application status through general pipeline stages.
     */
    public function updateStatus(Model $application, string $status, ?string $inductionDate = null, ?User $user = null): array
    {
        $oldStatus = $application->status;
        $updates = [
            'status' => $status,
        ];

        if ($inductionDate) {
            $updates['induction_date'] = $inductionDate;
        }

        // If manually moved to Stage 3 Interview Booked and no data exists, set defaults
        if ($status === 'Stage 3 Interview Booked' && empty($application->interview_data)) {
            $zoomLink = TraineeApplicationSetting::getByKey(
                'default_zoom_link',
                config('services.trafft.default_zoom_link', 'https://zoom.us/j/vanquishtherapies')
            );
            $updates['interview_data'] = [
                'trafft' => [
                    'appointment_id' => 'MANUAL-' . strtoupper(Str::random(6)),
                    'meeting_id'     => rand(100000000, 999999999),
                    'date'           => 'TBC',
                    'time'           => 'TBC',
                    'host_name'      => 'Vanquish Admin',
                    'zoom_link'      => $zoomLink,
                ]
            ];
        }

        // If Accepted, ensure response token
        if ($status === 'Accepted' && empty($application->placement_response_token)) {
            $updates['placement_response_token'] = Str::random(40);
        }

        $application->update($updates);

        $this->logActivity(
            $application,
            'application_status_updated',
            "Application status updated from {$oldStatus} to {$status} for " . ($application->name ?? $application->first_name),
            ['old_status' => $oldStatus, 'new_status' => $status],
            $user
        );

        // TRIGGER EMAIL: If Accepted
        if ($status === 'Accepted' && $oldStatus !== 'Accepted') {
            $this->sendAcceptanceAndPaperworkEmails($application, $inductionDate ?? $application->induction_date);
        }

        // TRIGGER EMAIL: If Rejected
        if ($status === 'Rejected' && $oldStatus !== 'Rejected') {
            $this->emailService->sendAndLog($application->email, $this->getTemplate($application, 'trainee_placement_rejection'), [
                'first_name' => $application->first_name,
            ], $application);
        }

        // TRIGGER EMAIL: If Interview No Show
        if ($status === 'Interview No Show' && $oldStatus !== 'Interview No Show') {
            $this->emailService->sendAndLog($application->email, $this->getTemplate($application, 'trainee_interview_not_attended'), [
                'first_name' => $application->first_name,
            ], $application);
        }

        // TRIGGER EMAIL: If Induction No-Show
        if ($status === 'Induction No-Show' && $oldStatus !== 'Induction No-Show') {
            $this->emailService->sendAndLog($application->email, $this->getTemplate($application, 'trainee_induction_not_attended'), [
                'first_name' => $application->first_name,
            ], $application);
        }

        // TRIGGER EMAIL: If Induction Attended
        if ($status === 'Induction Attended' && $oldStatus !== 'Induction Attended') {
            $this->emailService->sendAndLog($application->email, $this->getTemplate($application, 'trainee_induction_completed'), [
                'first_name' => $application->first_name,
            ], $application);
        }

        return [
            'message'     => 'Status updated successfully',
            'status'      => $status,
            'application' => $application->fresh(),
        ];
    }

    /**
     * Send acceptance and paperwork emails.
     */
    protected function sendAcceptanceAndPaperworkEmails(Model $application, ?string $inductionDate): void
    {
        $frontendUrl = rtrim(config('app.frontend_url') ?: 'https://vqtmanagement.com', '/');
        $therapyFormUrl = $frontendUrl . '/therapy-form?email=' . urlencode($application->email);

        if (empty($application->placement_response_token)) {
            $application->update(['placement_response_token' => Str::random(40)]);
        }

        $effectiveInductionDate = $inductionDate
            ?? $application->induction_date
            ?? TraineeApplicationSetting::getByKey('next_induction_date', config('services.trafft.next_induction_date', 'Monday, 19th January, 10:00am'));

        $this->emailService->sendAndLog($application->email, $this->getTemplate($application, 'trainee_placement_acceptance'), [
            'first_name'          => $application->first_name,
            'induction_date'      => $effectiveInductionDate,
            'induction_zoom_link' => config('services.trafft.default_zoom_link', 'https://zoom.us/j/vanquish-induction'),
            'meeting_id'          => TraineeApplicationSetting::getByKey('induction_meeting_id', config('services.trafft.induction_meeting_id', '216 124 5208')),
            'passcode'            => TraineeApplicationSetting::getByKey('induction_passcode', config('services.trafft.induction_passcode', '')),
            'therapy_form_url'    => $therapyFormUrl,
        ], $application);

        $frontendUrl = rtrim(config('app.frontend_url'), '/');
        $this->emailService->sendAndLog($application->email, $this->getTemplate($application, 'trainee_onboarding_paperwork'), [
            'first_name'              => $application->first_name,
            'agreement_download_link' => $frontendUrl . '/templates/4-way-agreement-trainee.docx',
            'therapy_form_url'        => $therapyFormUrl,
        ], $application);
    }

    /**
     * Check if application is a Qualified Counsellor application.
     */
    protected function isQcApplication(Model $application): bool
    {
        return $application instanceof \App\Models\QcApplication
            || ($application->getTable() ?? null) === 'qc_applications'
            || ($application->application_type ?? null) === 'Qualified';
    }

    /**
     * Resolve the appropriate template based on application type.
     */
    protected function getTemplate(Model $application, string $traineeTemplate): string
    {
        return $this->isQcApplication($application)
            ? str_replace('trainee_', 'qc_', $traineeTemplate)
            : $traineeTemplate;
    }

    /**
     * Log an activity record for an application.
     */
    protected function logActivity(Model $application, string $action, string $description, array $changes = [], ?User $user = null): void
    {
        try {
            ActivityLog::create([
                'user_id'     => $user?->id ?? request()->user()?->id ?? null,
                'action'      => $action,
                'model_type'  => get_class($application),
                'model_id'    => $application->id,
                'description' => $description,
                'changes'     => $changes,
                'ip_address'  => request()->ip(),
            ]);
        } catch (\Throwable $e) {
            Log::error("Failed to log activity for {$action}: " . $e->getMessage());
        }
    }
}
