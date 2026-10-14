<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\ConsultationSlot;
use App\Services\EmailService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Stripe\PaymentIntent;
use Tests\TestCase;

class BookingStageFlowC02Test extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_submit_before_payment_sets_stage_to_awaiting_payment_and_sends_no_emails()
    {
        // Mock email service to verify that NO email is sent upon initial intake submit before payment
        $mockEmailService = $this->mock(EmailService::class);
        $mockEmailService->shouldReceive('sendAndLog')->never();

        $slot = ConsultationSlot::create([
            'consultation_datetime' => Carbon::now()->addDays(2)->format('Y-m-d H:i:s'),
            'type' => 'online',
            'duration_minutes' => 15,
            'max_clients' => 1,
            'booked_slots' => 0,
            'is_available' => true,
        ]);

        $payload = [
            'first_name' => 'John',
            'last_name' => 'Awaiting',
            'email' => 'john.awaiting@example.com',
            'phone' => '07123456789',
            'date_of_birth' => '1990-01-01',
            'gender' => 'Male',
            'ethnicity' => 'White',
            'sexual_orientation' => 'Heterosexual',
            'address' => '123 High Street, London',
            'emergency_contact_name' => 'Mary Awaiting',
            'emergency_contact_phone' => '07987654321',
            'emergency_contact_relationship' => 'Mother',
            'terms_accepted' => true,
            'consultation_slot_id' => $slot->id,
            'consultation_fee' => 10.00,
            'service_type' => 'Low Cost',
        ];

        $response = $this->postJson('/api/client-intake', $payload);
        $response->assertStatus(201);

        $client = Client::where('email', 'john.awaiting@example.com')->first();
        $this->assertNotNull($client);
        $this->assertEquals('Awaiting payment', $client->stage);
        $this->assertNotEquals('Application & Assessment form Submitted', $client->stage);
    }

    public function test_payment_completion_transitions_stage_to_consultation_booked_and_sends_single_email()
    {
        $mock = Mockery::mock('overload:' . PaymentIntent::class);
        $mock->shouldReceive('retrieve')
            ->once()
            ->with('pi_test_c02_123')
            ->andReturn((object)[
                'id' => 'pi_test_c02_123',
                'status' => 'succeeded',
                'amount' => 1000,
                'customer' => 'cus_c02_123',
                'payment_method_types' => ['card'],
                'metadata' => (object)['payment_type' => 'consultation'],
            ]);

        $mockEmailService = $this->mock(EmailService::class);
        // The client must get exactly one email: the booking confirmation
        $mockEmailService->shouldReceive('sendAndLog')
            ->once()
            ->withArgs(function ($recipient, $template) {
                return $template === 'consultation_booking_confirmation';
            })
            ->andReturn(true);

        $client = Client::create([
            'client_id' => 'CL-C02-001',
            'name' => 'Jane Paid',
            'email' => 'jane.paid@example.com',
            'stage' => 'Awaiting payment',
        ]);

        $slot = ConsultationSlot::create([
            'consultation_datetime' => Carbon::now()->addDays(3)->format('Y-m-d H:i:s'),
            'type' => 'online',
            'duration_minutes' => 15,
            'max_clients' => 1,
            'booked_slots' => 0,
            'is_available' => true,
        ]);

        $response = $this->postJson('/api/payments/confirm', [
            'payment_intent_id' => 'pi_test_c02_123',
            'client_id' => $client->id,
            'consultation_slot_id' => $slot->id,
        ]);

        $response->assertStatus(200);

        $client->refresh();
        $this->assertEquals('Consultation Booked', $client->stage);
    }
}
