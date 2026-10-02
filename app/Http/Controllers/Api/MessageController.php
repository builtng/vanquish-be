<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Message;
use App\Models\TrainingCounsellor;
use App\Models\ActivityLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use App\Mail\DynamicEmail;
use App\Events\MessageSent;
use App\Events\MessageRead;
use App\Models\EmailLog;
use Illuminate\Support\Facades\Cache;

class MessageController extends Controller
{
    /**
     * Get messages for the authenticated user
     */
    public function index(Request $request)
    {
        $user = $request->user();
        $query = Message::with(['fromUser', 'toTrainingCounsellor', 'toUser', 'relatedClient', 'relatedConsultation', 'parentMessage.fromUser']);

        $folder = $request->get('folder', 'inbox');
        $peerId = $request->get('peer_id');
        $peerType = $request->get('peer_type', 'user'); // user, tc, or group

        // ── Admin Group Chat (counsellors talking to staff) ──
        if ($peerId === 'admin_group' && $peerType === 'group' && $user->isCounsellor() && $user->training_counsellor_id) {
            $tcId = $user->training_counsellor_id;
            $query->where(function($q) use ($user, $tcId) {
                // Messages sent by this counsellor to staff
                $q->where(function($inner) use ($user) {
                    $inner->where('from_user_id', $user->id)
                          ->where('type', 'counsellor_to_staff');
                })
                // OR messages from staff back to this TC
                ->orWhere(function($inner) use ($tcId) {
                    $inner->where('to_tc_id', $tcId)
                          ->where('type', 'staff_to_counsellor');
                });
            })->where('is_trashed', false);

            $messages = $query->orderBy('id', 'desc')->paginate(50);
            return response()->json($messages);
        }

        if ($peerId) {
            // Get history between user and peer
            $query->where(function($q) use ($user, $peerId, $peerType) {
                // If peer is a TC, we need to handle messages from their user account as well
                if ($peerType === 'tc') {
                    $tcUserId = \App\Models\User::where('training_counsellor_id', $peerId)->value('id');
                    
                    if ($user->isStaff()) {
                        // GROUP CHAT BEHAVIOR: Staff can see all messages with this TC
                        $q->where(function($inner) use ($peerId, $tcUserId) {
                            // Sent to TC by ANYONE (usually staff)
                            $inner->where('to_tc_id', $peerId);
                            // AND/OR From TC to ANYONE (usually staff)
                            if ($tcUserId) {
                                $inner->orWhere('from_user_id', $tcUserId);
                            }
                        });
                    } else {
                        // TC VIEW: Only show messages involving them
                        $q->where(function($inner) use ($user, $peerId, $tcUserId) {
                            $inner->where('from_user_id', $user->id)
                                  ->where(function($sq) use ($peerId, $tcUserId) {
                                      $sq->where('to_tc_id', $peerId);
                                      if ($tcUserId) $sq->orWhere('to_user_id', $tcUserId);
                                  });
                        })->orWhere(function($inner) use ($user, $tcUserId) {
                            if ($tcUserId) {
                                $inner->where('from_user_id', $tcUserId)
                                      ->where(function($sq) use ($user) {
                                          $sq->where('to_user_id', $user->id);
                                          if ($user->training_counsellor_id) $sq->orWhere('to_tc_id', $user->training_counsellor_id);
                                      });
                            } else {
                                $inner->whereRaw('1 = 0');
                            }
                        });
                    }
                } else {
                    // Standard user-to-user history
                    $q->where(function($inner) use ($user, $peerId) {
                        $inner->where('from_user_id', $user->id)->where('to_user_id', $peerId);
                    })->orWhere(function($inner) use ($user, $peerId) {
                        if ($user->isCounsellor() && $user->training_counsellor_id) {
                            $inner->where('to_tc_id', $user->training_counsellor_id);
                        } else {
                            $inner->where('to_user_id', $user->id);
                        }
                        $inner->where('from_user_id', $peerId);
                    });
                }
            })->where('is_trashed', false);
        } elseif ($user->isCounsellor() && $user->training_counsellor_id) {
            // ... (rest of folder logic remains same)
            if ($folder === 'trash') {
                $query->where(function ($q) use ($user) {
                    $q->where('to_tc_id', $user->training_counsellor_id)
                      ->orWhere('from_user_id', $user->id);
                })->where('is_trashed', true);
            } elseif ($folder === 'sent') {
                $query->where('from_user_id', $user->id)->where('is_trashed', false);
            } else {
                $query->where('to_tc_id', $user->training_counsellor_id)->where('is_trashed', false);
            }
        } else {
            // Staff/admin
            if ($folder === 'trash') {
                $query->where(function ($q) use ($user) {
                    $q->where('to_user_id', $user->id)
                      ->orWhere('from_user_id', $user->id);
                })->where('is_trashed', true);
            } elseif ($folder === 'sent') {
                $query->where('from_user_id', $user->id)->where('is_trashed', false);
            } else {
                $query->where('to_user_id', $user->id)->where('is_trashed', false);
            }
        }

        if ($request->has('unread_only') && $request->unread_only) {
            $query->where('is_read', false);
        }

        $messages = $query->orderBy('id', 'desc')->paginate(50);

        return response()->json($messages);
    }

