<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\EmailTemplate;
use App\Mail\DynamicEmail;
use App\Http\Controllers\Api\EmailTemplateController;
use Illuminate\Foundation\Testing\RefreshDatabase;

class TraineeStageTwoInviteWordingTest extends TestCase
{
    use RefreshDatabase;

    public function test_email_template_controller_default_contains_a_few_questions()
    {
        $controller = new EmailTemplateController();
        $ref = new \ReflectionClass($controller);
        $method = $ref->getMethod('getDefaults');
        $method->setAccessible(true);
        $defaults = $method->invoke($controller);

        $this->assertArrayHasKey('trainee_stage_two_invite', $defaults);
        $body = $defaults['trainee_stage_two_invite']['body'];

        $this->assertStringContainsString('a few questions', $body);
        $this->assertStringNotContainsString('five simple questions', $body);
        $this->assertStringNotContainsString('5 structured questions', $body);
    }

    public function test_dynamic_email_default_contains_a_few_questions()
    {
        $dynamicEmail = new DynamicEmail('trainee_stage_two_invite', []);
        $ref = new \ReflectionClass($dynamicEmail);
        $method = $ref->getMethod('getDefaults');
        $method->setAccessible(true);
        $defaults = $method->invoke($dynamicEmail);

        $this->assertArrayHasKey('trainee_stage_two_invite', $defaults);
        $body = $defaults['trainee_stage_two_invite']['body'];

        $this->assertStringContainsString('a few questions', $body);
        $this->assertStringNotContainsString('five simple questions', $body);
        $this->assertStringNotContainsString('5 structured questions', $body);
    }

    public function test_migration_and_database_template_contains_a_few_questions()
    {
        // Seed or create template with previous phrasing
        $template = EmailTemplate::create([
            'type' => 'trainee_stage_two_invite',
            'name' => 'Trainee: Stage Two Invite',
            'subject' => 'Congratulations! You Have Progressed to Stage 2 – Video Interview',
            'body' => '<p>We are inviting you to answer 5 structured questions - there are no trick questions.</p>',
            'placeholders' => ['first_name', 'interview_url'],
            'version' => 1,
        ]);

        // Run the migration logic
        $migration = require database_path('migrations/2026_10_14_120000_update_trainee_stage_two_invite_to_a_few_questions.php');
        $migration->up();

        $fresh = $template->fresh();
        $this->assertStringContainsString('a few questions', $fresh->body);
        $this->assertStringNotContainsString('5 structured questions', $fresh->body);
        $this->assertStringNotContainsString('five simple questions', $fresh->body);
    }
}
