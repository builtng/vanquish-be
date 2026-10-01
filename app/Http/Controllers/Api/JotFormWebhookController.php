<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\ActivityLog;
use App\Models\TraineeApplication;
use App\Mail\DynamicEmail;
use App\Jobs\SendTraineeStageTwoInvite;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use App\Services\EmailService;

class JotFormWebhookController extends Controller
{
    protected $emailService;

    public function __construct(EmailService $emailService)
    {
        $this->emailService = $emailService;
    }
    /**
     * Handle agreement submission from JotForm
     * Webhook URL: https://form.jotform.com/231635798225060
     */
    public function handleAgreementSubmission(Request $request)
    {
        try {
            // Log the incoming webhook for debugging
            Log::info('JotForm Agreement Webhook Received', [
                'payload' => $request->all(),
                'headers' => $request->headers->all(),
            ]);

            // JotForm sends data in different formats depending on configuration
            // Common formats: JSON body or form data
            
            $data = $request->all();
            
            // Extract client identifier (could be email, client_id, or UUID)
            $clientIdentifier = null;
            $clientEmail = null;
            
            // Try to find client by email (most common identifier)
            if (isset($data['email']) || isset($data['q3_email']) || isset($data['q4_email'])) {
                $clientEmail = $data['email'] ?? $data['q3_email'] ?? $data['q4_email'];
                $clientEmail = strtolower(trim($clientEmail));
            }
            
            // Try to find client by client_id or UUID if provided
            if (isset($data['client_id']) || isset($data['clientId']) || isset($data['uuid'])) {
                $clientIdentifier = $data['client_id'] ?? $data['clientId'] ?? $data['uuid'];
            }

            // Find client
            $client = null;
            if ($clientEmail) {
                $client = Client::where('email', $clientEmail)->first();
            }
            
            if (!$client && $clientIdentifier) {
                $client = Client::where('uuid', $clientIdentifier)
                    ->orWhere('client_id', $clientIdentifier)
                    ->first();
            }

            if (!$client) {
                Log::warning('JotForm Agreement: Client not found', [
                    'email' => $clientEmail,
                    'identifier' => $clientIdentifier,
                    'data' => $data,
                ]);
                
                return response()->json([
                    'message' => 'Client not found',
                    'received' => true,
                ], 200); // Return 200 to prevent JotForm retries
            }

            // Extract emergency contact information
            $emergencyContactName = $data['emergency_contact_name'] 
                ?? $data['q5_emergencyContactName'] 
                ?? $data['emergencyContactName'] 
                ?? null;
                
            $emergencyContactPhone = $data['emergency_contact_phone'] 
                ?? $data['q6_emergencyContactPhone'] 
                ?? $data['emergencyContactPhone'] 
                ?? null;
                
            $emergencyContactRelationship = $data['emergency_contact_relationship'] 
                ?? $data['q7_emergencyContactRelationship'] 
                ?? $data['emergencyContactRelationship'] 
                ?? null;

            // Check for signature upload - JotForm sends file uploads as URLs
            // Try multiple possible field names for signature
            $signatureUrl = $this->extractSignatureField($data);
            $hasSignature = !empty($signatureUrl);

            // Only mark as signed if signature is present
            if (!$hasSignature) {
                Log::warning('JotForm Agreement: No signature found in submission', [
                    'client_id' => $client->id,
                    'client_email' => $client->email,
                    'submission_id' => $data['submissionID'] ?? $data['submission_id'] ?? null,
                    'available_fields' => array_keys($data),
                ]);
                
                // Log as submitted but not signed
                ActivityLog::create([
                    'user_id' => null,
                    'action' => 'agreement_submitted',
                    'model_type' => Client::class,
                    'model_id' => $client->id,
                    'description' => "Agreement form submitted (without signature) via JotForm for {$client->name}",
                    'changes' => [
                        'note' => 'Form submitted but signature not detected',
                    ],
                    'ip_address' => $request->ip(),
                    'user_agent' => $request->userAgent(),
                ]);

                return response()->json([
                    'message' => 'Agreement form received but signature not detected',
                    'client_uuid' => $client->uuid,
                    'signed' => false,
                    'note' => 'Please ensure signature field is properly configured in JotForm',
                ], 200);
            }

            // Update client record - only if signature is present
            $updateData = [
                'agreement_status' => 'signed',
                'agreement_signed_at' => now(),
                'agreement_jotform_id' => $data['submissionID'] ?? $data['submission_id'] ?? null,
                'agreement_signature_url' => $signatureUrl,
            ];

            if ($emergencyContactName) {
                $updateData['emergency_contact_name'] = $emergencyContactName;
            }
            if ($emergencyContactPhone) {
                $updateData['emergency_contact_phone'] = $emergencyContactPhone;
            }
            if ($emergencyContactRelationship) {
                $updateData['emergency_contact_relationship'] = $emergencyContactRelationship;
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
                'description' => "Agreement signed via JotForm for {$client->name}",
                'changes' => [
                    'agreement_status' => 'signed',
                    'signature_detected' => true,
                    'signature_url' => $signatureUrl,
                    'emergency_contact' => [
                        'name' => $emergencyContactName,
                        'phone' => $emergencyContactPhone,
                        'relationship' => $emergencyContactRelationship,
                    ],
                ],
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);

            Log::info('JotForm Agreement: Successfully processed with signature', [
                'client_id' => $client->id,
                'client_uuid' => $client->uuid,
                'email' => $client->email,
                'signature_url' => $signatureUrl,
            ]);

            return response()->json([
                'message' => 'Agreement processed successfully',
                'client_uuid' => $client->uuid,
                'client_id' => $client->client_id,
                'signed' => true,
                'signature_detected' => true,
            ], 200);

        } catch (\Exception $e) {
            Log::error('JotForm Agreement Webhook Error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'data' => $request->all(),
            ]);

            // Return 200 to prevent JotForm from retrying excessively
            // But log the error for investigation
            return response()->json([
                'message' => 'Error processing agreement',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal error',
            ], 200);
        }
    }

    /**
     * Extract signature field from JotForm webhook data
     * JotForm sends file uploads as URLs in various field name formats
     * 
     * @param array $data Webhook data from JotForm
     * @return string|null Signature URL if found, null otherwise
     */
    private function extractSignatureField(array $data)
    {
        // Common JotForm signature field names (adjust based on your form)
        $possibleFieldNames = [
            'signature',
            'signature_upload',
            'signature_file',
            'client_signature',
            'agreement_signature',
            // JotForm question format (qX_fieldname)
            'q8_signature',
            'q9_signature',
            'q10_signature',
            'q11_signature',
            'q12_signature',
            'q13_signature',
            'q14_signature',
            'q15_signature',
            // Alternative formats
            'q8_signature_upload',
            'q9_signature_upload',
            'signatureImage',
            'signature_image',
        ];

        foreach ($possibleFieldNames as $fieldName) {
            if (isset($data[$fieldName]) && !empty($data[$fieldName])) {
                $value = $data[$fieldName];
                
                // Check if it's a URL (JotForm sends file uploads as URLs)
                if (is_string($value) && (filter_var($value, FILTER_VALIDATE_URL) || strpos($value, 'http') === 0)) {
                    return $value;
                }
                
                // Check if it's a file path
                if (is_string($value) && (strpos($value, '/') !== false || strpos($value, '\\') !== false)) {
                    return $value;
                }
            }
        }

        // Also check for any field containing "signature" in the key name
        foreach ($data as $key => $value) {
            if (stripos($key, 'signature') !== false && !empty($value)) {
                if (is_string($value) && (filter_var($value, FILTER_VALIDATE_URL) || strpos($value, 'http') === 0)) {
                    return $value;
                }
                if (is_string($value) && (strpos($value, '/') !== false || strpos($value, '\\') !== false)) {
                    return $value;
                }
            }
        }

        return null;
    }

    /**
     * Handle intake form submission from JotForm
     * Webhook URL: https://form.jotform.com/231631669909062
     * Important questions: 6, 9, 16, 22, 24, 34
     */
    public function handleIntakeSubmission(Request $request)
    {
        try {
            // Log the incoming webhook for debugging
            Log::info('JotForm Intake Webhook Received', [
                'payload' => $request->all(),
                'headers' => $request->headers->all(),
            ]);

            $data = $request->all();
            
            // Extract client identifier (email or UUID)
            $clientIdentifier = null;
            $clientEmail = null;
            
            // Try to find client by email
            if (isset($data['email']) || isset($data['q3_email']) || isset($data['q4_email'])) {
                $clientEmail = $data['email'] ?? $data['q3_email'] ?? $data['q4_email'];
                $clientEmail = strtolower(trim($clientEmail));
            }
            
            // Try to find client by UUID if provided
            if (isset($data['client_uuid']) || isset($data['clientUuid']) || isset($data['uuid'])) {
                $clientIdentifier = $data['client_uuid'] ?? $data['clientUuid'] ?? $data['uuid'];
            }

            // Find client
            $client = null;
            if ($clientEmail) {
                $client = Client::where('email', $clientEmail)->first();
            }
            
            if (!$client && $clientIdentifier) {
                $client = Client::where('uuid', $clientIdentifier)->first();
            }

            if (!$client) {
                Log::warning('JotForm Intake: Client not found', [
                    'email' => $clientEmail,
                    'identifier' => $clientIdentifier,
                    'data' => $data,
                ]);
                
                return response()->json([
                    'message' => 'Client not found',
                    'received' => true,
                    'redirect_url' => 'https://vanquishtherapiesvqt.trafft.com/',
                ], 200);
            }

            // Extract important questions: 6, 9, 16, 22, 24, 34
            // JotForm uses question numbers like q6, q9, q16, q22, q24, q34
            $importantQuestions = [
                'question_6' => $this->extractFieldValue($data, ['q6', 'question6', 'question_6']),
                'question_9' => $this->extractFieldValue($data, ['q9', 'question9', 'question_9']),
                'question_16' => $this->extractFieldValue($data, ['q16', 'question16', 'question_16']),
                'question_22' => $this->extractFieldValue($data, ['q22', 'question22', 'question_22']),
                'question_24' => $this->extractFieldValue($data, ['q24', 'question24', 'question_24']),
                'question_34' => $this->extractFieldValue($data, ['q34', 'question34', 'question_34']),
            ];

            // Store all form data plus important questions
            $jotformData = [
                'all_data' => $data,
                'important_questions' => $importantQuestions,
                'submission_id' => $data['submissionID'] ?? $data['submission_id'] ?? null,
            ];

            // Update client record
            $client->update([
                'jotform_intake_data' => $jotformData,
                'jotform_intake_submission_id' => $data['submissionID'] ?? $data['submission_id'] ?? null,
                'jotform_intake_completed_at' => now(),
            ]);

            // Log activity
            ActivityLog::create([
                'user_id' => null, // System action
                'action' => 'jotform_intake_submitted',
                'model_type' => Client::class,
                'model_id' => $client->id,
                'description' => "JotForm intake form submitted for {$client->name}",
                'changes' => [
                    'submission_id' => $data['submissionID'] ?? $data['submission_id'] ?? null,
                    'important_questions' => $importantQuestions,
                ],
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);

            Log::info('JotForm Intake: Successfully processed', [
                'client_id' => $client->id,
                'client_uuid' => $client->uuid,
                'email' => $client->email,
                'submission_id' => $data['submissionID'] ?? $data['submission_id'] ?? null,
            ]);

            // Return redirect URL for JotForm to redirect client to booking system
            return response()->json([
                'message' => 'Intake form processed successfully',
                'client_uuid' => $client->uuid,
                'client_id' => $client->client_id,
                'redirect_url' => 'https://vanquishtherapiesvqt.trafft.com/',
            ], 200);

        } catch (\Exception $e) {
            Log::error('JotForm Intake Webhook Error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'data' => $request->all(),
            ]);

            // Return 200 to prevent JotForm from retrying excessively
            return response()->json([
                'message' => 'Error processing intake form',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal error',
                'redirect_url' => 'https://vanquishtherapiesvqt.trafft.com/',
            ], 200);
        }
    }

    /**
     * Handle Stage 1 trainee application submission from JotForm.
     *
     * JotForm ID : 230152076043040
     * Webhook URL: POST /api/jotform/webhook/trainee
     *
     * NOTE: JotForm sends field names as  q{N}_{camelName}  e.g. q3_email,
     *       q4_firstName, q6_tel, q14_address, etc. The exact numbers depend
     *       on form ordering and may shift if the form is edited.  We try
     *       multiple aliases per field so that minor JotForm re-numberings do
     *       not break processing.  After a real submission, check the log key
     *       "jotform_raw_data" to see exactly what names JotForm used, then
     *       tighten the alias lists below.
     */
    public function handleTraineeSubmission(Request $request): \Illuminate\Http\JsonResponse
    {
        try {
            Log::info('JotForm Stage-1 Trainee Webhook Received', [
                'ip'      => $request->ip(),
                'payload' => $request->all(),
            ]);

            $data = $request->all();

            // ── 1. Locate email (required for deduplication) ──────────────────
            $email = $this->extractFieldValue($data, [
                'email', 'q3_email', 'q4_email', 'q5_email',
                'q3_emailAddress', 'q4_emailAddress', 'email_address',
            ]);

            // Brute-force fallback: find ANY valid email in the payload
            if (!$email) {
                foreach ($data as $val) {
                    if (is_string($val) && filter_var(trim($val), FILTER_VALIDATE_EMAIL)) {
                        $email = trim($val);
                        break;
                    }
                }
            }

            if (!$email) {
                Log::warning('JotForm Stage-1: Email not found in submission', ['data' => $data]);
                return response()->json(['message' => 'Email not found in submission'], 200);
            }

            $email = strtolower(trim($email));
            $submissionId = $data['submissionID'] ?? $data['submission_id'] ?? null;

            // ── 2. Handle combined full-name vs split first/last ─────────────
            $firstName = $this->extractFieldValue($data, [
                'first_name', 'firstName',
                'q4_firstName', 'q1_firstName', 'q2_firstName', 'q3_firstName',
                'q4_first_name',
            ]);
            $lastName = $this->extractFieldValue($data, [
                'last_name', 'lastName',
                'q4_lastName', 'q1_lastName', 'q2_lastName', 'q3_lastName',
                'q4_last_name',
            ]);

            // JotForm Name widget sends { "first": "...", "last": "..." }
            $nameWidget = $this->extractFieldValue($data, [
                'q4_fullLegalName', 'q4_name', 'q1_fullName', 'q1_name',
                'fullLegalName', 'fullName', 'name',
            ]);
            if ($nameWidget && is_array($nameWidget)) {
                $firstName = $firstName ?: ($nameWidget['first'] ?? '');
                $lastName  = $lastName  ?: ($nameWidget['last']  ?? '');
            } elseif ($nameWidget && is_string($nameWidget)) {
                $parts     = explode(' ', trim($nameWidget), 2);
                $firstName = $firstName ?: ($parts[0] ?? '');
                $lastName  = $lastName  ?: ($parts[1] ?? '');
            }

            // ── 3. Helper to pull address widget (object or string) ──────────
            $addressField = $this->extractFieldValue($data, [
                'address', 'currentAddress',
                'q14_address', 'q15_address', 'q7_address', 'q8_address',
                'q14_currentRegisteredAddress', 'q9_currentRegisteredAddress',
            ]);
            $addressStr = null;
            if (is_array($addressField)) {
                $addressStr = implode(', ', array_filter([
                    $addressField['addr_line1'] ?? $addressField['address'] ?? '',
                    $addressField['addr_line2'] ?? '',
                    $addressField['city']       ?? '',
                    $addressField['postal']     ?? $addressField['zip'] ?? '',
                ]));
            } elseif (is_string($addressField)) {
                $addressStr = $addressField;
            }

            // Institution address
            $institutionAddressField = $this->extractFieldValue($data, [
                'q20_institutionAddress', 'q19_institutionAddress',
                'q20_nameAddress', 'q19_nameAddress', 'institutionAddress',
            ]);
            $institutionAddressStr = null;
            if (is_array($institutionAddressField)) {
                $institutionAddressStr = implode(', ', array_filter([
                    $institutionAddressField['addr_line1'] ?? $institutionAddressField['address'] ?? '',
                    $institutionAddressField['addr_line2'] ?? '',
                    $institutionAddressField['city']       ?? '',
                    $institutionAddressField['postal']     ?? $institutionAddressField['zip'] ?? '',
                ]));
            } elseif (is_string($institutionAddressField)) {
                $institutionAddressStr = $institutionAddressField;
            }

            // ── 4. Build full mapped payload ─────────────────────────────────
            $mappedData = [
                // Identifiers
                'source'               => 'jotform',
                'status'               => 'New Application',
                'jotform_submission_id' => $submissionId,
                'jotform_submitted_at' => now(),
                'jotform_raw_data'     => $data,

                // ── Personal ─────────────────────────────────────────────────
                'first_name' => $firstName,
                'last_name'  => $lastName,
                'email'      => $email,
                'phone'      => $this->extractFieldValue($data, [
                    'phone', 'tel', 'telephone',
                    'q6_tel', 'q6_phone', 'q7_tel', 'q5_phone',
                    'q6_phoneNumber', 'q7_phone',
                ]),
                'date_of_birth' => $this->extractFieldValue($data, [
                    'date_of_birth', 'dateOfBirth', 'dob',
                    'q5_dateOfBirth', 'q5_dob', 'q6_dob', 'q5_date',
                ]),
                'gender' => $this->extractFieldValue($data, [
                    'gender', 'q7_gender', 'q8_gender', 'q6_gender',
                ]),
                'ethnicity' => $this->extractFieldValue($data, [
                    'ethnicity', 'q9_ethnicity', 'q8_ethnicity', 'q10_ethnicity',
                ]),
                'sexual_orientation' => $this->extractFieldValue($data, [
                    'sexual_orientation', 'sexualOrientation',
                    'q11_sexualOrientation', 'q10_sexualOrientation',
                    'q12_sexualOrientation',
                ]),
                'address' => $addressStr,
                'beliefs' => $this->extractFieldValue($data, [
                    'beliefs', 'q13_beliefs', 'q12_beliefs', 'q14_beliefs',
                    'q13_pleaseSelect', 'pleaseSelectYour',
                ]),
                'beliefs_other' => $this->extractFieldValue($data, [
                    'beliefs_other', 'beliefsOther', 'otherBeliefs',
                    'q14_other', 'q13_other', 'q15_other',
                ]),
                'disabilities' => $this->extractFieldValue($data, [
                    'disabilities', 'disability',
                    'q15_disabilities', 'q14_disabilities', 'q16_disabilities',
                ]),
                'medical_conditions' => $this->extractFieldValue($data, [
                    'medical_conditions', 'medicalConditions',
                    'q16_medicalConditions', 'q15_medicalConditions', 'q17_medicalConditions',
                ]),

                // ── Fitness to Practise ───────────────────────────────────────
                'has_insurance' => $this->extractFieldValue($data, [
                    'has_insurance', 'insurance', 'professionalIndemnityInsurance',
                    'q17_doYouHave', 'q16_doYouHave', 'q18_doYouHave',
                    'q17_insurance', 'q16_insurance',
                ]),
                'professional_body_member' => $this->extractFieldValue($data, [
                    'professional_body', 'professionalBody', 'bodyMember',
                    'q18_areYouA', 'q17_areYouA', 'q19_areYouA',
                    'q18_professionalBody',
                ]),
                'professional_body_details' => $this->extractFieldValue($data, [
                    'professional_body_details', 'professionalBodyDetails',
                    'q18_ifYes73', 'q18_ifYes', 'q19_ifYes',
                    'q18_membershipDetails',
                ]),
                'dbs_update_service' => $this->extractFieldValue($data, [
                    'dbs_update_service', 'dbsUpdateService', 'dbsUpdate',
                    'q19_areYouRegistered', 'q18_areYouRegistered',
                    'q20_dbsUpdate',
                ]),
                'dbs_update_details' => $this->extractFieldValue($data, [
                    'dbs_update_details', 'dbsUpdateDetails',
                    'q19_ifYes', 'q20_ifYes', 'q19_dbsDetails',
                ]),
                'in_personal_therapy' => $this->extractFieldValue($data, [
                    'in_personal_therapy', 'inPersonalTherapy', 'personalTherapy',
                    'q20_areYouCurrently', 'q19_areYouCurrently',
                    'q21_personalTherapy',
                ]),
                'personal_therapy_reason' => $this->extractFieldValue($data, [
                    'personal_therapy_reason', 'personalTherapyReason',
                    'q20_ifYouHave', 'q21_ifYouHave', 'q21_reason',
                ]),
                'has_clinical_supervisor' => $this->extractFieldValue($data, [
                    'has_clinical_supervisor', 'clinicalSupervisor', 'supervisor',
                    'q21_doYouCurrently', 'q22_doYouCurrently', 'q22_supervisor',
                ]),
                'supervisor_reason' => $this->extractFieldValue($data, [
                    'supervisor_reason', 'supervisorReason',
                    'q22_ifYouHave', 'q23_ifYouHave', 'q23_reason',
                ]),
                'previous_online_counselling' => $this->extractFieldValue($data, [
                    'previous_online_counselling', 'previousOnlineCounselling',
                    'q23_haveYouPreviously', 'q24_haveYouPreviously',
                    'q24_onlineCounselling',
                ]),
                'availability_schedule' => $this->extractFieldValue($data, [
                    'availability', 'availabilitySchedule', 'schedule',
                    'q25_pleaseMention', 'q24_pleaseMention',
                    'q25_availability',
                ]),
                'criminal_convictions' => $this->extractFieldValue($data, [
                    'criminal_convictions', 'criminalConvictions', 'criminalRecord',
                    'q26_haveYouBeen', 'q25_haveYouBeen', 'q27_criminal',
                ]),

                // ── Course Information ────────────────────────────────────────
                'institution'   => $this->extractFieldValue($data, [
                    'institution', 'trainingOrganisation', 'college',
                    'q27_nameAddress', 'q28_nameAddress', 'q20_institution',
                    'q27_trainingOrganisationcollege',
                ]),
                'college_address' => $institutionAddressStr,
                'tutor_name'    => $this->extractFieldValue($data, [
                    'tutor_name', 'tutorName', 'tutor',
                    'q28_tutorsName', 'q29_tutorsName', 'q21_tutorName',
                ]),
                'tutor_email'   => $this->extractFieldValue($data, [
                    'tutor_email', 'tutorEmail',
                    'q29_email', 'q30_email', 'q22_tutorEmail',
                ]),
                'tutor_phone'   => $this->extractFieldValue($data, [
                    'tutor_phone', 'tutorPhone', 'tutorTel',
                    'q30_tel', 'q31_tel', 'q23_tutorPhone',
                ]),
                'placement_lead_name'  => $this->extractFieldValue($data, [
                    'placement_lead_name', 'placementLeadName', 'placementLead',
                    'q31_placementLead', 'q32_placementLead', 'q24_placementLead',
                ]),
                'placement_lead_phone' => $this->extractFieldValue($data, [
                    'placement_lead_phone', 'placementLeadPhone',
                    'q32_tel', 'q33_tel', 'q25_placementPhone',
                ]),
                'placement_lead_email' => $this->extractFieldValue($data, [
                    'placement_lead_email', 'placementLeadEmail',
                    'q33_placementLead', 'q34_placementLead', 'q26_placementEmail',
                ]),
                'course_name'  => $this->extractFieldValue($data, [
                    'course_name', 'courseName', 'courseTitle', 'qualification',
                    'q34_courseTitle', 'q35_courseTitle', 'q27_courseName',
                    'q34_courseTitleTitle',
                ]),
                'course_title' => $this->extractFieldValue($data, [
                    'course_title', 'q34_courseTitle', 'q35_courseTitle',
                ]),
                'expected_qualification_date' => $this->extractFieldValue($data, [
                    'expected_qualification_date', 'qualificationDate', 'dateToQualify',
                    'q35_dateExpected', 'q36_dateExpected', 'q28_qualDate',
                ]),
                'counselling_type' => $this->extractFieldValue($data, [
                    'counselling_type', 'counsellingType', 'courseType',
                    'q36_isYour', 'q37_isYour', 'q29_counsellingType',
                    'q36_isYourCounselling',
                ]),
                'face_to_face_requirement' => $this->extractFieldValue($data, [
                    'face_to_face_requirement', 'faceToFaceRequirement',
                    'q37_doesYour', 'q38_doesYour', 'q30_f2fRequirement',
                ]),
                'has_face_to_face_clients' => $this->extractFieldValue($data, [
                    'has_face_to_face_clients', 'faceToFaceClients',
                    'q38_doYouCurrently', 'q39_doYouCurrently', 'q31_f2fClients',
                    'q38_doYouCurrently2',
                ]),
                'face_to_face_client_count' => $this->extractFieldValue($data, [
                    'face_to_face_client_count', 'f2fClientCount', 'clientCount',
                    'q39_ifYes', 'q40_ifYes', 'q32_f2fCount',
                ]),
                'face_to_face_hours_completed' => $this->extractFieldValue($data, [
                    'face_to_face_hours_completed', 'f2fHours', 'hoursCompleted',
                    'q40_howMany', 'q41_howMany', 'q33_f2fHours',
                ]),
                'skills_practice_details' => $this->extractFieldValue($data, [
                    'skills_practice', 'skillsPractice', 'skillsPracticeDetails',
                    'q41_doesYour', 'q42_doesYour', 'q34_skillsPractice',
                    'q41_doesYourCourse',
                ]),

                // ── Experience / Journey ──────────────────────────────────────
                'family_impact' => $this->extractFieldValue($data, [
                    'family_impact', 'familyImpact', 'familyBackground',
                    'q42_ourUpbringing', 'q43_ourUpbringing', 'q35_familyImpact',
                ]),
                'personal_journey' => $this->extractFieldValue($data, [
                    'personal_journey', 'personalJourney', 'journeyToCounselling',
                    'q43_pleaseTell', 'q44_pleaseTell', 'q36_personalJourney',
                ]),
                'self_awareness' => $this->extractFieldValue($data, [
                    'self_awareness', 'selfAwareness', 'selfAwarenessExample',
                    'q44_selfAwareness', 'q45_selfAwareness', 'q37_selfAwareness',
                ]),
                'training_history' => $this->extractFieldValue($data, [
                    'training_history', 'trainingHistory', 'counsellorTraining',
                    'q45_pleaseMention', 'q46_pleaseMention', 'q38_trainingHistory',
                    'q45_pleaseMentionAll',
                ]),
                'areas_of_experience' => $this->extractFieldValue($data, [
                    'areas_of_experience', 'areasOfExperience', 'experienceAreas',
                    'q46_whatAreas', 'q47_whatAreas', 'q39_experienceAreas',
                ]),
                'personal_development_areas' => $this->extractFieldValue($data, [
                    'personal_development_areas', 'personalDevelopment',
                    'q47_whatAreas', 'q48_whatAreas', 'q40_devAreas',
                    'q47_whatAreasOf',
                ]),
                'theoretical_approach' => $this->extractFieldValue($data, [
                    'theoretical_approach', 'theoreticalApproach', 'approach',
                    'q48_howWould', 'q49_howWould', 'q41_approach',
                ]),

                // ── Peer Support Group ────────────────────────────────────────
                'psg_day_preference' => $this->extractFieldValue($data, [
                    'psg_day', 'psgDay', 'peerSupportGroup', 'psgPreference',
                    'q49_selectThe', 'q50_selectThe', 'q42_psgDay',
                    'q49_selectTheDayYou',
                ]),
                'psg_reason' => $this->extractFieldValue($data, [
                    'psg_reason', 'psgReason',
                    'q50_ifYouSelected', 'q51_ifYouSelected', 'q43_psgReason',
                ]),

                // ── Document Uploads (JotForm sends CDN URLs) ─────────────────
                'doc_fitness_to_practise'  => $this->extractDocUrl($data, [
                    'q51_fitnessToPractise', 'q52_fitnessToPractise',
                    'fitnessToPractise', 'fitness_to_practise',
                    'q51_fitnessTo', 'doc_fitness',
                ]),
                'doc_prior_qualifications' => $this->extractDocUrl($data, [
                    'q52_priorCounselling', 'q53_priorCounselling',
                    'priorQualifications', 'prior_qualifications',
                    'q52_priorCounsellingQualifications', 'doc_qualifications',
                ]),
                'doc_dbs_certificate'      => $this->extractDocUrl($data, [
                    'q53_enhancedDbs', 'q54_enhancedDbs',
                    'dbsCertificate', 'dbs_certificate',
                    'q53_enhancedDbsCertificate', 'doc_dbs',
                ]),
                'doc_cv'                   => $this->extractDocUrl($data, [
                    'q54_completeCv', 'q55_completeCv',
                    'cv', 'doc_cv',
                    'q54_completeCV', 'q54_cv',
                ]),
                'doc_valid_id'             => $this->extractDocUrl($data, [
                    'q55_validId', 'q56_validId',
                    'validId', 'valid_id', 'doc_id',
                    'q55_validIDPassport',
                ]),
                'doc_indemnity_insurance'  => $this->extractDocUrl($data, [
                    'q56_copyOf', 'q57_copyOf',
                    'indemnityInsurance', 'indemnity_insurance', 'doc_insurance',
                    'q56_copyOfIndemnity',
                ]),

                // ── Disclaimer date ───────────────────────────────────────────
                'disclaimer_date' => $this->extractFieldValue($data, [
                    'date', 'disclaimerDate', 'q57_date', 'q58_date', 'q44_date',
                ]),
            ];

            // Remove nulls so updateOrCreate won't blank out existing values
            $mappedData = array_filter($mappedData, fn ($v) => $v !== null && $v !== '');

            // Duplicate prevention: Update if exists, Create if not
            $application = TraineeApplication::updateOrCreate(
                ['email' => $email],
                $mappedData
            );

            // Log activity
            ActivityLog::create([
                'user_id'    => null,
                'action'     => 'trainee_application_submitted',
                'model_type' => TraineeApplication::class,
                'model_id'   => $application->id,
                'description' => "Stage 1 application submitted via JotForm (230152076043040) for {$application->first_name} {$application->last_name}",
                'changes'    => array_diff_key($mappedData, ['jotform_raw_data' => true]),
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);

            Log::info('JotForm Stage-1 Trainee: Successfully processed', [
                'application_id'    => $application->id,
                'email'             => $email,
                'submission_id'     => $submissionId,
                'documents_present' => $application->hasDocuments(),
            ]);

            // Send Email #1 Immediately
            $this->emailService->sendAndLog($application->email, 'trainee_application_received', [
                'first_name' => $application->first_name,
                'last_name'  => $application->last_name,
                'email'      => $application->email,
            ], $application);

            // Send Email #2 instantly
            SendTraineeStageTwoInvite::dispatch($application);

            return response()->json([
                'message' => 'Trainee application processed successfully',
                'uuid'    => $application->uuid,
                'status'  => $application->status,
            ], 200);

        } catch (\Exception $e) {
            Log::error('JotForm Stage-1 Trainee Webhook Error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'data'  => $request->all(),
            ]);

            return response()->json([
                'message' => 'Error processing trainee application',
                'error'   => config('app.debug') ? $e->getMessage() : 'Internal error',
            ], 200);
        }
    }

    /**
     * Extract a document/file URL from JotForm webhook data.
     * JotForm sends file upload fields as plain URLs (strings) or URL arrays.
     *
     * @param array $data
     * @param array $possibleNames
     * @return string|null
     */
    private function extractDocUrl(array $data, array $possibleNames): ?string
    {
        foreach ($possibleNames as $fieldName) {
            if (!isset($data[$fieldName]) || empty($data[$fieldName])) {
                continue;
            }

            $value = $data[$fieldName];

            // JotForm sometimes sends an array of URLs for multi-file fields
            if (is_array($value)) {
                $value = reset($value); // take the first file
            }

            if (!is_string($value)) {
                continue;
            }

            $value = trim($value);
            if (filter_var($value, FILTER_VALIDATE_URL) || str_starts_with($value, 'https://')) {
                return $value;
            }
        }

        // Fallback: scan all keys for any matching pattern + URL value
        foreach ($data as $key => $value) {
            foreach ($possibleNames as $name) {
                if (stripos($key, substr($name, strpos($name, '_') + 1)) !== false) {
                    if (is_string($value) && filter_var(trim($value), FILTER_VALIDATE_URL)) {
                        return trim($value);
                    }
                }
            }
        }

        return null;
    }

    /**
     * Extract field value from JotForm webhook data
     * Tries multiple possible field name formats
     * 
     * @param array $data Webhook data from JotForm
     * @param array $possibleNames Array of possible field names to try
     * @return mixed Field value if found, null otherwise
     */
    private function extractFieldValue(array $data, array $possibleNames)
    {
        foreach ($possibleNames as $fieldName) {
            if (isset($data[$fieldName]) && !empty($data[$fieldName])) {
                return $data[$fieldName];
            }
        }

        return null;
    }
}
