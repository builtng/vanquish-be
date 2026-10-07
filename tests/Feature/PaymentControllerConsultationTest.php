<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Consultation;
use App\Models\ConsultationSlot;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;
use Stripe\PaymentIntent;
use Mockery;

class PaymentControllerConsultationTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_confirm_payment_with_a_slot_books_it_and_sends_one_confirmation_email()
    {
        Mail::fake();

        $mock = Mockery::mock('overload:' . PaymentIntent::class);
        $mock->shouldReceive('retrieve')
            ->once()
            ->with('pi_live1')
            ->andReturn((object)[
                'id' => 'pi_live1',
                'status' => 'succeeded',
                'amount' => 2500,
                'customer' => 'cus_123',
                'payment_method_types' => ['card'],
                'metadata' => (object)['payment_type' => 'consultation'],
            ]);

        $client = Client::create([
            'client_id' => 'CL-LIVE1',
            'name' => 'Live Test',
            'email' => 'live1@example.com',
            'stage' => 'New',
        ]);

        $slot = ConsultationSlot::create([
            'consultation_datetime' => now()->addDays(3),
            'status' => 'available',
            'max_slots' => 1,
            'booked_slots' => 0,
        ]);

        $response = $this->postJson('/api/payments/confirm', [
            'payment_intent_id' => 'pi_live1',
            'client_id' => $client->id,
            'consultation_slot_id' => $slot->id,
        ]);

        $response->assertStatus(200);

        // Exactly one email — the booking confirmation with the real
        // slot/Zoom details — never payment_confirmation as well.
        Mail::assertSent(\App\Mail\DynamicEmail::class, 1);

        $this->assertDatabaseHas('consultations', [
            'client_id' => $client->id,
            'consultation_slot_id' => $slot->id,
            'status' => 'scheduled',
        ]);

        $this->assertSame('Consultation Booked', $client->fresh()->stage);
        $this->assertSame(1, $slot->fresh()->booked_slots);
    }

    public function test_confirm_payment_without_a_slot_falls_back_to_payment_confirmation()
    {
        Mail::fake();

        $mock = Mockery::mock('overload:' . PaymentIntent::class);
        $mock->shouldReceive('retrieve')
            ->once()
            ->with('pi_live2')
            ->andReturn((object)[
                'id' => 'pi_live2',
                'status' => 'succeeded',
                'amount' => 2500,
                'customer' => 'cus_123',
                'payment_method_types' => ['card'],
                'metadata' => (object)['payment_type' => 'consultation'],
            ]);

        $client = Client::create([
            'client_id' => 'CL-LIVE2',
            'name' => 'Live Test 2',
            'email' => 'live2@example.com',
            'stage' => 'New',
        ]);

        $response = $this->postJson('/api/payments/confirm', [
            'payment_intent_id' => 'pi_live2',
            'client_id' => $client->id,
        ]);

        $response->assertStatus(200);
        Mail::assertSent(\App\Mail\DynamicEmail::class, 1);
    }

    public function test_Q13_cannot_pay_without_slot()
    {
        $client = Client::create([
            'client_id' => 'CL-Q13',
            'name' => 'Q13 Test',
            'email' => 'q13@example.com',
            'stage' => 'New',
        ]);

        $response = $this->postJson('/api/payments/create-intent', [
            'client_id' => $client->id,
            'amount' => 25.00,
            'payment_type' => 'consultation',
        ]);

        $response->assertStatus(422);
        $response->assertJson([
            'message' => 'Please choose a consultation time before paying.',
        ]);
    }
}
