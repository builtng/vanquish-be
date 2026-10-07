<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (Schema::hasTable('client_intake_forms') && Schema::hasColumn('client_intake_forms', 'age_preference')) {
            DB::table('client_intake_forms')
                ->where('age_preference', 'Younger')
                ->orWhere('age_preference', 'like', '%younger%')
                ->update(['age_preference' => 'No preference']);
        }

        if (Schema::hasTable('clients') && Schema::hasColumn('clients', 'age_preference')) {
            DB::table('clients')
                ->where('age_preference', 'Younger')
                ->orWhere('age_preference', 'like', '%younger%')
                ->update(['age_preference' => 'No preference']);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // No action needed for down
    }
};
