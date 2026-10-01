<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\TraineeApplication;
use App\Models\ActivityLog;
use App\Mail\DynamicEmail;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use App\Services\EmailService;

/**
 * HireVireWebhookController
 *
 * Handles incoming webhook callbacks from HireVire after a candidate
 * completes their video interview.
 *
 * HireVire interview link: https://app.hirevire.com/applications/091820fa-6fef-45e0-97e8-d714fc0b27cf
 *
 * ──────────────────────────────────────────────────────────────────────────
 * SETUP INSTRUCTIONS (HireVire Dashboard → Settings → Integrations):
 *   1. Webhook URL:  POST {APP_URL}/api/hirevire/webhook
 *   2. Events:       application.completed
 *   3. Secret:       Set HIREVIRE_WEBHOOK_SECRET in .env
 *   4. Custom field: In the HireVire form, add a hidden "email" or "uuid"
 *      custom question so we can match submissions back to our DB.
 *      Alternatively, HireVire passes the applicant email via `email` field.
 * ──────────────────────────────────────────────────────────────────────────
 *
 * Candidate identification priority:
 *   1. email field in payload
 *   2. applicant.email in nested payload
 *   3. custom_fields containing email-like value
 */
class HireVireWebhookController extends Controller
{
    protected $emailService;

    public function __construct(EmailService $emailService)
    {
        $this->emailService = $emailService;
    }
    /**
     * Handle HireVire video submission webhook.
     *
     * POST /api/hirevire/webhook
     */
    public function handleSubmission(Request $request): \Illuminate\Http\JsonResponse
    {
        try {
            Log::info('HireVire Webhook Received', [
                'ip'      => $request->ip(),
                'payload' => $request->all(),
            ]);

            $payload = $request->all();

            // ── 1. Optional webhook signature verification ─────────────────
            $secret = config('services.hirevire.webhook_secret');
            if ($secret) {
                $signature = $request->header('X-HireVire-Signature')
                    ?? $request->header('X-Webhook-Signature')
                    ?? '';
                $expected = hash_hmac('sha256', $request->getContent(), $secret);
                if (!hash_equals($expected, $signature)) {
                    Log::warning('HireVire Webhook: Invalid signature', ['ip' => $request->ip()]);
                    return response()->json(['message' => 'Invalid signature'], 401);
                }
            }

            // ── 2. Only process completed video submissions ─────────────────
            $event = $payload['event'] ?? $payload['type'] ?? '';
            if ($event && !in_array($event, ['application.completed', 'submission.completed', 'interview.completed', ''])) {
                return response()->json(['message' => "Event '{$event}' ignored"], 200);
            }

            // ── 3. Locate candidate email ───────────────────────────────────
            $email = $this->extractEmail($payload);
            if (!$email) {
                Log::warning('HireVire Webhook: Could not resolve applicant email', ['payload' => $payload]);
                return response()->json(['message' => 'Applicant email not found'], 200);
            }

            $email = strtolower(trim($email));

            // ── 4. Find matching trainee application ────────────────────────
            $application = TraineeApplication::where('email', $email)->first();
            if (!$application) {
                // Try UUID if passed as custom field or query param
                $uuid = $this->extractUuid($payload);
                if ($uuid) {
                    $application = TraineeApplication::where('uuid', $uuid)->first();
                }
            }

            if (!$application) {
                Log::warning('HireVire Webhook: No matching trainee application found', [
                    'email'   => $email,
                    'payload' => $payload,
                ]);
                return response()->json(['message' => 'No matching application found for this candidate'], 200);
            }

            // ── 5. Build interview data from HireVire payload ───────────────
            $hirevireData = $this->extractInterviewData($payload);
            $hirevireData['submitted_at'] = now()->toIso8601String();
            $hirevireData['hirevire_applicant_id'] = $payload['applicant_id']
                ?? $payload['id']
                ?? $payload['application_id']
                ?? null;

            // ── 6. Update application record ────────────────────────────────
            $application->update([
                'status'       => 'Stage 2 Video Submitted',
                'video_url'    => $hirevireData['video_url'] ?? $application->video_url,
                'interview_data' => array_merge(
                    is_array($application->interview_data) ? $application->interview_data : [],
                    ['hirevire' => $hirevireData]
                ),
            ]);

            // ── 7. Activity log ─────────────────────────────────────────────
            ActivityLog::create([
                'user_id'     => null,
                'action'      => 'hirevire_video_submitted',
                'model_type'  => TraineeApplication::class,
                'model_id'    => $application->id,
                'description' => "Stage 2 HireVire video interview submitted by {$application->first_name} {$application->last_name}",
                'changes'     => $hirevireData,
                'ip_address'  => $request->ip(),
                'user_agent'  => $request->userAgent(),
            ]);

            Log::info('HireVire Webhook: Application updated', [
                'application_id' => $application->id,
                'email'          => $email,
                'status'         => 'Stage 2 Video Submitted',
            ]);

            // ── 8. Step 5: Email the trainee (confirmation) ─────────────────
            $this->emailService->sendAndLog($application->email, 'trainee_video_interview_received', [
                'first_name' => $application->first_name,
                'full_name'  => $application->first_name . ' ' . $application->last_name,
            ], $application);

            // ── 9. Step 5: Notify admin(s) to review the video ─────────────
            $adminEmail = config('mail.admin_email', config('mail.from.address'));
            if ($adminEmail) {
                $this->emailService->sendAndLog($adminEmail, 'admin_video_review_notification', [
                    'applicant_name'  => $application->first_name . ' ' . $application->last_name,
                    'applicant_email' => $application->email,
                    'submitted_at'    => now()->format('l, j F Y \a\t H:i'),
                    'dashboard_url'   => config('app.frontend_url', config('app.url'))
                        . '/dashboard/trainee-applications/' . $application->id,
                ]);
            }

            return response()->json([
                'message' => 'Video submission processed successfully',
                'status'  => 'Stage 2 Video Submitted',
            ], 200);

        } catch (\Exception $e) {
            Log::error('HireVire Webhook Error', [
                'error'   => $e->getMessage(),
                'trace'   => $e->getTraceAsString(),
                'payload' => $request->all(),
            ]);

            return response()->json([
                'message' => 'Error processing submission',
                'error'   => config('app.debug') ? $e->getMessage() : 'Internal error',
            ], 200); // Always 200 so HireVire stops retrying
        }
    }

