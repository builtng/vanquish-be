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
        Schema::create('menu_privileges', function (Blueprint $table) {
            $table->id();
            $table->string('menu_id')->unique();
            $table->json('roles'); // Array of roles allowed to see this menu item
            $table->timestamps();
        });

        // Seed default values
        DB::table('menu_privileges')->insert([
            ['menu_id' => 'overview', 'roles' => json_encode(['admin', 'staff', 'counsellor']), 'created_at' => now(), 'updated_at' => now()],
            ['menu_id' => 'consultations', 'roles' => json_encode(['admin', 'staff']), 'created_at' => now(), 'updated_at' => now()],
            ['menu_id' => 'all-matches', 'roles' => json_encode(['admin', 'staff']), 'created_at' => now(), 'updated_at' => now()],
            ['menu_id' => 'pending-matches', 'roles' => json_encode(['admin', 'staff']), 'created_at' => now(), 'updated_at' => now()],
            ['menu_id' => 'completed-matches', 'roles' => json_encode(['admin', 'staff']), 'created_at' => now(), 'updated_at' => now()],
            ['menu_id' => 'tcs', 'roles' => json_encode(['admin', 'staff']), 'created_at' => now(), 'updated_at' => now()],
            ['menu_id' => 'inductions', 'roles' => json_encode(['admin', 'staff']), 'created_at' => now(), 'updated_at' => now()],
            ['menu_id' => 'providers', 'roles' => json_encode(['admin', 'staff']), 'created_at' => now(), 'updated_at' => now()],
            ['menu_id' => 'clients', 'roles' => json_encode(['admin', 'staff']), 'created_at' => now(), 'updated_at' => now()],
            ['menu_id' => 'activity', 'roles' => json_encode(['admin', 'staff']), 'created_at' => now(), 'updated_at' => now()],
            ['menu_id' => 'coupons', 'roles' => json_encode(['admin']), 'created_at' => now(), 'updated_at' => now()],
            ['menu_id' => 'email-management', 'roles' => json_encode(['admin']), 'created_at' => now(), 'updated_at' => now()],
            ['menu_id' => 'users', 'roles' => json_encode(['admin']), 'created_at' => now(), 'updated_at' => now()],
            ['menu_id' => 'consultation-slots', 'roles' => json_encode(['admin']), 'created_at' => now(), 'updated_at' => now()],
            ['menu_id' => 'color-guide', 'roles' => json_encode(['admin', 'staff']), 'created_at' => now(), 'updated_at' => now()],
            ['menu_id' => 'matching-algo', 'roles' => json_encode(['admin']), 'created_at' => now(), 'updated_at' => now()],
            ['menu_id' => 'reports-group', 'roles' => json_encode(['admin', 'staff']), 'created_at' => now(), 'updated_at' => now()],
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('menu_privileges');
    }
};
