<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\TrainingCounsellor;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ClientPreferencesAndDemographicsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
    }

    public function test_admin_can_update_client_counsellor_preferences()
    {
        $admin = User::factory()->create([
            'role' => 'admin',
        ]);

        $client = Client::create([
            'client_id' => 'CL001',
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'phone' => '07123456789',
            'gender_preference' => 'No preference',
            'age_preference' => 'No preference',
            'ethnicity_preference' => 'No preference',
            'orientation_preference' => 'No preference',
        ]);

        $response = $this->actingAs($admin)
            ->putJson("/api/clients/{$client->uuid}", [
                'name' => 'John Doe',
                'email' => 'john@example.com',
                'gender_preference' => 'Female',
                'age_preference' => '30-40',
                'ethnicity_preference' => 'Black / African / Caribbean / Black British',
                'orientation_preference' => 'Heterosexual / Straight',
                'emergency_contact_email' => 'emergency@example.com',
            ]);

        $response->assertStatus(200);

        $client->refresh();
        $this->assertEquals('Female', $client->gender_preference);
        $this->assertEquals('30-40', $client->age_preference);
        $this->assertEquals('Black / African / Caribbean / Black British', $client->ethnicity_preference);
        $this->assertEquals('Heterosexual / Straight', $client->orientation_preference);
        $this->assertEquals('emergency@example.com', $client->emergency_contact_email);
    }

    public function test_admin_can_update_training_counsellor_demographics()
    {
        $admin = User::factory()->create([
            'role' => 'admin',
        ]);

        $tc = TrainingCounsellor::create([
            'tc_id' => 'TC001',
            'name' => 'Jane Smith',
            'email' => 'jane@example.com',
            'modality' => 'CBT',
            'status' => 'Active',
        ]);

        $response = $this->actingAs($admin)
            ->putJson("/api/training-counsellors/{$tc->uuid}", [
                'name' => 'Jane Smith',
                'email' => 'jane@example.com',
                'gender' => 'Female',
                'ethnicity' => 'White',
                'sexual_orientation' => 'Heterosexual / Straight',
                'age' => 34,
                'date_of_birth' => '1992-05-12',
                'address' => '123 Test Street, London',
            ]);

        $response->assertStatus(200);

        $tc->refresh();
        $this->assertEquals('Female', $tc->gender);
        $this->assertEquals('White', $tc->ethnicity);
        $this->assertEquals('Heterosexual / Straight', $tc->sexual_orientation);
        $this->assertEquals(34, $tc->age);
        $this->assertEquals('1992-05-12', $tc->date_of_birth);
        $this->assertEquals('123 Test Street, London', $tc->address);
    }
}
