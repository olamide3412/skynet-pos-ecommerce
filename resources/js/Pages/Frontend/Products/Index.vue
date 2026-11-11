<script setup>
import { Head, Link, useForm, router, usePage } from '@inertiajs/vue3';
import Layout from '@/Layouts/Layout.vue';
import Pagination from '@/Components/Pagination.vue';
import StorefrontBreadcrumb from '@/Components/StorefrontBreadcrumb.vue';
import { ref, watch, reactive, computed, onMounted } from 'vue';
import debounce from 'lodash/debounce';
import { useToast } from 'vue-toastification';
import { useCart } from '@/Composables/useCart';

const props = defineProps({
    products: Object,
    categories: Array,
    brands: {
        type: Array,
        default: () => [],
    },
    current_brand: {
        type: Object,
        default: null,
    },
    filters: Object,
    wishlist_ids: {
        type: Array,
        default: () => [],
    },
});

const page = usePage();
const toast = useToast();
const search     = ref(props.filters.search || '');
const category   = ref(props.filters.category || '');
const brand      = ref(props.filters.brand || (props.current_brand ? props.current_brand.slug : ''));
const collection = ref(props.filters.collection || '');
const sort       = ref(props.filters.sort || 'default');
const attrs      = ref(props.filters.attrs || {});
const wishlistIds = ref(props.wishlist_ids ?? []);
const togglingWishlist = reactive({});

const collectionTitle = computed(() => {
    if (collection.value === 'flash-sales' || collection.value === 'flash_sales' || collection.value === 'flash_sale') return 'Flash Sales';
    if (collection.value === 'featured') return 'Featured Products';
    if (collection.value === 'best-sellers' || collection.value === 'best_sellers') return 'Best Sellers';
    if (collection.value === 'new-arrivals' || collection.value === 'new_arrivals') return 'New Arrivals';
    if (collection.value === 'most-searched' || collection.value === 'most_searched') return 'Most Searched';
    return '';
});

const sortLabel = computed(() => {
    switch (sort.value) {
        case 'name_asc': return 'A to Z';
        case 'name_desc': return 'Z to A';
        case 'price_asc': return 'Price: Low to High';
        case 'price_desc': return 'Price: High to Low';
        case 'newest': return 'Newest Arrivals';
        case 'popular':
        case 'best_selling': return 'Best Selling';
        default: return '';
    }
});

const {
    cartItemsByProductId,
    addToCart,
    updateQuantity,
    isUpdatingItem,
    isAddingProduct
} = useCart();

const wishlistEnabled = computed(() => page.props.store_settings?.wishlist_enabled === '1');

// Dynamic attributes for the selected category
// Now categories is a tree, so search parents + children
const allCategoriesFlat = computed(() => {
    const flat = [];
    for (const parent of (props.categories ?? [])) {
        flat.push(parent);
        if (parent.children?.length) flat.push(...parent.children);
    }
    return flat;
});

const activeCategoryParams = computed(() => {
    return allCategoriesFlat.value.find(c => c.slug === category.value) || null;
});
const activeAttributes = computed(() => activeCategoryParams.value?.attributes || []);

// Debounced search + category filter + brand + attributes + collection + sort -> router call
const applyFilters = debounce(() => {
    // Filter out empty/null values
    const cleanAttrs = Object.fromEntries(
        Object.entries(attrs.value).filter(([_, v]) => v !== '' && v !== null)
    );

    router.get(route('shop.index'), {
        search:     search.value || undefined,
        category:   category.value || undefined,
        brand:      brand.value || undefined,
        collection: collection.value || undefined,
        sort:       (sort.value && sort.value !== 'default') ? sort.value : undefined,
        attrs:      Object.keys(cleanAttrs).length ? cleanAttrs : undefined,
    }, { preserveState: true, replace: true });
}, 300);

watch(search, applyFilters);
watch(collection, applyFilters);
watch(sort, applyFilters);
watch(brand, applyFilters);
watch(category, () => {
    attrs.value = {}; // Reset attributes when category changes
    applyFilters();
});
watch(() => attrs.value, applyFilters, { deep: true });



const comparing = reactive({});
const addToCompare = (product) => {
    comparing[product.id] = true;
    useForm({ product_id: product.id }).post(route('compare.store'), {
        preserveScroll: true,
        onError: () => toast.error('Could not add to compare list. It might be full.'),
        onFinish: () => { comparing[product.id] = false; },
    });
};

const isWishlisted = (id) => wishlistIds.value.includes(id);

const toggleWishlist = (product) => {
    if (!page.props.auth.customer) {
        router.visit(route('login'));
        return;
    }
    togglingWishlist[product.id] = true;
    useForm({ product_id: product.id }).post(route('wishlist.toggle'), {
        preserveScroll: true,
        onSuccess: () => {
            if (isWishlisted(product.id)) {
                wishlistIds.value = wishlistIds.value.filter(id => id !== product.id);
            } else {
                wishlistIds.value.push(product.id);
            }
        },
        onError: () => toast.error('Could not update wishlist.'),
        onFinish: () => { togglingWishlist[product.id] = false; },
    });
};