    /**
     * Get unique conversations for the chat sidebar
     */
    public function conversations(Request $request)
    {
        $user = $request->user();

        // ── Counsellors: always see exactly ONE thread — "Admin Support" ──
        if ($user->isCounsellor() && $user->training_counsellor_id) {
            $tcId = $user->training_counsellor_id;

            // Find the latest message in this counsellor's admin-group thread
            $lastMsg = Message::with(['fromUser'])
                ->where(function($q) use ($user, $tcId) {
                    $q->where(function($inner) use ($user) {
                        $inner->where('from_user_id', $user->id)
                              ->where('type', 'counsellor_to_staff');
                    })->orWhere(function($inner) use ($tcId) {
                        $inner->where('to_tc_id', $tcId)
                              ->where('type', 'staff_to_counsellor');
                    });
                })
                ->where('is_trashed', false)
                ->orderBy('created_at', 'desc')
                ->first();

            // Unread: any staff_to_counsellor message unread for this TC
            $hasUnread = Message::where('to_tc_id', $tcId)
                ->where('type', 'staff_to_counsellor')
                ->where('is_read', false)
                ->where('is_trashed', false)
                ->exists();

            // If no messages yet, return a welcome placeholder
            if (!$lastMsg) {
                return response()->json([
                    [
                        'peer_id'    => 'admin_group',
                        'peer_uuid'  => 'admin_group',
                        'peer_type'  => 'group',
                        'peer_name'  => 'Admin Support',
                        'last_message' => [
                            'id'           => null,
                            'message'      => 'Send a message to the admin team.',
                            'created_at'   => now()->toISOString(),
                            'from_user_id' => null,
                            'to_user_id'   => null,
                            'is_read'      => true,
                        ],
                        'unread_for_user' => false,
                    ]
                ]);
            }

            return response()->json([
                [
                    'peer_id'        => 'admin_group',
                    'peer_uuid'      => 'admin_group',
                    'peer_type'      => 'group',
                    'peer_name'      => 'Admin Support',
                    'last_message'   => $lastMsg,
                    'unread_for_user'=> $hasUnread,
                ]
            ]);
        }
        
        // Find all messages involving the user
        $query = Message::with(['fromUser.trainingCounsellor', 'toUser.trainingCounsellor', 'toTrainingCounsellor'])
            ->where(function($q) use ($user) {
                if ($user->isCounsellor() && $user->training_counsellor_id) {
                    $q->where('to_tc_id', $user->training_counsellor_id)
                      ->orWhere('from_user_id', $user->id);
                } elseif ($user->isStaff()) {
                    // STAFF GROUP CHAT: Staff should see all TC interactions (to/from)
                    $q->where('to_user_id', $user->id)
                      ->orWhere('from_user_id', $user->id)
                      ->orWhereNotNull('to_tc_id')
                      ->orWhereHas('fromUser', function($sub) {
                          $sub->whereNotNull('training_counsellor_id');
                      });
                } else {
                    $q->where('to_user_id', $user->id)
                      ->orWhere('from_user_id', $user->id);
                }
            })
            ->where('is_trashed', false)
            ->orderBy('created_at', 'desc')
            ->orderBy('id', 'desc');

        $allMessages = $query->get();
        
        $conversations = [];
        $seen = [];

        foreach ($allMessages as $msg) {
            // Determine who the other person (peer) is
            $peerId = null;
            $peerType = null;
            $peerName = null;

            // 1. Identify the peer's raw user ID / TC ID
            if ($msg->from_user_id && $msg->from_user_id !== $user->id) {
                // Peer is the sender
                if ($msg->fromUser && $msg->fromUser->training_counsellor_id) {
                    $peerId = $msg->fromUser->training_counsellor_id;
                    $peerType = 'tc';
                    $peerName = $msg->fromUser->name;
                } else {
                    $peerId = $msg->from_user_id;
                    $peerType = 'user';
                    $peerName = $msg->fromUser?->name;
                }
            } else if ($msg->to_tc_id) {
                // Peer is the TC recipient
                if ($user->isCounsellor() && $user->training_counsellor_id == $msg->to_tc_id) {
                    // Current user is the TC recipient; identify sender (peer)
                    $peerId = $msg->from_user_id;
                    $peerType = 'user';
                    $peerName = $msg->fromUser?->name;
                } else {
                    // Staff/Other person viewing TC thread; identify TC as peer
                    $peerId = $msg->to_tc_id;
                    $peerType = 'tc';
                    $peerName = $msg->toTrainingCounsellor?->name;
                }
            } else if ($msg->to_user_id && $msg->to_user_id !== $user->id) {
                // Peer is the user recipient
                if ($msg->toUser && $msg->toUser->training_counsellor_id) {
                    $peerId = $msg->toUser->training_counsellor_id;
                    $peerType = 'tc';
                    $peerName = $msg->toUser->name;
                } else {
                    $peerId = $msg->to_user_id;
                    $peerType = 'user';
                    $peerName = $msg->toUser?->name;
                }
            } else if ($msg->fromUser && $msg->fromUser->training_counsellor_id && $user->isStaff()) {
                // Special case for staff: message FROM a TC (not necessarily TO me, but in a thread I see)
                $peerId = $msg->fromUser->training_counsellor_id;
                $peerType = 'tc';
                $peerName = $msg->fromUser->name;
            }

            if (!$peerId) continue;

            $key = $peerType . '_' . $peerId;
            if (!isset($seen[$key])) {
                $seen[$key] = true;

                $peerUuid = $peerId;
                if ($peerType === 'tc') {
                    if ($msg->to_tc_id == $peerId) {
                        $peerUuid = $msg->toTrainingCounsellor?->uuid ?: $peerId;
                    } else if ($msg->fromUser && $msg->fromUser->training_counsellor_id == $peerId) {
                        $peerUuid = $msg->fromUser->trainingCounsellor?->uuid ?: $peerId;
                    } else if ($msg->toUser && $msg->toUser->training_counsellor_id == $peerId) {
                        $peerUuid = $msg->toUser->trainingCounsellor?->uuid ?: $peerId;
                    }
                }

                // Correct unread status calculation: any unread message addressed TO me in THIS conversation
                $hasUnread = Message::where('is_read', false)
                    ->where('is_trashed', false)
                    ->where(function($q) use ($user, $peerId, $peerType) {
                        // Identified as belonging to this conversation
                        if ($peerType === 'tc') {
                            $tcUserId = \App\Models\User::where('training_counsellor_id', $peerId)->value('id');
                            $q->where(function($inner) use ($peerId, $tcUserId) {
                                // To TC
                                $inner->where('to_tc_id', $peerId);
                                // Or From TC
                                if ($tcUserId) $inner->orWhere('from_user_id', $tcUserId);
                            });
                            
                            // AND addressed TO the current user
                            $q->where(function($inner) use ($user) {
                                if ($user->isCounsellor() && $user->training_counsellor_id) {
                                    $inner->where('to_tc_id', $user->training_counsellor_id);
                                } else {
                                    $inner->where(function($sub) use ($user) {
                                        $sub->where('to_user_id', $user->id)
                                            ->orWhere(function($sub2) {
                                                $sub2->whereNull('to_user_id')
                                                     ->where('type', 'counsellor_to_staff');
                                            });
                                    });
                                }
                            });
                        } else {
                            // Direct User Peer - only messages from peer to me
                            $q->where('from_user_id', (int)$peerId)
                              ->where('to_user_id', $user->id);
                        }
                    })->exists();

                $conversations[] = [
                    'peer_id' => $peerId,
                    'peer_uuid' => $peerUuid,
                    'peer_type' => $peerType,
                    'peer_name' => $peerName ?: 'Unknown User',
                    'last_message' => $msg,
                    'unread_for_user' => $hasUnread, // Specific unread status for current user
                ];
            }
        }

        return response()->json($conversations);
    }


