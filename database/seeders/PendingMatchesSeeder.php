<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Client;
use App\Models\TrainingCounsellor;
use Illuminate\Support\Str;

class PendingMatchesSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Ensure we have at least one training counsellor
        $tc = TrainingCounsellor::first();

        if (!$tc) {
            // Create a training counsellor if none exists
            $latestTc = TrainingCounsellor::withTrashed()
                ->where('tc_id', 'LIKE', 'TC%')
                ->orderBy('id', 'desc')
                ->first();

            $nextNum = 1;
            if ($latestTc && preg_match('/^TC(\d+)$/', $latestTc->tc_id, $matches)) {
                $nextNum = intval($matches[1]) + 1;
            } else {
                $nextNum = TrainingCounsellor::withTrashed()->count() + 1;
            }

            $newTcId = 'TC' . str_pad($nextNum, 3, '0', STR_PAD_LEFT);

            $tc = TrainingCounsellor::create([
                'tc_id' => $newTcId,
                'name' => 'Sarah Johnson',
                'email' => 'sarah.johnson@example.com',
                'phone' => '+44 7700 900123',
                'modality' => 'Person-Centred',
                'course' => 'Level 4 Diploma in Therapeutic Counselling',
                'institution' => 'CPCAB',
                'topics_with_experience' => ['Anxiety & Panic Attacks', 'Depression', 'Relationship Issues'],
                'topics_not_ready_for' => ['Childhood Trauma', 'Addiction & Substance Misuse'],
                'availability' => [
                    'monday' => ['10am-1050am', '2pm-250pm'],
                    'tuesday' => ['11am-1150am', '3pm-350pm'],
                    'wednesday' => ['10am-1050am', '1pm-150pm'],
                    'thursday' => ['2pm-250pm', '4pm-450pm'],
                    'friday' => ['10am-1050am'],
                ],
                'joined_date' => now()->subMonths(3),
                'last_activity' => now(),
                'status' => 'Active',
            ]);
        }

        // Create pending match clients with different urgency levels
        $pendingClients = [
            [
                'name' => 'Emily Thompson',
                'email' => 'emily.thompson@example.com',
                'phone' => '+44 7700 900456',
                'age' => 28,
                'gender' => 'Female',
                'service_type' => 'Low Cost',
                'primary_issues' => ['Anxiety & Panic Attacks', 'Low Self-Esteem'],
                'submitted_date' => now()->subDays(15), // High urgency - waiting 15 days
                'stage' => 'Consultation Completed',
                'matched_tc_id' => null,
            ],
            [
                'name' => 'James Wilson',
                'email' => 'james.wilson@example.com',
                'phone' => '+44 7700 900789',
                'age' => 35,
                'gender' => 'Male',
                'service_type' => 'Mid Range',
                'primary_issues' => ['Depression', 'Relationship Issues'],
                'submitted_date' => now()->subDays(8), // Medium urgency - waiting 8 days
                'stage' => 'Consultation Completed',
                'matched_tc_id' => null,
            ],
            [
                'name' => 'Olivia Martinez',
                'email' => 'olivia.martinez@example.com',
                'phone' => '+44 7700 900321',
                'age' => 42,
                'gender' => 'Female',
                'service_type' => 'Low Cost',
                'primary_issues' => ['Stress Management', 'Work-Related Stress'],
                'submitted_date' => now()->subDays(3), // Low urgency - waiting 3 days
                'stage' => 'Consultation Completed',
                'matched_tc_id' => null,
            ],
            [
                'name' => 'Michael Brown',
                'email' => 'michael.brown@example.com',
                'phone' => '+44 7700 900654',
                'age' => 31,
                'gender' => 'Male',
                'service_type' => 'High Range',
                'primary_issues' => ['Anxiety & Panic Attacks', 'Relationship Issues'],
                'submitted_date' => now()->subDays(20), // High urgency - waiting 20 days
                'stage' => 'Consultation Completed',
                'matched_tc_id' => null,
            ],
            [
                'name' => 'Sophie Anderson',
                'email' => 'sophie.anderson@example.com',
                'phone' => '+44 7700 900987',
                'age' => 26,
                'gender' => 'Female',
                'service_type' => 'Mid Range',
                'primary_issues' => ['Depression', 'Family Conflicts'],
                'submitted_date' => now()->subDays(5), // Medium urgency - waiting 5 days
                'stage' => 'Consultation Completed',
                'matched_tc_id' => null,
            ],
        ];

        foreach ($pendingClients as $clientData) {
            // Check if client already exists
            $existingClient = Client::where('email', $clientData['email'])->first();

            if (!$existingClient) {
                // Generate unique client_id
                $latestClient = Client::withTrashed()
                    ->where('client_id', 'LIKE', 'CL%')
                    ->orderBy('id', 'desc')
                    ->first();

                $nextNum = 1;
                if ($latestClient && preg_match('/^CL(\d+)$/', $latestClient->client_id, $matches)) {
                    $nextNum = intval($matches[1]) + 1;
                } else {
                    $nextNum = Client::withTrashed()->count() + 1;
                }

                $newClientId = 'CL' . str_pad($nextNum, 3, '0', STR_PAD_LEFT);

                // Ensure uniqueness
                while (Client::withTrashed()->where('client_id', $newClientId)->exists()) {
                    $nextNum++;
                    $newClientId = 'CL' . str_pad($nextNum, 3, '0', STR_PAD_LEFT);
                }

                $clientData['client_id'] = $newClientId;
                $clientData['uuid'] = Str::uuid()->toString();

                Client::create($clientData);

                $this->command->info("Created pending match client: {$clientData['name']}");
            } else {
                $this->command->info("Client already exists: {$clientData['name']}");
            }
        }

        $this->command->info('Pending matches seeder completed successfully!');
    }
}
