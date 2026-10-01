<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            $table->uuid('uuid')->unique()->nullable()->after('id');
        });

        // Generate UUIDs for existing records
        $clients = DB::table('clients')->get();
        foreach ($clients as $client) {
            DB::table('clients')
                ->where('id', $client->id)
                ->update(['uuid' => Str::uuid()->toString()]);
        }

        // Make uuid NOT NULL after populating
        Schema::table('clients', function (Blueprint $table) {
            $table->uuid('uuid')->nullable(false)->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            $table->dropColumn('uuid');
        });
    }
};
