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
            // GP Information
            $table->string('gp_name')->nullable()->after('emergency_contact_relationship');
            $table->string('gp_practice_name')->nullable()->after('gp_name');
            $table->string('gp_practice_phone')->nullable()->after('gp_practice_name');

            // Current Address
            $table->text('current_address')->nullable()->after('gp_practice_phone');

            // Case Study Consent (for low-cost clients)
            $table->enum('case_study_consent', ['yes', 'no'])->nullable()->after('current_address');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            $table->dropColumn([
                'gp_name',
                'gp_practice_name',
                'gp_practice_phone',
                'current_address',
                'case_study_consent',
            ]);
        });
    }
};
