<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SessionNote;
use App\Models\ActivityLog;
use App\Models\Client;
use Illuminate\Http\Request;

class SessionNoteController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $query = SessionNote::query()->with([
            'client:id,name,uuid,client_id', 
            'counsellor:id,name,uuid,tc_id'
        ]);

        if ($user->role === 'counsellor') {
            $query->where('training_counsellor_id', '=', $user->training_counsellor_id);
        }

        if ($request->has('client_id')) {
            $query->where('client_id', $request->client_id);
        }

        if ($request->has('training_counsellor_id')) {
            $query->where('training_counsellor_id', $request->training_counsellor_id);
        }

        if ($request->has('type') && $request->type !== 'all') {
            $query->where('type', $request->type);
        }

        // Only return notes whose client still exists (exclude deleted clients)
        $query->whereHas('client');

        return response()->json($query->orderBy('created_at', 'desc')->get());
    }

    public function store(Request $request)
    {
        $user = $request->user();
        if ($user->role !== 'counsellor' || !$user->training_counsellor_id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $request->validate([
            'client_id' => 'required|exists:clients,id',
            'type' => 'required|string|in:weekly,block_summary,risk_update',
            'content' => 'required|array',
        ]);

        $note = SessionNote::create([
            'training_counsellor_id' => $user->training_counsellor_id,
            'client_id' => $request->client_id,
            'type' => $request->type,
            'content' => $request->input('content'),
            'status' => 'submitted',
        ]);

        // Log session notes activity
        $client = Client::find($request->client_id);
        $typeLabel = str_replace('_', ' ', $request->type);
        
        ActivityLog::create([
            'user_id' => $user->id,
            'action' => 'session_note_submitted',
            'model_type' => SessionNote::class,
            'model_id' => $note->id,
            'client_id' => $client->id,
            'description' => "{$user->name} submitted session notes ({$typeLabel}) for client {$client->name}",
            'ip_address' => $request->ip(),
        ]);

        return response()->json($note, 201);
    }

    public function show(Request $request, $id)
    {
        $user = $request->user();
        $note = SessionNote::with(['client:id,name,uuid,client_id', 'counsellor:id,name,uuid,tc_id'])->findOrFail($id);

        if ($user->role === 'counsellor' && $note->training_counsellor_id !== $user->training_counsellor_id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        return response()->json($note);
    }
}
