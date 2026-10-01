<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AttendanceGroup;
use App\Models\TrainingCounsellor;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class AttendanceGroupController extends Controller
{
    /**
     * List all PSG attendance groups.
     */
    public function index(Request $request)
    {
        $groups = AttendanceGroup::withCount('trainingCounsellors')
            ->with(['trainingCounsellors' => function ($q) {
                $q->select('id', 'name', 'email', 'attendance_group_id', 'uuid');
            }])
            ->orderBy('name')
            ->get();

        return response()->json($groups);
    }

    /**
     * Create a new PSG attendance group.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name'             => 'required|string|max:255',
            'supervisor_link'  => 'nullable|url|max:500',
            'supervisor_name'  => 'nullable|string|max:255',
            'supervisor_email' => 'nullable|email|max:255',
            'day_of_week'      => 'nullable|string|max:20',
        ]);

        $group = AttendanceGroup::create($validated);
        $group->load('trainingCounsellors');
        $group->loadCount('trainingCounsellors');

        return response()->json($group, 201);
    }

    /**
     * Show a single PSG group.
     */
    public function show(AttendanceGroup $attendanceGroup)
    {
        $attendanceGroup->load(['trainingCounsellors' => function ($q) {
            $q->select('id', 'name', 'email', 'attendance_group_id', 'uuid');
        }]);
        $attendanceGroup->loadCount('trainingCounsellors');

        return response()->json($attendanceGroup);
    }

    /**
     * Update a PSG attendance group.
     */
    public function update(Request $request, AttendanceGroup $attendanceGroup)
    {
        $validated = $request->validate([
            'name'             => 'sometimes|required|string|max:255',
            'supervisor_link'  => 'nullable|url|max:500',
            'supervisor_name'  => 'nullable|string|max:255',
            'supervisor_email' => 'nullable|email|max:255',
            'day_of_week'      => 'nullable|string|max:20',
        ]);

        $attendanceGroup->update($validated);
        $attendanceGroup->load(['trainingCounsellors' => function ($q) {
            $q->select('id', 'name', 'email', 'attendance_group_id', 'uuid');
        }]);
        $attendanceGroup->loadCount('trainingCounsellors');

        return response()->json($attendanceGroup);
    }

    /**
     * Delete a PSG group (unassigns counsellors first).
     */
    public function destroy(AttendanceGroup $attendanceGroup)
    {
        // Unassign all counsellors from this group
        TrainingCounsellor::where('attendance_group_id', $attendanceGroup->id)
            ->update(['attendance_group_id' => null]);

        $attendanceGroup->delete();

        return response()->json(['message' => 'PSG group deleted successfully.']);
    }

    /**
     * Allocate a counsellor to a PSG group.
     */
    public function allocate(Request $request, AttendanceGroup $attendanceGroup)
    {
        $validated = $request->validate([
            'tc_id' => 'required|exists:training_counsellors,id',
        ]);

        $tc = TrainingCounsellor::findOrFail($validated['tc_id']);

        if ($tc->counsellor_type !== 'Trainee') {
            return response()->json(['message' => 'Only Trainee counsellors can be allocated to a PSG group.'], 422);
        }

        $tc->update(['attendance_group_id' => $attendanceGroup->id]);

        return response()->json([
            'message' => "{$tc->name} allocated to {$attendanceGroup->name}.",
        ]);
    }

    /**
     * Remove a counsellor from their PSG group.
     */
    public function deallocate(Request $request, AttendanceGroup $attendanceGroup)
    {
        $validated = $request->validate([
            'tc_id' => 'required|exists:training_counsellors,id',
        ]);

        $tc = TrainingCounsellor::where('id', $validated['tc_id'])
            ->where('attendance_group_id', $attendanceGroup->id)
            ->firstOrFail();

        $tc->update(['attendance_group_id' => null]);

        return response()->json([
            'message' => "{$tc->name} removed from {$attendanceGroup->name}.",
        ]);
    }

    /**
     * List counsellors NOT yet in any group (for allocation picker).
     */
    public function unassigned(Request $request)
    {
        $tcs = TrainingCounsellor::whereNull('attendance_group_id')
            ->where('counsellor_type', 'Trainee')
            ->select('id', 'name', 'email', 'uuid')
            ->orderBy('name')
            ->get();

        return response()->json($tcs);
    }

    /**
     * Toggle active status of the group.
     */
    public function toggleStatus(AttendanceGroup $attendanceGroup)
    {
        $attendanceGroup->update([
            'is_active' => !$attendanceGroup->is_active,
        ]);

        return response()->json($attendanceGroup);
    }

    /**
     * List discussion messages for a PSG group.
     */
    public function discussions(AttendanceGroup $attendanceGroup)
    {
        $discussions = $attendanceGroup->discussions()
            ->with('user:id,name,email')
            ->orderBy('created_at')
            ->get();

        return response()->json($discussions);
    }

    /**
     * Post a new discussion message to a PSG group.
     */
    public function postDiscussion(Request $request, AttendanceGroup $attendanceGroup)
    {
        $validated = $request->validate([
            'message' => 'required|string|max:5000',
        ]);

        $discussion = $attendanceGroup->discussions()->create([
            'user_id' => $request->user()->id,
            'message' => $validated['message'],
        ]);

        $discussion->load('user:id,name,email');

        return response()->json($discussion, 201);
    }
}
