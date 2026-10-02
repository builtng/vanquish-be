<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Consultation;
use App\Models\Client;
use App\Models\ClientIntakeForm;
use App\Models\ActivityLog;
use App\Models\TrainingCounsellor;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Stripe\Stripe;
use Stripe\PaymentIntent;
use Stripe\Exception\ApiErrorException;
use Illuminate\Support\Facades\Mail;
use App\Mail\DynamicEmail;
use App\Services\ConsultationBookingService;
use Carbon\Carbon;

class PaymentController extends Controller
{
    protected ConsultationBookingService $bookingService;

    public function __construct(ConsultationBookingService $bookingService)
    {
        $this->bookingService = $bookingService;
        $mode = config('services.stripe.mode', 'test');
        $secretKey = config('services.stripe.secret_key');
        $publishableKey = config('services.stripe.key');

        // Log active mode
        Log::info("Stripe Integration Initialized. Mode: " . strtoupper($mode));

        if (empty($secretKey)) {
            Log::error("Stripe Exception: Secret key is missing for $mode mode. Please check your .env file.");
        } elseif (strpos($secretKey, 'sk_test_') === false && strpos($secretKey, 'sk_live_') === false) {
            Log::error('Stripe Exception: Invalid API key format. Should start with sk_test_ or sk_live_.');
        }

        // Check for mode mismatch
        if (!empty($secretKey) && !empty($publishableKey)) {
            $secretIsTest = strpos($secretKey, 'sk_test_') === 0;
            $publishableIsTest = strpos($publishableKey, 'pk_test_') === 0;

            if ($secretIsTest !== $publishableIsTest) {
                $secretMode = $secretIsTest ? 'test' : 'live';
                $publishableMode = $publishableIsTest ? 'test' : 'live';
                Log::critical("CRITICAL: Stripe Mode Mismatch! Secret key is in $secretMode mode, but Publishable key is in $publishableMode mode. This will cause payment failures.");
            }

            // Check if current mode settings match the keys being used
            if (($mode === 'test' && !$secretIsTest) || ($mode === 'live' && $secretIsTest)) {
                Log::warning("Stripe Warning: STRIPE_MODE is set to $mode, but the keys provided appear to be for " . ($secretIsTest ? 'test' : 'live') . " mode.");
            }
        }

        Stripe::setApiKey($secretKey);
    }

    /**
     * Resolve Client model from integer ID, UUID string, or client_id string.
     */
    private function resolveClient($identifier): ?Client
    {
        if (empty($identifier)) {
            return null;
        }

        return Client::where('id', $identifier)
            ->orWhere('uuid', $identifier)
            ->orWhere('client_id', $identifier)
            ->first();
    }

