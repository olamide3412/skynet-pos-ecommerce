<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\StoreSetting;
use App\Services\PosIntegration\PosConnectionService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class SettingsController extends Controller
{
    public function index(PosConnectionService $connectionService)
    {
        return Inertia::render('Admin/Settings/Index', [
            'settings'       => StoreSetting::allAsArray(),
            'pos_connection' => $connectionService->getConnectionSummary(),
        ]);
    }

    public function update(Request $request)
    {
        $data = $request->validate([
            // Company Branding
            'company_name'                  => 'nullable|string|max:255',
            'company_email'                 => 'nullable|email|max:255',
            'company_phone'                 => 'nullable|string|max:50',
            'company_address'               => 'nullable|string|max:500',
            'company_tagline'               => 'nullable|string|max:500',

            // Homepage Hero Content
            'home_slider_enabled'           => 'boolean',
            'home_marquee_enabled'          => 'boolean',
            'home_marquee_text'             => 'nullable|string|max:500',
            'hero_enabled'                  => 'boolean',
            'hero_badge'                    => 'nullable|string|max:100',
            'hero_title'                    => 'nullable|string|max:255',
            'hero_title_highlight'          => 'nullable|string|max:100',
            'hero_subtitle'                 => 'nullable|string|max:500',
            'hero_cta_primary'              => 'nullable|string|max:50',
            'hero_cta_secondary'            => 'nullable|string|max:50',

            // Homepage Promo Flyer / Banner
            'promo_banner_enabled'          => 'boolean',
            'promo_banner_title'            => 'nullable|string|max:255',
            'promo_banner_subtitle'         => 'nullable|string|max:500',
            'promo_banner_link'             => 'nullable|string|max:500',
            'promo_banner_button_text'      => 'nullable|string|max:50',

            // Homepage Promo / Coupon Flyer Popup Modal
            'promo_popup_enabled'           => 'boolean',
            'promo_popup_delay'             => 'nullable|integer|min:0|max:60',
            'promo_popup_title'             => 'nullable|string|max:255',
            'promo_popup_subtitle'          => 'nullable|string|max:500',
            'promo_popup_coupon_code'       => 'nullable|string|max:50',
            'promo_popup_cta_text'          => 'nullable|string|max:50',
            'promo_popup_cta_link'          => 'nullable|string|max:500',

            // Homepage Feature Blocks
            'feature_1_title'               => 'nullable|string|max:100',
            'feature_1_desc'                => 'nullable|string|max:255',
            'feature_2_title'               => 'nullable|string|max:100',
            'feature_2_desc'                => 'nullable|string|max:255',
            'feature_3_title'               => 'nullable|string|max:100',
            'feature_3_desc'                => 'nullable|string|max:255',

            // Store Features
            'order_notifications_enabled'   => 'boolean',
            'order_notification_emails'     => 'nullable|string|max:1000',
            'order_tracking_enabled'        => 'boolean',
            'customer_registration_enabled' => 'boolean',
            'shop_enabled'                  => 'boolean',
            'guest_checkout_enabled'        => 'boolean',
            'reviews_enabled'               => 'boolean',
            'wishlist_enabled'              => 'boolean',
            'show_cart_total_header'        => 'boolean',
            'tracking_number_prefix'        => 'nullable|string|max:10',
            // Product display
            'show_stock_level_default'      => 'boolean',
            'stock_display_mode'            => 'nullable|string|in:threshold_only,always,hidden',
            'low_stock_threshold'           => 'nullable|integer|min:1|max:100',

            // Authentication Providers
            'google_auth_enabled'           => 'boolean',
            'google_client_id'              => 'nullable|string',
            'google_client_secret'          => 'nullable|string',
            'facebook_auth_enabled'         => 'boolean',
            'facebook_client_id'            => 'nullable|string',
            'facebook_client_secret'        => 'nullable|string',

            // Payment gateways
            'paystack_enabled'              => 'boolean',
            'flutterwave_enabled'           => 'boolean',
            'cod_enabled'                   => 'boolean',
            'bank_transfer_enabled'         => 'boolean',
            'bank_name'                     => 'nullable|string|max:255',
            'bank_account_number'           => 'nullable|string|max:100',
            'bank_account_name'             => 'nullable|string|max:255',
            'bank_instructions'             => 'nullable|string|max:1000',

            // Store charges
            'tax_enabled'                   => 'boolean',
            'tax_rate'                      => 'nullable|numeric|min:0|max:100',
            'service_fee_enabled'           => 'boolean',
            'service_fee_amount'            => 'nullable|numeric|min:0',

            // Shipping
            'shipping_enabled'              => 'boolean',
            'waybill_fee'                   => 'nullable|numeric|min:0',
            'pickup_enabled'                => 'boolean',
            'pickup_address'                => 'nullable|string|max:500',
            'free_shipping_threshold'       => 'nullable|numeric|min:0',
            'default_delivery_address_hint' => 'nullable|string|max:500',
        ]);

        if ($request->hasFile('company_logo')) {
            $request->validate(['company_logo' => 'image|max:2048']);
            $logoPath = $request->file('company_logo')->store('settings', 'public');
            StoreSetting::set('company_logo', $logoPath);
        }

        if ($request->hasFile('hero_image')) {
            $request->validate(['hero_image' => 'image|max:4096']);
            $heroPath = $request->file('hero_image')->store('settings', 'public');
            StoreSetting::set('hero_image', $heroPath);
        }

        if ($request->hasFile('promo_banner_image')) {
            $request->validate(['promo_banner_image' => 'image|max:6144']);
            $bannerPath = $request->file('promo_banner_image')->store('settings', 'public');
            StoreSetting::set('promo_banner_image', $bannerPath);
        }

        if ($request->hasFile('promo_popup_image')) {
            $request->validate(['promo_popup_image' => 'image|max:6144']);
            $popupPath = $request->file('promo_popup_image')->store('settings', 'public');
            StoreSetting::set('promo_popup_image', $popupPath);
        }

        foreach ($data as $key => $value) {
            StoreSetting::set($key, is_bool($value) ? ($value ? '1' : '0') : ($value ?? ''));
        }

        return back()->with('success', 'Settings saved successfully.');
    }
}
