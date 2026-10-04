<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import Layout from '@/Layouts/Layout.vue';
import { ref, reactive, onMounted, onUnmounted } from 'vue';
import { useToast } from 'vue-toastification';
import Footer from '@/Components/Footer.vue';

const props = defineProps({
    featured_products: Array,
    new_arrivals: Array,
    home_categories: Array,
    sliders: {
        type: Array,
        default: () => []
    }
});

const toast = useToast();
const addingToCart = reactive({});

const addToCart = (product) => {
    addingToCart[product.id] = true;
    useForm({ product_id: product.id, quantity: 1 }).post(route('cart.store'), {
        preserveScroll: true,
        onSuccess: () => toast.success(`${product.name} added to cart!`),
        onError:   () => toast.error('Could not add to cart.'),
        onFinish:  () => { addingToCart[product.id] = false; },
    });
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
    if (props.sliders?.length > 1) {
        slideInterval = setInterval(nextSlide, 5000);
    }
});

onUnmounted(() => {
    if (slideInterval) clearInterval(slideInterval);
});
</script>

<template>
    <Head :title="'Welcome to ' + ($page.props.store_settings.company_name || 'Skynet Digital Store')" />
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
                            <Link v-if="slider.button_text && slider.button_link" :href="slider.button_link" class="inline-block bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-6 sm:py-3 sm:px-8 md:py-4 md:px-10 text-sm sm:text-base rounded-full shadow-2xl transition-all duration-300 transform hover:-translate-y-1">
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
                    <span class="text-blue-600 dark:text-blue-400 font-bold tracking-wider uppercase text-sm mb-4 block">
                        {{ $page.props.store_settings.hero_badge || 'New Collection 2026' }}
                    </span>
                    <h1 class="text-5xl md:text-6xl font-extrabold text-gray-900 dark:text-white leading-tight mb-6">
                        {{ $page.props.store_settings.hero_title || 'Discover Digital' }} <br>
                        <span class="text-transparent bg-clip-text bg-gradient-to-r from-blue-600 to-indigo-600">
                            {{ $page.props.store_settings.hero_title_highlight || 'Excellence' }}
                        </span>
                    </h1>
                    <p class="text-xl text-gray-600 dark:text-gray-300 mb-8 max-w-lg">
                        {{ $page.props.store_settings.hero_subtitle || 'Shop premium products, authentic brands, and quality gear built for your lifestyle.' }}
                    </p>
                    <div class="flex gap-4">
                        <Link :href="route('shop.index')" class="bg-blue-600 hover:bg-blue-700 text-white font-semibold py-3 px-8 rounded-lg shadow-lg hover:shadow-xl transition-all duration-300 transform hover:-translate-y-1">
                            {{ $page.props.store_settings.hero_cta_primary || 'Shop Now' }}
                        </Link>
                        <Link :href="route('shop.index')" class="bg-white dark:bg-gray-800 hover:bg-gray-50 dark:hover:bg-gray-700 text-gray-900 dark:text-white border border-gray-200 dark:border-gray-700 font-semibold py-3 px-8 rounded-lg transition-all duration-300 shadow-sm">
                            {{ $page.props.store_settings.hero_cta_secondary || 'Explore Categories' }}
                        </Link>
                    </div>
                </div>
                <div class="lg:w-1/2 relative" data-aos="fade-left">
                    <div class="absolute inset-0 bg-blue-400 opacity-20 blur-[100px] rounded-full"></div>
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
        <section class="py-12 md:py-20 bg-white dark:bg-dark container mx-auto px-4">
            <div class="flex flex-col sm:flex-row justify-between items-start sm:items-end mb-8 md:mb-10 gap-2">
                <div>
                    <h2 class="text-2xl md:text-3xl font-bold text-gray-900 dark:text-white">Shop by Category</h2>
                    <p class="text-sm md:text-base text-gray-500 dark:text-gray-400 mt-2">Find exactly what you need quickly</p>
                </div>
            </div>
            <div class="grid grid-cols-2 md:grid-cols-4 gap-3 sm:gap-6">
                <Link :href="route('shop.index', {category: category.slug})" v-for="category in home_categories" :key="category.id" class="group relative overflow-hidden rounded-xl aspect-[4/3] sm:aspect-square shadow-sm border dark:border-gray-800">
                    <div class="absolute inset-0 bg-gradient-to-t from-gray-900/80 via-gray-900/30 to-transparent opacity-80 group-hover:opacity-100 transition-opacity z-10"></div>
                    <img :src="category.image ? '/storage/' + category.image : 'https://source.unsplash.com/random/400x400/?'+category.name" class="w-full h-full object-cover transform group-hover:scale-110 transition duration-700" :alt="category.name" onerror="this.src='https://placehold.co/400x400'"/>
                    <div class="absolute inset-0 z-20 flex items-end justify-center p-4">
                        <h3 class="text-white text-base sm:text-xl md:text-2xl font-bold tracking-wide text-center drop-shadow-lg">{{ category.name }}</h3>
                    </div>
                </Link>
            </div>
        </section>

        <!-- Featured Products -->
        <section class="py-20 bg-gray-50 dark:bg-gray-900 border-y dark:border-gray-800">
            <div class="container mx-auto px-4">
                <div class="text-center mb-16">
                    <h2 class="text-3xl md:text-4xl font-bold text-gray-900 dark:text-white mb-4">Featured Products</h2>
                    <div class="w-24 h-1 bg-blue-600 mx-auto rounded"></div>
                </div>
                
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-8">
                    <div v-for="product in featured_products" :key="product.id" class="bg-white dark:bg-gray-800 rounded-xl shadow-sm hover:shadow-xl transition-all duration-300 border border-gray-100 dark:border-gray-700 group flex flex-col h-full">
                        <div class="relative overflow-hidden rounded-t-xl shrink-0">
                            <!-- Overlay actions -->
                            <div class="absolute inset-0 bg-black/40 opacity-0 group-hover:opacity-100 transition-opacity duration-300 z-10 flex items-center justify-center gap-4">
                                <Link :href="route('shop.show', product.url_key)" class="w-12 h-12 bg-white text-gray-900 flex items-center justify-center rounded-full hover:bg-blue-600 hover:text-white transition transform translate-y-4 group-hover:translate-y-0 duration-300">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path></svg>
                                </Link>
                            </div>
                            <!-- Image -->
                            <img :src="product.image ? '/storage/' + product.image : 'https://placehold.co/400x400'" class="w-full h-64 object-cover transform group-hover:scale-105 transition duration-500" :alt="product.name" />
                            <div v-if="product.discount_price" class="absolute top-4 left-4 z-20 bg-red-500 text-white text-xs font-bold px-3 py-1 rounded-full shadow-lg">SALE</div>
                        </div>
                        <div class="p-6 flex flex-col flex-grow">
                            <span class="text-xs text-gray-500 dark:text-gray-400 uppercase tracking-wider mb-2">{{ product.category?.name }}</span>
                            <Link :href="route('shop.show', product.url_key)" class="text-lg font-bold text-gray-900 dark:text-white hover:text-blue-600 dark:hover:text-blue-400 transition mb-2 line-clamp-2">{{ product.name }}</Link>
                            <div class="mt-auto pt-4 flex items-center justify-between border-t border-gray-100 dark:border-gray-700">
                                <div class="flex flex-col">
                                    <span v-if="product.discount_price" class="text-xs text-gray-400 line-through">₦{{ parseFloat(product.price).toLocaleString() }}</span>
                                    <span class="text-xl font-extrabold text-blue-600 dark:text-blue-400">
                                        ₦{{ product.discount_price ? parseFloat(product.discount_price).toLocaleString() : parseFloat(product.price).toLocaleString() }}
                                    </span>
                                </div>
                                <button @click="addToCart(product)" :disabled="addingToCart[product.id]" class="w-10 h-10 rounded-full bg-gray-100 dark:bg-gray-700 text-gray-600 dark:text-gray-300 hover:bg-blue-600 hover:text-white transition flex items-center justify-center disabled:opacity-60">
                                    <svg v-if="!addingToCart[product.id]" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z"/></svg>
                                    <svg v-else class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- New Arrivals -->
        <section class="py-20 bg-white dark:bg-dark container mx-auto px-4">
            <div class="flex justify-between items-end mb-10 border-b pb-4 dark:border-gray-800">
                <div>
                    <h2 class="text-3xl font-bold text-gray-900 dark:text-white">New Arrivals</h2>
                    <p class="text-gray-500 dark:text-gray-400 mt-2">The latest trends and tech</p>
                </div>
                <Link :href="route('shop.index')" class="hidden md:inline-flex items-center text-blue-600 dark:text-blue-400 hover:text-blue-800 dark:hover:text-blue-300 font-semibold group">
                    View All
                    <svg class="w-4 h-4 ml-2 transform group-hover:translate-x-1 transition" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path></svg>
                </Link>
            </div>
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-8">
                <div v-for="product in new_arrivals" :key="product.id" class="bg-gray-50 dark:bg-gray-800/50 rounded-xl overflow-hidden hover:shadow-lg transition-all duration-300 border border-gray-100 dark:border-gray-700 group flex flex-col">
                    <div class="relative overflow-hidden h-56 shrink-0">
                        <img :src="product.image ? '/storage/' + product.image : 'https://placehold.co/400x400'" class="w-full h-full object-cover transform group-hover:scale-105 transition duration-700" :alt="product.name" />
                        <div class="absolute top-4 left-4 z-20 bg-emerald-500 text-white text-xs font-bold px-3 py-1 rounded shadow-lg">NEW</div>
                    </div>
                    <div class="p-5 flex flex-col flex-grow">
                        <Link :href="route('shop.show', product.url_key)" class="text-md font-bold text-gray-900 dark:text-white hover:text-blue-600 dark:hover:text-blue-400 transition mb-2 line-clamp-1">{{ product.name }}</Link>
                        <div class="mt-auto flex items-center justify-between">
                            <span class="text-lg font-bold text-gray-900 dark:text-white">₦{{ parseFloat(product.price).toLocaleString() }}</span>
                            <button @click="addToCart(product)" :disabled="addingToCart[product.id]" class="text-sm font-semibold bg-blue-600 hover:bg-blue-700 text-white px-3 py-1.5 rounded-lg transition disabled:opacity-60 flex items-center gap-1">
                                <svg v-if="!addingToCart[product.id]" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z"/></svg>
                                <svg v-else class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
                                {{ addingToCart[product.id] ? '...' : 'Add' }}
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- Features Bar -->
        <section class="border-t dark:border-gray-800 bg-white dark:bg-dark py-12">
            <div class="container mx-auto px-4">
                <div class="grid grid-cols-1 md:grid-cols-3 gap-8 divide-y md:divide-y-0 md:divide-x dark:divide-gray-800">
                    <div class="p-6 text-center flex flex-col items-center">
                        <div class="w-16 h-16 bg-blue-50 dark:bg-blue-900/30 text-blue-600 dark:text-blue-400 rounded-full flex items-center justify-center mb-4">
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
