<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\ClientIntakeForm;
use App\Models\QcApplication;
use App\Models\TraineeApplication;
use App\Models\TrainingCounsellor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Str;
use Tests\TestCase;

class MapSupportAreasCanonicalCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_dry_run_does_not_modify_database()
    {
        $client = Client::create([
            'name' => 'Jane Test',
            'email' => 'jane@example.com',
            'uuid' => (string) Str::uuid(),
            'primary_issues' => ['Health Anxiety', 'Low mood/depression', 'Stress Management'],
        ]);

        $tc = TrainingCounsellor::create([
            'name' => 'Counsellor Test',
            'email' => 'tc@example.com',
            'uuid' => (string) Str::uuid(),
            'tc_id' => 'TC999',
            'topics_with_experience' => ['Social Anxiety', 'Anxiety & Panic Attacks'],
            'topics_not_ready_for' => ['Infidelity', 'Domestic Violence'],
        ]);

        $exitCode = Artisan::call('vqt:map-support-areas', ['--dry-run' => true]);
        $output = Artisan::output();

        $this->assertEquals(0, $exitCode);
        $this->assertStringContainsString('[DRY RUN]', $output);
        $this->assertStringContainsString('Health Anxiety', $output);
        $this->assertStringContainsString('removed', $output);
        $this->assertStringContainsString('Social Anxiety', $output);
        $this->assertStringContainsString('Anxiety/panic attacks', $output);

        // Verify DB records were NOT modified
        $client->refresh();
        $this->assertEquals(
            ['Health Anxiety', 'Low mood/depression', 'Stress Management'],
            $client->primary_issues
        );

        $tc->refresh();
        $this->assertEquals(
            ['Social Anxiety', 'Anxiety & Panic Attacks'],
            $tc->topics_with_experience
        );
    }

    public function test_live_run_removes_unmapped_terms_and_maps_equivalents()
    {
        $client = Client::create([
            'name' => 'Alice Test',
            'email' => 'alice@example.com',
            'uuid' => (string) Str::uuid(),
            'primary_issues' => ['Health Anxiety', 'Low mood', 'Parenting Issues', 'Work Stress'],
        ]);

        $intake = ClientIntakeForm::create([
            'client_id' => $client->id,
            'first_name' => 'Alice',
            'last_name' => 'Test',
            'email' => 'alice@example.com',
            'support_areas' => ['Social Anxiety', 'Depression', 'Infidelity', 'Self-Harm'],
        ]);

        $tc = TrainingCounsellor::create([
            'name' => 'John Counsellor',
            'email' => 'john@example.com',
            'uuid' => (string) Str::uuid(),
            'tc_id' => 'TC998',
            'topics_with_experience' => ['Stress Management', 'Bereavement & Grief', 'Anger Management'],
            'topics_not_ready_for' => ['Infidelity', 'Sexual Abuse'],
        ]);

        $trainee = TraineeApplication::create([
            'first_name' => 'Sam',
            'last_name' => 'Trainee',
            'email' => 'sam@example.com',
            'uuid' => (string) Str::uuid(),
            'areas_of_experience' => 'Social Anxiety, Anxiety & Panic Attacks, Parenting Issues',
            'personal_development_areas' => 'Health Anxiety, Work-Related Stress',
        ]);

        $qc = QcApplication::create([
            'uuid' => (string) Str::uuid(),
            'legal_first_name' => 'Qualified',
            'legal_last_name' => 'Counsellor',
            'email' => 'qc@example.com',
            'answers' => [
                'experience_areas' => ['Stress Management', 'Childhood Trauma', 'Infidelity'],
            ],
        ]);

        // Run mapping for real (without --dry-run)
        $exitCode = Artisan::call('vqt:map-support-areas');
        $output = Artisan::output();

        $this->assertEquals(0, $exitCode);
        $this->assertStringContainsString('[LIVE RUN]', $output);

        // 1. Verify Client
        $client->refresh();
        // Health Anxiety and Parenting Issues removed; Low mood -> Low mood/depression; Work Stress -> Work related stress
        $this->assertEquals(['Low mood/depression', 'Work related stress'], $client->primary_issues);

        // 2. Verify Client Intake Form
        $intake->refresh();
        // Social Anxiety and Infidelity removed; Depression -> Low mood/depression; Self-Harm -> Self-harm
        $this->assertEquals(['Low mood/depression', 'Self-harm'], $intake->support_areas);

        // 3. Verify Training Counsellor
        $tc->refresh();
        // Stress Management removed; Bereavement & Grief -> Bereavement and grief; Anger Management -> Anger management issues
        $this->assertEquals(['Bereavement and grief', 'Anger management issues'], $tc->topics_with_experience);
        // Infidelity removed; Sexual Abuse -> Abuse (physical/emotional/sexual)
        $this->assertEquals(['Abuse (physical/emotional/sexual)'], $tc->topics_not_ready_for);

        // 4. Verify Trainee Application
        $trainee->refresh();
        $this->assertEquals('Anxiety/panic attacks', $trainee->areas_of_experience);
        $this->assertEquals('Work related stress', $trainee->personal_development_areas);

        // 5. Verify QC Application
        $qc->refresh();
        $qcExp = $qc->answers['experience_areas'];
        // Stress Management and Infidelity removed; Childhood Trauma -> Childhood trauma
        $this->assertEquals(['Childhood trauma'], $qcExp);
    }

    public function test_maintenance_command_route()
    {
        // Unauthorized without token
        $response = $this->getJson('/api/maintenance/command');
        $response->assertStatus(401);

        // Execute optimize:clear
        $response = $this->getJson('/api/maintenance/command?token=vqt_secret_migrate_2026&cmd=optimize:clear');
        $response->assertStatus(200);
        $response->assertJsonStructure(['command', 'output', 'opcache_reset']);
        $this->assertEquals('optimize:clear', $response->json('command'));

        // Execute vqt:map-support-areas dry run
        $response = $this->getJson('/api/maintenance/command?token=vqt_secret_migrate_2026&cmd=vqt:map-support-areas&dry_run=1');
        $response->assertStatus(200);
        $response->assertJsonStructure(['command', 'dry_run', 'output']);
        $this->assertTrue($response->json('dry_run'));
        $this->assertStringContainsString('[DRY RUN]', $response->json('output'));
    }
}
