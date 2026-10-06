<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\Session;
use App\Models\TrainingCounsellor;
use App\Models\ActivityLog;
use App\Models\ClientTcMatch;
use Illuminate\Http\Request;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use App\Mail\DynamicEmail;
use Stripe\Stripe;
use Stripe\PaymentIntent;
use Stripe\Exception\ApiErrorException;

use App\Services\EmailService;

class ClientBookingController extends Controller
{
    protected $emailService;

    public function __construct(EmailService $emailService)
    {
        $this->emailService = $emailService;
        Stripe::setApiKey(config('services.stripe.secret_key'));
    }

    /**
     * Stamp newly booked session(s) as paid using a Stripe PaymentIntent that
     * was confirmed client-side. Booking creation doesn't depend on this
     * succeeding - if the PaymentIntent can't be verified for any reason, the
     * session(s) are simply left as payment_status=pending for manual
     * reconciliation rather than failing the booking.
     */
    private function stampSessionsPayment(array $sessions, ?string $paymentIntentId, Client $client): void
    {
        if (!$paymentIntentId || empty($sessions)) {
            return;
        }

        try {
            $paymentIntent = PaymentIntent::retrieve($paymentIntentId);

            if ($paymentIntent->status !== 'succeeded') {
                Log::warning("Session payment stamp skipped: PaymentIntent {$paymentIntentId} status is {$paymentIntent->status}");
                return;
            }

            if (($paymentIntent->metadata->client_id ?? null) != $client->id) {
                Log::warning("Session payment stamp skipped: PaymentIntent {$paymentIntentId} client_id mismatch");
                return;
            }

            if (Session::where('stripe_payment_intent_id', $paymentIntentId)->exists()) {
                Log::warning("Session payment stamp skipped: PaymentIntent {$paymentIntentId} already used for another session");
                return;
            }

            $amountPerSession = ($paymentIntent->amount / 100) / count($sessions);
            $paymentMethod = $paymentIntent->payment_method_types[0] ?? 'card';

            foreach ($sessions as $session) {
                $session->update([
                    'payment_status' => 'paid',
                    'payment_amount' => $amountPerSession,
                    'stripe_payment_intent_id' => $paymentIntentId,
                    'paid_at' => now(),
                    'payment_method' => $paymentMethod,
                ]);
            }
        } catch (ApiErrorException $e) {
            Log::error("Failed to verify PaymentIntent {$paymentIntentId} for session booking: " . $e->getMessage());
        }
    }
    /**
     * Authenticate client and get booking info
     */
    public function authenticate(Request $request)
    {
        $validated = $request->validate([
            'email' => 'required_without:client_uuid|nullable|email',
            'client_uuid' => 'required_without:email|nullable|string',
        ]);

        $query = Client::query();

        if (!empty($validated['email'])) {
            $query->where('email', $validated['email']);
        }

        if (!empty($validated['client_uuid'])) {
            $query->where('uuid', $validated['client_uuid']);
        }

        $client = $query->latest('id')->first();

        if (!$client) {
            return response()->json(['message' => 'Client not found'], 404);
        }

        // Check if client is matched and agreement signed
        if (!$client->matched_tc_id) {
            return response()->json([
                'message' => 'You have not been matched with a counsellor yet.',
                'client' => null,
            ], 400);
        }

        // We have removed the 'signed' requirement to allow consultation before signing
        // Get upcoming sessions
        $upcomingSessions = $client->getUpcomingSessions(20);

        // Get next session needing booking (for Low Cost)
        $nextSessionNeedingBooking = null;
        if ($client->service_type === 'Low Cost') {
            $nextSessionNeedingBooking = $client->getNextSessionNeedingBooking();
        }

        return response()->json([
            'client' => [
                'id' => $client->id,
                'uuid' => $client->uuid,
                'name' => $client->name,
                'email' => $client->email,
                'service_type' => $client->service_type,
                'allocated_day' => $client->allocated_day,
                'allocated_time' => $client->allocated_time,
                'next_booking_deadline' => $client->next_booking_deadline,
                'days_until_deadline' => $this->calculateDaysUntilDeadline($client),
                'matched_tc' => $client->matchedTc ? [
                    'name' => $client->matchedTc->abbreviated_name,
                    'uuid' => $client->matchedTc->uuid,
                    'photo' => $client->matchedTc->photo,
                    'photo_url' => $client->matchedTc->photo_url,
                    'session_price' => $client->matchedTc->session_price,
                ] : null,
            ],
            'upcoming_sessions' => $upcomingSessions,
            'next_session_needing_booking' => $nextSessionNeedingBooking,
        ]);
    }

