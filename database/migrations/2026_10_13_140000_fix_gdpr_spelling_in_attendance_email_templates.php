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
        $templates = DB::table('email_templates')
            ->whereIn('type', ['trainee_interview_not_attended', 'trainee_induction_not_attended'])
            ->get();

        foreach ($templates as $template) {
            if (str_contains($template->body, 'GPDR')) {
                DB::table('email_templates')
                    ->where('id', $template->id)
                    ->update([
                        'body' => str_replace('GPDR', 'GDPR', $template->body),
                        'updated_at' => now(),
                    ]);
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $templates = DB::table('email_templates')
            ->whereIn('type', ['trainee_interview_not_attended', 'trainee_induction_not_attended'])
            ->get();

        foreach ($templates as $template) {
            if (str_contains($template->body, 'GDPR')) {
                DB::table('email_templates')
                    ->where('id', $template->id)
                    ->update([
                        'body' => str_replace('GDPR', 'GPDR', $template->body),
                        'updated_at' => now(),
                    ]);
            }
        }
    }
};
