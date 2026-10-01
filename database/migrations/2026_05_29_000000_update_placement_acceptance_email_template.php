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
        $body = '
<div style="font-family: Arial, sans-serif; max-width: 640px; margin: 0 auto; color: #333;">
  <div style="background: linear-gradient(135deg, #6f1d56 0%, #9b2c7e 100%); padding: 40px 32px; border-radius: 12px 12px 0 0; text-align: center;">
    <div style="font-size: 48px; margin-bottom: 12px;">🎊</div>
    <h1 style="color: white; margin: 0; font-size: 26px;">Placement Acceptance</h1>
    <p style="color: rgba(255,255,255,0.8); margin: 8px 0 0; font-size: 15px;">Vanquish Therapies — Trainee Onboarding</p>
  </div>
  <div style="background: #ffffff; padding: 32px; border: 1px solid #e2e8f0; border-top: none; line-height: 1.6;">
    Dear {{first_name}},<br><br>
    We hope this email finds you well.<br><br>
    Congratulations! We are delighted to inform you that you have been successful in your interview and accepted onto our placement programme as a Trainee Counsellor. We are confident you will make a valuable addition to our team.<br><br>
    Our upcoming online Induction is scheduled for Monday, 19th January 2026 at 10am. Our online induction is a crucial step in familiarising you with our operational standards and procedures. Your attendance is mandatory, as it will set the groundwork for a successful placement experience with us.<br><br>
    <strong>Induction</strong><br>
    <a href="https://zoom.us/j/2161245208?pwd=NVFQbkJPRUFFMjkwMG9mTnZ0MTJRdz09" style="color:#6f1d56;text-decoration:underline;">https://zoom.us/j/2161245208?pwd=NVFQbkJPRUFFMjkwMG9mTnZ0MTJRdz09</a><br><br>
    Meeting ID: 216 124 5208<br>
    Passcode: 3Vpfg8<br><br>
    However, please note – The placement offer will be withdrawn if ethical and professional standards are not maintained within our Practice.<br><br>
    In the meantime, we would like to immediately begin the placement paperwork, please send us the following:<br><br>
    • <strong>Agreements/Contracts:</strong> Please send us a signed copy of the 4-way agreement (attached to this email). Ensure you sign and date it, and have your Tutor and Clinical Supervisor do same. Please ensure all signatures are on the same page. If signatures are on separate pages, we will have to ask you to do it again.<br><br>
    • <strong>Personal Therapy:</strong> We require you to confirm that you are currently in personal therapy. Please confirm your details by clicking the link here:<br>
    <a href="{{therapy_form_url}}" style="display:inline-block;padding:10px 20px;background:#6f1d56;color:white;text-decoration:none;border-radius:6px;font-weight:bold;font-size:13px;" target="_blank">Complete Therapy Form</a><br><br>
    Once again, congratulations! We look forward to welcoming you to our team.<br><br>
    Warmest regards,<br><br>
    Clinical & Operations Director<br>
    Vanquish Therapies Ltd
  </div>
  <div style="background: #f8fafc; padding: 16px 32px; border-radius: 0 0 12px 12px; border: 1px solid #e2e8f0; border-top: none; text-align: center;">
    <p style="font-size: 11px; color: #94a3b8; margin: 0;">© Vanquish Therapies Ltd. All rights reserved.</p>
  </div>
</div>';

        // Check if template exists. If it exists, update it. If not, insert it.
        $exists = DB::table('email_templates')->where('type', 'trainee_placement_acceptance')->exists();

        if ($exists) {
            DB::table('email_templates')
                ->where('type', 'trainee_placement_acceptance')
                ->update([
                    'subject' => 'Placement Acceptance',
                    'body' => $body,
                    'placeholders' => json_encode(['first_name', 'therapy_form_url']),
                    'updated_at' => now(),
                ]);
        } else {
            DB::table('email_templates')->insert([
                'type' => 'trainee_placement_acceptance',
                'subject' => 'Placement Acceptance',
                'body' => $body,
                'placeholders' => json_encode(['first_name', 'therapy_form_url']),
                'version' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // No-op or restore defaults if necessary
    }
};
