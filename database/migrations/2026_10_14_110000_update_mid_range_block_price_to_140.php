<?php

use App\Models\ServiceSetting;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        ServiceSetting::updateOrCreate(
            ['service_name' => 'Mid Range'],
            [
                'consultation_price' => 15.00,
                'session_price' => 40.00,
                'block_price' => 140.00,
                'booking_enabled' => true,
            ]
        );
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Maintain existing setting
    }
};
