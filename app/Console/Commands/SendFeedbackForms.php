<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Client;
use App\Mail\DynamicEmail;
use Illuminate\Support\Facades\Mail;
use Carbon\Carbon;

class SendFeedbackForms extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'clients:send-feedback-forms';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Send feedback form emails to clients who are eligible (3+ months since last feedback)';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Starting feedback form email process...');

        // Get clients who are eligible for feedback forms
        // - Must be in Active Therapy or Completed stage
        // - Must have email address
        // - Must not have received feedback form in last 3 months (or never received one)
        $eligibleClients = Client::whereIn('stage', ['Active Therapy', 'Completed'])
            ->whereNotNull('email')
            ->where(function ($query) {
                $query->whereNull('last_feedback_sent_at')
                    ->orWhereRaw('DATEDIFF(NOW(), last_feedback_sent_at) >= 90');
            })
            ->get();

        $this->info("Found {$eligibleClients->count()} eligible clients.");

        $sent = 0;
        $failed = 0;

        foreach ($eligibleClients as $client) {
            // Double-check eligibility (in case of race conditions)
            if ($client->last_feedback_sent_at) {
                $monthsSince = Carbon::parse($client->last_feedback_sent_at)->diffInMonths(now());
                if ($monthsSince < 3) {
                    $this->warn("Skipping {$client->name} - feedback sent {$monthsSince} month(s) ago");
                    continue;
                }
            }

            // Send email
            $baseUrl = rtrim(config('app.frontend_url'), '/');
            $success = app(\App\Services\EmailService::class)->sendAndLog(
                $client,
                'feedback_form',
                [
                    'client_name' => $client->name,
                    'feedback_url' => $baseUrl . '/feedback-form?' . http_build_query(['uuid' => $client->uuid])
                ]
            );

            if ($success) {
                // Update client record
                $client->update([
                    'last_feedback_sent_at' => now(),
                ]);

                $this->info("✓ Feedback form sent to {$client->name} ({$client->email})");
                $sent++;
            } else {
                $this->error("✗ Failed to send to {$client->name} ({$client->email})");
                $failed++;
            }
        }

        $this->info("\nCompleted: {$sent} sent, {$failed} failed");

        return Command::SUCCESS;
    }
}
