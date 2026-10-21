<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ref, computed } from 'vue';
import AdminLayout from '@/Layouts/AdminLayout.vue';
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
    company_email:          props.settings.company_email || 'support@skynetdigitalhub.com.ng',
    company_phone:          props.settings.company_phone || '+2348032072831',
    company_address:        props.settings.company_address || 'Delta State, Nigeria',
    company_logo:           null,
    // Homepage Hero & Slider
    home_slider_enabled:    props.settings.home_slider_enabled !== '0',
    home_marquee_enabled:   props.settings.home_marquee_enabled !== '0',
    home_marquee_text:      props.settings.home_marquee_text || 'E.E.W TECHNOLOGY LTD SHOP NOW',
    hero_enabled:           props.settings.hero_enabled !== '0',
    hero_badge:             props.settings.hero_badge || 'New Collection 2026',
    hero_title:             props.settings.hero_title || 'Discover Digital',
    hero_title_highlight:   props.settings.hero_title_highlight || 'Excellence',
    hero_subtitle:          props.settings.hero_subtitle || 'Shop premium products, authentic brands, and quality gear built for your lifestyle.',
    hero_cta_primary:       props.settings.hero_cta_primary || 'Shop Now',
    hero_cta_secondary:     props.settings.hero_cta_secondary || 'Explore Categories',
    hero_image:             null,
    // Homepage Feature Blocks
    feature_1_title:        props.settings.feature_1_title || 'Secure Payments',
    feature_1_desc:         props.settings.feature_1_desc || '100% secure checkout via Paystack & Flutterwave.',
    feature_2_title:        props.settings.feature_2_title || 'Fast Checkout',
    feature_2_desc:         props.settings.feature_2_desc || 'Seamless integration and instantaneous validation.',
    feature_3_title:        props.settings.feature_3_title || 'Premium Support',
    feature_3_desc:         props.settings.feature_3_desc || 'Priority technical response round the clock 24/7.',
    // Payments
    paystack_enabled:       props.settings.paystack_enabled === '1',
    flutterwave_enabled:    props.settings.flutterwave_enabled === '1',
    cod_enabled:            props.settings.cod_enabled === '1',
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
    // Store Features
    order_tracking_enabled:         props.settings.order_tracking_enabled === '1',
    customer_registration_enabled:  props.settings.customer_registration_enabled === '1',
    shop_enabled:                   props.settings.shop_enabled === '1',
    guest_checkout_enabled:         props.settings.guest_checkout_enabled === '1',
    reviews_enabled:                props.settings.reviews_enabled === '1',
    wishlist_enabled:               props.settings.wishlist_enabled === '1',
    tracking_number_prefix:         props.settings.tracking_number_prefix || 'SKY-',
    show_stock_level_default:       props.settings.show_stock_level_default !== '0',
    // Authentication Providers
    google_auth_enabled:            props.settings.google_auth_enabled === '1',
    google_client_id:               props.settings.google_client_id || '',
    google_client_secret:           props.settings.google_client_secret || '',
    facebook_auth_enabled:          props.settings.facebook_auth_enabled === '1',
    facebook_client_id:             props.settings.facebook_client_id || '',
    facebook_client_secret:         props.settings.facebook_client_secret || '',
});

const handleLogoUpload = (e) => {
    form.company_logo = e.target.files[0];
};

const handleHeroImageUpload = (e) => {
    form.hero_image = e.target.files[0];
};

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
    guest_checkout_enabled:        { label: 'Guest Checkout',            desc: 'Let customers checkout without creating an account.' },
    reviews_enabled:               { label: 'Product Reviews',           desc: 'Show and allow customer reviews on products.' },
    wishlist_enabled:              { label: 'Wishlist',                  desc: 'Let customers save products to a wishlist.' },
    show_stock_level_default:      { label: 'Show Stock Level (Default)', desc: 'Pre-fills the stock level toggle ON when creating new products.' },
}));

// Reusable toggle row component logic
const toggleSwitch = `relative w-11 h-6 bg-gray-200 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-blue-300 dark:peer-focus:ring-blue-800 rounded-full peer dark:bg-gray-700 peer-checked:after:translate-x-full rtl:peer-checked:after:-translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:start-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all dark:border-gray-600 peer-checked:bg-blue-600`;
</script>

