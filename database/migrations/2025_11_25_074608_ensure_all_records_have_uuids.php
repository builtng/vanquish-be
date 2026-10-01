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
        // Ensure clients table has uuid column
        if (Schema::hasTable('clients') && !Schema::hasColumn('clients', 'uuid')) {
            Schema::table('clients', function (Blueprint $table) {
                $table->uuid('uuid')->unique()->nullable()->after('id');
            });
        }

        // Generate UUIDs for clients that don't have them
        if (Schema::hasTable('clients') && Schema::hasColumn('clients', 'uuid')) {
            $clientsWithoutUuid = DB::table('clients')
                ->whereNull('uuid')
                ->orWhere('uuid', '')
                ->get();

            foreach ($clientsWithoutUuid as $client) {
                DB::table('clients')
                    ->where('id', $client->id)
                    ->update(['uuid' => Str::uuid()->toString()]);
            }

            // Make uuid NOT NULL if it's nullable
            if (Schema::hasColumn('clients', 'uuid')) {
                Schema::table('clients', function (Blueprint $table) {
                    $table->uuid('uuid')->nullable(false)->change();
                });
            }
        }

        // Ensure training_counsellors table has uuid column
        if (Schema::hasTable('training_counsellors') && !Schema::hasColumn('training_counsellors', 'uuid')) {
            Schema::table('training_counsellors', function (Blueprint $table) {
                $table->uuid('uuid')->unique()->nullable()->after('id');
            });
        }

        // Generate UUIDs for training counsellors that don't have them
        if (Schema::hasTable('training_counsellors') && Schema::hasColumn('training_counsellors', 'uuid')) {
            $tcsWithoutUuid = DB::table('training_counsellors')
                ->whereNull('uuid')
                ->orWhere('uuid', '')
                ->get();

            foreach ($tcsWithoutUuid as $tc) {
                DB::table('training_counsellors')
                    ->where('id', $tc->id)
                    ->update(['uuid' => Str::uuid()->toString()]);
            }

            // Make uuid NOT NULL if it's nullable
            if (Schema::hasColumn('training_counsellors', 'uuid')) {
                Schema::table('training_counsellors', function (Blueprint $table) {
                    $table->uuid('uuid')->nullable(false)->change();
                });
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // This migration is safe to reverse - it only ensures UUIDs exist
        // The actual UUID columns are managed by their respective migrations
    }
};
