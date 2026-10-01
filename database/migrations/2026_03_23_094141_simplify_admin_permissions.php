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
        $privileges = DB::table('menu_privileges')->get();

        foreach ($privileges as $privilege) {
            $roles = json_decode($privilege->roles, true);
            if (is_array($roles)) {
                // If super_admin is present, make sure admin is also present, then remove super_admin
                if (in_array('super_admin', $roles)) {
                    if (!in_array('admin', $roles)) {
                        $roles[] = 'admin';
                    }
                    $roles = array_diff($roles, ['super_admin']);
                    $roles = array_values($roles); // Re-index
                }

                DB::table('menu_privileges')
                    ->where('id', $privilege->id)
                    ->update(['roles' => json_encode($roles), 'updated_at' => now()]);
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // No need to revert specifically
    }
};
