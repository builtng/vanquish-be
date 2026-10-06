<?php

namespace Tests\Feature;

use App\Models\ConsultationDayOff;
use App\Models\ConsultationSlot;
use App\Models\Coupon;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Prompt 9 – Discount code, payment pop-up and consultation slot management (5.4 to 5.6)
 *
 * Tests:
 *   1. Case-insensitive discount codes ("FREE", "Free", " free ").
 *   2. Inactive/expired/invalid codes return clear message:
 *      "This code is not valid. Please check it or continue to payment."
 *   3. Duplicate consultation slots are blocked at API and database unique constraint level.
 *   4. Bulk creation of four weeks produces the exact expected number of slots.
 *   5. Preview recurring returns accurate count and dates.
 *   6. Marking a day off removes unbooked slots and prevents new slots on that date.
 */
class DiscountCodeAndConsultationSlotsTest extends TestCase
{
    use RefreshDatabase;

    // ─────────────────────────────────────────────────────────────────────────
    // 1 & 2: DISCOUNT CODES
    // ─────────────────────────────────────────────────────────────────────────

    public function test_discount_codes_are_case_insensitive_and_trimmed()
    {
        Coupon::create([
            'code' => 'FREE',
            'type' => 'percent',
            'value' => 100,
            'is_active' => true,
        ]);

        $variants = ['FREE', 'Free', 'free', '  free  ', ' FrEe '];

        foreach ($variants as $code) {
            $response = $this->postJson('/api/coupons/verify', ['code' => $code]);
            $response->assertStatus(200);
            $this->assertEquals('FREE', $response->json('code'));
            $this->assertEquals(100, (float)$response->json('value'));
        }
    }

    public function test_invalid_coupon_returns_standard_error_message()
    {
        $response = $this->postJson('/api/coupons/verify', ['code' => 'NONEXISTENT']);

        $response->assertStatus(404);
        $response->assertJson([
            'message' => 'This code is not valid. Please check it or continue to payment.'
        ]);
    }

