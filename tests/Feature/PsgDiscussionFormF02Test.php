<?php

namespace Tests\Feature;

use App\Models\TrainingCounsellor;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class PsgDiscussionFormF02Test extends TestCase
{
    use RefreshDatabase;

    public function test_use_internal_psg_form_defaults_to_1_when_not_set()
    {
        // Ensure company_settings table does not have use_internal_psg_form
        DB::table('company_settings')->where('key', 'use_internal_psg_form')->delete();

        $response = $this->getJson('/api/company-settings');
        $response->assertStatus(200);

        $this->assertEquals('1', $response->json('use_internal_psg_form'));
    }

    public function test_counsellor_submits_psg_discussion_successfully()
    {
        $tc = TrainingCounsellor::create([
            'name' => 'Sara Counsellor',
            'first_name' => 'Sara',
            'last_name' => 'Counsellor',
            'email' => 'sara.counsellor@example.com',
            'status' => 'active',
        ]);

        $user = User::create([
            'name' => 'Sara Counsellor',
            'email' => 'sara.counsellor@example.com',
            'password' => bcrypt('password123'),
            'role' => 'counsellor',
            'training_counsellor_id' => $tc->id,
        ]);

        $response = $this->actingAs($user)->postJson('/api/psg-reflections', [
            'discussion' => 'Discussed challenging client dynamic and transference.',
            'attendance_date' => '2026-10-14',
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('reflection', 'Discussed challenging client dynamic and transference.')
            ->assertJsonPath('status', 'submitted');

        $this->assertDatabaseHas('psg_reflections', [
            'training_counsellor_id' => $tc->id,
            'reflection' => 'Discussed challenging client dynamic and transference.',
        ]);
    }

    public function test_non_counsellor_gets_discussion_wording_error()
    {
        $admin = User::create([
            'name' => 'Admin User',
            'email' => 'admin.psg@example.com',
            'password' => bcrypt('password123'),
            'role' => 'admin',
        ]);

        $response = $this->actingAs($admin)->postJson('/api/psg-reflections', [
            'discussion' => 'Test',
            'attendance_date' => '2026-10-14',
        ]);

        $response->assertStatus(403)
            ->assertJson([
                'message' => 'Only counsellors can submit discussions',
            ]);
    }
}
