<?php

namespace Tests\Feature;

use App\Models\TraineeApplication;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class E02PlacementApplicationUploadTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_upload_document_separately_under_10mb_and_receives_upload_token()
    {
        Storage::fake('private');

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

        $token = $response->json('upload_token');
        $this->assertNotNull($token);
        $this->assertEquals(40, strlen($token));
        $this->assertTrue(Cache::has("tc_upload_token:{$token}"));

        $storedPath = Cache::get("tc_upload_token:{$token}");
        $this->assertStringStartsWith('tc_intake_forms/documents/fitnessTopractice/', $storedPath);
        Storage::disk('private')->assertExists($storedPath);
    }

    public function test_upload_document_does_not_modify_existing_application()
    {
        Storage::fake('private');

        $app = TraineeApplication::create([
            'first_name' => 'Alice',
            'last_name' => 'Original',
            'email' => 'alice.orig@example.com',
            'status' => 'New Application',
            'doc_cv' => 'tc_intake_forms/documents/cv/original_cv.pdf',
        ]);

        $file = UploadedFile::fake()->create('replacement.pdf', 512, 'application/pdf');

        $response = $this->postJson('/api/tc-intake/upload-document', [
            'file' => $file,
            'field' => 'cv',
            'application_id' => $app->id,
            'trainee_application_id' => $app->id,
        ]);

        $response->assertStatus(200);
        $this->assertEquals('tc_intake_forms/documents/cv/original_cv.pdf', $app->fresh()->doc_cv);
    }

    public function test_document_over_10mb_is_rejected_with_exact_message()
    {
        Storage::fake('private');

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

    public function test_tc_intake_accepts_valid_upload_tokens_and_deletes_from_cache()
    {
        Storage::fake('private');

        // Seed 6 upload tokens in cache
        $tokens = [];
        $fields = [
            'fitnessTopractice' => 'fitness_to_practice',
            'qualifications' => 'qualifications',
            'dbs' => 'dbs_certificate',
            'cv' => 'cv',
            'validId' => 'valid_id',
            'insurance' => 'insurance_certificate',
        ];

        foreach ($fields as $reqField => $dbCol) {
            $token = \Illuminate\Support\Str::random(40);
            $path = "tc_intake_forms/documents/{$reqField}/test_{$dbCol}.pdf";
            Storage::disk('private')->put($path, 'sample-content');
            Cache::put("tc_upload_token:{$token}", $path, now()->addHours(24));
            $tokens[$reqField] = [
                'token' => $token,
                'path' => $path,
            ];
        }

        $payload = [
            'name' => 'Jane Trainee',
            'email' => 'jane.trainee@example.com',
            'phone' => '07123456789',
            'fitnessTopractice' => $tokens['fitnessTopractice']['token'],
            'qualifications' => $tokens['qualifications']['token'],
            'dbs' => $tokens['dbs']['token'],
            'cv' => $tokens['cv']['token'],
            'validId' => $tokens['validId']['token'],
            'insurance' => $tokens['insurance']['token'],
        ];

        $response = $this->postJson('/api/tc-intake', $payload);

        $response->assertStatus(201);

        // Verify database stores the resolved private file paths (NOT the tokens)
        $this->assertDatabaseHas('tc_intake_forms', [
            'email' => 'jane.trainee@example.com',
            'fitness_to_practice' => $tokens['fitnessTopractice']['path'],
            'qualifications' => $tokens['qualifications']['path'],
            'dbs_certificate' => $tokens['dbs']['path'],
            'cv' => $tokens['cv']['path'],
            'valid_id' => $tokens['validId']['path'],
            'insurance_certificate' => $tokens['insurance']['path'],
        ]);

        $this->assertDatabaseHas('trainee_applications', [
            'email' => 'jane.trainee@example.com',
            'doc_fitness_to_practise' => $tokens['fitnessTopractice']['path'],
            'doc_prior_qualifications' => $tokens['qualifications']['path'],
            'doc_dbs_certificate' => $tokens['dbs']['path'],
            'doc_cv' => $tokens['cv']['path'],
            'doc_valid_id' => $tokens['validId']['path'],
            'doc_indemnity_insurance' => $tokens['insurance']['path'],
        ]);

        // Verify tokens were deleted from cache (one-time use)
        foreach ($tokens as $reqField => $data) {
            $this->assertFalse(Cache::has("tc_upload_token:{$data['token']}"));
        }
    }

    public function test_tc_intake_rejects_external_urls_with_422()
    {
        $payload = [
            'name' => 'Attacker',
            'email' => 'attacker@example.com',
            'phone' => '07123456789',
            'dbs' => 'https://evil.com/malicious_payload.pdf',
        ];

        $response = $this->postJson('/api/tc-intake', $payload);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['dbs']);
        $this->assertStringContainsString('valid one-time upload token', $response->json('errors.dbs.0'));
    }

    public function test_tc_intake_rejects_raw_storage_paths_with_422()
    {
        $payload = [
            'name' => 'Attacker',
            'email' => 'attacker2@example.com',
            'phone' => '07123456789',
            'cv' => 'storage/tc_intake_forms/documents/cv/another_secret.pdf',
        ];

        $response = $this->postJson('/api/tc-intake', $payload);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['cv']);
        $this->assertStringContainsString('valid one-time upload token', $response->json('errors.cv.0'));
    }

    public function test_tc_intake_rejects_unknown_or_expired_upload_tokens_with_422()
    {
        $payload = [
            'name' => 'Jane Trainee',
            'email' => 'jane.expired@example.com',
            'phone' => '07123456789',
            'validId' => 'nonexistenttoken012345678901234567890123',
        ];

        $response = $this->postJson('/api/tc-intake', $payload);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['validId']);
        $this->assertStringContainsString('invalid or has expired', $response->json('errors.validId.0'));
    }

    public function test_download_document_blocks_untrusted_external_redirects()
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $app = TraineeApplication::create([
            'first_name' => 'Bob',
            'last_name' => 'Applicant',
            'email' => 'bob@example.com',
            'status' => 'New Application',
            'doc_cv' => 'https://untrusted-phishing-site.com/exploit.exe',
        ]);

        $response = $this->actingAs($admin)->getJson("/api/trainee-applications/{$app->id}/document/cv");

        $response->assertStatus(403);
        $response->assertJson(['message' => 'Untrusted external document URL']);
    }
}
