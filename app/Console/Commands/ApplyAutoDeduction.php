<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Client;
use App\Models\Session;
use Carbon\Carbon;
use Illuminate\Support\Facades\Mail;
use App\Mail\DynamicEmail;

class ApplyAutoDeduction extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'bookings:apply-auto-deduction';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Apply auto-deduction for Low Cost clients who missed booking deadline (3 sessions instead of 4)';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Checking for clients who missed booking deadline...');

        // Find Low Cost clients with booking deadlines that have passed
        $today = Carbon::today();

        $clients = Client::where('service_type', 'Low Cost')
            ->whereNotNull('next_booking_deadline')
            ->where('next_booking_deadline', '<', $today)
            ->whereNotNull('matched_tc_id')
            ->where('agreement_status', 'signed')
            ->get();

        $deductionCount = 0;

        foreach ($clients as $client) {
            // Check if auto-deduction already applied
            $nextSession = $client->getNextSessionNeedingBooking();

            if ($nextSession && !$nextSession->auto_deduction_applied) {
                // Check if client has booked anything after the last booked
                // session (if they have, skip). Strictly after: the last
                // session itself is always "scheduled" and must not count.
                $hasBooked = Session::where('client_id', $client->id)
                    ->where('scheduled_at', '>', $nextSession->scheduled_at)
                    ->where('status', 'scheduled')
                    ->exists();

                if (!$hasBooked) {
                    // Apply auto-deduction: create 3 sessions instead of 4
                    $created = $this->createAutoDeductionSessions($client, $nextSession);

                    // Mark auto-deduction as applied
                    $nextSession->update(['auto_deduction_applied' => true]);

                    // Next deadline: 48 hours before the week after the last
                    // new session (same rule as a normal block booking).
                    $client->update([
                        'next_booking_deadline' => $this->deadlineAfter(end($created)),
                    ]);

                    // Notify client
                    if ($client->email) {
                        $baseUrl = rtrim(config('app.frontend_url'), '/');
                        $bookingUrl = $baseUrl . '/client-booking?' . http_build_query(['uuid' => $client->uuid]);

                        app(\App\Services\EmailService::class)->sendAndLog(
                            $client,
                            'auto_deduction_applied',
                            [
                                'client_name' => $client->name,
                                'booking_url' => $bookingUrl
                            ]
                        );
                    }

                    $deductionCount++;
                    $this->info("Auto-deduction applied for {$client->name} ({$client->email})");
                }
            }
        }

        $this->info("Applied auto-deduction for {$deductionCount} clients.");
        return 0;
    }

    /**
     * Create 3 sessions for auto-deduction
     */
    private function createAutoDeductionSessions(Client $client, Session $nextSession): array
    {
        // $nextSession is the LAST session already booked, so the new block
        // starts one week after it (it used to start on the same date,
        // double-booking that slot).
        $startDate = Carbon::parse($nextSession->scheduled_at)->addWeek();
        $created = [];

        // Create 3 sessions (weekly)
        for ($i = 0; $i < 3; $i++) {
            $sessionDate = $startDate->copy()->addWeeks($i);
            $isLast = $i === 2;

            $created[] = Session::create([
                'client_id' => $client->id,
                'tc_id' => $client->matched_tc_id,
                'session_type' => 'low_cost',
                'scheduled_at' => $sessionDate->format('Y-m-d H:i:s'),
                'status' => 'scheduled',
                'is_block_booking' => true,
                'block_number' => $i + 1,
                'total_sessions_in_block' => 3,
                'payment_status' => 'pending',
                'auto_deduction_applied' => true,
                'booking_deadline' => $isLast ? Carbon::parse($sessionDate)->addWeek()->subHours(48)->format('Y-m-d') : null,
            ]);
        }

        return $created;
    }

    private function deadlineAfter(Session $lastSession): string
    {
        return Carbon::parse($lastSession->scheduled_at)->addWeek()->subHours(48)->format('Y-m-d');
    }
}
