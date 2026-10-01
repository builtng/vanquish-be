<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('psg_session_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('attendance_group_id')
                  ->constrained('attendance_groups')
                  ->onDelete('cascade');
            $table->date('session_date');
            $table->string('supervisor_name');
            $table->text('activities');
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('psg_session_logs');
    }
};
