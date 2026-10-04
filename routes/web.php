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
Route::get('/', [HomeController::class , 'index'])->name('home');
Route::get('/shop', [FrontProductController::class , 'index'])->name('shop.index');
Route::get('/product/{url_key}', [FrontProductController::class , 'show'])->name('shop.show');

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

    return response()->json($products);
})->name('search.suggestions');

Route::get('/cart', [CartController::class , 'index'])->name('cart.index');
Route::post('/cart', [CartController::class , 'store'])->name('cart.store');
Route::put('/cart/{cartItem}', [CartController::class , 'update'])->name('cart.update');
Route::delete('/cart/{cartItem}', [CartController::class , 'destroy'])->name('cart.destroy');

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

// Customer Order History + Wishlist (authenticated customers only)
Route::middleware('auth:customer')->group(function () {
    Route::get('/my-orders', [OrderTrackingController::class , 'myOrders'])->name('customer.orders');
    Route::get('/my-orders/{order}', [OrderTrackingController::class , 'myOrderDetail'])->name('customer.orders.show');
    Route::get('/wishlist', [WishlistController::class , 'index'])->name('wishlist.index');
    Route::post('/wishlist/toggle', [WishlistController::class , 'toggle'])->name('wishlist.toggle');
});

/* |-------------------------------------------------------------------------- | CUSTOMER AUTH ROUTES  (guard: customer) |-------------------------------------------------------------------------- */
// Login / Register — open to guests (controller handles redirect if already logged in)
Route::get('/login', [CustomerAuthController::class , 'showLogin'])->name('login');
Route::post('/login', [CustomerAuthController::class , 'login'])->name('customer.login.submit');
Route::get('/register', [CustomerAuthController::class , 'showRegister'])->name('register');
Route::post('/register', [CustomerAuthController::class , 'register'])->name('customer.register.submit');

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
            Route::resource('sliders', SliderController::class)->except(['create', 'edit', 'show']);
            Route::post('products/bulk-action', [AdminProductController::class , 'bulkAction'])->name('products.bulk-action');
            Route::resource('products', AdminProductController::class)->except(['create', 'edit', 'show']);
            Route::get('products/search-related', [AdminProductController::class , 'searchRelated'])->name('products.search-related');
            Route::get('products/related-by-ids', [AdminProductController::class , 'relatedByIds'])->name('products.related-by-ids');
            Route::resource('attributes', \App\Http\Controllers\Admin\AttributeController::class)->except(['create', 'edit', 'show']);
            Route::resource('users', \App\Http\Controllers\Admin\UserController::class)->except(['create', 'edit', 'show']);

            Route::get('orders', [OrderController::class , 'index'])->name('orders.index');
            Route::get('orders/{order}', [OrderController::class , 'show'])->name('orders.show');
            Route::get('orders/{order}/invoice', [OrderController::class , 'invoice'])->name('orders.invoice');
            Route::put('orders/{order}/status', [OrderController::class , 'updateStatus'])->name('orders.status');

            Route::get('revenue', [\App\Http\Controllers\Admin\RevenueController::class, 'index'])->name('revenue.index');

            // Store & Payment Settings
            Route::get('settings', [SettingsController::class , 'index'])->name('settings.index');
            Route::put('settings', [SettingsController::class , 'update'])->name('settings.update');

            // POS Integration Center
            Route::get('pos-integration', [\App\Http\Controllers\Admin\PosIntegrationController::class, 'index'])->name('pos-integration.index');
            Route::post('pos-integration/save-and-test', [\App\Http\Controllers\Admin\PosIntegrationController::class, 'saveAndTestConnection'])->name('pos-integration.save-and-test');
            Route::post('pos-integration/test', [\App\Http\Controllers\Admin\PosIntegrationController::class, 'testConnection'])->name('pos-integration.test');
            Route::post('pos-integration/sync-catalog', [\App\Http\Controllers\Admin\PosIntegrationController::class, 'syncCatalog'])->name('pos-integration.sync-catalog');
            Route::post('pos-integration/sync-availability', [\App\Http\Controllers\Admin\PosIntegrationController::class, 'syncAvailability'])->name('pos-integration.sync-availability');
            Route::post('pos-integration/import-simple', [\App\Http\Controllers\Admin\PosIntegrationController::class, 'importSimple'])->name('pos-integration.import-simple');
            Route::post('pos-integration/bulk-import', [\App\Http\Controllers\Admin\PosIntegrationController::class, 'bulkImport'])->name('pos-integration.bulk-import');
            Route::post('pos-integration/import-all', [\App\Http\Controllers\Admin\PosIntegrationController::class, 'importAllEligible'])->name('pos-integration.import-all');
        }
        );

    // Protected POS Connection Handshake Callback (Called by POS Super Admin)
    Route::post('pos-integration/connect', [\App\Http\Controllers\Admin\PosIntegrationController::class, 'establishConnection'])->name('pos-integration.connect');
});
