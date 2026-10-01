<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AttendanceGroup;
use App\Models\PsgSessionLog;
use App\Models\PsgSessionAttendee;
use App\Models\TrainingCounsellor;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class PsgSessionController extends Controller
{
    /**
     * Public route to get group & assigned counsellors by public token.
     */
    public function getForm($token)
    {
        $group = AttendanceGroup::where('public_token', $token)->first();

        if (!$group) {
            return response()->json(['message' => 'Invalid or expired attendance link.'], 404);
        }

        $counsellors = TrainingCounsellor::where('attendance_group_id', $group->id)
            ->select('id', 'tc_id', 'name', 'email')
            ->orderBy('name')
            ->get();

        return response()->json([
            'group' => [
                'id' => $group->id,
                'name' => $group->name,
                'day_of_week' => $group->day_of_week,
            ],
            'counsellors' => $counsellors,
        ]);
    }

    /**
     * Public route to submit supervisor session attendance.
     */
    public function submitSession(Request $request, $token)
    {
        $group = AttendanceGroup::where('public_token', $token)->first();

        if (!$group) {
            return response()->json(['message' => 'Invalid or expired attendance link.'], 404);
        }

        $validated = $request->validate([
            'session_date' => 'required|date',
            'supervisor_name' => 'required|string|max:255',
            'activities' => 'required|string',
            'notes' => 'nullable|string',
            'attendance' => 'required|array', // Keys are training_counsellor_id, values are boolean
            'comments' => 'nullable|array',   // Keys are training_counsellor_id, values are string or null
        ]);

        // Check if there is already a log for this group on this date
        $exists = PsgSessionLog::where('attendance_group_id', $group->id)
            ->whereDate('session_date', $validated['session_date'])
            ->exists();

        if ($exists) {
            return response()->json([
                'message' => 'Attendance has already been logged for this group on this date.'
            ], 422);
        }

        DB::beginTransaction();
        try {
            // Create new log
            $sessionLog = PsgSessionLog::create([
                'attendance_group_id' => $group->id,
                'session_date' => $validated['session_date'],
                'supervisor_name' => $validated['supervisor_name'],
                'activities' => $validated['activities'],
                'notes' => $validated['notes'],
            ]);

            // Get all counsellors currently in this group to make sure we create attendee records for all of them
            $counsellors = TrainingCounsellor::where('attendance_group_id', $group->id)->get();

            foreach ($counsellors as $tc) {
                $attended = isset($validated['attendance'][$tc->id]) ? (bool)$validated['attendance'][$tc->id] : false;
                $comment = isset($validated['comments'][$tc->id]) ? $validated['comments'][$tc->id] : null;

                PsgSessionAttendee::create([
                    'psg_session_log_id' => $sessionLog->id,
                    'training_counsellor_id' => $tc->id,
                    'attended' => $attended,
                    'comment' => $comment,
                ]);
            }

            DB::commit();
            return response()->json(['message' => 'Attendance logged successfully.'], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error submitting PSG attendance: ' . $e->getMessage());
            return response()->json(['message' => 'An error occurred while logging attendance.'], 500);
        }
    }

    /**
     * Get counsellor's own session attendance history.
     */
    public function mySessions(Request $request)
    {
        $user = $request->user();

        if (!$user || $user->role !== 'counsellor' || !$user->training_counsellor_id) {
            return response()->json(['message' => 'Unauthorized. Counsellor access required.'], 403);
        }

        $attendeeRecords = PsgSessionAttendee::where('training_counsellor_id', $user->training_counsellor_id)
            ->with(['sessionLog.attendanceGroup'])
            ->get()
            ->map(function ($record) {
                return [
                    'id' => $record->id,
                    'session_date' => $record->sessionLog->session_date->format('Y-m-d'),
                    'supervisor_name' => $record->sessionLog->supervisor_name,
                    'activities' => $record->sessionLog->activities,
                    'notes' => $record->sessionLog->notes,
                    'attended' => $record->attended,
                    'comment' => $record->comment,
                ];
            })
            ->sortByDesc('session_date')
            ->values();

        return response()->json($attendeeRecords);
    }

    /**
     * Admin view of a group's sessions.
     */
    public function groupLogs(AttendanceGroup $attendanceGroup)
    {
        $logs = PsgSessionLog::where('attendance_group_id', $attendanceGroup->id)
            ->with(['attendees.trainingCounsellor' => function ($q) {
                $q->select('id', 'name', 'email', 'tc_id');
            }])
            ->orderBy('session_date', 'desc')
            ->get();

        return response()->json($logs);
    }
}
