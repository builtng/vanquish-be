<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\Client;
use App\Models\ClientIntakeForm;
use App\Models\Consultation;
use App\Models\ConsultationSlot;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;
use Stripe\PaymentIntent;
use Mockery;

/**
 * Prompt 8 – Correct consultation booking flow and client journey (5.2, 5.3)
 *
 * Tests:
 *   1. Paid booking via /api/payments/confirm creates Consultation, marks slot
 *      booked, sets client stage, creates ActivityLog, sends exactly 1
 *      confirmation email and appears in GET /api/consultations.
 *   2. Free / 100%-discount booking via POST /api/client-intake with
 *      consultation_fee = 0 creates Consultation, sets stage, logs activity,
 *      sends 1 confirmation email and appears in GET /api/consultations.
 *   3. Client journey stages update without sequential locking – admin can
 *      click any stage directly.
 */
class ConsultationBookingFlowTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    // ─────────────────────────────────────────────────────────────────────────
    // TEST 1 – Paid booking via /api/payments/confirm
    // ─────────────────────────────────────────────────────────────────────────

    public function test_paid_booking_creates_consultation_sends_one_confirmation_email_and_admin_log()
    {
        Mail::fake();

        $mock = Mockery::mock('overload:' . PaymentIntent::class);
        $mock->shouldReceive('retrieve')
            ->once()
            ->with('pi_paid_booking_test')
            ->andReturn((object)[
                'id'                  => 'pi_paid_booking_test',
                'status'              => 'succeeded',
                'amount'              => 9900,
                'customer'            => 'cus_abc123',
                'payment_method_types' => ['card'],
                'metadata'            => (object)['payment_type' => 'consultation'],
            ]);

        $client = Client::create([
            'client_id' => 'CL-PB001',
            'name'      => 'Alice Paid',
            'email'     => 'alice.paid@example.com',
            'stage'     => 'New',
        ]);

        $slot = ConsultationSlot::create([
            'consultation_datetime' => now()->addDays(5),
            'status'                => 'available',
            'max_slots'             => 3,
            'booked_slots'          => 0,
        ]);

        $response = $this->postJson('/api/payments/confirm', [
            'payment_intent_id'    => 'pi_paid_booking_test',
            'client_id'            => $client->id,
            'consultation_slot_id' => $slot->id,
        ]);

        $response->assertStatus(200);

        Mail::assertSent(\App\Mail\DynamicEmail::class, 1);

        $this->assertDatabaseHas('consultations', [
            'client_id'            => $client->id,
            'consultation_slot_id' => $slot->id,
            'status'               => 'scheduled',
            'payment_status'       => 'paid',
        ]);

        $this->assertSame('Consultation Booked', $client->fresh()->stage);
        $this->assertSame(1, $slot->fresh()->booked_slots);

        $this->assertDatabaseHas('activity_logs', [
            'action'   => 'consultation_booked',
            'model_id' => $client->id,
        ]);
    }

    public function test_paid_consultation_appears_in_consultations_list()
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $client = Client::create([
            'client_id' => 'CL-PB002',
            'name'      => 'Bob Listed',
            'email'     => 'bob.listed@example.com',
            'stage'     => 'Consultation Booked',
        ]);

        $slot = ConsultationSlot::create([
            'consultation_datetime' => now()->addDays(3),
            'status'                => 'available',
            'max_slots'             => 5,
            'booked_slots'          => 1,
        ]);

        Consultation::create([
            'consultation_id'      => 'CONS-LIST-01',
            'client_id'            => $client->id,
            'consultation_slot_id' => $slot->id,
            'status'               => 'scheduled',
            'payment_status'       => 'paid',
            'scheduled_at'         => $slot->consultation_datetime,
        ]);

        $response = $this->actingAs($admin)->getJson('/api/consultations');

        $response->assertStatus(200);
        $response->assertJsonFragment(['client_id' => $client->id]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // TEST 2 – Free / 100%-discount booking
    // ─────────────────────────────────────────────────────────────────────────

    public function test_free_code_booking_creates_consultation_sends_one_email_and_logs_activity()
    {
        Mail::fake();

        $slot = ConsultationSlot::create([
            'consultation_datetime' => now()->addDays(7),
            'status'                => 'available',
            'max_slots'             => 10,
            'booked_slots'          => 0,
        ]);

        $payload = [
            'first_name'                    => 'Charlie',
            'last_name'                     => 'Free',
            'email'                         => 'charlie.free@example.com',
            'phone'                         => '07777123456',
            'address'                       => '10 Free Street, London, E1 1AA',
            'emergency_contact_name'        => 'Jane Free',
            'emergency_contact_phone'       => '07700000001',
            'emergency_contact_relationship' => 'Partner',
            'terms_accepted'                => true,
            'consultation_fee'              => 0,
            'consultation_slot_id'          => $slot->id,
            'service_type'                  => 'Mid Range',
            'primary_issues'                => ['Anxiety'],
            'availability'                  => ['Monday' => ['Morning']],
        ];

        $response = $this->postJson('/api/client-intake', $payload);

        $this->assertContains($response->status(), [200, 201],
            'Free intake should return 200/201, got: ' . $response->status() . ' – ' . $response->content()
        );

        Mail::assertSent(\App\Mail\DynamicEmail::class, 1);

        $client = Client::where('email', 'charlie.free@example.com')->first();
        $this->assertNotNull($client, 'Client record should be created for free booking');
        $this->assertSame('Consultation Booked', $client->stage);

        $this->assertDatabaseHas('consultations', [
            'client_id'            => $client->id,
            'consultation_slot_id' => $slot->id,
            'status'               => 'scheduled',
        ]);

        $this->assertDatabaseHas('activity_logs', [
            'action'   => 'consultation_booked',
            'model_id' => $client->id,
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // TEST 3 – Journey stages are flexible and non-locking
    // ─────────────────────────────────────────────────────────────────────────

    public function test_admin_can_set_any_journey_stage_directly_without_sequential_lock()
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $client = Client::create([
            'client_id' => 'CL-JOURNEY-01',
            'name'      => 'Diana Journey',
            'email'     => 'diana.journey@example.com',
            'stage'     => 'Consultation Booked',
        ]);

        $stages = [
            'Consultation Completed',
            'Agreement Sent',
            'Agreement Signed',
            'Matched With Counsellor',
            'Sessions Booked',
            'Active Therapy',
            'Agreement Signed',      // Can go back
            'Consultation Completed', // Can go further back
        ];

        foreach ($stages as $stage) {
            $response = $this->actingAs($admin)->postJson(
                "/api/clients/{$client->uuid}/progress-stage",
                ['stage' => $stage]
            );

            $response->assertStatus(200,
                "Setting stage to '{$stage}' should return 200"
            );
            $this->assertSame($stage, $client->fresh()->stage,
                "Client stage should be '{$stage}' after update"
            );
        }
    }

    public function test_journey_stage_update_creates_activity_log()
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $client = Client::create([
            'client_id' => 'CL-JOURNEY-02',
            'name'      => 'Eve Log',
            'email'     => 'eve.log@example.com',
            'stage'     => 'Consultation Booked',
        ]);

        $this->actingAs($admin)->postJson(
            "/api/clients/{$client->uuid}/progress-stage",
            ['stage' => 'Consultation Completed']
        )->assertStatus(200);

        $this->assertDatabaseHas('activity_logs', [
            'action'   => 'client_stage_progressed',
            'model_id' => $client->id,
        ]);
    }

    public function test_all_seven_valid_journey_stages_are_accepted_by_api()
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $validStages = [
            'Consultation Booked',
            'Consultation Completed',
            'Agreement Sent',
            'Agreement Signed',
            'Matched With Counsellor',
            'Sessions Booked',
            'Active Therapy',
        ];

        foreach ($validStages as $i => $stage) {
            $client = Client::create([
                'client_id' => 'CL-STAGE-' . $i,
                'name'      => "Stage Test {$i}",
                'email'     => "stage.test.{$i}@example.com",
                'stage'     => 'Consultation Booked',
            ]);

            $response = $this->actingAs($admin)->postJson(
                "/api/clients/{$client->uuid}/progress-stage",
                ['stage' => $stage]
            );

            $response->assertStatus(200,
                "Stage '{$stage}' should be accepted – got {$response->status()}: {$response->content()}"
            );
        }
    }
}