    /**
     * List Active Qualified counsellors offering the client's service tier,
     * for the client to self-select from (Mid Range / Coaching only).
     */
    public function getAvailableCounsellors($clientUuid)
    {
        $client = Client::where('uuid', $clientUuid)->firstOrFail();

        if (!in_array($client->service_type, ['Mid Range', 'Counselling & Coaching'])) {
            return response()->json(['message' => 'Counsellor selection is not available for this service.'], 400);
        }

        if ($client->stage !== 'Ready to Choose Counsellor') {
            return response()->json(['message' => 'You are not yet ready to choose a practitioner. Please contact us if you believe this is an error.'], 400);
        }

        if ($client->matched_tc_id) {
            return response()->json(['message' => 'You have already been matched with a practitioner.'], 400);
        }

        $tierColumn = $client->service_type === 'Mid Range' ? 'offers_mid_range' : 'offers_coaching';

        $counsellors = TrainingCounsellor::where('counsellor_type', 'Qualified')
            ->where('status', 'Active')
            ->where($tierColumn, true)
            ->get()
            ->map(function ($tc) {
                return [
                    'uuid' => $tc->uuid,
                    'name' => $tc->abbreviated_name,
                    'bio' => $tc->bio,
                    'photo' => $tc->photo,
                    'photo_url' => $tc->photo_url,
                    'session_price' => $tc->session_price,
                    'topics_with_experience' => $tc->topics_with_experience ?? [],
                ];
            });

        return response()->json(['counsellors' => $counsellors]);
    }

    /**
     * Client self-service: pick a practitioner (Mid Range / Coaching only).
     */
    public function chooseCounsellor(Request $request, $clientUuid)
    {
        $validated = $request->validate([
            'tc_uuid' => 'required|string',
        ]);

        $client = Client::where('uuid', $clientUuid)->firstOrFail();

        if (!in_array($client->service_type, ['Mid Range', 'Counselling & Coaching'])) {
            return response()->json(['message' => 'Counsellor selection is not available for this service.'], 400);
        }

        if ($client->stage !== 'Ready to Choose Counsellor') {
            return response()->json(['message' => 'You are not yet ready to choose a practitioner.'], 400);
        }

        if ($client->matched_tc_id) {
            return response()->json(['message' => 'You have already been matched with a practitioner.'], 400);
        }

        $tierColumn = $client->service_type === 'Mid Range' ? 'offers_mid_range' : 'offers_coaching';

        $tc = TrainingCounsellor::where('uuid', $validated['tc_uuid'])
            ->where('counsellor_type', 'Qualified')
            ->where('status', 'Active')
            ->where($tierColumn, true)
            ->first();

        if (!$tc) {
            return response()->json(['message' => 'This practitioner is not available for your service.'], 404);
        }

        $match = ClientTcMatch::create([
            'client_id' => $client->id,
            'tc_id' => $tc->id,
            'service_type' => $client->service_type,
            'match_score' => null,
            'assignment_notes' => 'Client self-selected practitioner',
            'status' => 'assigned',
            'assigned_date' => now(),
            'send_notification' => true,
        ]);

        $tc->increment('current_clients');

        $client->update([
            'matched_tc_id' => $tc->id,
            'matched_date' => $client->matched_date ?? now(),
            'stage' => 'Matched with TC',
        ]);

        ActivityLog::create([
            'user_id' => null,
            'action' => 'client_tc_matched',
            'model_type' => ClientTcMatch::class,
            'model_id' => $match->id,
            'description' => "{$client->name} self-selected practitioner {$tc->name}",
            'ip_address' => $request->ip(),
        ]);

        try {
            $this->emailService->sendMatchNotification($client, $tc, $match, null, null, 0);
        } catch (\Exception $e) {
            Log::error('Failed to send match notification emails', [
                'match_id' => $match->id,
                'client_id' => $client->id,
                'tc_id' => $tc->id,
                'error' => $e->getMessage(),
            ]);
        }

        return response()->json([
            'message' => 'Practitioner selected successfully. Please check your email for next steps.',
            'client' => $client->fresh(),
        ]);
    }

