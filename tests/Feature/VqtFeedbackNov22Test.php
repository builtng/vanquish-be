<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\Client;
use App\Models\TrainingCounsellor;
use App\Models\User;
use App\Services\MatchScoringService;
use App\Support\SlotTime;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * VQT feedback, 22 November 2026 (Rooshan's email and videos).
 */
class VqtFeedbackNov22Test extends TestCase
{
    use RefreshDatabase;

    private function counsellor(array $attrs = []): TrainingCounsellor
    {
        static $n = 200;
        $n++;

        return TrainingCounsellor::create(array_merge([
            'tc_id' => 'TC' . $n,
            'name' => 'Rooshan Test',
            'email' => "tc{$n}@example.com",
            'status' => 'Active',
            'counsellor_type' => 'Trainee',
        ], $attrs));
    }

    private function lowCostClient(array $attrs = []): Client
    {
        return Client::create(array_merge([
            'client_id' => 'CL037', 'name' => 'Tom Smith', 'email' => 'tom@example.com',
            'service_type' => 'Low Cost', 'stage' => 'Consultation Completed',
            'agreement_status' => 'signed',
        ], $attrs));
    }

    // ── #2 / #3: one way of reading time slots ─────────────────────────

    public function test_slot_formats_from_every_form_read_as_the_same_start_time(): void
    {
        foreach (['10am-1050am', '10am-11am', '10:00am - 10:50am', '10:00', '10AM-11AM', 'morning-early'] as $slot) {
            $this->assertSame(['10:00'], SlotTime::startTimes($slot), $slot);
        }
        $this->assertSame(['13:00'], SlotTime::startTimes('1pm-150pm'));
        $this->assertSame(['13:00'], SlotTime::startTimes('1pm-2pm'));
        $this->assertSame(['12:00'], SlotTime::startTimes('12pm-1250pm'));
        $this->assertSame(['11:00', '12:00'], SlotTime::startTimes('morning-late'));
        $this->assertSame([], SlotTime::startTimes('whenever'));

        $this->assertTrue(SlotTime::offers(['Tuesday' => ['10am-11am']], 'tuesday', '10am-1050am'));
        $this->assertTrue(SlotTime::offers(json_encode(['tuesday' => ['10am-1050am']]), 'Tuesday', '10:00'));
        $this->assertFalse(SlotTime::offers(['tuesday' => ['10am-11am']], 'Tuesday', '11am-1150am'));
        $this->assertFalse(SlotTime::offers(['tuesday' => ['10am-11am']], 'Wednesday', '10am-1050am'));
    }

    public function test_an_overlapping_slot_from_the_matching_screen_is_accepted(): void
    {
        // The video: 9 overlapping slots shown, Tuesday 10:00 refused.
        Mail::fake();
        $admin = User::factory()->create(['role' => 'admin']);
        $tc = $this->counsellor(['availability' => ['Tuesday' => ['10am-11am'], 'Monday' => ['1pm-2pm']]]);
        $client = $this->lowCostClient(['availability' => ['tuesday' => ['10am-1050am'], 'monday' => ['1pm-150pm']]]);

        $this->actingAs($admin)->postJson('/api/matches', [
            'client_id' => $client->uuid, 'tc_id' => $tc->uuid,
            'allocated_day' => 'Tuesday', 'allocated_time' => '10am-1050am',
        ])->assertStatus(201);

        $client->refresh();
        $this->assertSame($tc->id, $client->matched_tc_id);
        $this->assertSame('Tuesday', $client->allocated_day);
        $this->assertSame('10am-1050am', $client->allocated_time);
    }

    public function test_a_slot_outside_both_schedules_still_needs_match_manually(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $tc = $this->counsellor(['availability' => ['Tuesday' => ['10am-11am']]]);
        $client = $this->lowCostClient(['availability' => ['tuesday' => ['10am-1050am']]]);

        $this->actingAs($admin)->postJson('/api/matches', [
            'client_id' => $client->uuid, 'tc_id' => $tc->uuid,
            'allocated_day' => 'Wednesday', 'allocated_time' => '3pm-350pm',
        ])->assertStatus(422)
            ->assertJsonPath('message', 'Assigned session time must be within both the client\'s and the counsellor\'s availability.');
    }

    public function test_availability_score_counts_slots_saved_in_different_formats(): void
    {
        $tc = $this->counsellor(['availability' => ['Tuesday' => ['10am-11am'], 'Monday' => ['1pm-2pm']]]);
        $result = app(MatchScoringService::class)->score([
            'availability' => ['tuesday' => ['10am-1050am'], 'monday' => ['1pm-150pm']],
        ], $tc);

        $availability = collect($result['breakdown'])->firstWhere('label', 'Availability Match');
        $this->assertSame(100, $availability['score']);
    }

    // ── #4: Low Cost matches need the weekly day and time ─────────────

    public function test_a_low_cost_match_without_a_weekly_time_is_refused(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $tc = $this->counsellor();
        $client = $this->lowCostClient();

        $this->actingAs($admin)->postJson('/api/matches', [
            'client_id' => $client->uuid, 'tc_id' => $tc->uuid,
        ])->assertStatus(422)
            ->assertJsonPath('message', 'Choose the weekly day and time for this Low Cost client before assigning.');

        $this->assertNull($client->fresh()->matched_tc_id);
    }

    // ── #5: a manually agreed time can be booked ──────────────────────

    public function test_a_time_agreed_with_match_manually_can_be_booked(): void
    {
        Mail::fake();
        $admin = User::factory()->create(['role' => 'admin']);
        // Counsellor only offers Tuesdays; the agreed time is Monday 10:00.
        $tc = $this->counsellor(['availability' => ['tuesday' => ['10am-1050am']]]);
        $client = $this->lowCostClient(['availability' => ['tuesday' => ['10am-1050am']]]);

        $this->actingAs($admin)->postJson('/api/matches', [
            'client_id' => $client->uuid, 'tc_id' => $tc->uuid,
            'allocated_day' => 'Monday', 'allocated_time' => '10am-1050am',
            'manual_override' => true, 'match_notes' => 'Agreed with client and counsellor by phone',
        ])->assertStatus(201);

        $res = $this->getJson("/api/client-booking/{$client->uuid}/next-block-slots")->assertStatus(200);
        $this->assertTrue($res->json('auto'), 'Book Block offers the dates instead of "Session Time Being Assigned"');
        $this->assertCount(4, $res->json('slots'));
        foreach ($res->json('slots') as $slot) {
            $this->assertStringStartsWith('Monday', $slot['formatted']);
            $this->assertSame('10:00', $slot['time']);
        }
    }

    public function test_a_normal_match_whose_time_the_counsellor_dropped_is_still_not_offered(): void
    {
        Mail::fake();
        $admin = User::factory()->create(['role' => 'admin']);
        $tc = $this->counsellor(['availability' => ['tuesday' => ['10am-11am']]]);
        $client = $this->lowCostClient(['availability' => ['tuesday' => ['10am-1050am']]]);

        $this->actingAs($admin)->postJson('/api/matches', [
            'client_id' => $client->uuid, 'tc_id' => $tc->uuid,
            'allocated_day' => 'Tuesday', 'allocated_time' => '10am-1050am',
        ])->assertStatus(201);

        // The counsellor's schedule saved in the other format still counts.
        $this->assertTrue($this->getJson("/api/client-booking/{$client->uuid}/next-block-slots")->json('auto'));

        // Once they drop that time, it is no longer offered.
        $tc->update(['availability' => ['wednesday' => ['10am-11am']]]);
        $res = $this->getJson("/api/client-booking/{$client->uuid}/next-block-slots");
        $this->assertFalse($res->json('auto'));
    }

    // ── #9: logins left deactivated by a duplicate record ────────────

    public function test_sending_a_portal_invite_switches_a_deactivated_login_back_on(): void
    {
        Mail::fake();
        $admin = User::factory()->create(['role' => 'admin']);
        $tc = $this->counsellor(['email' => 'rooshan@example.com']);
        $login = User::factory()->create([
            'email' => 'rooshan@example.com', 'role' => 'counsellor',
            'training_counsellor_id' => null, 'is_active' => false, 'deactivated_at' => now(),
        ]);

        $this->actingAs($admin)->postJson("/api/training-counsellors/{$tc->uuid}/portal-invite")->assertSuccessful();

        $login->refresh();
        $this->assertSame($tc->id, $login->training_counsellor_id);
        $this->assertTrue($login->isActive());
    }

    public function test_repair_command_dry_run_lists_broken_logins_and_the_real_run_fixes_them(): void
    {
        $tc = $this->counsellor(['email' => 'rooshan@example.com', 'tc_id' => 'TC023']);
        $old = $this->counsellor(['email' => 'rooshan@example.com', 'tc_id' => 'TC019', 'archived_at' => now()]);
        $login = User::factory()->create([
            'email' => 'rooshan@example.com', 'role' => 'counsellor',
            'training_counsellor_id' => null, 'is_active' => false, 'deactivated_at' => now(),
        ]);
        $staffTc = $this->counsellor(['email' => 'staff@example.com']);
        $staff = User::factory()->create(['email' => 'staff@example.com', 'role' => 'staff']);
        $fine = $this->counsellor(['email' => 'fine@example.com']);
        User::factory()->create(['email' => 'fine@example.com', 'role' => 'counsellor', 'training_counsellor_id' => $fine->id]);

        $this->artisan('vqt:repair-counsellor-logins', ['--dry-run' => true])
            // one table row: TC023 | Rooshan Test | rooshan@example.com | Would fix: ...
            ->expectsOutputToContain('rooshan@example.com | Would fix: not linked, deactivated')
            ->expectsOutputToContain('Dry run: nothing was changed.')
            ->doesntExpectOutputToContain('fine@example.com')
            ->assertExitCode(0);
        $this->assertFalse($login->fresh()->isActive());
        $this->assertNull($login->fresh()->training_counsellor_id);

        $this->artisan('vqt:repair-counsellor-logins')
            ->expectsOutputToContain('Fixed: not linked, deactivated')
            ->expectsOutputToContain('1 login(s) fixed.')
            ->assertExitCode(0);

        $login->refresh();
        $this->assertTrue($login->isActive());
        $this->assertSame($tc->id, $login->training_counsellor_id);
        $this->assertSame('staff', $staff->fresh()->role, 'a staff login is never changed');
        $this->assertSame(1, ActivityLog::where('action', 'counsellor_login_repaired')->count());

        // The repaired counsellor can now be opened with "View portal".
        $admin = User::factory()->create(['role' => 'admin']);
        $this->actingAs($admin)->postJson("/api/admin/impersonate/{$tc->uuid}")->assertStatus(200);
    }
}
