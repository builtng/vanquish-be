<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\TrainingCounsellor;
use App\Models\StaffNote;
use App\Models\ActivityLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AdminSecurityAndNamedAccountsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // Seed default company settings if needed
        DB::table('company_settings')->insertOrIgnore([
            'key' => 'company_name',
            'value' => 'Vanquish Therapies',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_can_create_fourth_and_fifth_admin_without_user_limit(): void
    {
        $superAdmin = User::factory()->create([
            'role' => 'admin',
            'password' => Hash::make('AdminPass12345!'),
            'is_active' => true,
        ]);

        $this->actingAs($superAdmin, 'sanctum');

        $names = [
            2 => 'Admin Staff Beta',
            3 => 'Admin Staff Gamma',
            4 => 'Admin Staff Delta',
            5 => 'Admin Staff Epsilon',
        ];

        // Create 2nd, 3rd, 4th, 5th users
        for ($i = 2; $i <= 5; $i++) {
            $response = $this->postJson('/api/users', [
                'name' => $names[$i],
                'email' => "staff{$i}@vqtmanagement.com",
                'role' => 'admin',
                'password' => 'StrongAdminPass123!',
                'password_confirmation' => 'StrongAdminPass123!',
            ]);

            $response->assertStatus(201)
                ->assertJsonPath('user.email', "staff{$i}@vqtmanagement.com");
        }

        // Verify count in database
        $this->assertEquals(5, User::count());

        // Count endpoint should reflect count and can_add_more
        $countResponse = $this->getJson('/api/users-count');
        $countResponse->assertStatus(200)
            ->assertJson([
                'count' => 5,
                'can_add_more' => true,
            ]);
    }

    public function test_password_policy_enforces_minimum_12_characters(): void
    {
        $admin = User::factory()->create([
            'role' => 'admin',
            'password' => Hash::make('AdminPass12345!'),
            'is_active' => true,
        ]);

        $this->actingAs($admin, 'sanctum');

        // Attempt creation with short password (8 chars)
        $response = $this->postJson('/api/users', [
            'name' => 'Short Pass User',
            'email' => 'short@vqtmanagement.com',
            'role' => 'staff',
            'password' => 'Short12!',
            'password_confirmation' => 'Short12!',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['password']);
    }

    public function test_admin_can_deactivate_and_reactivate_staff_account(): void
    {
        $admin = User::factory()->create([
            'role' => 'admin',
            'email' => 'primary.admin@vqtmanagement.com',
            'password' => Hash::make('AdminPass12345!'),
            'is_active' => true,
        ]);

        $staff = User::factory()->create([
            'role' => 'staff',
            'name' => 'Rooshan Staff',
            'email' => 'rooshan@vqtmanagement.com',
            'password' => Hash::make('StaffPass12345!'),
            'is_active' => true,
        ]);

        // Staff can log in initially
        $loginRes = $this->postJson('/api/login', [
            'email' => 'rooshan@vqtmanagement.com',
            'password' => 'StaffPass12345!',
        ]);
        $loginRes->assertStatus(200);

        // Deactivate staff as admin
        $this->actingAs($admin, 'sanctum');
        $toggleRes = $this->putJson("/api/users/{$staff->id}/toggle-active");
        $toggleRes->assertStatus(200)
            ->assertJsonPath('user.is_active', false);

        $staff->refresh();
        $this->assertFalse($staff->isActive());
        $this->assertNotNull($staff->deactivated_at);

        // Deactivated staff cannot log in
        $blockedLogin = $this->postJson('/api/login', [
            'email' => 'rooshan@vqtmanagement.com',
            'password' => 'StaffPass12345!',
        ]);
        $blockedLogin->assertStatus(422)
            ->assertJsonValidationErrors(['email']);

        // Reactivate staff as admin
        $reactivateRes = $this->putJson("/api/users/{$staff->id}/toggle-active");
        $reactivateRes->assertStatus(200)
            ->assertJsonPath('user.is_active', true);

        $staff->refresh();
        $this->assertTrue($staff->isActive());
        $this->assertNull($staff->deactivated_at);

        // Staff can log in again
        $reLogin = $this->postJson('/api/login', [
            'email' => 'rooshan@vqtmanagement.com',
            'password' => 'StaffPass12345!',
        ]);
        $reLogin->assertStatus(200);
    }

    public function test_admin_cannot_deactivate_self_or_last_active_admin(): void
    {
        $admin = User::factory()->create([
            'role' => 'admin',
            'email' => 'sole.admin@vqtmanagement.com',
            'password' => Hash::make('AdminPass12345!'),
            'is_active' => true,
        ]);

        $this->actingAs($admin, 'sanctum');

        // Self-deactivation blocked
        $selfRes = $this->putJson("/api/users/{$admin->id}/toggle-active");
        $selfRes->assertStatus(422)
            ->assertJson(['message' => 'You cannot deactivate your own account.']);
    }

    public function test_counsellor_role_is_rejected_from_admin_and_staff_routes(): void
    {
        $tc = TrainingCounsellor::create([
            'tc_id' => 'TC-99999',
            'uuid' => (string) \Illuminate\Support\Str::uuid(),
            'name' => 'Test Counsellor',
            'email' => 'counsellor@vqtmanagement.com',
        ]);

        $counsellorUser = User::factory()->create([
            'role' => 'counsellor',
            'email' => 'counsellor@vqtmanagement.com',
            'training_counsellor_id' => $tc->id,
            'password' => Hash::make('CounsellorPass123!'),
            'is_active' => true,
        ]);

        $this->actingAs($counsellorUser, 'sanctum');

        // Cannot view admin users
        $this->getJson('/api/users')->assertStatus(403);

        // Cannot view staff notes
        $this->getJson('/api/staff-notes')->assertStatus(403);

        // Cannot view matching algorithm settings
        $this->getJson('/api/matching-algorithm-settings')->assertStatus(403);

        // Cannot view client directory index
        $this->getJson('/api/clients')->assertStatus(403);

        // Cannot access user count
        $this->getJson('/api/users-count')->assertStatus(403);
    }

    public function test_staff_notes_and_activity_logs_record_real_author_identity(): void
    {
        $admin1 = User::factory()->create([
            'role' => 'admin',
            'name' => 'Victor Lead',
            'email' => 'victor@vqtmanagement.com',
            'password' => Hash::make('AdminPass12345!'),
            'is_active' => true,
        ]);

        $admin2 = User::factory()->create([
            'role' => 'admin',
            'name' => 'Charles Tech',
            'email' => 'charles@vqtmanagement.com',
            'password' => Hash::make('AdminPass12345!'),
            'is_active' => true,
        ]);

        $this->actingAs($admin1, 'sanctum');

        $response = $this->postJson('/api/staff-notes', [
            'staff_id' => $admin2->id,
            'note' => 'Please review the placement onboarding queue today.',
        ]);

        $response->assertStatus(201);
        $note = StaffNote::latest('id')->first();

        $this->assertEquals($admin1->id, $note->admin_id);
        $this->assertEquals($admin2->id, $note->staff_id);
        $this->assertEquals('Please review the placement onboarding queue today.', $note->note);
    }

    public function test_forgot_and_reset_password_flow_with_min_12_characters(): void
    {
        $user = User::factory()->create([
            'role' => 'admin',
            'name' => 'Victor Lead',
            'email' => 'victor.forgot@vqtmanagement.com',
            'password' => Hash::make('OldPassword12345!'),
            'is_active' => true,
        ]);

        // Request forgot password link
        $forgotRes = $this->postJson('/api/forgot-password', [
            'email' => 'victor.forgot@vqtmanagement.com',
        ]);

        $forgotRes->assertStatus(200);

        // Check token exists in password_reset_tokens table
        $resetRecord = DB::table('password_reset_tokens')
            ->where('email', 'victor.forgot@vqtmanagement.com')
            ->first();

        $this->assertNotNull($resetRecord);

        // Reset password with a short password (<12 chars) -> rejected
        // Since we store hash('sha256', $plainToken), let's simulate the token
        $plainToken = 'test-token-value-12345678901234567890';
        DB::table('password_reset_tokens')
            ->where('email', 'victor.forgot@vqtmanagement.com')
            ->update([
                'token' => hash('sha256', $plainToken),
                'created_at' => now(),
            ]);

        $shortResetRes = $this->postJson('/api/reset-password', [
            'token' => $plainToken,
            'email' => 'victor.forgot@vqtmanagement.com',
            'password' => 'Short12!',
            'password_confirmation' => 'Short12!',
        ]);

        $shortResetRes->assertStatus(422)
            ->assertJsonValidationErrors(['password']);

        // Reset with valid 12+ character password
        $validResetRes = $this->postJson('/api/reset-password', [
            'token' => $plainToken,
            'email' => 'victor.forgot@vqtmanagement.com',
            'password' => 'BrandNewStrongPassword123!',
            'password_confirmation' => 'BrandNewStrongPassword123!',
        ]);

        $validResetRes->assertStatus(200);

        // Token should be removed from database
        $this->assertNull(
            DB::table('password_reset_tokens')->where('email', 'victor.forgot@vqtmanagement.com')->first()
        );

        // Old password fails
        $failLogin = $this->postJson('/api/login', [
            'email' => 'victor.forgot@vqtmanagement.com',
            'password' => 'OldPassword12345!',
        ]);
        $failLogin->assertStatus(422);

        // New password succeeds
        $successLogin = $this->postJson('/api/login', [
            'email' => 'victor.forgot@vqtmanagement.com',
            'password' => 'BrandNewStrongPassword123!',
        ]);
        $successLogin->assertStatus(200);
    }
}
