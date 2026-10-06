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
        if (Schema::hasTable('qc_applications')) {
            Schema::table('qc_applications', function (Blueprint $table) {
                if (!Schema::hasColumn('qc_applications', 'suggested_training_counsellor_id')) {
                    $table->unsignedBigInteger('suggested_training_counsellor_id')->nullable()->index()->after('training_counsellor_id');
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('qc_applications')) {
            Schema::table('qc_applications', function (Blueprint $table) {
                if (Schema::hasColumn('qc_applications', 'suggested_training_counsellor_id')) {
                    $table->dropColumn('suggested_training_counsellor_id');
                }
            });
        }
    }
};
