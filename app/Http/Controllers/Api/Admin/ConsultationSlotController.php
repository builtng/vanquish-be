<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\ConsultationSlot;
use Illuminate\Http\Request;
use Carbon\Carbon;

class ConsultationSlotController extends Controller
{
    public function index(Request $request)
    {
        $order = strtolower($request->query('order', 'desc'));
        if (!in_array($order, ['asc', 'desc'])) {
            $order = 'desc';
        }

        $slots = ConsultationSlot::when($request->query('type'), function ($query, $type) {
                $query->where('type', $type);
            })
            ->orderBy('consultation_datetime', $order)
            ->get();

        // Because of the Consultation model Observer, 'booked_slots' and 'status' are automatically 
        // synced in the database whenever a consultation is created, updated, or deleted. 
        // Returning the slots directly is now accurate.

        return response()->json($slots);
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

        $slot = ConsultationSlot::create([
            'consultation_datetime' => $validated['consultation_datetime'],
            'max_slots' => $validated['max_slots'] ?? null,
            'status' => 'available',
            'booked_slots' => 0,
            'type' => $validated['type'] ?? 'consultation',
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
            'interval_minutes' => 'nullable|integer|in:15,30,45,60',
            'max_slots' => 'nullable|integer|min:1',
            'type' => 'nullable|string|in:consultation,placement_interview',
            'zoom_link' => 'nullable|string',
            'host_name' => 'nullable|string',
        ]);

        $interval = $validated['interval_minutes'] ?? 15;
        $cursor = Carbon::createFromFormat('Y-m-d H:i', $validated['date'] . ' ' . $validated['start_time']);
        $end = Carbon::createFromFormat('Y-m-d H:i', $validated['date'] . ' ' . $validated['end_time']);
        $minAllowed = now()->subMinutes(5);

        $created = [];
        while ($cursor->lt($end)) {
            if ($cursor->gte($minAllowed)) {
                $created[] = ConsultationSlot::create([
                    'consultation_datetime' => $cursor->copy(),
                    'max_slots' => $validated['max_slots'] ?? null,
                    'status' => 'available',
                    'booked_slots' => 0,
                    'type' => $validated['type'] ?? 'consultation',
                    'zoom_link' => $validated['zoom_link'] ?? null,
                    'host_name' => $validated['host_name'] ?? null,
                ]);
            }
            $cursor->addMinutes($interval);
        }

        if (empty($created)) {
            return response()->json(['message' => 'No slots were created. The selected range is entirely in the past.'], 422);
        }

        return response()->json([
            'message' => count($created) . ' consultation slot(s) created successfully',
            'slots' => $created,
        ], 201);
    }

    public function storeRecurring(Request $request)
    {
        $validated = $request->validate([
            'days_of_week' => 'required|array|min:1',
            'days_of_week.*' => 'required',
            'intervals' => 'required|array|min:1',
            'intervals.*.start_time' => 'required|date_format:H:i',
            'intervals.*.end_time' => 'nullable|date_format:H:i',
            'start_date' => 'required|date_format:Y-m-d',
            'end_date' => 'nullable|date_format:Y-m-d|after_or_equal:start_date',
            'weeks_count' => 'nullable|integer|min:1|max:52',
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
            return response()->json(['message' => 'Please select at least one valid day of the week.'], 422);
        }

        $startDate = Carbon::createFromFormat('Y-m-d', $validated['start_date'])->startOfDay();
        if (!empty($validated['end_date'])) {
            $endDate = Carbon::createFromFormat('Y-m-d', $validated['end_date'])->endOfDay();
        } else {
            $weeks = $validated['weeks_count'] ?? 4;
            $endDate = $startDate->copy()->addWeeks($weeks)->subDay()->endOfDay();
        }

        $slotType = $validated['type'] ?? 'consultation';
        $maxSlots = $validated['max_slots'] ?? null;
        $zoomLink = $validated['zoom_link'] ?? null;
        $hostName = $validated['host_name'] ?? null;
        $minAllowed = now()->subMinutes(5);

        $created = [];
        $skipped = 0;
        $current = $startDate->copy();

        while ($current->lte($endDate)) {
            $dayOfWeekIso = $current->isoWeekday(); // 1 (Mon) to 7 (Sun)
            if (in_array($dayOfWeekIso, $targetDays)) {
                $dateStr = $current->format('Y-m-d');
                foreach ($validated['intervals'] as $interval) {
                    $startTime = $interval['start_time'];
                    $slotDateTime = Carbon::createFromFormat('Y-m-d H:i', $dateStr . ' ' . $startTime);

                    if ($slotDateTime->lt($minAllowed)) {
                        $skipped++;
                        continue;
                    }

                    // Check for duplicate slot at exact datetime and type
                    $exists = ConsultationSlot::where('consultation_datetime', $slotDateTime)
                        ->where('type', $slotType)
                        ->exists();

                    if ($exists) {
                        $skipped++;
                        continue;
                    }

                    $created[] = ConsultationSlot::create([
                        'consultation_datetime' => $slotDateTime,
                        'max_slots' => $maxSlots,
                        'status' => 'available',
                        'booked_slots' => 0,
                        'type' => $slotType,
                        'zoom_link' => $zoomLink,
                        'host_name' => $hostName,
                    ]);
                }
            }
            $current->addDay();
        }

        if (empty($created)) {
            $msg = $skipped > 0 
                ? "No new slots were created. {$skipped} slot(s) were either in the past or already exist." 
                : 'No slots matched the selected date range and days.';
            return response()->json(['message' => $msg], 422);
        }

        return response()->json([
            'message' => count($created) . ' consultation slot(s) created successfully' . ($skipped > 0 ? " ({$skipped} existing/past slots skipped)" : ''),
            'created_count' => count($created),
            'skipped_count' => $skipped,
            'slots' => $created,
        ], 201);
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

            $exists = ConsultationSlot::where('consultation_datetime', $slotDateTime)
                ->where('type', $type)
                ->exists();

            if ($exists) {
                $skipped++;
                continue;
            }

            $created[] = ConsultationSlot::create([
                'consultation_datetime' => $slotDateTime,
                'max_slots' => $slotData['max_slots'] ?? null,
                'status' => 'available',
                'booked_slots' => 0,
                'type' => $type,
                'zoom_link' => $slotData['zoom_link'] ?? null,
                'host_name' => $slotData['host_name'] ?? null,
            ]);
        }

        if (empty($created)) {
            return response()->json([
                'message' => "No new slots created. {$skipped} slot(s) were past or duplicates."
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

        // If reducing max_slots, ensure it's not lower than already booked
        if (isset($validated['max_slots']) && $validated['max_slots'] < $slot->booked_slots) {
            return response()->json(['message' => 'Cannot set max slots lower than currently booked amount'], 400);
        }

        $slot->update($validated);

        // Auto update status to full if max is reached
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
