<?php

namespace Tests\Unit;

use App\Models\Client;
use App\Models\Consultation;
use App\Models\ConsultationSlot;
use App\Services\ConsultationBookingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class ConsultationBookingServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_finalize_is_idempotent_and_only_sends_one_email_even_if_called_twice()
    {
        Mail::fake();

        $client = Client::create([
            'client_id' => 'CL-IDEMP',
            'name' => 'Idempotent Client',
            'email' => 'idempotent@example.com',
            'stage' => 'New',
        ]);

        $slot = ConsultationSlot::create([
            'consultation_datetime' => now()->addDays(1),
            'status' => 'available',
            'max_slots' => 5,
            'booked_slots' => 0,
        ]);

        $consultation = Consultation::create([
            'consultation_id' => 'CONS-IDEMP',
            'client_id' => $client->id,
            'scheduled_at' => now(),
            'payment_status' => 'paid',
            'status' => 'scheduled',
        ]);

        $service = app(ConsultationBookingService::class);

        // Simulates the direct /payments/confirm call, then the async
        // Stripe webhook firing for the same payment shortly after.
        $service->finalize($client, $slot->id, $consultation);
        $service->finalize($client, $slot->id, $consultation);

        Mail::assertSent(\App\Mail\DynamicEmail::class, 1);
        $this->assertSame(1, $slot->fresh()->booked_slots);
    }
}
