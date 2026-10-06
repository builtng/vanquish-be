<?php

use Illuminate\Database\Migrations\Migration;
use App\Models\ServiceSetting;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Low Cost: consultation £13, session £18, block of 4 £72
        ServiceSetting::updateOrCreate(
            ['service_name' => 'Low Cost'],
            [
                'consultation_price' => 13.00,
                'session_price'      => 18.00,
                'block_price'        => 72.00,
            ]
        );

        // Ish: consultation £25
        ServiceSetting::updateOrCreate(
            ['service_name' => 'Ish'],
            [
                'consultation_price' => 25.00,
            ]
        );

        // Mid Range: consultation £15, single session £40, block of 4 £140 (£35/session)
        ServiceSetting::updateOrCreate(
            ['service_name' => 'Mid Range'],
            [
                'consultation_price' => 15.00,
                'session_price'      => 40.00,
                'block_price'        => 140.00,
            ]
        );

        // Counselling & Coaching: consultation £20
        ServiceSetting::updateOrCreate(
            ['service_name' => 'Counselling & Coaching'],
            [
                'consultation_price' => 20.00,
            ]
        );
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        ServiceSetting::updateOrCreate(
            ['service_name' => 'Low Cost'],
            [
                'consultation_price' => 13.00,
                'session_price'      => 6.25,
                'block_price'        => 25.00,
            ]
        );

        ServiceSetting::updateOrCreate(
            ['service_name' => 'Mid Range'],
            [
                'consultation_price' => 15.00,
                'session_price'      => 40.00,
                'block_price'        => null,
            ]
        );
    }
};
