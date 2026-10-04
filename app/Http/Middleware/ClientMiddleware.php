<?php

namespace App\Http\Middleware;

use App\Enums\StatusEnums;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class ClientMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next, $guard = 'client'): HttpResponse | RedirectResponse | JsonResponse
    {
      // Check if client is authenticated
        if (Auth::guard($guard)->check()) {
            $client = Auth::guard($guard)->user();

            // Check if client account is disabled
            if ($client->status === StatusEnums::Disable->value) {
                Auth::guard($guard)->logout();
                return $this->unauthorizedResponse($request, 'Your account has been disabled. Please contact support.');
            }

            // Check if client account is suspended
            if ($client->status === StatusEnums::Suspendened->value) {
                Auth::guard($guard)->logout();
                return $this->unauthorizedResponse($request, 'Your account has been suspended. Please contact support.');
            }

            return $next($request);

        }

        return $this->unauthorizedResponse($request, 'Please log in to access this page.');

    }

    private function unauthorizedResponse($request, $message)
    {
        if ($request->expectsJson()) {
            return response()->json(['error' => $message], 403);
        }

        return redirect()->route('login')->with('error', $message);
    }
}



