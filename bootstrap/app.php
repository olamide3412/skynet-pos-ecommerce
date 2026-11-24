<?php

use App\Http\Middleware\AdminMiddleware;
use App\Http\Middleware\Authenticate;
use App\Http\Middleware\HandleInertiaRequests;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Auth\Middleware\RedirectIfAuthenticated;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->web(append: [
            \App\Http\Middleware\CaptureReferralCode::class,
            HandleInertiaRequests::class,
        ]);
        $middleware->alias([
            'admin' => AdminMiddleware::class,
            'auth' => Authenticate::class,
            'guest' => RedirectIfAuthenticated::class,
            'super_admin' => \App\Http\Middleware\SuperAdminOnly::class,
            'admin_only' => \App\Http\Middleware\AdminOrSuperAdminOnly::class,
            'role' => \Spatie\Permission\Middleware\RoleMiddleware::class,
            'permission' => \Spatie\Permission\Middleware\PermissionMiddleware::class,
            'role_or_permission' => \Spatie\Permission\Middleware\RoleOrPermissionMiddleware::class,
        ]);
        $middleware->validateCsrfTokens(except:[
            'flw-webhook',
            'webhook/paystack',
            'webhook/flutterwave',
            'admin/pos-integration/connect',
            'api/webhooks/*',
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->respond(function (Response $response, \Throwable $exception, Request $request) {
            if ($response->getStatusCode() === 419) {
                if ($request->expectsJson() && !$request->header('X-Inertia')) {
                    return response()->json([
                        'message' => 'Your session token expired. Refreshing page — please try again.',
                        'expired' => true,
                    ], 419);
                }

                return back()->with('warning', 'The page expired due to inactivity or a session update. It has been refreshed — please try again.');
            }

            return $response;
        });
    })->create();
