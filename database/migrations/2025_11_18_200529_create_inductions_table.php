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
        Schema::create('inductions', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('tc_id')->constrained('training_counsellors')->onDelete('cascade');
            $table->dateTime('scheduled_at');
            $table->string('location')->nullable();
            $table->text('notes')->nullable();
            $table->enum('status', ['scheduled', 'completed', 'cancelled'])->default('scheduled');
            $table->timestamps();
        });

        Schema::create('induction_attendees', function (Blueprint $table) {
            $table->id();
            $table->foreignId('induction_id')->constrained('inductions')->onDelete('cascade');
            $table->foreignId('tc_id')->constrained('training_counsellors')->onDelete('cascade');
            $table->string('acceptance_token')->unique();
            $table->enum('status', ['pending', 'accepted', 'declined', 'expired'])->default('pending');
            $table->dateTime('accepted_at')->nullable();
            $table->dateTime('expires_at');
            $table->timestamps();

            $table->unique(['induction_id', 'tc_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('induction_attendees');
        Schema::dropIfExists('inductions');
    }
};

