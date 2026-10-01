<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Changes VARCHAR(255) columns that can hold long values to TEXT:
     * - theoretical_approach: multi-select therapies, can exceed 255 chars
     * - psg_day_preference: multi-select days, can exceed 255 chars
     * - doc_* URL columns: storage URLs can exceed 255 chars
     */
    public function up(): void
    {
        Schema::table('trainee_applications', function (Blueprint $table) {
            $table->text('theoretical_approach')->nullable()->change();
            $table->text('psg_day_preference')->nullable()->change();
            $table->text('doc_fitness_to_practise')->nullable()->change();
            $table->text('doc_prior_qualifications')->nullable()->change();
            $table->text('doc_dbs_certificate')->nullable()->change();
            $table->text('doc_cv')->nullable()->change();
            $table->text('doc_valid_id')->nullable()->change();
            $table->text('doc_indemnity_insurance')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('trainee_applications', function (Blueprint $table) {
            $table->string('theoretical_approach')->nullable()->change();
            $table->string('psg_day_preference')->nullable()->change();
            $table->string('doc_fitness_to_practise')->nullable()->change();
            $table->string('doc_prior_qualifications')->nullable()->change();
            $table->string('doc_dbs_certificate')->nullable()->change();
            $table->string('doc_cv')->nullable()->change();
            $table->string('doc_valid_id')->nullable()->change();
            $table->string('doc_indemnity_insurance')->nullable()->change();
        });
    }
};
