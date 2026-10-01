<?php

use Illuminate\Database\Migrations\Migration;
use App\Models\ServiceSetting;

/**
 * Data-only migration: sets the Mid Range / Coaching & Counselling
 * consultation prices per the new intake form spec (£15 / £20). No schema
 * change — `consultation_price` already exists. Confirmed unused by /client
 * and /ish (both read from the separate "General Assessment"/"TrafftBooking"
 * row, or a hardcoded value), so this only affects the new form and the
 * admin settings display.
 */
return new class extends Migration
{
    public function up(): void
    {
        ServiceSetting::where('service_name', 'Mid Range')->update(['consultation_price' => 15.00]);
        ServiceSetting::where('service_name', 'Counselling & Coaching')->update(['consultation_price' => 20.00]);
    }

    public function down(): void
    {
        ServiceSetting::where('service_name', 'Mid Range')->update(['consultation_price' => 13.00]);
        ServiceSetting::where('service_name', 'Counselling & Coaching')->update(['consultation_price' => 13.00]);
    }
};
