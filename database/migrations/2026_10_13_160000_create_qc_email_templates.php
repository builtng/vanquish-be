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
        $pairs = [
            'qc_stage_three_invite' => 'trainee_stage_three_invite',
            'qc_interview_confirmed' => 'trainee_interview_confirmed',
            'qc_interview_reminder' => 'trainee_interview_reminder',
            'qc_interview_not_attended' => 'trainee_interview_not_attended',
            'qc_placement_acceptance' => 'trainee_placement_acceptance',
            'qc_onboarding_paperwork' => 'trainee_onboarding_paperwork',
            'qc_placement_rejection' => 'trainee_placement_rejection',
            'qc_induction_completed' => 'trainee_induction_completed',
            'qc_induction_not_attended' => 'trainee_induction_not_attended',
        ];

        foreach ($pairs as $qcType => $traineeType) {
            $exists = DB::table('email_templates')->where('type', $qcType)->exists();
            if (!$exists) {
                $source = DB::table('email_templates')->where('type', $traineeType)->first();
                if ($source) {
                    DB::table('email_templates')->insert([
                        'type' => $qcType,
                        'subject' => $source->subject,
                        'body' => $source->body,
                        'placeholders' => $source->placeholders,
                        'version' => 1,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('email_templates')
            ->whereIn('type', [
                'qc_stage_three_invite',
                'qc_interview_confirmed',
                'qc_interview_reminder',
                'qc_interview_not_attended',
                'qc_placement_acceptance',
                'qc_onboarding_paperwork',
                'qc_placement_rejection',
                'qc_induction_completed',
                'qc_induction_not_attended',
            ])
            ->delete();
    }
};
