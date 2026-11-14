<?php

namespace App\Http\Controllers\Frontend;

use App\Http\Controllers\Controller;
use App\Models\Brand;
use App\Models\Product;
use App\Models\Category;
use App\Models\Wishlist;
use App\Services\Storefront\StorefrontListingExpander;
use Inertia\Inertia;
use Illuminate\Http\Request;

class ProductController extends Controller
{
    public function __construct(
        protected StorefrontListingExpander $expander
    ) {}

    public function index(Request $request, ?Brand $currentBrand = null)
    {
        $query = Product::availableOnStorefront()->with(array_merge(
            ['category', 'brand:id,name,slug,logo'],
            $this->expander->eagerLoad()
        ));
        
        $collection = $request->input('collection', $request->input('filter'));

        if ($collection === 'featured') {
            if (Product::where('status', true)->where('is_featured', true)->exists()) {
                $query->where('is_featured', true);
            } else {
                $query->orderByDesc('is_featured')->latest();
            }
        } elseif ($collection === 'best-sellers' || $collection === 'best_sellers') {
            $query->withSum('orderItems', 'quantity')
                ->orderByDesc('order_items_sum_quantity')
                ->orderByDesc('id');
        } elseif ($collection === 'most-searched' || $collection === 'most_searched') {
            $topTerms = \App\Models\SearchLog::select('normalized_query')
                ->selectRaw('COUNT(*) as search_count')
                ->where('results_count', '>', 0)
                ->where('created_at', '>=', now()->subDays(30))
                ->groupBy('normalized_query')
                ->orderByDesc('search_count')
                ->limit(10)
                ->pluck('normalized_query')
                ->toArray();

            if (!empty($topTerms)) {
                $query->where(function ($sub) use ($topTerms) {
                    foreach ($topTerms as $term) {
                        $sub->orWhere('name', 'like', '%' . $term . '%');
                    }
                });
            } else {
                $query->latest();
            }
        } elseif ($collection === 'new-arrivals' || $collection === 'new_arrivals') {
            if (Product::where('status', true)->where('is_new', true)->exists()) {
                $query->where('is_new', true)->latest();
            } else {
                $query->latest();
            }
        } elseif ($collection === 'flash-sales' || $collection === 'flash_sales' || $collection === 'flash_sale') {
            $now = now();
            $query->whereNotNull('special_price')
                ->where('special_price', '>', 0)
                ->where(function ($q) use ($now) {
                    $q->whereNull('special_price_from')
                      ->orWhere('special_price_from', '<=', $now);
                })
                ->where(function ($q) use ($now) {
                    $q->whereNull('special_price_to')
                      ->orWhere('special_price_to', '>=', $now);
                })
                ->orderByRaw('CASE WHEN special_price_to IS NOT NULL THEN special_price_to ELSE "9999-12-31" END ASC');
        }

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

        $brandParam = $currentBrand ? $currentBrand->slug : $request->input('brand');
        if ($currentBrand) {
            $query->where('brand_id', $currentBrand->id);
        } elseif (!empty($brandParam)) {
            $slugs = array_filter(array_map('trim', is_array($brandParam) ? $brandParam : explode(',', (string) $brandParam)));
            if (!empty($slugs)) {
                $brandIds = Brand::active()->whereIn('slug', $slugs)->pluck('id')->toArray();
                $query->whereIn('brand_id', $brandIds ?: [0]);
            }
        }
        
        if ($request->has('search')) {
            $searchTerm = trim($request->search);
            if ($searchTerm !== '') {
                $query->where(function ($q) use ($searchTerm) {
                    $q->where('name', 'like', '%' . $searchTerm . '%')
                        ->orWhereHas('brand', fn ($bq) => $bq->where('name', 'like', '%' . $searchTerm . '%'))
                        ->orWhereHas('posUnits', function ($uq) use ($searchTerm) {
                            $uq->where('is_enabled_online', true)
                               ->where('show_in_listing', true)
                               ->where(function ($sub) use ($searchTerm) {
                                   $sub->where('online_label', 'like', '%' . $searchTerm . '%')
                                       ->orWhere('unit_name', 'like', '%' . $searchTerm . '%');
                               });
                        });
                });

                // Log full shop search (first page only to avoid inflating count on pagination clicks)
                if ((int) $request->input('page', 1) <= 1) {
                    try {
                        \App\Models\SearchLog::record(
                            query: $searchTerm,
                            resultsCount: (clone $query)->count(),
                            customerId: auth('customer')->id(),
                            ip: $request->ip(),
                            userAgent: $request->userAgent()
                        );
                    } catch (\Throwable $e) {}
                }
            }
        }

        $attrs = $request->input('attrs', []);
        if (is_array($attrs) && !empty($attrs)) {
            foreach ($attrs as $attrId => $value) {
                if (!empty($value)) {
                    $query->whereJsonContains("attributes->{$attrId}", $value);
                }
            }
        }

        // Standard e-commerce sorting: A-Z, Z-A, Price Low-High, Price High-Low, Newest, Best Selling
        $sort = $request->input('sort', 'default');
        switch ($sort) {
            case 'name_asc':
                $query->orderBy('name', 'asc');
                break;
            case 'name_desc':
                $query->orderBy('name', 'desc');
                break;
            case 'price_asc':
                $query->orderByRaw('COALESCE(NULLIF(discount_price, 0), price) ASC');
                break;
            case 'price_desc':
                $query->orderByRaw('COALESCE(NULLIF(discount_price, 0), price) DESC');
                break;
            case 'newest':
                $query->latest();
                break;
            case 'popular':
            case 'best_selling':
                $query->withSum('orderItems', 'quantity')
                    ->orderByDesc('order_items_sum_quantity')
                    ->orderByDesc('id');
                break;
            default:
                if (!$collection) {
                    $query->latest();
                }
                break;
        }

        // Pass the authenticated customer's wishlist product IDs
        $wishlistIds = [];
        if (auth('customer')->check()) {
            $wishlistIds = Wishlist::where('customer_id', auth('customer')->id())
                ->pluck('product_id')
                ->toArray();
        }

        $filters = $request->only(['search', 'category', 'collection', 'sort', 'brand']);
        $filters['collection'] = $collection ?: '';
        $filters['sort']       = $sort ?: 'default';
        $filters['brand']      = is_array($brandParam) ? implode(',', $brandParam) : ($brandParam ?: '');
        $filters['attrs']      = $attrs;

        // Build categories with parent/child grouping for the sidebar filter
        $categories = Category::whereNull('parent_id')
            ->with(['children' => fn($q) => $q->orderBy('name')])
            ->orderBy('name')
            ->get(['id','name','slug','parent_id']);

        // Active brands with active product counts for sidebar filter
        $brands = Brand::active()
            ->withCount(['products' => fn ($q) => $q->where('status', true)])
            ->ordered()
            ->get(['id', 'name', 'slug', 'logo'])
            ->filter(fn ($b) => $b->products_count > 0 || ($currentBrand && $b->id === $currentBrand->id))
            ->values();

        $paginator = $query->paginate(16)->withQueryString();
        $expandedPaginator = $this->expander->expandPaginator($paginator, $request->input('search'));

        return Inertia::render('Frontend/Products/Index', [
            'products'      => $expandedPaginator,
            'categories'    => $categories,
            'brands'        => $brands,
            'current_brand' => $currentBrand,
            'filters'       => $filters,
            'wishlist_ids'  => $wishlistIds,
        ]);
    }