    /**
     * Extract applicant email from various HireVire payload structures.
     */
    private function extractEmail(array $payload): ?string
    {
        // Common top-level keys
        $candidates = [
            $payload['email']                        ?? null,
            $payload['applicant_email']              ?? null,
            $payload['candidate_email']              ?? null,
            $payload['applicant']['email']           ?? null,
            $payload['candidate']['email']           ?? null,
            $payload['application']['email']         ?? null,
            $payload['application']['applicant']['email'] ?? null,
            $payload['data']['email']                ?? null,
            $payload['data']['applicant']['email']   ?? null,
        ];

        foreach ($candidates as $val) {
            if ($val && filter_var(trim($val), FILTER_VALIDATE_EMAIL)) {
                return trim($val);
            }
        }

        // Search custom_fields array
        foreach (['custom_fields', 'fields', 'answers'] as $key) {
            if (isset($payload[$key]) && is_array($payload[$key])) {
                foreach ($payload[$key] as $field) {
                    $value = $field['value'] ?? $field['answer'] ?? '';
                    if (is_string($value) && filter_var(trim($value), FILTER_VALIDATE_EMAIL)) {
                        return trim($value);
                    }
                }
            }
        }

        // Brute-force scan all string values in the top-level payload
        foreach ($payload as $value) {
            if (is_string($value) && filter_var(trim($value), FILTER_VALIDATE_EMAIL)) {
                return trim($value);
            }
        }

        return null;
    }

    /**
     * Extract a UUID from the payload (if passed as custom field).
     */
    private function extractUuid(array $payload): ?string
    {
        $uuidPattern = '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i';

        $candidates = [
            $payload['uuid']        ?? null,
            $payload['external_id'] ?? null,
            $payload['reference']   ?? null,
            $payload['metadata']['uuid'] ?? null,
            $payload['data']['uuid'] ?? null,
        ];

        foreach ($candidates as $val) {
            if ($val && preg_match($uuidPattern, trim($val))) {
                return trim($val);
            }
        }

        // Scan custom fields
        foreach (['custom_fields', 'fields', 'answers'] as $key) {
            if (isset($payload[$key]) && is_array($payload[$key])) {
                foreach ($payload[$key] as $field) {
                    $value = $field['value'] ?? $field['answer'] ?? '';
                    if (is_string($value) && preg_match($uuidPattern, trim($value))) {
                        return trim($value);
                    }
                }
            }
        }

        return null;
    }

    /**
     * Extract structured interview data from the HireVire payload.
     * HireVire typically sends question/answer pairs and video URLs.
     */
    private function extractInterviewData(array $payload): array
    {
        $data = [];

        // Video URL / recording link
        $data['video_url'] = $payload['video_url']
            ?? $payload['recording_url']
            ?? $payload['submission_url']
            ?? $payload['application']['video_url']
            ?? $payload['data']['video_url']
            ?? null;

        // Review URL (for admin)
        $data['review_url'] = $payload['review_url']
            ?? $payload['admin_url']
            ?? $payload['dashboard_url']
            ?? null;

        // Applicant name from HireVire
        $data['hirevire_name'] = $payload['name']
            ?? $payload['applicant_name']
            ?? ($payload['applicant']['name'] ?? null);

        // Responses / answers to interview questions
        $questions = $payload['questions']
            ?? $payload['answers']
            ?? $payload['responses']
            ?? $payload['application']['questions']
            ?? [];

        if (!empty($questions) && is_array($questions)) {
            $data['questions'] = array_map(function ($q) {
                return [
                    'question' => $q['question'] ?? $q['text'] ?? $q['label'] ?? '',
                    'answer'   => $q['answer']   ?? $q['response'] ?? $q['value'] ?? '',
                    'video'    => $q['video_url'] ?? $q['recording'] ?? null,
                ];
            }, $questions);
        }

        // Raw HireVire scores (if any)
        $data['score']  = $payload['score']  ?? $payload['rating'] ?? null;
        $data['status'] = $payload['status'] ?? null;

        return array_filter($data, fn($v) => $v !== null && $v !== '');
    }
}
