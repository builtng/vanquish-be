<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Client;
use App\Models\Session;
use Carbon\Carbon;
use Illuminate\Support\Facades\Mail;
use App\Mail\DynamicEmail;

class SendBookingReminders extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'bookings:send-reminders';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Send booking reminders to Low Cost clients 48 hours before their booking deadline';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Checking for clients needing booking reminders...');

        // Find Low Cost clients with booking deadlines in 48 hours
        $deadlineDate = Carbon::now()->addHours(48)->format('Y-m-d');

        $clients = Client::where('service_type', 'Low Cost')
            ->where('next_booking_deadline', $deadlineDate)
            ->whereNotNull('matched_tc_id')
            ->where('agreement_status', 'signed')
            ->get();

        $reminderCount = 0;

        foreach ($clients as $client) {
            // Check if reminder already sent
            $nextSession = $client->getNextSessionNeedingBooking();

            if ($nextSession && !$nextSession->booking_reminder_sent) {
                // Send reminder email
                if ($client->email) {
                    $baseUrl = rtrim(config('app.frontend_url'), '/');
                    $bookingUrl = $baseUrl . '/client-booking?' . http_build_query(['uuid' => $client->uuid]);

                    $success = app(\App\Services\EmailService::class)->sendAndLog(
                        $client,
                        'booking_deadline_reminder',
                        [
                            'client_name' => $client->name,
                            'deadline_date' => $client->next_booking_deadline,
                            'booking_url' => $bookingUrl
                        ]
                    );

                    if ($success) {
                        // Mark reminder as sent
                        $nextSession->update(['booking_reminder_sent' => true]);
                        $reminderCount++;

                        $this->info("Reminder sent to {$client->name} ({$client->email})");
                    } else {
                        $this->error("Failed to send reminder to {$client->email}");
                    }
                }
            }
        }

        $this->info("Sent {$reminderCount} booking reminders.");
        return 0;
    }
}
