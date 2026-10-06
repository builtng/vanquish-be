<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Consultation;
use App\Models\ActivityLog;
use App\Models\Message;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\DB;
use App\Mail\DynamicEmail;

use App\Services\EmailService;

class ConsultationController extends Controller
{
    protected $emailService;

    public function __construct(EmailService $emailService)
    {
        $this->emailService = $emailService;
    }

    public function index(Request $request)
    {
        $query = Consultation::with(['client', 'tc'])->whereHas('client');

        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        if ($request->has('date')) {
            $query->whereDate('scheduled_at', $request->date);
        }

        $consultations = $query->orderBy('id', 'desc')->get();

        // Ensure each client has service_type resolved from intake form if missing on client record
        foreach ($consultations as $consultation) {
            if ($consultation->client && empty($consultation->client->service_type)) {
                $intakeServiceType = \App\Models\ClientIntakeForm::where('client_id', $consultation->client->id)
                    ->orWhere('email', $consultation->client->email)
                    ->whereNotNull('service_type')
                    ->latest()
                    ->value('service_type');
                if ($intakeServiceType) {
                    $consultation->client->service_type = $intakeServiceType;
                    $consultation->client->saveQuietly();
                }
            }
        }

        return response()->json($consultations);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'client_id' => 'required|exists:clients,id',
            'tc_id' => 'nullable|exists:training_counsellors,id',
            'scheduled_at' => 'required|date',
            'notes' => 'nullable|string',
            'send_confirmation' => 'boolean',
            'is_fallback' => 'boolean',
        ]);

        // Check for double booking if a TC is assigned
        if (!empty($validated['tc_id'])) {
            $isDoubleBooked = Consultation::where('tc_id', $validated['tc_id'])
                ->where('scheduled_at', $validated['scheduled_at'])
                ->whereIn('status', ['scheduled'])
                ->exists();

            if ($isDoubleBooked) {
                return response()->json([
                    'message' => 'The selected Trainee Counsellor is already booked for this time slot.'
                ], 422);
            }
        }

        $validated['consultation_id'] = Consultation::generateNextConsultationId();
        $validated['status'] = 'scheduled';

        // Automatically link to a slot if one matches the scheduled time
        $matchingSlot = \App\Models\ConsultationSlot::where('consultation_datetime', $validated['scheduled_at'])->first();
        if ($matchingSlot) {
            $validated['consultation_slot_id'] = $matchingSlot->id;
        }

        $consultation = Consultation::create($validated);
        $consultation->load(['client', 'tc']);

        // Log activity
        ActivityLog::create([
            'user_id' => $request->user()->id,
            'action' => 'consultation_booked',
            'model_type' => Consultation::class,
            'model_id' => $consultation->id,
            'description' => "Consultation booked for client {$consultation->client->name}",
            'ip_address' => $request->ip(),
        ]);

        $baseUrl = rtrim(config('app.frontend_url'), '/');
        $zoomLink = DB::table('company_settings')->where('key', 'consultation_zoom_link')->value('value');
        $consultationLink = $zoomLink ?: ($baseUrl . "/consultation/{$consultation->id}"); // Use Zoom link if set, otherwise fallback to internal link


        // Create in-system message notification
        if ($consultation->tc_id && $consultation->tc) {
            $tc = $consultation->tc;
            try {
                Message::create([
                    'from_user_id' => $request->user()->id,
                    'to_tc_id' => $tc->id,
                    'subject' => "New Consultation Booking: {$consultation->client->name}",
                    'message' => "A consultation has been scheduled for {$consultation->client->name} on " .
                        \Carbon\Carbon::parse($consultation->scheduled_at)->format('l, F j, Y \a\t g:i A') .
                        ($consultation->notes ? "\n\nNotes: {$consultation->notes}" : ''),
                    'type' => 'staff_to_counsellor',
                    'related_client_id' => $consultation->client_id,
                    'related_consultation_id' => $consultation->id,
                ]);
            } catch (\Exception $e) {
                Log::error('Failed to create consultation booking message: ' . $e->getMessage());
            }
        }

        // Send confirmation email to client using EmailService for tracking
        if ($consultation->client && $consultation->client->email) {
            $this->emailService->sendAndLog(
                $consultation->client,
                'booking_confirmation',
                [
                    'client_name' => $consultation->client->name,
                    'booking_type' => 'consultation',
                    'counsellor_name' => $consultation->tc ? $consultation->tc->abbreviated_name : 'Assigned Counsellor',
                    'booking_details' => \Carbon\Carbon::parse($consultation->scheduled_at)->format('l, jS F Y (H:i)'),
                    'location' => 'Online',
                    'duration' => 50,
                    'consultation_link' => $consultationLink,
                    'date' => \Carbon\Carbon::parse($consultation->scheduled_at)->format('F j, Y'),
                    'time' => \Carbon\Carbon::parse($consultation->scheduled_at)->format('H:i')
                ]
            );

            // Update client stage to Consultation Booked
            $consultation->client->update(['stage' => 'Consultation Booked']);
        }

        return response()->json($consultation, 201);
    }

    public function show($id)
    {
        $consultation = Consultation::with(['client', 'tc'])->findOrFail($id);
        return response()->json($consultation);
    }

    public function update(Request $request, $id)
    {
        $consultation = Consultation::findOrFail($id);

        $validated = $request->validate([
            'scheduled_at' => 'sometimes|date',
            'notes' => 'nullable|string',
            'status' => 'sometimes|in:scheduled,completed,cancelled,no_show',
        ]);

        if (isset($validated['scheduled_at'])) {
            $matchingSlot = \App\Models\ConsultationSlot::where('consultation_datetime', $validated['scheduled_at'])->first();
            $validated['consultation_slot_id'] = $matchingSlot ? $matchingSlot->id : null;
        }

        $previousStatus = $consultation->status;
        $consultation->update($validated);

        if ($consultation->status === 'completed' && $previousStatus !== 'completed') {
            $client = $consultation->client;
            if ($client && $client->email) {
                $this->emailService->sendAgreementEmail($client);
            }
        }

        return response()->json($consultation->load(['client', 'tc']));
    }

    public function destroy(Request $request, $id)
    {
        $consultation = Consultation::findOrFail($id);

        // Release booked slot capacity if attached to a slot
        if ($consultation->consultation_slot_id) {
            \App\Models\ConsultationSlot::where('id', $consultation->consultation_slot_id)
                ->where('booked_slots', '>', 0)
                ->decrement('booked_slots');
        }

        // Cancel consultation - never delete from history!
        $consultation->update([
            'status' => 'cancelled',
            'cancelled_at' => now(),
            'cancelled_by' => $request->user()->id ?? null,
        ]);

        $clientName = $consultation->client ? $consultation->client->name : 'Client';
        $userName = $request->user() ? $request->user()->name : 'User';

        ActivityLog::create([
            'user_id' => $request->user()->id ?? null,
            'action' => 'consultation_cancelled',
            'model_type' => Consultation::class,
            'model_id' => $consultation->id,
            'description' => "Consultation #{$consultation->consultation_id} cancelled by {$userName} for client {$clientName}",
            'ip_address' => $request->ip(),
        ]);

        return response()->json([
            'message' => 'Consultation cancelled successfully',
            'consultation' => $consultation->fresh()->load(['client', 'tc']),
        ]);
    }

    public function complete(Request $request, $id)
    {
        $consultation = Consultation::findOrFail($id);

        $validated = $request->validate([
            'duration_minutes' => 'required|integer|min:1',
            'notes' => 'nullable|string',
            'outcome' => 'required|in:approved,not_approved,pending',
            'recommended_service' => 'nullable|string',
            'recommended_modality' => 'nullable|string',
            'risk_notes' => 'nullable|string',
            'next_steps' => 'nullable|string',
        ]);

        // Set payment amount based on recommended service
        $paymentAmount = 13.00; // Default for counselling services
        if ($validated['recommended_service'] === 'Coaching/Counselling') {
            $paymentAmount = 25.00;
        }

        $consultation->update([
            'status' => 'completed',
            'completed_at' => now(),
            'payment_amount' => $paymentAmount,
            ...$validated,
        ]);

        // Update client stage and automatically send agreement email
        $client = $consultation->client;
        if ($client) {
            if ($validated['outcome'] === 'approved') {
                $client->update(['stage' => 'Consultation Completed']);
            }

            if ($client->email) {
                // Send agreement email automatically upon completion
                $this->emailService->sendAgreementEmail($client);
            }
        }

        ActivityLog::create([
            'user_id' => $request->user()->id,
            'action' => 'consultation_completed',
            'model_type' => Consultation::class,
            'model_id' => $consultation->id,
            'description' => "Consultation completed with outcome: {$validated['outcome']}",
            'ip_address' => $request->ip(),
        ]);

        return response()->json($consultation->load(['client', 'tc']));
    }

    public function cancel(Request $request, $id)
    {
        $consultation = Consultation::findOrFail($id);

        if ($consultation->consultation_slot_id) {
            \App\Models\ConsultationSlot::where('id', $consultation->consultation_slot_id)
                ->where('booked_slots', '>', 0)
                ->decrement('booked_slots');
        }

        $consultation->update([
            'status' => 'cancelled',
            'cancelled_at' => now(),
            'cancelled_by' => $request->user()->id ?? null,
        ]);

        $clientName = $consultation->client ? $consultation->client->name : 'Client';
        $userName = $request->user() ? $request->user()->name : 'User';

        ActivityLog::create([
            'user_id' => $request->user()->id ?? null,
            'action' => 'consultation_cancelled',
            'model_type' => Consultation::class,
            'model_id' => $consultation->id,
            'description' => "Consultation #{$consultation->consultation_id} cancelled by {$userName} for client {$clientName}",
            'ip_address' => $request->ip(),
        ]);

        return response()->json($consultation->fresh()->load(['client', 'tc']));
    }

    public function reschedule(Request $request, $id)
    {
        $consultation = Consultation::findOrFail($id);

        $validated = $request->validate([
            'scheduled_at' => 'required|date',
        ]);

        $matchingSlot = \App\Models\ConsultationSlot::where('consultation_datetime', $validated['scheduled_at'])->first();
        $validated['consultation_slot_id'] = $matchingSlot ? $matchingSlot->id : null;

        $consultation->update($validated);

        // Send reschedule email if client has email
        if ($consultation->client && $consultation->client->email) {
            $baseUrl = rtrim(config('app.frontend_url'), '/');
            $zoomLink = DB::table('company_settings')->where('key', 'consultation_zoom_link')->value('value');
            $consultationLink = $zoomLink ?: ($baseUrl . "/consultation/{$consultation->id}");

            $this->emailService->sendAndLog(
                $consultation->client,
                'booking_rescheduled',
                [
                    'client_name' => $consultation->client->name,
                    'booking_type' => 'consultation',
                    'counsellor_name' => $consultation->tc ? $consultation->tc->abbreviated_name : 'Assigned Counsellor',
                    'new_scheduled_at' => \Carbon\Carbon::parse($consultation->scheduled_at)->format('l, jS F Y (H:i)'),
                    'notes' => $consultation->notes ?? 'N/A',
                    'consultation_link' => $consultationLink
                ]
            );
        }

        ActivityLog::create([
            'user_id' => $request->user()->id,
            'action' => 'consultation_rescheduled',
            'model_type' => Consultation::class,
            'model_id' => $consultation->id,
            'description' => "Consultation rescheduled to {$validated['scheduled_at']}",
            'ip_address' => $request->ip(),
        ]);

        return response()->json($consultation->load(['client', 'tc']));
    }

    public function stats(Request $request)
    {
        $today = now()->startOfDay();
        $todayEnd = now()->endOfDay();
        $startOfMonth = now()->startOfMonth();

        $stats = [
            'today_count' => Consultation::where('status', 'scheduled')
                ->whereHas('client')
                ->where(function ($query) use ($today) {
                    $query->whereDate('scheduled_at', $today)
                        ->orWhere(function ($q) use ($today) {
                            $q->whereNull('scheduled_at')
                                ->whereDate('created_at', $today);
                        });
                })
                ->count(),

            'upcoming_count' => Consultation::where('status', 'scheduled')
                ->whereHas('client')
                ->count(),

            'this_week_count' => Consultation::where('status', 'scheduled')
                ->whereHas('client')
                ->whereDate('scheduled_at', '>=', $today)
                ->count(),

            'completed_this_month' => Consultation::where('status', 'completed')
                ->whereHas('client')
                ->where('completed_at', '>=', $startOfMonth)
                ->count(),

            'pending_payment' => Consultation::where('payment_status', 'pending')
                ->whereHas('client')
                ->count(),
        ];

        return response()->json($stats);
    }
}
