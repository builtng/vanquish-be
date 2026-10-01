<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ClientIntakeForm;
use App\Models\Coupon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Stripe\Stripe;
use Stripe\PaymentIntent;
use App\Services\ConsultationBookingService;

class IntakeController extends Controller
{
    protected ConsultationBookingService $bookingService;

    public function __construct(ConsultationBookingService $bookingService)
    {
        Stripe::setApiKey(config('services.stripe.secret_key'));
        $this->bookingService = $bookingService;
    }

    /**
     * Handle payment success for intake forms
     */
    public function handlePaymentSuccess(Request $request)
    {
        // Normalize consultation_slot_id: if not a numeric DB ID or empty, set to null
        if ($request->has('consultation_slot_id')) {
            $slotId = $request->input('consultation_slot_id');
            if (!is_numeric($slotId) || empty($slotId)) {
                $request->merge(['consultation_slot_id' => null]);
            }
        }

        try {
            $validated = $request->validate([
                'payment_intent_id' => 'required|string',
                'intake_id' => 'required|exists:client_intake_forms,id',
                'coupon_code' => 'nullable|string',
                'consultation_slot_id' => 'nullable|exists:consultation_slots,id',
            ]);

            // 1. Verify Stripe Payment Intent
            $paymentIntent = PaymentIntent::retrieve($validated['payment_intent_id']);

            if (!$paymentIntent || $paymentIntent->status !== 'succeeded') {
                return response()->json([
                    'message' => 'Payment verification failed.',
                    'status' => 'error'
                ], 400);
            }

            // 2. Confirm coupon validity (if applied)
            if (!empty($validated['coupon_code'])) {
                $coupon = Coupon::where('code', $validated['coupon_code'])->first();
                if (!$coupon || !$coupon->isValid()) {
                    Log::warning("Invalid coupon used during payment success: " . $validated['coupon_code']);
                    // We don't necessarily fail the whole process if payment succeeded, 
                    // but we log it. In a stricter flow:
                    // return response()->json(['message' => 'Invalid coupon.'], 400);
                }
            }

            // 3. Update intake/payment status
            $intake = ClientIntakeForm::findOrFail($validated['intake_id']);
            $intake->update([
                'payment_status' => 'paid',
                'payment_reference' => $paymentIntent->id,
                'payment_amount' => $paymentIntent->amount / 100,
                'payment_method' => $paymentIntent->payment_method_types[0] ?? 'card',
                'paid_at' => now(),
                'status' => 'submitted' // Or 'completed' as per user example
            ]);

            // 4. Send the single confirmation email
            $client = $intake->client;
            if ($client && $client->email) {
                if (!empty($validated['consultation_slot_id'])) {
                    $consultation = \App\Models\Consultation::where('client_id', $client->id)->latest()->first();
                    if (!$consultation) {
                        $consultation = \App\Models\Consultation::create([
                            'consultation_id' => \App\Models\Consultation::generateNextConsultationId(),
                            'client_id' => $client->id,
                            'scheduled_at' => now(),
                            'payment_status' => 'paid',
                            'payment_amount' => $paymentIntent->amount / 100,
                            'stripe_payment_intent_id' => $paymentIntent->id,
                            'paid_at' => now(),
                            'payment_method' => $paymentIntent->payment_method_types[0] ?? 'card',
                            'status' => 'scheduled',
                        ]);
                    }
                    $client->update(['stage' => 'Consultation Booked']);
                    $this->bookingService->finalize($client, (int) $validated['consultation_slot_id'], $consultation);
                } else {
                    $emailService = app(\App\Services\EmailService::class);
                    $emailService->sendAndLog($client, 'payment_confirmation', [
                        'client_name' => $client->name,
                        'email' => $client->email
                    ]);
                }
            }

            Log::info("Intake payment confirmed for ID: {$intake->id}");

            return response()->json([
                'message' => 'Your intake submission was successful.',
                'status' => 'success',
                'intake' => $intake
            ]);
        } catch (\Exception $e) {
            Log::error('Payment Success Error: ' . $e->getMessage());
            return response()->json([
                'message' => 'Something went wrong. Please contact support.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Success page view (if accessed via web)
     */
    public function success()
    {
        // This would usually return a view, but in this SPA setup, 
        // it might just redirect to the frontend success page or return a message.
        return response()->json(['message' => 'Success confirmation page endpoint']);
    }
}
