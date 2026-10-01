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
        Schema::table('training_counsellors', function (Blueprint $table) {
            $table->uuid('uuid')->unique()->nullable()->after('id');
        });

        // Generate UUIDs for existing records
        $tcs = DB::table('training_counsellors')->get();
        foreach ($tcs as $tc) {
            DB::table('training_counsellors')
                ->where('id', $tc->id)
                ->update(['uuid' => Str::uuid()->toString()]);
        }

        // Make uuid NOT NULL after populating
        Schema::table('training_counsellors', function (Blueprint $table) {
            $table->uuid('uuid')->nullable(false)->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('training_counsellors', function (Blueprint $table) {
            $table->dropColumn('uuid');
        });
    }
};
