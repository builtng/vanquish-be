<?php

namespace Tests\Feature;

use App\Models\QcApplication;
use App\Models\TrainingCounsellor;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class Q04PreviouslyWorkedFollowupTest extends TestCase
{
    use RefreshDatabase;

    public function test_Q04_yes_stores_areas_to_improve_on_application(): void
    {
        Mail::fake();

        $areasToImproveText = 'Previously worked as a placement trainee counsellor in 2024. Need development in couples therapy and safeguarding escalation procedures.';

        $payload = [
            'legal_first_name' => 'Jane',
            'legal_last_name' => 'Doe',
            'email' => 'jane.doe@example.com',
            'phone' => '07123456789',
            'registered_address' => '10 Downing Street',
            'registered_city' => 'London',
            'registered_postcode' => 'SW1A 2AA',
            'has_supervisor' => 'Yes',
            'previous_vanquish_work' => 'Yes',
            'areas_to_improve' => $areasToImproveText,
            'counsellor_training_details' => 'Level 4 Diploma in Therapeutic Counselling',
            'signature' => 'Jane Doe',
            'signature_date' => '2026-10-06',
        ];

        $response = $this->postJson('/api/qualified-counsellor/submit', $payload);
        $response->assertStatus(200);

        $app = QcApplication::where('email', 'jane.doe@example.com')->first();
        $this->assertNotNull($app, 'QC Application must be created');
        $this->assertEquals('Yes', $app->previous_vanquish_work);
        $this->assertEquals($areasToImproveText, $app->areas_to_improve);
        $this->assertEquals($areasToImproveText, $app->answers['areas_to_improve'] ?? null);

        // Verify it is not "N/A"
        $this->assertNotEquals('N/A', $app->areas_to_improve);
    }

    public function test_Q04_no_stores_null_without_areas_to_improve(): void
    {
        Mail::fake();

        $payload = [
            'legal_first_name' => 'John',
            'legal_last_name' => 'Smith',
            'email' => 'john.smith@example.com',
            'phone' => '07987654321',
            'registered_address' => '20 High Street',
            'registered_city' => 'Manchester',
            'registered_postcode' => 'M1 1AA',
            'has_supervisor' => 'Yes',
            'previous_vanquish_work' => 'No',
            // Notice: areas_to_improve is omitted when No, exactly as the frontend sends nothing
            'counsellor_training_details' => 'MSc Integrative Psychotherapy',
            'signature' => 'John Smith',
            'signature_date' => '2026-10-06',
        ];

        $response = $this->postJson('/api/qualified-counsellor/submit', $payload);
        $response->assertStatus(200);

        $app = QcApplication::where('email', 'john.smith@example.com')->first();
        $this->assertNotNull($app, 'QC Application must be created');
        $this->assertEquals('No', $app->previous_vanquish_work);
        $this->assertNull($app->areas_to_improve, 'areas_to_improve must be null, never hardcoded N/A');
    }

    public function test_Q04_accepting_application_transfers_areas_to_improve_to_practitioner(): void
    {
        Mail::fake();

        $admin = User::factory()->create([
            'role' => 'admin',
            'is_active' => true,
        ]);

        $areasToImproveText = 'Completed placement hours at Vanquish; focusing on transitioning to private practice boundaries.';

        $app = QcApplication::create([
            'legal_first_name' => 'Sarah',
            'legal_last_name' => 'Connor',
            'name' => 'Sarah Connor',
            'email' => 'sarah.connor@example.com',
            'phone' => '07111222333',
            'status' => 'New Application',
            'previous_vanquish_work' => 'Yes',
            'areas_to_improve' => $areasToImproveText,
            'answers' => [
                'legal_first_name' => 'Sarah',
                'legal_last_name' => 'Connor',
                'email' => 'sarah.connor@example.com',
                'registered_address' => '100 Cyber Road',
                'registered_city' => 'London',
                'registered_postcode' => 'EC1A 1BB',
                'previous_vanquish_work' => 'Yes',
                'areas_to_improve' => $areasToImproveText,
                'counsellor_training_details' => 'Diploma in Counselling',
                'has_supervisor' => 'Yes',
            ],
            'signature' => 'Sarah Connor',
            'signature_date' => '2026-10-06',
        ]);

        $this->actingAs($admin, 'sanctum');

        $response = $this->postJson("/api/qc-applications/{$app->id}/accept");
        $response->assertStatus(200);

        $tc = TrainingCounsellor::where('email', 'sarah.connor@example.com')->first();
        $this->assertNotNull($tc, 'Practitioner must be created upon acceptance');
        $this->assertEquals('Yes', $tc->previous_vanquish_work);
        $this->assertEquals($areasToImproveText, $tc->areas_to_improve);
    }
}
