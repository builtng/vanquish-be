<?php

namespace Tests\Feature;

use App\Mail\DynamicEmail;
use App\Models\Person;
use App\Models\QcApplication;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class Q19QcPipelineInterviewInductionTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test Q19: Send interview link for qualified counsellor application.
     * Reuses the trainee pipeline action, stages, and emails.
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

        // Verify the trainee pipeline email template 'trainee_stage_three_invite' was reused
        Mail::assertSent(DynamicEmail::class, function ($mail) use ($qcApp) {
            return $mail->hasTo($qcApp->email) &&
                   $mail->template->type === 'trainee_stage_three_invite' &&
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
            return $mail->template->type === 'trainee_interview_not_attended';
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

        // Verify trainee placement acceptance (induction invite) and paperwork emails were sent
        Mail::assertSent(DynamicEmail::class, function ($mail) use ($qcApp) {
            return $mail->hasTo($qcApp->email) &&
                   $mail->template->type === 'trainee_placement_acceptance' &&
                   $mail->data['first_name'] === 'Phoebe' &&
                   $mail->data['induction_date'] === 'Monday, 23rd March, 10:00am';
        });

        Mail::assertSent(DynamicEmail::class, function ($mail) use ($qcApp) {
            return $mail->hasTo($qcApp->email) &&
                   $mail->template->type === 'trainee_onboarding_paperwork';
        });

        // Test recording induction attendance triggers trainee_induction_completed
        $this->actingAs($admin)->postJson("/api/qc-applications/{$qcApp->id}/induction-attendance", [
            'attended' => true,
            'notes'    => 'Attended fully',
        ])->assertStatus(200);

        $this->assertEquals('Induction Attended', $qcApp->fresh()->status);
        Mail::assertSent(DynamicEmail::class, function ($mail) use ($qcApp) {
            return $mail->hasTo($qcApp->email) &&
                   $mail->template->type === 'trainee_induction_completed';
        });

        // Test induction no-show triggers trainee_induction_not_attended
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
                   $mail->template->type === 'trainee_induction_not_attended';
        });
    }
}
