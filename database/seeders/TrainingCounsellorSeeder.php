<?php

namespace Database\Seeders;

use App\Models\TrainingCounsellor;
use Illuminate\Database\Seeder;
use Carbon\Carbon;
use Illuminate\Support\Str;

class TrainingCounsellorSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $tcs = [
            [
                'uuid' => Str::uuid(),
                'tc_id' => 'TC001',
                'name' => 'Sarah Johnson',
                'email' => 'sarah.j@vanquish.com',
                'phone' => '+44 7700 900201',
                'modality' => 'CBT',
                'status' => 'Active',
                'counsellor_type' => 'Trainee',
                'current_clients' => 3,
                'availability' => [
                    'Monday' => ['morning-early', 'morning-late'],
                    'Tuesday' => [],
                    'Wednesday' => ['afternoon-early'],
                    'Thursday' => [],
                    'Friday' => ['morning-late']
                ],
                'topics_with_experience' => ['Anxiety & Panic Attacks', 'Depression', 'Work-Related Stress', 'Relationship Issues', 'Low Self-Esteem'],
                'topics_not_ready_for' => ['Sexual Abuse/Assault', 'Domestic Violence', 'Suicidal Ideation'],
                'course' => 'Level 4 Diploma in Therapeutic Counselling',
                'institution' => 'CPCAB',
                'joined_date' => Carbon::parse('2024-08-15'),
                'last_activity' => now()->subHours(2),
                'qualified_form_completed' => false,
            ],
            [
                'uuid' => Str::uuid(),
                'tc_id' => 'TC002',
                'name' => 'David Chen',
                'email' => 'david.c@vanquish.com',
                'phone' => '+44 7700 900202',
                'modality' => 'Person-Centred',
                'status' => 'Active',
                'counsellor_type' => 'Trainee',
                'current_clients' => 4,
                'availability' => [
                    'Monday' => ['afternoon-early', 'evening'],
                    'Tuesday' => ['morning-early'],
                    'Wednesday' => [],
                    'Thursday' => ['afternoon-late', 'evening'],
                    'Friday' => []
                ],
                'topics_with_experience' => ['Childhood Trauma', 'PTSD (Post-Traumatic Stress Disorder)', 'Bereavement & Grief', 'Depression', 'Anxiety & Panic Attacks', 'Self-Harm'],
                'topics_not_ready_for' => ['Eating Disorders', 'OCD (Obsessive Compulsive Disorder)'],
                'course' => 'MSc Counselling Psychology',
                'institution' => 'University of London',
                'joined_date' => Carbon::parse('2024-06-20'),
                'last_activity' => now()->subHours(5),
                'qualified_form_completed' => false,
            ],
            [
                'uuid' => Str::uuid(),
                'tc_id' => 'TC003',
                'name' => 'Priya Patel',
                'email' => 'priya.p@vanquish.com',
                'phone' => '+44 7700 900203',
                'modality' => 'Integrative',
                'status' => 'Active',
                'counsellor_type' => 'Trainee',
                'current_clients' => 2,
                'availability' => [
                    'Monday' => ['morning-early', 'afternoon-early'],
                    'Tuesday' => ['morning-late', 'afternoon-early'],
                    'Wednesday' => ['morning-early'],
                    'Thursday' => [],
                    'Friday' => ['afternoon-late']
                ],
                'topics_with_experience' => ['Anxiety & Panic Attacks', 'Social Anxiety', 'Depression', 'Stress Management', 'Relationship Issues', 'Low Self-Esteem'],
                'topics_not_ready_for' => ['Sexual Abuse/Assault', 'Domestic Violence', 'Addiction & Substance Misuse', 'Suicidal Ideation', 'Self-Harm'],
                'course' => 'Level 5 Diploma in Integrative Counselling',
                'institution' => 'BACP Accredited College',
                'joined_date' => Carbon::parse('2024-09-10'),
                'last_activity' => now()->subDay(),
                'qualified_form_completed' => false,
            ],
            [
                'uuid' => Str::uuid(),
                'tc_id' => 'TC004',
                'name' => 'James Wilson',
                'email' => 'james.w@vanquish.com',
                'phone' => '+44 7700 900204',
                'modality' => 'CBT',
                'status' => 'At Capacity',
                'counsellor_type' => 'Qualified',
                'current_clients' => 6,
                'availability' => [
                    'Monday' => ['evening'],
                    'Tuesday' => ['afternoon-late', 'evening'],
                    'Wednesday' => ['evening'],
                    'Thursday' => ['evening'],
                    'Friday' => []
                ],
                'topics_with_experience' => ['Anxiety & Panic Attacks', 'Depression', 'OCD (Obsessive Compulsive Disorder)', 'Phobias', 'Health Anxiety'],
                'topics_not_ready_for' => ['Sexual Abuse/Assault', 'Domestic Violence'],
                'course' => 'Postgraduate Diploma in CBT',
                'institution' => 'Oxford Cognitive Therapy Centre',
                'joined_date' => Carbon::parse('2024-03-12'),
                'last_activity' => now()->subHours(3),
                'qualified_form_completed' => true,
                'legal_first_name' => 'James',
                'legal_last_name' => 'Wilson',
                'registered_address' => '123 High Street',
                'registered_city' => 'London',
                'registered_postcode' => 'SW1A 1AA',
                'has_supervisor' => 'Yes',
                'qualified_to_work_with' => ['Individuals', 'Couples'],
            ],
            [
                'uuid' => Str::uuid(),
                'tc_id' => 'TC005',
                'name' => 'Emily Roberts',
                'email' => 'emily.r@vanquish.com',
                'phone' => '+44 7700 900205',
                'modality' => 'Person-Centred',
                'status' => 'On Leave',
                'counsellor_type' => 'Trainee',
                'current_clients' => 0,
                'availability' => [
                    'Monday' => [],
                    'Tuesday' => [],
                    'Wednesday' => [],
                    'Thursday' => [],
                    'Friday' => []
                ],
                'topics_with_experience' => ['Depression', 'Anxiety & Panic Attacks', 'Bereavement & Grief', 'Relationship Issues', 'Stress Management'],
                'topics_not_ready_for' => ['Sexual Abuse/Assault', 'Domestic Violence', 'Suicidal Ideation', 'Eating Disorders'],
                'course' => 'Level 4 Counselling Skills',
                'institution' => 'Relate Training',
                'joined_date' => Carbon::parse('2024-07-05'),
                'last_activity' => now()->subWeeks(2),
                'qualified_form_completed' => false,
            ],
            [
                'uuid' => Str::uuid(),
                'tc_id' => 'TC006',
                'name' => 'Mohammed Ali',
                'email' => 'mohammed.a@vanquish.com',
                'phone' => '+44 7700 900206',
                'modality' => 'Integrative',
                'status' => 'Active',
                'counsellor_type' => 'Qualified',
                'current_clients' => 5,
                'availability' => [
                    'Monday' => ['afternoon-late'],
                    'Tuesday' => ['morning-early', 'morning-late'],
                    'Wednesday' => ['afternoon-early'],
                    'Thursday' => ['morning-late'],
                    'Friday' => ['morning-early']
                ],
                'topics_with_experience' => ['Racial & Cultural Identity', 'Depression', 'Anxiety & Panic Attacks', 'Family Conflicts', 'Stress Management'],
                'topics_not_ready_for' => ['Sexual Abuse/Assault', 'Eating Disorders'],
                'course' => 'MA Counselling and Psychotherapy',
                'institution' => 'University of Manchester',
                'joined_date' => Carbon::parse('2024-05-18'),
                'last_activity' => now()->subHours(4),
                'qualified_form_completed' => true,
                'legal_first_name' => 'Mohammed',
                'legal_last_name' => 'Ali',
                'registered_address' => '456 Park Avenue',
                'registered_city' => 'Manchester',
                'registered_postcode' => 'M1 1AA',
                'has_supervisor' => 'Yes',
                'qualified_to_work_with' => ['Individuals', 'Families'],
            ],
        ];

        foreach ($tcs as $tc) {
            TrainingCounsellor::updateOrCreate(
                ['email' => $tc['email']],
                $tc
            );
        }

        $this->command->info('Trainee Counsellors seeded successfully!');
    }
}