    /**
     * Send a message to counsellors
     */
    public function sendToCounsellor(Request $request)
    {
        try {
            $user = $request->user();

            // Only staff/admin can send messages to counsellors
            if (!$user->isStaff()) {
                return response()->json(['message' => 'Unauthorized'], 403);
            }

            $validated = $request->validate([
                'tc_ids' => 'required|array',
                'tc_ids.*' => 'exists:training_counsellors,id',
                'cc_user_ids' => 'nullable|array',
                'cc_user_ids.*' => 'exists:users,id',
                'subject' => 'required|string|max:255',
                'message' => 'required_without:attachment|string|max:10000',
                'related_client_id' => 'nullable|exists:clients,id',
                'related_consultation_id' => 'nullable|exists:consultations,id',
                'send_email_notification' => 'boolean',
                'attachment' => 'nullable|file|max:10240',
                'reply_to_id' => 'nullable|exists:messages,id',
            ]);

            $ccUsers = $validated['cc_user_ids'] ?? [];
            $recipientTcIds = $validated['tc_ids'];
            
            $attachmentPath = null;
            $attachmentName = null;
            $attachmentType = null;

            if ($request->hasFile('attachment')) {
                $file = $request->file('attachment');
                $attachmentPath = $file->store('message_attachments', 'public');
                $attachmentName = $file->getClientOriginalName();
                $attachmentType = $file->getClientMimeType();
            }
            
            $messages = [];
            
            // Combine all unique targeted user IDs for records
            // For TCs, we need their user records if they have one
            foreach ($recipientTcIds as $tcId) {
                $tc = TrainingCounsellor::with('user')->find($tcId);
                
                if (!$tc) {
                    Log::warning('Counsellor not found during sendToCounsellor', [
                        'tc_id' => $tcId,
                    ]);
                    return response()->json([
                        'message' => 'Counsellor not found'
                    ], 404);
                }
                
                // If the TC doesn't have a user account, we still create the record but don't link to_user_id.
                // It will basically be a 'ghost' message until they have an account, but it will still send email.
                if (!$tc->user) {
                    Log::info('TC has no associated user account, only email notification will be sent', [
                        'tc_id' => $tcId,
                        'email' => $tc->email
                    ]);
                }
                
                $msg = Message::create([
                    'from_user_id' => $user->id,
                    'to_tc_id' => $tc->id,
                    'to_user_id' => $tc->user?->id, // Link to user if exists
                    'subject' => $validated['subject'],
                    'message' => $validated['message'] ?? null,
                    'type' => 'staff_to_counsellor',
                    'cc_users' => $ccUsers,
                    'related_client_id' => $validated['related_client_id'] ?? null,
                    'related_consultation_id' => $validated['related_consultation_id'] ?? null,
                    'attachment_path' => $attachmentPath,
                    'attachment_name' => $attachmentName,
                    'attachment_type' => $attachmentType,
                    'reply_to_id' => $validated['reply_to_id'] ?? null,
                ]);
                
                $messages[] = $msg;

                try {
                    event(new MessageSent($msg));
                } catch (\Exception $e) {
                    Log::error('Failed to broadcast message sent event: ' . $e->getMessage());
                }

                // Send email alert notifying counsellor that a message is waiting (no message content for privacy)
                $baseUrl = rtrim(config('app.frontend_url', 'http://localhost:3000'), '/');
                $loginUrl = $baseUrl . '/counsellor-login';
                $portalUrl = $baseUrl . '/counsellor-portal/messages';
                $conversationKey = "tc_{$tc->id}";

                $counsellorEmails = array_filter(array_unique([$tc->email, $tc->user?->email]));
                foreach ($counsellorEmails as $email) {
                    if ($this->shouldSendNotificationEmail($email, $conversationKey)) {
                        $sent = app(\App\Services\EmailService::class)->sendAndLog(
                            $email,
                            'message_waiting_counsellor',
                            [
                                'tc_name' => $tc->name,
                                'login_url' => $loginUrl,
                                'portal_url' => $portalUrl,
                                'conversation_key' => $conversationKey,
                            ]
                        );
                        if ($sent) {
                            $this->markNotificationEmailSent($email, $conversationKey);
                        }
                    }
                }
            }

            // Also create records for CC users so it shows in their inbox
            $baseUrl = rtrim(config('app.frontend_url', 'http://localhost:3000'), '/');
            $dashboardUrl = $baseUrl . '/dashboard/messages';
            $loginUrl = $baseUrl . '/login';
            foreach ($ccUsers as $ccUserId) {
                $ccUser = \App\Models\User::find($ccUserId);
                if ($ccUser && !in_array($ccUserId, array_column($messages, 'to_user_id'))) {
                    Message::create([
                        'from_user_id' => $user->id,
                        'to_user_id' => $ccUser->id,
                        'to_tc_id' => count($recipientTcIds) === 1 ? $recipientTcIds[0] : null, // Link to TC if sent to a single TC thread
                        'subject' => $validated['subject'],
                        'message' => $validated['message'] ?? null,
                        'type' => 'staff_to_staff',
                        'cc_users' => $ccUsers,
                        'related_client_id' => $validated['related_client_id'] ?? null,
                        'related_consultation_id' => $validated['related_consultation_id'] ?? null,
                        'attachment_path' => $attachmentPath,
                        'attachment_name' => $attachmentName,
                        'attachment_type' => $attachmentType,
                    ]);

                    if ($ccUser->email) {
                        $firstTcId = count($recipientTcIds) > 0 ? $recipientTcIds[0] : 0;
                        $conversationKey = "tc_{$firstTcId}_cc_{$ccUser->id}";
                        if ($this->shouldSendNotificationEmail($ccUser->email, $conversationKey)) {
                            $sent = app(\App\Services\EmailService::class)->sendAndLog(
                                $ccUser->email,
                                'message_waiting_admin',
                                [
                                    'recipient_name' => $ccUser->name,
                                    'sender_name'    => $user->name,
                                    'login_url'      => $loginUrl,
                                    'dashboard_url'  => $dashboardUrl,
                                    'conversation_key' => $conversationKey,
                                ]
                            );
                            if ($sent) {
                                $this->markNotificationEmailSent($ccUser->email, $conversationKey);
                            }
                        }
                    }
                }
            }

            return response()->json([
                'message' => 'Messages sent successfully',
                'count' => count($messages),
            ], 201);

        } catch (\Throwable $e) {
            Log::error('SendToCounsellor Error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'message' => 'Server error, please try again later'
            ], 500);
        }
    }


    /**
     * Get list of staff users (for counsellors to select as recipients)
     */
    public function getStaffList(Request $request)
    {
        $user = $request->user();

        // Staff and counsellors can use this endpoint
        if (!$user->isCounsellor() && !$user->isStaff()) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $staffRoles = ['super_admin', 'admin', 'staff', 'consultation_staff', 'compliance_officer'];

        $staffUsers = \App\Models\User::select('id', 'name', 'email', 'role')
            ->whereIn('role', $staffRoles)
            ->orderBy('name')
            ->get();

        return response()->json($staffUsers);
    }

    /**
     * Send a message from counsellor to staff
     */
    public function sendToStaff(Request $request)
    {
        $user = $request->user();

        // Counsellors and staff can send messages to staff
        if (!$user->isCounsellor() && !$user->isStaff()) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        // ── Admin Group shortcut: counsellor sends to admin group ──
        if ($user->isCounsellor() && $request->get('to_group') === 'admin_group') {
            $attachmentPath = null;
            $attachmentName = null;
            $attachmentType = null;

            if ($request->hasFile('attachment')) {
                $file = $request->file('attachment');
                $attachmentPath = $file->store('message_attachments', 'public');
                $attachmentName = $file->getClientOriginalName();
                $attachmentType = $file->getClientMimeType();
            }

            $msgData = [
                'from_user_id'  => $user->id,
                'to_user_id'    => null,
                'to_tc_id'      => null,
                'subject'       => $request->get('subject', 'Admin Support'),
                'message'       => $request->get('message'),
                'type'          => 'counsellor_to_staff',
                'attachment_path' => $attachmentPath,
                'attachment_name' => $attachmentName,
                'attachment_type' => $attachmentType,
                'reply_to_id'   => $request->get('reply_to_id'),
            ];

            $message = Message::create($msgData);

            try {
                event(new MessageSent($message));
            } catch (\Exception $e) {
                Log::error('Failed to broadcast admin_group message: ' . $e->getMessage());
            }

            // Send email alert to admins notifying that a message is waiting (no message content for privacy)
            $baseUrl = rtrim(config('app.frontend_url', 'http://localhost:3000'), '/');
            $loginUrl = $baseUrl . '/login';
            $dashboardUrl = $baseUrl . '/dashboard/messages';
            $counsellorKey = $user->training_counsellor_id ?? $user->id;
            $conversationKey = "admin_group_tc_{$counsellorKey}";

            $adminUsers = \App\Models\User::whereIn('role', ['super_admin', 'admin'])->get();
            $adminEmailsSent = [];
            foreach ($adminUsers as $adminUser) {
                if ($adminUser->email && !isset($adminEmailsSent[$adminUser->email])) {
                    $adminEmailsSent[$adminUser->email] = true;
                    if ($this->shouldSendNotificationEmail($adminUser->email, $conversationKey)) {
                        $sent = app(\App\Services\EmailService::class)->sendAndLog(
                            $adminUser->email,
                            'message_waiting_admin',
                            [
                                'recipient_name' => $adminUser->name,
                                'sender_name'    => $user->name,
                                'login_url'      => $loginUrl,
                                'dashboard_url'  => $dashboardUrl,
                                'conversation_key' => $conversationKey,
                            ]
                        );
                        if ($sent) {
                            $this->markNotificationEmailSent($adminUser->email, $conversationKey);
                        }
                    }
                }
            }

            return response()->json(['message' => 'Message sent to admin team.'], 201);
        }

        $validated = $request->validate([
            'to_user_ids' => 'required|array',
            'to_user_ids.*' => 'exists:users,id',
            'cc_user_ids' => 'nullable|array',
            'cc_user_ids.*' => 'exists:users,id',
            'subject' => 'required|string|max:255',
            'message' => 'required_without:attachment|string|max:10000',
            'related_client_id' => 'nullable|exists:clients,id',
            'related_consultation_id' => 'nullable|exists:consultations,id',
            'attachment' => 'nullable|file|max:10240',
            'reply_to_id' => 'nullable|exists:messages,id',
        ]);

        $ccUsers = $validated['cc_user_ids'] ?? [];
        $allRecipients = array_unique(array_merge($validated['to_user_ids'], $ccUsers));
        
        $attachmentPath = null;
        $attachmentName = null;
        $attachmentType = null;

        if ($request->hasFile('attachment')) {
            $file = $request->file('attachment');
            $attachmentPath = $file->store('message_attachments', 'public');
            $attachmentName = $file->getClientOriginalName();
            $attachmentType = $file->getClientMimeType();
        }
        
        $sentCount = 0;
        $baseUrl = rtrim(config('app.frontend_url', 'http://localhost:3000'), '/');

        foreach ($allRecipients as $targetUserId) {
            $targetUser = \App\Models\User::findOrFail($targetUserId);

            // Staff can message anyone; Counsellors can ONLY message staff
            if ($user->isCounsellor() && !$targetUser->isStaff()) {
                continue; // Skip non-staff recipients if sender is counsellor
            }

            $msgData = [
                'from_user_id' => $user->id,
                'to_user_id' => $targetUser->id,
                'subject' => $validated['subject'],
                'message' => $validated['message'] ?? null,
                'type' => $user->isCounsellor() ? 'counsellor_to_staff' : ($targetUser->isStaff() ? 'staff_to_staff' : 'staff_to_counsellor'),
                'cc_users' => $ccUsers,
                'attachment_path' => $attachmentPath,
                'attachment_name' => $attachmentName,
                'attachment_type' => $attachmentType,
                'reply_to_id' => $validated['reply_to_id'] ?? null,
            ];

            // If the recipient is a counsellor, ensure to_tc_id is linked for their inbox
            if ($targetUser->isCounsellor() && $targetUser->training_counsellor_id) {
                $msgData['to_tc_id'] = $targetUser->training_counsellor_id;
            }

            $message = Message::create($msgData);
            
            $sentCount++;

            // Broadcast
            try {
                event(new MessageSent($message));
            } catch (\Exception $e) {
                Log::error('Failed to broadcast staff message sent event: ' . $e->getMessage());
            }

            // Send privacy-preserving email alert
            if ($targetUser->isCounsellor()) {
                $counsellorEmails = array_filter(array_unique([
                    $targetUser->trainingCounsellor?->email,
                    $targetUser->email
                ]));
                $portalUrl = $baseUrl . '/counsellor-portal/messages';
                $loginUrl = $baseUrl . '/counsellor-login';
                $tcId = $targetUser->training_counsellor_id ?? $targetUser->id;
                $conversationKey = "tc_{$tcId}";

                foreach ($counsellorEmails as $email) {
                    if ($this->shouldSendNotificationEmail($email, $conversationKey)) {
                        $sent = app(\App\Services\EmailService::class)->sendAndLog(
                            $email,
                            'message_waiting_counsellor',
                            [
                                'tc_name' => $targetUser->name,
                                'login_url' => $loginUrl,
                                'portal_url' => $portalUrl,
                                'conversation_key' => $conversationKey,
                            ]
                        );
                        if ($sent) {
                            $this->markNotificationEmailSent($email, $conversationKey);
                        }
                    }
                }
            } else if ($targetUser->email) {
                $dashboardUrl = $baseUrl . '/dashboard/messages';
                $loginUrl = $baseUrl . '/login';
                $conversationKey = "user_direct_" . min($user->id, $targetUser->id) . "_" . max($user->id, $targetUser->id);

                if ($this->shouldSendNotificationEmail($targetUser->email, $conversationKey)) {
                    $sent = app(\App\Services\EmailService::class)->sendAndLog(
                        $targetUser->email,
                        'message_waiting_admin',
                        [
                            'recipient_name' => $targetUser->name,
                            'sender_name'    => $user->name,
                            'login_url'      => $loginUrl,
                            'dashboard_url'  => $dashboardUrl,
                            'conversation_key' => $conversationKey,
                        ]
                    );
                    if ($sent) {
                        $this->markNotificationEmailSent($targetUser->email, $conversationKey);
                    }
                }
            }
        }

        if ($sentCount === 0) {
            return response()->json(['message' => 'No valid staff recipients found.'], 422);
        }

        return response()->json([
            'message' => 'Message sent successfully to ' . $sentCount . ' recipients',
        ], 201);
    }

    /**
     * Mark message as read
     */
    public function markAsRead(Request $request, $id)
    {
        $user = $request->user();
        $message = Message::findOrFail($id);

        // Check if user has access to this message
        $hasAccess = false;
        if ($user->isCounsellor() && $user->training_counsellor_id) {
            $hasAccess = $message->to_tc_id === $user->training_counsellor_id || $message->from_user_id === $user->id;
        } else {
            $hasAccess = $message->to_user_id === $user->id || $message->from_user_id === $user->id;
        }

        if (!$hasAccess) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $message->update([
            'is_read' => true,
            'read_at' => now(),
        ]);

        // Broadcast read receipt to sender
        try {
            event(new MessageRead($message));
        } catch (\Exception $e) {
            Log::error('Failed to broadcast message read event: ' . $e->getMessage());
        }

        // Reset conversation notification throttle for recipient upon reading
        if ($user->email) {
            $convKeys = [];
            if ($user->isCounsellor() && $user->training_counsellor_id) {
                $convKeys[] = "tc_{$user->training_counsellor_id}";
                $convKeys[] = "admin_group_tc_{$user->training_counsellor_id}";
            } else {
                if ($message->to_user_id === $user->id) {
                    $convKeys[] = "user_direct_" . min($message->from_user_id, $message->to_user_id) . "_" . max($message->from_user_id, $message->to_user_id);
                }
                if ($message->type === 'counsellor_to_staff') {
                    $counsellorKey = $message->fromUser?->training_counsellor_id ?? $message->from_user_id;
                    $convKeys[] = "admin_group_tc_{$counsellorKey}";
                }
            }
            foreach ($convKeys as $k) {
                Cache::forget("msg_notif_" . md5(strtolower(trim($user->email)) . "_{$k}"));
            }
        }

        return response()->json(['message' => 'Message marked as read']);
    }

    /**
     * Get unread message count
     */
    public function unreadCount(Request $request)
    {
        $user = $request->user();
        $query = Message::where('is_read', false)->where('is_trashed', false);

        if ($user->isCounsellor() && $user->training_counsellor_id) {
            $query->where('to_tc_id', $user->training_counsellor_id);
        } else {
            $query->where('to_user_id', $user->id);
        }

        $count = $query->count();

        return response()->json(['count' => $count]);
    }

    /**
     * Trash a message
     */
    public function trash(Request $request, $id)
    {
        $user = $request->user();
        $message = Message::findOrFail($id);

        $message->update([
            'is_trashed' => true,
            'trashed_at' => now(),
        ]);

        return response()->json(['message' => 'Message moved to trash']);
    }

    /**
     * Restore a message from trash
     */
    public function restore(Request $request, $id)
    {
        $user = $request->user();
        $message = Message::findOrFail($id);

        $message->update([
            'is_trashed' => false,
            'trashed_at' => null,
        ]);

        return response()->json(['message' => 'Message restored from trash']);
    }

    /**
     * Permanently delete a message
     */
    public function destroy(Request $request, $id)
    {
        $user = $request->user();
        $message = Message::findOrFail($id);

        $message->delete();

        return response()->json(['message' => 'Message permanently deleted']);
    }

    /**
     * Get a single message
     */
    public function show(Request $request, $id)
    {
        $user = $request->user();
        $message = Message::with(['fromUser', 'toUser', 'toTrainingCounsellor', 'relatedClient', 'relatedConsultation', 'parentMessage.fromUser'])
            ->findOrFail($id);

        // Check access
        $hasAccess = false;
        if ($user->isCounsellor() && $user->training_counsellor_id) {
            $hasAccess = $message->to_tc_id === $user->training_counsellor_id || $message->from_user_id === $user->id;
        } else {
            $hasAccess = $message->to_user_id === $user->id || $message->from_user_id === $user->id;
        }

        if (!$hasAccess) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        return response()->json($message);
    }
    /**
     * Mark an entire conversation as read
     */
    public function markConversationAsRead(Request $request, $peerType, $peerId)
    {
        $user = $request->user();

        // ── Admin Group: counsellor marks admin thread as read ──
        if ($peerId === 'admin_group' && $peerType === 'group' && $user->isCounsellor() && $user->training_counsellor_id) {
            $updated = Message::where('to_tc_id', $user->training_counsellor_id)
                ->where('type', 'staff_to_counsellor')
                ->where('is_read', false)
                ->where('is_trashed', false);
            $msgs = $updated->get();
            $updated->update(['is_read' => true, 'read_at' => now()]);
            foreach ($msgs as $msg) {
                $msg->refresh();
                try {
                    event(new \App\Events\MessageRead($msg));
                } catch (\Exception $e) {
                    \Illuminate\Support\Facades\Log::warning('Failed to broadcast MessageRead: ' . $e->getMessage());
                }
            }
            return response()->json(['message' => 'Admin thread marked as read.', 'count' => $msgs->count()]);
        }

        // ── Staff: marking a TC thread as read ──
        if ($peerType === 'tc' && $user->isStaff()) {
            $tcUserId = \App\Models\User::where('training_counsellor_id', $peerId)->value('id');
            $updated = Message::where('is_read', false)
                ->where('is_trashed', false)
                ->where(function($q) use ($peerId, $tcUserId, $user) {
                    $q->where(function($inner) use ($tcUserId, $user) {
                        if ($tcUserId) {
                            $inner->where('from_user_id', $tcUserId)
                                  ->where(function($sub) use ($user) {
                                      $sub->where('to_user_id', $user->id)
                                          ->orWhereNull('to_user_id');
                                  });
                        }
                    })->orWhere(function($inner) use ($peerId, $user) {
                        $inner->where('to_tc_id', $peerId)
                              ->where('from_user_id', '!=', $user->id);
                    });
                });
            $msgs = $updated->get();
            $updated->update(['is_read' => true, 'read_at' => now()]);
            foreach ($msgs as $msg) {
                $msg->refresh();
                try { event(new \App\Events\MessageRead($msg)); } catch (\Exception $e) {}
            }
            return response()->json(['message' => 'Conversation marked as read.', 'count' => $msgs->count()]);
        }

        $query = Message::where('is_read', false)
            ->where('is_trashed', false)
            ->where(function($q) use ($user, $peerId, $peerType) {
                // Incoming messages to the user in the context of this peer
                if ($peerType === 'tc') {
                    $tcUserId = \App\Models\User::where('training_counsellor_id', $peerId)->value('id');
                    
                    $q->where(function($inner) use ($peerId, $tcUserId) {
                        // From the TC
                        if ($tcUserId) $inner->where('from_user_id', $tcUserId);
                        // OR part of this TC thread (sent to TC by others but me being the recipient/CC)
                        $inner->orWhere('to_tc_id', $peerId);
                    });
                } else {
                    $q->where('from_user_id', (int)$peerId);
                }
                
                // Identify the current user as the recipient
                if ($user->isCounsellor() && $user->training_counsellor_id) {
                    $q->where('to_tc_id', $user->training_counsellor_id);
                } else {
                    $q->where('to_user_id', $user->id);
                }
            });

        $count = $query->count();
        $messages = $query->get();
        $query->update([
            'is_read' => true,
            'read_at' => now(),
        ]);

        // Broadcast read receipts for each message
        foreach ($messages as $msg) {
            $msg->refresh();
            try {
                event(new MessageRead($msg));
            } catch (\Exception $e) {
                // silent error
            }
        }

        return response()->json([
            'message' => 'Conversation marked as read.',
            'count' => $count
        ]);
    }

    /**
     * Delete an entire conversation
     */
    public function deleteConversation(Request $request, $peerType, $peerId)
    {
        $user = $request->user();

        // ── Admin Group: counsellor deletes their admin thread ──
        if ($peerId === 'admin_group' && $peerType === 'group' && $user->isCounsellor() && $user->training_counsellor_id) {
            $count = Message::where(function($q) use ($user) {
                $q->where('from_user_id', $user->id)->where('type', 'counsellor_to_staff');
            })->orWhere(function($q) use ($user) {
                $q->where('to_tc_id', $user->training_counsellor_id)->where('type', 'staff_to_counsellor');
            })->count();
            Message::where(function($q) use ($user) {
                $q->where('from_user_id', $user->id)->where('type', 'counsellor_to_staff');
            })->orWhere(function($q) use ($user) {
                $q->where('to_tc_id', $user->training_counsellor_id)->where('type', 'staff_to_counsellor');
            })->delete();
            return response()->json(['message' => 'Admin thread deleted.', 'count' => $count]);
        }
        
        $query = Message::where(function($q) use ($user, $peerId, $peerType) {
            // Outgoing from current user to peer
            $q->where(function($sq) use ($user, $peerId, $peerType) {
                $sq->where('from_user_id', $user->id);
                if ($peerType === 'tc') {
                    $sq->where('to_tc_id', (int)$peerId);
                } else {
                    $sq->where('to_user_id', (int)$peerId);
                }
            })
            // Incoming from peer to current user
            ->orWhere(function($sq) use ($user, $peerId, $peerType) {
                if ($peerType === 'tc') {
                    $tcUserId = \App\Models\User::where('training_counsellor_id', $peerId)->value('id');
                    $sq->where('from_user_id', $tcUserId ?: -1);
                } else {
                    $sq->where('from_user_id', (int)$peerId);
                }
                
                if ($user->training_counsellor_id) {
                    $sq->where('to_tc_id', $user->training_counsellor_id);
                } else {
                    $sq->where('to_user_id', $user->id);
                }
            });
        });

        $count = $query->count();
        $query->delete();

        return response()->json([
            'message' => 'Conversation deleted successfully.',
            'count' => $count
        ]);
    }

    /**
     * Determine if a notification email should be sent for a given conversation (15-min anti-flooding limit).
     */
    protected function shouldSendNotificationEmail(string $recipientEmail, string $conversationKey): bool
    {
        $cacheKey = "msg_notif_" . md5(strtolower(trim($recipientEmail)) . "_{$conversationKey}");
        if (Cache::has($cacheKey)) {
            return false;
        }

        // Also check recent EmailLog entries in the last 15 minutes as persistence check
        $recentLog = EmailLog::where('email', $recipientEmail)
            ->whereIn('template_name', ['message_waiting_counsellor', 'message_waiting_admin'])
            ->where('created_at', '>=', now()->subMinutes(15))
            ->where('status', '!=', 'failed')
            ->get()
            ->first(function ($log) use ($conversationKey) {
                return isset($log->payload['conversation_key']) && $log->payload['conversation_key'] === $conversationKey;
            });

        return $recentLog === null;
    }

    /**
     * Mark that a notification email was sent for a conversation.
     */
    protected function markNotificationEmailSent(string $recipientEmail, string $conversationKey): void
    {
        $cacheKey = "msg_notif_" . md5(strtolower(trim($recipientEmail)) . "_{$conversationKey}");
        Cache::put($cacheKey, true, now()->addMinutes(15));
    }
}
