<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\User;
use App\Services\ClientFeedbackService;
use App\Services\EmailService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ClientFeedbackMilestoneTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Seed default company settings
        DB::table('company_settings')->updateOrInsert(
            ['key' => 'feedback_form_enabled'],
            ['value' => '1', 'updated_at' => now()]
        );
        DB::table('company_settings')->updateOrInsert(
            ['key' => 'feedback_form_session_number'],
            ['value' => '4', 'updated_at' => now()]
        );
        DB::table('company_settings')->updateOrInsert(
            ['key' => 'feedback_form_url'],
            ['value' => 'https://form.jotform.com/vanquish-feedback-test', 'updated_at' => now()]
        );
    }

    public function test_milestone_feedback_email_sends_at_milestone_4()
    {
        $mockEmailService = $this->mock(EmailService::class);
        $mockEmailService->shouldReceive('sendAndLog')
            ->once()
            ->withArgs(function ($client, $template, $params) {
                return $template === 'feedback_form'
                    && $params['feedback_url'] === 'https://form.jotform.com/vanquish-feedback-test'
                    && $params['milestone'] === 4;
            })
            ->andReturn(true);

        $client = Client::create([
            'name' => 'Alice Feedback',
            'email' => 'alice@example.com',
            'stage' => 'Active Therapy',
            'sessions_completed' => 4,
        ]);

        $service = app(ClientFeedbackService::class);
        $sent = $service->sendMilestoneFeedbackIfEligible($client);

        $this->assertTrue($sent);
        $client->refresh();
        $this->assertEquals(4, $client->last_feedback_milestone);
        $this->assertEquals([4], $client->feedback_milestones_sent);
        $this->assertNotNull($client->last_feedback_sent_at);
    }

    public function test_milestone_feedback_only_sends_once_per_milestone()
    {
        $mockEmailService = $this->mock(EmailService::class);
        $mockEmailService->shouldReceive('sendAndLog')->never();

        $client = Client::create([
            'name' => 'Bob Already Sent',
            'email' => 'bob@example.com',
            'stage' => 'Active Therapy',
            'sessions_completed' => 4,
            'last_feedback_milestone' => 4,
            'feedback_milestones_sent' => [4],
        ]);

        $service = app(ClientFeedbackService::class);
        $sent = $service->sendMilestoneFeedbackIfEligible($client);

        $this->assertFalse($sent);
    }

    public function test_milestone_feedback_does_not_send_if_under_milestone()
    {
        $mockEmailService = $this->mock(EmailService::class);
        $mockEmailService->shouldReceive('sendAndLog')->never();

        $client = Client::create([
            'name' => 'Charlie Sessions',
            'email' => 'charlie@example.com',
            'stage' => 'Active Therapy',
            'sessions_completed' => 3,
        ]);

        $service = app(ClientFeedbackService::class);
        $sent = $service->sendMilestoneFeedbackIfEligible($client);

        $this->assertFalse($sent);
    }

    public function test_milestone_feedback_does_not_send_when_disabled()
    {
        DB::table('company_settings')->where('key', 'feedback_form_enabled')->update(['value' => '0']);

        $mockEmailService = $this->mock(EmailService::class);
        $mockEmailService->shouldReceive('sendAndLog')->never();

        $client = Client::create([
            'name' => 'Dave Disabled',
            'email' => 'dave@example.com',
            'stage' => 'Active Therapy',
            'sessions_completed' => 4,
        ]);

        $service = app(ClientFeedbackService::class);
        $sent = $service->sendMilestoneFeedbackIfEligible($client);

        $this->assertFalse($sent);
    }

    public function test_milestone_feedback_does_not_send_when_url_empty()
    {
        DB::table('company_settings')->where('key', 'feedback_form_url')->update(['value' => '']);

        $mockEmailService = $this->mock(EmailService::class);
        $mockEmailService->shouldReceive('sendAndLog')->never();

        $client = Client::create([
            'name' => 'Eve No URL',
            'email' => 'eve@example.com',
            'stage' => 'Active Therapy',
            'sessions_completed' => 4,
        ]);

        $service = app(ClientFeedbackService::class);
        $sent = $service->sendMilestoneFeedbackIfEligible($client);

        $this->assertFalse($sent);
    }

    public function test_custom_milestone_number_is_respected()
    {
        DB::table('company_settings')->where('key', 'feedback_form_session_number')->update(['value' => '6']);

        $mockEmailService = $this->mock(EmailService::class);
        $mockEmailService->shouldReceive('sendAndLog')
            ->once()
            ->withArgs(function ($client, $template, $params) {
                return $template === 'feedback_form' && $params['milestone'] === 6;
            })
            ->andReturn(true);

        $client = Client::create([
            'name' => 'Frank Custom Milestone',
            'email' => 'frank@example.com',
            'stage' => 'Active Therapy',
            'sessions_completed' => 6,
        ]);

        $service = app(ClientFeedbackService::class);
        $sent = $service->sendMilestoneFeedbackIfEligible($client);

        $this->assertTrue($sent);
        $client->refresh();
        $this->assertEquals(6, $client->last_feedback_milestone);
        $this->assertEquals([6], $client->feedback_milestones_sent);
    }

    public function test_admin_settings_can_be_updated()
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $response = $this->actingAs($admin)->putJson('/api/company-settings', [
            'feedback_form_enabled' => '1',
            'feedback_form_session_number' => 8,
            'feedback_form_url' => 'https://example.com/vqt-survey',
        ]);

        $response->assertStatus(200);

        $this->assertDatabaseHas('company_settings', [
            'key' => 'feedback_form_session_number',
            'value' => '8',
        ]);
        $this->assertDatabaseHas('company_settings', [
            'key' => 'feedback_form_url',
            'value' => 'https://example.com/vqt-survey',
        ]);
    }

    public function test_artisan_command_sends_feedback_forms_for_eligible_clients()
    {
        $mockEmailService = $this->mock(EmailService::class);
        $mockEmailService->shouldReceive('sendAndLog')
            ->once()
            ->andReturn(true);

        Client::create([
            'name' => 'Grace Artisan',
            'email' => 'grace@example.com',
            'stage' => 'Active Therapy',
            'sessions_completed' => 4,
        ]);

        $this->artisan('clients:send-feedback-forms')
            ->expectsOutputToContain('1 feedback form email(s) sent')
            ->assertExitCode(0);
    }
}
