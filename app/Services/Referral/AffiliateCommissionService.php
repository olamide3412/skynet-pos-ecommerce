<?php

namespace App\Services\Referral;

use App\Models\Affiliate;
use App\Models\AffiliateCommission;
use App\Models\Order;
use App\Models\StoreSetting;
use Illuminate\Support\Facades\Log;

class AffiliateCommissionService
{
    public function __construct(
        protected AffiliateWalletService $walletService,
        protected ReferralTrackingService $trackingService
    ) {}

    /**
     * Calculate commission based on affiliate override or store default.
     */
    public function calculate(Affiliate $affiliate, float $baseAmount): array
    {
        $type = $affiliate->commission_type ?: StoreSetting::get('referral_commission_type', 'percentage');
        $rate = $affiliate->commission_rate !== null
            ? (float) $affiliate->commission_rate
            : (float) StoreSetting::get('referral_commission_rate', '5');

        if ($type === 'flat') {
            $amount = round($rate, 2);
        } else {
            // Percentage: default 5% of order base amount (subtotal - discount)
            $amount = round(($baseAmount * $rate) / 100, 2);
        }

        return [
            'type'   => $type,
            'rate'   => $rate,
            'amount' => max(0, $amount),
        ];
    }

    /**
     * Create a pending commission for an order if referred by an approved affiliate.
     */
    public function createPending(Order $order, ?string $referralCode): ?AffiliateCommission
    {
        if (!$this->trackingService->isEnabled() || empty($referralCode)) {
            return null;
        }

        $affiliate = $this->trackingService->findApprovedAffiliate($referralCode);
        if (!$affiliate) {
            return null;
        }

        // Prevent self-referral
        if ($order->customer_id && (int)$order->customer_id === (int)$affiliate->customer_id) {
            Log::info("Self-referral ignored for Order #{$order->id} by Affiliate #{$affiliate->id}.");
            return null;
        }

        if (!empty($order->billing_email) && strtolower($order->billing_email) === strtolower($affiliate->customer?->email ?? '')) {
            Log::info("Self-referral (email match) ignored for Order #{$order->id} by Affiliate #{$affiliate->id}.");
            return null;
        }

        // Avoid duplicate commission for same order
        if (AffiliateCommission::where('order_id', $order->id)->exists()) {
            return null;
        }

        // Calculate base: product subtotal after discounts (excluding tax, shipping, service fee)
        $baseAmount = max(0, (float)$order->subtotal - (float)$order->discount_amount);

        // Always record attribution on the order so admin/analytics know this customer was referred
        $order->update([
            'affiliate_id'  => $affiliate->id,
            'referral_code' => $affiliate->referral_code,
        ]);

        // Determine qualifying minimum order threshold (affiliate override or store default)
        $minThreshold = $affiliate->min_order_amount !== null
            ? (float) $affiliate->min_order_amount
            : (float) StoreSetting::get('referral_min_order_amount', '0');

        if ($minThreshold > 0 && $baseAmount < $minThreshold) {
            Log::info("Order #{$order->id} base amount (₦{$baseAmount}) is below minimum referral threshold (₦{$minThreshold}) for Affiliate #{$affiliate->id}. Attribution recorded, but commission skipped.");
            return null;
        }

        $calc = $this->calculate($affiliate, $baseAmount);

        if ($calc['amount'] <= 0) {
            return null;
        }

        return AffiliateCommission::create([
            'affiliate_id'      => $affiliate->id,
            'order_id'          => $order->id,
            'order_amount_base' => $baseAmount,
            'commission_type'   => $calc['type'],
            'commission_rate'   => $calc['rate'],
            'commission_amount' => $calc['amount'],
            'status'            => 'pending',
            'available_at'      => null,
        ]);
    }

    /**
     * Handle order completion: schedule commission availability based on holding period.
     */
    public function handleOrderCompleted(Order $order): void
    {
        $commission = AffiliateCommission::where('order_id', $order->id)
            ->where('status', 'pending')
            ->first();

        if (!$commission) {
            return;
        }

        $holdingDays = (int) StoreSetting::get('referral_holding_days', '7');

        if ($holdingDays <= 0) {
            // Immediate release
            $this->releaseCommission($commission);
        } else {
            // Schedule release after holding period (protects against returns)
            $commission->update([
                'available_at' => now()->addDays($holdingDays),
            ]);
        }
    }

    /**
     * Release a single commission to the affiliate's wallet.
     */
    public function releaseCommission(AffiliateCommission $commission): bool
    {
        if ($commission->status !== 'pending') {
            return false;
        }

        $affiliate = $commission->affiliate;
        if (!$affiliate || $affiliate->status === 'suspended') {
            Log::warning("Skipped releasing commission #{$commission->id}: Affiliate suspended or missing.");
            return false;
        }

        $this->walletService->credit(
            $affiliate,
            (float) $commission->commission_amount,
            'commission',
            $commission->id,
            "Referral commission for Order #{$commission->order_id} ({$commission->order?->tracking_number})"
        );

        $commission->update([
            'status'       => 'available',
            'available_at' => now(),
        ]);

        return true;
    }

    /**
     * Sweep and release all pending commissions whose holding period has passed.
     */
    public function releaseEligibleCommissions(): int
    {
        $eligible = AffiliateCommission::with(['affiliate', 'order'])
            ->where('status', 'pending')
            ->whereNotNull('available_at')
            ->where('available_at', '<=', now())
            ->get();

        $count = 0;
        foreach ($eligible as $commission) {
            if ($this->releaseCommission($commission)) {
                $count++;
            }
        }

        return $count;
    }

    /**
     * Handle order cancellation: reverse pending or available commission.
     */
    public function handleOrderCancelled(Order $order, string $reason = 'Order cancelled'): void
    {
        $commission = AffiliateCommission::where('order_id', $order->id)->first();

        if (!$commission || in_array($commission->status, ['reversed'], true)) {
            return;
        }

        $affiliate = $commission->affiliate;

        if ($commission->status === 'available' && $affiliate) {
            // Claw back credited money from wallet
            $this->walletService->debit(
                $affiliate,
                (float) $commission->commission_amount,
                'commission_clawback',
                $commission->id,
                "Reversal of commission for cancelled Order #{$order->id} ({$order->tracking_number})"
            );
        }

        $commission->update([
            'status'          => 'reversed',
            'reversed_at'     => now(),
            'reversed_reason' => $reason,
        ]);
    }
}

