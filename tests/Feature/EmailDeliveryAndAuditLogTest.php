<?php

namespace Tests\Feature;

use App\Mail\DynamicEmail;
use App\Models\Client;
use App\Models\EmailLog;
use App\Models\User;
use App\Services\EmailService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class EmailDeliveryAndAuditLogTest extends TestCase
{
    use RefreshDatabase;

    protected User $adminUser;
    protected User $counsellorUser;

    protected function setUp(): void
    {
        parent::setUp();

        $this->adminUser = User::factory()->create([
            'email' => 'admin@vqtmanagement.com',
            'role' => 'admin',
            'is_active' => true,
        ]);

        $this->counsellorUser = User::factory()->create([
            'email' => 'counsellor@example.com',
            'role' => 'training_counsellor',
            'is_active' => true,
        ]);
    }

    /**
     * Requirement 4: Reply-To is set to help@vanquishtherapies.co.uk on every DynamicEmail,
     * and the rendered layout footer displays help@vanquishtherapies.co.uk.
     */
    public function test_dynamic_email_envelope_includes_help_reply_to_and_footer_support_address(): void
    {
        $mailable = new DynamicEmail('payment_confirmation', [
            'client_name' => 'Jane Doe',
            'email' => 'jane@example.com',
        ]);

        $envelope = $mailable->envelope();
        $this->assertNotEmpty($envelope->replyTo, 'Envelope must have reply-to configured.');
        $this->assertEquals('help@vanquishtherapies.co.uk', $envelope->replyTo[0]->address);

        $rendered = $mailable->render();
        $this->assertStringContainsString('help@vanquishtherapies.co.uk', $rendered);
        $this->assertStringContainsString('Need help or have questions?', $rendered);
    }

    /**
     * Requirement 3 & 6: Every trigger in EmailService produces exactly one EmailLog row with details.
     */
    public function test_email_service_send_and_log_creates_single_email_log_row(): void
    {
        Mail::fake();

        $client = Client::create([
            'client_id' => 'CL-TEST-901',
            'uuid' => (string) \Illuminate\Support\Str::uuid(),
            'email' => 'client@example.com',
            'name' => 'Test Client',
            'service_type' => 'Low Cost',
        ]);

        $emailService = app(EmailService::class);
        $initialCount = EmailLog::count();

        $success = $emailService->sendAndLog(
            $client,
            'payment_confirmation',
            ['client_name' => 'Test Client', 'submission_id' => $client->id],
            $client
        );

        $this->assertTrue($success);
        $this->assertEquals($initialCount + 1, EmailLog::count(), 'Exactly one EmailLog row must be created.');

        $log = EmailLog::latest('id')->first();
        $this->assertEquals('client@example.com', $log->email);
        $this->assertEquals('payment_confirmation', $log->template_name);
        $this->assertEquals('sent', $log->status);
        $this->assertEquals($client->id, $log->submission_id);
        $this->assertEquals($client->id, $log->client_id);
        $this->assertNotNull($log->sent_at);
        $this->assertNull($log->error_message);
    }

    /**
     * Requirement 6: A simulated mail delivery failure is logged as 'failed' and does not crash calling code.
     */
    public function test_simulated_email_failure_is_logged_as_failed_without_crashing(): void
    {
        // Force Mail::send to throw an exception across all retry attempts (2 attempts)
        Mail::shouldReceive('to')
            ->twice()
            ->andThrow(new \Exception('Simulated Resend API timeout or 429 rate limit exceeded'));

        $emailService = app(EmailService::class);
        $success = $emailService->sendAndLog(
            'failtest@example.com',
            'consultation_booking_confirmation',
            ['client_name' => 'Failure Test']
        );

        $this->assertFalse($success, 'sendAndLog should return false on failure without crashing.');

        $log = EmailLog::where('email', 'failtest@example.com')->first();
        $this->assertNotNull($log);
        $this->assertEquals('failed', $log->status);
        $this->assertStringContainsString('Simulated Resend API timeout', $log->error_message);
    }

    /**
     * Requirement 3: Admin page API endpoint GET /api/email-logs lists rows with filters.
     */
    public function test_admin_can_view_email_logs_api(): void
    {
        EmailLog::create([
            'email' => 'sent1@example.com',
            'template_name' => 'payment_confirmation',
            'status' => 'sent',
            'sent_at' => now(),
            'payload' => ['name' => 'Sent User'],
        ]);

        EmailLog::create([
            'email' => 'failed1@example.com',
            'template_name' => 'client_matched',
            'status' => 'failed',
            'error_message' => '550 User not found',
            'payload' => ['name' => 'Failed User'],
        ]);

        $response = $this->actingAs($this->adminUser, 'sanctum')
            ->getJson('/api/email-logs');

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'summary' => ['total', 'sent', 'failed', 'pending'],
            'logs' => ['data', 'total', 'current_page', 'last_page'],
        ]);

        $this->assertGreaterThanOrEqual(2, $response->json('summary.total'));

        // Test filtering by status
        $filterResponse = $this->actingAs($this->adminUser, 'sanctum')
            ->getJson('/api/email-logs?status=failed');

        $filterResponse->assertStatus(200);
        foreach ($filterResponse->json('logs.data') as $item) {
            $this->assertEquals('failed', $item['status']);
        }
    }

    /**
     * Requirement 3: Admin can resend a failed email via POST /api/email-logs/{id}/resend.
     */
    public function test_admin_can_resend_failed_email_from_email_logs(): void
    {
        Mail::fake();

        $log = EmailLog::create([
            'email' => 'resendtest@example.com',
            'template_name' => 'agreement_sent',
            'status' => 'failed',
            'error_message' => 'Temporary connection timeout',
            'payload' => [
                'client_name' => 'Resend Candidate',
                'agreement_url' => 'https://vanquishtherapies.co.uk/agreement/123',
            ],
        ]);

        $response = $this->actingAs($this->adminUser, 'sanctum')
            ->postJson("/api/email-logs/{$log->id}/resend");

        $response->assertStatus(200);
        $response->assertJson([
            'message' => 'Email resent successfully.',
            'log' => [
                'id' => $log->id,
                'status' => 'sent',
            ],
        ]);

        $log->refresh();
        $this->assertEquals('sent', $log->status);
        $this->assertNotNull($log->sent_at);
        $this->assertNull($log->error_message);
    }

    /**
     * Non-admin roles (e.g. counsellors) cannot access email logs API.
     */
    public function test_counsellor_cannot_access_email_logs_api(): void
    {
        $response = $this->actingAs($this->counsellorUser, 'sanctum')
            ->getJson('/api/email-logs');

        $response->assertStatus(403);
    }
}
