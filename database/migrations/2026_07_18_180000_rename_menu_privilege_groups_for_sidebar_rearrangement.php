<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// The admin sidebar's "Applications" and "Consultations" groups were merged into
// "TC Management" and "Client Management" respectively. This preserves any
// admin-configured role visibility (menu_privileges rows) for the old group ids
// instead of silently orphaning them when the sidebar's group ids change.
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('menu_privileges')) {
            return;
        }

        // consultations-group -> client-management-group (simple rename, no merge target existed)
        $consultationsGroup = DB::table('menu_privileges')->where('menu_id', 'consultations-group')->first();
        if ($consultationsGroup) {
            if (DB::table('menu_privileges')->where('menu_id', 'client-management-group')->exists()) {
                DB::table('menu_privileges')->where('menu_id', 'consultations-group')->delete();
            } else {
                DB::table('menu_privileges')->where('menu_id', 'consultations-group')->update(['menu_id' => 'client-management-group']);
            }
        }

        // applications-group folds into tc-management-group: merge roles, then drop the old row.
        $applicationsGroup = DB::table('menu_privileges')->where('menu_id', 'applications-group')->first();
        if ($applicationsGroup) {
            $tcManagementGroup = DB::table('menu_privileges')->where('menu_id', 'tc-management-group')->first();
            if ($tcManagementGroup) {
                $mergedRoles = array_values(array_unique(array_merge(
                    json_decode($applicationsGroup->roles, true) ?? [],
                    json_decode($tcManagementGroup->roles, true) ?? [],
                )));
                DB::table('menu_privileges')
                    ->where('menu_id', 'tc-management-group')
                    ->update(['roles' => json_encode($mergedRoles)]);
                DB::table('menu_privileges')->where('menu_id', 'applications-group')->delete();
            } else {
                DB::table('menu_privileges')->where('menu_id', 'applications-group')->update(['menu_id' => 'tc-management-group']);
            }
        }
    }

    public function down(): void
    {
        // Menu privilege visibility config isn't meaningfully reversible once merged; no-op.
    }
};
