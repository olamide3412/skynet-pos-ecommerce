<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\Frontend\ProductController as FrontProductController;
use App\Http\Controllers\Frontend\CartController;
use App\Http\Controllers\Frontend\CompareController;
use App\Http\Controllers\Frontend\CheckoutController;
use App\Http\Controllers\Frontend\OrderTrackingController;
use App\Http\Controllers\Frontend\WishlistController;
use App\Http\Controllers\Admin\CategoryController;
use App\Http\Controllers\Admin\ProductController as AdminProductController;
use App\Http\Controllers\Admin\OrderController;
use App\Http\Controllers\Admin\SettingsController;
use App\Http\Controllers\Admin\SliderController;
use App\Http\Controllers\Frontend\HomeController;
use App\Http\Controllers\Frontend\CustomerAuthController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

/* |-------------------------------------------------------------------------- | PUBLIC STOREFRONT ROUTES |-------------------------------------------------------------------------- */
Route::get('/sitemap.xml', [\App\Http\Controllers\Frontend\SitemapController::class, 'index'])->name('sitemap');
Route::get('/robots.txt', [\App\Http\Controllers\Frontend\SitemapController::class, 'robots'])->name('robots');

Route::get('/', [HomeController::class , 'index'])->name('home');
Route::get('/shop', [FrontProductController::class , 'index'])->name('shop.index');
Route::get('/product/{url_key}', [FrontProductController::class , 'show'])->name('shop.show');
Route::post('/newsletter/subscribe', [\App\Http\Controllers\Frontend\NewsletterController::class, 'subscribe'])
    ->middleware('throttle:10,1')
    ->name('newsletter.subscribe');

// Quick search suggestions (JSON — used by the header search dropdown)
Route::get('/search/suggestions', function (\Illuminate\Http\Request $request) {
    $q = trim($request->get('q', ''));
    if (strlen($q) < 2)
        return response()->json([]);

    $products = \App\Models\Product::where('status', true)
        ->where('name', 'like', '%' . $q . '%')
        ->with('category:id,name,slug')
        ->select('id', 'name', 'price', 'discount_price', 'image', 'category_id', 'url_key')
        ->limit(5)
        ->get();

    // Log the search keyword
    try {
        \App\Models\SearchLog::record(
            query: $q,
            resultsCount: $products->count(),
            customerId: auth('customer')->id(),
            ip: $request->ip(),
            userAgent: $request->userAgent()
        );
    } catch (\Throwable $e) {}

    return response()->json($products);
})->name('search.suggestions');

// Top trending searches & most searched products (used on search bar focus)
Route::get('/search/trending', function () {
    $topTerms = \App\Models\SearchLog::select('normalized_query')
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
        $searchedQuery = \App\Models\Product::with('category:id,name,slug')
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
        $fallback = \App\Models\Product::with('category:id,name,slug')
            ->where('status', true)
            ->whereNotIn('id', $excludeIds)
            ->select('id', 'name', 'price', 'discount_price', 'image', 'category_id', 'url_key')
            ->inRandomOrder()
            ->take(5 - $products->count())
            ->get();
        $products = $products->concat($fallback);
    }

    return response()->json([
        'keywords' => $topTerms,
        'products' => $products->values(),
    ]);
})->name('search.trending');

Route::get('/cart', [CartController::class , 'index'])->name('cart.index');
Route::post('/cart', [CartController::class , 'store'])->name('cart.store');
Route::put('/cart/{cartItem}', [CartController::class , 'update'])->name('cart.update');
Route::delete('/cart/{cartItem}', [CartController::class , 'destroy'])->name('cart.destroy');

// Coupons
Route::post('/cart/coupon/apply', [\App\Http\Controllers\Frontend\CouponController::class, 'apply'])->name('coupon.apply');
Route::post('/cart/coupon/remove', [\App\Http\Controllers\Frontend\CouponController::class, 'remove'])->name('coupon.remove');

// Product Reviews
Route::post('/products/{product}/reviews', [\App\Http\Controllers\Frontend\ProductReviewController::class, 'store'])->name('products.reviews.store');

Route::get('/compare', [CompareController::class , 'index'])->name('compare.index');
Route::post('/compare', [CompareController::class , 'store'])->name('compare.store');
Route::delete('/compare/{compareItem}', [CompareController::class , 'destroy'])->name('compare.destroy');

