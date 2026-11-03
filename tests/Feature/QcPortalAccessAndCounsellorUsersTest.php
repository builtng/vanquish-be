<?php

namespace Tests\Feature;

use App\Mail\DynamicEmail;
use App\Models\QcApplication;
use App\Models\TrainingCounsellor;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class QcPortalAccessAndCounsellorUsersTest extends TestCase
{
    use RefreshDatabase;

    private function acceptedQcApplication(array $overrides = []): QcApplication
    {
        return QcApplication::create(array_merge([
            'legal_first_name' => 'Monica',
            'legal_last_name'  => 'Geller',
            'name'             => 'Monica Geller',
            'email'            => 'monica@example.com',
            'phone'            => '07123456789',
            'status'           => 'Induction Attended',
        ], $overrides));
    }

    public function test_grant_portal_access_creates_qualified_practitioner_and_login_and_emails_invite(): void
    {
        Mail::fake();
        $admin = User::factory()->create(['role' => 'admin']);
        $qcApp = $this->acceptedQcApplication();

        $this->actingAs($admin)
            ->postJson("/api/qc-applications/{$qcApp->id}/portal-invite")
            ->assertStatus(200)
            ->assertJson(['account_created' => true]);

        $qcApp->refresh();
        $this->assertSame('Onboarding', $qcApp->status);
        $this->assertTrue($qcApp->onboarding_data['portal_access_granted']);
        $this->assertNotEmpty($qcApp->onboarding_data['portal_invite_sent_at']);

        $tc = TrainingCounsellor::find($qcApp->training_counsellor_id);
        $this->assertSame('Qualified', $tc->counsellor_type);
        $this->assertStringStartsWith('QC', $tc->tc_id);

        $user = User::where('email', 'monica@example.com')->first();
        $this->assertSame('counsellor', $user->role);
        $this->assertSame($tc->id, $user->training_counsellor_id);

        Mail::assertSent(DynamicEmail::class, fn ($mail) => $mail->hasTo('monica@example.com')
            && $mail->template->type === 'counsellor_portal_invite');
    }

    public function test_grant_portal_access_never_downgrades_a_staff_account(): void
    {
        Mail::fake();
        $admin = User::factory()->create(['role' => 'admin']);
        User::factory()->create(['role' => 'staff', 'email' => 'monica@example.com']);
        $qcApp = $this->acceptedQcApplication();

        $this->actingAs($admin)
            ->postJson("/api/qc-applications/{$qcApp->id}/portal-invite")
            ->assertStatus(409);

        $this->assertSame('staff', User::where('email', 'monica@example.com')->first()->role);
    }

    public function test_finalize_requires_portal_access_first(): void
    {
        Mail::fake();
        $admin = User::factory()->create(['role' => 'admin']);
        $qcApp = $this->acceptedQcApplication();

        $this->actingAs($admin)->postJson("/api/qc-applications/{$qcApp->id}/finalize")->assertStatus(422);

        $this->actingAs($admin)->postJson("/api/qc-applications/{$qcApp->id}/portal-invite")->assertStatus(200);
        $this->actingAs($admin)->postJson("/api/qc-applications/{$qcApp->id}/finalize")->assertStatus(200);

        $this->assertSame('Active Placement', $qcApp->fresh()->status);
    }

    public function test_admin_can_create_trainee_and_qualified_counsellor_users(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $password = 'StrongPassword123';

        foreach (['trainee_counsellor' => 'Trainee', 'qualified_counsellor' => 'Qualified'] as $choice => $type) {
            $email = "{$choice}@example.com";

            $this->actingAs($admin)->postJson('/api/users', [
                'name' => 'Phoebe Buffay',
                'email' => $email,
                'password' => $password,
                'password_confirmation' => $password,
                'role' => $choice,
            ])->assertStatus(201)->assertJsonPath('user.role_choice', $choice);

            $user = User::where('email', $email)->first();
            $this->assertSame('counsellor', $user->role);
            $this->assertSame($type, TrainingCounsellor::find($user->training_counsellor_id)->counsellor_type);
        }

        $listed = collect($this->actingAs($admin)->getJson('/api/users')->assertStatus(200)->json());
        $this->assertSame('Qualified', $listed->firstWhere('email', 'qualified_counsellor@example.com')['counsellor_type']);
        $this->assertSame('Trainee', $listed->firstWhere('email', 'trainee_counsellor@example.com')['counsellor_type']);
    }

    public function test_admin_can_switch_a_counsellor_between_trainee_and_qualified(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $password = 'StrongPassword123';

        $this->actingAs($admin)->postJson('/api/users', [
            'name' => 'Joey Tribbiani',
            'email' => 'joey@example.com',
            'password' => $password,
            'password_confirmation' => $password,
            'role' => 'trainee_counsellor',
        ])->assertStatus(201);

        $user = User::where('email', 'joey@example.com')->first();

        $this->actingAs($admin)
            ->putJson("/api/users/{$user->id}", ['role' => 'qualified_counsellor'])
            ->assertStatus(200)
            ->assertJsonPath('user.counsellor_type', 'Qualified');

        $this->assertSame(1, TrainingCounsellor::where('email', 'joey@example.com')->count());
    }
}
