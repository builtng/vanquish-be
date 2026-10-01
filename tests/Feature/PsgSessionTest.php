<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Models\User;
use App\Models\AttendanceGroup;
use App\Models\TrainingCounsellor;
use App\Models\PsgSessionLog;
use App\Models\PsgSessionAttendee;

class PsgSessionTest extends TestCase
{
    use RefreshDatabase;

    private function getCounsellorUser()
    {
        $tc = TrainingCounsellor::create([
            'tc_id' => 'TC001',
            'name' => 'Counsellor Test',
            'email' => 'counsellor@test.com',
            'status' => 'Active',
        ]);

        $user = User::create([
            'name' => 'Counsellor Test',
            'email' => 'counsellor@test.com',
            'password' => bcrypt('password'),
            'role' => 'counsellor',
            'training_counsellor_id' => $tc->id,
        ]);

        return [$user, $tc];
    }

    public function test_can_retrieve_public_attendance_form_by_token()
    {
        $group = AttendanceGroup::create([
            'name' => 'Group Test',
            'day_of_week' => 'Monday',
        ]);

        // Verify token was auto-generated
        $this->assertNotEmpty($group->public_token);

        $response = $this->getJson("/api/public/psg/{$group->public_token}");

        $response->assertStatus(200)
            ->assertJsonPath('group.name', 'Group Test')
            ->assertJsonPath('group.day_of_week', 'Monday');
    }

    public function test_public_form_returns_404_for_invalid_token()
    {
        $response = $this->getJson("/api/public/psg/invalid-token-12345");
        $response->assertStatus(404);
    }

    public function test_can_submit_session_attendance_publicly()
    {
        $group = AttendanceGroup::create([
            'name' => 'Group Test',
            'day_of_week' => 'Monday',
        ]);

        $tc1 = TrainingCounsellor::create([
            'tc_id' => 'TC001',
            'name' => 'TC One',
            'email' => 'tc1@test.com',
            'attendance_group_id' => $group->id,
        ]);

        $tc2 = TrainingCounsellor::create([
            'tc_id' => 'TC002',
            'name' => 'TC Two',
            'email' => 'tc2@test.com',
            'attendance_group_id' => $group->id,
        ]);

        $payload = [
            'session_date' => '2026-06-04',
            'supervisor_name' => 'Jane Supervisor',
            'activities' => 'Discussed case studies and ethical boundaries.',
            'notes' => 'Some extra notes.',
            'attendance' => [
                $tc1->id => true,
                $tc2->id => false,
            ],
            'comments' => [
                $tc1->id => 'Comment for TC 1',
                $tc2->id => 'Comment for TC 2',
            ]
        ];

        $response = $this->postJson("/api/public/psg/{$group->public_token}", $payload);

        $response->assertStatus(201);

        $this->assertDatabaseHas('psg_session_logs', [
            'attendance_group_id' => $group->id,
            'session_date' => '2026-06-04 00:00:00',
            'supervisor_name' => 'Jane Supervisor',
            'activities' => 'Discussed case studies and ethical boundaries.',
        ]);

        $this->assertDatabaseHas('psg_session_attendees', [
            'training_counsellor_id' => $tc1->id,
            'attended' => true,
            'comment' => 'Comment for TC 1',
        ]);

        $this->assertDatabaseHas('psg_session_attendees', [
            'training_counsellor_id' => $tc2->id,
            'attended' => false,
            'comment' => 'Comment for TC 2',
        ]);
    }

    public function test_counsellor_can_retrieve_own_attendance_history()
    {
        list($user, $tc) = $this->getCounsellorUser();

        $group = AttendanceGroup::create([
            'name' => 'Group Test',
            'day_of_week' => 'Monday',
        ]);

        $tc->update(['attendance_group_id' => $group->id]);

        $log = PsgSessionLog::create([
            'attendance_group_id' => $group->id,
            'session_date' => '2026-06-04',
            'supervisor_name' => 'Jane Supervisor',
            'activities' => 'Discussed boundaries.',
        ]);

        PsgSessionAttendee::create([
            'psg_session_log_id' => $log->id,
            'training_counsellor_id' => $tc->id,
            'attended' => true,
        ]);

        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/counsellor/psg-sessions');

        $response->assertStatus(200)
            ->assertJsonCount(1)
            ->assertJsonPath('0.session_date', '2026-06-04')
            ->assertJsonPath('0.attended', true)
            ->assertJsonPath('0.supervisor_name', 'Jane Supervisor');
    }

    public function test_duplicate_session_attendance_submission_is_blocked()
    {
        $group = AttendanceGroup::create([
            'name' => 'Group Test',
            'day_of_week' => 'Monday',
        ]);

        $tc = TrainingCounsellor::create([
            'tc_id' => 'TC001',
            'name' => 'TC One',
            'email' => 'tc1@test.com',
            'attendance_group_id' => $group->id,
        ]);

        $payload = [
            'session_date' => '2026-06-04',
            'supervisor_name' => 'Jane Supervisor',
            'activities' => 'Discussed case studies.',
            'notes' => 'Some notes.',
            'attendance' => [
                $tc->id => true,
            ]
        ];

        // First submission
        $response1 = $this->postJson("/api/public/psg/{$group->public_token}", $payload);
        $response1->assertStatus(201);

        // Duplicate submission
        $response2 = $this->postJson("/api/public/psg/{$group->public_token}", $payload);
        $response2->assertStatus(422)
            ->assertJsonPath('message', 'Attendance has already been logged for this group on this date.');
    }
}