    /**
     * Get available time slots for booking
     */
    public function getAvailableSlots(Request $request, $clientUuid)
    {
        $client = Client::where('uuid', $clientUuid)->firstOrFail();

        if (!$client->matched_tc_id) {
            return response()->json(['message' => 'Client not matched'], 400);
        }

        // --- BOOKING GATE ---
        // Admin must explicitly enable bookings for each service type.
        // If not enabled, return empty slots with a clear flag so the frontend can
        // show a "Booking not yet open" message rather than a calendar of fake dates.
        $serviceType = $client->service_type;
        $serviceSetting = \App\Models\ServiceSetting::where('service_name', $serviceType)->first();

        if (!$serviceSetting || !$serviceSetting->booking_enabled) {
            return response()->json([
                'slots'           => [],
                'booking_enabled' => false,
                'message'         => 'Booking is not yet open for your service. Please check back soon or contact support.',
            ]);
        }

        $tc = TrainingCounsellor::find($client->matched_tc_id);

        if (!$tc) {
            return response()->json(['message' => 'Matched counsellor not found'], 404);
        }

        // Normalize availability keys to lowercase for robust lookup
        $rawAvailability = $tc->availability;
        if (is_string($rawAvailability)) {
            $rawAvailability = json_decode($rawAvailability, true);
        }
        $availabilityData = is_array($rawAvailability) && !empty($rawAvailability)
            ? array_change_key_case($rawAvailability, CASE_LOWER)
            : [];

        if (empty($availabilityData)) {
            return response()->json([
                'slots' => [],
                'booking_type' => $client->service_type === 'Low Cost' ? 'block' : 'flexible',
                'message' => 'Your assigned counsellor has not configured their weekly availability schedule yet. Please check back soon or contact support.',
            ]);
        }

        // Get all existing sessions for this TC to prevent double booking
        $existingSessions = Session::where('tc_id', $tc->id)
            ->whereIn('status', ['scheduled', 'completed'])
            ->get(['scheduled_at']);

        $bookedTimes = $existingSessions->map(function ($session) {
            return $session->scheduled_at->format('Y-m-d H:i');
        })->toArray();

        $slots = [];
        $startDate = Carbon::now();

        for ($i = 0; $i < 42; $i++) {
            $currentDate = $startDate->copy()->addDays($i);
            $dayName = $currentDate->format('l');
            $dayKey = strtolower($dayName);

            if (isset($availabilityData[$dayKey]) && is_array($availabilityData[$dayKey])) {
                foreach ($availabilityData[$dayKey] as $time) {
                    $slotDateTime = $currentDate->format('Y-m-d') . ' ' . $this->formatTimeToHHi($time);

                    // 48-hour rule for Low Cost/Mid-Range
                    $isAvailable = !in_array($slotDateTime, $bookedTimes);
                    if (($client->service_type === 'Low Cost' || $client->service_type === 'Mid Range') &&
                        $currentDate->diffInHours(now(), false) > -48
                    ) {
                        $isAvailable = false;
                    }

                    $slots[] = [
                        'date' => $currentDate->format('Y-m-d'),
                        'day' => $dayName,
                        'time' => $time,
                        'formatted_time' => $this->formatTimeToHHi($time),
                        'available' => $isAvailable,
                    ];
                }
            }
        }

        return response()->json([
            'slots' => $slots,
            'booking_type' => $client->service_type === 'Low Cost' ? 'block' : 'flexible',
        ]);
    }

