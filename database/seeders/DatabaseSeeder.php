<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Create or update test admin user
        User::updateOrCreate(
            ['email' => 'admin@vanquish.com'],
            [
                'name' => 'Admin User',
                'password' => Hash::make('admin123'),
                'role' => 'admin',
            ]
        );

        // Create or update test staff user
        User::updateOrCreate(
            ['email' => 'staff@vanquish.com'],
            [
                'name' => 'Staff User',
                'password' => Hash::make('staff123'),
                'role' => 'staff',
            ]
        );

        $this->command->info('Test users created successfully!');
        $this->command->info('Admin: admin@vanquish.com / admin123');
        $this->command->info('Staff: staff@vanquish.com / staff123');

        // Seed in order (due to foreign key dependencies)
        $this->call(TrainingCounsellorSeeder::class);
        $this->call(ClientSeeder::class);
        $this->call(ConsultationSeeder::class);
        $this->call(ClientTcMatchSeeder::class);
        $this->call(ActivityLogSeeder::class);
        $this->call(CompanySettingsSeeder::class);
        
        $this->command->info('All seeders completed successfully!');
    }
}
