<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Client;
use App\Models\TrainingCounsellor;
use App\Models\ClientTcMatch;
use Illuminate\Http\Request;

class ActivityLogController extends Controller
{
    public function index(Request $request)
    {
        // Ensure user is authenticated and has staff/admin role
        $user = $request->user();
        if (!$user || !in_array($user->role, ['admin', 'staff'])) {
            return response()->json([
                'message' => 'Unauthorized. Staff or admin access required.',
            ], 403);
        }

        $query = ActivityLog::with(['user'])->orderBy('id', 'desc');

        if ($request->has('action')) {
            $query->where('action', $request->action);
        }

        if ($request->has('model_type')) {
            $query->where('model_type', $request->model_type);
        }

        // Filter by model_id (specific record ID)
        if ($request->has('model_id')) {
            $query->where('model_id', $request->model_id);
        }

        // Filter by user_id (admin who performed the action)
        if ($request->has('user_id')) {
            $query->where('user_id', $request->user_id);
        }

        // Filter by date range
        if ($request->has('date_from')) {
            $query->whereDate('created_at', '>=', $request->date_from);
        }

        if ($request->has('date_to')) {
            $query->whereDate('created_at', '<=', $request->date_to);
        }

        $perPage = $request->get('per_page', 50);
        $logs = $query->paginate($perPage);

        // Transform logs to include related model UUIDs and user information
        $logs->getCollection()->transform(function ($log) {
            $data = $log->toArray();

            // Ensure user information is included
            if ($log->user) {
                $data['user'] = [
                    'id' => $log->user->id,
                    'name' => $log->user->name,
                    'email' => $log->user->email,
                    'role' => $log->user->role,
                ];
            } else {
                $data['user'] = null;
            }

            // Add related model UUID if available
            if ($log->model_type && $log->model_id) {
                if ($log->model_type === Client::class) {
                    $client = Client::find($log->model_id);
                    if ($client) {
                        $data['client_uuid'] = $client->uuid;
                        $data['client_id'] = $client->client_id;
                        $data['client_name'] = $client->name;
                    }
                } elseif ($log->model_type === TrainingCounsellor::class) {
                    $tc = TrainingCounsellor::find($log->model_id);
                    if ($tc) {
                        $data['tc_uuid'] = $tc->uuid;
                        $data['tc_id'] = $tc->tc_id;
                        $data['tc_name'] = $tc->name;
                    }
                } elseif ($log->model_type === ClientTcMatch::class) {
                    $match = ClientTcMatch::with(['client', 'tc'])->find($log->model_id);
                    if ($match) {
                        if ($match->client) {
                            $data['client_uuid'] = $match->client->uuid;
                            $data['client_id'] = $match->client->client_id;
                            $data['client_name'] = $match->client->name;
                        }
                        if ($match->tc) {
                            $data['tc_uuid'] = $match->tc->uuid;
                            $data['tc_id'] = $match->tc->tc_id;
                            $data['tc_name'] = $match->tc->name;
                        }
                    }
                }
            }

            return $data;
        });

        return response()->json($logs);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'action' => 'required|string|max:255',
            'model_type' => 'required|string',
            'model_id' => 'required|integer',
            'description' => 'required|string',
            'changes' => 'nullable|array',
        ]);

        $log = ActivityLog::create([
            'user_id' => $request->user()->id,
            'action' => $validated['action'],
            'model_type' => $validated['model_type'],
            'model_id' => $validated['model_id'],
            'description' => $validated['description'],
            'changes' => $validated['changes'] ?? null,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        return response()->json($log, 201);
    }

    public function update(Request $request, $id)
    {
        $log = ActivityLog::findOrFail($id);
        $user = $request->user();

        // Only allow updating if user is admin/staff, or if they created the note
        if (!in_array($user->role, ['admin', 'staff']) && $log->user_id !== $user->id) {
            return response()->json([
                'message' => 'Unauthorized. You can only edit your own notes.',
            ], 403);
        }

        // Only allow updating description for admin notes
        $validated = $request->validate([
            'description' => 'required|string',
        ]);

        $log->update([
            'description' => $validated['description'],
        ]);

        return response()->json($log);
    }

    public function destroy(Request $request, $id)
    {
        $log = ActivityLog::findOrFail($id);
        $user = $request->user();

        // Only allow deleting if user is admin/staff, or if they created the note
        if (!in_array($user->role, ['admin', 'staff']) && $log->user_id !== $user->id) {
            return response()->json([
                'message' => 'Unauthorized. You can only delete your own notes.',
            ], 403);
        }

        $log->delete();

        return response()->json(['message' => 'Activity log deleted successfully']);
    }
}
