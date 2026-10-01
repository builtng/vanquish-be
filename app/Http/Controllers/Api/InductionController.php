<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Induction;
use App\Models\InductionAttendee;
use App\Models\TrainingCounsellor;
use App\Mail\DynamicEmail;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Validator;
use Carbon\Carbon;

class InductionController extends Controller
{
    /**
     * Get all inductions
     */
    public function index(Request $request)
    {
        $query = Induction::with(['trainingCounsellor', 'attendees.trainingCounsellor']);

        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        if ($request->has('upcoming')) {
            $query->where('scheduled_at', '>=', Carbon::now());
        }

        $inductions = $query->orderBy('id', 'desc')->get();

        return response()->json($inductions);
    }

    /**
     * Get a specific induction
     */
    public function show($id)
    {
        $induction = Induction::with(['trainingCounsellor', 'attendees.trainingCounsellor'])
            ->where('uuid', $id)
            ->orWhere('id', $id)
            ->firstOrFail();

        return response()->json($induction);
    }

    /**
     * Create a new induction
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'tc_id' => 'nullable|exists:training_counsellors,id',
            'scheduled_at' => 'required|date|after:' . now()->subMinutes(5)->toDateTimeString(),
            'scheduled_end_at' => 'nullable|date|after:scheduled_at',
            'duration_minutes' => 'nullable|integer|min:1',
            'location' => 'nullable|string|max:255',
            'notes' => 'nullable|string',
            'attendee_tc_ids' => 'required|array|min:1',
            'attendee_tc_ids.*' => 'exists:training_counsellors,id',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $scheduledEndAt = $request->scheduled_end_at;
        if (!$scheduledEndAt && $request->filled('duration_minutes')) {
            $scheduledEndAt = Carbon::parse($request->scheduled_at)->addMinutes((int) $request->duration_minutes);
        }

        $induction = Induction::create([
            'tc_id' => $request->tc_id,
            'scheduled_at' => $request->scheduled_at,
            'scheduled_end_at' => $scheduledEndAt,
            'location' => $request->location,
            'notes' => $request->notes,
            'status' => 'scheduled',
        ]);

        // Create attendees
        foreach ($request->attendee_tc_ids as $tcId) {
            $attendee = InductionAttendee::create([
                'induction_id' => $induction->id,
                'tc_id' => $tcId,
                'expires_at' => Carbon::now()->addHours(72),
            ]);

            // Send invitation email
            $tc = TrainingCounsellor::find($tcId);
            if ($tc && $tc->email) {
                $baseUrl = rtrim(config('app.frontend_url'), '/');
                $inductionDateFormatted = $this->formatInductionDate($induction);
                $startTime = Carbon::parse($induction->scheduled_at)->format('H:i');
                $endTime = $induction->scheduled_end_at ? Carbon::parse($induction->scheduled_end_at)->format('H:i') : null;

                app(\App\Services\EmailService::class)->sendAndLog(
                    $tc->email,
                    'induction_invitation',
                    [
                        'tc_name' => $tc->name,
                        'induction_date' => $inductionDateFormatted,
                        'start_time' => $startTime,
                        'end_time' => $endTime ?? '',
                        'location' => $induction->location ?? 'Online',
                        'notes' => $induction->notes ?? 'N/A',
                        'acceptance_url' => $baseUrl . '/induction/accept/' . $attendee->acceptance_token,
                        'decline_url' => $baseUrl . '/induction/decline/' . $attendee->acceptance_token
                    ]
                );

                // Add short delay to prevent SMTP burst limits
                sleep(1);
            }
        }

        $induction->load(['trainingCounsellor', 'attendees.trainingCounsellor']);

        return response()->json($induction, 201);
    }

    /**
     * Update an induction
     */
    public function update(Request $request, $id)
    {
        $induction = Induction::where('uuid', $id)
            ->orWhere('id', $id)
            ->firstOrFail();

        $validator = Validator::make($request->all(), [
            'tc_id' => 'nullable|exists:training_counsellors,id',
            'scheduled_at' => 'sometimes|date|after:' . now()->subMinutes(5)->toDateTimeString(),
            'scheduled_end_at' => 'nullable|date|after:scheduled_at',
            'duration_minutes' => 'nullable|integer|min:1',
            'location' => 'nullable|string|max:255',
            'notes' => 'nullable|string',
            'status' => 'sometimes|in:scheduled,completed,cancelled',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $updateData = $request->only(['tc_id', 'scheduled_at', 'location', 'notes', 'status']);
        if ($request->has('scheduled_end_at')) {
            $updateData['scheduled_end_at'] = $request->scheduled_end_at;
        } elseif ($request->filled('duration_minutes')) {
            $start = $request->has('scheduled_at') ? Carbon::parse($request->scheduled_at) : $induction->scheduled_at;
            $updateData['scheduled_end_at'] = Carbon::parse($start)->addMinutes((int) $request->duration_minutes);
        }

        $induction->update($updateData);

        $induction->load(['trainingCounsellor', 'attendees.trainingCounsellor']);

        return response()->json($induction);
    }

    /**
     * Accept induction invitation
     */
    public function acceptInvitation(Request $request, $token)
    {
        try {
            $attendee = InductionAttendee::where('acceptance_token', $token)->firstOrFail();
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'message' => 'Invalid or expired invitation token.',
            ], 404);
        }

        if (!$attendee->canAccept()) {
            return response()->json([
                'message' => 'This invitation has expired or has already been processed.',
                'expired' => $attendee->isExpired(),
                'status' => $attendee->status,
            ], 400);
        }

        $attendee->update([
            'status' => 'accepted',
            'accepted_at' => Carbon::now(),
        ]);

        $attendee->load(['induction.trainingCounsellor', 'trainingCounsellor']);

        return response()->json([
            'message' => 'Induction invitation accepted successfully.',
            'induction' => $attendee->induction,
            'attendee' => $attendee,
        ]);
    }

