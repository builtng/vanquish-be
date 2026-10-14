<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class E02PlacementApplicationUploadTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_upload_document_separately_under_10mb()
    {
        Storage::fake('public');

        $file = UploadedFile::fake()->create('fitness_cert.pdf', 1024, 'application/pdf'); // 1MB

        $response = $this->postJson('/api/tc-intake/upload-document', [
            'file' => $file,
            'field' => 'fitnessTopractice',
        ]);

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
            'message' => 'Document uploaded successfully',
        ]);
        $this->assertNotNull($response->json('url'));
        $this->assertNotNull($response->json('file_path'));
    }

    public function test_document_over_10mb_is_rejected_with_exact_message()
    {
        Storage::fake('public');

        // 11 MB file (11264 KB)
        $file = UploadedFile::fake()->create('large_dbs.pdf', 11264, 'application/pdf');

        $response = $this->postJson('/api/tc-intake/upload-document', [
            'file' => $file,
            'field' => 'dbs',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['file']);
        $this->assertStringContainsString(
            'This file is larger than 10 MB. Please upload a smaller file.',
            $response->json('errors.file.0')
        );
    }

    public function test_tc_intake_accepts_pre_uploaded_document_urls()
    {
        Storage::fake('public');

        $payload = [
            'name' => 'Jane Trainee',
            'email' => 'jane.trainee@example.com',
            'phone' => '07123456789',
            'fitnessTopractice' => 'http://localhost/storage/tc_intake_forms/documents/fitnessTopractice/fit.pdf',
            'qualifications' => 'http://localhost/storage/tc_intake_forms/documents/qualifications/qual.pdf',
            'dbs' => 'http://localhost/storage/tc_intake_forms/documents/dbs/dbs.pdf',
            'cv' => 'http://localhost/storage/tc_intake_forms/documents/cv/cv.pdf',
            'validId' => 'http://localhost/storage/tc_intake_forms/documents/validId/id.pdf',
            'insurance' => 'http://localhost/storage/tc_intake_forms/documents/insurance/ins.pdf',
        ];

        $response = $this->postJson('/api/tc-intake', $payload);

        $response->assertStatus(201);
        $this->assertDatabaseHas('tc_intake_forms', [
            'email' => 'jane.trainee@example.com',
            'fitness_to_practice' => 'http://localhost/storage/tc_intake_forms/documents/fitnessTopractice/fit.pdf',
            'qualifications' => 'http://localhost/storage/tc_intake_forms/documents/qualifications/qual.pdf',
            'dbs_certificate' => 'http://localhost/storage/tc_intake_forms/documents/dbs/dbs.pdf',
            'cv' => 'http://localhost/storage/tc_intake_forms/documents/cv/cv.pdf',
            'valid_id' => 'http://localhost/storage/tc_intake_forms/documents/validId/id.pdf',
            'insurance_certificate' => 'http://localhost/storage/tc_intake_forms/documents/insurance/ins.pdf',
        ]);
    }
}