Route::get('/checkout', [CheckoutController::class , 'index'])->name('checkout.index');
Route::post('/checkout/process', [CheckoutController::class , 'process'])->name('checkout.process');
Route::get('/checkout/success/{tracking}', [CheckoutController::class , 'success'])->name('checkout.success');
Route::get('/payment/callback', [CheckoutController::class , 'callback'])->name('payment.callback');
Route::post('/webhook/paystack', [CheckoutController::class , 'paystackWebhook'])->name('webhook.paystack');
Route::post('/webhook/flutterwave', [CheckoutController::class , 'flutterwaveWebhook'])->name('webhook.flutterwave');

// Order Tracking (public — no login needed)
Route::get('/track-order', [OrderTrackingController::class , 'index'])->name('order.track');
Route::post('/track-order', [OrderTrackingController::class , 'track'])->name('order.track.search');
Route::get('/track/{tracking}', [OrderTrackingController::class , 'trackDirect'])->name('order.track.direct');

// Customer Order History + Wishlist + Account Portal (authenticated customers only)
Route::middleware('auth:customer')->group(function () {
    Route::get('/my-orders', [OrderTrackingController::class , 'myOrders'])->name('customer.orders');
    Route::get('/my-orders/{order}', [OrderTrackingController::class , 'myOrderDetail'])->name('customer.orders.show');
    Route::get('/wishlist', [WishlistController::class , 'index'])->name('wishlist.index');
    Route::post('/wishlist/toggle', [WishlistController::class , 'toggle'])->name('wishlist.toggle');

    // Customer Account & Saved Addresses
    Route::get('/my-account', [\App\Http\Controllers\Frontend\CustomerProfileController::class, 'show'])->name('customer.profile');
    Route::put('/my-account', [\App\Http\Controllers\Frontend\CustomerProfileController::class, 'updateProfile'])->name('customer.profile.update');
    Route::put('/my-account/password', [\App\Http\Controllers\Frontend\CustomerProfileController::class, 'updatePassword'])->name('customer.password.update');
    Route::get('/my-addresses', [\App\Http\Controllers\Frontend\CustomerProfileController::class, 'addresses'])->name('customer.addresses');
    Route::post('/my-addresses', [\App\Http\Controllers\Frontend\CustomerProfileController::class, 'storeAddress'])->name('customer.addresses.store');
    Route::put('/my-addresses/{address}', [\App\Http\Controllers\Frontend\CustomerProfileController::class, 'updateAddress'])->name('customer.addresses.update');
    Route::delete('/my-addresses/{address}', [\App\Http\Controllers\Frontend\CustomerProfileController::class, 'deleteAddress'])->name('customer.addresses.delete');
    Route::post('/my-addresses/{address}/default', [\App\Http\Controllers\Frontend\CustomerProfileController::class, 'setDefaultAddress'])->name('customer.addresses.default');
});

/* |-------------------------------------------------------------------------- | CUSTOMER AUTH ROUTES  (guard: customer) |-------------------------------------------------------------------------- */
// Login / Register — open to guests (controller handles redirect if already logged in)
Route::get('/login', [CustomerAuthController::class , 'showLogin'])->name('login');
Route::post('/login', [CustomerAuthController::class , 'login'])->name('customer.login.submit');
Route::get('/register', [CustomerAuthController::class , 'showRegister'])->name('register');
Route::post('/register', [CustomerAuthController::class , 'register'])->name('customer.register.submit');

// Password Reset Routes
Route::get('/forgot-password', [\App\Http\Controllers\Frontend\CustomerPasswordResetController::class, 'showForgotPassword'])->name('password.request');
Route::post('/forgot-password', [\App\Http\Controllers\Frontend\CustomerPasswordResetController::class, 'sendResetLink'])->name('password.email');
Route::get('/reset-password/{token}', [\App\Http\Controllers\Frontend\CustomerPasswordResetController::class, 'showResetPassword'])->name('password.reset');
Route::post('/reset-password', [\App\Http\Controllers\Frontend\CustomerPasswordResetController::class, 'resetPassword'])->name('password.update');

Route::get('/auth/{provider}/redirect', [\App\Http\Controllers\Frontend\SocialLoginController::class, 'redirect'])->name('social.redirect');
Route::get('/auth/{provider}/callback', [\App\Http\Controllers\Frontend\SocialLoginController::class, 'callback'])->name('social.callback');

