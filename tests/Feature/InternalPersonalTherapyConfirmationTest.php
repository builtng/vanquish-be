<?php

namespace Tests\Feature;

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
            'therapist_name' => 'Dr. Marcus Vance',
            'therapist_email' => 'marcus.vance@therapy.co.uk',
            'therapist_phone' => '07999888777',
            'professional_body' => 'BACP',
            'membership_number' => 'BACP-998811',
            'therapy_modality' => 'Integrative',
            'therapy_frequency' => 'Weekly',
            'start_date' => '2025-10-01',
            'confirmed' => true,
            'signature' => 'Marcus Vance',
        ], $overrides);
    }

    /**
     * Requirement: No token in POST returns 422 with exact invalid message.
     */
    public function test_no_token_returns_422(): void
    {
        $payload = $this->validTherapistPayload('');
        unset($payload['token']);

        $response = $this->postJson('/api/trainee-applications/confirm-personal-therapy', $payload);

        $response->assertStatus(422)
            ->assertJson([
                'message' => 'This link is no longer valid. Please contact help@vanquishtherapies.co.uk.',
            ]);
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
        $this->assertEquals('BACP', $meta['professional_body']);
        $this->assertEquals('BACP-998811', $meta['membership_number']);

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
