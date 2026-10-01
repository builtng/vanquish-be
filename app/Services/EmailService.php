<?php

namespace App\Services;

use App\Models\EmailLog;
use App\Models\Client;
use App\Models\TrainingCounsellor;
use App\Models\ClientTcMatch;
use App\Models\Message;
use App\Models\ActivityLog;
use Illuminate\Support\Facades\Mail;
use App\Mail\DynamicEmail;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

class EmailService
{
    /**
     * Build and send the service agreement email to a client.
     */
    public function isAgreementSigned(Client $client): bool
    {
        return $client->agreement_status === 'signed'
            || !empty($client->agreement_signed_at)
            || in_array($client->stage, ['Agreement Signed', 'Matched with TC', 'Sessions Bookable', 'Sessions Booked', 'Active Therapy']);
    }

    /**
     * Build and send the service agreement email to a client.
     */
    public function sendAgreementEmail(Client $client): bool
    {
        if (!$client->email) {
            return false;
        }

        if ($this->isAgreementSigned($client)) {
            Log::info("EmailService: Skipped sending agreement email to client {$client->id} as agreement is already signed.");
            return true;
        }

        $companySettings = DB::table('company_settings')->pluck('value', 'key')->toArray();
        $useInternalAgreement = ($companySettings['use_internal_agreement_form'] ?? '0') === '1';
        $baseUrl = rtrim(config('app.frontend_url', 'http://localhost:3000'), '/');

        if ($useInternalAgreement) {
            $slug = ($client->service_type === 'Low Cost') ? 'low-cost' : 'mid-range';
            $agreementUrl = $baseUrl . '/agreement/' . $slug . '?uuid=' . $client->uuid;
        } else {
            $jotformUrl = trim($companySettings['jotform_agreement_url'] ?? '');
            if (!empty($jotformUrl)) {
                $agreementUrl = $jotformUrl;
            } else {
                $slug = ($client->service_type === 'Low Cost') ? 'low-cost' : 'mid-range';
                $agreementUrl = $baseUrl . '/agreement/' . $slug . '?uuid=' . $client->uuid;
            }
        }

        $success = $this->sendAndLog(
            $client,
            'agreement_sent',
            [
                'client_name'    => $client->name,
                'email'          => $client->email,
                'agreement_url'  => $agreementUrl,
                'agreement_link' => $agreementUrl,
                'agreementUrl'   => $agreementUrl,
                'link'           => $agreementUrl,
                'url'            => $agreementUrl,
            ]
        );

        if ($success) {
            $client->update([
                'agreement_status'  => 'sent',
                'agreement_sent_at' => now(),
                'stage'             => 'Agreement Sent',
            ]);
        }

        return $success;
    }

