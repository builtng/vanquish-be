<?php

namespace Tests\Feature;

use App\Models\TrainingCounsellor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class MidRangeFilteredCounsellorsTest extends TestCase
{
    use RefreshDatabase;

    public function test_counsellor_not_offering_mid_range_never_appears_for_mid_range_search(): void
    {
        // TC1 does not offer Mid Range
        $tc1 = TrainingCounsellor::create([
            'tc_id' => 'QC001',
            'uuid' => (string) Str::uuid(),
            'name' => 'Coaching Only Counsellor',
            'email' => 'coaching@example.com',
            'counsellor_type' => 'Qualified',
            'status' => 'Active',
            'offers_mid_range' => false,
            'offers_coaching' => true,
        ]);

        // TC2 offers Mid Range
        $tc2 = TrainingCounsellor::create([
            'tc_id' => 'QC002',
            'uuid' => (string) Str::uuid(),
            'name' => 'Mid Range Counsellor',
            'email' => 'midrange@example.com',
            'counsellor_type' => 'Qualified',
            'status' => 'Active',
            'offers_mid_range' => true,
            'offers_coaching' => false,
        ]);

        $response = $this->postJson('/api/mid-range-intake/filtered-counsellors', [
            'service_type' => 'Mid Range',
        ]);

        $response->assertStatus(200);
        $data = $response->json();

        $this->assertEquals(1, $data['total']);
        $this->assertCount(1, $data['counsellors']);
        $this->assertEquals($tc2->uuid, $data['counsellors'][0]['uuid']);

        $uuids = collect($data['counsellors'])->pluck('uuid');
        $this->assertFalse($uuids->contains($tc1->uuid), 'Counsellor not offering Mid Range must never appear in Mid Range search');
    }

    public function test_with_nobody_offering_the_tier_the_list_is_empty(): void
    {
        // Active Qualified counsellor who offers neither
        TrainingCounsellor::create([
            'tc_id' => 'QC003',
            'uuid' => (string) Str::uuid(),
            'name' => 'Other Counsellor',
            'email' => 'other@example.com',
            'counsellor_type' => 'Qualified',
            'status' => 'Active',
            'offers_mid_range' => false,
            'offers_coaching' => false,
        ]);

        $response = $this->postJson('/api/mid-range-intake/filtered-counsellors', [
            'service_type' => 'Mid Range',
        ]);

        $response->assertStatus(200);
        $response->assertJson([
            'counsellors' => [],
            'total' => 0,
        ]);
    }

    public function test_counsellor_with_empty_bio_returns_null_and_no_stock_strings_appear_anywhere_in_backend(): void
    {
        $tc = TrainingCounsellor::create([
            'tc_id' => 'QC004',
            'uuid' => (string) Str::uuid(),
            'name' => 'Blank Profile Counsellor',
            'email' => 'blank@example.com',
            'counsellor_type' => 'Qualified',
            'status' => 'Active',
            'offers_mid_range' => true,
            'bio' => null,
            'modality' => null,
            'topics_with_experience' => null,
            'qualified_to_work_with' => null,
        ]);

        $response = $this->postJson('/api/mid-range-intake/filtered-counsellors', [
            'service_type' => 'Mid Range',
        ]);

        $response->assertStatus(200);
        $counsellorData = $response->json('counsellors.0');

        $this->assertNotNull($counsellorData);
        $this->assertNull($counsellorData['bio'], 'Empty bio must return null');
        $this->assertNull($counsellorData['modality'], 'Empty modality must return null, not default to Integrative');
        $this->assertNull($counsellorData['topics_with_experience'], 'Empty topics must return null, not sample list');
        $this->assertNull($counsellorData['qualified_to_work_with'], 'Empty qualified_to_work_with must return null, not default list');

        // Q10 removed fields must not exist in response
        $this->assertArrayNotHasKey('years_of_experience', $counsellorData);
        $this->assertArrayNotHasKey('qualification_title', $counsellorData);
        $this->assertArrayNotHasKey('education_credentials', $counsellorData);

        // Verify that the old stock strings do not appear in the backend response
        $jsonString = $response->getContent();
        $this->assertStringNotContainsString('I help individuals and couples navigate life challenges', $jsonString);
        $this->assertStringNotContainsString('Registered Counsellor (RPC)', $jsonString);
        $this->assertStringNotContainsString('Registered Professional Counsellor (RPC)', $jsonString);
        $this->assertStringNotContainsString('5+ years experience', $jsonString);

        // Verify across the backend app codebase that stock bio / stock RPC strings do not exist
        $appFiles = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(app_path()));
        foreach ($appFiles as $file) {
            if ($file->isFile() && $file->getExtension() === 'php') {
                $content = file_get_contents($file->getPathname());
                $this->assertStringNotContainsString(
                    'I help individuals and couples navigate life challenges',
                    $content,
                    "Stock bio text found in {$file->getFilename()}"
                );
                $this->assertStringNotContainsString(
                    'Registered Professional Counsellor (RPC)',
                    $content,
                    "Stock RPC title found in {$file->getFilename()}"
                );
            }
        }
    }
}
