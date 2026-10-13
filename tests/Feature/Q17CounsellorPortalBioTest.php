<?php

namespace Tests\Feature;

use App\Models\TrainingCounsellor;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class Q17CounsellorPortalBioTest extends TestCase
{
    use RefreshDatabase;

    public function test_qc_saves_bio_in_portal_and_it_appears_in_filtered_counsellors_response(): void
    {
        // 1. Create a Qualified counsellor record
        $tc = TrainingCounsellor::create([
            'tc_id' => 'QC-TEST-01',
            'uuid' => (string) Str::uuid(),
            'name' => 'Dr. Jane Qualified',
            'email' => 'jane.qualified@example.com',
            'counsellor_type' => 'Qualified',
            'status' => 'Active',
            'offers_mid_range' => true,
            'bio' => null,
        ]);

        // Create associated user with counsellor role
        $qcUser = User::create([
            'name' => 'Dr. Jane Qualified',
            'email' => 'jane.qualified@example.com',
            'password' => bcrypt('password123'),
            'role' => 'counsellor',
            'training_counsellor_id' => $tc->id,
            'is_active' => true,
        ]);

        $customBio = 'Hello, I am Dr. Jane. I specialize in cognitive-behavioral and person-centered approaches to anxiety, grief, and life transitions.';

        // 2. QC saves bio in the portal via PUT /api/counsellor/bio
        $response = $this->actingAs($qcUser, 'sanctum')->putJson('/api/counsellor/bio', [
            'bio' => $customBio,
        ]);

        $response->assertStatus(200);
        $response->assertJson([
            'message' => 'Bio updated successfully.',
            'bio' => $customBio,
        ]);

        // 3. Verify it saved to training_counsellors.bio (same field admin edits)
        $this->assertEquals($customBio, $tc->fresh()->bio);

        // 4. Verify it appears in the filtered counsellors response for clients
        $clientResponse = $this->postJson('/api/mid-range-intake/filtered-counsellors', [
            'service_type' => 'Mid Range',
        ]);

        $clientResponse->assertStatus(200);
        $counsellors = $clientResponse->json('counsellors');
        $this->assertNotEmpty($counsellors);

        $matched = collect($counsellors)->firstWhere('uuid', $tc->uuid);
        $this->assertNotNull($matched, 'Qualified counsellor should appear in filtered counsellors');
        $this->assertEquals($customBio, $matched['bio'], 'Saved bio must appear on counsellor card data in filtered counsellors response');
    }

    public function test_qc_can_also_update_bio_via_user_profile_endpoint(): void
    {
        $tc = TrainingCounsellor::create([
            'tc_id' => 'QC-TEST-02',
            'uuid' => (string) Str::uuid(),
            'name' => 'Mark Qualified',
            'email' => 'mark.qualified@example.com',
            'counsellor_type' => 'Qualified',
            'status' => 'Active',
            'offers_mid_range' => true,
            'bio' => 'Old bio',
        ]);

        $qcUser = User::create([
            'name' => 'Mark Qualified',
            'email' => 'mark.qualified@example.com',
            'password' => bcrypt('password123'),
            'role' => 'counsellor',
            'training_counsellor_id' => $tc->id,
            'is_active' => true,
        ]);

        $newBio = 'Updated bio via profile endpoint.';

        $response = $this->actingAs($qcUser, 'sanctum')->putJson('/api/user/profile', [
            'name' => 'Mark Qualified',
            'bio' => $newBio,
        ]);

        $response->assertStatus(200);
        $this->assertEquals($newBio, $tc->fresh()->bio);
    }

    public function test_trainee_counsellor_is_forbidden_from_updating_bio(): void
    {
        // Trainee counsellor record
        $tc = TrainingCounsellor::create([
            'tc_id' => 'TC-TEST-01',
            'uuid' => (string) Str::uuid(),
            'name' => 'Sam Trainee',
            'email' => 'sam.trainee@example.com',
            'counsellor_type' => 'Trainee',
            'status' => 'Active',
            'offers_mid_range' => false,
            'bio' => null,
        ]);

        $traineeUser = User::create([
            'name' => 'Sam Trainee',
            'email' => 'sam.trainee@example.com',
            'password' => bcrypt('password123'),
            'role' => 'counsellor',
            'training_counsellor_id' => $tc->id,
            'is_active' => true,
        ]);

        $response = $this->actingAs($traineeUser, 'sanctum')->putJson('/api/counsellor/bio', [
            'bio' => 'Trainees should not have a bio.',
        ]);

        $response->assertStatus(403);
        $this->assertNull($tc->fresh()->bio);
    }

    public function test_bio_exceeding_1200_chars_is_rejected_by_validation(): void
    {
        $tc = TrainingCounsellor::create([
            'tc_id' => 'QC-TEST-03',
            'uuid' => (string) Str::uuid(),
            'name' => 'Overlimit Qualified',
            'email' => 'overlimit@example.com',
            'counsellor_type' => 'Qualified',
            'status' => 'Active',
            'offers_mid_range' => true,
            'bio' => null,
        ]);

        $qcUser = User::create([
            'name' => 'Overlimit Qualified',
            'email' => 'overlimit@example.com',
            'password' => bcrypt('password123'),
            'role' => 'counsellor',
            'training_counsellor_id' => $tc->id,
            'is_active' => true,
        ]);

        $longBio = str_repeat('A', 1201);

        $response = $this->actingAs($qcUser, 'sanctum')->putJson('/api/counsellor/bio', [
            'bio' => $longBio,
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('bio');
    }
}
