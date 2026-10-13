<?php

namespace Tests\Feature;

use App\Jobs\SendInterviewReminder;
use App\Mail\DynamicEmail;
use App\Models\EmailTemplate;
use App\Models\Person;
use App\Models\QcApplication;
use App\Models\TraineeApplication;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class Q19QcPipelineInterviewInductionTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test Q19: Send interview link for qualified counsellor application.
     * Reuses the pipeline stages and sends qc_stage_three_invite.
     */
    public function test_Q19_qc_interview_link_sent(): void
    {
        Mail::fake();

        $admin = User::factory()->create(['role' => 'admin']);

        $qcApp = QcApplication::create([
            'legal_first_name' => 'Rachel',
            'legal_last_name'  => 'Green',
            'name'             => 'Rachel Green',
            'email'            => 'rachel.green@example.com',
            'phone'            => '07123456789',
            'status'           => 'Submitted',
        ]);

        $response = $this->actingAs($admin)
            ->postJson("/api/qc-applications/{$qcApp->id}/send-interview-link");

        $response->assertStatus(200);
        $response->assertJson([
            'message' => 'Interview link sent successfully',
            'status'  => 'Stage 3 Interview Booked',
        ]);

        $freshApp = $qcApp->fresh();
        $this->assertEquals('Stage 3 Interview Booked', $freshApp->status);
        $this->assertNotEmpty($freshApp->interview_data);
        $this->assertNotEmpty($freshApp->interview_data['trafft']['zoom_link']);

        // Verify the QC pipeline email template 'qc_stage_three_invite' was sent
        Mail::assertSent(DynamicEmail::class, function ($mail) use ($qcApp) {
            return $mail->hasTo($qcApp->email) &&
                   $mail->template->type === 'qc_stage_three_invite' &&
                   $mail->data['first_name'] === 'Rachel';
        });

        // Also test route alias /api/qc-applications/{id}/invite-stage-three
        $qcApp2 = QcApplication::create([
            'legal_first_name' => 'Ross',
            'legal_last_name'  => 'Geller',
            'name'             => 'Ross Geller',
            'email'            => 'ross.geller@example.com',
            'status'           => 'Submitted',
        ]);

        $res2 = $this->actingAs($admin)
            ->postJson("/api/qc-applications/{$qcApp2->id}/invite-stage-three");

        $res2->assertStatus(200);
        $this->assertEquals('Stage 3 Interview Booked', $qcApp2->fresh()->status);
    }

    /**
     * Test Q19: Two applications for one person move through stages independently.
     */
    public function test_Q19_two_applications_independent(): void
    {
        Mail::fake();

        $admin = User::factory()->create(['role' => 'admin']);

        $person = Person::findOrCreateByEmail('monica.geller@example.com', 'Monica Geller', '07999888777');

        // Application 1
        $app1 = QcApplication::create([
            'person_id'        => $person->id,
            'legal_first_name' => 'Monica',
            'legal_last_name'  => 'Geller',
            'name'             => 'Monica Geller',
            'email'            => 'monica.geller@example.com',
            'status'           => 'Submitted',
        ]);

        // Application 2 (re-application or secondary submission for the same person)
        $app2 = QcApplication::create([
            'person_id'        => $person->id,
            'legal_first_name' => 'Monica',
            'legal_last_name'  => 'Geller-Bing',
            'name'             => 'Monica Geller-Bing',
            'email'            => 'monica.geller@example.com',
            'status'           => 'Submitted',
        ]);

        $this->assertEquals($app1->person_id, $app2->person_id);
        $this->assertNotEquals($app1->id, $app2->id);

        // 1. Advance App 1: Send interview link
        $this->actingAs($admin)->postJson("/api/qc-applications/{$app1->id}/send-interview-link")->assertStatus(200);

        $this->assertEquals('Stage 3 Interview Booked', $app1->fresh()->status);
        $this->assertEquals('Submitted', $app2->fresh()->status); // App 2 remains unchanged

        // 2. Advance App 1: Mark Interview Attended
        $this->actingAs($admin)->postJson("/api/qc-applications/{$app1->id}/attendance", [
            'attended' => true,
            'notes'    => 'Strong candidate',
        ])->assertStatus(200);

        $this->assertEquals('Interview Attended', $app1->fresh()->status);
        $this->assertEquals('Submitted', $app2->fresh()->status);

        // 3. Update App 2: Mark Interview No Show (independent progression)
        $this->actingAs($admin)->postJson("/api/qc-applications/{$app2->id}/attendance", [
            'attended' => false,
            'notes'    => 'Candidate missed call',
        ])->assertStatus(200);

        $this->assertEquals('Interview Attended', $app1->fresh()->status);
        $this->assertEquals('Interview No Show', $app2->fresh()->status);

        // App 2 triggered interview not attended email
        Mail::assertSent(DynamicEmail::class, function ($mail) {
            return $mail->template->type === 'qc_interview_not_attended';
        });

        // 4. Advance App 1: Accept & Invite to Induction
        $this->actingAs($admin)->postJson("/api/qc-applications/{$app1->id}/decision", [
            'decision'       => 'Accepted',
            'induction_date' => 'Monday, 19th January, 10:00am',
            'notes'          => 'Placement accepted',
        ])->assertStatus(200);

        $this->assertEquals('Accepted', $app1->fresh()->status);
        $this->assertEquals('Monday, 19th January, 10:00am', $app1->fresh()->induction_date);
        $this->assertNotEmpty($app1->fresh()->placement_response_token);

        // App 2 remains completely independent
        $this->assertEquals('Interview No Show', $app2->fresh()->status);
        $this->assertNull($app2->fresh()->induction_date);
        $this->assertNull($app2->fresh()->placement_response_token);

        // 5. Advance App 1: Mark Induction Attended
        $this->actingAs($admin)->postJson("/api/qc-applications/{$app1->id}/induction-attendance", [
            'attended' => true,
            'notes'    => 'Completed induction session',
        ])->assertStatus(200);

        $this->assertEquals('Induction Attended', $app1->fresh()->status);
        $this->assertEquals('Interview No Show', $app2->fresh()->status);

        // Confirm previous submissions endpoint lists the sibling application
        $showRes = $this->actingAs($admin)->getJson("/api/qc-applications/{$app1->id}");
        $showRes->assertStatus(200);
        $prevSubmissions = $showRes->json('previous_submissions');
        $this->assertCount(1, $prevSubmissions);
        $this->assertEquals($app2->id, $prevSubmissions[0]['id']);
    }

    /**
     * Test Q19: Send induction invite for qualified counsellor application.
     * Reuses the trainee pipeline induction invite emails.
     */
    public function test_Q19_qc_induction_invite(): void
    {
        Mail::fake();

        $admin = User::factory()->create(['role' => 'admin']);

        $qcApp = QcApplication::create([
            'legal_first_name' => 'Phoebe',
            'legal_last_name'  => 'Buffay',
            'name'             => 'Phoebe Buffay',
            'email'            => 'phoebe.buffay@example.com',
            'status'           => 'Interview Attended',
        ]);

        $response = $this->actingAs($admin)->postJson("/api/qc-applications/{$qcApp->id}/induction-invite", [
            'induction_date' => 'Monday, 23rd March, 10:00am',
        ]);

        $response->assertStatus(200);

        $fresh = $qcApp->fresh();
        $this->assertEquals('Accepted', $fresh->status);
        $this->assertEquals('Monday, 23rd March, 10:00am', $fresh->induction_date);
        $this->assertNotEmpty($fresh->placement_response_token);

        // Verify QC placement acceptance (induction invite) and paperwork emails were sent
        Mail::assertSent(DynamicEmail::class, function ($mail) use ($qcApp) {
            return $mail->hasTo($qcApp->email) &&
                   $mail->template->type === 'qc_placement_acceptance' &&
                   $mail->data['first_name'] === 'Phoebe' &&
                   $mail->data['induction_date'] === 'Monday, 23rd March, 10:00am';
        });

        Mail::assertSent(DynamicEmail::class, function ($mail) use ($qcApp) {
            return $mail->hasTo($qcApp->email) &&
                   $mail->template->type === 'qc_onboarding_paperwork';
        });

        // Test recording induction attendance triggers qc_induction_completed
        $this->actingAs($admin)->postJson("/api/qc-applications/{$qcApp->id}/induction-attendance", [
            'attended' => true,
            'notes'    => 'Attended fully',
        ])->assertStatus(200);

        $this->assertEquals('Induction Attended', $qcApp->fresh()->status);
        Mail::assertSent(DynamicEmail::class, function ($mail) use ($qcApp) {
            return $mail->hasTo($qcApp->email) &&
                   $mail->template->type === 'qc_induction_completed';
        });

        // Test induction no-show triggers qc_induction_not_attended
        $qcAppNoShow = QcApplication::create([
            'legal_first_name' => 'Joey',
            'legal_last_name'  => 'Tribbiani',
            'name'             => 'Joey Tribbiani',
            'email'            => 'joey.tribbiani@example.com',
            'status'           => 'Accepted',
        ]);

        $this->actingAs($admin)->postJson("/api/qc-applications/{$qcAppNoShow->id}/induction-attendance", [
            'attended' => false,
            'notes'    => 'Did not attend induction',
        ])->assertStatus(200);

        $this->assertEquals('Induction No-Show', $qcAppNoShow->fresh()->status);
        Mail::assertSent(DynamicEmail::class, function ($mail) use ($qcAppNoShow) {
            return $mail->hasTo($qcAppNoShow->email) &&
                   $mail->template->type === 'qc_induction_not_attended';
        });
    }

    /**
     * Test Q19: QC interview attendance no-show triggers qc_interview_not_attended.
     */
    public function test_Q19_qc_interview_no_show_sends_qc_template(): void
    {
        Mail::fake();

        $admin = User::factory()->create(['role' => 'admin']);

        $qcApp = QcApplication::create([
            'legal_first_name' => 'Chandler',
            'legal_last_name'  => 'Bing',
            'name'             => 'Chandler Bing',
            'email'            => 'chandler.bing@example.com',
            'status'           => 'Stage 3 Interview Booked',
        ]);

        $res = $this->actingAs($admin)->postJson("/api/qc-applications/{$qcApp->id}/attendance", [
            'attended' => false,
            'notes'    => 'Did not show up for Zoom interview',
        ]);

        $res->assertStatus(200);
        $this->assertEquals('Interview No Show', $qcApp->fresh()->status);

        Mail::assertSent(DynamicEmail::class, function ($mail) use ($qcApp) {
            return $mail->hasTo($qcApp->email) &&
                   $mail->template->type === 'qc_interview_not_attended';
        });
    }

    /**
     * Test Q19: QC rejection triggers qc_placement_rejection.
     */
    public function test_Q19_qc_rejection_sends_qc_template(): void
    {
        Mail::fake();

        $admin = User::factory()->create(['role' => 'admin']);

        $qcApp = QcApplication::create([
            'legal_first_name' => 'Gunther',
            'legal_last_name'  => 'Central',
            'name'             => 'Gunther Central',
            'email'            => 'gunther@example.com',
            'status'           => 'Interview Attended',
        ]);

        $res = $this->actingAs($admin)->postJson("/api/qc-applications/{$qcApp->id}/decision", [
            'decision' => 'Rejected',
            'notes'    => 'Not suitable at this time',
        ]);

        $res->assertStatus(200);
        $this->assertEquals('Rejected', $qcApp->fresh()->status);

        Mail::assertSent(DynamicEmail::class, function ($mail) use ($qcApp) {
            return $mail->hasTo($qcApp->email) &&
                   $mail->template->type === 'qc_placement_rejection';
        });
    }

    /**
     * Test Q19: Editing a qc_* template does NOT affect trainee templates or emails.
     */
    public function test_Q19_editing_qc_template_does_not_change_trainee_email(): void
    {
        Mail::fake();

        $admin = User::factory()->create(['role' => 'admin']);

        // 1. Ensure templates exist in DB
        $qcTemplate = EmailTemplate::firstOrCreate(
            ['type' => 'qc_stage_three_invite'],
            [
                'subject' => 'Original QC Subject',
                'body'    => '<p>Original QC Body for {{first_name}}</p>',
                'placeholders' => ['first_name'],
            ]
        );

        $traineeTemplate = EmailTemplate::firstOrCreate(
            ['type' => 'trainee_stage_three_invite'],
            [
                'subject' => 'Original Trainee Subject',
                'body'    => '<p>Original Trainee Body for {{first_name}}</p>',
                'placeholders' => ['first_name'],
            ]
        );

        // 2. Edit the qc_* template via API
        $editResponse = $this->actingAs($admin)->putJson("/api/email-templates/qc_stage_three_invite", [
            'subject' => 'CUSTOM QC SUBJECT: Welcome Qualified Counsellor',
            'body'    => '<p>Custom QC body for {{first_name}}</p>',
        ]);
        $editResponse->assertStatus(200);

        // 3. Confirm trainee template is untouched
        $traineeTemplate->refresh();
        $this->assertEquals('Original Trainee Subject', $traineeTemplate->subject);
        $this->assertEquals('<p>Original Trainee Body for {{first_name}}</p>', $traineeTemplate->body);

        // 4. Send interview link to QC application
        $qcApp = QcApplication::create([
            'legal_first_name' => 'Dr. Richard',
            'legal_last_name'  => 'Burke',
            'name'             => 'Dr. Richard Burke',
            'email'            => 'richard.burke@example.com',
            'status'           => 'Submitted',
        ]);

        $this->actingAs($admin)->postJson("/api/qc-applications/{$qcApp->id}/send-interview-link")
            ->assertStatus(200);

        // Verify QC applicant got the custom QC template
        Mail::assertSent(DynamicEmail::class, function ($mail) use ($qcApp) {
            return $mail->hasTo($qcApp->email) &&
                   $mail->template->type === 'qc_stage_three_invite' &&
                   $mail->template->subject === 'CUSTOM QC SUBJECT: Welcome Qualified Counsellor';
        });

        // 5. Send interview link to Trainee application
        $traineeApp = TraineeApplication::create([
            'first_name' => 'Emma',
            'last_name'  => 'Geller-Green',
            'email'      => 'emma@example.com',
            'status'     => 'Submitted',
        ]);

        $pipelineService = app(\App\Services\ApplicationPipelineService::class);
        $pipelineService->sendInterviewLink($traineeApp, $admin);

        // Verify Trainee applicant got the unedited trainee template
        Mail::assertSent(DynamicEmail::class, function ($mail) use ($traineeApp) {
            return $mail->hasTo($traineeApp->email) &&
                   $mail->template->type === 'trainee_stage_three_invite' &&
                   $mail->template->subject === 'Original Trainee Subject';
        });
    }

    /**
     * Test Q19: Trainee journey continues sending trainee_* templates exactly as before.
     */
    public function test_Q19_trainee_journey_sends_same_emails_as_before(): void
    {
        Mail::fake();

        $admin = User::factory()->create(['role' => 'admin']);
        $pipelineService = app(\App\Services\ApplicationPipelineService::class);

        $traineeApp = TraineeApplication::create([
            'first_name' => 'Ben',
            'last_name'  => 'Geller',
            'email'      => 'ben.geller@example.com',
            'status'     => 'Submitted',
        ]);

        // 1. Send interview link
        $pipelineService->sendInterviewLink($traineeApp, $admin);
        Mail::assertSent(DynamicEmail::class, fn($m) => $m->template->type === 'trainee_stage_three_invite');

        // 2. Attendance no-show
        $pipelineService->recordAttendance($traineeApp, false, 'Did not attend', null, $admin);
        Mail::assertSent(DynamicEmail::class, fn($m) => $m->template->type === 'trainee_interview_not_attended');

        // 3. Make decision accepted
        $pipelineService->makeDecision($traineeApp, 'Accepted', 'Monday 20th', null, $admin);
        Mail::assertSent(DynamicEmail::class, fn($m) => $m->template->type === 'trainee_placement_acceptance');
        Mail::assertSent(DynamicEmail::class, fn($m) => $m->template->type === 'trainee_onboarding_paperwork');

        // 4. Record induction attended
        $pipelineService->recordInductionAttendance($traineeApp, true, 'Great job', $admin);
        Mail::assertSent(DynamicEmail::class, fn($m) => $m->template->type === 'trainee_induction_completed');

        // 5. Test trainee rejection
        $traineeApp2 = TraineeApplication::create([
            'first_name' => 'Carol',
            'last_name'  => 'Willick',
            'email'      => 'carol@example.com',
            'status'     => 'Interview Attended',
        ]);
        $pipelineService->makeDecision($traineeApp2, 'Rejected', null, 'Not right fit', $admin);
        Mail::assertSent(DynamicEmail::class, fn($m) => $m->template->type === 'trainee_placement_rejection');
    }

    /**
     * Test Q19: Trafft booking and interview reminder send qc_* templates for QC applications.
     */
    public function test_Q19_qc_trafft_booking_and_reminder_send_qc_templates(): void
    {
        Mail::fake();

        $qcApp = QcApplication::create([
            'legal_first_name' => 'Janice',
            'legal_last_name'  => 'Hosenstein',
            'name'             => 'Janice Hosenstein',
            'email'            => 'janice@example.com',
            'status'           => 'Submitted',
        ]);

        // Simulate Trafft booking webhook for QC application
        $payload = [
            'event'    => 'appointment.created',
            'customer' => ['email' => 'janice@example.com'],
            'booking'  => [
                'id'         => 'TRAFFT-QC-999',
                'start'      => '2026-11-01 10:00:00',
                'zoom_link'  => 'https://zoom.us/j/qc123',
                'meeting_id' => '123456789',
            ],
            'employee' => ['name' => 'Interviewer Admin'],
            'service'  => ['name' => 'QC Interview'],
        ];

        $res = $this->postJson('/api/trafft/webhook', $payload);
        $res->assertStatus(200);

        Mail::assertSent(DynamicEmail::class, function ($mail) use ($qcApp) {
            return $mail->hasTo($qcApp->email) &&
                   $mail->template->type === 'qc_interview_confirmed';
        });

        // Test SendInterviewReminder job sends qc_interview_reminder
        SendInterviewReminder::dispatchSync($qcApp->fresh());

        Mail::assertSent(DynamicEmail::class, function ($mail) use ($qcApp) {
            return $mail->hasTo($qcApp->email) &&
                   $mail->template->type === 'qc_interview_reminder';
        });
    }

    /**
     * Test Q19: Email templates API returns clear human-readable names and paired ordering.
     */
    public function test_Q19_email_templates_api_ordering_and_names(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $res = $this->actingAs($admin)->getJson('/api/email-templates');
        $res->assertStatus(200);

        $templates = $res->json();
        $this->assertIsArray($templates);

        // Find trainee and qc invite templates
        $traineeInvite = collect($templates)->firstWhere('type', 'trainee_stage_three_invite');
        $qcInvite = collect($templates)->firstWhere('type', 'qc_stage_three_invite');

        $this->assertNotNull($traineeInvite);
        $this->assertNotNull($qcInvite);

        // Check human-readable names
        $this->assertEquals('Trainee: Interview Invitation', $traineeInvite['name']);
        $this->assertEquals('Qualified Counsellor: Interview Invitation', $qcInvite['name']);

        // Check adjacent ordering in the list
        $traineeIndex = array_search('trainee_stage_three_invite', array_column($templates, 'type'));
        $qcIndex = array_search('qc_stage_three_invite', array_column($templates, 'type'));

        $this->assertEquals($traineeIndex + 1, $qcIndex, 'QC template should be positioned next to Trainee template');
    }
}
