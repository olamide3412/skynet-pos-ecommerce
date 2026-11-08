<script setup>
import { Head, Link, useForm, router } from '@inertiajs/vue3';
import { ref, computed } from 'vue';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import SettingsNavTabs from '@/Components/Admin/SettingsNavTabs.vue';
import { useToast } from 'vue-toastification';

const toast = useToast();

const props = defineProps({
    settings: Object,
    pos_connection: Object,
});

const form = useForm({
    _method: 'PUT',
    // Company Branding
    company_name:           props.settings.company_name || 'Skynet',
    company_tagline:        props.settings.company_tagline || '',
    site_url:               props.settings.site_url || '',
    company_email:          props.settings.company_email || 'support@skynetdigitalhub.com.ng',
    company_phone:          props.settings.company_phone || '+2348032072831',
    company_address:        props.settings.company_address || 'Delta State, Nigeria',
    meta_description:       props.settings.meta_description || '',
    meta_keywords:          props.settings.meta_keywords || '',
    company_logo:           null,
    // Order Notifications & Staff Alerts
    order_notifications_enabled:   props.settings.order_notifications_enabled !== '0',
    order_notification_emails:     props.settings.order_notification_emails || '',
    // Referral & Affiliate Program
    referral_enabled:              props.settings.referral_enabled !== '0',
    referral_allow_applications:   props.settings.referral_allow_applications !== '0',
    referral_commission_type:      props.settings.referral_commission_type || 'percentage',
    referral_commission_rate:      props.settings.referral_commission_rate || '5',
    referral_cookie_days:          props.settings.referral_cookie_days || '30',
    referral_holding_days:         props.settings.referral_holding_days || '7',
    referral_min_withdrawal:       props.settings.referral_min_withdrawal || '5000',
    referral_min_order_amount:     props.settings.referral_min_order_amount || '0',
    // Payments
    paystack_enabled:       props.settings.paystack_enabled === '1',
    flutterwave_enabled:    props.settings.flutterwave_enabled === '1',
    cod_enabled:            props.settings.cod_enabled === '1',
    bank_transfer_enabled:  props.settings.bank_transfer_enabled === '1',
    bank_name:              props.settings.bank_name || '',
    bank_account_number:    props.settings.bank_account_number || '',
    bank_account_name:      props.settings.bank_account_name || '',
    bank_instructions:      props.settings.bank_instructions || '',
    // Tax & Service Fee
    tax_enabled:            props.settings.tax_enabled === '1',
    tax_rate:               props.settings.tax_rate || '7.5',
    service_fee_enabled:    props.settings.service_fee_enabled === '1',
    service_fee_amount:     props.settings.service_fee_amount || '0',
    // Shipping
    shipping_enabled:       props.settings.shipping_enabled === '1',
    waybill_fee:            props.settings.waybill_fee || '1500',
    pickup_enabled:         props.settings.pickup_enabled === '1',
    pickup_address:         props.settings.pickup_address || '',
    free_shipping_threshold: props.settings.free_shipping_threshold || '50000',
    default_delivery_address_hint: props.settings.default_delivery_address_hint || '',
    // Store Features
    order_tracking_enabled:         props.settings.order_tracking_enabled === '1',
    customer_registration_enabled:  props.settings.customer_registration_enabled === '1',
    shop_enabled:                   props.settings.shop_enabled === '1',
    guest_checkout_enabled:         props.settings.guest_checkout_enabled === '1',
    reviews_enabled:                props.settings.reviews_enabled === '1',
    wishlist_enabled:               props.settings.wishlist_enabled === '1',
    show_cart_total_header:         props.settings.show_cart_total_header !== '0',
    tracking_number_prefix:         props.settings.tracking_number_prefix || 'SKY-',
    show_stock_level_default:       props.settings.show_stock_level_default !== '0',
    stock_display_mode:             props.settings.stock_display_mode || 'threshold_only',
    low_stock_threshold:            props.settings.low_stock_threshold || '5',
    // Authentication Providers
    turnstile_enabled:              props.settings.turnstile_enabled !== '0',
    google_auth_enabled:            props.settings.google_auth_enabled === '1',
    google_client_id:               props.settings.google_client_id || '',
    google_client_secret:           props.settings.google_client_secret || '',
    facebook_auth_enabled:          props.settings.facebook_auth_enabled === '1',
    facebook_client_id:             props.settings.facebook_client_id || '',
    facebook_client_secret:         props.settings.facebook_client_secret || '',
    // AI Assistant & Providers
    ai_enabled:                     props.settings.ai_enabled === '1',
    ai_active_provider:             props.settings.ai_active_provider || 'gemini',
    ai_gemini_key:                  props.settings.ai_gemini_key || '',
    ai_gemini_model:                props.settings.ai_gemini_model || 'gemini-2.5-flash',
    ai_openai_key:                  props.settings.ai_openai_key || '',
    ai_openai_model:                props.settings.ai_openai_model || 'gpt-4o-mini',
    ai_deepseek_key:                props.settings.ai_deepseek_key || '',
    ai_deepseek_model:              props.settings.ai_deepseek_model || 'deepseek-chat',
    ai_claude_key:                  props.settings.ai_claude_key || '',
    ai_claude_model:                props.settings.ai_claude_model || 'claude-3-5-haiku-20241022',
});

const handleLogoUpload = (e) => {
    form.company_logo = e.target.files[0];
};

const parsedStaffEmails = computed(() => {
    const raw = form.order_notification_emails || '';
    if (!raw.trim()) return [];
    return raw
        .split(/[\r\n,;]+/)
        .map(e => e.trim().toLowerCase())
        .filter(e => /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(e));
});

const parsedStaffEmailsCount = computed(() => parsedStaffEmails.value.length);

const save = () => {
    form.post(route('admin.settings.update'), {
        forceFormData: true,
        preserveScroll: true,
        onSuccess: () => {
            //toast.success('Settings saved successfully!');
            form.company_logo = null;
        },
        onError: () => toast.error('Failed to save settings, please check the form.'),
    });
};

