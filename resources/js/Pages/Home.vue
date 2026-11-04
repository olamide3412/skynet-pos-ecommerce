<script setup>
import { Head, Link } from '@inertiajs/vue3';
import Layout from '@/Layouts/Layout.vue';
import { ref, onMounted, onUnmounted } from 'vue';
import Footer from '@/Components/Footer.vue';
import { useCart } from '@/Composables/useCart';

const props = defineProps({
    flash_sales: {
        type: Array,
        default: () => []
    },
    flash_sale_end: {
        type: String,
        default: null
    },
    featured_products: Array,
    new_arrivals: Array,
    best_sellers: {
        type: Array,
        default: () => []
    },
    most_searched: {
        type: Array,
        default: () => []
    },
    top_search_keywords: {
        type: Array,
        default: () => []
    },
    home_categories: Array,
    sliders: {
        type: Array,
        default: () => []
    }
});

const showAllMobileHomeCats = ref(false);

const {
    cartItemsByProductId,
    addToCart,
    updateQuantity,
    isUpdatingItem,
    isAddingProduct
} = useCart();

// ─── Carousel Scrolling Logic (Jumia Style) ───────────────────
const flashSaleTrackRef = ref(null);
const featuredTrackRef = ref(null);
const newArrivalsTrackRef = ref(null);
const bestSellersTrackRef = ref(null);
const mostSearchedTrackRef = ref(null);

const scrollTrack = (trackRef, direction) => {
    if (!trackRef) return;
    const scrollAmount = trackRef.clientWidth * 0.75;
    trackRef.scrollBy({
        left: direction === 'left' ? -scrollAmount : scrollAmount,
        behavior: 'smooth'
    });
};

// ─── Flash Sale Countdown Timer ──────────────────────────────
const flashCountdown = ref({ hours: '00', minutes: '00', seconds: '00' });
let flashTimer = null;

const tickFlashCountdown = () => {
    if (!props.flash_sale_end) {
        const now = new Date();
        const midnight = new Date(now.getFullYear(), now.getMonth(), now.getDate(), 23, 59, 59);
        const remaining = Math.max(0, midnight.getTime() - now.getTime());
        setCountdownValues(remaining);
        return;
    }
    const endMs = new Date(props.flash_sale_end).getTime();
    const remaining = Math.max(0, endMs - Date.now());
    setCountdownValues(remaining);
};

const setCountdownValues = (remainingMs) => {
    const totalSecs = Math.floor(remainingMs / 1000);
    const h = Math.floor(totalSecs / 3600);
    const m = Math.floor((totalSecs % 3600) / 60);
    const s = totalSecs % 60;
    flashCountdown.value = {
        hours: String(h).padStart(2, '0'),
        minutes: String(m).padStart(2, '0'),
        seconds: String(s).padStart(2, '0')
    };
};

// ─── Slider Logic ───────────────────────────────────────────────
const currentSlide = ref(0);
let slideInterval = null;

const nextSlide = () => {
    if (!props.sliders?.length) return;
    currentSlide.value = (currentSlide.value + 1) % props.sliders.length;
};

const prevSlide = () => {
    if (!props.sliders?.length) return;
    currentSlide.value = currentSlide.value === 0 ? props.sliders.length - 1 : currentSlide.value - 1;
};

const goToSlide = (index) => {
    currentSlide.value = index;
};

onMounted(() => {
    tickFlashCountdown();
    flashTimer = setInterval(tickFlashCountdown, 1000);

    if (props.sliders?.length > 1) {
        slideInterval = setInterval(nextSlide, 5000);
    }
});

onUnmounted(() => {
    if (flashTimer) clearInterval(flashTimer);
    if (slideInterval) clearInterval(slideInterval);
});
</script>

