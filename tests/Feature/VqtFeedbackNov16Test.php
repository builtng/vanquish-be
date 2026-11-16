<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\Client;
use App\Models\Session;
use App\Models\TrainingCounsellor;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Console\Scheduling\CallbackEvent;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\PersonalAccessToken;
use Tests\TestCase;

/**
 * VQT follow-up, 16 November 2026.
 */
class VqtFeedbackNov16Test extends TestCase
{
    use RefreshDatabase;

    private function counsellor(array $attrs = []): TrainingCounsellor
    {
        static $n = 100;
        $n++;

        return TrainingCounsellor::create(array_merge([
            'tc_id' => 'TC' . $n,
            'name' => 'Rooshan C' . $n,
            'email' => "tc{$n}@example.com",
            'status' => 'Active',
            'counsellor_type' => 'Trainee',
        ], $attrs));
    }

    private function counsellorUser(TrainingCounsellor $tc): User
    {
        return User::factory()->create([
            'email' => 'user-' . $tc->email,
            'role' => 'counsellor',
            'training_counsellor_id' => $tc->id,
        ]);
    }

    private function tomSmith(array $attrs = []): Client
    {
        return Client::create(array_merge([
            'client_id' => 'CL001', 'name' => 'Tom Smith', 'email' => 'tom@example.com',
            'service_type' => 'Low Cost', 'stage' => 'Consultation Completed',
        ], $attrs));
    }

    /** Each call is a fresh request: forget the user the guards cached. */
    private function freshAuth(): void
    {
        $this->app['auth']->forgetGuards();
    }

    // ── 1. Matched stage without a counsellor ────────────────────────

    public function test_progress_stage_to_any_matched_stage_without_a_counsellor_is_refused(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $client = $this->tomSmith();

        foreach (Client::MATCHED_STAGES as $stage) {
            $this->actingAs($admin)
                ->postJson("/api/clients/{$client->uuid}/progress-stage", ['stage' => $stage])
                ->assertStatus(422)
                ->assertJsonPath('message', 'Choose a counsellor before marking this client as matched');
        }

        $this->assertSame('Consultation Completed', $client->fresh()->stage);
    }

    public function test_update_to_a_matched_stage_without_a_counsellor_is_refused(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $client = $this->tomSmith();

        foreach (Client::MATCHED_STAGES as $stage) {
            $this->actingAs($admin)
                ->putJson("/api/clients/{$client->uuid}", ['stage' => $stage])
                ->assertStatus(422)
                ->assertJsonPath('message', 'Choose a counsellor before marking this client as matched');
        }

        $this->assertSame('Consultation Completed', $client->fresh()->stage);
    }

    public function test_an_archived_counsellor_does_not_count_as_matched(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $tc = $this->counsellor(['archived_at' => now()]);
        $client = $this->tomSmith(['matched_tc_id' => $tc->id]);

        $this->actingAs($admin)
            ->postJson("/api/clients/{$client->uuid}/progress-stage", ['stage' => 'Matched with TC'])
            ->assertStatus(422);
    }

    public function test_matched_stage_with_an_active_counsellor_still_works(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $tc = $this->counsellor();
        $client = $this->tomSmith(['matched_tc_id' => $tc->id]);

        $this->actingAs($admin)
            ->postJson("/api/clients/{$client->uuid}/progress-stage", ['stage' => 'Matched with TC'])
            ->assertStatus(200);
        $this->assertSame('Matched with TC', $client->fresh()->stage);

        $this->actingAs($admin)
            ->putJson("/api/clients/{$client->uuid}", ['stage' => 'Matched With Counsellor', 'name' => 'Tom Smith'])
            ->assertStatus(200);

        // Non-matched stages are unaffected for clients without a counsellor.
        $other = Client::create(['client_id' => 'CL002', 'name' => 'Ann Lee', 'email' => 'ann@example.com', 'stage' => 'Consultation Booked']);
        $this->actingAs($admin)
            ->postJson("/api/clients/{$other->uuid}/progress-stage", ['stage' => 'Agreement Sent'])
            ->assertStatus(200);
    }

