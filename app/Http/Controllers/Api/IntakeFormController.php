<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ClientIntakeForm;
use App\Models\TcIntakeForm;
use App\Models\TraineeApplication;
use App\Models\Person;
use App\Models\Client;
use App\Models\TrainingCounsellor;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use App\Mail\DynamicEmail;
use Carbon\Carbon;

use App\Services\EmailService;
use App\Services\ConsultationBookingService;

class IntakeFormController extends Controller
{
    protected $emailService;
    protected ConsultationBookingService $bookingService;

    public function __construct(EmailService $emailService, ConsultationBookingService $bookingService)
    {
        $this->emailService = $emailService;
        $this->bookingService = $bookingService;
    }
    public function clientIntake(Request $request)
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
                'first_name' => 'required|string|max:255',
                'last_name' => 'required|string|max:255',
                'email' => 'required|email|max:255',
                'phone' => 'nullable|string|max:255',
                'age' => 'nullable|integer|min:1|max:2100',
                'voicemail_ok' => 'nullable|boolean',
                'currently_in_therapy' => 'nullable|boolean',
                'gender' => 'nullable|string|max:255',
                'ethnicity' => 'nullable|string|max:255',
                'sexual_orientation' => 'nullable|string|max:255',
                'service_type' => 'nullable|string|max:255',
                'on_medication' => 'nullable|boolean',
                'medication_details' => 'nullable|string',
                'disabilities' => 'nullable|string',
                'support_areas' => 'nullable|array',
                'concerns_details' => 'nullable|string',
                'risk_issues' => 'nullable|string',
                'availability' => 'nullable|array',
                'gender_preference' => 'nullable|string|max:255',
                'age_preference' => 'nullable|string|max:255',
                'ethnicity_preference' => 'nullable|string|max:255',
                'orientation_preference' => 'nullable|string|max:255',
                'hear_about_us' => 'nullable|string|max:255',
                'referral_reason' => 'nullable|string',
                'whatsapp_agreement' => 'nullable|string',
                'partner_email' => 'nullable|email|max:255',
                'partner_phone' => 'nullable|string|max:255',
                'working_with_another_reason' => 'nullable|string',
                'location_of_residence' => 'nullable|string|max:255',
                'referral_type' => 'nullable|string|max:255',
                'referrer_name' => 'nullable|string|max:255',
                'referrer_phone' => 'nullable|string|max:255',
                'referrer_org' => 'nullable|string|max:255',
                'referrer_email' => 'nullable|email|max:255',
                'address' => 'required|string|max:500',
                'emergency_contact_name' => 'required|string|max:255',
                'emergency_contact_phone' => 'required|string|max:255',
                'emergency_contact_relationship' => 'required|string|max:255',
                'core34_answers' => 'nullable|array',
                'terms_accepted' => 'required|boolean|accepted',
                'create_client' => 'nullable|boolean',
                'consultation_fee' => 'nullable|numeric',
                'discount_code' => 'nullable|string',
                'consultation_slot_id' => 'nullable|exists:consultation_slots,id',
                // Mid Range / Coaching & Counselling: couples/family + counsellor delegation
                'is_couples' => 'nullable|boolean',
                'partner_first_name' => 'nullable|string|max:255',
                'partner_last_name' => 'nullable|string|max:255',
                'partner_age' => 'nullable|integer|min:1|max:2100',
                'partner_gender' => 'nullable|string|max:255',
                'partner_ethnicity' => 'nullable|string|max:255',
                'partner_sexual_orientation' => 'nullable|string|max:255',
                'partner_on_medication' => 'nullable|boolean',
                'partner_medication_details' => 'nullable|string',
                'partner_disabilities' => 'nullable|string',
                'partner_has_disability' => 'nullable|boolean',
                'partner_disability_details' => 'nullable|string',
                'preferred_tc_uuid' => 'nullable|string|exists:training_counsellors,uuid',
                'consultation_with_tc_uuid' => 'nullable|string|exists:training_counsellors,uuid',
                'consultation_datetime' => 'nullable|date',
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $e->errors(),
            ], 422);
        }

        try {
            $serviceType = $validated['service_type'] ?? null;
            if (empty($serviceType) && !empty($validated['is_couples'])) {
                $serviceType = 'Mid Range';
            }

            $currentYear = (int) date('Y');
            $calculatedAge = isset($validated['age']) && $validated['age'] !== null ? (int) $validated['age'] : null;
            if ($calculatedAge !== null) {
                if ($calculatedAge >= 1920 && $calculatedAge <= $currentYear) {
                    $calculatedAge = $currentYear - $calculatedAge;
                } elseif ($calculatedAge >= 70 && $calculatedAge <= 99) {
                    $calculatedAge = $currentYear - (1900 + $calculatedAge);
                }
            }

            $calculatedPartnerAge = isset($validated['partner_age']) && $validated['partner_age'] !== null ? (int) $validated['partner_age'] : null;
            if ($calculatedPartnerAge !== null) {
                if ($calculatedPartnerAge >= 1920 && $calculatedPartnerAge <= $currentYear) {
                    $calculatedPartnerAge = $currentYear - $calculatedPartnerAge;
                } elseif ($calculatedPartnerAge >= 70 && $calculatedPartnerAge <= 99) {
                    $calculatedPartnerAge = $currentYear - (1900 + $calculatedPartnerAge);
                }
            }

            $form = ClientIntakeForm::create([
                'first_name' => $validated['first_name'],
                'last_name' => $validated['last_name'],
                'email' => $validated['email'],
                'phone' => $validated['phone'] ?? null,
                'age' => $calculatedAge,
                'voicemail_ok' => $validated['voicemail_ok'] ?? false,
                'currently_in_therapy' => $validated['currently_in_therapy'] ?? false,
                'gender' => $validated['gender'] ?? null,
                'ethnicity' => $validated['ethnicity'] ?? null,
                'sexual_orientation' => $validated['sexual_orientation'] ?? null,
                'service_type' => $serviceType,
                'on_medication' => $validated['on_medication'] ?? false,
                'medication_details' => $validated['medication_details'] ?? null,
                'disabilities' => $validated['disabilities'] ?? null,
                'support_areas' => $validated['support_areas'] ?? [],
                'concerns_details' => $validated['concerns_details'] ?? null,
                'risk_issues' => $validated['risk_issues'] ?? null,
                'availability' => $validated['availability'] ?? [],
                'gender_preference' => $validated['gender_preference'] ?? 'No preference',
                'age_preference' => $validated['age_preference'] ?? 'No preference',
                'ethnicity_preference' => $validated['ethnicity_preference'] ?? 'No preference',
                'orientation_preference' => $validated['orientation_preference'] ?? 'No preference',
                'hear_about_us' => $validated['hear_about_us'] ?? null,
                'referral_reason' => $validated['referral_reason'] ?? null,
                'whatsapp_agreement' => $validated['whatsapp_agreement'] ?? null,
                'partner_email' => $validated['partner_email'] ?? null,
                'partner_phone' => $validated['partner_phone'] ?? null,
                'working_with_another_reason' => $validated['working_with_another_reason'] ?? null,
                'location_of_residence' => $validated['location_of_residence'] ?? null,
                'referral_type' => $validated['referral_type'] ?? null,
                'referrer_name' => $validated['referrer_name'] ?? null,
                'referrer_phone' => $validated['referrer_phone'] ?? null,
                'referrer_org' => $validated['referrer_org'] ?? null,
                'referrer_email' => $validated['referrer_email'] ?? null,
                'address' => $validated['address'] ?? null,
                'emergency_contact_name' => $validated['emergency_contact_name'] ?? null,
                'emergency_contact_phone' => $validated['emergency_contact_phone'] ?? null,
                'emergency_contact_relationship' => $validated['emergency_contact_relationship'] ?? null,
                'core34_answers' => $validated['core34_answers'] ?? [],
                'terms_accepted' => $validated['terms_accepted'],
                'discount_code' => $validated['discount_code'] ?? null,
                'payment_amount' => $validated['consultation_fee'] ?? 0,
                'status' => 'submitted',
                'is_couples' => $validated['is_couples'] ?? false,
                'partner_first_name' => $validated['partner_first_name'] ?? null,
                'partner_last_name' => $validated['partner_last_name'] ?? null,
                'partner_age' => $calculatedPartnerAge,
                'partner_gender' => $validated['partner_gender'] ?? null,
                'partner_ethnicity' => $validated['partner_ethnicity'] ?? null,
                'partner_sexual_orientation' => $validated['partner_sexual_orientation'] ?? null,
                'partner_on_medication' => $validated['partner_on_medication'] ?? null,
                'partner_medication_details' => $validated['partner_medication_details'] ?? null,
                'partner_disabilities' => $validated['partner_disabilities'] ?? null,
            ]);

            $preferredTc = !empty($validated['preferred_tc_uuid'])
                ? TrainingCounsellor::where('uuid', $validated['preferred_tc_uuid'])->first()
                : null;

            $clientId = null;
            $client = null;

            // Create separate client case for intake form submission (defaults to true)
            $shouldCreateClient = $request->boolean('create_client', true);
            if ($shouldCreateClient) {
                $newClientId = Client::generateNextClientId();

                $person = Person::findOrCreateByEmail(
                    strtolower(trim($validated['email'])),
                    "{$validated['first_name']} {$validated['last_name']}",
                    $validated['phone'] ?? null
                );

                $client = Client::create([
                    'person_id' => $person->id,
                    'client_id' => $newClientId,
                    'name' => "{$validated['first_name']} {$validated['last_name']}",
                    'first_name' => $validated['first_name'] ?? null,
                    'last_name' => $validated['last_name'] ?? null,
                    'email' => $validated['email'],
                    'phone' => $validated['phone'] ?? null,
                    'age' => $calculatedAge,
                    'gender' => $validated['gender'] ?? null,
                    'ethnicity' => $validated['ethnicity'] ?? null,
                    'sexual_orientation' => $validated['sexual_orientation'] ?? null,
                    'gender_preference' => $validated['gender_preference'] ?? 'No preference',
                    'age_preference' => $validated['age_preference'] ?? 'No preference',
                    'ethnicity_preference' => $validated['ethnicity_preference'] ?? 'No preference',
                    'orientation_preference' => $validated['orientation_preference'] ?? 'No preference',
                    'on_medication' => isset($validated['on_medication']) ? ($validated['on_medication'] ? 'Yes' : 'No') : null,
                    'medication_details' => $validated['medication_details'] ?? null,
                    'disabilities' => $validated['disabilities'] ?? null,
                    'risk_issues' => $validated['risk_issues'] ?? null,
                    'voicemail_permission' => isset($validated['voicemail_ok']) ? ($validated['voicemail_ok'] ? 'Yes' : 'No') : ($validated['voicemail_permission'] ?? null),
                    'how_heard_about' => $validated['hear_about_us'] ?? $validated['how_heard_about'] ?? null,
                    'service_type' => $serviceType,
                    'primary_issues' => $validated['support_areas'] ?? [],
                    'additional_details' => $validated['concerns_details'] ?? null,
                    'availability' => $validated['availability'] ?? [],
                    'whatsapp_agreement' => $validated['whatsapp_agreement'] ?? null,
                    'partner_email' => $validated['partner_email'] ?? null,
                    'partner_phone' => $validated['partner_phone'] ?? null,
                    'working_with_another_reason' => $validated['working_with_another_reason'] ?? null,
                    'location_of_residence' => $validated['location_of_residence'] ?? null,
                    'referral_type' => $validated['referral_type'] ?? null,
                    'address' => $validated['address'] ?? null,
                    'emergency_contact_name' => $validated['emergency_contact_name'] ?? null,
                    'emergency_contact_phone' => $validated['emergency_contact_phone'] ?? null,
                    'emergency_contact_email' => $validated['emergency_contact_email'] ?? null,
                    'emergency_contact_relationship' => $validated['emergency_contact_relationship'] ?? null,
                    'core34_answers' => $validated['core34_answers'] ?? [],
                    'submitted_date' => now(),
                    'stage' => 'Application & Assessment form Submitted',
                    'is_couples' => $validated['is_couples'] ?? false,
                    'partner_first_name' => $validated['partner_first_name'] ?? null,
                    'partner_last_name' => $validated['partner_last_name'] ?? null,
                    'partner_age' => $calculatedPartnerAge,
                    'partner_gender' => $validated['partner_gender'] ?? null,
                    'partner_ethnicity' => $validated['partner_ethnicity'] ?? null,
                    'partner_sexual_orientation' => $validated['partner_sexual_orientation'] ?? null,
                    'partner_on_medication' => $validated['partner_on_medication'] ?? null,
                    'partner_medication_details' => $validated['partner_medication_details'] ?? null,
                    'partner_disabilities' => $validated['partner_disabilities'] ?? null,
                    'preferred_tc_id' => $preferredTc?->id,
                ]);

                $form->update(['client_id' => $client->id]);
                $clientId = $client->id;
            }

            // Get client UUID if client was created or form has client_id
            $clientUuid = null;
            if ($client) {
                $clientUuid = $client->uuid;
            } elseif ($clientId) {
                $client = Client::find($clientId);
                $clientUuid = $client ? $client->uuid : null;
            } elseif ($form->client_id) {
                $client = Client::find($form->client_id);
                $clientUuid = $client ? $client->uuid : null;
            }

            // Check if this is a free intake (due to coupon) and send the
            // single booking confirmation email
            if (isset($validated['consultation_fee']) && $validated['consultation_fee'] <= 0 && $client) {
                $form->update([
                    'payment_status' => 'paid',
                    'payment_method' => 'coupon',
                    'paid_at' => now(),
                    'status' => 'processed'
                ]);

                $consultation = \App\Models\Consultation::create([
                    'consultation_id' => \App\Models\Consultation::generateNextConsultationId(),
                    'client_id' => $client->id,
                    'scheduled_at' => now(),
                    'payment_status' => 'paid',
                    'payment_amount' => 0,
                    'payment_method' => 'coupon',
                    'paid_at' => now(),
                    'status' => 'scheduled',
                ]);
                $client->update(['stage' => 'Consultation Booked']);

                if (!empty($validated['consultation_with_tc_uuid']) && !empty($validated['consultation_datetime'])) {
                    $consultationTc = TrainingCounsellor::where('uuid', $validated['consultation_with_tc_uuid'])->first();
                    if ($consultationTc) {
                        $this->bookingService->finalizeWithCounsellor(
                            $client,
                            $consultationTc,
                            Carbon::parse($validated['consultation_datetime']),
                            $consultation
                        );
                    }
                } elseif (!empty($validated['consultation_slot_id'])) {
                    $this->bookingService->finalize($client, (int) $validated['consultation_slot_id'], $consultation);
                }
            } elseif ($client) {
                // For paid consultation intakes, pre-create the consultation record with slot attached
                $scheduledAt = now();
                if (!empty($validated['consultation_slot_id'])) {
                    $chosenSlot = \App\Models\ConsultationSlot::find($validated['consultation_slot_id']);
                    if ($chosenSlot) {
                        $scheduledAt = $chosenSlot->consultation_datetime;
                    }
                } elseif (!empty($validated['consultation_datetime'])) {
                    $scheduledAt = Carbon::parse($validated['consultation_datetime']);
                }

                $consultationTc = !empty($validated['consultation_with_tc_uuid'])
                    ? TrainingCounsellor::where('uuid', $validated['consultation_with_tc_uuid'])->first()
                    : null;

                \App\Models\Consultation::create([
                    'consultation_id' => \App\Models\Consultation::generateNextConsultationId(),
                    'client_id' => $client->id,
                    'consultation_slot_id' => $validated['consultation_slot_id'] ?? null,
                    'tc_id' => $consultationTc?->id,
                    'scheduled_at' => $scheduledAt,
                    'payment_status' => 'pending',
                    'payment_amount' => $validated['consultation_fee'] ?? 15,
                    'status' => 'pending',
                ]);
            }

            return response()->json([
                'message' => 'Intake form submitted successfully',
                'form' => $form,
                'client_id' => $clientId ?? $form->client_id,
                'client_uuid' => $clientUuid,
            ], 201);
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Intake Form Error: ' . $e->getMessage());
            return response()->json([
                'message' => 'Failed to create intake form',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function tcIntake(Request $request)
    {
        try {
            // Parse JSON strings for array fields if they come as strings (from FormData)
            $arrayFields = ['topics_with_experience', 'topics_not_ready_for', 'availability'];
            foreach ($arrayFields as $field) {
                if ($request->has($field) && is_string($request->input($field))) {
                    try {
                        $decoded = json_decode($request->input($field), true);
                        if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                            $request->merge([$field => $decoded]);
                        }
                    } catch (\Exception $e) {
                        // If parsing fails, leave as is and let validation handle it
                    }
                }
            }

            // Validate form fields
            $validated = $request->validate([
                'name' => 'required|string|max:255',
                'email' => 'required|email|max:255',
                'phone' => 'nullable|string|max:255',
                'modality' => 'nullable|string|max:255',
                'course' => 'nullable|string|max:255',
                'institution' => 'nullable|string|max:255',
                'topics_with_experience' => 'nullable|array',
                'topics_not_ready_for' => 'nullable|array',
                'availability' => 'nullable|array',
                'additional_info' => 'nullable|string',
                // Personal information
                'date_of_birth' => 'nullable|string|max:50',
                'gender' => 'nullable|string|max:100',
                'ethnicity' => 'nullable|string|max:100',
                'ethnicity_other' => 'nullable|string|max:255',
                'sexual_orientation' => 'nullable|string|max:100',
                'sexual_orientation_other' => 'nullable|string|max:255',
                'address' => 'nullable|string|max:500',
                'beliefs' => 'nullable|string',
                'beliefs_other' => 'nullable|string|max:255',
                'disabilities' => 'nullable|string',
                'medical_conditions' => 'nullable|string',
                // Fitness to Practise
                'has_insurance' => 'nullable|string|max:50',
                'professional_body_member' => 'nullable|string|max:50',
                'professional_body_details' => 'nullable|string',
                'dbs_update_service' => 'nullable|string|max:50',
                'dbs_update_details' => 'nullable|string',
                'in_personal_therapy' => 'nullable|string|max:50',
                'personal_therapy_reason' => 'nullable|string',
                'has_clinical_supervisor' => 'nullable|string|max:50',
                'supervisor_reason' => 'nullable|string',
                'previous_online_counselling' => 'nullable|string|max:50',
                'criminal_convictions' => 'nullable|string',
                'availability_schedule' => 'nullable|string',
                // Training provider details
                'training_org_name' => 'nullable|string|max:255',
                'training_org_address' => 'nullable|string|max:500',
                'college_address' => 'nullable|string|max:500',
                'course_title' => 'nullable|string|max:255',
                'expected_qualification_date' => 'nullable|string|max:100',
                'counselling_type' => 'nullable|string|max:255',
                'face_to_face_requirement' => 'nullable|string',
                'has_face_to_face_clients' => 'nullable|string|max:100',
                'face_to_face_client_count' => 'nullable|string|max:50',
                'face_to_face_hours_completed' => 'nullable|string|max:50',
                'skills_practice_details' => 'nullable|string',
                'tutor_name' => 'nullable|string|max:255',
                'tutor_email' => 'nullable|email|max:255',
                'tutor_phone' => 'nullable|string|max:255',
                'placement_lead_name' => 'nullable|string|max:255',
                'placement_lead_email' => 'nullable|email|max:255',
                'placement_lead_phone' => 'nullable|string|max:255',
                // Experience & Journey
                'family_impact' => 'nullable|string',
                'personal_journey' => 'nullable|string',
                'self_awareness' => 'nullable|string',
                'training_history' => 'nullable|string',
                'areas_of_experience' => 'nullable|string',
                'personal_development_areas' => 'nullable|string',
                'theoretical_approach' => 'nullable|string',
                // PSG
                'psg_day_preference' => 'nullable|string|max:100',
                'psg_reason' => 'nullable|string',
            // Document validations
            'fitnessTopractice' => 'nullable|file|mimes:pdf,doc,docx,jpg,jpeg,png|max:10240',
            'qualifications' => 'nullable|file|mimes:pdf,doc,docx,jpg,jpeg,png|max:10240',
            'dbs' => 'nullable|file|mimes:pdf,doc,docx,jpg,jpeg,png|max:10240',
            'cv' => 'nullable|file|mimes:pdf,doc,docx|max:10240',
            'validId' => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:10240',
            'insurance' => 'nullable|file|mimes:pdf,doc,docx,jpg,jpeg,png|max:10240',
            'create_tc' => 'nullable|boolean',
        ], [
            'validId.file' => 'Please upload a valid ID document',
            'validId.mimes' => 'Please upload a valid ID document (PDF, JPG, or PNG)',
            'validId.max' => 'The ID document must not exceed 10MB',
        ], [
            'validId' => 'Valid ID',
            'fitnessTopractice' => 'Fitness to Practise',
        ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $e->errors(),
            ], 422);
        }

        try {
            // Decode 'beliefs' if it came through as a JSON-encoded array
            if (!empty($validated['beliefs']) && is_string($validated['beliefs'])) {
                $decoded = json_decode($validated['beliefs'], true);
                if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                    $validated['beliefs'] = implode(', ', $decoded);
                }
            }

            // Prepare training provider details as JSON
            $trainingProviderDetails = [];
            if (!empty($validated['training_org_name'])) $trainingProviderDetails['training_org_name'] = $validated['training_org_name'];
            if (!empty($validated['training_org_address'])) $trainingProviderDetails['training_org_address'] = $validated['training_org_address'];
            if (!empty($validated['course_title'])) $trainingProviderDetails['course_title'] = $validated['course_title'];
            if (!empty($validated['tutor_name'])) $trainingProviderDetails['tutor_name'] = $validated['tutor_name'];
            if (!empty($validated['tutor_email'])) $trainingProviderDetails['tutor_email'] = $validated['tutor_email'];
            if (!empty($validated['tutor_phone'])) $trainingProviderDetails['tutor_phone'] = $validated['tutor_phone'];
            if (!empty($validated['placement_lead_name'])) $trainingProviderDetails['placement_lead_name'] = $validated['placement_lead_name'];
            if (!empty($validated['placement_lead_email'])) $trainingProviderDetails['placement_lead_email'] = $validated['placement_lead_email'];
            if (!empty($validated['placement_lead_phone'])) $trainingProviderDetails['placement_lead_phone'] = $validated['placement_lead_phone'];

            // Merge with existing additional_info if it exists
            $existingAdditionalInfo = $validated['additional_info'] ?? null;
            if ($existingAdditionalInfo) {
                try {
                    $existingData = json_decode($existingAdditionalInfo, true);
                    if (is_array($existingData)) {
                        $trainingProviderDetails = array_merge($existingData, $trainingProviderDetails);
                    }
                } catch (\Exception $e) {
                    // If additional_info is not JSON, keep it as is
                }
            }

            // Prepare form data
            $formData = [
                'name' => $validated['name'],
                'email' => $validated['email'],
                'phone' => $validated['phone'] ?? null,
                'modality' => $validated['modality'] ?? null,
                'course' => $validated['course_title'] ?? $validated['course'] ?? null,
                'institution' => $validated['training_org_name'] ?? $validated['institution'] ?? null,
                'topics_with_experience' => $validated['topics_with_experience'] ?? [],
                'topics_not_ready_for' => $validated['topics_not_ready_for'] ?? [],
                'availability' => $validated['availability'] ?? [],
                'additional_info' => !empty($trainingProviderDetails) ? json_encode($trainingProviderDetails) : null,
                'status' => 'submitted',
            ];

            // Handle document uploads - support both formats
            $documentFields = [
                'fitnessTopractice' => 'fitness_to_practice',
                'fitness_to_practice' => 'fitness_to_practice',
                'qualifications' => 'qualifications',
                'dbs' => 'dbs_certificate',
                'dbs_certificate' => 'dbs_certificate',
                'cv' => 'cv',
                'validId' => 'valid_id',
                'valid_id' => 'valid_id',
                'insurance' => 'insurance_certificate',
                'insurance_certificate' => 'insurance_certificate',
            ];

            foreach ($documentFields as $requestField => $dbField) {
                if ($request->hasFile($requestField)) {
                    $file = $request->file($requestField);
                    
                    // Basic validation already done by $request->validate, but we double check
                    $originalName = $file->getClientOriginalName();
                    $sanitizedName = preg_replace('/[^a-zA-Z0-9._-]/', '_', $originalName);
                    $filename = time() . '_' . uniqid() . '_' . $sanitizedName;

                    $path = $file->storeAs("tc_intake_forms/documents/{$dbField}", $filename, 'public');
                    // Store full URL or path depending on app config
                    $formData[$dbField] = config('app.url') . '/storage/' . $path;
                }
            }

            // Create the intake form
            $form = TcIntakeForm::create($formData);

            // Also Create a recruitment-style TraineeApplication so it shows up in the unified dashboard
            try {
                $normalizedEmail = strtolower(trim($validated['email']));
                $person = Person::findOrCreateByEmail(
                    $normalizedEmail,
                    $validated['name'],
                    $validated['phone'] ?? null
                );

                $application = TraineeApplication::create([
                    'person_id'   => $person->id,
                    'first_name'  => explode(' ', $validated['name'], 2)[0] ?? $validated['name'],
                    'last_name'   => explode(' ', $validated['name'], 2)[1] ?? '',
                    'email'       => $normalizedEmail,
                        'phone'       => $validated['phone'] ?? null,
                        'source'      => 'internal_form',
                        'status'      => 'New Application',

                        // Personal information
                        'date_of_birth'       => $validated['date_of_birth'] ?? null,
                        'gender'              => $validated['gender'] ?? null,
                        'ethnicity'           => $validated['ethnicity'] ?? null,
                        'ethnicity_other'     => $validated['ethnicity_other'] ?? null,
                        'sexual_orientation'  => $validated['sexual_orientation'] ?? null,
                        'sexual_orientation_other' => $validated['sexual_orientation_other'] ?? null,
                        'address'             => $validated['address'] ?? null,
                        'beliefs'             => $validated['beliefs'] ?? null,
                        'beliefs_other'       => $validated['beliefs_other'] ?? null,
                        'disabilities'        => $validated['disabilities'] ?? null,
                        'medical_conditions'  => $validated['medical_conditions'] ?? null,

                        // Fitness to Practise
                        'has_insurance'              => $validated['has_insurance'] ?? null,
                        'professional_body_member'   => $validated['professional_body_member'] ?? null,
                        'professional_body_details'  => $validated['professional_body_details'] ?? null,
                        'dbs_update_service'         => $validated['dbs_update_service'] ?? null,
                        'dbs_update_details'         => $validated['dbs_update_details'] ?? null,
                        'in_personal_therapy'        => $validated['in_personal_therapy'] ?? null,
                        'personal_therapy_reason'    => $validated['personal_therapy_reason'] ?? null,
                        'has_clinical_supervisor'    => $validated['has_clinical_supervisor'] ?? null,
                        'supervisor_reason'          => $validated['supervisor_reason'] ?? null,
                        'previous_online_counselling'=> $validated['previous_online_counselling'] ?? null,
                        'criminal_convictions'       => $validated['criminal_convictions'] ?? null,

                        // Course information
                        'institution'                  => $validated['training_org_name'] ?? $validated['institution'] ?? null,
                        'college_address'              => $validated['college_address'] ?? $validated['training_org_address'] ?? null,
                        'course_name'                  => $validated['course_title'] ?? $validated['course'] ?? null,
                        'course_title'                 => $validated['course_title'] ?? null,
                        'expected_qualification_date'  => $validated['expected_qualification_date'] ?? null,
                        'counselling_type'             => $validated['counselling_type'] ?? null,
                        'face_to_face_requirement'     => $validated['face_to_face_requirement'] ?? null,
                        'has_face_to_face_clients'     => $validated['has_face_to_face_clients'] ?? null,
                        'face_to_face_client_count'    => $validated['face_to_face_client_count'] ?? null,
                        'face_to_face_hours_completed' => $validated['face_to_face_hours_completed'] ?? null,
                        'skills_practice_details'      => $validated['skills_practice_details'] ?? null,
                        'tutor_name'                   => $validated['tutor_name'] ?? null,
                        'tutor_email'                  => $validated['tutor_email'] ?? null,
                        'tutor_phone'                  => $validated['tutor_phone'] ?? null,
                        'placement_lead_name'          => $validated['placement_lead_name'] ?? null,
                        'placement_lead_email'         => $validated['placement_lead_email'] ?? null,
                        'placement_lead_phone'         => $validated['placement_lead_phone'] ?? null,

                        // Experience & Journey
                        'experience_background'      => $validated['additional_info'] ?? null,
                        'family_impact'              => $validated['family_impact'] ?? null,
                        'personal_journey'           => $validated['personal_journey'] ?? null,
                        'self_awareness'             => $validated['self_awareness'] ?? null,
                        'training_history'           => $validated['training_history'] ?? null,
                        'areas_of_experience'        => $validated['areas_of_experience'] ?? null,
                        'personal_development_areas' => $validated['personal_development_areas'] ?? null,
                        'theoretical_approach'       => $validated['theoretical_approach'] ?? null,

                        // PSG preference
                        'psg_day_preference' => $validated['psg_day_preference'] ?? null,
                        'psg_reason'         => $validated['psg_reason'] ?? null,

                        // Availability
                        'availability_schedule' => isset($validated['availability'])
                            ? json_encode($validated['availability'])
                            : ($validated['availability_schedule'] ?? null),

                        // Document uploads
                        'doc_fitness_to_practise' => $formData['fitness_to_practice'] ?? null,
                        'doc_prior_qualifications'=> $formData['qualifications'] ?? null,
                        'doc_dbs_certificate'     => $formData['dbs_certificate'] ?? null,
                        'doc_cv'                  => $formData['cv'] ?? null,
                        'doc_valid_id'            => $formData['valid_id'] ?? null,
                        'doc_indemnity_insurance' => $formData['insurance_certificate'] ?? null,
                    ]
                );

                // Send confirmation email for trainee application
                $this->emailService->sendAndLog($application->email, 'trainee_application_received', [
                    'first_name' => $application->first_name,
                    'last_name' => $application->last_name,
                    'email' => $application->email,
                ], $application);
            } catch (\Exception $e) {
                \Illuminate\Support\Facades\Log::error('Failed to create TraineeApplication from TC Intake: ' . $e->getMessage());
            }

            // Optionally create TC from intake form
            if ($request->has('create_tc') && $request->create_tc) {
                // Check if TC with email already exists
                $tc = TrainingCounsellor::withTrashed()->where('email', $validated['email'])->first();

                if ($tc) {
                    if ($tc->trashed()) {
                        $tc->restore();
                    }

                    $tc->update([
                        'name' => $validated['name'],
                        'phone' => $validated['phone'] ?? $tc->phone,
                        'gender' => $validated['gender'] ?? $tc->gender,
                        'ethnicity' => $validated['ethnicity'] ?? $tc->ethnicity,
                        'sexual_orientation' => $validated['sexual_orientation'] ?? $tc->sexual_orientation,
                        'date_of_birth' => $validated['date_of_birth'] ?? $tc->date_of_birth,
                        'address' => $validated['address'] ?? $tc->address,
                        'modality' => $validated['modality'] ?? $tc->modality,
                        'course' => $validated['course'] ?? $validated['course_title'] ?? $tc->course,
                        'institution' => $validated['institution'] ?? $validated['training_org_name'] ?? $tc->institution,
                        'training_org_address' => $validated['training_org_address'] ?? $tc->training_org_address,
                        'tutor_name' => $validated['tutor_name'] ?? $tc->tutor_name,
                        'tutor_email' => $validated['tutor_email'] ?? $tc->tutor_email,
                        'tutor_phone' => $validated['tutor_phone'] ?? $tc->tutor_phone,
                        'placement_lead_name' => $validated['placement_lead_name'] ?? $tc->placement_lead_name,
                        'placement_lead_email' => $validated['placement_lead_email'] ?? $tc->placement_lead_email,
                        'placement_lead_phone' => $validated['placement_lead_phone'] ?? $tc->placement_lead_phone,
                        'topics_with_experience' => $validated['topics_with_experience'] ?? $tc->topics_with_experience ?? [],
                        'topics_not_ready_for' => $validated['topics_not_ready_for'] ?? $tc->topics_not_ready_for ?? [],
                        'availability' => $validated['availability'] ?? $tc->availability ?? [],
                        'last_activity' => now(),
                    ]);

                    // Send welcome email to existing trainee counsellor retrying
                    app(\App\Services\EmailService::class)->sendAndLog(
                        $tc->email,
                        'tc_welcome',
                        [
                            'tc_name' => $tc->name,
                            'tc_id' => $tc->tc_id,
                            'email' => $tc->email,
                            'modality' => $tc->modality ?? 'Not specified'
                        ]
                    );
                } else {
                    // Generate unique TC ID safely
                    $latestTc = TrainingCounsellor::withTrashed()
                        ->where('tc_id', 'LIKE', 'TC%')
                        ->orderBy('id', 'desc')
                        ->first();

                    $nextNum = 1;
                    if ($latestTc && preg_match('/^TC(\d+)$/', $latestTc->tc_id, $matches)) {
                        $nextNum = intval($matches[1]) + 1;
                    } else {
                        $nextNum = TrainingCounsellor::withTrashed()->count() + 1;
                    }

                    $newTcId = 'TC' . str_pad($nextNum, 3, '0', STR_PAD_LEFT);

                    // Ensure uniqueness
                    while (TrainingCounsellor::withTrashed()->where('tc_id', $newTcId)->exists()) {
                        $nextNum++;
                        $newTcId = 'TC' . str_pad($nextNum, 3, '0', STR_PAD_LEFT);
                    }

                    $tc = TrainingCounsellor::create([
                        'tc_id' => $newTcId,
                        'name' => $validated['name'],
                        'email' => $validated['email'],
                        'phone' => $validated['phone'] ?? null,
                        'gender' => $validated['gender'] ?? null,
                        'ethnicity' => $validated['ethnicity'] ?? null,
                        'sexual_orientation' => $validated['sexual_orientation'] ?? null,
                        'date_of_birth' => $validated['date_of_birth'] ?? null,
                        'address' => $validated['address'] ?? null,
                        'modality' => $validated['modality'] ?? null,
                        'course' => $validated['course'] ?? $validated['course_title'] ?? null,
                        'institution' => $validated['institution'] ?? $validated['training_org_name'] ?? null,
                        'training_org_address' => $validated['training_org_address'] ?? null,
                        'tutor_name' => $validated['tutor_name'] ?? null,
                        'tutor_email' => $validated['tutor_email'] ?? null,
                        'tutor_phone' => $validated['tutor_phone'] ?? null,
                        'placement_lead_name' => $validated['placement_lead_name'] ?? null,
                        'placement_lead_email' => $validated['placement_lead_email'] ?? null,
                        'placement_lead_phone' => $validated['placement_lead_phone'] ?? null,
                        'topics_with_experience' => $validated['topics_with_experience'] ?? [],
                        'topics_not_ready_for' => $validated['topics_not_ready_for'] ?? [],
                        'availability' => $validated['availability'] ?? [],
                        'joined_date' => now(),
                        'last_activity' => now(),
                        'status' => 'Active',
                    ]);

                    // Send welcome email to new trainee counsellor
                    app(\App\Services\EmailService::class)->sendAndLog(
                        $tc->email,
                        'tc_welcome',
                        [
                            'tc_name' => $tc->name,
                            'tc_id' => $tc->tc_id,
                            'email' => $tc->email,
                            'modality' => $tc->modality ?? 'Not specified'
                        ]
                    );
                }

                $form->update(['tc_id' => $tc->id]);
            }

            return response()->json([
                'message' => 'Intake form submitted successfully',
                'form' => $form,
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to create intake form',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
