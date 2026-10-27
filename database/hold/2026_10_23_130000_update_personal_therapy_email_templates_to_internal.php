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
        // Update trainee_placement_acceptance template if exists
        $acceptance = DB::table('email_templates')->where('type', 'trainee_placement_acceptance')->first();
        if ($acceptance) {
            $updatedBody = str_replace(
                'https://form.jotform.com/241002800146035',
                '{{therapy_form_url}}',
                $acceptance->body
            );
            DB::table('email_templates')->where('type', 'trainee_placement_acceptance')->update([
                'body' => $updatedBody,
                'updated_at' => now(),
            ]);
        }

        // Update trainee_onboarding_paperwork template if exists
        $paperwork = DB::table('email_templates')->where('type', 'trainee_onboarding_paperwork')->first();
        if ($paperwork) {
            $updatedBody = str_replace(
                'https://form.jotform.com/241002800146035',
                '{{therapy_form_url}}',
                $paperwork->body
            );
            DB::table('email_templates')->where('type', 'trainee_onboarding_paperwork')->update([
                'body' => $updatedBody,
                'updated_at' => now(),
            ]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // No-op
    }
};
