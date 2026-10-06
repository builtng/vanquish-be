<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\ConsultationSlot;
use App\Models\ConsultationDayOff;
use Illuminate\Http\Request;
use Carbon\Carbon;

class ConsultationSlotController extends Controller
{
    public function index(Request $request)
    {
        $order = strtolower($request->query('order', 'asc'));
        if (!in_array($order, ['asc', 'desc'])) {
            $order = 'asc';
        }

        $slots = ConsultationSlot::when($request->query('type'), function ($query, $type) {
                $query->where('type', $type);
            })
            ->orderBy('consultation_datetime', $order)
            ->get();

        // Deduplicate in response to guarantee UI never shows twin slots
        $deduped = $slots->unique(function ($s) {
            return $s->consultation_datetime->toIso8601String() . '_' . $s->type;
        })->values();

        return response()->json($deduped);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'consultation_datetime' => 'required|date|after:' . now()->subMinutes(5)->toDateTimeString(),
            'max_slots' => 'nullable|integer|min:1',
            'type' => 'nullable|string|in:consultation,placement_interview',
            'zoom_link' => 'nullable|string',
            'host_name' => 'nullable|string',
        ]);

        $slotType = $validated['type'] ?? 'consultation';
        $datetime = Carbon::parse($validated['consultation_datetime']);

        // Check if date is marked as a day off
        if (ConsultationDayOff::whereDate('date', $datetime->toDateString())->exists()) {
            return response()->json([
                'message' => 'Cannot create slot: ' . $datetime->toDateString() . ' is marked as a day off.'
            ], 422);
        }

        // Check for duplicate slot
        $existing = ConsultationSlot::where('consultation_datetime', $datetime)
            ->where('type', $slotType)
            ->first();

        if ($existing) {
            return response()->json([
                'message' => 'A consultation slot for this date and time already exists.',
                'slot' => $existing
            ], 422);
        }

        $slot = ConsultationSlot::create([
            'consultation_datetime' => $datetime,
            'max_slots' => $validated['max_slots'] ?? null,
            'status' => 'available',
            'booked_slots' => 0,
            'type' => $slotType,
            'zoom_link' => $validated['zoom_link'] ?? null,
            'host_name' => $validated['host_name'] ?? null,
        ]);

        return response()->json(['message' => 'Consultation slot created successfully', 'slot' => $slot], 201);
    }

    public function storeRange(Request $request)
    {
        $validated = $request->validate([
            'date' => 'required|date_format:Y-m-d',
            'start_time' => 'required|date_format:H:i',
            'end_time' => 'required|date_format:H:i|after:start_time',
            'interval_minutes' => 'nullable|integer|in:15,20,30,45,50,60',
            'max_slots' => 'nullable|integer|min:1',
            'type' => 'nullable|string|in:consultation,placement_interview',
            'zoom_link' => 'nullable|string',
            'host_name' => 'nullable|string',
        ]);

        if (ConsultationDayOff::whereDate('date', $validated['date'])->exists()) {
            return response()->json([
                'message' => 'Cannot create slots: ' . $validated['date'] . ' is marked as a day off.'
            ], 422);
        }

        $interval = $validated['interval_minutes'] ?? 15;
        $cursor = Carbon::createFromFormat('Y-m-d H:i', $validated['date'] . ' ' . $validated['start_time']);
        $end = Carbon::createFromFormat('Y-m-d H:i', $validated['date'] . ' ' . $validated['end_time']);
        $minAllowed = now()->subMinutes(5);
        $slotType = $validated['type'] ?? 'consultation';

        $created = [];
        $skipped = 0;
        while ($cursor->lt($end)) {
            if ($cursor->gte($minAllowed)) {
                $exists = ConsultationSlot::where('consultation_datetime', $cursor)
                    ->where('type', $slotType)
                    ->exists();

                if (!$exists) {
                    $created[] = ConsultationSlot::create([
                        'consultation_datetime' => $cursor->copy(),
                        'max_slots' => $validated['max_slots'] ?? null,
                        'status' => 'available',
                        'booked_slots' => 0,
                        'type' => $slotType,
                        'zoom_link' => $validated['zoom_link'] ?? null,
                        'host_name' => $validated['host_name'] ?? null,
                    ]);
                } else {
                    $skipped++;
                }
            } else {
                $skipped++;
            }
            $cursor->addMinutes($interval);
        }

        if (empty($created)) {
            return response()->json([
                'message' => 'No slots were created. Selected slots were either duplicates, in the past, or on a day off.'
            ], 422);
        }

        return response()->json([
            'message' => count($created) . ' consultation slot(s) created successfully' . ($skipped > 0 ? " ({$skipped} skipped)" : ''),
            'slots' => $created,
        ], 201);
    }

    /**
     * Preview recurring / bulk consultation slots without persisting them.
     */
    public function previewRecurring(Request $request)
    {
        $plan = $this->buildSlotPlan($request);
        if (isset($plan['error'])) {
            return response()->json(['message' => $plan['error']], 422);
        }

        return response()->json([
            'slots' => $plan['slots'],
            'total_count' => count($plan['slots']),
            'days_off_skipped' => $plan['days_off_skipped'],
            'duplicates_skipped' => $plan['duplicates_skipped'],
            'past_skipped' => $plan['past_skipped'],
            'weeks_count' => $plan['weeks_count'],
        ]);
    }

    /**
     * Store recurring / bulk consultation slots.
     */
    public function storeRecurring(Request $request)
    {
        $plan = $this->buildSlotPlan($request);
        if (isset($plan['error'])) {
            return response()->json(['message' => $plan['error']], 422);
        }

        $created = [];
        foreach ($plan['slots'] as $slotData) {
            // Check again for safety
            $exists = ConsultationSlot::where('consultation_datetime', $slotData['datetime'])
                ->where('type', $slotData['type'])
                ->exists();

            if (!$exists) {
                $created[] = ConsultationSlot::create([
                    'consultation_datetime' => $slotData['datetime'],
                    'max_slots' => $slotData['max_slots'],
                    'status' => 'available',
                    'booked_slots' => 0,
                    'type' => $slotData['type'],
                    'zoom_link' => $slotData['zoom_link'],
                    'host_name' => $slotData['host_name'],
                ]);
            }
        }

        $skippedTotal = count($plan['days_off_skipped']) + $plan['duplicates_skipped'] + $plan['past_skipped'];

        if (empty($created)) {
            $msg = $skippedTotal > 0
                ? "No new slots were created. All slots were either existing duplicates, in the past, or on days off."
                : 'No slots matched the selected date range and days.';
            return response()->json(['message' => $msg], 422);
        }

        return response()->json([
            'message' => count($created) . ' consultation slot(s) created successfully' . ($skippedTotal > 0 ? " ({$skippedTotal} skipped)" : ''),
            'created_count' => count($created),
            'skipped_count' => $skippedTotal,
            'days_off_skipped_count' => count($plan['days_off_skipped']),
            'slots' => $created,
        ], 201);
    }

    /**
     * Helper to compute slot generation plan (used by preview and store).
     */
    protected function buildSlotPlan(Request $request): array
    {
        $validated = $request->validate([
            'days_of_week' => 'required|array|min:1',
            'days_of_week.*' => 'required',
            // Either intervals array OR start_time + end_time + slot_length
            'intervals' => 'nullable|array',
            'intervals.*.start_time' => 'required_with:intervals|date_format:H:i',
            'intervals.*.end_time' => 'nullable|date_format:H:i',
            'start_time' => 'nullable|date_format:H:i',
            'end_time' => 'nullable|date_format:H:i|after:start_time',
            'slot_length' => 'nullable|integer|in:15,20,30,45,50,60',
            'break_minutes' => 'nullable|integer|min:0|max:60',
            'start_date' => 'required|date_format:Y-m-d',
            'end_date' => 'nullable|date_format:Y-m-d|after_or_equal:start_date',
            'weeks_count' => 'nullable|integer|min:1|max:52',
            'days_off' => 'nullable|array',
            'days_off.*' => 'date_format:Y-m-d',
            'max_slots' => 'nullable|integer|min:1',
            'type' => 'nullable|string|in:consultation,placement_interview',
            'zoom_link' => 'nullable|string',
            'host_name' => 'nullable|string',
        ]);

        $dayMap = [
            'mon' => 1, 'monday' => 1, 1 => 1, '1' => 1,
            'tue' => 2, 'tuesday' => 2, 2 => 2, '2' => 2,
            'wed' => 3, 'wednesday' => 3, 3 => 3, '3' => 3,
            'thu' => 4, 'thursday' => 4, 4 => 4, '4' => 4,
            'fri' => 5, 'friday' => 5, 5 => 5, '5' => 5,
            'sat' => 6, 'saturday' => 6, 6 => 6, '6' => 6,
            'sun' => 7, 'sunday' => 7, 7 => 7, '0' => 7, 0 => 7,
        ];

        $targetDays = [];
        foreach ($validated['days_of_week'] as $day) {
            $normalized = is_string($day) ? strtolower(trim($day)) : $day;
            if (isset($dayMap[$normalized])) {
                $targetDays[] = $dayMap[$normalized];
            }
        }
        $targetDays = array_unique($targetDays);

        if (empty($targetDays)) {
            return ['error' => 'Please select at least one valid day of the week.'];
        }

        // Resolve intervals
        $intervals = [];
        if (!empty($validated['intervals'])) {
            foreach ($validated['intervals'] as $i) {
                if (!empty($i['start_time'])) {
                    $intervals[] = [
                        'start_time' => $i['start_time'],
                        'end_time' => $i['end_time'] ?? null,
                    ];
                }
            }
        } elseif (!empty($validated['start_time']) && !empty($validated['end_time'])) {
            $slotLength = $validated['slot_length'] ?? 15;
            $breakMinutes = $validated['break_minutes'] ?? 0;

            $cursor = Carbon::createFromFormat('H:i', $validated['start_time']);
            $end = Carbon::createFromFormat('H:i', $validated['end_time']);

            while ($cursor->copy()->addMinutes($slotLength)->lte($end)) {
                $slotStart = $cursor->format('H:i');
                $cursor->addMinutes($slotLength);
                $slotEnd = $cursor->format('H:i');
                $intervals[] = [
                    'start_time' => $slotStart,
                    'end_time' => $slotEnd,
                ];
                $cursor->addMinutes($breakMinutes);
            }
        }

        if (empty($intervals)) {
            return ['error' => 'Please provide time intervals or start/end times with slot length.'];
        }

        $startDate = Carbon::createFromFormat('Y-m-d', $validated['start_date'])->startOfDay();
        $weeks = $validated['weeks_count'] ?? 4;

        if (!empty($validated['end_date'])) {
            $endDate = Carbon::createFromFormat('Y-m-d', $validated['end_date'])->endOfDay();
            $diffDays = $startDate->diffInDays($endDate) + 1;
            $weeks = max(1, (int)ceil($diffDays / 7));
        } else {
            $endDate = $startDate->copy()->addWeeks($weeks)->subDay()->endOfDay();
        }

        // Fetch all marked days off in the date range
        $dbDaysOff = ConsultationDayOff::whereBetween('date', [$startDate->toDateString(), $endDate->toDateString()])
            ->pluck('date')
            ->map(fn($d) => Carbon::parse($d)->toDateString())
            ->toArray();

        $customDaysOff = $validated['days_off'] ?? [];
        $allDaysOff = array_unique(array_merge($dbDaysOff, $customDaysOff));

        $slotType = $validated['type'] ?? 'consultation';
        $maxSlots = $validated['max_slots'] ?? 1;
        $zoomLink = $validated['zoom_link'] ?? null;
        $hostName = $validated['host_name'] ?? null;
        $minAllowed = now()->subMinutes(5);

        $slots = [];
        $daysOffSkipped = [];
        $duplicatesSkipped = 0;
        $pastSkipped = 0;

        $current = $startDate->copy();

        while ($current->lte($endDate)) {
            $dayOfWeekIso = $current->isoWeekday();
            $dateStr = $current->format('Y-m-d');

            if (in_array($dayOfWeekIso, $targetDays)) {
                // Check if this date is a day off
                if (in_array($dateStr, $allDaysOff)) {
                    $daysOffSkipped[] = $dateStr;
                    $current->addDay();
                    continue;
                }

                foreach ($intervals as $interval) {
                    $slotDateTime = Carbon::createFromFormat('Y-m-d H:i', $dateStr . ' ' . $interval['start_time']);

                    if ($slotDateTime->lt($minAllowed)) {
                        $pastSkipped++;
                        continue;
                    }

                    $exists = ConsultationSlot::where('consultation_datetime', $slotDateTime)
                        ->where('type', $slotType)
                        ->exists();

                    if ($exists) {
                        $duplicatesSkipped++;
                        continue;
                    }

                    $slots[] = [
                        'datetime' => $slotDateTime,
                        'formatted_date' => $slotDateTime->format('D, d M Y'),
                        'date' => $dateStr,
                        'day_name' => $slotDateTime->format('l'),
                        'start_time' => $interval['start_time'],
                        'end_time' => $interval['end_time'] ?? null,
                        'max_slots' => $maxSlots,
                        'type' => $slotType,
                        'zoom_link' => $zoomLink,
                        'host_name' => $hostName,
                    ];
                }
            }

            $current->addDay();
        }

        return [
            'slots' => $slots,
            'days_off_skipped' => array_values(array_unique($daysOffSkipped)),
            'duplicates_skipped' => $duplicatesSkipped,
            'past_skipped' => $pastSkipped,
            'weeks_count' => $weeks,
        ];
    }

    public function storeBulk(Request $request)
    {
        $validated = $request->validate([
            'slots' => 'required|array|min:1',
            'slots.*.consultation_datetime' => 'required|date',
            'slots.*.max_slots' => 'nullable|integer|min:1',
            'slots.*.type' => 'nullable|string|in:consultation,placement_interview',
            'slots.*.zoom_link' => 'nullable|string',
            'slots.*.host_name' => 'nullable|string',
        ]);

        $created = [];
        $skipped = 0;
        $minAllowed = now()->subMinutes(5);

        foreach ($validated['slots'] as $slotData) {
            $slotDateTime = Carbon::parse($slotData['consultation_datetime']);
            $type = $slotData['type'] ?? 'consultation';

            if ($slotDateTime->lt($minAllowed)) {
                $skipped++;
                continue;
            }

            if (ConsultationDayOff::whereDate('date', $slotDateTime->toDateString())->exists()) {
                $skipped++;
                continue;
            }

            $exists = ConsultationSlot::where('consultation_datetime', $slotDateTime)
                ->where('type', $type)
                ->exists();

            if ($exists) {
                $skipped++;
                continue;
            }

            $created[] = ConsultationSlot::create([
                'consultation_datetime' => $slotDateTime,
                'max_slots' => $slotData['max_slots'] ?? 1,
                'status' => 'available',
                'booked_slots' => 0,
                'type' => $type,
                'zoom_link' => $slotData['zoom_link'] ?? null,
                'host_name' => $slotData['host_name'] ?? null,
            ]);
        }

        if (empty($created)) {
            return response()->json([
                'message' => "No new slots created. {$skipped} slot(s) were past, duplicates, or on days off."
            ], 422);
        }

        return response()->json([
            'message' => count($created) . ' consultation slot(s) created successfully' . ($skipped > 0 ? " ({$skipped} skipped)" : ''),
            'created_count' => count($created),
            'skipped_count' => $skipped,
            'slots' => $created,
        ], 201);
    }

    public function update(Request $request, $id)
    {
        $slot = ConsultationSlot::findOrFail($id);

        $validated = $request->validate([
            'consultation_datetime' => 'sometimes|date|after:' . now()->subMinutes(5)->toDateTimeString(),
            'max_slots' => 'nullable|integer|min:1',
            'status' => 'sometimes|in:available,full,closed',
            'type' => 'sometimes|string|in:consultation,placement_interview',
            'zoom_link' => 'nullable|string',
            'host_name' => 'nullable|string',
        ]);

        if (isset($validated['consultation_datetime'])) {
            $newDt = Carbon::parse($validated['consultation_datetime']);
            $newType = $validated['type'] ?? $slot->type;

            if (ConsultationDayOff::whereDate('date', $newDt->toDateString())->exists()) {
                return response()->json([
                    'message' => 'Cannot move slot to ' . $newDt->toDateString() . ': it is marked as a day off.'
                ], 422);
            }

            $conflict = ConsultationSlot::where('consultation_datetime', $newDt)
                ->where('type', $newType)
                ->where('id', '!=', $slot->id)
                ->exists();

            if ($conflict) {
                return response()->json([
                    'message' => 'A consultation slot already exists at that date and time.'
                ], 422);
            }
        }

        if (isset($validated['max_slots']) && $validated['max_slots'] < $slot->booked_slots) {
            return response()->json(['message' => 'Cannot set max slots lower than currently booked amount'], 400);
        }

        $slot->update($validated);

        if ($slot->max_slots && $slot->booked_slots >= $slot->max_slots) {
            $slot->update(['status' => 'full']);
        } elseif (($slot->status === 'full' || $slot->status === 'available') && ($slot->max_slots && $slot->booked_slots < $slot->max_slots)) {
            $slot->update(['status' => 'available']);
        }

        return response()->json(['message' => 'Consultation slot updated successfully', 'slot' => $slot]);
    }

    public function destroy($id)
    {
        $slot = ConsultationSlot::findOrFail($id);

        if ($slot->booked_slots > 0) {
            return response()->json(['message' => 'Cannot delete a slot that has bookings'], 400);
        }

        $slot->delete();

        return response()->json(['message' => 'Consultation slot deleted successfully']);
    }
}
