<?php

namespace Tests\Feature;

use App\Models\Induction;
use App\Models\TrainingCounsellor;
use App\Models\User;
use App\Mail\DynamicEmail;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class InductionDurationTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_create_induction_without_conducted_by_and_with_duration(): void
    {
        Mail::fake();

        $admin = User::factory()->create([
            'role' => 'admin',
        ]);

        $tc = TrainingCounsellor::create([
            'tc_id' => 'TC-001',
            'name' => 'Jane Trainee',
            'email' => 'jane@example.com',
            'status' => 'Active',
        ]);

        $scheduledAt = Carbon::now()->addDays(2)->setHour(10)->setMinute(0)->setSecond(0)->format('Y-m-d H:i:s');

        $response = $this->actingAs($admin)->postJson('/api/inductions', [
            'scheduled_at' => $scheduledAt,
            'duration_minutes' => 60,
            'location' => 'Zoom Room 1',
            'notes' => 'Bring notes and ID',
            'attendee_tc_ids' => [$tc->id],
        ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('inductions', [
            'location' => 'Zoom Room 1',
            'tc_id' => null,
            'notes' => 'Bring notes and ID',
        ]);

        $induction = Induction::first();
        $this->assertNotNull($induction);
        $this->assertNull($induction->tc_id);
        $this->assertEquals(
            Carbon::parse($scheduledAt)->addMinutes(60)->toDateTimeString(),
            $induction->scheduled_end_at->toDateTimeString()
        );

        Mail::assertSent(DynamicEmail::class, function ($mail) use ($tc) {
            return $mail->hasTo($tc->email) &&
                str_contains($mail->data['induction_date'], '10:00 - 11:00') &&
                $mail->data['start_time'] === '10:00' &&
                $mail->data['end_time'] === '11:00';
        });
    }

    public function test_can_create_induction_with_direct_scheduled_end_at(): void
    {
        Mail::fake();

        $admin = User::factory()->create([
            'role' => 'admin',
        ]);

        $tc = TrainingCounsellor::create([
            'tc_id' => 'TC-002',
            'name' => 'Bob Trainee',
            'email' => 'bob@example.com',
            'status' => 'Active',
        ]);

        $scheduledAt = Carbon::now()->addDays(3)->setHour(14)->setMinute(0)->setSecond(0)->format('Y-m-d H:i:s');
        $scheduledEndAt = Carbon::now()->addDays(3)->setHour(15)->setMinute(30)->setSecond(0)->format('Y-m-d H:i:s');

        $response = $this->actingAs($admin)->postJson('/api/inductions', [
            'scheduled_at' => $scheduledAt,
            'scheduled_end_at' => $scheduledEndAt,
            'location' => 'Conference Room B',
            'attendee_tc_ids' => [$tc->id],
        ]);

        $response->assertStatus(201);

        $induction = Induction::first();
        $this->assertEquals(
            Carbon::parse($scheduledEndAt)->toDateTimeString(),
            $induction->scheduled_end_at->toDateTimeString()
        );

        Mail::assertSent(DynamicEmail::class, function ($mail) use ($tc) {
            return $mail->hasTo($tc->email) &&
                str_contains($mail->data['induction_date'], '14:00 - 15:30') &&
                $mail->data['start_time'] === '14:00' &&
                $mail->data['end_time'] === '15:30';
        });
    }
}
