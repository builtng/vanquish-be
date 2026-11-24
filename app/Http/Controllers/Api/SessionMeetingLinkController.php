<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Session;
use Illuminate\Http\Request;

/**
 * Set or clear the video-call link of one booked therapy session.
 * Admins only (admin, super_admin): not other staff roles, not counsellors.
 * An admin viewing a counsellor's portal is read-only and is refused by
 * BlockImpersonationWrites before reaching here.
 */
class SessionMeetingLinkController extends Controller
{
    public function update(Request $request, $id)
    {
        $user = $request->user();
        $session = Session::findOrFail($id);

        if (!$user->isAdmin()) {
            return response()->json(['message' => 'Only an admin can set the meeting link.'], 403);
        }

        $validated = $request->validate([
            'meeting_link' => ['nullable', 'string', 'max:500', 'url:http,https'],
        ], [
            'meeting_link.url' => 'Enter a full link starting with https:// (e.g. your Zoom or Teams link).',
        ]);

        $link = trim((string) ($validated['meeting_link'] ?? ''));
        $session->update(['meeting_link' => $link === '' ? null : $link]);

        return response()->json([
            'message' => $link === '' ? 'Meeting link removed.' : 'Meeting link saved.',
            'session' => $session->fresh(),
        ]);
    }
}
