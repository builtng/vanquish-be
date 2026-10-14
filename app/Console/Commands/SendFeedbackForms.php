<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Client;
use App\Services\ClientFeedbackService;
use Illuminate\Support\Facades\DB;

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
    protected $description = 'Send feedback form emails to clients when completed sessions reach milestone N';

    /**
     * Execute the console command.
     */
    public function handle(ClientFeedbackService $feedbackService)
    {
        $this->info('Starting milestone-based feedback form email process...');

        $settings = DB::table('company_settings')
            ->whereIn('key', ['feedback_form_enabled', 'feedback_form_session_number', 'feedback_form_url'])
            ->pluck('value', 'key');

        $enabled = ($settings['feedback_form_enabled'] ?? '1') === '1';
        if (!$enabled) {
            $this->warn('Feedback form emails are disabled in admin settings.');
            return Command::SUCCESS;
        }

        $feedbackUrl = trim($settings['feedback_form_url'] ?? '');
        if (empty($feedbackUrl)) {
            $this->warn('Feedback form URL is not configured in admin settings. Skipping.');
            return Command::SUCCESS;
        }

        $milestoneStep = max(1, (int) ($settings['feedback_form_session_number'] ?? 4));
        $this->info("Checking clients for milestone step {$milestoneStep} with URL: {$feedbackUrl}");

        $clients = Client::whereIn('stage', ['Active Therapy', 'Completed'])
            ->whereNotNull('email')
            ->get();

        $sent = 0;
        foreach ($clients as $client) {
            if ($feedbackService->sendMilestoneFeedbackIfEligible($client)) {
                $this->info("✓ Feedback form sent to {$client->name} ({$client->email}) for milestone {$client->last_feedback_milestone}");
                $sent++;
            }
        }

        $this->info("Completed: {$sent} feedback form email(s) sent.");

        return Command::SUCCESS;
    }
}
