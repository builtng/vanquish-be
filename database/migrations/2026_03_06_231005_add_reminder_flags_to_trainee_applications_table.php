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
        Schema::table('trainee_applications', function (Blueprint $table) {
            $table->boolean('reminder_48h_sent')->default(false)->after('status');
            $table->boolean('reminder_2h_sent')->default(false)->after('reminder_48h_sent');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('trainee_applications', function (Blueprint $table) {
            $table->dropColumn(['reminder_48h_sent', 'reminder_2h_sent']);
        });
    }
};
