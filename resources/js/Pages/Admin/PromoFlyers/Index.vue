<script setup>
import { Head, useForm } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import SettingsNavTabs from '@/Components/Admin/SettingsNavTabs.vue';
import { useToast } from 'vue-toastification';

const props = defineProps({
    settings: Object,
});

const toast = useToast();

const form = useForm({
    // Promotional Mid-Page Banner
    promo_banner_enabled:       props.settings.promo_banner_enabled === '1',
    promo_banner_auto_optimize: props.settings.promo_banner_auto_optimize !== '0',
    promo_banner_title:         props.settings.promo_banner_title || '',
    promo_banner_subtitle:      props.settings.promo_banner_subtitle || '',
    promo_banner_link:          props.settings.promo_banner_link || '',
    promo_banner_button_text:   props.settings.promo_banner_button_text || 'Explore Deals',
    promo_banner_image:         null,

    // Timed Promo / Coupon Flyer Popup Modal
    promo_popup_enabled:        props.settings.promo_popup_enabled === '1',
    promo_popup_auto_optimize:  props.settings.promo_popup_auto_optimize !== '0',
    promo_popup_delay:          props.settings.promo_popup_delay || '3',
    promo_popup_frequency:      props.settings.promo_popup_frequency || '24_hours',
    promo_popup_title:          props.settings.promo_popup_title || '',
    promo_popup_subtitle:       props.settings.promo_popup_subtitle || '',
    promo_popup_coupon_code:    props.settings.promo_popup_coupon_code || '',
    promo_popup_cta_text:       props.settings.promo_popup_cta_text || 'Shop Now',
    promo_popup_cta_link:       props.settings.promo_popup_cta_link || '/shop',
    promo_popup_image:          null,
});

const handlePromoBannerUpload = (e) => {
    form.promo_banner_image = e.target.files[0];
};

const handlePromoPopupImageUpload = (e) => {
    form.promo_popup_image = e.target.files[0];
};

const save = () => {
    form.post(route('admin.promo-flyers.update'), {
        forceFormData: true,
        preserveScroll: true,
        onSuccess: () => {
            form.promo_banner_image = null;
            form.promo_popup_image = null;
        },
        onError: () => toast.error('Failed to save settings, please check the form.'),
    });
};

const toggleSwitch = `relative w-11 h-6 bg-gray-200 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-primary rounded-full peer dark:bg-gray-700 peer-checked:after:translate-x-full rtl:peer-checked:after:-translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:start-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all dark:border-gray-600 peer-checked:bg-primary`;
</script>

