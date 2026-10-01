<?php

namespace Tests\Feature;

use App\Mail\DynamicEmail;
use App\Models\TraineeApplication;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class TraineeAttendanceEmailsTest extends TestCase
{
    use RefreshDatabase;

    public function test_interview_not_attended_email_rendering(): void
    {
        $data = [
            'first_name' => 'Sarah',
        ];

        $mailable = new DynamicEmail('trainee_interview_not_attended', $data);

        $this->assertEquals('Interview Not Attended – Vanquish Therapies', $mailable->envelope()->subject);

        $html = $mailable->render();
        $this->assertStringContainsString('Sarah', $html);
        $this->assertStringContainsString('We are sorry you were not able to attend your interview, to comply with GPDR regulations, your information has been deleted, we wish you all the best in your journey to becoming a qualified counsellor', $html);
    }

    public function test_induction_not_attended_email_rendering(): void
    {
        $data = [
            'first_name' => 'Michael',
        ];

        $mailable = new DynamicEmail('trainee_induction_not_attended', $data);

        $this->assertEquals('Induction Not Attended – Vanquish Therapies', $mailable->envelope()->subject);

        $html = $mailable->render();
        $this->assertStringContainsString('Michael', $html);
        $this->assertStringContainsString('We are sorry you were not able to attend your induction, to comply with GPDR regulations, your information has been deleted, we wish you all the best in your journey to becoming a qualified counsellor', $html);
    }

    public function test_recording_interview_no_show_triggers_email(): void
    {
        Mail::fake();

        $admin = User::factory()->create(['role' => 'admin']);

        $application = TraineeApplication::create([
            'first_name' => 'Alice',
            'last_name' => 'Smith',
            'email' => 'alice.smith@example.com',
            'status' => 'Stage 3 Interview Booked',
        ]);

        $response = $this->actingAs($admin)
            ->postJson("/api/trainee-applications/{$application->id}/attendance", [
                'attended' => false,
                'notes' => 'Candidate did not connect to Zoom',
            ]);

        $response->assertStatus(200);
        $this->assertEquals('Interview No Show', $application->fresh()->status);

        Mail::assertSent(DynamicEmail::class, function ($mail) use ($application) {
            return $mail->hasTo($application->email) &&
                   $mail->template->type === 'trainee_interview_not_attended';
        });
    }

    public function test_recording_induction_no_show_triggers_email(): void
    {
        Mail::fake();

        $admin = User::factory()->create(['role' => 'admin']);

        $application = TraineeApplication::create([
            'first_name' => 'David',
            'last_name' => 'Jones',
            'email' => 'david.jones@example.com',
            'status' => 'Accepted',
        ]);

        $response = $this->actingAs($admin)
            ->postJson("/api/trainee-applications/{$application->id}/induction-attendance", [
                'attended' => false,
                'notes' => 'Did not join induction session',
            ]);

        $response->assertStatus(200);
        $this->assertEquals('Induction No-Show', $application->fresh()->status);

        Mail::assertSent(DynamicEmail::class, function ($mail) use ($application) {
            return $mail->hasTo($application->email) &&
                   $mail->template->type === 'trainee_induction_not_attended';
        });
    }

    public function test_status_update_to_interview_no_show_triggers_email(): void
    {
        Mail::fake();

        $admin = User::factory()->create(['role' => 'admin']);

        $application = TraineeApplication::create([
            'first_name' => 'Emma',
            'last_name' => 'Watson',
            'email' => 'emma.watson@example.com',
            'status' => 'Stage 3 Interview Booked',
        ]);

        $response = $this->actingAs($admin)
            ->postJson("/api/trainee-applications/{$application->id}/status", [
                'status' => 'Interview No Show',
            ]);

        $response->assertStatus(200);

        Mail::assertSent(DynamicEmail::class, function ($mail) use ($application) {
            return $mail->hasTo($application->email) &&
                   $mail->template->type === 'trainee_interview_not_attended';
        });
    }

    public function test_status_update_to_induction_no_show_triggers_email(): void
    {
        Mail::fake();

        $admin = User::factory()->create(['role' => 'admin']);

        $application = TraineeApplication::create([
            'first_name' => 'Oliver',
            'last_name' => 'Brown',
            'email' => 'oliver.brown@example.com',
            'status' => 'Accepted',
        ]);

        $response = $this->actingAs($admin)
            ->postJson("/api/trainee-applications/{$application->id}/status", [
                'status' => 'Induction No-Show',
            ]);

        $response->assertStatus(200);

        Mail::assertSent(DynamicEmail::class, function ($mail) use ($application) {
            return $mail->hasTo($application->email) &&
                   $mail->template->type === 'trainee_induction_not_attended';
        });
    }
}
