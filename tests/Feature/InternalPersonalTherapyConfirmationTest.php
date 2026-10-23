<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\TraineeApplication;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InternalPersonalTherapyConfirmationTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Requirement 1: Validation requires therapist credentials and confirmation.
     */
    public function test_confirmation_endpoint_validates_required_fields(): void
    {
        $response = $this->postJson('/api/trainee-applications/confirm-personal-therapy', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors([
                'trainee_email',
                'therapist_name',
                'therapist_email',
                'professional_body',
                'membership_number',
                'confirmed',
                'signature',
            ]);
    }

    /**
     * Requirement 2: Submitting personal therapy confirmation automatically ticks therapy_form on the trainee application.
     */
    public function test_valid_submission_ticks_therapy_form_and_records_audit_metadata(): void
    {
        $trainee = TraineeApplication::create([
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
        ]);

        $payload = [
            'trainee_email' => 'alice.smith@example.com',
            'trainee_name' => 'Alice Smith',
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
        ];

        $response = $this->postJson('/api/trainee-applications/confirm-personal-therapy', $payload);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'matched_application' => true,
                'trainee_name' => 'Alice Smith',
            ]);

        $trainee->refresh();

        // 1. Checklist therapy_form must be true
        $this->assertTrue($trainee->onboarding_data['checklist']['therapy_form']);

        // 2. Checklist meta must record therapist credentials and internal_form source
        $meta = $trainee->onboarding_data['checklist_meta']['therapy_form'];
        $this->assertTrue($meta['status']);
        $this->assertEquals('internal_form', $meta['source']);
        $this->assertEquals('Dr. Marcus Vance', $meta['therapist_name']);
        $this->assertEquals('BACP', $meta['professional_body']);
        $this->assertEquals('BACP-998811', $meta['membership_number']);
        $this->assertEquals('Marcus Vance', $meta['signature']);
        $this->assertStringContainsString('Dr. Marcus Vance', $meta['ticked_by']);

        // 3. ActivityLog must record automatic tick
        $this->assertDatabaseHas('activity_logs', [
            'model_type' => TraineeApplication::class,
            'model_id' => $trainee->id,
            'action' => 'paperwork_therapy_form_auto_ticked',
        ]);
    }

    /**
     * Requirement 3: Case insensitivity and whitespace trimming on trainee email.
     */
    public function test_email_matching_is_case_insensitive_and_trimmed(): void
    {
        $trainee = TraineeApplication::create([
            'first_name' => 'Bob',
            'last_name' => 'Jones',
            'email' => 'bob.jones@example.com',
            'status' => 'Accepted',
            'onboarding_data' => [
                'checklist' => ['therapy_form' => false],
            ],
        ]);

        $payload = [
            'trainee_email' => '  BOB.JONES@EXAMPLE.COM  ',
            'therapist_name' => 'Sarah Connor',
            'therapist_email' => 'sarah@example.com',
            'professional_body' => 'UKCP',
            'membership_number' => 'UKCP-1234',
            'confirmed' => true,
            'signature' => 'Sarah Connor',
        ];

        $response = $this->postJson('/api/trainee-applications/confirm-personal-therapy', $payload);
        $response->assertStatus(200)->assertJson(['matched_application' => true]);

        $trainee->refresh();
        $this->assertTrue($trainee->onboarding_data['checklist']['therapy_form']);
    }

    /**
     * Requirement 4: Public alias route /api/therapy-form/confirm works identically.
     */
    public function test_public_alias_route_works(): void
    {
        $trainee = TraineeApplication::create([
            'first_name' => 'Chloe',
            'last_name' => 'Clark',
            'email' => 'chloe@example.com',
            'status' => 'Accepted',
            'onboarding_data' => [
                'checklist' => ['therapy_form' => false],
            ],
        ]);

        $payload = [
            'trainee_email' => 'chloe@example.com',
            'therapist_name' => 'Therapist Name',
            'therapist_email' => 'therapist@example.com',
            'professional_body' => 'NCPS',
            'membership_number' => 'NCPS-5678',
            'confirmed' => true,
            'signature' => 'Therapist Name',
        ];

        $response = $this->postJson('/api/therapy-form/confirm', $payload);
        $response->assertStatus(200)->assertJson(['matched_application' => true]);

        $trainee->refresh();
        $this->assertTrue($trainee->onboarding_data['checklist']['therapy_form']);
    }
}
