<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class BrandingAndVersionConsistencyTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test that company-settings returns Vanquish Therapies and no placeholder names.
     */
    public function test_company_settings_public_endpoint_returns_vanquish_therapies(): void
    {
        $response = $this->getJson('/api/company-settings');

        $response->assertStatus(200);
        $data = $response->json();

        $this->assertEquals('Vanquish Therapies', $data['company_name'] ?? null);
        $this->assertStringNotContainsString('VQT Management', $data['company_name'] ?? '');
        $this->assertStringNotContainsString('Vanquish Training', $data['company_name'] ?? '');
    }

    /**
     * Test that service settings coaching capacity fallback does not contain VQT COACHING & THERAPY.
     */
    public function test_coaching_capacity_endpoint_uses_vanquish_therapies(): void
    {
        $response = $this->getJson('/api/services/coaching-capacity');

        $response->assertStatus(200);
        $data = $response->json();

        $message = $data['message'] ?? '';
        $this->assertStringNotContainsString('VQT COACHING & THERAPY', $message);
        $this->assertStringContainsString('Vanquish Therapies', $message);
    }
}
