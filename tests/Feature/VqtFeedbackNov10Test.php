<?php

namespace Tests\Feature;

use App\Mail\DynamicEmail;
use App\Models\Client;
use App\Models\Consultation;
use App\Models\EmailLog;
use App\Models\QcApplication;
use App\Models\TcIntakeForm;
use App\Models\TraineeApplication;
use App\Models\TrainingCounsellor;
use App\Models\User;
use App\Services\PlacementProfileFiller;
use App\Services\PortalAccessService;
use App\Support\AcceptanceEmailContent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * VQT system feedback, 10 November 2026 (phases 1–3).
 */
class VqtFeedbackNov10Test extends TestCase
{
    use RefreshDatabase;

    // ── Phase 1: "Too Many Attempts" ───────────────────────────────────

    public function test_uploads_and_form_submissions_use_separate_rate_limits(): void
    {
        $routes = Route::getRoutes();
        foreach (['/api/tc-intake/upload-document', '/api/qualified-counsellor/upload-document'] as $uri) {
            $middleware = $routes->match(app('request')->create($uri, 'POST'))->gatherMiddleware();
            $this->assertContains('throttle:public-uploads', $middleware, $uri);
            $this->assertNotContains('throttle:public-forms', $middleware, $uri);
        }

        foreach (['/api/tc-intake', '/api/qualified-counsellor/submit', '/api/client-intake'] as $uri) {
            $middleware = $routes->match(app('request')->create($uri, 'POST'))->gatherMiddleware();
            $this->assertContains('throttle:public-forms', $middleware, $uri);
        }
    }

    public function test_six_uploads_do_not_block_the_form_submission(): void
    {
        // 6 uploads (the qualified counsellor form) then the submit: the submit
        // must reach the controller (422 validation), not be throttled (429).
        for ($i = 0; $i < 6; $i++) {
            $this->postJson('/api/qualified-counsellor/upload-document', [])->assertStatus(422);
        }
        for ($i = 0; $i < 12; $i++) {
            $this->postJson('/api/qualified-counsellor/submit', [])->assertStatus(422);
        }
    }

    // ── Phase 1: portal login passwords ───────────────────────────────

    private function counsellor(array $attrs = []): TrainingCounsellor
    {
        return TrainingCounsellor::create(array_merge([
            'tc_id' => 'TC' . random_int(100, 999),
            'name' => 'Rooshan C',
            'email' => 'rooshan@example.com',
            'status' => 'Active',
            'counsellor_type' => 'Trainee',
        ], $attrs));
    }

    public function test_invite_gives_a_real_password_to_an_account_that_has_never_logged_in(): void
    {
        Mail::fake();
        $admin = User::factory()->create(['role' => 'admin']);
        $tc = $this->counsellor();
        $user = User::factory()->create([
            'email' => 'rooshan@example.com',
            'role' => 'counsellor',
            'training_counsellor_id' => $tc->id,
        ]);

        $this->actingAs($admin)->postJson("/api/training-counsellors/{$tc->id}/portal-invite")->assertStatus(200);

        $log = EmailLog::where('template_name', 'counsellor_portal_invite')->latest('id')->first();
        $password = $log->payload['temporary_password'];
        $this->assertStringNotContainsString('existing password', $password);
        $this->assertTrue(Hash::check($password, $user->fresh()->password), 'Emailed password must work for login');

        $this->postJson('/api/login', ['email' => 'rooshan@example.com', 'password' => $password])
            ->assertStatus(200);
        $this->assertNotNull($user->fresh()->last_login_at);
    }

    public function test_invite_keeps_the_password_of_someone_who_has_logged_in(): void
    {
        $user = User::factory()->create(['role' => 'counsellor', 'last_login_at' => now()]);
        $before = $user->password;

        $text = app(PortalAccessService::class)->passwordForInvite($user);

        $this->assertSame(PortalAccessService::EXISTING_PASSWORD_TEXT, $text);
        $this->assertSame($before, $user->fresh()->password);
    }

    public function test_invite_never_changes_a_staff_password(): void
    {
        $staff = User::factory()->create(['role' => 'admin']);
        $before = $staff->password;

        app(PortalAccessService::class)->passwordForInvite($staff);

        $this->assertSame($before, $staff->fresh()->password);
    }

