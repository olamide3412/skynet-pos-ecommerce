<script setup>
import Logo from '../../images/logo.png';
import { Link } from '@inertiajs/vue3';
import { ref } from 'vue';

// ─── Newsletter State ──────────────────────────────────────────
const newsletterEmail = ref('');
const newsletterSubmitted = ref(false);
const newsletterLoading = ref(false);
const newsletterMessage = ref('');
const newsletterError = ref('');

const handleNewsletterSubmit = async () => {
    if (!newsletterEmail.value || !newsletterEmail.value.includes('@')) return;
    newsletterLoading.value = true;
    newsletterError.value = '';
    try {
        const response = await window.axios.post(route('newsletter.subscribe'), {
            email: newsletterEmail.value,
            source: 'footer'
        });
        newsletterSubmitted.value = true;
        newsletterMessage.value = response.data?.message || 'Thank you for subscribing!';
        newsletterEmail.value = '';
    } catch (err) {
        newsletterError.value = err.response?.data?.message || 'Unable to subscribe right now. Please try again.';
    } finally {
        newsletterLoading.value = false;
    }
};

// ─── Back to Top ───────────────────────────────────────────────
const scrollToTop = () => {
    window.scrollTo({
        top: 0,
        behavior: 'smooth'
    });
};

// ─── Information Modals ─────────────────────────────────────────
const activeModal = ref(null);

const openModal = (type) => {
    activeModal.value = type;
};

const closeModal = () => {
    activeModal.value = null;
};
</script>