    /**
     * Book a single session (Mid-Range or Counselling & Coaching)
     */
    public function bookSession(Request $request)
    {
        $validated = $request->validate([
            'client_uuid' => 'required|string',
            'scheduled_at' => 'required|date|after:' . now()->subMinutes(5)->toDateTimeString(),
            'session_type' => 'required|in:mid_range,counselling_coaching',
            'payment_intent_id' => 'nullable|string',
        ]);

        $client = Client::where('uuid', $validated['client_uuid'])->firstOrFail();

        if (!$client->matched_tc_id) {
            return response()->json(['message' => 'Client not matched with a counsellor yet'], 400);
        }

        // Check for double booking
        $isBooked = Session::where('tc_id', $client->matched_tc_id)
            ->where('scheduled_at', $validated['scheduled_at'])
            ->whereIn('status', ['scheduled', 'completed'])
            ->exists();

        if ($isBooked) {
            return response()->json(['message' => 'This slot is already booked. Please choose another one.'], 400);
        }

        // If Mid Range and no allocated day/time, set it now
        if ($client->service_type === 'Mid Range' && (!$client->allocated_day || !$client->allocated_time)) {
            $scheduledAt = Carbon::parse($validated['scheduled_at']);
            $client->update([
                'allocated_day' => $scheduledAt->format('l'),
                'allocated_time' => $scheduledAt->format('g:ia'),
            ]);
        }

        // Create session
        $session = Session::create([
            'client_id' => $client->id,
            'tc_id' => $client->matched_tc_id,
            'session_type' => $validated['session_type'],
            'scheduled_at' => $validated['scheduled_at'],
            'status' => 'scheduled',
            'payment_status' => 'pending',
        ]);

        $this->stampSessionsPayment([$session], $validated['payment_intent_id'] ?? null, $client);

        // Send notification to TC
        $this->notifyCounsellor($session);

        // Send confirmation to Client
        if ($client->email) {
            $this->emailService->sendAndLog(
                $client,
                'booking_confirmation',
                [
                    'client_name' => $client->name,
                    'booking_type' => 'session',
                    'counsellor_name' => $session->tc ? $session->tc->abbreviated_name : 'Your Counsellor',
                    'booking_details' => Carbon::parse($session->scheduled_at)->format('l, jS F Y (H:i)'),
                    'location' => 'Online',
                    'duration' => 50,
                    'date' => Carbon::parse($session->scheduled_at)->format('F j, Y'),
                    'time' => Carbon::parse($session->scheduled_at)->format('H:i'),
                    'consultation_link' => config('app.frontend_url') . '/client-booking?uuid=' . $client->uuid
                ]
            );
        }

        return response()->json([
            'message' => 'Session booked successfully. Please proceed to payment.',
            'session' => $session->load(['client', 'tc']),
            'session_ids' => [$session->id],
        ], 201);
    }

