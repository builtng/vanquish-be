<?php

namespace Tests\Feature;

use App\Models\Person;
use App\Models\QcApplication;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class QcApplicationDocumentUploadSecurityTest extends TestCase
{
    use RefreshDatabase;

    public function test_qc_upload_document_stores_file_on_private_disk_and_returns_one_time_token()
    {
        Storage::fake('private');

        $file = UploadedFile::fake()->create('qualification.pdf', 1024, 'application/pdf');

        $response = $this->postJson('/api/qualified-counsellor/upload-document', [
            'file' => $file,
            'field' => 'qualification',
        ]);

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
            'message' => 'Document uploaded successfully',
        ]);

        $token = $response->json('upload_token');
        $this->assertNotNull($token);
        $this->assertEquals(40, strlen($token));
        $this->assertTrue(Cache::has("qc_upload_token:{$token}"));

        $storedPath = Cache::get("qc_upload_token:{$token}");
        $this->assertStringStartsWith('qualified_counsellors/documents/qualification/', $storedPath);
        Storage::disk('private')->assertExists($storedPath);
    }

    public function test_qc_upload_with_any_application_id_changes_no_existing_qc_application()
    {
        Storage::fake('private');

        $person = Person::create([
            'name' => 'Original QC',
            'email' => 'original.qc@example.com',
        ]);

        $qcApp = QcApplication::create([
            'person_id' => $person->id,
            'name' => 'Original QC',
            'legal_first_name' => 'Original',
            'legal_last_name' => 'QC',
            'email' => 'original.qc@example.com',
            'status' => 'New Application',
            'registered_address' => '123 High St',
            'registered_city' => 'London',
            'registered_postcode' => 'EC1A 1BB',
            'signature' => 'Original QC',
            'signature_date' => '2026-10-14',
            'qualification_document' => 'qualified_counsellors/documents/qualification/original.pdf',
        ]);

        $file = UploadedFile::fake()->create('hacked.pdf', 512, 'application/pdf');

        $response = $this->postJson('/api/qualified-counsellor/upload-document', [
            'file' => $file,
            'field' => 'qualification',
            'application_id' => $qcApp->id,
            'qc_application_id' => $qcApp->id,
            'tc_id' => $qcApp->id,
        ]);

        $response->assertStatus(200);
        // Verify the original application record was NOT modified
        $this->assertEquals(
            'qualified_counsellors/documents/qualification/original.pdf',
            $qcApp->fresh()->qualification_document
        );
    }

    public function test_qc_submit_rejects_url_or_raw_path_with_422()
    {
        // 1. External URL rejection
        $responseUrl = $this->postJson('/api/qualified-counsellor/submit', [
            'legal_first_name' => 'Attacker',
            'legal_last_name' => 'User',
            'email' => 'attacker@example.com',
            'registered_address' => '456 Malicious Rd',
            'registered_city' => 'London',
            'registered_postcode' => 'SW1A 1AA',
            'signature' => 'Attacker',
            'signature_date' => '2026-10-14',
            'dbs_certificate_qualified' => 'https://evil.com/malicious.pdf',
        ]);

        $responseUrl->assertStatus(422);
        $responseUrl->assertJsonValidationErrors(['dbs_certificate_qualified']);
        $this->assertStringContainsString(
            'valid one-time upload token',
            $responseUrl->json('errors.dbs_certificate_qualified.0')
        );

        // 2. Raw file path rejection
        $responsePath = $this->postJson('/api/qualified-counsellor/submit', [
            'legal_first_name' => 'Attacker',
            'legal_last_name' => 'User',
            'email' => 'attacker2@example.com',
            'registered_address' => '456 Malicious Rd',
            'registered_city' => 'London',
            'registered_postcode' => 'SW1A 1AA',
            'signature' => 'Attacker',
            'signature_date' => '2026-10-14',
            'qualification_document' => 'storage/qualified_counsellors/documents/secret.pdf',
        ]);

        $responsePath->assertStatus(422);
        $responsePath->assertJsonValidationErrors(['qualification_document']);
        $this->assertStringContainsString(
            'valid one-time upload token',
            $responsePath->json('errors.qualification_document.0')
        );
    }

    public function test_qc_valid_token_is_attached_to_application_and_cannot_be_used_twice()
    {
        Storage::fake('private');

        $docFields = [
            'qualification_document',
            'dbs_certificate_qualified',
            'insurance_qualified',
            'self_employment_proof',
            'professional_membership',
            'valid_id_document',
        ];

        $tokens = [];
        foreach ($docFields as $docField) {
            $token = Str::random(40);
            $path = "qualified_counsellors/documents/{$docField}/saved_{$token}.pdf";
            Storage::disk('private')->put($path, 'fake content');
            Cache::put("qc_upload_token:{$token}", $path, now()->addHours(24));
            $tokens[$docField] = [
                'token' => $token,
                'path' => $path,
            ];
        }

        $payload = [
            'legal_first_name' => 'Sarah',
            'legal_last_name' => 'Counsellor',
            'email' => 'sarah.qc@example.com',
            'phone' => '07999888777',
            'registered_address' => '789 Wellness Way',
            'registered_city' => 'Manchester',
            'registered_postcode' => 'M1 1AA',
            'signature' => 'Sarah Counsellor',
            'signature_date' => '2026-10-14',
            'qualification_document' => $tokens['qualification_document']['token'],
            'dbs_certificate_qualified' => $tokens['dbs_certificate_qualified']['token'],
            'insurance_qualified' => $tokens['insurance_qualified']['token'],
            'self_employment_proof' => $tokens['self_employment_proof']['token'],
            'professional_membership' => $tokens['professional_membership']['token'],
            'valid_id_document' => $tokens['valid_id_document']['token'],
        ];

        // First submission - should succeed
        $response1 = $this->postJson('/api/qualified-counsellor/submit', $payload);
        $response1->assertStatus(200);

        // Verify database stores the resolved private disk paths (NOT tokens)
        $this->assertDatabaseHas('qc_applications', [
            'email' => 'sarah.qc@example.com',
            'qualification_document' => $tokens['qualification_document']['path'],
            'dbs_certificate_qualified' => $tokens['dbs_certificate_qualified']['path'],
            'insurance_qualified' => $tokens['insurance_qualified']['path'],
            'self_employment_proof' => $tokens['self_employment_proof']['path'],
            'professional_membership' => $tokens['professional_membership']['path'],
            'valid_id_document' => $tokens['valid_id_document']['path'],
        ]);

        // Verify tokens are deleted from cache (one-time use)
        foreach ($tokens as $docField => $data) {
            $this->assertFalse(Cache::has("qc_upload_token:{$data['token']}"));
        }

        // Second submission with the same tokens - must be rejected with 422
        $response2 = $this->postJson('/api/qualified-counsellor/submit', $payload);
        $response2->assertStatus(422);
        $response2->assertJsonValidationErrors([
            'qualification_document',
            'dbs_certificate_qualified',
            'insurance_qualified',
            'self_employment_proof',
            'professional_membership',
            'valid_id_document',
        ]);
        $this->assertStringContainsString(
            'invalid or has expired',
            $response2->json('errors.qualification_document.0')
        );
    }

    public function test_qc_upload_route_has_throttle_middleware()
    {
        $routes = Route::getRoutes();
        $route = $routes->getByName('api.qualified-counsellor.upload-document')
            ?: $routes->match(app('request')->create('/api/qualified-counsellor/upload-document', 'POST'));

        $this->assertNotNull($route);
        $middleware = $route->gatherMiddleware();
        $this->assertContains('throttle:60,1', $middleware);
    }

    public function test_qc_download_document_blocks_untrusted_redirects()
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $person = Person::create(['name' => 'QC Test', 'email' => 'qctest@example.com']);

        $qcApp = QcApplication::create([
            'person_id' => $person->id,
            'name' => 'QC Test',
            'legal_first_name' => 'QC',
            'legal_last_name' => 'Test',
            'email' => 'qctest@example.com',
            'status' => 'New Application',
            'registered_address' => '123 Main St',
            'registered_city' => 'London',
            'registered_postcode' => 'SW1A 1AA',
            'signature' => 'QC Test',
            'signature_date' => '2026-10-14',
            'qualification_document' => 'https://untrusted-phishing-site.com/malware.exe',
        ]);

        $response = $this->actingAs($admin)->getJson("/api/qc-applications/{$qcApp->id}/document/qualification_document");

        $response->assertStatus(403);
        $response->assertJson(['message' => 'Untrusted external document URL']);
    }
}
