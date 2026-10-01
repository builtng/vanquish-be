<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Consultation;
use App\Models\ConsultationSlot;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DuplicateConsultationIdTest extends TestCase
{
    use RefreshDatabase;

    public function test_consultation_id_generation_prevents_duplicates()
    {
        $client = Client::create([
            'client_id' => 'CL001',
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'address' => '123 Main St',
        ]);

        // Create an existing consultation with ID 'CONS006'
        Consultation::create([
            'consultation_id' => 'CONS006',
            'client_id' => $client->id,
            'status' => 'scheduled',
            'scheduled_at' => now(),
        ]);

        // Count is 1, but max num is 6. Next should be CONS007
        $nextId = Consultation::generateNextConsultationId();
        $this->assertEquals('CONS007', $nextId);

        // Now create a consultation without providing consultation_id
        $newConsultation = Consultation::create([
            'client_id' => $client->id,
            'status' => 'scheduled',
            'scheduled_at' => now(),
        ]);

        $this->assertEquals('CONS007', $newConsultation->consultation_id);
    }

    public function test_same_email_can_submit_multiple_intake_forms_and_book_multiple_consultations()
    {
        $slot1 = ConsultationSlot::create([
            'consultation_datetime' => now()->addDays(1)->startOfHour(),
            'status' => 'available',
            'type' => 'Standard',
            'booked_slots' => 0,
            'max_slots' => 1,
        ]);

        $slot2 = ConsultationSlot::create([
            'consultation_datetime' => now()->addDays(2)->startOfHour(),
            'status' => 'available',
            'type' => 'Standard',
            'booked_slots' => 0,
            'max_slots' => 1,
        ]);

        // Submit first intake form with free coupon for test@example.com
        $response1 = $this->postJson('/api/client-intake', [
            'first_name' => 'Jane',
            'last_name' => 'Smith',
            'email' => 'test@example.com',
            'address' => '456 High St',
            'emergency_contact_name' => 'Bob',
            'emergency_contact_phone' => '123456789',
            'emergency_contact_relationship' => 'Friend',
            'terms_accepted' => true,
            'create_client' => true,
            'consultation_fee' => 0,
            'discount_code' => 'FREE100',
            'consultation_slot_id' => $slot1->id,
        ]);

        $response1->assertSuccessful();

        // Verify client created and first consultation created
        $client = Client::where('email', 'test@example.com')->first();
        $this->assertNotNull($client);
        $this->assertEquals(1, Consultation::where('client_id', $client->id)->count());

        // Submit second intake form with SAME EMAIL test@example.com
        $response2 = $this->postJson('/api/client-intake', [
            'first_name' => 'Jane',
            'last_name' => 'Smith',
            'email' => 'test@example.com',
            'address' => '456 High St',
            'emergency_contact_name' => 'Bob',
            'emergency_contact_phone' => '123456789',
            'emergency_contact_relationship' => 'Friend',
            'terms_accepted' => true,
            'create_client' => true,
            'consultation_fee' => 0,
            'discount_code' => 'FREE100',
            'consultation_slot_id' => $slot2->id,
        ]);

        $response2->assertSuccessful();

        // Verify that 2 distinct consultations exist for the same client email
        $clientIds = Client::where('email', 'test@example.com')->pluck('id');
        $consultations = Consultation::whereIn('client_id', $clientIds)->get();
        $this->assertCount(2, $consultations);
        $this->assertNotEquals($consultations[0]->consultation_id, $consultations[1]->consultation_id);
    }
}
