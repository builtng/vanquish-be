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
        Schema::create('messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('from_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('to_tc_id')->nullable()->constrained('training_counsellors')->nullOnDelete();
            $table->foreignId('to_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('subject');
            $table->text('message');
            $table->enum('type', ['staff_to_counsellor', 'counsellor_to_staff', 'staff_to_staff'])->default('staff_to_counsellor');
            $table->boolean('is_read')->default(false);
            $table->timestamp('read_at')->nullable();
            $table->foreignId('related_client_id')->nullable()->constrained('clients')->nullOnDelete();
            $table->foreignId('related_consultation_id')->nullable()->constrained('consultations')->nullOnDelete();
            $table->timestamps();
            
            $table->index(['to_tc_id', 'is_read']);
            $table->index(['to_user_id', 'is_read']);
            $table->index(['from_user_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('messages');
    }
};


