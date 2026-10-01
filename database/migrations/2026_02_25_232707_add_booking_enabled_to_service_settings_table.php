<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('service_settings', function (Blueprint $table) {
            // When false (default), clients CANNOT see or book slots.
            // Admin must explicitly flip this to true to open bookings for a service.
            $table->boolean('booking_enabled')->default(false)->after('block_price');
        });

        // All existing services start with booking DISABLED
        // Admin must explicitly enable them from Settings > Booking System Settings
        DB::table('service_settings')->update(['booking_enabled' => false]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('service_settings', function (Blueprint $table) {
            $table->dropColumn('booking_enabled');
        });
    }
};
