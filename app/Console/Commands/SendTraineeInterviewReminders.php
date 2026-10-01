<?php

namespace App\Console\Commands;

use App\Models\TraineeApplication;
use App\Mail\DynamicEmail;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class SendTraineeInterviewReminders extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:send-trainee-reminders';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Send 48h and 2h reminders for trainee interviews';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $now = Carbon::now();

        // 48h Reminders
        $apps48h = TraineeApplication::where('status', 'Stage 3 Interview Booked')
            ->where('reminder_48h_sent', false)
            ->get()
            ->filter(function ($app) use ($now) {
                if (!isset($app->interview_data['datetime'])) return false;
                $dt = Carbon::parse($app->interview_data['datetime']);
                return $dt->isAfter($now) && $dt->diffInHours($now) <= 48;
            });

        foreach ($apps48h as $app) {
            $this->sendReminder($app, '48h');
            $app->update(['reminder_48h_sent' => true]);
        }

        // 2h Reminders
        $apps2h = TraineeApplication::where('status', 'Stage 3 Interview Booked')
            ->where('reminder_2h_sent', false)
            ->get()
            ->filter(function ($app) use ($now) {
                if (!isset($app->interview_data['datetime'])) return false;
                $dt = Carbon::parse($app->interview_data['datetime']);
                return $dt->isAfter($now) && $dt->diffInHours($now) <= 2;
            });

        foreach ($apps2h as $app) {
            $this->sendReminder($app, '2h');
            $app->update(['reminder_2h_sent' => true]);
        }

        $this->info("Reminders processed: " . ($apps48h->count() + $apps2h->count()));
    }

    private function sendReminder($app, $type)
    {
        $sent = app(\App\Services\EmailService::class)->sendAndLog(
            $app->email,
            'trainee_interview_reminder',
            [
                'first_name' => $app->first_name,
                'scheduled_at' => Carbon::parse($app->interview_data['datetime'])->format('l, F jS @ H:i'),
                'zoom_link' => $app->interview_data['zoom_link'] ?? 'Will be provided shortly',
            ]
        );

        if ($sent) {
            Log::info("Trainee {$type} reminder sent to {$app->email}");
        }
    }
}
