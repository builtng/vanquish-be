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
            $table->decimal('consultation_price', 10, 2)->default(13.00)->after('service_name');
        });

        // Set default price for Ish's service
        DB::table('service_settings')
            ->where('service_name', 'Ish')
            ->update(['consultation_price' => 25.00]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('service_settings', function (Blueprint $table) {
            $table->dropColumn('consultation_price');
        });
    }
};

