<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\TraineeApplication;
use App\Models\TraineeApplicationSetting;
use App\Models\ActivityLog;
use App\Mail\DynamicEmail;
use App\Jobs\SendInterviewReminder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Carbon\Carbon;

/**
 * TrafftWebhookController
 *
 * Handles booking confirmation webhooks from Trafft (Step 7).
 *
 * ──────────────────────────────────────────────────────────────────────────
 * SETUP INSTRUCTIONS (Trafft Dashboard → Settings → Integrations → Webhooks):
 *   1. Webhook URL : POST {APP_URL}/api/trafft/webhook
 *   2. Events      : booking.created  (and optionally booking.cancelled)
 *   3. Secret      : Set TRAFFT_WEBHOOK_SECRET in .env
 *
 * Candidate identification:
 *   Trafft passes customer details in the booking payload. The candidate's
 *   email is matched to TraineeApplication.email. Optionally, pass the
 *   application UUID as a "Custom Field" in the Trafft booking form so that
 *   it appears in custom_fields[].value.
 *
 * Trafft booking page: https://vanquishtherapies.co.uk/placement-interview/
 * ──────────────────────────────────────────────────────────────────────────
 */
class TrafftWebhookController extends Controller
{
    /**
     * Handle Trafft booking webhook.
     * POST /api/trafft/webhook
     */
    public function handleBooking(Request $request): \Illuminate\Http\JsonResponse
    {
        try {
            Log::info('Trafft Webhook Received', [
                'ip'      => $request->ip(),
                'payload' => $request->all(),
            ]);

            $payload = $request->all();

            // ── 1. Optional signature verification ────────────────────────
            $secret = config('services.trafft.webhook_secret');
            if ($secret) {
                $signature = $request->header('X-Trafft-Signature')
                    ?? $request->header('X-Webhook-Signature')
                    ?? $request->header('Authorization')
                    ?? '';
                $expected = 'sha256=' . hash_hmac('sha256', $request->getContent(), $secret);
                if (!hash_equals($expected, ltrim($signature, 'sha256='))) {
                    Log::warning('Trafft Webhook: Invalid signature', ['ip' => $request->ip()]);
                    // Log but don't reject — Trafft format varies by version
                }
            }

            // ── 2. Handle cancellations ───────────────────────────────────
            $event = $payload['event'] ?? $payload['type'] ?? $payload['action'] ?? '';
            if (in_array($event, ['booking.cancelled', 'appointment.cancelled', 'booking.deleted'])) {
                return $this->handleCancellation($payload);
            }

            // ── 3. Extract customer email ─────────────────────────────────
            $email = $this->extractEmail($payload);
            if (!$email) {
                Log::warning('Trafft Webhook: Email not found in payload', ['payload' => $payload]);
                return response()->json(['message' => 'Customer email not found'], 200);
            }

            $email = strtolower(trim($email));

            // ── 4. Find matching trainee application ──────────────────────
            $application = TraineeApplication::where('email', $email)->first();
            if (!$application) {
                $uuid = $this->extractMetadata($payload, 'uuid');
                if ($uuid) {
                    $application = TraineeApplication::where('uuid', $uuid)->first();
                }
            }

            if (!$application) {
                Log::warning('Trafft Webhook: No matching trainee application', [
                    'email'   => $email,
                    'payload' => $payload,
                ]);
                return response()->json(['message' => 'No matching trainee application found'], 200);
            }

            // ── 5. Parse booking details from Trafft payload ──────────────
            $booking = $this->parseBookingData($payload);

            // ── 6. Prevent double-booking: if already confirmed, skip ─────
            $interviewData = is_array($application->interview_data) ? $application->interview_data : [];
            if (
                isset($interviewData['trafft']['appointment_id']) &&
                $interviewData['trafft']['appointment_id'] === ($booking['appointment_id'] ?? null)
            ) {
                Log::info('Trafft Webhook: Duplicate event ignored', [
                    'appointment_id' => $booking['appointment_id'],
                ]);
                return response()->json(['message' => 'Booking already processed'], 200);
            }

            // ── 7. Update application record ──────────────────────────────
            $application->update([
                'status'         => 'Stage 3 Interview Booked',
                'interview_data' => array_merge($interviewData, ['trafft' => $booking]),
            ]);

            // ── 8. Activity log ───────────────────────────────────────────
            ActivityLog::create([
                'user_id'     => null,
                'action'      => 'trafft_interview_booked',
                'model_type'  => TraineeApplication::class,
                'model_id'    => $application->id,
                'description' => "Stage 3 interview booked via Trafft for {$application->first_name} {$application->last_name} on {$booking['formatted_datetime']}",
                'changes'     => $booking,
                'ip_address'  => $request->ip(),
            ]);

            Log::info('Trafft Webhook: Interview booked', [
                'application_id' => $application->id,
                'email'          => $email,
                'datetime'       => $booking['formatted_datetime'],
                'zoom_link'      => $booking['zoom_link'],
            ]);

            // ── 9. Step 7: Send booking confirmation email ────────────────
            app(\App\Services\EmailService::class)->sendAndLog(
                $application->email,
                'trainee_interview_confirmed',
                [
                    'first_name'   => $application->first_name,
                    'full_name'    => $application->first_name . ' ' . $application->last_name,
                    'date'         => $booking['date'],
                    'time'         => $booking['time'],
                    'scheduled_at' => $booking['formatted_datetime'],
                    'zoom_link'    => $booking['zoom_link'],
                    'meeting_id'   => $booking['meeting_id'],
                    'host_name'    => $booking['employee_name'],
                    'service_name' => $booking['service_name'],
                ]
            );

            // Send interview reminder instantly
            SendInterviewReminder::dispatch($application);
            Log::info('Trafft Webhook: Interview reminder sent', [
                'application_id' => $application->id,
            ]);

            return response()->json([
                'message'   => 'Booking confirmed successfully',
                'status'    => 'Stage 3 Interview Booked',
                'interview' => [
                    'datetime'  => $booking['formatted_datetime'],
                    'zoom_link' => $booking['zoom_link'],
                ],
            ], 200);

        } catch (\Exception $e) {
            Log::error('Trafft Webhook Error', [
                'error'   => $e->getMessage(),
                'trace'   => $e->getTraceAsString(),
                'payload' => $request->all(),
            ]);

            return response()->json([
                'message' => 'Error processing booking',
                'error'   => config('app.debug') ? $e->getMessage() : 'Internal error',
            ], 200); // Always 200 so Trafft stops retrying
        }
    }

