<?php

namespace Tests\Feature;

use App\Models\TrainingCounsellor;
use App\Services\MatchScoringService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class MatchingSensibleResultsE04Test extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
    }

    public function test_e04_matching_gives_sensible_results_with_three_counsellors_and_two_clients(): void
    {
        // 3 Test Counsellors
        $tc1 = TrainingCounsellor::create([
            'tc_id' => 'QC-E04-1',
            'uuid' => (string) Str::uuid(),
            'name' => 'Sarah Johnson',
            'email' => 'sarah@example.com',
            'status' => 'Active',
            'counsellor_type' => 'Qualified',
            'offers_mid_range' => true,
            'offers_coaching' => true,
            'modality' => 'CBT',
            'topics_with_experience' => ['Anxiety & Panic Attacks', 'Depression', 'Work-Related Stress'],
            'topics_not_ready_for' => ['Domestic Violence', 'Sexual Abuse/Assault', 'Suicidal Ideation'],
            'availability' => [
                'monday' => ['10am-1050am', '11am-1150am'],
                'wednesday' => ['1pm-150pm'],
            ],
            'gender' => 'Female',
            'age' => 32,
            'ethnicity' => 'White British',
            'sexual_orientation' => 'Heterosexual',
        ]);

        $tc2 = TrainingCounsellor::create([
            'tc_id' => 'QC-E04-2',
            'uuid' => (string) Str::uuid(),
            'name' => 'David Chen',
            'email' => 'david@example.com',
            'status' => 'Active',
            'counsellor_type' => 'Qualified',
            'offers_mid_range' => true,
            'offers_coaching' => true,
            'modality' => 'Person-Centred',
            'topics_with_experience' => [
                'Childhood Trauma',
                'PTSD',
                'Bereavement & Grief',
                'Depression',
                'Anxiety & Panic Attacks',
            ],
            'topics_not_ready_for' => ['Eating Disorders', 'OCD'],
            'availability' => [
                'monday' => ['1pm-150pm', '2pm-250pm'],
                'thursday' => ['5pm-550pm'],
            ],
            'gender' => 'Male',
            'age' => 38,
            'ethnicity' => 'Asian',
            'sexual_orientation' => 'Heterosexual',
        ]);

        $tc3 = TrainingCounsellor::create([
            'tc_id' => 'QC-E04-3',
            'uuid' => (string) Str::uuid(),
            'name' => 'Priya Patel',
            'email' => 'priya@example.com',
            'status' => 'Active',
            'counsellor_type' => 'Qualified',
            'offers_mid_range' => true,
            'offers_coaching' => true,
            'modality' => 'Integrative Therapy',
            'topics_with_experience' => [
                'Depression',
                'Anxiety & Panic Attacks',
                'Relationship Issues',
                'Low Self-Esteem',
                'Domestic Violence',
            ],
            'topics_not_ready_for' => ['Eating Disorders', 'Addiction & Substance Misuse'],
            'availability' => [
                'monday' => ['10am-1050am'],
                'tuesday' => ['10am-1050am'],
            ],
            'gender' => 'Female',
            'age' => 29,
            'ethnicity' => 'British Indian',
            'sexual_orientation' => 'Heterosexual',
        ]);

        $scoringService = app(MatchScoringService::class);

        // Client 1: Alex (Domestic Violence + Specific preferences)
        $client1 = [
            'service_type' => 'Mid Range',
            'support_areas' => ['Anxiety & Panic Attacks', 'Domestic Violence'],
            'modality_preference' => 'Integrative Therapy',
            'availability' => [
                'monday' => ['10am-1050am', '11am-1150am'],
            ],
            'gender_preference' => 'Female',
            'age_preference' => '20-30',
            'ethnicity_preference' => 'No preference',
            'orientation_preference' => 'No preference',
        ];

        // 1. Confirm "Not ready for" always excludes
        $this->assertTrue(
            $scoringService->isExcluded($client1, $tc1),
            'Sarah Johnson must be excluded because she is not ready for Domestic Violence'
        );
        $scoreAlexSarah = $scoringService->score($client1, $tc1);
        $this->assertTrue($scoreAlexSarah['is_excluded']);
        $this->assertEquals(0, $scoreAlexSarah['score']);

        $this->assertFalse($scoringService->isExcluded($client1, $tc2));
        $this->assertFalse($scoringService->isExcluded($client1, $tc3));

        // When queried via endpoint, Sarah must NOT appear
        $resAlex = $this->postJson('/api/mid-range-intake/filtered-counsellors', $client1);
        $resAlex->assertStatus(200);
        $alexData = $resAlex->json();
        $alexUuids = collect($alexData['counsellors'])->pluck('uuid');
        $this->assertFalse($alexUuids->contains($tc1->uuid), 'Excluded counsellor (Sarah) must not be in filtered results');
        $this->assertTrue($alexUuids->contains($tc3->uuid), 'Priya (Integrative Therapy) must be in filtered results');
        $this->assertFalse($alexUuids->contains($tc2->uuid), 'David (Person-Centred) is filtered out because Alex requested Integrative Therapy');

        // Confirm availability overlap is scored for Alex
        $scoreAlexDavid = $scoringService->score($client1, $tc2);
        $scoreAlexPriya = $scoringService->score($client1, $tc3);

        $davidAvail = collect($scoreAlexDavid['breakdown'])->firstWhere('label', 'Availability Match');
        $priyaAvail = collect($scoreAlexPriya['breakdown'])->firstWhere('label', 'Availability Match');

        $this->assertEquals(0, $davidAvail['score'], 'David has 0 overlap with Alex');
        $this->assertEquals(50, $priyaAvail['score'], 'Priya has 1 of 2 overlapping slots with Alex (50%)');
        $this->assertGreaterThan($scoreAlexDavid['score'], $scoreAlexPriya['score'], 'Priya must score higher than David for Alex');

        // Client 2: Jordan ("No preference" across the board)
        $client2 = [
            'service_type' => 'Mid Range',
            'support_areas' => ['Depression'],
            'modality_preference' => 'No preference',
            'availability' => [
                'monday' => ['1pm-150pm'],
            ],
            'gender_preference' => 'No preference',
            'age_preference' => 'No preference',
            'ethnicity_preference' => 'No preference',
            'orientation_preference' => 'No preference',
        ];

        // 2. Confirm "No preference" never lowers a score
        foreach ([$tc1, $tc2, $tc3] as $tc) {
            $this->assertFalse($scoringService->isExcluded($client2, $tc));
            $sc = $scoringService->score($client2, $tc);
            $breakdown = collect($sc['breakdown']);

            // Modality score is 100 (max) because Jordan has "No preference"
            $modalityCategory = $breakdown->firstWhere('label', 'Modality Match');
            $this->assertEquals(100, $modalityCategory['score'], "Modality score for {$tc->name} must be 100");

            // Demographic categories are 100 (max) because Jordan has "No preference"
            $genderCat = $breakdown->firstWhere('label', 'Gender Preference');
            $this->assertEquals(100, $genderCat['score'], "Gender score for {$tc->name} must be 100");

            $ethCat = $breakdown->firstWhere('label', 'Ethnicity Preference');
            $this->assertEquals(100, $ethCat['score'], "Ethnicity score for {$tc->name} must be 100");

            $oriCat = $breakdown->firstWhere('label', 'Sexual Orientation Preference');
            $this->assertEquals(100, $oriCat['score'], "Orientation score for {$tc->name} must be 100");

            $ageCat = $breakdown->firstWhere('label', 'Age Preference');
            $this->assertEquals(100, $ageCat['score'], "Age score for {$tc->name} must be 100");
        }

        // 3. Confirm availability overlap is scored for Jordan
        $scoreJordanSarah = $scoringService->score($client2, $tc1);
        $scoreJordanDavid = $scoringService->score($client2, $tc2);
        $scoreJordanPriya = $scoringService->score($client2, $tc3);

        $sarahAvail = collect($scoreJordanSarah['breakdown'])->firstWhere('label', 'Availability Match');
        $davidAvailJordan = collect($scoreJordanDavid['breakdown'])->firstWhere('label', 'Availability Match');
        $priyaAvailJordan = collect($scoreJordanPriya['breakdown'])->firstWhere('label', 'Availability Match');

        $this->assertEquals(0, $sarahAvail['score'], 'Sarah has 0 overlap with Jordan (Monday 1pm)');
        $this->assertEquals(100, $davidAvailJordan['score'], 'David has 1 of 1 overlapping slot with Jordan (100%)');
        $this->assertEquals(0, $priyaAvailJordan['score'], 'Priya has 0 overlap with Jordan (Monday 1pm)');

        // David Chen must be the top match for Jordan
        $this->assertGreaterThan($scoreJordanSarah['score'], $scoreJordanDavid['score']);
        $this->assertGreaterThan($scoreJordanPriya['score'], $scoreJordanDavid['score']);
    }
}
