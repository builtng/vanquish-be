<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\ClientIntakeForm;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MultipleAvailabilityIntakeTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test that selecting multiple time slots (e.g. 5 slots across multiple days)
     * persists all 5 slots in both ClientIntakeForm and Client models,
     * and is returned accurately via the admin client details API.
     */
    public function test_multiple_availability_slots_saved_to_intake_form_and_client_and_returned_by_api(): void
    {
        $availabilityData = [
            'monday' => ['10am-1050am', '11am-1150am'],
            'wednesday' => ['2pm-250pm'],
            'friday' => ['10am-1050am', '3pm-350pm'],
        ];

        $payload = [
            'first_name' => 'Sarah',
            'last_name' => 'Connor',
            'email' => 'sarah.connor@example.com',
            'phone' => '07111222333',
            'age' => 32,
            'service_type' => 'Low Cost',
            'availability' => $availabilityData,
            'address' => '42 Skynet Way, London',
            'emergency_contact_name' => 'John Connor',
            'emergency_contact_phone' => '07999888777',
            'emergency_contact_relationship' => 'Son',
            'terms_accepted' => true,
            'create_client' => true,
        ];

        $response = $this->postJson('/api/client-intake', $payload);
        $response->assertStatus(201);

        // 1. Verify ClientIntakeForm has all 5 slots across 3 days
        $intakeForm = ClientIntakeForm::where('email', 'sarah.connor@example.com')->first();
        $this->assertNotNull($intakeForm);
        $this->assertIsArray($intakeForm->availability);
        $this->assertCount(2, $intakeForm->availability['monday']);
        $this->assertEquals(['10am-1050am', '11am-1150am'], $intakeForm->availability['monday']);
        $this->assertEquals(['2pm-250pm'], $intakeForm->availability['wednesday']);
        $this->assertEquals(['10am-1050am', '3pm-350pm'], $intakeForm->availability['friday']);

        // Count total slots saved in ClientIntakeForm
        $totalIntakeSlots = array_sum(array_map('count', $intakeForm->availability));
        $this->assertEquals(5, $totalIntakeSlots, 'ClientIntakeForm should save exactly 5 selected slots');

        // 2. Verify Client model has all 5 slots across 3 days
        $client = Client::where('email', 'sarah.connor@example.com')->first();
        $this->assertNotNull($client);
        $this->assertIsArray($client->availability);
        $this->assertEquals(['10am-1050am', '11am-1150am'], $client->availability['monday']);
        $this->assertEquals(['2pm-250pm'], $client->availability['wednesday']);
        $this->assertEquals(['10am-1050am', '3pm-350pm'], $client->availability['friday']);

        $totalClientSlots = array_sum(array_map('count', $client->availability));
        $this->assertEquals(5, $totalClientSlots, 'Client model should save exactly 5 selected slots');

        // 3. Verify admin profile endpoint (/api/clients/{uuid}) returns the full multi-slot structure
        $admin = User::factory()->create(['role' => 'admin']);
        $apiResponse = $this->actingAs($admin)->getJson("/api/clients/{$client->uuid}");
        $apiResponse->assertStatus(200);

        $returnedClient = $apiResponse->json();
        $this->assertNotNull($returnedClient['availability']);
        $this->assertEquals($availabilityData, $returnedClient['availability']);
    }

    /**
     * Test multi-slot availability persistence across Mid Range intake.
     */
    public function test_mid_range_intake_preserves_multiple_availability_slots(): void
    {
        $availabilityData = [
            'tuesday' => ['1pm-150pm', '2pm-250pm', '5pm-550pm'],
            'thursday' => ['10am-1050am', '6pm-650pm'],
        ];

        $payload = [
            'first_name' => 'Marcus',
            'last_name' => 'Aurelius',
            'email' => 'marcus.aurelius@example.com',
            'phone' => '07222333444',
            'age' => 45,
            'service_type' => 'Mid Range',
            'availability' => $availabilityData,
            'address' => 'Palatine Hill, Rome, London',
            'emergency_contact_name' => 'Faustina',
            'emergency_contact_phone' => '07888777666',
            'emergency_contact_relationship' => 'Spouse',
            'terms_accepted' => true,
        ];

        $response = $this->postJson('/api/client-intake', $payload);
        $response->assertStatus(201);

        $client = Client::where('email', 'marcus.aurelius@example.com')->first();
        $this->assertNotNull($client);
        $totalSlots = array_sum(array_map('count', $client->availability));
        $this->assertEquals(5, $totalSlots);
        $this->assertCount(3, $client->availability['tuesday']);
        $this->assertCount(2, $client->availability['thursday']);
    }

    /**
     * Test multi-slot availability persistence in Coaching & Counselling intake.
     */
    public function test_coaching_intake_preserves_multiple_availability_slots(): void
    {
        $availabilityData = [
            'monday' => ['12pm-1250pm'],
            'tuesday' => ['10am-1050am', '11am-1150am'],
            'thursday' => ['3pm-350pm', '4pm-450pm', '5pm-550pm'],
        ];

        $payload = [
            'first_name' => 'Elena',
            'last_name' => 'Rostova',
            'email' => 'elena.rostova@example.com',
            'phone' => '07333444555',
            'age' => 29,
            'service_type' => 'Coaching & Counselling',
            'availability' => $availabilityData,
            'address' => '10 Victoria Road, London',
            'emergency_contact_name' => 'Dmitri Rostov',
            'emergency_contact_phone' => '07777666555',
            'emergency_contact_relationship' => 'Brother',
            'terms_accepted' => true,
        ];

        $response = $this->postJson('/api/client-intake', $payload);
        $response->assertStatus(201);

        $client = Client::where('email', 'elena.rostova@example.com')->first();
        $this->assertNotNull($client);
        $totalSlots = array_sum(array_map('count', $client->availability));
        $this->assertEquals(6, $totalSlots);
    }

    /**
     * Test multi-slot availability persistence in Partner / clform intake.
     */
    public function test_clform_intake_preserves_multiple_availability_slots(): void
    {
        $availabilityData = [
            'monday' => ['10am-1050am'],
            'friday' => ['10am-1050am', '11am-1150am', '2pm-250pm'],
        ];

        $payload = [
            'first_name' => 'James',
            'last_name' => 'Holden',
            'email' => 'james.holden@example.com',
            'phone' => '07444555666',
            'age' => 35,
            'service_type' => 'Partner Service Intake',
            'availability' => $availabilityData,
            'address' => 'Ceres Station, London',
            'emergency_contact_name' => 'Naomi Nagata',
            'emergency_contact_phone' => '07666555444',
            'emergency_contact_relationship' => 'Partner',
            'terms_accepted' => true,
            'create_client' => true,
        ];

        $response = $this->postJson('/api/client-intake', $payload);
        $response->assertStatus(201);

        $client = Client::where('email', 'james.holden@example.com')->first();
        $this->assertNotNull($client);
        $totalSlots = array_sum(array_map('count', $client->availability));
        $this->assertEquals(4, $totalSlots);
        $this->assertEquals(['10am-1050am', '11am-1150am', '2pm-250pm'], $client->availability['friday']);
    }
}
