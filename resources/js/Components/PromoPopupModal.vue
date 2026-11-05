<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue';
import { Link } from '@inertiajs/vue3';
import { useToast } from 'vue-toastification';

const props = defineProps({
    settings: {
        type: Object,
        default: () => ({}),
    },
});

const toast = useToast();
const showModal = ref(false);
const copied = ref(false);
let timer = null;

const isEnabled = computed(() => props.settings?.promo_popup_enabled === '1');
const flyerImage = computed(() => props.settings?.promo_popup_image ? `/storage/${props.settings.promo_popup_image}` : null);
const couponCode = computed(() => (props.settings?.promo_popup_coupon_code || '').trim());
const title = computed(() => props.settings?.promo_popup_title || '');
const subtitle = computed(() => props.settings?.promo_popup_subtitle || '');
const ctaText = computed(() => props.settings?.promo_popup_cta_text || 'Shop Now');
const ctaLink = computed(() => props.settings?.promo_popup_cta_link || '/shop');
const delaySeconds = computed(() => Math.max(0, parseInt(props.settings?.promo_popup_delay || 3)));
const frequency = computed(() => props.settings?.promo_popup_frequency || '24_hours');

// Unique campaign fingerprint: if admin changes flyer image, coupon code, or title, it's considered a new promo campaign!
const campaignKey = computed(() => {
    return [
        props.settings?.promo_popup_image || '',
        props.settings?.promo_popup_coupon_code || '',
        props.settings?.promo_popup_title || '',
    ].join('::');
});

const hasContent = computed(() => !!(flyerImage.value || couponCode.value || title.value));

const isDismissed = () => {
    if (typeof window === 'undefined') return true;

    // Refresh mode: always re-show on page load (developer/testing mode)
    if (frequency.value === 'refresh') {
        return false;
    }

    // Session mode: only hide while the current browser session / tab is active
    if (frequency.value === 'session') {
        try {
            const rawSession = sessionStorage.getItem('skynet_promo_popup_session');
            if (rawSession) {
                const parsed = JSON.parse(rawSession);
                if (parsed.campaignKey === campaignKey.value) {
                    return true;
                }
            }
        } catch (e) {}
        return false;
    }

    // Persistent storage modes (24_hours, 7_days, 30_days, once)
    try {
        const raw = localStorage.getItem('skynet_promo_popup_dismissed');
        if (!raw) return false;

        const parsed = JSON.parse(raw);
        // If admin published a new promo (different image, code, or title), reset dismissal so customers see it!
        if (parsed.campaignKey !== campaignKey.value) {
            return false;
        }

        // 'once' mode: permanently dismissed for this promo campaign
        if (frequency.value === 'once') {
            return true;
        }

        const elapsedMs = Date.now() - (parsed.dismissedAt || 0);

        if (frequency.value === '24_hours') {
            return elapsedMs < 24 * 60 * 60 * 1000;
        }

        if (frequency.value === '7_days') {
            return elapsedMs < 7 * 24 * 60 * 60 * 1000;
        }

        if (frequency.value === '30_days') {
            return elapsedMs < 30 * 24 * 60 * 60 * 1000;
        }
    } catch (e) {
        return false;
    }

    return false;
};

const closeModal = () => {
    if (typeof window !== 'undefined') {
        const payload = JSON.stringify({
            dismissedAt: Date.now(),
            campaignKey: campaignKey.value,
        });

        // Store in sessionStorage (handles 'session' mode)
        try {
            sessionStorage.setItem('skynet_promo_popup_session', payload);
        } catch (e) {}

        // Store in localStorage (handles 24_hours, 7_days, 30_days, once)
        try {
            localStorage.setItem('skynet_promo_popup_dismissed', payload);
        } catch (e) {}
    }
    showModal.value = false;
};

const copyCoupon = async () => {
    if (!couponCode.value) return;
    try {
        if (navigator?.clipboard?.writeText) {
            await navigator.clipboard.writeText(couponCode.value);
        } else {
            // Fallback for older browsers
            const textArea = document.createElement('textarea');
            textArea.value = couponCode.value;
            textArea.style.position = 'fixed';
            textArea.style.opacity = '0';
            document.body.appendChild(textArea);
            textArea.select();
            document.execCommand('copy');
            document.body.removeChild(textArea);
        }
        copied.value = true;
        toast.success(`Coupon code "${couponCode.value}" copied! Paste at checkout.`);
        setTimeout(() => {
            copied.value = false;
        }, 2500);
    } catch (e) {
        toast.info(`Coupon code: ${couponCode.value}`);
    }
};

const handleKeyDown = (e) => {
    if (e.key === 'Escape' && showModal.value) {
        closeModal();
    }
};

onMounted(() => {
    if (typeof window === 'undefined') return;
    window.addEventListener('keydown', handleKeyDown);

    // If dismissed under current frequency/cooldown rule, do not show
    if (isDismissed()) {
        return;
    }

    if (isEnabled.value && hasContent.value) {
        timer = setTimeout(() => {
            showModal.value = true;
        }, delaySeconds.value * 1000);
    }
});

