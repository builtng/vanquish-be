<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class ConsultationSlotSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $endDate = Carbon::now()->addMonth();
        $currentDate = Carbon::now()->addDays(2);

        // Assuming we want slots on Mondays and Wednesdays
        while ($currentDate <= $endDate) {
            if ($currentDate->isMonday() || $currentDate->isWednesday()) {
                // Add a slot at 10 AM
                $slotDate = $currentDate->copy()->setHour(10)->setMinute(0)->setSecond(0);
                DB::table('consultation_slots')->insert([
                    'consultation_datetime' => $slotDate,
                    'status' => 'available',
                    'max_slots' => 5,
                    'booked_slots' => 0,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                // Add a slot at 2 PM
                $slotDate2 = $currentDate->copy()->setHour(14)->setMinute(0)->setSecond(0);
                DB::table('consultation_slots')->insert([
                    'consultation_datetime' => $slotDate2,
                    'status' => 'available',
                    'max_slots' => 5,
                    'booked_slots' => 0,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            $currentDate->addDay();
        }
    }
}
