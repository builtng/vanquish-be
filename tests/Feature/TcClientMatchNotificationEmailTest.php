<?php

namespace Tests\Feature;

use App\Mail\DynamicEmail;
use App\Models\Client;
use App\Models\ClientTcMatch;
use App\Models\EmailLog;
use App\Models\Message;
use App\Models\TrainingCounsellor;
use App\Models\User;
use App\Services\EmailService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class TcClientMatchNotificationEmailTest extends TestCase
{
    use RefreshDatabase;

    public function test_matching_trainee_counsellor_sends_client_email_and_tc_portal_notification_email(): void
    {
        Mail::fake();

        $admin = User::factory()->create(['role' => 'admin']);

        $tc = TrainingCounsellor::create([
            'tc_id' => 'TC-TR-01',
            'name' => 'Sarah Johnson',
            'email' => 'sarah.tc@example.com',
            'status' => 'Active',
            'counsellor_type' => 'Trainee',
            'current_clients' => 0,
        ]);

        $client = Client::create([
            'client_id' => 'CL-LC-01',
            'name' => 'Emma Watson',
            'email' => 'emma.client@example.com',
            'service_type' => 'Low Cost',
            'stage' => 'Pending Match',
            'status' => 'Active',
            'agreement_status' => 'pending',
        ]);

        $response = $this->actingAs($admin)->postJson('/api/matches', [
            'client_id' => $client->uuid,
            'tc_id' => $tc->uuid,
            'send_notification' => true,
        ]);

        $response->assertStatus(201);

        // 1. Client receives their own email (client_matched)
        Mail::assertSent(DynamicEmail::class, function (DynamicEmail $mail) use ($client) {
            return $mail->hasTo($client->email) && $mail->template->type === 'client_matched';
        });

        // 2. TC receives the requested match notification email
        Mail::assertSent(DynamicEmail::class, function (DynamicEmail $mail) use ($tc) {
            if (!$mail->hasTo($tc->email) || $mail->template->type !== 'tc_match_notification') {
                return false;
            }

            $content = $mail->render();

            // Must contain "Hi Sarah."
            $hasGreeting = str_contains($content, 'Hi Sarah.') || str_contains($content, 'Hi ' . $tc->first_name . '.');

            // Must contain "You have a message in your portal to attend to, kindly."
            $hasMessageNotice = str_contains($content, 'You have a message in your portal to attend to, kindly.');

            // Must contain login button / counsellor-login link
            $hasLoginButton = str_contains($content, '/counsellor-login');

            return $hasGreeting && $hasMessageNotice && $hasLoginButton;
        });

        // 3. Verify in-portal message was created for the TC
        $portalMessage = Message::where('to_tc_id', $tc->id)->first();
        $this->assertNotNull($portalMessage, 'In-portal message should be created for the TC');
        $this->assertEquals('staff_to_counsellor', $portalMessage->type);
        $this->assertStringContainsString('Emma .W', $portalMessage->subject);
        $this->assertEquals($client->id, $portalMessage->related_client_id);
    }

    public function test_matching_signed_client_sends_client_matched_email_and_tc_email(): void
    {
        Mail::fake();

        $admin = User::factory()->create(['role' => 'admin']);

        $tc = TrainingCounsellor::create([
            'tc_id' => 'TC-TR-02',
            'name' => 'Michael Scott',
            'email' => 'michael.tc@example.com',
            'status' => 'Active',
            'counsellor_type' => 'Trainee',
            'current_clients' => 0,
        ]);

        $client = Client::create([
            'client_id' => 'CL-LC-02',
            'name' => 'Pam Beesly',
            'email' => 'pam.client@example.com',
            'service_type' => 'Low Cost',
            'stage' => 'Agreement Signed',
            'status' => 'Active',
            'agreement_status' => 'signed',
            'agreement_signed_at' => now(),
        ]);

        $response = $this->actingAs($admin)->postJson('/api/matches', [
            'client_id' => $client->uuid,
            'tc_id' => $tc->uuid,
            'send_notification' => true,
        ]);

        $response->assertStatus(201);

        // Client gets client_matched email
        Mail::assertSent(DynamicEmail::class, function (DynamicEmail $mail) use ($client) {
            return $mail->hasTo($client->email) && $mail->template->type === 'client_matched';
        });

        // TC gets their match notification email
        Mail::assertSent(DynamicEmail::class, function (DynamicEmail $mail) use ($tc) {
            if (!$mail->hasTo($tc->email) || $mail->template->type !== 'tc_match_notification') {
                return false;
            }

            $content = $mail->render();
            return str_contains($content, 'Hi Michael.')
                && str_contains($content, 'You have a message in your portal to attend to, kindly.')
                && str_contains($content, '/counsellor-login');
        });
    }

    public function test_send_match_notification_sends_match_assigned_when_agreement_unsigned(): void
    {
        Mail::fake();

        $tc = TrainingCounsellor::create([
            'tc_id' => 'TC-TR-03',
            'name' => 'Jim Halpert',
            'email' => 'jim.tc@example.com',
            'status' => 'Active',
            'counsellor_type' => 'Trainee',
            'current_clients' => 0,
        ]);

        $client = Client::create([
            'client_id' => 'CL-LC-03',
            'name' => 'Dwight Schrute',
            'email' => 'dwight.client@example.com',
            'service_type' => 'Low Cost',
            'stage' => 'Intake Submitted',
            'status' => 'Active',
            'agreement_status' => 'pending',
        ]);

        $match = ClientTcMatch::create([
            'client_id' => $client->id,
            'tc_id' => $tc->id,
            'match_score' => 90,
        ]);

        $emailService = app(EmailService::class);
        $emailService->sendMatchNotification($client, $tc, $match);

        // Client gets match_assigned email
        Mail::assertSent(DynamicEmail::class, function (DynamicEmail $mail) use ($client) {
            return $mail->hasTo($client->email) && $mail->template->type === 'match_assigned';
        });

        // TC gets tc_match_notification email
        Mail::assertSent(DynamicEmail::class, function (DynamicEmail $mail) use ($tc) {
            return $mail->hasTo($tc->email)
                && $mail->template->type === 'tc_match_notification'
                && str_contains($mail->render(), 'Hi Jim.')
                && str_contains($mail->render(), 'You have a message in your portal to attend to, kindly.')
                && str_contains($mail->render(), '/counsellor-login');
        });
    }

    public function test_first_name_accessor_extracts_correct_first_name(): void
    {
        $tc1 = new TrainingCounsellor(['name' => 'Dr. Jane Smith']);
        $this->assertEquals('Jane', $tc1->first_name);

        $tc2 = new TrainingCounsellor(['name' => 'John Doe']);
        $this->assertEquals('John', $tc2->first_name);

        $tc3 = new TrainingCounsellor(['name' => 'Robert', 'legal_first_name' => 'Rob']);
        $this->assertEquals('Rob', $tc3->first_name);
    }

    public function test_client_abbreviated_name_formats_correctly(): void
    {
        $client1 = new Client(['name' => 'Charles Smith']);
        $this->assertEquals('Charles .S', $client1->abbreviated_name);

        $client2 = new Client(['name' => 'Victor Ijomah']);
        $this->assertEquals('Victor .I', $client2->abbreviated_name);

        $client3 = new Client(['name' => 'SingleName']);
        $this->assertEquals('SingleName', $client3->abbreviated_name);

        $client4 = new Client(['first_name' => 'Jane', 'last_name' => 'Doe']);
        $this->assertEquals('Jane .D', $client4->abbreviated_name);
    }
}
