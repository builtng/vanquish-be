<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Schedule feedback form emails to be sent monthly (checks for clients eligible every month)
Schedule::command('clients:send-feedback-forms')
    ->monthly()
    ->description('Send feedback form emails to eligible clients (every 3 months)');

// Schedule booking reminders - check every hour for clients needing reminders (48hrs before deadline)
Schedule::command('bookings:send-reminders')
    ->hourly()
    ->description('Send booking reminders to Low Cost clients 48 hours before deadline');

// Schedule auto-deduction - check daily for clients who missed booking deadline
Schedule::command('bookings:apply-auto-deduction')
    ->daily()
    ->description('Apply auto-deduction (3 sessions instead of 4) for clients who missed booking deadline');

// Trainee interview reminders
Schedule::command('app:send-trainee-reminders')
    ->everyTenMinutes()
    ->description('Send 48h and 2h reminders for trainee interviews');
