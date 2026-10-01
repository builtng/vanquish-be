<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Permission;

class PermissionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $permissions = [
            [
                'name' => 'match_clients',
                'display_name' => 'Match Clients with TCs',
                'description' => 'Ability to match clients with training counsellors',
            ],
            [
                'name' => 'edit_clients',
                'display_name' => 'Edit Client Information',
                'description' => 'Ability to edit client details and information',
            ],
            [
                'name' => 'view_only',
                'display_name' => 'View Only Access',
                'description' => 'Read-only access to client and TC information',
            ],
            [
                'name' => 'delete_clients',
                'display_name' => 'Delete Clients',
                'description' => 'Ability to delete client records',
            ],
            [
                'name' => 'manage_users',
                'display_name' => 'Manage Users',
                'description' => 'Ability to create, edit, and delete system users',
            ],
            [
                'name' => 'view_own_clients',
                'display_name' => 'View Own Clients',
                'description' => 'Counsellors can view their assigned clients',
            ],
            [
                'name' => 'view_own_sessions',
                'display_name' => 'View Own Sessions',
                'description' => 'Counsellors can view their scheduled sessions',
            ],
            [
                'name' => 'send_message',
                'display_name' => 'Send Messages',
                'description' => 'Ability to send messages to staff/counsellors',
            ],
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate(
                ['name' => $permission['name']],
                $permission
            );
        }
    }
}


