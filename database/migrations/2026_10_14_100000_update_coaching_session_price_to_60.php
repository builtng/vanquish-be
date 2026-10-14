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
        ServiceSetting::updateOrCreate(
            ['service_name' => 'Counselling & Coaching'],
            [
                'session_price' => 60.00,
            ]
        );
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        ServiceSetting::updateOrCreate(
            ['service_name' => 'Counselling & Coaching'],
            [
                'session_price' => 50.00,
            ]
        );
    }
};
