<?php

namespace App\Http\Middleware;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Inertia\Middleware;
use App\Models\Cart;
use App\Models\Compare;
use App\Models\Category;
use App\Models\Product;
use App\Models\SearchLog;
use App\Models\StoreSetting;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        return [
            ...parent::share($request),
            'auth' => [
                'user' => $this->getAuthUser('web'),
                'customer' => $this->getAuthUser('customer'),
                'check' => $this->isAuthenticated(),
                'type' => $this->getAuthType(),
                'roles' => fn() => Auth::guard('web')->check() ? Auth::guard('web')->user()->roles->pluck('name')->toArray() : [],
                'permissions' => fn() => $this->getUserPermissionsMap(),
                'isSuperAdmin' => fn() => Auth::guard('web')->check() && Auth::guard('web')->user()->isSuperAdmin(),
            ],
            'flash' => [
                'success' => fn() => $request->session()->get('success'),
                'error' => fn() => $request->session()->get('error'),
                'info' => fn() => $request->session()->get('info'),
                'warning' => fn() => $request->session()->get('warning'),
                'message' => fn() => $request->session()->get('message'),
                'status' => fn() => $request->session()->get('status'),
            ],
            'support' => [
                'phone' => '+2348032072831',
                'phone_whatsapp' => '2348032072831', //'2348151702840' +234 803 207 2831,
                'phone_formatted' => '+234 803 207 2831',
                'email' => 'support@skynetdigitalhub.com.ng',
                'location' => 'Delta State, Nigeria'
            ],
            'csrf_token' => csrf_token(),
            'app_url' => StoreSetting::getBaseUrl() ?: url('/'),
            'turnstileSiteKey' => config('services.turnstile.site_key'),
            'cart' => fn() => $this->getCartData($request),
            'cart_count' => fn() => $this->getCartCount($request),
            'compare_count' => fn() => $this->getCompareCount($request),
            'store_settings' => fn() => StoreSetting::allAsArray(),
            'top_searched_preview' => fn() => $this->getTopSearchedPreview(),
            'categories' => fn() => \App\Models\Category::whereNull('parent_id')
                ->where('visible_in_menu', true)
                ->with([
                    'children' => fn($q) => $q
                        ->where('visible_in_menu', true)
                        ->orderBy('menu_position', 'asc')
                ])
                ->orderBy('menu_position', 'asc')
                ->get(),
        ];
    }

    protected function getUserPermissionsMap(): array
    {
        $user = Auth::guard('web')->user();
        if (!$user) {
            return [];
        }

        if ($user->isSuperAdmin()) {
            // Super Admin can do everything
            $allPerms = \App\Support\PermissionCatalog::allPermissionNames();
            $map = [];
            foreach ($allPerms as $p) {
                $map[$p] = true;
            }
            return $map;
        }

        // Collect all permissions via roles and direct
        $map = [];
        foreach ($user->getAllPermissions() as $perm) {
            $map[$perm->name] = true;
        }
        return $map;
    }

    protected function getAuthUser($guard)
    {
        try {
            return fn() => Auth::guard($guard)->check() ? Auth::guard($guard)->user() : null;
        } catch (\InvalidArgumentException $e) {
            return fn() => null;
        }
    }

    protected function isAuthenticated()
    {
        try {
            return fn() => Auth::guard('web')->check() || Auth::guard('customer')->check();
        } catch (\InvalidArgumentException $e) {
            return fn() => Auth::guard('web')->check();
        }
    }

    protected function getAuthType()
    {
        try {
            if (Auth::guard('web')->check())
                return 'staff';
            if (Auth::guard('customer')->check())
                return 'customer';
        } catch (\InvalidArgumentException $e) {
            if (Auth::guard('web')->check())
                return 'staff';
        }
        return null;
    }

    protected function getCartCount(Request $request): int
    {
        try {
            if (Auth::guard('customer')->check()) {
                $cart = Cart::where('customer_id', Auth::guard('customer')->id())->first();
            } else {
                $cart = Cart::where('session_id', $request->session()->getId())->first();
            }
            return $cart ? (int) $cart->items()->sum('quantity') : 0;
        } catch (\Throwable $e) {
            return 0;
        }
    }

    protected function getCartData(Request $request): ?array
    {
        try {
            $query = Cart::with(['items.product', 'items.posUnit']);
            if (Auth::guard('customer')->check()) {
                $cart = $query->where('customer_id', Auth::guard('customer')->id())->first();
            } else {
                $cart = $query->where('session_id', $request->session()->getId())->first();
            }

            if (!$cart) {
                return [
                    'id'    => null,
                    'items' => [],
                    'total' => 0,
                    'count' => 0,
                ];
            }

            return [
                'id'    => $cart->id,
                'items' => $cart->items->map(function ($item) {
                    return [
                        'id'                  => $item->id,
                        'product_id'          => $item->product_id,
                        'product_pos_unit_id' => $item->product_pos_unit_id,
                        'unit_name'           => $item->unit_name,
                        'quantity'            => (int) $item->quantity,
                        'price'               => (float) $item->price,
                        'variant'             => $item->variant,
                        'variant_label'       => $item->variant_label,
                        'product'             => $item->product ? [
                            'id'             => $item->product->id,
                            'name'           => $item->product->name,
                            'image'          => $item->product->image,
                            'url_key'        => $item->product->url_key,
                            'price'          => (float) $item->product->price,
                            'discount_price' => $item->product->discount_price ? (float) $item->product->discount_price : null,
                            'stock'          => $item->product->stock,
                        ] : null,
                    ];
                })->values()->all(),
                'total' => (float) $cart->items->sum(fn($i) => $i->price * $i->quantity),
                'count' => (int) $cart->items->sum('quantity'),
            ];
        } catch (\Throwable $e) {
            return [
                'id'    => null,
                'items' => [],
                'total' => 0,
                'count' => 0,
            ];
        }
    }

    protected function getCompareCount(Request $request): int
    {
        try {
            if (Auth::guard('customer')->check()) {
                $compare = Compare::where('customer_id', Auth::guard('customer')->id())->first();
            } else {
                $compare = Compare::where('session_id', $request->session()->getId())->first();
            }
            return $compare ? $compare->items()->count() : 0;
        } catch (\Throwable $e) {
            return 0;
        }
    }

    /**
     * Cache and share the top 5 most searched items and keywords for instant header search focus.
     */
    protected function getTopSearchedPreview(): array
    {
        try {
            return Cache::remember('top_searched_preview', 600, function () {
                $topTerms = SearchLog::select('normalized_query')
                    ->selectRaw('COUNT(*) as search_count')
                    ->where('results_count', '>', 0)
                    ->where('created_at', '>=', now()->subDays(30))
                    ->groupBy('normalized_query')
                    ->orderByDesc('search_count')
                    ->limit(5)
                    ->pluck('normalized_query')
                    ->toArray();

                $products = collect();
                if (!empty($topTerms)) {
                    $searchedQuery = Product::with('category:id,name,slug')
                        ->where('status', true)
                        ->select('id', 'name', 'price', 'discount_price', 'image', 'category_id', 'url_key');

                    $searchedQuery->where(function ($sub) use ($topTerms) {
                        foreach ($topTerms as $term) {
                            $sub->orWhere('name', 'like', '%' . $term . '%');
                        }
                    });
                    $products = $searchedQuery->take(5)->get();
                }

                if ($products->count() < 5) {
                    $excludeIds = $products->pluck('id')->toArray();
                    $fallback = Product::with('category:id,name,slug')
                        ->where('status', true)
                        ->whereNotIn('id', $excludeIds)
                        ->select('id', 'name', 'price', 'discount_price', 'image', 'category_id', 'url_key')
                        ->inRandomOrder()
                        ->take(5 - $products->count())
                        ->get();
                    $products = $products->concat($fallback);
                }

                return [
                    'keywords' => $topTerms,
                    'products' => $products->values()->toArray(),
                ];
            });
        } catch (\Throwable $e) {
            return [
                'keywords' => [],
                'products' => [],
            ];
        }
    }
}
