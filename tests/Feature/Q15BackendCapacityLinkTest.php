<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class Q15BackendCapacityLinkTest extends TestCase
{
    use RefreshDatabase;

    public function test_coaching_capacity_alternative_url_points_to_low_cost_intake_when_internal_form_enabled(): void
    {
        DB::table('company_settings')->updateOrInsert(
            ['key' => 'use_internal_intake_form'],
            ['value' => '1', 'updated_at' => now(), 'created_at' => now()]
        );

        $response = $this->getJson('/api/services/coaching-capacity');

        $response->assertStatus(200);
        $data = $response->json();

        $this->assertStringContainsString('/low-cost-intake', $data['alternative_url']);
        $this->assertStringNotContainsString('/clform', $data['alternative_url']);
    }
}
