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
        Schema::create('clients', function (Blueprint $table) {
            $table->id();
            $table->string('client_id')->unique();
            $table->string('name');
            $table->integer('age')->nullable();
            $table->string('email')->unique();
            $table->string('phone')->nullable();
            $table->text('address')->nullable();
            $table->string('postcode')->nullable();
            $table->string('gender')->nullable();
            $table->string('ethnicity')->nullable();
            $table->string('sexual_orientation')->nullable();
            $table->enum('stage', [
                'Application',
                'Consultation Booked',
                'Consultation Completed',
                'Pending Match',
                'Matched',
                'Agreement Pending',
                'Active Therapy',
                'Completed'
            ])->default('Application');
            $table->enum('status', ['active', 'urgent', 'stuck', 'archived'])->default('active');
            $table->enum('service_type', ['Low Cost', 'Mid Range', 'High Range'])->nullable();
            $table->json('primary_issues')->nullable();
            $table->text('additional_details')->nullable();
            $table->string('medication')->nullable();
            $table->string('disabilities')->nullable();
            $table->text('risk_flags')->nullable();
            $table->string('substance_misuse')->nullable();
            $table->json('availability')->nullable();
            $table->date('submitted_date')->nullable();
            $table->date('start_date')->nullable();
            $table->integer('sessions_completed')->default(0);
            $table->unsignedBigInteger('matched_tc_id')->nullable();
            $table->date('matched_date')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('clients');
    }
};
