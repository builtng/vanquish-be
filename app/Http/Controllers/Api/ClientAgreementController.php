<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\Consultation;
use App\Models\ActivityLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Barryvdh\DomPDF\Facade\Pdf;

class ClientAgreementController extends Controller
{
    /**
     * Resolve client by client UUID, client ID, consultation UUID, consultation ID, or email.
     */
    private function resolveClient(?string $identifier, ?string $email = null): ?Client
    {
        if (!empty($identifier)) {
            $client = Client::where('uuid', $identifier)->orWhere('client_id', $identifier)->first();
            if ($client) {
                return $client;
            }

            $consultation = Consultation::where('uuid', $identifier)
                ->orWhere('consultation_id', $identifier)
                ->orWhere('id', $identifier)
                ->first();

            if ($consultation && $consultation->client) {
                return $consultation->client;
            }
        }

        if (!empty($email)) {
            return Client::where('email', strtolower(trim($email)))->latest('id')->first();
        }

        return null;
    }

    /**
     * Check if a client has already signed their service agreement.
     */
    private function isAgreementSigned(Client $client): bool
    {
        return $client->agreement_status === 'signed'
            || !empty($client->agreement_signed_at)
            || in_array($client->stage, ['Agreement Signed', 'Matched with TC', 'Sessions Bookable', 'Sessions Booked', 'Active Therapy']);
    }

