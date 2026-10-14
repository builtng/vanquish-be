<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class MapSupportAreasToCanonical extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'vqt:map-support-areas {--dry-run : Run mapping analysis without writing changes to the database}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Map stored therapy topics/support areas to VQT 25 canonical terms and remove unmapped terms';

    /**
     * VQT's 25 Canonical Terms (canonical list)
     */
    public const CANONICAL_TERMS = [
        'Communication problems',
        'Low mood/depression',
        'Low confidence/self-esteem',
        'Fear of intimacy/loneliness',
        'Self-defeating behaviour',
        'Relationship/family problems',
        'People pleasing',
        'Discrimination/racial and cultural identity',
        'Anxiety/panic attacks',
        'High sensitivity',
        'Abuse (physical/emotional/sexual)',
        'Anger management issues',
        'Bereavement and grief',
        'Childhood trauma',
        'Domestic violence',
        'LGBTQ+/gender identity worries',
        'OCD (obsessive compulsive disorder)',
        'Phobias',
        'Self-harm',
        'Suicidal ideation',
        'Addiction and substance misuse',
        'Body image issues',
        'Eating disorders',
        'PTSD (post-traumatic stress disorder)',
        'Work related stress',
    ];

    /**
     * Equivalent mappings for terms with a direct canonical counterpart.
     * Any term not in CANONICAL_TERMS or this mapping table is removed.
     */
    public const EQUIVALENTS = [
        // Abuse
        'Abuse (Physical, Emotional, Sexual)' => 'Abuse (physical/emotional/sexual)',
        'Abuse (physical, emotional, sexual)' => 'Abuse (physical/emotional/sexual)',
        'Sexual Abuse/Assault' => 'Abuse (physical/emotional/sexual)',
        'Sexual Abuse' => 'Abuse (physical/emotional/sexual)',

        // Addiction
        'Addiction & Substance Misuse' => 'Addiction and substance misuse',
        'Addiction' => 'Addiction and substance misuse',

        // Anger
        'Anger Management' => 'Anger management issues',

        // Anxiety
        'Anxiety & Panic Attacks' => 'Anxiety/panic attacks',
        'Anxiety' => 'Anxiety/panic attacks',

        // Bereavement
        'Bereavement & Grief' => 'Bereavement and grief',
        'Bereavement' => 'Bereavement and grief',
        'Grief & Loss' => 'Bereavement and grief',

        // Body image
        'Body Image Issues' => 'Body image issues',

        // Trauma
        'Childhood Trauma' => 'Childhood trauma',

        // Depression
        'Depression' => 'Low mood/depression',
        'Low mood' => 'Low mood/depression',

        // Domestic violence
        'Domestic Violence' => 'Domestic violence',

        // Eating disorders
        'Eating Disorders' => 'Eating disorders',

        // Relationship / family
        'Family Conflicts' => 'Relationship/family problems',
        'Relationship Issues' => 'Relationship/family problems',
        'Relationship problems' => 'Relationship/family problems',

        // LGBTQ+
        'Gender Identity Issues' => 'LGBTQ+/gender identity worries',
        'LGBTQ+ Issues' => 'LGBTQ+/gender identity worries',

        // Self-esteem
        'Low Self-Esteem' => 'Low confidence/self-esteem',
        'Low confidence' => 'Low confidence/self-esteem',

        // OCD
        'OCD (Obsessive Compulsive Disorder)' => 'OCD (obsessive compulsive disorder)',
        'OCD' => 'OCD (obsessive compulsive disorder)',

        // Phobias
        'Phobias' => 'Phobias',

        // PTSD
        'PTSD (Post-Traumatic Stress Disorder)' => 'PTSD (post-traumatic stress disorder)',
        'PTSD' => 'PTSD (post-traumatic stress disorder)',

        // Discrimination
        'Racial & Cultural Identity' => 'Discrimination/racial and cultural identity',
        'Discrimination & Racism' => 'Discrimination/racial and cultural identity',

        // Self-harm / suicidal
        'Self-Harm' => 'Self-harm',
        'Suicidal Ideation' => 'Suicidal ideation',

        // Stress / work
        'Work-Related Stress' => 'Work related stress',
        'Work Stress' => 'Work related stress',

        // Intimacy / loneliness
        'Fear of intimacy' => 'Fear of intimacy/loneliness',
        'Loneliness' => 'Fear of intimacy/loneliness',
    ];

    /**
     * Map a single term to a canonical term or null (removed).
     */
    public static function mapTerm(?string $term): ?string
    {
        if ($term === null) {
            return null;
        }

        $trimmed = trim($term);
        if ($trimmed === '') {
            return null;
        }

        // 1. Direct canonical check (case-insensitive)
        foreach (self::CANONICAL_TERMS as $canonical) {
            if (strcasecmp($canonical, $trimmed) === 0) {
                return $canonical;
            }
        }

        // 2. Equivalent mapping check (case-insensitive)
        foreach (self::EQUIVALENTS as $old => $canonical) {
            if (strcasecmp($old, $trimmed) === 0) {
                return $canonical;
            }
        }

        // 3. Any value with no equivalent in VQT's 25 terms is REMOVED
        return null;
    }

    /**
     * Map an array of terms, dropping removed values and deduplicating.
     */
    public static function mapArray(array $arr, array &$stats): array
    {
        $mapped = [];
        foreach ($arr as $val) {
            if (!is_string($val)) {
                continue;
            }
            $target = self::mapTerm($val);
            $action = $target ?? 'removed';

            if (!isset($stats[$val])) {
                $stats[$val] = [
                    'old' => $val,
                    'new' => $action,
                    'count' => 0,
                ];
            }
            $stats[$val]['count']++;

            if ($target !== null) {
                $mapped[] = $target;
            }
        }

        return array_values(array_unique($mapped));
    }

    /**
     * Map a string value that might be JSON or comma-separated.
     */
    public static function mapValue(mixed $value, array &$stats, bool &$changed): mixed
    {
        if ($value === null || $value === '') {
            $changed = false;
            return $value;
        }

        $isJson = false;
        $array = null;

        if (is_string($value)) {
            $decoded = json_decode($value, true);
            if (is_string($decoded)) {
                $decoded2 = json_decode($decoded, true);
                if (is_array($decoded2)) {
                    $decoded = $decoded2;
                }
            }
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                $isJson = true;
                $array = $decoded;
            } else {
                // Comma-separated string
                $array = array_map('trim', explode(',', $value));
            }
        } elseif (is_array($value)) {
            $array = $value;
        } else {
            $changed = false;
            return $value;
        }

        $originalNormalized = array_values(array_unique(array_filter($array, 'is_string')));
        $mapped = self::mapArray($originalNormalized, $stats);

        $changed = ($originalNormalized !== $mapped);

        if ($isJson) {
            return json_encode($mapped);
        }

        if (is_array($value)) {
            return $mapped;
        }

        return implode(', ', $mapped);
    }

    /**
     * Execute the mapping logic and return structured report.
     */
    public static function runMapping(bool $dryRun = true): array
    {
        $stats = [];
        $tableCounts = [];

        // 1. Clients (primary_issues)
        if (Schema::hasTable('clients') && Schema::hasColumn('clients', 'primary_issues')) {
            $tableCounts['clients'] = ['inspected' => 0, 'updated' => 0];
            $rows = DB::table('clients')->whereNotNull('primary_issues')->get(['id', 'primary_issues']);
            foreach ($rows as $row) {
                $tableCounts['clients']['inspected']++;
                $changed = false;
                $newVal = self::mapValue($row->primary_issues, $stats, $changed);
                if ($changed) {
                    $tableCounts['clients']['updated']++;
                    if (!$dryRun) {
                        DB::table('clients')->where('id', $row->id)->update(['primary_issues' => $newVal]);
                    }
                }
            }
        }

        // 2. Client Intake Forms (support_areas)
        if (Schema::hasTable('client_intake_forms') && Schema::hasColumn('client_intake_forms', 'support_areas')) {
            $tableCounts['client_intake_forms'] = ['inspected' => 0, 'updated' => 0];
            $rows = DB::table('client_intake_forms')->whereNotNull('support_areas')->get(['id', 'support_areas']);
            foreach ($rows as $row) {
                $tableCounts['client_intake_forms']['inspected']++;
                $changed = false;
                $newVal = self::mapValue($row->support_areas, $stats, $changed);
                if ($changed) {
                    $tableCounts['client_intake_forms']['updated']++;
                    if (!$dryRun) {
                        DB::table('client_intake_forms')->where('id', $row->id)->update(['support_areas' => $newVal]);
                    }
                }
            }
        }

        // 3. Training Counsellors (topics_with_experience, topics_not_ready_for)
        if (Schema::hasTable('training_counsellors')) {
            $tableCounts['training_counsellors'] = ['inspected' => 0, 'updated' => 0];
            $rows = DB::table('training_counsellors')->get(['id', 'topics_with_experience', 'topics_not_ready_for']);
            foreach ($rows as $row) {
                $tableCounts['training_counsellors']['inspected']++;
                $updates = [];

                if ($row->topics_with_experience) {
                    $changed = false;
                    $newVal = self::mapValue($row->topics_with_experience, $stats, $changed);
                    if ($changed) {
                        $updates['topics_with_experience'] = $newVal;
                    }
                }

                if ($row->topics_not_ready_for) {
                    $changed = false;
                    $newVal = self::mapValue($row->topics_not_ready_for, $stats, $changed);
                    if ($changed) {
                        $updates['topics_not_ready_for'] = $newVal;
                    }
                }

                if (!empty($updates)) {
                    $tableCounts['training_counsellors']['updated']++;
                    if (!$dryRun) {
                        DB::table('training_counsellors')->where('id', $row->id)->update($updates);
                    }
                }
            }
        }

        // 4. TC Intake Forms (topics_with_experience, topics_not_ready_for)
        if (Schema::hasTable('tc_intake_forms')) {
            $tableCounts['tc_intake_forms'] = ['inspected' => 0, 'updated' => 0];
            $rows = DB::table('tc_intake_forms')->get(['id', 'topics_with_experience', 'topics_not_ready_for']);
            foreach ($rows as $row) {
                $tableCounts['tc_intake_forms']['inspected']++;
                $updates = [];

                if ($row->topics_with_experience) {
                    $changed = false;
                    $newVal = self::mapValue($row->topics_with_experience, $stats, $changed);
                    if ($changed) {
                        $updates['topics_with_experience'] = $newVal;
                    }
                }

                if ($row->topics_not_ready_for) {
                    $changed = false;
                    $newVal = self::mapValue($row->topics_not_ready_for, $stats, $changed);
                    if ($changed) {
                        $updates['topics_not_ready_for'] = $newVal;
                    }
                }

                if (!empty($updates)) {
                    $tableCounts['tc_intake_forms']['updated']++;
                    if (!$dryRun) {
                        DB::table('tc_intake_forms')->where('id', $row->id)->update($updates);
                    }
                }
            }
        }

        // 5. Trainee Applications (areas_of_experience, personal_development_areas)
        if (Schema::hasTable('trainee_applications')) {
            $tableCounts['trainee_applications'] = ['inspected' => 0, 'updated' => 0];
            $hasExp = Schema::hasColumn('trainee_applications', 'areas_of_experience');
            $hasDev = Schema::hasColumn('trainee_applications', 'personal_development_areas');

            if ($hasExp || $hasDev) {
                $cols = ['id'];
                if ($hasExp) $cols[] = 'areas_of_experience';
                if ($hasDev) $cols[] = 'personal_development_areas';

                $rows = DB::table('trainee_applications')->get($cols);
                foreach ($rows as $row) {
                    $tableCounts['trainee_applications']['inspected']++;
                    $updates = [];

                    if ($hasExp && !empty($row->areas_of_experience)) {
                        $changed = false;
                        $newVal = self::mapValue($row->areas_of_experience, $stats, $changed);
                        if ($changed) {
                            $updates['areas_of_experience'] = $newVal;
                        }
                    }

                    if ($hasDev && !empty($row->personal_development_areas)) {
                        $changed = false;
                        $newVal = self::mapValue($row->personal_development_areas, $stats, $changed);
                        if ($changed) {
                            $updates['personal_development_areas'] = $newVal;
                        }
                    }

                    if (!empty($updates)) {
                        $tableCounts['trainee_applications']['updated']++;
                        if (!$dryRun) {
                            DB::table('trainee_applications')->where('id', $row->id)->update($updates);
                        }
                    }
                }
            }
        }

        // 6. QC Applications (answers, other_experience_areas)
        if (Schema::hasTable('qc_applications')) {
            $tableCounts['qc_applications'] = ['inspected' => 0, 'updated' => 0];
            $hasAnswers = Schema::hasColumn('qc_applications', 'answers');
            $hasOther = Schema::hasColumn('qc_applications', 'other_experience_areas');

            $cols = ['id'];
            if ($hasAnswers) $cols[] = 'answers';
            if ($hasOther) $cols[] = 'other_experience_areas';

            $rows = DB::table('qc_applications')->get($cols);
            foreach ($rows as $row) {
                $tableCounts['qc_applications']['inspected']++;
                $updates = [];

                if ($hasAnswers && !empty($row->answers)) {
                    $answers = json_decode($row->answers, true);
                    if (is_array($answers)) {
                        $answersChanged = false;
                        foreach (['experience_areas', 'topics_with_experience', 'topics_not_ready_for'] as $k) {
                            if (isset($answers[$k]) && is_array($answers[$k])) {
                                $original = $answers[$k];
                                $mapped = self::mapArray($original, $stats);
                                if ($original !== $mapped) {
                                    $answers[$k] = $mapped;
                                    $answersChanged = true;
                                }
                            }
                        }
                        if ($answersChanged) {
                            $updates['answers'] = json_encode($answers);
                        }
                    }
                }

                if ($hasOther && !empty($row->other_experience_areas)) {
                    $changed = false;
                    $newVal = self::mapValue($row->other_experience_areas, $stats, $changed);
                    if ($changed) {
                        $updates['other_experience_areas'] = $newVal;
                    }
                }

                if (!empty($updates)) {
                    $tableCounts['qc_applications']['updated']++;
                    if (!$dryRun) {
                        DB::table('qc_applications')->where('id', $row->id)->update($updates);
                    }
                }
            }
        }

        return [
            'dry_run' => $dryRun,
            'stats' => $stats,
            'table_counts' => $tableCounts,
        ];
    }

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');

        $this->info("================================================================================");
        $this->info($dryRun ? " [DRY RUN] VQT THERAPY TOPICS CANONICAL MAPPING ANALYSIS" : " [LIVE RUN] APPLYING VQT CANONICAL MAPPING TO DATABASE");
        $this->info("================================================================================");
        $this->line("Canonical list terms: " . count(self::CANONICAL_TERMS) . " terms.");
        $this->line("Policy: Unmapped terms with no VQT equivalent will be marked as 'removed'.");
        $this->newLine();

        $results = self::runMapping($dryRun);

        $tableData = [];
        foreach ($results['stats'] as $item) {
            $tableData[] = [
                $item['old'],
                $item['new'] === 'removed' ? '<fg=red>removed</>' : "<fg=green>{$item['new']}</>",
                $item['count'],
            ];
        }

        usort($tableData, function ($a, $b) {
            // Put 'removed' items first or sort by count desc
            return $b[2] <=> $a[2];
        });

        $this->table(['Old Stored Value', 'New Value / Action', 'Occurrences'], $tableData);

        $this->newLine();
        $this->info("--------------------------------------------------------------------------------");
        $this->info(" ROW COUNTS PER TABLE");
        $this->info("--------------------------------------------------------------------------------");

        $rowCountRows = [];
        $totalInspected = 0;
        $totalUpdated = 0;

        foreach ($results['table_counts'] as $table => $c) {
            $rowCountRows[] = [
                $table,
                $c['inspected'],
                $c['updated'],
            ];
            $totalInspected += $c['inspected'];
            $totalUpdated += $c['updated'];
        }

        $rowCountRows[] = ['TOTAL', $totalInspected, $totalUpdated];

        $this->table(['Table Name', 'Rows Inspected', $dryRun ? 'Rows Needing Update' : 'Rows Updated'], $rowCountRows);

        $this->newLine();
        if ($dryRun) {
            $this->warn(">>> DRY RUN COMPLETE: No database modifications were made. <<<");
            $this->line("To apply these changes after Victor's approval, run without the --dry-run option.");
        } else {
            $this->info(">>> LIVE RUN COMPLETE: Database successfully updated to VQT canonical terms. <<<");
        }

        return Command::SUCCESS;
    }
}