    /**
     * Book a block of sessions (Low Cost - 4 sessions)
     */
    public function bookBlock(Request $request)
    {
        $validated = $request->validate([
            'client_uuid' => 'required|string',
            'start_date' => 'nullable|date',
            'sessions_count' => 'nullable|integer',
            'time_slot' => 'nullable|string',
            'session_slots' => 'nullable|array',
            'session_slots.*' => 'date',
            'payment_intent_id' => 'nullable|string',
        ]);

        $client = Client::where('uuid', $validated['client_uuid'])->firstOrFail();

        if (!$client->matched_tc_id) {
            return response()->json(['message' => 'Client not matched with a counsellor yet'], 400);
        }

        $tc = TrainingCounsellor::find($client->matched_tc_id);
        $isLowCost = $client->service_type === 'Low Cost';
        $now = Carbon::now();

        if ($isLowCost) {
            $sessionsCount = $validated['sessions_count'] ?? 4;

            // --- Penalty/Deadline Check ---
            if ($client->next_booking_deadline && $now->gt(Carbon::parse($client->next_booking_deadline))) {
                $sessionsCount = 3; // Enforce penalty
                Log::info("Penalty applied for client {$client->uuid}: deadline was {$client->next_booking_deadline}");
            }

            if (!in_array($sessionsCount, [3, 4], true)) {
                return response()->json(['message' => 'Low Cost sessions must be booked in a block of 4.'], 422);
            }

            if (!$client->allocated_day || !$client->allocated_time) {
                return response()->json([
                    'message' => 'Your regular weekly session time has not been assigned by our admin team yet. Please contact support.',
                ], 422);
            }

            // Auto mode: generate the next valid dates (holiday + double-booking aware)
            $sessionDates = $this->generateNextLowCostSlots($tc, $client, $sessionsCount);

            if (count($sessionDates) < $sessionsCount) {
                return response()->json(['message' => 'Could not find enough available slots for your regular day/time. Please contact support.'], 400);
            }
        } else {
            $sessionsCount = $validated['sessions_count'] ?? null;

            if (!in_array($sessionsCount, [2, 3, 4], true)) {
                return response()->json(['message' => 'Please choose a block of 2, 3, or 4 sessions.'], 422);
            }

            $sessionDates = [];
            $isManualSelection = false;

            if (!empty($validated['session_slots'])) {
                // Manual selection mode (client explicitly chose these dates)
                $isManualSelection = true;
                $slots = $validated['session_slots'];

                // Sort chronologically
                usort($slots, function($a, $b) {
                    return strtotime($a) - strtotime($b);
                });

                $slots = array_slice($slots, 0, $sessionsCount);

                if (count($slots) < $sessionsCount) {
                    return response()->json(['message' => "Please select exactly {$sessionsCount} slots."], 400);
                }

                foreach ($slots as $slot) {
                    $sessionDates[] = Carbon::parse($slot);
                }
            } else {
                // Legacy recurring mode (Fallback - explicit start date/time selection)
                $isManualSelection = true;

                if (empty($validated['start_date'])) {
                    return response()->json(['message' => 'Start date or session slots are required.'], 400);
                }
                $startDate = Carbon::parse($validated['start_date']);
                $timeToUse = $validated['time_slot'] ?? $client->allocated_time;

                if ($timeToUse) {
                    $formattedTime = $this->formatTimeToHHi($timeToUse);
                    $startDate = Carbon::parse($startDate->format('Y-m-d') . ' ' . $formattedTime);
                }

                for ($i = 0; $i < $sessionsCount; $i++) {
                    $sessionDates[] = $startDate->copy()->addWeeks($i);
                }
            }
        }

        // --- 48-hour rule check for the FIRST session ---
        if ($now->diffInHours($sessionDates[0], false) < 48) {
            return response()->json(['message' => 'The first session must be booked at least 48 hours in advance.'], 400);
        }

        // Check for double booking for ALL selected slots
        foreach ($sessionDates as $date) {
            $isBooked = Session::where('tc_id', $client->matched_tc_id)
                ->where('scheduled_at', $date->format('Y-m-d H:i:s'))
                ->whereIn('status', ['scheduled', 'completed'])
                ->exists();

            if ($isBooked) {
                return response()->json(['message' => 'One of the selected slots (' . $date->format('Y-m-d H:i') . ') is already booked. Please choose another.'], 400);
            }
        }

        $sessionType = match ($client->service_type) {
            'Mid Range' => 'mid_range',
            'Counselling & Coaching' => 'counselling_coaching',
            default => 'low_cost',
        };

        $sessions = [];
        $bookingDeadline = null;

        foreach ($sessionDates as $i => $sessionDate) {
            // Set booking deadline for next block (48hrs before what would be the next week) - Low Cost only
            if ($isLowCost && $i === count($sessionDates) - 1) {
                $nextSessionDate = $sessionDate->copy()->addWeek();
                $bookingDeadline = $nextSessionDate->copy()->subHours(48)->format('Y-m-d');
            }

            $session = Session::create([
                'client_id' => $client->id,
                'tc_id' => $client->matched_tc_id,
                'session_type' => $sessionType,
                'scheduled_at' => $sessionDate->format('Y-m-d H:i:s'),
                'status' => 'scheduled',
                'is_block_booking' => true,
                'block_number' => $i + 1,
                'total_sessions_in_block' => $sessionsCount,
                'payment_status' => 'pending',
                'booking_deadline' => $isLowCost && $i === count($sessionDates) - 1 ? $bookingDeadline : null,
                'auto_deduction_applied' => $isLowCost && $sessionsCount === 3,
            ]);

            $sessions[] = $session;
        }
        $sessionIds = collect($sessions)->pluck('id')->toArray();

        $this->stampSessionsPayment($sessions, $validated['payment_intent_id'] ?? null, $client);

        // Update client's next booking deadline (Low Cost only)
        if ($isLowCost) {
            $client->update([
                'next_booking_deadline' => $bookingDeadline,
            ]);
        }

        // Send confirmation to Client
        if ($client->email) {
            $sessionDatesFormatted = collect($sessions)->map(fn($s) => Carbon::parse($s->scheduled_at)->format('l, jS F Y (H:i)'))->join(', ');

            $this->emailService->sendAndLog(
                $client,
                'booking_confirmation',
                [
                    'client_name' => $client->name,
                    'booking_type' => 'Block of ' . count($sessions) . ' Sessions' . ($isLowCost && $sessionsCount === 3 ? ' (Penalty Applied)' : ''),
                    'counsellor_name' => $client->matchedTc ? $client->matchedTc->abbreviated_name : 'Your Counsellor',
                    'booking_details' => $sessionDatesFormatted,
                    'location' => 'Online',
                    'duration' => 50,
                    'consultation_link' => config('app.frontend_url') . '/client-booking?uuid=' . $client->uuid,
                    'date' => $sessionDates[0]->format('F j, Y'),
                    'time' => $sessionDates[0]->format('H:i')
                ]
            );
        }

        return response()->json([
            'message' => "Block of {$sessionsCount} sessions booked successfully" . ($isLowCost && $sessionsCount === 3 ? " (Penalty applied for missing deadline)" : "") . ". Please proceed to payment.",
            'sessions' => Session::whereIn('id', $sessionIds)->with(['client', 'tc'])->get(),
            'session_ids' => $sessionIds,
            'next_booking_deadline' => $bookingDeadline,
            'penalty_applied' => $isLowCost && $sessionsCount === 3,
        ], 201);
    }

