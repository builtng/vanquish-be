<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\TrainingCounsellor;
use App\Models\User;
use App\Support\Impersonation;
use Illuminate\Http\Request;

/**
 * "View portal": admins and super admins see a counsellor's portal,
 * read-only, for 30 minutes. See BlockImpersonationWrites.
 */
class ImpersonationController extends Controller
{
    public function start(Request $request, TrainingCounsellor $tc)
    {
        $admin = $request->user();
        if (!$admin->isAdmin()) {
            return response()->json(['message' => 'Unauthorized. Admin access required.'], 403);
        }

        $counsellorUser = User::where('training_counsellor_id', $tc->id)
            ->where('role', 'counsellor')
            ->orderByDesc('is_active')
            ->orderBy('id')
            ->first();

        if (!$counsellorUser) {
            return response()->json(['message' => "{$tc->name} does not have a portal account yet."], 422);
        }

        $expiresAt = now()->addMinutes(Impersonation::MINUTES);
        $token = $counsellorUser->createToken(Impersonation::tokenName($admin->id), ['*'], $expiresAt);

        ActivityLog::create([
            'user_id' => $admin->id,
            'action' => 'impersonation_started',
            'model_type' => TrainingCounsellor::class,
            'model_id' => $tc->id,
            'description' => "{$admin->name} started viewing {$tc->name}'s portal ({$tc->tc_id}), read-only",
            'changes' => [
                'counsellor_user_id' => $counsellorUser->id,
                'token_id' => $token->accessToken->id,
                'expires_at' => $expiresAt->toIso8601String(),
            ],
            'ip_address' => $request->ip(),
            'user_agent' => substr((string) $request->userAgent(), 0, 255),
        ]);

        return response()->json([
            'token' => $token->plainTextToken,
            'expires_at' => $expiresAt->toIso8601String(),
            'read_only' => true,
            'counsellor' => [
                'id' => $tc->id,
                'uuid' => $tc->uuid,
                'tc_id' => $tc->tc_id,
                'name' => $tc->name,
            ],
            'user' => $counsellorUser->fresh(),
        ]);
    }

    /**
     * Ends an impersonation. Called from the impersonation tab with its own
     * token, or by the admin with the token_id they were given.
     */
    public function stop(Request $request)
    {
        $user = $request->user();
        $token = Impersonation::token($request);

        if (!$token) {
            if (!$user->isAdmin()) {
                return response()->json(['message' => 'Unauthorized. Admin access required.'], 403);
            }
            $request->validate(['token_id' => 'required|integer']);
            $token = \Laravel\Sanctum\PersonalAccessToken::whereKey($request->token_id)
                ->where('name', Impersonation::tokenName($user->id))
                ->first();
            if (!$token) {
                return response()->json(['message' => 'Already ended.']);
            }
        }

        $adminId = Impersonation::adminId($token);
        $counsellorUser = $token->tokenable;
        $tc = $counsellorUser?->training_counsellor_id ? TrainingCounsellor::withTrashed()->find($counsellorUser->training_counsellor_id) : null;
        $admin = $adminId ? User::find($adminId) : null;
        $tokenId = $token->id;
        $token->delete();

        ActivityLog::create([
            'user_id' => $adminId,
            'action' => 'impersonation_ended',
            'model_type' => TrainingCounsellor::class,
            'model_id' => $tc?->id,
            'description' => ($admin?->name ?? 'An admin') . ' stopped viewing ' . ($tc?->name ?? 'a counsellor') . "'s portal",
            'changes' => ['token_id' => $tokenId],
            'ip_address' => $request->ip(),
            'user_agent' => substr((string) $request->userAgent(), 0, 255),
        ]);

        return response()->json(['message' => 'Stopped viewing the portal.']);
    }
}
