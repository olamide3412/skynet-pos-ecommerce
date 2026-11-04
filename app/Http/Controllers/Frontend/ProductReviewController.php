<?php

namespace App\Http\Controllers\Frontend;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\ProductReview;
use App\Models\StoreSetting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ProductReviewController extends Controller
{
    /**
     * Submit a customer product review.
     */
    public function store(Request $request, Product $product)
    {
        if (!StoreSetting::getBool('reviews_enabled', true)) {
            return back()->with('error', 'Customer reviews are currently disabled.');
        }

        if (!Auth::guard('customer')->check()) {
            return redirect()->route('login')->with('warning', 'Please sign in to submit a review.');
        }

        $validated = $request->validate([
            'rating'  => ['required', 'integer', 'min:1', 'max:5'],
            'comment' => ['nullable', 'string', 'max:1000'],
        ]);

        $customerId = Auth::guard('customer')->id();

        // Optional: link to existing completed order if any
        $order = Auth::guard('customer')->user()->orders()
            ->whereHas('items', fn ($q) => $q->where('product_id', $product->id))
            ->whereIn('status', ['processing', 'shipped', 'completed'])
            ->latest()
            ->first();

        // Check if customer already submitted a review for this product
        $existing = ProductReview::where('product_id', $product->id)
            ->where('customer_id', $customerId)
            ->first();

        if ($existing) {
            $existing->update([
                'rating'      => $validated['rating'],
                'comment'     => $validated['comment'] ?? null,
                'order_id'    => $order?->id ?? $existing->order_id,
                'is_approved' => true,
            ]);
            return back()->with('success', 'Your review has been updated!');
        }

        ProductReview::create([
            'product_id'  => $product->id,
            'customer_id' => $customerId,
            'order_id'    => $order?->id,
            'rating'      => $validated['rating'],
            'comment'     => $validated['comment'] ?? null,
            'is_approved' => true,
        ]);

        return back()->with('success', 'Thank you! Your product review has been submitted.');
    }
}

