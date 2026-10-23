<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Models\User;
use App\Models\Message;
use App\Models\TrainingCounsellor;

class MessageTrashRestoreSecurityTest extends TestCase
{
    use RefreshDatabase;

    private function createCounsellorUser(string $prefix = 'c1'): array
    {
        $tc = TrainingCounsellor::create([
            'tc_id' => "TC-{$prefix}",
            'name' => "Counsellor {$prefix}",
            'first_name' => "Counsellor",
            'last_name' => $prefix,
            'email' => "{$prefix}@counsellor.test",
            'phone' => '07111222333',
            'status' => 'active',
        ]);

        $user = User::create([
            'name' => "Counsellor {$prefix}",
            'email' => "{$prefix}@counsellor.test",
            'password' => bcrypt('password'),
            'role' => 'counsellor',
            'training_counsellor_id' => $tc->id,
        ]);

        return [$user, $tc];
    }

    private function createStaffUser(string $role = 'admin'): User
    {
        return User::create([
            'name' => "Staff {$role}",
            'email' => "{$role}@staff.test",
            'password' => bcrypt('password'),
            'role' => $role,
        ]);
    }

    public function test_unauthenticated_user_cannot_trash_or_restore_message()
    {
        [$counsellor1, $tc1] = $this->createCounsellorUser('c1');
        $staff = $this->createStaffUser('admin');

        $message = Message::create([
            'from_user_id' => $staff->id,
            'to_user_id' => $counsellor1->id,
            'to_tc_id' => $tc1->id,
            'subject' => 'Confidential Note',
            'message' => 'Hello',
            'type' => 'staff_to_counsellor',
        ]);

        $this->postJson("/api/messages/{$message->id}/trash")
            ->assertStatus(401);

        $this->postJson("/api/messages/{$message->id}/restore")
            ->assertStatus(401);
    }

    public function test_unrelated_counsellor_cannot_trash_another_users_message()
    {
        [$counsellor1, $tc1] = $this->createCounsellorUser('c1');
        [$counsellor2, $tc2] = $this->createCounsellorUser('c2');
        $staff = $this->createStaffUser('admin');

        $message = Message::create([
            'from_user_id' => $staff->id,
            'to_user_id' => $counsellor1->id,
            'to_tc_id' => $tc1->id,
            'subject' => 'Confidential Message for C1',
            'message' => 'Private information',
            'type' => 'staff_to_counsellor',
            'is_trashed' => false,
        ]);

        // Counsellor 2 attempts to trash Counsellor 1's message
        $response = $this->actingAs($counsellor2)
            ->postJson("/api/messages/{$message->id}/trash");

        $response->assertStatus(403);
        $this->assertFalse((bool) $message->fresh()->is_trashed);
    }

    public function test_unrelated_counsellor_cannot_restore_another_users_message()
    {
        [$counsellor1, $tc1] = $this->createCounsellorUser('c1');
        [$counsellor2, $tc2] = $this->createCounsellorUser('c2');
        $staff = $this->createStaffUser('admin');

        $message = Message::create([
            'from_user_id' => $staff->id,
            'to_user_id' => $counsellor1->id,
            'to_tc_id' => $tc1->id,
            'subject' => 'Trashed Message for C1',
            'message' => 'Private information',
            'type' => 'staff_to_counsellor',
            'is_trashed' => true,
            'trashed_at' => now(),
        ]);

        // Counsellor 2 attempts to restore Counsellor 1's trashed message
        $response = $this->actingAs($counsellor2)
            ->postJson("/api/messages/{$message->id}/restore");

        $response->assertStatus(403);
        $this->assertTrue((bool) $message->fresh()->is_trashed);
    }

    public function test_sender_can_trash_and_restore_their_own_message()
    {
        [$counsellor1, $tc1] = $this->createCounsellorUser('c1');
        $staff = $this->createStaffUser('admin');

        $message = Message::create([
            'from_user_id' => $counsellor1->id,
            'to_user_id' => $staff->id,
            'subject' => 'Case query',
            'message' => 'Please advise on case',
            'type' => 'counsellor_to_staff',
            'is_trashed' => false,
        ]);

        // Sender trashes message
        $this->actingAs($counsellor1)
            ->postJson("/api/messages/{$message->id}/trash")
            ->assertStatus(200)
            ->assertJson(['message' => 'Message moved to trash']);

        $this->assertTrue((bool) $message->fresh()->is_trashed);

        // Sender restores message
        $this->actingAs($counsellor1)
            ->postJson("/api/messages/{$message->id}/restore")
            ->assertStatus(200)
            ->assertJson(['message' => 'Message restored from trash']);

        $this->assertFalse((bool) $message->fresh()->is_trashed);
    }

    public function test_recipient_can_trash_and_restore_message()
    {
        [$counsellor1, $tc1] = $this->createCounsellorUser('c1');
        $staff = $this->createStaffUser('admin');

        $message = Message::create([
            'from_user_id' => $staff->id,
            'to_user_id' => $counsellor1->id,
            'to_tc_id' => $tc1->id,
            'subject' => 'Supervision notes',
            'message' => 'Here are your supervision notes',
            'type' => 'staff_to_counsellor',
            'is_trashed' => false,
        ]);

        // Recipient trashes message
        $this->actingAs($counsellor1)
            ->postJson("/api/messages/{$message->id}/trash")
            ->assertStatus(200)
            ->assertJson(['message' => 'Message moved to trash']);

        $this->assertTrue((bool) $message->fresh()->is_trashed);

        // Recipient restores message
        $this->actingAs($counsellor1)
            ->postJson("/api/messages/{$message->id}/restore")
            ->assertStatus(200)
            ->assertJson(['message' => 'Message restored from trash']);

        $this->assertFalse((bool) $message->fresh()->is_trashed);
    }

    public function test_staff_can_trash_and_restore_messages()
    {
        [$counsellor1, $tc1] = $this->createCounsellorUser('c1');
        $staff = $this->createStaffUser('staff');

        $message = Message::create([
            'from_user_id' => $counsellor1->id,
            'to_user_id' => null,
            'subject' => 'General Inquiry',
            'message' => 'Hello staff team',
            'type' => 'counsellor_to_staff',
            'is_trashed' => false,
        ]);

        // Staff moves to trash
        $this->actingAs($staff)
            ->postJson("/api/messages/{$message->id}/trash")
            ->assertStatus(200);

        $this->assertTrue((bool) $message->fresh()->is_trashed);

        // Staff restores
        $this->actingAs($staff)
            ->postJson("/api/messages/{$message->id}/restore")
            ->assertStatus(200);

        $this->assertFalse((bool) $message->fresh()->is_trashed);
    }
}
