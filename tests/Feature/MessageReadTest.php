<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Models\User;
use App\Models\Message;
use App\Models\TrainingCounsellor;
use Illuminate\Support\Facades\Event;
use App\Events\MessageRead;

class MessageReadTest extends TestCase
{
    use RefreshDatabase;

    private function getCounsellorUser()
    {
        $tc = TrainingCounsellor::create([
            'tc_id' => 'TC-TEST',
            'name' => 'Counsellor Test',
            'email' => 'counsellor@test.com',
            'phone' => '12345678',
        ]);

        return User::create([
            'name' => 'Counsellor Test',
            'email' => 'counsellor@test.com',
            'password' => bcrypt('password'),
            'role' => 'counsellor',
            'training_counsellor_id' => $tc->id,
        ]);
    }

    private function getAdminUser()
    {
        return User::create([
            'name' => 'Admin Test',
            'email' => 'admin@test.com',
            'password' => bcrypt('password'),
            'role' => 'admin',
        ]);
    }

    public function test_counsellor_marking_admin_group_conversation_as_read_broadcasts_event_with_correct_is_read_flag()
    {
        Event::fake([MessageRead::class]);

        $counsellor = $this->getCounsellorUser();
        $admin = $this->getAdminUser();

        // Create an unread message sent from admin to counsellor
        $message = Message::create([
            'from_user_id' => $admin->id,
            'to_tc_id' => $counsellor->training_counsellor_id,
            'to_user_id' => $counsellor->id,
            'subject' => 'Test Subject',
            'message' => 'Test Message',
            'type' => 'staff_to_counsellor',
            'is_read' => false,
        ]);

        // Access route as counsellor to mark admin_group conversation read
        $response = $this->actingAs($counsellor, 'sanctum')
            ->postJson("/api/messages/conversations/group/admin_group/mark-read");

        $response->assertStatus(200);

        // Verify message is updated in database
        $this->assertDatabaseHas('messages', [
            'id' => $message->id,
            'is_read' => true,
        ]);

        // Verify Event was dispatched carrying the correct attributes
        Event::assertDispatched(MessageRead::class, function ($event) use ($message) {
            return $event->message->id === $message->id &&
                   $event->message->is_read === true &&
                   !is_null($event->message->read_at);
        });
    }

    public function test_admin_marking_counsellor_conversation_as_read_broadcasts_event_with_correct_is_read_flag()
    {
        Event::fake([MessageRead::class]);

        $counsellor = $this->getCounsellorUser();
        $admin = $this->getAdminUser();

        // Create an unread message sent from counsellor to staff/admin
        $message = Message::create([
            'from_user_id' => $counsellor->id,
            'to_user_id' => null,
            'subject' => 'Support Request',
            'message' => 'Help needed',
            'type' => 'counsellor_to_staff',
            'is_read' => false,
        ]);

        // Access route as admin to mark TC conversation read
        $response = $this->actingAs($admin, 'sanctum')
            ->postJson("/api/messages/conversations/tc/{$counsellor->training_counsellor_id}/mark-read");

        $response->assertStatus(200);

        // Verify message is updated in database
        $this->assertDatabaseHas('messages', [
            'id' => $message->id,
            'is_read' => true,
        ]);

        // Verify Event was dispatched carrying the correct attributes
        Event::assertDispatched(MessageRead::class, function ($event) use ($message) {
            return $event->message->id === $message->id &&
                   $event->message->is_read === true &&
                   !is_null($event->message->read_at);
        });
    }
}