const showMobileFilters = ref(false);
const showAllCategories = ref(false);
const showAllBrands     = ref(false);

const activeBrandObj = computed(() => {
    if (props.current_brand && (!brand.value || brand.value === props.current_brand.slug)) {
        return props.current_brand;
    }
    if (!brand.value) return null;
    return (props.brands || []).find(b => b.slug === brand.value) || null;
});

const visibleBrands = computed(() => {
    const list = props.brands || [];
    if (showAllBrands.value || list.length <= 10) return list;
    return list.slice(0, 10);
});

// Auto-expand categories if the selected category is beyond the first 7
onMounted(() => {
    if (category.value && props.categories?.length > 7) {
        const idx = props.categories.findIndex(p => p.slug === category.value || p.children?.some(c => c.slug === category.value));
        if (idx >= 7) {
            showAllCategories.value = true;
        }
    }
    if (brand.value && props.brands?.length > 10) {
        const bIdx = props.brands.findIndex(b => b.slug === brand.value);
        if (bIdx >= 10) {
            showAllBrands.value = true;
        }
    }
});

const visibleCategories = computed(() => {
    if (showAllCategories.value || !props.categories || props.categories.length <= 7) {
        return props.categories || [];
    }
    return props.categories.slice(0, 7);
});

const breadcrumbs = computed(() => {
    const crumbs = [];
    crumbs.push({ label: 'All Products', url: route('shop.index') });

    if (collectionTitle.value) {
        if (category.value && allCategoriesFlat.value.length) {
            crumbs.push({ label: collectionTitle.value, url: route('shop.index', { collection: collection.value }) });
            const cat = allCategoriesFlat.value.find(c => c.slug === category.value);
            if (cat) crumbs.push({ label: cat.name });
            return crumbs;
        }
        crumbs.push({ label: collectionTitle.value });
        return crumbs;
    }

    if (category.value && allCategoriesFlat.value.length) {
        const cat = allCategoriesFlat.value.find(c => c.slug === category.value);
        if (cat) {
            if (cat.parent_id) {
                const parent = props.categories?.find(p => p.id === cat.parent_id);
                if (parent) crumbs.push({ label: parent.name, url: route('shop.index', { category: parent.slug }) });
            }
            crumbs.push({ label: cat.name });
            return crumbs;
        }
    }
    if (activeBrandObj.value) {
        crumbs.push({ label: `Brand: ${activeBrandObj.value.name}` });
        return crumbs;
    }
    return [{ label: 'All Products' }];
});

// Dynamic SEO
const seoTitle = computed(() => {
    const store = page.props.store_settings?.company_name || 'Skynet';
    if (activeBrandObj.value) {
        if (activeBrandObj.value.meta_title) return `${activeBrandObj.value.meta_title} | ${store}`;
        return `${activeBrandObj.value.name} Products — Shop Online | ${store}`;
    }
    if (collectionTitle.value) {
        if (category.value) {
            const cat = props.categories?.find(c => c.slug === category.value);
            if (cat) return `${collectionTitle.value} — ${cat.name} | ${store}`;
        }
        return `${collectionTitle.value} — Shop Online | ${store}`;
    }
    if (category.value) {
        const cat = props.categories?.find(c => c.slug === category.value);
        if (cat) return `${cat.name} — Shop Online | ${store}`;
    }
    if (search.value) {
        return `Search: "${search.value}" | ${store}`;
    }
    return `Shop Products & Collections | ${store}`;
});

const seoDescription = computed(() => {
    const store = page.props.store_settings?.company_name || 'Skynet';
    if (activeBrandObj.value) {
        if (activeBrandObj.value.meta_description) return activeBrandObj.value.meta_description;
        if (activeBrandObj.value.description) return activeBrandObj.value.description;
        return `Shop authentic ${activeBrandObj.value.name} products online at ${store}. Fast nationwide delivery.`;
    }
    if (collectionTitle.value) {
        return `Browse ${collectionTitle.value.toLowerCase()} with exclusive prices at ${store}. Nationwide delivery across Nigeria.`;
    }
    if (category.value) {
        const cat = props.categories?.find(c => c.slug === category.value);
        if (cat) return `Browse quality ${cat.name} products at unbeatable prices at ${store}. Fast delivery across Nigeria.`;
    }
    return `Discover authentic, high-quality products on ${store}. Enjoy secure checkout, nationwide delivery, and warranty support.`;
});
</script>

