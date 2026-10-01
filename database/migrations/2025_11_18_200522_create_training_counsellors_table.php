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
        Schema::create('training_counsellors', function (Blueprint $table) {
            $table->id();
            $table->string('tc_id')->unique();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('phone')->nullable();
            $table->enum('modality', ['CBT', 'Person-Centred', 'Integrative', 'Psychodynamic', 'Other'])->nullable();
            $table->enum('status', ['Active', 'At Capacity', 'On Leave', 'Inactive'])->default('Active');
            $table->integer('current_clients')->default(0);
            $table->json('availability')->nullable(); // {Monday: ['morning-early', 'afternoon'], ...}
            $table->json('topics_with_experience')->nullable();
            $table->json('topics_not_ready_for')->nullable();
            $table->string('course')->nullable();
            $table->string('institution')->nullable();
            $table->date('joined_date')->nullable();
            $table->timestamp('last_activity')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('training_counsellors');
    }
};
