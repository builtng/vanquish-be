<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\Client;
use App\Models\Consultation;
use App\Models\Message;
use App\Models\QcApplication;
use App\Models\Session;
use App\Models\SessionNote;
use App\Models\StaffNote;
use App\Models\TraineeApplication;
use App\Models\TrainingCounsellor;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class Q03NeverDeleteRecordsTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::create([
            'name' => 'Admin Staff',
            'email' => 'admin@vqtmanagement.com',
            'role' => 'admin',
            'password' => Hash::make('password123'),
            'is_active' => true,
        ]);
    }

    public function test_Q03_client_delete_archives_and_keeps_related(): void
    {
        $this->actingAs($this->admin, 'sanctum');

        $client = Client::create([
            'name' => 'Test Client',
            'email' => 'client@example.com',
            'phone' => '07123456789',
            'status' => 'Active',
            'uuid' => 'client-uuid-1234',
        ]);

        $tc = TrainingCounsellor::create([
            'name' => 'Counselor Test',
            'email' => 'counsellor@example.com',
            'counsellor_type' => 'Trainee',
            'status' => 'Active',
            'tc_id' => 'TC999',
            'uuid' => 'tc-uuid-999',
        ]);

        $consultation = Consultation::create([
            'consultation_id' => 'CONS001',
            'client_id' => $client->id,
            'tc_id' => $tc->id,
            'status' => 'scheduled',
            'scheduled_at' => now(),
            'duration_minutes' => 50,
        ]);

        $sessionNote = SessionNote::create([
            'client_id' => $client->id,
            'training_counsellor_id' => $tc->id,
            'type' => 'weekly',
            'content' => ['note' => 'Confidential therapy notes for client'],
        ]);

        $client->update([
            'agreement_status' => 'signed',
            'agreement_signed_at' => now(),
        ]);

        $staffNote = StaffNote::create([
            'staff_id' => $this->admin->id,
            'admin_id' => $this->admin->id,
            'note' => 'Admin staff note for client history',
        ]);

        $activityLog = ActivityLog::create([
            'user_id' => $this->admin->id,
            'action' => 'client_registered',
            'model_type' => Client::class,
            'model_id' => $client->id,
            'description' => 'Client profile created',
        ]);

        $response = $this->deleteJson("/api/clients/{$client->id}");
        $response->assertStatus(200);

        // Assert Client is soft deleted and archived_at is set
        $clientFresh = Client::withTrashed()->find($client->id);
        $this->assertNotNull($clientFresh, 'Client row must remain in database');
        $this->assertNotNull($clientFresh->archived_at, 'Client archived_at must be set');
        $this->assertNotNull($clientFresh->deleted_at, 'Client must be soft deleted');

        // Assert all related records are kept in database
        $this->assertEquals('signed', $clientFresh->agreement_status, 'Client agreement status preserved');
        $this->assertDatabaseHas('consultations', ['id' => $consultation->id]);
        $this->assertDatabaseHas('session_notes', ['id' => $sessionNote->id]);
        $this->assertDatabaseHas('staff_notes', ['id' => $staffNote->id]);
        $this->assertDatabaseHas('activity_logs', ['id' => $activityLog->id]);
    }

    public function test_Q03_consultation_not_hard_deleted(): void
    {
        $this->actingAs($this->admin, 'sanctum');

        $client = Client::create([
            'name' => 'Consultation Client',
            'email' => 'client2@example.com',
            'status' => 'Active',
            'uuid' => 'client2-uuid-1234',
        ]);

        $consultation = Consultation::create([
            'consultation_id' => 'CONS002',
            'client_id' => $client->id,
            'status' => 'scheduled',
            'scheduled_at' => now(),
            'duration_minutes' => 50,
        ]);

        $response = $this->deleteJson("/api/consultations/{$consultation->id}");
        $response->assertStatus(200);

        // Consultation must still exist in DB, marked cancelled with cancelled_at and cancelled_by
        $fresh = Consultation::withTrashed()->find($consultation->id);
        $this->assertNotNull($fresh, 'Consultation must not be permanently deleted');
        $this->assertEquals('cancelled', $fresh->status);
        $this->assertNotNull($fresh->cancelled_at);
        $this->assertEquals($this->admin->id, $fresh->cancelled_by);
    }

    public function test_Q03_activity_log_cannot_be_deleted(): void
    {
        $this->actingAs($this->admin, 'sanctum');

        $log = ActivityLog::create([
            'user_id' => $this->admin->id,
            'action' => 'admin_note_created',
            'description' => 'Critical audit note',
        ]);

        $response = $this->deleteJson("/api/activity-logs/{$log->id}");
        // Must return 405 Method Not Allowed or 404
        $this->assertTrue(in_array($response->status(), [404, 405]), "Status was {$response->status()}, expected 404 or 405");

        $this->assertDatabaseHas('activity_logs', ['id' => $log->id]);
    }

    public function test_Q03_archived_hidden_by_default_and_listed_with_flag(): void
    {
        $this->actingAs($this->admin, 'sanctum');

        // 1. Clients
        $activeClient = Client::create([
            'name' => 'Active Client',
            'email' => 'active@example.com',
            'status' => 'Active',
            'uuid' => 'c-active-uuid',
        ]);
        $archivedClient = Client::create([
            'name' => 'Archived Client',
            'email' => 'archived@example.com',
            'status' => 'Archived',
            'archived_at' => now(),
            'uuid' => 'c-archived-uuid',
        ]);
        $archivedClient->delete(); // Soft delete

        $res1 = $this->getJson('/api/clients');
        $res1->assertStatus(200);
        $clientIds1 = collect($res1->json('data') ?? $res1->json())->pluck('id')->toArray();
        $this->assertContains($activeClient->id, $clientIds1);
        $this->assertNotContains($archivedClient->id, $clientIds1);

        $res2 = $this->getJson('/api/clients?include_archived=1');
        $res2->assertStatus(200);
        $clientIds2 = collect($res2->json('data') ?? $res2->json())->pluck('id')->toArray();
        $this->assertContains($activeClient->id, $clientIds2);
        $this->assertContains($archivedClient->id, $clientIds2);

        // 2. Training Counsellors
        $activeTc = TrainingCounsellor::create([
            'name' => 'Active Counsellor',
            'email' => 'active_tc@example.com',
            'tc_id' => 'TC101',
            'status' => 'Active',
            'uuid' => 'tc101-uuid',
        ]);
        $archivedTc = TrainingCounsellor::create([
            'name' => 'Archived Counsellor',
            'email' => 'archived_tc@example.com',
            'tc_id' => 'TC102',
            'status' => 'Archived',
            'archived_at' => now(),
            'uuid' => 'tc102-uuid',
        ]);
        $archivedTc->delete();

        $resTc1 = $this->getJson('/api/training-counsellors');
        $resTc1->assertStatus(200);
        $tcData1 = $resTc1->json('data') ?? $resTc1->json();
        $tcIds1 = collect($tcData1)->pluck('id')->toArray();
        $this->assertContains($activeTc->id, $tcIds1);
        $this->assertNotContains($archivedTc->id, $tcIds1);

        $resTc2 = $this->getJson('/api/training-counsellors?include_archived=1');
        $resTc2->assertStatus(200);
        $tcData2 = $resTc2->json('data') ?? $resTc2->json();
        $tcIds2 = collect($tcData2)->pluck('id')->toArray();
        $this->assertContains($activeTc->id, $tcIds2);
        $this->assertContains($archivedTc->id, $tcIds2);

        // 3. Trainee Applications
        $activeApp = TraineeApplication::create([
            'first_name' => 'Alice',
            'last_name' => 'Active',
            'email' => 'alice@example.com',
            'status' => 'New Application',
            'institution' => 'University A',
            'course_name' => 'Counselling MSc',
            'source' => 'internal_form',
        ]);
        $archivedApp = TraineeApplication::create([
            'first_name' => 'Bob',
            'last_name' => 'Archived',
            'email' => 'bob@example.com',
            'status' => 'Archived',
            'archived_at' => now(),
            'institution' => 'University B',
            'course_name' => 'Psychology Dip',
            'source' => 'internal_form',
        ]);
        $archivedApp->delete();

        $resApp1 = $this->getJson('/api/trainee-applications');
        $resApp1->assertStatus(200);
        $appIds1 = collect($resApp1->json('data') ?? $resApp1->json())->pluck('id')->toArray();
        $this->assertContains($activeApp->id, $appIds1);
        $this->assertNotContains($archivedApp->id, $appIds1);

        $resApp2 = $this->getJson('/api/trainee-applications?include_archived=1');
        $resApp2->assertStatus(200);
        $appIds2 = collect($resApp2->json('data') ?? $resApp2->json())->pluck('id')->toArray();
        $this->assertContains($activeApp->id, $appIds2);
        $this->assertContains($archivedApp->id, $appIds2);

        // 4. QC Applications
        $activeQc = QcApplication::create([
            'legal_first_name' => 'Charlie',
            'legal_last_name' => 'Active',
            'name' => 'Charlie Active',
            'email' => 'charlie@example.com',
            'phone' => '07111222333',
            'status' => 'Submitted',
        ]);
        $archivedQc = QcApplication::create([
            'legal_first_name' => 'David',
            'legal_last_name' => 'Archived',
            'name' => 'David Archived',
            'email' => 'david@example.com',
            'phone' => '07222333444',
            'status' => 'Archived',
            'archived_at' => now(),
        ]);
        $archivedQc->delete();

        $resQc1 = $this->getJson('/api/qc-applications');
        $resQc1->assertStatus(200);
        $qcIds1 = collect($resQc1->json('data') ?? $resQc1->json())->pluck('id')->toArray();
        $this->assertContains($activeQc->id, $qcIds1);
        $this->assertNotContains($archivedQc->id, $qcIds1);

        $resQc2 = $this->getJson('/api/qc-applications?include_archived=1');
        $resQc2->assertStatus(200);
        $qcIds2 = collect($resQc2->json('data') ?? $resQc2->json())->pluck('id')->toArray();
        $this->assertContains($activeQc->id, $qcIds2);
        $this->assertContains($archivedQc->id, $qcIds2);
    }

    public function test_Q03_restore_is_logged(): void
    {
        $this->actingAs($this->admin, 'sanctum');

        // Client restore
        $client = Client::create([
            'name' => 'Restorable Client',
            'email' => 'restore_client@example.com',
            'archived_at' => now(),
            'uuid' => 'rc-uuid-1',
        ]);
        $client->delete();

        $resClient = $this->postJson("/api/clients/{$client->id}/restore");
        $resClient->assertStatus(200);

        $clientFresh = Client::find($client->id);
        $this->assertNotNull($clientFresh);
        $this->assertNull($clientFresh->archived_at);
        $this->assertNull($clientFresh->deleted_at);
        $this->assertDatabaseHas('activity_logs', [
            'action' => 'client_restored',
            'model_id' => $client->id,
        ]);

        // Counselor restore
        $tc = TrainingCounsellor::create([
            'name' => 'Restorable TC',
            'email' => 'restore_tc@example.com',
            'tc_id' => 'TC303',
            'archived_at' => now(),
            'uuid' => 'rtc-uuid-303',
        ]);
        $tc->delete();

        $resTc = $this->postJson("/api/training-counsellors/{$tc->id}/restore");
        $resTc->assertStatus(200);

        $tcFresh = TrainingCounsellor::find($tc->id);
        $this->assertNotNull($tcFresh);
        $this->assertNull($tcFresh->archived_at);
        $this->assertNull($tcFresh->deleted_at);
        $this->assertDatabaseHas('activity_logs', [
            'action' => 'counsellor_restored',
            'model_id' => $tc->id,
        ]);

        // Trainee application restore
        $app = TraineeApplication::create([
            'first_name' => 'Trainee',
            'last_name' => 'Restorable',
            'email' => 'restore_trainee@example.com',
            'status' => 'Archived',
            'archived_at' => now(),
        ]);
        $app->delete();

        $resApp = $this->postJson("/api/trainee-applications/{$app->id}/restore");
        $resApp->assertStatus(200);

        $appFresh = TraineeApplication::find($app->id);
        $this->assertNotNull($appFresh);
        $this->assertNull($appFresh->archived_at);
        $this->assertNull($appFresh->deleted_at);
        $this->assertDatabaseHas('activity_logs', [
            'action' => 'trainee_application_restored',
            'model_id' => $app->id,
        ]);

        // QC application restore
        $qc = QcApplication::create([
            'legal_first_name' => 'QC',
            'legal_last_name' => 'Restorable',
            'name' => 'QC Restorable',
            'email' => 'restore_qc@example.com',
            'phone' => '07333444555',
            'status' => 'Archived',
            'archived_at' => now(),
        ]);
        $qc->delete();

        $resQc = $this->postJson("/api/qc-applications/{$qc->id}/restore");
        $resQc->assertStatus(200);

        $qcFresh = QcApplication::find($qc->id);
        $this->assertNotNull($qcFresh);
        $this->assertNull($qcFresh->archived_at);
        $this->assertNull($qcFresh->deleted_at);
        $this->assertDatabaseHas('activity_logs', [
            'action' => 'qc_application_restored',
            'model_id' => $qc->id,
        ]);
    }

    public function test_Q03_no_controller_calls_force_delete(): void
    {
        $controllerDir = app_path('Http/Controllers');
        $files = File::allFiles($controllerDir);

        $violatingFiles = [];
        foreach ($files as $file) {
            $contents = File::get($file->getRealPath());
            if (str_contains($contents, 'forceDelete')) {
                $violatingFiles[] = $file->getRelativePathname();
            }
        }

        $this->assertEmpty(
            $violatingFiles,
            'Controllers must NEVER permanently delete records via forceDelete(): ' . implode(', ', $violatingFiles)
        );
    }

    public function test_Q03_session_cancel_keeps_record(): void
    {
        $this->actingAs($this->admin, 'sanctum');

        $client = Client::create([
            'name' => 'Session Client',
            'email' => 'session_client@example.com',
            'status' => 'Active',
            'uuid' => 'session-client-uuid',
        ]);

        $tc = TrainingCounsellor::create([
            'name' => 'Session TC',
            'email' => 'session_tc@example.com',
            'tc_id' => 'TC808',
            'status' => 'Active',
            'uuid' => 'session-tc-uuid',
        ]);

        $consultation = Consultation::create([
            'consultation_id' => 'CONS003',
            'client_id' => $client->id,
            'tc_id' => $tc->id,
            'status' => 'scheduled',
            'scheduled_at' => now(),
            'duration_minutes' => 50,
        ]);

        $session = Session::create([
            'client_id' => $client->id,
            'tc_id' => $tc->id,
            'session_type' => 'Counselling',
            'scheduled_at' => now(),
            'status' => 'scheduled',
            'duration_minutes' => 50,
        ]);

        // Cancel consultation via cancel endpoint
        $response = $this->postJson("/api/consultations/{$consultation->id}/cancel", [
            'reason' => 'Client requested cancellation',
        ]);
        $response->assertStatus(200);

        $freshConsultation = Consultation::withTrashed()->find($consultation->id);
        $this->assertNotNull($freshConsultation);
        $this->assertEquals('cancelled', $freshConsultation->status);
        $this->assertNotNull($freshConsultation->cancelled_at);
        $this->assertEquals($this->admin->id, $freshConsultation->cancelled_by);

        // Cancel session
        $session->update([
            'status' => 'cancelled',
            'cancelled_at' => now(),
            'cancelled_by' => $this->admin->id,
        ]);

        $freshSession = Session::withTrashed()->find($session->id);
        $this->assertNotNull($freshSession);
        $this->assertEquals('cancelled', $freshSession->status);
        $this->assertNotNull($freshSession->cancelled_at);
        $this->assertEquals($this->admin->id, $freshSession->cancelled_by);

        $this->assertDatabaseHas('activity_logs', [
            'action' => 'consultation_cancelled',
        ]);
    }

    public function test_Q03_note_hide_keeps_log(): void
    {
        $this->actingAs($this->admin, 'sanctum');

        $note = ActivityLog::create([
            'user_id' => $this->admin->id,
            'action' => 'admin_note_created',
            'description' => 'Sensitive staff observation',
        ]);

        // Hide note
        $resHide = $this->postJson("/api/activity-logs/{$note->id}/hide");
        $resHide->assertStatus(200);

        $freshNote = ActivityLog::find($note->id);
        $this->assertNotNull($freshNote, 'Note must remain in database');
        $this->assertNotNull($freshNote->hidden_at);
        $this->assertEquals($this->admin->id, $freshNote->hidden_by);

        // Excluded by default
        $resList = $this->getJson('/api/activity-logs');
        $logIds = collect($resList->json('data') ?? $resList->json())->pluck('id')->toArray();
        $this->assertNotContains($note->id, $logIds);

        // Included when include_hidden=1
        $resListHidden = $this->getJson('/api/activity-logs?include_hidden=1');
        $logIdsHidden = collect($resListHidden->json('data') ?? $resListHidden->json())->pluck('id')->toArray();
        $this->assertContains($note->id, $logIdsHidden);

        // Unhide note
        $resUnhide = $this->postJson("/api/activity-logs/{$note->id}/unhide");
        $resUnhide->assertStatus(200);

        $freshNote2 = ActivityLog::find($note->id);
        $this->assertNull($freshNote2->hidden_at);
        $this->assertNull($freshNote2->hidden_by);
    }

    public function test_Q03_message_delete_is_soft(): void
    {
        $userA = User::create([
            'name' => 'User Alice',
            'email' => 'alice@vqtmanagement.com',
            'role' => 'counsellor',
            'password' => Hash::make('password123'),
        ]);

        $userB = User::create([
            'name' => 'User Bob',
            'email' => 'bob@vqtmanagement.com',
            'role' => 'client',
            'password' => Hash::make('password123'),
        ]);

        $message = Message::create([
            'from_user_id' => $userA->id,
            'to_user_id' => $userB->id,
            'subject' => 'Session Update',
            'message' => 'Hello Bob, regarding our session next week.',
        ]);

        // User A deletes message
        $this->actingAs($userA, 'sanctum');
        $resA = $this->deleteJson("/api/messages/{$message->id}");
        $resA->assertStatus(200);

        $freshMsg = Message::find($message->id);
        $this->assertNotNull($freshMsg, 'Message row must remain in database');
        $this->assertNotNull($freshMsg->deleted_by_sender_at);
        $this->assertNull($freshMsg->deleted_by_recipient_at);

        // User A no longer sees it in messages
        $resInboxA = $this->getJson("/api/messages?contact_id={$userB->id}");
        $messagesA = collect($resInboxA->json('data') ?? $resInboxA->json())->pluck('id')->toArray();
        $this->assertNotContains($message->id, $messagesA);

        // User B STILL sees it!
        $this->actingAs($userB, 'sanctum');
        $resInboxB = $this->getJson("/api/messages?contact_id={$userA->id}");
        $messagesB = collect($resInboxB->json('data') ?? $resInboxB->json())->pluck('id')->toArray();
        $this->assertContains($message->id, $messagesB);

        // User B deletes message
        $resB = $this->deleteJson("/api/messages/{$message->id}");
        $resB->assertStatus(200);

        $freshMsg2 = Message::find($message->id);
        $this->assertNotNull($freshMsg2, 'Message row must remain for system/admin retention');
        $this->assertNotNull($freshMsg2->deleted_by_recipient_at);
    }
}
