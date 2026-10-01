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
        $driver = DB::getDriverName();
        
        if ($driver === 'sqlite') {
            // SQLite doesn't support MODIFY COLUMN or ENUM constraints
            // The role column already exists and SQLite stores it as TEXT
            // Validation will be handled at the application level
            // No database changes needed for SQLite
        } else {
            // MySQL/MariaDB - use MODIFY COLUMN
            DB::statement("ALTER TABLE users MODIFY COLUMN role ENUM('admin', 'staff', 'counsellor') DEFAULT 'admin'");
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $driver = DB::getDriverName();
        
        if ($driver === 'sqlite') {
            // SQLite - no changes needed
        } else {
            // MySQL/MariaDB - revert to original enum
            DB::statement("ALTER TABLE users MODIFY COLUMN role ENUM('admin', 'staff') DEFAULT 'admin'");
        }
    }
};

