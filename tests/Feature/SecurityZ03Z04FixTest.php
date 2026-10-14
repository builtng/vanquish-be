<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\ServiceSetting;
use App\Models\TrainingCounsellor;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class SecurityZ03Z04FixTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Seed ServiceSettings
        ServiceSetting::updateOrCreate(
            ['service_name' => 'Mid Range'],
            [
                'consultation_price' => 15.00,
                'session_price' => 45.00,
                'block_price' => 180.00,
            ]
        );
    }

    protected function createTc($attributes = [])
    {
        return TrainingCounsellor::create(array_merge([
            'name' => 'Jane Doe',
            'first_name' => 'Jane',
            'last_name' => 'Doe',
            'email' => 'tc.' . Str::random(8) . '@example.com',
            'qualification_level' => 'Qualified',
            'counsellor_type' => 'qualified',
            'status' => 'Active',
            'is_qualified' => true,
            'service_tiers' => ['Mid Range'],
        ], $attributes));
    }

    /**
     * FIX Z03: Test that server sets the price and ignores browser amount
     */
    public function test_z03_payment_intent_ignores_browser_amount_and_uses_service_setting_price()
    {
        $client = Client::create([
            'client_id' => 'CL-Z03',
            'name' => 'Z03 Test',
            'service_type' => 'Mid Range',
            'email' => 'client.z03@example.com',
            'stage' => 'New',
        ]);

        $tc = $this->createTc();
        $slot = \App\Models\ConsultationSlot::create([
            'consultation_datetime' => now()->addDays(2),
            'status' => 'available',
            'max_slots' => 3,
            'booked_slots' => 0,
        ]);

        // Mock Stripe Customer and PaymentIntent
        \Mockery::mock('overload:\Stripe\Customer')
            ->shouldReceive('create')
            ->andReturn((object)['id' => 'cus_mock_z03']);

        $mock = \Mockery::mock('overload:\Stripe\PaymentIntent');
        $mock->shouldReceive('create')
            ->once()
            ->with(\Mockery::on(function ($params) {
                // Must be 1500 pence (£15.00) despite browser sending 0.50
                return $params['amount'] === 1500;
            }))
            ->andReturn((object)[
                'id' => 'pi_mock_z03',
                'client_secret' => 'pi_mock_z03_secret',
            ]);

        // Browser sends amount = 0.50
        $response = $this->postJson('/api/payments/create-intent', [
            'client_id' => $client->id,
            'amount' => 0.50,
            'payment_type' => 'consultation',
            'consultation_slot_id' => $slot->id,
        ]);

        $response->assertStatus(200);
        $response->assertJson([
            'amount' => 15.00,
            'payment_intent_id' => 'pi_mock_z03',
        ]);
    }

    /**
     * FIX Z04: Test that authenticate with email alone returns no client data
     */
    public function test_z04_email_alone_returns_no_client_data_and_generic_message()
    {
        $tc = $this->createTc();
        $client = Client::create([
            'client_id' => 'CL-Z04-1',
            'name' => 'Victim Client',
            'email' => 'victim@example.com',
            'uuid' => (string) Str::uuid(),
            'matched_tc_id' => $tc->id,
            'service_type' => 'Mid Range',
            'stage' => 'New',
        ]);

        $response = $this->postJson('/api/client-booking/authenticate', [
            'email' => 'victim@example.com',
        ]);

        $response->assertStatus(200);
        $response->assertJson([
            'message' => 'If this email is registered, we have sent you your booking link.',
        ]);
        $response->assertJsonMissing(['client' => ['name' => $client->name]]);
        $this->assertArrayNotHasKey('client', $response->json());
        $this->assertArrayNotHasKey('upcoming_sessions', $response->json());
    }

    /**
     * FIX Z04: Unregistered email returns identical generic message
     */
    public function test_z04_unregistered_email_returns_identical_message()
    {
        $response = $this->postJson('/api/client-booking/authenticate', [
            'email' => 'nonexistent@example.com',
        ]);

        $response->assertStatus(200);
        $response->assertJson([
            'message' => 'If this email is registered, we have sent you your booking link.',
        ]);
        $this->assertArrayNotHasKey('client', $response->json());
    }

    /**
     * FIX Z04: Valid private link (client_uuid) still works
     */
    public function test_z04_valid_client_uuid_authenticates_successfully()
    {
        $tc = $this->createTc();
        $client = Client::create([
            'client_id' => 'CL-Z04-2',
            'name' => 'Valid Client',
            'email' => 'client@example.com',
            'uuid' => (string) Str::uuid(),
            'matched_tc_id' => $tc->id,
            'service_type' => 'Mid Range',
            'stage' => 'New',
        ]);

        $response = $this->postJson('/api/client-booking/authenticate', [
            'client_uuid' => $client->uuid,
        ]);

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'client' => ['id', 'uuid', 'name', 'email', 'service_type'],
            'upcoming_sessions',
        ]);
        $this->assertEquals($client->id, $response->json('client.id'));
    }

    /**
     * FIX Z04: Missing both email and client_uuid returns 422
     */
    public function test_z04_missing_parameters_returns_422()
    {
        $response = $this->postJson('/api/client-booking/authenticate', []);
        $response->assertStatus(422);
    }
}
