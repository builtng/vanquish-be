<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\ClientIntakeForm;
use App\Models\Consultation;
use App\Models\ConsultationSlot;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;
use Stripe\PaymentIntent;
use Mockery;

class IntakeControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_payment_success_without_a_slot_sends_a_single_fallback_email()
    {
        Mail::fake();

        $mock = Mockery::mock('overload:' . PaymentIntent::class);
        $mock->shouldReceive('retrieve')
            ->once()
            ->with('pi_test123')
            ->andReturn((object)[
                'id' => 'pi_test123',
                'status' => 'succeeded',
                'amount' => 5000,
                'payment_method_types' => ['card']
            ]);

        $client = Client::create([
            'client_id' => 'CL-TEST',
            'name' => 'John Doe',
            'first_name' => 'John',
            'last_name' => 'Doe',
            'email' => 'johndoe@example.com',
            'stage' => 'New',
        ]);

        $intake = ClientIntakeForm::create([
            'client_id' => $client->id,
            'first_name' => 'John',
            'last_name' => 'Doe',
            'email' => 'johndoe@example.com',
            'status' => 'draft',
            'payment_status' => 'unpaid',
        ]);

        $payload = [
            'payment_intent_id' => 'pi_test123',
            'intake_id' => $intake->id,
        ];

        $response = $this->postJson('/api/intake/confirm-payment', $payload);

        $response->assertStatus(200);

        $this->assertDatabaseHas('client_intake_forms', [
            'id' => $intake->id,
            'payment_status' => 'paid',
            'status' => 'submitted',
        ]);

        // No consultation slot was selected, so this only sends the
        // fallback payment_confirmation email — never the old three-email
        // cascade (payment_confirmation + intake_submission +
        // consultation_booking_link).
        Mail::assertSent(\App\Mail\DynamicEmail::class, 1);
    }

    public function test_payment_success_with_a_slot_sends_exactly_one_booking_confirmation_email()
    {
        Mail::fake();

        $mock = Mockery::mock('overload:' . PaymentIntent::class);
        $mock->shouldReceive('retrieve')
            ->once()
            ->with('pi_test456')
            ->andReturn((object)[
                'id' => 'pi_test456',
                'status' => 'succeeded',
                'amount' => 2500,
                'payment_method_types' => ['card']
            ]);

        $client = Client::create([
            'client_id' => 'CL-TEST2',
            'name' => 'Jane Doe',
            'first_name' => 'Jane',
            'last_name' => 'Doe',
            'email' => 'janedoe@example.com',
            'stage' => 'New',
        ]);

        $intake = ClientIntakeForm::create([
            'client_id' => $client->id,
            'first_name' => 'Jane',
            'last_name' => 'Doe',
            'email' => 'janedoe@example.com',
            'status' => 'draft',
            'payment_status' => 'unpaid',
        ]);

        $slot = ConsultationSlot::create([
            'consultation_datetime' => now()->addDays(2),
            'status' => 'available',
            'max_slots' => 1,
            'booked_slots' => 0,
        ]);

        $payload = [
            'payment_intent_id' => 'pi_test456',
            'intake_id' => $intake->id,
            'consultation_slot_id' => $slot->id,
        ];

        $response = $this->postJson('/api/intake/confirm-payment', $payload);

        $response->assertStatus(200);

        Mail::assertSent(\App\Mail\DynamicEmail::class, 1);

        $this->assertDatabaseHas('consultations', [
            'client_id' => $client->id,
            'consultation_slot_id' => $slot->id,
            'status' => 'scheduled',
        ]);

        $this->assertSame('Consultation Booked', $client->fresh()->stage);
    }
}