    public function brand(Request $request, string $slug)
    {
        $brand = Brand::active()->where('slug', $slug)->firstOrFail();

        return $this->index($request, $brand);
    }

    public function show(Request $request, string $url_key)
    {
        // Resolve active product by url_key first, fall back to slug for old links
        $product = Product::where('status', true)
            ->where(function ($q) use ($url_key) {
                $q->where('url_key', $url_key)
                  ->orWhere('slug', $url_key);
            })
            ->firstOrFail();

        // Check if this product is in the authenticated customer's wishlist
        $isWishlisted = false;
        $userReview = null;
        if (auth('customer')->check()) {
            $customerId = auth('customer')->id();
            $isWishlisted = Wishlist::where('customer_id', $customerId)
                ->where('product_id', $product->id)
                ->exists();
            $userReview = $product->reviews()->where('customer_id', $customerId)->first();
        }

        $reviews = $product->approvedReviews()
            ->with('customer:id,name')
            ->latest()
            ->take(20)
            ->get();
        $avgRating = round((float) ($product->approvedReviews()->avg('rating') ?: 5.0), 1);
        $reviewsCount = $product->approvedReviews()->count();

        // Preselect selling unit if passed via ?unit= parameter
        $preselectedUnitId = null;
        if ($request->has('unit')) {
            $requestedUnit = $product->posUnits()->where('id', $request->query('unit'))->where('is_enabled_online', true)->first();
            if ($requestedUnit) {
                $preselectedUnitId = (int) $requestedUnit->id;
            }
        }
        
        return Inertia::render('Frontend/Products/Show', [
            'product'             => $product->load([
                'category',
                'brand:id,name,slug,logo',
                'posUnits' => fn($q) => $q->where('is_enabled_online', true)->orderBy('sort_order'),
                'variantMappings' => fn($q) => $q->where('is_active', true)->with('syncedPosItem')->orderBy('sort_order'),
            ]),
            'is_wishlisted'       => $isWishlisted,
            'related'             => $this->getRelated($product),
            'reviews'             => $reviews,
            'average_rating'      => $avgRating,
            'reviews_count'       => $reviewsCount,
            'user_review'         => $userReview,
            'preselected_unit_id' => $preselectedUnitId,
        ]);
    }

    private function getRelated(Product $product): \Illuminate\Support\Collection
    {
        $eagerLoad = array_merge(['category', 'brand:id,name,slug,logo'], $this->expander->eagerLoad());

        // Manually selected takes priority
        if (!empty($product->related_products)) {
            $related = Product::with($eagerLoad)
                ->whereIn('id', $product->related_products)
                ->where('status', true)
                ->where('id', '!=', $product->id)
                ->take(12)
                ->get();
            if ($related->isNotEmpty()) {
                return $this->expander->expandAndLimit($related, 12);
            }
        }
        // Fallback: same category
        $related = Product::with($eagerLoad)
            ->where('category_id', $product->category_id)
            ->where('id', '!=', $product->id)
            ->where('status', true)
            ->take(10)
            ->get();

        return $this->expander->expandAndLimit($related, 10);
    }
}