onUnmounted(() => {
    if (timer) clearTimeout(timer);
    if (typeof window !== 'undefined') {
        window.removeEventListener('keydown', handleKeyDown);
    }
});
</script>

<template>
    <Teleport to="body">
        <Transition
            enter-active-class="transition duration-300 ease-out"
            enter-from-class="opacity-0 scale-95"
            enter-to-class="opacity-100 scale-100"
            leave-active-class="transition duration-200 ease-in"
            leave-from-class="opacity-100 scale-100"
            leave-to-class="opacity-0 scale-95"
        >
            <div
                v-if="showModal"
                class="fixed inset-0 z-50 overflow-y-auto flex items-center justify-center p-4 sm:p-6"
                role="dialog"
                aria-modal="true"
            >
                <!-- Backdrop -->
                <div
                    class="fixed inset-0 bg-black/60 backdrop-blur-xs transition-opacity"
                    @click="closeModal"
                ></div>

                <!-- Modal Dialog -->
                <div class="relative bg-white dark:bg-gray-800 rounded-3xl max-w-lg w-full overflow-hidden shadow-2xl border border-gray-100 dark:border-gray-700 z-10 flex flex-col max-h-[90vh]">
                    <!-- Close (X) Button -->
                    <button
                        @click="closeModal"
                        type="button"
                        class="absolute top-3.5 right-3.5 z-20 w-9 h-9 rounded-full bg-black/50 hover:bg-black/75 text-white flex items-center justify-center transition shadow-lg focus:outline-none"
                        title="Close (Esc)"
                    >
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M6 18L18 6M6 6l12 12"/>
                        </svg>
                    </button>

                    <!-- Scrollable Modal Content Container -->
                    <div class="overflow-y-auto flex-1 divide-y divide-gray-100 dark:divide-gray-700">
                        <!-- Flyer / Banner Image -->
                        <div v-if="flyerImage" class="relative w-full bg-gray-900/5 dark:bg-gray-900/40 flex items-center justify-center">
                            <Link :href="ctaLink" @click="closeModal" class="block w-full">
                                <img
                                    :src="flyerImage"
                                    :alt="title || 'Promotional Flyer'"
                                    loading="lazy"
                                    decoding="async"
                                    class="w-full max-h-[380px] sm:max-h-[440px] object-contain hover:opacity-95 transition"
                                />
                            </Link>
                        </div>

                        <!-- Text & Coupon Content -->
                        <div class="p-6 space-y-4 text-center">
                            <div v-if="title || subtitle">
                                <h3 v-if="title" class="text-xl sm:text-2xl font-black text-gray-900 dark:text-white tracking-tight">
                                    {{ title }}
                                </h3>
                                <p v-if="subtitle" class="text-sm text-gray-600 dark:text-gray-300 mt-1 leading-relaxed">
                                    {{ subtitle }}
                                </p>
                            </div>

                            <!-- Coupon Code Box with 1-Click Copy -->
                            <div v-if="couponCode" class="p-3.5 rounded-2xl border-2 border-dashed border-primary/50 bg-primary/5 dark:bg-primary/10 flex flex-col sm:flex-row items-center justify-between gap-3">
                                <div class="flex items-center gap-2.5">
                                    <span class="w-8 h-8 rounded-lg bg-primary/15 text-primary flex items-center justify-center shrink-0">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z"/>
                                        </svg>
                                    </span>
                                    <div class="text-left">
                                        <span class="text-[10px] uppercase font-bold text-gray-500 dark:text-gray-400 block tracking-wider">Discount Code</span>
                                        <span class="font-mono text-base font-black text-gray-900 dark:text-white tracking-wider">{{ couponCode }}</span>
                                    </div>
                                </div>
                                <button
                                    type="button"
                                    @click="copyCoupon"
                                    :class="copied ? 'bg-emerald-600 text-white' : 'bg-primary hover:bg-primary-dark text-white'"
                                    class="w-full sm:w-auto px-4 py-2 rounded-xl text-xs font-bold transition shadow-xs flex items-center justify-center gap-1.5 shrink-0"
                                >
                                    <template v-if="copied">
                                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/></svg>
                                        <span>Copied! ✓</span>
                                    </template>
                                    <template v-else>
                                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"/></svg>
                                        <span>Copy Code</span>
                                    </template>
                                </button>
                            </div>

                            <!-- Action Buttons -->
                            <div class="pt-2 flex flex-col gap-2">
                                <Link
                                    :href="ctaLink"
                                    @click="closeModal"
                                    class="w-full py-3.5 px-6 rounded-xl bg-primary hover:bg-primary-dark text-white font-bold text-sm tracking-wide shadow-md transition text-center flex items-center justify-center gap-2 group"
                                >
                                    <span>{{ ctaText }}</span>
                                    <svg class="w-4 h-4 transition-transform group-hover:translate-x-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3"/>
                                    </svg>
                                </Link>
                                <button
                                    type="button"
                                    @click="closeModal"
                                    class="text-xs text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200 transition py-1"
                                >
                                    No thanks, continue browsing
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </Transition>
    </Teleport>
</template>

