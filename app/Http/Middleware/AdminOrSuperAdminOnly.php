<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AdminOrSuperAdminOnly
{
    /**
     * Handle an incoming request.
     * Only allow Administrator and Super Administrator.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user('web');

        if (!$user || !$user->isAdminOrSuperAdmin()) {
            if ($request->expectsJson()) {
                return response()->json(['message' => 'Administrator or Super Administrator privilege required.'], 403);
            }
            abort(403, 'Access denied. Only Administrators and Super Administrators can manage customer records.');
        }

        return $next($request);
    }
}

