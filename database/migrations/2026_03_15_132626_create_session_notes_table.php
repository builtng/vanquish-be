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
        Schema::create('session_notes', function (Blueprint $blueprint) {
            $blueprint->id();
            $blueprint->foreignId('training_counsellor_id')->constrained('training_counsellors')->onDelete('cascade');
            $blueprint->foreignId('client_id')->nullable()->constrained('clients')->onDelete('cascade');
            $blueprint->string('type'); // weekly, block_summary, risk_update
            $blueprint->json('content');
            $blueprint->string('status')->default('submitted');
            $blueprint->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('session_notes');
    }
};
