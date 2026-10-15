<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\TrainingCounsellor;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class ClientAgreementSignatureSecurityTest extends TestCase
{
    use RefreshDatabase;

    private function getAdminUser(): User
    {
        return User::create([
            'name' => 'Admin User',
            'email' => 'admin@test.com',
            'password' => bcrypt('password'),
            'role' => 'admin',
        ]);
    }

    private function getStaffUser(): User
    {
        return User::create([
            'name' => 'Staff User',
            'email' => 'staff@test.com',
            'password' => bcrypt('password'),
            'role' => 'staff',
        ]);
    }

    private function getClientUser(): User
    {
        return User::create([
            'name' => 'Client User',
            'email' => 'client@test.com',
            'password' => bcrypt('password'),
            'role' => 'client',
        ]);
    }

    public function test_submitting_agreement_saves_signature_to_private_disk_and_not_public()
    {
        Storage::fake('public');
        Storage::fake('private');

        $client = Client::create([
            'uuid' => (string) Str::uuid(),
            'client_id' => 'CL-70001',
            'name' => 'Jane Agreement',
            'email' => 'jane.agreement@test.com',
            'phone' => '07123456789',
            'service_type' => 'Low Cost',
            'stage' => 'Agreement Sent',
        ]);

        // 1x1 transparent PNG base64
        $base64Png = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==';

        $payload = [
            'client_uuid' => $client->uuid,
            'email' => $client->email,
            'full_name' => $client->name,
            'emergency_contact_name' => 'John Contact',
            'emergency_contact_relationship' => 'Partner',
            'emergency_contact_phone' => '07987654321',
            'gp_name' => 'Dr Smith',
            'gp_practice_name' => 'Town Clinic',
            'gp_practice_phone' => '01234567890',
            'current_address' => '123 High Street, London',
            'signature_data' => $base64Png,
            'signature_date' => now()->format('Y-m-d'),
            'terms_agreed' => true,
            'service_type' => 'Low Cost',
            'case_study_consent' => 'yes',
        ];

        $response = $this->postJson('/api/client-agreement/submit', $payload);
        $response->assertStatus(200);

        $client->refresh();
        $this->assertEquals('signed', $client->agreement_status);
        $this->assertNotNull($client->agreement_signature_url);
        $this->assertStringStartsWith('signatures/', $client->agreement_signature_url);

        // Crucial security assertions: saved on private disk, NOT public disk
        $this->assertTrue(Storage::disk('private')->exists($client->agreement_signature_url));
        $this->assertFalse(Storage::disk('public')->exists($client->agreement_signature_url));
    }

    public function test_unauthenticated_user_cannot_download_client_signature()
    {
        Storage::fake('private');

        $sigPath = 'signatures/test_unauth_signature.png';
        Storage::disk('private')->put($sigPath, 'signature binary content');

        $client = Client::create([
            'uuid' => (string) Str::uuid(),
            'client_id' => 'CL-70002',
            'name' => 'Test Client',
            'email' => 'unauth.client@test.com',
            'agreement_status' => 'signed',
            'agreement_signature_url' => $sigPath,
        ]);

        $response = $this->getJson("/api/clients/{$client->uuid}/signature");
        $response->assertStatus(401);
    }

    public function test_unauthorized_user_cannot_download_client_signature()
    {
        Storage::fake('private');

        $sigPath = 'signatures/test_unauthz_signature.png';
        Storage::disk('private')->put($sigPath, 'signature binary content');

        $client = Client::create([
            'uuid' => (string) Str::uuid(),
            'client_id' => 'CL-70003',
            'name' => 'Test Client',
            'email' => 'unauthz.client@test.com',
            'agreement_status' => 'signed',
            'agreement_signature_url' => $sigPath,
        ]);

        $clientUser = $this->getClientUser();

        $response = $this->actingAs($clientUser, 'sanctum')
            ->getJson("/api/clients/{$client->uuid}/signature");

        $response->assertStatus(403);
    }

    public function test_admin_and_staff_can_download_client_signature_from_private_disk()
    {
        Storage::fake('private');
        Storage::fake('public');

        $sigPath = 'signatures/test_private_signature.png';
        Storage::disk('private')->put($sigPath, 'private signature binary content');

        $client = Client::create([
            'uuid' => (string) Str::uuid(),
            'client_id' => 'CL-70004',
            'name' => 'Alice Client',
            'email' => 'alice.client@test.com',
            'agreement_status' => 'signed',
            'agreement_signature_url' => $sigPath,
        ]);

        // Admin download
        $admin = $this->getAdminUser();
        $responseAdmin = $this->actingAs($admin, 'sanctum')
            ->getJson("/api/clients/{$client->uuid}/signature");

        $responseAdmin->assertStatus(200);
        $this->assertStringContainsString('signature', $responseAdmin->headers->get('content-disposition'));

        // Staff download
        $staff = $this->getStaffUser();
        $responseStaff = $this->actingAs($staff, 'sanctum')
            ->getJson("/api/clients/{$client->uuid}/signature");

        $responseStaff->assertStatus(200);

        // Also works via alternative document route
        $responseDocRoute = $this->actingAs($admin, 'sanctum')
            ->getJson("/api/clients/{$client->uuid}/document/signature");

        $responseDocRoute->assertStatus(200);
    }

    public function test_admin_and_staff_can_download_legacy_signature_from_public_disk_fallback()
    {
        Storage::fake('private');
        Storage::fake('public');

        $sigPath = 'signatures/test_legacy_signature.png';
        Storage::disk('public')->put($sigPath, 'legacy public signature binary content');

        $client = Client::create([
            'uuid' => (string) Str::uuid(),
            'client_id' => 'CL-70005',
            'name' => 'Legacy Client',
            'email' => 'legacy.client@test.com',
            'agreement_status' => 'signed',
            'agreement_signature_url' => $sigPath,
        ]);

        $admin = $this->getAdminUser();
        $response = $this->actingAs($admin, 'sanctum')
            ->getJson("/api/clients/{$client->uuid}/signature");

        $response->assertStatus(200);
    }

    public function test_matched_counsellor_can_download_client_signature_while_unmatched_cannot()
    {
        Storage::fake('private');

        $sigPath = 'signatures/test_tc_signature.png';
        Storage::disk('private')->put($sigPath, 'counsellor client signature binary');

        $tc = TrainingCounsellor::create([
            'tc_id' => 'TC-70001',
            'uuid' => (string) Str::uuid(),
            'name' => 'Matched Counsellor',
            'email' => 'matched.tc@test.com',
        ]);

        $otherTc = TrainingCounsellor::create([
            'tc_id' => 'TC-70002',
            'uuid' => (string) Str::uuid(),
            'name' => 'Other Counsellor',
            'email' => 'other.tc@test.com',
        ]);

        $client = Client::create([
            'uuid' => (string) Str::uuid(),
            'client_id' => 'CL-70006',
            'name' => 'Client With TC',
            'email' => 'withtc.client@test.com',
            'matched_tc_id' => $tc->id,
            'agreement_status' => 'signed',
            'agreement_signature_url' => $sigPath,
        ]);

        $matchedUser = User::create([
            'name' => 'Matched TC User',
            'email' => 'matched.tc.user@test.com',
            'password' => bcrypt('password'),
            'role' => 'counsellor',
            'training_counsellor_id' => $tc->id,
        ]);

        $unmatchedUser = User::create([
            'name' => 'Unmatched TC User',
            'email' => 'unmatched.tc.user@test.com',
            'password' => bcrypt('password'),
            'role' => 'counsellor',
            'training_counsellor_id' => $otherTc->id,
        ]);

        // Matched counsellor can access signature
        $respMatched = $this->actingAs($matchedUser, 'sanctum')
            ->getJson("/api/clients/{$client->uuid}/signature");
        $respMatched->assertStatus(200);

        // Unmatched counsellor cannot access signature
        $respUnmatched = $this->actingAs($unmatchedUser, 'sanctum')
            ->getJson("/api/clients/{$client->uuid}/signature");
        $respUnmatched->assertStatus(403);
    }

    public function test_download_signature_returns_404_if_client_or_file_missing()
    {
        $admin = $this->getAdminUser();

        // 1. Non-existent client
        $response = $this->actingAs($admin, 'sanctum')
            ->getJson('/api/clients/' . (string) Str::uuid() . '/signature');
        $response->assertStatus(404);

        // 2. Client with no signature recorded
        $clientNoSig = Client::create([
            'uuid' => (string) Str::uuid(),
            'client_id' => 'CL-70007',
            'name' => 'No Sig Client',
            'email' => 'nosig@test.com',
            'agreement_signature_url' => null,
        ]);

        $responseNoSig = $this->actingAs($admin, 'sanctum')
            ->getJson("/api/clients/{$clientNoSig->uuid}/signature");
        $responseNoSig->assertStatus(404);

        // 3. Client with signature path but file missing from storage
        Storage::fake('private');
        Storage::fake('public');

        $clientMissingFile = Client::create([
            'uuid' => (string) Str::uuid(),
            'client_id' => 'CL-70008',
            'name' => 'Missing File Client',
            'email' => 'missingfile@test.com',
            'agreement_signature_url' => 'signatures/missing_file.png',
        ]);

        $responseMissingFile = $this->actingAs($admin, 'sanctum')
            ->getJson("/api/clients/{$clientMissingFile->uuid}/signature");
        $responseMissingFile->assertStatus(404);
    }

    public function test_download_agreement_pdf_works_with_private_disk_signature()
    {
        Storage::fake('private');
        Storage::fake('public');

        $sigPath = 'signatures/pdf_test_signature.png';
        // 1x1 transparent PNG
        $pngData = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==');
        Storage::disk('private')->put($sigPath, $pngData);

        $client = Client::create([
            'uuid' => (string) Str::uuid(),
            'client_id' => 'CL-70009',
            'name' => 'PDF Test Client',
            'email' => 'pdf.client@test.com',
            'agreement_status' => 'signed',
            'agreement_signed_at' => now(),
            'agreement_signature_url' => $sigPath,
            'gp_name' => 'Dr Health',
            'gp_practice_name' => 'Central Surgery',
            'gp_practice_phone' => '02071234567',
        ]);

        $response = $this->get("/api/client-agreement/download/{$client->uuid}");
        $response->assertStatus(200);
        $this->assertEquals('application/pdf', $response->headers->get('content-type'));
    }
}
