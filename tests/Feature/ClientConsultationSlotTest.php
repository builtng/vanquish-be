<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Consultation;
use App\Models\ConsultationSlot;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Tests\TestCase;

class ClientConsultationSlotTest extends TestCase
{
    use RefreshDatabase;

    public function test_get_available_slots_returns_200_with_future_available_slot(): void
    {
        // Future available slot
        $futureSlot = ConsultationSlot::create([
            'consultation_datetime' => Carbon::now()->addDays(3)->setTime(10, 0),
            'status' => 'available',
            'max_slots' => 1,
            'booked_slots' => 0,
        ]);

        // Past slot (should not be returned)
        ConsultationSlot::create([
            'consultation_datetime' => Carbon::now()->subDays(1)->setTime(10, 0),
            'status' => 'available',
            'max_slots' => 1,
            'booked_slots' => 0,
        ]);

        // Full slot (should not be returned)
        ConsultationSlot::create([
            'consultation_datetime' => Carbon::now()->addDays(4)->setTime(14, 0),
            'status' => 'available',
            'max_slots' => 1,
            'booked_slots' => 1,
        ]);

        $response = $this->getJson('/api/consultation-slots/available');

        $response->assertStatus(200);
        $response->assertJsonFragment([
            'id' => $futureSlot->id,
        ]);

        $data = $response->json();
        $this->assertCount(1, $data);
        $this->assertEquals($futureSlot->id, $data[0]['id']);
    }

    public function test_book_consultation_through_controller(): void
    {
        Mail::fake();

        $client = Client::create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Alice Wonder',
            'first_name' => 'Alice',
            'last_name' => 'Wonder',
            'email' => 'alice@example.com',
            'phone' => '+447700900000',
            'service_type' => 'Mid Range',
            'stage' => 'Intake Submitted',
        ]);

        $slot = ConsultationSlot::create([
            'consultation_datetime' => Carbon::now()->addDays(5)->setTime(11, 0),
            'status' => 'available',
            'max_slots' => 1,
            'booked_slots' => 0,
        ]);

        $response = $this->postJson('/api/client/book-consultation', [
            'client_uuid' => $client->uuid,
            'consultation_slot_id' => $slot->id,
        ]);

        $response->assertStatus(200);
        $response->assertJsonFragment([
            'message' => 'Consultation booked successfully',
        ]);

        $this->assertDatabaseHas('consultations', [
            'client_id' => $client->id,
            'consultation_slot_id' => $slot->id,
        ]);
    }
}
