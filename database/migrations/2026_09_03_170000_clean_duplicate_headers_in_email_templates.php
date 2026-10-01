<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 1. trainee_application_received (Image 1)
        $appReceivedBody = '<h1 style="margin: 0 0 20px 0; color: #1e293b; font-size: 24px; font-weight: 700; line-height: 1.3;">Application Received</h1>
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
<p style="font-size: 15px; font-weight: bold; color: #6f1d56; margin-top: 24px;">The Compliance Team<br><span style="font-weight: normal; color: #777777; font-size: 13px;">Vanquish Therapies</span></p>';

        DB::table('email_templates')
            ->where('type', 'trainee_application_received')
            ->update([
                'subject' => 'Placement Application Received',
                'body' => $appReceivedBody,
                'placeholders' => json_encode(['first_name', 'email']),
                'updated_at' => now(),
            ]);

        // 2. trainee_placement_acceptance (Image 2)
        $placementAcceptBody = '<h1 style="margin: 0 0 20px 0; color: #1e293b; font-size: 24px; font-weight: 700; line-height: 1.3;">Placement Acceptance</h1>
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
<p style="font-size: 15px; font-weight: bold; color: #6f1d56; margin-top: 24px;">Warm congratulations once again, and welcome to the team!<br><br><span style="font-weight: normal; color: #777777; font-size: 13px;">The Compliance Team<br>Vanquish Therapies</span></p>';

        DB::table('email_templates')
            ->where('type', 'trainee_placement_acceptance')
            ->update([
                'subject' => 'Placement Acceptance',
                'body' => $placementAcceptBody,
                'placeholders' => json_encode(['first_name', 'induction_date', 'induction_zoom_link', 'therapy_form_url']),
                'updated_at' => now(),
            ]);

        // 3. client_matched (Image 3)
        $clientMatchedBody = '<h1 style="margin: 0 0 20px 0; color: #1e293b; font-size: 24px; font-weight: 700; line-height: 1.3;">Practitioner Match</h1>
<p style="font-size: 16px; margin: 0 0 16px 0; color: #333333;">Hi <strong>{{client_name}}</strong>,</p>
<p style="margin: 0 0 16px 0; color: #4b5563; font-size: 15px; line-height: 1.6;">Great news! You have been matched with <strong>{{tc_name}}</strong>.</p>
<p style="margin: 0 0 24px 0; color: #4b5563; font-size: 15px; line-height: 1.6;">Your service agreement is already signed. You can now select your session slots and book your therapy sessions directly.</p>
<p style="margin: 24px 0;"><a href="{{booking_link}}" style="display:inline-block;padding:12px 24px;background-color:#6f1d56;color:white;text-decoration:none;border-radius:8px;font-weight:bold;">Book Therapy Sessions</a></p>
<p style="font-size: 15px; font-weight: bold; color: #6f1d56; margin-top: 24px;">Warm regards,<br>The Vanquish Therapies Team</p>';

        DB::table('email_templates')
            ->where('type', 'client_matched')
            ->update([
                'subject' => 'You have been matched with a practitioner',
                'body' => $clientMatchedBody,
                'placeholders' => json_encode(['client_name', 'tc_name', 'email', 'booking_link']),
                'updated_at' => now(),
            ]);

        // 3b. match_assigned
        $matchAssignedBody = '<h1 style="margin: 0 0 20px 0; color: #1e293b; font-size: 24px; font-weight: 700; line-height: 1.3;">Practitioner Match</h1>
<p style="font-size: 16px; margin: 0 0 16px 0; color: #333333;">Hi <strong>{{client_name}}</strong>,</p>
<p style="margin: 0 0 16px 0; color: #4b5563; font-size: 15px; line-height: 1.6;">Great news! You have been matched with <strong>{{tc_name}}</strong>.</p>
<p style="margin: 0 0 24px 0; color: #4b5563; font-size: 15px; line-height: 1.6;">The next step is to sign your service agreement. Please use the link below to review and sign your agreement. Once signed, you will be able to select your session slots and book your sessions directly.</p>
<p style="margin: 24px 0;"><a href="{{agreement_url}}" style="display:inline-block;padding:12px 24px;background-color:#6f1d56;color:white;text-decoration:none;border-radius:8px;font-weight:bold;">Sign Service Agreement</a></p>
<p style="font-size: 15px; font-weight: bold; color: #6f1d56; margin-top: 24px;">Warm regards,<br>The Vanquish Therapies Team</p>';

        DB::table('email_templates')
            ->where('type', 'match_assigned')
            ->update([
                'subject' => 'You have been matched with a practitioner',
                'body' => $matchAssignedBody,
                'placeholders' => json_encode(['client_name', 'tc_name', 'email', 'agreement_url']),
                'updated_at' => now(),
            ]);

        // 4. trainee_video_interview_received (Clean reference - Image 4)
        $videoReceivedBody = '<h1 style="margin: 0 0 20px 0; color: #1e293b; font-size: 24px; font-weight: 700; line-height: 1.3;">Video Interview Received</h1>
<p style="font-size: 16px; margin: 0 0 16px 0; color: #333333;">Dear {{first_name}},</p>
<p style="margin: 0 0 16px 0; color: #4b5563; font-size: 15px; line-height: 1.6;">Thank you for completing your Stage 2 video interview. We have successfully received all of your responses.</p>
<p style="margin: 0 0 24px 0; color: #4b5563; font-size: 15px; line-height: 1.6;">A member of our team will review your video interview — this typically takes 3–5 working days. If successful, you will receive a Stage 3 invitation to book a face-to-face interview.</p>
<p style="font-size: 15px; font-weight: bold; color: #6f1d56; margin-top: 24px;">The Compliance Team<br><span style="font-weight: normal; color: #777777; font-size: 13px;">Vanquish Therapies</span></p>';

        DB::table('email_templates')
            ->where('type', 'trainee_video_interview_received')
            ->update([
                'subject' => 'Video Interview Received – Next Steps',
                'body' => $videoReceivedBody,
                'placeholders' => json_encode(['first_name']),
                'updated_at' => now(),
            ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Revert not required
    }
};
