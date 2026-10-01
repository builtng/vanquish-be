<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\ClientTcMatch;
use App\Models\EmailLog;
use App\Models\TrainingCounsellor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class MatchAssignedAgreementSignedTest extends TestCase
{
    use RefreshDatabase;

    public function test_send_match_notification_does_not_reset_signed_agreement_status()
    {
        Mail::fake();

        $client = Client::create([
            'client_id' => 'CL300',
            'uuid' => 'uuid-match-300',
            'name' => 'John SignedClient',
            'email' => 'john.signed300@example.com',
            'agreement_status' => 'signed',
            'agreement_signed_at' => now(),
            'stage' => 'Agreement Signed',
        ]);

        $tc = TrainingCounsellor::create([
            'tc_id' => 'TC300',
            'name' => 'Dr Jane Counselor',
            'first_name' => 'Jane',
            'last_name' => 'Counselor',
            'email' => 'jane.tc@example.com',
            'counsellor_type' => 'Qualified',
        ]);

        $match = ClientTcMatch::create([
            'client_id' => $client->id,
            'tc_id' => $tc->id,
            'match_score' => 95,
        ]);

        $emailService = app(\App\Services\EmailService::class);
        $emailService->sendMatchNotification($client, $tc, $match);

        $client->refresh();
        $this->assertEquals('signed', $client->agreement_status);
        $this->assertEquals('Matched with TC', $client->stage);

        $log = EmailLog::where('client_id', $client->id)->where('template_name', 'client_matched')->first();
        $this->assertNotNull($log);
    }

    public function test_submitting_agreement_when_already_signed_returns_error()
    {
        $client = Client::create([
            'client_id' => 'CL301',
            'uuid' => 'uuid-match-301',
            'name' => 'Jane AlreadySigned',
            'email' => 'jane.signed301@example.com',
            'agreement_status' => 'signed',
            'agreement_signed_at' => now(),
            'stage' => 'Agreement Signed',
        ]);

        // Prefill check
        $prefillResponse = $this->getJson("/api/client-agreement/prefill/{$client->uuid}");
        $prefillResponse->assertStatus(400)->assertJson(['already_signed' => true]);

        // Submit check
        $submitResponse = $this->postJson('/api/client-agreement/submit', [
            'client_uuid' => $client->uuid,
            'email' => $client->email,
            'full_name' => $client->name,
            'emergency_contact_name' => 'Contact Name',
            'emergency_contact_relationship' => 'Friend',
            'emergency_contact_phone' => '07123456789',
            'gp_name' => 'Dr GP',
            'gp_practice_name' => 'Practice',
            'gp_practice_phone' => '02012345678',
            'current_address' => '456 Street',
            'signature_data' => 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==',
            'signature_date' => now()->toDateString(),
            'terms_agreed' => true,
        ]);

        $submitResponse->assertStatus(400)->assertJson(['already_signed' => true]);
    }
}
