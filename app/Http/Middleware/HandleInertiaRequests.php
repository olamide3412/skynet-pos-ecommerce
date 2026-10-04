<?php

namespace App\Http\Middleware;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Middleware;
use App\Models\Cart;
use App\Models\Compare;
use App\Models\Category;
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
            ],
            'flash' => [
                'success' => fn() => $request->session()->get('success'),
                'error' => fn() => $request->session()->get('error'),
                'message' => fn() => $request->session()->get('message')
            ],
            'support' => [
                'phone' => '+2348032072831',
                'phone_whatsapp' => '2348032072831', //'2348151702840' +234 803 207 2831,
                'phone_formatted' => '+234 803 207 2831',
                'email' => 'support@skynetdigitalhub.com.ng',
                'location' => 'Delta State, Nigeria'
            ],
            'csrf_token' => csrf_token(),
            'turnstileSiteKey' => config('services.turnstile.site_key'),
            'cart_count' => fn() => $this->getCartCount($request),
            'compare_count' => fn() => $this->getCompareCount($request),
            'store_settings' => fn() => StoreSetting::allAsArray(),
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
            return $cart ? $cart->items()->sum('quantity') : 0;
        } catch (\Throwable $e) {
            return 0;
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
}