    /**
     * Decline induction invitation
     */
    public function declineInvitation(Request $request, $token)
    {
        $attendee = InductionAttendee::where('acceptance_token', $token)->firstOrFail();

        if ($attendee->status !== 'pending') {
            return response()->json([
                'message' => 'This invitation has already been processed.',
            ], 400);
        }

        $attendee->update([
            'status' => 'declined',
        ]);

        return response()->json([
            'message' => 'Induction invitation declined.',
        ]);
    }

    /**
     * Add attendees to an induction
     */
    public function addAttendees(Request $request, $id)
    {
        $induction = Induction::where('uuid', $id)
            ->orWhere('id', $id)
            ->firstOrFail();

        $validator = Validator::make($request->all(), [
            'attendee_tc_ids' => 'required|array|min:1',
            'attendee_tc_ids.*' => 'exists:training_counsellors,id',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        foreach ($request->attendee_tc_ids as $tcId) {
            // Check if attendee already exists
            $existing = InductionAttendee::where('induction_id', $induction->id)
                ->where('tc_id', $tcId)
                ->first();

            if (!$existing) {
                $attendee = InductionAttendee::create([
                    'induction_id' => $induction->id,
                    'tc_id' => $tcId,
                    'expires_at' => Carbon::now()->addHours(72),
                ]);

                // Send invitation email
                $tc = TrainingCounsellor::find($tcId);
                if ($tc && $tc->email) {
                    $baseUrl = rtrim(config('app.frontend_url'), '/');
                    $inductionDateFormatted = $this->formatInductionDate($induction);
                    $startTime = Carbon::parse($induction->scheduled_at)->format('H:i');
                    $endTime = $induction->scheduled_end_at ? Carbon::parse($induction->scheduled_end_at)->format('H:i') : null;

                    app(\App\Services\EmailService::class)->sendAndLog(
                        $tc->email,
                        'induction_invitation',
                        [
                            'tc_name' => $tc->name,
                            'induction_date' => $inductionDateFormatted,
                            'start_time' => $startTime,
                            'end_time' => $endTime ?? '',
                            'location' => $induction->location ?? 'Online',
                            'notes' => $induction->notes ?? 'N/A',
                            'acceptance_url' => $baseUrl . '/induction/accept/' . $attendee->acceptance_token,
                            'decline_url' => $baseUrl . '/induction/decline/' . $attendee->acceptance_token
                        ]
                    );

                    // Add short delay to prevent SMTP burst limits
                    sleep(1);
                }
            }
        }

        $induction->load(['trainingCounsellor', 'attendees.trainingCounsellor']);

        return response()->json($induction);
    }

    /**
     * Format induction date and duration string for emails.
     */
    protected function formatInductionDate(Induction $induction): string
    {
        $start = Carbon::parse($induction->scheduled_at);
        $end = $induction->scheduled_end_at ? Carbon::parse($induction->scheduled_end_at) : null;

        if ($end) {
            if ($start->isSameDay($end)) {
                return $start->format('l, jS F Y') . ' (' . $start->format('H:i') . ' - ' . $end->format('H:i') . ')';
            } else {
                return $start->format('l, jS F Y (H:i)') . ' - ' . $end->format('l, jS F Y (H:i)');
            }
        }

        return $start->format('l, jS F Y (H:i)');
    }
}
