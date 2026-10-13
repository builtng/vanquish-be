<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Consultation;
use App\Models\TrainingCounsellor;
use App\Services\MatchScoringService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * New, additive endpoints backing the Mid Range / Coaching & Counselling
 * intake form's "Filtered Counsellors" and "Consultation" steps. These are
 * separate from — and do not modify — the existing client-booking,
 * consultation-slots, or staff matching endpoints.
 */
class MidRangeIntakeController extends Controller
{
    protected MatchScoringService $scoringService;

    public function __construct(MatchScoringService $scoringService)
    {
        $this->scoringService = $scoringService;
    }

    /**
     * POST /mid-range-intake/filtered-counsellors
     *
     * Ranks Qualified counsellors offering the requested service tier
     * against the client's in-progress intake answers.
     */
    public function filteredCounsellors(Request $request)
    {
        $validated = $request->validate([
            'service_type' => 'required|in:Mid Range,Counselling & Coaching',
            'is_couples' => 'nullable|boolean',
            'support_areas' => 'nullable|array',
            'availability' => 'nullable|array',
            'gender_preference' => 'nullable|string|max:255',
            'age_preference' => 'nullable|string|max:255',
            'ethnicity_preference' => 'nullable|string|max:255',
            'orientation_preference' => 'nullable|string|max:255',
            'modality_preference' => 'nullable|string|max:255',
            'modality' => 'nullable|string|max:255',
        ]);

        $tierColumn = $validated['service_type'] === 'Mid Range' ? 'offers_mid_range' : 'offers_coaching';
        $isCouples = $validated['is_couples'] ?? false;
        $modalityPref = $validated['modality_preference'] ?? $validated['modality'] ?? null;
        $isModalityChosen = !empty($modalityPref) && !in_array(strtolower(trim($modalityPref)), ['unsure', 'no preference', 'any', 'all']);

        $query = TrainingCounsellor::where('counsellor_type', 'Qualified')
            ->where('status', 'Active')
            ->where($tierColumn, true);

        if ($isCouples) {
            $query->where(function ($q) {
                $q->whereJsonContains('qualified_to_work_with', 'Couples')
                    ->orWhereJsonContains('qualified_to_work_with', 'Families');
            });
        }

        if ($isModalityChosen) {
            $needle = trim($modalityPref);
            $query->where(function ($q) use ($needle) {
                $q->where('modality', 'like', '%' . $needle . '%');
                if (strcasecmp($needle, 'Integrative Therapy') === 0 || strcasecmp($needle, 'Integrative') === 0) {
                    $q->orWhere('modality', 'like', '%Integrative%');
                }
            });
        }

        $counsellors = $query->get();

        // Fallback level 1: relax the couples/family JSON constraint
        // (catches counsellors whose qualified_to_work_with isn't populated yet,
        // but only if they offer the requested tier)
        if ($counsellors->isEmpty() && $isCouples) {
            $fallbackQuery = TrainingCounsellor::where('counsellor_type', 'Qualified')
                ->where('status', 'Active')
                ->where($tierColumn, true);

            if ($isModalityChosen) {
                $needle = trim($modalityPref);
                $fallbackQuery->where(function ($q) use ($needle) {
                    $q->where('modality', 'like', '%' . $needle . '%');
                    if (strcasecmp($needle, 'Integrative Therapy') === 0 || strcasecmp($needle, 'Integrative') === 0) {
                        $q->orWhere('modality', 'like', '%Integrative%');
                    }
                });
            }

            $counsellors = $fallbackQuery->get();
        }

        $results = $counsellors
            ->reject(fn (TrainingCounsellor $tc) => $this->scoringService->isExcluded($validated, $tc))
            ->map(function (TrainingCounsellor $tc) use ($validated) {
            $result = $this->scoringService->score($validated, $tc);

            return [
                'uuid' => $tc->uuid,
                'name' => $tc->name,
                'first_name' => $tc->first_name ?: (explode(' ', trim($tc->name))[0] ?? 'Counsellor'),
                'abbreviated_name' => $tc->abbreviated_name,
                'photo_url' => $tc->photo_url,
                'gender' => $tc->gender,
                'modality' => $tc->modality ?: null,
                'session_price' => $tc->session_price,
                'bio' => $tc->bio ?: null,
                'topics_with_experience' => $tc->topics_with_experience ?: null,
                'qualified_to_work_with' => $tc->qualified_to_work_with ?: null,
                'availability' => $tc->availability,
                'match_score' => $result['score'],
                'match_breakdown' => $result['breakdown'],
                'fit_label' => $this->fitLabel($result['score']),
                'show_own_consultation_availability' => (bool) $tc->show_own_consultation_availability,
                'show_vanquish_consultation_availability' => (bool) $tc->show_vanquish_consultation_availability,
                'specialty' => $this->buildSpecialty($tc),
            ];
        })
        ->sortByDesc('match_score')
        ->values();

        return response()->json([
            'counsellors' => $results,
            'total' => $results->count(),
        ]);
    }

