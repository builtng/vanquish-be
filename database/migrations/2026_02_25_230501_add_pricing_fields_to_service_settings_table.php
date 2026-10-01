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
            $table->decimal('session_price', 10, 2)->nullable()->after('consultation_price');
            $table->decimal('block_price', 10, 2)->nullable()->after('session_price');
        });

        // Set TrafftBooking as the 'Default' for general assessment fee
        DB::table('service_settings')->where('service_name', 'TrafftBooking')->update(['service_name' => 'General Assessment']);

        // Seed with current default values
        $defaults = [
            [
                'service_name' => 'Low Cost',
                'consultation_price' => 13.00,
                'session_price' => 6.25,
                'block_price' => 25.00,
                'capacity_full' => false,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'service_name' => 'Mid Range',
                'consultation_price' => 13.00,
                'session_price' => 40.00,
                'block_price' => null,
                'capacity_full' => false,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'service_name' => 'Counselling & Coaching',
                'consultation_price' => 13.00,
                'session_price' => 50.00,
                'block_price' => null,
                'capacity_full' => false,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ];

        foreach ($defaults as $default) {
            DB::table('service_settings')->updateOrInsert(
                ['service_name' => $default['service_name']],
                $default
            );
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('service_settings', function (Blueprint $table) {
            $table->dropColumn(['session_price', 'block_price']);
        });
    }
};
