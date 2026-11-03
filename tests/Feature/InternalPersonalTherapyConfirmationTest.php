<?php

namespace Tests\Feature;

use App\Http\Controllers\Api\TraineeApplicationController;
use App\Models\ActivityLog;
use App\Models\PersonalTherapyToken;
use App\Models\TraineeApplication;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InternalPersonalTherapyConfirmationTest extends TestCase
{
    use RefreshDatabase;

    private function createTrainee(array $attributes = []): TraineeApplication
    {
        return TraineeApplication::create(array_merge([
            'first_name' => 'Alice',
            'last_name' => 'Smith',
            'email' => 'alice.smith@example.com',
            'phone' => '07123456789',
            'status' => 'Accepted',
            'onboarding_data' => [
                'checklist' => [
                    'four_way_agreement' => false,
                    'therapy_form' => false,
                ],
            ],
        ], $attributes));
    }

    private function validTherapistPayload(string $token, array $overrides = []): array
    {
        return array_merge([
            'token' => $token,
            'trainee_name' => 'Alice Smith',
            'therapist_name' => 'Dr. Marcus Vance',
            'practice_name_website' => 'Vance Therapy, vancetherapy.co.uk',
            'therapist_email' => 'marcus.vance@therapy.co.uk',
            'therapist_phone' => '07999888777',
            'professional_membership' => 'BACP 998811',
            'qualifications_modality' => 'MSc Integrative Psychotherapy',
            'first_session_date' => '01-10-2025',
            'sessions_to_date' => '24',
            'sessions_during_course' => 'Yes',
            'therapy_hours_completed' => 'On-going',
            'suitability_concerns' => TraineeApplicationController::THERAPY_FORM_SUITABILITY[0],
            'disclaimer' => TraineeApplicationController::THERAPY_FORM_DISCLAIMERS[0],
            'signature' => 'Marcus Vance',
            'signing_date' => '03-11-2026',
        ], $overrides);
    }

    /**
     * Requirement: The open /therapy-form link (no token) needs the trainee email.
     */
    public function test_no_token_without_trainee_email_returns_422(): void
    {
        $this->createTrainee();
        $payload = $this->validTherapistPayload('');
        unset($payload['token']);

        $response = $this->postJson('/api/trainee-applications/confirm-personal-therapy', $payload);

        $response->assertStatus(422)->assertJsonValidationErrors(['trainee_email']);
    }

    /**
     * Requirement: The open /therapy-form link (no token) matches the trainee by email,
     * the same way the old JotForm webhook did, and ticks the checklist.
     */
    public function test_no_token_matches_trainee_by_email_and_ticks_therapy_form(): void
    {
        $alice = $this->createTrainee(['email' => 'Alice.Smith@example.com']);
        $bob = $this->createTrainee(['email' => 'bob@example.com']);

        $payload = $this->validTherapistPayload('', [
            'trainee_name' => 'Alice Smith',
            'trainee_email' => 'alice.smith@EXAMPLE.com',
        ]);
        unset($payload['token']);

        $response = $this->postJson('/api/therapy-form/confirm', $payload);
        $response->assertStatus(200)->assertExactJson(['success' => true]);

        $alice->refresh();
        $bob->refresh();

        $this->assertTrue($alice->onboarding_data['checklist']['therapy_form']);
        $this->assertSame('internal_form_open_link', $alice->onboarding_data['checklist_meta']['therapy_form']['source']);
        $this->assertFalse($alice->onboarding_data['checklist_meta']['therapy_form']['verified_by_token']);
        $this->assertFalse($bob->onboarding_data['checklist']['therapy_form']);
    }

    /**
     * Requirement: The open link rejects an email that has no trainee application.
     */
    public function test_no_token_with_unknown_trainee_email_returns_422(): void
    {
        $this->createTrainee();
        $payload = $this->validTherapistPayload('', ['trainee_email' => 'nobody@example.com']);
        unset($payload['token']);

        $response = $this->postJson('/api/trainee-applications/confirm-personal-therapy', $payload);

        $response->assertStatus(422)->assertJsonValidationErrors(['trainee_email']);
    }

    /**
     * Requirement: Answers must use the exact JotForm wording for the multiple choice questions.
     */
    public function test_answers_outside_the_jotform_options_are_rejected(): void
    {
        $trainee = $this->createTrainee();
        $token = PersonalTherapyToken::createFor($trainee);

        $payload = $this->validTherapistPayload($token->token, [
            'therapy_hours_completed' => 'Maybe',
            'disclaimer' => 'I confirm something else',
        ]);

        $response = $this->postJson('/api/therapy-form/confirm', $payload);

        $response->assertStatus(422)->assertJsonValidationErrors(['therapy_hours_completed', 'disclaimer']);
        $this->assertNull($token->fresh()->used_at);
    }

    /**
     * Requirement: Choosing "Yes, I have concerns" needs details, and the concerns are recorded for staff.
     */
    public function test_concerns_require_details_and_are_recorded(): void
    {
        $trainee = $this->createTrainee();
        $token = PersonalTherapyToken::createFor($trainee);

        $payload = $this->validTherapistPayload($token->token, [
            'suitability_concerns' => TraineeApplicationController::THERAPY_FORM_SUITABILITY[1],
        ]);

        $this->postJson('/api/therapy-form/confirm', $payload)
            ->assertStatus(422)
            ->assertJsonValidationErrors(['concerns_details']);

        $payload['concerns_details'] = 'Struggling with boundaries in recent sessions.';
        $this->postJson('/api/therapy-form/confirm', $payload)
            ->assertStatus(200);

        $meta = $trainee->fresh()->onboarding_data['checklist_meta']['therapy_form'];
        $this->assertTrue($meta['concerns_raised']);
        $this->assertSame('Struggling with boundaries in recent sessions.', $meta['concerns_details']);
        $this->assertSame('BACP 998811', $meta['professional_membership']);
    }

    /**
     * Requirement: Wrong/invalid token in POST returns 422 with exact invalid message.
     */
    public function test_wrong_token_returns_422(): void
    {
        $payload = $this->validTherapistPayload('completely-invalid-nonexistent-token');

        $response = $this->postJson('/api/trainee-applications/confirm-personal-therapy', $payload);

        $response->assertStatus(422)
            ->assertJson([
                'message' => 'This link is no longer valid. Please contact help@vanquishtherapies.co.uk.',
            ]);
    }

    /**
     * Requirement: Expired token in POST returns 422 with exact invalid message.
     */
    public function test_expired_token_returns_422(): void
    {
        $trainee = $this->createTrainee();
        $token = PersonalTherapyToken::create([
            'trainee_application_id' => $trainee->id,
            'token' => 'expired-token-12345',
            'expires_at' => now()->subDay(),
            'used_at' => null,
        ]);

        $payload = $this->validTherapistPayload($token->token);

        $response = $this->postJson('/api/trainee-applications/confirm-personal-therapy', $payload);

        $response->assertStatus(422)
            ->assertJson([
                'message' => 'This link is no longer valid. Please contact help@vanquishtherapies.co.uk.',
            ]);
    }

    /**
     * Requirement: Used token in POST returns 422 with exact invalid message.
     */
    public function test_used_token_returns_422(): void
    {
        $trainee = $this->createTrainee();
        $token = PersonalTherapyToken::create([
            'trainee_application_id' => $trainee->id,
            'token' => 'already-used-token-12345',
            'expires_at' => now()->addDays(20),
            'used_at' => now()->subHours(2),
        ]);

        $payload = $this->validTherapistPayload($token->token);

        $response = $this->postJson('/api/trainee-applications/confirm-personal-therapy', $payload);

        $response->assertStatus(422)
            ->assertJson([
                'message' => 'This link is no longer valid. Please contact help@vanquishtherapies.co.uk.',
            ]);
    }

    /**
     * Requirement: Valid token ticks therapy_form, logs activity, marks token used,
     * and returns ONLY {"success": true} with no trainee name.
     */
    public function test_valid_token_ticks_therapy_form_and_logs_activity_and_response_contains_no_trainee_name(): void
    {
        $trainee = $this->createTrainee();
        $token = PersonalTherapyToken::createFor($trainee);

        $payload = $this->validTherapistPayload($token->token);

        $response = $this->postJson('/api/trainee-applications/confirm-personal-therapy', $payload);

        $response->assertStatus(200);
        $json = $response->json();

        // 1. Response must not include trainee name or matched application
        $this->assertEquals(['success' => true], $json);
        $this->assertArrayNotHasKey('trainee_name', $json);
        $this->assertArrayNotHasKey('matched_application', $json);

        // 2. Trainee onboarding checklist must be ticked
        $trainee->refresh();
        $this->assertTrue($trainee->onboarding_data['checklist']['therapy_form']);

        // 3. Checklist meta must record therapist details
        $meta = $trainee->onboarding_data['checklist_meta']['therapy_form'];
        $this->assertTrue($meta['status']);
        $this->assertEquals('internal_form', $meta['source']);
        $this->assertEquals('Dr. Marcus Vance', $meta['therapist_name']);
        $this->assertEquals('BACP 998811', $meta['professional_membership']);
        $this->assertEquals('On-going', $meta['therapy_hours_completed']);
        $this->assertFalse($meta['concerns_raised']);

        // 4. Activity log must record auto tick
        $this->assertDatabaseHas('activity_logs', [
            'model_type' => TraineeApplication::class,
            'model_id' => $trainee->id,
            'action' => 'paperwork_therapy_form_auto_ticked',
        ]);

        // 5. Token must be marked used
        $token->refresh();
        $this->assertNotNull($token->used_at);

        // 6. Re-using the same token immediately fails with 422
        $reuseResponse = $this->postJson('/api/trainee-applications/confirm-personal-therapy', $payload);
        $reuseResponse->assertStatus(422)
            ->assertJson([
                'message' => 'This link is no longer valid. Please contact help@vanquishtherapies.co.uk.',
            ]);
    }

    /**
     * Requirement: Trainee email in request is completely ignored; trainee is resolved strictly from the token.
     */
    public function test_trainee_email_in_request_is_ignored(): void
    {
        $alice = $this->createTrainee(['email' => 'alice@example.com']);
        $bob = $this->createTrainee(['email' => 'bob@example.com']);

        $tokenForAlice = PersonalTherapyToken::createFor($alice);

        $payload = $this->validTherapistPayload($tokenForAlice->token, [
            'trainee_email' => 'bob@example.com',
            'trainee_name' => 'Bob Hacker',
        ]);

        $response = $this->postJson('/api/trainee-applications/confirm-personal-therapy', $payload);
        $response->assertStatus(200)->assertExactJson(['success' => true]);

        $alice->refresh();
        $bob->refresh();

        // Alice is ticked because token belongs to Alice
        $this->assertTrue($alice->onboarding_data['checklist']['therapy_form']);
        // Bob is NOT ticked despite bob@example.com sent in request
        $this->assertFalse($bob->onboarding_data['checklist']['therapy_form']);
    }

    /**
     * Requirement: Public alias /api/therapy-form/confirm works identically.
     */
    public function test_public_alias_route_therapy_form_confirm(): void
    {
        $trainee = $this->createTrainee();
        $token = PersonalTherapyToken::createFor($trainee);

        $payload = $this->validTherapistPayload($token->token);

        $response = $this->postJson('/api/therapy-form/confirm', $payload);
        $response->assertStatus(200)->assertExactJson(['success' => true]);

        $trainee->refresh();
        $this->assertTrue($trainee->onboarding_data['checklist']['therapy_form']);
        $this->assertNotNull($token->fresh()->used_at);
    }

    /**
     * Requirement: GET /api/therapy-form/{token} returns trainee's first name only when valid.
     */
    public function test_get_therapy_form_returns_first_name_only_for_valid_token(): void
    {
        $trainee = $this->createTrainee([
            'first_name' => 'Samantha',
            'last_name' => 'Connors',
            'email' => 'samantha@example.com',
        ]);
        $token = PersonalTherapyToken::createFor($trainee);

        $response = $this->getJson("/api/therapy-form/{$token->token}");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'first_name' => 'Samantha',
            ]);

        $json = $response->json();
        $this->assertArrayNotHasKey('last_name', $json);
        $this->assertArrayNotHasKey('email', $json);
    }

    /**
     * Requirement: GET /api/therapy-form/{token} returns 422 for invalid, expired, or used token.
     */
    public function test_get_therapy_form_returns_422_for_bad_token(): void
    {
        $badResponse = $this->getJson('/api/therapy-form/non-existent-token');
        $badResponse->assertStatus(422)
            ->assertJson([
                'message' => 'This link is no longer valid. Please contact help@vanquishtherapies.co.uk.',
            ]);

        $trainee = $this->createTrainee();
        $expiredToken = PersonalTherapyToken::create([
            'trainee_application_id' => $trainee->id,
            'token' => 'expired-token-get-test',
            'expires_at' => now()->subMinutes(10),
        ]);

        $expiredResponse = $this->getJson("/api/therapy-form/{$expiredToken->token}");
        $expiredResponse->assertStatus(422)
            ->assertJson([
                'message' => 'This link is no longer valid. Please contact help@vanquishtherapies.co.uk.',
            ]);

        $usedToken = PersonalTherapyToken::create([
            'trainee_application_id' => $trainee->id,
            'token' => 'used-token-get-test',
            'expires_at' => now()->addDays(5),
            'used_at' => now()->subMinute(),
        ]);

        $usedResponse = $this->getJson("/api/therapy-form/{$usedToken->token}");
        $usedResponse->assertStatus(422)
            ->assertJson([
                'message' => 'This link is no longer valid. Please contact help@vanquishtherapies.co.uk.',
            ]);
    }

    /**
     * Requirement: Moving trainee application to Accepted generates a 30-day token link.
     */
    public function test_accepted_status_creates_30_day_token_and_links_therapy_form(): void
    {
        \Illuminate\Support\Facades\Mail::fake();

        $admin = \App\Models\User::factory()->create(['role' => 'admin']);

        $trainee = $this->createTrainee([
            'status' => 'Pending Review',
            'stage' => 'Stage 2 Approved',
        ]);

        $response = $this->actingAs($admin, 'sanctum')->postJson("/api/trainee-applications/{$trainee->id}/status", [
            'status' => 'Accepted',
            'induction_date' => '2026-11-15 10:00:00',
        ]);

        $response->assertStatus(200);

        $tokenRecord = PersonalTherapyToken::where('trainee_application_id', $trainee->id)->first();
        $this->assertNotNull($tokenRecord);
        $this->assertNull($tokenRecord->used_at);
        $this->assertTrue($tokenRecord->expires_at->isFuture());
        $this->assertTrue($tokenRecord->expires_at->greaterThan(now()->addDays(28)));
    }
}
