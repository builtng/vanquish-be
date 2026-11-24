<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Session;
use App\Models\TrainingCounsellor;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * VQT, 24 Nov: each booked session can have a video-call link, added by an
 * admin or the session's counsellor; the portal's "Join Session" opens it.
 */
class SessionMeetingLinkTest extends TestCase
{
    use RefreshDatabase;

    private function setUpSession(): array
    {
        $tc = TrainingCounsellor::create([
            'tc_id' => 'TC901', 'name' => 'Rooshan Test', 'email' => 'tc901@example.com',
            'status' => 'Active', 'counsellor_type' => 'Trainee',
        ]);
        $other = TrainingCounsellor::create([
            'tc_id' => 'TC902', 'name' => 'Other TC', 'email' => 'tc902@example.com',
            'status' => 'Active', 'counsellor_type' => 'Trainee',
        ]);
        $client = Client::create([
            'client_id' => 'CL901', 'name' => 'Tom Smith', 'email' => 'tom901@example.com',
            'service_type' => 'Low Cost', 'stage' => 'Sessions Booked', 'matched_tc_id' => $tc->id,
        ]);
        $session = Session::create([
            'client_id' => $client->id, 'tc_id' => $tc->id, 'session_type' => 'low_cost',
            'scheduled_at' => now()->addWeek(), 'status' => 'scheduled',
            'is_block_booking' => true, 'block_number' => 1, 'total_sessions_in_block' => 4,
        ]);

        return [$tc, $other, $client, $session];
    }

    public function test_the_sessions_counsellor_can_add_change_and_remove_the_link(): void
    {
        [$tc, , , $session] = $this->setUpSession();
        $user = User::factory()->create(['role' => 'counsellor', 'training_counsellor_id' => $tc->id]);

        $this->actingAs($user)->patchJson("/api/sessions/{$session->id}/meeting-link", [
            'meeting_link' => 'https://zoom.us/j/123456789',
        ])->assertOk()->assertJsonPath('session.meeting_link', 'https://zoom.us/j/123456789');

        // Shown in the counsellor portal
        $this->actingAs($user)->getJson('/api/counsellor/my-data')
            ->assertOk()
            ->assertJsonPath('upcoming_sessions.0.meeting_link', 'https://zoom.us/j/123456789');

        // Removing it
        $this->actingAs($user)->patchJson("/api/sessions/{$session->id}/meeting-link", ['meeting_link' => ''])
            ->assertOk();
        $this->assertNull($session->fresh()->meeting_link);
    }

    public function test_staff_can_add_a_link_to_any_session(): void
    {
        [, , , $session] = $this->setUpSession();
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)->patchJson("/api/sessions/{$session->id}/meeting-link", [
            'meeting_link' => 'https://teams.microsoft.com/l/meetup-join/abc',
        ])->assertOk();
        $this->assertSame('https://teams.microsoft.com/l/meetup-join/abc', $session->fresh()->meeting_link);
    }

    public function test_another_counsellor_cannot_set_the_link(): void
    {
        [, $other, , $session] = $this->setUpSession();
        $user = User::factory()->create(['role' => 'counsellor', 'training_counsellor_id' => $other->id]);

        $this->actingAs($user)->patchJson("/api/sessions/{$session->id}/meeting-link", [
            'meeting_link' => 'https://zoom.us/j/1',
        ])->assertForbidden();
        $this->assertNull($session->fresh()->meeting_link);
    }

    public function test_only_web_links_are_accepted(): void
    {
        [, , , $session] = $this->setUpSession();
        $admin = User::factory()->create(['role' => 'admin']);

        foreach (['not a link', 'javascript:alert(1)', 'zoom.us/j/1'] as $bad) {
            $this->actingAs($admin)->patchJson("/api/sessions/{$session->id}/meeting-link", ['meeting_link' => $bad])
                ->assertStatus(422);
        }
        $this->assertNull($session->fresh()->meeting_link);
    }

    public function test_the_client_sees_the_link_on_their_booking_page(): void
    {
        [, , $client, $session] = $this->setUpSession();
        $session->update(['meeting_link' => 'https://zoom.us/j/555']);

        $this->getJson("/api/client-booking/{$client->uuid}/status")
            ->assertOk()
            ->assertJsonPath('upcoming_sessions.0.meeting_link', 'https://zoom.us/j/555');
    }
}
