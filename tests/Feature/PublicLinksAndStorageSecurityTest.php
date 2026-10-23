<?php

namespace Tests\Feature;

use App\Models\Message;
use App\Models\SharedDocument;
use App\Models\TrainingCounsellor;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PublicLinksAndStorageSecurityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
        Storage::fake('private');
    }

    public function test_shared_document_upload_saves_to_private_disk_and_not_public()
    {
        $admin = User::create([
            'name' => 'Admin User',
            'email' => 'admin@example.com',
            'password' => bcrypt('password'),
            'role' => 'admin',
        ]);

        $file = UploadedFile::fake()->create('contract.pdf', 100, 'application/pdf');

        $response = $this->actingAs($admin)->post('/api/shared-documents', [
            'name' => 'Contract',
            'file' => $file,
        ]);

        $response->assertStatus(201);
        $doc = SharedDocument::first();
        $this->assertNotNull($doc);

        // Crucial security check: stored on private disk, NOT public
        $this->assertTrue(Storage::disk('private')->exists($doc->file_path));
        $this->assertFalse(Storage::disk('public')->exists($doc->file_path));
    }

    public function test_message_attachment_upload_saves_to_private_disk_and_not_public()
    {
        $sender = User::create([
            'name' => 'Staff Sender',
            'email' => 'sender@example.com',
            'password' => bcrypt('password'),
            'role' => 'staff',
        ]);

        $recipient = User::create([
            'name' => 'Counsellor Recipient',
            'email' => 'recipient@example.com',
            'password' => bcrypt('password'),
            'role' => 'counsellor',
        ]);

        $tc = TrainingCounsellor::create([
            'user_id' => $recipient->id,
            'name' => 'Counsellor Recipient',
            'first_name' => 'Counsellor',
            'last_name' => 'Recipient',
            'email' => 'recipient@example.com',
            'phone' => '07111222999',
            'status' => 'active',
        ]);
        $recipient->update(['training_counsellor_id' => $tc->id]);

        $file = UploadedFile::fake()->create('session_schedule.pdf', 50, 'application/pdf');

        $response = $this->actingAs($sender)->post('/api/messages/send-to-counsellor', [
            'tc_ids' => [$tc->id],
            'subject' => 'Session Schedule',
            'message' => 'Please find attached schedule.',
            'attachment' => $file,
            'send_email_notification' => false,
        ]);

        $response->assertStatus(201);

        $message = Message::whereNotNull('attachment_path')->first();
        $this->assertNotNull($message);

        // Crucial security check: stored on private disk, NOT public
        $this->assertTrue(Storage::disk('private')->exists($message->attachment_path));
        $this->assertFalse(Storage::disk('public')->exists($message->attachment_path));
    }

    public function test_unauthenticated_user_cannot_download_message_attachment()
    {
        Storage::disk('private')->put('message_attachments/secret.pdf', 'Secret attachment data');

        $sender = User::create([
            'name' => 'Sender',
            'email' => 'sender2@example.com',
            'password' => bcrypt('password'),
            'role' => 'admin',
        ]);

        $recipient = User::create([
            'name' => 'Recipient User 2',
            'email' => 'recipient2@example.com',
            'password' => bcrypt('password'),
            'role' => 'counsellor',
        ]);

        $msg = Message::create([
            'from_user_id' => $sender->id,
            'to_user_id' => $recipient->id,
            'subject' => 'Secret Message',
            'message' => 'Hello',
            'attachment_path' => 'message_attachments/secret.pdf',
            'attachment_name' => 'secret.pdf',
            'attachment_type' => 'application/pdf',
        ]);

        $response = $this->getJson("/api/messages/{$msg->id}/attachment");
        $response->assertStatus(401);
    }

    public function test_uninvolved_counsellor_cannot_download_another_users_message_attachment()
    {
        Storage::disk('private')->put('message_attachments/confidential.pdf', 'Confidential attachment');

        $sender = User::create([
            'name' => 'Alice',
            'email' => 'alice@example.com',
            'password' => bcrypt('password'),
            'role' => 'counsellor',
        ]);

        $recipient = User::create([
            'name' => 'Bob',
            'email' => 'bob@example.com',
            'password' => bcrypt('password'),
            'role' => 'counsellor',
        ]);

        $attacker = User::create([
            'name' => 'Eve',
            'email' => 'eve@example.com',
            'password' => bcrypt('password'),
            'role' => 'counsellor',
        ]);

        $msg = Message::create([
            'from_user_id' => $sender->id,
            'to_user_id' => $recipient->id,
            'subject' => 'Confidential Discussion',
            'message' => 'Private message',
            'attachment_path' => 'message_attachments/confidential.pdf',
            'attachment_name' => 'confidential.pdf',
            'attachment_type' => 'application/pdf',
        ]);

        // Eve should get 403 Forbidden
        $response = $this->actingAs($attacker)->getJson("/api/messages/{$msg->id}/attachment");
        $response->assertStatus(403);
    }

    public function test_sender_recipient_and_staff_can_download_message_attachment()
    {
        Storage::disk('private')->put('message_attachments/valid.pdf', 'Valid attachment content');

        $sender = User::create([
            'name' => 'Sender User',
            'email' => 'sender3@example.com',
            'password' => bcrypt('password'),
            'role' => 'counsellor',
        ]);

        $recipient = User::create([
            'name' => 'Recipient User',
            'email' => 'recipient3@example.com',
            'password' => bcrypt('password'),
            'role' => 'counsellor',
        ]);

        $staff = User::create([
            'name' => 'Staff Admin',
            'email' => 'staff3@example.com',
            'password' => bcrypt('password'),
            'role' => 'staff',
        ]);

        $msg = Message::create([
            'from_user_id' => $sender->id,
            'to_user_id' => $recipient->id,
            'subject' => 'Valid Message',
            'message' => 'Test message',
            'attachment_path' => 'message_attachments/valid.pdf',
            'attachment_name' => 'valid.pdf',
            'attachment_type' => 'application/pdf',
        ]);

        // Sender can download
        $senderResp = $this->actingAs($sender)->get("/api/messages/{$msg->id}/attachment");
        $senderResp->assertStatus(200);

        // Recipient can download
        $recipientResp = $this->actingAs($recipient)->get("/api/messages/{$msg->id}/attachment");
        $recipientResp->assertStatus(200);

        // Staff can download
        $staffResp = $this->actingAs($staff)->get("/api/messages/{$msg->id}/attachment");
        $staffResp->assertStatus(200);
    }

    public function test_move_documents_to_private_migrates_shared_documents_and_message_attachments()
    {
        Storage::fake('public');
        Storage::fake('private');

        $sharedPath = 'shared_documents/legacy_shared.pdf';
        $messagePath = 'message_attachments/legacy_attachment.pdf';

        Storage::disk('public')->put($sharedPath, 'Legacy shared content');
        Storage::disk('public')->put($messagePath, 'Legacy message content');

        $admin = User::create([
            'name' => 'Admin Legacy',
            'email' => 'admin.legacy@example.com',
            'password' => bcrypt('password'),
            'role' => 'admin',
        ]);

        SharedDocument::create([
            'name' => 'Legacy Doc',
            'file_path' => $sharedPath,
            'file_type' => 'PDF',
            'file_size' => '20 B',
            'uploaded_by' => $admin->id,
        ]);

        Message::create([
            'from_user_id' => $admin->id,
            'to_user_id' => $admin->id,
            'subject' => 'Legacy Subject',
            'message' => 'Legacy msg',
            'attachment_path' => $messagePath,
            'attachment_name' => 'legacy_attachment.pdf',
        ]);

        // Run migration command
        $this->artisan('documents:move-to-private')
            ->expectsOutputToContain('Moved: ' . $sharedPath)
            ->expectsOutputToContain('Moved: ' . $messagePath)
            ->assertExitCode(0);

        // Verify files moved to private disk and removed from public
        $this->assertTrue(Storage::disk('private')->exists($sharedPath));
        $this->assertFalse(Storage::disk('public')->exists($sharedPath));

        $this->assertTrue(Storage::disk('private')->exists($messagePath));
        $this->assertFalse(Storage::disk('public')->exists($messagePath));
    }

    public function test_move_documents_to_private_dry_run_moves_nothing()
    {
        Storage::fake('public');
        Storage::fake('private');

        $sharedPath = 'shared_documents/dry_run_shared.pdf';
        $messagePath = 'message_attachments/dry_run_msg.pdf';

        Storage::disk('public')->put($sharedPath, 'Dry run shared');
        Storage::disk('public')->put($messagePath, 'Dry run msg');

        $this->artisan('documents:move-to-private', ['--dry-run' => true])
            ->assertExitCode(0);

        // Dry run must leave files on public and NOT move them to private
        $this->assertTrue(Storage::disk('public')->exists($sharedPath));
        $this->assertFalse(Storage::disk('private')->exists($sharedPath));

        $this->assertTrue(Storage::disk('public')->exists($messagePath));
        $this->assertFalse(Storage::disk('private')->exists($messagePath));
    }
}
