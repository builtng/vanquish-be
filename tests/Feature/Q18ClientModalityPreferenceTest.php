<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\ClientIntakeForm;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class Q18ClientModalityPreferenceTest extends TestCase
{
    use RefreshDatabase;

    public function test_intake_form_saves_modality_preference_on_both_records(): void
    {
        $payload = [
            'first_name' => 'Jane',
            'last_name' => 'Doe',
            'email' => 'jane.doe@example.com',
            'phone' => '07123456789',
            'address' => '123 Test St, London',
            'emergency_contact_name' => 'John Doe',
            'emergency_contact_phone' => '07987654321',
            'emergency_contact_relationship' => 'Spouse',
            'terms_accepted' => true,
            'gender_preference' => 'Female',
            'age_preference' => 'No preference',
            'ethnicity_preference' => 'No preference',
            'orientation_preference' => 'No preference',
            'modality_preference' => 'Person-Centred',
        ];

        $response = $this->postJson('/api/client-intake', $payload);

        $response->assertSuccessful();

        $intake = ClientIntakeForm::where('email', 'jane.doe@example.com')->first();
        $this->assertNotNull($intake);
        $this->assertEquals('Person-Centred', $intake->modality_preference);

        $client = Client::where('email', 'jane.doe@example.com')->first();
        $this->assertNotNull($client);
        $this->assertEquals('Person-Centred', $client->modality_preference);
    }

    public function test_intake_form_defaults_modality_preference_to_unsure(): void
    {
        $payload = [
            'first_name' => 'Bob',
            'last_name' => 'Smith',
            'email' => 'bob.smith@example.com',
            'phone' => '07123456780',
            'address' => '456 Test Ave, London',
            'emergency_contact_name' => 'Alice Smith',
            'emergency_contact_phone' => '07987654320',
            'emergency_contact_relationship' => 'Parent',
            'terms_accepted' => true,
        ];

        $response = $this->postJson('/api/client-intake', $payload);

        $response->assertSuccessful();

        $intake = ClientIntakeForm::where('email', 'bob.smith@example.com')->first();
        $this->assertNotNull($intake);
        $this->assertEquals('Unsure', $intake->modality_preference);

        $client = Client::where('email', 'bob.smith@example.com')->first();
        $this->assertNotNull($client);
        $this->assertEquals('Unsure', $client->modality_preference);
    }

    public function test_admin_can_view_and_update_modality_preference(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $client = Client::create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Charlie Brown',
            'first_name' => 'Charlie',
            'last_name' => 'Brown',
            'email' => 'charlie@example.com',
            'client_id' => 'CL-9999',
            'gender_preference' => 'No preference',
            'age_preference' => 'No preference',
            'ethnicity_preference' => 'No preference',
            'orientation_preference' => 'No preference',
            'modality_preference' => 'Unsure',
        ]);

        $showResponse = $this->actingAs($admin)->getJson("/api/clients/{$client->uuid}");
        $showResponse->assertStatus(200);
        $this->assertEquals('Unsure', $showResponse->json('modality_preference'));

        $updateResponse = $this->actingAs($admin)->putJson("/api/clients/{$client->uuid}", [
            'modality_preference' => 'CBT',
        ]);
        $updateResponse->assertStatus(200);

        $client->refresh();
        $this->assertEquals('CBT', $client->modality_preference);
    }
}
