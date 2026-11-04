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
        $user = auth('web')->user();

        if ($user && $user->canAccessBackoffice()) {
            return $next($request);
        }

        abort(403, 'Unauthorized access to admin area.');
        return response('Unauthorized', 403);
    }
}
