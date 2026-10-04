<?php

namespace App\Http\Controllers\Frontend;

use App\Http\Controllers\Controller;
use App\Models\StoreSetting;
use App\Models\Wishlist;
use Illuminate\Http\Request;
use Inertia\Inertia;

class WishlistController extends Controller
{
    /**
     * Show the authenticated customer's wishlist.
     */
    public function index()
    {
        $wishlist = Wishlist::with('product.category')
            ->where('customer_id', auth('customer')->id())
            ->latest()
            ->get();

        return Inertia::render('Frontend/Wishlist/Index', [
            'items' => $wishlist,
        ]);
    }

    /**
     * Toggle a product in the authenticated customer's wishlist.
     */
    public function toggle(Request $request)
    {
        // Check if wishlists are enabled
        if (StoreSetting::get('wishlist_enabled', '0') !== '1') {
            return back()->with('error', 'Wishlist is currently disabled.');
        }

        $request->validate(['product_id' => 'required|exists:products,id']);

        $customerId = auth('customer')->id();
        $productId  = $request->product_id;

        $existing = Wishlist::where('customer_id', $customerId)
                            ->where('product_id', $productId)
                            ->first();

        if ($existing) {
            $existing->delete();
        } else {
            Wishlist::create([
                'customer_id' => $customerId,
                'product_id'  => $productId,
            ]);
        }

        return back();
    }
}