    /**
     * Handle agreement submission from custom form
     */
    public function submitAgreement(Request $request)
    {
        try {
            // Log the incoming request for debugging
            Log::info('Client Agreement Submission Received', [
                'email' => $request->input('email'),
                'service_type' => $request->input('service_type'),
            ]);

            // Validate the request
            $validated = $request->validate([
                'email' => 'required|email',
                'client_uuid' => 'nullable|string',
                'full_name' => 'required|string|max:255',
                'case_study_consent' => 'nullable|string|in:yes,no',
                'emergency_contact_name' => 'required|string|max:255',
                'emergency_contact_relationship' => 'required|string|max:255',
                'emergency_contact_phone' => 'required|string|max:50',
                'gp_name' => 'required|string|max:255',
                'gp_practice_name' => 'required|string|max:255',
                'gp_practice_phone' => 'required|string|max:50',
                'current_address' => 'required|string|max:1000',
                'signature_data' => 'required|string',
                'signature_date' => 'required|date',
                'terms_agreed' => 'required|accepted',
                'service_type' => 'nullable|string|max:50',
            ]);

            // Find client by identifier or email
            $clientEmail = strtolower(trim($validated['email']));
            $client = $this->resolveClient($validated['client_uuid'] ?? null, $clientEmail);

            if (!$client) {
                Log::warning('Client Agreement: Client not found', [
                    'email' => $clientEmail,
                    'uuid' => $validated['client_uuid'] ?? null,
                ]);

                return response()->json([
                    'message' => 'Client not found. Please ensure you are using the correct email address.',
                ], 404);
            }

            if ($this->isAgreementSigned($client)) {
                return response()->json([
                    'message' => 'You have already signed your service agreement.',
                    'already_signed' => true,
                    'signed_at' => $client->agreement_signed_at,
                ], 400);
            }

            // Save signature image to storage
            $signatureUrl = null;
            if (!empty($validated['signature_data'])) {
                try {
                    // Extract base64 data
                    $signatureData = $validated['signature_data'];
                    if (strpos($signatureData, 'data:image') === 0) {
                        $signatureData = substr($signatureData, strpos($signatureData, ',') + 1);
                    }

                    $signatureImage = base64_decode($signatureData);

                    // Generate unique filename
                    $filename = 'signatures/' . $client->uuid . '_' . time() . '.png';

                    // Store in public storage
                    Storage::disk('public')->put($filename, $signatureImage);

                    $signatureUrl = $filename;

                    Log::info('Signature saved successfully', [
                        'client_id' => $client->id,
                        'filename' => $filename,
                    ]);
                } catch (\Exception $e) {
                    Log::error('Failed to save signature', [
                        'client_id' => $client->id,
                        'error' => $e->getMessage(),
                    ]);
                    // Continue anyway - signature URL will be null
                }
            }

            // Update client record
            $updateData = [
                'agreement_status' => 'signed',
                'agreement_signed_at' => now(),
                'agreement_signature_url' => $signatureUrl,
                'emergency_contact_name' => $validated['emergency_contact_name'],
                'emergency_contact_relationship' => $validated['emergency_contact_relationship'],
                'emergency_contact_phone' => $validated['emergency_contact_phone'],
                'gp_name' => $validated['gp_name'],
                'gp_practice_name' => $validated['gp_practice_name'],
                'gp_practice_phone' => $validated['gp_practice_phone'],
                'current_address' => $validated['current_address'],
            ];

            // Add case study consent for low-cost clients
            if (isset($validated['case_study_consent'])) {
                $updateData['case_study_consent'] = $validated['case_study_consent'];
            }

            // Update client stage when agreement is signed so client moves to awaiting matching queue
            if ($client->stage !== 'Active Therapy' && $client->stage !== 'Matched with TC') {
                $updateData['stage'] = 'Agreement Signed';
            }

            $client->update($updateData);

            // Log activity
            ActivityLog::create([
                'user_id' => null, // System action
                'action' => 'agreement_signed',
                'model_type' => Client::class,
                'model_id' => $client->id,
                'description' => "Agreement signed via custom form for {$client->name}",
                'changes' => [
                    'agreement_status' => 'signed',
                    'signature_saved' => !empty($signatureUrl),
                    'service_type' => $validated['service_type'] ?? null,
                    'emergency_contact' => [
                        'name' => $validated['emergency_contact_name'],
                        'phone' => $validated['emergency_contact_phone'],
                        'relationship' => $validated['emergency_contact_relationship'],
                    ],
                    'gp_details' => [
                        'name' => $validated['gp_name'],
                        'practice' => $validated['gp_practice_name'],
                        'phone' => $validated['gp_practice_phone'],
                    ],
                ],
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);

            Log::info('Client Agreement: Successfully processed', [
                'client_id' => $client->id,
                'client_uuid' => $client->uuid,
                'email' => $client->email,
                'signature_saved' => !empty($signatureUrl),
            ]);

            $baseUrl = rtrim(config('app.frontend_url'), '/');
            $redirectUrl = $baseUrl . '/client-booking?uuid=' . $client->uuid;

            return response()->json([
                'message' => 'Agreement submitted successfully',
                'client_uuid' => $client->uuid,
                'client_id' => $client->client_id,
                'signed' => true,
                'redirect_url' => $redirectUrl,
            ], 200);
        } catch (\Illuminate\Validation\ValidationException $e) {
            Log::error('Client Agreement Validation Error', [
                'errors' => $e->errors(),
                'data' => $request->all(),
            ]);

            return response()->json([
                'message' => 'Validation failed',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            Log::error('Client Agreement Submission Error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'data' => $request->all(),
            ]);

            return response()->json([
                'message' => 'Error processing agreement submission',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal error',
            ], 500);
        }
    }

    /**
     * Generate and stream a PDF copy of the client's signed agreement.
     */
    public function downloadAgreementPdf($uuid)
    {
        $client = $this->resolveClient($uuid);

        if (!$client) {
            return response()->json(['message' => 'Client not found'], 404);
        }

        if (!$this->isAgreementSigned($client)) {
            return response()->json(['message' => 'Agreement has not been signed yet'], 400);
        }

        $signaturePath = null;
        if ($client->agreement_signature_url) {
            $absolutePath = storage_path('app/public/' . $client->agreement_signature_url);
            if (file_exists($absolutePath)) {
                $signaturePath = $absolutePath;
            }
        }

        $serviceType = $client->service_type === 'Low Cost' ? 'Low Cost' : 'Mid Range';

        $pdf = Pdf::loadView('pdf.client-agreement', [
            'client' => $client,
            'serviceType' => $serviceType,
            'caseStudyConsent' => $client->case_study_consent ? ucfirst($client->case_study_consent) : 'N/A',
            'signaturePath' => $signaturePath,
            'signedDate' => $client->agreement_signed_at
                ? $client->agreement_signed_at->format('d/m/Y')
                : now()->format('d/m/Y'),
        ]);

        $pdf->setPaper('A4', 'portrait');

        $sanitizedName = strtoupper(preg_replace('/[^a-zA-Z0-9]/', '_', $client->name));
        return $pdf->download("VQT_AGREEMENT_{$sanitizedName}.pdf");
    }

    /**
     * Get client data for prefilling the agreement form
     */
    public function getAgreementData($uuid)
    {
        try {
            $client = $this->resolveClient($uuid);

            if (!$client) {
                return response()->json([
                    'message' => 'Client not found',
                ], 404);
            }

            if ($this->isAgreementSigned($client)) {
                return response()->json([
                    'message' => 'You have already signed your service agreement.',
                    'already_signed' => true,
                    'signed_at' => $client->agreement_signed_at,
                    'data' => [
                        'name' => $client->name,
                        'email' => $client->email,
                        'already_signed' => true,
                        'signed_at' => $client->agreement_signed_at,
                    ],
                ], 400);
            }

            // Return only necessary data for prefilling
            return response()->json([
                'success' => true,
                'data' => [
                    'name' => $client->name,
                    'email' => $client->email,
                    'address' => $client->address,
                    'postcode' => $client->postcode,
                    'current_address' => $client->current_address,
                    'emergency_contact_name' => $client->emergency_contact_name,
                    'emergency_contact_phone' => $client->emergency_contact_phone,
                    'emergency_contact_relationship' => $client->emergency_contact_relationship,
                    'gp_name' => $client->gp_name,
                    'gp_practice_name' => $client->gp_practice_name,
                    'gp_practice_phone' => $client->gp_practice_phone,
                    'case_study_consent' => $client->case_study_consent,
                    'service_type' => $client->service_type,
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error fetching client data',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal error',
            ], 500);
        }
    }
}
