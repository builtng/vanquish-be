<?php

namespace App\Mail;

use App\Models\EmailTemplate;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Blade;

class DynamicEmail extends Mailable
{
    use Queueable, SerializesModels;

    public $template;
    public $data;

    /**
     * Create a new message instance.
     * 
     * @param string $type The template type
     * @param array $data Data for placeholders
     */
    public function __construct($type, $data = [])
    {
        $this->template = EmailTemplate::where('type', $type)->first();

        // Fallback to default if not in DB
        if (!$this->template) {
            $defaults = $this->getDefaults();
            if (isset($defaults[$type])) {
                $this->template = (object) array_merge(['type' => $type], $defaults[$type]);
            } else {
                // Last resort fallback
                $this->template = (object) [
                    'subject' => 'Notification from Vanquish Therapies',
                    'body' => '<p>This is an automated notification.</p>',
                    'placeholders' => []
                ];
            }
        }

        // Ensure login_url fallback is provided for message notifications
        $baseUrl = rtrim(config('app.frontend_url', 'http://localhost:3000'), '/');
        if (!isset($data['login_url'])) {
            if ($type === 'message_waiting_counsellor') {
                $data['login_url'] = $data['portal_url'] ?? ($baseUrl . '/counsellor-login');
            } elseif ($type === 'message_waiting_admin') {
                $data['login_url'] = $data['dashboard_url'] ?? ($baseUrl . '/login');
            }
        }

        $this->data = $data;
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        $subject = $this->renderString($this->template->subject);

        // Determine category based on template type
        $type = $this->template->type ?? collect(array_keys($this->getDefaults()))->first(fn($key) => $this->getDefaults()[$key]['subject'] == $this->template->subject);
        $category = $this->determineCategory($type);

        $sender = app(\App\Services\DynamicMailSenderService::class)->resolve($category);

        return new Envelope(
            from: new \Illuminate\Mail\Mailables\Address($sender['email'], $sender['name'] ?? config('mail.from.name')),
            replyTo: [new \Illuminate\Mail\Mailables\Address('help@vanquishtherapies.co.uk', 'Vanquish Therapies Support')],
            subject: $subject,
        );
    }

    /**
     * Determine sender category based on email type
     */
    private function determineCategory($type): string
    {
        if (!$type) return 'general';

        $counsellorTypes = [
            'booking_notification',
            'tc_welcome',
            'tc_match_notification',
            'induction_invitation',
            'qualified_form',
            'qualified_counsellor_submission',
            'generic_tc_email',
            'trainee_initial_invite',
            'trainee_application_received',
            'trainee_stage_two_invite',
            'trainee_video_interview_received',
            'trainee_stage_three_invite',
            'trainee_interview_reminder',
            'trainee_placement_acceptance',
            'trainee_placement_rejection',
            'trainee_induction_completed',
            'trainee_interview_not_attended',
            'trainee_induction_not_attended',
            'trainee_portal_invite',
            'counsellor_portal_invite',
            'tc_late_to_session',
            'tc_missed_psg',
            'tc_missed_session',
            'tc_late_session_notes',
            'tc_session_disruption',
            'message_waiting_counsellor',
        ];

        $generalTypes = [
            'message_waiting_admin',
            'password_reset',
        ];

        $consultationTypes = [
            'consultation_booking_confirmation',
        ];

        if (in_array($type, $counsellorTypes)) {
            return 'counsellor';
        }

        if (in_array($type, $generalTypes)) {
            return 'general';
        }

        if (in_array($type, $consultationTypes)) {
            return 'consultation';
        }

        // Default to client since most emails are for clients
        return 'client';
    }

    /**
     * Email types that include their own styled header in the body HTML.
     * These should not show the outer layout header to avoid duplicates.
     */
    private array $selfHeaderedTypes = [
        'admin_video_review_notification',
        'trainee_interview_confirmed',
        'trainee_interview_reminder',
        'trainee_portal_invite',
        'counsellor_portal_invite',
        'trainee_onboarding_paperwork',
        'message_waiting_counsellor',
        'message_waiting_admin',
    ];

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        $type = $this->template->type ?? null;
        $hasOwnHeader = $type && in_array($type, $this->selfHeaderedTypes);

