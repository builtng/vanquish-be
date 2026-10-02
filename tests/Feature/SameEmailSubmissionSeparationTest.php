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
}
