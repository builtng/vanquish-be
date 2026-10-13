<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\MatchingAlgorithmSetting;
use App\Models\TrainingCounsellor;
use App\Models\User;
use App\Services\MatchScoringService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class MatchScoringVqtWeightsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        MatchingAlgorithmSetting::truncate();
        MatchingAlgorithmSetting::create([
            'availability_weight' => 40,
            'modality_weight' => 20,
            'gender_weight' => 20,
            'ethnicity_weight' => 10,
            'sexual_orientation_weight' => 5,
            'age_weight' => 5,
        ]);
    }

    /**
     * TEST 1: Changing a weight in admin changes the Pending Matches score.
     */
    public function test_changing_a_weight_in_admin_changes_pending_matches_score(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $client = Client::create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Test Client',
            'email' => 'client@test.com',
            'service_type' => 'Mid Range',
            'stage' => 'Pending Match',
            'availability' => [
                'monday' => ['10am-1050am'],
            ],
            'modality_preference' => 'CBT',
            'gender_preference' => 'Female',
            'ethnicity_preference' => 'No preference',
            'orientation_preference' => 'No preference',
            'age_preference' => 'No preference',
            'primary_issues' => ['Anxiety & Panic Attacks'],
        ]);

        $tc = TrainingCounsellor::create([
            'uuid' => (string) Str::uuid(),
            'tc_id' => 'QC901',
            'name' => 'Fiona Gallagher',
            'email' => 'fiona@vanquish.co.uk',
            'counsellor_type' => 'Qualified',
            'status' => 'Active',
            'modality' => 'CBT',
            'gender' => 'Male', // Doesn't match Female preference
            'ethnicity' => 'White British',
            'sexual_orientation' => 'Heterosexual',
            'age' => 35,
            'current_clients' => 0,
            'max_clients' => 6,
            'offers_mid_range' => true,
            'availability' => [
                'monday' => ['10am-1050am'], // Overlaps 100%
            ],
            'topics_with_experience' => ['Anxiety & Panic Attacks'],
            'topics_not_ready_for' => [],
        ]);

        // Default weights:
        // Avail: 40 (100% = 40)
        // Modality: 20 (100% = 20)
        // Gender: 20 (0% = 0)
        // Ethnicity: 10 (100% = 10)
        // Orientation: 5 (100% = 5)
        // Age: 5 (100% = 5)
        // Expected score = 40 + 20 + 0 + 10 + 5 + 5 = 80
        $resInitial = $this->actingAs($admin, 'sanctum')
            ->getJson("/api/pending-matches/{$client->uuid}/suggestions");
        $resInitial->assertStatus(200);
        $initialData = $resInitial->json('suggestions');
        $this->assertNotEmpty($initialData);
        $this->assertEquals(80, $initialData[0]['matchScore']);

        // Now admin updates weights: Increase Gender to 40, decrease Availability to 20
        $updateRes = $this->actingAs($admin, 'sanctum')
            ->putJson('/api/matching-algorithm-settings', [
                'availability_weight' => 20,
                'modality_weight' => 20,
                'gender_weight' => 40,
                'ethnicity_weight' => 10,
                'sexual_orientation_weight' => 5,
                'age_weight' => 5,
            ]);
        $updateRes->assertStatus(200);

        // Score must change:
        // Avail: 20 (100% = 20)
        // Modality: 20 (100% = 20)
        // Gender: 40 (0% = 0)
        // Ethnicity: 10 (100% = 10)
        // Orientation: 5 (100% = 5)
        // Age: 5 (100% = 5)
        // Expected score = 20 + 20 + 0 + 10 + 5 + 5 = 60
        $resUpdated = $this->actingAs($admin, 'sanctum')
            ->getJson("/api/pending-matches/{$client->uuid}/suggestions");
        $resUpdated->assertStatus(200);
        $updatedData = $resUpdated->json('suggestions');
        $this->assertEquals(60, $updatedData[0]['matchScore']);
        $this->assertNotEquals($initialData[0]['matchScore'], $updatedData[0]['matchScore']);
    }

    /**
     * TEST 2: Pending Matches and the client-facing list give the SAME SCORE for the same client and counsellor.
     */
    public function test_pending_matches_and_client_facing_list_give_same_score(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $clientPayload = [
            'service_type' => 'Mid Range',
            'support_areas' => ['Anxiety & Panic Attacks'],
            'primary_issues' => ['Anxiety & Panic Attacks'],
            'modality_preference' => 'Integrative Therapy',
            'availability' => [
                'monday' => ['10am-1050am'],
            ],
            'gender_preference' => 'Female',
            'age_preference' => '25-35',
            'ethnicity_preference' => 'Asian',
            'orientation_preference' => 'Heterosexual',
        ];

        $client = Client::create(array_merge($clientPayload, [
            'uuid' => (string) Str::uuid(),
            'name' => 'Identical Test Client',
            'email' => 'identical@test.com',
            'stage' => 'Pending Match',
        ]));

        $tc = TrainingCounsellor::create([
            'uuid' => (string) Str::uuid(),
            'tc_id' => 'QC902',
            'name' => 'Maya Lin',
            'email' => 'maya@vanquish.co.uk',
            'counsellor_type' => 'Qualified',
            'status' => 'Active',
            'modality' => 'Integrative Therapy',
            'gender' => 'Female',
            'ethnicity' => 'Asian',
            'sexual_orientation' => 'Heterosexual',
            'age' => 30, // within 25-35
            'current_clients' => 1,
            'max_clients' => 6,
            'offers_mid_range' => true,
            'availability' => [
                'monday' => ['10am-1050am'],
            ],
            'topics_with_experience' => ['Anxiety & Panic Attacks'],
            'topics_not_ready_for' => [],
        ]);

        // 1. Client-facing filtered counsellors endpoint
        $clientFacingRes = $this->postJson('/api/mid-range-intake/filtered-counsellors', $clientPayload);
        $clientFacingRes->assertStatus(200);
        $clientFacingData = collect($clientFacingRes->json('counsellors'))->firstWhere('uuid', $tc->uuid);
        $this->assertNotNull($clientFacingData);
        $clientFacingScore = $clientFacingData['match_score'];

        // 2. Pending Matches suggestions endpoint
        $pendingRes = $this->actingAs($admin, 'sanctum')
            ->getJson("/api/pending-matches/{$client->uuid}/suggestions");
        $pendingRes->assertStatus(200);
        $pendingData = collect($pendingRes->json('suggestions'))->firstWhere('uuid', $tc->uuid);
        $this->assertNotNull($pendingData);
        $pendingScore = $pendingData['matchScore'];

        // 3. Confirm scores are identical
        $this->assertEquals(100, $clientFacingScore);
        $this->assertEquals(100, $pendingScore);
        $this->assertEquals($clientFacingScore, $pendingScore, 'Pending matches and client-facing score must be identical');
    }

    /**
     * TEST 3: A counsellor whose "not ready for" list contains one of the client's areas,
     * matched by exact name, never appears. A partial word match does NOT exclude.
     */
    public function test_exact_name_matching_excludes_and_partial_word_match_does_not(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $scoringService = app(MatchScoringService::class);

        // TC with "Trauma" in topics_not_ready_for
        $tcTrauma = TrainingCounsellor::create([
            'uuid' => (string) Str::uuid(),
            'tc_id' => 'QC903',
            'name' => 'Trauma Specialist',
            'email' => 'trauma@vanquish.co.uk',
            'counsellor_type' => 'Qualified',
            'status' => 'Active',
            'modality' => 'Integrative',
            'offers_mid_range' => true,
            'current_clients' => 0,
            'max_clients' => 6,
            'topics_not_ready_for' => ['Trauma'],
        ]);

        // TC with "Eating Disorders" in topics_not_ready_for
        $tcEatingDisorders = TrainingCounsellor::create([
            'uuid' => (string) Str::uuid(),
            'tc_id' => 'QC904',
            'name' => 'Eating Disorders Excluded',
            'email' => 'eating@vanquish.co.uk',
            'counsellor_type' => 'Qualified',
            'status' => 'Active',
            'modality' => 'Integrative',
            'offers_mid_range' => true,
            'current_clients' => 0,
            'max_clients' => 6,
            'topics_not_ready_for' => ['Eating Disorders'],
        ]);

        // Client A has "Childhood Trauma" (should NOT be excluded by "Trauma")
        $clientA = [
            'service_type' => 'Mid Range',
            'support_areas' => ['Childhood Trauma'],
            'primary_issues' => ['Childhood Trauma'],
        ];

        // Partial match must NOT exclude
        $this->assertFalse(
            $scoringService->isExcluded($clientA, $tcTrauma),
            '"Childhood Trauma" must NOT be excluded by "Trauma"'
        );

        // Client B has "Trauma" (exact match, trimmed, case-insensitive)
        $clientB = [
            'service_type' => 'Mid Range',
            'support_areas' => ['  trauma  '],
            'primary_issues' => ['  trauma  '],
        ];

        // Exact match MUST exclude
        $this->assertTrue(
            $scoringService->isExcluded($clientB, $tcTrauma),
            'Exact match "trauma" MUST exclude counsellor'
        );

        // Client C has "Eating" (partial match for "Eating Disorders" - must NOT exclude)
        $clientC = [
            'service_type' => 'Mid Range',
            'support_areas' => ['Eating'],
            'primary_issues' => ['Eating'],
        ];
        $this->assertFalse(
            $scoringService->isExcluded($clientC, $tcEatingDisorders),
            '"Eating" must NOT be excluded by "Eating Disorders"'
        );

        // Client D has "Eating Disorders" (exact match - MUST exclude)
        $clientD = [
            'service_type' => 'Mid Range',
            'support_areas' => ['Eating Disorders'],
            'primary_issues' => ['Eating Disorders'],
        ];
        $this->assertTrue(
            $scoringService->isExcluded($clientD, $tcEatingDisorders),
            'Exact match "Eating Disorders" MUST exclude counsellor'
        );

        // Test in endpoint: Client with "Eating Disorders" never gets $tcEatingDisorders in suggestions
        $dbClientD = Client::create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Client D',
            'email' => 'clientd@test.com',
            'service_type' => 'Mid Range',
            'stage' => 'Pending Match',
            'primary_issues' => ['Eating Disorders'],
        ]);

        $resD = $this->actingAs($admin, 'sanctum')
            ->getJson("/api/pending-matches/{$dbClientD->uuid}/suggestions");
        $resD->assertStatus(200);
        $uuidsD = collect($resD->json('suggestions'))->pluck('uuid');
        $this->assertFalse(
            $uuidsD->contains($tcEatingDisorders->uuid),
            'Excluded counsellor must never appear in pending matches suggestions'
        );
    }
}
