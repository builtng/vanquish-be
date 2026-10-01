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
        Schema::table('clients', function (Blueprint $table) {
            if (!Schema::hasColumn('clients', 'gender_preference')) {
                $table->string('gender_preference')->default('No preference')->after('sexual_orientation');
            }
            if (!Schema::hasColumn('clients', 'age_preference')) {
                $table->string('age_preference')->default('No preference')->after('gender_preference');
            }
            if (!Schema::hasColumn('clients', 'ethnicity_preference')) {
                $table->string('ethnicity_preference')->default('No preference')->after('age_preference');
            }
            if (!Schema::hasColumn('clients', 'orientation_preference')) {
                $table->string('orientation_preference')->default('No preference')->after('ethnicity_preference');
            }
            if (!Schema::hasColumn('clients', 'emergency_contact_email')) {
                $table->string('emergency_contact_email')->nullable()->after('emergency_contact_phone');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            $columnsToDrop = [];
            foreach (['gender_preference', 'age_preference', 'ethnicity_preference', 'orientation_preference', 'emergency_contact_email'] as $col) {
                if (Schema::hasColumn('clients', $col)) {
                    $columnsToDrop[] = $col;
                }
            }
            if (!empty($columnsToDrop)) {
                $table->dropColumn($columnsToDrop);
            }
        });
    }
};
