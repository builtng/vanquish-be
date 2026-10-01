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
        $body = '<p style="margin: 0 0 16px 0; font-size: 16px; line-height: 1.6; color: #333333;">Hi {{first_name}}.</p><p style="margin: 0 0 24px 0; font-size: 16px; line-height: 1.6; color: #333333;">You have a message in your portal to attend to, kindly.</p><div style="text-align: center; margin: 32px 0;"><a href="{{login_url}}" style="display: inline-block; background-color: #6f1d56; color: #ffffff; padding: 14px 32px; text-decoration: none; border-radius: 8px; font-weight: 600; font-size: 16px; box-shadow: 0 2px 4px rgba(111, 29, 86, 0.2);">Log in to Portal</a></div>';

        $exists = DB::table('email_templates')->where('type', 'tc_match_notification')->exists();

        if ($exists) {
            DB::table('email_templates')
                ->where('type', 'tc_match_notification')
                ->update([
                    'subject' => 'You have a message in your portal to attend to',
                    'body' => $body,
                    'placeholders' => json_encode(['first_name', 'login_url', 'Login Button']),
                    'updated_at' => now(),
                ]);
        } else {
            DB::table('email_templates')->insert([
                'type' => 'tc_match_notification',
                'subject' => 'You have a message in your portal to attend to',
                'body' => $body,
                'placeholders' => json_encode(['first_name', 'login_url', 'Login Button']),
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
        // Revert to prior template if needed
        DB::table('email_templates')
            ->where('type', 'tc_match_notification')
            ->update([
                'subject' => 'New Client Match - Action Required',
                'updated_at' => now(),
            ]);
    }
};
