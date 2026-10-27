<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Models\User;
use App\Models\Client;
use App\Models\Consultation;
use App\Models\ClientIntakeForm;
use Illuminate\Support\Facades\DB;

class ConsultationServiceTypeNormalizationTest extends TestCase
{
    use RefreshDatabase;

    private function createStaff(): User
    {
        return User::create([
            'name' => 'Staff User',
            'email' => 'staff@vanquishtherapies.co.uk',
            'password' => bcrypt('password'),
            'role' => 'staff',
        ]);
    }

    public function test_consultation_index_normalizes_client_service_type_from_low_cost_slug(): void
    {
        $staff = $this->createStaff();

        $client = Client::create([
            'client_id' => 'CL-001',
            'name' => 'Jane Doe',
            'email' => 'jane@example.com',
            'stage' => 'Consultation Booked',
            'status' => 'active',
            'service_type' => 'low-cost',
        ]);

        $consultation = Consultation::create([
            'consultation_id' => 'CS-001',
            'client_id' => $client->id,
            'scheduled_at' => now()->addDay(),
            'status' => 'scheduled',
        ]);

        $response = $this->actingAs($staff, 'sanctum')
            ->getJson('/api/consultations');

        $response->assertStatus(200);
        $consultations = $response->json();
        $this->assertNotEmpty($consultations);
        $this->assertEquals('Low Cost', $consultations[0]['client']['service_type']);

        // Check persisted client record is also normalized
        $this->assertEquals('Low Cost', $client->fresh()->service_type);
    }

    public function test_consultation_index_resolves_and_normalizes_service_type_from_intake_form(): void
    {
        $staff = $this->createStaff();

        $client = Client::create([
            'client_id' => 'CL-002',
            'name' => 'John Smith',
            'email' => 'john@example.com',
            'stage' => 'Consultation Booked',
            'status' => 'active',
            'service_type' => null,
        ]);

        ClientIntakeForm::create([
            'client_id' => $client->id,
            'email' => 'john@example.com',
            'first_name' => 'John',
            'last_name' => 'Smith',
            'service_type' => 'low-cost',
        ]);

        Consultation::create([
            'consultation_id' => 'CS-002',
            'client_id' => $client->id,
            'scheduled_at' => now()->addDay(),
            'status' => 'scheduled',
        ]);

        $response = $this->actingAs($staff, 'sanctum')
            ->getJson('/api/consultations');

        $response->assertStatus(200);
        $consultations = $response->json();
        $this->assertEquals('Low Cost', $consultations[0]['client']['service_type']);
        $this->assertEquals('Low Cost', $client->fresh()->service_type);
    }

    public function test_intake_form_submission_preserves_submitted_service_type(): void
    {
        $payload = [
            'first_name' => 'Alice',
            'last_name' => 'Wonder',
            'email' => 'alice@example.com',
            'phone' => '07123456789',
            'age' => 28,
            'service_type' => 'low-cost',
            'concerns_details' => 'Anxiety support',
            'address' => '123 Test Street, London',
            'emergency_contact_name' => 'Bob Wonder',
            'emergency_contact_phone' => '07999888777',
            'emergency_contact_relationship' => 'Brother',
            'terms_accepted' => true,
        ];

        $response = $this->postJson('/api/client-intake', $payload);
        $response->assertStatus(201);

        $this->assertDatabaseHas('client_intake_forms', [
            'email' => 'alice@example.com',
            'service_type' => 'low-cost',
        ]);

        $this->assertDatabaseHas('clients', [
            'email' => 'alice@example.com',
            'service_type' => 'low-cost',
        ]);
    }
}