    /**
     * GET /mid-range-intake/consultation-availability/{tcUuid}
     *
     * Priority logic:
     * 1. Toggle 1 on (show_own_consultation_availability) and own slots exist: return own slots.
     * 2. Otherwise, toggle 2 on (show_vanquish_consultation_availability): return Vanquish slots,
     *    and the booking records this counsellor as the chosen counsellor.
     * 3. Both off (or own slots empty and toggle 2 off): no slots,
     *    message: "This counsellor is not taking new consultations at the moment."
     */
    public function consultationAvailability(Request $request, string $tcUuid)
    {
        $tc = TrainingCounsellor::where('uuid', $tcUuid)->first();

        if (!$tc) {
            return response()->json(['message' => 'Counsellor not found'], 404);
        }

        $showOwn = (bool) $tc->show_own_consultation_availability;
        $showVanquish = (bool) $tc->show_vanquish_consultation_availability;

        if ($showOwn) {
            $slots = $this->generateOwnConsultationSlots($tc);

            if (!empty($slots)) {
                return response()->json([
                    'source' => 'counsellor',
                    'tc_uuid' => $tc->uuid,
                    'tc_name' => $tc->name,
                    'slots' => $slots,
                ]);
            }
        }

        if ($showVanquish) {
            return response()->json([
                'source' => 'vanquish',
                'tc_uuid' => $tc->uuid,
                'tc_name' => $tc->name,
                'slots' => [],
            ]);
        }

        return response()->json([
            'source' => 'none',
            'tc_uuid' => $tc->uuid,
            'tc_name' => $tc->name,
            'slots' => [],
            'message' => 'This counsellor is not taking new consultations at the moment.',
        ]);
    }

    /**
     * Walk forward 42 days from a Qualified counsellor's
     * `consultation_availability` weekly pattern, excluding times already
     * booked in `consultations` for that counsellor. Mirrors the pattern
     * used by ClientBookingController::getAvailableSlots for session
     * booking, applied here to the separate consultation-slot concept.
     */
    private function generateOwnConsultationSlots(TrainingCounsellor $tc): array
    {
        $availability = array_change_key_case($tc->consultation_availability ?? [], CASE_LOWER);

        if (empty($availability)) {
            return [];
        }

        $bookedTimes = Consultation::where('tc_id', $tc->id)
            ->whereIn('status', ['scheduled', 'completed'])
            ->pluck('scheduled_at')
            ->map(fn ($dt) => Carbon::parse($dt)->format('Y-m-d H:i'))
            ->toArray();

        $slots = [];
        $startDate = Carbon::now();

        for ($i = 0; $i < 42; $i++) {
            $currentDate = $startDate->copy()->addDays($i);
            $dayKey = strtolower($currentDate->format('l'));

            if (empty($availability[$dayKey]) || !is_array($availability[$dayKey])) {
                continue;
            }

            foreach ($availability[$dayKey] as $time) {
                $formattedTime = $this->formatTimeToHHi($time);
                $slotDateTime = $currentDate->format('Y-m-d') . ' ' . $formattedTime;

                if (in_array($slotDateTime, $bookedTimes) || $currentDate->diffInHours(now(), false) > -24) {
                    continue;
                }

                $slots[] = [
                    'date' => $currentDate->format('Y-m-d'),
                    'day' => $currentDate->format('l'),
                    'time' => $time,
                    'formatted_time' => $formattedTime,
                    'datetime' => $slotDateTime,
                    'available' => true,
                ];
            }
        }

        return $slots;
    }

    private function formatTimeToHHi($timeString)
    {
        if (!$timeString) return '00:00';

        $mapping = [
            'morning-early' => '10:00',
            'morning-late' => '11:00',
            'afternoon-early' => '13:00',
            'afternoon-late' => '16:00',
            'evening' => '17:00',
        ];

        if (isset($mapping[$timeString])) {
            return $mapping[$timeString];
        }

        if (str_contains($timeString, '-')) {
            $timeString = explode('-', $timeString)[0];
        }

        try {
            return Carbon::parse($timeString)->format('H:i');
        } catch (\Exception $e) {
            $timestamp = strtotime($timeString);
            return $timestamp ? date('H:i', $timestamp) : '00:00';
        }
    }

    private function fitLabel(int $score): string
    {
        if ($score >= 95) return 'Best Fit';
        if ($score >= 85) return 'Great Fit';
        if ($score >= 70) return 'Good Fit';
        return 'Fair Fit';
    }

    /**
     * Derive a specialty label from who the counsellor is qualified to work with.
     */
    private function buildSpecialty(TrainingCounsellor $tc): ?string
    {
        $qualifiedWith = $tc->qualified_to_work_with ?? [];

        if (in_array('Couples', $qualifiedWith) || in_array('Families', $qualifiedWith)) {
            if (in_array('Individuals', $qualifiedWith)) {
                return 'Individual & Couples Counsellor';
            }
            return 'Couples Counsellor';
        }

        if (in_array('Individuals', $qualifiedWith)) {
            return 'Individual Counsellor';
        }

        return null;
    }
}
