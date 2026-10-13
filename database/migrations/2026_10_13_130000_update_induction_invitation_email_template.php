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
        $body = '<div style="font-family: Arial, sans-serif; max-width: 640px; margin: 0 auto; color: #333;"><div style="background: linear-gradient(135deg, #6f1d56 0%, #9b2c7e 100%); padding: 36px 32px; border-radius: 12px 12px 0 0; text-align: center;"><h1 style="color: white; margin: 0; font-size: 24px;">Induction Invitation</h1><p style="color: rgba(255,255,255,0.85); margin: 8px 0 0; font-size: 14px;">Vanquish Therapies</p></div><div style="background: #ffffff; padding: 32px; border: 1px solid #e8e8e8; border-top: none;"><p style="font-size: 16px; margin-top: 0;">Hi <strong>{{tc_name}}</strong>,</p><p>You have been invited to an induction session on {{induction_date}}, {{induction_start_time}} to {{induction_end_time}} (UK time).</p><div style="background: #f9f4f8; border-left: 4px solid #6f1d56; padding: 16px 20px; border-radius: 0 8px 8px 0; margin: 24px 0;"><p style="margin: 0; font-size: 14px;"><strong>Date &amp; Time:</strong> {{induction_date}}, {{induction_start_time}} to {{induction_end_time}} (UK time)</p><p style="margin: 6px 0 0; font-size: 14px;"><strong>Location:</strong> {{location}}</p><p style="margin: 6px 0 0; font-size: 14px;"><strong>Notes:</strong> {{notes}}</p></div><p style="margin: 24px 0;"><a href="{{acceptance_url}}" style="display:inline-block;padding:12px 24px;background-color:green;color:white;text-decoration:none;border-radius:8px;font-weight:bold;margin-right:10px;">Accept Invitation</a> <a href="{{decline_url}}" style="display:inline-block;padding:12px 24px;background-color:red;color:white;text-decoration:none;border-radius:8px;font-weight:bold;">Decline</a></p><p style="font-size: 15px; font-weight: bold; color: #6f1d56; margin-top: 24px;">Vanquish Therapies Admin</p></div><div style="background: #f5f5f5; padding: 16px 32px; text-align: center; border: 1px solid #e8e8e8; border-top: none; border-radius: 0 0 12px 12px;"><p style="font-size: 11px; color: #aaa; margin: 0;">© Vanquish Therapies Ltd. All rights reserved.</p></div></div>';

        $placeholders = json_encode([
            'tc_name',
            'induction_date',
            'induction_start_time',
            'induction_end_time',
            'start_time',
            'end_time',
            'location',
            'notes',
            'acceptance_url',
            'decline_url',
        ]);

        $exists = DB::table('email_templates')->where('type', 'induction_invitation')->exists();

        if ($exists) {
            DB::table('email_templates')
                ->where('type', 'induction_invitation')
                ->update([
                    'body' => $body,
                    'placeholders' => $placeholders,
                    'updated_at' => now(),
                ]);
        } else {
            DB::table('email_templates')->insert([
                'type' => 'induction_invitation',
                'subject' => 'Induction Session Invitation',
                'body' => $body,
                'placeholders' => $placeholders,
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
    }
};
