<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\ConsultationSlot;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Carbon\Carbon;

class ConsultationSlotRecurringTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_create_weekly_recurring_consultation_slots()
    {
        $user = User::factory()->create(['role' => 'admin']);

        $startDate = Carbon::tomorrow()->format('Y-m-d');
        $payload = [
            'days_of_week' => ['mon', 'wed'],
            'intervals' => [
                ['start_time' => '18:00', 'end_time' => '18:15'],
                ['start_time' => '18:25', 'end_time' => '18:40'],
                ['start_time' => '18:50', 'end_time' => '19:05'],
            ],
            'start_date' => $startDate,
            'weeks_count' => 2,
            'max_slots' => 1,
            'type' => 'consultation',
        ];

        $response = $this->actingAs($user)->postJson('/api/consultation-slots/recurring', $payload);

        $response->assertStatus(201);
        $this->assertGreaterThan(0, ConsultationSlot::count());
        $this->assertDatabaseHas('consultation_slots', [
            'type' => 'consultation',
            'max_slots' => 1,
            'status' => 'available',
        ]);
    }

    public function test_recurring_skips_duplicates()
    {
        $user = User::factory()->create(['role' => 'admin']);

        // Find upcoming Monday
        $nextMonday = Carbon::now()->next(Carbon::MONDAY);
        
        ConsultationSlot::create([
            'consultation_datetime' => $nextMonday->copy()->setTime(18, 0),
            'max_slots' => 1,
            'status' => 'available',
            'booked_slots' => 0,
            'type' => 'consultation',
        ]);

        $payload = [
            'days_of_week' => ['mon'],
            'intervals' => [
                ['start_time' => '18:00', 'end_time' => '18:15'],
                ['start_time' => '18:25', 'end_time' => '18:40'],
            ],
            'start_date' => $nextMonday->format('Y-m-d'),
            'weeks_count' => 1,
            'max_slots' => 1,
            'type' => 'consultation',
        ];

        $response = $this->actingAs($user)->postJson('/api/consultation-slots/recurring', $payload);
        $response->assertStatus(201);

        $json = $response->json();
        $this->assertEquals(1, $json['created_count']);
        $this->assertEquals(1, $json['skipped_count']);
    }
}