<template>
    <Head title="Store Settings" />
    <AdminLayout>
        <div class="max-w-4xl mx-auto space-y-6">
            <!-- Top Navigation Tabs (Matching POS Super Admin Settings Style) -->
            <div class="flex items-center gap-2 overflow-x-auto border-b border-gray-200 dark:border-gray-700 pb-3">
                <Link
                    :href="route('admin.themes.index')"
                    class="px-4 py-2 rounded-lg text-xs font-semibold text-gray-600 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-800 transition flex items-center gap-2"
                >
                    <span>🎨</span> Branding & Color Themes
                </Link>
                <Link
                    :href="route('admin.settings.index')"
                    class="px-4 py-2 rounded-lg text-xs font-bold transition flex items-center gap-2 text-white shadow-xs"
                    :style="{ background: `linear-gradient(135deg, var(--color-primary, #7B00FF), var(--color-secondary-500, #FBA43D))` }"
                >
                    <span>⚙️</span> General Store Config
                </Link>
                <Link
                    :href="route('admin.pos-connection.index')"
                    class="px-4 py-2 rounded-lg text-xs font-semibold text-gray-600 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-800 transition flex items-center gap-2"
                >
                    <span>🔗</span> POS Connection & Pairing
                </Link>
                <Link
                    :href="route('admin.sliders.index')"
                    class="px-4 py-2 rounded-lg text-xs font-semibold text-gray-600 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-800 transition flex items-center gap-2"
                >
                    <span>🖼️</span> Sliders & Banners
                </Link>
            </div>

            <div class="flex items-center justify-between mb-8">
                <div>
                    <h1 class="text-2xl font-bold text-gray-900 dark:text-white">Store Settings</h1>
                    <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">Control all store features, payment methods, taxes, and shipping.</p>
                </div>
                <button @click="save" :disabled="form.processing" class="text-white hover:opacity-90 font-bold px-6 py-2.5 rounded-lg transition flex items-center gap-2 disabled:opacity-60 shadow-xs" :style="{ backgroundColor: 'var(--color-primary, #7B00FF)' }">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                    {{ form.processing ? 'Saving...' : 'Save Settings' }}
                </button>
            </div>

            <!-- POS Connection Quick Link Notice -->
            <div class="mb-8 p-4 bg-gradient-to-r from-blue-50 to-indigo-50 dark:from-gray-800 dark:to-gray-800/80 rounded-xl border border-blue-200 dark:border-blue-900/50 flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                <div class="flex items-center gap-3">
                    <div class="p-2.5 text-white rounded-lg shrink-0 shadow-xs" :style="{ backgroundColor: 'var(--color-primary, #7B00FF)' }">
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
                    class="inline-flex items-center gap-1.5 px-4 py-2 text-xs font-semibold rounded-lg text-white hover:opacity-90 transition shrink-0 shadow-xs"
                    :style="{ backgroundColor: 'var(--color-primary, #7B00FF)' }"
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
                                <input type="file" @change="handleLogoUpload" accept="image/*" class="text-sm text-gray-500 file:mr-3 file:py-1 file:px-3 file:rounded-md file:border-0 file:text-xs file:font-semibold file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100 cursor-pointer w-full max-w-[200px]" />
                            </div>
                        </div>
                    </div>
                </section>

                <!-- ─── Homepage Content ──────────────────────────────────── -->
                <section class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 overflow-hidden">
                    <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-800/60">
                        <h2 class="text-lg font-semibold">🏠 Homepage Content</h2>
                        <p class="text-sm text-gray-500 dark:text-gray-400">Edit the text and image displayed on the storefront homepage hero and feature blocks.</p>
                    </div>
                    <div class="p-6 space-y-6">

                        <!-- Slider & Marquee Setting -->
                        <div class="border border-indigo-100 dark:border-indigo-900/50 bg-indigo-50/30 dark:bg-indigo-900/10 rounded-xl p-5 mb-6 shadow-sm">
                            <h3 class="text-sm font-bold uppercase tracking-wider text-indigo-700 dark:text-indigo-400 mb-4 flex items-center gap-2">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                                Dynamic Hero Slider & Marquee
                            </h3>
                            <div class="flex flex-col md:flex-row gap-8">
                                <!-- Dynamic Slider Toggle -->
                                <div class="flex-1 space-y-4">
                                    <div class="flex items-center justify-between">
                                        <label class="text-sm font-semibold text-gray-900 dark:text-gray-200">Enable Slider Gallery</label>
                                        <label class="inline-flex items-center cursor-pointer">
                                            <input type="checkbox" v-model="form.home_slider_enabled" class="sr-only peer">
                                            <div :class="toggleSwitch"></div>
                                        </label>
                                    </div>
                                    <p class="text-xs text-gray-500">Replaces the static hero section with the slides managed in the <a href="/admin/sliders" class="text-indigo-600 font-bold hover:underline" target="_blank">Sliders menu</a>.</p>
                                </div>
                                <!-- Marquee Setting -->
                                <div class="flex-1 space-y-4 border-t md:border-t-0 md:border-l border-gray-200 dark:border-gray-700 pt-4 md:pt-0 md:pl-8">
                                    <div class="flex items-center justify-between">
                                        <label class="text-sm font-semibold text-gray-900 dark:text-gray-200">Enable Text Banner</label>
                                        <label class="inline-flex items-center cursor-pointer">
                                            <input type="checkbox" v-model="form.home_marquee_enabled" class="sr-only peer">
                                            <div :class="toggleSwitch"></div>
                                        </label>
                                    </div>
                                    <div v-if="form.home_marquee_enabled">
                                        <label class="block text-xs font-semibold text-gray-700 dark:text-gray-300 mb-1">Banner Text</label>
                                        <input type="text" v-model="form.home_marquee_text" class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-800 px-3 py-2 text-sm" placeholder="E.E.W TECHNOLOGY LTD SHOP NOW" />
                                    </div>
                                    <p class="text-xs text-gray-500">Displays a full-width text bar directly below the hero area.</p>
                                </div>
                            </div>
                        </div>

                        <!-- Hero Badge + Title -->
                        <div>
                            <div class="flex items-center justify-between mb-4">
                                <h3 class="text-sm font-bold uppercase tracking-wider text-blue-600 dark:text-blue-400">Hero Section</h3>
                                <label class="inline-flex items-center cursor-pointer">
                                    <input type="checkbox" v-model="form.hero_enabled" class="sr-only peer">
                                    <div :class="toggleSwitch"></div>
                                    <span class="ml-3 text-sm font-medium text-gray-900 dark:text-gray-300">{{ form.hero_enabled ? 'Enabled' : 'Disabled' }}</span>
                                </label>
                            </div>
                            <div v-if="form.hero_enabled" class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Badge Text <span class="text-xs text-gray-400">(e.g. Summer Collection 2026)</span></label>
                                    <input type="text" v-model="form.hero_badge" class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 px-3 py-2 text-sm" placeholder="Summer Collection 2026" />
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Highlighted Word <span class="text-xs text-gray-400">(rendered in gradient)</span></label>
                                    <input type="text" v-model="form.hero_title_highlight" class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 px-3 py-2 text-sm" placeholder="Excellence" />
                                </div>
                                <div v-if="form.hero_enabled" class="sm:col-span-2">
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Hero Heading <span class="text-xs text-gray-400">(line before the highlighted word)</span></label>
                                    <input type="text" v-model="form.hero_title" class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 px-3 py-2 text-sm" placeholder="Discover Digital" />
                                </div>
                                <div v-if="form.hero_enabled" class="sm:col-span-2">
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Hero Subtitle / Description</label>
                                    <textarea v-model="form.hero_subtitle" rows="2" class="w-full rounded-lg border border-gray-300 dark:border-gray-600 dark:bg-gray-700 px-3 py-2 text-sm" placeholder="Shop premium products..."></textarea>
                                </div>
                                <div v-if="form.hero_enabled">
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Primary CTA Button Text</label>
                                    <input type="text" v-model="form.hero_cta_primary" class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 px-3 py-2 text-sm" placeholder="Shop Now" />
                                </div>
                                <div v-if="form.hero_enabled">
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Secondary CTA Button Text</label>
                                    <input type="text" v-model="form.hero_cta_secondary" class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 px-3 py-2 text-sm" placeholder="Explore Categories" />
                                </div>
                            </div>
                        </div>

                        <!-- Hero Image Upload -->
                        <div v-if="form.hero_enabled" class="border-t dark:border-gray-700 pt-5">
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Hero Image</label>
                            <div class="flex items-start gap-6">
                                <div class="w-48 h-32 rounded-xl overflow-hidden border dark:border-gray-600 bg-gray-100 dark:bg-gray-900 flex-shrink-0">
                                    <img v-if="props.settings.hero_image" :src="'/storage/' + props.settings.hero_image" class="w-full h-full object-cover" alt="Current hero image" />
                                    <div v-else class="w-full h-full flex items-center justify-center text-gray-400 text-xs">No image set</div>
                                </div>
                                <div class="flex-1">
                                    <div class="border-2 border-dashed border-gray-300 dark:border-gray-600 rounded-lg p-4 text-center bg-gray-50 dark:bg-gray-900/50 hover:bg-gray-100 dark:hover:bg-gray-800 transition">
                                        <svg class="w-8 h-8 text-gray-400 mx-auto mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"/></svg>
                                        <p class="text-xs text-gray-500 mb-2">Upload a new hero image (PNG, JPG, max 4MB)</p>
                                        <input type="file" @change="handleHeroImageUpload" accept="image/*" class="text-sm text-gray-500 file:mr-2 file:py-1 file:px-3 file:rounded-md file:border-0 file:text-xs file:font-semibold file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100 cursor-pointer" />
                                    </div>
                                    <p v-if="form.hero_image" class="text-xs text-green-600 mt-2 font-semibold">✓ New image selected — will be applied on save.</p>
                                </div>
                            </div>
                        </div>

                        <!-- Feature Blocks -->
                        <div class="border-t dark:border-gray-700 pt-5">
                            <h3 class="text-sm font-bold uppercase tracking-wider text-blue-600 dark:text-blue-400 mb-4">Feature Blocks (bottom of homepage)</h3>
                            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                                <div v-for="n in 3" :key="n" class="bg-gray-50 dark:bg-gray-900/40 rounded-xl p-4 border dark:border-gray-700 space-y-3">
                                    <p class="text-xs font-bold text-gray-500 uppercase tracking-wider">Feature {{ n }}</p>
                                    <div>
                                        <label class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">Title</label>
                                        <input type="text" v-model="form[`feature_${n}_title`]" class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 px-3 py-2 text-sm" />
                                    </div>
                                    <div>
                                        <label class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">Description</label>
                                        <textarea v-model="form[`feature_${n}_desc`]" rows="2" class="w-full rounded-lg border border-gray-300 dark:border-gray-600 dark:bg-gray-700 px-3 py-2 text-sm"></textarea>
                                    </div>
                                </div>
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
                                <label class="inline-flex items-center cursor-pointer">
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
                                <label class="inline-flex items-center cursor-pointer">
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
                                    <div class="w-11 h-6 bg-gray-200 peer-focus:outline-none rounded-full peer dark:bg-gray-700 peer-checked:after:translate-x-full after:content-[''] after:absolute after:top-[2px] after:start-[2px] after:bg-white after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-blue-600"></div>
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
                                <div class="w-11 h-6 bg-gray-200 peer-focus:outline-none rounded-full peer dark:bg-gray-700 peer-checked:after:translate-x-full after:content-[''] after:absolute after:top-[2px] after:start-[2px] after:bg-white after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-blue-600"></div>
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
                                <div class="w-11 h-6 bg-gray-200 peer-focus:outline-none rounded-full peer dark:bg-gray-700 peer-checked:after:translate-x-full after:content-[''] after:absolute after:top-[2px] after:start-[2px] after:bg-white after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-blue-600"></div>
                            </label>
                        </div>
                        <!-- COD -->
                        <div class="flex items-center justify-between p-4 rounded-lg border border-gray-200 dark:border-gray-700">
                            <div class="flex items-center gap-3">
                                <div class="w-10 h-10 rounded-lg bg-green-100 dark:bg-green-900/20 flex items-center justify-center">
                                    <svg class="w-5 h-5 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z"/></svg>
                                </div>
                                <div>
                                    <p class="font-semibold text-gray-900 dark:text-white">Payment on Delivery (POD)</p>
                                    <p class="text-sm text-gray-500 dark:text-gray-400">Customer pays when order is delivered</p>
                                </div>
                            </div>
                            <label class="relative inline-flex items-center cursor-pointer">
                                <input type="checkbox" v-model="form.cod_enabled" class="sr-only peer" />
                                <div class="w-11 h-6 bg-gray-200 peer-focus:outline-none rounded-full peer dark:bg-gray-700 peer-checked:after:translate-x-full after:content-[''] after:absolute after:top-[2px] after:start-[2px] after:bg-white after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-green-500"></div>
                            </label>
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
                                    <input type="checkbox" v-model="form.tax_enabled" class="w-4 h-4 text-blue-600 rounded" />
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
                                    <input type="checkbox" v-model="form.service_fee_enabled" class="w-4 h-4 text-blue-600 rounded" />
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
                        <h2 class="text-lg font-semibold">🚚 Shipping & Delivery</h2>
                        <p class="text-sm text-gray-500 dark:text-gray-400">Configure waybill charges and pickup options.</p>
                    </div>
                    <div class="p-6 space-y-6">
                        <!-- Waybill -->
                        <div class="flex flex-col sm:flex-row sm:items-center gap-4">
                            <div class="flex-1">
                                <label class="flex items-center gap-2 cursor-pointer">
                                    <input type="checkbox" v-model="form.shipping_enabled" class="w-4 h-4 text-blue-600 rounded" />
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
                        <!-- Pickup -->
                        <div class="border-t dark:border-gray-700 pt-6 space-y-4">
                            <label class="flex items-center gap-2 cursor-pointer">
                                <input type="checkbox" v-model="form.pickup_enabled" class="w-4 h-4 text-blue-600 rounded" />
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
                    <button type="submit" :disabled="form.processing" class="bg-blue-600 hover:bg-blue-700 text-white font-bold px-8 py-3 rounded-lg transition disabled:opacity-60">
                        {{ form.processing ? 'Saving...' : 'Save All Settings' }}
                    </button>
                </div>
            </form>
        </div>
    </AdminLayout>
</template>
