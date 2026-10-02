<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\TrainingCounsellor;
use App\Models\Message;
use App\Mail\DynamicEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class MessageNotificationEmailTest extends TestCase
{
    use RefreshDatabase;

    public function test_message_waiting_counsellor_email_contains_exact_copy_and_no_message_content(): void
    {
        $data = [
            'tc_name' => 'Jane Counsellor',
            'login_url' => 'https://example.com/counsellor-login',
            'portal_url' => 'https://example.com/counsellor-portal/messages',
        ];

        $mailable = new DynamicEmail('message_waiting_counsellor', $data);

        $envelope = $mailable->envelope();
        $this->assertEquals('You have a new message', $envelope->subject);

        $html = $mailable->render();
        $this->assertStringContainsString('Jane Counsellor', $html);
        $this->assertStringContainsString('You have received a new message on the Vanquish Therapies system. For privacy, the message is not included in this email. Please log in to read it.', $html);
        $this->assertStringContainsString('Log in to read your message', $html);
        $this->assertStringContainsString('https://example.com/counsellor-login', $html);
        // Ensure no sensitive content leaks
        $this->assertStringNotContainsString('{{message}}', $html);
    }

    public function test_message_waiting_admin_email_contains_exact_copy_and_no_message_content(): void
    {
        $data = [
            'recipient_name' => 'Admin Staff',
            'sender_name' => 'Jane Counsellor',
            'login_url' => 'https://example.com/login',
            'dashboard_url' => 'https://example.com/dashboard/messages',
        ];

        $mailable = new DynamicEmail('message_waiting_admin', $data);

        $envelope = $mailable->envelope();
        $this->assertEquals('You have a new message', $envelope->subject);

        $html = $mailable->render();
        $this->assertStringContainsString('Admin Staff', $html);
        $this->assertStringContainsString('You have received a new message on the Vanquish Therapies system. For privacy, the message is not included in this email. Please log in to read it.', $html);
        $this->assertStringContainsString('Log in to read your message', $html);
        $this->assertStringContainsString('https://example.com/login', $html);
        // Ensure no sensitive content leaks
        $this->assertStringNotContainsString('{{message}}', $html);
    }

    public function test_staff_send_to_counsellor_triggers_privacy_email_with_exact_copy(): void
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
            'message' => 'This is highly sensitive client therapy details that must never appear in email.',
        ]);

        $response->assertStatus(201);

        Mail::assertSent(DynamicEmail::class, 1);
        Mail::assertSent(DynamicEmail::class, function ($mail) use ($tc) {
            $envelope = $mail->envelope();
            $html = $mail->render();

            return $mail->hasTo($tc->email)
                && $envelope->subject === 'You have a new message'
                && str_contains($html, 'You have received a new message on the Vanquish Therapies system. For privacy, the message is not included in this email. Please log in to read it.')
                && str_contains($html, 'Log in to read your message')
                && !str_contains($html, 'This is highly sensitive client therapy details that must never appear in email.');
        });
    }

    public function test_15_minute_throttle_prevents_repeat_emails_within_window(): void
    {
        Mail::fake();

        $admin = User::factory()->create([
            'role' => 'admin',
            'email' => 'admin@vanquish.test',
        ]);

        $tc = TrainingCounsellor::create([
            'uuid' => 'tc-uuid-throttle',
            'tc_id' => 'TC-THROTTLE',
            'name' => 'Throttle Counsellor',
            'email' => 'throttle@therapist.test',
        ]);

        // Send 1st message
        $response1 = $this->actingAs($admin, 'sanctum')->postJson('/api/messages/send-to-counsellor', [
            'tc_ids' => [$tc->id],
            'subject' => 'First message',
            'message' => 'Message 1 body',
        ]);
        $response1->assertStatus(201);

        // 1 email should have been sent
        Mail::assertSent(DynamicEmail::class, 1);

        // Send 2nd message immediately (within 15 minutes)
        $response2 = $this->actingAs($admin, 'sanctum')->postJson('/api/messages/send-to-counsellor', [
            'tc_ids' => [$tc->id],
            'subject' => 'Second message',
            'message' => 'Message 2 body',
        ]);
        $response2->assertStatus(201);

        // Advance 5 minutes (still within 15 minutes)
        Carbon::setTestNow(now()->addMinutes(5));

        // Send 3rd message
        $response3 = $this->actingAs($admin, 'sanctum')->postJson('/api/messages/send-to-counsellor', [
            'tc_ids' => [$tc->id],
            'subject' => 'Third message',
            'message' => 'Message 3 body',
        ]);
        $response3->assertStatus(201);

        // Verify still exactly 1 email sent in total!
        Mail::assertSent(DynamicEmail::class, 1);
    }

    public function test_subsequent_message_after_15_minutes_triggers_email_notification(): void
    {
        Mail::fake();
        Carbon::setTestNow(Carbon::parse('2026-10-01 10:00:00'));

        $admin = User::factory()->create([
            'role' => 'admin',
            'email' => 'admin@vanquish.test',
        ]);

        $tc = TrainingCounsellor::create([
            'uuid' => 'tc-uuid-window',
            'tc_id' => 'TC-WINDOW',
            'name' => 'Window Counsellor',
            'email' => 'window@therapist.test',
        ]);

        // 1st message at 10:00
        $response1 = $this->actingAs($admin, 'sanctum')->postJson('/api/messages/send-to-counsellor', [
            'tc_ids' => [$tc->id],
            'subject' => 'Morning message',
            'message' => 'Morning message body',
        ]);
        $response1->assertStatus(201);
        Mail::assertSent(DynamicEmail::class, 1);

        // Travel 16 minutes into the future (10:16)
        Carbon::setTestNow(Carbon::parse('2026-10-01 10:16:00'));

        // 2nd message at 10:16 (after 15-minute window expired)
        $response2 = $this->actingAs($admin, 'sanctum')->postJson('/api/messages/send-to-counsellor', [
            'tc_ids' => [$tc->id],
            'subject' => 'Follow up message',
            'message' => 'Follow up message body',
        ]);
        $response2->assertStatus(201);

        // Verify a 2nd email was sent!
        Mail::assertSent(DynamicEmail::class, 2);
    }

    public function test_counsellor_send_to_admin_group_notifies_admins_with_privacy_email(): void
    {
        Mail::fake();

        $admin = User::factory()->create([
            'role' => 'admin',
            'email' => 'headadmin@vanquish.test',
            'name' => 'Head Admin',
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

        Mail::assertSent(DynamicEmail::class, 1);
        Mail::assertSent(DynamicEmail::class, function ($mail) use ($admin) {
            $envelope = $mail->envelope();
            $html = $mail->render();

            return $mail->hasTo($admin->email)
                && $envelope->subject === 'You have a new message'
                && str_contains($html, 'You have received a new message on the Vanquish Therapies system. For privacy, the message is not included in this email. Please log in to read it.')
                && str_contains($html, 'Log in to read your message')
                && !str_contains($html, 'Extremely sensitive private message for admin eyes only.');
        });

        // 2nd message immediately is throttled
        $response2 = $this->actingAs($counsellorUser, 'sanctum')->postJson('/api/messages/send-to-staff', [
            'to_group' => 'admin_group',
            'subject' => 'Quick addition',
            'message' => 'Another private message right away.',
        ]);
        $response2->assertStatus(201);

        // Count remains 1
        Mail::assertSent(DynamicEmail::class, 1);
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

        Mail::assertSent(DynamicEmail::class, function ($mail) use ($recipientStaff) {
            $envelope = $mail->envelope();
            $html = $mail->render();

            return $mail->hasTo($recipientStaff->email)
                && $envelope->subject === 'You have a new message'
                && !str_contains($html, 'Internal sensitive ops message.')
                && str_contains($html, 'Liam Staff')
                && str_contains($html, 'Log in to read your message');
        });
    }
}
