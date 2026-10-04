<script setup>
import { Head, Link, useForm, router, usePage } from '@inertiajs/vue3';
import Layout from '@/Layouts/Layout.vue';
import Pagination from '@/Components/Pagination.vue';
import StorefrontBreadcrumb from '@/Components/StorefrontBreadcrumb.vue';
import { ref, watch, reactive, computed } from 'vue';
import debounce from 'lodash/debounce';
import { useToast } from 'vue-toastification';

const props = defineProps({
    products: Object,
    categories: Array,
    filters: Object,
    wishlist_ids: {
        type: Array,
        default: () => [],
    },
});

const page = usePage();
const toast = useToast();
const search   = ref(props.filters.search || '');
const category = ref(props.filters.category || '');
const attrs    = ref(props.filters.attrs || {});
const addingToCart = reactive({});
const wishlistIds = ref(props.wishlist_ids ?? []);
const togglingWishlist = reactive({});

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

// Debounced search + category filter + attributes -> router call
const applyFilters = debounce(() => {
    // Filter out empty/null values
    const cleanAttrs = Object.fromEntries(
        Object.entries(attrs.value).filter(([_, v]) => v !== '' && v !== null)
    );

    router.get(route('shop.index'), {
        search:   search.value || undefined,
        category: category.value || undefined,
        attrs:    Object.keys(cleanAttrs).length ? cleanAttrs : undefined,
    }, { preserveState: true, replace: true });
}, 300);

watch(search, applyFilters);
watch(category, () => {
    attrs.value = {}; // Reset attributes when category changes
    applyFilters();
});
watch(() => attrs.value, applyFilters, { deep: true });

const addToCart = (product) => {
    addingToCart[product.id] = true;
    useForm({ product_id: product.id, quantity: 1 }).post(route('cart.store'), {
        preserveScroll: true,
        onError:   () => toast.error('Could not add to cart. Please try again.'),
        onFinish:  () => { addingToCart[product.id] = false; },
    });
};

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
        toast.info('Please log in to save items to your wishlist.');
        return;
    }
    togglingWishlist[product.id] = true;
    useForm({ product_id: product.id }).post(route('wishlist.toggle'), {
        preserveScroll: true,
        onSuccess: () => {
            if (isWishlisted(product.id)) {
                wishlistIds.value = wishlistIds.value.filter(id => id !== product.id);
                toast.info(`${product.name} removed from wishlist.`);
            } else {
                wishlistIds.value.push(product.id);
                toast.success(`${product.name} saved to wishlist!`);
            }
        },
        onError: () => toast.error('Could not update wishlist.'),
        onFinish: () => { togglingWishlist[product.id] = false; },
    });
};

const showMobileFilters = ref(false);

const breadcrumbs = computed(() => {
    const crumbs = [];
    if (category.value && allCategoriesFlat.value.length) {
        const cat = allCategoriesFlat.value.find(c => c.slug === category.value);
        if (cat) {
            crumbs.push({ label: 'All Products', url: route('shop.index') });
            // If it's a subcategory, also add the parent
            if (cat.parent_id) {
                const parent = props.categories?.find(p => p.id === cat.parent_id);
                if (parent) crumbs.push({ label: parent.name, url: route('shop.index', { category: parent.slug }) });
            }
            crumbs.push({ label: cat.name });
            return crumbs;
        }
    }
    crumbs.push({ label: 'All Products' });
    return crumbs;
});
</script>

