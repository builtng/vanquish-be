<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\ClientIntakeForm;
use App\Models\Person;
use App\Models\QcApplication;
use App\Models\TraineeApplication;
use App\Models\TrainingCounsellor;
use App\Models\User;
use App\Models\EmailLog;
use App\Models\ActivityLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class SameEmailSubmissionSeparationTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Requirement 1: Two client intakes with the same email.
     * Confirm two distinct client submissions exist in the database, each with its own answers.
     * Confirm client profile shows the second submission as current and the first in history.
     */
    public function test_two_client_intakes_with_same_email_create_separate_cases_and_preserve_history()
    {
        Mail::fake();

        $admin = User::factory()->create(['role' => 'admin']);

        // First intake
        $payload1 = [
            'first_name' => 'Sarah',
            'last_name' => 'Connor',
            'email' => 'sarah.connor@example.com',
            'phone' => '07111111111',
            'age' => 28,
            'address' => '10 First St, London',
            'emergency_contact_name' => 'John Connor',
            'emergency_contact_phone' => '07222222222',
            'emergency_contact_relationship' => 'Son',
            'service_type' => 'Low Cost',
            'support_areas' => ['Anxiety'],
            'terms_accepted' => true,
            'create_client' => true,
            'consultation_fee' => 10.00,
        ];

        $res1 = $this->postJson('/api/client-intake', $payload1);
        $res1->assertStatus(201);

        $client1 = Client::where('email', 'sarah.connor@example.com')->first();
        $this->assertNotNull($client1);
        $this->assertEquals('Low Cost', $client1->service_type);

        // Second intake later with same email but different details
        $payload2 = [
            'first_name' => 'Sarah',
            'last_name' => 'Connor-Reese',
            'email' => 'sarah.connor@example.com',
            'phone' => '07333333333',
            'age' => 30,
            'address' => '20 Second Ave, Manchester',
            'emergency_contact_name' => 'Kyle Reese',
            'emergency_contact_phone' => '07444444444',
            'emergency_contact_relationship' => 'Partner',
            'service_type' => 'Mid Range',
            'support_areas' => ['Bereavement', 'Trauma'],
            'terms_accepted' => true,
            'create_client' => true,
            'consultation_fee' => 35.00,
        ];

        $res2 = $this->postJson('/api/client-intake', $payload2);
        $res2->assertStatus(201);

        // Both clients must exist independently
        $clients = Client::where('email', 'sarah.connor@example.com')->orderBy('id', 'asc')->get();
        $this->assertCount(2, $clients);
        $this->assertNotEquals($clients[0]->id, $clients[1]->id);
        $this->assertNotEquals($clients[0]->client_id, $clients[1]->client_id);
        $this->assertEquals('Low Cost', $clients[0]->service_type);
        $this->assertEquals('Mid Range', $clients[1]->service_type);

        // Both linked to the same Person identity
        $this->assertNotNull($clients[0]->person_id);
        $this->assertEquals($clients[0]->person_id, $clients[1]->person_id);

        // Check client details API on second client loads first client in related_cases
        $showRes = $this->actingAs($admin)->getJson("/api/clients/{$clients[1]->uuid}");
        $showRes->assertStatus(200);
        $relatedCases = $showRes->json('related_cases');
        $this->assertCount(1, $relatedCases);
        $this->assertEquals($clients[0]->client_id, $relatedCases[0]['client_id']);
    }

    /**
     * Requirement 2: Two trainee applications with the same email.
     * Confirm both exist as separate applications with independent pipeline progress.
     */
    public function test_two_trainee_applications_with_same_email_create_separate_applications()
    {
        Mail::fake();

        $admin = User::factory()->create(['role' => 'admin']);

        // First application
        $app1 = [
            'first_name' => 'James',
            'last_name' => 'Wilson',
            'email' => 'james.wilson@example.com',
            'phone' => '07123456789',
            'institution' => 'University of London',
            'course_name' => 'MSc Counselling Stage 1',
        ];

        $res1 = $this->postJson('/api/trainee-applications', $app1);
        $res1->assertStatus(201);
        $firstAppId = $res1->json('application.id');

        // Advance first application's pipeline
        $firstApp = TraineeApplication::find($firstAppId);
        $firstApp->update(['status' => 'Interview Scheduled']);

        // Second application from the same person (e.g. re-applying next year)
        $app2 = [
            'first_name' => 'James',
            'last_name' => 'Wilson',
            'email' => 'james.wilson@example.com',
            'phone' => '07987654321',
            'institution' => 'Metanoia Institute',
            'course_name' => 'Diploma in Integrative Psychotherapy',
        ];

        $res2 = $this->postJson('/api/trainee-applications', $app2);
        $res2->assertStatus(201);
        $secondAppId = $res2->json('application.id');

        $this->assertNotEquals($firstAppId, $secondAppId);

        // Confirm both exist independently in database
        $apps = TraineeApplication::where('email', 'james.wilson@example.com')->get();
        $this->assertCount(2, $apps);

        // Independent status / progress
        $firstAppFresh = TraineeApplication::find($firstAppId);
        $secondAppFresh = TraineeApplication::find($secondAppId);
        $this->assertEquals('Interview Scheduled', $firstAppFresh->status);
        // Second application was dispatched to Stage 2 Invite synchronously in test
        $this->assertEquals('Stage 2 Invited', $secondAppFresh->status);

        // Show endpoint includes previous submissions
        $showRes = $this->actingAs($admin)->getJson("/api/trainee-applications/{$secondAppId}");
        $showRes->assertStatus(200);
        $prevSubmissions = $showRes->json('previous_submissions');
        $this->assertCount(1, $prevSubmissions);
        $this->assertEquals($firstAppId, $prevSubmissions[0]['id']);
    }

    /**
     * Requirement 3 & 4: Rooshan's bug reproduction and fix.
     * Rooshan deleted old "Dettol Smith" profile and resubmitted.
     * Confirm:
     * - Resubmission does NOT resurrect "Dettol Smith"
     * - New record is created clean with zero data inherited from the deleted profile
     * - Deleted profile remains archived in database
     * - Confirmation email is addressed to Rooshan, NOT Dettol Smith
     */
    public function test_rooshan_dettol_smith_bug_fixed_separate_clean_record_and_correct_email()
    {
        Mail::fake();

        $admin = User::factory()->create(['role' => 'admin']);

        // 1. Pre-existing profile: "Dettol Smith" with email rooshan@example.com
        $person = Person::findOrCreateByEmail('rooshan@example.com', 'Dettol Smith', '07000000000');

        $oldTc = TrainingCounsellor::create([
            'person_id' => $person->id,
            'tc_id' => 'QC001',
            'name' => 'Dettol Smith',
            'legal_first_name' => 'Dettol',
            'legal_last_name' => 'Smith',
            'email' => 'rooshan@example.com',
            'phone' => '07000000000',
            'registered_address' => '1 Old Dirt Road',
            'registered_city' => 'Manchester',
            'registered_postcode' => 'M1 1AA',
            'counsellor_type' => 'Qualified',
            'status' => 'Active',
            'signature' => 'Dettol Smith',
            'signature_date' => '2026-01-01',
        ]);

        $user = User::create([
            'name' => 'Dettol Smith',
            'email' => 'rooshan@example.com',
            'password' => bcrypt('secret123'),
            'role' => 'counsellor',
            'training_counsellor_id' => $oldTc->id,
        ]);

        // 2. Rooshan deletes/archives "Dettol Smith"
        $delRes = $this->actingAs($admin)->deleteJson("/api/training-counsellors/{$oldTc->id}");
        $delRes->assertStatus(200);

        // Confirm old record is soft-deleted and archived, NOT permanently destroyed
        $this->assertSoftDeleted('training_counsellors', ['id' => $oldTc->id]);
        $oldTcFresh = TrainingCounsellor::withTrashed()->find($oldTc->id);
        $this->assertNotNull($oldTcFresh->archived_at);

        // 3. Rooshan submits the Qualified Counsellor onboarding form
        $qcPayload = [
            'legal_first_name' => 'Rooshan',
            'legal_last_name' => 'Ahmed',
            'email' => 'rooshan@example.com',
            'phone' => '07999888777',
            'registered_address' => '50 High Street',
            'registered_city' => 'Birmingham',
            'registered_postcode' => 'B1 1BB',
            'has_supervisor' => 'yes',
            'signature' => 'Rooshan Ahmed',
            'signature_date' => '2026-10-01',
        ];

        $submitRes = $this->postJson('/api/submit-qualified-form', $qcPayload);
        $submitRes->assertStatus(200);

        // 4. Verify new record is created clean:
        // - A new QcApplication record exists for Rooshan
        $qcApp = QcApplication::where('email', 'rooshan@example.com')->latest('id')->first();
        $this->assertNotNull($qcApp);
        $this->assertEquals('Rooshan', $qcApp->legal_first_name);
        $this->assertEquals('Ahmed', $qcApp->legal_last_name);

        // Admin accepts the application (TrainingCounsellor is created when admin accepts)
        $acceptRes = $this->actingAs($admin)->postJson("/api/qc-applications/{$qcApp->id}/accept");
        $acceptRes->assertStatus(200);

        // - A fresh active TrainingCounsellor exists with name "Rooshan Ahmed"
        $activeTc = TrainingCounsellor::whereNull('archived_at')->where('email', 'rooshan@example.com')->first();
        $this->assertNotNull($activeTc);
        $this->assertNotEquals($oldTc->id, $activeTc->id);
        $this->assertEquals('Rooshan Ahmed', $activeTc->name);
        $this->assertEquals('Rooshan', $activeTc->legal_first_name);
        $this->assertEquals('Ahmed', $activeTc->legal_last_name);
        $this->assertEquals('50 High Street', $activeTc->registered_address);

        // - Old record remained trashed and untouched as "Dettol Smith"
        $oldTcCheck = TrainingCounsellor::withTrashed()->find($oldTc->id);
        $this->assertTrue($oldTcCheck->trashed());
        $this->assertEquals('Dettol Smith', $oldTcCheck->name);

        // - User record is updated to point to the active profile
        $userFresh = User::where('email', 'rooshan@example.com')->first();
        $this->assertEquals($activeTc->id, $userFresh->training_counsellor_id);
        $this->assertEquals('Rooshan Ahmed', $userFresh->name);

        // 5. Requirement 5: Confirmation email sent reflects ROOSHAN, NOT Dettol Smith
        $emailLog = EmailLog::where('email', 'rooshan@example.com')
            ->where('template_name', 'qualified_counsellor_submission')
            ->latest('id')
            ->first();

        $this->assertNotNull($emailLog, 'Confirmation email log should exist');
        $payload = is_array($emailLog->payload) ? $emailLog->payload : json_decode($emailLog->payload, true);
        $this->assertEquals('Rooshan', $payload['first_name']);
        $this->assertEquals('Rooshan Ahmed', $payload['counsellor_name']);
    }

    /**
     * Requirement 6: Case sensitivity and whitespace handling.
     * 'User@Example.COM ' and 'user@example.com' are recognized as the same person,
     * but still produce separate submissions.
     */
    public function test_case_insensitivity_and_whitespace_matching()
    {
        Mail::fake();

        // 1. Submit first trainee application with irregular casing and trailing space
        $res1 = $this->postJson('/api/trainee-applications', [
            'first_name' => 'Oliver',
            'last_name' => 'Queen',
            'email' => ' Oliver.Queen@Example.COM  ',
            'phone' => '07111222333',
        ]);
        $res1->assertStatus(201);

        // 2. Submit second trainee application with clean lowercase email
        $res2 = $this->postJson('/api/trainee-applications', [
            'first_name' => 'Oliver',
            'last_name' => 'Queen',
            'email' => 'oliver.queen@example.com',
            'phone' => '07444555666',
        ]);
        $res2->assertStatus(201);

        // Confirm only ONE Person was created
        $persons = Person::where('email', 'oliver.queen@example.com')->get();
        $this->assertCount(1, $persons);

        // Confirm TWO distinct applications were created, both pointing to that one Person
        $apps = TraineeApplication::where('email', 'oliver.queen@example.com')->get();
        $this->assertCount(2, $apps);
        $this->assertEquals($persons[0]->id, $apps[0]->person_id);
        $this->assertEquals($persons[0]->id, $apps[1]->person_id);
    }

    /**
     * FIX Q01 Check First:
     * An active counsellor "Old Person" exists with a@x.com.
     * POST /api/qualified-counsellor/submit as "New Person" with "A@x.com ".
     * Show that today the existing counsellor is renamed.
     */
    public function test_Q01_repeat_qc_keeps_existing_profile()
    {
        Mail::fake();

        $oldTc = TrainingCounsellor::create([
            'tc_id' => 'QC001',
            'name' => 'Old Person',
            'legal_first_name' => 'Old',
            'legal_last_name' => 'Person',
            'email' => 'a@x.com',
            'phone' => '07000000000',
            'registered_address' => '1 Old St',
            'registered_city' => 'London',
            'registered_postcode' => 'EC1 1AA',
            'counsellor_type' => 'Qualified',
            'status' => 'Active',
            'signature' => 'Old Person',
            'signature_date' => '2026-01-01',
        ]);

        $qcPayload = [
            'legal_first_name' => 'New',
            'legal_last_name' => 'Person',
            'email' => 'A@x.com ',
            'phone' => '07999888777',
            'registered_address' => '50 High Street',
            'registered_city' => 'Birmingham',
            'registered_postcode' => 'B1 1BB',
            'has_supervisor' => 'yes',
            'signature' => 'New Person',
            'signature_date' => '2026-10-01',
        ];

        $res = $this->postJson('/api/qualified-counsellor/submit', $qcPayload);
        $res->assertStatus(200);

        // The existing counsellor profile must remain unchanged as "Old Person"
        $this->assertEquals('Old Person', $oldTc->fresh()->name);
    }

    /**
     * Requirement: Two QC applications with one email produce two distinct applications
     * linked to the same person, with no automatic counsellor creation.
     */
    public function test_Q01_repeat_qc_creates_second_application()
    {
        Mail::fake();

        $app1 = [
            'legal_first_name' => 'Alice',
            'legal_last_name' => 'Wonderland',
            'email' => 'alice@example.com',
            'phone' => '07111222333',
            'registered_address' => '1 Rabbit Hole',
            'registered_city' => 'Oxford',
            'registered_postcode' => 'OX1 1AA',
            'has_supervisor' => 'yes',
            'signature' => 'Alice Wonderland',
            'signature_date' => '2026-01-01',
        ];

        $res1 = $this->postJson('/api/qualified-counsellor/submit', $app1);
        $res1->assertStatus(200);

        $app2 = [
            'legal_first_name' => 'Alice',
            'legal_last_name' => 'Liddell',
            'email' => 'alice@example.com',
            'phone' => '07444555666',
            'registered_address' => '2 Looking Glass Way',
            'registered_city' => 'Oxford',
            'registered_postcode' => 'OX2 2BB',
            'has_supervisor' => 'no',
            'signature' => 'Alice Liddell',
            'signature_date' => '2026-06-01',
        ];

        $res2 = $this->postJson('/api/qualified-counsellor/submit', $app2);
        $res2->assertStatus(200);

        // Confirm TWO distinct QcApplication records exist
        $apps = QcApplication::where('email', 'alice@example.com')->orderBy('id', 'asc')->get();
        $this->assertCount(2, $apps);
        $this->assertEquals('Alice Wonderland', $apps[0]->name);
        $this->assertEquals('Alice Liddell', $apps[1]->name);

        // Both link to the same Person
        $this->assertNotNull($apps[0]->person_id);
        $this->assertEquals($apps[0]->person_id, $apps[1]->person_id);

        // No training_counsellors row created automatically
        $tcCount = TrainingCounsellor::where('email', 'alice@example.com')->count();
        $this->assertEquals(0, $tcCount);
    }

    /**
     * Requirement: Submitting a QC form never restores an archived counsellor.
     */
    public function test_Q01_archived_counsellor_never_restored()
    {
        Mail::fake();

        $archivedTc = TrainingCounsellor::create([
            'tc_id' => 'QC099',
            'name' => 'Archived Counsellor',
            'legal_first_name' => 'Archived',
            'legal_last_name' => 'Counsellor',
            'email' => 'archived.qc@example.com',
            'phone' => '07000000000',
            'registered_address' => '1 Vault Road',
            'registered_city' => 'London',
            'registered_postcode' => 'EC1 1AA',
            'counsellor_type' => 'Qualified',
            'status' => 'Archived',
            'signature' => 'Archived Counsellor',
            'signature_date' => '2025-01-01',
            'archived_at' => now(),
        ]);
        $archivedTc->delete();

        $this->assertTrue($archivedTc->fresh()->trashed());

        // Submit QC form with same email
        $res = $this->postJson('/api/qualified-counsellor/submit', [
            'legal_first_name' => 'New',
            'legal_last_name' => 'Applicant',
            'email' => 'archived.qc@example.com',
            'phone' => '07999888777',
            'registered_address' => '10 New St',
            'registered_city' => 'Leeds',
            'registered_postcode' => 'LS1 1AA',
            'signature' => 'New Applicant',
            'signature_date' => '2026-10-01',
        ]);
        $res->assertStatus(200);

        // Archived counsellor remains soft-deleted and untouched
        $archivedFresh = TrainingCounsellor::withTrashed()->find($archivedTc->id);
        $this->assertTrue($archivedFresh->trashed());
        $this->assertEquals('Archived Counsellor', $archivedFresh->name);

        // No active counsellor exists
        $activeTc = TrainingCounsellor::whereNull('archived_at')->where('email', 'archived.qc@example.com')->first();
        $this->assertNull($activeTc);
    }

    /**
     * Requirement: IntakeFormController tc-intake never restores an archived counsellor.
     * Creates a new clean record linked to the person_id instead.
     */
    public function test_Q01_tc_intake_never_restores()
    {
        Mail::fake();

        $person = Person::findOrCreateByEmail('archived.intake@example.com', 'Archived Trainee', '07000000000');

        $archivedTc = TrainingCounsellor::create([
            'person_id' => $person->id,
            'tc_id' => 'TC050',
            'name' => 'Archived Trainee',
            'email' => 'archived.intake@example.com',
            'phone' => '07000000000',
            'status' => 'Archived',
            'archived_at' => now(),
        ]);
        $archivedTc->delete();

        $this->assertTrue($archivedTc->fresh()->trashed());

        // Submit tc-intake with create_tc = true
        $res = $this->postJson('/api/tc-intake', [
            'create_tc' => true,
            'name' => 'Returning Trainee',
            'first_name' => 'Returning',
            'last_name' => 'Trainee',
            'email' => 'archived.intake@example.com',
            'phone' => '07888999000',
            'gender' => 'Female',
            'modality' => 'CBT',
            'course' => 'MSc Counselling',
            'institution' => 'University of Manchester',
        ]);
        $res->assertStatus(201);

        // Archived record was NEVER restored
        $archivedFresh = TrainingCounsellor::withTrashed()->find($archivedTc->id);
        $this->assertTrue($archivedFresh->trashed());
        $this->assertEquals('Archived Trainee', $archivedFresh->name);

        // New active record was created linked to same Person
        $activeTc = TrainingCounsellor::whereNull('archived_at')->where('email', 'archived.intake@example.com')->first();
        $this->assertNotNull($activeTc);
        $this->assertNotEquals($archivedTc->id, $activeTc->id);
        $this->assertEquals('Returning Trainee', $activeTc->name);
        $this->assertEquals($person->id, $activeTc->person_id);
    }

    /**
     * Requirement: TraineeApplicationController sendPortalInvite never restores an archived counsellor.
     * Creates a new clean record linked to the person_id instead.
     */
    public function test_Q01_portal_invite_never_restores()
    {
        Mail::fake();

        $admin = User::factory()->create(['role' => 'admin']);
        $person = Person::findOrCreateByEmail('archived.portal@example.com', 'Archived Counsellor', '07000000000');

        $archivedTc = TrainingCounsellor::create([
            'person_id' => $person->id,
            'tc_id' => 'TC060',
            'name' => 'Archived Counsellor',
            'email' => 'archived.portal@example.com',
            'phone' => '07000000000',
            'status' => 'Archived',
            'archived_at' => now(),
        ]);
        $archivedTc->delete();

        $this->assertTrue($archivedTc->fresh()->trashed());

        // Create new trainee application for this email
        $app = TraineeApplication::create([
            'person_id' => $person->id,
            'first_name' => 'Returning',
            'last_name' => 'Applicant',
            'name' => 'Returning Applicant',
            'email' => 'archived.portal@example.com',
            'phone' => '07777888999',
            'status' => 'Induction Attended',
        ]);

        // Admin triggers portal invite
        $res = $this->actingAs($admin)->postJson("/api/trainee-applications/{$app->id}/portal-invite");
        $res->assertStatus(200);

        // Archived counsellor remains soft-deleted
        $archivedFresh = TrainingCounsellor::withTrashed()->find($archivedTc->id);
        $this->assertTrue($archivedFresh->trashed());
        $this->assertEquals('Archived Counsellor', $archivedFresh->name);

        // A new clean counsellor profile was created
        $activeTc = TrainingCounsellor::whereNull('archived_at')->where('email', 'archived.portal@example.com')->first();
        $this->assertNotNull($activeTc);
        $this->assertNotEquals($archivedTc->id, $activeTc->id);
        $this->assertEquals('Returning Applicant', $activeTc->name);
        $this->assertEquals($person->id, $activeTc->person_id);

        // User account points to the active counsellor
        $user = User::where('email', 'archived.portal@example.com')->first();
        $this->assertNotNull($user);
        $this->assertEquals($activeTc->id, $user->training_counsellor_id);
    }

    /**
     * Requirement: Accept QC application preserves admin/staff user account.
     */
    public function test_Q01_admin_accept_preserves_admin_user_role()
    {
        Mail::fake();

        $admin = User::factory()->create([
            'name' => 'Admin Boss',
            'email' => 'admin.boss@example.com',
            'role' => 'admin',
        ]);

        $res = $this->postJson('/api/qualified-counsellor/submit', [
            'legal_first_name' => 'Admin',
            'legal_last_name' => 'Boss',
            'email' => 'admin.boss@example.com',
            'phone' => '07111222333',
            'registered_address' => '10 Downing St',
            'registered_city' => 'London',
            'registered_postcode' => 'SW1A 2AA',
            'signature' => 'Admin Boss',
            'signature_date' => '2026-10-01',
        ]);
        $res->assertStatus(200);

        $appId = $res->json('application_id');
        $acceptRes = $this->actingAs($admin)->postJson("/api/qc-applications/{$appId}/accept");
        $acceptRes->assertStatus(200);

        // User role remains admin!
        $adminFresh = User::where('email', 'admin.boss@example.com')->first();
        $this->assertEquals('admin', $adminFresh->role);
    }

    /**
     * Requirement: Linking to existing practitioner does not alter counsellor_type or tc_id,
     * copies only selected fields, and rejects correctly archives application.
     */
    public function test_Q01_admin_link_selective_copy_and_reject()
    {
        Mail::fake();

        $admin = User::factory()->create(['role' => 'admin']);

        $existingTc = TrainingCounsellor::create([
            'tc_id' => 'TC010',
            'name' => 'Existing Trainee',
            'legal_first_name' => 'Existing',
            'legal_last_name' => 'Trainee',
            'email' => 'trainee.link@example.com',
            'phone' => '07000000000',
            'registered_address' => 'Old Address',
            'registered_city' => 'Old City',
            'registered_postcode' => 'OLD 1AA',
            'counsellor_type' => 'Trainee',
            'status' => 'Active',
            'signature' => 'Existing Trainee',
            'signature_date' => '2025-01-01',
        ]);

        // Submit QC application with new phone and address
        $res = $this->postJson('/api/qualified-counsellor/submit', [
            'legal_first_name' => 'Existing',
            'legal_last_name' => 'Trainee',
            'email' => 'trainee.link@example.com',
            'phone' => '07999888777',
            'registered_address' => 'New Address',
            'registered_city' => 'New City',
            'registered_postcode' => 'NEW 2BB',
            'signature' => 'Existing Trainee',
            'signature_date' => '2026-10-01',
        ]);
        $res->assertStatus(200);
        $appId = $res->json('application_id');

        // Admin links application, copying ONLY phone (not registered address)
        $linkRes = $this->actingAs($admin)->postJson("/api/qc-applications/{$appId}/link", [
            'training_counsellor_id' => $existingTc->id,
            'copy_fields' => ['phone'],
        ]);
        $linkRes->assertStatus(200);

        $existingFresh = $existingTc->fresh();
        // counsellor_type and tc_id must NOT be altered on link
        $this->assertEquals('Trainee', $existingFresh->counsellor_type);
        $this->assertEquals('TC010', $existingFresh->tc_id);
        // Only phone was copied
        $this->assertEquals('07999888777', $existingFresh->phone);
        // Address was NOT changed
        $this->assertEquals('Old Address', $existingFresh->registered_address);

        $appFresh = QcApplication::find($appId);
        $this->assertEquals('Linked', $appFresh->status);
        $this->assertEquals($existingTc->id, $appFresh->training_counsellor_id);

        // Test reject endpoint on another application
        $res2 = $this->postJson('/api/qualified-counsellor/submit', [
            'legal_first_name' => 'Reject',
            'legal_last_name' => 'Me',
            'email' => 'reject.me@example.com',
            'phone' => '07111111111',
            'registered_address' => 'No where',
            'registered_city' => 'London',
            'registered_postcode' => 'N1 1AA',
            'signature' => 'Reject Me',
            'signature_date' => '2026-10-01',
        ]);
        $app2Id = $res2->json('application_id');

        $rejectRes = $this->actingAs($admin)->postJson("/api/qc-applications/{$app2Id}/reject", [
            'notes' => 'Did not meet requirements',
        ]);
        $rejectRes->assertStatus(200);

        $rejectedApp = QcApplication::find($app2Id);
        $this->assertEquals('Rejected', $rejectedApp->status);
        $this->assertNotNull($rejectedApp->archived_at);
        $this->assertEquals('Did not meet requirements', $rejectedApp->notes);
    }

    /**
     * Follow-up 1: Accept must refuse an application whose status is Rejected or has archived_at set.
     * Returns 422: "This application was rejected. Restore it before accepting."
     */
    public function test_Q01_cannot_accept_rejected()
    {
        Mail::fake();
        $admin = User::factory()->create(['role' => 'admin', 'email' => 'admin_reject_check@example.com']);

        $res = $this->postJson('/api/qualified-counsellor/submit', [
            'legal_first_name' => 'Rejected',
            'legal_last_name' => 'Candidate',
            'email' => 'rejected.candidate@example.com',
            'phone' => '07111222333',
            'registered_address' => '1 Rejected Lane',
            'registered_city' => 'Bristol',
            'registered_postcode' => 'BS1 1AA',
            'signature' => 'Rejected Candidate',
            'signature_date' => '2026-10-01',
        ]);
        $appId = $res->json('application_id');

        // Admin rejects the application
        $this->actingAs($admin)->postJson("/api/qc-applications/{$appId}/reject", [
            'reason' => 'Qualifications did not meet criteria',
        ])->assertStatus(200);

        // Attempting to accept a rejected application must return 422
        $acceptRes = $this->actingAs($admin)->postJson("/api/qc-applications/{$appId}/accept");
        $acceptRes->assertStatus(422);
        $acceptRes->assertJson([
            'message' => 'This application was rejected. Restore it before accepting.',
        ]);

        // Restore the application and verify accept now succeeds
        $restoreRes = $this->actingAs($admin)->postJson("/api/qc-applications/{$appId}/restore");
        $restoreRes->assertStatus(200);
        $this->assertEquals('Submitted', QcApplication::find($appId)->status);

        $acceptAfterRestore = $this->actingAs($admin)->postJson("/api/qc-applications/{$appId}/accept");
        $acceptAfterRestore->assertStatus(200);
        $this->assertEquals('Accepted', QcApplication::find($appId)->status);
    }

    /**
     * Follow-up 2: If an active practitioner already has the application's email (or there is a suggested match),
     * Accept must stop and return 409:
     * "A practitioner with this email already exists (<name>, <tc_id>). Use Link to existing practitioner instead."
     * Admin can override only with an explicit force_new=true, logged in the activity log.
     */
    public function test_Q01_accept_blocks_duplicate_email()
    {
        Mail::fake();
        $admin = User::factory()->create(['role' => 'admin', 'email' => 'admin_dup_check@example.com']);

        // 1. Existing active practitioner exists with dr.jones@example.com
        $existingTc = TrainingCounsellor::create([
            'tc_id' => 'TC042',
            'name' => 'Dr Indiana Jones',
            'legal_first_name' => 'Indiana',
            'legal_last_name' => 'Jones',
            'email' => 'dr.jones@example.com',
            'phone' => '07000111222',
            'registered_address' => 'University of London',
            'registered_city' => 'London',
            'registered_postcode' => 'WC1E 7HU',
            'counsellor_type' => 'Trainee',
            'status' => 'Active',
        ]);

        // 2. Submit QC application with same email
        $res = $this->postJson('/api/qualified-counsellor/submit', [
            'legal_first_name' => 'Indiana',
            'legal_last_name' => 'Jones',
            'email' => 'DR.JONES@EXAMPLE.COM ',
            'phone' => '07999888777',
            'registered_address' => 'Archeology Dept',
            'registered_city' => 'London',
            'registered_postcode' => 'WC1E 7HU',
            'signature' => 'Indiana Jones',
            'signature_date' => '2026-10-01',
        ]);
        $appId = $res->json('application_id');
        $this->assertNotNull($appId);

        // 3. Accept without force_new must return 409 with exact message
        $acceptRes = $this->actingAs($admin)->postJson("/api/qc-applications/{$appId}/accept");
        $acceptRes->assertStatus(409);
        $acceptRes->assertJson([
            'message' => 'A practitioner with this email already exists (Dr Indiana Jones, TC042). Use Link to existing practitioner instead.',
        ]);

        // Verify no second practitioner was created yet
        $this->assertEquals(1, TrainingCounsellor::where('email', 'dr.jones@example.com')->count());

        // 4. Accept WITH force_new=true succeeds and logs in ActivityLog
        $forceRes = $this->actingAs($admin)->postJson("/api/qc-applications/{$appId}/accept", [
            'force_new' => true,
        ]);
        $forceRes->assertStatus(200);

        // Verify two practitioners now exist
        $this->assertEquals(2, TrainingCounsellor::where('email', 'dr.jones@example.com')->count());

        // Verify ActivityLog recorded the force_new override
        $log = ActivityLog::where('model_id', $appId)
            ->where('action', 'qc_application_accept_forced_new')
            ->first();
        $this->assertNotNull($log, 'ActivityLog should record force_new override');
        $this->assertStringContainsString('force_new=true', $log->description);
    }

    /**
     * Follow-up 3: In the qualified_counsellor_submission email, always send the
     * application reference (QC-APP-0001 style), never the suggested practitioner's tc_id.
     */
    public function test_Q01_email_shows_application_reference()
    {
        Mail::fake();

        // Create an existing practitioner who sends a prefill link
        $existingTc = TrainingCounsellor::create([
            'tc_id' => 'TC099',
            'name' => 'Senior Trainee',
            'email' => 'senior.trainee@example.com',
            'counsellor_type' => 'Trainee',
            'status' => 'Active',
        ]);

        // Submit form using tc_id param from prefill link
        $res = $this->postJson('/api/qualified-counsellor/submit', [
            'tc_id' => $existingTc->tc_id,
            'legal_first_name' => 'Senior',
            'legal_last_name' => 'Trainee',
            'email' => 'senior.trainee@example.com',
            'phone' => '07123456789',
            'registered_address' => '10 High St',
            'registered_city' => 'Manchester',
            'registered_postcode' => 'M1 1AA',
            'signature' => 'Senior Trainee',
            'signature_date' => '2026-10-01',
        ]);
        $res->assertStatus(200);
        $appId = $res->json('application_id');
        $this->assertNotNull($appId);

        // Expected format: QC-APP-0001 style
        $expectedRef = 'QC-APP-' . str_pad($appId, 4, '0', STR_PAD_LEFT);

        // Check the EmailLog
        $emailLog = EmailLog::where('email', 'senior.trainee@example.com')
            ->where('template_name', 'qualified_counsellor_submission')
            ->latest('id')
            ->first();

        $this->assertNotNull($emailLog, 'EmailLog must be present');
        $payload = is_array($emailLog->payload) ? $emailLog->payload : json_decode($emailLog->payload, true);

        // Must be the QC-APP-0001 style, NEVER TC099
        $this->assertEquals($expectedRef, $payload['tc_id']);
        $this->assertEquals($expectedRef, $payload['application_reference']);
        $this->assertNotEquals('TC099', $payload['tc_id']);
    }
}

