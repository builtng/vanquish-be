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
            if (!Schema::hasColumn('qc_applications', 'previous_vanquish_work')) {
                $table->string('previous_vanquish_work')->nullable()->after('phone');
            }
            if (!Schema::hasColumn('qc_applications', 'areas_to_improve')) {
                $table->text('areas_to_improve')->nullable()->after('previous_vanquish_work');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('qc_applications', function (Blueprint $table) {
            if (Schema::hasColumn('qc_applications', 'areas_to_improve')) {
                $table->dropColumn('areas_to_improve');
            }
            if (Schema::hasColumn('qc_applications', 'previous_vanquish_work')) {
                $table->dropColumn('previous_vanquish_work');
            }
        });
    }
};
