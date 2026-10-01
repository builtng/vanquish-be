<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\ClientIntakeForm;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class RejoiningClientCaseTest extends TestCase
{
    use RefreshDatabase;

    public function test_submitting_intake_form_with_existing_email_creates_new_separate_case()
    {
        Mail::fake();

        // 1. Existing client from a previous intake/therapy journey
        $existingClient = Client::create([
            'client_id' => 'CL001',
            'name' => 'Alice Smith',
            'first_name' => 'Alice',
            'last_name' => 'Smith',
            'email' => 'alice@example.com',
            'phone' => '07111111111',
            'stage' => 'Active Therapy',
            'service_type' => 'Low Cost',
            'primary_issues' => ['Anxiety', 'Stress'],
        ]);

        $existingIntake = ClientIntakeForm::create([
            'client_id' => $existingClient->id,
            'first_name' => 'Alice',
            'last_name' => 'Smith',
            'email' => 'alice@example.com',
            'status' => 'processed',
        ]);

        // 2. Alice rejoins and submits a new intake form with the SAME email but new details
        $payload = [
            'first_name' => 'Alice',
            'last_name' => 'Smith-Jones',
            'email' => 'alice@example.com',
            'phone' => '07999999999',
            'age' => 32,
            'address' => '456 New Road, London',
            'emergency_contact_name' => 'Bob Jones',
            'emergency_contact_phone' => '07888888888',
            'emergency_contact_relationship' => 'Partner',
            'service_type' => 'Mid Range',
            'support_areas' => ['Depression', 'Bereavement'],
            'terms_accepted' => true,
            'create_client' => true,
            'consultation_fee' => 35.00,
        ];

        $response = $this->postJson('/api/client-intake', $payload);

        $response->assertStatus(201);
        $data = $response->json();

        // Must return a new client ID and UUID distinct from the first case
        $this->assertNotEquals($existingClient->id, $data['client_id']);
        $this->assertNotEquals($existingClient->uuid, $data['client_uuid']);

        // Verify total client cases in database is now 2
        $this->assertEquals(2, Client::where('email', 'alice@example.com')->count());

        // Verify the original client was NOT overwritten or merged
        $freshExisting = $existingClient->fresh();
        $this->assertEquals('CL001', $freshExisting->client_id);
        $this->assertEquals('Alice Smith', $freshExisting->name);
        $this->assertEquals('07111111111', $freshExisting->phone);
        $this->assertEquals('Active Therapy', $freshExisting->stage);
        $this->assertEquals('Low Cost', $freshExisting->service_type);
        $this->assertEquals(['Anxiety', 'Stress'], $freshExisting->primary_issues);

        // Verify the new client case has its own distinct details
        $newClient = Client::find($data['client_id']);
        $this->assertNotNull($newClient);
        $this->assertNotEquals('CL001', $newClient->client_id);
        $this->assertEquals('Alice Smith-Jones', $newClient->name);
        $this->assertEquals('07999999999', $newClient->phone);
        $this->assertEquals('Application & Assessment form Submitted', $newClient->stage);
        $this->assertEquals('Mid Range', $newClient->service_type);
        $this->assertEquals(['Depression', 'Bereavement'], $newClient->primary_issues);
    }

    public function test_admin_can_create_client_with_same_email()
    {
        $admin = User::factory()->create(['role' => 'admin']);

        // First client
        $client1 = Client::create([
            'client_id' => 'CL010',
            'name' => 'Charlie Brown',
            'email' => 'charlie@example.com',
            'stage' => 'Completed',
        ]);

        // Admin creates a second case for Charlie with same email
        $payload = [
            'name' => 'Charlie Brown Rejoining',
            'email' => 'charlie@example.com',
            'stage' => 'Application & Assessment form Submitted',
        ];

        $response = $this->actingAs($admin, 'sanctum')->postJson('/api/clients', $payload);

        $response->assertStatus(201);
        $this->assertEquals(2, Client::where('email', 'charlie@example.com')->count());
    }

    public function test_client_show_endpoint_returns_related_cases_for_same_email()
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $case1 = Client::create([
            'client_id' => 'CL101',
            'name' => 'David Miller',
            'email' => 'david@example.com',
            'stage' => 'Completed',
            'service_type' => 'Low Cost',
        ]);

        $case2 = Client::create([
            'client_id' => 'CL102',
            'name' => 'David Miller',
            'email' => 'david@example.com',
            'stage' => 'Application & Assessment form Submitted',
            'service_type' => 'Mid Range',
        ]);

        $response = $this->actingAs($admin, 'sanctum')->getJson("/api/clients/{$case2->uuid}");

        $response->assertStatus(200);
        $data = $response->json();

        $this->assertArrayHasKey('related_cases', $data);
        $this->assertCount(1, $data['related_cases']);
        $this->assertEquals('CL101', $data['related_cases'][0]['client_id']);
        $this->assertEquals('Completed', $data['related_cases'][0]['stage']);
    }
}
