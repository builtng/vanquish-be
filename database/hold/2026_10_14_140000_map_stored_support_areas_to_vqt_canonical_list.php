<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * NOTE: Do not run on live database until Victor approves the mapping!
     */
    public function up(): void
    {
        $mapValue = function ($value) {
            $mapping = [
                // Old stored topic labels -> New VQT Canonical Topics
                'Abuse (Physical, Emotional, Sexual)' => 'Abuse (physical/emotional/sexual)',
                'Sexual Abuse/Assault' => 'Abuse (physical/emotional/sexual)',
                'Sexual Abuse' => 'Abuse (physical/emotional/sexual)',
                'Addiction & Substance Misuse' => 'Addiction and substance misuse',
                'Addiction' => 'Addiction and substance misuse',
                'Anger Management' => 'Anger management issues',
                'Anxiety & Panic Attacks' => 'Anxiety/panic attacks',
                'Anxiety' => 'Anxiety/panic attacks',
                'Bereavement & Grief' => 'Bereavement and grief',
                'Bereavement' => 'Bereavement and grief',
                'Grief & Loss' => 'Bereavement and grief',
                'Body Image Issues' => 'Body image issues',
                'Childhood Trauma' => 'Childhood trauma',
                'Depression' => 'Low mood/depression',
                'Low mood' => 'Low mood/depression',
                'Domestic Violence' => 'Domestic violence',
                'Eating Disorders' => 'Eating disorders',
                'Family Conflicts' => 'Relationship/family problems',
                'Relationship Issues' => 'Relationship/family problems',
                'Relationship problems' => 'Relationship/family problems',
                'Gender Identity Issues' => 'LGBTQ+/gender identity worries',
                'LGBTQ+ Issues' => 'LGBTQ+/gender identity worries',
                'Low Self-Esteem' => 'Low confidence/self-esteem',
                'Low confidence' => 'Low confidence/self-esteem',
                'OCD (Obsessive Compulsive Disorder)' => 'OCD (obsessive compulsive disorder)',
                'OCD' => 'OCD (obsessive compulsive disorder)',
                'Phobias' => 'Phobias',
                'PTSD (Post-Traumatic Stress Disorder)' => 'PTSD (post-traumatic stress disorder)',
                'PTSD' => 'PTSD (post-traumatic stress disorder)',
                'Racial & Cultural Identity' => 'Discrimination/racial and cultural identity',
                'Discrimination & Racism' => 'Discrimination/racial and cultural identity',
                'Self-Harm' => 'Self-harm',
                'Suicidal Ideation' => 'Suicidal ideation',
                'Work-Related Stress' => 'Work related stress',
                'Work Stress' => 'Work related stress',

                // Direct VQT Canonical list mappings (identity / normalization)
                'Communication problems' => 'Communication problems',
                'Low mood/depression' => 'Low mood/depression',
                'Low confidence/self-esteem' => 'Low confidence/self-esteem',
                'Fear of intimacy/loneliness' => 'Fear of intimacy/loneliness',
                'Fear of intimacy' => 'Fear of intimacy/loneliness',
                'Loneliness' => 'Fear of intimacy/loneliness',
                'Self-defeating behaviour' => 'Self-defeating behaviour',
                'Relationship/family problems' => 'Relationship/family problems',
                'People pleasing' => 'People pleasing',
                'Discrimination/racial and cultural identity' => 'Discrimination/racial and cultural identity',
                'Anxiety/panic attacks' => 'Anxiety/panic attacks',
                'High sensitivity' => 'High sensitivity',
                'Abuse (physical/emotional/sexual)' => 'Abuse (physical/emotional/sexual)',
                'Anger management issues' => 'Anger management issues',
                'Bereavement and grief' => 'Bereavement and grief',
                'Childhood trauma' => 'Childhood trauma',
                'Domestic violence' => 'Domestic violence',
                'LGBTQ+/gender identity worries' => 'LGBTQ+/gender identity worries',
                'OCD (obsessive compulsive disorder)' => 'OCD (obsessive compulsive disorder)',
                'Self-harm' => 'Self-harm',
                'Suicidal ideation' => 'Suicidal ideation',
                'Addiction and substance misuse' => 'Addiction and substance misuse',
                'Body image issues' => 'Body image issues',
                'Eating disorders' => 'Eating disorders',
                'PTSD (post-traumatic stress disorder)' => 'PTSD (post-traumatic stress disorder)',
                'Work related stress' => 'Work related stress',
            ];

            if (isset($mapping[$value])) {
                return $mapping[$value];
            }

            foreach ($mapping as $k => $v) {
                if (strcasecmp($k, $value) === 0) {
                    return $v;
                }
            }

            // Unmapped values are preserved as-is without inventing mappings
            return $value;
        };

        $mapArray = function ($arr) use ($mapValue) {
            if (!is_array($arr)) {
                return $arr;
            }
            $mapped = array_map($mapValue, $arr);
            return array_values(array_unique(array_filter($mapped)));
        };

        // 1. Clients (primary_issues)
        $clients = DB::table('clients')->whereNotNull('primary_issues')->get();
        foreach ($clients as $client) {
            $decoded = json_decode($client->primary_issues, true);
            if (is_array($decoded)) {
                DB::table('clients')->where('id', $client->id)->update([
                    'primary_issues' => json_encode($mapArray($decoded)),
                ]);
            }
        }

        // 2. Client Intake Forms (support_areas)
        $intakes = DB::table('client_intake_forms')->whereNotNull('support_areas')->get();
        foreach ($intakes as $intake) {
            $decoded = json_decode($intake->support_areas, true);
            if (is_array($decoded)) {
                DB::table('client_intake_forms')->where('id', $intake->id)->update([
                    'support_areas' => json_encode($mapArray($decoded)),
                ]);
            }
        }

        // 3. Training Counsellors (topics_with_experience, topics_not_ready_for)
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

        // 4. TC Intake Forms (topics_with_experience, topics_not_ready_for)
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
        // No lossy reverse mapping
    }
};
