<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Client;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class UnusedMatchRouteE04Test extends TestCase
{
    use RefreshDatabase;

    public function test_e04_post_pending_matches_match_is_removed(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $response = $this->actingAs($admin, 'sanctum')
            ->postJson('/api/pending-matches/match', []);

        // Route should not exist (404 or 405)
        $this->assertTrue(in_array($response->status(), [404, 405]));
    }

    public function test_e04_get_pending_matches_suggestions_is_kept(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $client = Client::create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Test Client',
            'email' => 'client@test.com',
            'service_type' => 'Low Cost',
            'status' => 'Pending Match',
        ]);

        $response = $this->actingAs($admin, 'sanctum')
            ->getJson("/api/pending-matches/{$client->uuid}/suggestions");

        $response->assertStatus(200);
    }
}
