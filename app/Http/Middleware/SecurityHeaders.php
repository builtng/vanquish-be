<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SecurityHeaders
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        // Security headers
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('X-Frame-Options', 'DENY');
        $response->headers->set('X-XSS-Protection', '1; mode=block');
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        
        // HSTS - Only in production with HTTPS
        if (config('app.env') === 'production' && $request->secure()) {
            $response->headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
        }

        // Content Security Policy
        $csp = "default-src 'self'; script-src 'self' 'unsafe-inline' 'unsafe-eval' https://stats.pusher.com; style-src 'self' 'unsafe-inline' https://fonts.googleapis.com; img-src 'self' data: https: *.vqtmanagement.com; font-src 'self' data: https://fonts.gstatic.com; connect-src 'self' wss://*.vqtmanagement.com https://vqtmanagement.com https://*.vqtmanagement.com https://api.stripe.com *.pusher.com wss://*.pusher.com http://localhost:8000 http://127.0.0.1:8000 http://localhost:3000 http://127.0.0.1:3000; frame-src https://js.stripe.com;";
        $response->headers->set('Content-Security-Policy', $csp);

        // Remove server information
        $response->headers->remove('X-Powered-By');
        $response->headers->remove('Server');

        return $response;
    }
}

