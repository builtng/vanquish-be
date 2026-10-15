<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\File;
use App\Models\TraineeApplication;
use App\Models\TrainingCounsellor;
use App\Models\QcApplication;
use App\Models\TcIntakeForm;

class MoveDocumentsToPrivate extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'documents:move-to-private {--copy : Copy files to private disk instead of moving} {--dry-run : Report files without actually copying or moving}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Move or copy applicant and counsellor documents from public disk to private disk';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $isCopy = $this->option('copy');
        $isDryRun = $this->option('dry-run');

        $this->info(($isDryRun ? '[DRY RUN] ' : '') . ($isCopy ? 'Copying' : 'Moving') . ' documents to private disk...');

        $filesToProcess = collect();

        // 1. Files in tc_intake_forms/documents
        if (Storage::disk('public')->exists('tc_intake_forms/documents')) {
            $files = Storage::disk('public')->allFiles('tc_intake_forms/documents');
            foreach ($files as $file) {
                $filesToProcess->push($file);
            }
        }

        // 2. Files in qualified_counsellors (excluding tc_photos)
        if (Storage::disk('public')->exists('qualified_counsellors')) {
            $files = Storage::disk('public')->allFiles('qualified_counsellors');
            foreach ($files as $file) {
                $filesToProcess->push($file);
            }
        }

        // 3. Files in trainee_agreements
        if (Storage::disk('public')->exists('trainee_agreements')) {
            $files = Storage::disk('public')->allFiles('trainee_agreements');
            foreach ($files as $file) {
                $filesToProcess->push($file);
            }
        }

        // 4. Collect from database records
        $dbPaths = collect();

        // Trainee applications
        $traineeDocs = ['doc_fitness_to_practise', 'doc_prior_qualifications', 'doc_dbs_certificate', 'doc_cv', 'doc_valid_id', 'doc_indemnity_insurance'];
        foreach (TraineeApplication::all() as $app) {
            foreach ($traineeDocs as $doc) {
                if ($app->$doc) {
                    $dbPaths->push($app->$doc);
                }
            }
            if (is_array($app->onboarding_data) && isset($app->onboarding_data['documents'])) {
                foreach ($app->onboarding_data['documents'] as $docMeta) {
                    if (is_array($docMeta) && !empty($docMeta['path'])) {
                        $dbPaths->push($docMeta['path']);
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
                    $dbPaths->push($tc->$doc);
                }
            }
        }

        // QC Applications
        $qcDocs = ['qualification_document', 'dbs_certificate_qualified', 'insurance_qualified', 'self_employment_proof', 'professional_membership', 'valid_id_document'];
        foreach (QcApplication::all() as $qc) {
            foreach ($qcDocs as $doc) {
                if ($qc->$doc) {
                    $dbPaths->push($qc->$doc);
                }
            }
        }

        // TC Intake forms
        if (class_exists(TcIntakeForm::class)) {
            $intakeDocs = ['valid_id', 'dbs_certificate', 'insurance_certificate', 'fitness_to_practice', 'cv', 'qualifications'];
            foreach (TcIntakeForm::all() as $intake) {
                foreach ($intakeDocs as $doc) {
                    if ($intake->$doc) {
                        $dbPaths->push($intake->$doc);
                    }
                }
            }
        }

        foreach ($dbPaths as $rawPath) {
            if (!$rawPath) continue;
            // Clean path
            $p = $rawPath;
            if (filter_var($p, FILTER_VALIDATE_URL)) {
                $parsed = parse_url($p);
                $urlPath = $parsed['path'] ?? '';
                if (str_contains($urlPath, '/storage/')) {
                    $p = substr($urlPath, strpos($urlPath, '/storage/') + 9);
                } else {
                    continue; // External URL
                }
            }
            $p = ltrim($p, '/');
            if (str_starts_with($p, 'storage/')) {
                $p = substr($p, 8);
            }
            // Skip photos/logos
            if (str_starts_with($p, 'tc_photos/') || str_starts_with($p, 'client_photos/') || str_starts_with($p, 'company/')) {
                continue;
            }
            if (Storage::disk('public')->exists($p)) {
                $filesToProcess->push($p);
            }
        }

        $uniqueFiles = $filesToProcess->unique()->values();
        $count = 0;

        foreach ($uniqueFiles as $file) {
            $publicPath = Storage::disk('public')->path($file);
            $privatePath = Storage::disk('private')->path($file);

            if (!File::exists($publicPath)) {
                continue;
            }

            if ($isDryRun) {
                $this->line("Would " . ($isCopy ? "copy" : "move") . ": {$file}");
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

        $actionWord = $isCopy ? 'copied' : 'moved';
        $this->info("Successfully {$actionWord} {$count} files to private disk.");

        return 0;
    }
}