<template>
    <Head>
        <title>{{ ($page.props.store_settings.company_name || 'Skynet') + ($page.props.store_settings.company_tagline ? ' — ' + $page.props.store_settings.company_tagline : ' — Premium Online Shopping') }}</title>
        <meta name="description" :content="$page.props.store_settings.hero_subtitle || 'Shop authentic, high-quality products online at best prices. Fast nationwide delivery.'" />
        <meta property="og:title" :content="$page.props.store_settings.company_name || 'Skynet Digital Store'" />
        <meta property="og:description" :content="$page.props.store_settings.hero_subtitle || 'Shop authentic, high-quality products online at best prices.'" />
        <meta property="og:type" content="website" />
        <meta name="twitter:card" content="summary_large_image" />
    </Head>
    <Layout>
        <!-- Dynamic Hero Slider -->
        <div v-if="$page.props.store_settings.home_slider_enabled !== '0' && sliders && sliders.length > 0" class="container mx-auto px-4 sm:px-6 lg:px-8 py-4 md:py-8">
            <section class="relative bg-gray-900 border-none overflow-hidden h-[200px] md:h-[300px] lg:h-[450px] rounded-2xl md:rounded-[3rem] shadow-2xl">
                <div v-for="(slider, index) in sliders" :key="slider.id"
                    class="absolute inset-0 transition-opacity duration-1000 ease-in-out"
                    :class="index === currentSlide ? 'opacity-100 z-10' : 'opacity-0 z-0'" >
                    <!-- Background Image -->
                    <div class="absolute inset-0">
                        <img :src="'/storage/' + slider.image_path" class="w-full h-full object-cover object-center" alt="Slider Image" />
                        <!-- Gradient Overlay -->
                        <div v-if="slider.title || slider.subtitle" class="absolute inset-0 bg-gradient-to-r from-gray-900/80 via-gray-900/40 to-transparent"></div>
                    </div>
                    <!-- Content -->
                    <div v-if="slider.title || slider.subtitle || slider.button_text" class="relative z-20 container mx-auto px-10 h-full flex flex-col justify-center">
                        <div class="max-w-2xl transform transition-transform duration-1000" :class="index === currentSlide ? 'translate-y-0 opacity-100' : 'translate-y-10 opacity-0'">
                            <h2 v-if="slider.title" class="text-2xl sm:text-3xl md:text-4xl lg:text-5xl font-extrabold text-white leading-tight mb-2 md:mb-3 drop-shadow-lg">
                                {{ slider.title }}
                            </h2>
                            <p v-if="slider.subtitle" class="text-sm md:text-lg font-medium text-gray-100 mb-4 md:mb-6 drop-shadow-md max-w-xl">
                                {{ slider.subtitle }}
                            </p>
                            <Link v-if="slider.button_text && slider.button_link" :href="slider.button_link" class="inline-block bg-primary hover:opacity-90 text-white font-bold py-2 px-6 sm:py-3 sm:px-8 md:py-4 md:px-10 text-sm sm:text-base rounded-full shadow-2xl transition-all duration-300 transform hover:-translate-y-1">
                                {{ slider.button_text }} &rarr;
                            </Link>
                        </div>
                    </div>
                </div>

                <!-- Slider Controls -->
                <button v-if="sliders.length > 1" @click="prevSlide" class="absolute left-2 md:left-6 top-1/2 -translate-y-1/2 z-30 w-8 h-8 md:w-12 md:h-12 flex items-center justify-center rounded-full bg-black/20 hover:bg-black/60 text-white backdrop-blur-md border border-white/20 transition-all">
                    <svg class="w-5 h-5 md:w-6 md:h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
                </button>
                <button v-if="sliders.length > 1" @click="nextSlide" class="absolute right-2 md:right-6 top-1/2 -translate-y-1/2 z-30 w-8 h-8 md:w-12 md:h-12 flex items-center justify-center rounded-full bg-black/20 hover:bg-black/60 text-white backdrop-blur-md border border-white/20 transition-all">
                    <svg class="w-5 h-5 md:w-6 md:h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                </button>

                <!-- Dots -->
                <div v-if="sliders.length > 1" class="absolute bottom-6 left-1/2 -translate-x-1/2 z-30 flex gap-2">
                    <button v-for="(_, index) in sliders" :key="'dot-'+index" @click="goToSlide(index)"
                        class="h-2 md:h-3 rounded-full transition-all duration-300 shadow-lg border border-white/30"
                        :class="index === currentSlide ? 'bg-white w-8 md:w-12' : 'bg-white/40 hover:bg-white/70 w-2 md:w-3'">
                    </button>
                </div>
            </section>
        </div>

        <!-- Static Hero Fallback -->
        <section v-else-if="$page.props.store_settings.hero_enabled !== '0'" class="relative bg-gray-50 dark:bg-gray-900 border-b dark:border-gray-800">
            <div class="container mx-auto px-4 py-20 lg:py-32 flex flex-col lg:flex-row items-center gap-12">
                <div class="lg:w-1/2" data-aos="fade-right">
                    <span class="text-primary font-bold tracking-wider uppercase text-sm mb-4 block">
                        {{ $page.props.store_settings.hero_badge || 'New Collection 2026' }}
                    </span>
                    <h1 class="text-5xl md:text-6xl font-extrabold text-gray-900 dark:text-white leading-tight mb-6">
                        {{ $page.props.store_settings.hero_title || 'Discover Digital' }} <br>
                        <span class="text-transparent bg-clip-text bg-gradient-to-r from-[var(--color-primary)] to-[var(--color-secondary-500,#f97316)]">
                            {{ $page.props.store_settings.hero_title_highlight || 'Excellence' }}
                        </span>
                    </h1>
                    <p class="text-xl text-gray-600 dark:text-gray-300 mb-8 max-w-lg">
                        {{ $page.props.store_settings.hero_subtitle || 'Shop premium products, authentic brands, and quality gear built for your lifestyle.' }}
                    </p>
                    <div class="flex gap-4">
                        <Link :href="route('shop.index')" class="bg-primary hover:opacity-90 text-white font-semibold py-3 px-8 rounded-lg shadow-lg hover:shadow-xl transition-all duration-300 transform hover:-translate-y-1">
                            {{ $page.props.store_settings.hero_cta_primary || 'Shop Now' }}
                        </Link>
                        <Link :href="route('shop.index')" class="bg-white dark:bg-gray-800 hover:bg-gray-50 dark:hover:bg-gray-700 text-gray-900 dark:text-white border border-gray-200 dark:border-gray-700 font-semibold py-3 px-8 rounded-lg transition-all duration-300 shadow-sm">
                            {{ $page.props.store_settings.hero_cta_secondary || 'Explore Categories' }}
                        </Link>
                    </div>
                </div>
                <div class="lg:w-1/2 relative" data-aos="fade-left">
                    <div class="absolute inset-0 bg-primary opacity-20 blur-[100px] rounded-full"></div>
                    <img
                        :src="$page.props.store_settings.hero_image
                            ? '/storage/' + $page.props.store_settings.hero_image
                            : 'https://images.unsplash.com/photo-1483985988355-763728e1935b?auto=format&fit=crop&q=80&w=800&h=600'"
                        alt="Hero Image"
                        class="relative z-10 w-full rounded-2xl shadow-2xl object-cover h-[500px]"
                    />
                </div>
            </div>
        </section>

        <!-- Dynamic Text Marquee / Banner -->
        <section v-if="$page.props.store_settings.home_marquee_enabled !== '0'" class="bg-gray-50 dark:bg-gray-900 border-b dark:border-gray-800 py-6 shadow-sm overflow-hidden whitespace-nowrap">
            <div class="container mx-auto px-4 text-center">
                <h2 class="text-sm md:text-3xl font-serif font-black text-gray-900 dark:text-white tracking-widest uppercase">
                    {{ $page.props.store_settings.home_marquee_text || 'SHOP NOW' }}
                </h2>
            </div>
        </section>

        <!-- Categories Section -->
        <section class="py-10 md:py-16 bg-white dark:bg-dark container mx-auto px-4">
            <div class="flex flex-col sm:flex-row justify-between items-start sm:items-end mb-6 md:mb-8 gap-2">
                <div>
                    <h2 class="text-xl sm:text-2xl md:text-3xl font-extrabold text-gray-900 dark:text-white tracking-tight flex items-center gap-2">
                        <span class="w-8 h-8 sm:w-9 sm:h-9 rounded-lg bg-primary/10 text-primary flex items-center justify-center shrink-0">
                            <svg class="w-4 h-4 sm:w-5 sm:h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM14 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zM14 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z"/>
                            </svg>
                        </span>
                        <span>Shop by Category</span>
                    </h2>
                    <p class="text-xs sm:text-sm text-gray-500 dark:text-gray-400 mt-1">Explore our wide catalog by curated collections</p>
                </div>
                <Link
                    :href="route('shop.index')"
                    class="text-xs sm:text-sm font-bold text-primary hover:underline flex items-center gap-1 group"
                >
                    View All Categories
                    <span class="inline-block transition-transform group-hover:translate-x-1">→</span>
                </Link>
            </div>

            <!-- Responsive Circular Category Grid (Only 6 shown on mobile view) -->
            <div class="grid grid-cols-3 sm:grid-cols-4 md:grid-cols-6 lg:grid-cols-8 gap-4 sm:gap-6 justify-items-center">
                <Link
                    :href="route('shop.index', {category: category.slug})"
                    v-for="(category, index) in home_categories"
                    :key="category.id"
                    :class="[
                        !showAllMobileHomeCats && index >= 6 ? 'hidden sm:flex' : 'flex',
                        'group flex-col items-center text-center transition transform hover:-translate-y-1 duration-200'
                    ]"
                >
                    <!-- Circle Avatar with Accent Ring and Subtle Shadow -->
                    <div class="relative w-20 h-20 sm:w-24 sm:h-24 md:w-26 md:h-26 rounded-full p-1 bg-white dark:bg-gray-800 border-2 border-gray-200/80 dark:border-gray-700 group-hover:border-primary shadow-sm group-hover:shadow-md transition-all duration-300">
                        <div class="w-full h-full rounded-full overflow-hidden bg-gray-50 dark:bg-gray-900 flex items-center justify-center">
                            <img
                                :src="category.image ? '/storage/' + category.image : 'https://placehold.co/200x200?text=' + encodeURIComponent(category.name)"
                                class="w-full h-full object-cover transform group-hover:scale-110 transition duration-500"
                                :alt="category.name"
                                onerror="this.src='https://placehold.co/200x200?text=Category'"
                            />
                        </div>
                    </div>

                    <!-- Category Title -->
                    <h3 class="mt-2 text-xs sm:text-sm font-semibold text-gray-800 dark:text-gray-200 group-hover:text-primary transition line-clamp-2 max-w-[90px] sm:max-w-[105px] leading-snug">
                        {{ category.name }}
                    </h3>
                </Link>
            </div>

            <!-- Optional expand button for mobile if more than 6 categories exist -->
            <div v-if="home_categories && home_categories.length > 6" class="mt-4 text-center sm:hidden">
                <button
                    type="button"
                    @click="showAllMobileHomeCats = !showAllMobileHomeCats"
                    class="inline-flex items-center gap-1.5 px-4 py-1.5 rounded-full border border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-800 text-xs font-bold text-primary shadow-2xs hover:bg-primary/10 transition cursor-pointer"
                >
                    <span>{{ showAllMobileHomeCats ? 'Show Fewer Categories ▲' : 'See All Categories (' + home_categories.length + ') ▼' }}</span>
                </button>
            </div>
        </section>

        <!-- ─── ⚡ Flash Sales Section (Spot 1: Above Featured Products) ─── -->
        <section v-if="flash_sales && flash_sales.length" class="py-6 sm:py-8 bg-gradient-to-r from-red-500/5 via-amber-500/5 to-transparent dark:from-red-950/25 dark:via-amber-950/15 dark:to-transparent border-y border-red-200/40 dark:border-red-900/40">
            <div class="container mx-auto px-3 sm:px-4">
                <!-- Section Header -->
                <div class="flex flex-wrap items-center justify-between gap-3 mb-3 sm:mb-4 pb-2.5 border-b border-red-200/50 dark:border-red-900/30">
                    <div class="flex items-center gap-2 sm:gap-3 flex-wrap">
                        <span class="w-8 h-8 sm:w-9 sm:h-9 rounded-xl bg-red-600 text-white flex items-center justify-center shrink-0 shadow-sm animate-pulse">
                            <span class="text-base sm:text-lg">⚡</span>
                        </span>
                        <div>
                            <div class="flex items-center gap-2">
                                <h2 class="text-base sm:text-xl md:text-2xl font-black text-gray-900 dark:text-white tracking-tight flex items-center gap-1.5">
                                    Flash Sales
                                </h2>
                                <span class="bg-red-500 text-white text-[9px] sm:text-[10px] font-black px-2 py-0.5 rounded-full uppercase tracking-wider shadow-xs">
                                    Limited Time
                                </span>
                            </div>
                        </div>

                        <!-- Live Countdown Timer -->
                        <div class="flex items-center gap-1 sm:gap-1.5 ml-0 sm:ml-3 bg-white dark:bg-gray-800 border border-red-200 dark:border-red-900/50 px-2 sm:px-2.5 py-1 rounded-lg shadow-2xs text-[11px] sm:text-xs">
                            <span class="text-gray-500 dark:text-gray-400 font-medium text-[10px] uppercase tracking-wide mr-0.5">Ends in:</span>
                            <span class="bg-red-600 text-white font-black px-1.5 py-0.5 rounded text-[11px] sm:text-xs min-w-[22px] text-center">{{ flashCountdown.hours }}</span>
                            <span class="font-black text-red-600">:</span>
                            <span class="bg-red-600 text-white font-black px-1.5 py-0.5 rounded text-[11px] sm:text-xs min-w-[22px] text-center">{{ flashCountdown.minutes }}</span>
                            <span class="font-black text-red-600">:</span>
                            <span class="bg-red-600 text-white font-black px-1.5 py-0.5 rounded text-[11px] sm:text-xs min-w-[22px] text-center">{{ flashCountdown.seconds }}</span>
                        </div>
                    </div>

                    <div class="flex items-center gap-2 sm:gap-3 ml-auto">
                        <Link :href="route('shop.index', { collection: 'flash-sales' })" class="text-red-600 dark:text-red-400 hover:opacity-80 font-bold text-xs sm:text-sm flex items-center gap-1 group">
                            <span>See All Deals</span>
                            <span class="transition-transform group-hover:translate-x-0.5">→</span>
                        </Link>
                        <!-- Carousel Prev / Next Controls in Header -->
                        <div class="flex items-center gap-1">
                            <button
                                type="button"
                                @click="scrollTrack(flashSaleTrackRef, 'left')"
                                class="w-7 h-7 sm:w-8 sm:h-8 rounded-full bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 hover:bg-red-600 hover:text-white hover:border-red-600 text-gray-700 dark:text-gray-200 flex items-center justify-center transition shadow-2xs cursor-pointer"
                                aria-label="Previous Flash Sale Products"
                            >
                                <svg class="w-3.5 h-3.5 sm:w-4 sm:h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M15 19l-7-7 7-7" />
                                </svg>
                            </button>
                            <button
                                type="button"
                                @click="scrollTrack(flashSaleTrackRef, 'right')"
                                class="w-7 h-7 sm:w-8 sm:h-8 rounded-full bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 hover:bg-red-600 hover:text-white hover:border-red-600 text-gray-700 dark:text-gray-200 flex items-center justify-center transition shadow-2xs cursor-pointer"
                                aria-label="Next Flash Sale Products"
                            >
                                <svg class="w-3.5 h-3.5 sm:w-4 sm:h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 5l7 7-7 7" />
                                </svg>
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Carousel Track with Side Floating Buttons -->
                <div class="relative group">
                    <!-- Left Floating Arrow (desktop) -->
                    <button
                        type="button"
                        @click="scrollTrack(flashSaleTrackRef, 'left')"
                        class="hidden md:flex absolute -left-3.5 top-1/2 -translate-y-1/2 z-20 w-9 h-9 rounded-full bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 text-gray-700 dark:text-gray-200 shadow-md hover:bg-red-600 hover:text-white hover:border-red-600 items-center justify-center transition cursor-pointer opacity-0 group-hover:opacity-100"
                        aria-label="Previous Flash Sale Products"
                    >
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M15 19l-7-7 7-7" />
                        </svg>
                    </button>

                    <!-- Scrollable Container -->
                    <div
                        ref="flashSaleTrackRef"
                        class="flex gap-2.5 sm:gap-3.5 overflow-x-auto scroll-smooth scrollbar-hide pb-2 pt-0.5"
                    >
                        <div
                            v-for="product in flash_sales"
                            :key="product.id"
                            class="w-[145px] sm:w-[170px] md:w-[190px] shrink-0 bg-white dark:bg-gray-800 rounded-lg sm:rounded-xl shadow-2xs hover:shadow-md transition-all duration-300 border border-red-100 dark:border-red-900/30 group/card flex flex-col overflow-hidden"
                        >
                            <!-- Image Box -->
                            <div class="relative overflow-hidden h-28 sm:h-36 md:h-40 shrink-0 bg-gray-100 dark:bg-gray-700">
                                <Link :href="route('shop.show', product.url_key)" class="block w-full h-full">
                                    <img
                                        :src="product.image ? '/storage/' + product.image : 'https://placehold.co/400x400'"
                                        class="w-full h-full object-cover transform group-hover/card:scale-105 transition duration-500"
                                        :alt="product.name"
                                    />
                                </Link>
                                <!-- Badges -->
                                <div class="absolute top-1.5 left-1.5 z-10 bg-red-600 text-white text-[8px] sm:text-[9px] font-black px-1.5 py-0.5 rounded shadow-xs flex items-center gap-0.5">
                                    <span>⚡</span>
                                    <span v-if="product.price > 0 && (product.special_price || product.discount_price)">
                                        -{{ Math.round(((product.price - (product.special_price || product.discount_price)) / product.price) * 100) }}%
                                    </span>
                                    <span v-else>DEAL</span>
                                </div>
                            </div>

                            <!-- Content -->
                            <div class="p-2 sm:p-3 flex flex-col flex-grow">
                                <span class="text-[9px] sm:text-[10px] text-gray-400 dark:text-gray-500 uppercase tracking-wider mb-0.5 truncate">
                                    {{ product.category?.name || 'General' }}
                                </span>
                                <Link
                                    :href="route('shop.show', product.url_key)"
                                    class="text-xs sm:text-sm font-semibold text-gray-900 dark:text-white hover:text-red-600 transition mb-1 line-clamp-2 leading-tight min-h-[2rem]"
                                >
                                    {{ product.name }}
                                </Link>

                                <div class="mt-auto pt-2 flex flex-col gap-1.5 border-t border-gray-100 dark:border-gray-700/60">
                                    <div class="min-w-0">
                                        <span class="text-[9px] sm:text-[10px] text-gray-400 line-through block leading-none">
                                            ₦{{ parseFloat(product.price).toLocaleString() }}
                                        </span>
                                        <span class="text-xs sm:text-sm font-black text-red-600 dark:text-red-400 truncate block leading-tight">
                                            ₦{{ parseFloat(product.effective_price || product.special_price || product.discount_price || product.price).toLocaleString() }}
                                        </span>
                                    </div>

                                    <!-- Add to Cart or Quantity Controls -->
                                    <div v-if="cartItemsByProductId[product.id]" class="w-full inline-flex items-center justify-between border border-red-500/40 bg-red-500/5 dark:bg-red-500/10 rounded-md overflow-hidden shadow-2xs h-7">
                                        <button
                                            type="button"
                                            @click.prevent="updateQuantity(cartItemsByProductId[product.id], cartItemsByProductId[product.id].quantity - 1)"
                                            :disabled="isUpdatingItem(cartItemsByProductId[product.id].id)"
                                            class="w-6 h-full flex items-center justify-center font-bold text-gray-700 dark:text-gray-200 hover:bg-red-600 hover:text-white transition disabled:opacity-50 text-xs cursor-pointer"
                                            title="Decrease quantity">
                                            -
                                        </button>
                                        <span class="px-1 text-center text-xs font-black text-red-600 dark:text-red-400">
                                            {{ cartItemsByProductId[product.id].quantity }}
                                        </span>
                                        <button
                                            type="button"
                                            @click.prevent="updateQuantity(cartItemsByProductId[product.id], cartItemsByProductId[product.id].quantity + 1)"
                                            :disabled="isUpdatingItem(cartItemsByProductId[product.id].id)"
                                            class="w-6 h-full flex items-center justify-center font-bold text-gray-700 dark:text-gray-200 hover:bg-red-600 hover:text-white transition disabled:opacity-50 text-xs cursor-pointer"
                                            title="Increase quantity">
                                            +
                                        </button>
                                    </div>
                                    <button
                                        v-else
                                        @click="addToCart(product)"
                                        :disabled="isAddingProduct(product.id)"
                                        class="w-full py-1 sm:py-1.5 rounded-md bg-red-600 hover:bg-red-700 text-white text-[11px] sm:text-xs font-bold transition disabled:opacity-60 flex items-center justify-center gap-1 shadow-2xs cursor-pointer h-7"
                                    >
                                        <svg v-if="!isAddingProduct(product.id)" class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z"/>
                                        </svg>
                                        <svg v-else class="w-3 h-3 animate-spin" fill="none" viewBox="0 0 24 24">
                                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/>
                                        </svg>
                                        <span>{{ isAddingProduct(product.id) ? '...' : 'Claim Deal' }}</span>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Right Floating Arrow (desktop) -->
                    <button
                        type="button"
                        @click="scrollTrack(flashSaleTrackRef, 'right')"
                        class="hidden md:flex absolute -right-3.5 top-1/2 -translate-y-1/2 z-20 w-9 h-9 rounded-full bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 text-gray-700 dark:text-gray-200 shadow-md hover:bg-red-600 hover:text-white hover:border-red-600 items-center justify-center transition cursor-pointer opacity-0 group-hover:opacity-100"
                        aria-label="Next Flash Sale Products"
                    >
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 5l7 7-7 7" />
                        </svg>
                    </button>
                </div>
            </div>
        </section>

        <!-- Featured Products (Horizontal Jumia-style carousel) -->
        <section v-if="featured_products && featured_products.length" class="py-6 sm:py-8 bg-gray-50 dark:bg-gray-900 border-y border-gray-100 dark:border-gray-800">
            <div class="container mx-auto px-3 sm:px-4">
                <!-- Section Header -->
                <div class="flex items-center justify-between mb-3 sm:mb-4 pb-2 border-b border-gray-200/70 dark:border-gray-800">
                    <div class="flex items-center gap-2">
                        <span class="w-7 h-7 sm:w-8 sm:h-8 rounded-lg bg-amber-500/10 text-amber-500 dark:text-amber-400 flex items-center justify-center shrink-0">
                            <svg class="w-4 h-4 sm:w-5 sm:h-5" fill="currentColor" viewBox="0 0 24 24">
                                <path d="M12 17.27L18.18 21l-1.64-7.03L22 9.24l-7.19-.61L12 2 9.19 8.63 2 9.24l5.46 4.73L5.82 21z"/>
                            </svg>
                        </span>
                        <h2 class="text-base sm:text-xl md:text-2xl font-extrabold text-gray-900 dark:text-white tracking-tight">
                            Featured Products
                        </h2>
                        <span class="bg-primary/10 text-primary text-[10px] sm:text-xs font-black px-2 py-0.5 rounded-full uppercase tracking-wider hidden xs:inline-flex">
                            ★ Featured
                        </span>
                    </div>

                    <div class="flex items-center gap-2 sm:gap-3">
                        <Link :href="route('shop.index', { collection: 'featured' })" class="text-primary hover:opacity-80 font-bold text-xs sm:text-sm flex items-center gap-1 group">
                            <span>See All</span>
                            <span class="transition-transform group-hover:translate-x-0.5">→</span>
                        </Link>
                        <!-- Carousel Prev / Next Controls in Header -->
                        <div class="flex items-center gap-1">
                            <button
                                type="button"
                                @click="scrollTrack(featuredTrackRef, 'left')"
                                class="w-7 h-7 sm:w-8 sm:h-8 rounded-full bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 hover:bg-primary hover:text-white hover:border-primary text-gray-700 dark:text-gray-200 flex items-center justify-center transition shadow-2xs cursor-pointer"
                                aria-label="Previous Featured Products"
                            >
                                <svg class="w-3.5 h-3.5 sm:w-4 sm:h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M15 19l-7-7 7-7" />
                                </svg>
                            </button>
                            <button
                                type="button"
                                @click="scrollTrack(featuredTrackRef, 'right')"
                                class="w-7 h-7 sm:w-8 sm:h-8 rounded-full bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 hover:bg-primary hover:text-white hover:border-primary text-gray-700 dark:text-gray-200 flex items-center justify-center transition shadow-2xs cursor-pointer"
                                aria-label="Next Featured Products"
                            >
                                <svg class="w-3.5 h-3.5 sm:w-4 sm:h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 5l7 7-7 7" />
                                </svg>
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Carousel Track with Side Floating Buttons -->
                <div class="relative group">
                    <!-- Left Floating Arrow (desktop) -->
                    <button
                        type="button"
                        @click="scrollTrack(featuredTrackRef, 'left')"
                        class="hidden md:flex absolute -left-3.5 top-1/2 -translate-y-1/2 z-20 w-9 h-9 rounded-full bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 text-gray-700 dark:text-gray-200 shadow-md hover:bg-primary hover:text-white hover:border-primary items-center justify-center transition cursor-pointer opacity-0 group-hover:opacity-100"
                        aria-label="Previous Products"
                    >
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M15 19l-7-7 7-7" />
                        </svg>
                    </button>

                    <!-- Scrollable Container -->
                    <div
                        ref="featuredTrackRef"
                        class="flex gap-2.5 sm:gap-3.5 overflow-x-auto scroll-smooth scrollbar-hide pb-2 pt-0.5"
                    >
                        <div
                            v-for="product in featured_products"
                            :key="product.id"
                            class="w-[145px] sm:w-[170px] md:w-[190px] shrink-0 bg-white dark:bg-gray-800 rounded-lg sm:rounded-xl shadow-2xs hover:shadow-md transition-all duration-300 border border-gray-100 dark:border-gray-700/80 group/card flex flex-col overflow-hidden"
                        >
                            <!-- Image Box -->
                            <div class="relative overflow-hidden h-28 sm:h-36 md:h-40 shrink-0 bg-gray-100 dark:bg-gray-700">
                                <Link :href="route('shop.show', product.url_key)" class="block w-full h-full">
                                    <img
                                        :src="product.image ? '/storage/' + product.image : 'https://placehold.co/400x400'"
                                        class="w-full h-full object-cover transform group-hover/card:scale-105 transition duration-500"
                                        :alt="product.name"
                                    />
                                </Link>
                                <!-- Badges -->
                                <div v-if="product.is_on_flash_sale" class="absolute top-1.5 left-1.5 z-10 bg-amber-500 text-white text-[8px] sm:text-[9px] font-black px-1.5 py-0.5 rounded shadow-xs">
                                    ⚡ FLASH
                                </div>
                                <div v-else-if="product.discount_price" class="absolute top-1.5 left-1.5 z-10 bg-red-500 text-white text-[8px] sm:text-[9px] font-bold px-1.5 py-0.5 rounded shadow-xs">
                                    SALE
                                </div>
                            </div>

                            <!-- Content -->
                            <div class="p-2 sm:p-3 flex flex-col flex-grow">
                                <span class="text-[9px] sm:text-[10px] text-gray-400 dark:text-gray-500 uppercase tracking-wider mb-0.5 truncate">
                                    {{ product.category?.name || 'General' }}
                                </span>
                                <Link
                                    :href="route('shop.show', product.url_key)"
                                    class="text-xs sm:text-sm font-semibold text-gray-900 dark:text-white hover:text-primary transition mb-1 line-clamp-2 leading-tight min-h-[2rem]"
                                >
                                    {{ product.name }}
                                </Link>

                                <div class="mt-auto pt-2 flex flex-col gap-1.5 border-t border-gray-100 dark:border-gray-700/60">
                                    <div class="min-w-0">
                                        <span v-if="product.is_on_flash_sale || product.discount_price" class="text-[9px] sm:text-[10px] text-gray-400 line-through block leading-none">
                                            ₦{{ parseFloat(product.price).toLocaleString() }}
                                        </span>
                                        <span class="text-xs sm:text-sm font-black text-primary truncate block leading-tight">
                                            ₦{{ parseFloat(product.effective_price || product.discount_price || product.price).toLocaleString() }}
                                        </span>
                                    </div>

                                    <!-- Add to Cart or Quantity Controls -->
                                    <div v-if="cartItemsByProductId[product.id]" class="w-full inline-flex items-center justify-between border border-primary/40 bg-primary/5 dark:bg-primary/10 rounded-md overflow-hidden shadow-2xs h-7">
                                        <button
                                            type="button"
                                            @click.prevent="updateQuantity(cartItemsByProductId[product.id], cartItemsByProductId[product.id].quantity - 1)"
                                            :disabled="isUpdatingItem(cartItemsByProductId[product.id].id)"
                                            class="w-6 h-full flex items-center justify-center font-bold text-gray-700 dark:text-gray-200 hover:bg-primary hover:text-white transition disabled:opacity-50 text-xs cursor-pointer"
                                            title="Decrease quantity">
                                            -
                                        </button>
                                        <span class="px-1 text-center text-xs font-black text-primary">
                                            {{ cartItemsByProductId[product.id].quantity }}
                                        </span>
                                        <button
                                            type="button"
                                            @click.prevent="updateQuantity(cartItemsByProductId[product.id], cartItemsByProductId[product.id].quantity + 1)"
                                            :disabled="isUpdatingItem(cartItemsByProductId[product.id].id)"
                                            class="w-6 h-full flex items-center justify-center font-bold text-gray-700 dark:text-gray-200 hover:bg-primary hover:text-white transition disabled:opacity-50 text-xs cursor-pointer"
                                            title="Increase quantity">
                                            +
                                        </button>
                                    </div>
                                    <button
                                        v-else
                                        @click="addToCart(product)"
                                        :disabled="isAddingProduct(product.id)"
                                        class="w-full py-1 sm:py-1.5 rounded-md bg-primary hover:opacity-90 text-white text-[11px] sm:text-xs font-bold transition disabled:opacity-60 flex items-center justify-center gap-1 shadow-2xs cursor-pointer h-7"
                                    >
                                        <svg v-if="!isAddingProduct(product.id)" class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z"/>
                                        </svg>
                                        <svg v-else class="w-3 h-3 animate-spin" fill="none" viewBox="0 0 24 24">
                                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/>
                                        </svg>
                                        <span>{{ isAddingProduct(product.id) ? '...' : 'Add' }}</span>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Right Floating Arrow (desktop) -->
                    <button
                        type="button"
                        @click="scrollTrack(featuredTrackRef, 'right')"
                        class="hidden md:flex absolute -right-3.5 top-1/2 -translate-y-1/2 z-20 w-9 h-9 rounded-full bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 text-gray-700 dark:text-gray-200 shadow-md hover:bg-primary hover:text-white hover:border-primary items-center justify-center transition cursor-pointer opacity-0 group-hover:opacity-100"
                        aria-label="Next Products"
                    >
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 5l7 7-7 7" />
                        </svg>
                    </button>
                </div>
            </div>
        </section>

        <!-- New Arrivals (Horizontal Jumia-style carousel) -->
        <section v-if="new_arrivals && new_arrivals.length" class="py-6 sm:py-8 bg-white dark:bg-dark border-b border-gray-100 dark:border-gray-800">
            <div class="container mx-auto px-3 sm:px-4">
                <!-- Section Header -->
                <div class="flex items-center justify-between mb-3 sm:mb-4 pb-2 border-b border-gray-200/70 dark:border-gray-800">
                    <div class="flex items-center gap-2">
                        <span class="w-7 h-7 sm:w-8 sm:h-8 rounded-lg bg-emerald-500/10 text-emerald-500 dark:text-emerald-400 flex items-center justify-center shrink-0">
                            <svg class="w-4 h-4 sm:w-5 sm:h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 3v4M3 5h4M6 17v4m-2-2h4m5-16l2.286 6.857L21 12l-5.714 2.143L13 21l-2.286-6.857L5 12l5.714-2.143L13 3z"/>
                            </svg>
                        </span>
                        <h2 class="text-base sm:text-xl md:text-2xl font-extrabold text-gray-900 dark:text-white tracking-tight">
                            New Arrivals
                        </h2>
                        <span class="bg-emerald-100 dark:bg-emerald-900/40 text-emerald-700 dark:text-emerald-300 text-[10px] sm:text-xs font-black px-2 py-0.5 rounded-full uppercase tracking-wider hidden xs:inline-flex items-center gap-1">
                            ✨ New
                        </span>
                    </div>

                    <div class="flex items-center gap-2 sm:gap-3">
                        <Link :href="route('shop.index', { collection: 'new-arrivals' })" class="text-primary hover:opacity-80 font-bold text-xs sm:text-sm flex items-center gap-1 group">
                            <span>See All</span>
                            <span class="transition-transform group-hover:translate-x-0.5">→</span>
                        </Link>
                        <!-- Carousel Prev / Next Controls in Header -->
                        <div class="flex items-center gap-1">
                            <button
                                type="button"
                                @click="scrollTrack(newArrivalsTrackRef, 'left')"
                                class="w-7 h-7 sm:w-8 sm:h-8 rounded-full bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 hover:bg-primary hover:text-white hover:border-primary text-gray-700 dark:text-gray-200 flex items-center justify-center transition shadow-2xs cursor-pointer"
                                aria-label="Previous New Arrivals"
                            >
                                <svg class="w-3.5 h-3.5 sm:w-4 sm:h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M15 19l-7-7 7-7" />
                                </svg>
                            </button>
                            <button
                                type="button"
                                @click="scrollTrack(newArrivalsTrackRef, 'right')"
                                class="w-7 h-7 sm:w-8 sm:h-8 rounded-full bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 hover:bg-primary hover:text-white hover:border-primary text-gray-700 dark:text-gray-200 flex items-center justify-center transition shadow-2xs cursor-pointer"
                                aria-label="Next New Arrivals"
                            >
                                <svg class="w-3.5 h-3.5 sm:w-4 sm:h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 5l7 7-7 7" />
                                </svg>
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Carousel Track with Side Floating Buttons -->
                <div class="relative group">
                    <!-- Left Floating Arrow (desktop) -->
                    <button
                        type="button"
                        @click="scrollTrack(newArrivalsTrackRef, 'left')"
                        class="hidden md:flex absolute -left-3.5 top-1/2 -translate-y-1/2 z-20 w-9 h-9 rounded-full bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 text-gray-700 dark:text-gray-200 shadow-md hover:bg-primary hover:text-white hover:border-primary items-center justify-center transition cursor-pointer opacity-0 group-hover:opacity-100"
                        aria-label="Previous Products"
                    >
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M15 19l-7-7 7-7" />
                        </svg>
                    </button>

                    <!-- Scrollable Container -->
                    <div
                        ref="newArrivalsTrackRef"
                        class="flex gap-2.5 sm:gap-3.5 overflow-x-auto scroll-smooth scrollbar-hide pb-2 pt-0.5"
                    >
                        <div
                            v-for="product in new_arrivals"
                            :key="product.id"
                            class="w-[145px] sm:w-[170px] md:w-[190px] shrink-0 bg-white dark:bg-gray-800 rounded-lg sm:rounded-xl shadow-2xs hover:shadow-md transition-all duration-300 border border-gray-100 dark:border-gray-700/80 group/card flex flex-col overflow-hidden"
                        >
                            <!-- Image Box -->
                            <div class="relative overflow-hidden h-28 sm:h-36 md:h-40 shrink-0 bg-gray-100 dark:bg-gray-700">
                                <Link :href="route('shop.show', product.url_key)" class="block w-full h-full">
                                    <img
                                        :src="product.image ? '/storage/' + product.image : 'https://placehold.co/400x400'"
                                        class="w-full h-full object-cover transform group-hover/card:scale-105 transition duration-500"
                                        :alt="product.name"
                                    />
                                </Link>
                                <!-- Badges -->
                                <div v-if="product.is_on_flash_sale" class="absolute top-1.5 left-1.5 z-10 bg-amber-500 text-white text-[8px] sm:text-[9px] font-black px-1.5 py-0.5 rounded shadow-xs">
                                    ⚡ FLASH
                                </div>
                                <div v-else-if="product.discount_price" class="absolute top-1.5 left-1.5 z-10 bg-red-500 text-white text-[8px] sm:text-[9px] font-bold px-1.5 py-0.5 rounded shadow-xs">
                                    SALE
                                </div>
                                <div v-else class="absolute top-1.5 left-1.5 z-10 bg-emerald-500 text-white text-[8px] sm:text-[9px] font-bold px-1.5 py-0.5 rounded shadow-xs">
                                    ✨ NEW
                                </div>
                            </div>

                            <!-- Content -->
                            <div class="p-2 sm:p-3 flex flex-col flex-grow">
                                <span class="text-[9px] sm:text-[10px] text-gray-400 dark:text-gray-500 uppercase tracking-wider mb-0.5 truncate">
                                    {{ product.category?.name || 'General' }}
                                </span>
                                <Link
                                    :href="route('shop.show', product.url_key)"
                                    class="text-xs sm:text-sm font-semibold text-gray-900 dark:text-white hover:text-primary transition mb-1 line-clamp-2 leading-tight min-h-[2rem]"
                                >
                                    {{ product.name }}
                                </Link>

                                <div class="mt-auto pt-2 flex flex-col gap-1.5 border-t border-gray-100 dark:border-gray-700/60">
                                    <div class="min-w-0">
                                        <span v-if="product.is_on_flash_sale || product.discount_price" class="text-[9px] sm:text-[10px] text-gray-400 line-through block leading-none">
                                            ₦{{ parseFloat(product.price).toLocaleString() }}
                                        </span>
                                        <span class="text-xs sm:text-sm font-black text-primary truncate block leading-tight">
                                            ₦{{ parseFloat(product.effective_price || product.discount_price || product.price).toLocaleString() }}
                                        </span>
                                    </div>

                                    <!-- Add to Cart or Quantity Controls -->
                                    <div v-if="cartItemsByProductId[product.id]" class="w-full inline-flex items-center justify-between border border-primary/40 bg-primary/5 dark:bg-primary/10 rounded-md overflow-hidden shadow-2xs h-7">
                                        <button
                                            type="button"
                                            @click.prevent="updateQuantity(cartItemsByProductId[product.id], cartItemsByProductId[product.id].quantity - 1)"
                                            :disabled="isUpdatingItem(cartItemsByProductId[product.id].id)"
                                            class="w-6 h-full flex items-center justify-center font-bold text-gray-700 dark:text-gray-200 hover:bg-primary hover:text-white transition disabled:opacity-50 text-xs cursor-pointer"
                                            title="Decrease quantity">
                                            -
                                        </button>
                                        <span class="px-1 text-center text-xs font-black text-primary">
                                            {{ cartItemsByProductId[product.id].quantity }}
                                        </span>
                                        <button
                                            type="button"
                                            @click.prevent="updateQuantity(cartItemsByProductId[product.id], cartItemsByProductId[product.id].quantity + 1)"
                                            :disabled="isUpdatingItem(cartItemsByProductId[product.id].id)"
                                            class="w-6 h-full flex items-center justify-center font-bold text-gray-700 dark:text-gray-200 hover:bg-primary hover:text-white transition disabled:opacity-50 text-xs cursor-pointer"
                                            title="Increase quantity">
                                            +
                                        </button>
                                    </div>
                                    <button
                                        v-else
                                        @click="addToCart(product)"
                                        :disabled="isAddingProduct(product.id)"
                                        class="w-full py-1 sm:py-1.5 rounded-md bg-primary hover:opacity-90 text-white text-[11px] sm:text-xs font-bold transition disabled:opacity-60 flex items-center justify-center gap-1 shadow-2xs cursor-pointer h-7"
                                    >
                                        <svg v-if="!isAddingProduct(product.id)" class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z"/>
                                        </svg>
                                        <svg v-else class="w-3 h-3 animate-spin" fill="none" viewBox="0 0 24 24">
                                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/>
                                        </svg>
                                        <span>{{ isAddingProduct(product.id) ? '...' : 'Add' }}</span>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Right Floating Arrow (desktop) -->
                    <button
                        type="button"
                        @click="scrollTrack(newArrivalsTrackRef, 'right')"
                        class="hidden md:flex absolute -right-3.5 top-1/2 -translate-y-1/2 z-20 w-9 h-9 rounded-full bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 text-gray-700 dark:text-gray-200 shadow-md hover:bg-primary hover:text-white hover:border-primary items-center justify-center transition cursor-pointer opacity-0 group-hover:opacity-100"
                        aria-label="Next Products"
                    >
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 5l7 7-7 7" />
                        </svg>
                    </button>
                </div>
            </div>
        </section>

        <!-- Best Sellers (Horizontal Jumia-style carousel) -->
        <section v-if="best_sellers && best_sellers.length" class="py-6 sm:py-8 bg-gray-50 dark:bg-gray-900 border-b border-gray-100 dark:border-gray-800">
            <div class="container mx-auto px-3 sm:px-4">
                <!-- Section Header -->
                <div class="flex items-center justify-between mb-3 sm:mb-4 pb-2 border-b border-gray-200/70 dark:border-gray-800">
                    <div class="flex items-center gap-2">
                        <span class="w-7 h-7 sm:w-8 sm:h-8 rounded-lg bg-orange-500/10 text-orange-500 dark:text-orange-400 flex items-center justify-center shrink-0">
                            <svg class="w-4 h-4 sm:w-5 sm:h-5" fill="currentColor" viewBox="0 0 24 24">
                                <path d="M13.5.67s.74 2.65.74 4.8c0 2.06-1.35 3.73-3.41 3.73-2.07 0-3.63-1.67-3.63-3.73l.03-.36C5.21 7.51 4 10.61 4 14c0 4.42 3.58 8 8 8s8-3.58 8-8C20 8.61 17.41 3.8 13.5.67zM11.71 19c-1.78 0-3.22-1.4-3.22-3.14 0-1.62 1.05-2.76 2.81-3.12 1.77-.36 3.6-1.21 4.62-2.58.39 1.29.59 2.65.59 4.04 0 2.65-2.15 4.8-4.8 4.8z"/>
                            </svg>
                        </span>
                        <h2 class="text-base sm:text-xl md:text-2xl font-extrabold text-gray-900 dark:text-white tracking-tight">
                            Best Sellers
                        </h2>
                        <span class="bg-amber-100 dark:bg-amber-900/40 text-amber-700 dark:text-amber-300 text-[10px] sm:text-xs font-black px-2 py-0.5 rounded-full uppercase tracking-wider hidden xs:inline-flex items-center gap-1">
                            🔥 Top Picks
                        </span>
                    </div>

                    <div class="flex items-center gap-2 sm:gap-3">
                        <Link :href="route('shop.index', { collection: 'best-sellers' })" class="text-primary hover:opacity-80 font-bold text-xs sm:text-sm flex items-center gap-1 group">
                            <span>See All</span>
                            <span class="transition-transform group-hover:translate-x-0.5">→</span>
                        </Link>
                        <!-- Carousel Prev / Next Controls in Header -->
                        <div class="flex items-center gap-1">
                            <button
                                type="button"
                                @click="scrollTrack(bestSellersTrackRef, 'left')"
                                class="w-7 h-7 sm:w-8 sm:h-8 rounded-full bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 hover:bg-primary hover:text-white hover:border-primary text-gray-700 dark:text-gray-200 flex items-center justify-center transition shadow-2xs cursor-pointer"
                                aria-label="Previous Best Sellers"
                            >
                                <svg class="w-3.5 h-3.5 sm:w-4 sm:h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M15 19l-7-7 7-7" />
                                </svg>
                            </button>
                            <button
                                type="button"
                                @click="scrollTrack(bestSellersTrackRef, 'right')"
                                class="w-7 h-7 sm:w-8 sm:h-8 rounded-full bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 hover:bg-primary hover:text-white hover:border-primary text-gray-700 dark:text-gray-200 flex items-center justify-center transition shadow-2xs cursor-pointer"
                                aria-label="Next Best Sellers"
                            >
                                <svg class="w-3.5 h-3.5 sm:w-4 sm:h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 5l7 7-7 7" />
                                </svg>
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Carousel Track with Side Floating Buttons -->
                <div class="relative group">
                    <!-- Left Floating Arrow (desktop) -->
                    <button
                        type="button"
                        @click="scrollTrack(bestSellersTrackRef, 'left')"
                        class="hidden md:flex absolute -left-3.5 top-1/2 -translate-y-1/2 z-20 w-9 h-9 rounded-full bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 text-gray-700 dark:text-gray-200 shadow-md hover:bg-primary hover:text-white hover:border-primary items-center justify-center transition cursor-pointer opacity-0 group-hover:opacity-100"
                        aria-label="Previous Products"
                    >
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M15 19l-7-7 7-7" />
                        </svg>
                    </button>

                    <!-- Scrollable Container -->
                    <div
                        ref="bestSellersTrackRef"
                        class="flex gap-2.5 sm:gap-3.5 overflow-x-auto scroll-smooth scrollbar-hide pb-2 pt-0.5"
                    >
                        <div
                            v-for="product in best_sellers"
                            :key="product.id"
                            class="w-[145px] sm:w-[170px] md:w-[190px] shrink-0 bg-white dark:bg-gray-800 rounded-lg sm:rounded-xl shadow-2xs hover:shadow-md transition-all duration-300 border border-gray-100 dark:border-gray-700/80 group/card flex flex-col overflow-hidden"
                        >
                            <!-- Image Box -->
                            <div class="relative overflow-hidden h-28 sm:h-36 md:h-40 shrink-0 bg-gray-100 dark:bg-gray-700">
                                <Link :href="route('shop.show', product.url_key)" class="block w-full h-full">
                                    <img
                                        :src="product.image ? '/storage/' + product.image : 'https://placehold.co/400x400'"
                                        class="w-full h-full object-cover transform group-hover/card:scale-105 transition duration-500"
                                        :alt="product.name"
                                    />
                                </Link>
                                <!-- Badges -->
                                <div v-if="product.is_on_flash_sale" class="absolute top-1.5 left-1.5 z-10 bg-amber-500 text-white text-[8px] sm:text-[9px] font-black px-1.5 py-0.5 rounded shadow-xs">
                                    ⚡ FLASH
                                </div>
                                <div v-else-if="product.discount_price" class="absolute top-1.5 left-1.5 z-10 bg-red-500 text-white text-[8px] sm:text-[9px] font-bold px-1.5 py-0.5 rounded shadow-xs">
                                    SALE
                                </div>
                                <div v-else class="absolute top-1.5 left-1.5 z-10 bg-gradient-to-r from-amber-500 to-orange-500 text-white text-[8px] sm:text-[9px] font-extrabold px-1.5 py-0.5 rounded shadow-xs">
                                    🔥 HOT
                                </div>
                            </div>

                            <!-- Content -->
                            <div class="p-2 sm:p-3 flex flex-col flex-grow">
                                <span class="text-[9px] sm:text-[10px] text-gray-400 dark:text-gray-500 uppercase tracking-wider mb-0.5 truncate">
                                    {{ product.category?.name || 'General' }}
                                </span>
                                <Link
                                    :href="route('shop.show', product.url_key)"
                                    class="text-xs sm:text-sm font-semibold text-gray-900 dark:text-white hover:text-primary transition mb-1 line-clamp-2 leading-tight min-h-[2rem]"
                                >
                                    {{ product.name }}
                                </Link>

                                <div class="mt-auto pt-2 flex flex-col gap-1.5 border-t border-gray-100 dark:border-gray-700/60">
                                    <div class="min-w-0">
                                        <span v-if="product.is_on_flash_sale || product.discount_price" class="text-[9px] sm:text-[10px] text-gray-400 line-through block leading-none">
                                            ₦{{ parseFloat(product.price).toLocaleString() }}
                                        </span>
                                        <span class="text-xs sm:text-sm font-black text-primary truncate block leading-tight">
                                            ₦{{ parseFloat(product.effective_price || product.discount_price || product.price).toLocaleString() }}
                                        </span>
                                    </div>

                                    <!-- Add to Cart or Quantity Controls -->
                                    <div v-if="cartItemsByProductId[product.id]" class="w-full inline-flex items-center justify-between border border-primary/40 bg-primary/5 dark:bg-primary/10 rounded-md overflow-hidden shadow-2xs h-7">
                                        <button
                                            type="button"
                                            @click.prevent="updateQuantity(cartItemsByProductId[product.id], cartItemsByProductId[product.id].quantity - 1)"
                                            :disabled="isUpdatingItem(cartItemsByProductId[product.id].id)"
                                            class="w-6 h-full flex items-center justify-center font-bold text-gray-700 dark:text-gray-200 hover:bg-primary hover:text-white transition disabled:opacity-50 text-xs cursor-pointer"
                                            title="Decrease quantity">
                                            -
                                        </button>
                                        <span class="px-1 text-center text-xs font-black text-primary">
                                            {{ cartItemsByProductId[product.id].quantity }}
                                        </span>
                                        <button
                                            type="button"
                                            @click.prevent="updateQuantity(cartItemsByProductId[product.id], cartItemsByProductId[product.id].quantity + 1)"
                                            :disabled="isUpdatingItem(cartItemsByProductId[product.id].id)"
                                            class="w-6 h-full flex items-center justify-center font-bold text-gray-700 dark:text-gray-200 hover:bg-primary hover:text-white transition disabled:opacity-50 text-xs cursor-pointer"
                                            title="Increase quantity">
                                            +
                                        </button>
                                    </div>
                                    <button
                                        v-else
                                        @click="addToCart(product)"
                                        :disabled="isAddingProduct(product.id)"
                                        class="w-full py-1 sm:py-1.5 rounded-md bg-primary hover:opacity-90 text-white text-[11px] sm:text-xs font-bold transition disabled:opacity-60 flex items-center justify-center gap-1 shadow-2xs cursor-pointer h-7"
                                    >
                                        <svg v-if="!isAddingProduct(product.id)" class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z"/>
                                        </svg>
                                        <svg v-else class="w-3 h-3 animate-spin" fill="none" viewBox="0 0 24 24">
                                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/>
                                        </svg>
                                        <span>{{ isAddingProduct(product.id) ? '...' : 'Add' }}</span>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Right Floating Arrow (desktop) -->
                    <button
                        type="button"
                        @click="scrollTrack(bestSellersTrackRef, 'right')"
                        class="hidden md:flex absolute -right-3.5 top-1/2 -translate-y-1/2 z-20 w-9 h-9 rounded-full bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 text-gray-700 dark:text-gray-200 shadow-md hover:bg-primary hover:text-white hover:border-primary items-center justify-center transition cursor-pointer opacity-0 group-hover:opacity-100"
                        aria-label="Next Products"
                    >
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 5l7 7-7 7" />
                        </svg>
                    </button>
                </div>
            </div>
        </section>

        <!-- Promotional Flyer / Mid-Page Banner -->
        <section
            v-if="($page.props.store_settings?.promo_banner_enabled === '1' || $page.props.store_settings?.promo_banner_enabled === true) && ($page.props.store_settings?.promo_banner_image || $page.props.store_settings?.promo_banner_title)"
            class="py-4 sm:py-6 bg-gray-50/60 dark:bg-gray-900/60 border-b border-gray-100 dark:border-gray-800"
        >
            <div class="container mx-auto px-3 sm:px-4">
                <component
                    :is="$page.props.store_settings.promo_banner_link ? ($page.props.store_settings.promo_banner_link.startsWith('http') ? 'a' : Link) : 'div'"
                    :href="$page.props.store_settings.promo_banner_link || undefined"
                    :target="$page.props.store_settings.promo_banner_link?.startsWith('http') ? '_blank' : undefined"
                    :rel="$page.props.store_settings.promo_banner_link?.startsWith('http') ? 'noopener noreferrer' : undefined"
                    class="group relative block w-full overflow-hidden rounded-xl sm:rounded-2xl shadow-sm hover:shadow-md transition-all duration-300 border border-gray-200/80 dark:border-gray-800"
                >
                    <!-- If Image is provided -->
                    <div v-if="$page.props.store_settings.promo_banner_image" class="relative w-full">
                        <img
                            :src="$page.props.store_settings.promo_banner_image.startsWith('http') || $page.props.store_settings.promo_banner_image.startsWith('/')
                                ? $page.props.store_settings.promo_banner_image
                                : '/storage/' + $page.props.store_settings.promo_banner_image"
                            :alt="$page.props.store_settings.promo_banner_title || 'Promotional Flyer'"
                            class="w-full h-auto min-h-[140px] max-h-[190px] xs:max-h-[240px] sm:max-h-[320px] md:max-h-[380px] lg:max-h-[440px] object-cover object-center group-hover:scale-[1.01] transition-transform duration-500"
                            loading="lazy"
                        />

                        <!-- Gradient overlay if title/subtitle or button exists -->
                        <div
                            v-if="$page.props.store_settings.promo_banner_title || $page.props.store_settings.promo_banner_subtitle || $page.props.store_settings.promo_banner_button_text"
                            class="absolute inset-0 bg-gradient-to-t sm:bg-gradient-to-r from-black/85 via-black/50 to-transparent flex items-center p-4 sm:p-8 md:p-10"
                        >
                            <div class="max-w-xl text-white">
                                <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-[10px] sm:text-xs font-black tracking-wider uppercase bg-primary text-white mb-2 shadow-sm">
                                    Special Offer
                                </span>
                                <h3 v-if="$page.props.store_settings.promo_banner_title" class="text-base xs:text-lg sm:text-2xl md:text-3xl lg:text-4xl font-extrabold text-white leading-tight drop-shadow-md mb-1.5 sm:mb-2">
                                    {{ $page.props.store_settings.promo_banner_title }}
                                </h3>
                                <p v-if="$page.props.store_settings.promo_banner_subtitle" class="text-xs sm:text-sm md:text-base text-gray-200 drop-shadow line-clamp-2 max-w-lg mb-3 sm:mb-4">
                                    {{ $page.props.store_settings.promo_banner_subtitle }}
                                </p>
                                <span
                                    v-if="$page.props.store_settings.promo_banner_button_text"
                                    class="inline-flex items-center gap-1.5 px-3.5 py-1.5 sm:px-5 sm:py-2.5 rounded-lg bg-primary hover:opacity-90 text-white text-xs sm:text-sm font-bold shadow-md transition-transform group-hover:translate-x-1"
                                >
                                    {{ $page.props.store_settings.promo_banner_button_text }}
                                    <svg class="w-3.5 h-3.5 sm:w-4 sm:h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3"/>
                                    </svg>
                                </span>
                            </div>
                        </div>
                    </div>

                    <!-- Fallback: Banner without image (styled gradient banner card) -->
                    <div
                        v-else
                        class="w-full bg-gradient-to-r from-primary via-primary/90 to-gray-900 text-white p-6 sm:p-10 flex flex-col sm:flex-row items-center justify-between gap-4"
                    >
                        <div class="max-w-xl text-center sm:text-left">
                            <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-[10px] sm:text-xs font-black tracking-wider uppercase bg-white/20 text-white mb-2 backdrop-blur-xs">
                                Special Promotion
                            </span>
                            <h3 v-if="$page.props.store_settings.promo_banner_title" class="text-xl sm:text-2xl md:text-3xl font-extrabold text-white leading-tight mb-2">
                                {{ $page.props.store_settings.promo_banner_title }}
                            </h3>
                            <p v-if="$page.props.store_settings.promo_banner_subtitle" class="text-sm md:text-base text-white/80 line-clamp-2">
                                {{ $page.props.store_settings.promo_banner_subtitle }}
                            </p>
                        </div>
                        <div v-if="$page.props.store_settings.promo_banner_button_text || $page.props.store_settings.promo_banner_link" class="shrink-0">
                            <span class="inline-flex items-center gap-2 px-5 py-2.5 rounded-lg bg-white text-gray-900 font-bold text-sm shadow hover:bg-gray-100 transition-transform group-hover:scale-105">
                                {{ $page.props.store_settings.promo_banner_button_text || 'Explore Deals' }}
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3"/>
                                </svg>
                            </span>
                        </div>
                    </div>
                </component>
            </div>
        </section>

        <!-- Most Searched Items (Horizontal Jumia-style carousel) -->
        <section v-if="most_searched && most_searched.length" class="py-6 sm:py-8 bg-white dark:bg-dark border-b border-gray-100 dark:border-gray-800">
            <div class="container mx-auto px-3 sm:px-4">
                <!-- Section Header -->
                <div class="flex items-center justify-between mb-3 sm:mb-4 pb-2 border-b border-gray-200/70 dark:border-gray-800">
                    <div class="flex items-center gap-2">
                        <span class="w-7 h-7 sm:w-8 sm:h-8 rounded-lg bg-blue-500/10 text-blue-500 dark:text-blue-400 flex items-center justify-center shrink-0">
                            <svg class="w-4 h-4 sm:w-5 sm:h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                            </svg>
                        </span>
                        <h2 class="text-base sm:text-xl md:text-2xl font-extrabold text-gray-900 dark:text-white tracking-tight">
                            Most Searched Items
                        </h2>
                        <span class="bg-blue-100 dark:bg-blue-900/40 text-blue-700 dark:text-blue-300 text-[10px] sm:text-xs font-black px-2 py-0.5 rounded-full uppercase tracking-wider hidden xs:inline-flex items-center gap-1">
                            🔍 Trending
                        </span>
                    </div>

                    <div class="flex items-center gap-2 sm:gap-3">
                        <Link :href="route('shop.index', { collection: 'most-searched' })" class="text-primary hover:opacity-80 font-bold text-xs sm:text-sm flex items-center gap-1 group">
                            <span>See All</span>
                            <span class="transition-transform group-hover:translate-x-0.5">→</span>
                        </Link>
                        <!-- Carousel Prev / Next Controls in Header -->
                        <div class="flex items-center gap-1">
                            <button
                                type="button"
                                @click="scrollTrack(mostSearchedTrackRef, 'left')"
                                class="w-7 h-7 sm:w-8 sm:h-8 rounded-full bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 hover:bg-primary hover:text-white hover:border-primary text-gray-700 dark:text-gray-200 flex items-center justify-center transition shadow-2xs cursor-pointer"
                                aria-label="Previous Most Searched Products"
                            >
                                <svg class="w-3.5 h-3.5 sm:w-4 sm:h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M15 19l-7-7 7-7" />
                                </svg>
                            </button>
                            <button
                                type="button"
                                @click="scrollTrack(mostSearchedTrackRef, 'right')"
                                class="w-7 h-7 sm:w-8 sm:h-8 rounded-full bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 hover:bg-primary hover:text-white hover:border-primary text-gray-700 dark:text-gray-200 flex items-center justify-center transition shadow-2xs cursor-pointer"
                                aria-label="Next Most Searched Products"
                            >
                                <svg class="w-3.5 h-3.5 sm:w-4 sm:h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 5l7 7-7 7" />
                                </svg>
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Trending Keywords Cloud Pills (if available) -->
                <div v-if="top_search_keywords && top_search_keywords.length" class="flex flex-wrap items-center gap-1.5 mb-3">
                    <span class="text-[11px] font-semibold text-gray-400 uppercase tracking-wider mr-1">Trending:</span>
                    <Link
                        v-for="kw in top_search_keywords"
                        :key="kw"
                        :href="route('shop.index', { search: kw })"
                        class="px-2.5 py-1 rounded-full text-xs font-semibold bg-gray-100 hover:bg-primary/10 text-gray-700 dark:text-gray-300 hover:text-primary dark:bg-gray-800 transition border border-gray-200 dark:border-gray-700"
                    >
                        #{{ kw }}
                    </Link>
                </div>

                <!-- Carousel Track with Side Floating Buttons -->
                <div class="relative group">
                    <!-- Left Floating Arrow (desktop) -->
                    <button
                        type="button"
                        @click="scrollTrack(mostSearchedTrackRef, 'left')"
                        class="hidden md:flex absolute -left-3.5 top-1/2 -translate-y-1/2 z-20 w-9 h-9 rounded-full bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 text-gray-700 dark:text-gray-200 shadow-md hover:bg-primary hover:text-white hover:border-primary items-center justify-center transition cursor-pointer opacity-0 group-hover:opacity-100"
                        aria-label="Previous Products"
                    >
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M15 19l-7-7 7-7" />
                        </svg>
                    </button>

                    <!-- Scrollable Container -->
                    <div
                        ref="mostSearchedTrackRef"
                        class="flex gap-2.5 sm:gap-3.5 overflow-x-auto scroll-smooth scrollbar-hide pb-2 pt-0.5"
                    >
                        <div
                            v-for="product in most_searched"
                            :key="product.id"
                            class="w-[145px] sm:w-[170px] md:w-[190px] shrink-0 bg-white dark:bg-gray-800 rounded-lg sm:rounded-xl shadow-2xs hover:shadow-md transition-all duration-300 border border-gray-100 dark:border-gray-700/80 group/card flex flex-col overflow-hidden"
                        >
                            <!-- Image Box -->
                            <div class="relative overflow-hidden h-28 sm:h-36 md:h-40 shrink-0 bg-gray-100 dark:bg-gray-700">
                                <Link :href="route('shop.show', product.url_key)" class="block w-full h-full">
                                    <img
                                        :src="product.image ? '/storage/' + product.image : 'https://placehold.co/400x400'"
                                        class="w-full h-full object-cover transform group-hover/card:scale-105 transition duration-500"
                                        :alt="product.name"
                                    />
                                </Link>
                                <!-- Badges -->
                                <div v-if="product.is_on_flash_sale" class="absolute top-1.5 left-1.5 z-10 bg-amber-500 text-white text-[8px] sm:text-[9px] font-black px-1.5 py-0.5 rounded shadow-xs">
                                    ⚡ FLASH
                                </div>
                                <div v-else-if="product.discount_price" class="absolute top-1.5 left-1.5 z-10 bg-red-500 text-white text-[8px] sm:text-[9px] font-bold px-1.5 py-0.5 rounded shadow-xs">
                                    SALE
                                </div>
                                <div v-else class="absolute top-1.5 left-1.5 z-10 bg-blue-500 text-white text-[8px] sm:text-[9px] font-extrabold px-1.5 py-0.5 rounded shadow-xs">
                                    🔍 POPULAR
                                </div>
                            </div>

                            <!-- Content -->
                            <div class="p-2 sm:p-3 flex flex-col flex-grow">
                                <span class="text-[9px] sm:text-[10px] text-gray-400 dark:text-gray-500 uppercase tracking-wider mb-0.5 truncate">
                                    {{ product.category?.name || 'General' }}
                                </span>
                                <Link
                                    :href="route('shop.show', product.url_key)"
                                    class="text-xs sm:text-sm font-semibold text-gray-900 dark:text-white hover:text-primary transition mb-1 line-clamp-2 leading-tight min-h-[2rem]"
                                >
                                    {{ product.name }}
                                </Link>

                                <div class="mt-auto pt-2 flex flex-col gap-1.5 border-t border-gray-100 dark:border-gray-700/60">
                                    <div class="min-w-0">
                                        <span v-if="product.is_on_flash_sale || product.discount_price" class="text-[9px] sm:text-[10px] text-gray-400 line-through block leading-none">
                                            ₦{{ parseFloat(product.price).toLocaleString() }}
                                        </span>
                                        <span class="text-xs sm:text-sm font-black text-primary truncate block leading-tight">
                                            ₦{{ parseFloat(product.effective_price || product.discount_price || product.price).toLocaleString() }}
                                        </span>
                                    </div>

                                    <!-- Add to Cart or Quantity Controls -->
                                    <div v-if="cartItemsByProductId[product.id]" class="w-full inline-flex items-center justify-between border border-primary/40 bg-primary/5 dark:bg-primary/10 rounded-md overflow-hidden shadow-2xs h-7">
                                        <button
                                            type="button"
                                            @click.prevent="updateQuantity(cartItemsByProductId[product.id], cartItemsByProductId[product.id].quantity - 1)"
                                            :disabled="isUpdatingItem(cartItemsByProductId[product.id].id)"
                                            class="w-6 h-full flex items-center justify-center font-bold text-gray-700 dark:text-gray-200 hover:bg-primary hover:text-white transition disabled:opacity-50 text-xs cursor-pointer"
                                            title="Decrease quantity">
                                            -
                                        </button>
                                        <span class="px-1 text-center text-xs font-black text-primary">
                                            {{ cartItemsByProductId[product.id].quantity }}
                                        </span>
                                        <button
                                            type="button"
                                            @click.prevent="updateQuantity(cartItemsByProductId[product.id], cartItemsByProductId[product.id].quantity + 1)"
                                            :disabled="isUpdatingItem(cartItemsByProductId[product.id].id)"
                                            class="w-6 h-full flex items-center justify-center font-bold text-gray-700 dark:text-gray-200 hover:bg-primary hover:text-white transition disabled:opacity-50 text-xs cursor-pointer"
                                            title="Increase quantity">
                                            +
                                        </button>
                                    </div>
                                    <button
                                        v-else
                                        @click="addToCart(product)"
                                        :disabled="isAddingProduct(product.id)"
                                        class="w-full py-1 sm:py-1.5 rounded-md bg-primary hover:opacity-90 text-white text-[11px] sm:text-xs font-bold transition disabled:opacity-60 flex items-center justify-center gap-1 shadow-2xs cursor-pointer h-7"
                                    >
                                        <svg v-if="!isAddingProduct(product.id)" class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z"/>
                                        </svg>
                                        <svg v-else class="w-3 h-3 animate-spin" fill="none" viewBox="0 0 24 24">
                                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/>
                                        </svg>
                                        <span>{{ isAddingProduct(product.id) ? '...' : 'Add' }}</span>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Right Floating Arrow (desktop) -->
                    <button
                        type="button"
                        @click="scrollTrack(mostSearchedTrackRef, 'right')"
                        class="hidden md:flex absolute -right-3.5 top-1/2 -translate-y-1/2 z-20 w-9 h-9 rounded-full bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 text-gray-700 dark:text-gray-200 shadow-md hover:bg-primary hover:text-white hover:border-primary items-center justify-center transition cursor-pointer opacity-0 group-hover:opacity-100"
                        aria-label="Next Products"
                    >
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 5l7 7-7 7" />
                        </svg>
                    </button>
                </div>
            </div>
        </section>

        <!-- Features Bar -->
        <section class="border-t dark:border-gray-800 bg-white dark:bg-dark py-12">
            <div class="container mx-auto px-4">
                <div class="grid grid-cols-1 md:grid-cols-3 gap-8 divide-y md:divide-y-0 md:divide-x dark:divide-gray-800">
                    <div class="p-6 text-center flex flex-col items-center">
                        <div class="w-16 h-16 bg-primary/10 text-primary rounded-full flex items-center justify-center mb-4">
                            <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"></path></svg>
                        </div>
                        <h3 class="text-lg font-bold mb-2">{{ $page.props.store_settings.feature_1_title || 'Secure Payments' }}</h3>
                        <p class="text-sm text-gray-500 dark:text-gray-400">{{ $page.props.store_settings.feature_1_desc || '100% secure checkout via Paystack & Flutterwave.' }}</p>
                    </div>
                    <div class="p-6 text-center flex flex-col items-center">
                        <div class="w-16 h-16 bg-emerald-50 dark:bg-emerald-900/30 text-emerald-600 dark:text-emerald-400 rounded-full flex items-center justify-center mb-4">
                            <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path></svg>
                        </div>
                        <h3 class="text-lg font-bold mb-2">{{ $page.props.store_settings.feature_2_title || 'Fast Checkout' }}</h3>
                        <p class="text-sm text-gray-500 dark:text-gray-400">{{ $page.props.store_settings.feature_2_desc || 'Seamless integration and instantaneous validation.' }}</p>
                    </div>
                    <div class="p-6 text-center flex flex-col items-center">
                        <div class="w-16 h-16 bg-purple-50 dark:bg-purple-900/30 text-purple-600 dark:text-purple-400 rounded-full flex items-center justify-center mb-4">
                            <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18.364 5.636l-3.536 3.536m0 5.656l3.536 3.536M9.172 9.172L5.636 5.636m3.536 9.192l-3.536 3.536M21 12a9 9 0 11-18 0 9 9 0 0118 0zm-5 0a4 4 0 11-8 0 4 4 0 018 0z"></path></svg>
                        </div>
                        <h3 class="text-lg font-bold mb-2">{{ $page.props.store_settings.feature_3_title || 'Premium Support' }}</h3>
                        <p class="text-sm text-gray-500 dark:text-gray-400">{{ $page.props.store_settings.feature_3_desc || 'Priority technical response round the clock 24/7.' }}</p>
                    </div>
                </div>
            </div>
        </section>
        <Footer />
    </Layout>
</template>