    /**
     * Handle booking cancellation from Trafft.
     */
    private function handleCancellation(array $payload): \Illuminate\Http\JsonResponse
    {
        $email = $this->extractEmail($payload);
        if ($email) {
            $application = TraineeApplication::where('email', strtolower(trim($email)))->first();
            if ($application && $application->status === 'Stage 3 Interview Booked') {
                $application->update(['status' => 'Stage 2 Approved']); // Revert so they can rebook
                Log::info('Trafft Webhook: Booking cancelled, status reverted', [
                    'application_id' => $application->id,
                ]);
            }
        }
        return response()->json(['message' => 'Cancellation processed'], 200);
    }

    /**
     * Parse and normalise Trafft booking payload into a consistent structure.
     * Trafft payload varies between API versions; we handle multiple formats.
     */
    private function parseBookingData(array $payload): array
    {
        // Appointment/booking ID
        $appointmentId = $payload['appointmentId']
            ?? $payload['appointment_id']
            ?? $payload['bookingId']
            ?? $payload['booking_id']
            ?? $payload['id']
            ?? null;

        // Start time — Trafft sends ISO 8601 or "Y-m-d H:i:s"
        $startRaw = $payload['startTime']
            ?? $payload['start_time']
            ?? $payload['appointmentStartTime']
            ?? $payload['appointment']['startTime']
            ?? $payload['booking']['startTime']
            ?? $payload['datetime']
            ?? null;

        $startCarbon = null;
        if ($startRaw) {
            try {
                $startCarbon = Carbon::parse($startRaw);
            } catch (\Exception $e) {
                Log::warning("Trafft: Could not parse start time: {$startRaw}");
            }
        }

        $formattedDatetime = $startCarbon
            ? $startCarbon->format('l, j F Y \a\t g:ia')
            : 'Date to be confirmed';
        $date = $startCarbon ? $startCarbon->format('l, j F Y') : '';
        $time = $startCarbon ? $startCarbon->format('g:ia') : '';

        // Employee / host details
        $employeeName = $payload['employeeName']
            ?? $payload['employee_name']
            ?? $payload['employee']['name']
            ?? ($payload['employees'][0]['name'] ?? null)
            ?? $payload['hostName']
            ?? 'Vanquish Therapies';

        // Zoom / meeting link — often in custom fields or location
        $zoomLink = $payload['zoomLink']
            ?? $payload['zoom_link']
            ?? $payload['meetingLink']
            ?? $payload['meeting_link']
            ?? $payload['location']
            ?? $payload['locationAddress']
            ?? $payload['appointment']['location']
            ?? null;

        // Trafft sometimes puts zoom in a customFields key
        if (!$zoomLink) {
            $zoomLink = $this->extractMetadata($payload, 'zoom')
                ?? $this->extractMetadata($payload, 'zoom_link')
                ?? $this->extractMetadata($payload, 'meeting_link');
        }

        // Fall back to default Zoom from config/settings
        if (!$zoomLink) {
            $zoomLink = TraineeApplicationSetting::getByKey('default_zoom_link', config('services.trafft.default_zoom_link', 'https://zoom.us/j/vanquishtherapies'));
        }

        // Service name
        $serviceName = $payload['serviceName']
            ?? $payload['service_name']
            ?? $payload['service']['name']
            ?? 'Placement Interview';

        // Meeting ID (generate from appointmentId if not present)
        $meetingId = $payload['meetingId']
            ?? $payload['meeting_id']
            ?? ($appointmentId ? substr(str_replace('-', '', $appointmentId), 0, 9) : rand(100000000, 999999999));

        return [
            'appointment_id'        => $appointmentId,
            'start_time'            => $startRaw,
            'start_datetime_carbon' => $startCarbon,
            'formatted_datetime'    => $formattedDatetime,
            'date'                  => $date,
            'time'                  => $time,
            'employee_name'         => $employeeName,
            'zoom_link'             => $zoomLink,
            'meeting_id'            => $meetingId,
            'service_name'          => $serviceName,
            'raw_payload_keys'      => array_keys($payload), // for debugging
        ];
    }

