<?php

namespace Tests\Feature;

use App\Models\ConsultationSlot;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ConsultationSlotZoomLeakFix7Test extends TestCase
{
    use RefreshDatabase;

    public function test_available_slots_endpoint_does_not_leak_zoom_links_or_meeting_ids(): void
    {
        $slot = ConsultationSlot::create([
            'consultation_datetime' => Carbon::now()->addDays(2)->setTime(14, 0),
            'status' => 'available',
            'type' => 'Standard Consultation',
            'max_slots' => 1,
            'booked_slots' => 0,
            'zoom_link' => 'https://zoom.us/j/98765432101?pwd=SecretMeetingPassword123',
            'host_name' => 'Confidential Host',
        ]);

        $response = $this->getJson('/api/consultation-slots/available?order=asc');

        $response->assertStatus(200);

        $slots = $response->json();
        $this->assertNotEmpty($slots);

        $first = $slots[0];

        // Must contain id, datetime, type, availability
        $this->assertEquals($slot->id, $first['id']);
        $this->assertArrayHasKey('datetime', $first);
        $this->assertArrayHasKey('consultation_datetime', $first);
        $this->assertEquals('Standard Consultation', $first['type']);
        $this->assertArrayHasKey('is_available', $first);
        $this->assertTrue($first['is_available']);

        // Must NOT leak Zoom link, meeting ID, passcode, or confidential host details
        $this->assertArrayNotHasKey('zoom_link', $first);
        $this->assertArrayNotHasKey('meeting_id', $first);
        $this->assertArrayNotHasKey('passcode', $first);
        $this->assertArrayNotHasKey('host_name', $first);
        $this->assertStringNotContainsString('SecretMeetingPassword123', $response->getContent());
        $this->assertStringNotContainsString('https://zoom.us', $response->getContent());
    }
}
