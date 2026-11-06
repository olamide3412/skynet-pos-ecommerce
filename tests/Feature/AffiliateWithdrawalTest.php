<?php

namespace Tests\Feature;

use App\Models\Affiliate;
use App\Models\AffiliateWithdrawal;
use App\Models\Customer;
use App\Models\StoreSetting;
use App\Models\User;
use App\Services\Referral\AffiliateWalletService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AffiliateWithdrawalTest extends TestCase
{
    use RefreshDatabase;

    protected Customer $customer;
    protected Affiliate $affiliate;
    protected User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        StoreSetting::set('referral_enabled', '1');
        StoreSetting::set('referral_allow_applications', '1');
        StoreSetting::set('referral_min_withdrawal', '5000');

        $this->customer = Customer::create([
            'name'     => 'Affiliate User',
            'email'    => 'user@example.com',
            'password' => bcrypt('password'),
        ]);

        $this->affiliate = Affiliate::create([
            'customer_id'    => $this->customer->id,
            'referral_code'  => 'AFFIL123',
            'status'         => 'approved',
            'wallet_balance' => 20000,
            'total_earned'   => 20000,
        ]);

        $this->admin = User::factory()->create([
            'role' => 'administrator',
        ]);
        $this->seed(\Database\Seeders\RolePermissionSeeder::class);
        $this->admin->assignRole('Administrator');
    }

    public function test_customer_can_submit_withdrawal_request(): void
    {
        $response = $this->actingAs($this->customer, 'customer')
            ->post(route('customer.referral.withdraw'), [
                'amount'         => 8000,
                'bank_name'      => 'Zenith Bank',
                'account_number' => '1234567890',
                'account_name'   => 'Affiliate User',
            ]);

        $response->assertSessionHasNoErrors();
        $response->assertRedirect();

        $this->affiliate->refresh();
        // 20,000 - 8,000 = 12,000
        $this->assertEquals(12000, (float)$this->affiliate->wallet_balance);

        $this->assertDatabaseHas('affiliate_withdrawals', [
            'affiliate_id'   => $this->affiliate->id,
            'amount'         => 8000,
            'status'         => 'pending',
            'account_number' => '1234567890',
        ]);

        $this->assertDatabaseHas('affiliate_wallet_transactions', [
            'affiliate_id' => $this->affiliate->id,
            'type'         => 'debit',
            'amount'       => 8000,
            'source_type'  => 'withdrawal_hold',
        ]);
    }

    public function test_cannot_withdraw_below_minimum_or_above_balance(): void
    {
        // 1. Below minimum (₦2,000 < ₦5,000 min)
        $resp1 = $this->actingAs($this->customer, 'customer')
            ->post(route('customer.referral.withdraw'), [
                'amount'         => 2000,
                'bank_name'      => 'GTBank',
                'account_number' => '1234567890',
                'account_name'   => 'User',
            ]);
        $resp1->assertSessionHasErrors('amount');

        // 2. Above balance (₦50,000 > ₦20,000 balance)
        $resp2 = $this->actingAs($this->customer, 'customer')
            ->post(route('customer.referral.withdraw'), [
                'amount'         => 50000,
                'bank_name'      => 'GTBank',
                'account_number' => '1234567890',
                'account_name'   => 'User',
            ]);
        $resp2->assertSessionHas('error');
    }

    public function test_admin_can_mark_withdrawal_as_paid(): void
    {
        $withdrawal = AffiliateWithdrawal::create([
            'affiliate_id'   => $this->affiliate->id,
            'amount'         => 10000,
            'status'         => 'pending',
            'bank_name'      => 'Kuda Bank',
            'account_number' => '1234567890',
            'account_name'   => 'Affiliate User',
        ]);

        $response = $this->actingAs($this->admin)
            ->post(route('admin.affiliates.withdrawals.mark-paid', $withdrawal->id), [
                'payment_reference' => 'NIP-TX-89218291',
                'admin_note'        => 'Transferred via corporate banking',
            ]);

        $response->assertSessionHasNoErrors();

        $withdrawal->refresh();
        $this->assertEquals('paid', $withdrawal->status);
        $this->assertEquals('NIP-TX-89218291', $withdrawal->payment_reference);

        $this->affiliate->refresh();
        $this->assertEquals(10000, (float)$this->affiliate->total_withdrawn);
    }

    public function test_admin_rejecting_withdrawal_refunds_wallet(): void
    {
        $withdrawal = AffiliateWithdrawal::create([
            'affiliate_id'   => $this->affiliate->id,
            'amount'         => 15000,
            'status'         => 'pending',
            'bank_name'      => 'Access Bank',
            'account_number' => '1234567890',
            'account_name'   => 'Affiliate User',
        ]);

        // Place hold first (simulate user request)
        app(AffiliateWalletService::class)->holdForWithdrawal($this->affiliate, 15000, $withdrawal);
        $this->affiliate->refresh();
        $this->assertEquals(5000, (float)$this->affiliate->wallet_balance);

        $response = $this->actingAs($this->admin)
            ->post(route('admin.affiliates.withdrawals.reject', $withdrawal->id), [
                'admin_note' => 'Account name mismatch',
            ]);

        $response->assertSessionHasNoErrors();

        $withdrawal->refresh();
        $this->assertEquals('rejected', $withdrawal->status);

        // Wallet is refunded back to 20,000
        $this->affiliate->refresh();
        $this->assertEquals(20000, (float)$this->affiliate->wallet_balance);

        $this->assertDatabaseHas('affiliate_wallet_transactions', [
            'affiliate_id' => $this->affiliate->id,
            'type'         => 'credit',
            'amount'       => 15000,
            'source_type'  => 'withdrawal_refund',
        ]);
    }
}

