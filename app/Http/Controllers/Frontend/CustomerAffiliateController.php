<?php

namespace App\Http\Controllers\Frontend;

use App\Http\Controllers\Controller;
use App\Models\Affiliate;
use App\Models\AffiliateWithdrawal;
use App\Models\StoreSetting;
use App\Services\Referral\AffiliateWalletService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class CustomerAffiliateController extends Controller
{
    public function __construct(
        protected AffiliateWalletService $walletService
    ) {}

    public function index(Request $request)
    {
        $customer = $request->user('customer');
        $affiliate = Affiliate::where('customer_id', $customer->id)->first();

        $minWithdrawal = (float) StoreSetting::get('referral_min_withdrawal', '5000');
        $allowApplications = StoreSetting::get('referral_allow_applications', '1') === '1';
        $referralEnabled = StoreSetting::get('referral_enabled', '1') === '1';

        $globalCommissionType = StoreSetting::get('referral_commission_type', 'percentage');
        $globalCommissionRate = (float) StoreSetting::get('referral_commission_rate', '5');
        $globalMinOrderAmount = (float) StoreSetting::get('referral_min_order_amount', '0');

        if (!$affiliate) {
            return Inertia::render('Frontend/Account/ReferralProgram', [
                'has_applied'         => false,
                'allow_applications'  => $allowApplications,
                'referral_enabled'    => $referralEnabled,
                'default_commission'  => [
                    'type'             => $globalCommissionType,
                    'rate'             => $globalCommissionRate,
                    'min_order_amount' => $globalMinOrderAmount,
                ],
            ]);
        }

        $affiliate->loadMissing(['customer']);

        $pendingEarnings = (float) $affiliate->commissions()
            ->where('status', 'pending')
            ->sum('commission_amount');

        $activeCommissionType = $affiliate->commission_type ?: $globalCommissionType;
        $activeCommissionRate = $affiliate->commission_rate !== null ? (float)$affiliate->commission_rate : $globalCommissionRate;
        $activeMinOrderAmount = $affiliate->min_order_amount !== null ? (float)$affiliate->min_order_amount : $globalMinOrderAmount;

        $commissions = $affiliate->commissions()
            ->with('order:id,tracking_number,total_amount,created_at')
            ->latest('id')
            ->paginate(10, ['*'], 'commissions_page')
            ->withQueryString();

        $transactions = $affiliate->walletTransactions()
            ->latest('id')
            ->paginate(10, ['*'], 'ledger_page')
            ->withQueryString();

        $withdrawals = $affiliate->withdrawals()
            ->latest('id')
            ->paginate(10, ['*'], 'withdrawals_page')
            ->withQueryString();

        $hasPendingWithdrawal = $affiliate->withdrawals()
            ->where('status', 'pending')
            ->exists();

        return Inertia::render('Frontend/Account/ReferralProgram', [
            'has_applied'            => true,
            'affiliate'              => $affiliate,
            'status'                 => $affiliate->status,
            'referral_url'           => $affiliate->referral_url,
            'referral_code'          => $affiliate->referral_code,
            'commission_display'     => [
                'type'             => $activeCommissionType,
                'rate'             => $activeCommissionRate,
                'min_order_amount' => $activeMinOrderAmount,
            ],
            'stats' => [
                'wallet_balance'     => (float) $affiliate->wallet_balance,
                'total_earned'       => (float) $affiliate->total_earned,
                'total_withdrawn'    => (float) $affiliate->total_withdrawn,
                'pending_earnings'   => $pendingEarnings,
                'visits_count'       => $affiliate->visits()->count(),
                'referred_orders'    => $affiliate->commissions()->count(),
            ],
            'min_withdrawal'         => $minWithdrawal,
            'has_pending_withdrawal' => $hasPendingWithdrawal,
            'commissions'            => $commissions,
            'transactions'           => $transactions,
            'withdrawals'            => $withdrawals,
        ]);
    }

    public function apply(Request $request)
    {
        $allowApplications = StoreSetting::get('referral_allow_applications', '1') === '1';
        if (!$allowApplications) {
            return back()->with('error', 'New affiliate applications are currently closed.');
        }

        $customer = $request->user('customer');

        if (Affiliate::where('customer_id', $customer->id)->exists()) {
            return back()->with('error', 'You have already applied for the referral program.');
        }

        $validated = $request->validate([
            'bank_name'        => 'required|string|max:100',
            'account_number'   => 'required|string|digits_between:10,12',
            'account_name'     => 'required|string|max:150',
            'application_note' => 'nullable|string|max:1000',
        ]);

        $code = Affiliate::generateUniqueCode($customer->name);

        Affiliate::create([
            'customer_id'      => $customer->id,
            'referral_code'    => $code,
            'status'           => 'pending',
            'bank_name'        => $validated['bank_name'],
            'account_number'   => $validated['account_number'],
            'account_name'     => $validated['account_name'],
            'application_note' => $validated['application_note'] ?? null,
        ]);

        return back()->with('success', 'Your affiliate application has been submitted! Our team will review and approve your account shortly.');
    }

    public function requestWithdrawal(Request $request)
    {
        $customer = $request->user('customer');
        $affiliate = Affiliate::where('customer_id', $customer->id)->firstOrFail();

        if ($affiliate->status !== 'approved') {
            return back()->with('error', 'Only approved affiliates can request withdrawals.');
        }

        $minWithdrawal = (float) StoreSetting::get('referral_min_withdrawal', '5000');

        $validated = $request->validate([
            'amount'         => "required|numeric|min:{$minWithdrawal}",
            'bank_name'      => 'required|string|max:100',
            'account_number' => 'required|string|digits_between:10,12',
            'account_name'   => 'required|string|max:150',
        ]);

        $requestedAmount = (float) $validated['amount'];

        if ($affiliate->wallet_balance < $requestedAmount) {
            return back()->with('error', "Insufficient wallet balance. You have ₦" . number_format($affiliate->wallet_balance, 2) . " available.");
        }

        // Check if there is already a pending withdrawal
        if ($affiliate->withdrawals()->where('status', 'pending')->exists()) {
            return back()->with('error', 'You already have a pending withdrawal request in queue. Please wait for it to be processed.');
        }

        // Update default bank details on affiliate profile for future convenience
        $affiliate->update([
            'bank_name'      => $validated['bank_name'],
            'account_number' => $validated['account_number'],
            'account_name'   => $validated['account_name'],
        ]);

        $withdrawal = AffiliateWithdrawal::create([
            'affiliate_id'   => $affiliate->id,
            'amount'         => $requestedAmount,
            'status'         => 'pending',
            'bank_name'      => $validated['bank_name'],
            'account_number' => $validated['account_number'],
            'account_name'   => $validated['account_name'],
        ]);

        // Place a hold on wallet balance
        $this->walletService->holdForWithdrawal($affiliate, $requestedAmount, $withdrawal);

        return back()->with('success', "Withdrawal request for ₦" . number_format($requestedAmount, 2) . " submitted. You will be credited via bank transfer once processed.");
    }
}

