<?php

namespace Database\Seeders;

use App\Models\ClientTcMatch;
use App\Models\Client;
use App\Models\TrainingCounsellor;
use Illuminate\Database\Seeder;
use Carbon\Carbon;

class ClientTcMatchSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Get clients and TCs
        $emma = Client::where('client_id', 'CL001')->first();
        $john = Client::where('client_id', 'CL002')->first();
        $michael = Client::where('client_id', 'CL011')->first();
        $sophie = Client::where('client_id', 'CL020')->first();

        $sarah = TrainingCounsellor::where('tc_id', 'TC001')->first();
        $david = TrainingCounsellor::where('tc_id', 'TC002')->first();
        $priya = TrainingCounsellor::where('tc_id', 'TC003')->first();

        $matches = [
            [
                'client_id' => $emma->id ?? null,
                'tc_id' => $sarah->id ?? null,
                'match_score' => 88,
                'assignment_notes' => 'Good match for CBT approach. Client prefers morning sessions.',
                'status' => 'active',
                'assigned_date' => Carbon::parse('2025-02-22'),
                'agreement_signed_date' => Carbon::parse('2025-02-25'),
                'start_date' => Carbon::parse('2025-02-28'),
                'send_notification' => true,
            ],
            [
                'client_id' => $john->id ?? null,
                'tc_id' => $david->id ?? null,
                'match_score' => 92,
                'assignment_notes' => 'Excellent match for Person-Centred approach. Good availability alignment.',
                'status' => 'active',
                'assigned_date' => Carbon::parse('2025-01-28'),
                'agreement_signed_date' => Carbon::parse('2025-01-30'),
                'start_date' => Carbon::parse('2025-02-01'),
                'send_notification' => true,
            ],
            [
                'client_id' => $michael->id ?? null,
                'tc_id' => $priya->id ?? null,
                'match_score' => 85,
                'assignment_notes' => 'Good match for Integrative approach. Client needs communication support.',
                'status' => 'active',
                'assigned_date' => Carbon::parse('2025-02-27'),
                'agreement_signed_date' => Carbon::parse('2025-02-28'),
                'start_date' => Carbon::parse('2025-03-01'),
                'send_notification' => true,
            ],
            [
                'client_id' => $sophie->id ?? null,
                'tc_id' => $priya->id ?? null,
                'match_score' => 87,
                'assignment_notes' => 'Good match for social anxiety support.',
                'status' => 'assigned',
                'assigned_date' => Carbon::parse('2025-03-17'),
                'agreement_signed_date' => null,
                'start_date' => null,
                'send_notification' => true,
            ],
        ];

        foreach ($matches as $match) {
            if ($match['client_id'] && $match['tc_id']) {
                ClientTcMatch::updateOrCreate(
                    [
                        'client_id' => $match['client_id'],
                        'tc_id' => $match['tc_id']
                    ],
                    $match
                );
            }
        }

        $this->command->info('Client-TC Matches seeded successfully!');
    }
}

