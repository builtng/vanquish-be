<?php

namespace Tests\Feature;

use App\Models\TraineeApplication;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PaperworkAutomaticTicksD04Test extends TestCase
{
    use RefreshDatabase;

    public function test_personal_therapy_webhook_requires_secret_key_and_returns_403_without_it()
    {
        $payload = [
            'submissionID' => '1234567890',
            'email' => 'jane.trainee@example.com',
            'formTitle' => 'Personal Therapy Form',
        ];

        // Without key
        $response = $this->postJson('/api/jotform/personal-therapy-webhook', $payload);
        $response->assertStatus(403)
            ->assertJson([
                'error' => 'Unauthorized',
                'message' => 'Invalid or missing webhook key',
            ]);

        // With invalid key
        $invalidResponse = $this->postJson('/api/jotform/personal-therapy-webhook?key=wrong_secret', $payload);
        $invalidResponse->assertStatus(403);
    }

    public function test_agreement_webhook_requires_secret_key_and_returns_403_without_it()
    {
        $payload = [
            'submissionID' => '987654321',
            'email' => 'client@example.com',
        ];

        // Without key
        $response = $this->postJson('/api/jotform/agreement-webhook', $payload);
        $response->assertStatus(403)
            ->assertJson([
                'error' => 'Unauthorized',
                'message' => 'Invalid or missing webhook key',
            ]);

        // With invalid key
        $invalidResponse = $this->postJson('/api/jotform/agreement-webhook?key=wrong_key', $payload);
        $invalidResponse->assertStatus(403);
    }

    public function test_personal_therapy_jotform_webhook_ticks_therapy_form_and_records_audit_trail_with_secret_key()
    {
        $trainee = TraineeApplication::create([
            'first_name' => 'Jane',
            'last_name' => 'Trainee',
            'email' => 'jane.trainee@example.com',
            'phone' => '07111222333',
            'status' => 'Accepted',
            'onboarding_data' => [
                'checklist' => [
                    'four_way_agreement' => false,
                    'therapy_form' => false,
                ],
            ],
        ]);

        $payload = [
            'submissionID' => '1234567890',
            'email' => 'jane.trainee@example.com',
            'formTitle' => 'Personal Therapy Form',
        ];

        $response = $this->postJson('/api/jotform/personal-therapy-webhook?key=vqt_jotform_secret_2026', $payload);
        $response->assertStatus(200)
            ->assertJson([
                'message' => 'Webhook received and processed',
            ]);

        $trainee->refresh();

        $this->assertTrue($trainee->onboarding_data['checklist']['therapy_form']);
        $this->assertEquals('Personal Therapy JotForm Webhook', $trainee->onboarding_data['checklist_meta']['therapy_form']['ticked_by']);
        $this->assertEquals('1234567890', $trainee->onboarding_data['checklist_meta']['therapy_form']['submission_id']);
        $this->assertNotEmpty($trainee->onboarding_data['checklist_meta']['therapy_form']['ticked_at']);
        $this->assertDatabaseHas('activity_logs', [
            'action' => 'paperwork_therapy_form_auto_ticked',
            'model_id' => $trainee->id,
        ]);
    }

    public function test_personal_therapy_webhook_always_replies_with_same_message_when_trainee_not_found()
    {
        $payload = [
            'submissionID' => '999999',
            'email' => 'nonexistent.trainee@example.com',
        ];

        $response = $this->postJson('/api/jotform/personal-therapy-webhook?key=vqt_jotform_secret_2026', $payload);
        $response->assertStatus(200)
            ->assertJson([
                'message' => 'Webhook received and processed',
            ]);
    }

    public function test_agreement_webhook_always_replies_with_same_message_whether_or_not_found()
    {
        // Trainee agreement auto-tick
        $trainee = TraineeApplication::create([
            'first_name' => 'John',
            'last_name' => 'Agreement',
            'email' => 'john.agreement@example.com',
            'phone' => '07111999888',
            'status' => 'Accepted',
            'onboarding_data' => [
                'checklist' => [
                    'four_way_agreement' => false,
                ],
            ],
        ]);

        $payload = [
            'submissionID' => '888777666',
            'email' => 'john.agreement@example.com',
            'signature' => 'https://files.jotform.com/sig123.png',
        ];

        $response = $this->postJson('/api/jotform/agreement-webhook?key=vqt_jotform_secret_2026', $payload);
        $response->assertStatus(200)
            ->assertJson([
                'message' => 'Webhook received and processed',
            ]);

        $trainee->refresh();
        $this->assertTrue($trainee->onboarding_data['checklist']['four_way_agreement']);

        // When not found
        $notFoundPayload = [
            'submissionID' => '111222333',
            'email' => 'unknown@example.com',
        ];
        $notFoundResponse = $this->postJson('/api/jotform/agreement-webhook?key=vqt_jotform_secret_2026', $notFoundPayload);
        $notFoundResponse->assertStatus(200)
            ->assertJson([
                'message' => 'Webhook received and processed',
            ]);
    }

    public function test_personal_therapy_webhook_via_alias_route_and_raw_request()
    {
        $trainee = TraineeApplication::create([
            'first_name' => 'Alex',
            'last_name' => 'Counsellor',
            'email' => 'alex.therapy@example.com',
            'phone' => '07222333444',
            'status' => 'Accepted',
            'onboarding_data' => [
                'checklist' => [
                    'therapy_form' => false,
                ],
            ],
        ]);

        $payload = [
            'rawRequest' => json_encode([
                'q3_email' => 'alex.therapy@example.com',
                'submissionID' => '555666777',
            ]),
        ];

        $response = $this->postJson('/api/jotform/therapy-webhook?key=vqt_jotform_secret_2026', $payload);
        $response->assertStatus(200)
            ->assertJson([
                'message' => 'Webhook received and processed',
            ]);

        $trainee->refresh();
        $this->assertTrue($trainee->onboarding_data['checklist']['therapy_form']);
        $this->assertEquals('Personal Therapy JotForm Webhook', $trainee->onboarding_data['checklist_meta']['therapy_form']['ticked_by']);
    }

    public function test_signed_4_way_agreement_upload_ticks_four_way_agreement_and_records_audit_trail()
    {
        Storage::fake('private');

        $admin = User::create([
            'name' => 'Super Admin',
            'email' => 'superadmin@example.com',
            'password' => bcrypt('secret123'),
            'role' => 'admin',
        ]);

        $trainee = TraineeApplication::create([
            'first_name' => 'Mark',
            'last_name' => 'Agreement',
            'email' => 'mark.agreement@example.com',
            'phone' => '07333444555',
            'status' => 'Accepted',
            'onboarding_data' => [
                'checklist' => [
                    'four_way_agreement' => false,
                    'therapy_form' => false,
                ],
            ],
        ]);

        $file = UploadedFile::fake()->create('signed_four_way_agreement.pdf', 250, 'application/pdf');

        $response = $this->actingAs($admin)->post('/api/trainee-applications/' . $trainee->id . '/upload-agreement', [
            'file' => $file,
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'message' => 'Signed 4-way agreement uploaded successfully',
            ]);

        $trainee->refresh();

        $this->assertTrue($trainee->onboarding_data['checklist']['four_way_agreement']);
        $this->assertStringContainsString('Super Admin', $trainee->onboarding_data['checklist_meta']['four_way_agreement']['ticked_by']);
        $this->assertStringContainsString('signed_four_way_agreement.pdf', $trainee->onboarding_data['checklist_meta']['four_way_agreement']['source']);
        $this->assertEquals('signed_four_way_agreement.pdf', $trainee->onboarding_data['documents']['four_way_agreement']['filename']);

        // Check document download works
        $downloadResponse = $this->actingAs($admin)->get('/api/trainee-applications/' . $trainee->id . '/document/four_way_agreement');
        $downloadResponse->assertStatus(200);
    }

    public function test_admin_manual_paperwork_toggle_preserves_ability_to_change_by_hand_and_updates_audit_meta()
    {
        $admin = User::create([
            'name' => 'Manual Admin',
            'email' => 'manual.admin@example.com',
            'password' => bcrypt('secret123'),
            'role' => 'admin',
        ]);

        $trainee = TraineeApplication::create([
            'first_name' => 'Manual',
            'last_name' => 'Test',
            'email' => 'manual.test@example.com',
            'phone' => '07444555666',
            'status' => 'Accepted',
            'onboarding_data' => [
                'checklist' => [
                    'therapy_form' => true,
                ],
            ],
        ]);

        // Untick manually
        $response = $this->actingAs($admin)->postJson('/api/trainee-applications/' . $trainee->id . '/paperwork', [
            'document_key' => 'therapy_form',
            'status' => false,
        ]);

        $response->assertStatus(200);
        $trainee->refresh();

        $this->assertFalse($trainee->onboarding_data['checklist']['therapy_form']);
        $this->assertStringContainsString('Manual Admin', $trainee->onboarding_data['checklist_meta']['therapy_form']['ticked_by']);
        $this->assertEquals('manual_admin', $trainee->onboarding_data['checklist_meta']['therapy_form']['source']);
    }
}
