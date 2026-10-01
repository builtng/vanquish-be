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
use Carbon\Carbon;

class SendTraineeStageTwoInvite

{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Number of times the job may be attempted.
     */
    public int $tries = 3;

    protected $application;

    /**
     * Create a new job instance.
     */
    public function __construct(TraineeApplication $application)
    {
        $this->application = $application;
    }

    /**
     * Execute the job.
     *
     * Sent automatically 48 hours after Stage 1 submission.
     * The admin can also trigger this manually via the dashboard (dispatchSync).
     *
     * HireVire Interview: https://app.hirevire.com/applications/091820fa-6fef-45e0-97e8-d714fc0b27cf
     */
    public function handle(): void
    {
        // Re-fetch fresh data to avoid acting on stale state
        $this->application->refresh();

        // Do not send if the application has been withdrawn, rejected,
        // or has already progressed past Stage 1
        $skipStatuses = [
            'Rejected',
            'Stage 2 Invited',
            'Stage 2 Video Submitted',
            'Stage 2 Approved',
            'Stage 3 Interview Booked',
            'Accepted',
        ];
        if (in_array($this->application->status, $skipStatuses)) {
            Log::info('SendTraineeStageTwoInvite: Skipped – already past Stage 1', [
                'application_id' => $this->application->id,
                'status'         => $this->application->status,
            ]);
            return;
        }

        // Calculate 3-working-day deadline from now
        $deadline = $this->addWorkingDays(now(), 3)->format('l, j F Y');

        // The fixed HireVire interview link for this placement cohort
        // Override via HIREVIRE_INTERVIEW_URL in .env if the link changes
        $hirevireUrl = config(
            'services.hirevire.interview_url',
            'https://app.hirevire.com/applications/091820fa-6fef-45e0-97e8-d714fc0b27cf'
        );

        // Send the Stage 2 invitation email using the centralized EmailService for logging
        $emailService = app(\App\Services\EmailService::class);
        $emailService->sendAndLog(
            $this->application->email, 
            'trainee_stage_two_invite', 
            [
                'first_name'    => $this->application->first_name,
                'full_name'     => $this->application->first_name . ' ' . $this->application->last_name,
                'interview_url' => $hirevireUrl,
                'deadline_date' => $deadline,
            ],
            $this->application
        );

        // Progress status to Stage 2 Invited
        if (in_array($this->application->status, ['New Application', 'Stage 1 Complete', 'pending', 'reviewed'])) {
            $this->application->update(['status' => 'Stage 2 Invited']);
        }

        Log::info('SendTraineeStageTwoInvite: Stage 2 email sent', [
            'application_id' => $this->application->id,
            'email'          => $this->application->email,
            'deadline'       => $deadline,
        ]);
    }

    /**
     * Add N working days (Mon–Fri) to a Carbon date.
     */
    private function addWorkingDays(Carbon $date, int $days): Carbon
    {
        $d = $date->copy();
        $added = 0;
        while ($added < $days) {
            $d->addDay();
            if ($d->isWeekday()) {
                $added++;
            }
        }
        return $d;
    }
}
