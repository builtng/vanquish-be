<?php

namespace Tests\Feature;

use App\Models\TrainingCounsellor;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class UserRolesAndPortalInviteSafetyTest extends TestCase
{
    use RefreshDatabase;

    private function newUserPayload(string $role, string $email): array
    {
        return [
            'name' => 'Rachel Green',
            'email' => $email,
            'password' => 'StrongPassword123',
            'password_confirmation' => 'StrongPassword123',
            'role' => $role,
        ];
    }

    public function test_admin_can_create_consultation_staff_and_compliance_officer(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        foreach (['consultation_staff', 'compliance_officer', 'staff', 'admin'] as $role) {
            $this->actingAs($admin)
                ->postJson('/api/users', $this->newUserPayload($role, "{$role}@example.com"))
                ->assertStatus(201)
                ->assertJsonPath('user.role', $role);
        }
    }

    public function test_only_a_super_admin_can_create_or_change_a_super_admin(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $superAdmin = User::factory()->create(['role' => 'super_admin']);

        $this->actingAs($admin)
            ->postJson('/api/users', $this->newUserPayload('super_admin', 'new.super@example.com'))
            ->assertStatus(403);

        $this->actingAs($admin)
            ->putJson("/api/users/{$superAdmin->id}", ['role' => 'staff'])
            ->assertStatus(403);
        $this->assertSame('super_admin', $superAdmin->fresh()->role);

        $this->actingAs($admin)
            ->deleteJson("/api/users/{$superAdmin->id}")
            ->assertStatus(403);

        $this->actingAs($superAdmin)
            ->postJson('/api/users', $this->newUserPayload('super_admin', 'new.super@example.com'))
            ->assertStatus(201)
            ->assertJsonPath('user.role', 'super_admin');
    }

    public function test_super_admin_can_manage_users(): void
    {
        $superAdmin = User::factory()->create(['role' => 'super_admin']);

        $this->actingAs($superAdmin)->getJson('/api/users')->assertStatus(200);
    }

    public function test_tc_portal_invite_does_not_change_a_staff_login(): void
    {
        Mail::fake();
        $admin = User::factory()->create(['role' => 'admin']);
        $staff = User::factory()->create(['role' => 'compliance_officer', 'email' => 'chandler@example.com']);
        $tc = TrainingCounsellor::create([
            'tc_id' => 'TC901',
            'name' => 'Chandler Bing',
            'email' => 'chandler@example.com',
            'status' => 'Active',
        ]);

        $this->actingAs($admin)
            ->postJson("/api/training-counsellors/{$tc->id}/portal-invite")
            ->assertStatus(409);

        $response = $this->actingAs($admin)
            ->postJson('/api/training-counsellors/bulk-portal-invite', ['ids' => [(string) $tc->id]])
            ->assertStatus(200);
        $this->assertCount(1, $response->json('results.failed'));

        $staff->refresh();
        $this->assertSame('compliance_officer', $staff->role);
        $this->assertNull($staff->training_counsellor_id);
    }
}
