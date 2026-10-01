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
        Schema::create('consultation_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained('clients')->cascadeOnDelete();
            $table->foreignId('tc_id')->nullable()->constrained('training_counsellors')->nullOnDelete();
            $table->string('session_type'); // 'low_cost', 'mid_range', 'counselling_coaching'
            $table->dateTime('scheduled_at');
            $table->dateTime('completed_at')->nullable();
            $table->enum('status', ['scheduled', 'completed', 'cancelled', 'no_show'])->default('scheduled');
            $table->integer('duration_minutes')->default(50);
            $table->text('notes')->nullable();
            
            // Payment and booking fields
            $table->enum('payment_status', ['pending', 'paid', 'refunded'])->default('pending');
            $table->decimal('payment_amount', 10, 2)->nullable();
            $table->boolean('is_block_booking')->default(false);
            $table->integer('block_number')->nullable(); // Which session in the block (1-4 for low cost)
            $table->integer('total_sessions_in_block')->nullable();
            
            // Low Cost specific fields
            $table->date('booking_deadline')->nullable(); // 48hrs before scheduled session
            $table->boolean('booking_reminder_sent')->default(false);
            $table->boolean('auto_deduction_applied')->default(false); // If they didn't book in time
            
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('consultation_sessions');
    }
};