    /**
     * Auto-generate the next block of available session slots for a Low Cost
     * client's regular weekly day/time (holiday + double-booking aware).
     */
    public function getNextBlockSlots($clientUuid)
    {
        $client = Client::where('uuid', $clientUuid)->firstOrFail();

        if ($client->service_type !== 'Low Cost') {
            return response()->json(['message' => 'This is only available for Low Cost clients.'], 400);
        }

        if (!$client->matched_tc_id) {
            return response()->json(['message' => 'Client not matched with a counsellor yet'], 400);
        }

        if (!$client->allocated_day || !$client->allocated_time) {
            return response()->json([
                'auto' => false,
                'slots' => [],
                'message' => 'No regular weekly slot set yet. Please select your first session slot.',
            ]);
        }

        $tc = TrainingCounsellor::find($client->matched_tc_id);

        $sessionsCount = 4;
        if ($client->next_booking_deadline && Carbon::now()->gt(Carbon::parse($client->next_booking_deadline))) {
            $sessionsCount = 3; // Penalty applied
        }

        if (!$this->tcStillOffersSlot($tc, $client->allocated_day, $this->formatTimeToHHi($client->allocated_time))) {
            return response()->json([
                'auto' => false,
                'slots' => [],
                'message' => 'Your counsellor no longer offers your regular day/time. Please select a new weekly slot.',
            ]);
        }

        $sessionDates = $this->generateNextLowCostSlots($tc, $client, $sessionsCount);

        if (empty($sessionDates)) {
            return response()->json([
                'auto' => false,
                'slots' => [],
                'message' => 'Could not find available slots for your regular day/time in the next 12 months. Please select a new weekly slot or contact support.',
            ]);
        }

        return response()->json([
            'auto' => true,
            'allocated_day' => $client->allocated_day,
            'allocated_time' => $client->allocated_time,
            'sessions_count' => $sessionsCount,
            'penalty_applied' => $sessionsCount === 3,
            'slots' => collect($sessionDates)->map(fn ($date) => [
                'date' => $date->format('Y-m-d'),
                'time' => $date->format('H:i'),
                'scheduled_at' => $date->format('Y-m-d H:i:s'),
                'formatted' => $date->format('l, jS F Y (H:i)'),
            ]),
        ]);
    }

    /**
     * Get client's booking status
     */
    public function getBookingStatus($clientUuid)
    {
        $client = Client::where('uuid', $clientUuid)->firstOrFail();

        $upcomingSessions = $client->getUpcomingSessions(20);
        $nextSessionNeedingBooking = $client->getNextSessionNeedingBooking();

        return response()->json([
            'client' => [
                'id' => $client->id,
                'uuid' => $client->uuid,
                'name' => $client->name,
                'service_type' => $client->service_type,
                'allocated_day' => $client->allocated_day,
                'allocated_time' => $client->allocated_time,
                'next_booking_deadline' => $client->next_booking_deadline,
                'days_until_deadline' => $this->calculateDaysUntilDeadline($client),
            ],
            'upcoming_sessions' => $upcomingSessions,
            'next_session_needing_booking' => $nextSessionNeedingBooking,
        ]);
    }

    /**
     * Whole number of days remaining until the client's booking deadline
     * (rounded up, so any partial day left still counts as a full day).
     */
    private function calculateDaysUntilDeadline(Client $client): ?int
    {
        if (!$client->next_booking_deadline) {
            return null;
        }

        $deadline = Carbon::parse($client->next_booking_deadline);

        return (int) ceil(now()->diffInDays($deadline, false));
    }

