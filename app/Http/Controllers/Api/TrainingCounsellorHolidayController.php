<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\TrainingCounsellor;
use App\Models\TrainingCounsellorHoliday;
use Illuminate\Http\Request;

class TrainingCounsellorHolidayController extends Controller
{
    /**
     * List a counsellor's holiday date ranges.
     */
    public function index($tc)
    {
        $trainingCounsellor = TrainingCounsellor::where('uuid', $tc)->orWhere('tc_id', $tc)->firstOrFail();

        return response()->json(
            $trainingCounsellor->holidays()->orderBy('start_date')->get()
        );
    }

    /**
     * Add a holiday date range for a counsellor (admin-initiated, auto-approved).
     */
    public function store(Request $request, $tc)
    {
        $trainingCounsellor = TrainingCounsellor::where('uuid', $tc)->orWhere('tc_id', $tc)->firstOrFail();

        $validated = $request->validate([
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
            'reason' => 'nullable|string|max:255',
        ]);

        $validated['status'] = 'approved';
        $holiday = $trainingCounsellor->holidays()->create($validated);

        return response()->json($holiday, 201);
    }

    /**
     * Remove a holiday date range.
     */
    public function destroy($tc, $holiday)
    {
        $trainingCounsellor = TrainingCounsellor::where('uuid', $tc)->orWhere('tc_id', $tc)->firstOrFail();

        $holidayRecord = $trainingCounsellor->holidays()->where('id', $holiday)->firstOrFail();
        $holidayRecord->delete();

        return response()->json(['message' => 'Holiday removed successfully.']);
    }

    /**
     * Resolve the authenticated counsellor's TrainingCounsellor record, or fail.
     */
    private function resolveAuthenticatedTc(Request $request): TrainingCounsellor
    {
        $user = $request->user();

        if (!$user || $user->role !== 'counsellor' || !$user->training_counsellor_id) {
            abort(403, 'Unauthorized. Counsellor access required.');
        }

        return TrainingCounsellor::findOrFail($user->training_counsellor_id);
    }

    /**
     * List the authenticated counsellor's own time-off requests.
     */
    public function myHolidays(Request $request)
    {
        $trainingCounsellor = $this->resolveAuthenticatedTc($request);

        return response()->json(
            $trainingCounsellor->holidays()->orderBy('start_date', 'desc')->get()
        );
    }

    /**
     * Submit a new time-off request for the authenticated counsellor (pending review).
     */
    public function requestHoliday(Request $request)
    {
        $trainingCounsellor = $this->resolveAuthenticatedTc($request);

        $minDate = now()->addWeeks(4)->toDateString();

        $validated = $request->validate([
            'start_date' => 'required|date|after_or_equal:' . $minDate,
            'end_date' => 'required|date|after_or_equal:start_date',
            'reason' => 'nullable|string|max:255',
        ], [
            'start_date.after_or_equal' => 'Time-off requests must be submitted at least 4 weeks in advance.',
        ]);

        $validated['status'] = 'pending';
        $holiday = $trainingCounsellor->holidays()->create($validated);

        return response()->json($holiday, 201);
    }

    /**
     * List all pending time-off requests across counsellors, for admin review.
     */
    public function pending()
    {
        return response()->json(
            TrainingCounsellorHoliday::with('trainingCounsellor:id,uuid,tc_id,name,email')
                ->where('status', 'pending')
                ->orderBy('start_date')
                ->get()
        );
    }

    /**
     * Approve a pending time-off request.
     */
    public function approve($holiday)
    {
        $holidayRecord = TrainingCounsellorHoliday::findOrFail($holiday);
        $holidayRecord->update(['status' => 'approved']);

        return response()->json($holidayRecord);
    }

    /**
     * Reject a pending time-off request.
     */
    public function reject($holiday)
    {
        $holidayRecord = TrainingCounsellorHoliday::findOrFail($holiday);
        $holidayRecord->update(['status' => 'rejected']);

        return response()->json($holidayRecord);
    }
}
