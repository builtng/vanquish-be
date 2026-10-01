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
        Schema::table('trainee_applications', function (Blueprint $table) {
            $table->json('onboarding_data')->nullable()->after('interview_data');
            $table->boolean('portal_access_granted')->default(false)->after('onboarding_data');
        });
    }

    public function down(): void
    {
        Schema::table('trainee_applications', function (Blueprint $table) {
            $table->dropColumn(['onboarding_data', 'portal_access_granted']);
        });
    }
};