const storeFeatures = computed(() => ({
    shop_enabled:                  { label: 'Shop / Product Listing',   desc: 'Show the /shop page and product listings to visitors.' },
    order_tracking_enabled:        { label: 'Order Tracking',            desc: 'Allow customers to track orders by tracking number or ID.' },
    customer_registration_enabled: { label: 'Customer Registration',     desc: 'Allow new customers to create accounts.' },
    turnstile_enabled:             { label: 'Cloudflare Turnstile CAPTCHA', desc: 'Protect Login, Registration, and Forgot Password forms with Cloudflare CAPTCHA. Disable temporarily if poor network prevents loading.' },
    guest_checkout_enabled:        { label: 'Guest Checkout',            desc: 'Let customers checkout without creating an account.' },
    reviews_enabled:               { label: 'Product Reviews',           desc: 'Show and allow customer reviews on products.' },
    wishlist_enabled:              { label: 'Wishlist',                  desc: 'Let customers save products to a wishlist.' },
    show_cart_total_header:        { label: 'Show Cart Total in Header', desc: 'Display live total monetary worth of cart items in the storefront top bar and navigation bar.' },
    show_stock_level_default:      { label: 'Show Stock Level (Default)', desc: 'Pre-fills the stock level toggle ON when creating new products.' },
}));

// Reusable toggle row component logic
const toggleSwitch = `relative w-11 h-6 bg-gray-200 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-primary rounded-full peer dark:bg-gray-700 peer-checked:after:translate-x-full rtl:peer-checked:after:-translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:start-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all dark:border-gray-600 peer-checked:bg-primary`;
</script>