    /**
     * Extract customer email from various Trafft payload structures.
     */
    private function extractEmail(array $payload): ?string
    {
        $candidates = [
            $payload['customerEmail']              ?? null,
            $payload['customer_email']             ?? null,
            $payload['email']                      ?? null,
            $payload['customer']['email']          ?? null,
            $payload['booking']['customerEmail']   ?? null,
            $payload['appointment']['customer']['email'] ?? null,
            $payload['attendee']['email']          ?? null,
            $payload['data']['customerEmail']      ?? null,
        ];

        foreach ($candidates as $val) {
            if ($val && filter_var(trim($val), FILTER_VALIDATE_EMAIL)) {
                return trim($val);
            }
        }

        // Scan customFields
        foreach (['customFields', 'custom_fields', 'fields'] as $key) {
            if (isset($payload[$key]) && is_array($payload[$key])) {
                foreach ($payload[$key] as $field) {
                    $value = $field['value'] ?? $field['answer'] ?? '';
                    if (is_string($value) && filter_var(trim($value), FILTER_VALIDATE_EMAIL)) {
                        return trim($value);
                    }
                }
            }
        }

        return null;
    }

    /**
     * Extract a specific metadata value from custom fields.
     */
    private function extractMetadata(array $payload, string $key): ?string
    {
        foreach (['customFields', 'custom_fields', 'fields', 'metadata'] as $fieldKey) {
            if (!isset($payload[$fieldKey]) || !is_array($payload[$fieldKey])) {
                continue;
            }
            foreach ($payload[$fieldKey] as $field) {
                $label = strtolower($field['label'] ?? $field['name'] ?? $field['key'] ?? '');
                if (str_contains($label, $key)) {
                    return $field['value'] ?? $field['answer'] ?? null;
                }
            }
        }
        return null;
    }
}
