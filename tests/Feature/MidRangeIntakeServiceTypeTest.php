<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\ClientIntakeForm;
use App\Models\Consultation;
use App\Models\ConsultationSlot;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MidRangeIntakeServiceTypeTest extends TestCase
{
    use RefreshDatabase;

    public function test_mid_range_intake_creates_client_and_form_with_mid_range_service_type(): void
    {
        $slot = ConsultationSlot::create([
            'consultation_datetime' => Carbon::tomorrow()->setTime(14, 0),
            'status' => 'available',
            'max_slots' => 1,
            'booked_slots' => 0,
        ]);

        $payload = [
            'first_name' => 'Marny',
            'last_name' => 'Wyatt',
            'email' => 'marny.wyatt@example.com',
            'phone' => '07123456789',
            'age' => 37,
            'service_type' => 'Mid Range',
            'is_couples' => false,
            'address' => '123 High Street, London',
            'emergency_contact_name' => 'Jane Wyatt',
            'emergency_contact_phone' => '07987654321',
            'emergency_contact_relationship' => 'Spouse',
            'terms_accepted' => true,
            'consultation_fee' => 0,
            'consultation_slot_id' => $slot->id,
        ];

        $response = $this->postJson('/api/client-intake', $payload);
        $response->assertStatus(201);

        // Verify intake form has Mid Range service type
        $form = ClientIntakeForm::where('email', 'marny.wyatt@example.com')->first();
        $this->assertNotNull($form);
        $this->assertEquals('Mid Range', $form->service_type);

        // Verify client was created and has Mid Range service type
        $client = Client::where('email', 'marny.wyatt@example.com')->first();
        $this->assertNotNull($client);
        $this->assertEquals('Mid Range', $client->service_type);
        $this->assertEquals('Marny Wyatt', $client->name);

        // Verify consultation was booked and linked to client
        $consultation = Consultation::where('client_id', $client->id)->first();
        $this->assertNotNull($consultation);
        $this->assertEquals('scheduled', $consultation->status);
        $this->assertEquals('paid', $consultation->payment_status);

        // Verify consultation endpoint returns client with Mid Range service type
        $user = User::factory()->create(['role' => 'admin']);
        $consultationsResponse = $this->actingAs($user)->getJson('/api/consultations');
        $consultationsResponse->assertStatus(200);
        $consultationData = collect($consultationsResponse->json())->firstWhere('id', $consultation->id);
        $this->assertNotNull($consultationData);
        $this->assertEquals('Mid Range', $consultationData['client']['service_type']);
    }

    public function test_intake_updates_existing_client_service_type_if_previously_different(): void
    {
        // Existing client with Low Cost
        $existing = Client::create([
            'client_id' => 'CL999',
            'name' => 'Marny Wyatt',
            'email' => 'marny.wyatt@example.com',
            'service_type' => 'Low Cost',
            'stage' => 'Lead',
        ]);

        $payload = [
            'first_name' => 'Marny',
            'last_name' => 'Wyatt',
            'email' => 'marny.wyatt@example.com',
            'age' => 37,
            'service_type' => 'Mid Range',
            'is_couples' => false,
            'address' => '123 High Street, London',
            'emergency_contact_name' => 'Jane Wyatt',
            'emergency_contact_phone' => '07987654321',
            'emergency_contact_relationship' => 'Spouse',
            'terms_accepted' => true,
        ];

        $response = $this->postJson('/api/client-intake', $payload);
        $response->assertStatus(201);

        $newClient = Client::where('email', 'marny.wyatt@example.com')->where('id', '!=', $existing->id)->first();
        $this->assertNotNull($newClient);
        $this->assertEquals('Mid Range', $newClient->service_type);
    }

    public function test_birth_year_is_converted_to_accurate_age(): void
    {
        $currentYear = (int) date('Y');
        $expectedAgeFrom92 = $currentYear - 1992;

        $payload = [
            'first_name' => 'John',
            'last_name' => 'Doe',
            'email' => 'john.doe92@example.com',
            'age' => 92, // entered as 2-digit birth year 92
            'service_type' => 'Mid Range',
            'address' => '123 High Street, London',
            'emergency_contact_name' => 'Jane Doe',
            'emergency_contact_phone' => '07987654321',
            'emergency_contact_relationship' => 'Spouse',
            'terms_accepted' => true,
        ];

        $response = $this->postJson('/api/client-intake', $payload);
        $response->assertStatus(201);

        $client = Client::where('email', 'john.doe92@example.com')->first();
        $this->assertNotNull($client);
        $this->assertEquals($expectedAgeFrom92, $client->age);

        $form = ClientIntakeForm::where('email', 'john.doe92@example.com')->first();
        $this->assertNotNull($form);
        $this->assertEquals($expectedAgeFrom92, $form->age);
    }
}