    public function test_repair_command_dry_run_lists_tom_smith_without_changing_him(): void
    {
        $tom = $this->tomSmith(['stage' => 'Matched With Counsellor', 'matched_tc_id' => null]);
        $fine = $this->tomSmith([
            'client_id' => 'CL002', 'name' => 'Ann Lee', 'email' => 'ann@example.com',
            'stage' => 'Matched with TC', 'matched_tc_id' => $this->counsellor()->id,
        ]);
        $logsBefore = ActivityLog::count();

        $this->artisan('vqt:repair-unassigned-matches', ['--dry-run' => true])
            ->expectsOutputToContain('Tom Smith')
            ->expectsOutputToContain('Dry run: 1 client(s) would be moved. Nothing was changed.')
            ->doesntExpectOutputToContain('Ann Lee')
            ->assertExitCode(0);

        $this->assertSame('Matched With Counsellor', $tom->fresh()->stage);
        $this->assertSame($logsBefore, ActivityLog::count());
    }

    public function test_repair_command_moves_tom_smith_back_to_pending_matches_and_logs_it(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $tom = $this->tomSmith(['stage' => 'Matched with counsellor', 'matched_tc_id' => null]);

        $this->artisan('vqt:repair-unassigned-matches')
            ->expectsOutputToContain('Tom Smith')
            ->expectsOutputToContain('1 client(s) moved back to Pending Matches.')
            ->assertExitCode(0);

        $this->assertSame('Consultation Completed', $tom->fresh()->stage);
        $log = ActivityLog::where('action', 'client_stage_repaired')->where('model_id', $tom->id)->first();
        $this->assertNotNull($log);
        $this->assertStringContainsString('Tom Smith', $log->description);

        $pending = $this->actingAs($admin)->getJson('/api/pending-matches')->json();
        $this->assertContains($tom->id, collect($pending)->pluck('id')->all());
    }

    // ── 2. Counsellor profile documents ──────────────────────────────

    public function test_counsellor_documents_open_for_staff_and_the_counsellor_only(): void
    {
        Storage::fake('private');
        Storage::disk('private')->put('training_counsellors/cv/rooshan_cv.pdf', '%PDF-1.4 test');

        $tc = $this->counsellor(['qualification_document' => 'training_counsellors/cv/rooshan_cv.pdf']);
        $other = $this->counsellor();
        $url = "/api/training-counsellors/{$tc->uuid}/document/qualification_document";

        $superAdmin = User::factory()->create(['role' => 'super_admin']);
        $compliance = User::factory()->create(['role' => 'compliance_officer']);
        $own = $this->counsellorUser($tc);
        $otherUser = $this->counsellorUser($other);

        $this->actingAs($superAdmin)->get($url)->assertStatus(200);
        $this->actingAs($compliance)->get($url)->assertStatus(200);
        $this->actingAs($own)->get($url)->assertStatus(200);
        $this->actingAs($otherUser)->getJson($url)->assertStatus(403);
    }

    // ── 3. View a counsellor's portal, read-only ─────────────────────

    public function test_staff_roles_cannot_view_a_counsellors_portal(): void
    {
        $tc = $this->counsellor();
        $this->counsellorUser($tc);

        foreach (['staff', 'consultation_staff', 'compliance_officer', 'counsellor'] as $role) {
            $user = User::factory()->create(['role' => $role]);
            $this->actingAs($user)->postJson("/api/admin/impersonate/{$tc->uuid}")->assertStatus(403);
        }

        $this->assertSame(0, PersonalAccessToken::where('name', 'like', 'impersonation:%')->count());
    }