    public function test_counsellor_reset_link_returns_to_counsellor_login(): void
    {
        Mail::fake();
        User::factory()->create(['email' => 'c@example.com', 'role' => 'counsellor']);

        $this->postJson('/api/forgot-password', ['email' => 'c@example.com'])->assertStatus(200);

        $log = EmailLog::where('template_name', 'password_reset')->latest('id')->first();
        $this->assertStringContainsString('portal=counsellor', $log->payload['reset_link']);
    }

    // ── Phase 1: Mid Range "Failed to create intake form" ─────────────

    public function test_paid_mid_range_intake_is_saved_with_a_pending_consultation(): void
    {
        $tc = $this->counsellor(['tc_id' => 'QC001', 'email' => 'ben@example.com', 'counsellor_type' => 'Qualified']);

        $this->postJson('/api/client-intake', [
            'first_name' => 'Jane', 'last_name' => 'Doe', 'email' => 'jane@example.com',
            'address' => '1 High St, London', 'service_type' => 'Mid Range',
            'emergency_contact_name' => 'John', 'emergency_contact_phone' => '0700',
            'emergency_contact_relationship' => 'Father', 'terms_accepted' => true,
            'consultation_fee' => 2, 'discount_code' => 'FREE',
            'consultation_with_tc_uuid' => $tc->uuid,
            'consultation_datetime' => now()->addDays(3)->format('Y-m-d') . ' 10:00',
        ])->assertStatus(201);

        $consultation = Consultation::first();
        $this->assertSame('pending', $consultation->status);
        $this->assertSame($tc->id, $consultation->tc_id);
    }

    public function test_consultation_status_column_accepts_pending_on_mysql(): void
    {
        $migration = file_get_contents(database_path('migrations/2026_11_11_091000_allow_pending_status_on_consultations.php'));
        $this->assertStringContainsString("ENUM('pending','scheduled','completed','cancelled','no_show')", $migration);
    }

    // ── Phase 2: editing a trainee's profile ──────────────────────────

    public function test_saving_a_profile_with_its_own_email_works_even_if_an_archived_duplicate_exists(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $old = $this->counsellor(['tc_id' => 'TC001']);
        $old->update(['archived_at' => now()]);
        $old->delete();
        $tc = $this->counsellor(['tc_id' => 'TC002']);

        $this->actingAs($admin)->putJson("/api/training-counsellors/{$tc->uuid}", [
            'name' => 'Rooshan C',
            'email' => 'rooshan@example.com',
            'availability' => ['monday' => ['10am-1050am']],
        ])->assertStatus(200);

        $this->assertSame(['monday' => ['10am-1050am']], $tc->fresh()->availability);
    }

    public function test_changing_to_another_live_counsellors_email_is_still_blocked(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $this->counsellor(['tc_id' => 'TC001', 'email' => 'taken@example.com']);
        $tc = $this->counsellor(['tc_id' => 'TC002']);

        $this->actingAs($admin)->putJson("/api/training-counsellors/{$tc->uuid}", [
            'email' => 'taken@example.com',
        ])->assertStatus(422);
    }

    // ── Phase 2: availability / topics from the placement application ─

    public function test_profile_is_filled_from_the_placement_application(): void
    {
        TcIntakeForm::create([
            'name' => 'Rooshan C',
            'email' => 'Rooshan@example.com',
            'modality' => 'Integrative',
            'topics_with_experience' => ['Low mood/depression'],
            'topics_not_ready_for' => ['Eating disorders'],
            'availability' => ['monday' => ['10am-1050am']],
        ]);
        $tc = $this->counsellor();

        app(PlacementProfileFiller::class)->fill($tc);

        $tc->refresh();
        $this->assertSame(['monday' => ['10am-1050am']], $tc->availability);
        $this->assertSame(['Low mood/depression'], $tc->topics_with_experience);
        $this->assertSame(['Eating disorders'], $tc->topics_not_ready_for);
        $this->assertSame('Integrative', $tc->modality);
    }

    public function test_filler_never_overwrites_what_admin_already_set(): void
    {
        TcIntakeForm::create([
            'name' => 'Rooshan C', 'email' => 'rooshan@example.com',
            'availability' => ['monday' => ['10am-1050am']],
        ]);
        $tc = $this->counsellor(['availability' => ['friday' => ['2pm-250pm']]]);

        app(PlacementProfileFiller::class)->fill($tc);

        $this->assertSame(['friday' => ['2pm-250pm']], $tc->fresh()->availability);
    }

