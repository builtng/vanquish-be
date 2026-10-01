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
            // Store JotForm intake form data (questions 6, 9, 16, 22, 24, 34)
            if (!Schema::hasColumn('clients', 'jotform_intake_data')) {
                $table->json('jotform_intake_data')->nullable();
            }
            if (!Schema::hasColumn('clients', 'jotform_intake_submission_id')) {
                $table->string('jotform_intake_submission_id')->nullable();
            }
            if (!Schema::hasColumn('clients', 'jotform_intake_completed_at')) {
                $table->timestamp('jotform_intake_completed_at')->nullable();
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            $table->dropColumn([
                'jotform_intake_data',
                'jotform_intake_submission_id',
                'jotform_intake_completed_at',
            ]);
        });
    }
};