<template>
    <Head title="Promo Flyers & Popups" />
    <AdminLayout>
        <div class="max-w-4xl mx-auto space-y-6">
            <!-- Top Navigation Tabs -->
            <SettingsNavTabs />

            <!-- Page Title Bar -->
            <div class="flex items-center justify-between mb-8">
                <div>
                    <h1 class="text-2xl font-bold text-gray-900 dark:text-white flex items-center gap-2.5">
                        <span class="p-2 rounded-xl bg-primary-soft text-primary">🎉</span>
                        Promo Flyers & Popups
                    </h1>
                    <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">
                        Manage your homepage marketing banners, adverts, and welcome coupon popups.
                    </p>
                </div>
                <button
                    @click="save"
                    :disabled="form.processing"
                    class="bg-primary hover:bg-primary-dark text-white font-bold px-6 py-2.5 rounded-xl transition flex items-center gap-2 disabled:opacity-60 shadow-xs"
                >
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                    {{ form.processing ? 'Saving...' : 'Save All Settings' }}
                </button>
            </div>

            <form @submit.prevent="save" class="space-y-6">

                <!-- ─── 1. Promotional Mid-Page Banner ─────────────────────────────────── -->
                <section class="bg-white dark:bg-gray-800 rounded-2xl border border-gray-100 dark:border-gray-700 shadow-xs overflow-hidden">
                    <div class="px-6 py-4 border-b border-gray-100 dark:border-gray-700 bg-gray-50/50 dark:bg-gray-800/60 flex items-center justify-between">
                        <div class="flex items-center gap-3">
                            <span class="w-10 h-10 rounded-xl bg-blue-500/10 text-blue-600 dark:text-blue-400 flex items-center justify-center shrink-0">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5.882V19.24a1.76 1.76 0 01-3.417.592l-2.147-6.15M18 13a3 3 0 100-6M5.436 13.683A4.001 4.001 0 017 6h1.832c4.1 0 7.625-1.234 9.168-3v14c-1.543-1.766-5.067-3-9.168-3H7a3.988 3.988 0 01-1.564-.317z"/>
                                </svg>
                            </span>
                            <div>
                                <h2 class="text-base font-bold text-gray-900 dark:text-white">
                                    Mid-Page Promotional Flyer / Banner
                                </h2>
                                <p class="text-xs text-gray-500 dark:text-gray-400">
                                    Displays a full-width responsive promo banner between Best Sellers and Most Searched Items on the homepage.
                                </p>
                            </div>
                        </div>
                        <label class="relative inline-flex items-center cursor-pointer">
                            <input type="checkbox" v-model="form.promo_banner_enabled" class="sr-only peer">
                            <div :class="toggleSwitch"></div>
                            <span class="ml-3 text-xs sm:text-sm font-bold text-gray-900 dark:text-gray-300">
                                {{ form.promo_banner_enabled ? 'Active' : 'Disabled' }}
                            </span>
                        </label>
                    </div>

                    <div v-if="form.promo_banner_enabled" class="p-6 space-y-5">
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-xs font-semibold text-gray-700 dark:text-gray-300 mb-1">Promo Title (Optional)</label>
                                <input type="text" v-model="form.promo_banner_title" class="w-full rounded-xl border border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-900 px-3.5 py-2.5 text-sm" placeholder="e.g. Mega Clearance Sale — Up to 50% Off" />
                            </div>
                            <div>
                                <label class="block text-xs font-semibold text-gray-700 dark:text-gray-300 mb-1">Button Text</label>
                                <input type="text" v-model="form.promo_banner_button_text" class="w-full rounded-xl border border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-900 px-3.5 py-2.5 text-sm" placeholder="e.g. Shop the Sale" />
                            </div>
                            <div class="sm:col-span-2">
                                <label class="block text-xs font-semibold text-gray-700 dark:text-gray-300 mb-1">Target Link / URL</label>
                                <input type="text" v-model="form.promo_banner_link" class="w-full rounded-xl border border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-900 px-3.5 py-2.5 text-sm font-mono" placeholder="e.g. /shop or /shop?category=electronics" />
                            </div>
                            <div class="sm:col-span-2">
                                <label class="block text-xs font-semibold text-gray-700 dark:text-gray-300 mb-1">Subtitle / Description (Optional)</label>
                                <textarea v-model="form.promo_banner_subtitle" rows="2" class="w-full rounded-xl border border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-900 px-3.5 py-2.5 text-sm" placeholder="Short description or terms for this promotion..."></textarea>
                            </div>
                        </div>

                        <!-- Flyer Image Upload -->
                        <div class="pt-4 border-t border-gray-100 dark:border-gray-700">
                            <label class="block text-xs font-bold text-gray-700 dark:text-gray-300 mb-2">Flyer / Banner Image (Recommended: ~1200x400 or 16:9 ratio, max 6MB)</label>
                            <div class="flex flex-col sm:flex-row items-start gap-4">
                                <div class="w-full sm:w-64 h-32 rounded-xl overflow-hidden border dark:border-gray-700 bg-gray-100 dark:bg-gray-900 flex-shrink-0">
                                    <img v-if="props.settings.promo_banner_image" :src="'/storage/' + props.settings.promo_banner_image" class="w-full h-full object-cover" alt="Active mid-page banner" />
                                    <div v-else class="w-full h-full flex items-center justify-center text-gray-400 text-xs">No banner uploaded</div>
                                </div>
                                <div class="flex-1 w-full">
                                    <div class="border-2 border-dashed border-gray-300 dark:border-gray-600 rounded-xl p-4 text-center bg-gray-50 dark:bg-gray-900/50 hover:bg-gray-100 dark:hover:bg-gray-800 transition">
                                        <svg class="w-7 h-7 text-gray-400 mx-auto mb-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"/></svg>
                                        <p class="text-xs text-gray-500 mb-2">Upload banner image (PNG, JPG, WebP up to 12MB)</p>
                                        <input type="file" @change="handlePromoBannerUpload" accept="image/*" class="text-xs text-gray-500 file:mr-2 file:py-1 file:px-3 file:rounded-md file:border-0 file:text-xs file:font-semibold file:bg-primary/10 file:text-primary cursor-pointer" />
                                    </div>
                                    <p v-if="form.promo_banner_image" class="text-xs text-green-600 mt-2 font-semibold">✓ New banner selected — click Save All Settings to apply.</p>

                                    <!-- Auto-Optimize Banner Toggle -->
                                    <div class="mt-3 flex items-center justify-between p-3 rounded-xl bg-gray-50 dark:bg-gray-900/40 border border-gray-200/80 dark:border-gray-700">
                                        <div class="flex items-center gap-2.5">
                                            <span class="text-base">⚡</span>
                                            <div>
                                                <span class="text-xs font-bold text-gray-900 dark:text-white block">Auto-Optimize & Compress Banner</span>
                                                <span class="text-[11px] text-gray-500 dark:text-gray-400 block">Converts large uploads to high-speed WebP (~150KB). Turn off to keep raw file.</span>
                                            </div>
                                        </div>
                                        <label class="relative inline-flex items-center cursor-pointer shrink-0 ml-3">
                                            <input type="checkbox" v-model="form.promo_banner_auto_optimize" class="sr-only peer">
                                            <div :class="toggleSwitch"></div>
                                        </label>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </section>

                <!-- ─── 2. Timed Promo / Coupon Flyer Popup Modal ──────────────────────── -->
                <section class="bg-white dark:bg-gray-800 rounded-2xl border border-gray-100 dark:border-gray-700 shadow-xs overflow-hidden">
                    <div class="px-6 py-4 border-b border-gray-100 dark:border-gray-700 bg-gray-50/50 dark:bg-gray-800/60 flex items-center justify-between">
                        <div class="flex items-center gap-3">
                            <span class="w-10 h-10 rounded-xl bg-primary-soft text-primary flex items-center justify-center shrink-0">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 5v2m0 4v2m0 4v2M5 5a2 2 0 00-2 2v3a2 2 0 110 4v3a2 2 0 002 2h14a2 2 0 002-2v-3a2 2 0 110-4V7a2 2 0 00-2-2H5z"/>
                                </svg>
                            </span>
                            <div>
                                <h2 class="text-base font-bold text-gray-900 dark:text-white">
                                    Timed Promo / Coupon Flyer Popup Modal
                                </h2>
                                <p class="text-xs text-gray-500 dark:text-gray-400">
                                    Pops up automatically after a configurable number of seconds on the homepage with an optional coupon code copy button.
                                </p>
                            </div>
                        </div>
                        <label class="relative inline-flex items-center cursor-pointer">
                            <input type="checkbox" v-model="form.promo_popup_enabled" class="sr-only peer">
                            <div :class="toggleSwitch"></div>
                            <span class="ml-3 text-xs sm:text-sm font-bold text-gray-900 dark:text-gray-300">
                                {{ form.promo_popup_enabled ? 'Active' : 'Disabled' }}
                            </span>
                        </label>
                    </div>

                    <div v-if="form.promo_popup_enabled" class="p-6 space-y-5">
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-xs font-semibold text-gray-700 dark:text-gray-300 mb-1">Popup Delay (Seconds) *</label>
                                <div class="flex items-center gap-2">
                                    <input type="number" min="0" max="60" v-model="form.promo_popup_delay" class="w-24 rounded-xl border border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-900 px-3 py-2 text-sm font-bold text-center" />
                                    <span class="text-xs text-gray-500 dark:text-gray-400">seconds after page loads</span>
                                </div>
                            </div>
                            <div>
                                <label class="block text-xs font-semibold text-gray-700 dark:text-gray-300 mb-1">Reappearance Frequency / Cooldown *</label>
                                <select v-model="form.promo_popup_frequency" class="w-full rounded-xl border border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-900 px-3.5 py-2 text-sm font-semibold">
                                    <option value="24_hours">Once every 24 Hours (Recommended)</option>
                                    <option value="7_days">Once every 7 Days</option>
                                    <option value="30_days">Once every 30 Days</option>
                                    <option value="once">Only Once (Permanent)</option>
                                    <option value="session">Once per Browser Session</option>
                                    <option value="refresh">Every Page Refresh (Testing)</option>
                                </select>
                                <p class="text-[11px] text-gray-400 mt-1">Controls how long the popup stays hidden once closed by the visitor.</p>
                            </div>
                            <div>
                                <label class="block text-xs font-semibold text-gray-700 dark:text-gray-300 mb-1">Coupon / Promo Code (Optional)</label>
                                <input type="text" v-model="form.promo_popup_coupon_code" class="w-full rounded-xl border border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-900 px-3.5 py-2 text-sm font-mono uppercase tracking-wider font-bold" placeholder="e.g. WELCOME10 or FLASH20" />
                                <p class="text-[11px] text-gray-400 mt-1">If set, visitors get an interactive 1-click "Copy Code" button in the popup.</p>
                            </div>
                            <div>
                                <label class="block text-xs font-semibold text-gray-700 dark:text-gray-300 mb-1">Headline / Offer Title (Optional)</label>
                                <input type="text" v-model="form.promo_popup_title" class="w-full rounded-xl border border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-900 px-3.5 py-2 text-sm" placeholder="e.g. Special Welcome Offer!" />
                            </div>
                            <div>
                                <label class="block text-xs font-semibold text-gray-700 dark:text-gray-300 mb-1">CTA Button Text</label>
                                <input type="text" v-model="form.promo_popup_cta_text" class="w-full rounded-xl border border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-900 px-3.5 py-2 text-sm" placeholder="e.g. Shop Now" />
                            </div>
                            <div class="sm:col-span-2">
                                <label class="block text-xs font-semibold text-gray-700 dark:text-gray-300 mb-1">Target Link (Where clicking the button or flyer goes)</label>
                                <input type="text" v-model="form.promo_popup_cta_link" class="w-full rounded-xl border border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-900 px-3.5 py-2 text-sm font-mono" placeholder="e.g. /shop or /shop?category=deals" />
                            </div>
                            <div class="sm:col-span-2">
                                <label class="block text-xs font-semibold text-gray-700 dark:text-gray-300 mb-1">Short Description / Subtitle (Optional)</label>
                                <textarea v-model="form.promo_popup_subtitle" rows="2" class="w-full rounded-xl border border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-900 px-3.5 py-2 text-sm" placeholder="e.g. Apply this code at checkout to enjoy 10% off your entire cart!"></textarea>
                            </div>
                        </div>

                        <!-- Flyer Image Upload -->
                        <div class="pt-4 border-t border-gray-100 dark:border-gray-700">
                            <label class="block text-xs font-bold text-gray-700 dark:text-gray-300 mb-2">Flyer / Advert Image (Recommended: Portrait or square flyer, up to 6MB)</label>
                            <div class="flex flex-col sm:flex-row items-start gap-4">
                                <div class="w-full sm:w-48 h-48 rounded-xl overflow-hidden border dark:border-gray-700 bg-gray-100 dark:bg-gray-900 flex-shrink-0 flex items-center justify-center">
                                    <img v-if="props.settings.promo_popup_image" :src="'/storage/' + props.settings.promo_popup_image" class="w-full h-full object-contain bg-black/5" alt="Active popup flyer" />
                                    <div v-else class="text-gray-400 text-xs text-center p-3">No flyer uploaded</div>
                                </div>
                                <div class="flex-1 w-full">
                                    <div class="border-2 border-dashed border-gray-300 dark:border-gray-600 rounded-xl p-4 text-center bg-gray-50 dark:bg-gray-900/50 hover:bg-gray-100 dark:hover:bg-gray-800 transition">
                                        <svg class="w-7 h-7 text-gray-400 mx-auto mb-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"/></svg>
                                        <p class="text-xs text-gray-500 mb-2">Upload popup flyer image (PNG, JPG, WebP up to 12MB)</p>
                                        <input type="file" @change="handlePromoPopupImageUpload" accept="image/*" class="text-xs text-gray-500 file:mr-2 file:py-1 file:px-3 file:rounded-md file:border-0 file:text-xs file:font-semibold file:bg-primary-soft file:text-primary cursor-pointer" />
                                    </div>
                                    <p v-if="form.promo_popup_image" class="text-xs text-green-600 mt-2 font-semibold">✓ New flyer selected — click Save All Settings below to apply.</p>

                                    <!-- Auto-Optimize Popup Flyer Toggle -->
                                    <div class="mt-3 flex items-center justify-between p-3 rounded-xl bg-primary-soft/30 border border-primary-soft">
                                        <div class="flex items-center gap-2.5">
                                            <span class="text-base">⚡</span>
                                            <div>
                                                <span class="text-xs font-bold text-gray-900 dark:text-white block">Auto-Optimize & Compress Flyer Image (Recommended)</span>
                                                <span class="text-[11px] text-gray-500 dark:text-gray-400 block">Converts large flyers (up to 12MB) to ultra-fast, high-quality WebP (~150KB) so the modal opens instantly. Turn off to keep the exact original raw file.</span>
                                            </div>
                                        </div>
                                        <label class="relative inline-flex items-center cursor-pointer shrink-0 ml-3">
                                            <input type="checkbox" v-model="form.promo_popup_auto_optimize" class="sr-only peer">
                                            <div :class="toggleSwitch"></div>
                                        </label>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </section>

                <!-- Bottom Save Button -->
                <div class="flex justify-end pt-4">
                    <button
                        type="submit"
                        :disabled="form.processing"
                        class="bg-primary hover:bg-primary-dark text-white font-bold px-8 py-3 rounded-xl transition disabled:opacity-60 shadow-xs"
                    >
                        {{ form.processing ? 'Saving...' : 'Save All Settings' }}
                    </button>
                </div>
            </form>
        </div>
    </AdminLayout>
</template>

