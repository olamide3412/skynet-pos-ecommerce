<?php

namespace App\Http\Controllers\Frontend;

use App\Http\Controllers\Controller;
use App\Models\Cart;
use App\Models\Coupon;
use Illuminate\Http\Request;

class CouponController extends Controller
{
    /**
     * Apply a discount coupon to the current cart.
     */
    public function apply(Request $request)
    {
        $request->validate([
            'code' => ['required', 'string', 'max:50'],
        ]);

        $code = strtoupper(trim($request->code));
        $coupon = Coupon::where('code', $code)->first();

        if (!$coupon || !$coupon->is_active) {
            return back()->with('error', 'Invalid or expired coupon code.');
        }

        // Get cart subtotal
        $cart = $this->getCart($request);
        if (!$cart || $cart->items->isEmpty()) {
            return back()->with('error', 'Your cart is empty.');
        }

        $subtotal = (float) $cart->items->sum(fn ($i) => $i->price * $i->quantity);

        if (!$coupon->isValidForAmount($subtotal)) {
            if ($coupon->min_order_amount > 0 && $subtotal < (float) $coupon->min_order_amount) {
                return back()->with('error', "This coupon requires a minimum cart subtotal of ₦" . number_format($coupon->min_order_amount, 2));
            }
            return back()->with('error', 'This coupon is no longer valid or has reached its usage limit.');
        }

        $discount = $coupon->calculateDiscount($subtotal, $cart);

        if ($coupon->exclude_sale_items && $discount <= 0) {
            return back()->with('error', "This coupon cannot be applied because items in your cart are already on sale or flash deals.");
        }

        $request->session()->put('applied_coupon', [
            'id'       => $coupon->id,
            'code'     => $coupon->code,
            'discount' => $discount,
            'type'     => $coupon->type,
            'value'    => $coupon->value,
        ]);

        return back()->with('success', "Coupon '{$coupon->code}' applied successfully! Saved ₦" . number_format($discount, 2));
    }

    /**
     * Remove the currently applied coupon.
     */
    public function remove(Request $request)
    {
        $request->session()->forget('applied_coupon');
        return back()->with('info', 'Coupon removed.');
    }

    protected function getCart(Request $request): ?Cart
    {
        $query = Cart::with(['items.product.posUnits', 'items.posUnit']);
        if (auth('customer')->check()) {
            return $query->where('customer_id', auth('customer')->id())->first();
        }
        return $query->where('session_id', $request->session()->getId())->first();
    }
}

