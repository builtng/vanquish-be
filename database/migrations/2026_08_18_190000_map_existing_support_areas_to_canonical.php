<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $mapValue = function ($value) {
            $mapping = [
                // Old Client Support Areas -> Canonical Therapy Topics
                'Communication problems' => 'Relationship Issues',
                'People pleasing' => 'Low Self-Esteem',
                'Loneliness' => 'Social Anxiety',
                'Discrimination & Racism' => 'Racial & Cultural Identity',
                'Low mood' => 'Depression',
                'Stress' => 'Stress Management',
                'Low confidence' => 'Low Self-Esteem',
                'Family issues' => 'Family Conflicts',
                'Fear of intimacy' => 'Relationship Issues',
                'Personal development' => 'Low Self-Esteem',
                'Self-defeating behaviour' => 'Low Self-Esteem',
                'Low self-esteem' => 'Low Self-Esteem',
                'Low Self-esteem' => 'Low Self-Esteem',
                'Relationship problems' => 'Relationship Issues',
                'High sensitivity' => 'Anxiety & Panic Attacks',
                'Work-life balance' => 'Work-Related Stress',
                'Work-Life Balance' => 'Work-Related Stress',
                'Identity Issues' => 'Gender Identity Issues',

                // Variations / Old TC topics -> Canonical Therapy Topics
                'Anxiety' => 'Anxiety & Panic Attacks',
                'Anxiety Disorders' => 'Anxiety & Panic Attacks',
                'Panic Attacks' => 'Anxiety & Panic Attacks',
                'Health Anxiety' => 'Health Anxiety',
                'Social Anxiety' => 'Social Anxiety',
                'Depression' => 'Depression',
                'Stress Management' => 'Stress Management',
                'Work Stress' => 'Work-Related Stress',
                'Workplace Issues' => 'Work-Related Stress',
                'Work-Related Stress' => 'Work-Related Stress',
                'Relationship Issues' => 'Relationship Issues',
                'Family Conflicts' => 'Family Conflicts',
                'Family Therapy' => 'Family Conflicts',
                'Couples Therapy' => 'Relationship Issues',
                'Child Therapy' => 'Childhood Trauma',
                'Childhood Trauma' => 'Childhood Trauma',
                'Child Abuse' => 'Childhood Trauma',
                'Trauma' => 'Childhood Trauma',
                'Trauma/PTSD' => 'PTSD (Post-Traumatic Stress Disorder)',
                'PTSD' => 'PTSD (Post-Traumatic Stress Disorder)',
                'PTSD (Post-Traumatic Stress Disorder)' => 'PTSD (Post-Traumatic Stress Disorder)',
                'Grief & Loss' => 'Bereavement & Grief',
                'Grief and Bereavement' => 'Bereavement & Grief',
                'Bereavement' => 'Bereavement & Grief',
                'Bereavement & Grief' => 'Bereavement & Grief',
                'Self-Esteem' => 'Low Self-Esteem',
                'Low Self-Esteem' => 'Low Self-Esteem',
                'Anger Management' => 'Anger Management',
                'Addiction' => 'Addiction & Substance Misuse',
                'Substance Abuse' => 'Addiction & Substance Misuse',
                'Addiction & Substance Misuse' => 'Addiction & Substance Misuse',
                'Eating Disorders' => 'Eating Disorders',
                'OCD' => 'OCD (Obsessive Compulsive Disorder)',
                'OCD (Obsessive Compulsive Disorder)' => 'OCD (Obsessive Compulsive Disorder)',
                'Obsessive-Compulsive Disorder (OCD)' => 'OCD (Obsessive Compulsive Disorder)',
                'Bipolar Disorder' => 'Depression',
                'ADHD' => 'Stress Management',
                'Autism' => 'Social Anxiety',
                'LGBTQ+ Issues' => 'LGBTQ+ Issues',
                'Gender Identity Issues' => 'Gender Identity Issues',
                'Domestic Violence' => 'Domestic Violence',
                'Sexual Abuse' => 'Sexual Abuse/Assault',
                'Sexual Abuse/Assault' => 'Sexual Abuse/Assault',
                'Self-Harm' => 'Self-Harm',
                'Suicidal Ideation' => 'Suicidal Ideation',
                'Personality Disorders' => 'Depression',
                'Psychosis' => 'Depression',
                'Life Transitions' => 'Stress Management',
                'Cultural Identity' => 'Racial & Cultural Identity',
                'Racial & Cultural Identity' => 'Racial & Cultural Identity',
                'Parenting Issues' => 'Parenting Issues',
                'Phobias' => 'Phobias',
                'Infidelity' => 'Infidelity',
                'Body Image Issues' => 'Body Image Issues',
                'Abuse (Physical, Emotional, Sexual)' => 'Abuse (Physical, Emotional, Sexual)',
            ];

            if (isset($mapping[$value])) {
                return $mapping[$value];
            }

            foreach ($mapping as $k => $v) {
                if (strcasecmp($k, $value) === 0) {
                    return $v;
                }
            }

            return $value;
        };

        $mapArray = function ($arr) use ($mapValue) {
            if (!is_array($arr)) {
                return $arr;
            }
            $mapped = array_map($mapValue, $arr);
            return array_values(array_unique(array_filter($mapped)));
        };

        // 1. Clients
        $clients = DB::table('clients')->get();
        foreach ($clients as $client) {
            if ($client->primary_issues) {
                $decoded = json_decode($client->primary_issues, true);
                if (is_array($decoded)) {
                    $newValues = $mapArray($decoded);
                    DB::table('clients')->where('id', $client->id)->update([
                        'primary_issues' => json_encode($newValues),
                    ]);
                }
            }
        }

        // 2. Client Intake Forms
        $intakes = DB::table('client_intake_forms')->get();
        foreach ($intakes as $intake) {
            if ($intake->support_areas) {
                $decoded = json_decode($intake->support_areas, true);
                if (is_array($decoded)) {
                    $newValues = $mapArray($decoded);
                    DB::table('client_intake_forms')->where('id', $intake->id)->update([
                        'support_areas' => json_encode($newValues),
                    ]);
                }
            }
        }

        // 3. Training Counsellors
        $tcs = DB::table('training_counsellors')->get();
        foreach ($tcs as $tc) {
            $updates = [];
            if ($tc->topics_with_experience) {
                $decoded = json_decode($tc->topics_with_experience, true);
                if (is_array($decoded)) {
                    $updates['topics_with_experience'] = json_encode($mapArray($decoded));
                }
            }
            if ($tc->topics_not_ready_for) {
                $decoded = json_decode($tc->topics_not_ready_for, true);
                if (is_array($decoded)) {
                    $updates['topics_not_ready_for'] = json_encode($mapArray($decoded));
                }
            }
            if (!empty($updates)) {
                DB::table('training_counsellors')->where('id', $tc->id)->update($updates);
            }
        }

        // 4. TC Intake Forms
        $tcIntakes = DB::table('tc_intake_forms')->get();
        foreach ($tcIntakes as $tcIntake) {
            $updates = [];
            if ($tcIntake->topics_with_experience) {
                $decoded = json_decode($tcIntake->topics_with_experience, true);
                if (is_array($decoded)) {
                    $updates['topics_with_experience'] = json_encode($mapArray($decoded));
                }
            }
            if ($tcIntake->topics_not_ready_for) {
                $decoded = json_decode($tcIntake->topics_not_ready_for, true);
                if (is_array($decoded)) {
                    $updates['topics_not_ready_for'] = json_encode($mapArray($decoded));
                }
            }
            if (!empty($updates)) {
                DB::table('tc_intake_forms')->where('id', $tcIntake->id)->update($updates);
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // No lossy reverse mapping needed
    }
};
