<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;
use App\Models\User;
use App\Models\Client;
use App\Models\TrainingCounsellor;
use App\Models\Consultation;
use App\Models\EmailLog;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class FallbackBookingTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_create_fallback_booking_with_tc()
    {
        Mail::fake();

        $admin = User::create([
            'name' => 'Admin Test',
            'email' => 'admin@test.com',
            'password' => bcrypt('password'),
            'role' => 'admin',
        ]);

        $client = Client::create([
            'name' => 'Client Test',
            'email' => 'client@test.com',
            'client_id' => 'CL001',
            'uuid' => Str::uuid(),
            'stage' => 'Lead',
        ]);

        $tc = TrainingCounsellor::create([
            'name' => 'TC Test',
            'email' => 'tc@test.com',
            'tc_id' => 'TC001',
            'uuid' => Str::uuid(),
            'status' => 'Active',
        ]);

        $payload = [
            'client_id' => $client->id,
            'tc_id' => $tc->id,
            'scheduled_at' => now()->addDays(2)->format('Y-m-d H:i:s'),
            'notes' => 'Fallback booking note',
            'is_fallback' => true,
        ];

        $response = $this->actingAs($admin, 'sanctum')
            ->postJson('/api/consultations', $payload);

        $response->assertStatus(201)
            ->assertJsonPath('is_fallback', true)
            ->assertJsonPath('client_id', $client->id)
            ->assertJsonPath('tc_id', $tc->id);

        $this->assertDatabaseHas('consultations', [
            'client_id' => $client->id,
            'tc_id' => $tc->id,
            'is_fallback' => true,
        ]);
    }

    public function test_fallback_booking_prevents_double_booking()
    {
        $admin = User::create([
            'name' => 'Admin Test 2',
            'email' => 'admin2@test.com',
            'password' => bcrypt('password'),
            'role' => 'admin',
        ]);

        $client = Client::create(['name' => 'C1', 'email' => 'c1@t.com', 'client_id' => 'CL002', 'uuid' => Str::uuid(), 'stage' => 'Lead']);
        $client2 = Client::create(['name' => 'C2', 'email' => 'c2@t.com', 'client_id' => 'CL003', 'uuid' => Str::uuid(), 'stage' => 'Lead']);
        $tc = TrainingCounsellor::create(['name' => 'T1', 'email' => 't1@t.com', 'tc_id' => 'TC002', 'uuid' => Str::uuid(), 'status' => 'Active']);

        $scheduledAt = now()->addDays(2)->format('Y-m-d H:i:s');

        // First booking
        Consultation::create([
            'consultation_id' => 'CONS001',
            'client_id' => $client->id,
            'tc_id' => $tc->id,
            'scheduled_at' => $scheduledAt,
            'status' => 'scheduled'
        ]);

        // Attempt double booking
        $payload = [
            'client_id' => $client2->id,
            'tc_id' => $tc->id,
            'scheduled_at' => $scheduledAt,
            'is_fallback' => true,
        ];

        $response = $this->actingAs($admin, 'sanctum')
            ->postJson('/api/consultations', $payload);

        $response->assertStatus(422)
            ->assertJsonPath('message', 'The selected Trainee Counsellor is already booked for this time slot.');
    }
}
