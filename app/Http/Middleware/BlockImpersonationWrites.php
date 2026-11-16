<?php

namespace App\Http\Middleware;

use App\Support\Impersonation;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * An admin viewing a counsellor's portal can look but not change anything:
 * any request other than GET/HEAD made with an impersonation token is
 * refused. The only exception is ending the impersonation itself.
 */
class BlockImpersonationWrites
{
    public function handle(Request $request, Closure $next): Response
    {
        if (!in_array($request->method(), ['GET', 'HEAD', 'OPTIONS'], true)
            && Impersonation::active($request)
            && !$request->routeIs('admin.impersonate.stop')) {
            return response()->json([
                'message' => 'Read-only: you are viewing this counsellor\'s portal and cannot make changes.',
            ], 403);
        }

        return $next($request);
    }
}
