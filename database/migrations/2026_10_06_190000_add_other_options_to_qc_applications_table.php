<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('qc_applications', function (Blueprint $table) {
            if (!Schema::hasColumn('qc_applications', 'other_modalities')) {
                $table->text('other_modalities')->nullable()->after('areas_to_improve');
            }
            if (!Schema::hasColumn('qc_applications', 'other_experience_areas')) {
                $table->text('other_experience_areas')->nullable()->after('other_modalities');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('qc_applications', function (Blueprint $table) {
            if (Schema::hasColumn('qc_applications', 'other_experience_areas')) {
                $table->dropColumn('other_experience_areas');
            }
            if (Schema::hasColumn('qc_applications', 'other_modalities')) {
                $table->dropColumn('other_modalities');
            }
        });
    }
};
