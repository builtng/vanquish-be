<?php

namespace App\Services;

use App\Models\MatchingAlgorithmSetting;
use App\Models\TrainingCounsellor;

/**
 * Computes a 0-100 match score (plus per-category breakdown) between
 * client requirements and a counsellor.
 *
 * Uses VQT's six admin-configurable weights exactly from MatchingAlgorithmSetting:
 * Availability (40), Modality (20), Gender (20), Ethnicity (10),
 * Sexual Orientation (5), Age (5).
 */
class MatchScoringService
{
    /**
     * @param array $clientData Accumulated intake form fields: support_areas,
     *              availability, gender_preference, age_preference,
     *              ethnicity_preference, orientation_preference, modality_preference.
     * @param TrainingCounsellor $tc
     * @return array{score:int, breakdown: array<int, array{label:string, weight:float, score:int}>}
     */
    public function score(array $clientData, TrainingCounsellor $tc): array
    {
        $weights = $this->resolveWeights();

        $categories = [
            ['label' => 'Availability Match', 'weight' => $weights['availability'], 'score' => $this->scoreAvailability($clientData, $tc)],
            ['label' => 'Modality Match', 'weight' => $weights['modality'], 'score' => $this->scoreModality($clientData, $tc)],
            ['label' => 'Gender Preference', 'weight' => $weights['gender'], 'score' => $this->scorePreference($clientData['gender_preference'] ?? null, $tc->gender)],
            ['label' => 'Ethnicity Preference', 'weight' => $weights['ethnicity'], 'score' => $this->scorePreference($clientData['ethnicity_preference'] ?? null, $tc->ethnicity)],
            ['label' => 'Sexual Orientation Preference', 'weight' => $weights['sexual_orientation'], 'score' => $this->scorePreference($clientData['orientation_preference'] ?? null, $tc->sexual_orientation)],
            ['label' => 'Age Preference', 'weight' => $weights['age'], 'score' => $this->scoreAgePreference($clientData['age_preference'] ?? null, $tc->age)],
        ];

        $totalWeight = array_sum(array_column($categories, 'weight')) ?: 1;
        $overall = 0.0;
        foreach ($categories as $category) {
            $overall += ($category['weight'] / $totalWeight) * $category['score'];
        }

        if ($this->isExcluded($clientData, $tc)) {
            return [
                'score' => 0,
                'is_excluded' => true,
                'exclusion_reason' => 'Counsellor has indicated they are not ready for this support area',
                'breakdown' => array_map(fn ($c) => [
                    'label' => $c['label'],
                    'weight' => round($c['weight'], 2),
                    'score' => 0,
                ], $categories),
            ];
        }

        return [
            'score' => (int) round($overall),
            'is_excluded' => false,
            'breakdown' => array_map(fn ($c) => [
                'label' => $c['label'],
                'weight' => round($c['weight'], 2),
                'score' => (int) round($c['score']),
            ], $categories),
        ];
    }

