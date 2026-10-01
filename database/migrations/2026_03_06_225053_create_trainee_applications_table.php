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
        Schema::create('trainee_applications', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('first_name');
            $table->string('last_name');
            $table->string('email')->index();
            $table->string('phone')->nullable();
            $table->text('address')->nullable();
            $table->string('institution')->nullable();
            $table->string('course_name')->nullable();
            $table->string('course_duration')->nullable();
            $table->text('experience_background')->nullable();
            $table->json('assessment_answers')->nullable();
            $table->string('source')->default('internal_form'); // jotform, internal_form
            $table->string('status')->default('pending'); // pending, reviewed, approved, rejected
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('trainee_application_settings', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->json('value')->nullable();
            $table->timestamps();
        });

        // Seed initial orientation/priority questions
        // They can be managed via UI later
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('trainee_application_settings');
        Schema::dropIfExists('trainee_applications');
    }
};