// Logout — requires authenticated customer
Route::middleware('auth:customer')->group(function () {
    Route::post('/logout', [CustomerAuthController::class , 'destroy'])->name('customer.logout');
});

/* |-------------------------------------------------------------------------- | ADMIN ROUTES  (all prefixed /admin/, guard: web) |-------------------------------------------------------------------------- */
Route::prefix('admin')->name('admin.')->group(function () {

    // Admin login — open, controller redirects if already logged in
    Route::get('/login', [AuthController::class , 'showLogin'])->name('login');
    Route::post('/login', [AuthController::class , 'login'])->name('login.submit');

    // Authenticated admin pages
    Route::middleware(['auth:web', 'admin'])->group(function () {
            Route::post('/logout', [AuthController::class , 'destroy'])->name('logout');
            Route::get('/dashboard', [DashboardController::class , 'index'])->name('dashboard');
            Route::get('/profile', [ProfileController::class , 'edit'])->name('profile.edit');
            Route::put('/profile/password', [ProfileController::class , 'updatePassword'])->name('profile.password.update');

            Route::resource('categories', CategoryController::class)->except(['create', 'edit', 'show']);
            Route::post('sliders/settings', [SliderController::class, 'updateSettings'])->name('sliders.settings.update');
            Route::resource('sliders', SliderController::class)->except(['create', 'edit', 'show']);
            Route::post('products/bulk-action', [AdminProductController::class , 'bulkAction'])->name('products.bulk-action');
            Route::resource('products', AdminProductController::class)->except(['create', 'edit', 'show']);
            Route::get('products/search-related', [AdminProductController::class , 'searchRelated'])->name('products.search-related');
            Route::get('products/related-by-ids', [AdminProductController::class , 'relatedByIds'])->name('products.related-by-ids');
            Route::resource('attributes', \App\Http\Controllers\Admin\AttributeController::class)->except(['create', 'edit', 'show']);
            Route::patch('users/{user}/toggle-status', [\App\Http\Controllers\Admin\UserController::class, 'toggleStatus'])->name('users.toggle-status');
            Route::put('users/{user}/reset-password', [\App\Http\Controllers\Admin\UserController::class, 'resetPassword'])->name('users.reset-password');
            Route::resource('users', \App\Http\Controllers\Admin\UserController::class)->except(['create', 'edit', 'show']);

            // Customer Management (Strictly Admin and Super Admin Only)
            Route::middleware('admin_only')->group(function () {
                Route::get('customers/export/csv', [\App\Http\Controllers\Admin\CustomerController::class, 'exportCsv'])->name('customers.export');
                Route::put('customers/{customer}/reset-password', [\App\Http\Controllers\Admin\CustomerController::class, 'resetPassword'])->name('customers.reset-password');
                Route::resource('customers', \App\Http\Controllers\Admin\CustomerController::class)->only(['index', 'show', 'update', 'destroy']);
            });

            Route::get('orders', [OrderController::class , 'index'])->name('orders.index');
            Route::get('orders/{order}', [OrderController::class , 'show'])->name('orders.show');
            Route::get('orders/{order}/invoice', [OrderController::class , 'invoice'])->name('orders.invoice');
            Route::put('orders/{order}/status', [OrderController::class , 'updateStatus'])->name('orders.status');
            Route::post('orders/{order}/add-note', [OrderController::class, 'addNote'])->name('orders.add-note');

            Route::get('revenue', [\App\Http\Controllers\Admin\RevenueController::class, 'index'])->name('revenue.index');
            Route::get('reports/end-of-day', [\App\Http\Controllers\Admin\EndOfDayReportController::class, 'index'])->name('reports.end-of-day');
            Route::get('reports/sales', [\App\Http\Controllers\Admin\SalesReportController::class, 'index'])->name('reports.sales');
            Route::get('reports/sales/export', [\App\Http\Controllers\Admin\SalesReportController::class, 'exportCsv'])->name('reports.sales.export');
            Route::get('reports/search-analytics', [\App\Http\Controllers\Admin\SearchAnalyticsController::class, 'index'])->name('reports.search-analytics');
            Route::post('orders/{order}/assign-staff', [\App\Http\Controllers\Admin\SalesReportController::class, 'assignStaff'])->name('orders.assign-staff');
            Route::post('orders/{order}/mark-cash-collected', [\App\Http\Controllers\Admin\SalesReportController::class, 'markCashCollected'])->name('orders.mark-cash-collected');
            Route::post('orders/{order}/mark-cash-remitted', [\App\Http\Controllers\Admin\SalesReportController::class, 'markCashRemitted'])->name('orders.mark-cash-remitted');
            Route::post('orders/{order}/reconcile-pod', [\App\Http\Controllers\Admin\SalesReportController::class, 'reconcilePod'])->name('orders.reconcile-pod');

            // Dedicated POD Cash Management & Till Audit Station
            Route::get('pod-cash', [\App\Http\Controllers\Admin\PodCashController::class, 'index'])->name('pod-cash.index');
            Route::post('pod-cash/{order}/collect', [\App\Http\Controllers\Admin\PodCashController::class, 'markCollected'])->name('pod-cash.collect');
            Route::post('pod-cash/{order}/remit', [\App\Http\Controllers\Admin\PodCashController::class, 'markRemitted'])->name('pod-cash.remit');
            Route::post('pod-cash/bulk-remit', [\App\Http\Controllers\Admin\PodCashController::class, 'bulkRemit'])->name('pod-cash.bulk-remit');
            Route::post('pod-cash/{order}/reconcile', [\App\Http\Controllers\Admin\PodCashController::class, 'reconcile'])->name('pod-cash.reconcile');
            Route::post('pod-cash/bulk-reconcile', [\App\Http\Controllers\Admin\PodCashController::class, 'bulkReconcile'])->name('pod-cash.bulk-reconcile');
            Route::get('pod-cash/export', [\App\Http\Controllers\Admin\PodCashController::class, 'exportCsv'])->name('pod-cash.export');

            // Collections Received - Dedicated Audit & Settlement Hub by Cashier & Date Range
            Route::get('collections-received', [\App\Http\Controllers\Admin\CollectionsReceivedController::class, 'index'])->name('collections-received.index');
            Route::get('collections-received/export', [\App\Http\Controllers\Admin\CollectionsReceivedController::class, 'exportCsv'])->name('collections-received.export');

            // Dedicated Discounts & Flash Deals Management
            Route::get('discounts/search-products', [\App\Http\Controllers\Admin\DiscountController::class, 'searchProducts'])->name('discounts.search-products');
            Route::post('discounts/apply', [\App\Http\Controllers\Admin\DiscountController::class, 'apply'])->name('discounts.apply');
            Route::post('discounts/bulk-action', [\App\Http\Controllers\Admin\DiscountController::class, 'bulkAction'])->name('discounts.bulk-action');
            Route::put('discounts/{product}', [\App\Http\Controllers\Admin\DiscountController::class, 'update'])->name('discounts.update');
            Route::delete('discounts/{product}', [\App\Http\Controllers\Admin\DiscountController::class, 'destroy'])->name('discounts.destroy');
            Route::get('discounts', [\App\Http\Controllers\Admin\DiscountController::class, 'index'])->name('discounts.index');

            // Coupons & Discount Engine
            Route::patch('coupons/{coupon}/toggle-status', [\App\Http\Controllers\Admin\CouponController::class, 'toggleStatus'])->name('coupons.toggle-status');
            Route::resource('coupons', \App\Http\Controllers\Admin\CouponController::class)->except(['create', 'edit', 'show']);

            // Product Reviews Moderation
            Route::get('reviews', [\App\Http\Controllers\Admin\ProductReviewController::class, 'index'])->name('reviews.index');
            Route::patch('reviews/{review}/toggle-approval', [\App\Http\Controllers\Admin\ProductReviewController::class, 'toggleApproval'])->name('reviews.toggle-approval');
            Route::delete('reviews/{review}', [\App\Http\Controllers\Admin\ProductReviewController::class, 'destroy'])->name('reviews.destroy');

            // Shipping Zones Management
            Route::patch('shipping-zones/{shipping_zone}/set-default', [\App\Http\Controllers\Admin\ShippingZoneController::class, 'setDefault'])->name('shipping-zones.set-default');
            Route::patch('shipping-zones/{shipping_zone}/toggle-status', [\App\Http\Controllers\Admin\ShippingZoneController::class, 'toggleStatus'])->name('shipping-zones.toggle-status');
            Route::resource('shipping-zones', \App\Http\Controllers\Admin\ShippingZoneController::class)->except(['create', 'edit', 'show']);

            // Store & Payment Settings
            Route::get('settings', [SettingsController::class , 'index'])->name('settings.index');
            Route::put('settings', [SettingsController::class , 'update'])->name('settings.update');

            // Promo Flyers & Timed Popup Modal
            Route::get('promo-flyers', [\App\Http\Controllers\Admin\PromoFlyerController::class, 'index'])->name('promo-flyers.index');
            Route::post('promo-flyers', [\App\Http\Controllers\Admin\PromoFlyerController::class, 'update'])->name('promo-flyers.update');

            // Newsletter Subscribers
            Route::get('newsletter-subscribers', [\App\Http\Controllers\Admin\NewsletterSubscriberController::class, 'index'])->name('newsletter.index');
            Route::delete('newsletter-subscribers/{subscriber}', [\App\Http\Controllers\Admin\NewsletterSubscriberController::class, 'destroy'])->name('newsletter.destroy');
            Route::patch('newsletter-subscribers/{subscriber}/toggle-status', [\App\Http\Controllers\Admin\NewsletterSubscriberController::class, 'toggleStatus'])->name('newsletter.toggle-status');
            Route::get('newsletter-subscribers/export/csv', [\App\Http\Controllers\Admin\NewsletterSubscriberController::class, 'exportCsv'])->name('newsletter.export');

            // Roles & Permissions Management (STRICTLY Super Administrator)
            Route::middleware('super_admin')->group(function () {
                Route::get('roles', [\App\Http\Controllers\Admin\RoleController::class, 'index'])->name('roles.index');
                Route::post('roles', [\App\Http\Controllers\Admin\RoleController::class, 'store'])->name('roles.store');
                Route::put('roles/{role}', [\App\Http\Controllers\Admin\RoleController::class, 'update'])->name('roles.update');
                Route::delete('roles/{role}', [\App\Http\Controllers\Admin\RoleController::class, 'destroy'])->name('roles.destroy');
            });

            // Theme & Dual-Color Palette Settings
            Route::get('theme-settings', [\App\Http\Controllers\Admin\ThemeController::class, 'index'])->name('themes.index');
            Route::put('theme-settings', [\App\Http\Controllers\Admin\ThemeController::class, 'update'])->name('themes.update');

            // POS Integration & Connection Settings
            Route::get('pos-connection', [\App\Http\Controllers\Admin\PosConnectionController::class, 'index'])->name('pos-connection.index');
            Route::get('pos-integration', [\App\Http\Controllers\Admin\PosIntegrationController::class, 'index'])->name('pos-integration.index');
            Route::post('pos-integration/save-and-test', [\App\Http\Controllers\Admin\PosIntegrationController::class, 'saveAndTestConnection'])->name('pos-integration.save-and-test');
            Route::post('pos-integration/test', [\App\Http\Controllers\Admin\PosIntegrationController::class, 'testConnection'])->name('pos-integration.test');
            Route::post('pos-integration/sync-catalog', [\App\Http\Controllers\Admin\PosIntegrationController::class, 'syncCatalog'])->name('pos-integration.sync-catalog');
            Route::post('pos-integration/sync-availability', [\App\Http\Controllers\Admin\PosIntegrationController::class, 'syncAvailability'])->name('pos-integration.sync-availability');
            Route::post('pos-integration/reconcile-mappings', [\App\Http\Controllers\Admin\PosIntegrationController::class, 'reconcileMappings'])->name('pos-integration.reconcile-mappings');
            Route::post('pos-integration/import-simple', [\App\Http\Controllers\Admin\PosIntegrationController::class, 'importSimple'])->name('pos-integration.import-simple');
            Route::post('pos-integration/bulk-import', [\App\Http\Controllers\Admin\PosIntegrationController::class, 'bulkImport'])->name('pos-integration.bulk-import');
            Route::post('pos-integration/import-all', [\App\Http\Controllers\Admin\PosIntegrationController::class, 'importAllEligible'])->name('pos-integration.import-all');
        }
        );

    // Protected POS Connection Handshake Callback (Called by POS Super Admin)
    Route::post('pos-integration/connect', [\App\Http\Controllers\Admin\PosIntegrationController::class, 'establishConnection'])->name('pos-integration.connect');
});
