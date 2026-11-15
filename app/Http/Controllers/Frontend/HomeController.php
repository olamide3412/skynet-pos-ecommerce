<?php

namespace App\Http\Controllers\Frontend;

use App\Http\Controllers\Controller;
use App\Models\Brand;
use App\Models\Product;
use App\Models\Category;
use App\Models\OrderItem;
use App\Models\Wishlist;
use App\Services\Storefront\StorefrontListingExpander;
use Inertia\Inertia;

class HomeController extends Controller
{
    public function __construct(
        protected StorefrontListingExpander $expander
    ) {}

    public function index()
    {
        $eagerLoad = array_merge(['category', 'brand:id,name,slug,logo'], $this->expander->eagerLoad());

        // 0. Active flash sale products (only when currently running)
        $now = now();
        $flashSales = Product::with(['category', 'brand:id,name,slug,logo'])
            ->where('status', true)
            ->whereNotNull('special_price')
            ->where('special_price', '>', 0)
            ->where(function ($q) use ($now) {
                $q->whereNull('special_price_from')
                  ->orWhere('special_price_from', '<=', $now);
            })
            ->where(function ($q) use ($now) {
                $q->whereNull('special_price_to')
                  ->orWhere('special_price_to', '>=', $now);
            })
            ->orderByRaw('CASE WHEN special_price_to IS NOT NULL THEN special_price_to ELSE "9999-12-31" END ASC')
            ->take(12)
            ->get();

        $flashSaleEndTime = $flashSales->whereNotNull('special_price_to')->min('special_price_to');

        // 1. Featured products (10 products)
        $featured = Product::with($eagerLoad)
            ->where('status', true)
            ->when(Product::where('status', true)->where('is_featured', true)->exists(), function ($q) {
                $q->orderByDesc('is_featured');
            })
            ->inRandomOrder()
            ->take(10)
            ->get();
        $featured = $this->expander->expandAndLimit($featured, 10);

        // 2. Best sellers by order volume with fallback to active products (10 products)
        $bestSellerIds = OrderItem::select('product_id')
            ->selectRaw('SUM(quantity) as total_sold')
            ->whereNotNull('product_id')
            ->groupBy('product_id')
            ->orderByDesc('total_sold')
            ->take(10)
            ->pluck('product_id')
            ->toArray();

        $bestSellers = Product::with($eagerLoad)
            ->where('status', true)
            ->whereIn('id', $bestSellerIds)
            ->get();

        if ($bestSellers->isNotEmpty() && !empty($bestSellerIds)) {
            $bestSellers = $bestSellers->sortBy(function ($p) use ($bestSellerIds) {
                return array_search($p->id, $bestSellerIds);
            })->values();
        }

        if ($bestSellers->count() < 10) {
            $existingIds = $bestSellers->pluck('id')->toArray();
            $fillers = Product::with($eagerLoad)
                ->where('status', true)
                ->whereNotIn('id', $existingIds)
                ->inRandomOrder()
                ->take(10 - $bestSellers->count())
                ->get();
            $bestSellers = $bestSellers->concat($fillers);
        }
        $bestSellers = $this->expander->expandAndLimit($bestSellers, 10);

        // 3. New arrivals (10 products)
        $newArrivals = Product::with($eagerLoad)
            ->where('status', true)
            ->latest()
            ->take(10)
            ->get();
        $newArrivals = $this->expander->expandAndLimit($newArrivals, 10);

        // 4. Most searched products (10 products)
        // Find the top search terms, match products against those terms, or fallback to popular items
        $topTerms = \App\Models\SearchLog::select('normalized_query')
            ->selectRaw('COUNT(*) as search_count')
            ->where('results_count', '>', 0)
            ->where('created_at', '>=', now()->subDays(30))
            ->groupBy('normalized_query')
            ->orderByDesc('search_count')
            ->limit(10)
            ->pluck('normalized_query')
            ->toArray();

        $mostSearched = collect();
        if (!empty($topTerms)) {
            $searchedQuery = Product::with($eagerLoad)->where('status', true);
            $searchedQuery->where(function ($sub) use ($topTerms) {
                foreach ($topTerms as $term) {
                    $sub->orWhere('name', 'like', '%' . $term . '%');
                }
            });
            $mostSearched = $searchedQuery->take(10)->get();
        }

        // Fill up to 10 if fewer match or before searches accumulate
        if ($mostSearched->count() < 10) {
            $excludeIds = $mostSearched->pluck('id')->toArray();
            $fallback = Product::with($eagerLoad)
                ->where('status', true)
                ->whereNotIn('id', $excludeIds)
                ->inRandomOrder()
                ->take(10 - $mostSearched->count())
                ->get();
            $mostSearched = $mostSearched->concat($fallback);
        }
        $mostSearched = $this->expander->expandAndLimit($mostSearched, 10);

        $featuredBrands = Brand::active()
            ->withCount(['products' => fn ($q) => $q->where('status', true)])
            ->orderByDesc('is_featured')
            ->ordered()
            ->take(12)
            ->get(['id', 'name', 'slug', 'logo', 'is_featured']);

        $wishlistIds = [];
        if (auth('customer')->check()) {
            $wishlistIds = Wishlist::where('customer_id', auth('customer')->id())
                ->pluck('product_id')
                ->toArray();
        }

        return Inertia::render('Home', [
            'flash_sales' => $flashSales,
            'flash_sale_end' => $flashSaleEndTime ? $flashSaleEndTime->toIso8601String() : null,
            'sliders' => \App\Models\StoreSetting::get('home_slider_enabled') !== '0' 
                ? \App\Models\Slider::where('is_active', true)->orderBy('order')->get() 
                : [],
            'featured_products' => $featured,
            'new_arrivals' => $newArrivals,
            'best_sellers' => $bestSellers->take(10)->values(),
            'most_searched' => $mostSearched->take(10)->values(),
            'top_search_keywords' => array_slice($topTerms, 0, 6),
            'home_categories' => $this->getSmartHomeCategories(8),
            'featured_brands' => $featuredBrands,
            'wishlist_ids' => $wishlistIds,
        ]);
    }