    /**
     * Determines whether a counsellor should be excluded due to having
     * any topic in topics_not_ready_for matching the client's support areas.
     * Matches on exact area names only (trimmed, case-insensitive).
     * Substring / partial word matching is NOT used.
     */
    public function isExcluded(array $clientData, TrainingCounsellor $tc): bool
    {
        $rawClient = $clientData['support_areas'] ?? $clientData['primary_issues'] ?? $clientData['primaryIssues'] ?? [];
        if (is_string($rawClient)) {
            $rawClient = json_decode($rawClient, true) ?: [$rawClient];
        }
        $clientAreas = array_filter(array_map('trim', (array) $rawClient));

        $rawNotReady = $tc->topics_not_ready_for ?? $tc->topicsNotReadyFor ?? [];
        if (is_string($rawNotReady)) {
            $rawNotReady = json_decode($rawNotReady, true) ?: [$rawNotReady];
        }
        $notReadyAreas = array_filter(array_map('trim', (array) $rawNotReady));

        if (empty($clientAreas) || empty($notReadyAreas)) {
            return false;
        }

        foreach ($clientAreas as $ca) {
            $cleanCa = strtolower(trim($ca));
            if ($cleanCa === '') {
                continue;
            }
            foreach ($notReadyAreas as $nr) {
                $cleanNr = strtolower(trim($nr));
                if ($cleanNr === '') {
                    continue;
                }
                // Exact area name match only (trimmed, case-insensitive)
                if ($cleanCa === $cleanNr) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * @return array{availability:float, modality:float, gender:float, ethnicity:float, sexual_orientation:float, age:float}
     */
    private function resolveWeights(): array
    {
        $settings = MatchingAlgorithmSetting::first();

        return [
            'availability' => (float) ($settings->availability_weight ?? 40),
            'modality' => (float) ($settings->modality_weight ?? 20),
            'gender' => (float) ($settings->gender_weight ?? 20),
            'ethnicity' => (float) ($settings->ethnicity_weight ?? 10),
            'sexual_orientation' => (float) ($settings->sexual_orientation_weight ?? 5),
            'age' => (float) ($settings->age_weight ?? 5),
        ];
    }

    private const ABSTRACT_SLOT_EXPANSIONS = [
        'morning-early' => ['10am-1050am'],
        'morning-late' => ['11am-1150am', '12pm-1250pm'],
        'afternoon-early' => ['1pm-150pm', '2pm-250pm', '3pm-350pm'],
        'afternoon-late' => ['4pm-450pm'],
        'evening' => ['5pm-550pm', '6pm-650pm'],
        'morning' => ['10am-1050am', '11am-1150am'],
        'afternoon' => ['12pm-1250pm', '1pm-150pm', '2pm-250pm', '3pm-350pm', '4pm-450pm'],
    ];

    private function expandSlot(string $slot): array
    {
        $clean = strtolower(preg_replace('/\s+/', '', $slot));
        return self::ABSTRACT_SLOT_EXPANSIONS[$clean] ?? [$clean];
    }

    private function scoreAvailability(array $clientData, TrainingCounsellor $tc): int
    {
        $rawClient = $clientData['availability'] ?? [];
        if (is_string($rawClient)) {
            $rawClient = json_decode($rawClient, true) ?: [];
        }
        $clientAvailability = is_array($rawClient) ? array_change_key_case($rawClient, CASE_LOWER) : [];

        $rawTc = $tc->availability ?? [];
        if (is_string($rawTc)) {
            $rawTc = json_decode($rawTc, true) ?: [];
        }
        $tcAvailability = is_array($rawTc) ? array_change_key_case($rawTc, CASE_LOWER) : [];

        $clientSlots = [];
        foreach ($clientAvailability as $day => $times) {
            foreach ((array) $times as $time) {
                foreach ($this->expandSlot((string) $time) as $exp) {
                    $clientSlots[] = $day . '|' . $exp;
                }
            }
        }
        $clientSlots = array_unique($clientSlots);

        if (empty($clientSlots)) {
            return 100;
        }

        $tcSlots = [];
        foreach ($tcAvailability as $day => $times) {
            foreach ((array) $times as $time) {
                foreach ($this->expandSlot((string) $time) as $exp) {
                    $tcSlots[] = $day . '|' . $exp;
                }
            }
        }
        $tcSlots = array_unique($tcSlots);

        $matched = count(array_intersect($clientSlots, $tcSlots));

        return (int) round(($matched / count($clientSlots)) * 100);
    }

    private function scoreAreasOfSupport(array $clientData, TrainingCounsellor $tc): int
    {
        $clientAreas = array_map('strtolower', $clientData['support_areas'] ?? []);
        $tcAreas = array_map('strtolower', $tc->topics_with_experience ?? []);

        if (empty($clientAreas)) {
            return 100;
        }

        $matched = count(array_intersect($clientAreas, $tcAreas));

        return (int) round(($matched / count($clientAreas)) * 100);
    }

    private function scoreSpecialty(array $clientData, TrainingCounsellor $tc): int
    {
        if (empty($clientData['is_couples'])) {
            return 100;
        }

        $qualifiedFor = array_map('strtolower', $tc->qualified_to_work_with ?? []);

        return (in_array('couples', $qualifiedFor) || in_array('families', $qualifiedFor)) ? 100 : 0;
    }

    private function scorePreference(?string $preference, ?string $tcValue): int
    {
        if (empty($preference) || strtolower($preference) === 'no preference') {
            return 100;
        }

        return (strtolower($preference) === strtolower((string) $tcValue)) ? 100 : 0;
    }

    private function scoreAgePreference(?string $preference, $tcAge): int
    {
        if (empty($preference) || strtolower($preference) === 'no preference' || $tcAge === null) {
            return 100;
        }

        // Preference stored as an age-bracket string (e.g. "25-35", "45+");
        // treat anything we can't parse as satisfied rather than penalizing.
        if (preg_match('/^(\d+)\s*-\s*(\d+)$/', trim($preference), $m)) {
            return ($tcAge >= (int) $m[1] && $tcAge <= (int) $m[2]) ? 100 : 0;
        }

        if (preg_match('/^(\d+)\s*\+$/', trim($preference), $m)) {
            return ($tcAge >= (int) $m[1]) ? 100 : 0;
        }

        return 100;
    }

    private function scoreModality(array $clientData, TrainingCounsellor $tc): int
    {
        $pref = $clientData['modality_preference'] ?? $clientData['modality'] ?? null;
        if (empty($pref) || in_array(strtolower(trim($pref)), ['unsure', 'no preference', 'any', 'all'])) {
            return 100;
        }

        $tcModality = (string) ($tc->modality ?? '');
        if (empty($tcModality)) {
            return 0;
        }

        $needle = strtolower(trim($pref));
        $haystack = strtolower($tcModality);

        if (str_contains($haystack, $needle)) {
            return 100;
        }

        if (($needle === 'integrative therapy' || $needle === 'integrative') && str_contains($haystack, 'integrative')) {
            return 100;
        }

        return 0;
    }
}
