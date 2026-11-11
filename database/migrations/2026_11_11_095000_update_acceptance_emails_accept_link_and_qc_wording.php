<?php

use App\Support\AcceptanceEmailContent;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Saved email templates override the built-in ones, so update them too:
 * - Trainee "Placement Acceptance" gets the 72-hour Accept button.
 * - Qualified counsellor acceptance / paperwork / rejection emails no
 *   longer say "placement".
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('email_templates')) {
            return;
        }

        $trainee = DB::table('email_templates')->where('type', 'trainee_placement_acceptance')->first();
        if ($trainee && !str_contains((string) $trainee->body, '{{acceptance_link}}')) {
            DB::table('email_templates')->where('id', $trainee->id)->update([
                'body' => AcceptanceEmailContent::withAcceptBlock((string) $trainee->body),
                'placeholders' => $this->mergePlaceholders($trainee->placeholders, ['acceptance_link', 'accept_by']),
                'updated_at' => now(),
            ]);
        }

        $qc = DB::table('email_templates')->where('type', 'qc_placement_acceptance')->first();
        if ($qc && (stripos((string) $qc->body, 'placement') !== false || !str_contains((string) $qc->body, '{{acceptance_link}}'))) {
            DB::table('email_templates')->where('id', $qc->id)->update([
                'subject' => AcceptanceEmailContent::qcAcceptanceSubject(),
                'body' => AcceptanceEmailContent::qcAcceptanceBody(),
                'placeholders' => json_encode(['first_name', 'induction_date', 'induction_zoom_link', 'acceptance_link', 'accept_by']),
                'updated_at' => now(),
            ]);
        }

        foreach (['qc_onboarding_paperwork', 'qc_placement_rejection'] as $type) {
            $row = DB::table('email_templates')->where('type', $type)->first();
            if ($row) {
                DB::table('email_templates')->where('id', $row->id)->update([
                    'subject' => AcceptanceEmailContent::qcWording((string) $row->subject),
                    'body' => AcceptanceEmailContent::qcWording((string) $row->body),
                    'updated_at' => now(),
                ]);
            }
        }
    }

    public function down(): void
    {
        // Content update only.
    }

    private function mergePlaceholders($existing, array $add): string
    {
        $list = is_string($existing) ? (json_decode($existing, true) ?: []) : (array) $existing;
        return json_encode(array_values(array_unique(array_merge($list, $add))));
    }
};
