<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Models\User;

class SignUpRouteRemovedSecurityTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_register_route_is_completely_removed()
    {
        $response = $this->postJson('/api/register', [
            'name' => 'Attacker User',
            'email' => 'attacker@example.com',
            'password' => 'Password12345!',
            'password_confirmation' => 'Password12345!',
            'role' => 'admin',
        ]);

        // Route should not exist (404 or 405)
        $this->assertTrue(in_array($response->status(), [404, 405]), "Expected 404 or 405 but received {$response->status()}");
        $this->assertDatabaseMissing('users', ['email' => 'attacker@example.com']);
    }

    public function test_admin_can_still_create_users_via_authenticated_endpoint()
    {
        $admin = User::create([
            'name' => 'Admin User',
            'email' => 'admin@example.com',
            'password' => bcrypt('password'),
            'role' => 'admin',
        ]);

        $response = $this->actingAs($admin)->postJson('/api/users', [
            'name' => 'New Staff Member',
            'email' => 'staff.member@example.com',
            'password' => 'SecurePass1234!',
            'password_confirmation' => 'SecurePass1234!',
            'role' => 'staff',
        ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('users', ['email' => 'staff.member@example.com', 'role' => 'staff']);
    }

    public function test_non_admin_cannot_create_users_via_admin_endpoint()
    {
        $counsellor = User::create([
            'name' => 'Counsellor User',
            'email' => 'counsellor@example.com',
            'password' => bcrypt('password'),
            'role' => 'counsellor',
        ]);

        $response = $this->actingAs($counsellor)->postJson('/api/users', [
            'name' => 'Unauthorized Staff',
            'email' => 'unauth.staff@example.com',
            'password' => 'SecurePass1234!',
            'role' => 'staff',
        ]);

        $response->assertStatus(403);
        $this->assertDatabaseMissing('users', ['email' => 'unauth.staff@example.com']);
    }
}
