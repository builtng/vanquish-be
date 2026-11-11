<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Paid Mid Range / Coaching intakes save the consultation as 'pending' until
 * the card payment goes through. The MySQL enum did not include 'pending',
 * so every paid intake failed with "Failed to create intake form".
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!in_array(DB::getDriverName(), ['mysql', 'mariadb'], true)) {
            return; // SQLite (tests) stores enums as plain text.
        }

        DB::statement("ALTER TABLE consultations MODIFY status ENUM('pending','scheduled','completed','cancelled','no_show') NOT NULL DEFAULT 'scheduled'");
    }

    public function down(): void
    {
        if (!in_array(DB::getDriverName(), ['mysql', 'mariadb'], true)) {
            return;
        }

        DB::table('consultations')->where('status', 'pending')->update(['status' => 'scheduled']);
        DB::statement("ALTER TABLE consultations MODIFY status ENUM('scheduled','completed','cancelled','no_show') NOT NULL DEFAULT 'scheduled'");
    }
};
