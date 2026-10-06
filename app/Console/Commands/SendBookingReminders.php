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
    protected $description = 'Send booking reminders to Low Cost clients 3 days before their booking deadline';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Checking for clients needing booking reminders...');

        // Find Low Cost clients with booking deadlines in 3 days (or within 3 days if not yet notified)
        $deadlineTarget = Carbon::now()->addDays(3)->format('Y-m-d');
        $today = Carbon::now()->format('Y-m-d');

        $clients = Client::where('service_type', 'Low Cost')
            ->whereNotNull('next_booking_deadline')
            ->whereDate('next_booking_deadline', '>=', $today)
            ->whereDate('next_booking_deadline', '<=', $deadlineTarget)
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