        // We use a base email layout and inject the rendered body
        return new Content(
            view: 'emails.dynamic-layout',
            with: [
                'body'         => $this->renderString($this->template->body),
                'hasOwnHeader' => $hasOwnHeader,
            ],
        );
    }


    /**
     * Get the attachments for the message.
     *
     * @return array<int, \Illuminate\Mail\Mailables\Attachment>
     */
    public function attachments(): array
    {
        $attachments = [];

        $type = $this->template->type ?? null;
        if ($type === 'trainee_placement_acceptance') {
            $directory = storage_path('app/templates');
            if (!is_dir($directory)) {
                mkdir($directory, 0755, true);
            }
            $filePath = $directory . '/4-way-agreement-trainee.docx';
            if (!file_exists($filePath)) {
                file_put_contents($filePath, 'Placeholder for 4-way-agreement-trainee.docx. Please replace this file with the actual document.');
            }

            $attachments[] = \Illuminate\Mail\Mailables\Attachment::fromPath($filePath)
                ->as('4-way-agreement-trainee.docx')
                ->withMime('application/vnd.openxmlformats-officedocument.wordprocessingml.document');
        }

        if ($type === 'trainee_induction_completed') {
            $directory = storage_path('app/templates');
            if (!is_dir($directory)) {
                mkdir($directory, 0755, true);
            }

            $files = [
                'welcome-guide.pdf' => 'Placeholder for welcome-guide.pdf. Please replace this file with the actual Welcome Guide & Practitioner Handbook.',
                'induction-disclosure-form.docx' => 'Placeholder for induction-disclosure-form.docx. Please replace this file with the actual Induction Disclosure Form.',
            ];

            foreach ($files as $filename => $placeholderText) {
                $filePath = $directory . '/' . $filename;
                if (!file_exists($filePath)) {
                    file_put_contents($filePath, $placeholderText);
                }

                $attachments[] = \Illuminate\Mail\Mailables\Attachment::fromPath($filePath)->as($filename);
            }
        }

        return $attachments;
    }

    /**
     * Render a string with placeholders.
     */
    private function renderString($string)
    {
        foreach ($this->data as $key => $value) {
            if (is_scalar($value)) {
                $string = str_replace('{{' . $key . '}}', (string)$value, $string);
                $string = str_replace('{' . $key . '}', (string)$value, $string);
            }
        }

        // Also support Blade-style if needed, but str_replace is safer for user-input templates
        return $string;
    }

    private function getDefaults()
    {
        return [
            'password_reset' => [
                'subject' => 'Password Reset Request - Vanquish Therapies',
                'body' => '<h1 style="margin: 0 0 20px 0; color: #1e293b; font-size: 24px; font-weight: 700; line-height: 1.3;">Password Reset Request</h1><p style="font-size: 16px; margin: 0 0 16px 0; color: #333333;">Hello <strong>{{name}}</strong>,</p><p style="margin: 0 0 16px 0; color: #4b5563; font-size: 15px; line-height: 1.6;">We received a request to reset the password for your Vanquish Therapies account.</p><p style="margin: 24px 0;"><a href="{{reset_link}}" style="display:inline-block;padding:12px 24px;background-color:#6f1d56;color:white;text-decoration:none;border-radius:8px;font-weight:bold;">Reset My Password</a></p><p style="margin: 0 0 16px 0; color: #64748b; font-size: 14px; line-height: 1.5;">This password reset link will expire in {{expires_in}}. If you did not request a password reset, no further action is required and your account remains secure.</p><p style="font-size: 15px; font-weight: bold; color: #6f1d56; margin-top: 24px;">Warm regards,<br>The Vanquish Therapies Team</p>',
                'placeholders' => ['name', 'reset_link', 'expires_in']
            ],
            'intake_submission' => [
                'subject' => 'We have received your intake form',
                'body' => '<h1>Hello {{client_name}},</h1><p>Thank you for submitting your intake form. We will review it and get back to you soon.</p><p>Warm regards,<br>The Vanquish Therapies Team</p>',
                'placeholders' => ['client_name', 'email']
            ],
            'payment_confirmation' => [
                'subject' => 'Payment Confirmation - Vanquish Therapies',
                'body' => '<h1>Payment Received</h1><p>Hi {{client_name}},</p><p>We have successfully received your payment. Thank you for choosing Vanquish Therapies.</p><p>Warm regards,<br>The Vanquish Therapies Team</p>',
                'placeholders' => ['client_name', 'email']
            ],

            'match_assigned' => [
                'subject' => 'You have been matched with a practitioner',
                'body' => '<h1 style="margin: 0 0 20px 0; color: #1e293b; font-size: 24px; font-weight: 700; line-height: 1.3;">Practitioner Match</h1><p style="font-size: 16px; margin: 0 0 16px 0; color: #333333;">Hi <strong>{{client_name}}</strong>,</p><p style="margin: 0 0 16px 0; color: #4b5563; font-size: 15px; line-height: 1.6;">Great news! You have been matched with <strong>{{tc_name}}</strong>.</p><p style="margin: 0 0 24px 0; color: #4b5563; font-size: 15px; line-height: 1.6;">The next step is to sign your service agreement. Please use the link below to review and sign your agreement. Once signed, you will be able to select your session slots and book your sessions directly.</p><p style="margin: 24px 0;"><a href="{{agreement_url}}" style="display:inline-block;padding:12px 24px;background-color:#6f1d56;color:white;text-decoration:none;border-radius:8px;font-weight:bold;">Sign Service Agreement</a></p><p style="font-size: 15px; font-weight: bold; color: #6f1d56; margin-top: 24px;">Warm regards,<br>The Vanquish Therapies Team</p>',
                'placeholders' => ['client_name', 'tc_name', 'email', 'agreement_url']
            ],

            'client_matched' => [
                'subject' => 'You have been matched with a practitioner',
                'body' => '<h1 style="margin: 0 0 20px 0; color: #1e293b; font-size: 24px; font-weight: 700; line-height: 1.3;">Practitioner Match</h1><p style="font-size: 16px; margin: 0 0 16px 0; color: #333333;">Hi <strong>{{client_name}}</strong>,</p><p style="margin: 0 0 16px 0; color: #4b5563; font-size: 15px; line-height: 1.6;">Great news! You have been matched with <strong>{{tc_name}}</strong>.</p><p style="margin: 0 0 24px 0; color: #4b5563; font-size: 15px; line-height: 1.6;">Your service agreement is already signed. You can now select your session slots and book your therapy sessions directly.</p><p style="margin: 24px 0;"><a href="{{booking_link}}" style="display:inline-block;padding:12px 24px;background-color:#6f1d56;color:white;text-decoration:none;border-radius:8px;font-weight:bold;">Book Therapy Sessions</a></p><p style="font-size: 15px; font-weight: bold; color: #6f1d56; margin-top: 24px;">Warm regards,<br>The Vanquish Therapies Team</p>',
                'placeholders' => ['client_name', 'tc_name', 'email', 'booking_link']
            ],


            'agreement_sent' => [
                'subject' => 'Action Required: Service Agreement',
                'body' => '<h1>Service Agreement</h1><p>Hi {{client_name}},</p><p>Please review and sign the service agreement sent to your email.</p><p><a href="{{agreement_url}}" style="display:inline-block;padding:10px 20px;background-color:#6f1d56;color:white;text-decoration:none;border-radius:5px;">Sign Agreement</a></p><p>Warm regards,<br>The Vanquish Therapies Team</p>',
                'placeholders' => ['client_name', 'email', 'agreement_url']
            ],

            'counsellor_selection_ready' => [
                'subject' => 'Choose your practitioner - Vanquish Therapies',
                'body' => '<h1>Choose Your Practitioner</h1><p>Hi {{client_name}},</p><p>You are ready to choose the practitioner who will be working with you. Please use the link below to browse our available practitioners and make your selection.</p><p><a href="{{selection_url}}" style="display:inline-block;padding:10px 20px;background-color:#6f1d56;color:white;text-decoration:none;border-radius:5px;">Choose Your Practitioner</a></p><p>Warm regards,<br>The Vanquish Therapies Team</p>',
                'placeholders' => ['client_name', 'email', 'selection_url']
            ],
            'booking_confirmation' => [
                'subject' => 'Booking Confirmation - Vanquish Therapies',
                'body' => '<h1>Your Booking is Confirmed</h1><p>Hi {{client_name}},</p><p>Your {{booking_type}} with {{counsellor_name}} has been scheduled.</p><p><strong>Date:</strong> {{date}}</p><p><strong>Time:</strong> {{time}}</p><p><strong>Details:</strong> {{booking_details}}</p><p><strong>Location:</strong> {{location}}</p><p><strong>Duration:</strong> {{duration}} minutes</p><p><strong>Link:</strong> <a href="{{consultation_link}}">Join Session</a></p><p>Warm regards,<br>The Vanquish Therapies Team</p>',
                'placeholders' => ['client_name', 'booking_type', 'counsellor_name', 'booking_details', 'location', 'duration', 'consultation_link', 'date', 'time']
            ],
            'booking_notification' => [
                'subject' => 'New Booking Notification',
                'body' => '<h1>New Booking</h1><p>Hi {{tc_name}},</p><p>A new {{booking_type}} has been scheduled with you.</p><p><strong>Client:</strong> {{client_name}}</p><p><strong>Date/Time:</strong> {{scheduled_at}}</p><p><strong>Notes:</strong> {{notes}}</p><p>Warm regards,<br>The Vanquish Therapies Team</p>',
                'placeholders' => ['tc_name', 'client_name', 'booking_type', 'scheduled_at', 'notes']
            ],
            'consultation_follow_up' => [
                'subject' => 'Consultation Update - Vanquish Therapies',
                'body' => '<h1>Consultation Completed</h1><p>Hi {{client_name}},</p><p>Thank you for attending your consultation. Your outcome is: <strong>{{outcome}}</strong>.</p><p>{{next_steps}}</p><p>Warm regards,<br>The Vanquish Therapies Team</p>',
                'placeholders' => ['client_name', 'outcome', 'next_steps']
            ],
            'consultation_booking_link' => [
                'subject' => 'Book sessions - Vanquish Therapies',
                'body' => '<h1>Book sessions</h1><p>Hi {{client_name}},</p><p>Please use the button below to select a preferred date and time for your sessions.</p><p><a href="{{booking_link}}" style="display:inline-block;padding:10px 20px;background-color:#6f1d56;color:white;text-decoration:none;border-radius:5px;">Book sessions</a></p><p>Warm regards,<br>The Vanquish Therapies Team</p>',
                'placeholders' => ['client_name', 'booking_link', 'tc_name', 'session_date']
            ],
            'consultation_booking_confirmation' => [
                'subject' => 'Consultation Booking Confirmation - Vanquish Therapies',
                'body' => '<p>Dear {{client_name}}</p><p>Thank you for completing our consultation forms and scheduling your consultation for <strong>{{schedule_datetime}}</strong> - {{timezone}}</p><p><strong>The allocated duration of your consultation slot is for {{duration}}-minutes.</strong></p><p><strong><em>For international clients - All consultation and session times are in UK time.</em></strong></p><p>Please find the zoom link below:</p><p><strong>Consultation</strong></p><p><a href="{{zoom_link}}">{{zoom_link}}</a></p><p><strong>Meeting ID:</strong> {{meeting_id}}</p><p><strong>Passcode:</strong> {{passcode}}</p><p>Please do not hesitate to contact us on <a href="mailto:help@vanquishtherapies.co.uk">help@vanquishtherapies.co.uk</a> if you have any questions, or if you require any assistance with Zoom, we are happy to assist.</p><p><strong>Please note:</strong> All consultation slots are limited. If you do not attend your scheduled consultation or join after your allocated time has ended, a new payment will be required to secure another consultation.</p><p><strong>All consultation/admin fees are non-refundable</strong>, as they cover the processing and booking of your consultation regardless of attendance. We appreciate your understanding</p><p>Thank you.</p><p>Wishing you a kind and gentle rest of the day.</p><p>Warmest regards,</p><p><strong>Vanquish Therapies Team</strong></p>',
                'placeholders' => ['client_name', 'schedule_datetime', 'timezone', 'duration', 'zoom_link', 'meeting_id', 'passcode']
            ],
            'feedback_form' => [
                'subject' => 'How are we doing? - Vanquish Therapies',
                'body' => '<h1>We value your feedback</h1><p>Hi {{client_name}},</p><p>We hope you are finding your sessions helpful. Could you please take a moment to provide us with some feedback?</p><p><a href="{{feedback_url}}" style="display:inline-block;padding:10px 20px;background-color:#6f1d56;color:white;text-decoration:none;border-radius:5px;">Give Feedback</a></p><p>Warm regards,<br>The Vanquish Therapies Team</p>',
                'placeholders' => ['client_name', 'feedback_url']
            ],
            'tc_welcome' => [
                'subject' => 'Welcome to Vanquish Therapies',
                'body' => '<h1>Welcome, {{tc_name}}!</h1><p>We are excited to have you join our team. Your Practitioner ID is {{tc_id}}.</p><p><strong>Modality:</strong> {{modality}}</p><p>We will be in touch soon with next steps.</p><p>Warm regards,<br>The Vanquish Therapies Team</p>',
                'placeholders' => ['tc_name', 'tc_id', 'email', 'modality']
            ],
            'tc_match_notification' => [
                'subject' => 'You have a message in your portal to attend to',
                'body' => '<p style="margin: 0 0 16px 0; font-size: 16px; line-height: 1.6; color: #333333;">Hi {{first_name}}.</p><p style="margin: 0 0 24px 0; font-size: 16px; line-height: 1.6; color: #333333;">You have a message in your portal to attend to, kindly.</p><div style="text-align: center; margin: 32px 0;"><a href="{{login_url}}" style="display: inline-block; background-color: #6f1d56; color: #ffffff; padding: 14px 32px; text-decoration: none; border-radius: 8px; font-weight: 600; font-size: 16px; box-shadow: 0 2px 4px rgba(111, 29, 86, 0.2);">Log in to Portal</a></div>',
                'placeholders' => ['first_name', 'login_url', 'Login Button']
            ],

            'induction_invitation' => [
                'subject' => 'Induction Session Invitation',
                'body' => '<h1>Induction Invitation</h1><p>Hi {{tc_name}},</p><p>You have been invited to an induction session on {{induction_date}}.</p><p><strong>Location:</strong> {{location}}</p><p><strong>Notes:</strong> {{notes}}</p><p><a href="{{acceptance_url}}" style="padding:10px 20px;background-color:green;color:white;text-decoration:none;border-radius:5px;">Accept Invitation</a> <a href="{{decline_url}}" style="padding:10px 20px;background-color:red;color:white;text-decoration:none;border-radius:5px;">Decline</a></p><p>Warm regards,<br>The Vanquish Therapies Team</p>',
                'placeholders' => ['tc_name', 'induction_date', 'start_time', 'end_time', 'location', 'notes', 'acceptance_url', 'decline_url']
            ],
            'qualified_form' => [
                'subject' => 'Action Required: Qualified Practitioner Form',
                'body' => '<h1>Transition to Qualified Practitioner</h1><p>Hi {{tc_name}},</p><p>Congratulations on becoming a qualified practitioner! Please complete the mandatory form to update your status.</p><p><a href="{{form_url}}" style="display:inline-block;padding:10px 20px;background-color:#6f1d56;color:white;text-decoration:none;border-radius:5px;">Complete Form</a></p><p>Warm regards,<br>The Vanquish Therapies Team</p>',
                'placeholders' => ['tc_name', 'form_url']
            ],
            'qualified_counsellor_submission' => [
                'subject' => 'Confirmation of Submission – Qualified Counsellor Registration',
                'body' => '<div style="font-family: Arial, sans-serif; max-width: 640px; margin: 0 auto; color: #333333; line-height: 1.6;">
  <div style="background: linear-gradient(135deg, #6f1d56 0%, #9b2c7e 100%); padding: 36px 32px; border-radius: 12px 12px 0 0; text-align: center;">
    <h1 style="color: #ffffff; margin: 0; font-size: 24px; font-weight: 700; letter-spacing: -0.5px;">Submission Confirmed</h1>
    <p style="color: rgba(255, 255, 255, 0.9); margin: 8px 0 0; font-size: 14px;">Vanquish Therapies — Qualified Practitioner Registration</p>
  </div>
  <div style="background: #ffffff; padding: 32px; border: 1px solid #e8e8e8; border-top: none;">
    <p style="font-size: 16px; margin-top: 0; color: #1e293b;">Dear <strong>{{first_name}}</strong>,</p>
    <p style="color: #4b5563; font-size: 15px; margin-bottom: 20px;">
      Thank you for completing and submitting your <strong>Qualified Counsellor Registration &amp; Compliance Details</strong> to <strong>Vanquish Therapies</strong>. We are pleased to confirm that we have successfully received your form, professional credentials, and uploaded documentation.
    </p>
    <div style="background: #f9f4f8; border: 1px solid #e8d5e4; border-radius: 10px; padding: 20px 24px; margin: 24px 0;">
      <h2 style="color: #6f1d56; margin: 0 0 14px; font-size: 16px; font-weight: 700;">Submission Summary</h2>
      <table width="100%" cellpadding="0" cellspacing="0" style="border-collapse: collapse; font-size: 14px;">
        <tr>
          <td style="padding: 8px 0; color: #64748b; font-weight: 600; width: 38%; border-bottom: 1px solid #f0e6ed;">Practitioner Name:</td>
          <td style="padding: 8px 0; color: #1e293b; font-weight: bold; border-bottom: 1px solid #f0e6ed;">{{counsellor_name}}</td>
        </tr>
        <tr>
          <td style="padding: 8px 0; color: #64748b; font-weight: 600; border-bottom: 1px solid #f0e6ed;">Reference ID:</td>
          <td style="padding: 8px 0; color: #6f1d56; font-family: monospace; font-weight: bold; font-size: 15px; border-bottom: 1px solid #f0e6ed;">{{tc_id}}</td>
        </tr>
        <tr>
          <td style="padding: 8px 0; color: #64748b; font-weight: 600; border-bottom: 1px solid #f0e6ed;">Submission Date:</td>
          <td style="padding: 8px 0; color: #1e293b; border-bottom: 1px solid #f0e6ed;">{{submission_date}}</td>
        </tr>
        <tr>
          <td style="padding: 8px 0; color: #64748b; font-weight: 600;">Status:</td>
          <td style="padding: 8px 0;">
            <span style="display: inline-block; background-color: #ecfdf5; color: #047857; font-weight: 600; font-size: 12px; padding: 3px 8px; border-radius: 4px; border: 1px solid #a7f3d0;">
              ✓ Received &amp; Under Review
            </span>
          </td>
        </tr>
      </table>
    </div>
    <h2 style="color: #6f1d56; font-size: 17px; border-bottom: 2px solid #f0e6ed; padding-bottom: 8px; margin: 28px 0 16px 0;">What Happens Next</h2>
    <div style="margin: 16px 0;">
      <div style="padding: 12px 16px; background: #fafafa; border-left: 3px solid #6f1d56; border-radius: 0 6px 6px 0; margin-bottom: 10px;">
        <p style="margin: 0; font-size: 14px; color: #1e293b;"><strong>1. Compliance &amp; Document Verification</strong></p>
        <p style="margin: 4px 0 0; font-size: 13px; color: #64748b;">Our SAR &amp; Compliance Team will verify your submitted certificates, professional body membership (e.g., BACP, NCPS), indemnity insurance, and DBS check.</p>
      </div>
      <div style="padding: 12px 16px; background: #fafafa; border-left: 3px solid #6f1d56; border-radius: 0 6px 6px 0; margin-bottom: 10px;">
        <p style="margin: 0; font-size: 14px; color: #1e293b;"><strong>2. Practitioner Profile &amp; Availability</strong></p>
        <p style="margin: 4px 0 0; font-size: 13px; color: #64748b;">Your working modalities, specialized client groups, and schedule availability will be configured in our clinical matching directory.</p>
      </div>
      <div style="padding: 12px 16px; background: #fafafa; border-left: 3px solid #6f1d56; border-radius: 0 6px 6px 0;">
        <p style="margin: 0; font-size: 14px; color: #1e293b;"><strong>3. Portal Access &amp; Final Confirmation</strong></p>
        <p style="margin: 4px 0 0; font-size: 13px; color: #64748b;">Upon successful review, you will receive an invitation with full credentials and onboarding access to the Counsellor Portal.</p>
      </div>
    </div>
    <div style="background: #fdf2f8; border: 1px solid #fce7f3; border-radius: 8px; padding: 14px 18px; margin: 24px 0;">
      <p style="margin: 0; font-size: 13px; color: #9d174d;">
        <strong>Questions or updates?</strong> If you need to supply updated documents or have questions regarding your application, please reach out to our team at <a href="mailto:sar.compliance@vanquishtherapies.co.uk" style="color: #6f1d56; font-weight: bold; text-decoration: underline;">sar.compliance@vanquishtherapies.co.uk</a>.
      </p>
    </div>
    <p style="font-size: 15px; margin-top: 24px; color: #333333;">
      Thank you for your commitment to professional excellence with Vanquish Therapies.
    </p>
    <p style="font-size: 14px; margin-top: 24px; color: #333333;">
      Kind regards,<br>
      <strong style="color: #6f1d56;">SAR and Compliance Team</strong><br>
      <span style="color: #64748b; font-size: 13px;">Vanquish Therapies │ Integrative Life Coaching &amp; Counselling</span><br>
      <span style="color: #64748b; font-size: 12px;">E: <a href="mailto:sar.compliance@vanquishtherapies.co.uk" style="color: #6f1d56;">sar.compliance@vanquishtherapies.co.uk</a> │ W: <a href="http://www.vanquishtherapies.co.uk/" style="color: #6f1d56;">www.vanquishtherapies.co.uk</a></span>
    </p>
  </div>
  <div style="background: #f5f5f5; padding: 16px 32px; border-radius: 0 0 12px 12px; border: 1px solid #e8e8e8; border-top: none; text-align: center;">
    <p style="font-size: 11px; color: #94a3b8; margin: 0;">© Vanquish Therapies Ltd. All rights reserved.</p>
  </div>
</div>',
                'placeholders' => ['first_name', 'counsellor_name', 'email', 'tc_id', 'submission_date']
            ],
            'session_reminder' => [
                'subject' => 'Upcoming Session Reminder',
                'body' => '<h1>Session Reminder</h1><p>Hi {{client_name}},</p><p>This is a reminder for your upcoming session with {{counsellor_name}} on {{scheduled_at}}.</p><p>Warm regards,<br>The Vanquish Therapies Team</p>',
                'placeholders' => ['client_name', 'scheduled_at', 'counsellor_name']
            ],
            'booking_deadline_reminder' => [
                'subject' => 'Please Note: Booking Your Next Block of Sessions',
                'body' => '<h1>Session Reminder</h1><p>Hi {{client_name}},</p><p>Please note: Your current block of sessions will come to an end on {{deadline_date}}. To help ensure your space remains secured with your counsellor, please book your next block of sessions at least 48 hours prior to your next session.</p><p><a href="{{booking_url}}" style="display:inline-block;padding:10px 20px;background-color:#6f1d56;color:white;text-decoration:none;border-radius:5px;">Book Now</a></p><p>Warm regards,<br>The Vanquish Therapies Team</p>',
                'placeholders' => ['client_name', 'deadline_date', 'booking_url']
            ],
            'generic_client_email' => [
                'subject' => 'Message from Vanquish Therapies',
                'body' => '<h1>Hello {{client_name}},</h1><div style="white-space: pre-wrap;">{{message}}</div>',
                'placeholders' => ['client_name', 'message']
            ],
            'generic_tc_email' => [
                'subject' => 'Message from Vanquish Therapies Admin',
                'body' => '<h1>Hello {{tc_name}},</h1><div style="white-space: pre-wrap;">{{message}}</div>',
                'placeholders' => ['tc_name', 'message']
            ],
            'tc_late_to_session' => [
                'subject' => 'Notice: Late Arrival to Session',
                'body' => '<div style="font-family: Arial, sans-serif; max-width: 640px; margin: 0 auto; color: #333;"><div style="background: linear-gradient(135deg, #b45309 0%, #d97706 100%); padding: 32px; border-radius: 12px 12px 0 0; text-align: center;"><h1 style="color: white; margin: 0; font-size: 22px;">Late Arrival Notice</h1><p style="color: rgba(255,255,255,0.85); margin: 8px 0 0; font-size: 13px;">Vanquish Therapies — Clinical Admin</p></div><div style="background: #ffffff; padding: 28px 32px; border: 1px solid #e8e8e8; border-top: none;"><p style="font-size: 15px; margin-top: 0;">Hi {{tc_name}},</p><p>Our records show you were <strong>late to a recent session</strong>. Punctuality is an important part of maintaining a professional standard for our clients, and repeated lateness may affect your placement progress.</p><p style="background: #fffbeb; border: 1px solid #fde68a; border-radius: 8px; padding: 12px 16px; font-size: 13px; color: #92400e;"><strong>Notes:</strong> {{notes}}<br><strong>Logged:</strong> {{logged_date}}</p><p>If you believe this was logged in error, please contact the clinical admin team.</p><p style="font-size: 14px; font-weight: bold; color: #6f1d56; margin-top: 24px;">Vanquish Therapies Admin</p></div></div>',
                'placeholders' => ['tc_name', 'event_label', 'notes', 'logged_date']
            ],
            'tc_missed_psg' => [
                'subject' => 'Notice: Missed Peer Support Group (PSG) Session',
                'body' => '<div style="font-family: Arial, sans-serif; max-width: 640px; margin: 0 auto; color: #333;"><div style="background: linear-gradient(135deg, #b45309 0%, #d97706 100%); padding: 32px; border-radius: 12px 12px 0 0; text-align: center;"><h1 style="color: white; margin: 0; font-size: 22px;">Missed PSG Notice</h1><p style="color: rgba(255,255,255,0.85); margin: 8px 0 0; font-size: 13px;">Vanquish Therapies — Clinical Admin</p></div><div style="background: #ffffff; padding: 28px 32px; border: 1px solid #e8e8e8; border-top: none;"><p style="font-size: 15px; margin-top: 0;">Hi {{tc_name}},</p><p>Our records show you <strong>missed your Peer Support Group (PSG) session</strong>. Attendance at PSG is a mandatory part of your placement.</p><p style="background: #fffbeb; border: 1px solid #fde68a; border-radius: 8px; padding: 12px 16px; font-size: 13px; color: #92400e;"><strong>Notes:</strong> {{notes}}<br><strong>Logged:</strong> {{logged_date}}</p><p>Please make sure to attend your next scheduled PSG session. If you believe this was logged in error, please contact the clinical admin team.</p><p style="font-size: 14px; font-weight: bold; color: #6f1d56; margin-top: 24px;">Vanquish Therapies Admin</p></div></div>',
                'placeholders' => ['tc_name', 'event_label', 'notes', 'logged_date']
            ],
            'tc_missed_session' => [
                'subject' => 'Notice: Missed Session',
                'body' => '<div style="font-family: Arial, sans-serif; max-width: 640px; margin: 0 auto; color: #333;"><div style="background: linear-gradient(135deg, #b91c1c 0%, #dc2626 100%); padding: 32px; border-radius: 12px 12px 0 0; text-align: center;"><h1 style="color: white; margin: 0; font-size: 22px;">Missed Session Notice</h1><p style="color: rgba(255,255,255,0.85); margin: 8px 0 0; font-size: 13px;">Vanquish Therapies — Clinical Admin</p></div><div style="background: #ffffff; padding: 28px 32px; border: 1px solid #e8e8e8; border-top: none;"><p style="font-size: 15px; margin-top: 0;">Hi {{tc_name}},</p><p>Our records show you <strong>missed a scheduled session</strong> with a client. This has been logged against your placement record.</p><p style="background: #fef2f2; border: 1px solid #fecaca; border-radius: 8px; padding: 12px 16px; font-size: 13px; color: #7f1d1d;"><strong>Notes:</strong> {{notes}}<br><strong>Logged:</strong> {{logged_date}}</p><p>Please contact the clinical admin team as soon as possible to discuss this and to reschedule with your client if needed.</p><p style="font-size: 14px; font-weight: bold; color: #6f1d56; margin-top: 24px;">Vanquish Therapies Admin</p></div></div>',
                'placeholders' => ['tc_name', 'event_label', 'notes', 'logged_date']
            ],
            'tc_late_session_notes' => [
                'subject' => 'Reminder: Session Notes Pending Completion',
                'body' => '<div style="font-family: Arial, sans-serif; max-width: 640px; margin: 0 auto; color: #333;"><div style="background: linear-gradient(135deg, #6f1d56 0%, #9b2c7e 100%); padding: 32px; border-radius: 12px 12px 0 0; text-align: center;"><h1 style="color: white; margin: 0; font-size: 22px;">Session Notes Reminder</h1><p style="color: rgba(255,255,255,0.85); margin: 8px 0 0; font-size: 13px;">Vanquish Therapies — Clinical Admin</p></div><div style="background: #ffffff; padding: 28px 32px; border: 1px solid #e8e8e8; border-top: none;"><p style="font-size: 15px; margin-top: 0;">Hi {{tc_name}},</p><p>This is a reminder that one or more of your <strong>session notes are pending completion</strong>. Please complete and submit them via your practitioner portal as soon as possible.</p><p style="background: #f9f4f8; border: 1px solid #e8d5e4; border-radius: 8px; padding: 12px 16px; font-size: 13px; color: #4a0d3a;"><strong>Notes:</strong> {{notes}}<br><strong>Logged:</strong> {{logged_date}}</p><p style="font-size: 14px; font-weight: bold; color: #6f1d56; margin-top: 24px;">Vanquish Therapies Admin</p></div></div>',
                'placeholders' => ['tc_name', 'event_label', 'notes', 'logged_date']
            ],
            'tc_session_disruption' => [
                'subject' => 'Notice: Session Disruption Logged (Internet/Device)',
                'body' => '<div style="font-family: Arial, sans-serif; max-width: 640px; margin: 0 auto; color: #333;"><div style="background: linear-gradient(135deg, #1d4ed8 0%, #2563eb 100%); padding: 32px; border-radius: 12px 12px 0 0; text-align: center;"><h1 style="color: white; margin: 0; font-size: 22px;">Session Disruption Logged</h1><p style="color: rgba(255,255,255,0.85); margin: 8px 0 0; font-size: 13px;">Vanquish Therapies — Clinical Admin</p></div><div style="background: #ffffff; padding: 28px 32px; border: 1px solid #e8e8e8; border-top: none;"><p style="font-size: 15px; margin-top: 0;">Hi {{tc_name}},</p><p>We have logged a <strong>technical disruption (internet/device)</strong> that affected one of your recent sessions.</p><p style="background: #eff6ff; border: 1px solid #bfdbfe; border-radius: 8px; padding: 12px 16px; font-size: 13px; color: #1e3a8a;"><strong>Notes:</strong> {{notes}}<br><strong>Logged:</strong> {{logged_date}}</p><p>If you are experiencing ongoing connectivity or equipment issues, please let the clinical admin team know so we can support you.</p><p style="font-size: 14px; font-weight: bold; color: #6f1d56; margin-top: 24px;">Vanquish Therapies Admin</p></div></div>',
                'placeholders' => ['tc_name', 'event_label', 'notes', 'logged_date']
            ],
            'message_waiting_counsellor' => [
                'subject' => 'You have a new message',
                'body' => '<div style="font-family: Arial, sans-serif; max-width: 640px; margin: 0 auto; color: #333;"><div style="background: linear-gradient(135deg, #6f1d56 0%, #9b2c7e 100%); padding: 32px; border-radius: 12px 12px 0 0; text-align: center;"><h1 style="color: white; margin: 0; font-size: 22px;">New Message Waiting</h1><p style="color: rgba(255,255,255,0.85); margin: 8px 0 0; font-size: 13px;">Vanquish Therapies — Practitioner Portal</p></div><div style="background: #ffffff; padding: 28px 32px; border: 1px solid #e8e8e8; border-top: none;"><p style="font-size: 15px; margin-top: 0;">Hi {{tc_name}},</p><p style="margin: 16px 0; color: #4b5563; font-size: 15px; line-height: 1.6;">You have received a new message on the Vanquish Therapies system. For privacy, the message is not included in this email. Please log in to read it.</p><p style="margin-top: 28px; margin-bottom: 28px; text-align: center;"><a href="{{login_url}}" style="display:inline-block;padding:12px 28px;background-color:#6f1d56;color:#ffffff;text-decoration:none;border-radius:6px;font-weight:600;font-size:14px;box-shadow: 0 2px 4px rgba(0,0,0,0.1);">Log in to read your message</a></p><p style="font-size: 13px; color: #666; margin-top: 24px;">Warm regards,<br><strong style="color: #6f1d56;">The Vanquish Therapies Team</strong></p></div></div>',
                'placeholders' => ['tc_name', 'login_url', 'portal_url']
            ],
            'message_waiting_admin' => [
                'subject' => 'You have a new message',
                'body' => '<div style="font-family: Arial, sans-serif; max-width: 640px; margin: 0 auto; color: #333;"><div style="background: linear-gradient(135deg, #6f1d56 0%, #9b2c7e 100%); padding: 32px; border-radius: 12px 12px 0 0; text-align: center;"><h1 style="color: white; margin: 0; font-size: 22px;">New Internal Message</h1><p style="color: rgba(255,255,255,0.85); margin: 8px 0 0; font-size: 13px;">Vanquish Therapies — Admin Dashboard</p></div><div style="background: #ffffff; padding: 28px 32px; border: 1px solid #e8e8e8; border-top: none;"><p style="font-size: 15px; margin-top: 0;">Hi {{recipient_name}},</p><p style="margin: 16px 0; color: #4b5563; font-size: 15px; line-height: 1.6;">You have received a new message on the Vanquish Therapies system. For privacy, the message is not included in this email. Please log in to read it.</p><p style="margin-top: 28px; margin-bottom: 28px; text-align: center;"><a href="{{login_url}}" style="display:inline-block;padding:12px 28px;background-color:#6f1d56;color:#ffffff;text-decoration:none;border-radius:6px;font-weight:600;font-size:14px;box-shadow: 0 2px 4px rgba(0,0,0,0.1);">Log in to read your message</a></p><p style="font-size: 13px; color: #666; margin-top: 24px;">Warm regards,<br><strong style="color: #6f1d56;">The Vanquish Therapies Team</strong></p></div></div>',
                'placeholders' => ['recipient_name', 'sender_name', 'login_url', 'dashboard_url']
            ],
            'auto_deduction_applied' => [
                'subject' => 'Booking Update - Auto-Deduction Applied',
                'body' => '<h1>Important Update</h1><p>Hi {{client_name}},</p><p>Your booking deadline has passed. As per our policy, you have been automatically allocated 3 sessions instead of 4 (same price).</p><p>Your sessions have been scheduled. Please check your booking portal for details.</p><p><a href="{{booking_url}}" style="display:inline-block;padding:10px 20px;background-color:#6f1d56;color:white;text-decoration:none;border-radius:5px;">View Bookings</a></p><p>Warm regards,<br>The Vanquish Therapies Team</p>',
                'placeholders' => ['client_name', 'booking_url']
            ],
            'booking_rescheduled' => [
                'subject' => 'Booking Rescheduled - Vanquish Therapies',
                'body' => '<h1>Your Booking has been Rescheduled</h1><p>Hi {{client_name}},</p><p>Your {{booking_type}} with {{counsellor_name}} has been scheduled.</p><p><strong>New Date/Time:</strong> {{new_scheduled_at}}</p><p><strong>Notes:</strong> {{notes}}</p><p><strong>Link:</strong> <a href="{{consultation_link}}">Join Session</a></p><p>Warm regards,<br>The Vanquish Therapies Team</p>',
                'placeholders' => ['client_name', 'booking_type', 'counsellor_name', 'new_scheduled_at', 'notes', 'consultation_link']
            ],
            'trainee_initial_invite' => [
                'subject' => 'Trainee Counsellor Placement Application',
                'body' => '
<div style="font-family: Arial, sans-serif; color: #333; line-height: 1.6;">
    <p>Dear {{first_name}},</p>
    <p>Thank you for your email.</p>
    <p>We greatly appreciate your interest in our placement programme. To apply, please complete the Placement Application form provided below:</p>
    <p><a href="{{trainee_application_url}}" style="color: #6f1d56; font-weight: bold; text-decoration: underline;">Vanquish Therapies │ Trainee Counsellor Placement Application</a></p>
    <p><strong>We require the following mandatory documents to be uploaded to the application:</strong></p>
    <ul>
        <li>CV</li>
        <li>Evidence of fitness to practice.</li>
        <li>Prior Counselling Qualification Certificates (e.g., Level 3/Level 4)</li>
        <li>Recent enhanced DBS certificate – Adult workforce (if you do not have a DBS, this can be arranged).</li>
        <li>Valid ID (Driver’s license or Passport)</li>
    </ul>
    <p>Thank you again for your interest in our placement programme. We look forward to receiving your application.</p>
    <p>Kind regards,</p>
    <p><strong>Nicole McLaren</strong><br>
    SAR and Compliance Team | Vanquish Therapies<br>
    Integrative Life Coaching & Counselling<br>
    E: <a href="mailto:sar.compliance@vanquishtherapies.co.uk">sar.compliance@vanquishtherapies.co.uk</a><br>
    W: <a href="http://www.vanquishtherapies.co.uk/">www.vanquishtherapies.co.uk</a></p>
</div>',
                'placeholders' => ['first_name', 'trainee_application_url']
            ],

            'trainee_application_received' => [
                'subject' => 'Placement Application Received',
                'body' => '<h1 style="margin: 0 0 20px 0; color: #1e293b; font-size: 24px; font-weight: 700; line-height: 1.3;">Application Received</h1>
<p style="margin: 0 0 16px 0; color: #333333; font-size: 16px;">Dear {{first_name}},</p>
<p style="margin: 0 0 16px 0; color: #4b5563; font-size: 15px; line-height: 1.6;">Thank you for submitting your Stage 1 placement application to <strong>Vanquish Therapies</strong>. We are pleased to confirm that we have <strong>successfully received your application</strong>, including all personal information, course details, and any supporting documents you uploaded.</p>
<div style="background: #f9f4f8; border-left: 4px solid #6f1d56; padding: 16px 20px; border-radius: 0 8px 8px 0; margin: 24px 0;">
    <p style="margin: 0; font-size: 14px; color: #555555;"><strong>Submission Email:</strong> {{email}}</p>
    <p style="margin: 6px 0 0; font-size: 14px; color: #555555;">Please keep this email for your records. A copy of the submitted form is not separately provided.</p>
</div>
<h2 style="color: #6f1d56; font-size: 18px; border-bottom: 2px solid #f0e6ed; padding-bottom: 8px; margin: 24px 0 16px 0;">Review Process &amp; Timeline</h2>
<p style="margin: 0 0 16px 0; color: #4b5563; font-size: 15px; line-height: 1.6;">Our <strong>Compliance Team</strong> and clinical lead review every application carefully and personally. Here is what to expect:</p>
<table width="100%" cellpadding="0" cellspacing="0" style="border-collapse: collapse; margin: 16px 0;">
    <tr>
        <td style="padding: 12px 16px; background: #f9f4f8; font-weight: bold; color: #6f1d56; width: 30%; vertical-align: top; font-size: 14px;">⏱ 48–72 hours</td>
        <td style="padding: 12px 16px; font-size: 14px; vertical-align: top; color: #4b5563;">Initial review of your application, documents, and course information by our clinical lead.</td>
    </tr>
    <tr>
        <td style="padding: 12px 16px; background: #f0e6ed; font-weight: bold; color: #6f1d56; font-size: 14px; vertical-align: top;">📧 Stage 2 Invitation</td>
        <td style="padding: 12px 16px; font-size: 14px; vertical-align: top; color: #4b5563;">If your application meets our placement criteria, you will receive a <strong>Stage 2 Video Interview invitation</strong> within 48 hours of this email.</td>
    </tr>
    <tr>
        <td style="padding: 12px 16px; background: #f9f4f8; font-weight: bold; color: #6f1d56; font-size: 14px; vertical-align: top;">🎥 Stage 2: Video</td>
        <td style="padding: 12px 16px; font-size: 14px; vertical-align: top; color: #4b5563;">Complete a structured asynchronous video interview (approx. 15–20 minutes) from home.</td>
    </tr>
    <tr>
        <td style="padding: 12px 16px; background: #f0e6ed; font-weight: bold; color: #6f1d56; font-size: 14px; vertical-align: top;">🤝 Stage 3: Interview</td>
        <td style="padding: 12px 16px; font-size: 14px; vertical-align: top; color: #4b5563;">Successful Stage 2 candidates are invited for a final face-to-face (online) interview.</td>
    </tr>
</table>
<h2 style="color: #6f1d56; font-size: 18px; border-bottom: 2px solid #f0e6ed; padding-bottom: 8px; margin: 24px 0 16px 0;">What Happens Next</h2>
<ul style="padding-left: 20px; line-height: 1.8; font-size: 14px; color: #444444; margin: 0 0 20px 0;">
    <li>You do not need to take any action at this stage — we will contact you directly.</li>
    <li>If progressed to Stage 2, you will receive a separate email with a personal video interview link.</li>
    <li>Please ensure emails from <strong>no-reply@vanquishtherapies.co.uk</strong> are not going to your spam folder.</li>
    <li>To update application information, email <a href="mailto:compliance@vanquishtherapies.co.uk" style="color:#6f1d56;">compliance@vanquishtherapies.co.uk</a>.</li>
</ul>
<div style="background: #fffbf0; border: 1px solid #f0d080; border-radius: 8px; padding: 16px 20px; margin: 24px 0;">
    <p style="margin: 0; font-size: 13px; color: #7a6000;"><strong>⚠️ Important:</strong> If you have not received a Stage 2 invitation within <strong>72 hours</strong>, please check your spam folder before contacting us.</p>
</div>
<p style="font-size: 15px; font-weight: bold; color: #6f1d56; margin-top: 24px;">The Compliance Team<br><span style="font-weight: normal; color: #777777; font-size: 13px;">Vanquish Therapies</span></p>',
                'placeholders' => ['first_name', 'email']
            ],

            'trainee_stage_two_invite' => [
                'subject' => 'Congratulations! You Have Progressed to Stage 2 – Video Interview',
                'body' => '
<div style="font-family: Arial, sans-serif; color: #333; line-height: 1.6;">
    <p>Dear {{first_name}},</p>
    <p>We hope this email finds you well.</p>
    <p>Congratulations on making it to the <strong>2nd stage out of 3</strong> of our vetting and interview process!</p>
    <p>This video interview process will take approximately <strong>15-20 minutes</strong> to complete. Please ensure you are in a quiet, private environment, and free from distractions before beginning, as your full attention is important. We appreciate your time and thoughtful responses.</p>
    <p><strong>We are inviting you to answer five simple questions - there are no trick questions. We are simply hoping to learn more about you and hear from your authentic, genuine self.</strong></p>
    <p><strong>Please note the following below:</strong></p>
    <ul>
        <li>You will have <strong>3 working days</strong> to begin this interview. If you do not begin the interview within this time frame, the link will expire, and you will need to reapply for the placement.</li>
        <li>Connect to the interview using your <strong>Computer/Laptop</strong>.</li>
        <li>You should <strong>respond to all questions in English</strong></li>
        <li>Please ensure that you <strong>grant permission to access your camera and microphone</strong>, as this is required to progress through the interview.</li>
        <li>If you have any issues with recording your videos, try using your mobile data connection.</li>
        <li>The recording will start automatically when you click on each question. Each question will have a time limit.</li>
    </ul>
    <p><strong>To begin,</strong> please click here - <a href="{{interview_url}}" style="color: #6f1d56; font-weight: bold; text-decoration: underline;">Video Interview - Vanquish Therapies Placement</a></p>
    <p>Best of luck! If you clear this stage, you will be invited for an online face-to-face interview.</p>
    <p>Kind regards,</p>
    <p><strong>Nicole McLaren</strong></p>
</div>',
                'placeholders' => ['first_name', 'interview_url']
            ],
            'trainee_video_interview_received' => [
                'subject' => 'Video Interview Received – Next Steps',
                'body' => '<h1 style="margin: 0 0 20px 0; color: #1e293b; font-size: 24px; font-weight: 700; line-height: 1.3;">Video Interview Received</h1>
<p style="font-size: 16px; margin: 0 0 16px 0; color: #333333;">Dear {{first_name}},</p>
<p style="margin: 0 0 16px 0; color: #4b5563; font-size: 15px; line-height: 1.6;">Thank you for completing your Stage 2 video interview. We have successfully received all of your responses.</p>
<p style="margin: 0 0 24px 0; color: #4b5563; font-size: 15px; line-height: 1.6;">A member of our team will review your video interview — this typically takes 3–5 working days. If successful, you will receive a Stage 3 invitation to book a face-to-face interview.</p>
<p style="font-size: 15px; font-weight: bold; color: #6f1d56; margin-top: 24px;">The Compliance Team<br><span style="font-weight: normal; color: #777777; font-size: 13px;">Vanquish Therapies</span></p>',
                'placeholders' => ['first_name']
            ],
            'admin_video_review_notification' => [
                'subject' => '🎬 Action Required: Stage 2 Video Ready for Review – {{applicant_name}}',
                'body' => '
<div style="font-family: Arial, sans-serif; max-width: 580px; margin: 0 auto; color: #333;">
  <div style="background: linear-gradient(135deg, #6f1d56 0%, #9b2c7e 100%); padding: 24px 28px; border-radius: 10px 10px 0 0;">
    <h1 style="color: white; margin: 0; font-size: 18px;">🎬 Stage 2 Video Submission Ready</h1>
    <p style="color: rgba(255,255,255,0.8); margin: 6px 0 0; font-size: 13px;">Vanquish Therapies — Admin Notification</p>
  </div>
  <div style="background: #fff; padding: 28px; border: 1px solid #e2e8f0; border-top: none; border-radius: 0 0 10px 10px;">
    <p style="margin-top: 0; font-size: 14px;">A trainee applicant has completed their <strong>Stage 2 HireVire video interview</strong> and their responses are now ready for your review.</p>
    <table width="100%" cellpadding="6" cellspacing="0" style="border-collapse: collapse; font-size: 14px; margin: 16px 0;">
      <tr style="background: #f8fafc;"><td style="padding: 10px 14px; font-weight: bold; width: 35%; color: #64748b; border: 1px solid #e2e8f0;">Applicant</td><td style="padding: 10px 14px; border: 1px solid #e2e8f0;">{{applicant_name}}</td></tr>
      <tr><td style="padding: 10px 14px; font-weight: bold; color: #64748b; border: 1px solid #e2e8f0;">Email</td><td style="padding: 10px 14px; border: 1px solid #e2e8f0;">{{applicant_email}}</td></tr>
      <tr style="background: #f8fafc;"><td style="padding: 10px 14px; font-weight: bold; color: #64748b; border: 1px solid #e2e8f0;">Submitted At</td><td style="padding: 10px 14px; border: 1px solid #e2e8f0;">{{submitted_at}}</td></tr>
    </table>
    <div style="text-align: center; margin: 24px 0;">
      <a href="{{dashboard_url}}" style="display:inline-block;padding:12px 28px;background:#6f1d56;color:white;text-decoration:none;border-radius:8px;font-weight:bold;font-size:14px;">Review Application in Dashboard →</a>
    </div>
    <p style="font-size: 12px; color: #94a3b8; text-align: center;">After reviewing the video, update the status to <em>Stage 2 Approved</em> or <em>Rejected</em> in the dashboard to trigger the corresponding email to the applicant.</p>
  </div>
</div>',
                'placeholders' => ['applicant_name', 'applicant_email', 'submitted_at', 'dashboard_url']
            ],
            'admin_placement_response_notification' => [
                'subject' => 'Placement Response: {{applicant_name}} — {{placement_accepted}}',
                'body' => '
<div style="font-family: Arial, sans-serif; max-width: 580px; margin: 0 auto; color: #333;">
  <div style="background: linear-gradient(135deg, #6f1d56 0%, #9b2c7e 100%); padding: 24px 28px; border-radius: 10px 10px 0 0;">
    <h1 style="color: white; margin: 0; font-size: 18px;">Placement Response Received</h1>
    <p style="color: rgba(255,255,255,0.8); margin: 6px 0 0; font-size: 13px;">Vanquish Therapies — Admin Notification</p>
  </div>
  <div style="background: #fff; padding: 28px; border: 1px solid #e2e8f0; border-top: none; border-radius: 0 0 10px 10px;">
    <p style="margin-top: 0; font-size: 14px;">A trainee applicant has responded to their placement offer.</p>
    <table width="100%" cellpadding="6" cellspacing="0" style="border-collapse: collapse; font-size: 14px; margin: 16px 0;">
      <tr style="background: #f8fafc;"><td style="padding: 10px 14px; font-weight: bold; width: 35%; color: #64748b; border: 1px solid #e2e8f0;">Applicant</td><td style="padding: 10px 14px; border: 1px solid #e2e8f0;">{{applicant_name}}</td></tr>
      <tr><td style="padding: 10px 14px; font-weight: bold; color: #64748b; border: 1px solid #e2e8f0;">Email</td><td style="padding: 10px 14px; border: 1px solid #e2e8f0;">{{applicant_email}}</td></tr>
      <tr style="background: #f8fafc;"><td style="padding: 10px 14px; font-weight: bold; color: #64748b; border: 1px solid #e2e8f0;">Placement</td><td style="padding: 10px 14px; border: 1px solid #e2e8f0;">{{placement_accepted}}</td></tr>
      <tr><td style="padding: 10px 14px; font-weight: bold; color: #64748b; border: 1px solid #e2e8f0;">Induction Attendance</td><td style="padding: 10px 14px; border: 1px solid #e2e8f0;">{{induction_rsvp}}</td></tr>
    </table>
    <div style="text-align: center; margin: 24px 0;">
      <a href="{{dashboard_url}}" style="display:inline-block;padding:12px 28px;background:#6f1d56;color:white;text-decoration:none;border-radius:8px;font-weight:bold;font-size:14px;">View Application in Dashboard →</a>
    </div>
  </div>
</div>',
                'placeholders' => ['applicant_name', 'applicant_email', 'placement_accepted', 'induction_rsvp', 'dashboard_url']
            ],
            'trainee_stage_three_invite' => [
                'subject' => 'Congratulations! Stage 2 Passed – Book Your Placement Interview',
                'body' => '
<div style="font-family: Arial, sans-serif; color: #333; line-height: 1.6;">
    <p>Dear {{first_name}},</p>
    <p>Congratulations! We are delighted to inform you that your application for our Trainee Counsellor Placement Programme has been successful at this stage. We are inviting you to Stage 3 For a face-to-face online interview.</p>
    <p>Please find the link below to our website, where you can select an available interview date and time:<br>
    <a href="{{booking_link}}" style="color: #6f1d56; font-weight: bold; text-decoration: underline;">{{booking_link}}</a></p>
    <p>If your interview is successful, the next step will be for you to attend our mandatory online Induction scheduled for {{induction_date}}.</p>
    <p>We look forward to connecting with you.</p>
    <p>Kind regards,</p>
    <p><strong>Nicole McLaren</strong><br>
    SAR and Compliance Team | Vanquish Therapies<br>
    Integrative Life Coaching &amp; Counselling<br>
    E: <a href="mailto:sar.compliance@vanquishtherapies.co.uk">sar.compliance@vanquishtherapies.co.uk</a><br>
    W: <a href="http://www.vanquishtherapies.co.uk/">www.vanquishtherapies.co.uk</a></p>
</div>',
                'placeholders' => ['first_name', 'booking_link', 'induction_date']
            ],
            'trainee_interview_confirmed' => [
                'subject' => '📊 Interview Confirmed – Vanquish Therapies Placement',
                'body' => '
<div style="font-family: Arial, sans-serif; max-width: 640px; margin: 0 auto; color: #333;">
  <div style="background: linear-gradient(135deg, #6f1d56 0%, #9b2c7e 100%); padding: 36px 32px; border-radius: 12px 12px 0 0; text-align: center;">
    <div style="font-size: 40px; margin-bottom: 8px;">📅</div>
    <h1 style="color: white; margin: 0; font-size: 24px;">Interview Confirmed</h1>
    <p style="color: rgba(255,255,255,0.85); margin: 8px 0 0; font-size: 14px;">Vanquish Therapies — Placement Interview</p>
  </div>
  <div style="background: #ffffff; padding: 32px; border: 1px solid #e8e8e8; border-top: none;">
    <p style="font-size: 16px; margin-top: 0;">Dear <strong>{{first_name}}</strong>,</p>
    <p>Great news! Your placement interview has been <strong>successfully booked</strong>. Please keep the details below safe — a reminder will be sent 48 hours before your interview.</p>
    <div style="background: #f9f4f8; border-radius: 12px; padding: 24px; margin: 24px 0; border: 2px solid #e8d5e4;">
      <h2 style="color: #6f1d56; margin: 0 0 16px; font-size: 18px; text-align: center;">Your Interview Details</h2>
      <table width="100%" cellpadding="0" cellspacing="0">
        <tr><td style="padding: 8px 0; font-size: 13px; color: #888; width: 120px;">DATE</td><td style="padding: 8px 0; font-size: 15px; font-weight: bold; color: #333;">{{date}}</td></tr>
        <tr><td style="padding: 8px 0; font-size: 13px; color: #888;">TIME</td><td style="padding: 8px 0; font-size: 15px; font-weight: bold; color: #333;">{{time}}</td></tr>
        <tr><td style="padding: 8px 0; font-size: 13px; color: #888;">HOST</td><td style="padding: 8px 0; font-size: 14px; color: #333;">{{host_name}}</td></tr>
        <tr><td style="padding: 8px 0; font-size: 13px; color: #888;">MEETING ID</td><td style="padding: 8px 0; font-size: 14px; color: #333; font-family: monospace;">{{meeting_id}}</td></tr>
      </table>
      <div style="text-align: center; margin-top: 20px;">
        <a href="{{zoom_link}}" style="display:inline-block;padding:14px 32px;background:linear-gradient(135deg,#6f1d56,#9b2c7e);color:white;text-decoration:none;border-radius:10px;font-weight:bold;font-size:15px;">🔗 Join via Zoom</a>
      </div>
    </div>
    <h2 style="color: #6f1d56; font-size: 17px; border-bottom: 2px solid #f0e6ed; padding-bottom: 8px;">Before Your Interview</h2>
    <ul style="padding-left: 20px; line-height: 2.2; font-size: 14px; color: #444;">
      <li>✅ Join the Zoom call <strong>5 minutes early</strong></li>
      <li>✅ Ensure you are in a <strong>quiet, private location</strong></li>
      <li>✅ Test your <strong>camera and microphone</strong> beforehand</li>
      <li>✅ Have a <strong>valid photo ID</strong> ready to present</li>
      <li>✅ Dress <strong>professionally</strong> — treat this as a formal interview</li>
      <li>✅ Have a copy of your <strong>application and CV</strong> nearby for reference</li>
    </ul>
    <div style="background: #fef2f2; border: 1px solid #fecaca; border-radius: 8px; padding: 14px 18px; margin: 20px 0;">
      <p style="margin: 0; font-size: 13px; color: #7f1d1d;"><strong>⚠️ Need to reschedule?</strong> If you need to change your interview date, please contact us immediately at <a href="mailto:compliance@vanquishtherapies.co.uk" style="color:#6f1d56;">compliance@vanquishtherapies.co.uk</a> and we will do our best to accommodate you.</p>
    </div>
    <p style="font-size: 15px; font-weight: bold; color: #6f1d56; margin-top: 20px;">The Compliance Team<br><span style="font-weight: normal; color: #777; font-size: 13px;">Vanquish Therapies</span></p>
  </div>
  <div style="background: #f5f5f5; padding: 16px 32px; border-radius: 0 0 12px 12px; border: 1px solid #e8e8e8; border-top: none; text-align: center;">
    <p style="font-size: 11px; color: #aaa; margin: 0;">© Vanquish Therapies Ltd. All rights reserved.</p>
  </div>
</div>',
                'placeholders' => ['first_name', 'full_name', 'date', 'time', 'zoom_link', 'meeting_id', 'host_name', 'service_name', 'scheduled_at']
            ],
            'trainee_interview_reminder' => [
                'subject' => '⏰ 48-Hour Reminder: Your Placement Interview Tomorrow',
                'body' => '
<div style="font-family: Arial, sans-serif; max-width: 640px; margin: 0 auto; color: #333;">
  <div style="background: linear-gradient(135deg, #6f1d56 0%, #9b2c7e 100%); padding: 36px 32px; border-radius: 12px 12px 0 0; text-align: center;">
    <div style="font-size: 40px; margin-bottom: 8px;">⏰</div>
    <h1 style="color: white; margin: 0; font-size: 24px;">Interview Reminder</h1>
    <p style="color: rgba(255,255,255,0.85); margin: 8px 0 0; font-size: 14px;">Vanquish Therapies — Placement Interview</p>
  </div>
  <div style="background: #ffffff; padding: 32px; border: 1px solid #e8e8e8; border-top: none;">
    <p style="font-size: 16px; margin-top: 0;">Dear <strong>{{first_name}}</strong>,</p>
    <p>This is a <strong>friendly reminder</strong> that your Vanquish Therapies Placement Interview is coming up soon. We look forward to speaking with you!</p>
    <div style="background: #f0e6ed; border-radius: 12px; padding: 24px; margin: 24px 0; text-align: center; border: 2px solid #d8b4d0;">
      <p style="margin: 0 0 4px; font-size: 13px; color: #6f1d56; font-weight: bold; text-transform: uppercase; letter-spacing: 1px;">Your Interview</p>
      <p style="margin: 0; font-size: 22px; font-weight: bold; color: #4a0d3a;">{{scheduled_at}}</p>
    </div>
    <div style="text-align: center; margin: 24px 0;">
      <a href="{{zoom_link}}" style="display:inline-block;padding:14px 32px;background:linear-gradient(135deg,#6f1d56,#9b2c7e);color:white;text-decoration:none;border-radius:10px;font-weight:bold;font-size:15px;">🔗 Join via Zoom</a>
      <p style="margin: 8px 0 0; font-size: 12px; color: #888;">Click this link at the time of your interview</p>
    </div>
    <h2 style="color: #6f1d56; font-size: 17px; border-bottom: 2px solid #f0e6ed; padding-bottom: 8px;">Last-Minute Checklist</h2>
    <ul style="padding-left: 20px; line-height: 2.2; font-size: 14px; color: #444;">
      <li>✅ Test your <strong>camera and microphone</strong> now</li>
      <li>✅ Find a <strong>quiet, well-lit location</strong></li>
      <li>✅ Charge your device or plug it in</li>
      <li>✅ Have your <strong>photo ID</strong> ready</li>
      <li>✅ Join the call <strong>5 minutes early</strong></li>
      <li>✅ Close unnecessary browser tabs and apps</li>
    </ul>
    <div style="background: #fefce8; border: 1px solid #fde047; border-radius: 8px; padding: 14px 18px; margin: 20px 0;">
      <p style="margin: 0; font-size: 13px; color: #713f12;"><strong>💡 Tip:</strong> If you have any technical issues on the day, contact us immediately at <a href="mailto:compliance@vanquishtherapies.co.uk" style="color:#6f1d56;">compliance@vanquishtherapies.co.uk</a> before your scheduled time.</p>
    </div>
    <p style="font-size: 14px;">Good luck, {{first_name}} — we look forward to meeting you!</p>
    <p style="font-size: 15px; font-weight: bold; color: #6f1d56; margin-top: 20px;">The Compliance Team<br><span style="font-weight: normal; color: #777; font-size: 13px;">Vanquish Therapies</span></p>
  </div>
  <div style="background: #f5f5f5; padding: 16px 32px; border-radius: 0 0 12px 12px; border: 1px solid #e8e8e8; border-top: none; text-align: center;">
    <p style="font-size: 11px; color: #aaa; margin: 0;">© Vanquish Therapies Ltd. All rights reserved.</p>
  </div>
</div>',
                'placeholders' => ['first_name', 'scheduled_at', 'zoom_link', 'date', 'time', 'host_name']
            ],
            'trainee_placement_acceptance' => [
                'subject' => 'Placement Acceptance',
                'body' => '<h1 style="margin: 0 0 20px 0; color: #1e293b; font-size: 24px; font-weight: 700; line-height: 1.3;">Placement Acceptance</h1>
<p style="font-size: 16px; margin: 0 0 16px 0; color: #333333;">Dear {{first_name}},</p>
<p style="margin: 0 0 20px 0; color: #4b5563; font-size: 15px; line-height: 1.6;">Congratulations on your successful interview! We are delighted to confirm your acceptance onto our placement programme at <strong>Vanquish Therapies</strong>.</p>
<div style="background: #f8fafc; border-radius: 12px; padding: 24px; margin: 24px 0; border: 1px solid #e2e8f0;">
    <h2 style="color: #6f1d56; margin: 0 0 16px 0; font-size: 18px;">Mandatory Induction Details</h2>
    <p style="margin: 0 0 8px 0; font-size: 14px; color: #64748b;">Please attend our mandatory induction:</p>
    <p style="margin: 8px 0 0; font-size: 15px; font-weight: bold; color: #333333;"><strong>Date of Induction:</strong> {{induction_date}}</p>
    <p style="margin: 6px 0 0; font-size: 14px; color: #333333;"><strong>Platform:</strong> <a href="{{induction_zoom_link}}" style="color:#6f1d56;text-decoration:underline;">Zoom Link</a></p>
</div>
<h2 style="color: #6f1d56; font-size: 17px; border-bottom: 2px solid #f0e6ed; padding-bottom: 8px; margin: 24px 0 16px 0;">Immediate Paperwork Requirements</h2>
<p style="font-size: 14px; color: #475569; margin: 0 0 16px 0;">To finalize your placement, please complete the immediate paperwork requirements listed below:</p>
<div style="margin: 20px 0;">
    <div style="padding: 16px; background: #fffbeb; border: 1px solid #fef3c7; border-radius: 8px; margin-bottom: 12px;">
        <p style="margin: 0; font-size: 14px; color: #1e293b;"><strong>1. 4-Way Agreement</strong> (Attached)</p>
        <p style="margin: 4px 0 0; font-size: 12px; color: #92400e;">Please sign and have your tutor and clinical supervisor sign the attached 4-way agreement document, and return it to us.</p>
    </div>
    <div style="padding: 16px; background: #fdf2f8; border: 1px solid #e8d5e4; border-radius: 8px;">
        <p style="margin: 0 0 4px 0; font-size: 14px; color: #1e293b;"><strong>2. Personal Therapy Confirmation Form</strong></p>
        <p style="margin: 4px 0 12px; font-size: 12px; color: #6f1d56;">Please confirm your personal therapy by completing the form at the following link:</p>
        <a href="{{therapy_form_url}}" target="_blank" style="display:inline-block;padding:10px 20px;background:#6f1d56;color:white;text-decoration:none;border-radius:6px;font-weight:bold;font-size:13px;">Complete Therapy Form</a>
    </div>
</div>
<p style="font-size: 15px; font-weight: bold; color: #6f1d56; margin-top: 24px;">Warm congratulations once again, and welcome to the team!<br><br><span style="font-weight: normal; color: #777777; font-size: 13px;">The Compliance Team<br>Vanquish Therapies</span></p>',
                'placeholders' => ['first_name', 'induction_date', 'induction_zoom_link', 'therapy_form_url']
            ],
            'trainee_portal_invite' => [
                'subject' => '🔐 Your Vanquish Therapies Practitioner Portal Access',
                'body' => '
<div style="font-family: Arial, sans-serif; max-width: 640px; margin: 0 auto; color: #333;">
  <div style="background: linear-gradient(135deg, #6f1d56 0%, #9b2c7e 100%); padding: 40px 32px; border-radius: 12px 12px 0 0; text-align: center;">
    <div style="font-size: 48px; margin-bottom: 12px;">🔐</div>
    <h1 style="color: white; margin: 0; font-size: 24px;">Portal Access Granted</h1>
    <p style="color: rgba(255,255,255,0.8); margin: 8px 0 0; font-size: 15px;">Practitioner Onboarding — Vanquish Therapies</p>
  </div>
  <div style="background: #ffffff; padding: 32px; border: 1px solid #e2e8f0; border-top: none;">
    <p style="font-size: 16px; margin-top: 0;">Hello <strong>{{first_name}}</strong>,</p>
    <p>We are excited to grant you access to the <strong>Vanquish Practitioner Portal</strong>. This will be your hub for client matching, session notes, policy documents, and clinical oversight.</p>

    <div style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 10px; padding: 20px 24px; margin: 24px 0;">
      <h2 style="color: #6f1d56; margin: 0 0 14px; font-size: 16px;">🔑 Your Login Credentials</h2>
      <table width="100%" cellpadding="0" cellspacing="0">
        <tr><td style="padding: 6px 0; font-size: 13px; color: #64748b; width: 130px;">Login Email</td><td style="padding: 6px 0; font-size: 14px; font-weight: bold; color: #6f1d56; font-family: monospace;">{{email}}</td></tr>
        <tr><td style="padding: 6px 0; font-size: 13px; color: #64748b;">Temporary Password</td><td style="padding: 6px 0; font-size: 14px; font-weight: bold; color: #6f1d56; font-family: monospace;">{{temporary_password}}</td></tr>
      </table>
      <p style="margin: 12px 0 0; font-size: 12px; color: #94a3b8;">⚠️ Please change your password after your first login for security.</p>
    </div>

    <div style="text-align: center; margin: 28px 0;">
      <a href="{{portal_link}}" style="display:inline-block;padding:16px 36px;background:#6f1d56;color:white;text-decoration:none;border-radius:10px;font-weight:bold;font-size:16px;">🚀 Access My Portal Now</a>
      <p style="margin: 10px 0 0; font-size: 12px; color: #888;">Sign in with the credentials above</p>
    </div>

    <h2 style="color: #6f1d56; font-size: 17px; border-bottom: 2px solid #f1f5f9; padding-bottom: 8px;">Onboarding Checklist</h2>
    <p style="font-size: 14px; color: #475569;">Once inside your portal, please complete these induction steps:</p>
    <ul style="padding-left: 20px; line-height: 2; font-size: 14px; color: #333;">
      <li>📖 Read the <strong>Welcome Guide &amp; Practitioner Handbook</strong></li>
      <li>✍️ Sign the <strong>Induction Disclosure Form</strong></li>
      <li>📂 Upload your <strong>Professional Certificates</strong> &amp; <strong>Insurance</strong></li>
      <li>🔒 Change your <strong>temporary password</strong> immediately</li>
      <li>📞 Confirm the <strong>Emergency WhatsApp line: +44 0800 008 6556</strong></li>
    </ul>

    <div style="background: #fdf2f8; border: 1px solid #fce7f3; border-radius: 8px; padding: 14px 18px; margin: 24px 0;">
      <p style="margin: 0; font-size: 13px; color: #9d174d;"><strong>💡 Need Help?</strong> Contact our team at <a href="mailto:compliance@vanquishtherapies.co.uk" style="color:#6f1d56;">compliance@vanquishtherapies.co.uk</a> and we will be happy to assist you.</p>
    </div>

    <p style="font-size: 15px; font-weight: bold; color: #6f1d56; margin-top: 20px;">The Compliance Team<br><span style="font-weight: normal; color: #777; font-size: 13px;">Vanquish Therapies</span></p>
  </div>
  <div style="background: #f8fafc; padding: 16px 32px; border-radius: 0 0 12px 12px; border: 1px solid #e2e8f0; border-top: none; text-align: center;">
    <p style="font-size: 11px; color: #94a3b8; margin: 0;">© Vanquish Therapies Ltd. All rights reserved.</p>
  </div>
</div>',
                'placeholders' => ['first_name', 'portal_link', 'email', 'temporary_password']
            ],
            'trainee_induction_completed' => [
                'subject' => 'Portal & Policies',
                'body' => '
<div style="font-family: Arial, sans-serif; color: #333; line-height: 1.6;">
    <p>Dear {{first_name}},</p>
    <p>We hope this email finds you well.</p>
    <p>Thank you for attending the induction on Monday. Please find attached the finalised copy of our agreement for everyone\'s records.</p>
    <p>Please see the <strong>next steps</strong> below:</p>
    <p>You will receive an invite to sign-up for our portal (check spam/junk folder), the sign-up process is straight forward, if you do require any assistance or face any issues, please let us know. I have attached a welcome guide to this email on how to use the portal and how to access our policies.</p>
    <p>Please also find attached our Induction disclosure form, this form is to be signed after you have read and understood the policies. This must be uploaded to the portal directly once filled and signed by you. Kindly, do <strong>not</strong> upload the form or any other file inside the &lsquo;induction pack&rsquo; folder, as all trainee counsellors/coaches have access to it. You can create a separate folder or upload files outside the &lsquo;shared with me&rsquo; folder.</p>
    <p>Please upload a copy of your professional membership and insurance details to the portal.</p>
    <p>Our Coordinators &ndash; Jae and Rooshan are both available on the portal for easy communication and assistance, please copy both of them when messaging through the portal. In case of emergencies, safeguarding concerns, you can communicate with the Coordinators through WhatsApp <strong>+44 0800 008 6556</strong> for prompt communication.</p>
    <p>Kind regards,</p>
    <p><strong>Nicole McLaren</strong><br>
    SAR and Compliance Team | Vanquish Therapies<br>
    Integrative Life Coaching &amp; Counselling<br>
    E: <a href="mailto:sar.compliance@vanquishtherapies.co.uk">sar.compliance@vanquishtherapies.co.uk</a><br>
    W: <a href="http://www.vanquishtherapies.co.uk/">www.vanquishtherapies.co.uk</a></p>
</div>',
                'placeholders' => ['first_name']
            ],
            'counsellor_portal_invite' => [
                'subject' => '🔐 Your Vanquish Therapies Practitioner Portal Access',
                'body' => '
<div style="font-family: Arial, sans-serif; max-width: 640px; margin: 0 auto; color: #333;">
  <div style="background: linear-gradient(135deg, #6f1d56 0%, #9b2c7e 100%); padding: 40px 32px; border-radius: 12px 12px 0 0; text-align: center;">
    <div style="font-size: 48px; margin-bottom: 12px;">🔐</div>
    <h1 style="color: white; margin: 0; font-size: 24px;">Portal Access Granted</h1>
    <p style="color: rgba(255,255,255,0.8); margin: 8px 0 0; font-size: 15px;">Practitioner Portal Access — Vanquish Therapies</p>
  </div>
  <div style="background: #ffffff; padding: 32px; border: 1px solid #e2e8f0; border-top: none;">
    <p style="font-size: 16px; margin-top: 0;">Hello <strong>{{first_name}}</strong>,</p>
    <p>We are pleased to invite you to the <strong>Vanquish Practitioner Portal</strong>. This portal will serve as your primary platform for managing your client roster, submitting session notes, and accessing important clinical documents.</p>

    <div style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 10px; padding: 20px 24px; margin: 24px 0;">
      <h2 style="color: #6f1d56; margin: 0 0 14px; font-size: 16px;">🔑 Your Login Credentials</h2>
      <table width="100%" cellpadding="0" cellspacing="0">
        <tr><td style="padding: 6px 0; font-size: 13px; color: #64748b; width: 130px;">Login Email</td><td style="padding: 6px 0; font-size: 14px; font-weight: bold; color: #6f1d56; font-family: monospace;">{{email}}</td></tr>
        <tr><td style="padding: 6px 0; font-size: 13px; color: #64748b;">Temporary Password</td><td style="padding: 6px 0; font-size: 14px; font-weight: bold; color: #6f1d56; font-family: monospace;">{{temporary_password}}</td></tr>
      </table>
      <p style="margin: 12px 0 0; font-size: 12px; color: #94a3b8;">⚠️ For security, you will be prompted to change your password upon your first login.</p>
    </div>

    <div style="text-align: center; margin: 28px 0;">
      <a href="{{portal_link}}" style="display:inline-block;padding:16px 36px;background:#6f1d56;color:white;text-decoration:none;border-radius:10px;font-weight:bold;font-size:16px;">🚀 Access Practitioner Portal</a>
      <p style="margin: 10px 0 0; font-size: 12px; color: #888;">Log in with the credentials provided above</p>
    </div>

    <h2 style="color: #6f1d56; font-size: 17px; border-bottom: 2px solid #f1f5f9; padding-bottom: 8px;">Getting Started</h2>
    <p style="font-size: 14px; color: #475569;">To ensure a smooth transition to the portal, please complete the following steps:</p>
    <ul style="padding-left: 20px; line-height: 2; font-size: 14px; color: #333;">
      <li>🔐 Update your <strong>temporary password</strong> to a secure personal one</li>
      <li>📂 Review your <strong>Active Clients</strong> list for accuracy</li>
      <li>📖 Access the <strong>Practitioner Handbook</strong> in the shared documents section</li>
      <li>📞 Save the <strong>Emergency Clinical Support Line</strong> to your contacts</li>
    </ul>

    <div style="background: #fdf2f8; border: 1px solid #fce7f3; border-radius: 8px; padding: 14px 18px; margin: 24px 0;">
      <p style="margin: 0; font-size: 13px; color: #9d174d;"><strong>💡 Support Needed?</strong> If you encounter any technical issues, please contact <a href="mailto:compliance@vanquishtherapies.co.uk" style="color:#6f1d56;">compliance@vanquishtherapies.co.uk</a>.</p>
    </div>

    <p style="font-size: 15px; font-weight: bold; color: #6f1d56; margin-top: 20px;">The Compliance Team<br><span style="font-weight: normal; color: #777; font-size: 13px;">Vanquish Therapies</span></p>
  </div>
  <div style="background: #f8fafc; padding: 16px 32px; border-radius: 0 0 12px 12px; border: 1px solid #e2e8f0; border-top: none; text-align: center;">
    <p style="font-size: 11px; color: #94a3b8; margin: 0;">© Vanquish Therapies Ltd. All rights reserved.</p>
  </div>
</div>',
                'placeholders' => ['first_name', 'portal_link', 'email', 'temporary_password']
            ],
            'trainee_placement_rejection' => [
                'subject' => 'Update on your application',
                'body' => '<h1>Application Update</h1><p>Hello {{first_name}},</p><p>Thank you for your interest in joining Vanquish Therapies and for taking the time to attend the interview process.</p><p>After careful consideration, we regret to inform you that we will not be moving forward with your placement at this time.</p><p>We wish you the very best in your future clinical career.</p>',
                'placeholders' => ['first_name']
            ],
            'trainee_interview_not_attended' => [
                'subject' => 'Interview Not Attended – Vanquish Therapies',
                'body' => '
<div style="font-family: Arial, sans-serif; color: #333; line-height: 1.6;">
    <p>Dear {{first_name}},</p>
    <p>We are sorry you were not able to attend your interview, to comply with GPDR regulations, your information has been deleted, we wish you all the best in your journey to becoming a qualified counsellor.</p>
    <p>Kind regards,</p>
    <p><strong>Nicole McLaren</strong><br>
    SAR and Compliance Team | Vanquish Therapies<br>
    Integrative Life Coaching &amp; Counselling<br>
    E: <a href="mailto:sar.compliance@vanquishtherapies.co.uk">sar.compliance@vanquishtherapies.co.uk</a><br>
    W: <a href="http://www.vanquishtherapies.co.uk/">www.vanquishtherapies.co.uk</a></p>
</div>',
                'placeholders' => ['first_name']
            ],
            'trainee_induction_not_attended' => [
                'subject' => 'Induction Not Attended – Vanquish Therapies',
                'body' => '
<div style="font-family: Arial, sans-serif; color: #333; line-height: 1.6;">
    <p>Dear {{first_name}},</p>
    <p>We are sorry you were not able to attend your induction, to comply with GPDR regulations, your information has been deleted, we wish you all the best in your journey to becoming a qualified counsellor.</p>
    <p>Kind regards,</p>
    <p><strong>Nicole McLaren</strong><br>
    SAR and Compliance Team | Vanquish Therapies<br>
    Integrative Life Coaching &amp; Counselling<br>
    E: <a href="mailto:sar.compliance@vanquishtherapies.co.uk">sar.compliance@vanquishtherapies.co.uk</a><br>
    W: <a href="http://www.vanquishtherapies.co.uk/">www.vanquishtherapies.co.uk</a></p>
</div>',
                'placeholders' => ['first_name']
            ],
        ];
    }
}
