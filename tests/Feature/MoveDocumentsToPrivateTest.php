<?php

namespace Tests\Feature;

use App\Models\QcApplication;
use App\Models\TraineeApplication;
use App\Models\TrainingCounsellor;
use App\Models\Client;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class MoveDocumentsToPrivateTest extends TestCase
{
    use RefreshDatabase;

    public function test_move_documents_to_private_dry_run_reports_files_without_moving()
    {
        Storage::fake('public');
        Storage::fake('private');

        $docPath = 'qualified_counsellors/1/dbs/test_dbs.pdf';
        Storage::disk('public')->put($docPath, 'dbs binary content');

        $tc = TrainingCounsellor::create([
            'tc_id' => 'TC-20001',
            'uuid' => (string) Str::uuid(),
            'name' => 'Alice Test',
            'email' => 'alice@test.com',
            'dbs_certificate_qualified' => $docPath,
        ]);

        $this->artisan('documents:move-to-private --dry-run')
            ->expectsOutputToContain('Would move: ' . $docPath)
            ->expectsOutputToContain('Successfully moved 1 files to private disk.')
            ->assertExitCode(0);

        // File should still exist on public disk and NOT on private disk
        $this->assertTrue(Storage::disk('public')->exists($docPath));
        $this->assertFalse(Storage::disk('private')->exists($docPath));
    }

    public function test_move_documents_to_private_copy_option_copies_and_preserves_public()
    {
        Storage::fake('public');
        Storage::fake('private');

        $docPath = 'tc_intake_forms/documents/valid_id/test_id.png';
        Storage::disk('public')->put($docPath, 'valid id content');

        $this->artisan('documents:move-to-private --copy')
            ->expectsOutputToContain('Copied: ' . $docPath)
            ->expectsOutputToContain('Successfully copied 1 files to private disk.')
            ->assertExitCode(0);

        // File should exist on BOTH public and private disks
        $this->assertTrue(Storage::disk('public')->exists($docPath));
        $this->assertTrue(Storage::disk('private')->exists($docPath));
    }

    public function test_move_documents_to_private_moves_files_and_skips_photos_and_logos()
    {
        Storage::fake('public');
        Storage::fake('private');

        // Document files
        $dbsPath = 'qualified_counsellors/1/dbs/test_dbs.pdf';
        $cvPath = 'tc_intake_forms/documents/cv/test_cv.pdf';
        Storage::disk('public')->put($dbsPath, 'dbs content');
        Storage::disk('public')->put($cvPath, 'cv content');

        // Public photos and logos
        $photoPath = 'tc_photos/1/avatar.jpg';
        $clientPhotoPath = 'client_photos/1/profile.png';
        $logoPath = 'company/logo.png';
        Storage::disk('public')->put($photoPath, 'photo content');
        Storage::disk('public')->put($clientPhotoPath, 'client photo');
        Storage::disk('public')->put($logoPath, 'logo content');

        $tc = TrainingCounsellor::create([
            'tc_id' => 'TC-20002',
            'uuid' => (string) Str::uuid(),
            'name' => 'Bob Test',
            'email' => 'bob@test.com',
            'dbs_certificate_qualified' => $dbsPath,
            'photo' => $photoPath,
        ]);

        $this->artisan('documents:move-to-private')
            ->expectsOutputToContain('Moved: ' . $dbsPath)
            ->expectsOutputToContain('Moved: ' . $cvPath)
            ->assertExitCode(0);

        // Document files must be moved to private disk and deleted from public
        $this->assertTrue(Storage::disk('private')->exists($dbsPath));
        $this->assertTrue(Storage::disk('private')->exists($cvPath));
        $this->assertFalse(Storage::disk('public')->exists($dbsPath));
        $this->assertFalse(Storage::disk('public')->exists($cvPath));

        // Public photos and logos must remain on public disk and NOT be in private
        $this->assertTrue(Storage::disk('public')->exists($photoPath));
        $this->assertTrue(Storage::disk('public')->exists($clientPhotoPath));
        $this->assertTrue(Storage::disk('public')->exists($logoPath));
        $this->assertFalse(Storage::disk('private')->exists($photoPath));
    }

    public function test_move_documents_to_private_summary_only_omits_individual_filenames()
    {
        Storage::fake('public');
        Storage::fake('private');

        $docPath = 'tc_intake_forms/documents/cv/john_doe_cv.pdf';
        Storage::disk('public')->put($docPath, 'sample cv content');

        $tc = TrainingCounsellor::create([
            'tc_id' => 'TC-20003',
            'uuid' => (string) Str::uuid(),
            'name' => 'John Doe',
            'email' => 'john.doe@test.com',
            'dbs_certificate_qualified' => $docPath,
        ]);

        $this->artisan('documents:move-to-private --copy --dry-run --summary-only')
            ->doesntExpectOutput('Would copy: ' . $docPath)
            ->expectsOutputToContain('--- Document Location & Source Summary ---')
            ->expectsOutputToContain('tc_intake_forms/documents/cv')
            ->expectsOutputToContain('training_counsellors')
            ->expectsOutputToContain('Total Files: 1')
            ->assertExitCode(0);

        // Verify public preserved, private not written
        $this->assertTrue(Storage::disk('public')->exists($docPath));
        $this->assertFalse(Storage::disk('private')->exists($docPath));
    }

    public function test_move_documents_to_private_moves_client_agreement_signatures_from_public_to_private()
    {
        Storage::fake('public');
        Storage::fake('private');

        $sigPath = 'signatures/client_agreement_signature_123.png';
        Storage::disk('public')->put($sigPath, 'signature binary png');

        $client = Client::create([
            'uuid' => (string) Str::uuid(),
            'client_id' => 'CL-80001',
            'name' => 'Signature Client',
            'email' => 'sig.client@test.com',
            'agreement_status' => 'signed',
            'agreement_signature_url' => $sigPath,
        ]);

        $this->artisan('documents:move-to-private')
            ->expectsOutputToContain('Moved: ' . $sigPath)
            ->expectsOutputToContain('Successfully moved 1 files to private disk.')
            ->assertExitCode(0);

        // Signature must be on private disk and removed from public disk
        $this->assertTrue(Storage::disk('private')->exists($sigPath));
        $this->assertFalse(Storage::disk('public')->exists($sigPath));
    }

    public function test_move_documents_to_private_dry_run_reports_signatures()
    {
        Storage::fake('public');
        Storage::fake('private');

        $sigPath = 'signatures/dry_run_sig.png';
        Storage::disk('public')->put($sigPath, 'dry run signature');

        $client = Client::create([
            'uuid' => (string) Str::uuid(),
            'client_id' => 'CL-80002',
            'name' => 'Dry Run Client',
            'email' => 'dryrun.client@test.com',
            'agreement_status' => 'signed',
            'agreement_signature_url' => $sigPath,
        ]);

        $this->artisan('documents:move-to-private --dry-run')
            ->expectsOutputToContain('Would move: ' . $sigPath)
            ->expectsOutputToContain('signatures')
            ->expectsOutputToContain('clients')
            ->assertExitCode(0);

        // Dry run must NOT move the file
        $this->assertTrue(Storage::disk('public')->exists($sigPath));
        $this->assertFalse(Storage::disk('private')->exists($sigPath));
    }
}