    /**
     * Create a payment intent for a consultation
     */
    public function createPaymentIntent(Request $request)
    {
        if ($request->has('coupon_code')) {
            $request->merge(['coupon_code' => strtoupper(trim($request->coupon_code))]);
        }

        if ($request->has('consultation_slot_id')) {
            $slotId = $request->input('consultation_slot_id');
            if (!is_numeric($slotId) || empty($slotId)) {
                $request->merge(['consultation_slot_id' => null]);
            }
        }

        $validated = $request->validate([
            'client_id' => 'required',
            'amount' => 'required|numeric|min:0.50',
            'currency' => 'nullable|string|size:3',
            'coupon_code' => 'nullable|string|exists:coupons,code',
            'payment_type' => 'required|in:consultation,session,session_block',
            'session_ids' => 'nullable|array',
            'session_ids.*' => 'exists:consultation_sessions,id',
            'consultation_slot_id' => 'nullable|exists:consultation_slots,id',
        ]);


        $currency = $validated['currency'] ?? 'gbp';
        $originalAmount = $validated['amount'];
        $finalAmount = $originalAmount;
        $discountAmount = 0;
        $couponId = null;

        // Apply coupon if exists
        if (!empty($validated['coupon_code'])) {
            $coupon = \App\Models\Coupon::where('code', $validated['coupon_code'])->first();
            if ($coupon && $coupon->isValid()) {
                $couponId = $coupon->id;
                if ($coupon->type === 'fixed') {
                    $finalAmount = max(0.50, $originalAmount - $coupon->value); // Stripe minimum is mostly 0.50
                    $discountAmount = $originalAmount - $finalAmount;
                } elseif ($coupon->type === 'percent') {
                    $discount = ($originalAmount * $coupon->value) / 100;
                    $finalAmount = max(0.50, $originalAmount - $discount);
                    $discountAmount = $originalAmount - $finalAmount;
                }

                // Track usage (increment on confirm, but for intent we just validate)
            }
        }

        $amountInPence = (int)round($finalAmount * 100);

        try {
            $client = $this->resolveClient($validated['client_id']);

            if (!$client) {
                return response()->json(['message' => 'Client not found'], 404);
            }

            // Create or retrieve Stripe customer
            $customerId = $this->getOrCreateStripeCustomer($client, $request->email ?? $client->email);

            $metadata = [
                'client_id' => (string) $client->id,
                'client_name' => (string) $client->name,
                'payment_type' => (string) $validated['payment_type'],
                'original_amount' => (string) $originalAmount,
                'discount_amount' => (string) $discountAmount
            ];

            if (isset($validated['session_ids'])) {
                $metadata['session_ids'] = implode(',', $validated['session_ids']);
            }
            if (!empty($validated['coupon_code'])) {
                $metadata['coupon_code'] = (string) $validated['coupon_code'];
            }
            if (!empty($validated['consultation_slot_id'])) {
                $metadata['consultation_slot_id'] = (string) $validated['consultation_slot_id'];
            }

            // Create payment intent
            $paymentIntent = PaymentIntent::create([
                'amount' => $amountInPence,
                'currency' => $currency,
                'customer' => $customerId,
                'metadata' => $metadata,
                'automatic_payment_methods' => [
                    'enabled' => true,
                ],
            ]);


            return response()->json([
                'client_secret' => $paymentIntent->client_secret,
                'payment_intent_id' => $paymentIntent->id,
                'amount' => $finalAmount,
                'discount' => $discountAmount
            ]);
        } catch (ApiErrorException $e) {
            $message = $e->getMessage();
            $type = get_class($e);
            Log::error("Stripe API Error ($type): $message", [
                'client_id' => $validated['client_id'] ?? 'unknown',
                'amount' => $amountInPence ?? 'unknown',
                'error_detail' => $e->getError()
            ]);

            // Specific handling for key issues
            if (strpos($message, 'api_key') !== false || strpos($message, 'No such') !== false) {
                Log::critical("CRITICAL: Stripe API Key mismatch or invalid. Check your .env variables and Stripe Dashboard.");
            }

            return response()->json([
                'message' => 'Failed to create payment intent',
                'error' => $message,
                'type' => $type
            ], 500);
        } catch (\Exception $e) {
            Log::error('General Payment Intent Error: ' . $e->getMessage());
            return response()->json([
                'message' => 'An unexpected error occurred during payment initialization',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Confirm payment and update consultation
     */
    public function confirmPayment(Request $request)
    {
        $validated = $request->validate([
            'payment_intent_id' => 'required|string',
            'consultation_id' => 'nullable',
            'client_id' => 'required',
            'intake_id' => 'nullable',
            'consultation_slot_id' => 'nullable',
            'consultation_with_tc_uuid' => 'nullable|string|exists:training_counsellors,uuid',
            'consultation_datetime' => 'nullable|date',
        ]);

        $client = $this->resolveClient($validated['client_id']);
        if ($client) {
            $validated['client_id'] = $client->id;
        }

        try {
            $paymentIntent = PaymentIntent::retrieve($validated['payment_intent_id']);

            if ($paymentIntent->status !== 'succeeded') {
                return response()->json([
                    'message' => 'Payment not completed',
                    'status' => $paymentIntent->status,
                ], 400);
            }

            $paymentType = $paymentIntent->metadata->payment_type ?? 'consultation';
            $sessionIdsStr = $paymentIntent->metadata->session_ids ?? null;
            $sessionIds = $sessionIdsStr ? explode(',', $sessionIdsStr) : [];

            if ($paymentType === 'consultation') {
                // Update or create consultation with payment info
                $consultation = Consultation::where('client_id', $validated['client_id'])
                    ->whereNull('paid_at')
                    ->latest()
                    ->first();

                if ($consultation) {
                    $consultation->update([
                        'payment_status' => 'paid',
                        'payment_amount' => $paymentIntent->amount / 100,
                        'stripe_payment_intent_id' => $paymentIntent->id,
                        'stripe_customer_id' => $paymentIntent->customer,
                        'paid_at' => now(),
                        'payment_method' => $paymentIntent->payment_method_types[0] ?? 'card',
                    ]);

                    // Update related Client Intake Form
                    $intake = ClientIntakeForm::where('client_id', $validated['client_id'])->latest()->first();
                    if ($intake) {
                        $intake->update([
                            'payment_status' => 'paid',
                            'payment_reference' => $paymentIntent->id,
                            'payment_amount' => $paymentIntent->amount / 100,
                            'payment_method' => $paymentIntent->payment_method_types[0] ?? 'card',
                            'paid_at' => now(),
                            'status' => 'processed'
                        ]);
                    }

                    // Also update client stage
                    $client = Client::find($validated['client_id']);
                    if ($client) {
                        $client->update(['stage' => 'Consultation Booked']);
                    }
                } else {
                    // Create consultation if it doesn't exist
                    $consultation = Consultation::create([
                        'consultation_id' => Consultation::generateNextConsultationId(),
                        'client_id' => $validated['client_id'],
                        'scheduled_at' => now(),
                        'payment_status' => 'paid',
                        'payment_amount' => $paymentIntent->amount / 100,
                        'stripe_payment_intent_id' => $paymentIntent->id,
                        'stripe_customer_id' => $paymentIntent->customer,
                        'paid_at' => now(),
                        'payment_method' => $paymentIntent->payment_method_types[0] ?? 'card',
                        'status' => 'scheduled',
                    ]);

                    // Also update client stage
                    $client = Client::find($validated['client_id']);
                    if ($client) {
                        $client->update(['stage' => 'Consultation Booked']);
                    }
                }
            } elseif ($paymentType === 'session' || $paymentType === 'session_block') {
                if (!empty($sessionIds)) {
                    $sessions = \App\Models\Session::whereIn('id', $sessionIds)->get();
                    foreach ($sessions as $session) {
                        /** @var \App\Models\Session $session */
                        $session->update([

                            'payment_status' => 'paid',
                            'payment_amount' => ($paymentIntent->amount / 100) / count($sessionIds),
                            'stripe_payment_intent_id' => $paymentIntent->id,
                            'paid_at' => now(),
                            'payment_method' => $paymentIntent->payment_method_types[0] ?? 'card',
                        ]);
                        $this->notifyCounsellor($session);
                    }
                }
            }



            // Update Client Intake Form status if available
            $intakeId = $validated['intake_id'] ?? null;
            if ($intakeId) {
                $intake = ClientIntakeForm::find($intakeId);
            } else {
                // Try to find the latest intake for this client
                $intake = ClientIntakeForm::where('client_id', $validated['client_id'])->latest()->first();
            }

            if ($intake) {
                $intake->update([
                    'payment_status' => 'paid',
                    'payment_reference' => $paymentIntent->id,
                    'payment_amount' => $paymentIntent->amount / 100,
                    'payment_method' => $paymentIntent->payment_method_types[0] ?? 'card',
                    'paid_at' => now(),
                    'status' => 'processed'
                ]);
            }

            // Send the single confirmation email
            try {
                $client = Client::find($validated['client_id']);
                if ($client) {
                    if ($paymentType === 'consultation' && !empty($validated['consultation_with_tc_uuid']) && !empty($validated['consultation_datetime']) && isset($consultation)) {
                        // Booking directly with a Qualified counsellor's own
                        // consultation availability (Mid Range / Coaching &
                        // Counselling delegation feature).
                        $consultationTc = TrainingCounsellor::where('uuid', $validated['consultation_with_tc_uuid'])->first();
                        if ($consultationTc) {
                            $this->bookingService->finalizeWithCounsellor(
                                $client,
                                $consultationTc,
                                Carbon::parse($validated['consultation_datetime']),
                                $consultation
                            );
                        }
                    } elseif ($paymentType === 'consultation') {
                        $slotId = $validated['consultation_slot_id'] ?? $consultation?->consultation_slot_id;
                        if (!$slotId && isset($intake) && $intake) {
                            $slotId = $intake->consultation_slot_id ?? null;
                        }

                        if ($slotId && isset($consultation)) {
                            // One automatic email: booking confirmed with the
                            // chosen date/time and Zoom details.
                            $this->bookingService->finalize($client, (int) $slotId, $consultation);
                        } else {
                            $emailService = new \App\Services\EmailService();
                            $emailService->sendAndLog($client, 'payment_confirmation', [
                                'client_name' => $client->name,
                                'email' => $client->email
                            ]);
                        }
                    } else {
                        $emailService = new \App\Services\EmailService();
                        $emailService->sendAndLog($client, 'payment_confirmation', [
                            'client_name' => $client->name,
                            'email' => $client->email
                        ]);
                    }
                }
            } catch (\Exception $e) {
                Log::error('Failed to send dynamic payment confirmation/booking email: ' . $e->getMessage());
            }

            // Log activity (works for both authenticated admin and public clients)
            $userId = $request->user()?->id;
            if ($paymentType === 'consultation' && isset($consultation)) {
                ActivityLog::create([
                    'user_id' => $userId,
                    'action' => 'payment_completed',
                    'model_type' => Consultation::class,
                    'model_id' => $consultation->id,
                    'description' => "Payment of £{$consultation->payment_amount} completed for consultation",
                    'ip_address' => $request->ip(),
                ]);
            } elseif (($paymentType === 'session' || $paymentType === 'session_block') && isset($sessions) && count($sessions) > 0) {
                ActivityLog::create([
                    'user_id' => $userId,
                    'action' => 'payment_completed',
                    'model_type' => \App\Models\Session::class,
                    'model_id' => $sessions[0]->id,
                    'description' => "Payment of £" . ($paymentIntent->amount / 100) . " completed for session(s)",
                    'ip_address' => $request->ip(),
                ]);
            }

            if ($paymentType === 'consultation' && isset($consultation)) {
                return response()->json([
                    'message' => 'Payment confirmed successfully',
                    'consultation' => $consultation->load(['client']),
                ]);
            } else {
                return response()->json([
                    'message' => 'Payment confirmed successfully',
                    'sessions' => isset($sessions) ? $sessions : [],
                ]);
            }
        } catch (ApiErrorException $e) {
            Log::error('Stripe Payment Confirmation Error: ' . $e->getMessage(), [
                'payment_intent_id' => $validated['payment_intent_id'],
                'client_id' => $validated['client_id'],
                'error' => $e->getError()
            ]);
            return response()->json([
                'message' => 'Failed to confirm payment',
                'error' => $e->getMessage(),
            ], 500);
        } catch (\Exception $e) {
            Log::error('General Payment Confirmation Error: ' . $e->getMessage());
            return response()->json([
                'message' => 'An unexpected error occurred during payment confirmation',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Handle Stripe webhook events
     */
    public function handleWebhook(Request $request)
    {
        $payload = $request->getContent();
        $sigHeader = $request->header('Stripe-Signature');
        $endpointSecret = config('services.stripe.webhook_secret');

        // Validate webhook secret is configured
        if (!$endpointSecret) {
            Log::error('Stripe webhook secret not configured');
            return response()->json(['error' => 'Webhook configuration error'], 500);
        }

        try {
            $event = \Stripe\Webhook::constructEvent(
                $payload,
                $sigHeader,
                $endpointSecret
            );
        } catch (\UnexpectedValueException $e) {
            // Invalid payload
            Log::warning('Stripe Webhook Invalid Payload', [
                'ip' => $request->ip(),
                'error' => $e->getMessage(),
                'payload_preview' => substr($payload, 0, 100)
            ]);
            return response()->json(['error' => 'Invalid payload'], 400);
        } catch (\Stripe\Exception\SignatureVerificationException $e) {
            // Invalid signature - potential security issue
            Log::warning('Stripe Webhook Invalid Signature', [
                'ip' => $request->ip(),
                'error' => $e->getMessage(),
                'signature' => $sigHeader
            ]);
            return response()->json(['error' => 'Invalid signature'], 400);
        } catch (\Exception $e) {
            Log::error("Stripe Webhook Processing Error: " . $e->getMessage(), [
                'type' => get_class($e),
                'trace' => $e->getTraceAsString()
            ]);
            return response()->json(['error' => 'Webhook processing failed'], 500);
        }

        // Handle the event
        switch ($event->type) {
            case 'payment_intent.succeeded':
                $paymentIntent = $event->data->object;
                $this->handlePaymentSuccess($paymentIntent);
                break;

            case 'payment_intent.payment_failed':
                $paymentIntent = $event->data->object;
                $this->handlePaymentFailure($paymentIntent);
                break;

            default:
                Log::info('Unhandled Stripe event: ' . $event->type);
        }

        return response()->json(['received' => true]);
    }

    /**
     * Get or create Stripe customer
     */
    private function getOrCreateStripeCustomer(Client $client, $email)
    {
        if ($client->stripe_customer_id) {
            try {
                $customer = \Stripe\Customer::retrieve($client->stripe_customer_id);
                return $customer->id;
            } catch (\Exception $e) {
                // Customer doesn't exist, create new one
            }
        }

        $customer = \Stripe\Customer::create([
            'email' => $email,
            'name' => (string) $client->name,
            'metadata' => [
                'client_id' => (string) $client->id,
            ],
        ]);

        // Update client with Stripe customer ID if column exists
        if (Schema::hasColumn('clients', 'stripe_customer_id')) {
            $client->update(['stripe_customer_id' => $customer->id]);
        }

        return $customer->id;
    }

    /**
     * Handle successful payment
     */
    private function handlePaymentSuccess($paymentIntent)
    {
        $clientId = $paymentIntent->metadata->client_id ?? null;
        if (!$clientId) {
            return;
        }

        $paymentType = $paymentIntent->metadata->payment_type ?? 'consultation';
        $sessionIdsStr = $paymentIntent->metadata->session_ids ?? null;
        $sessionIds = $sessionIdsStr ? explode(',', $sessionIdsStr) : [];

        if ($paymentType === 'consultation') {
            $consultation = Consultation::where('client_id', $clientId)
                ->whereNull('paid_at')
                ->latest()
                ->first();

            if ($consultation) {
                $consultation->update([
                    'payment_status' => 'paid',
                    'payment_amount' => $paymentIntent->amount / 100,
                    'stripe_payment_intent_id' => $paymentIntent->id,
                    'stripe_customer_id' => $paymentIntent->customer,
                    'paid_at' => now(),
                    'payment_method' => $paymentIntent->payment_method_types[0] ?? 'card',
                ]);

                // Update related Client Intake Form
                $intake = ClientIntakeForm::where('client_id', $clientId)->latest()->first();
                if ($intake) {
                    $intake->update([
                        'payment_status' => 'paid',
                        'payment_reference' => $paymentIntent->id,
                        'payment_amount' => $paymentIntent->amount / 100,
                        'payment_method' => $paymentIntent->payment_method_types[0] ?? 'card',
                        'paid_at' => now(),
                        'status' => 'processed'
                    ]);
                }
            }
        } elseif (($paymentType === 'session' || $paymentType === 'session_block') && !empty($sessionIds)) {
            $sessions = \App\Models\Session::whereIn('id', $sessionIds)->get();
            foreach ($sessions as $session) {
                /** @var \App\Models\Session $session */
                $session->update([

                    'payment_status' => 'paid',
                    'payment_amount' => ($paymentIntent->amount / 100) / count($sessionIds),
                    'stripe_payment_intent_id' => $paymentIntent->id,
                    'paid_at' => now(),
                    'payment_method' => $paymentIntent->payment_method_types[0] ?? 'card',
                ]);
                $this->notifyCounsellor($session);
            }
        }


        // Send the single confirmation email
        $client = Client::find($clientId);
        if ($client) {
            $slotId = $paymentIntent->metadata->consultation_slot_id ?? null;
            if ($paymentType === 'consultation') {
                if (!$slotId && isset($consultation) && $consultation->consultation_slot_id) {
                    $slotId = $consultation->consultation_slot_id;
                }
                if ($slotId && isset($consultation)) {
                    // One automatic email: booking confirmed with the chosen
                    // date/time and Zoom details. finalize() is idempotent, so
                    // if the direct /payments/confirm call already handled this
                    // (the common path — the webhook is a fallback), this is a
                    // no-op and we never double-send.
                    $this->bookingService->finalize($client, (int) $slotId, $consultation);
                }
            } else {
                $emailService = app(\App\Services\EmailService::class);
                $emailService->sendAndLog($client, 'payment_confirmation', [
                    'client_name' => $client->name,
                    'email' => $client->email
                ]);
            }
        }
    }


    /**
     * Handle failed payment
     */
    private function handlePaymentFailure($paymentIntent)
    {
        $clientId = $paymentIntent->metadata->client_id ?? null;
        if (!$clientId) {
            return;
        }

        $consultation = Consultation::where('client_id', $clientId)
            ->whereNull('paid_at')
            ->latest()
            ->first();

        if ($consultation) {
            $consultation->update([
                'payment_status' => 'failed',
            ]);
        }
    }

    private function notifyCounsellor($session)
    {
        if ($session->tc && $session->tc->email && $session->tc->counsellor_type !== 'Trainee') {
            app(\App\Services\EmailService::class)->sendAndLog(
                $session->tc->email,
                'booking_notification',
                [
                    'tc_name' => $session->tc->name,
                    'client_name' => $session->client->name,
                    'booking_type' => 'session',
                    'scheduled_at' => \Carbon\Carbon::parse($session->scheduled_at)->format('l, jS F Y (H:i)'),
                    'notes' => $session->notes ?? 'N/A'
                ]
            );
        }
    }
}
