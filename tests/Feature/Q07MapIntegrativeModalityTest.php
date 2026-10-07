<?php

namespace Tests\Feature;

use App\Models\TrainingCounsellor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class Q07MapIntegrativeModalityTest extends TestCase
{
    use RefreshDatabase;

    public function test_migration_maps_whole_word_integrative_in_comma_lists_and_qc_answers(): void
    {
        // 1. Seed test records in training_counsellors
        $tc1 = TrainingCounsellor::create([
            'tc_id' => 'TC001',
            'uuid' => (string) Str::uuid(),
            'name' => 'TC One',
            'email' => 'tcone@example.com',
            'status' => 'Active',
            'modality' => 'Integrative, Person-Centred',
        ]);

        $tc2 = TrainingCounsellor::create([
            'tc_id' => 'TC002',
            'uuid' => (string) Str::uuid(),
            'name' => 'TC Two',
            'email' => 'tctwo@example.com',
            'status' => 'Active',
            'modality' => 'Integrative Therapy, CBT',
        ]);

        $tc3 = TrainingCounsellor::create([
            'tc_id' => 'TC003',
            'uuid' => (string) Str::uuid(),
            'name' => 'TC Three',
            'email' => 'tcthree@example.com',
            'status' => 'Active',
            'modality' => 'CBT, Integrative Counselling and Therapy',
        ]);

        // 2. Seed test records in qc_applications
        $personId = DB::table('persons')->insertGetId([
            'uuid' => (string) Str::uuid(),
            'name' => 'Jane Doe',
            'first_name' => 'Jane',
            'last_name' => 'Doe',
            'email' => 'janedoe@example.com',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $qc1Id = DB::table('qc_applications')->insertGetId([
            'uuid' => (string) Str::uuid(),
            'person_id' => $personId,
            'name' => 'Jane Doe',
            'legal_first_name' => 'Jane',
            'legal_last_name' => 'Doe',
            'email' => 'janedoe@example.com',
            'status' => 'New Application',
            'answers' => json_encode([
                'modalities' => ['Integrative', 'Person-Centred'],
            ]),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $qc2Id = DB::table('qc_applications')->insertGetId([
            'uuid' => (string) Str::uuid(),
            'person_id' => $personId,
            'name' => 'Jane Doe',
            'legal_first_name' => 'Jane',
            'legal_last_name' => 'Doe',
            'email' => 'janedoe@example.com',
            'status' => 'New Application',
            'answers' => json_encode([
                'modalities' => ['Integrative Therapy', 'CBT'],
            ]),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Run the migration's up() method
        $migration = require database_path('migrations/2026_10_07_090000_map_integrative_modality_to_integrative_therapy.php');
        $migration->up();

        // Assert TC values
        $this->assertEquals('Integrative Therapy, Person-Centred', $tc1->fresh()->modality);
        $this->assertEquals('Integrative Therapy, CBT', $tc2->fresh()->modality);
        $this->assertEquals('CBT, Integrative Therapy', $tc3->fresh()->modality);

        // Assert QC applications values
        $qc1 = DB::table('qc_applications')->find($qc1Id);
        $answers1 = json_decode($qc1->answers, true);
        $this->assertEquals(['Integrative Therapy', 'Person-Centred'], $answers1['modalities']);

        $qc2 = DB::table('qc_applications')->find($qc2Id);
        $answers2 = json_decode($qc2->answers, true);
        $this->assertEquals(['Integrative Therapy', 'CBT'], $answers2['modalities']);
    }
}