    /**
     * Send the "you've been matched" client emails (agreement link) and the
     * TC assignment notification, then log the activity. Shared by admin-driven
     * matching (ClientController::assignMatch) and client self-service
     * counsellor selection (ClientBookingController::chooseCounsellor).
     */
    public function sendMatchNotification(
        Client $client,
        TrainingCounsellor $tc,
        ClientTcMatch $match,
        ?int $actorUserId = null,
        ?string $notes = null,
        $matchScore = 0
    ): void {
        $companySettings = DB::table('company_settings')->pluck('value', 'key')->toArray();
        $useInternalAgreement = ($companySettings['use_internal_agreement_form'] ?? '0') === '1';
        $baseUrl = rtrim(config('app.frontend_url', 'http://localhost:3000'), '/');

        if ($useInternalAgreement) {
            $slug = ($client->service_type === 'Low Cost') ? 'low-cost' : 'mid-range';
            $agreementUrl = $baseUrl . '/agreement/' . $slug . '?uuid=' . $client->uuid;
        } else {
            $jotformUrl = trim($companySettings['jotform_agreement_url'] ?? '');
            if (!empty($jotformUrl)) {
                $agreementUrl = $jotformUrl;
            } else {
                $slug = ($client->service_type === 'Low Cost') ? 'low-cost' : 'mid-range';
                $agreementUrl = $baseUrl . '/agreement/' . $slug . '?uuid=' . $client->uuid;
            }
        }

        if ($client->email) {
            $isAlreadySigned = $this->isAgreementSigned($client);

            $this->sendAndLog(
                $client,
                $isAlreadySigned ? 'client_matched' : 'match_assigned',
                [
                    'client_name'    => $client->name,
                    'tc_name'        => $tc->abbreviated_name,
                    'email'          => $client->email,
                    'agreement_url'  => $agreementUrl,
                    'agreement_link' => $agreementUrl,
                    'agreementUrl'   => $agreementUrl,
                    'link'           => $agreementUrl,
                    'url'            => $agreementUrl,
                    'booking_link'   => $baseUrl . '/client-booking?uuid=' . $client->uuid,
                    'allocated_day'  => $client->allocated_day,
                    'allocated_time' => $client->allocated_time,
                    'allocated_slot' => ($client->allocated_day && $client->allocated_time) ? "{$client->allocated_day}s at {$client->allocated_time}" : null,
                ]
            );

            if (!$isAlreadySigned) {
                $client->update([
                    'agreement_status' => 'sent',
                    'agreement_sent_at' => now(),
                    'stage' => 'Agreement Sent',
                ]);
            } else {
                $client->update([
                    'stage' => 'Matched with TC',
                    'matched_tc_id' => $tc->id,
                    'matched_date' => now(),
                ]);
            }
        }

        // Send email to TC (Trainee and Qualified counsellors)
        if ($tc->email) {
            if (!app()->environment('testing')) {
                sleep(1); // Delay to prevent SMTP burst limits
            }

            $loginUrl = $baseUrl . '/counsellor-login';
            $firstName = $tc->first_name ?: (explode(' ', trim($tc->name))[0] ?? 'there');
            $loginButtonHtml = '<div style="text-align: center; margin: 32px 0;"><a href="' . $loginUrl . '" style="display: inline-block; background-color: #6f1d56; color: #ffffff; padding: 14px 32px; text-decoration: none; border-radius: 8px; font-weight: 600; font-size: 16px; box-shadow: 0 2px 4px rgba(111, 29, 86, 0.2);">Log in to Portal</a></div>';

            $this->sendAndLog(
                $tc->email,
                'tc_match_notification',
                [
                    'first_name'   => $firstName,
                    'tc_name'      => $tc->name,
                    'login_url'    => $loginUrl,
                    'portal_url'   => $loginUrl,
                    'portal_link'  => $loginUrl,
                    'login_button' => $loginButtonHtml,
                    'Login Button' => $loginButtonHtml,
                    'client_name'  => $client->abbreviated_name ?: $client->name,
                    'client_age'   => $client->age ?? 'Not provided',
                    'service_type' => $client->service_type ?? 'Not provided',
                    'match_score'  => $matchScore,
                    'notes'        => $notes ?? 'N/A',
                    'dashboard_url'=> $loginUrl,
                ],
                $tc
            );
        }

        // Create an in-portal message for the TC to attend to
        try {
            $clientDisplayName = $client->abbreviated_name ?: $client->name;
            Message::create([
                'from_user_id' => $actorUserId ?? \App\Models\User::where('role', 'admin')->first()?->id,
                'to_tc_id' => $tc->id,
                'subject' => "New Client Match: {$clientDisplayName}",
                'message' => "You have been matched with a new client: {$clientDisplayName}." .
                    ($client->service_type ? "\nService Type: {$client->service_type}" : '') .
                    (($client->allocated_day && $client->allocated_time) ? "\nAllocated Slot: {$client->allocated_day}s at {$client->allocated_time}" : '') .
                    ($notes ? "\n\nNotes: {$notes}" : '') .
                    "\n\nPlease review the client details in your portal and attend to the next steps.",
                'type' => 'staff_to_counsellor',
                'related_client_id' => $client->id,
            ]);
        } catch (\Exception $e) {
            Log::warning("Could not create in-portal match message for TC {$tc->id}: " . $e->getMessage());
        }

        ActivityLog::create([
            'user_id' => $actorUserId,
            'action' => 'match_notifications_sent',
            'model_type' => ClientTcMatch::class,
            'model_id' => $match->id,
            'description' => "Match notification emails sent to {$client->name} and {$tc->name}",
            'ip_address' => request()->ip(),
        ]);
    }

    public function sendAndLog($recipient, string $templateName, array $placeholders, $model = null): bool
    {
        $email = '';
        if (is_object($recipient)) {
            $email = $recipient->email ?? '';
            if (is_null($model)) {
                $model = $recipient;
            }
        } else {
            $email = (string) $recipient;
        }

        $logData = [
            'email' => $email,
            'template_name' => $templateName,
            'payload' => $placeholders,
            'status' => 'pending',
        ];

        if ($model instanceof \App\Models\Client) {
            $logData['client_id'] = $model->id;
        }

        $log = EmailLog::create($logData);

        $maxAttempts = 2;
        $attempt = 0;
        $lastError = null;

        while ($attempt < $maxAttempts) {
            $attempt++;
            try {
                Mail::to($email)->send(new DynamicEmail($templateName, $placeholders));

                $log->update([
                    'status' => 'sent',
                    'sent_at' => now(),
                    'error_message' => null,
                ]);

                return true;
            } catch (\Throwable $e) {
                $lastError = $e->getMessage();
                Log::warning("EmailService attempt {$attempt}/{$maxAttempts} failed for {$email} ({$templateName}): {$lastError}");
                
                if ($attempt < $maxAttempts) {
                    usleep(500000); // 0.5s pause before retry
                }
            }
        }

        Log::error("Failed to send email to {$email} ({$templateName}) after {$maxAttempts} attempts: {$lastError}");

        $log->update([
            'status' => 'failed',
            'error_message' => $lastError,
        ]);

        return false;
    }

    /**
     * Retry a specific failed email log
     */
    public function retry(EmailLog $log): bool
    {
        if (!$log->payload) {
            $log->update(['error_message' => 'Cannot retry: payload missing']);
            return false;
        }

        try {
            Mail::to($log->email)->send(new DynamicEmail($log->template_name, $log->payload));

            $log->update([
                'status' => 'sent',
                'sent_at' => now(),
                'error_message' => null
            ]);

            return true;
        } catch (\Throwable $e) {
            $log->update([
                'error_message' => 'Retry failed: ' . $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Retry failed emails
     */
    public function retryFailedEmails($limit = 10)
    {
        $failedLogs = EmailLog::where('status', 'failed')
            ->orderBy('created_at', 'asc')
            ->limit($limit)
            ->get();

        $successCount = 0;
        foreach ($failedLogs as $log) {
            if ($this->retry($log)) {
                $successCount++;
            }
        }
        return $successCount;
    }
}
