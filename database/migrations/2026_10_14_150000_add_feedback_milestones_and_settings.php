<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            if (!Schema::hasColumn('clients', 'last_feedback_milestone')) {
                $table->integer('last_feedback_milestone')->nullable()->after('last_feedback_date');
            }
            if (!Schema::hasColumn('clients', 'feedback_milestones_sent')) {
                $table->json('feedback_milestones_sent')->nullable()->after('last_feedback_milestone');
            }
        });

        // Insert default company settings for feedback form if they don't exist
        $defaults = [
            'feedback_form_enabled' => '1',
            'feedback_form_session_number' => '4',
            'feedback_form_url' => '',
        ];

        foreach ($defaults as $key => $val) {
            DB::table('company_settings')->updateOrInsert(
                ['key' => $key],
                ['value' => $val, 'updated_at' => now()]
            );
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            if (Schema::hasColumn('clients', 'feedback_milestones_sent')) {
                $table->dropColumn('feedback_milestones_sent');
            }
            if (Schema::hasColumn('clients', 'last_feedback_milestone')) {
                $table->dropColumn('last_feedback_milestone');
            }
        });

        DB::table('company_settings')
            ->whereIn('key', ['feedback_form_enabled', 'feedback_form_session_number', 'feedback_form_url'])
            ->delete();
    }
};
