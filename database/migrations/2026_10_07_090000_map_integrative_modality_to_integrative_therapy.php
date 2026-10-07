<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (Schema::hasTable('training_counsellors') && Schema::hasColumn('training_counsellors', 'modality')) {
            DB::table('training_counsellors')
                ->where('modality', 'Integrative')
                ->orWhere('modality', 'Integrative Counselling and Therapy')
                ->update(['modality' => 'Integrative Therapy']);
        }

        if (Schema::hasTable('clients')) {
            $col = Schema::hasColumn('clients', 'preferred_modality') ? 'preferred_modality' : (Schema::hasColumn('clients', 'modality') ? 'modality' : null);
            if ($col) {
                DB::table('clients')
                    ->where($col, 'Integrative')
                    ->orWhere($col, 'Integrative Counselling and Therapy')
                    ->update([$col => 'Integrative Therapy']);
            }
        }

        if (Schema::hasTable('consultations') && Schema::hasColumn('consultations', 'recommended_modality')) {
            DB::table('consultations')
                ->where('recommended_modality', 'Integrative')
                ->orWhere('recommended_modality', 'Integrative Counselling and Therapy')
                ->update(['recommended_modality' => 'Integrative Therapy']);
        }

        if (Schema::hasTable('tc_intake_forms') && Schema::hasColumn('tc_intake_forms', 'modality')) {
            DB::table('tc_intake_forms')
                ->where('modality', 'Integrative')
                ->orWhere('modality', 'Integrative Counselling and Therapy')
                ->update(['modality' => 'Integrative Therapy']);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // No action needed for down
    }
};
