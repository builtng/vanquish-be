<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AgreementSignedStageTest extends TestCase
{
    use RefreshDatabase;

    public function test_custom_agreement_submission_moves_client_to_agreement_signed_and_pending_matches()
    {
        $client = Client::create([
            'client_id' => 'CL200',
            'uuid' => 'uuid-agreement-200',
            'name' => 'John AgreementSigned',
            'email' => 'john.signed@example.com',
            'stage' => 'Agreement Sent',
            'service_type' => 'Low Cost',
            'address' => '123 Main St',
        ]);

        $response = $this->postJson('/api/client-agreement/submit', [
            'client_uuid' => $client->uuid,
            'email' => $client->email,
            'full_name' => $client->name,
            'service_type' => 'Low Cost',
            'emergency_contact_name' => 'Jane Doe',
            'emergency_contact_relationship' => 'Spouse',
            'emergency_contact_phone' => '07123456789',
            'gp_name' => 'Dr Smith',
            'gp_practice_name' => 'Health Clinic',
            'gp_practice_phone' => '02012345678',
            'current_address' => '123 Main St',
            'signature_data' => 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==',
            'signature_date' => now()->toDateString(),
            'terms_agreed' => true,
            'case_study_consent' => 'yes',
        ]);

        $response->assertStatus(200);

        $client->refresh();
        $this->assertEquals('signed', $client->agreement_status);
        $this->assertEquals('Agreement Signed', $client->stage);

        // Verify client appears in pending-matches
        $admin = User::factory()->create(['role' => 'admin']);
        $pendingResponse = $this->actingAs($admin, 'sanctum')->getJson('/api/pending-matches');
        $pendingResponse->assertStatus(200);

        $matchedClient = collect($pendingResponse->json())->firstWhere('uuid', $client->uuid);
        $this->assertNotNull($matchedClient);

        // Verify count
        $countResponse = $this->actingAs($admin, 'sanctum')->getJson('/api/pending-matches/count');
        $countResponse->assertStatus(200)->assertJson(['count' => 1]);
    }
}