<template>
    <footer class="relative bg-[#18181b] text-gray-300 border-t border-gray-800 transition-colors">
        <div class="container mx-auto px-4 sm:px-6 lg:px-8 py-10 sm:py-14">
            <!-- ─── Top Section: Brand + Newsletter / Mobile App + Contact ─────────────────── -->
            <div class="grid grid-cols-1 lg:grid-cols-12 gap-8 lg:gap-10 items-start">
                
                <!-- Left: Logo & Welcome Message -->
                <div class="lg:col-span-3 text-center lg:text-left flex flex-col items-center lg:items-start">
                    <Link :href="route('home')" class="inline-block mb-3">
                        <img
                            :src="$page.props.store_settings?.company_logo ? '/storage/' + $page.props.store_settings.company_logo : Logo"
                            :alt="$page.props.store_settings?.company_name || 'Store Logo'"
                            class="h-10 sm:h-12 w-auto object-contain"
                        />
                    </Link>
                    <p class="text-xs sm:text-sm text-gray-400 leading-relaxed max-w-sm">
                        Welcome to {{ $page.props.store_settings?.company_name || 'Skynet' }}. Browse our products and shop online with ease.
                    </p>
                </div>

                <!-- Middle: Coming Soon App Badges + Newsletter (Centered) -->
                <div class="lg:col-span-6 text-center flex flex-col items-center">
                    
                    <!-- Coming Soon App Badges -->
                    <div class="flex flex-wrap items-center justify-center gap-2.5 sm:gap-3 mb-4">
                        <!-- Google Play Badge -->
                        <div class="inline-flex items-center gap-2 px-3 py-1.5 rounded-lg bg-black/90 border border-white/20 text-left shadow-sm select-none hover:border-white/40 transition">
                            <svg class="w-5 h-5 shrink-0" viewBox="0 0 24 24">
                                <path fill="#4285F4" d="M3.6 1.4L13.8 12 3.6 22.6c-.4-.3-.6-.8-.6-1.5V2.9c0-.7.2-1.2.6-1.5z"/>
                                <path fill="#FBBC05" d="M17.3 8.6L14.7 11.2 13.8 12l.9.8 2.6 2.6 3.6-2.1c1-.6 1-1.6 0-2.2l-3.6-2.5z"/>
                                <path fill="#34A853" d="M3.6 22.6l11.1-11.4 2.6 2.6-12.7 7.3c-.4.3-.8.2-1-.5z"/>
                                <path fill="#EA4335" d="M3.6 1.4c.2-.7.6-.8 1-.5l12.7 7.3-2.6 2.6-11.1-11.4z"/>
                            </svg>
                            <div class="flex flex-col leading-none">
                                <span class="text-[8px] uppercase tracking-wider text-gray-400 font-semibold mb-0.5">COMING SOON TO</span>
                                <span class="text-xs font-bold text-white tracking-tight">Google Play</span>
                            </div>
                        </div>

                        <!-- App Store Badge -->
                        <div class="inline-flex items-center gap-2 px-3 py-1.5 rounded-lg bg-black/90 border border-white/20 text-left shadow-sm select-none hover:border-white/40 transition">
                            <svg class="w-5 h-5 shrink-0 text-white fill-current" viewBox="0 0 24 24">
                                <path d="M18.71 19.5c-.83 1.24-1.71 2.45-3.05 2.47-1.34.03-1.77-.79-3.29-.79-1.53 0-2 .77-3.27.82-1.31.05-2.3-1.32-3.14-2.53C4.25 17 2.94 12.45 4.7 9.39c.87-1.52 2.43-2.48 4.12-2.51 1.28-.02 2.5.87 3.29.87.78 0 2.26-1.07 3.81-.91.65.03 2.47.26 3.64 1.98-.09.06-2.17 1.28-2.15 3.81.03 3.02 2.65 4.03 2.68 4.04-.03.07-.42 1.44-1.38 2.83M15.97 6.38c.62-.75 1.04-1.8 0.93-2.85-.9.04-2 .6-2.65 1.35-.58.66-1.09 1.73-.95 2.76.99.08 2.03-.51 2.67-1.26z"/>
                            </svg>
                            <div class="flex flex-col leading-none">
                                <span class="text-[8px] uppercase tracking-wider text-gray-400 font-semibold mb-0.5">COMING SOON TO THE</span>
                                <span class="text-xs font-bold text-white tracking-tight">App Store</span>
                            </div>
                        </div>
                    </div>

                    <!-- STAY UPDATED Pill -->
                    <span class="inline-block px-3 py-0.5 rounded-full text-[10px] font-black tracking-widest uppercase border border-primary/50 text-primary bg-primary/10 mb-2">
                        STAY UPDATED
                    </span>

                    <!-- Newsletter Heading & Subtitle -->
                    <h3 class="text-base sm:text-lg md:text-xl font-extrabold text-white mb-1 tracking-tight">
                        Sign up for our newsletter
                    </h3>
                    <p class="text-xs text-gray-400 mb-4 max-w-sm">
                        Get new products, offers & promotions in your inbox.
                    </p>

                    <!-- Newsletter Subscription Form -->
                    <form @submit.prevent="handleNewsletterSubmit" class="max-w-md w-full">
                        <div class="bg-white rounded-full p-1 pl-4 sm:pl-5 flex items-center shadow-lg border border-gray-200 focus-within:ring-2 focus-within:ring-primary/40 transition">
                            <input
                                v-model="newsletterEmail"
                                type="email"
                                placeholder="Enter your email address"
                                required
                                class="w-full bg-transparent text-gray-900 placeholder-gray-400 text-xs sm:text-sm border-0 border-none outline-none ring-0 focus:ring-0 focus:outline-none focus:border-0 shadow-none pr-2 py-1.5"
                                style="border: none !important; outline: none !important; box-shadow: none !important;"
                            />
                            <button
                                type="submit"
                                :disabled="newsletterLoading"
                                class="bg-primary hover:opacity-90 disabled:opacity-70 text-white font-bold px-5 sm:px-6 py-2 sm:py-2.5 rounded-full text-xs sm:text-sm flex items-center gap-1.5 transition shadow-sm shrink-0 cursor-pointer"
                            >
                                <span>{{ newsletterSubmitted ? 'Subscribed!' : 'Subscribe' }}</span>
                                <svg v-if="!newsletterSubmitted" class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 5l7 7-7 7" />
                                </svg>
                                <svg v-else class="w-3.5 h-3.5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7" />
                                </svg>
                            </button>
                        </div>
                        <p v-if="newsletterSubmitted" class="text-emerald-400 text-[11px] mt-2 font-medium">
                            ✓ {{ newsletterMessage }}
                        </p>
                        <p v-else-if="newsletterError" class="text-red-400 text-[11px] mt-2 font-medium">
                            ⚠ {{ newsletterError }}
                        </p>
                    </form>
                </div>

                <!-- Right: Contact Us -->
                <div class="lg:col-span-3 text-center lg:text-left flex flex-col items-center lg:items-start">
                    <h4 class="font-bold text-sm sm:text-base text-white mb-3">
                        Contact Us
                    </h4>
                    <ul class="space-y-2.5 text-xs sm:text-sm text-gray-400">
                        <!-- Website / Company Name -->
                        <li class="flex items-center gap-2">
                            <svg class="w-4 h-4 text-primary shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" />
                            </svg>
                            <span>{{ $page.props.store_settings?.company_name || 'Skynet Digital Hub' }}</span>
                        </li>

                        <!-- Email / Contact Us link -->
                        <li>
                            <a
                                :href="'mailto:' + ($page.props.store_settings?.company_email || 'support@skynetdigitalhub.com.ng')"
                                class="flex items-center gap-2 hover:text-primary transition"
                            >
                                <svg class="w-4 h-4 text-primary shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
                                </svg>
                                <span>Contact us</span>
                            </a>
                        </li>

                        <!-- Phone / WhatsApp if available -->
                        <li v-if="$page.props.store_settings?.company_phone">
                            <a
                                :href="'tel:' + $page.props.store_settings.company_phone"
                                class="flex items-center gap-2 hover:text-primary transition"
                            >
                                <svg class="w-4 h-4 text-primary shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z" />
                                </svg>
                                <span>{{ $page.props.store_settings.company_phone }}</span>
                            </a>
                        </li>
                    </ul>
                </div>
            </div>

            <!-- Divider -->
            <hr class="my-8 sm:my-10 border-gray-800" />

            <!-- ─── Bottom Navigation Links (4 Columns) ───────────────────────────── -->
            <div class="grid grid-cols-2 md:grid-cols-4 gap-8 text-xs sm:text-sm">
                
                <!-- Col 1: Shop -->
                <div>
                    <h5 class="font-bold text-white text-sm sm:text-base mb-3.5 tracking-tight">
                        Shop
                    </h5>
                    <ul class="space-y-2 text-gray-400">
                        <li>
                            <Link :href="route('home')" class="hover:text-primary transition">Home</Link>
                        </li>
                        <li>
                            <Link :href="route('shop.index')" class="hover:text-primary transition">Products</Link>
                        </li>
                        <li>
                            <Link :href="route('shop.index')" class="hover:text-primary transition">Categories</Link>
                        </li>
                        <li>
                            <Link :href="route('cart.index')" class="hover:text-primary transition">Cart</Link>
                        </li>
                        <li>
                            <Link :href="route('wishlist.index')" class="hover:text-primary transition">Wishlist</Link>
                        </li>
                    </ul>
                </div>

                <!-- Col 2: Top Categories -->
                <div>
                    <h5 class="font-bold text-white text-sm sm:text-base mb-3.5 tracking-tight">
                        Top Categories
                    </h5>
                    <ul class="space-y-2 text-gray-400">
                        <template v-if="$page.props.categories && $page.props.categories.length > 0">
                            <li v-for="cat in $page.props.categories.slice(0, 5)" :key="cat.id">
                                <Link
                                    :href="route('shop.index', { category: cat.slug })"
                                    class="hover:text-primary transition capitalize"
                                >
                                    {{ cat.name }}
                                </Link>
                            </li>
                        </template>
                        <template v-else>
                            <li><Link :href="route('shop.index')" class="hover:text-primary transition">Electronics</Link></li>
                            <li><Link :href="route('shop.index')" class="hover:text-primary transition">Computing & Accessories</Link></li>
                            <li><Link :href="route('shop.index')" class="hover:text-primary transition">Phones & Tablets</Link></li>
                            <li><Link :href="route('shop.index')" class="hover:text-primary transition">Home & Office</Link></li>
                        </template>
                    </ul>
                </div>

                <!-- Col 3: Account -->
                <div>
                    <h5 class="font-bold text-white text-sm sm:text-base mb-3.5 tracking-tight">
                        Account
                    </h5>
                    <ul class="space-y-2 text-gray-400">
                        <template v-if="$page.props.auth?.customer || $page.props.auth?.user">
                            <li>
                                <Link :href="route('customer.orders')" class="hover:text-primary transition">My Orders</Link>
                            </li>
                            <li>
                                <Link :href="route('order.track')" class="hover:text-primary transition">Track Order</Link>
                            </li>
                        </template>
                        <template v-else>
                            <li>
                                <Link :href="route('login')" class="hover:text-primary transition">Sign In</Link>
                            </li>
                            <li>
                                <Link :href="route('register')" class="hover:text-primary transition">Register</Link>
                            </li>
                            <li>
                                <Link :href="route('order.track')" class="hover:text-primary transition">Track Order</Link>
                            </li>
                        </template>
                    </ul>
                </div>

                <!-- Col 4: Information -->
                <div>
                    <h5 class="font-bold text-white text-sm sm:text-base mb-3.5 tracking-tight">
                        Information
                    </h5>
                    <ul class="space-y-2 text-gray-400">
                        <li>
                            <button
                                type="button"
                                @click="openModal('privacy')"
                                class="hover:text-primary transition cursor-pointer text-left"
                            >
                                Privacy Policy
                            </button>
                        </li>
                        <li>
                            <button
                                type="button"
                                @click="openModal('terms')"
                                class="hover:text-primary transition cursor-pointer text-left"
                            >
                                Terms & Conditions
                            </button>
                        </li>
                        <li>
                            <button
                                type="button"
                                @click="openModal('returns')"
                                class="hover:text-primary transition cursor-pointer text-left"
                            >
                                Returns Policy
                            </button>
                        </li>
                        <li>
                            <button
                                type="button"
                                @click="openModal('contact')"
                                class="hover:text-primary transition cursor-pointer text-left"
                            >
                                Contact Us
                            </button>
                        </li>
                    </ul>
                </div>
            </div>

            <!-- ─── Bottom Bar: Copyright & Back to Top ───────────────────────────── -->
            <div class="mt-10 pt-6 border-t border-gray-800 flex items-center justify-between text-xs text-gray-400">
                <p>
                    © {{ new Date().getFullYear() }}
                    <span class="text-primary font-bold">
                        {{ $page.props.store_settings?.company_name || 'Skynet' }}.
                    </span>
                    All rights reserved.
                </p>

                <!-- Back to Top Button (Orange square with chevron as in reference) -->
                <button
                    type="button"
                    @click="scrollToTop"
                    class="w-8 h-8 sm:w-9 sm:h-9 rounded-lg bg-primary hover:opacity-90 text-white flex items-center justify-center transition shadow-md cursor-pointer hover:scale-105 active:scale-95"
                    aria-label="Back to Top"
                    title="Back to Top"
                >
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 15l7-7 7 7" />
                    </svg>
                </button>
            </div>
        </div>

        <!-- ─── Information Modal (Privacy, Terms, Returns, Contact) ───────────── -->
        <div
            v-if="activeModal"
            class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/70 backdrop-blur-xs transition-opacity"
            @click.self="closeModal"
        >
            <div class="bg-white dark:bg-gray-900 text-gray-800 dark:text-gray-100 rounded-2xl max-w-lg w-full max-h-[85vh] flex flex-col shadow-2xl border border-gray-200 dark:border-gray-800 overflow-hidden">
                <!-- Modal Header -->
                <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-800 flex items-center justify-between">
                    <h3 class="text-lg font-bold text-gray-900 dark:text-white capitalize">
                        {{
                            activeModal === 'privacy' ? 'Privacy Policy' :
                            activeModal === 'terms' ? 'Terms & Conditions' :
                            activeModal === 'returns' ? 'Returns & Refund Policy' : 'Contact Support'
                        }}
                    </h3>
                    <button
                        type="button"
                        @click="closeModal"
                        class="p-1 rounded-lg text-gray-400 hover:text-gray-600 dark:hover:text-gray-200 hover:bg-gray-100 dark:hover:bg-gray-800 transition"
                    >
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>

                <!-- Modal Content -->
                <div class="p-6 overflow-y-auto space-y-4 text-xs sm:text-sm text-gray-600 dark:text-gray-300 leading-relaxed">
                    <!-- Privacy Policy -->
                    <template v-if="activeModal === 'privacy'">
                        <p>We respect your privacy and are committed to protecting your personal data. This privacy notice explains how we look after your personal data when you visit our store and informs you about your privacy rights.</p>
                        <h4 class="font-bold text-gray-900 dark:text-white">1. Information We Collect</h4>
                        <p>We collect personal information that you provide when creating an account, making a purchase, or signing up for our newsletter. This includes your name, email address, shipping address, and phone number.</p>
                        <h4 class="font-bold text-gray-900 dark:text-white">2. Payment Security</h4>
                        <p>All online payments are securely processed through encrypted payment gateways. We never store your full payment card details on our servers.</p>
                    </template>

                    <!-- Terms & Conditions -->
                    <template v-if="activeModal === 'terms'">
                        <p>By placing an order on our platform, you agree to the following terms and conditions:</p>
                        <h4 class="font-bold text-gray-900 dark:text-white">1. Orders & Pricing</h4>
                        <p>All orders are subject to acceptance and availability. Prices for products are quoted in Naira (₦) and are subject to change without prior notice.</p>
                        <h4 class="font-bold text-gray-900 dark:text-white">2. Delivery & Verification</h4>
                        <p>We deliver nationwide. Delivery times may vary depending on the destination and shipping method selected during checkout.</p>
                    </template>

                    <!-- Returns Policy -->
                    <template v-if="activeModal === 'returns'">
                        <p>We want you to be completely satisfied with your purchase:</p>
                        <h4 class="font-bold text-gray-900 dark:text-white">1. Return Window</h4>
                        <p>You may initiate a return within 7 days of receiving your item if it is defective, damaged, or not as described.</p>
                        <h4 class="font-bold text-gray-900 dark:text-white">2. Return Conditions</h4>
                        <p>Items must be in original condition with all original packaging, tags, accessories, and warranty cards intact.</p>
                    </template>

                    <!-- Contact Us -->
                    <template v-if="activeModal === 'contact'">
                        <p>Have questions or need assistance? Our support team is here to help:</p>
                        <div class="space-y-3 pt-2">
                            <div class="flex items-start gap-2.5">
                                <span class="font-semibold text-gray-900 dark:text-white min-w-[70px]">Store:</span>
                                <span>{{ $page.props.store_settings?.company_name || 'Skynet Digital Hub' }}</span>
                            </div>
                            <div v-if="$page.props.store_settings?.company_address" class="flex items-start gap-2.5">
                                <span class="font-semibold text-gray-900 dark:text-white min-w-[70px]">Address:</span>
                                <span>{{ $page.props.store_settings.company_address }}</span>
                            </div>
                            <div class="flex items-start gap-2.5">
                                <span class="font-semibold text-gray-900 dark:text-white min-w-[70px]">Email:</span>
                                <a :href="'mailto:' + ($page.props.store_settings?.company_email || 'support@skynetdigitalhub.com.ng')" class="text-primary hover:underline">
                                    {{ $page.props.store_settings?.company_email || 'support@skynetdigitalhub.com.ng' }}
                                </a>
                            </div>
                            <div v-if="$page.props.store_settings?.company_phone" class="flex items-start gap-2.5">
                                <span class="font-semibold text-gray-900 dark:text-white min-w-[70px]">Phone:</span>
                                <span>{{ $page.props.store_settings.company_phone }}</span>
                            </div>
                        </div>
                    </template>
                </div>

                <!-- Modal Footer -->
                <div class="px-6 py-3 bg-gray-50 dark:bg-gray-800/50 border-t border-gray-200 dark:border-gray-800 text-right">
                    <button
                        type="button"
                        @click="closeModal"
                        class="px-4 py-2 bg-primary hover:opacity-90 text-white rounded-lg text-xs font-bold transition cursor-pointer"
                    >
                        Close
                    </button>
                </div>
            </div>
        </div>
    </footer>
</template>
