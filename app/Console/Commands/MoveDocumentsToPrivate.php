<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\File;
use App\Models\TraineeApplication;
use App\Models\TrainingCounsellor;
use App\Models\QcApplication;
use App\Models\TcIntakeForm;
use App\Models\Client;

class MoveDocumentsToPrivate extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'documents:move-to-private {--copy : Copy files to private disk instead of moving} {--dry-run : Report files without actually copying or moving} {--summary-only : Output counts, tables/columns and folders only, omitting individual file paths}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Move or copy applicant, counsellor, and client agreement documents from public disk to private disk';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $isCopy = $this->option('copy');
        $isDryRun = $this->option('dry-run');
        $isSummaryOnly = $this->option('summary-only');

        $this->info(($isDryRun ? '[DRY RUN] ' : '') . ($isCopy ? 'Copying' : 'Moving') . ' documents to private disk...');

        $filesToProcess = collect();
        $sourceTracker = []; // $normalizedPath => ['sources' => []]

        // Helper to normalize and add
        $registerFile = function ($rawPath, $sourceLabel) use (&$filesToProcess, &$sourceTracker) {
            if (!$rawPath) return;
            $p = $rawPath;
            if (filter_var($p, FILTER_VALIDATE_URL)) {
                $parsed = parse_url($p);
                $urlPath = $parsed['path'] ?? '';
                if (str_contains($urlPath, '/storage/')) {
                    $p = substr($urlPath, strpos($urlPath, '/storage/') + 9);
                } else {
                    return;
                }
            }
            $p = ltrim($p, '/');
            if (str_starts_with($p, 'storage/')) {
                $p = substr($p, 8);
            }
            // Skip photos/logos
            if (str_starts_with($p, 'tc_photos/') || str_starts_with($p, 'client_photos/') || str_starts_with($p, 'company/')) {
                return;
            }
            if (Storage::disk('public')->exists($p)) {
                $filesToProcess->push($p);
                if (!isset($sourceTracker[$p])) {
                    $sourceTracker[$p] = [];
                }
                if (!in_array($sourceLabel, $sourceTracker[$p])) {
                    $sourceTracker[$p][] = $sourceLabel;
                }
            }
        };

        // 1. Files in tc_intake_forms/documents
        if (Storage::disk('public')->exists('tc_intake_forms/documents')) {
            $files = Storage::disk('public')->allFiles('tc_intake_forms/documents');
            foreach ($files as $file) {
                $registerFile($file, 'Folder: tc_intake_forms/documents');
            }
        }

        // 2. Files in qualified_counsellors (excluding tc_photos)
        if (Storage::disk('public')->exists('qualified_counsellors')) {
            $files = Storage::disk('public')->allFiles('qualified_counsellors');
            foreach ($files as $file) {
                $registerFile($file, 'Folder: qualified_counsellors');
            }
        }

        // 3. Files in trainee_agreements
        if (Storage::disk('public')->exists('trainee_agreements')) {
            $files = Storage::disk('public')->allFiles('trainee_agreements');
            foreach ($files as $file) {
                $registerFile($file, 'Folder: trainee_agreements');
            }
        }

        // 4. Files in signatures
        if (Storage::disk('public')->exists('signatures')) {
            $files = Storage::disk('public')->allFiles('signatures');
            foreach ($files as $file) {
                $registerFile($file, 'Folder: signatures');
            }
        }

        // 4. Collect from database records
        // Trainee applications
        $traineeDocs = ['doc_fitness_to_practise', 'doc_prior_qualifications', 'doc_dbs_certificate', 'doc_cv', 'doc_valid_id', 'doc_indemnity_insurance'];
        foreach (TraineeApplication::all() as $app) {
            foreach ($traineeDocs as $doc) {
                if ($app->$doc) {
                    $registerFile($app->$doc, "trainee_applications.{$doc}");
                }
            }
            if (is_array($app->onboarding_data) && isset($app->onboarding_data['documents'])) {
                foreach ($app->onboarding_data['documents'] as $docKey => $docMeta) {
                    if (is_array($docMeta) && !empty($docMeta['path'])) {
                        $registerFile($docMeta['path'], "trainee_applications.onboarding_data.documents.{$docKey}");
                    }
                }
            }
        }

        // Training counsellors
        $tcDocs = [
            'qualification_document', 'dbs_certificate_qualified', 'insurance_qualified',
            'self_employment_proof', 'professional_membership', 'valid_id_document',
            'dbs_certificate', 'insurance_certificate', 'cv', 'qualifications', 'student_id'
        ];
        foreach (TrainingCounsellor::all() as $tc) {
            foreach ($tcDocs as $doc) {
                if ($tc->$doc) {
                    $registerFile($tc->$doc, "training_counsellors.{$doc}");
                }
            }
        }

        // QC Applications
        $qcDocs = ['qualification_document', 'dbs_certificate_qualified', 'insurance_qualified', 'self_employment_proof', 'professional_membership', 'valid_id_document'];
        foreach (QcApplication::all() as $qc) {
            foreach ($qcDocs as $doc) {
                if ($qc->$doc) {
                    $registerFile($qc->$doc, "qc_applications.{$doc}");
                }
            }
        }

        // TC Intake forms
        if (class_exists(TcIntakeForm::class)) {
            $intakeDocs = ['valid_id', 'dbs_certificate', 'insurance_certificate', 'fitness_to_practice', 'cv', 'qualifications'];
            foreach (TcIntakeForm::all() as $intake) {
                foreach ($intakeDocs as $doc) {
                    if ($intake->$doc) {
                        $registerFile($intake->$doc, "tc_intake_forms.{$doc}");
                    }
                }
            }
        }

        // Client agreement signatures
        foreach (Client::withTrashed()->whereNotNull('agreement_signature_url')->get(['id', 'agreement_signature_url']) as $client) {
            if ($client->agreement_signature_url) {
                $registerFile($client->agreement_signature_url, 'clients.agreement_signature_url');
            }
        }

        $uniqueFiles = $filesToProcess->unique()->values();
        $count = 0;
        $totalBytes = 0;
        $folderStats = [];
        $sourceStats = [];

        foreach ($uniqueFiles as $file) {
            $publicPath = Storage::disk('public')->path($file);
            $privatePath = Storage::disk('private')->path($file);

            if (!File::exists($publicPath)) {
                continue;
            }

            $size = File::size($publicPath);
            $totalBytes += $size;
            $folder = dirname($file);
            if (!isset($folderStats[$folder])) {
                $folderStats[$folder] = ['count' => 0, 'size' => 0];
            }
            $folderStats[$folder]['count']++;
            $folderStats[$folder]['size'] += $size;

            $sources = $sourceTracker[$file] ?? ['Folder Scan'];
            foreach ($sources as $src) {
                $sourceStats[$src] = ($sourceStats[$src] ?? 0) + 1;
            }

            if ($isDryRun) {
                if (!$isSummaryOnly) {
                    $this->line("Would " . ($isCopy ? "copy" : "move") . ": {$file}");
                }
                $count++;
                continue;
            }

            // Ensure destination directory exists
            $privateDir = dirname($privatePath);
            if (!File::isDirectory($privateDir)) {
                File::makeDirectory($privateDir, 0755, true);
            }

            if ($isCopy) {
                File::copy($publicPath, $privatePath);
                $this->line("Copied: {$file}");
            } else {
                File::move($publicPath, $privatePath);
                $this->line("Moved: {$file}");
            }
            $count++;
        }

        // Output summary tables
        $folderRows = [];
        ksort($folderStats);
        foreach ($folderStats as $fld => $stats) {
            $folderRows[] = [
                $fld,
                $stats['count'],
                $this->formatBytes($stats['size']),
            ];
        }

        $sourceRows = [];
        ksort($sourceStats);
        foreach ($sourceStats as $src => $num) {
            $parts = explode('.', $src, 2);
            $sourceRows[] = [
                $parts[0] ?? $src,
                $parts[1] ?? 'Directory Scan',
                $num,
            ];
        }

        $this->newLine();
        $this->info('--- Document Location & Source Summary ---');
        if (!empty($folderRows)) {
            $this->table(['Folder', 'Files Count', 'Size'], $folderRows);
        } else {
            $this->line('No files found on public disk.');
        }

        if (!empty($sourceRows)) {
            $this->newLine();
            $this->table(['Table / Source', 'Column / Field', 'References Found'], $sourceRows);
        }

        $this->newLine();
        $this->info("Total Files: {$count}");
        $this->info("Total Size: " . $this->formatBytes($totalBytes));

        $actionWord = $isCopy ? 'copied' : 'moved';
        $this->info("Successfully {$actionWord} {$count} files to private disk.");

        return 0;
    }

    /**
     * Format bytes into human readable string.
     */
    protected function formatBytes($bytes, $precision = 2): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        $bytes /= pow(1024, $pow);
        return round($bytes, $precision) . ' ' . $units[$pow];
    }
}
