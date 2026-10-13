<?php

namespace Tests\Feature;

use App\Models\TrainingCounsellor;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class Q15CounsellorDisplayNameTest extends TestCase
{
    use RefreshDatabase;

    public function test_counsellor_first_name_accessor_uses_display_name_not_legal_first_name(): void
    {
        $counsellor = new TrainingCounsellor([
            'name' => 'Charles Sedenu',
            'legal_first_name' => 'Joel',
            'legal_last_name' => 'Gonzales',
        ]);

        $this->assertEquals('Charles', $counsellor->first_name);
        $this->assertEquals('Charles S.', $counsellor->abbreviated_name);
    }

    public function test_filtered_counsellors_returns_display_name_and_never_leaks_legal_name(): void
    {
        $counsellor = TrainingCounsellor::create([
            'name' => 'Charles Sedenu',
            'legal_first_name' => 'Joel',
            'legal_last_name' => 'Gonzales',
            'email' => 'charles.sedenu@example.com',
            'counsellor_type' => 'Qualified',
            'status' => 'Active',
            'offers_mid_range' => true,
            'offers_coaching' => true,
            'session_price' => 50.00,
            'qualified_to_work_with' => ['Individuals'],
            'availability' => ['monday' => ['morning-early']],
        ]);

        $response = $this->postJson('/api/mid-range-intake/filtered-counsellors', [
            'service_type' => 'Mid Range',
            'is_couples' => false,
            'client_availability' => ['monday' => ['morning-early']],
        ]);

        $response->assertStatus(200);

        $json = $response->json();
        $this->assertNotEmpty($json['counsellors']);

        $match = collect($json['counsellors'])->firstWhere('uuid', $counsellor->uuid);
        $this->assertNotNull($match);

        $this->assertEquals('Charles Sedenu', $match['name']);
        $this->assertEquals('Charles', $match['first_name']);

        // Assert that legal name "Joel" and "Gonzales" appear nowhere in the client-facing response
        $rawResponse = $response->getContent();
        $this->assertStringNotContainsString('Joel', $rawResponse);
        $this->assertStringNotContainsString('Gonzales', $rawResponse);
        $this->assertArrayNotHasKey('legal_first_name', $match);
        $this->assertArrayNotHasKey('legal_last_name', $match);
    }
}
