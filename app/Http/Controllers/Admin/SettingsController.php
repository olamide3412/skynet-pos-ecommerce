<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\StoreSetting;
use App\Services\ActivityLogger;
use App\Services\PosIntegration\PosConnectionService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class SettingsController extends Controller
{
    protected function authorizeSuperAdmin(): void
    {
        $user = auth('web')->user();
        if (!$user || !$user->isSuperAdmin()) {
            abort(403, 'Unauthorized. Only the Super Administrator can access or modify system settings.');
        }
    }

    public function index(PosConnectionService $connectionService)
    {
        $this->authorizeSuperAdmin();

        return Inertia::render('Admin/Settings/Index', [
            'settings'       => StoreSetting::allAsArray(),
            'pos_connection' => $connectionService->getConnectionSummary(),
        ]);
    }

    public function update(Request $request)
    {
        $this->authorizeSuperAdmin();

        $data = $request->validate([
            // Company Branding & SEO
            'company_name'                  => 'nullable|string|max:255',
            'company_email'                 => 'nullable|email|max:255',
            'company_phone'                 => 'nullable|string|max:50',
            'company_address'               => 'nullable|string|max:500',
            'company_tagline'               => 'nullable|string|max:500',
            'site_url'                      => 'nullable|url|max:255',
            'meta_description'              => 'nullable|string|max:500',
            'meta_keywords'                 => 'nullable|string|max:500',

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
            'show_stock_level_default'          => 'boolean',
            'stock_display_mode'                => 'nullable|string|in:threshold_only,always,hidden',
            'low_stock_threshold'               => 'nullable|integer|min:1|max:100',
            'split_selling_units_in_listing'    => 'boolean',
            'split_unit_out_of_stock_behavior'  => 'nullable|string|in:show_out_of_stock,hide',
            'pos_brand_sync_overwrite'          => 'boolean',
            'default_product_warranty'          => 'nullable|string|max:1000',
            'default_product_return_policy'     => 'nullable|string|max:2000',
            'enable_product_expiry_tracking'    => 'boolean',
            'expiry_warning_threshold_days'     => 'nullable|integer|min:1|max:365',
            'hide_expired_products_on_storefront' => 'boolean',

            // Authentication Providers
            'turnstile_enabled'             => 'boolean',
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

            // Referral / Affiliate Program
            'referral_enabled'              => 'boolean',
            'referral_allow_applications'   => 'boolean',
            'referral_commission_type'      => 'nullable|string|in:percentage,flat',
            'referral_commission_rate'      => 'nullable|numeric|min:0',
            'referral_cookie_days'          => 'nullable|integer|min:1|max:365',
            'referral_holding_days'         => 'nullable|integer|min:0|max:90',
            'referral_min_withdrawal'       => 'nullable|numeric|min:0',
            'referral_min_order_amount'     => 'nullable|numeric|min:0',

            // AI Assistant & Providers
            'ai_enabled'                    => 'boolean',
            'ai_active_provider'            => 'nullable|string|in:gemini,openai,deepseek,claude',
            'ai_gemini_key'                 => 'nullable|string|max:500',
            'ai_gemini_model'               => 'nullable|string|max:100',
            'ai_openai_key'                 => 'nullable|string|max:500',
            'ai_openai_model'               => 'nullable|string|max:100',
            'ai_deepseek_key'               => 'nullable|string|max:500',
            'ai_deepseek_model'             => 'nullable|string|max:100',
            'ai_claude_key'                 => 'nullable|string|max:500',
            'ai_claude_model'               => 'nullable|string|max:100',
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

        ActivityLogger::setting("Updated store configurations", [
            'updated_fields' => array_keys($data),
        ]);

        return back()->with('success', 'Settings saved successfully.');
    }
}