<template>
    <Head>
        <title>{{ seoTitle }}</title>
        <meta name="description" :content="seoDescription" />
        <meta property="og:title" :content="seoTitle" />
        <meta property="og:description" :content="seoDescription" />
        <meta property="og:type" content="website" />
        <meta name="twitter:card" content="summary" />
        <meta name="twitter:title" :content="seoTitle" />
        <meta name="twitter:description" :content="seoDescription" />
    </Head>
    <Layout>
        <div class="container mx-auto px-4 pt-4 pb-8">
            <StorefrontBreadcrumb :items="breadcrumbs" />
            <div class="flex flex-col lg:flex-row gap-8">
                
                <!-- ─── Mobile Filter Overlay ────────────────────── -->
                <div v-if="showMobileFilters" 
                    @click="showMobileFilters = false" 
                    class="fixed inset-0 bg-black/50 z-40 lg:hidden">
                </div>

                <!-- ─── Sidebar (Filters) ────────────────────── -->
                <aside :class="[
                    'fixed inset-y-0 left-0 z-50 w-72 bg-white dark:bg-gray-900 shadow-2xl lg:shadow-none lg:border-none p-5 lg:p-0 overflow-y-auto transition-transform duration-300',
                    'lg:static lg:w-64 lg:flex-shrink-0 lg:block lg:bg-transparent dark:lg:bg-transparent lg:z-auto lg:overflow-visible lg:translate-x-0',
                    showMobileFilters ? 'translate-x-0' : '-translate-x-full'
                ]">
                    <div class="flex items-center justify-between lg:hidden mb-6">
                        <h2 class="font-bold text-xl">Filters</h2>
                        <button @click="showMobileFilters = false" class="text-gray-500 hover:text-gray-800 dark:hover:text-white text-xl leading-none">&times;</button>
                    </div>

                    <div class="lg:sticky lg:top-24 space-y-6">
                        <!-- Sort By Menu in Sidebar -->
                        <div class="bg-white dark:bg-gray-800 p-5 rounded-xl shadow-sm border border-gray-100 dark:border-gray-700">
                            <h3 class="font-bold mb-3 uppercase text-xs tracking-wider text-gray-500 dark:text-gray-400">Sort Products</h3>
                            <div class="relative">
                                <select
                                    v-model="sort"
                                    @change="showMobileFilters = false"
                                    class="w-full py-2.5 pl-3 pr-8 rounded-lg border border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-900 text-xs sm:text-sm font-semibold dark:text-white focus:ring-2 focus:ring-primary focus:border-primary cursor-pointer transition appearance-none"
                                >
                                    <option value="default">Default / Recommended</option>
                                    <option value="name_asc">Name: A to Z</option>
                                    <option value="name_desc">Name: Z to A</option>
                                    <option value="price_asc">Price: Low to High</option>
                                    <option value="price_desc">Price: High to Low</option>
                                    <option value="newest">Newest Arrivals</option>
                                    <option value="popular">Best Selling / Popular</option>
                                </select>
                                <div class="pointer-events-none absolute inset-y-0 right-0 flex items-center px-2.5 text-gray-400">
                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                                    </svg>
                                </div>
                            </div>
                        </div>

                        <!-- Curated Collections Menu -->
                        <div class="bg-white dark:bg-gray-800 p-5 rounded-xl shadow-sm border border-gray-100 dark:border-gray-700">
                            <h3 class="font-bold mb-3 uppercase text-xs tracking-wider text-gray-500 dark:text-gray-400">Collections</h3>
                            <ul class="space-y-1 text-sm">
                                <li>
                                    <button @click="collection = ''; showMobileFilters = false"
                                        class="text-left w-full px-2 py-1.5 rounded-lg transition font-medium text-xs sm:text-sm cursor-pointer"
                                        :class="!collection ? 'bg-primary/10 font-bold text-primary' : 'text-gray-600 dark:text-gray-400 hover:bg-gray-100 dark:hover:bg-gray-700 hover:text-primary'">
                                        📦 All Products
                                    </button>
                                </li>
                                <li>
                                    <button @click="collection = 'featured'; showMobileFilters = false"
                                        class="text-left w-full px-2 py-1.5 rounded-lg transition font-medium text-xs sm:text-sm flex items-center justify-between cursor-pointer"
                                        :class="collection === 'featured' ? 'bg-primary/10 font-bold text-primary' : 'text-gray-600 dark:text-gray-400 hover:bg-gray-100 dark:hover:bg-gray-700 hover:text-primary'">
                                        <span>★ Featured</span>
                                        <span class="text-[10px] uppercase font-bold text-primary">Curated</span>
                                    </button>
                                </li>
                                <li>
                                    <button @click="collection = 'best-sellers'; showMobileFilters = false"
                                        class="text-left w-full px-2 py-1.5 rounded-lg transition font-medium text-xs sm:text-sm flex items-center justify-between cursor-pointer"
                                        :class="collection === 'best-sellers' || collection === 'best_sellers' ? 'bg-amber-100 text-amber-800 dark:bg-amber-900/40 dark:text-amber-300 font-bold' : 'text-gray-600 dark:text-gray-400 hover:bg-gray-100 dark:hover:bg-gray-700 hover:text-primary'">
                                        <span>🔥 Best Sellers</span>
                                        <span class="text-[10px] uppercase font-bold text-amber-600 dark:text-amber-400">Popular</span>
                                    </button>
                                </li>
                                <li>
                                    <button @click="collection = 'new-arrivals'; showMobileFilters = false"
                                        class="text-left w-full px-2 py-1.5 rounded-lg transition font-medium text-xs sm:text-sm flex items-center justify-between cursor-pointer"
                                        :class="collection === 'new-arrivals' || collection === 'new_arrivals' ? 'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/40 dark:text-emerald-300 font-bold' : 'text-gray-600 dark:text-gray-400 hover:bg-gray-100 dark:hover:bg-gray-700 hover:text-primary'">
                                        <span>✨ New Arrivals</span>
                                        <span class="text-[10px] uppercase font-bold text-emerald-600 dark:text-emerald-400">Fresh</span>
                                    </button>
                                </li>
                                <li>
                                    <button @click="collection = 'most-searched'; showMobileFilters = false"
                                        class="text-left w-full px-2 py-1.5 rounded-lg transition font-medium text-xs sm:text-sm flex items-center justify-between cursor-pointer"
                                        :class="collection === 'most-searched' || collection === 'most_searched' ? 'bg-blue-100 text-blue-800 dark:bg-blue-900/40 dark:text-blue-300 font-bold' : 'text-gray-600 dark:text-gray-400 hover:bg-gray-100 dark:hover:bg-gray-700 hover:text-primary'">
                                        <span>🔍 Most Searched</span>
                                        <span class="text-[10px] uppercase font-bold text-blue-600 dark:text-blue-400">Trending</span>
                                    </button>
                                </li>
                            </ul>
                        </div>

                        <!-- Category Filter Menu -->
                        <div class="bg-white dark:bg-gray-800 p-5 rounded-xl shadow-sm border border-gray-100 dark:border-gray-700">
                            <h3 class="font-bold mb-4 uppercase text-sm tracking-wider text-gray-900 dark:text-gray-100">Categories</h3>
                            <ul class="space-y-1 text-sm">
                                <!-- All categories -->  
                                <li>
                                    <button @click="category = ''; showMobileFilters = false"
                                        class="text-left w-full px-2 py-1.5 rounded-lg transition"
                                        :class="!category ? 'bg-primary/10 font-bold text-primary' : 'text-gray-600 dark:text-gray-400 hover:bg-gray-100 dark:hover:bg-gray-700 hover:text-primary'">
                                        🏷️ All Categories
                                    </button>
                                </li>

                                <!-- Parent categories with optional subcategories (First 7 with See All toggle) -->
                                <template v-for="parent in visibleCategories" :key="parent.id">
                                    <!-- Parent button -->
                                    <li>
                                        <button @click="category = parent.slug; showMobileFilters = false"
                                            class="text-left w-full px-2 py-1.5 rounded-lg transition font-semibold"
                                            :class="category === parent.slug
                                                ? 'bg-primary/10 text-primary font-bold'
                                                : 'text-gray-800 dark:text-gray-200 hover:bg-gray-100 dark:hover:bg-gray-700 hover:text-primary'">
                                            📁 {{ parent.name }}
                                        </button>
                                    </li>
                                    <!-- Subcategories indented beneath -->  
                                    <li v-for="sub in parent.children" :key="sub.id" class="pl-4">
                                        <button @click="category = sub.slug; showMobileFilters = false"
                                            class="text-left w-full px-2 py-1 rounded-lg transition text-xs flex items-center gap-1"
                                            :class="category === sub.slug
                                                ? 'font-semibold text-primary bg-primary/10'
                                                : 'text-gray-500 dark:text-gray-400 hover:bg-gray-100 dark:hover:bg-gray-700 hover:text-primary'">
                                            <span class="text-gray-300 dark:text-gray-600">↳</span> {{ sub.name }}
                                        </button>
                                    </li>
                                </template>

                                <!-- See All / Show Less Categories Button -->
                                <li v-if="categories && categories.length > 7" class="pt-2 border-t border-gray-100 dark:border-gray-700/60">
                                    <button
                                        type="button"
                                        @click="showAllCategories = !showAllCategories"
                                        class="w-full py-1.5 px-2 rounded-lg text-xs font-bold text-primary hover:bg-primary/10 transition flex items-center justify-between cursor-pointer"
                                    >
                                        <span>{{ showAllCategories ? 'Show Less' : `See All (${categories.length})` }}</span>
                                        <svg class="w-3.5 h-3.5 transform transition-transform" :class="showAllCategories ? 'rotate-180' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M19 9l-7 7-7-7" />
                                        </svg>
                                    </button>
                                </li>
                            </ul>
                        </div>

                        <!-- Brand Filter Menu -->
                        <div v-if="brands && brands.length > 0" class="bg-white dark:bg-gray-800 p-5 rounded-xl shadow-sm border border-gray-100 dark:border-gray-700">
                            <h3 class="font-bold mb-4 uppercase text-sm tracking-wider text-gray-900 dark:text-gray-100">Brands</h3>
                            <ul class="space-y-1 text-sm">
                                <li>
                                    <button @click="brand = ''; showMobileFilters = false"
                                        class="text-left w-full px-2 py-1.5 rounded-lg transition flex items-center justify-between"
                                        :class="!brand ? 'bg-primary/10 font-bold text-primary' : 'text-gray-600 dark:text-gray-400 hover:bg-gray-100 dark:hover:bg-gray-700 hover:text-primary'">
                                        <span>✨ All Brands</span>
                                    </button>
                                </li>
                                <li v-for="b in visibleBrands" :key="b.id">
                                    <button @click="brand = b.slug; showMobileFilters = false"
                                        class="text-left w-full px-2 py-1.5 rounded-lg transition flex items-center justify-between gap-2"
                                        :class="brand === b.slug
                                            ? 'bg-primary/10 text-primary font-bold'
                                            : 'text-gray-800 dark:text-gray-200 hover:bg-gray-100 dark:hover:bg-gray-700 hover:text-primary'">
                                        <span class="flex items-center gap-2 min-w-0">
                                            <img v-if="b.logo_url" :src="b.logo_url" class="w-4 h-4 rounded object-contain bg-white border dark:border-gray-600 shrink-0" />
                                            <span class="truncate">{{ b.name }}</span>
                                        </span>
                                        <span v-if="b.products_count !== undefined" class="text-[11px] px-1.5 py-0.5 rounded-full bg-gray-100 dark:bg-gray-700 text-gray-500 dark:text-gray-400 font-semibold shrink-0">
                                            {{ b.products_count }}
                                        </span>
                                    </button>
                                </li>
                                <li v-if="brands.length > 10" class="pt-2 border-t border-gray-100 dark:border-gray-700/60">
                                    <button
                                        type="button"
                                        @click="showAllBrands = !showAllBrands"
                                        class="w-full py-1.5 px-2 rounded-lg text-xs font-bold text-primary hover:bg-primary/10 transition flex items-center justify-between cursor-pointer"
                                    >
                                        <span>{{ showAllBrands ? 'Show Less' : `See All (${brands.length})` }}</span>
                                        <svg class="w-3.5 h-3.5 transform transition-transform" :class="showAllBrands ? 'rotate-180' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M19 9l-7 7-7-7" />
                                        </svg>
                                    </button>
                                </li>
                            </ul>
                        </div>

                        <!-- Dynamic Category Attributes Filters -->
                        <div v-if="activeAttributes.length > 0" class="bg-white dark:bg-gray-800 p-5 rounded-xl shadow-sm border border-gray-100 dark:border-gray-700 transition">
                            <h3 class="font-bold mb-4 uppercase text-sm tracking-wider text-gray-900 dark:text-gray-100 flex items-center gap-2">
                                <span class="text-yellow-500">✨</span> Filters
                            </h3>
                            <div class="space-y-6">
                                <div v-for="attr in activeAttributes" :key="attr.id">
                                    <label class="block text-xs font-semibold text-gray-500 uppercase mb-2">{{ attr.name }}</label>
                                    
                                    <!-- Color Swatches -->
                                    <div v-if="attr.type === 'color'" class="flex flex-wrap gap-2">
                                        <button v-for="opt in attr.options" :key="opt"
                                            @click="attrs[attr.id] = (attrs[attr.id] === opt ? '' : opt)"
                                            class="w-7 h-7 rounded-full border-2 transition-transform duration-200"
                                            :class="attrs[attr.id] === opt ? 'scale-110 border-primary shadow-md ring-2 ring-primary/40' : 'border-gray-200 dark:border-gray-600 hover:scale-110'"
                                            :style="{ backgroundColor: opt.startsWith('#') ? opt : opt.toLowerCase().replace(/\s+/g, '') }"
                                            :title="opt">
                                        </button>
                                    </div>

                                    <!-- Default Select Options for Non-Colors -->
                                    <select v-else
                                        v-model="attrs[attr.id]"
                                        class="w-full text-sm rounded-lg border border-gray-300 dark:border-gray-600 dark:bg-gray-800 dark:text-white py-2 px-3 shadow-sm focus:ring-2 focus:ring-primary focus:border-primary appearance-none cursor-pointer transition">
                                        <option value="">Any {{ attr.name }}</option>
                                        <option v-for="opt in attr.options" :key="opt" :value="opt">{{ opt }}</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                    </div>
                </aside>

                <!-- ─── Main Content ──────────────────────────────── -->
                <div class="flex-1 min-w-0">
                    
                    <!-- Top Search Bar & Sort Dropdown & Mobile Toggle -->
                    <div class="flex flex-col sm:flex-row items-stretch sm:items-center gap-2.5 sm:gap-3 mb-5">
                        <div class="flex items-center gap-2 sm:gap-3 flex-1">
                            <!-- Mobile Filter Button -->
                            <button @click="showMobileFilters = true" 
                                class="lg:hidden p-2.5 sm:p-3 bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-xl shadow-xs text-gray-600 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700 transition flex items-center gap-1.5 cursor-pointer shrink-0">
                                <svg class="w-5 h-5 text-primary" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z"/></svg>
                                <span class="text-xs font-bold sm:hidden">Filter</span>
                            </button>
                            
                            <!-- Search input -->
                            <div class="relative flex-1">
                                <span class="absolute inset-y-0 left-3 flex items-center pointer-events-none">
                                    <svg class="w-4 h-4 sm:w-5 sm:h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                                    </svg>
                                </span>
                                <input
                                    v-model="search"
                                    type="text"
                                    placeholder="Search products..."
                                    class="w-full pl-9 sm:pl-10 pr-4 py-2.5 sm:py-3 rounded-xl border border-gray-200 dark:border-gray-700 dark:bg-gray-800 dark:text-white text-xs sm:text-sm shadow-xs focus:ring-2 focus:ring-primary focus:border-primary transition"
                                />
                            </div>
                        </div>

                        <!-- Top Sort Dropdown -->
                        <div class="flex items-center gap-2 shrink-0">
                            <label for="catalog-sort" class="text-xs font-semibold text-gray-500 dark:text-gray-400 whitespace-nowrap hidden md:inline-block">
                                Sort by:
                            </label>
                            <div class="relative w-full sm:w-auto">
                                <select
                                    id="catalog-sort"
                                    v-model="sort"
                                    class="w-full sm:w-auto py-2.5 sm:py-3 pl-3 pr-8 rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 dark:text-white text-xs sm:text-sm font-semibold shadow-xs focus:ring-2 focus:ring-primary focus:border-primary cursor-pointer transition appearance-none"
                                >
                                    <option value="default">Default / Recommended</option>
                                    <option value="name_asc">Name: A to Z</option>
                                    <option value="name_desc">Name: Z to A</option>
                                    <option value="price_asc">Price: Low to High</option>
                                    <option value="price_desc">Price: High to Low</option>
                                    <option value="newest">Newest Arrivals</option>
                                    <option value="popular">Best Selling / Popular</option>
                                </select>
                                <div class="pointer-events-none absolute inset-y-0 right-0 flex items-center px-2.5 text-gray-400">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                                    </svg>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Active filter pill -->
                    <div v-if="search || category || brand || collectionTitle || sortLabel || Object.keys(attrs).length > 0" class="flex flex-wrap gap-2 mb-6">
                        <span v-if="collectionTitle" class="inline-flex items-center gap-1.5 bg-primary/10 text-primary border border-primary/20 px-3 py-1 rounded-full text-xs sm:text-sm font-medium">
                            Collection: {{ collectionTitle }}
                            <button @click="collection = ''" class="hover:opacity-75 font-bold cursor-pointer">×</button>
                        </span>
                        <span v-if="sortLabel" class="inline-flex items-center gap-1.5 bg-blue-50 dark:bg-blue-900/30 text-blue-700 dark:text-blue-300 border border-blue-200 dark:border-blue-800 px-3 py-1 rounded-full text-xs sm:text-sm font-medium">
                            Sort: {{ sortLabel }}
                            <button @click="sort = 'default'" class="hover:opacity-75 font-bold cursor-pointer">×</button>
                        </span>
                        <span v-if="search" class="inline-flex items-center gap-1.5 bg-primary/10 text-primary border border-primary/20 px-3 py-1 rounded-full text-sm font-medium">
                            Search: "{{ search }}"
                            <button @click="search = ''" class="hover:opacity-75 font-bold">×</button>
                        </span>
                        <span v-if="category" class="inline-flex items-center gap-1.5 bg-primary/10 text-primary border border-primary/20 px-3 py-1 rounded-full text-sm font-medium">
                            Category: {{ allCategoriesFlat.find(c => c.slug === category)?.name || category }}
                            <button @click="category = ''" class="hover:opacity-75 font-bold">×</button>
                        </span>
                        <span v-if="brand" class="inline-flex items-center gap-1.5 bg-primary/10 text-primary border border-primary/20 px-3 py-1 rounded-full text-sm font-medium">
                            Brand: {{ activeBrandObj?.name || brand }}
                            <button @click="brand = ''" class="hover:opacity-75 font-bold">×</button>
                        </span>
                        
                        <!-- Attribute Pills -->
                        <template v-for="(val, key) in attrs" :key="'pill-'+key">
                            <span v-if="val" class="inline-flex items-center gap-1.5 bg-yellow-100 dark:bg-yellow-900/30 text-yellow-700 dark:text-yellow-300 px-3 py-1 rounded-full text-sm font-medium">
                                {{ activeAttributes?.find(a => a.id == key)?.name }}:
                                <span v-if="activeAttributes?.find(a => a.id == key)?.type === 'color'" 
                                    class="w-4 h-4 rounded-full border border-black/10 dark:border-white/20 shadow-sm"
                                    :title="val" :style="{ backgroundColor: val.startsWith('#') ? val : val.toLowerCase().replace(/\s+/g, '') }"></span>
                                <span v-else>{{ val }}</span>
                                <button @click="attrs[key] = ''" class="hover:text-yellow-900 dark:hover:text-yellow-100 font-bold">×</button>
                            </span>
                        </template>
                    </div>

                    <!-- Active Brand Header Banner -->
                    <div v-if="activeBrandObj && !collectionTitle" class="mb-5 flex flex-wrap items-center justify-between gap-4 bg-white dark:bg-gray-800 p-4 sm:p-5 rounded-xl border border-gray-100 dark:border-gray-700 shadow-2xs">
                        <div class="flex items-center gap-3.5">
                            <img v-if="activeBrandObj.logo_url" :src="activeBrandObj.logo_url" :alt="activeBrandObj.name" class="w-12 h-12 rounded-xl object-contain bg-white border dark:border-gray-700 p-1.5 shrink-0" />
                            <div>
                                <div class="flex items-center gap-2">
                                    <h1 class="text-lg sm:text-2xl font-black text-gray-900 dark:text-white">
                                        {{ activeBrandObj.name }}
                                    </h1>
                                    <span class="text-[10px] sm:text-xs font-black px-2.5 py-0.5 rounded-full uppercase tracking-wider bg-primary/10 text-primary">
                                        {{ products.total }} {{ products.total === 1 ? 'Product' : 'Products' }}
                                    </span>
                                </div>
                                <p v-if="activeBrandObj.description" class="text-xs text-gray-500 dark:text-gray-400 mt-1 line-clamp-2">
                                    {{ activeBrandObj.description }}
                                </p>
                            </div>
                        </div>
                        <button @click="brand = ''" class="text-xs font-semibold text-primary hover:underline flex items-center gap-1 cursor-pointer">
                            All Brands &rarr;
                        </button>
                    </div>

                    <!-- Collection Header Banner -->
                    <div v-if="collectionTitle" class="mb-5 flex flex-wrap items-center justify-between gap-3 bg-white dark:bg-gray-800 p-4 sm:p-5 rounded-xl border border-gray-100 dark:border-gray-700 shadow-2xs">
                        <div>
                            <div class="flex items-center gap-2">
                                <h1 class="text-lg sm:text-2xl font-black text-gray-900 dark:text-white">
                                    {{ collectionTitle }}
                                </h1>
                                <span class="text-[10px] sm:text-xs font-black px-2.5 py-0.5 rounded-full uppercase tracking-wider"
                                    :class="collection.includes('flash') ? 'bg-red-500/10 text-red-600 dark:text-red-400' : (collection === 'featured' ? 'bg-primary/10 text-primary' : (collection.includes('best') ? 'bg-amber-100 text-amber-800 dark:bg-amber-900/40 dark:text-amber-300' : 'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/40 dark:text-emerald-300'))">
                                    {{ products.total }} {{ products.total === 1 ? 'Product' : 'Products' }}
                                </span>
                            </div>
                            <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                                {{ collection.includes('flash') ? 'Limited-time special prices and exclusive flash sale deals' : (collection === 'featured' ? 'Handpicked essentials and featured items' : (collection.includes('best') ? 'Top performing products loved by our customers' : 'The latest additions to our store')) }}
                            </p>
                        </div>
                        <button @click="collection = ''" class="text-xs font-semibold text-primary hover:underline flex items-center gap-1 cursor-pointer">
                            View All Products &rarr;
                        </button>
                    </div>

                    <!-- ─── Product Grid ──────────────────────────────── -->
                    <div class="grid grid-cols-2 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-2.5 sm:gap-3.5 md:gap-4">
                <div v-for="product in products.data" :key="product.id"
                    class="border border-gray-100 dark:border-gray-700/80 rounded-xl sm:rounded-2xl overflow-hidden shadow-xs hover:shadow-md hover:border-gray-200 dark:hover:border-gray-600 transition-all bg-white dark:bg-gray-800 flex flex-col relative group">

                    <!-- Wishlist Heart Button -->
                    <button
                        v-if="wishlistEnabled"
                        @click.prevent="toggleWishlist(product)"
                        :disabled="togglingWishlist[product.id]"
                        :title="isWishlisted(product.id) ? 'Remove from wishlist' : 'Save to wishlist'"
                        class="absolute top-2 right-2 sm:top-3 sm:right-3 z-10 w-7 h-7 sm:w-9 sm:h-9 rounded-full bg-white/90 dark:bg-gray-700/90 backdrop-blur-xs shadow-xs flex items-center justify-center border border-gray-200/80 dark:border-gray-600 hover:scale-110 transition-transform disabled:opacity-60 cursor-pointer">
                        <svg class="w-3.5 h-3.5 sm:w-5 sm:h-5 transition-colors"
                            :class="isWishlisted(product.id) ? 'text-red-500 fill-red-500' : 'text-gray-400 dark:text-gray-300 fill-none'"
                            stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z"/>
                        </svg>
                    </button>

                    <!-- Flash Sale or Regular Sale Badge -->
                    <div v-if="product.is_on_flash_sale" class="absolute top-2 left-2 sm:top-3 sm:left-3 z-10 bg-amber-500 text-white text-[9px] sm:text-[10px] font-black px-2 py-0.5 sm:px-2.5 sm:py-1 rounded-full shadow-md animate-pulse flex items-center gap-1">
                        ⚡ <span class="hidden sm:inline">FLASH DEAL</span><span class="sm:hidden">DEAL</span>
                    </div>
                    <div v-else-if="product.discount_price" class="absolute top-2 left-2 sm:top-3 sm:left-3 z-10 bg-red-500 text-white text-[9px] sm:text-[10px] font-bold px-2 py-0.5 sm:px-2.5 sm:py-1 rounded-full shadow-md">
                        SALE
                    </div>

                    <Link :href="route('shop.show', product.url_key)" class="block flex-1 flex flex-col">
                        <img :src="product.image ? '/storage/' + product.image : 'https://placehold.co/400x300?text=Product'"
                            :alt="product.name" class="w-full h-36 sm:h-44 md:h-48 object-cover group-hover:scale-105 transition duration-300" />
                        <div class="p-2 sm:p-3 flex-1 flex flex-col">
                            <div class="flex items-center justify-between gap-1 mb-0.5">
                                <p v-if="product.category" class="text-[10px] sm:text-xs text-gray-400 dark:text-gray-500 uppercase tracking-wide truncate">{{ product.category.name }}</p>
                                <span v-if="product.brand?.name" class="text-[10px] font-bold text-primary/90 truncate shrink-0">{{ product.brand.name }}</span>
                            </div>
                            <h4 class="text-xs sm:text-sm font-semibold mb-1 text-gray-900 dark:text-white line-clamp-2 leading-snug">{{ product.name }}</h4>
                            <div class="flex flex-col mt-auto pt-1 sm:pt-2">
                                <span v-if="product.is_on_flash_sale || product.discount_price" class="text-[10px] sm:text-xs font-medium text-gray-400 line-through">₦{{ parseFloat(product.price).toLocaleString() }}</span>
                                <span class="text-primary font-black text-sm sm:text-base">
                                    ₦{{ parseFloat(product.effective_price || product.discount_price || product.price).toLocaleString() }}
                                </span>
                            </div>
                        </div>
                    </Link>

                    <!-- Add to Cart & Compare -->
                    <div class="px-2 pb-2 sm:px-3 sm:pb-3 mt-auto flex items-center gap-1.5 sm:gap-2">
                        <!-- In Cart: Quantity Controls -[1]+ -->
                        <div v-if="cartItemsByProductId[product.id]" class="flex-1 inline-flex items-center justify-between border border-primary/40 bg-primary/5 dark:bg-primary/10 rounded-lg overflow-hidden shadow-xs h-8 sm:h-9.5">
                            <button
                                type="button"
                                @click.prevent="updateQuantity(cartItemsByProductId[product.id], cartItemsByProductId[product.id].quantity - 1)"
                                :disabled="isUpdatingItem(cartItemsByProductId[product.id].id)"
                                class="w-7 sm:w-10 h-full flex items-center justify-center font-bold text-gray-700 dark:text-gray-200 hover:bg-primary hover:text-white transition disabled:opacity-50 text-xs sm:text-base cursor-pointer"
                                title="Decrease quantity">
                                -
                            </button>
                            <span class="px-1 sm:px-2 text-center text-xs sm:text-sm font-black text-primary">
                                {{ cartItemsByProductId[product.id].quantity }}
                            </span>
                            <button
                                type="button"
                                @click.prevent="updateQuantity(cartItemsByProductId[product.id], cartItemsByProductId[product.id].quantity + 1)"
                                :disabled="isUpdatingItem(cartItemsByProductId[product.id].id)"
                                class="w-7 sm:w-10 h-full flex items-center justify-center font-bold text-gray-700 dark:text-gray-200 hover:bg-primary hover:text-white transition disabled:opacity-50 text-xs sm:text-base cursor-pointer"
                                title="Increase quantity">
                                +
                            </button>
                        </div>

                        <button v-else
                            @click="addToCart(product)"
                            :disabled="isAddingProduct(product.id)"
                            class="flex-1 py-1.5 sm:py-2 rounded-lg bg-primary hover:opacity-90 text-white text-xs sm:text-sm font-bold transition disabled:opacity-60 flex items-center justify-center gap-1 sm:gap-2 shadow-xs cursor-pointer h-8 sm:h-9.5">
                            <svg v-if="!isAddingProduct(product.id)" class="w-3.5 h-3.5 sm:w-4 sm:h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z"/>
                            </svg>
                            <svg v-else class="w-3.5 h-3.5 sm:w-4 sm:h-4 animate-spin shrink-0" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/>
                            </svg>
                            <span class="truncate">{{ isAddingProduct(product.id) ? '...' : 'Add' }}</span>
                        </button>

                        <button @click="addToCompare(product)" :disabled="comparing[product.id]"
                            title="Compare Product"
                            class="py-1.5 px-2 sm:py-2 sm:px-3 rounded-lg bg-gray-100 hover:bg-primary/10 text-gray-700 hover:text-primary dark:bg-gray-700 dark:hover:bg-gray-600 dark:text-gray-200 dark:hover:text-primary transition disabled:opacity-60 flex items-center justify-center border border-gray-200 dark:border-gray-600 cursor-pointer h-8 sm:h-9.5 shrink-0">
                            <svg v-if="!comparing[product.id]" class="w-3.5 h-3.5 sm:w-5 sm:h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4"/>
                            </svg>
                            <svg v-else class="w-3.5 h-3.5 sm:w-4 sm:h-4 animate-spin" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/>
                            </svg>
                        </button>
                    </div>
                </div>
            </div>

            <!-- Empty state -->
            <div v-if="products.data.length === 0" class="text-center py-16 text-gray-500">
                <svg class="w-12 h-12 mx-auto mb-3 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9.172 16.172a4 4 0 015.656 0M9 10h.01M15 10h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
                No products found. <button @click="search=''; category=''; brand=''" class="text-primary underline ml-1 font-semibold">Clear filters</button>
            </div>

            <Pagination v-if="products.data && products.data.length > 0" :paginator="products" class="mt-8 mb-24 sm:mb-6 border-t dark:border-gray-700 pt-6" />
                </div> <!-- End Main Content -->
            </div> <!-- End Flex Container -->
        </div>
    </Layout>
</template>
