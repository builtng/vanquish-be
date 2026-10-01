<?php

namespace App\Jobs;

use App\Models\TraineeApplication;
use App\Mail\DynamicEmail;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;

/**
 * SendInterviewReminder
 *
 * Dispatched after a Trafft booking is confirmed.
 * Fires the reminder email 48 hours before the interview.
 *
 * Usage:
 *   SendInterviewReminder::dispatch($application)
 *       ->delay($interviewDateTime->subHours(48));
 */
class SendInterviewReminder
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    protected $application;

    public function __construct(TraineeApplication $application)
    {
        $this->application = $application;
    }

    public function handle(): void
    {
        $this->application->refresh();

        // Only send if the interview is still booked (not yet attended, cancelled, or decided)
        $activeStatuses = ['Stage 3 Interview Booked', 'Stage 2 Approved'];
        if (!in_array($this->application->status, $activeStatuses)) {
            Log::info('SendInterviewReminder: Skipped – status changed', [
                'application_id' => $this->application->id,
                'status'         => $this->application->status,
            ]);
            return;
        }

        // Fetch interview data stored from the Trafft webhook
        $interviewData = is_array($this->application->interview_data)
            ? $this->application->interview_data
            : [];

        $trafft = $interviewData['trafft'] ?? [];

        $sent = app(\App\Services\EmailService::class)->sendAndLog(
            $this->application->email,
            'trainee_interview_reminder',
            [
                'first_name'   => $this->application->first_name,
                'scheduled_at' => $trafft['formatted_datetime'] ?? ($trafft['start_time'] ?? 'Your scheduled time'),
                'zoom_link'    => $trafft['zoom_link'] ?? $trafft['meeting_link'] ?? config('services.trafft.default_zoom_link', '#'),
                'date'         => $trafft['date'] ?? '',
                'time'         => $trafft['time'] ?? '',
                'host_name'    => $trafft['employee_name'] ?? 'A member of the Vanquish team',
            ]
        );

        Log::info('SendInterviewReminder: Reminder processed', [
            'application_id' => $this->application->id,
            'email'          => $this->application->email,
            'sent'           => $sent,
        ]);
    }
}
