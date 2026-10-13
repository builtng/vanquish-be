<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('qc_applications', function (Blueprint $table) {
            if (!Schema::hasColumn('qc_applications', 'interview_data')) {
                $table->json('interview_data')->nullable()->after('notes');
            }
            if (!Schema::hasColumn('qc_applications', 'onboarding_data')) {
                $table->json('onboarding_data')->nullable()->after('interview_data');
            }
            if (!Schema::hasColumn('qc_applications', 'induction_date')) {
                $table->string('induction_date')->nullable()->after('onboarding_data');
            }
            if (!Schema::hasColumn('qc_applications', 'placement_response_token')) {
                $table->string('placement_response_token')->nullable()->after('induction_date');
            }
        });
    }

    public function down(): void
    {
        Schema::table('qc_applications', function (Blueprint $table) {
            $table->dropColumn([
                'interview_data',
                'onboarding_data',
                'induction_date',
                'placement_response_token',
            ]);
        });
    }
};
