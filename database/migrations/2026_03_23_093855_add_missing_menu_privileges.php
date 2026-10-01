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
        $menuItems = [
            'overview' => ['admin', 'super_admin', 'staff', 'counsellor'],
            'applications-group' => ['admin', 'super_admin', 'staff'],
            'trainee-applications' => ['admin', 'super_admin', 'staff'],
            'video-reviews' => ['admin', 'super_admin', 'staff'],
            'consultations-group' => ['admin', 'super_admin', 'staff'],
            'pending-matches' => ['admin', 'super_admin', 'staff'],
            'consultations' => ['admin', 'super_admin', 'staff'],
            'completed-matches' => ['admin', 'super_admin', 'staff'],
            'tc-management-group' => ['admin', 'super_admin', 'staff'],
            'tcs' => ['admin', 'super_admin', 'staff'],
            'inductions' => ['admin', 'super_admin', 'staff'],
            'providers' => ['admin', 'super_admin', 'staff'],
            'reports-group' => ['admin', 'super_admin', 'staff'],
            'activity' => ['admin', 'super_admin', 'staff', 'counsellor'],
            'resources' => ['admin', 'super_admin', 'staff', 'counsellor'],
            'communications-group' => ['admin', 'super_admin', 'staff'],
            'messages' => ['admin', 'super_admin', 'staff'],
            'staff-notes' => ['admin', 'super_admin', 'staff'],
            'settings-group' => ['admin', 'super_admin'],
            'email-management' => ['admin', 'super_admin'],
            'matching-algo' => ['admin', 'super_admin'],
            'consultation-slots' => ['admin', 'super_admin'],
            'users' => ['admin', 'super_admin'],
            'coupons' => ['admin', 'super_admin'],
            'color-guide' => ['admin', 'super_admin', 'staff'],
            'account-settings' => ['admin', 'super_admin', 'staff', 'counsellor'],
            'clients' => ['admin', 'super_admin', 'staff'],
        ];

        foreach ($menuItems as $id => $roles) {
            DB::table('menu_privileges')->updateOrInsert(
                ['menu_id' => $id],
                [
                    'roles' => json_encode($roles),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            );
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // No need to remove them in down, but we could if we wanted to be strict
    }
};
