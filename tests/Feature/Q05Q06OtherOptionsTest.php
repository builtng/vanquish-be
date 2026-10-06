<?php

namespace Tests\Feature;

use App\Models\QcApplication;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class Q05Q06OtherOptionsTest extends TestCase
{
    use RefreshDatabase;

    public function test_Q05_and_Q06_stores_other_modalities_and_other_experience_areas_on_application(): void
    {
        Mail::fake();

        $otherModalitiesText = 'Internal Family Systems (IFS), Narrative Therapy';
        $otherExperienceAreasText = 'Spiritual crisis, Career transition, Hoarding';

        $payload = [
            'legal_first_name' => 'Sarah',
            'legal_last_name' => 'Connor',
            'email' => 'sarah.connor@example.com',
            'phone' => '07123456789',
            'registered_address' => '10 Downing Street',
            'registered_city' => 'London',
            'registered_postcode' => 'SW1A 2AA',
            'has_supervisor' => 'Yes',
            'counsellor_training_details' => 'Level 5 Psychotherapeutic Counselling',
            'modalities' => ['Cognitive Behavioural Therapy (CBT)', 'Other (not listed above)'],
            'other_modalities' => $otherModalitiesText,
            'experience_areas' => ['Anxiety & Stress', 'Other (not listed above)'],
            'other_experience_areas' => $otherExperienceAreasText,
            'signature' => 'Sarah Connor',
            'signature_date' => '2026-10-06',
        ];

        $response = $this->postJson('/api/qualified-counsellor/submit', $payload);
        $response->assertStatus(200);

        $app = QcApplication::where('email', 'sarah.connor@example.com')->first();
        $this->assertNotNull($app, 'QC Application must be created');
        $this->assertEquals($otherModalitiesText, $app->other_modalities);
        $this->assertEquals($otherExperienceAreasText, $app->other_experience_areas);
        $this->assertEquals($otherModalitiesText, $app->answers['other_modalities'] ?? null);
        $this->assertEquals($otherExperienceAreasText, $app->answers['other_experience_areas'] ?? null);
    }

    public function test_Q05_and_Q06_stores_null_when_other_options_omitted(): void
    {
        Mail::fake();

        $payload = [
            'legal_first_name' => 'Kyle',
            'legal_last_name' => 'Reese',
            'email' => 'kyle.reese@example.com',
            'phone' => '07987654321',
            'registered_address' => '20 High Street',
            'registered_city' => 'Manchester',
            'registered_postcode' => 'M1 1AA',
            'has_supervisor' => 'Yes',
            'counsellor_training_details' => 'Level 4 Diploma',
            'modalities' => ['Cognitive Behavioural Therapy (CBT)'],
            'experience_areas' => ['Depression'],
            'signature' => 'Kyle Reese',
            'signature_date' => '2026-10-06',
        ];

        $response = $this->postJson('/api/qualified-counsellor/submit', $payload);
        $response->assertStatus(200);

        $app = QcApplication::where('email', 'kyle.reese@example.com')->first();
        $this->assertNotNull($app);
        $this->assertNull($app->other_modalities);
        $this->assertNull($app->other_experience_areas);
    }

    public function test_admin_can_view_application_with_other_options(): void
    {
        $admin = User::factory()->create([
            'role' => 'admin',
        ]);

        $app = QcApplication::create([
            'name' => 'Sarah Connor',
            'legal_first_name' => 'Sarah',
            'legal_last_name' => 'Connor',
            'email' => 'sarah.connor@example.com',
            'phone' => '07123456789',
            'registered_address' => '10 Downing Street',
            'registered_city' => 'London',
            'registered_postcode' => 'SW1A 2AA',
            'signature' => 'Sarah Connor',
            'signature_date' => '2026-10-06',
            'status' => 'New Application',
            'other_modalities' => 'Internal Family Systems',
            'other_experience_areas' => 'Spiritual crisis',
            'answers' => [
                'modalities' => ['Other (not listed above)'],
                'other_modalities' => 'Internal Family Systems',
                'experience_areas' => ['Other (not listed above)'],
                'other_experience_areas' => 'Spiritual crisis',
            ],
        ]);

        $response = $this->actingAs($admin)->getJson('/api/qc-applications');
        $response->assertStatus(200);

        $applications = is_array($response->json()) && isset($response->json()[0]) 
            ? $response->json() 
            : ($response->json('applications.data') ?? $response->json('applications') ?? $response->json('data') ?? []);
        $found = collect($applications)->firstWhere('email', 'sarah.connor@example.com');
        $this->assertNotNull($found, 'Application must be returned to admin');
        $this->assertEquals('Internal Family Systems', $found['other_modalities'] ?? $found['answers']['other_modalities'] ?? null);
        $this->assertEquals('Spiritual crisis', $found['other_experience_areas'] ?? $found['answers']['other_experience_areas'] ?? null);
    }
}
