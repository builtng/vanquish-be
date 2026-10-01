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
        Schema::create('client_intake_forms', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->nullable()->constrained('clients')->nullOnDelete();
            $table->string('first_name')->nullable();
            $table->string('last_name')->nullable();
            $table->string('email');
            $table->string('phone')->nullable();
            $table->integer('age')->nullable();
            $table->boolean('voicemail_ok')->default(false);
            $table->boolean('currently_in_therapy')->default(false);
            $table->string('gender')->nullable();
            $table->string('ethnicity')->nullable();
            $table->string('sexual_orientation')->nullable();
            $table->string('service_type')->nullable();
            $table->boolean('on_medication')->default(false);
            $table->text('medication_details')->nullable();
            $table->text('disabilities')->nullable();
            $table->json('support_areas')->nullable();
            $table->text('concerns_details')->nullable();
            $table->text('risk_issues')->nullable();
            $table->json('availability')->nullable();
            $table->string('gender_preference')->default('No preference');
            $table->string('age_preference')->default('No preference');
            $table->string('ethnicity_preference')->default('No preference');
            $table->string('orientation_preference')->default('No preference');
            $table->string('hear_about_us')->nullable();
            $table->text('referral_reason')->nullable();
            $table->string('referrer_name')->nullable();
            $table->string('referrer_phone')->nullable();
            $table->string('referrer_org')->nullable();
            $table->string('referrer_email')->nullable();
            $table->string('card_name')->nullable();
            $table->string('card_number')->nullable();
            $table->string('expiry_date')->nullable();
            $table->string('cvv')->nullable();
            $table->string('postcode')->nullable();
            $table->boolean('terms_accepted')->default(false);
            $table->enum('status', ['draft', 'submitted', 'processed'])->default('draft');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('client_intake_forms');
    }
};
