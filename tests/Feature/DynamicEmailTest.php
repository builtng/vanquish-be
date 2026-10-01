<?php

namespace Tests\Feature;

use App\Mail\DynamicEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class DynamicEmailTest extends TestCase
{
    use RefreshDatabase;
    /**
     * Test that the Placement Acceptance email returns correct properties.
     */
    public function test_placement_acceptance_email_properties(): void
    {
        $data = [
            'first_name' => 'John',
            'induction_date' => 'Monday, 19th January',
            'induction_zoom_link' => 'https://zoom.us/j/test',
            'therapy_form_url' => 'https://form.jotform.com/241002800146035',
        ];

        $mailable = new DynamicEmail('trainee_placement_acceptance', $data);

        // Assert subject
        $envelope = $mailable->envelope();
        $this->assertEquals('Placement Acceptance', $envelope->subject);

        // Assert attachment
        $attachments = $mailable->attachments();
        $this->assertCount(1, $attachments);

        $attachment = $attachments[0];
        $this->assertEquals('4-way-agreement-trainee.docx', $attachment->as);
        $this->assertEquals(
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            $attachment->mime
        );

        // Assert content view and template rendering
        $content = $mailable->content();
        $this->assertEquals('emails.dynamic-layout', $content->view);

        $html = $mailable->render();
        $this->assertStringContainsString('John', $html);
        $this->assertStringContainsString('Monday, 19th January', $html);
    }
}
