<?php

namespace App\Services\Referral;

use App\Models\Affiliate;
use App\Models\AffiliateReferralVisit;
use App\Models\StoreSetting;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Cookie;

class ReferralTrackingService
{
    public const COOKIE_NAME = 'skynet_ref';

    /**
     * Check if referral program is enabled globally.
     */
    public function isEnabled(): bool
    {
        return StoreSetting::get('referral_enabled', '1') === '1';
    }

    /**
     * Find an approved affiliate by referral code.
     */
    public function findApprovedAffiliate(?string $code): ?Affiliate
    {
        if (empty($code)) {
            return null;
        }

        return Affiliate::with('customer')
            ->where('referral_code', strtoupper(trim($code)))
            ->where('status', 'approved')
            ->first();
    }

    /**
     * Process referral click, log visit, and create attribution cookie.
     */
    public function handleReferralVisit(string $code, Request $request): ?Cookie
    {
        if (!$this->isEnabled()) {
            return null;
        }

        $affiliate = $this->findApprovedAffiliate($code);
        if (!$affiliate) {
            return null;
        }

        // Throttle visit logging per IP & affiliate within 1 hour to prevent click-spam
        $ip = $request->ip();
        $recentVisit = AffiliateReferralVisit::where('affiliate_id', $affiliate->id)
            ->where('ip_address', $ip)
            ->where('created_at', '>=', now()->subHour())
            ->exists();

        if (!$recentVisit) {
            AffiliateReferralVisit::create([
                'affiliate_id' => $affiliate->id,
                'ip_address'   => $ip,
                'user_agent'   => substr((string)$request->userAgent(), 0, 500),
                'landing_url'  => substr((string)$request->fullUrl(), 0, 500),
                'created_at'   => now(),
            ]);
        }

        $cookieDays = (int) StoreSetting::get('referral_cookie_days', '30');
        $cookieDays = max(1, min($cookieDays, 365));

        // Create cookie (lifetime in minutes: days * 1440)
        return cookie(
            self::COOKIE_NAME,
            $affiliate->referral_code,
            $cookieDays * 1440,
            null,
            null,
            false,
            false // accessible via client if needed
        );
    }
}

