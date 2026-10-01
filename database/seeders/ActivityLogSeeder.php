<?php

namespace Database\Seeders;

use App\Models\ActivityLog;
use App\Models\User;
use App\Models\Client;
use App\Models\TrainingCounsellor;
use Illuminate\Database\Seeder;
use Carbon\Carbon;

class ActivityLogSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $admin = User::where('email', 'admin@vanquish.com')->first();
        $staff = User::where('email', 'staff@vanquish.com')->first();

        if (!$admin || !$staff) {
            $this->command->warn('Users not found. Please run UserSeeder first.');
            return;
        }

        $emma = Client::where('client_id', 'CL001')->first();
        $john = Client::where('client_id', 'CL002')->first();
        $sarah = TrainingCounsellor::where('tc_id', 'TC001')->first();

        $activities = [
            [
                'user_id' => $admin->id,
                'action' => 'client_created',
                'model_type' => Client::class,
                'model_id' => $emma->id ?? 1,
                'description' => 'Client Emma Wilson created',
                'ip_address' => '127.0.0.1',
                'created_at' => Carbon::parse('2025-02-15 10:00'),
            ],
            [
                'user_id' => $admin->id,
                'action' => 'client_tc_matched',
                'model_type' => \App\Models\ClientTcMatch::class,
                'model_id' => 1,
                'description' => 'Client Emma Wilson matched with TC Sarah Johnson',
                'ip_address' => '127.0.0.1',
                'created_at' => Carbon::parse('2025-02-22 14:30'),
            ],
            [
                'user_id' => $staff->id,
                'action' => 'consultation_completed',
                'model_type' => \App\Models\Consultation::class,
                'model_id' => 1,
                'description' => 'Consultation completed for Emma Wilson',
                'ip_address' => '127.0.0.1',
                'created_at' => Carbon::parse('2025-02-20 15:00'),
            ],
            [
                'user_id' => $admin->id,
                'action' => 'tc_created',
                'model_type' => TrainingCounsellor::class,
                'model_id' => $sarah->id ?? 1,
                'description' => 'Trainee Counsellor Sarah Johnson created',
                'ip_address' => '127.0.0.1',
                'created_at' => Carbon::parse('2024-08-15 09:00'),
            ],
            [
                'user_id' => $admin->id,
                'action' => 'client_updated',
                'model_type' => Client::class,
                'model_id' => $emma->id ?? 1,
                'description' => 'Client Emma Wilson information updated',
                'ip_address' => '127.0.0.1',
                'created_at' => Carbon::parse('2025-02-18 11:00'),
            ],
        ];

        foreach ($activities as $activity) {
            ActivityLog::updateOrCreate(
                [
                    'description' => $activity['description'],
                    'created_at' => $activity['created_at']
                ],
                $activity
            );
        }

        $this->command->info('Activity Logs seeded successfully!');
    }
}

