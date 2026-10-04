<?php

namespace App\Http\Controllers\Frontend;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\Category;
use App\Models\Wishlist;
use Inertia\Inertia;
use Illuminate\Http\Request;

class ProductController extends Controller
{
    public function index(Request $request)
    {
        $query = Product::where('status', true)->with('category');
        
        if ($request->has('category')) {
            $slug = $request->category;
            // Find the category by slug
            $cat = Category::where('slug', $slug)->first();
            if ($cat) {
                if (is_null($cat->parent_id)) {
                    // It's a parent: include products in this category OR any of its subcategories
                    $childIds = $cat->children()->pluck('id')->toArray();
                    $ids = array_merge([$cat->id], $childIds);
                    $query->whereIn('category_id', $ids);
                } else {
                    // It's a subcategory: match directly
                    $query->where('category_id', $cat->id);
                }
            }
        }
        
        if ($request->has('search')) {
            $query->where('name', 'like', '%' . $request->search . '%');
        }

        $attrs = $request->input('attrs', []);
        if (is_array($attrs) && !empty($attrs)) {
            foreach ($attrs as $attrId => $value) {
                if (!empty($value)) {
                    $query->whereJsonContains("attributes->{$attrId}", $value);
                }
            }
        }

        // Pass the authenticated customer's wishlist product IDs
        $wishlistIds = [];
        if (auth('customer')->check()) {
            $wishlistIds = Wishlist::where('customer_id', auth('customer')->id())
                ->pluck('product_id')
                ->toArray();
        }

        $filters = $request->only(['search', 'category']);
        $filters['attrs'] = $attrs;

        // Build categories with parent/child grouping for the sidebar filter
        $categories = Category::whereNull('parent_id')
            ->with(['children' => fn($q) => $q->orderBy('name')])
            ->orderBy('name')
            ->get(['id','name','slug','parent_id']);

        return Inertia::render('Frontend/Products/Index', [
            'products'     => $query->paginate(12)->withQueryString(),
            'categories'   => $categories,
            'filters'      => $filters,
            'wishlist_ids' => $wishlistIds,
        ]);
    }

    public function show(string $url_key)
    {
        // Resolve by url_key first, fall back to slug for old links
        $product = Product::where('url_key', $url_key)
            ->orWhere('slug', $url_key)
            ->where('status', true)
            ->firstOrFail();

        // Check if this product is in the authenticated customer's wishlist
        $isWishlisted = false;
        if (auth('customer')->check()) {
            $isWishlisted = Wishlist::where('customer_id', auth('customer')->id())
                ->where('product_id', $product->id)
                ->exists();
        }
        
        return Inertia::render('Frontend/Products/Show', [
            'product'       => $product->load([
                'category',
                'posUnits' => fn($q) => $q->where('is_enabled_online', true)->orderBy('sort_order'),
                'variantMappings' => fn($q) => $q->where('is_active', true)->orderBy('sort_order'),
            ]),
            'is_wishlisted' => $isWishlisted,
            'related'       => $this->getRelated($product),
        ]);
    }

    private function getRelated(Product $product): \Illuminate\Support\Collection
    {
        // Manually selected takes priority
        if (!empty($product->related_products)) {
            $related = Product::whereIn('id', $product->related_products)
                ->where('status', true)
                ->where('id', '!=', $product->id)
                ->take(8)
                ->get();
            if ($related->isNotEmpty()) return $related;
        }
        // Fallback: same category
        return Product::where('category_id', $product->category_id)
            ->where('id', '!=', $product->id)
            ->where('status', true)
            ->take(4)
            ->get();
    }
}
