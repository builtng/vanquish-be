<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Consultation;
use App\Models\EmailLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use App\Mail\DynamicEmail;
use Tests\TestCase;

class AgreementEmailTest extends TestCase
{
    use RefreshDatabase;

    public function test_send_agreement_populates_valid_link_and_all_placeholder_aliases()
    {
        Mail::fake();

        $client = Client::create([
            'client_id' => 'CL100',
            'uuid' => 'client-uuid-1234',
            'name' => 'John AgreementTest',
            'email' => 'john.agreement@example.com',
            'service_type' => 'Low Cost',
            'address' => '123 Test Street',
        ]);

        $emailService = app(\App\Services\EmailService::class);
        $success = $emailService->sendAgreementEmail($client);

        $this->assertTrue($success);

        $log = EmailLog::where('client_id', $client->id)->where('template_name', 'agreement_sent')->first();
        $this->assertNotNull($log);
        $this->assertNotEmpty($log->payload['agreement_url']);
        $this->assertEquals($log->payload['agreement_url'], $log->payload['agreement_link']);
        $this->assertEquals($log->payload['agreement_url'], $log->payload['agreementUrl']);

        $client->refresh();
        $this->assertEquals('sent', $client->agreement_status);
        $this->assertEquals('Agreement Sent', $client->stage);
    }

    public function test_completing_consultation_automatically_sends_agreement_email()
    {
        Mail::fake();

        $user = User::factory()->create(['role' => 'admin']);

        $client = Client::create([
            'client_id' => 'CL101',
            'uuid' => 'client-uuid-5678',
            'name' => 'Jane AutoAgreement',
            'email' => 'jane.auto@example.com',
            'service_type' => 'Mid Range',
            'address' => '456 Test Street',
        ]);

        $consultation = Consultation::create([
            'consultation_id' => 'CONS100',
            'client_id' => $client->id,
            'scheduled_at' => now(),
            'status' => 'scheduled',
        ]);

        $response = $this->actingAs($user, 'sanctum')->postJson("/api/consultations/{$consultation->id}/complete", [
            'duration_minutes' => 45,
            'outcome' => 'approved',
            'recommended_service' => 'Low Cost Counselling',
            'notes' => 'Consultation completed successfully',
        ]);

        $response->assertStatus(200);

        $log = EmailLog::where('client_id', $client->id)->where('template_name', 'agreement_sent')->first();
        $this->assertNotNull($log);
        $this->assertNotEmpty($log->payload['agreement_url']);

        $client->refresh();
        $this->assertEquals('sent', $client->agreement_status);
        $this->assertEquals('Agreement Sent', $client->stage);
    }
}
