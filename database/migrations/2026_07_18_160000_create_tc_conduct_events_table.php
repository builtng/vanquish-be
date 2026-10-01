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
        Schema::create('tc_conduct_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tc_id')->constrained('training_counsellors')->cascadeOnDelete();
            $table->string('type');
            $table->text('notes')->nullable();
            $table->foreignId('logged_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->boolean('email_sent')->default(false);
            $table->timestamps();

            $table->index(['tc_id', 'type']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tc_conduct_events');
    }
};
