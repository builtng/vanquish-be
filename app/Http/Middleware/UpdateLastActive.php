<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

use Illuminate\Support\Facades\Cache;

class UpdateLastActive
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if ($user = $request->user()) {
            if ($user->role === 'counsellor' && $user->training_counsellor_id) {
                $cacheKey = "last-active-update-{$user->id}";

                // Only update database if we haven't updated in the last 5 minutes
                if (!Cache::has($cacheKey)) {
                    try {
                        \App\Models\TrainingCounsellor::where('id', $user->training_counsellor_id)
                            ->update(['last_activity' => now()]);
                        
                        Cache::put($cacheKey, true, now()->addMinutes(5));
                    } catch (\Exception $e) {
                        // Silently fail if DB or Cache is unavailable/deadlocked
                        // Non-critical update should not break the request
                        \Illuminate\Support\Facades\Log::warning("Failed to update last_activity: " . $e->getMessage());
                    }
                }
            }
        }

        return $next($request);
    }
}
