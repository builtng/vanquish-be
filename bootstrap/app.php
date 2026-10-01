<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        channels: __DIR__.'/../routes/channels.php',
        web: __DIR__ . '/../routes/web.php',
        api: __DIR__ . '/../routes/api.php',
        commands: __DIR__ . '/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->api(prepend: [
            \App\Http\Middleware\DebugApiMiddleware::class,
            \App\Http\Middleware\SecurityHeaders::class,
            \Illuminate\Http\Middleware\HandleCors::class,
            \App\Http\Middleware\UpdateLastActive::class,
        ]);

        // Rate limiting is handled per-route in routes/api.php
        // This allows different limits for public vs authenticated routes

        // Alias middleware for easier use
        $middleware->alias([
            'admin' => \App\Http\Middleware\EnsureUserIsAdmin::class,
            'staff' => \App\Http\Middleware\EnsureUserIsStaff::class,
            'maintenance' => \App\Http\Middleware\CheckMaintenance::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Return JSON errors for API routes instead of redirects
        $exceptions->shouldRenderJsonWhen(function ($request, Throwable $e) {
            if ($request->is('api/*')) {
                return true;
            }
            return $request->expectsJson();
        });

        // Don't expose sensitive error details in production
        if (config('app.env') === 'production') {
            $exceptions->render(function (Throwable $e, $request) {
                if ($request->is('api/*')) {
                    // Respect ValidationException
                    if ($e instanceof \Illuminate\Validation\ValidationException) {
                        return response()->json([
                            'message' => $e->getMessage(),
                            'errors' => $e->errors(),
                        ], $e->status);
                    }

                    // Respect AuthenticationException
                    if ($e instanceof \Illuminate\Auth\AuthenticationException) {
                        return response()->json([
                            'message' => $e->getMessage(),
                        ], 401);
                    }

                    // Respect generic HttpException
                    $statusCode = method_exists($e, 'getStatusCode') ? $e->getStatusCode() : 500;
                    $message = $statusCode === 500
                        ? 'An error occurred. Please try again later.'
                        : $e->getMessage();

                    // Log the actual error
                    if ($statusCode === 500) {
                        \Illuminate\Support\Facades\Log::error('API 500 Error: ' . $e->getMessage(), [
                            'exception' => $e,
                            'url' => $request->fullUrl(),
                            'method' => $request->method(),
                            'user_id' => $request->user()?->id,
                        ]);
                    }

                    // Sanitize error message to prevent database credentials/SQL leakage
                    $errorMessage = $e->getMessage();
                    if ($e instanceof \Illuminate\Database\QueryException || $e instanceof \PDOException) {
                        if (isset($e->errorInfo[1]) && $e->errorInfo[1] == 1062) {
                            if (preg_match("/Duplicate entry '([^']+)' for key '([^']+)'/", $e->getMessage(), $matches)) {
                                $errorMessage = "Duplicate record detected: '{$matches[1]}' is already taken.";
                            } else {
                                $errorMessage = 'A duplicate record already exists.';
                            }
                        } else {
                            $errorMessage = 'A database error occurred while processing the request.';
                        }
                    }

                    $responsePayload = [
                        'message' => $message,
                        'error'   => $errorMessage,
                    ];

                    if (config('app.debug')) {
                        $responsePayload['file'] = $e->getFile();
                        $responsePayload['line'] = $e->getLine();
                    }

                    return response()->json($responsePayload, $statusCode);
                }
            });
        }

        // Also add logic outside of production to log errors
        $exceptions->render(function (Throwable $e, $request) {
            if ($request->is('api/*') && !config('app.debug')) {
                 \Illuminate\Support\Facades\Log::error('API Error: ' . $e->getMessage(), [
                     'exception' => $e,
                     'url' => $request->fullUrl(),
                 ]);
            }
        });
    })->create();
