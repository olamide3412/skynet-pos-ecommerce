<?php

namespace Tests\Feature;

use App\Models\Affiliate;
use App\Models\AffiliateCommission;
use App\Models\Customer;
use App\Models\Order;
use App\Models\StoreSetting;
use App\Services\Referral\AffiliateCommissionService;
use App\Services\Referral\ReferralTrackingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AffiliateTrackingAndCommissionTest extends TestCase
{
    use RefreshDatabase;

    protected Customer $affiliateCustomer;
    protected Affiliate $affiliate;

    protected function setUp(): void
    {
        parent::setUp();

        StoreSetting::set('referral_enabled', '1');
        StoreSetting::set('referral_commission_type', 'percentage');
        StoreSetting::set('referral_commission_rate', '10'); // 10%
        StoreSetting::set('referral_holding_days', '0'); // Instant release for test

        $this->affiliateCustomer = Customer::create([
            'name'     => 'Affiliate Promoter',
            'email'    => 'promoter@example.com',
            'password' => bcrypt('password'),
            'phone'    => '08011112222',
        ]);

        $this->affiliate = Affiliate::create([
            'customer_id'   => $this->affiliateCustomer->id,
            'referral_code' => 'PROMO10',
            'status'        => 'approved',
        ]);
    }

    public function test_approved_referral_link_attaches_cookie_and_logs_visit(): void
    {
        $response = $this->get('/?ref=PROMO10');

        $response->assertOk();
        $response->assertCookie(ReferralTrackingService::COOKIE_NAME, 'PROMO10');
        $this->assertDatabaseHas('affiliate_referral_visits', [
            'affiliate_id' => $this->affiliate->id,
        ]);
    }

    public function test_unapproved_referral_link_does_not_attach_cookie(): void
    {
        $pendingAffiliate = Affiliate::create([
            'customer_id'   => Customer::create(['name' => 'Pending', 'email' => 'p@ex.com', 'password' => 'secret'])->id,
            'referral_code' => 'PENDING99',
            'status'        => 'pending',
        ]);

        $response = $this->get('/?ref=PENDING99');

        $response->assertOk();
        $response->assertCookieMissing(ReferralTrackingService::COOKIE_NAME);
    }

    public function test_referred_order_creates_pending_commission(): void
    {
        $buyer = Customer::create([
            'name'     => 'Shopper Buyer',
            'email'    => 'buyer@example.com',
            'password' => bcrypt('password'),
        ]);

        $order = Order::create([
            'customer_id'     => $buyer->id,
            'subtotal'        => 20000,
            'discount_amount' => 5000, // Net base: 15,000
            'shipping_fee'    => 1500,
            'total_amount'    => 16500,
            'status'          => 'pending',
            'billing_email'   => 'buyer@example.com',
        ]);

        $commissionService = app(AffiliateCommissionService::class);
        $commission = $commissionService->createPending($order, 'PROMO10');

        $this->assertNotNull($commission);
        $this->assertEquals(15000, (float)$commission->order_amount_base);
        // 10% of 15,000 = 1,500
        $this->assertEquals(1500, (float)$commission->commission_amount);
        $this->assertEquals('pending', $commission->status);

        $order->refresh();
        $this->assertEquals($this->affiliate->id, $order->affiliate_id);
        $this->assertEquals('PROMO10', $order->referral_code);
    }

    public function test_custom_affiliate_override_rate_takes_precedence(): void
    {
        // Set custom flat rate ₦2,500 for this affiliate
        $this->affiliate->update([
            'commission_type' => 'flat',
            'commission_rate' => 2500,
        ]);

        $order = Order::create([
            'subtotal'        => 50000,
            'total_amount'    => 50000,
            'status'          => 'pending',
            'billing_email'   => 'another@example.com',
        ]);

        $commissionService = app(AffiliateCommissionService::class);
        $commission = $commissionService->createPending($order, 'PROMO10');

        $this->assertEquals('flat', $commission->commission_type);
        $this->assertEquals(2500, (float)$commission->commission_amount);
    }

    public function test_self_referral_is_blocked(): void
    {
        $order = Order::create([
            'customer_id'   => $this->affiliateCustomer->id, // Affiliate buying for themselves
            'subtotal'      => 30000,
            'total_amount'  => 30000,
            'status'        => 'pending',
            'billing_email' => 'promoter@example.com',
        ]);

        $commissionService = app(AffiliateCommissionService::class);
        $commission = $commissionService->createPending($order, 'PROMO10');

        $this->assertNull($commission);
        $this->assertDatabaseMissing('affiliate_commissions', ['order_id' => $order->id]);
    }

    public function test_order_completion_releases_commission_into_wallet(): void
    {
        $order = Order::create([
            'subtotal'      => 10000,
            'total_amount'  => 10000,
            'status'        => 'pending',
            'billing_email' => 'buyer@example.com',
        ]);

        $commissionService = app(AffiliateCommissionService::class);
        $commission = $commissionService->createPending($order, 'PROMO10');

        $this->assertEquals('pending', $commission->status);

        // When order completes (with 0 holding days), it releases immediately into wallet
        $commissionService->handleOrderCompleted($order);

        $commission->refresh();
        $this->assertEquals('available', $commission->status);

        $this->affiliate->refresh();
        $this->assertEquals(1000, (float)$this->affiliate->wallet_balance);
        $this->assertEquals(1000, (float)$this->affiliate->total_earned);

        $this->assertDatabaseHas('affiliate_wallet_transactions', [
            'affiliate_id' => $this->affiliate->id,
            'type'         => 'credit',
            'amount'       => 1000,
        ]);
    }

    public function test_order_cancellation_reverses_commission_and_debits_wallet(): void
    {
        $order = Order::create([
            'subtotal'      => 10000,
            'total_amount'  => 10000,
            'status'        => 'pending',
            'billing_email' => 'buyer@example.com',
        ]);

        $commissionService = app(AffiliateCommissionService::class);
        $commissionService->createPending($order, 'PROMO10');
        $commissionService->handleOrderCompleted($order);

        $this->affiliate->refresh();
        $this->assertEquals(1000, (float)$this->affiliate->wallet_balance);

        // Order cancelled afterwards
        $commissionService->handleOrderCancelled($order, 'Customer returned items');

        $this->affiliate->refresh();
        $this->assertEquals(0, (float)$this->affiliate->wallet_balance);

        $this->assertDatabaseHas('affiliate_wallet_transactions', [
            'affiliate_id' => $this->affiliate->id,
            'type'         => 'debit',
            'amount'       => 1000,
        ]);

        $this->assertDatabaseHas('affiliate_commissions', [
            'order_id' => $order->id,
            'status'   => 'reversed',
        ]);
    }

    public function test_order_below_store_minimum_order_threshold_does_not_create_commission_but_records_attribution(): void
    {
        StoreSetting::set('referral_min_order_amount', '15000');

        $order = Order::create([
            'subtotal'        => 12000,
            'discount_amount' => 2000, // Net base: 10,000 < 15,000 threshold
            'shipping_fee'    => 6000, // Even if total with shipping is 16,000
            'total_amount'    => 16000,
            'status'          => 'pending',
            'billing_email'   => 'buyer@example.com',
        ]);

        $commissionService = app(AffiliateCommissionService::class);
        $commission = $commissionService->createPending($order, 'PROMO10');

        $this->assertNull($commission);
        $this->assertDatabaseMissing('affiliate_commissions', ['order_id' => $order->id]);

        // Verify attribution was still recorded on the order
        $order->refresh();
        $this->assertEquals($this->affiliate->id, $order->affiliate_id);
        $this->assertEquals('PROMO10', $order->referral_code);
    }

    public function test_order_meeting_store_minimum_order_threshold_creates_commission(): void
    {
        StoreSetting::set('referral_min_order_amount', '15000');

        $order = Order::create([
            'subtotal'        => 20000,
            'discount_amount' => 3000, // Net base: 17,000 >= 15,000 threshold
            'shipping_fee'    => 2000,
            'total_amount'    => 19000,
            'status'          => 'pending',
            'billing_email'   => 'buyer@example.com',
        ]);

        $commissionService = app(AffiliateCommissionService::class);
        $commission = $commissionService->createPending($order, 'PROMO10');

        $this->assertNotNull($commission);
        $this->assertEquals(17000, (float)$commission->order_amount_base);
        $this->assertEquals(1700, (float)$commission->commission_amount); // 10% of 17,000
    }

    public function test_affiliate_custom_min_order_threshold_overrides_store_default(): void
    {
        StoreSetting::set('referral_min_order_amount', '50000'); // Store default is high

        // Affiliate has custom lower threshold of 10,000
        $this->affiliate->update([
            'min_order_amount' => 10000,
        ]);

        $order = Order::create([
            'subtotal'        => 15000,
            'discount_amount' => 0, // Net base: 15,000 (below 50k store, above 10k partner)
            'total_amount'    => 15000,
            'status'          => 'pending',
            'billing_email'   => 'buyer@example.com',
        ]);

        $commissionService = app(AffiliateCommissionService::class);
        $commission = $commissionService->createPending($order, 'PROMO10');

        $this->assertNotNull($commission);
        $this->assertEquals(15000, (float)$commission->order_amount_base);
        $this->assertEquals(1500, (float)$commission->commission_amount);
    }

    public function test_zero_min_order_threshold_qualifies_any_order_amount(): void
    {
        StoreSetting::set('referral_min_order_amount', '0');

        $order = Order::create([
            'subtotal'        => 500,
            'discount_amount' => 0,
            'total_amount'    => 500,
            'status'          => 'pending',
            'billing_email'   => 'buyer@example.com',
        ]);

        $commissionService = app(AffiliateCommissionService::class);
        $commission = $commissionService->createPending($order, 'PROMO10');

        $this->assertNotNull($commission);
        $this->assertEquals(500, (float)$commission->order_amount_base);
        $this->assertEquals(50, (float)$commission->commission_amount);
    }
}

