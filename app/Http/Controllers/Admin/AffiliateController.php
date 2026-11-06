<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Affiliate;
use App\Models\Customer;
use App\Models\StoreSetting;
use App\Services\ActivityLogger;
use Illuminate\Http\Request;
use Inertia\Inertia;

class AffiliateController extends Controller
{
    protected function authorizePermission(string $permission): void
    {
        $user = auth('web')->user();
        if (!$user) {
            abort(403);
        }
        if ($user->role === 'super administrator' || $user->role === 'administrator') {
            return;
        }
        if (!$user->can($permission)) {
            abort(403, "Unauthorized. You do not have permission [{$permission}].");
        }
    }

    public function index(Request $request)
    {
        $this->authorizePermission('affiliates.view');

        $status = $request->input('status');
        $search = $request->input('search');

        $query = Affiliate::with(['customer', 'approver'])
            ->withCount(['commissions', 'orders', 'visits']);

        if ($status && in_array($status, ['pending', 'approved', 'rejected', 'suspended'], true)) {
            $query->where('status', $status);
        }

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('referral_code', 'like', "%{$search}%")
                    ->orWhereHas('customer', function ($cq) use ($search) {
                        $cq->where('name', 'like', "%{$search}%")
                            ->orWhere('email', 'like', "%{$search}%")
                            ->orWhere('phone', 'like', "%{$search}%");
                    });
            });
        }

        $affiliates = $query->latest('id')->paginate(15)->withQueryString();

        $metrics = [
            'total_affiliates'    => Affiliate::count(),
            'approved_affiliates' => Affiliate::where('status', 'approved')->count(),
            'pending_requests'    => Affiliate::where('status', 'pending')->count(),
            'total_wallet_owed'   => (float) Affiliate::sum('wallet_balance'),
            'total_paid_out'      => (float) Affiliate::sum('total_withdrawn'),
        ];

        // Eligible customers to enroll directly
        $existingCustomerIds = Affiliate::pluck('customer_id');
        $availableCustomers = Customer::whereNotIn('id', $existingCustomerIds)
            ->select(['id', 'name', 'email', 'phone'])
            ->latest('id')
            ->limit(100)
            ->get();

        return Inertia::render('Admin/Affiliates/Index', [
            'affiliates'          => $affiliates,
            'filters'             => $request->only(['status', 'search']),
            'metrics'             => $metrics,
            'available_customers' => $availableCustomers,
            'store_defaults'      => [
                'commission_type'  => StoreSetting::get('referral_commission_type', 'percentage'),
                'commission_rate'  => StoreSetting::get('referral_commission_rate', '5'),
                'min_order_amount' => StoreSetting::get('referral_min_order_amount', '0'),
            ],
        ]);
    }

    public function show(Affiliate $affiliate)
    {
        $this->authorizePermission('affiliates.view');

        $affiliate->load(['customer', 'approver']);

        $commissions = $affiliate->commissions()
            ->with('order')
            ->latest('id')
            ->paginate(10, ['*'], 'commissions_page');

        $transactions = $affiliate->walletTransactions()
            ->paginate(15, ['*'], 'wallet_page');

        $withdrawals = $affiliate->withdrawals()
            ->with('processor')
            ->paginate(10, ['*'], 'withdrawals_page');

        return Inertia::render('Admin/Affiliates/Show', [
            'affiliate'    => $affiliate,
            'commissions'  => $commissions,
            'transactions' => $transactions,
            'withdrawals'  => $withdrawals,
            'stats'        => [
                'visits_count'      => $affiliate->visits()->count(),
                'orders_count'      => $affiliate->orders()->count(),
                'commissions_count' => $affiliate->commissions()->count(),
                'pending_earnings'  => (float) $affiliate->commissions()->where('status', 'pending')->sum('commission_amount'),
            ],
            'store_defaults' => [
                'commission_type'  => StoreSetting::get('referral_commission_type', 'percentage'),
                'commission_rate'  => StoreSetting::get('referral_commission_rate', '5'),
                'min_order_amount' => StoreSetting::get('referral_min_order_amount', '0'),
            ],
        ]);
    }

    public function update(Request $request, Affiliate $affiliate)
    {
        $this->authorizePermission('affiliates.manage');

        $validated = $request->validate([
            'status'           => 'required|string|in:pending,approved,rejected,suspended',
            'commission_type'  => 'nullable|string|in:percentage,flat',
            'commission_rate'  => 'nullable|numeric|min:0',
            'min_order_amount' => 'nullable|numeric|min:0',
            'bank_name'        => 'nullable|string|max:100',
            'account_number'   => 'nullable|string|max:30',
            'account_name'     => 'nullable|string|max:150',
            'admin_notes'      => 'nullable|string|max:1000',
        ]);

        $statusChangedToApproved = ($affiliate->status !== 'approved' && $validated['status'] === 'approved');

        $updateData = [
            'status'           => $validated['status'],
            'commission_type'  => array_key_exists('commission_type', $validated) ? ($validated['commission_type'] ?: null) : $affiliate->commission_type,
            'commission_rate'  => array_key_exists('commission_rate', $validated) ? ($validated['commission_rate'] !== null && $validated['commission_rate'] !== '' ? $validated['commission_rate'] : null) : $affiliate->commission_rate,
            'min_order_amount' => array_key_exists('min_order_amount', $validated) ? ($validated['min_order_amount'] !== null && $validated['min_order_amount'] !== '' ? $validated['min_order_amount'] : null) : $affiliate->min_order_amount,
            'bank_name'        => $validated['bank_name'] ?? $affiliate->bank_name,
            'account_number'   => $validated['account_number'] ?? $affiliate->account_number,
            'account_name'     => $validated['account_name'] ?? $affiliate->account_name,
            'admin_notes'      => $validated['admin_notes'] ?? $affiliate->admin_notes,
        ];

        if ($statusChangedToApproved) {
            $updateData['approved_at'] = now();
            $updateData['approved_by'] = $request->user()?->id;
        }

        $affiliate->update($updateData);

        ActivityLogger::affiliate("Updated affiliate #{$affiliate->id} ({$affiliate->customer?->name}) status to '{$validated['status']}'", [
            'affiliate_id'  => $affiliate->id,
            'referral_code' => $affiliate->referral_code,
            'status'        => $validated['status'],
        ]);

        return back()->with('success', "Affiliate status updated successfully.");
    }

    public function enrollCustomer(Request $request)
    {
        $this->authorizePermission('affiliates.manage');

        $validated = $request->validate([
            'customer_id'      => 'required|exists:customers,id|unique:affiliates,customer_id',
            'commission_type'  => 'nullable|string|in:percentage,flat',
            'commission_rate'  => 'nullable|numeric|min:0',
            'min_order_amount' => 'nullable|numeric|min:0',
            'bank_name'        => 'nullable|string|max:100',
            'account_number'   => 'nullable|string|max:30',
            'account_name'     => 'nullable|string|max:150',
            'admin_notes'      => 'nullable|string|max:1000',
        ]);

        $customer = Customer::findOrFail($validated['customer_id']);
        $referralCode = Affiliate::generateUniqueCode($customer->name);

        Affiliate::create([
            'customer_id'      => $customer->id,
            'referral_code'    => $referralCode,
            'status'           => 'approved',
            'commission_type'  => $validated['commission_type'] ?: null,
            'commission_rate'  => $validated['commission_rate'] !== null && $validated['commission_rate'] !== '' ? $validated['commission_rate'] : null,
            'min_order_amount' => isset($validated['min_order_amount']) && $validated['min_order_amount'] !== null && $validated['min_order_amount'] !== '' ? $validated['min_order_amount'] : null,
            'bank_name'        => $validated['bank_name'] ?? null,
            'account_number'   => $validated['account_number'] ?? null,
            'account_name'     => $validated['account_name'] ?? null,
            'admin_notes'      => $validated['admin_notes'] ?? 'Enrolled directly by administrator.',
            'approved_at'      => now(),
            'approved_by'      => $request->user()?->id,
        ]);

        ActivityLogger::affiliate("Manually enrolled customer {$customer->name} as affiliate ({$referralCode})", [
            'customer_id'   => $customer->id,
            'referral_code' => $referralCode,
        ]);

        return back()->with('success', "Customer {$customer->name} successfully enrolled as an approved affiliate (Code: {$referralCode}).");
    }
}

