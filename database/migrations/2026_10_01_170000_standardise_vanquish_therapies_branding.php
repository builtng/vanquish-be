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
        // 1. Standardise company_name in company_settings
        if (Schema::hasTable('company_settings')) {
            DB::table('company_settings')
                ->where('key', 'company_name')
                ->update(['value' => 'Vanquish Therapies', 'updated_at' => now()]);

            DB::table('company_settings')
                ->where('key', 'pdf_header_text')
                ->where('value', 'like', '%Vanquish Training%')
                ->update(['value' => 'Vanquish Therapies - Confidential', 'updated_at' => now()]);
        }

        // 2. Standardise service_settings capacity messages
        if (Schema::hasTable('service_settings')) {
            DB::table('service_settings')
                ->where('capacity_message', 'like', '%VQT COACHING%')
                ->update([
                    'capacity_message' => 'This service is at capacity at this time. If you would like to work with our Counselling & Coaching service, you can proceed with our Partner service Vanquish Therapies Coaching & Therapy.',
                    'updated_at' => now(),
                ]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // No-op rollback to preserve clean practice naming
    }
};
