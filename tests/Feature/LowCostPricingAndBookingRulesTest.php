<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\ServiceSetting;
use App\Models\Session;
use App\Models\TrainingCounsellor;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class LowCostPricingAndBookingRulesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
    }

    public function test_service_settings_contain_correct_pricing_for_all_services()
    {
        // 1. Low Cost: consultation £13, session £18, block of 4 £72
        $lowCost = ServiceSetting::where('service_name', 'Low Cost')->first();
        $this->assertNotNull($lowCost);
        $this->assertEquals(13.00, (float) $lowCost->consultation_price);
        $this->assertEquals(18.00, (float) $lowCost->session_price);
        $this->assertEquals(72.00, (float) $lowCost->block_price);

        // 2. Ish: consultation £25
        $ish = ServiceSetting::where('service_name', 'Ish')->first();
        $this->assertNotNull($ish);
        $this->assertEquals(25.00, (float) $ish->consultation_price);

        // 3. Mid Range: consultation £15, single session £40, block of 4 £140 (£35/session)
        $midRange = ServiceSetting::where('service_name', 'Mid Range')->first();
        $this->assertNotNull($midRange);
        $this->assertEquals(15.00, (float) $midRange->consultation_price);
        $this->assertEquals(40.00, (float) $midRange->session_price);
        $this->assertEquals(140.00, (float) $midRange->block_price);

        // 4. Counselling & Coaching: consultation £20
        $coaching = ServiceSetting::where('service_name', 'Counselling & Coaching')->first();
        $this->assertNotNull($coaching);
        $this->assertEquals(20.00, (float) $coaching->consultation_price);
    }

    public function test_low_cost_client_cannot_book_block_without_admin_slot_assignment()
    {
        $tc = TrainingCounsellor::create([
            'tc_id' => 'TC-TEST-LC1',
            'name' => 'Trainee One',
            'email' => 'tc1@example.com',
            'status' => 'Active',
            'counsellor_type' => 'Trainee',
            'availability' => ['monday' => ['10am-1050am']],
        ]);

        $client = Client::create([
            'client_id' => 'CL-LC1',
            'name' => 'Low Cost Client',
            'email' => 'client1@example.com',
            'service_type' => 'Low Cost',
            'matched_tc_id' => $tc->id,
            'agreement_status' => 'signed',
            'status' => 'Active',
            'allocated_day' => null,
            'allocated_time' => null,
        ]);

        $response = $this->postJson('/api/client-booking/book-block', [
            'client_uuid' => $client->uuid,
            'sessions_count' => 4,
        ]);

        $response->assertStatus(422);
        $response->assertJsonFragment([
            'message' => 'Your regular weekly session time has not been assigned by our admin team yet. Please contact support.',
        ]);
    }

    public function test_low_cost_client_auto_slots_use_admin_assigned_day_and_time()
    {
        $tc = TrainingCounsellor::create([
            'tc_id' => 'TC-TEST-LC2',
            'name' => 'Trainee Two',
            'email' => 'tc2@example.com',
            'status' => 'Active',
            'counsellor_type' => 'Trainee',
            'availability' => [
                'monday' => ['10am-1050am'],
            ],
        ]);

        $client = Client::create([
            'client_id' => 'CL-LC2',
            'name' => 'Low Cost Client Two',
            'email' => 'client2@example.com',
            'service_type' => 'Low Cost',
            'matched_tc_id' => $tc->id,
            'agreement_status' => 'signed',
            'status' => 'Active',
            'allocated_day' => 'Monday',
            'allocated_time' => '10am-1050am',
        ]);

        $response = $this->postJson('/api/client-booking/book-block', [
            'client_uuid' => $client->uuid,
            'sessions_count' => 4,
        ]);

        $response->assertStatus(201);
        $data = $response->json();
        $this->assertCount(4, $data['session_ids']);
        $this->assertFalse($data['penalty_applied']);

        // Check created sessions were placed on Monday at 10:00
        $sessions = Session::whereIn('id', $data['session_ids'])->get();
        $this->assertCount(4, $sessions);

        foreach ($sessions as $session) {
            $carbon = Carbon::parse($session->scheduled_at);
            $this->assertEquals('Monday', $carbon->format('l'));
            $this->assertEquals('10:00', $carbon->format('H:i'));
            $this->assertEquals($tc->id, $session->tc_id);
            $this->assertEquals($client->id, $session->client_id);
        }
    }

    public function test_low_cost_client_cannot_manually_override_slots()
    {
        $tc = TrainingCounsellor::create([
            'tc_id' => 'TC-TEST-LC3',
            'name' => 'Trainee Three',
            'email' => 'tc3@example.com',
            'status' => 'Active',
            'counsellor_type' => 'Trainee',
            'availability' => [
                'monday' => ['10am-1050am'],
                'friday' => ['2pm-250pm'],
            ],
        ]);

        $client = Client::create([
            'client_id' => 'CL-LC3',
            'name' => 'Low Cost Client Three',
            'email' => 'client3@example.com',
            'service_type' => 'Low Cost',
            'matched_tc_id' => $tc->id,
            'agreement_status' => 'signed',
            'status' => 'Active',
            'allocated_day' => 'Monday',
            'allocated_time' => '10am-1050am',
        ]);

        // Attempting to send custom Friday slots in request payload
        $arbitraryFriday1 = Carbon::now()->next(Carbon::FRIDAY)->format('Y-m-d 14:00:00');
        $arbitraryFriday2 = Carbon::now()->next(Carbon::FRIDAY)->addWeek()->format('Y-m-d 14:00:00');
        $arbitraryFriday3 = Carbon::now()->next(Carbon::FRIDAY)->addWeeks(2)->format('Y-m-d 14:00:00');
        $arbitraryFriday4 = Carbon::now()->next(Carbon::FRIDAY)->addWeeks(3)->format('Y-m-d 14:00:00');

        $response = $this->postJson('/api/client-booking/book-block', [
            'client_uuid' => $client->uuid,
            'sessions_count' => 4,
            'session_slots' => [
                $arbitraryFriday1,
                $arbitraryFriday2,
                $arbitraryFriday3,
                $arbitraryFriday4,
            ],
        ]);

        $response->assertStatus(201);
        $data = $response->json();

        // Must still be allocated on Monday at 10:00, completely ignoring user submitted manual slots
        $sessions = Session::whereIn('id', $data['session_ids'])->get();
        foreach ($sessions as $session) {
            $carbon = Carbon::parse($session->scheduled_at);
            $this->assertEquals('Monday', $carbon->format('l'));
            $this->assertEquals('10:00', $carbon->format('H:i'));
        }
    }

    public function test_48_hour_deadline_penalty_reduces_block_to_3_sessions()
    {
        $tc = TrainingCounsellor::create([
            'tc_id' => 'TC-TEST-LC4',
            'name' => 'Trainee Four',
            'email' => 'tc4@example.com',
            'status' => 'Active',
            'counsellor_type' => 'Trainee',
            'availability' => [
                'wednesday' => ['11am-1150am'],
            ],
        ]);

        $client = Client::create([
            'client_id' => 'CL-LC4',
            'name' => 'Low Cost Client Four',
            'email' => 'client4@example.com',
            'service_type' => 'Low Cost',
            'matched_tc_id' => $tc->id,
            'agreement_status' => 'signed',
            'status' => 'Active',
            'allocated_day' => 'Wednesday',
            'allocated_time' => '11am-1150am',
            // Deadline was yesterday (passed)
            'next_booking_deadline' => Carbon::now()->subDay()->format('Y-m-d H:i:s'),
        ]);

        $response = $this->postJson('/api/client-booking/book-block', [
            'client_uuid' => $client->uuid,
            'sessions_count' => 4,
        ]);

        $response->assertStatus(201);
        $data = $response->json();

        // Penalty applied: 3 sessions instead of 4
        $this->assertCount(3, $data['session_ids']);
        $this->assertTrue($data['penalty_applied']);
    }

    public function test_admin_can_assign_and_update_allocated_day_and_time()
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $client = Client::create([
            'client_id' => 'CL-LC5',
            'name' => 'Low Cost Client Five',
            'email' => 'client5@example.com',
            'service_type' => 'Low Cost',
            'status' => 'Active',
        ]);

        $response = $this->actingAs($admin)->putJson("/api/clients/{$client->uuid}", [
            'allocated_day' => 'Thursday',
            'allocated_time' => '3pm-350pm',
        ]);

        $response->assertStatus(200);

        $client->refresh();
        $this->assertEquals('Thursday', $client->allocated_day);
        $this->assertEquals('3pm-350pm', $client->allocated_time);
    }

    public function test_send_booking_reminders_targets_clients_3_days_before_deadline()
    {
        $tc = TrainingCounsellor::create([
            'tc_id' => 'TC-TEST-LC6',
            'name' => 'Trainee Six',
            'email' => 'tc6@example.com',
            'status' => 'Active',
            'counsellor_type' => 'Trainee',
            'availability' => ['tuesday' => ['10am-1050am']],
        ]);

        // Client whose deadline is 3 days away (Y-m-d format matching date column)
        $deadlineIn3Days = Carbon::now()->addDays(3)->format('Y-m-d');
        $sessionDate = Carbon::parse($deadlineIn3Days)->addHours(48);

        $client = Client::create([
            'client_id' => 'CL-LC6',
            'name' => 'Remind Me Client',
            'email' => 'remindme@example.com',
            'service_type' => 'Low Cost',
            'matched_tc_id' => $tc->id,
            'agreement_status' => 'signed',
            'status' => 'Active',
            'allocated_day' => 'Tuesday',
            'allocated_time' => '10am-1050am',
            'next_booking_deadline' => $deadlineIn3Days,
        ]);

        // Create the session holding the deadline
        $session = Session::create([
            'client_id' => $client->id,
            'tc_id' => $tc->id,
            'session_type' => 'individual',
            'scheduled_at' => $sessionDate,
            'booking_deadline' => $deadlineIn3Days,
            'booking_reminder_sent' => false,
            'status' => 'scheduled',
            'payment_status' => 'paid',
        ]);

        $exitCode = Artisan::call('bookings:send-reminders');
        $this->assertEquals(0, $exitCode);

        $session->refresh();
        $this->assertTrue((bool) $session->booking_reminder_sent);
    }
}
