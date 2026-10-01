<?php

namespace App\Services;

use App\Models\MatchingAlgorithmSetting;
use App\Models\TrainingCounsellor;

/**
 * Computes a 0-100 client-facing match score (plus per-category breakdown)
 * between an in-progress intake submission and a Qualified counsellor, for
 * the Mid Range / Coaching & Counselling "Filtered Counsellors" step.
 *
 * Reads the admin-configurable weights from MatchingAlgorithmSetting for the
 * six categories it already defines (availability, modality, gender,
 * ethnicity, sexual_orientation, age), rescaled to make room for two
 * additional categories the settings model doesn't cover yet — areas of
 * support and specialty (couples/family capability) — at fixed weights.
 * This is separate, new code: it does not read from or modify the staff
 * dashboard's existing client-side matching algorithm.
 */
class MatchScoringService
{
    private const AREAS_OF_SUPPORT_WEIGHT = 15.0;
    private const SPECIALTY_WEIGHT = 10.0;

    /**
     * @param array $clientData Accumulated intake form fields: support_areas,
     *              availability, gender_preference, age_preference,
     *              ethnicity_preference, orientation_preference, is_couples.
     * @param TrainingCounsellor $tc
     * @return array{score:int, breakdown: array<int, array{label:string, weight:float, score:int}>}
     */
    public function score(array $clientData, TrainingCounsellor $tc): array
    {
        $weights = $this->resolveWeights();

        $categories = [
            ['label' => 'Availability Match', 'weight' => $weights['availability'], 'score' => $this->scoreAvailability($clientData, $tc)],
            ['label' => 'Areas of Support Match', 'weight' => self::AREAS_OF_SUPPORT_WEIGHT, 'score' => $this->scoreAreasOfSupport($clientData, $tc)],
            ['label' => 'Modality Match', 'weight' => $weights['modality'], 'score' => 100],
            ['label' => 'Specialty Match', 'weight' => self::SPECIALTY_WEIGHT, 'score' => $this->scoreSpecialty($clientData, $tc)],
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

        return [
            'score' => (int) round($overall),
            'breakdown' => array_map(fn ($c) => [
                'label' => $c['label'],
                'weight' => round($c['weight'], 2),
                'score' => (int) round($c['score']),
            ], $categories),
        ];
    }

    /**
     * @return array{availability:float, modality:float, gender:float, ethnicity:float, sexual_orientation:float, age:float}
     */
    private function resolveWeights(): array
    {
        $settings = MatchingAlgorithmSetting::first();

        $raw = [
            'availability' => (float) ($settings->availability_weight ?? 40),
            'modality' => (float) ($settings->modality_weight ?? 20),
            'gender' => (float) ($settings->gender_weight ?? 20),
            'ethnicity' => (float) ($settings->ethnicity_weight ?? 10),
            'sexual_orientation' => (float) ($settings->sexual_orientation_weight ?? 5),
            'age' => (float) ($settings->age_weight ?? 5),
        ];

        $rawTotal = array_sum($raw) ?: 100;
        // Rescale the six configured weights to make room for the two fixed
        // categories (areas of support + specialty) while keeping their
        // relative proportions intact.
        $remaining = 100 - self::AREAS_OF_SUPPORT_WEIGHT - self::SPECIALTY_WEIGHT;
        foreach ($raw as $key => $value) {
            $raw[$key] = ($value / $rawTotal) * $remaining;
        }

        return $raw;
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
}
