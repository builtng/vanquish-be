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
        if (Schema::hasTable('clients')) {
            Schema::table('clients', function (Blueprint $table) {
                // Safely drop unique constraint on email if it exists
                // In SQLite / MySQL, catch if it doesn't exist
                try {
                    $table->dropUnique(['email']);
                } catch (\Throwable $e) {
                    try {
                        $table->dropUnique('clients_email_unique');
                    } catch (\Throwable $e2) {
                        // Ignore if index did not exist or SQLite table restructure
                    }
                }
            });

            // Ensure a standard index on email for quick lookups
            Schema::table('clients', function (Blueprint $table) {
                try {
                    $table->index('email');
                } catch (\Throwable $e) {
                    // Ignore if already indexed
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('clients')) {
            Schema::table('clients', function (Blueprint $table) {
                try {
                    $table->dropIndex(['email']);
                } catch (\Throwable $e) {
                    // Ignore
                }
                try {
                    $table->unique('email');
                } catch (\Throwable $e) {
                    // Ignore
                }
            });
        }
    }
};
