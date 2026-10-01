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
        Schema::create('tc_intake_forms', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tc_id')->nullable()->constrained('training_counsellors')->nullOnDelete();
            $table->string('name');
            $table->string('email');
            $table->string('phone')->nullable();
            $table->string('modality')->nullable();
            $table->string('course')->nullable();
            $table->string('institution')->nullable();
            $table->json('topics_with_experience')->nullable();
            $table->json('topics_not_ready_for')->nullable();
            $table->json('availability')->nullable();
            $table->text('additional_info')->nullable();
            $table->enum('status', ['draft', 'submitted', 'approved', 'rejected'])->default('draft');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tc_intake_forms');
    }
};
