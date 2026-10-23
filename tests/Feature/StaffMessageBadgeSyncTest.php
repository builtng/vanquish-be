<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Models\User;
use App\Models\Message;
use App\Models\TrainingCounsellor;

class StaffMessageBadgeSyncTest extends TestCase
{
    use RefreshDatabase;

    private function createStaff(string $email, string $role = 'staff'): User
    {
        return User::create([
            'name' => "Staff {$email}",
            'email' => $email,
            'password' => bcrypt('password'),
            'role' => $role,
        ]);
    }

    private function createCounsellor(string $email): User
    {
        $tc = TrainingCounsellor::create([
            'tc_id' => 'TC-' . uniqid(),
            'name' => "Counsellor {$email}",
            'email' => $email,
            'phone' => '07111222333',
            'status' => 'active',
        ]);

        return User::create([
            'name' => "Counsellor {$email}",
            'email' => $email,
            'password' => bcrypt('password'),
            'role' => 'counsellor',
            'training_counsellor_id' => $tc->id,
        ]);
    }

    public function test_staff_badge_count_matches_actual_unread_inbox_messages_for_general_staff_messages()
    {
        $staff = $this->createStaff('staff1@test.com');
        $counsellor = $this->createCounsellor('counsellor1@test.com');

        // Message to staff group (to_user_id is null)
        Message::create([
            'from_user_id' => $counsellor->id,
            'to_user_id' => null,
            'subject' => 'Group Note',
            'message' => 'Hello staff team',
            'type' => 'counsellor_to_staff',
            'is_read' => false,
            'is_trashed' => false,
        ]);

        // Check badge unread-count
        $badgeResponse = $this->actingAs($staff)->getJson('/api/messages/unread-count');
        $badgeResponse->assertStatus(200);
        $badgeCount = $badgeResponse->json('count');

        // Check actual inbox messages
        $inboxResponse = $this->actingAs($staff)->getJson('/api/messages?unread_only=1');
        $inboxResponse->assertStatus(200);
        $inboxCount = count($inboxResponse->json('data'));

        $this->assertEquals(1, $badgeCount);
        $this->assertEquals($badgeCount, $inboxCount, 'Badge count must match unread inbox message count');
    }

    public function test_staff_badge_does_not_count_messages_targeted_only_to_other_staff()
    {
        $staffA = $this->createStaff('staffA@test.com');
        $staffB = $this->createStaff('staffB@test.com');
        $counsellor = $this->createCounsellor('counsellor2@test.com');

        // Message sent specifically to Staff A
        Message::create([
            'from_user_id' => $counsellor->id,
            'to_user_id' => $staffA->id,
            'subject' => 'Direct to Staff A',
            'message' => 'For Staff A eyes only',
            'type' => 'counsellor_to_staff',
            'is_read' => false,
            'is_trashed' => false,
        ]);

        // Staff A should see 1 unread
        $badgeA = $this->actingAs($staffA)->getJson('/api/messages/unread-count')->json('count');
        $inboxA = count($this->actingAs($staffA)->getJson('/api/messages?unread_only=1')->json('data'));
        $this->assertEquals(1, $badgeA);
        $this->assertEquals(1, $inboxA);

        // Staff B should see 0 unread (NO leakage or ghost count)
        $badgeB = $this->actingAs($staffB)->getJson('/api/messages/unread-count')->json('count');
        $inboxB = count($this->actingAs($staffB)->getJson('/api/messages?unread_only=1')->json('data'));
        $this->assertEquals(0, $badgeB);
        $this->assertEquals(0, $inboxB);
    }

    public function test_soft_deleted_and_trashed_messages_do_not_appear_in_badge_count()
    {
        $staff = $this->createStaff('staff3@test.com');
        $counsellor = $this->createCounsellor('counsellor3@test.com');

        // Message 1: Soft deleted by recipient
        Message::create([
            'from_user_id' => $counsellor->id,
            'to_user_id' => $staff->id,
            'subject' => 'Deleted Msg',
            'message' => 'Soft deleted',
            'type' => 'counsellor_to_staff',
            'is_read' => false,
            'is_trashed' => false,
            'deleted_by_recipient_at' => now(),
        ]);

        // Message 2: Moved to trash
        Message::create([
            'from_user_id' => $counsellor->id,
            'to_user_id' => $staff->id,
            'subject' => 'Trashed Msg',
            'message' => 'In trash',
            'type' => 'counsellor_to_staff',
            'is_read' => false,
            'is_trashed' => true,
            'trashed_at' => now(),
        ]);

        $badge = $this->actingAs($staff)->getJson('/api/messages/unread-count')->json('count');
        $inbox = count($this->actingAs($staff)->getJson('/api/messages?unread_only=1')->json('data'));

        $this->assertEquals(0, $badge);
        $this->assertEquals(0, $inbox);
    }

    public function test_own_sent_messages_never_increment_badge_count()
    {
        $staff = $this->createStaff('staff4@test.com');

        Message::create([
            'from_user_id' => $staff->id,
            'to_user_id' => null,
            'subject' => 'Staff Broadcast',
            'message' => 'Sent by staff to team',
            'type' => 'staff_to_counsellor',
            'is_read' => false,
            'is_trashed' => false,
        ]);

        $badge = $this->actingAs($staff)->getJson('/api/messages/unread-count')->json('count');
        $this->assertEquals(0, $badge);
    }
}
