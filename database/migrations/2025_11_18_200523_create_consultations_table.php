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
        Schema::create('consultations', function (Blueprint $table) {
            $table->id();
            $table->string('consultation_id')->unique();
            $table->foreignId('client_id')->constrained('clients')->cascadeOnDelete();
            $table->foreignId('tc_id')->nullable()->constrained('training_counsellors')->nullOnDelete();
            $table->dateTime('scheduled_at');
            $table->dateTime('completed_at')->nullable();
            $table->enum('status', ['scheduled', 'completed', 'cancelled', 'no_show'])->default('scheduled');
            $table->integer('duration_minutes')->nullable();
            $table->text('notes')->nullable();
            $table->enum('outcome', ['approved', 'not_approved', 'pending'])->nullable();
            $table->string('recommended_service')->nullable();
            $table->string('recommended_modality')->nullable();
            $table->text('risk_notes')->nullable();
            $table->text('next_steps')->nullable();
            $table->boolean('send_confirmation')->default(true);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('consultations');
    }
};
