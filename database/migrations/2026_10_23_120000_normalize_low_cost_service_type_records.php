<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 1. clients table
        if (Schema::hasTable('clients') && Schema::hasColumn('clients', 'service_type')) {
            DB::table('clients')
                ->whereIn(DB::raw('LOWER(TRIM(service_type))'), ['low-cost', 'low_cost', 'low cost'])
                ->update(['service_type' => 'Low Cost']);
        }

        // 2. client_intake_forms table
        if (Schema::hasTable('client_intake_forms') && Schema::hasColumn('client_intake_forms', 'service_type')) {
            DB::table('client_intake_forms')
                ->whereIn(DB::raw('LOWER(TRIM(service_type))'), ['low-cost', 'low_cost', 'low cost'])
                ->update(['service_type' => 'Low Cost']);
        }

        // 3. client_tc_matches table
        if (Schema::hasTable('client_tc_matches') && Schema::hasColumn('client_tc_matches', 'service_type')) {
            DB::table('client_tc_matches')
                ->whereIn(DB::raw('LOWER(TRIM(service_type))'), ['low-cost', 'low_cost', 'low cost'])
                ->update(['service_type' => 'Low Cost']);
        }

        // 4. consultations table (recommended_service column)
        if (Schema::hasTable('consultations') && Schema::hasColumn('consultations', 'recommended_service')) {
            DB::table('consultations')
                ->whereIn(DB::raw('LOWER(TRIM(recommended_service))'), ['low-cost', 'low_cost', 'low cost'])
                ->update(['recommended_service' => 'Low Cost']);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // No reversal needed as 'Low Cost' is the canonical enum value
    }
};
