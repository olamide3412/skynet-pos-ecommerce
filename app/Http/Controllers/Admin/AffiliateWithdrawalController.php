<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AffiliateWithdrawal;
use App\Services\ActivityLogger;
use App\Services\Referral\AffiliateWalletService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class AffiliateWithdrawalController extends Controller
{
    public function __construct(
        protected AffiliateWalletService $walletService
    ) {}

    protected function authorizePermission(): void
    {
        $user = auth('web')->user();
        if (!$user) {
            abort(403);
        }
        if ($user->role === 'super administrator' || $user->role === 'administrator') {
            return;
        }
        if (!$user->can('affiliates.withdrawals')) {
            abort(403, 'Unauthorized. You do not have permission [affiliates.withdrawals].');
        }
    }

    public function index(Request $request)
    {
        $this->authorizePermission();

        $status = $request->input('status');
        $search = $request->input('search');

        $query = AffiliateWithdrawal::with(['affiliate.customer', 'processor']);

        if ($status && in_array($status, ['pending', 'paid', 'rejected'], true)) {
            $query->where('status', $status);
        }

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('payment_reference', 'like', "%{$search}%")
                    ->orWhere('account_number', 'like', "%{$search}%")
                    ->orWhere('account_name', 'like', "%{$search}%")
                    ->orWhereHas('affiliate.customer', function ($cq) use ($search) {
                        $cq->where('name', 'like', "%{$search}%")
                            ->orWhere('email', 'like', "%{$search}%");
                    });
            });
        }

        $withdrawals = $query->latest('id')->paginate(15)->withQueryString();

        $metrics = [
            'pending_count'    => AffiliateWithdrawal::where('status', 'pending')->count(),
            'pending_amount'   => (float) AffiliateWithdrawal::where('status', 'pending')->sum('amount'),
            'paid_amount'      => (float) AffiliateWithdrawal::where('status', 'paid')->sum('amount'),
            'rejected_count'   => AffiliateWithdrawal::where('status', 'rejected')->count(),
        ];

        return Inertia::render('Admin/Affiliates/Withdrawals', [
            'withdrawals' => $withdrawals,
            'filters'     => $request->only(['status', 'search']),
            'metrics'     => $metrics,
        ]);
    }

    public function markPaid(Request $request, AffiliateWithdrawal $withdrawal)
    {
        $this->authorizePermission();

        if ($withdrawal->status !== 'pending') {
            return back()->with('error', 'Only pending withdrawal requests can be marked as paid.');
        }

        $validated = $request->validate([
            'payment_reference' => 'required|string|max:100',
            'admin_note'        => 'nullable|string|max:500',
        ]);

        $withdrawal->update([
            'status'            => 'paid',
            'payment_reference' => $validated['payment_reference'],
            'admin_note'        => $validated['admin_note'] ?? null,
            'processed_by'      => $request->user()?->id,
            'processed_at'      => now(),
        ]);

        $this->walletService->finalizeWithdrawal($withdrawal->affiliate, $withdrawal);

        ActivityLogger::affiliate("Marked affiliate withdrawal #{$withdrawal->id} as paid (₦" . number_format($withdrawal->amount, 2) . ")", [
            'withdrawal_id'     => $withdrawal->id,
            'affiliate_id'      => $withdrawal->affiliate_id,
            'amount'            => $withdrawal->amount,
            'payment_reference' => $validated['payment_reference'],
        ]);

        return back()->with('success', "Withdrawal #{$withdrawal->id} marked as paid.");
    }

    public function reject(Request $request, AffiliateWithdrawal $withdrawal)
    {
        $this->authorizePermission();

        if ($withdrawal->status !== 'pending') {
            return back()->with('error', 'Only pending withdrawal requests can be rejected.');
        }

        $validated = $request->validate([
            'admin_note' => 'required|string|max:500',
        ]);

        $withdrawal->update([
            'status'       => 'rejected',
            'admin_note'   => $validated['admin_note'],
            'processed_by' => $request->user()?->id,
            'processed_at' => now(),
        ]);

        // Refund the held funds back into affiliate wallet
        $this->walletService->refundWithdrawal($withdrawal->affiliate, $withdrawal, $validated['admin_note']);

        ActivityLogger::affiliate("Rejected affiliate withdrawal #{$withdrawal->id} (₦" . number_format($withdrawal->amount, 2) . ")", [
            'withdrawal_id' => $withdrawal->id,
            'affiliate_id'  => $withdrawal->affiliate_id,
            'amount'        => $withdrawal->amount,
            'reason'        => $validated['admin_note'],
        ]);

        return back()->with('success', "Withdrawal #{$withdrawal->id} rejected and funds refunded to affiliate wallet.");
    }
}

