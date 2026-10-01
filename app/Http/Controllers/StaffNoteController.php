<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

use App\Models\StaffNote;
use App\Events\StaffNoteSent;
use Illuminate\Support\Facades\Auth;

class StaffNoteController extends Controller
{
    /**
     * Store a newly created note from admin to staff.
     */
    public function store(Request $request)
    {
        $request->validate([
            'staff_id' => 'required|exists:users,id',
            'note' => 'required|string',
        ]);

        $sender = Auth::user();

        // Allow any staff or admin to send notes
        if (!in_array($sender->role, ['admin', 'super_admin', 'manager', 'supervisor', 'hr', 'staff', 'compliance_officer'])) {
            return response()->json(['success' => false, 'message' => 'Unauthorized entry'], 403);
        }

        $note = StaffNote::create([
            'staff_id' => $request->staff_id,
            'admin_id' => $sender->id,
            'note' => $request->note,
        ]);

        // Dispatch Event for Real-time Notification
        try {
            broadcast(new StaffNoteSent($note))->toOthers();
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('StaffNoteSent broadcast failed: ' . $e->getMessage());
        }

        return response()->json([
            'success' => true,
            'message' => 'Note dropped successfully.',
            'data' => $note
        ], 201);
    }

    /**
     * Display a listing of notes (received or sent).
     */
    public function index(Request $request)
    {
        $user = Auth::user();
        $type = $request->query('type', 'received'); // 'received' or 'sent'

        if ($type === 'sent') {
            $notes = StaffNote::where('admin_id', $user->id)
                ->with('staff:id,name')
                ->orderBy('created_at', 'desc')
                ->get();
        } else {
            $notes = StaffNote::where('staff_id', $user->id)
                ->with('admin:id,name')
                ->orderBy('created_at', 'desc')
                ->get();
        }

        return response()->json([
            'success' => true,
            'data' => $notes
        ]);
    }

    /**
     * Get unread notes for the authenticated staff member.
     */
    public function getUnreadNotes()
    {
        $user = Auth::user();

        $notes = StaffNote::where('staff_id', $user->id)
            ->where('is_read', false)
            ->with('admin:id,name')
            ->orderBy('created_at', 'asc')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $notes
        ]);
    }

    /**
     * Mark a specific note as read.
     */
    public function markAsRead($id)
    {
        $user = Auth::user();

        $note = StaffNote::where('staff_id', $user->id)->where('id', $id)->first();

        if (!$note) {
            return response()->json(['success' => false, 'message' => 'Note not found'], 404);
        }

        $note->is_read = true;
        $note->save();

        return response()->json([
            'success' => true,
            'message' => 'Note marked as read.'
        ]);
    }
}