<template>
    <Head title="Store Settings" />
    <AdminLayout>
        <div class="max-w-4xl mx-auto space-y-6">
            <!-- Top Navigation Tabs -->
            <SettingsNavTabs />

            <div class="flex items-center justify-between mb-8">
                <div>
                    <h1 class="text-2xl font-bold text-gray-900 dark:text-white">Store Settings</h1>
                    <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">Control all store features, payment methods, taxes, and shipping.</p>
                </div>
                <button @click="save" :disabled="form.processing" class="bg-primary hover:bg-primary-dark text-white font-bold px-6 py-2.5 rounded-lg transition flex items-center gap-2 disabled:opacity-60 shadow-xs">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                    {{ form.processing ? 'Saving...' : 'Save Settings' }}
                </button>
            </div>

            <!-- POS Connection Quick Link Notice -->
            <div class="mb-8 p-4 theme-gradient-banner rounded-xl flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                <div class="flex items-center gap-3">
                    <div class="p-2.5 bg-primary text-white rounded-lg shrink-0 shadow-xs">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1"/></svg>
                    </div>
                    <div>
                        <h2 class="text-sm font-semibold text-gray-900 dark:text-white flex items-center gap-2">
                            POS Branch Connection & Pairing
                            <span v-if="pos_connection?.is_connected" class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-semibold bg-emerald-100 text-emerald-800 dark:bg-emerald-900/40 dark:text-emerald-300">● Connected</span>
                            <span v-else class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-semibold bg-amber-100 text-amber-800 dark:bg-amber-900/40 dark:text-amber-300">● Disconnected</span>
                        </h2>
                        <p class="text-xs text-gray-600 dark:text-gray-400 mt-0.5">
                            Branch pairing, credentials, and webhook notification settings have moved to a dedicated menu.
                        </p>
                    </div>
                </div>
                <Link
                    :href="route('admin.pos-connection.index')"
                    class="inline-flex items-center gap-1.5 px-4 py-2 text-xs font-semibold rounded-lg bg-primary hover:bg-primary-dark text-white transition shrink-0 shadow-xs"
                >
                    Manage POS Connection &rarr;
                </Link>
            </div>

            <form @submit.prevent="save" class="space-y-8">

                <!-- ─── Company Branding ───────────────────────────────────── -->
                <section class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 overflow-hidden">
                    <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-800/60">
                        <h2 class="text-lg font-semibold">🏢 Company Profile</h2>
                        <p class="text-sm text-gray-500 dark:text-gray-400">Settings here dynamically replace all branding across the site.</p>
                    </div>
                    <div class="p-6 grid grid-cols-1 gap-y-6 sm:grid-cols-2 sm:gap-x-6">
                        <!-- Left Column -->
                        <div class="space-y-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Company Name *</label>
                                <input type="text" v-model="form.company_name" required class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 px-3 py-2" />
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Tagline / Slogan</label>
                                <input type="text" v-model="form.company_tagline" class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 px-3 py-2" />
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Store Website / Live URL</label>
                                <input type="url" v-model="form.site_url" placeholder="https://easypick.ng" class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 px-3 py-2 text-sm" />
                                <p class="text-[11px] text-gray-500 dark:text-gray-400 mt-0.5">Used for logo links, tracking links, and customer action buttons in all outgoing emails so they never default to localhost.</p>
                            </div>
                            <div class="grid grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Support Email</label>
                                    <input type="email" v-model="form.company_email" class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 px-3 py-2 text-sm" />
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Support Phone</label>
                                    <input type="text" v-model="form.company_phone" class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 px-3 py-2 text-sm" />
                                </div>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Office Address</label>
                                <textarea v-model="form.company_address" rows="2" class="w-full rounded-lg border border-gray-300 dark:border-gray-600 dark:bg-gray-700 px-3 py-2"></textarea>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">SEO Meta Description</label>
                                <textarea v-model="form.meta_description" rows="2" placeholder="Brief summary of your store for Google search results..." class="w-full rounded-lg border border-gray-300 dark:border-gray-600 dark:bg-gray-700 px-3 py-2 text-sm"></textarea>
                                <p class="text-[11px] text-gray-500 dark:text-gray-400 mt-0.5">Recommended: 150-160 characters describing your store.</p>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">SEO Meta Keywords</label>
                                <input type="text" v-model="form.meta_keywords" placeholder="e.g. online store, electronics, gadgets, buy online" class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 px-3 py-2 text-sm" />
                                <p class="text-[11px] text-gray-500 dark:text-gray-400 mt-0.5">Comma-separated search keywords.</p>
                            </div>
                        </div>
                        
                        <!-- Right Column (Logo) -->
                        <div class="flex flex-col">
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Company Logo</label>
                            <div class="flex-1 border-2 border-dashed border-gray-300 dark:border-gray-600 rounded-lg bg-gray-50 dark:bg-gray-900/50 flex flex-col items-center justify-center p-6 text-center hover:bg-gray-100 dark:hover:bg-gray-800 transition">
                                <template v-if="props.settings.company_logo && !form.company_logo">
                                    <img :src="'/storage/' + props.settings.company_logo" class="h-20 object-contain mb-4" />
                                    <p class="text-xs text-green-600 font-semibold">Current Logo Active</p>
                                </template>
                                <template v-else>
                                    <svg class="w-10 h-10 text-gray-400 mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"/></svg>
                                    <p class="text-sm font-medium text-gray-900 dark:text-white mb-1">Upload a new logo</p>
                                    <p class="text-xs text-gray-500 mb-3">PNG, JPG up to 2MB</p>
                                </template>
                                <input type="file" @change="handleLogoUpload" accept="image/*" class="text-sm text-gray-500 file:mr-3 file:py-1 file:px-3 file:rounded-md file:border-0 file:text-xs file:font-semibold file:bg-gray-100 dark:file:bg-gray-700 file:text-primary cursor-pointer w-full max-w-[200px]" />
                            </div>
                        </div>
                    </div>
                </section>

                <!-- ─── Order Notifications & Staff Alerts ───────────────────────────── -->
                <section class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 overflow-hidden">
                    <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-800/60 flex items-center justify-between">
                        <div class="flex items-center gap-3">
                            <span class="w-9 h-9 rounded-lg bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 flex items-center justify-center shrink-0 text-lg">📬</span>
                            <div>
                                <h2 class="text-base font-semibold text-gray-900 dark:text-white">Order Email Notifications & Staff Alerts</h2>
                                <p class="text-xs text-gray-500 dark:text-gray-400">Specify which staff members receive an alert email whenever a customer places a new order.</p>
                            </div>
                        </div>
                        <label class="relative inline-flex items-center cursor-pointer shrink-0">
                            <input type="checkbox" v-model="form.order_notifications_enabled" class="sr-only peer">
                            <div :class="toggleSwitch"></div>
                            <span class="ml-2.5 text-xs font-bold text-gray-900 dark:text-gray-300">
                                {{ form.order_notifications_enabled ? 'Active' : 'Disabled' }}
                            </span>
                        </label>
                    </div>
                    <div v-if="form.order_notifications_enabled" class="p-6 space-y-4">
                        <div>
                            <div class="flex items-center justify-between mb-1.5">
                                <label class="block text-xs font-bold uppercase tracking-wider text-gray-700 dark:text-gray-300">
                                    Recipient Staff Emails
                                </label>
                                <span class="text-[11px] font-semibold text-primary">
                                    {{ parsedStaffEmailsCount }} recipient(s) recognized
                                </span>
                            </div>
                            <textarea
                                v-model="form.order_notification_emails"
                                rows="3"
                                class="w-full rounded-xl border border-gray-300 dark:border-gray-600 dark:bg-gray-700 px-3.5 py-2.5 text-sm font-mono placeholder:font-sans"
                                placeholder="manager@skynetdigitalhub.com.ng, warehouse@skynetdigitalhub.com.ng, packaging@skynetdigitalhub.com.ng"
                            ></textarea>
                            <p class="text-xs text-gray-500 dark:text-gray-400 mt-1.5 leading-relaxed">
                                Enter all staff emails separated by commas, semicolons, or new lines. If left empty, alerts will default to your Company Email (<code>{{ form.company_email || 'support@skynetdigitalhub.com.ng' }}</code>).
                            </p>
                        </div>

                        <div v-if="parsedStaffEmails.length" class="flex flex-wrap items-center gap-1.5 pt-1">
                            <span class="text-xs font-semibold text-gray-500 dark:text-gray-400 mr-1">Alerts will be delivered to:</span>
                            <span
                                v-for="email in parsedStaffEmails"
                                :key="email"
                                class="inline-flex items-center gap-1 px-2.5 py-1 rounded-lg text-xs font-medium bg-emerald-50 dark:bg-emerald-950/40 text-emerald-700 dark:text-emerald-300 border border-emerald-200 dark:border-emerald-800/60"
                            >
                                <span>✉️</span>
                                <span>{{ email }}</span>
                            </span>
                        </div>
                    </div>
                </section>

                <!-- ─── Referral & Affiliate Program Settings ────────────────────────── -->
                <section class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 overflow-hidden">
                    <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-800/60 flex items-center justify-between">
                        <div class="flex items-center gap-3">
                            <span class="w-9 h-9 rounded-lg bg-primary-soft text-primary flex items-center justify-center shrink-0 text-lg">🤝</span>
                            <div>
                                <h2 class="text-base font-semibold text-gray-900 dark:text-white">Referral & Affiliate Program</h2>
                                <p class="text-xs text-gray-500 dark:text-gray-400">Configure storewide default commission rates, holding periods, and customer application rules.</p>
                            </div>
                        </div>
                        <label class="relative inline-flex items-center cursor-pointer shrink-0">
                            <input type="checkbox" v-model="form.referral_enabled" class="sr-only peer">
                            <div :class="toggleSwitch"></div>
                            <span class="ml-2.5 text-xs font-bold text-gray-900 dark:text-gray-300">
                                {{ form.referral_enabled ? 'Active' : 'Disabled' }}
                            </span>
                        </label>
                    </div>

                    <div v-if="form.referral_enabled" class="p-6 space-y-6">
                        <!-- Quick Link to Affiliates Management -->
                        <div class="p-4 rounded-xl border border-primary-soft bg-primary-soft/30 flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                            <div class="text-xs text-slate-800 dark:text-slate-200">
                                <span class="font-bold text-primary">Manage Affiliates & Withdrawal Requests:</span>
                                View registered affiliates, approve applications, customize individual partner rates, and process bank payouts.
                            </div>
                            <div class="flex items-center gap-2 shrink-0">
                                <Link :href="route('admin.affiliates.index')" class="px-3 py-1.5 rounded-lg bg-primary hover:bg-primary-dark text-white text-xs font-bold transition shadow-xs">
                                    Affiliates List →
                                </Link>
                                <Link :href="route('admin.affiliates.withdrawals.index')" class="px-3 py-1.5 rounded-lg bg-white dark:bg-gray-700 border border-gray-300 dark:border-gray-600 text-gray-800 dark:text-gray-200 text-xs font-bold hover:bg-gray-50 transition">
                                    Withdrawal Queue →
                                </Link>
                            </div>
                        </div>

                        <!-- Customer Applications Toggle -->
                        <div class="flex items-center justify-between py-2 border-b dark:border-gray-700">
                            <div>
                                <h3 class="text-sm font-semibold text-gray-900 dark:text-white">Allow Customer Applications</h3>
                                <p class="text-xs text-gray-500">Customers can apply to become an affiliate from their account. Applications require your manual approval.</p>
                            </div>
                            <label class="relative inline-flex items-center cursor-pointer">
                                <input type="checkbox" v-model="form.referral_allow_applications" class="sr-only peer">
                                <div :class="toggleSwitch"></div>
                            </label>
                        </div>

                        <!-- Commission & Qualification Rules -->
                        <div class="space-y-3">
                            <h4 class="text-xs font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400 flex items-center gap-1.5">
                                <span>💰</span> Commission & Qualification Rules
                            </h4>
                            <div class="grid grid-cols-1 md:grid-cols-3 gap-5">
                                <div>
                                    <label class="block text-xs font-bold uppercase tracking-wider text-gray-700 dark:text-gray-300 mb-1.5">
                                        Commission Model
                                    </label>
                                    <select v-model="form.referral_commission_type" class="w-full rounded-xl border border-gray-300 dark:border-gray-600 dark:bg-gray-700 px-3.5 py-2.5 text-sm">
                                        <option value="percentage">Percentage of Sales (%)</option>
                                        <option value="flat">Flat Fixed Fee (₦ per Order)</option>
                                    </select>
                                    <span class="text-[11px] text-gray-400 mt-1 block">
                                        {{ form.referral_commission_type === 'percentage' ? 'Calculated as percentage on qualifying net subtotal.' : 'Fixed cash payout awarded per qualifying order.' }}
                                    </span>
                                </div>

                                <div>
                                    <label class="block text-xs font-bold uppercase tracking-wider text-gray-700 dark:text-gray-300 mb-1.5">
                                        Default Rate {{ form.referral_commission_type === 'percentage' ? '(%)' : '(₦)' }}
                                    </label>
                                    <input
                                        type="number"
                                        step="0.1"
                                        min="0"
                                        v-model="form.referral_commission_rate"
                                        class="w-full rounded-xl border border-gray-300 dark:border-gray-600 dark:bg-gray-700 px-3.5 py-2.5 text-sm"
                                        :placeholder="form.referral_commission_type === 'percentage' ? '5.0' : '1000'"
                                    />
                                    <span class="text-[11px] text-gray-400 mt-1 block">
                                        {{ form.referral_commission_type === 'percentage' ? 'Calculated on product subtotal (after discounts).' : 'Fixed amount credited per referred order.' }}
                                    </span>
                                </div>

                                <div>
                                    <label class="block text-xs font-bold uppercase tracking-wider text-gray-700 dark:text-gray-300 mb-1.5">
                                        Qualifying Min. Order (₦)
                                    </label>
                                    <input
                                        type="number"
                                        step="0.01"
                                        min="0"
                                        v-model="form.referral_min_order_amount"
                                        class="w-full rounded-xl border border-gray-300 dark:border-gray-600 dark:bg-gray-700 px-3.5 py-2.5 text-sm"
                                        placeholder="0"
                                    />
                                    <span class="text-[11px] text-gray-400 mt-1 block">
                                        Min. net subtotal required to earn commission. Set to 0 for any order amount.
                                    </span>
                                </div>
                            </div>
                        </div>

                        <!-- Holding & Payout Controls -->
                        <div class="space-y-3 pt-5 border-t border-gray-200 dark:border-gray-700/80">
                            <h4 class="text-xs font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400 flex items-center gap-1.5">
                                <span>🛡️</span> Holding & Payout Controls
                            </h4>
                            <div class="grid grid-cols-1 md:grid-cols-3 gap-5">
                                <div>
                                    <label class="block text-xs font-bold uppercase tracking-wider text-gray-700 dark:text-gray-300 mb-1.5">
                                        Holding Window (Days)
                                    </label>
                                    <input
                                        type="number"
                                        min="0"
                                        max="90"
                                        v-model="form.referral_holding_days"
                                        class="w-full rounded-xl border border-gray-300 dark:border-gray-600 dark:bg-gray-700 px-3.5 py-2.5 text-sm"
                                        placeholder="7"
                                    />
                                    <span class="text-[11px] text-gray-400 mt-1 block">
                                        Days after order completion before commission becomes withdrawable (protects against returns).
                                    </span>
                                </div>

                                <div>
                                    <label class="block text-xs font-bold uppercase tracking-wider text-gray-700 dark:text-gray-300 mb-1.5">
                                        Min. Payout Amount (₦)
                                    </label>
                                    <input
                                        type="number"
                                        min="0"
                                        v-model="form.referral_min_withdrawal"
                                        class="w-full rounded-xl border border-gray-300 dark:border-gray-600 dark:bg-gray-700 px-3.5 py-2.5 text-sm"
                                        placeholder="5000"
                                    />
                                    <span class="text-[11px] text-gray-400 mt-1 block">
                                        Minimum virtual wallet balance required to submit a withdrawal request.
                                    </span>
                                </div>

                                <div>
                                    <label class="block text-xs font-bold uppercase tracking-wider text-gray-700 dark:text-gray-300 mb-1.5">
                                        Cookie Attribution Window (Days)
                                    </label>
                                    <input
                                        type="number"
                                        min="1"
                                        max="365"
                                        v-model="form.referral_cookie_days"
                                        class="w-full rounded-xl border border-gray-300 dark:border-gray-600 dark:bg-gray-700 px-3.5 py-2.5 text-sm"
                                        placeholder="30"
                                    />
                                    <span class="text-[11px] text-gray-400 mt-1 block">
                                        How long a referral link click stays attached to a visitor's browser.
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>
                </section>

                <!-- ─── Homepage & Dedicated Settings Quick Links ─────────────────────────────── -->
                <section class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-6">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div class="p-4 rounded-xl border border-blue-200 dark:border-blue-900/60 bg-blue-50/50 dark:bg-blue-950/20 flex flex-col justify-between gap-3 shadow-xs">
                            <div class="flex items-start gap-3">
                                <span class="w-9 h-9 rounded-lg bg-blue-500/10 text-blue-600 dark:text-blue-400 flex items-center justify-center shrink-0 text-lg">🖼️</span>
                                <div>
                                    <h3 class="text-xs font-bold uppercase tracking-wider text-blue-900 dark:text-blue-200">
                                        Homepage & Sliders Settings
                                    </h3>
                                    <p class="text-xs text-blue-700/80 dark:text-blue-300/70 mt-1">
                                        Hero image slider gallery, marquee announcement bar, fallback hero text, and trust badges are now grouped together.
                                    </p>
                                </div>
                            </div>
                            <div class="pt-2">
                                <Link :href="route('admin.sliders.index')" class="inline-flex items-center gap-1.5 px-4 py-2 rounded-lg bg-blue-600 hover:bg-blue-700 text-white text-xs font-bold transition shadow-xs">
                                    Open Homepage & Sliders →
                                </Link>
                            </div>
                        </div>

                        <div class="p-4 rounded-xl border border-primary-soft bg-primary-soft/30 flex flex-col justify-between gap-3 shadow-xs">
                            <div class="flex items-start gap-3">
                                <span class="w-9 h-9 rounded-lg bg-primary-soft text-primary flex items-center justify-center shrink-0 text-lg">🎉</span>
                                <div>
                                    <h3 class="text-xs font-bold uppercase tracking-wider text-slate-900 dark:text-slate-100">
                                        Promotional Flyers & Coupon Popups
                                    </h3>
                                    <p class="text-xs text-slate-600 dark:text-slate-400 mt-1">
                                        Timed welcome coupon popups and mid-page promo banners have their own dedicated management menu.
                                    </p>
                                </div>
                            </div>
                            <div class="pt-2">
                                <Link :href="route('admin.promo-flyers.index')" class="inline-flex items-center gap-1.5 px-4 py-2 rounded-lg bg-primary hover:bg-primary-dark text-white text-xs font-bold transition shadow-xs">
                                    Open Promo Flyers & Popups →
                                </Link>
                            </div>
                        </div>
                    </div>
                </section>

                <!-- ─── Authentication Providers ───────────────────────────────────── -->
                <section class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 overflow-hidden">
                    <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-800/60">
                        <h2 class="text-lg font-semibold flex items-center gap-2">🔐 Authentication Providers</h2>
                        <p class="text-sm text-gray-500 dark:text-gray-400">Configure Google and Facebook OAuth for quick customer sign in.</p>
                    </div>
                    <div class="p-6 space-y-8">
                        <!-- Google Block -->
                        <div>
                            <div class="flex items-center justify-between mb-4">
                                <div>
                                    <h3 class="text-md font-bold text-gray-900 dark:text-white flex items-center gap-2">
                                        <svg class="w-5 h-5 text-red-500" viewBox="0 0 24 24" fill="currentColor"><path d="M21.35,11.1H12.18V13.83H18.69C18.36,17.64 15.19,19.27 12.19,19.27C8.36,19.27 5,16.25 5,12C5,7.9 8.2,4.73 12.2,4.73C15.29,4.73 17.1,6.7 17.1,6.7L19,4.72C19,4.72 16.56,2 12.1,2C6.42,2 2.03,6.8 2.03,12C2.03,17.05 6.16,22 12.25,22C17.6,22 21.5,18.33 21.5,12.91C21.5,11.76 21.35,11.1 21.35,11.1V11.1Z" /></svg>
                                        Google Login
                                    </h3>
                                    <p class="text-xs text-gray-500 mt-1">Callback URI: <code>{{ $page.props.app_url || 'https://yourdomain.com' }}/auth/google/callback</code></p>
                                </div>
                                <label class="relative inline-flex items-center cursor-pointer">
                                    <input type="checkbox" v-model="form.google_auth_enabled" class="sr-only peer">
                                    <div :class="toggleSwitch"></div>
                                </label>
                            </div>
                            <div v-if="form.google_auth_enabled" class="grid grid-cols-1 md:grid-cols-2 gap-4 bg-gray-50 border dark:border-gray-700 dark:bg-gray-900/40 p-4 rounded-lg">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Google Client ID</label>
                                    <input type="text" v-model="form.google_client_id" class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 px-3 py-2 text-sm" placeholder="123456789-xxxx...apps.googleusercontent.com" />
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Google Client Secret</label>
                                    <input type="password" v-model="form.google_client_secret" class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 px-3 py-2 text-sm" placeholder="GOCSPX-xxxx..." />
                                </div>
                            </div>
                        </div>

                        <!-- Facebook Block -->
                        <div class="border-t border-gray-100 dark:border-gray-700 pt-6 mt-6">
                            <div class="flex items-center justify-between mb-4">
                                <div>
                                    <h3 class="text-md font-bold text-gray-900 dark:text-white flex items-center gap-2">
                                        <svg class="w-5 h-5 text-blue-600" viewBox="0 0 24 24" fill="currentColor"><path d="M12 2.04C6.5 2.04 2 6.53 2 12.06C2 17.06 5.66 21.21 10.44 21.96V14.96H7.9V12.06H10.44V9.85C10.44 7.34 11.93 5.96 14.22 5.96C15.31 5.96 16.45 6.15 16.45 6.15V8.62H15.19C13.95 8.62 13.56 9.39 13.56 10.18V12.06H16.34L15.89 14.96H13.56V21.96A10 10 0 0 0 22 12.06C22 6.53 17.5 2.04 12 2.04Z"/></svg>
                                        Facebook Login
                                    </h3>
                                    <p class="text-xs text-gray-500 mt-1">Callback URI: <code>{{ $page.props.app_url || 'https://yourdomain.com' }}/auth/facebook/callback</code></p>
                                </div>
                                <label class="relative inline-flex items-center cursor-pointer">
                                    <input type="checkbox" v-model="form.facebook_auth_enabled" class="sr-only peer">
                                    <div :class="toggleSwitch"></div>
                                </label>
                            </div>
                            <div v-if="form.facebook_auth_enabled" class="grid grid-cols-1 md:grid-cols-2 gap-4 bg-gray-50 border dark:border-gray-700 dark:bg-gray-900/40 p-4 rounded-lg">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Facebook App ID</label>
                                    <input type="text" v-model="form.facebook_client_id" class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 px-3 py-2 text-sm" placeholder="123456789012345" />
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Facebook App Secret</label>
                                    <input type="password" v-model="form.facebook_client_secret" class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 px-3 py-2 text-sm" placeholder="xxxxxxxxxxxx" />
                                </div>
                            </div>
                        </div>
                    </div>
                </section>

                <!-- ─── AI Assistant Sub-menu Shortcut Banner ───────────────── -->
                <section class="bg-primary-soft/30 rounded-xl border border-primary-soft p-5 flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                    <div class="flex items-center gap-3">
                        <span class="text-3xl">🤖</span>
                        <div>
                            <div class="flex items-center gap-2">
                                <h3 class="text-base font-bold text-gray-900 dark:text-white">AI Assistant & Provider Settings</h3>
                                <span class="px-2 py-0.5 text-xs font-semibold rounded-full bg-primary-soft text-primary border border-primary-soft">Dedicated Sub-Menu</span>
                            </div>
                            <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">Manage Google Gemini, OpenAI ChatGPT, DeepSeek, and Claude API credentials, models, and latency connection tests.</p>
                        </div>
                    </div>
                    <Link
                        :href="route('admin.ai-settings.index')"
                        class="inline-flex items-center gap-1.5 px-4 py-2 text-xs font-semibold rounded-lg bg-primary hover:bg-primary-dark text-white transition shrink-0 shadow-sm"
                    >
                        <span>Configure AI Settings &rarr;</span>
                    </Link>
                </section>

                <!-- ─── Store Features ─────────────────────────────────────── -->
                <section class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 overflow-hidden">
                    <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-800/60">
                        <h2 class="text-lg font-semibold">🏪 Store Features</h2>
                        <p class="text-sm text-gray-500 dark:text-gray-400">Enable or disable major store sections and capabilities.</p>
                    </div>
                    <div class="p-6 space-y-4">

                        <!-- Feature toggle rows -->
                        <template v-for="(feature, key) in storeFeatures" :key="key">
                            <div class="flex items-center justify-between p-4 rounded-lg border border-gray-200 dark:border-gray-700">
                                <div>
                                    <p class="font-semibold text-gray-900 dark:text-white">{{ feature.label }}</p>
                                    <p class="text-sm text-gray-500 dark:text-gray-400">{{ feature.desc }}</p>
                                </div>
                                <label class="relative inline-flex items-center cursor-pointer">
                                    <input type="checkbox" v-model="form[key]" class="sr-only peer" />
                                    <div class="w-11 h-6 bg-gray-200 peer-focus:outline-none rounded-full peer dark:bg-gray-700 peer-checked:after:translate-x-full after:content-[''] after:absolute after:top-[2px] after:start-[2px] after:bg-white after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-primary"></div>
                                </label>
                            </div>
                        </template>

                        <!-- Tracking number prefix -->
                        <div class="flex items-center justify-between p-4 rounded-lg border border-gray-200 dark:border-gray-700">
                            <div>
                                <p class="font-semibold text-gray-900 dark:text-white">Tracking Number Prefix</p>
                                <p class="text-sm text-gray-500 dark:text-gray-400">Prefix added before tracking numbers (e.g. SKY- → SKY-AB12CD34).</p>
                            </div>
                            <input type="text" v-model="form.tracking_number_prefix" maxlength="10"
                                class="w-28 rounded-lg border border-gray-300 dark:border-gray-600 dark:bg-gray-700 px-3 py-2 text-center font-mono uppercase tracking-wider" />
                        </div>

                        <!-- Stock Display Mode & Low Stock Urgency Threshold -->
                        <div class="p-4 rounded-lg border border-gray-200 dark:border-gray-700 bg-gray-50/50 dark:bg-gray-800/40 space-y-4">
                            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                                <div>
                                    <p class="font-semibold text-gray-900 dark:text-white flex items-center gap-2">
                                        <span>📦</span> Front Store Stock Display Mode
                                    </p>
                                    <p class="text-sm text-gray-500 dark:text-gray-400">
                                        Choose how product stock quantities are displayed to storefront shoppers.
                                    </p>
                                </div>
                                <select v-model="form.stock_display_mode" class="rounded-lg border border-gray-300 dark:border-gray-600 dark:bg-gray-700 px-3 py-2 text-sm text-gray-900 dark:text-white font-medium focus:ring-primary focus:border-primary">
                                    <option value="threshold_only">Smart Urgency (Show "In Stock" if high, exact count if low)</option>
                                    <option value="always">Always Show Exact Count (e.g. "In Stock (58)")</option>
                                    <option value="hidden">Never Show Exact Count (Always show "In Stock")</option>
                                </select>
                            </div>

                            <div v-if="form.stock_display_mode === 'threshold_only'" class="flex items-center justify-between pt-3 border-t border-gray-200 dark:border-gray-700">
                                <div>
                                    <p class="text-sm font-medium text-gray-800 dark:text-gray-200">Low Stock Alert Threshold</p>
                                    <p class="text-xs text-gray-500 dark:text-gray-400">
                                        If stock is above this number, shows <strong>"In Stock"</strong>. If stock is at or below this number, shows <strong>"Only X left in stock"</strong>.
                                    </p>
                                </div>
                                <div class="flex items-center gap-2">
                                    <input type="number" min="1" max="100" v-model="form.low_stock_threshold"
                                        class="w-20 rounded-lg border border-gray-300 dark:border-gray-600 dark:bg-gray-700 px-3 py-1.5 text-center font-bold text-sm" />
                                    <span class="text-xs text-gray-500 dark:text-gray-400">units</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </section>

                <!-- ─── Payment Methods ─────────────────────────────────── -->
                <section class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 overflow-hidden">
                    <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-800/60">
                        <h2 class="text-lg font-semibold">💳 Payment Methods</h2>
                        <p class="text-sm text-gray-500 dark:text-gray-400">Enable or disable payment options shown at checkout.</p>
                    </div>
                    <div class="p-6 space-y-4">
                        <!-- Paystack -->
                        <div class="flex items-center justify-between p-4 rounded-lg border border-gray-200 dark:border-gray-700">
                            <div class="flex items-center gap-3">
                                <div class="w-10 h-10 rounded-lg bg-[#00C3F7]/10 flex items-center justify-center">
                                    <span class="font-extrabold text-[#00C3F7] text-xs">PS</span>
                                </div>
                                <div>
                                    <p class="font-semibold text-gray-900 dark:text-white">Paystack</p>
                                    <p class="text-sm text-gray-500 dark:text-gray-400">Cards, bank transfer, USSD</p>
                                </div>
                            </div>
                            <label class="relative inline-flex items-center cursor-pointer">
                                <input type="checkbox" v-model="form.paystack_enabled" class="sr-only peer" />
                                <div class="w-11 h-6 bg-gray-200 peer-focus:outline-none rounded-full peer dark:bg-gray-700 peer-checked:after:translate-x-full after:content-[''] after:absolute after:top-[2px] after:start-[2px] after:bg-white after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-primary"></div>
                            </label>
                        </div>
                        <!-- Flutterwave -->
                        <div class="flex items-center justify-between p-4 rounded-lg border border-gray-200 dark:border-gray-700">
                            <div class="flex items-center gap-3">
                                <div class="w-10 h-10 rounded-lg bg-[#F5A623]/10 flex items-center justify-center">
                                    <span class="font-extrabold text-[#F5A623] text-xs">FW</span>
                                </div>
                                <div>
                                    <p class="font-semibold text-gray-900 dark:text-white">Flutterwave</p>
                                    <p class="text-sm text-gray-500 dark:text-gray-400">Cards, mobile money, bank</p>
                                </div>
                            </div>
                            <label class="relative inline-flex items-center cursor-pointer">
                                <input type="checkbox" v-model="form.flutterwave_enabled" class="sr-only peer" />
                                <div class="w-11 h-6 bg-gray-200 peer-focus:outline-none rounded-full peer dark:bg-gray-700 peer-checked:after:translate-x-full after:content-[''] after:absolute after:top-[2px] after:start-[2px] after:bg-white after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-primary"></div>
                            </label>
                        </div>
                        <!-- POD (Payment on Delivery) -->
                        <div class="flex items-center justify-between p-4 rounded-lg border border-gray-200 dark:border-gray-700">
                            <div class="flex items-center gap-3">
                                <div class="w-10 h-10 rounded-lg bg-green-100 dark:bg-green-900/20 flex items-center justify-center">
                                    <svg class="w-5 h-5 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z"/></svg>
                                </div>
                                <div>
                                    <p class="font-semibold text-gray-900 dark:text-white">Payment on Delivery (POD)</p>
                                    <p class="text-sm text-gray-500 dark:text-gray-400">Customer pays with cash, bank transfer, or card when order is delivered</p>
                                </div>
                            </div>
                            <label class="relative inline-flex items-center cursor-pointer">
                                <input type="checkbox" v-model="form.cod_enabled" class="sr-only peer" />
                                <div class="w-11 h-6 bg-gray-200 peer-focus:outline-none rounded-full peer dark:bg-gray-700 peer-checked:after:translate-x-full after:content-[''] after:absolute after:top-[2px] after:start-[2px] after:bg-white after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-green-500"></div>
                            </label>
                        </div>
                        <!-- Bank Transfer -->
                        <div class="p-4 rounded-lg border border-gray-200 dark:border-gray-700">
                            <div class="flex items-center justify-between">
                                <div class="flex items-center gap-3">
                                    <div class="w-10 h-10 rounded-lg bg-blue-100 dark:bg-blue-900/20 flex items-center justify-center">
                                        <svg class="w-5 h-5 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 14v3m4-3v3m4-3v3M3 21h18M3 10h18M3 7l9-4 9 4M4 10h16v11H4V10z"/></svg>
                                    </div>
                                    <div>
                                        <p class="font-semibold text-gray-900 dark:text-white">Direct Bank Transfer</p>
                                        <p class="text-sm text-gray-500 dark:text-gray-400">Customer transfers payment directly to your bank account</p>
                                    </div>
                                </div>
                                <label class="relative inline-flex items-center cursor-pointer">
                                    <input type="checkbox" v-model="form.bank_transfer_enabled" class="sr-only peer" />
                                    <div class="w-11 h-6 bg-gray-200 peer-focus:outline-none rounded-full peer dark:bg-gray-700 peer-checked:after:translate-x-full after:content-[''] after:absolute after:top-[2px] after:start-[2px] after:bg-white after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-primary"></div>
                                </label>
                            </div>
                            <div v-if="form.bank_transfer_enabled" class="mt-4 pt-4 border-t border-gray-100 dark:border-gray-700 grid grid-cols-1 md:grid-cols-3 gap-4">
                                <div>
                                    <label class="block text-xs font-semibold text-gray-600 dark:text-gray-400 mb-1">Bank Name</label>
                                    <input type="text" v-model="form.bank_name" class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 px-3 py-2 text-sm" placeholder="e.g. Zenith Bank" />
                                </div>
                                <div>
                                    <label class="block text-xs font-semibold text-gray-600 dark:text-gray-400 mb-1">Account Number</label>
                                    <input type="text" v-model="form.bank_account_number" class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 px-3 py-2 text-sm" placeholder="e.g. 1012345678" />
                                </div>
                                <div>
                                    <label class="block text-xs font-semibold text-gray-600 dark:text-gray-400 mb-1">Account Name</label>
                                    <input type="text" v-model="form.bank_account_name" class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 px-3 py-2 text-sm" placeholder="e.g. Skynet Digital Ltd" />
                                </div>
                                <div class="md:col-span-3">
                                    <label class="block text-xs font-semibold text-gray-600 dark:text-gray-400 mb-1">Instructions for Customer</label>
                                    <input type="text" v-model="form.bank_instructions" class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 px-3 py-2 text-sm" placeholder="Please use your tracking number as transfer narration..." />
                                </div>
                            </div>
                        </div>
                    </div>
                </section>

                <!-- ─── Tax & Service Fees ──────────────────────────────── -->
                <section class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 overflow-hidden">
                    <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-800/60">
                        <h2 class="text-lg font-semibold">🧾 Tax & Service Fees</h2>
                        <p class="text-sm text-gray-500 dark:text-gray-400">These fees are automatically added during checkout.</p>
                    </div>
                    <div class="p-6 space-y-6">
                        <!-- VAT -->
                        <div class="flex flex-col sm:flex-row sm:items-center gap-4">
                            <div class="flex-1">
                                <label class="flex items-center gap-2 cursor-pointer">
                                    <input type="checkbox" v-model="form.tax_enabled" class="w-4 h-4 text-primary rounded" />
                                    <span class="font-semibold text-gray-900 dark:text-white">Enable VAT / Tax</span>
                                </label>
                                <p class="text-sm text-gray-500 dark:text-gray-400 ml-6">Automatically calculate and add tax to the order total.</p>
                            </div>
                            <div class="flex items-center gap-2">
                                <input type="number" v-model="form.tax_rate" :disabled="!form.tax_enabled" min="0" max="100" step="0.5" class="w-24 rounded-lg border border-gray-300 dark:border-gray-600 dark:bg-gray-700 px-3 py-2 text-center disabled:opacity-40" />
                                <span class="text-gray-600 dark:text-gray-300 font-bold">%</span>
                            </div>
                        </div>
                        <!-- Service Fee -->
                        <div class="flex flex-col sm:flex-row sm:items-center gap-4 border-t dark:border-gray-700 pt-6">
                            <div class="flex-1">
                                <label class="flex items-center gap-2 cursor-pointer">
                                    <input type="checkbox" v-model="form.service_fee_enabled" class="w-4 h-4 text-primary rounded" />
                                    <span class="font-semibold text-gray-900 dark:text-white">Enable Service Fee</span>
                                </label>
                                <p class="text-sm text-gray-500 dark:text-gray-400 ml-6">A flat fee applied to every order at checkout.</p>
                            </div>
                            <div class="flex items-center gap-2">
                                <span class="text-gray-600 dark:text-gray-300 font-bold">₦</span>
                                <input type="number" v-model="form.service_fee_amount" :disabled="!form.service_fee_enabled" min="0" class="w-32 rounded-lg border border-gray-300 dark:border-gray-600 dark:bg-gray-700 px-3 py-2 text-center disabled:opacity-40" />
                            </div>
                        </div>
                    </div>
                </section>

                <!-- ─── Shipping & Delivery ────────────────────────────── -->
                <section class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 overflow-hidden">
                    <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-800/60">
                        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2">
                            <div>
                                <h2 class="text-lg font-semibold">🚚 Shipping & Delivery</h2>
                                <p class="text-sm text-gray-500 dark:text-gray-400">Configure waybill charges and pickup options.</p>
                            </div>
                            <Link
                                :href="route('admin.shipping-zones.index')"
                                class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-semibold rounded-lg bg-primary/10 hover:bg-primary/20 text-primary transition shrink-0"
                            >
                                🗺️ State-by-State Shipping Rates &rarr;
                            </Link>
                        </div>
                    </div>
                    <div class="p-6 space-y-6">
                        <!-- Waybill -->
                        <div class="flex flex-col sm:flex-row sm:items-center gap-4">
                            <div class="flex-1">
                                <label class="flex items-center gap-2 cursor-pointer">
                                    <input type="checkbox" v-model="form.shipping_enabled" class="w-4 h-4 text-primary rounded" />
                                    <span class="font-semibold text-gray-900 dark:text-white">Enable Waybill / Delivery Fee</span>
                                </label>
                                <p class="text-sm text-gray-500 dark:text-gray-400 ml-6">Charge customers a shipping fee for home delivery.</p>
                            </div>
                            <div class="flex items-center gap-2">
                                <span class="text-gray-600 dark:text-gray-300 font-bold">₦</span>
                                <input type="number" v-model="form.waybill_fee" :disabled="!form.shipping_enabled" min="0" class="w-32 rounded-lg border border-gray-300 dark:border-gray-600 dark:bg-gray-700 px-3 py-2 text-center disabled:opacity-40" />
                            </div>
                        </div>
                        <!-- Free Shipping -->
                        <div class="flex flex-col sm:flex-row sm:items-center gap-4 border-t dark:border-gray-700 pt-4">
                            <div class="flex-1">
                                <p class="font-semibold text-gray-900 dark:text-white">Free Shipping Threshold</p>
                                <p class="text-sm text-gray-500 dark:text-gray-400">Orders above this amount get free shipping (set 0 to disable).</p>
                            </div>
                            <div class="flex items-center gap-2">
                                <span class="text-gray-600 dark:text-gray-300 font-bold">₦</span>
                                <input type="number" v-model="form.free_shipping_threshold" :disabled="!form.shipping_enabled" min="0" class="w-36 rounded-lg border border-gray-300 dark:border-gray-600 dark:bg-gray-700 px-3 py-2 text-center disabled:opacity-40" />
                            </div>
                        </div>

                        <!-- Default Delivery Town / Area Hint -->
                        <div class="border-t dark:border-gray-700 pt-4 space-y-2">
                            <div>
                                <p class="font-semibold text-gray-900 dark:text-white">Default Delivery Town / Area Hint</p>
                                <p class="text-sm text-gray-500 dark:text-gray-400">Pre-populates the customer's delivery address field at checkout (e.g. "Abraka, Delta State", "Benin City, Edo State", or "Ikeja, Lagos"). Customers can simply add their street name or leave it as-is.</p>
                            </div>
                            <input type="text" v-model="form.default_delivery_address_hint" placeholder="e.g. Abraka, Delta State" class="w-full rounded-lg border border-gray-300 dark:border-gray-600 dark:bg-gray-700 px-3 py-2 text-sm" />
                        </div>
                        <!-- Pickup -->
                        <div class="border-t dark:border-gray-700 pt-6 space-y-4">
                            <label class="flex items-center gap-2 cursor-pointer">
                                <input type="checkbox" v-model="form.pickup_enabled" class="w-4 h-4 text-primary rounded" />
                                <span class="font-semibold text-gray-900 dark:text-white">Enable Store Pickup</span>
                            </label>
                            <p class="text-sm text-gray-500 dark:text-gray-400 ml-6">Allow customers to pick up their order at your store for free.</p>
                            <div v-if="form.pickup_enabled" class="ml-6">
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Pickup Address</label>
                                <textarea v-model="form.pickup_address" rows="2" class="w-full rounded-lg border border-gray-300 dark:border-gray-600 dark:bg-gray-700 px-3 py-2" placeholder="e.g. 123 Digital Hub Street, Delta State"></textarea>
                            </div>
                        </div>
                    </div>
                </section>

                <!-- Save (bottom) -->
                <div class="flex justify-end">
                    <button type="submit" :disabled="form.processing" class="bg-primary hover:bg-primary-dark text-white font-bold px-8 py-3 rounded-lg transition disabled:opacity-60">
                        {{ form.processing ? 'Saving...' : 'Save All Settings' }}
                    </button>
                </div>
            </form>

            <!-- ─── Database Backups Sub-menu Shortcut Banner ──────────────── -->
            <section class="bg-primary-soft/30 rounded-xl border border-primary-soft p-5 flex flex-col sm:flex-row sm:items-center justify-between gap-4 mt-6">
                <div class="flex items-center gap-3">
                    <span class="text-3xl">💾</span>
                    <div>
                        <div class="flex items-center gap-2">
                            <h3 class="text-base font-bold text-gray-900 dark:text-white">Database Backups</h3>
                            <span class="px-2.5 py-0.5 text-xs font-bold rounded-full bg-emerald-100 text-emerald-800 dark:bg-emerald-950/40 dark:text-emerald-300 border border-emerald-200 dark:border-emerald-800">
                                Automated Daily at 02:00 AM
                            </span>
                        </div>
                        <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">
                            Create manual dumps, download compressed MySQL backups, manage storage retention, and view historical backups.
                        </p>
                    </div>
                </div>
                <Link
                    :href="route('admin.backups.index')"
                    class="inline-flex items-center gap-1.5 px-4 py-2 text-xs font-semibold rounded-lg bg-primary hover:bg-primary-dark text-white transition shrink-0 shadow-sm"
                >
                    <span>Manage Database Backups &rarr;</span>
                </Link>
            </section>
        </div>
    </AdminLayout>
</template>
