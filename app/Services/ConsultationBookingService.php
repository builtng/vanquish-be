<?php

namespace App\Services;

use App\Models\Client;
use App\Models\Consultation;
use App\Models\ConsultationSlot;
use App\Models\TrainingCounsellor;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ConsultationBookingService
{
    protected EmailService $emailService;

    public function __construct(EmailService $emailService)
    {
        $this->emailService = $emailService;
    }

    /**
     * Attach a chosen consultation slot to the client's consultation and send
     * the single booking confirmation email (date/time + Zoom details).
     *
     * Idempotent: safe to call twice for the same payment (e.g. once from the
     * synchronous payment-confirm request and once from the async Stripe
     * webhook) — only the first call has any effect, so the client never
     * receives more than one confirmation email.
     */
    public function finalize(Client $client, int $consultationSlotId, ?Consultation $consultation = null): void
    {
        DB::transaction(function () use ($client, $consultationSlotId, $consultation) {
            $slot = ConsultationSlot::lockForUpdate()->find($consultationSlotId);
            if (!$slot) {
                Log::warning('ConsultationBookingService: slot not found', [
                    'slot_id' => $consultationSlotId,
                    'client_id' => $client->id,
                ]);
                return;
            }

            $target = $consultation
                ? Consultation::lockForUpdate()->find($consultation->id)
                : (Consultation::where('client_id', $client->id)->whereNull('consultation_slot_id')->latest()->lockForUpdate()->first()
                    ?? Consultation::where('client_id', $client->id)->latest()->lockForUpdate()->first());

            if (!$target) {
                $target = Consultation::create([
                    'consultation_id' => Consultation::generateNextConsultationId(),
                    'client_id' => $client->id,
                    'consultation_slot_id' => $slot->id,
                    'scheduled_at' => $slot->consultation_datetime,
                    'status' => 'scheduled',
                    'payment_status' => 'paid',
                ]);
            } else {
                // Already finalized with this slot (e.g. webhook fired after direct confirm
                // already booked the slot) — skip so we never double-send.
                if ($target->consultation_slot_id === $slot->id && $target->status === 'scheduled') {
                    return;
                }

                $target->update([
                    'consultation_slot_id' => $slot->id,
                    'scheduled_at' => $slot->consultation_datetime,
                    'status' => 'scheduled',
                    'payment_status' => 'paid',
                ]);
            }

            $client->update(['stage' => 'Consultation Booked']);

            // Create admin notification activity log
            \App\Models\ActivityLog::create([
                'user_id' => null,
                'action' => 'consultation_booked',
                'model_type' => Consultation::class,
                'model_id' => $target->id,
                'description' => "{$client->name} booked a consultation for " . Carbon::parse($slot->consultation_datetime)->format('l, M j, Y g:i A'),
                'changes' => [
                    'client_id' => $client->id,
                    'client_name' => $client->name,
                    'scheduled_at' => $slot->consultation_datetime,
                    'slot_id' => $slot->id,
                ],
            ]);

            $duration = (int) (DB::table('company_settings')->where('key', 'consultation_duration_minutes')->value('value') ?: 15);
            $zoomLink = DB::table('company_settings')->where('key', 'consultation_zoom_link')->value('value') ?: '';
            $meetingId = DB::table('company_settings')->where('key', 'consultation_meeting_id')->value('value') ?: '';
            $passcode = DB::table('company_settings')->where('key', 'consultation_passcode')->value('value') ?: '';

            $slotStart = Carbon::parse($slot->consultation_datetime);
            $slotEnd = $slotStart->copy()->addMinutes($duration);
            $scheduleDatetime = $slotStart->format('l, M j, Y g:i A') . '-' . $slotEnd->format('g:i A');

            $this->emailService->sendAndLog(
                $client,
                'consultation_booking_confirmation',
                [
                    'client_name' => $client->name,
                    'schedule_datetime' => $scheduleDatetime,
                    'timezone' => 'Europe/London',
                    'duration' => $duration,
                    'zoom_link' => $zoomLink,
                    'meeting_id' => $meetingId,
                    'passcode' => $passcode,
                    'support_email' => 'help@vanquishtherapies.co.uk',
                ]
            );
        });
    }

    /**
     * Book the client's initial consultation directly with a Qualified
     * counsellor's own consultation availability (Mid Range / Coaching &
     * Counselling delegation feature), instead of the generic Vanquish
     * Therapies consultation-slot pool used by `finalize()`.
     *
     * Idempotent in the same sense as `finalize()`: safe to call twice for
     * the same payment.
     */
    public function finalizeWithCounsellor(Client $client, TrainingCounsellor $tc, Carbon $scheduledAt, ?Consultation $consultation = null): void
    {
        DB::transaction(function () use ($client, $tc, $scheduledAt, $consultation) {
            $target = $consultation
                ? Consultation::lockForUpdate()->find($consultation->id)
                : (Consultation::where('client_id', $client->id)->whereNull('consultation_slot_id')->whereNull('tc_id')->latest()->lockForUpdate()->first()
                    ?? Consultation::where('client_id', $client->id)->latest()->lockForUpdate()->first());

            if (!$target) {
                $target = Consultation::create([
                    'consultation_id' => Consultation::generateNextConsultationId(),
                    'client_id' => $client->id,
                    'tc_id' => $tc->id,
                    'scheduled_at' => $scheduledAt,
                    'status' => 'scheduled',
                    'payment_status' => 'paid',
                    'is_fallback' => false,
                ]);
            } else {
                // Already finalized with this counsellor and time — skip double-sending.
                if ($target->tc_id === $tc->id && $target->scheduled_at && Carbon::parse($target->scheduled_at)->eq($scheduledAt) && $target->status === 'scheduled') {
                    return;
                }

                $alreadyBooked = Consultation::where('tc_id', $tc->id)
                    ->where('scheduled_at', $scheduledAt)
                    ->whereIn('status', ['scheduled', 'completed'])
                    ->where('id', '!=', $target->id)
                    ->exists();

                if ($alreadyBooked) {
                    Log::warning('ConsultationBookingService: counsellor consultation slot no longer available', [
                        'tc_id' => $tc->id,
                        'scheduled_at' => $scheduledAt->toDateTimeString(),
                    ]);
                    return;
                }

                $target->update([
                    'tc_id' => $tc->id,
                    'scheduled_at' => $scheduledAt,
                    'status' => 'scheduled',
                    'payment_status' => 'paid',
                    'is_fallback' => false,
                ]);
            }

            $client->update([
                'stage' => 'Consultation Booked',
                'preferred_tc_id' => $tc->id,
            ]);

            // Create admin notification activity log
            \App\Models\ActivityLog::create([
                'user_id' => null,
                'action' => 'consultation_booked',
                'model_type' => Consultation::class,
                'model_id' => $target->id,
                'description' => "{$client->name} booked a consultation with {$tc->name} for " . $scheduledAt->format('l, M j, Y g:i A'),
                'changes' => [
                    'client_id' => $client->id,
                    'client_name' => $client->name,
                    'tc_id' => $tc->id,
                    'scheduled_at' => $scheduledAt->toDateTimeString(),
                ],
            ]);

            $duration = (int) (DB::table('company_settings')->where('key', 'consultation_duration_minutes')->value('value') ?: 15);
            $zoomLink = DB::table('company_settings')->where('key', 'consultation_zoom_link')->value('value') ?: '';
            $meetingId = DB::table('company_settings')->where('key', 'consultation_meeting_id')->value('value') ?: '';
            $passcode = DB::table('company_settings')->where('key', 'consultation_passcode')->value('value') ?: '';

            $slotStart = Carbon::parse($scheduledAt);
            $slotEnd = $slotStart->copy()->addMinutes($duration);
            $scheduleDatetime = $slotStart->format('l, M j, Y g:i A') . '-' . $slotEnd->format('g:i A');

            $this->emailService->sendAndLog(
                $client,
                'consultation_booking_confirmation',
                [
                    'client_name' => $client->name,
                    'schedule_datetime' => $scheduleDatetime,
                    'timezone' => 'Europe/London',
                    'duration' => $duration,
                    'counsellor_name' => $tc->name,
                    'zoom_link' => $zoomLink,
                    'meeting_id' => $meetingId,
                    'passcode' => $passcode,
                    'support_email' => 'help@vanquishtherapies.co.uk',
                ]
            );
        });
    }
}
