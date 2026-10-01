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
        Schema::create('client_tc_matches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained('clients')->cascadeOnDelete();
            $table->foreignId('tc_id')->constrained('training_counsellors')->cascadeOnDelete();
            $table->integer('match_score')->nullable(); // 0-100
            $table->text('assignment_notes')->nullable();
            $table->enum('status', ['pending', 'assigned', 'agreement_pending', 'active', 'completed', 'cancelled'])->default('pending');
            $table->date('assigned_date')->nullable();
            $table->date('agreement_signed_date')->nullable();
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            $table->boolean('send_notification')->default(true);
            $table->timestamps();
            
            $table->unique(['client_id', 'tc_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('client_tc_matches');
    }
};
