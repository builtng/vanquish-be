<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Drop the table if it already exists (guards against a partial prior run
        // where the table was created but the unique index failed).
        Schema::dropIfExists('psg_session_attendees');

        Schema::create('psg_session_attendees', function (Blueprint $table) {
            $table->id();
            $table->foreignId('psg_session_log_id')
                  ->constrained('psg_session_logs')
                  ->onDelete('cascade');
            $table->foreignId('training_counsellor_id')
                  ->constrained('training_counsellors')
                  ->onDelete('cascade');
            $table->boolean('attended')->default(true);
            $table->timestamps();

            $table->unique(['psg_session_log_id', 'training_counsellor_id'], 'psg_attendees_log_tc_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('psg_session_attendees');
    }
};