    // ── Phase 2: manual match override ────────────────────────────────

    private function lowCostPair(): array
    {
        $tc = $this->counsellor(['availability' => ['monday' => ['10am-1050am']]]);
        $client = Client::create([
            'client_id' => 'CL001', 'name' => 'Jane Doe', 'email' => 'jane@example.com',
            'service_type' => 'Low Cost', 'stage' => 'Pending Match',
            'availability' => ['friday' => ['2pm-250pm']],
        ]);

        return [$tc, $client];
    }

    public function test_low_cost_match_without_overlap_is_refused_without_override(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        [$tc, $client] = $this->lowCostPair();

        $this->actingAs($admin)->postJson('/api/matches', [
            'client_id' => $client->uuid, 'tc_id' => $tc->uuid,
            'allocated_day' => 'Friday', 'allocated_time' => '2pm-250pm',
            'send_notification' => false,
        ])->assertStatus(422);
    }

    public function test_manual_override_requires_a_note(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        [$tc, $client] = $this->lowCostPair();

        $this->actingAs($admin)->postJson('/api/matches', [
            'client_id' => $client->uuid, 'tc_id' => $tc->uuid,
            'allocated_day' => 'Friday', 'allocated_time' => '2pm-250pm',
            'manual_override' => true, 'send_notification' => false,
        ])->assertStatus(422);
    }

    public function test_manual_override_with_a_note_matches_the_client(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        [$tc, $client] = $this->lowCostPair();

        $this->actingAs($admin)->postJson('/api/matches', [
            'client_id' => $client->uuid, 'tc_id' => $tc->uuid,
            'allocated_day' => 'Friday', 'allocated_time' => '2pm-250pm',
            'manual_override' => true, 'assignment_notes' => 'Agreed Friday 2pm with both by phone.',
            'send_notification' => false,
        ])->assertStatus(201);

        $client->refresh();
        $this->assertSame($tc->id, $client->matched_tc_id);
        $this->assertSame('Friday', $client->allocated_day);
    }

    // ── Phase 2: "Matched" but "Not assigned" ────────────────────────

    public function test_archiving_a_counsellor_returns_their_clients_to_pending_matches(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $tc = $this->counsellor();
        $client = Client::create([
            'client_id' => 'CL001', 'name' => 'Tom Smith', 'email' => 'tom@example.com',
            'service_type' => 'Low Cost', 'stage' => 'Matched with TC', 'matched_tc_id' => $tc->id,
        ]);

        $this->actingAs($admin)->deleteJson("/api/training-counsellors/{$tc->uuid}")->assertStatus(200);

        $client->refresh();
        $this->assertNull($client->matched_tc_id);
        $pending = $this->actingAs($admin)->getJson('/api/pending-matches')->json();
        $this->assertContains($client->id, collect($pending)->pluck('id')->all());
    }

    // ── Phase 3: acceptance emails ───────────────────────────────────

    public function test_trainee_acceptance_email_has_a_72_hour_accept_link(): void
    {
        Mail::fake();
        $admin = User::factory()->create(['role' => 'admin']);
        $app = TraineeApplication::create([
            'first_name' => 'Rooshan', 'last_name' => 'C', 'email' => 'rooshan@example.com',
            'status' => 'Interview Attended',
        ]);

        $this->actingAs($admin)->postJson("/api/trainee-applications/{$app->id}/decision", [
            'decision' => 'Accepted', 'induction_date' => 'Monday, 19th January, 10:00am',
        ])->assertStatus(200);

        $app->refresh();
        $this->assertNotNull($app->placement_response_sent_at);
        $log = EmailLog::where('template_name', 'trainee_placement_acceptance')->latest('id')->first();
        $this->assertStringEndsWith('/placement-response/' . $app->placement_response_token, $log->payload['acceptance_link']);

        $html = (new DynamicEmail('trainee_placement_acceptance', $log->payload))->render();
        $this->assertStringContainsString($log->payload['acceptance_link'], $html);
        $this->assertStringContainsString('72 hours', $html);
    }

