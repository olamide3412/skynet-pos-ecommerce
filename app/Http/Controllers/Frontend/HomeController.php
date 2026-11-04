<?php

namespace App\Http\Controllers\Frontend;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\Category;
use App\Models\OrderItem;
use Inertia\Inertia;

class HomeController extends Controller
{
    public function index()
    {
        // 0. Active flash sale products (only when currently running)
        $now = now();
        $flashSales = Product::with('category')
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
        $featured = Product::with('category')
            ->where('status', true)
            ->when(Product::where('status', true)->where('is_featured', true)->exists(), function ($q) {
                $q->orderByDesc('is_featured');
            })
            ->inRandomOrder()
            ->take(10)
            ->get();

        // 2. Best sellers by order volume with fallback to active products (10 products)
        $bestSellerIds = OrderItem::select('product_id')
            ->selectRaw('SUM(quantity) as total_sold')
            ->whereNotNull('product_id')
            ->groupBy('product_id')
            ->orderByDesc('total_sold')
            ->take(10)
            ->pluck('product_id')
            ->toArray();

        $bestSellers = Product::with('category')
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
            $fillers = Product::with('category')
                ->where('status', true)
                ->whereNotIn('id', $existingIds)
                ->inRandomOrder()
                ->take(10 - $bestSellers->count())
                ->get();
            $bestSellers = $bestSellers->concat($fillers);
        }

        // 3. New arrivals (10 products)
        $newArrivals = Product::with('category')
            ->where('status', true)
            ->latest()
            ->take(10)
            ->get();

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
            $searchedQuery = Product::with('category')->where('status', true);
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
            $fallback = Product::with('category')
                ->where('status', true)
                ->whereNotIn('id', $excludeIds)
                ->inRandomOrder()
                ->take(10 - $mostSearched->count())
                ->get();
            $mostSearched = $mostSearched->concat($fallback);
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
            'home_categories' => Category::whereNull('parent_id')
                ->orderBy('menu_position')
                ->take(8)
                ->get(['id', 'name', 'slug', 'image']),
        ]);
    }
}
