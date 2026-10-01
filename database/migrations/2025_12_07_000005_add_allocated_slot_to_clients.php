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
        Schema::table('clients', function (Blueprint $table) {
            // For Low Cost Counselling - allocated day and time slot
            $table->string('allocated_day')->nullable()->after('service_type'); // e.g., 'Monday', 'Tuesday'
            $table->string('allocated_time')->nullable()->after('allocated_day'); // e.g., '10am-1050am'
            $table->date('next_booking_deadline')->nullable()->after('allocated_time'); // 48hrs before next scheduled session
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            $table->dropColumn(['allocated_day', 'allocated_time', 'next_booking_deadline']);
        });
    }
};


