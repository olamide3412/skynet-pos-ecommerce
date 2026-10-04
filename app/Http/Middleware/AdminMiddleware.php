<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use App\Enums\RoleEnums;

class AdminMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        if (auth('web')->check() && (auth('web')->user()->role === RoleEnums::Administrator->value || auth('web')->user()->role === RoleEnums::SuperAdministrator->value)) {
            return $next($request);
        }

        abort(403, 'Unauthorized access to admin area.');
        return response('Unauthorized', 403);
    }
}