<template>
    <Head title="Shop" />
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
                        <!-- Category Filter Menu -->
                        <div class="bg-white dark:bg-gray-800 p-5 rounded-xl shadow-sm border dark:border-gray-700">
                            <h3 class="font-bold mb-4 uppercase text-sm tracking-wider text-gray-900 dark:text-gray-100">Categories</h3>
                            <ul class="space-y-1 text-sm">
                                <!-- All categories -->  
                                <li>
                                    <button @click="category = ''; showMobileFilters = false"
                                        class="text-left w-full px-2 py-1.5 rounded-lg transition"
                                        :class="!category ? 'bg-blue-50 dark:bg-blue-900/20 font-bold text-blue-600 dark:text-blue-400' : 'text-gray-600 dark:text-gray-400 hover:bg-gray-100 dark:hover:bg-gray-700 hover:text-blue-600 dark:hover:text-blue-400'">
                                        🏷️ All Categories
                                    </button>
                                </li>

                                <!-- Parent categories with optional subcategories -->
                                <template v-for="parent in categories" :key="parent.id">
                                    <!-- Parent button -->
                                    <li>
                                        <button @click="category = parent.slug; showMobileFilters = false"
                                            class="text-left w-full px-2 py-1.5 rounded-lg transition font-semibold"
                                            :class="category === parent.slug
                                                ? 'bg-blue-50 dark:bg-blue-900/20 text-blue-600 dark:text-blue-400'
                                                : 'text-gray-800 dark:text-gray-200 hover:bg-gray-100 dark:hover:bg-gray-700'">
                                            📁 {{ parent.name }}
                                        </button>
                                    </li>
                                    <!-- Subcategories indented beneath -->  
                                    <li v-for="sub in parent.children" :key="sub.id" class="pl-4">
                                        <button @click="category = sub.slug; showMobileFilters = false"
                                            class="text-left w-full px-2 py-1 rounded-lg transition text-xs flex items-center gap-1"
                                            :class="category === sub.slug
                                                ? 'font-semibold text-indigo-600 dark:text-indigo-400 bg-indigo-50 dark:bg-indigo-900/20'
                                                : 'text-gray-500 dark:text-gray-400 hover:bg-gray-100 dark:hover:bg-gray-700 hover:text-indigo-600 dark:hover:text-indigo-400'">
                                            <span class="text-gray-300 dark:text-gray-600">↳</span> {{ sub.name }}
                                        </button>
                                    </li>
                                </template>
                            </ul>
                        </div>

                        <!-- Dynamic Category Attributes Filters -->
                        <div v-if="activeAttributes.length > 0" class="bg-white dark:bg-gray-800 p-5 rounded-xl shadow-sm border dark:border-gray-700 transition">
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
                                            :class="attrs[attr.id] === opt ? 'scale-110 border-blue-500 shadow-md ring-2 ring-blue-200 dark:ring-blue-900' : 'border-gray-200 dark:border-gray-600 hover:scale-110'"
                                            :style="{ backgroundColor: opt.startsWith('#') ? opt : opt.toLowerCase().replace(/\s+/g, '') }"
                                            :title="opt">
                                        </button>
                                    </div>

                                    <!-- Default Select Options for Non-Colors -->
                                    <select v-else
                                        v-model="attrs[attr.id]"
                                        class="w-full text-sm rounded-lg border border-gray-300 dark:border-gray-600 dark:bg-gray-800 dark:text-white py-2 px-3 shadow-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 appearance-none cursor-pointer transition">
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
                    
                    <!-- Top Search Bar & Mobile Toggle -->
                    <div class="flex items-center gap-3 mb-6">
                        <!-- Mobile Filter Button -->
                        <button @click="showMobileFilters = true" 
                            class="lg:hidden p-3 bg-white dark:bg-gray-800 border dark:border-gray-300 dark:border-gray-600 rounded-xl shadow-sm text-gray-600 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700 transition">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h7"/></svg>
                        </button>
                        
                        <!-- Search input -->
                        <div class="relative flex-1">
                            <span class="absolute inset-y-0 left-3 flex items-center pointer-events-none">
                                <svg class="w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                                </svg>
                            </span>
                            <input
                                v-model="search"
                                type="text"
                                placeholder="Search products..."
                                class="w-full pl-10 pr-4 py-3 rounded-xl border border-gray-300 dark:border-gray-600 dark:bg-gray-800 dark:text-white shadow-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition"
                            />
                        </div>
                    </div>

                    <!-- Active filter pill -->
                    <div v-if="search || category || Object.keys(attrs).length > 0" class="flex flex-wrap gap-2 mb-6">
                        <span v-if="search" class="inline-flex items-center gap-1.5 bg-blue-100 dark:bg-blue-900/30 text-blue-700 dark:text-blue-300 px-3 py-1 rounded-full text-sm font-medium">
                            Search: "{{ search }}"
                            <button @click="search = ''" class="hover:text-blue-900 dark:hover:text-blue-100 font-bold">×</button>
                        </span>
                        <span v-if="category" class="inline-flex items-center gap-1.5 bg-purple-100 dark:bg-purple-900/30 text-purple-700 dark:text-purple-300 px-3 py-1 rounded-full text-sm font-medium">
                            Category: {{ categories.find(c => c.slug === category)?.name }}
                            <button @click="category = ''" class="hover:text-purple-900 dark:hover:text-purple-100 font-bold">×</button>
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

                    <!-- ─── Product Grid ──────────────────────────────── -->
                    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6">
                <div v-for="product in products.data" :key="product.id"
                    class="border dark:border-gray-700 rounded-xl overflow-hidden shadow-sm hover:shadow-md transition bg-white dark:bg-gray-800 flex flex-col relative group">

                    <!-- Wishlist Heart Button -->
                    <button
                        v-if="wishlistEnabled"
                        @click.prevent="toggleWishlist(product)"
                        :disabled="togglingWishlist[product.id]"
                        :title="isWishlisted(product.id) ? 'Remove from wishlist' : 'Save to wishlist'"
                        class="absolute top-3 right-3 z-10 w-9 h-9 rounded-full bg-white dark:bg-gray-700 shadow-md flex items-center justify-center border border-gray-200 dark:border-gray-600 hover:scale-110 transition-transform disabled:opacity-60">
                        <svg class="w-5 h-5 transition-colors"
                            :class="isWishlisted(product.id) ? 'text-red-500 fill-red-500' : 'text-gray-400 dark:text-gray-300 fill-none'"
                            stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z"/>
                        </svg>
                    </button>

                    <Link :href="route('shop.show', product.url_key)" class="block">
                        <img :src="product.image ? '/storage/' + product.image : 'https://placehold.co/400x300?text=Product'"
                            :alt="product.name" class="w-full h-48 object-cover group-hover:scale-105 transition duration-300" />
                        <div class="p-4">
                            <p v-if="product.category" class="text-xs text-gray-400 dark:text-gray-500 mb-1 uppercase tracking-wide">{{ product.category.name }}</p>
                            <h4 class="text-base font-semibold mb-1 text-gray-900 dark:text-white line-clamp-2">{{ product.name }}</h4>
                            <div class="flex flex-col mt-2">
                                <span v-if="product.discount_price" class="text-xs font-medium text-gray-400 line-through">₦{{ parseFloat(product.price).toLocaleString() }}</span>
                                <span class="text-blue-600 dark:text-blue-400 font-black text-lg">
                                    ₦{{ product.discount_price ? parseFloat(product.discount_price).toLocaleString() : parseFloat(product.price).toLocaleString() }}
                                </span>
                            </div>
                        </div>
                    </Link>

                    <!-- Add to Cart & Compare -->
                    <div class="px-4 pb-4 mt-auto flex gap-2">
                        <button @click="addToCart(product)" :disabled="addingToCart[product.id]"
                            class="flex-1 py-2 rounded-lg bg-blue-600 hover:bg-blue-700 text-white text-sm font-bold transition disabled:opacity-60 flex items-center justify-center gap-2">
                            <svg v-if="!addingToCart[product.id]" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z"/>
                            </svg>
                            <svg v-else class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/>
                            </svg>
                            {{ addingToCart[product.id] ? 'Adding...' : 'Add to Cart' }}
                        </button>

                        <button @click="addToCompare(product)" :disabled="comparing[product.id]"
                            title="Compare Product"
                            class="py-2 px-3 rounded-lg bg-gray-100 hover:bg-blue-50 text-gray-700 hover:text-blue-600 dark:bg-gray-700 dark:hover:bg-gray-600 dark:text-gray-200 dark:hover:text-blue-300 transition disabled:opacity-60 flex items-center justify-center border border-gray-200 dark:border-gray-600">
                            <svg v-if="!comparing[product.id]" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4"/>
                            </svg>
                            <svg v-else class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24">
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
                No products found. <button @click="search=''; category=''" class="text-blue-600 underline ml-1">Clear filters</button>
            </div>

            <Pagination v-if="products.data && products.data.length > 0" :paginator="products" class="mt-8 mb-4 border-t dark:border-gray-700 pt-6" />
                </div> <!-- End Main Content -->
            </div> <!-- End Flex Container -->
        </div>
    </Layout>
</template>
