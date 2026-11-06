<?php

namespace App\Http\Middleware;

use App\Services\Referral\ReferralTrackingService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CaptureReferralCode
{
    public function __construct(
        protected ReferralTrackingService $trackingService
    ) {}

    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $refCode = $request->query('ref');
        $cookieToAttach = null;

        if (!empty($refCode) && is_string($refCode)) {
            $cookieToAttach = $this->trackingService->handleReferralVisit($refCode, $request);
        }

        $response = $next($request);

        if ($cookieToAttach && method_exists($response, 'withCookie')) {
            $response->withCookie($cookieToAttach);
        }

        return $response;
    }
}

