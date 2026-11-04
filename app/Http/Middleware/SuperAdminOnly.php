<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SuperAdminOnly
{
    /**
     * Handle an incoming request.
     * Only allow Super Administrator.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user('web');

        if (!$user || !$user->isSuperAdmin()) {
            if ($request->expectsJson()) {
                return response()->json(['message' => 'Super Administrator privilege required.'], 403);
            }
            abort(403, 'Access denied. Only the Super Administrator can manage roles, permissions, and security assignments.');
        }

        return $next($request);
    }
}