    /**
     * Smartly load and rank parent categories for the "Shop by Category" homepage section:
     * 1. Only visible parent categories with active products (including subcategories).
     * 2. Explicit manual menu_position (> 0) first.
     * 3. Categories with custom uploaded images next.
     * 4. Ranked by in-stock product depth (DESC), then total active products (DESC), then ID (ASC).
     * 5. Populates a fallback product thumbnail when a category does not have its own image uploaded yet.
     */
    private function getSmartHomeCategories(int $limit = 8): array
    {
        $parents = Category::whereNull('parent_id')
            ->where('visible_in_menu', true)
            ->with('children:id,parent_id')
            ->get(['id', 'name', 'slug', 'image', 'menu_position']);

        if ($parents->isEmpty()) {
            return [];
        }

        // Map every parent & child category ID to its root parent ID
        $catToParent = [];
        foreach ($parents as $parent) {
            $catToParent[$parent->id] = $parent->id;
            foreach ($parent->children as $child) {
                $catToParent[$child->id] = $parent->id;
            }
        }

        // Aggregate active and in-stock product counts in a single query
        $countsByCat = Product::query()
            ->whereIn('category_id', array_keys($catToParent))
            ->where('status', true)
            ->selectRaw('category_id, COUNT(*) as active_count, SUM(CASE WHEN stock > 0 THEN 1 ELSE 0 END) as in_stock_count')
            ->groupBy('category_id')
            ->get();

        $parentStats = [];
        foreach ($countsByCat as $row) {
            $rootId = $catToParent[$row->category_id] ?? null;
            if (!$rootId) {
                continue;
            }
            if (!isset($parentStats[$rootId])) {
                $parentStats[$rootId] = ['active' => 0, 'in_stock' => 0];
            }
            $parentStats[$rootId]['active'] += (int) $row->active_count;
            $parentStats[$rootId]['in_stock'] += (int) $row->in_stock_count;
        }

        // Filter to non-empty categories (fallback to all visible if store has no products yet)
        $eligible = $parents->filter(fn ($c) => ($parentStats[$c->id]['active'] ?? 0) > 0);
        if ($eligible->isEmpty()) {
            $eligible = $parents;
        }

        // Sort by smart merchandising tiers
        $sorted = $eligible->sort(function ($a, $b) use ($parentStats) {
            // Tier 1: Explicit manual menu_position (> 0)
            $aPinned = ($a->menu_position ?? 0) > 0 ? 0 : 1;
            $bPinned = ($b->menu_position ?? 0) > 0 ? 0 : 1;
            if ($aPinned !== $bPinned) {
                return $aPinned <=> $bPinned;
            }
            if ($aPinned === 0 && $a->menu_position !== $b->menu_position) {
                return $a->menu_position <=> $b->menu_position;
            }

            // Tier 2: Has custom category image
            $aHasImg = !empty($a->image) ? 1 : 0;
            $bHasImg = !empty($b->image) ? 1 : 0;
            if ($aHasImg !== $bHasImg) {
                return $bHasImg <=> $aHasImg;
            }

            // Tier 3: In-stock product depth (DESC)
            $aInStock = $parentStats[$a->id]['in_stock'] ?? 0;
            $bInStock = $parentStats[$b->id]['in_stock'] ?? 0;
            if ($aInStock !== $bInStock) {
                return $bInStock <=> $aInStock;
            }

            // Tier 4: Total active product count (DESC)
            $aActive = $parentStats[$a->id]['active'] ?? 0;
            $bActive = $parentStats[$b->id]['active'] ?? 0;
            if ($aActive !== $bActive) {
                return $bActive <=> $aActive;
            }

            // Tier 5: Deterministic ID tie-breaker
            return $a->id <=> $b->id;
        })->take($limit)->values();

        // Populate a representative product image for categories missing a dedicated category image
        return $sorted->map(function ($cat) use ($parentStats) {
            $fallbackImage = null;
            if (empty($cat->image)) {
                $familyIds = array_merge([$cat->id], $cat->children->pluck('id')->all());
                $fallbackImage = Product::whereIn('category_id', $familyIds)
                    ->where('status', true)
                    ->whereNotNull('image')
                    ->where('image', '!=', '')
                    ->orderByRaw('CASE WHEN stock > 0 THEN 1 ELSE 0 END DESC')
                    ->orderByDesc('is_featured')
                    ->orderByDesc('id')
                    ->value('image');
            }

            return [
                'id'             => $cat->id,
                'name'           => $cat->name,
                'slug'           => $cat->slug,
                'image'          => $cat->image,
                'fallback_image' => $fallbackImage,
                'products_count' => $parentStats[$cat->id]['active'] ?? 0,
            ];
        })->all();
    }
}
