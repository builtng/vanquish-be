<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\ConsultationDayOff;
use App\Models\ConsultationSlot;
use Illuminate\Http\Request;
use Carbon\Carbon;

class ConsultationDayOffController extends Controller
{
    /**
     * Display a listing of marked days off.
     */
    public function index()
    {
        $daysOff = ConsultationDayOff::with('creator:id,name,email')
            ->orderBy('date', 'asc')
            ->get();

        return response()->json($daysOff);
    }

    /**
     * Mark a date as a day off and remove/block unbooked consultation slots on that date.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'date' => 'required|date_format:Y-m-d|unique:consultation_days_off,date',
            'reason' => 'nullable|string|max:255',
        ]);

        $dayOff = ConsultationDayOff::create([
            'date' => $validated['date'],
            'reason' => $validated['reason'] ?? null,
            'created_by' => auth()->id(),
        ]);

        $dateStr = $validated['date'];

        // Remove unbooked slots on this day
        $deletedCount = ConsultationSlot::whereDate('consultation_datetime', $dateStr)
            ->where('booked_slots', 0)
            ->delete();

        // Close any slots with existing bookings so no new bookings can be made
        $closedCount = ConsultationSlot::whereDate('consultation_datetime', $dateStr)
            ->where('booked_slots', '>', 0)
            ->where('status', '!=', 'closed')
            ->update(['status' => 'closed']);

        $msg = "Day off marked for {$dateStr}. ";
        if ($deletedCount > 0) {
            $msg .= "Removed {$deletedCount} unbooked slot(s). ";
        }
        if ($closedCount > 0) {
            $msg .= "Closed {$closedCount} booked slot(s).";
        }

        return response()->json([
            'message' => trim($msg),
            'day_off' => $dayOff,
            'removed_slots' => $deletedCount,
            'closed_slots' => $closedCount,
        ], 201);
    }

    /**
     * Remove a marked day off.
     */
    public function destroy($id)
    {
        $dayOff = ConsultationDayOff::findOrFail($id);
        $dayOff->delete();

        return response()->json([
            'message' => 'Day off removed successfully.',
        ]);
    }
}
