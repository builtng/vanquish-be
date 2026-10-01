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
        Schema::create('psg_reflections', function (Blueprint $blueprint) {
            $blueprint->id();
            $blueprint->foreignId('training_counsellor_id')->constrained('training_counsellors')->onDelete('cascade');
            $blueprint->date('attendance_date');
            $blueprint->text('reflection');
            $blueprint->string('status')->default('submitted'); // e.g. submitted, reviewed
            $blueprint->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('psg_reflections');
    }
};
