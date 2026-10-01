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
        Schema::table('tc_intake_forms', function (Blueprint $table) {
            $table->string('fitness_to_practice')->nullable()->after('additional_info');
            $table->string('qualifications')->nullable()->after('fitness_to_practice');
            $table->string('dbs_certificate')->nullable()->after('qualifications');
            $table->string('cv')->nullable()->after('dbs_certificate');
            $table->string('valid_id')->nullable()->after('cv');
            $table->string('insurance_certificate')->nullable()->after('valid_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tc_intake_forms', function (Blueprint $table) {
            $table->dropColumn([
                'fitness_to_practice',
                'qualifications',
                'dbs_certificate',
                'cv',
                'valid_id',
                'insurance_certificate',
            ]);
        });
    }
};