    public function test_inactive_or_expired_coupon_returns_standard_error_message()
    {
        // Inactive coupon
        Coupon::create([
            'code' => 'INACTIVE10',
            'type' => 'percent',
            'value' => 10,
            'is_active' => false,
        ]);

        $response1 = $this->postJson('/api/coupons/verify', ['code' => 'inactive10']);
        $response1->assertStatus(422);
        $response1->assertJson([
            'message' => 'This code is not valid. Please check it or continue to payment.'
        ]);

        // Expired coupon
        Coupon::create([
            'code' => 'EXPIRED20',
            'type' => 'percent',
            'value' => 20,
            'is_active' => true,
            'starts_at' => now()->subDays(10),
            'expires_at' => now()->subDay(),
        ]);

        $response2 = $this->postJson('/api/coupons/verify', ['code' => 'expired20']);
        $response2->assertStatus(422);
        $response2->assertJson([
            'message' => 'This code is not valid. Please check it or continue to payment.'
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // 3: DUPLICATE SLOTS (STOPPED AT DB & API LEVEL)
    // ─────────────────────────────────────────────────────────────────────────

    public function test_duplicate_slots_are_blocked_at_api_level()
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $dt = Carbon::now()->addDays(3)->setTime(14, 0, 0);

        // First creation succeeds
        $res1 = $this->actingAs($admin)->postJson('/api/consultation-slots', [
            'consultation_datetime' => $dt->toDateTimeString(),
            'max_slots' => 1,
            'type' => 'consultation',
        ]);
        $res1->assertStatus(201);

        // Second creation of same datetime & type is rejected with 422
        $res2 = $this->actingAs($admin)->postJson('/api/consultation-slots', [
            'consultation_datetime' => $dt->toDateTimeString(),
            'max_slots' => 1,
            'type' => 'consultation',
        ]);
        $res2->assertStatus(422);
        $res2->assertJsonFragment([
            'message' => 'A consultation slot for this date and time already exists.'
        ]);

        $this->assertEquals(1, ConsultationSlot::where('consultation_datetime', $dt)->count());
    }

    public function test_duplicate_slots_are_blocked_at_database_level()
    {
        $dt = Carbon::now()->addDays(4)->setTime(10, 0, 0);

        ConsultationSlot::create([
            'consultation_datetime' => $dt,
            'max_slots' => 1,
            'booked_slots' => 0,
            'status' => 'available',
            'type' => 'consultation',
        ]);

        $this->expectException(\Illuminate\Database\QueryException::class);

        // Direct DB attempt to insert twin slot must throw Unique constraint violation
        ConsultationSlot::create([
            'consultation_datetime' => $dt,
            'max_slots' => 1,
            'booked_slots' => 0,
            'status' => 'available',
            'type' => 'consultation',
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // 4 & 5: BULK CREATION OF FOUR WEEKS & PREVIEW
    // ─────────────────────────────────────────────────────────────────────────

    public function test_bulk_creation_of_four_weeks_produces_the_right_number_of_slots()
    {
        $admin = User::factory()->create(['role' => 'admin']);

        // Start on next Monday
        $startDate = Carbon::now()->next(Carbon::MONDAY);

        // 2 days per week (Mon, Wed)
        // 18:00 to 19:30 with 30m slots and 10m break:
        // Slot 1: 18:00 - 18:30 (next 18:40)
        // Slot 2: 18:40 - 19:10 (next 19:20 -> 19:20+30m > 19:30 so 2 slots)
        // Total slots = 2 slots/day * 2 days/week * 4 weeks = 16 slots
        $payload = [
            'days_of_week' => ['mon', 'wed'],
            'start_time' => '18:00',
            'end_time' => '19:30',
            'slot_length' => 30,
            'break_minutes' => 10,
            'start_date' => $startDate->format('Y-m-d'),
            'weeks_count' => 4,
            'max_slots' => 1,
            'type' => 'consultation',
        ];

        // 1. Check Preview
        $previewRes = $this->actingAs($admin)->postJson('/api/consultation-slots/preview', $payload);
        $previewRes->assertStatus(200);
        $this->assertEquals(16, $previewRes->json('total_count'));

        // 2. Check Store
        $storeRes = $this->actingAs($admin)->postJson('/api/consultation-slots/recurring', $payload);
        $storeRes->assertStatus(201);
        $this->assertEquals(16, $storeRes->json('created_count'));

        $this->assertEquals(16, ConsultationSlot::where('type', 'consultation')->count());
    }

    // ─────────────────────────────────────────────────────────────────────────
    // 6: DAYS OFF REMOVE UNBOOKED SLOTS AND BLOCK NEW ONES
    // ─────────────────────────────────────────────────────────────────────────

    public function test_days_off_removes_unbooked_slots_and_blocks_new_slots()
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $nextFriday = Carbon::now()->next(Carbon::FRIDAY);

        // Pre-create 2 unbooked slots on next Friday
        $slot1 = ConsultationSlot::create([
            'consultation_datetime' => $nextFriday->copy()->setTime(10, 0),
            'max_slots' => 1,
            'booked_slots' => 0,
            'status' => 'available',
            'type' => 'consultation',
        ]);
        $slot2 = ConsultationSlot::create([
            'consultation_datetime' => $nextFriday->copy()->setTime(11, 0),
            'max_slots' => 1,
            'booked_slots' => 0,
            'status' => 'available',
            'type' => 'consultation',
        ]);

        $this->assertEquals(2, ConsultationSlot::whereDate('consultation_datetime', $nextFriday->toDateString())->count());

        // Mark next Friday as a Day Off
        $dayOffRes = $this->actingAs($admin)->postJson('/api/consultation-days-off', [
            'date' => $nextFriday->format('Y-m-d'),
            'reason' => 'Bank Holiday',
        ]);

        $dayOffRes->assertStatus(201);
        $this->assertEquals(2, $dayOffRes->json('removed_slots'));

        // Verify unbooked slots were deleted
        $this->assertEquals(0, ConsultationSlot::whereDate('consultation_datetime', $nextFriday->toDateString())->count());
        $this->assertDatabaseHas('consultation_days_off', [
            'date' => $nextFriday->format('Y-m-d'),
            'reason' => 'Bank Holiday',
        ]);

        // Attempting to create a single slot on this day off is rejected
        $singleRes = $this->actingAs($admin)->postJson('/api/consultation-slots', [
            'consultation_datetime' => $nextFriday->copy()->setTime(14, 0)->toDateTimeString(),
            'max_slots' => 1,
            'type' => 'consultation',
        ]);
        $singleRes->assertStatus(422);
        $singleRes->assertJsonFragment([
            'message' => 'Cannot create slot: ' . $nextFriday->format('Y-m-d') . ' is marked as a day off.'
        ]);

        // Recurring slot creation spanning next Friday skips that day
        $payload = [
            'days_of_week' => ['fri'],
            'start_time' => '10:00',
            'end_time' => '11:00',
            'slot_length' => 30,
            'start_date' => $nextFriday->format('Y-m-d'),
            'weeks_count' => 2,
            'type' => 'consultation',
        ];

        $recurringRes = $this->actingAs($admin)->postJson('/api/consultation-slots/recurring', $payload);
        $recurringRes->assertStatus(201);

        // Next Friday had 2 slots that were skipped because of day off, the following Friday has 2 slots created
        $this->assertEquals(2, $recurringRes->json('created_count'));
        $this->assertEquals(1, $recurringRes->json('days_off_skipped_count'));
        $createdSlotDate = Carbon::parse($recurringRes->json('slots.0.consultation_datetime'))->toDateString();
        $this->assertEquals($nextFriday->copy()->addWeek()->toDateString(), $createdSlotDate);
    }
}
