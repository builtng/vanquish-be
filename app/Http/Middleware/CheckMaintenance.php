<?php

namespace App\Http\Middleware;

use Closure;
use App\Models\ServiceSetting;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckMaintenance
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $setting = ServiceSetting::where('service_name', 'Maintenance')->first();

        if ($setting && $setting->capacity_full) {
            // Check if there is an authenticated admin (using sanctum guard explicitly for public routes)
            $user = $request->user() ?: auth('sanctum')->user();

            if ($user && ($user->role === 'admin' || $user->role === 'staff')) {
                return $next($request);
            }

            return response()->json([
                'message' => $setting->capacity_message ?? 'The system is currently undergoing maintenance. Registration and intake are temporarily disabled.',
                'maintenance_mode' => true
            ], 503);
        }

        return $next($request);
    }
}
