<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\TrainingCounsellor;
use App\Mail\DynamicEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class MessageNotificationEmailTest extends TestCase
{
    use RefreshDatabase;

    public function test_message_waiting_counsellor_email_contains_no_message_content(): void
    {
        $data = [
            'tc_name' => 'Jane Counsellor',
            'portal_url' => 'https://example.com/counsellor-portal/messages',
        ];

        $mailable = new DynamicEmail('message_waiting_counsellor', $data);

        $envelope = $mailable->envelope();
        $this->assertEquals('New message waiting in your Counsellor Portal', $envelope->subject);

        $html = $mailable->render();
        $this->assertStringContainsString('Jane Counsellor', $html);
        $this->assertStringContainsString('https://example.com/counsellor-portal/messages', $html);
        $this->assertStringContainsString('Confidentiality Notice', $html);
        // Ensure no sensitive placeholder leaks
        $this->assertStringNotContainsString('{{message}}', $html);
    }

    public function test_message_waiting_admin_email_contains_no_message_content(): void
    {
        $data = [
            'recipient_name' => 'Admin Staff',
            'sender_name' => 'Jane Counsellor',
            'dashboard_url' => 'https://example.com/dashboard/messages',
        ];

        $mailable = new DynamicEmail('message_waiting_admin', $data);

        $envelope = $mailable->envelope();
        $this->assertEquals('New message waiting in Admin Portal', $envelope->subject);

        $html = $mailable->render();
        $this->assertStringContainsString('Admin Staff', $html);
        $this->assertStringContainsString('Jane Counsellor', $html);
        $this->assertStringContainsString('https://example.com/dashboard/messages', $html);
        $this->assertStringContainsString('Confidentiality Notice', $html);
        // Ensure no message body placeholders
        $this->assertStringNotContainsString('{{message}}', $html);
    }

    public function test_staff_send_to_counsellor_triggers_privacy_email(): void
    {
        Mail::fake();

        $admin = User::factory()->create([
            'role' => 'admin',
            'email' => 'admin@vanquish.test',
        ]);

        $tc = TrainingCounsellor::create([
            'uuid' => 'tc-uuid-1',
            'tc_id' => 'TC-1001',
            'name' => 'Sarah Therapist',
            'email' => 'sarah@therapist.test',
        ]);

        $response = $this->actingAs($admin, 'sanctum')->postJson('/api/messages/send-to-counsellor', [
            'tc_ids' => [$tc->id],
            'subject' => 'Confidential Session Update',
            'message' => 'This is highly sensitive client therapy details.',
        ]);

        $response->assertStatus(201);

        Mail::assertSent(DynamicEmail::class, function ($mail) {
            $envelope = $mail->envelope();
            $html = $mail->render();

            return $mail->template->type === 'message_waiting_counsellor'
                && $envelope->subject === 'New message waiting in your Counsellor Portal'
                && !str_contains($html, 'This is highly sensitive client therapy details.');
        });
    }

    public function test_counsellor_send_to_admin_group_triggers_privacy_email(): void
    {
        Mail::fake();

        $admin = User::factory()->create([
            'role' => 'admin',
            'email' => 'headadmin@vanquish.test',
        ]);

        $tc = TrainingCounsellor::create([
            'uuid' => 'tc-uuid-2',
            'tc_id' => 'TC-1002',
            'name' => 'Alex Counsellor',
            'email' => 'alex@counsellor.test',
        ]);

        $counsellorUser = User::factory()->create([
            'role' => 'counsellor',
            'email' => 'alex@counsellor.test',
            'training_counsellor_id' => $tc->id,
            'name' => 'Alex Counsellor',
        ]);

        $response = $this->actingAs($counsellorUser, 'sanctum')->postJson('/api/messages/send-to-staff', [
            'to_group' => 'admin_group',
            'subject' => 'Need clinical supervision note',
            'message' => 'Extremely sensitive private message for admin eyes only.',
        ]);

        $response->assertStatus(201);

        Mail::assertSent(DynamicEmail::class, function ($mail) {
            $envelope = $mail->envelope();
            $html = $mail->render();

            return $mail->template->type === 'message_waiting_admin'
                && $envelope->subject === 'New message waiting in Admin Portal'
                && !str_contains($html, 'Extremely sensitive private message for admin eyes only.');
        });
    }

    public function test_staff_send_to_staff_triggers_privacy_email(): void
    {
        Mail::fake();

        $senderStaff = User::factory()->create([
            'role' => 'admin',
            'name' => 'Emma Admin',
            'email' => 'emma@vanquish.test',
        ]);

        $recipientStaff = User::factory()->create([
            'role' => 'staff',
            'name' => 'Liam Staff',
            'email' => 'liam@vanquish.test',
        ]);

        $response = $this->actingAs($senderStaff, 'sanctum')->postJson('/api/messages/send-to-staff', [
            'to_user_ids' => [$recipientStaff->id],
            'subject' => 'Internal Operational Note',
            'message' => 'Internal sensitive ops message.',
        ]);

        $response->assertStatus(201);

        Mail::assertSent(DynamicEmail::class, function ($mail) {
            $envelope = $mail->envelope();
            $html = $mail->render();

            return $mail->template->type === 'message_waiting_admin'
                && $envelope->subject === 'New message waiting in Admin Portal'
                && !str_contains($html, 'Internal sensitive ops message.')
                && str_contains($html, 'Emma Admin')
                && str_contains($html, 'Liam Staff');
        });
    }
}
