<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Coupon;
use Illuminate\Http\Request;
use Inertia\Inertia;

class CouponController extends Controller
{
    public function index(Request $request)
    {
        $query = Coupon::query();

        if ($request->filled('search')) {
            $search = trim($request->search);
            $query->where('code', 'like', "%{$search}%");
        }

        if ($request->filled('status')) {
            if ($request->status === 'active') {
                $query->where('is_active', true);
            } elseif ($request->status === 'inactive') {
                $query->where('is_active', false);
            }
        }

        $coupons = $query->latest()->paginate(25)->withQueryString();

        return Inertia::render('Admin/Coupons/Index', [
            'coupons' => $coupons,
            'filters' => [
                'search' => $request->search ?? '',
                'status' => $request->status ?? '',
            ],
            'stats'   => [
                'total'        => Coupon::count(),
                'active'       => Coupon::where('is_active', true)->count(),
                'total_used'   => (int) Coupon::sum('used_count'),
            ],
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'code'             => ['required', 'string', 'max:50', 'unique:coupons,code'],
            'type'             => ['required', 'in:percent,fixed'],
            'value'            => ['required', 'numeric', 'min:0.01'],
            'min_order_amount' => ['nullable', 'numeric', 'min:0'],
            'max_uses'         => ['nullable', 'integer', 'min:1'],
            'starts_at'          => ['nullable', 'date'],
            'expires_at'         => ['nullable', 'date', 'after_or_equal:starts_at'],
            'is_active'          => ['boolean'],
            'exclude_sale_items' => ['boolean'],
        ]);

        $validated['code'] = strtoupper(trim($validated['code']));
        $validated['min_order_amount'] = $validated['min_order_amount'] ?? 0;
        $validated['is_active'] = $request->boolean('is_active', true);
        $validated['exclude_sale_items'] = $request->boolean('exclude_sale_items', false);

        Coupon::create($validated);

        return back()->with('success', "Coupon '{$validated['code']}' created successfully.");
    }

    public function update(Request $request, Coupon $coupon)
    {
        $validated = $request->validate([
            'code'             => ['required', 'string', 'max:50', 'unique:coupons,code,' . $coupon->id],
            'type'             => ['required', 'in:percent,fixed'],
            'value'            => ['required', 'numeric', 'min:0.01'],
            'min_order_amount' => ['nullable', 'numeric', 'min:0'],
            'max_uses'         => ['nullable', 'integer', 'min:1'],
            'starts_at'          => ['nullable', 'date'],
            'expires_at'         => ['nullable', 'date'],
            'is_active'          => ['boolean'],
            'exclude_sale_items' => ['boolean'],
        ]);

        $validated['code'] = strtoupper(trim($validated['code']));
        $validated['min_order_amount'] = $validated['min_order_amount'] ?? 0;
        $validated['is_active'] = $request->boolean('is_active', true);
        $validated['exclude_sale_items'] = $request->boolean('exclude_sale_items', false);

        $coupon->update($validated);

        return back()->with('success', "Coupon '{$coupon->code}' updated successfully.");
    }

    public function destroy(Coupon $coupon)
    {
        $code = $coupon->code;
        $coupon->delete();

        return back()->with('success', "Coupon '{$code}' deleted successfully.");
    }

    public function toggleStatus(Coupon $coupon)
    {
        $coupon->update(['is_active' => !$coupon->is_active]);

        $status = $coupon->is_active ? 'activated' : 'deactivated';
        return back()->with('success', "Coupon '{$coupon->code}' {$status}.");
    }
}

