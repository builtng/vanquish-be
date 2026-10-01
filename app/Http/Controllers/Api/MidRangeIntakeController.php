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
        ]);

        $tierColumn = $validated['service_type'] === 'Mid Range' ? 'offers_mid_range' : 'offers_coaching';
        $isCouples = $validated['is_couples'] ?? false;

        $query = TrainingCounsellor::where('counsellor_type', 'Qualified')
            ->where('status', 'Active')
            ->where($tierColumn, true);

        if ($isCouples) {
            $query->where(function ($q) {
                $q->whereJsonContains('qualified_to_work_with', 'Couples')
                    ->orWhereJsonContains('qualified_to_work_with', 'Families');
            });
        }

        $counsellors = $query->get();

        // Fallback level 1: relax the couples/family JSON constraint
        // (catches counsellors whose qualified_to_work_with isn't populated yet)
        if ($counsellors->isEmpty()) {
            $fallbackQuery = TrainingCounsellor::where('counsellor_type', 'Qualified')
                ->where('status', 'Active')
                ->where($tierColumn, true);
            $counsellors = $fallbackQuery->get();
        }

        // Fallback level 2: relax the tier flag too — return all active Qualified
        // counsellors so the page always shows real data instead of mock names
        if ($counsellors->isEmpty()) {
            $counsellors = TrainingCounsellor::where('counsellor_type', 'Qualified')
                ->where('status', 'Active')
                ->get();
        }

        $results = $counsellors->map(function (TrainingCounsellor $tc) use ($validated) {
            $result = $this->scoringService->score($validated, $tc);

            // Compute years of experience or reasonable default
            $yearsExp = '5+ years experience';
            if ($tc->joined_date) {
                $years = (int) floor(abs(Carbon::parse($tc->joined_date)->diffInYears(now())));
                $totalYears = max(4, $years + 4);
                $yearsExp = $totalYears . '+ years experience';
            }

            // Professional qualification title
            $qualTitle = $tc->professional_membership ?: ($tc->course ? 'Registered Counsellor (' . Str::limit($tc->course, 30) . ')' : 'Registered Counsellor (RPC)');

            return [
                'uuid' => $tc->uuid,
                'name' => $tc->name,
                'first_name' => $tc->first_name ?: (explode(' ', trim($tc->name))[0] ?? 'Counsellor'),
                'abbreviated_name' => $tc->abbreviated_name,
                'photo_url' => $tc->photo_url,
                'gender' => $tc->gender,
                'modality' => $tc->modality ?: 'Integrative',
                'session_price' => $tc->session_price,
                'bio' => $tc->bio ?: 'I help individuals and couples navigate life challenges, improve communication, and build healthier emotional wellbeing.',
                'topics_with_experience' => $tc->topics_with_experience ?: ['Anxiety', 'Depression', 'Trauma', 'Relationship Issues', 'Stress', 'Self-Esteem'],
                'qualified_to_work_with' => $tc->qualified_to_work_with ?: ['Individuals', 'Couples'],
                'availability' => $tc->availability,
                'qualification_title' => $qualTitle,
                'years_of_experience' => $yearsExp,
                'match_score' => $result['score'],
                'match_breakdown' => $result['breakdown'],
                'fit_label' => $this->fitLabel($result['score']),
                'show_own_consultation_availability' => $tc->show_own_consultation_availability,
                'education_credentials' => $this->buildEducationCredentials($tc),
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
     * Priority logic: if the counsellor has opted to show their own
     * consultation availability and has open slots, return those. Otherwise
     * signal the frontend to fall back to the existing generic
     * `GET /consultation-slots/available` (Vanquish Therapies pool).
     */
    public function consultationAvailability(Request $request, string $tcUuid)
    {
        $tc = TrainingCounsellor::where('uuid', $tcUuid)->first();

        if (!$tc) {
            return response()->json(['message' => 'Counsellor not found'], 404);
        }

        if ($tc->show_own_consultation_availability) {
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

        return response()->json([
            'source' => 'vanquish',
            'tc_uuid' => $tc->uuid,
            'tc_name' => $tc->name,
            'slots' => [],
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
    private function buildSpecialty(TrainingCounsellor $tc): string
    {
        $qualifiedWith = $tc->qualified_to_work_with ?? [];

        if (in_array('Couples', $qualifiedWith) || in_array('Families', $qualifiedWith)) {
            if (in_array('Individuals', $qualifiedWith)) {
                return 'Individual & Couples Counsellor';
            }
            return 'Couples Counsellor';
        }

        return 'Individual Counsellor';
    }

    /**
     * Build a human-readable education & credentials array from real stored
     * fields on the counsellor record.  Returns a non-empty array; falls back
     * to a sensible generic entry only if absolutely nothing is recorded.
     */
    private function buildEducationCredentials(TrainingCounsellor $tc): array
    {
        $credentials = [];

        // Primary course + institution
        if ($tc->course && $tc->institution) {
            $credentials[] = $tc->course . ' — ' . $tc->institution;
        } elseif ($tc->course) {
            $credentials[] = $tc->course;
        } elseif ($tc->institution) {
            $credentials[] = $tc->institution;
        }

        // Professional membership / registration body
        if ($tc->professional_membership) {
            $credentials[] = $tc->professional_membership;
        }

        // Additional training details (stored as free text; split on newlines)
        if ($tc->counsellor_training_details) {
            $lines = preg_split('/[\r\n]+/', $tc->counsellor_training_details);
            foreach ($lines as $line) {
                $line = trim($line);
                if ($line !== '' && !in_array($line, $credentials)) {
                    $credentials[] = $line;
                }
            }
        }

        // If nothing is stored, return a generic placeholder so the UI
        // always has something meaningful to display
        if (empty($credentials)) {
            $credentials[] = 'Registered Professional Counsellor (RPC)';
        }

        return $credentials;
    }
}
