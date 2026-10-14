<?php

namespace Tests\Feature;

use App\Models\TraineeApplication;
use App\Models\TrainingCounsellor;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TrainingCounsellorJourneyJ11Test extends TestCase
{
    use RefreshDatabase;

    protected User $adminUser;

    protected function setUp(): void
    {
        parent::setUp();

        $this->adminUser = User::factory()->create([
            'role' => 'admin',
        ]);
    }

    public function test_tc_show_returns_five_journey_stages_from_real_pipeline_data(): void
    {
        $app = TraineeApplication::create([
            'first_name' => 'Jane',
            'last_name' => 'Doe',
            'email' => 'jane.doe@example.com',
            'phone' => '07123456789',
            'status' => 'Active',
            'jotform_submitted_at' => '2026-01-10 10:00:00',
            'interview_scheduled_at' => '2026-01-20 14:00:00',
            'interview_data' => [
                'attendance' => [
                    'attended' => true,
                    'attended_at' => '2026-01-20 15:00:00',
                ],
                'decision' => [
                    'outcome' => 'Accepted',
                    'decided_at' => '2026-01-22 09:30:00',
                ],
            ],
            'onboarding_data' => [
                'induction_date' => '2026-02-01',
                'checklist' => [
                    'induction_attended' => true,
                ],
            ],
        ]);

        $tc = TrainingCounsellor::create([
            'name' => 'Jane Doe',
            'email' => 'jane.doe@example.com',
            'status' => 'Active',
            'counsellor_type' => 'Trainee',
        ]);

        $response = $this->actingAs($this->adminUser, 'sanctum')
            ->getJson("/api/training-counsellors/{$tc->id}");

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'id',
            'journey' => [
                '*' => ['stage', 'name', 'date', 'completed', 'current'],
            ],
        ]);

        $journey = $response->json('journey');
        $this->assertCount(5, $journey);

        $stageNames = array_column($journey, 'stage');
        $this->assertEquals([
            'Application Received',
            'Interview Invited and Attended',
            'Accepted',
            'Induction Attended',
            'Active',
        ], $stageNames);

        // Verify all 5 are completed based on the real pipeline data
        $this->assertTrue($journey[0]['completed']);
        $this->assertTrue($journey[1]['completed']);
        $this->assertEquals('2026-01-20 15:00:00', $journey[1]['date']);
        $this->assertTrue($journey[2]['completed']);
        $this->assertEquals('2026-01-22 09:30:00', $journey[2]['date']);
        $this->assertTrue($journey[3]['completed']);
        $this->assertEquals('2026-02-01', $journey[3]['date']);
        $this->assertTrue($journey[4]['completed']);
        $this->assertTrue($journey[4]['current']);
    }

    public function test_tc_show_journey_reflects_incomplete_stages_when_pipeline_in_progress(): void
    {
        $app = TraineeApplication::create([
            'first_name' => 'Mark',
            'last_name' => 'Smith',
            'email' => 'mark.smith@example.com',
            'phone' => '07999888777',
            'status' => 'Interview Scheduled',
            'interview_scheduled_at' => '2026-03-01 10:00:00',
            'interview_data' => [
                'attendance' => [
                    'attended' => false,
                ],
            ],
        ]);

        $tc = TrainingCounsellor::create([
            'name' => 'Mark Smith',
            'email' => 'mark.smith@example.com',
            'status' => 'Inactive',
            'counsellor_type' => 'Trainee',
        ]);

        $response = $this->actingAs($this->adminUser, 'sanctum')
            ->getJson("/api/training-counsellors/{$tc->id}");

        $response->assertStatus(200);
        $journey = $response->json('journey');

        $this->assertCount(5, $journey);
        $this->assertTrue($journey[0]['completed']); // Application Received
        $this->assertFalse($journey[1]['completed']); // Interview Not Attended
        $this->assertFalse($journey[2]['completed']); // Not Accepted
        $this->assertFalse($journey[3]['completed']); // Induction Not Attended
        $this->assertFalse($journey[4]['completed']); // Not Active
        $this->assertTrue($journey[0]['current']); // Current is Application Received
    }
}
