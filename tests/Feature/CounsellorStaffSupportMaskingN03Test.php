<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Models\User;
use App\Models\Message;
use App\Models\TrainingCounsellor;

class CounsellorStaffSupportMaskingN03Test extends TestCase
{
    use RefreshDatabase;

    private function createCounsellor(): User
    {
        $tc = TrainingCounsellor::create([
            'tc_id' => 'TC-N03',
            'name' => 'Jane Counsellor',
            'email' => 'jane.counsellor@test.com',
            'phone' => '07123456789',
        ]);

        return User::create([
            'name' => 'Jane Counsellor',
            'email' => 'jane.counsellor@test.com',
            'password' => bcrypt('password123'),
            'role' => 'counsellor',
            'training_counsellor_id' => $tc->id,
        ]);
    }

    private function createStaffMember(string $name = 'Alice Admin', string $email = 'alice.admin@vanquish.com'): User
    {
        return User::create([
            'name' => $name,
            'email' => $email,
            'password' => bcrypt('password123'),
            'role' => 'admin',
        ]);
    }

    public function test_counsellor_staff_list_endpoint_returns_only_staff_support_with_no_names_or_emails()
    {
        $counsellor = $this->createCounsellor();
        $this->createStaffMember('Bob Office', 'bob.office@vanquish.com');
        $this->createStaffMember('Charles Clinical', 'charles.clinical@vanquish.com');

        $response = $this->actingAs($counsellor, 'sanctum')
            ->getJson('/api/messages/staff-list');

        $response->assertStatus(200);
        $data = $response->json();

        // Must return exactly one entry: Staff Support
        $this->assertCount(1, $data);
        $this->assertEquals('Staff Support', $data[0]['name']);
        $this->assertNull($data[0]['email']);
        $this->assertEquals('admin_group', $data[0]['id']);

        // Assert no staff names or emails appear in the entire JSON response
        $responseContent = $response->getContent();
        $this->assertStringNotContainsString('Bob Office', $responseContent);
        $this->assertStringNotContainsString('bob.office@vanquish.com', $responseContent);
        $this->assertStringNotContainsString('Charles Clinical', $responseContent);
        $this->assertStringNotContainsString('charles.clinical@vanquish.com', $responseContent);
    }

    public function test_staff_user_sees_real_staff_list()
    {
        $staff = $this->createStaffMember('Alice Admin', 'alice.admin@vanquish.com');
        $this->createStaffMember('Bob Office', 'bob.office@vanquish.com');

        $response = $this->actingAs($staff, 'sanctum')
            ->getJson('/api/messages/staff-list');

        $response->assertStatus(200);
        $this->assertGreaterThanOrEqual(2, count($response->json()));
        $response->assertSee('Alice Admin');
        $response->assertSee('Bob Office');
    }

    public function test_counsellor_conversations_endpoint_shows_only_staff_support()
    {
        $counsellor = $this->createCounsellor();
        $staff = $this->createStaffMember('Secret Staff Member', 'secret.staff@vanquish.com');

        // Staff sends message to counsellor
        Message::create([
            'from_user_id' => $staff->id,
            'to_tc_id' => $counsellor->training_counsellor_id,
            'to_user_id' => $counsellor->id,
            'subject' => 'Staff Subject',
            'message' => 'Hello Jane, this is confidential.',
            'type' => 'staff_to_counsellor',
            'cc_users' => [$staff->id],
            'is_read' => false,
        ]);

        $response = $this->actingAs($counsellor, 'sanctum')
            ->getJson('/api/messages/conversations');

        $response->assertStatus(200);
        $data = $response->json();

        $this->assertCount(1, $data);
        $this->assertEquals('Staff Support', $data[0]['peer_name']);
        $this->assertEquals('Staff Support', $data[0]['last_message']['from_user']['name']);
        $this->assertNull($data[0]['last_message']['from_user']['email'] ?? null);

        // Raw response content must not contain staff name or email
        $content = $response->getContent();
        $this->assertStringNotContainsString('Secret Staff Member', $content);
        $this->assertStringNotContainsString('secret.staff@vanquish.com', $content);
    }

    public function test_counsellor_messages_index_masks_staff_names_and_emails()
    {
        $counsellor = $this->createCounsellor();
        $staff = $this->createStaffMember('Private Admin', 'private.admin@vanquish.com');

        Message::create([
            'from_user_id' => $staff->id,
            'to_tc_id' => $counsellor->training_counsellor_id,
            'to_user_id' => $counsellor->id,
            'subject' => 'Welcome message',
            'message' => 'Please review induction.',
            'type' => 'staff_to_counsellor',
            'cc_users' => [$staff->id],
            'is_read' => false,
        ]);

        // Group endpoint
        $responseGroup = $this->actingAs($counsellor, 'sanctum')
            ->getJson('/api/messages?peer_id=admin_group&peer_type=group');

        $responseGroup->assertStatus(200);
        $groupContent = $responseGroup->getContent();
        $this->assertStringNotContainsString('Private Admin', $groupContent);
        $this->assertStringNotContainsString('private.admin@vanquish.com', $groupContent);
        $this->assertStringContainsString('Staff Support', $groupContent);

        // Inbox folder endpoint
        $responseInbox = $this->actingAs($counsellor, 'sanctum')
            ->getJson('/api/messages?folder=inbox');

        $responseInbox->assertStatus(200);
        $inboxContent = $responseInbox->getContent();
        $this->assertStringNotContainsString('Private Admin', $inboxContent);
        $this->assertStringNotContainsString('private.admin@vanquish.com', $inboxContent);
        $this->assertStringContainsString('Staff Support', $inboxContent);
    }

    public function test_counsellor_message_show_masks_staff_details()
    {
        $counsellor = $this->createCounsellor();
        $staff = $this->createStaffMember('Clara Coordinator', 'clara.coord@vanquish.com');

        $msg = Message::create([
            'from_user_id' => $staff->id,
            'to_tc_id' => $counsellor->training_counsellor_id,
            'to_user_id' => $counsellor->id,
            'subject' => 'Important update',
            'message' => 'Your paperwork is complete.',
            'type' => 'staff_to_counsellor',
            'cc_users' => [$staff->id],
            'is_read' => false,
        ]);

        $response = $this->actingAs($counsellor, 'sanctum')
            ->getJson("/api/messages/{$msg->id}");

        $response->assertStatus(200);
        $content = $response->getContent();
        $this->assertStringNotContainsString('Clara Coordinator', $content);
        $this->assertStringNotContainsString('clara.coord@vanquish.com', $content);
        $this->assertStringContainsString('Staff Support', $content);

        $json = $response->json();
        $this->assertEquals('Staff Support', $json['from_user']['name']);
        $this->assertNull($json['from_user']['email'] ?? null);
        $this->assertEmpty($json['cc_users']);
    }
}
