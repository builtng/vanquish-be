<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Models\User;
use App\Models\TrainingCounsellor;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class TrainingCounsellorDocumentTest extends TestCase
{
    use RefreshDatabase;

    private function getAdminUser()
    {
        return User::create([
            'name' => 'Admin User',
            'email' => 'admin@test.com',
            'password' => bcrypt('password'),
            'role' => 'admin',
        ]);
    }

    private function getStaffUser()
    {
        return User::create([
            'name' => 'Staff User',
            'email' => 'staff@test.com',
            'password' => bcrypt('password'),
            'role' => 'staff',
        ]);
    }

    private function getClientUser()
    {
        return User::create([
            'name' => 'Client User',
            'email' => 'client@test.com',
            'password' => bcrypt('password'),
            'role' => 'client',
        ]);
    }

    public function test_unauthenticated_user_cannot_download_document()
    {
        $tc = TrainingCounsellor::create([
            'tc_id' => 'TC-10001',
            'uuid' => (string) Str::uuid(),
            'name' => 'Sarah Smith',
            'email' => 'sarah@test.com',
            'qualification_document' => 'qualified_counsellors/1/qualification_document/file.pdf',
        ]);

        $response = $this->getJson("/api/training-counsellors/{$tc->uuid}/document/qualification_document");

        $response->assertStatus(401);
    }

    public function test_non_staff_user_cannot_download_document()
    {
        $clientUser = $this->getClientUser();

        $tc = TrainingCounsellor::create([
            'tc_id' => 'TC-10002',
            'uuid' => (string) Str::uuid(),
            'name' => 'Sarah Smith',
            'email' => 'sarah@test.com',
            'qualification_document' => 'qualified_counsellors/1/qualification_document/file.pdf',
        ]);

        $response = $this->actingAs($clientUser, 'sanctum')
            ->getJson("/api/training-counsellors/{$tc->uuid}/document/qualification_document");

        $response->assertStatus(403);
    }

    public function test_staff_or_admin_can_download_document()
    {
        Storage::fake('public');

        $staff = $this->getStaffUser();

        $tc = TrainingCounsellor::create([
            'tc_id' => 'TC-10003',
            'uuid' => (string) Str::uuid(),
            'name' => 'Sarah Smith',
            'email' => 'sarah@test.com',
            'qualification_document' => 'qualified_counsellors/1/qualification_document/123456_abc123_certificate.pdf',
        ]);

        // Place a fake file
        Storage::disk('public')->put($tc->qualification_document, 'fake content');

        $response = $this->actingAs($staff, 'sanctum')
            ->getJson("/api/training-counsellors/{$tc->uuid}/document/qualification_document");

        $response->assertStatus(200);
        $response->assertHeader('content-disposition', 'attachment; filename=certificate.pdf');
    }

    public function test_returns_404_for_invalid_field()
    {
        $staff = $this->getStaffUser();

        $tc = TrainingCounsellor::create([
            'tc_id' => 'TC-10004',
            'uuid' => (string) Str::uuid(),
            'name' => 'Sarah Smith',
            'email' => 'sarah@test.com',
            'qualification_document' => 'qualified_counsellors/1/qualification_document/file.pdf',
        ]);

        $response = $this->actingAs($staff, 'sanctum')
            ->getJson("/api/training-counsellors/{$tc->uuid}/document/invalid_field_name");

        $response->assertStatus(400);
    }

    public function test_returns_404_if_file_not_found()
    {
        Storage::fake('public');

        $staff = $this->getStaffUser();

        $tc = TrainingCounsellor::create([
            'tc_id' => 'TC-10005',
            'uuid' => (string) Str::uuid(),
            'name' => 'Sarah Smith',
            'email' => 'sarah@test.com',
            'qualification_document' => 'qualified_counsellors/1/qualification_document/file.pdf',
        ]);

        // File is NOT on storage disk

        $response = $this->actingAs($staff, 'sanctum')
            ->getJson("/api/training-counsellors/{$tc->uuid}/document/qualification_document");

        $response->assertStatus(404);
    }
}