    public function test_admin_views_portal_read_only_and_start_and_end_are_logged(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'name' => 'Ada Admin']);
        $adminToken = $admin->createToken('auth_token')->plainTextToken;
        $tc = $this->counsellor(['name' => 'Rooshan C', 'last_activity' => null]);
        $tcUser = $this->counsellorUser($tc);

        // Start
        $start = $this->withToken($adminToken)->postJson("/api/admin/impersonate/{$tc->uuid}")
            ->assertStatus(200)
            ->assertJsonPath('counsellor.name', 'Rooshan C')
            ->assertJsonPath('read_only', true);
        $token = $start->json('token');

        $pat = PersonalAccessToken::findToken($token);
        $this->assertSame("impersonation:{$admin->id}", $pat->name);
        $this->assertSame($tcUser->id, $pat->tokenable_id);
        $this->assertEqualsWithDelta(now()->addMinutes(30)->timestamp, $pat->expires_at->timestamp, 5);

        $started = ActivityLog::where('action', 'impersonation_started')->first();
        $this->assertSame($admin->id, $started->user_id);
        $this->assertSame($tc->id, $started->model_id);
        $this->assertStringContainsString("Ada Admin started viewing Rooshan C's portal", $started->description);

        // Reading works, as the counsellor
        $this->freshAuth();
        $this->withToken($token)->getJson('/api/user')->assertStatus(200)
            ->assertJsonPath('user.id', $tcUser->id);
        $this->freshAuth();
        $this->withToken($token)->getJson('/api/counsellor/my-data')->assertStatus(200);

        // GET while impersonating does not count as the counsellor being active
        $this->assertNull($tc->fresh()->last_activity);

        // Any write is refused
        foreach ([
            ['PUT', '/api/counsellor/bio', ['bio' => 'changed']],
            ['POST', '/api/counsellor/holidays', ['start_date' => now()->addWeek()->toDateString(), 'end_date' => now()->addWeeks(2)->toDateString()]],
            ['POST', '/api/messages/send-to-staff', ['message' => 'hi']],
            ['POST', '/api/logout', []],
            ['DELETE', '/api/counsellor/delete-photo', []],
        ] as [$method, $uri, $body]) {
            $this->freshAuth();
            $this->withToken($token)->json($method, $uri, $body)->assertStatus(403, "$method $uri");
        }
        $this->assertNotSame('changed', $tc->fresh()->bio);

        // The impersonation token cannot start another impersonation either
        $this->freshAuth();
        $this->withToken($token)->postJson("/api/admin/impersonate/{$tc->uuid}")->assertStatus(403);

        // The admin's own sign-in is unaffected
        $this->freshAuth();
        $this->withToken($adminToken)->getJson('/api/user')->assertStatus(200)
            ->assertJsonPath('user.id', $admin->id);

        // Stop (from the impersonation tab)
        $this->freshAuth();
        $this->withToken($token)->postJson('/api/admin/impersonate/stop')->assertStatus(200);
        $this->assertNull(PersonalAccessToken::findToken($token));

        $ended = ActivityLog::where('action', 'impersonation_ended')->first();
        $this->assertSame($admin->id, $ended->user_id);
        $this->assertSame($tc->id, $ended->model_id);

        $this->freshAuth();
        $this->withToken($token)->getJson('/api/user')->assertStatus(401);

        // Admin still signed in after the view ends
        $this->freshAuth();
        $this->withToken($adminToken)->getJson('/api/user')->assertStatus(200)
            ->assertJsonPath('user.id', $admin->id);
    }

    public function test_super_admin_can_view_portal_and_stop_it_by_token_id(): void
    {
        $superAdmin = User::factory()->create(['role' => 'super_admin']);
        $tc = $this->counsellor();
        $this->counsellorUser($tc);

        $token = $this->actingAs($superAdmin)->postJson("/api/admin/impersonate/{$tc->uuid}")
            ->assertStatus(200)->json('token');
        $pat = PersonalAccessToken::findToken($token);

        $this->actingAs($superAdmin)->postJson('/api/admin/impersonate/stop', ['token_id' => $pat->id])
            ->assertStatus(200);
        $this->assertNull(PersonalAccessToken::find($pat->id));
        $this->assertSame(1, ActivityLog::where('action', 'impersonation_ended')->count());
    }

    public function test_impersonation_token_expires_after_30_minutes(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $tc = $this->counsellor();
        $this->counsellorUser($tc);
        $token = $this->actingAs($admin)->postJson("/api/admin/impersonate/{$tc->uuid}")->json('token');

        $this->travel(31)->minutes();
        $this->freshAuth();
        $this->app['auth']->guard('web')->logout();
        $this->withToken($token)->getJson('/api/user')->assertStatus(401);
    }

    public function test_counsellor_without_a_portal_account_cannot_be_viewed(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $tc = $this->counsellor();

        $this->actingAs($admin)->postJson("/api/admin/impersonate/{$tc->uuid}")->assertStatus(422);
    }

    // ── 4. Scheduler and auto-deduction ──────────────────────────────

    public function test_scheduled_jobs_run_in_process_without_proc_open(): void
    {
        $expected = [
            'clients:send-feedback-forms' => '0 0 1 * *',
            'bookings:send-reminders' => '0 * * * *',
            'bookings:apply-auto-deduction' => '0 0 * * *',
            'app:send-trainee-reminders' => '*/10 * * * *',
        ];

        $events = collect(app(Schedule::class)->events());
        foreach ($expected as $command => $cron) {
            $event = $events->first(fn ($e) => str_starts_with((string) $e->description, "{$command}:")
                || str_contains((string) ($e->command ?? ''), $command));
            $this->assertNotNull($event, "$command is scheduled");
            // CallbackEvent runs in this PHP process: no proc_open needed.
            $this->assertInstanceOf(CallbackEvent::class, $event, $command);
            $this->assertSame($cron, $event->expression, $command);
        }
    }

    public function test_each_scheduled_command_runs_standalone(): void
    {
        Mail::fake();
        foreach (['clients:send-feedback-forms', 'bookings:send-reminders', 'bookings:apply-auto-deduction', 'app:send-trainee-reminders'] as $command) {
            $this->assertSame(0, Artisan::call($command), $command);
        }
    }

    public function test_auto_deduction_block_starts_the_week_after_the_last_booked_session(): void
    {
        Mail::fake();
        Carbon::setTestNow(Carbon::parse('2026-11-16 09:00:00'));

        $tc = $this->counsellor();
        $client = Client::create([
            'client_id' => 'CL010', 'name' => 'Tom Smith', 'email' => 'tom@example.com',
            'service_type' => 'Low Cost', 'stage' => 'Sessions Booked',
            'matched_tc_id' => $tc->id, 'agreement_status' => 'signed',
            // Deadline (48h before the week after the last session) has passed
            'next_booking_deadline' => '2026-11-15',
        ]);

        // A block of 4 weekly sessions; the last one is Tuesday 17 Nov 18:00
        // and carries the booking deadline for the next block.
        $last = null;
        foreach (['2026-10-27', '2026-11-03', '2026-11-10', '2026-11-17'] as $i => $date) {
            $last = Session::create([
                'client_id' => $client->id, 'tc_id' => $tc->id, 'session_type' => 'low_cost',
                'scheduled_at' => "$date 18:00:00",
                'status' => $date < '2026-11-16' ? 'completed' : 'scheduled',
                'is_block_booking' => true, 'block_number' => $i + 1, 'total_sessions_in_block' => 4,
                'booking_deadline' => $i === 3 ? '2026-11-22' : null,
            ]);
        }

        $this->artisan('bookings:apply-auto-deduction')->assertExitCode(0);

        $new = Session::where('client_id', $client->id)->where('id', '>', $last->id)->orderBy('scheduled_at')->get();
        $this->assertCount(3, $new);
        $this->assertSame(
            ['2026-11-24 18:00:00', '2026-12-01 18:00:00', '2026-12-08 18:00:00'],
            $new->map(fn ($s) => $s->scheduled_at->format('Y-m-d H:i:s'))->all()
        );
        // No slot is booked twice (the first new session used to land on 17 Nov).
        $this->assertSame(1, Session::where('client_id', $client->id)->where('scheduled_at', '2026-11-17 18:00:00')->count());

        // Next deadline: 48h before the week after the last new session (15 Dec).
        $this->assertSame('2026-12-13', $client->fresh()->next_booking_deadline->format('Y-m-d'));
        $this->assertTrue((bool) $last->fresh()->auto_deduction_applied);

        // Running again the same day does nothing more.
        $this->artisan('bookings:apply-auto-deduction')->assertExitCode(0);
        $this->assertSame(7, Session::where('client_id', $client->id)->count());

        Carbon::setTestNow();
    }

    // ── 5. Emergency contact step removed from the intake forms ─────

    public function test_intake_is_accepted_without_emergency_contact_details(): void
    {
        foreach (['Low Cost' => 'lowcost@example.com', 'Mid Range' => 'midrange@example.com'] as $service => $email) {
            $this->postJson('/api/client-intake', [
                'first_name' => 'Tom',
                'last_name' => 'Smith',
                'email' => $email,
                'age' => 30,
                'service_type' => $service,
                'address' => '1 High Street, London',
                'terms_accepted' => true,
            ])->assertStatus(201);

            $client = Client::where('email', $email)->first();
            $this->assertNotNull($client, $service);
            $this->assertNull($client->emergency_contact_name);
            $this->assertNull($client->emergency_contact_phone);
            $this->assertNull($client->emergency_contact_relationship);
        }
    }
}
