<?php

namespace App\Services;

use App\Models\ActivityLog;
use App\Models\Client;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ClientFeedbackService
{
    /**
     * Check if a client has reached a feedback milestone (N sessions completed)
     * and if enabled, send the feedback form email once per milestone.
     */
    public function sendMilestoneFeedbackIfEligible(Client $client): bool
    {
        if (empty($client->email)) {
            return false;
        }

        // Fetch company settings
        $settings = DB::table('company_settings')
            ->whereIn('key', ['feedback_form_enabled', 'feedback_form_session_number', 'feedback_form_url'])
            ->pluck('value', 'key');

        $enabled = ($settings['feedback_form_enabled'] ?? '1') === '1';
        if (!$enabled) {
            return false;
        }

        $feedbackUrl = trim($settings['feedback_form_url'] ?? '');
        if (empty($feedbackUrl)) {
            // Cannot send feedback form if admin has not provided the URL
            Log::info("Feedback form email skipped for client {$client->id}: feedback_form_url is empty");
            return false;
        }

        $milestoneStep = max(1, (int) ($settings['feedback_form_session_number'] ?? 4));
        $completedSessions = $client->getCompletedSessionsCount();

        if ($completedSessions < $milestoneStep) {
            return false;
        }

        // Calculate milestone reached: highest multiple of milestoneStep <= completedSessions
        $milestone = (int) (floor($completedSessions / $milestoneStep) * $milestoneStep);
        if ($milestone < $milestoneStep) {
            return false;
        }

        $sentMilestones = is_array($client->feedback_milestones_sent)
            ? $client->feedback_milestones_sent
            : (is_string($client->feedback_milestones_sent) ? json_decode($client->feedback_milestones_sent, true) ?? [] : []);

        // Enforce: Send once per client per milestone
        if (in_array($milestone, $sentMilestones, true)) {
            return false;
        }

        // Send feedback email using the configured URL
        $emailService = app(EmailService::class);
        $success = $emailService->sendAndLog(
            $client,
            'feedback_form',
            [
                'client_name' => $client->name,
                'feedback_url' => $feedbackUrl,
                'milestone' => $milestone,
                'session_number' => $milestone,
            ]
        );

        if ($success) {
            $sentMilestones[] = $milestone;
            $client->feedback_milestones_sent = array_values(array_unique($sentMilestones));
            $client->last_feedback_milestone = $milestone;
            $client->last_feedback_sent_at = now();
            $client->save();

            ActivityLog::create([
                'user_id' => auth()->id(),
                'action' => 'feedback_form_sent',
                'model_type' => Client::class,
                'model_id' => $client->id,
                'client_id' => $client->id,
                'description' => "Feedback form email sent to {$client->name} ({$client->email}) after completing session milestone {$milestone}",
                'ip_address' => request()->ip() ?? '127.0.0.1',
            ]);

            return true;
        }

        return false;
    }
}
