<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Consultation;
use App\Models\ConsultationSlot;
use App\Models\TrainingCounsellor;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class ConsultationTogglesH08Test extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        \Mockery::close();
        parent::tearDown();
    }

    /**
     * Combination 1: Toggle 1 on and own slots exist -> returns own slots.
     */
    public function test_combination_1_toggle_1_on_with_own_slots_returns_own_slots(): void
    {
        $tc = TrainingCounsellor::create([
            'tc_id' => 'QC-H08-01',
            'uuid' => (string) Str::uuid(),
            'name' => 'Dr. Jane OwnSlots',
            'email' => 'jane.ownslots@example.com',
            'counsellor_type' => 'Qualified',
            'status' => 'Active',
            'offers_mid_range' => true,
            'show_own_consultation_availability' => true,
            'show_vanquish_consultation_availability' => false,
            'consultation_availability' => [
                'monday' => ['10:00', '14:00'],
                'friday' => ['09:00'],
            ],
        ]);

        $response = $this->getJson("/api/mid-range-intake/consultation-availability/{$tc->uuid}");

        $response->assertStatus(200);
        $response->assertJson([
            'source' => 'counsellor',
            'tc_uuid' => $tc->uuid,
            'tc_name' => $tc->name,
        ]);
        $this->assertNotEmpty($response->json('slots'));
    }

    /**
     * Combination 2: Toggle 1 on, own slots empty, Toggle 2 on -> returns Vanquish slots.
     */
    public function test_combination_2_toggle_1_on_empty_own_slots_toggle_2_on_returns_vanquish_slots(): void
    {
        $tc = TrainingCounsellor::create([
            'tc_id' => 'QC-H08-02',
            'uuid' => (string) Str::uuid(),
            'name' => 'Dr. Bob FallbackToVanquish',
            'email' => 'bob.fallback@example.com',
            'counsellor_type' => 'Qualified',
            'status' => 'Active',
            'offers_mid_range' => true,
            'show_own_consultation_availability' => true,
            'show_vanquish_consultation_availability' => true,
            'consultation_availability' => [], // no own slots configured
        ]);

        $response = $this->getJson("/api/mid-range-intake/consultation-availability/{$tc->uuid}");

        $response->assertStatus(200);
        $response->assertJson([
            'source' => 'vanquish',
            'tc_uuid' => $tc->uuid,
            'tc_name' => $tc->name,
            'slots' => [],
        ]);
    }

    /**
     * Combination 3: Toggle 1 off, Toggle 2 on -> returns Vanquish slots.
     */
    public function test_combination_3_toggle_1_off_toggle_2_on_returns_vanquish_slots(): void
    {
        $tc = TrainingCounsellor::create([
            'tc_id' => 'QC-H08-03',
            'uuid' => (string) Str::uuid(),
            'name' => 'Dr. Alice VanquishPool',
            'email' => 'alice.vanquish@example.com',
            'counsellor_type' => 'Qualified',
            'status' => 'Active',
            'offers_mid_range' => true,
            'show_own_consultation_availability' => false,
            'show_vanquish_consultation_availability' => true,
            'consultation_availability' => [
                'monday' => ['10:00'],
            ],
        ]);

        $response = $this->getJson("/api/mid-range-intake/consultation-availability/{$tc->uuid}");

        $response->assertStatus(200);
        $response->assertJson([
            'source' => 'vanquish',
            'tc_uuid' => $tc->uuid,
            'tc_name' => $tc->name,
            'slots' => [],
        ]);
    }

    /**
     * Combination 4: Both off -> no slots, and message "This counsellor is not taking new consultations at the moment."
     */
    public function test_combination_4_both_off_returns_no_slots_and_not_taking_consultations_message(): void
    {
        $tc = TrainingCounsellor::create([
            'tc_id' => 'QC-H08-04',
            'uuid' => (string) Str::uuid(),
            'name' => 'Dr. Charlie BothOff',
            'email' => 'charlie.bothoff@example.com',
            'counsellor_type' => 'Qualified',
            'status' => 'Active',
            'offers_mid_range' => true,
            'show_own_consultation_availability' => false,
            'show_vanquish_consultation_availability' => false,
        ]);

        $response = $this->getJson("/api/mid-range-intake/consultation-availability/{$tc->uuid}");

        $response->assertStatus(200);
        $response->assertJson([
            'source' => 'none',
            'tc_uuid' => $tc->uuid,
            'tc_name' => $tc->name,
            'slots' => [],
            'message' => 'This counsellor is not taking new consultations at the moment.',
        ]);
    }

    /**
     * Combination 5: Toggle 1 on, own slots empty, Toggle 2 off -> no slots, and message "This counsellor is not taking new consultations at the moment."
     */
    public function test_combination_5_toggle_1_on_empty_own_slots_toggle_2_off_returns_no_slots_and_message(): void
    {
        $tc = TrainingCounsellor::create([
            'tc_id' => 'QC-H08-05',
            'uuid' => (string) Str::uuid(),
            'name' => 'Dr. Diana EmptyOwnAndVanquishOff',
            'email' => 'diana.empty@example.com',
            'counsellor_type' => 'Qualified',
            'status' => 'Active',
            'offers_mid_range' => true,
            'show_own_consultation_availability' => true,
            'show_vanquish_consultation_availability' => false,
            'consultation_availability' => [], // no own slots
        ]);

        $response = $this->getJson("/api/mid-range-intake/consultation-availability/{$tc->uuid}");

        $response->assertStatus(200);
        $response->assertJson([
            'source' => 'none',
            'tc_uuid' => $tc->uuid,
            'tc_name' => $tc->name,
            'slots' => [],
            'message' => 'This counsellor is not taking new consultations at the moment.',
        ]);
    }

    /**
     * Booking records this counsellor as chosen counsellor when booking with Vanquish slot.
     */
    public function test_booking_with_vanquish_slot_records_counsellor_as_chosen_counsellor(): void
    {
        $tc = TrainingCounsellor::create([
            'tc_id' => 'QC-H08-06',
            'uuid' => (string) Str::uuid(),
            'name' => 'Dr. Edward Chosen',
            'email' => 'edward.chosen@example.com',
            'counsellor_type' => 'Qualified',
            'status' => 'Active',
            'offers_mid_range' => true,
            'show_own_consultation_availability' => false,
            'show_vanquish_consultation_availability' => true,
        ]);

        $slot = ConsultationSlot::create([
            'consultation_datetime' => Carbon::now()->addDays(3)->setTime(10, 0),
            'status' => 'available',
        ]);

        $response = $this->postJson('/api/client-intake', [
            'name' => 'Sarah Client',
            'first_name' => 'Sarah',
            'last_name' => 'Client',
            'email' => 'sarah.client@example.com',
            'phone' => '07123456789',
            'date_of_birth' => '1995-05-15',
            'service_type' => 'Mid Range',
            'consultation_slot_id' => $slot->id,
            'consultation_with_tc_uuid' => $tc->uuid,
            'consultation_fee' => 0, // Coupon / free intake path
            'coupon_code' => 'TEST100',
            'emergency_contact_name' => 'John Client',
            'emergency_contact_phone' => '07987654321',
            'emergency_contact_relationship' => 'Brother',
            'address' => '123 High Street, London',
            'terms_accepted' => true,
        ]);

        $response->assertStatus(201);

        $client = Client::where('email', 'sarah.client@example.com')->first();
        $this->assertNotNull($client);
        $this->assertEquals($tc->id, $client->preferred_tc_id);

        $consultation = Consultation::where('client_id', $client->id)->first();
        $this->assertNotNull($consultation);
        $this->assertEquals($tc->id, $consultation->tc_id);
    }

    /**
     * Only qualified counsellors see/update the toggles; trainees are forbidden.
     */
    public function test_only_qualified_counsellors_can_update_toggles_trainees_forbidden(): void
    {
        // 1. Qualified counsellor succeeds
        $qc = TrainingCounsellor::create([
            'tc_id' => 'QC-H08-07',
            'uuid' => (string) Str::uuid(),
            'name' => 'Qualified Counsellor',
            'email' => 'qc.toggle@example.com',
            'counsellor_type' => 'Qualified',
            'status' => 'Active',
        ]);

        $qcUser = User::create([
            'name' => 'Qualified Counsellor',
            'email' => 'qc.toggle@example.com',
            'password' => bcrypt('password123'),
            'role' => 'counsellor',
            'training_counsellor_id' => $qc->id,
            'is_active' => true,
        ]);

        $qcResponse = $this->actingAs($qcUser, 'sanctum')->putJson('/api/counsellor/consultation-availability-toggles', [
            'show_own_consultation_availability' => true,
            'show_vanquish_consultation_availability' => false,
        ]);

        $qcResponse->assertStatus(200);
        $this->assertTrue($qc->fresh()->show_own_consultation_availability);
        $this->assertFalse($qc->fresh()->show_vanquish_consultation_availability);

        // 2. Trainee counsellor receives 403 Forbidden
        $trainee = TrainingCounsellor::create([
            'tc_id' => 'TC-H08-08',
            'uuid' => (string) Str::uuid(),
            'name' => 'Trainee Counsellor',
            'email' => 'trainee.toggle@example.com',
            'counsellor_type' => 'Trainee',
            'status' => 'Active',
        ]);

        $traineeUser = User::create([
            'name' => 'Trainee Counsellor',
            'email' => 'trainee.toggle@example.com',
            'password' => bcrypt('password123'),
            'role' => 'counsellor',
            'training_counsellor_id' => $trainee->id,
            'is_active' => true,
        ]);

        $traineeResponse = $this->actingAs($traineeUser, 'sanctum')->putJson('/api/counsellor/consultation-availability-toggles', [
            'show_own_consultation_availability' => true,
            'show_vanquish_consultation_availability' => true,
        ]);

        $traineeResponse->assertStatus(403);
    }

    #[\PHPUnit\Framework\Attributes\RunInSeparateProcess]
    #[\PHPUnit\Framework\Attributes\PreserveGlobalState(false)]
    public function test_paid_stripe_booking_with_vanquish_slot_records_counsellor_as_chosen_counsellor(): void
    {
        \Illuminate\Support\Facades\Mail::fake();

        $mock = \Mockery::mock('overload:' . \Stripe\PaymentIntent::class);
        $mock->shouldReceive('retrieve')
            ->once()
            ->with('pi_h08_test')
            ->andReturn((object)[
                'id' => 'pi_h08_test',
                'status' => 'succeeded',
                'amount' => 2500,
                'customer' => 'cus_123',
                'payment_method_types' => ['card'],
                'metadata' => (object)['payment_type' => 'consultation'],
            ]);

        $tc = TrainingCounsellor::create([
            'tc_id' => 'QC-H08-09',
            'uuid' => (string) Str::uuid(),
            'name' => 'Dr. Stripe Chosen',
            'email' => 'stripe.chosen@example.com',
            'counsellor_type' => 'Qualified',
            'status' => 'Active',
            'offers_mid_range' => true,
            'show_own_consultation_availability' => false,
            'show_vanquish_consultation_availability' => true,
        ]);

        $slot = ConsultationSlot::create([
            'consultation_datetime' => Carbon::now()->addDays(2)->setTime(14, 0),
            'status' => 'available',
        ]);

        $client = Client::create([
            'client_id' => Client::generateNextClientId(),
            'name' => 'Stripe Client',
            'email' => 'stripe.client@example.com',
        ]);

        $consultation = Consultation::create([
            'consultation_id' => Consultation::generateNextConsultationId(),
            'client_id' => $client->id,
            'consultation_slot_id' => $slot->id,
            'scheduled_at' => $slot->consultation_datetime,
            'payment_status' => 'pending',
            'status' => 'pending',
        ]);

        $response = $this->postJson('/api/payments/confirm', [
            'client_id' => $client->id,
            'payment_intent_id' => 'pi_h08_test',
            'payment_type' => 'consultation',
            'consultation_slot_id' => $slot->id,
            'consultation_with_tc_uuid' => $tc->uuid,
        ]);

        $response->assertStatus(200);

        $this->assertEquals($tc->id, $client->fresh()->preferred_tc_id);
        $this->assertEquals($tc->id, $consultation->fresh()->tc_id);
    }
}
