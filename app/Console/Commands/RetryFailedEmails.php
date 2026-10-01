<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class RetryFailedEmails extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'emails:retry {--limit=10 : Number of emails to retry}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Retry sending failed emails from the email_logs table';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $limit = $this->option('limit');
        $failedEmails = \App\Models\EmailLog::where('status', 'failed')
            ->orderBy('created_at', 'asc')
            ->limit($limit)
            ->get();


        if ($failedEmails->isEmpty()) {
            $this->info('No failed emails to retry.');
            return 0;
        }

        $this->info("Retrying {$failedEmails->count()} failed emails...");

        $successCount = app(\App\Services\EmailService::class)->retryFailedEmails($limit);


        $this->info("Retry complete. Successfully sent {$successCount} emails.");
        return 0;
    }
}
