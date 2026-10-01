<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Models\User;
use App\Models\AttendanceGroup;

class AttendanceGroupTest extends TestCase
{
    use RefreshDatabase;

    private function getStaffUser()
    {
        return User::create([
            'name' => 'Staff Test',
            'email' => 'staff@test.com',
            'password' => bcrypt('password'),
            'role' => 'staff',
        ]);
    }

    public function test_staff_can_create_attendance_group_with_valid_data()
    {
        $staff = $this->getStaffUser();

        $payload = [
            'name' => 'Group 6',
            'supervisor_link' => 'https://form.jotform.com/123456789',
            'day_of_week' => 'Tuesday',
        ];

        $response = $this->actingAs($staff, 'sanctum')
            ->postJson('/api/attendance-groups', $payload);

        $response->assertStatus(201)
            ->assertJsonPath('name', 'Group 6')
            ->assertJsonPath('supervisor_link', 'https://form.jotform.com/123456789')
            ->assertJsonPath('day_of_week', 'Tuesday');

        $this->assertDatabaseHas('attendance_groups', [
            'name' => 'Group 6',
            'supervisor_link' => 'https://form.jotform.com/123456789',
            'day_of_week' => 'Tuesday',
        ]);
    }

    public function test_create_attendance_group_validation_fails_for_invalid_url()
    {
        $staff = $this->getStaffUser();

        $payload = [
            'name' => 'Group 6',
            'supervisor_link' => 'not-a-valid-url',
            'day_of_week' => 'Tuesday',
        ];

        $response = $this->actingAs($staff, 'sanctum')
            ->postJson('/api/attendance-groups', $payload);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['supervisor_link']);
    }

    public function test_create_attendance_group_fails_when_nullable_fields_are_omitted()
    {
        $staff = $this->getStaffUser();

        // Omit supervisor_link and day_of_week
        $payload = [
            'name' => 'Group 6',
        ];

        $response = $this->actingAs($staff, 'sanctum')
            ->postJson('/api/attendance-groups', $payload);

        // If the database columns are NOT NULL, this should throw a database error (500)
        // because the controller validation passes (they are nullable) but the DB insert fails.
        // Let's see what happens.
        $status = $response->getStatusCode();
        $this->assertEquals(201, $status, "Expected 201 if they are optional in both validation and DB, but got " . $status . ". Response: " . $response->getContent());
    }

    public function test_staff_can_toggle_attendance_group_status()
    {
        $staff = $this->getStaffUser();
        $group = AttendanceGroup::create([
            'name' => 'Test Group ActiveToggle',
            'day_of_week' => 'Wednesday',
            'is_active' => true,
        ]);

        $this->assertTrue($group->is_active);

        // Toggle to inactive
        $response1 = $this->actingAs($staff, 'sanctum')
            ->patchJson("/api/attendance-groups/{$group->id}/toggle-status");

        $response1->assertStatus(200)
            ->assertJsonPath('is_active', false);

        $this->assertFalse($group->fresh()->is_active);

        // Toggle back to active
        $response2 = $this->actingAs($staff, 'sanctum')
            ->patchJson("/api/attendance-groups/{$group->id}/toggle-status");

        $response2->assertStatus(200)
            ->assertJsonPath('is_active', true);

        $this->assertTrue($group->fresh()->is_active);
    }
}
