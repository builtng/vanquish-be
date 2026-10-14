<?php

use Illuminate\Database\Migrations\Migration;
use App\Models\EmailTemplate;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $template = EmailTemplate::where('type', 'trainee_stage_two_invite')->first();
        if ($template && !empty($template->body)) {
            $updatedBody = str_replace(
                [
                    '5 structured questions',
                    'five structured questions',
                    '5 simple questions',
                    'five simple questions',
                    '5 questions',
                    'five questions',
                ],
                'a few questions',
                $template->body
            );
            $template->update(['body' => $updatedBody]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $template = EmailTemplate::where('type', 'trainee_stage_two_invite')->first();
        if ($template && !empty($template->body)) {
            $updatedBody = str_replace('a few questions', 'five simple questions', $template->body);
            $template->update(['body' => $updatedBody]);
        }
    }
};
