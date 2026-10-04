<?php

namespace App\Http\Controllers\Frontend;

use App\Http\Controllers\Controller;
use App\Models\Compare;
use App\Models\CompareItem;
use App\Models\Product;
use Illuminate\Http\Request;
use Inertia\Inertia;

class CompareController extends Controller
{
    /**
     * Display the comparison page.
     */
    public function index(Request $request)
    {
        $compare = $this->getCompare($request);
        
        // Eager load product category for comparison table
        if ($compare) {
            $compare->load('items.product.category');
        }

        return Inertia::render('Frontend/Compare/Index', [
            'compare' => $compare
        ]);
    }

    /**
     * Add a product to the comparison list.
     */
    public function store(Request $request)
    {
        $request->validate([
            'product_id' => 'required|exists:products,id'
        ]);

        $compare = $this->getOrCreateCompare($request);

        // Limit the comparison list to 4 items for UI sanity
        if ($compare->items()->count() >= 4) {
            return back()->with('error', 'You can only compare up to 4 products at a time.');
        }

        // Prevent duplicate items in the comparison list
        $exists = $compare->items()->where('product_id', $request->product_id)->exists();
        if ($exists) {
            return back()->with('info', 'This product is already in your comparison list.');
        }

        $compare->items()->create([
            'product_id' => $request->product_id
        ]);

        return back()->with('success', 'Product added to comparison list.');
    }

    /**
     * Remove a product from the comparison list.
     */
    public function destroy(CompareItem $compareItem)
    {
        $compareItem->delete();
        return back()->with('success', 'Product removed from comparison list.');
    }

    /**
     * Helper to get active compare list for current user or session.
     */
    protected function getCompare(Request $request)
    {
        if (auth('customer')->check()) {
            return Compare::where('customer_id', auth('customer')->id())->first();
        }
        
        return Compare::where('session_id', $request->session()->getId())->first();
    }

    /**
     * Helper to get or create compare list.
     */
    protected function getOrCreateCompare(Request $request)
    {
        $compare = $this->getCompare($request);
        
        if (!$compare) {
            $compare = Compare::create([
                'customer_id' => auth('customer')->id() ?? null,
                'session_id' => auth('customer')->check() ? null : $request->session()->getId()
            ]);
        }
        
        return $compare;
    }
}