    public function test_accept_link_expires_after_72_hours(): void
    {
        $app = TraineeApplication::create([
            'first_name' => 'R', 'last_name' => 'C', 'email' => 'r@example.com', 'status' => 'Accepted',
            'placement_response_token' => str_repeat('a', 40),
            'placement_response_sent_at' => now()->subHours(73),
        ]);

        $this->getJson('/api/trainee-placement-response/' . $app->placement_response_token)->assertStatus(410);
        $this->postJson('/api/trainee-placement-response/' . $app->placement_response_token, [
            'placement_accepted' => true, 'induction_rsvp' => true,
        ])->assertStatus(410);
    }

    public function test_qualified_counsellor_can_accept_through_the_link(): void
    {
        Mail::fake();
        $qc = QcApplication::create([
            'legal_first_name' => 'Vaseline', 'legal_last_name' => 'Smith', 'name' => 'Vaseline Smith',
            'email' => 'v@example.com', 'status' => 'Accepted',
            'placement_response_token' => str_repeat('b', 40),
            'placement_response_sent_at' => now(),
        ]);

        $this->getJson('/api/trainee-placement-response/' . $qc->placement_response_token)
            ->assertStatus(200)
            ->assertJson(['applicant_type' => 'qualified', 'first_name' => 'Vaseline']);

        $this->postJson('/api/trainee-placement-response/' . $qc->placement_response_token, [
            'placement_accepted' => true, 'induction_rsvp' => true,
        ])->assertStatus(200);

        $this->assertTrue($qc->fresh()->placement_accepted);
        $this->assertTrue($qc->fresh()->induction_rsvp);
    }

    public function test_qualified_counsellor_emails_do_not_say_placement(): void
    {
        $html = (new DynamicEmail('qc_placement_acceptance', [
            'first_name' => 'Vaseline', 'acceptance_link' => 'https://x/placement-response/abc', 'accept_by' => 'Friday',
        ]))->render();

        $withoutLink = str_replace('placement-response', '', $html);
        $this->assertStringNotContainsStringIgnoringCase('placement', $withoutLink);
        $this->assertStringContainsString('https://x/placement-response/abc', $html);

        foreach (['qc_onboarding_paperwork', 'qc_placement_rejection'] as $type) {
            $mail = new DynamicEmail($type, ['first_name' => 'V']);
            $this->assertStringNotContainsStringIgnoringCase('placement', $mail->render() . $mail->envelope()->subject, $type);
        }
    }

    public function test_qualified_counsellor_onboarding_email_has_no_therapy_form_or_trainee_agreement(): void
    {
        $mail = new DynamicEmail('qc_onboarding_paperwork', [
            'first_name' => 'Vaseline',
            'agreement_download_link' => 'https://x/templates/4-way-agreement-trainee.docx',
            'therapy_form_url' => 'https://x/therapy-form',
        ]);
        $html = $mail->render();

        $this->assertStringContainsString('Vaseline', $html);
        $this->assertStringContainsString('Qualified Counsellor Agreement', $html);
        $this->assertStringNotContainsStringIgnoringCase('therapy form', $html);
        $this->assertStringNotContainsString('https://x/therapy-form', $html);
        $this->assertStringNotContainsStringIgnoringCase('4-way', $html);
        $this->assertStringNotContainsStringIgnoringCase('tutor', $html);

        // Trainees still get both.
        $trainee = (new DynamicEmail('trainee_onboarding_paperwork', [
            'first_name' => 'T', 'agreement_download_link' => 'https://x/a.docx', 'therapy_form_url' => 'https://x/therapy-form',
        ]))->render();
        $this->assertStringContainsString('https://x/therapy-form', $trainee);
        $this->assertStringContainsStringIgnoringCase('4-Way Agreement', $trainee);
    }

    public function test_saved_trainee_template_gets_the_accept_button_added(): void
    {
        $body = '<p>Hi</p><h2 style="color: #6f1d56; font-size: 17px; border-bottom: 2px solid #f0e6ed; padding-bottom: 8px; margin: 24px 0 16px 0;">Immediate Paperwork Requirements</h2><p>x</p>';
        $updated = AcceptanceEmailContent::withAcceptBlock($body);

        $this->assertStringContainsString('{{acceptance_link}}', $updated);
        $this->assertTrue(strpos($updated, '{{acceptance_link}}') < strpos($updated, 'Immediate Paperwork'));
        $this->assertSame($updated, AcceptanceEmailContent::withAcceptBlock($updated), 'Adding twice must not duplicate');
    }
}
