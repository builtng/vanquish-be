<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class DebugApiMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        try {
            $response = $next($request);
            @file_put_contents('/tmp/api_logs.txt', "[" . date('Y-m-d H:i:s') . "] " . $request->method() . " " . $request->fullUrl() . " " . $response->getStatusCode() . "\n", FILE_APPEND);
            return $response;
        } catch (\Throwable $e) {
            @file_put_contents('/tmp/api_logs.txt', "[" . date('Y-m-d H:i:s') . "] ERROR " . $request->method() . " " . $request->fullUrl() . " - " . $e->getMessage() . " in " . $e->getFile() . ":" . $e->getLine() . "\n", FILE_APPEND);
            throw $e;
        }
    }
}
