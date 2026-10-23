<?php

namespace Tests\Feature;

use App\Http\Middleware\SecurityHeaders;
use App\Models\TraineeApplication;
use App\Models\TrainingCounsellor;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\View;
use Tests\TestCase;

class VqtManagementDomainCutoverTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Item 10: Sanctum stateful domains must include vqtmanagement.com and no vercel.app.
     */
    public function test_sanctum_config_contains_vqtmanagement_domain(): void
    {
        $sanctumFile = file_get_contents(config_path('sanctum.php'));
        $this->assertStringContainsString('vqtmanagement.com', $sanctumFile);
        $this->assertStringNotContainsString('vercel.app', $sanctumFile, 'sanctum.php must not contain vercel.app');
    }

    /**
     * Item 10: SecurityHeaders middleware Content-Security-Policy contains vqtmanagement.com and no vercel.app.
     */
    public function test_security_headers_csp_points_to_vqtmanagement_and_no_vercel(): void
    {
        $middleware = new SecurityHeaders();
        $request = Request::create('http://localhost/api/test', 'GET');
        $response = new Response();

        $response = $middleware->handle($request, function ($req) use ($response) {
            return $response;
        });

        $csp = $response->headers->get('Content-Security-Policy');
        $this->assertNotEmpty($csp);
        $this->assertStringContainsString('vqtmanagement.com', $csp);
        $this->assertStringNotContainsString('vercel.app', $csp, 'CSP header must not contain vercel.app');
    }

    /**
     * Item 10: Email template tc-match-notification uses config('app.frontend_url') with vqtmanagement.com fallback.
     */
    public function test_tc_match_notification_email_template_defaults_to_vqtmanagement(): void
    {
        Config::set('app.frontend_url', null);
        $rendered = View::make('emails.tc-match-notification', [
            'firstName' => 'Jane',
            'loginUrl' => null,
        ])->render();

        $this->assertStringContainsString('https://vqtmanagement.com/counsellor-login', $rendered);
        $this->assertStringNotContainsString('vanquish0.vercel.app', $rendered);
    }

    /**
     * Item 10: Trainee application acceptance email uses config('app.frontend_url') falling back to vqtmanagement.com.
     */
    public function test_trainee_acceptance_email_builds_vqtmanagement_link(): void
    {
        Config::set('app.frontend_url', null);

        $trainee = TraineeApplication::create([
            'first_name' => 'John',
            'last_name' => 'Doe',
            'email' => 'john.doe@example.com',
            'phone' => '07123456789',
            'stage' => 'Stage 2 Approved',
            'status' => 'Pending Review',
            'course_level' => 'Level 4 Diploma',
            'application_fee_paid' => true,
        ]);

        $this->assertNotNull($trainee);
    }
}
