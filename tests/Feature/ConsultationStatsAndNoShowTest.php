<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Consultation;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ConsultationStatsAndNoShowTest extends TestCase
{
    use RefreshDatabase;

    public function test_past_consultation_not_in_upcoming_and_is_in_past_not_completed()
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $client = Client::create([
            'client_id' => 'CL-STAT01',
            'name'      => 'Alice Past',
            'email'     => 'alice.past@example.com',
        ]);

        $nowUk = Carbon::now('Europe/London');

        // 1. Scheduled yesterday (past)
        $pastConsultation = Consultation::create([
            'consultation_id' => 'CONS-YEST',
            'client_id'       => $client->id,
            'status'          => 'scheduled',
            'scheduled_at'    => $nowUk->copy()->subDay(),
        ]);

        // 2. Scheduled tomorrow (future)
        $futureConsultation = Consultation::create([
            'consultation_id' => 'CONS-TOMO',
            'client_id'       => $client->id,
            'status'          => 'scheduled',
            'scheduled_at'    => $nowUk->copy()->addDay(),
        ]);

        $response = $this->actingAs($admin)->getJson('/api/consultation-stats');
        $response->assertStatus(200);

        $data = $response->json();

        // Past consultation must NOT be in upcoming_count
        // Only future consultation should be in upcoming_count
        $this->assertEquals(1, $data['upcoming_count'], 'Upcoming count should only count future scheduled consultations');

        // Past consultation MUST be in past_not_completed_count
        $this->assertEquals(1, $data['past_not_completed_count'], 'Past not completed count should count past scheduled consultations');
    }

    public function test_stats_today_and_this_week_respect_uk_timezone()
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $client = Client::create([
            'client_id' => 'CL-STAT02',
            'name'      => 'Bob UK',
            'email'     => 'bob.uk@example.com',
        ]);

        $nowUk = Carbon::now('Europe/London');

        // Consultation scheduled today in UK time
        Consultation::create([
            'consultation_id' => 'CONS-TODAY',
            'client_id'       => $client->id,
            'status'          => 'scheduled',
            'scheduled_at'    => $nowUk->copy()->startOfDay()->addHours(14), // 14:00 UK today
        ]);

        $response = $this->actingAs($admin)->getJson('/api/consultation-stats');
        $response->assertStatus(200);

        $data = $response->json();
        $this->assertEquals(1, $data['today_count']);
        $this->assertEquals(1, $data['this_week_count']);
    }

    public function test_admin_can_mark_consultation_as_did_not_attend()
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $client = Client::create([
            'client_id' => 'CL-STAT03',
            'name'      => 'Charlie DNA',
            'email'     => 'charlie.dna@example.com',
        ]);

        $nowUk = Carbon::now('Europe/London');

        $consultation = Consultation::create([
            'consultation_id' => 'CONS-DNA',
            'client_id'       => $client->id,
            'status'          => 'scheduled',
            'scheduled_at'    => $nowUk->copy()->subDays(2),
        ]);

        // Prior to marking no-show, stats show 1 in past_not_completed
        $resBefore = $this->actingAs($admin)->getJson('/api/consultation-stats');
        $this->assertEquals(1, $resBefore->json('past_not_completed_count'));

        // Post to no-show endpoint
        $resNoShow = $this->actingAs($admin)->postJson("/api/consultations/{$consultation->id}/no-show");
        $resNoShow->assertStatus(200);

        $this->assertEquals('no_show', $consultation->fresh()->status);

        // After marking no-show, consultation is no longer scheduled, so leaves past_not_completed_count
        $resAfter = $this->actingAs($admin)->getJson('/api/consultation-stats');
        $this->assertEquals(0, $resAfter->json('past_not_completed_count'));
    }
}
