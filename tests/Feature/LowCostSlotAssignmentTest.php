<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\TrainingCounsellor;
use App\Models\User;
use App\Models\Session;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LowCostSlotAssignmentTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
    }

    public function test_assign_match_saves_allocated_day_and_time_for_low_cost_client()
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $tc = TrainingCounsellor::create([
            'tc_id' => 'TC-TEST-01',
            'name' => 'Dr. Jane Test',
            'email' => 'jane.test@example.com',
            'status' => 'Active',
            'counsellor_type' => 'Trainee',
            'current_clients' => 0,
            'availability' => [
                'monday' => ['10am-1050am', '11am-1150am'],
                'wednesday' => ['2pm-250pm'],
            ],
        ]);

        $client = Client::create([
            'client_id' => 'CL-TEST-01',
            'name' => 'John Client',
            'email' => 'john.client@example.com',
            'service_type' => 'Low Cost',
            'stage' => 'Pending Match',
            'status' => 'Active',
            'availability' => [
                'monday' => ['10am-1050am', '1pm-150pm'],
                'friday' => ['10am-1050am'],
            ],
        ]);

        $response = $this->actingAs($admin)->postJson('/api/matches', [
            'client_id' => $client->uuid,
            'tc_id' => $tc->uuid,
            'allocated_day' => 'Monday',
            'allocated_time' => '10am-1050am',
            'assignment_notes' => 'Matching based on Monday 10am overlap.',
            'send_notification' => false,
        ]);

        $response->assertStatus(201);

        $client->refresh();
        $this->assertEquals($tc->id, $client->matched_tc_id);
        $this->assertEquals('Monday', $client->allocated_day);
        $this->assertEquals('10am-1050am', $client->allocated_time);
        $this->assertEquals('Matched with TC', $client->stage);
    }

    public function test_get_slot_bookings_returns_accurate_client_counts_and_details()
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $tc = TrainingCounsellor::create([
            'tc_id' => 'TC-TEST-02',
            'name' => 'David Counsellor',
            'email' => 'david.c@example.com',
            'status' => 'Active',
            'counsellor_type' => 'Trainee',
            'current_clients' => 2,
            'availability' => [
                'monday' => ['10am-1050am', '11am-1150am'],
                'tuesday' => ['2pm-250pm'],
            ],
        ]);

        // Create 2 clients on Monday 10am-1050am
        $client1 = Client::create([
            'client_id' => 'CL-001',
            'name' => 'Alice Smith',
            'email' => 'alice@example.com',
            'service_type' => 'Low Cost',
            'matched_tc_id' => $tc->id,
            'allocated_day' => 'Monday',
            'allocated_time' => '10am-1050am',
            'status' => 'Active',
        ]);

        $client2 = Client::create([
            'client_id' => 'CL-002',
            'name' => 'Bob Jones',
            'email' => 'bob@example.com',
            'service_type' => 'Low Cost',
            'matched_tc_id' => $tc->id,
            'allocated_day' => 'Monday',
            'allocated_time' => '10am-1050am',
            'status' => 'Active',
        ]);

        // Create 1 client on Tuesday 2pm-250pm
        $client3 = Client::create([
            'client_id' => 'CL-003',
            'name' => 'Charlie Brown',
            'email' => 'charlie@example.com',
            'service_type' => 'Low Cost',
            'matched_tc_id' => $tc->id,
            'allocated_day' => 'Tuesday',
            'allocated_time' => '2pm-250pm',
            'status' => 'Active',
        ]);

        $response = $this->actingAs($admin)->getJson("/api/training-counsellors/{$tc->uuid}/slot-bookings");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'tc' => ['id', 'uuid', 'name', 'availability', 'current_clients'],
                'slots',
                'active_clients_count',
            ]);

        $data = $response->json();
        $this->assertEquals(3, $data['active_clients_count']);

        // Check Monday 10am slot has 2 bookings
        $mondaySlotBookings = $data['slots']['monday']['10am-1050am'];
        $this->assertCount(2, $mondaySlotBookings);
        $names = array_column($mondaySlotBookings, 'name');
        $this->assertContains('Alice Smith', $names);
        $this->assertContains('Bob Jones', $names);

        // Check Tuesday 2pm slot has 1 booking
        $tuesdaySlotBookings = $data['slots']['tuesday']['2pm-250pm'];
        $this->assertCount(1, $tuesdaySlotBookings);
        $this->assertEquals('Charlie Brown', $tuesdaySlotBookings[0]['name']);
    }

    public function test_admin_cannot_assign_non_overlapping_slot_for_low_cost_client()
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $tc = TrainingCounsellor::create([
            'tc_id' => 'TC-TEST-NO-OVERLAP',
            'name' => 'Dr. Jane Trainee',
            'email' => 'jane.trainee@example.com',
            'status' => 'Active',
            'counsellor_type' => 'Trainee',
            'availability' => [
                'monday' => ['10am-1050am'],
            ],
        ]);

        $client = Client::create([
            'client_id' => 'CL-TEST-NO-OVERLAP',
            'name' => 'John Client',
            'email' => 'john.client.no@example.com',
            'service_type' => 'Low Cost',
            'stage' => 'Pending Match',
            'status' => 'Active',
            'availability' => [
                'friday' => ['2pm-250pm'],
            ],
        ]);

        // Attempting to assign Monday 10am which client did not select
        $response = $this->actingAs($admin)->postJson('/api/matches', [
            'client_id' => $client->uuid,
            'tc_id' => $tc->uuid,
            'allocated_day' => 'Monday',
            'allocated_time' => '10am-1050am',
            'send_notification' => false,
        ]);

        $response->assertStatus(422);
        $response->assertJsonFragment([
            'message' => 'Assigned session time must be within both the client\'s and the counsellor\'s availability.',
        ]);
    }

    public function test_N05_low_cost_cannot_choose_time()
    {
        $tc = TrainingCounsellor::create([
            'tc_id' => 'TC-N05',
            'name' => 'Trainee Counsellor N05',
            'email' => 'tc-n05@example.com',
            'status' => 'Active',
            'counsellor_type' => 'Trainee',
            'availability' => [
                'monday' => ['10am-1050am'],
                'wednesday' => ['2pm-250pm'],
            ],
        ]);

        $client = Client::create([
            'client_id' => 'CL-N05',
            'name' => 'Low Cost Client N05',
            'email' => 'client-n05@example.com',
            'service_type' => 'Low Cost',
            'matched_tc_id' => $tc->id,
            'status' => 'Active',
            'allocated_day' => 'Monday',
            'allocated_time' => '10am-1050am',
        ]);

        // 1. Refuses if client attempts to choose a different time slot
        $resTime = $this->postJson('/api/client-booking/book-block', [
            'client_uuid' => $client->uuid,
            'sessions_count' => 4,
            'time_slot' => '2pm-250pm',
        ]);
        $resTime->assertStatus(422);
        $resTime->assertJsonFragment([
            'message' => 'Low Cost session times are set by Vanquish. You cannot choose a different session time.',
        ]);

        // 2. Refuses if client attempts to choose a different day via start_date
        $nextWednesday = Carbon::now()->next(Carbon::WEDNESDAY)->format('Y-m-d');
        $resDay = $this->postJson('/api/client-booking/book-block', [
            'client_uuid' => $client->uuid,
            'sessions_count' => 4,
            'start_date' => $nextWednesday,
        ]);
        $resDay->assertStatus(422);
        $resDay->assertJsonFragment([
            'message' => 'Low Cost session times are set by Vanquish. You cannot choose a different session time.',
        ]);

        // 3. Refuses if custom session_slots on a different day/time are passed
        $nextWednesdaySlot = Carbon::now()->next(Carbon::WEDNESDAY)->format('Y-m-d 14:00:00');
        $resSlots = $this->postJson('/api/client-booking/book-block', [
            'client_uuid' => $client->uuid,
            'sessions_count' => 4,
            'session_slots' => [$nextWednesdaySlot],
        ]);
        $resSlots->assertStatus(422);
        $resSlots->assertJsonFragment([
            'message' => 'Low Cost session times are set by Vanquish. You cannot choose a different session time.',
        ]);

        // 4. Refuses if Low Cost client calls single-session bookSession
        $resSingle = $this->postJson('/api/client-booking/book-session', [
            'client_uuid' => $client->uuid,
            'scheduled_at' => Carbon::now()->addDays(3)->format('Y-m-d 10:00:00'),
            'session_type' => 'mid_range',
        ]);
        $resSingle->assertStatus(422);
        $resSingle->assertJsonFragment([
            'message' => 'Low Cost sessions must be booked in blocks of 4.',
        ]);

        // 5. Accepts when booking without overriding time (or with slots matching assigned time)
        $resValid = $this->postJson('/api/client-booking/book-block', [
            'client_uuid' => $client->uuid,
            'sessions_count' => 4,
        ]);
        $resValid->assertStatus(201);
        $data = $resValid->json();
        $this->assertCount(4, $data['session_ids']);

        $sessions = Session::whereIn('id', $data['session_ids'])->get();
        $this->assertCount(4, $sessions);
        foreach ($sessions as $session) {
            $carbon = Carbon::parse($session->scheduled_at);
            $this->assertEquals('Monday', $carbon->format('l'));
            $this->assertEquals('10:00', $carbon->format('H:i'));
        }
    }
}
