<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ConsultationSlot;
use App\Models\Consultation;
use App\Models\Client;
use Illuminate\Http\Request;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use App\Services\ConsultationBookingService;
use App\Services\EmailService;

class ClientConsultationSlotController extends Controller
{
    protected $emailService;
    protected ConsultationBookingService $bookingService;

    public function __construct(EmailService $emailService, ConsultationBookingService $bookingService)
    {
        $this->emailService = $emailService;
        $this->bookingService = $bookingService;
    }

    public function getAvailableSlots(Request $request)
    {
        $order = strtolower($request->query('order', 'desc'));
        if (!in_array($order, ['asc', 'desc'])) {
            $order = 'desc';
        }

        $slots = ConsultationSlot::where('status', 'available')
            ->where('consultation_datetime', '>=', Carbon::now())
            ->where(function ($query) {
                $query->whereNull('max_slots')
                    ->orWhereRaw('booked_slots < max_slots');
            })
            ->orderBy('consultation_datetime', $order)
            ->get()
            ->unique('consultation_datetime')
            ->values();

        return response()->json($slots);
    }

    public function bookConsultation(Request $request)
    {
        $validated = $request->validate([
            'client_uuid' => 'required|exists:clients,uuid',
            'consultation_slot_id' => 'required|exists:consultation_slots,id',
        ]);

        $client = Client::where('uuid', $validated['client_uuid'])->firstOrFail();

        // Use a transaction and lock for update to prevent race conditions
        return DB::transaction(function () use ($client, $validated) {
            $slot = ConsultationSlot::lockForUpdate()->findOrFail($validated['consultation_slot_id']);

            // Verify slot is available
            if ($slot->status !== 'available' && $slot->status !== 'open') {
                if ($slot->status === 'full' || ($slot->max_slots && $slot->booked_slots >= $slot->max_slots)) {
                    return response()->json(['message' => 'This slot is already fully booked.'], 400);
                }
                if ($slot->status === 'closed') {
                    return response()->json(['message' => 'This slot is closed.'], 400);
                }
            }

            if (Carbon::parse($slot->consultation_datetime)->isPast()) {
                return response()->json(['message' => 'Cannot book a slot in the past.'], 400);
            }

            // Check if they already booked this exact slot to prevent double-booking.
            // Must run before the find-or-create below: once a booking is finalized,
            // consultation_slot_id is no longer null, so the lookup below would find
            // nothing and fall into creating a duplicate, broken consultation record.
            if (Consultation::where('client_id', $client->id)->where('consultation_slot_id', $slot->id)->exists()) {
                return response()->json(['message' => 'You have already booked this slot.'], 400);
            }

            // Find existing consultation or let finalize create one
            $consultation = Consultation::where('client_id', $client->id)
                ->whereNull('consultation_slot_id')
                ->where(function ($query) {
                    $query->where('payment_status', 'paid')
                        ->orWhere('status', '!=', 'completed');
                })
                ->latest()
                ->first();

            $this->bookingService->finalize($client, (int) $slot->id, $consultation);

            $consultation = Consultation::where('client_id', $client->id)
                ->where('consultation_slot_id', $slot->id)
                ->latest()
                ->first();

            return response()->json([
                'message' => 'Consultation booked successfully',
                'consultation' => $consultation ? $consultation->load(['consultationSlot']) : null,
            ]);
        });
    }
}