    /**
     * Helper: Notify counsellor of booking
     */
    private function notifyCounsellor(Session $session)
    {
        if ($session->tc && $session->tc->email && $session->tc->counsellor_type !== 'Trainee') {
            $this->emailService->sendAndLog(
                $session->tc->email,
                'booking_notification',
                [
                    'tc_name' => $session->tc->name,
                    'client_name' => $session->client->name,
                    'booking_type' => 'session',
                    'scheduled_at' => Carbon::parse($session->scheduled_at)->format('l, jS F Y (H:i)'),
                    'notes' => $session->notes ?? 'N/A'
                ]
            );
        }
    }

    /**
     * Helper: Get day of week number for Carbon
     */
    private function getDayOfWeekNumber($dayName)
    {
        $days = [
            'Monday' => Carbon::MONDAY,
            'Tuesday' => Carbon::TUESDAY,
            'Wednesday' => Carbon::WEDNESDAY,
            'Thursday' => Carbon::THURSDAY,
            'Friday' => Carbon::FRIDAY,
        ];

        return $days[$dayName] ?? Carbon::MONDAY;
    }

    /**
     * Helper: Format time string (e.g., "10:00am") to "HH:mm"
     */
    /**
     * Generate the next $count valid weekly slots for a Low Cost client's
     * allocated day/time, skipping the TC's holiday date ranges and any
     * already-booked sessions.
     */
    private function generateNextLowCostSlots(TrainingCounsellor $tc, Client $client, int $count): array
    {
        $formattedTime = $this->formatTimeToHHi($client->allocated_time);

        // Confirm the counsellor's current weekly availability still includes
        // the client's regular day/time before generating any slots for it -
        // a TC may have dropped that slot since it was first allocated.
        if (!$this->tcStillOffersSlot($tc, $client->allocated_day, $formattedTime)) {
            return [];
        }

        $earliestAllowed = Carbon::now()->addHours(48);

        // Find the first occurrence of the allocated weekday, at the allocated time
        $cursor = Carbon::parse($earliestAllowed->format('Y-m-d') . ' ' . $formattedTime);
        while (strtolower($cursor->format('l')) !== strtolower($client->allocated_day)) {
            $cursor->addDay();
        }
        if ($cursor->lt($earliestAllowed)) {
            $cursor->addWeek();
        }

        $slots = [];
        $weeksChecked = 0;

        while (count($slots) < $count && $weeksChecked < 52) {
            $onHoliday = $tc->holidays()
                ->whereDate('start_date', '<=', $cursor->format('Y-m-d'))
                ->whereDate('end_date', '>=', $cursor->format('Y-m-d'))
                ->exists();

            $isBooked = Session::where('tc_id', $tc->id)
                ->where('scheduled_at', $cursor->format('Y-m-d H:i:s'))
                ->whereIn('status', ['scheduled', 'completed'])
                ->exists();

            if (!$onHoliday && !$isBooked) {
                $slots[] = $cursor->copy();
            }

            $cursor->addWeek();
            $weeksChecked++;
        }

        return $slots;
    }

    /**
     * Whether a TC's current weekly availability grid still includes the
     * given day/time (day names and abstract slot keys are matched
     * case-insensitively by their resolved clock time).
     */
    private function tcStillOffersSlot(TrainingCounsellor $tc, ?string $day, string $formattedTime): bool
    {
        if (!$day) {
            return false;
        }

        $availability = array_change_key_case($tc->availability ?? [], CASE_LOWER);
        $daySlots = $availability[strtolower($day)] ?? [];

        foreach ($daySlots as $slot) {
            if ($this->formatTimeToHHi($slot) === $formattedTime) {
                return true;
            }
        }

        return false;
    }

    private function formatTimeToHHi($timeString)
    {
        if (!$timeString) return '00:00';

        // Mapping for abstract time slots
        $mapping = [
            'morning-early' => '10:00',
            'morning-late' => '11:00',
            'afternoon-early' => '13:00',
            'afternoon-late' => '16:00',
            'evening' => '17:00'
        ];

        if (isset($mapping[$timeString])) {
            return $mapping[$timeString];
        }

        // Handle range like "10:00am-10:50am" - take only the start
        if (str_contains($timeString, '-')) {
            $timeString = explode('-', $timeString)[0];
        }

        try {
            return Carbon::parse($timeString)->format('H:i');
        } catch (\Exception $e) {
            // Fallback for simple formats like "10am"
            $timestamp = strtotime($timeString);
            return $timestamp ? date('H:i', $timestamp) : '00:00';
        }
    }
}
