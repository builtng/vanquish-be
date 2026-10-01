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
            $table->string('video_url')->nullable()->after('assessment_answers');
            $table->string('zoom_link')->nullable()->after('video_url');
            $table->json('interview_data')->nullable()->after('zoom_link');
            // Change status to support more values if needed, but string is fine.
        });

        Schema::table('consultation_slots', function (Blueprint $table) {
            $table->string('type')->default('consultation')->index()->after('status'); // consultation, placement_interview
            $table->string('zoom_link')->nullable()->after('type');
            $table->string('host_name')->nullable()->after('zoom_link');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('consultation_slots', function (Blueprint $table) {
            $table->dropColumn(['type', 'zoom_link', 'host_name']);
        });

        Schema::table('trainee_applications', function (Blueprint $table) {
            $table->dropColumn(['video_url', 'zoom_link', 'interview_data']);
        });
    }
};
