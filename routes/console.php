<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Scheduled jobs
// The live host (Hostinger) disables proc_open, so `schedule:run` cannot
// start a command in a separate process (Schedule::command needs that).
//
// On Hostinger each job has its own cron line that runs it directly:
//
//   0 0 1 * *     php artisan clients:send-feedback-forms     (monthly)
//   0 * * * *     php artisan bookings:send-reminders         (hourly)
//   0 0 * * *     php artisan bookings:apply-auto-deduction   (daily)
//   */10 * * * *  php artisan app:send-trainee-reminders      (every 10 min)
//
// The entries below run the same commands in-process (Schedule::call, no
// proc_open), so `schedule:run` also works where it is used instead. Use
// ONE of the two set-ups, never both, or each job runs twice.

$scheduled = [
    // Feedback form emails, checked monthly (clients eligible every 3 months)
    'clients:send-feedback-forms' => ['monthly', 'Send feedback form emails to eligible clients (every 3 months)'],
    // Low Cost booking reminders, 48 hours before the deadline
    'bookings:send-reminders' => ['hourly', 'Send booking reminders to Low Cost clients 48 hours before deadline'],
    // Auto-deduction (3 sessions instead of 4) for missed booking deadlines
    'bookings:apply-auto-deduction' => ['daily', 'Apply auto-deduction (3 sessions instead of 4) for clients who missed booking deadline'],
    // Trainee interview reminders (48h and 2h)
    'app:send-trainee-reminders' => ['everyTenMinutes', 'Send 48h and 2h reminders for trainee interviews'],
];

foreach ($scheduled as $command => [$frequency, $description]) {
    Schedule::call(fn () => Artisan::call($command))
        ->name("{$command}: {$description}")
        ->{$frequency}();
}
