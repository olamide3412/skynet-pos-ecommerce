<script setup>
import { Head, Link, useForm, usePage, router } from '@inertiajs/vue3';
import Layout from '@/Layouts/Layout.vue';
import StorefrontBreadcrumb from '@/Components/StorefrontBreadcrumb.vue';
import { ref, computed, watch } from 'vue';
import { useToast } from 'vue-toastification';

const props = defineProps({
    product: Object,
    related: Array,
    is_wishlisted: {
        type: Boolean,
        default: false,
    },
});

const page = usePage();
const toast = useToast();

const wishlistEnabled = computed(() => page.props.store_settings?.wishlist_enabled === '1');
const isWishlisted = ref(props.is_wishlisted);
const togglingWishlist = ref(false);

const selectedVariant = ref(null);
const selectedPosUnit = ref(
    props.product.pos_units && props.product.pos_units.length > 0 
        ? (props.product.pos_units.find(u => u.unit_kind === 'base') || props.product.pos_units[0]) 
        : null
);

const allImages = computed(() => {
    let imgs = [];
    if (props.product.image) imgs.push(props.product.image);
    if (props.product.images && Array.isArray(props.product.images)) {
        imgs.push(...props.product.images);
    }
    return imgs;
});

const activeImage = ref(allImages.value.length ? allImages.value[0] : null);

const form = useForm({
    product_id: props.product.id,
    quantity: 1,
    variant: null,
    product_pos_unit_id: selectedPosUnit.value ? selectedPosUnit.value.id : null,
});

watch(selectedVariant, (newVal) => {
    form.variant = newVal ? newVal.name : null;
});

watch(selectedPosUnit, (newVal) => {
    form.product_pos_unit_id = newVal ? newVal.id : null;
    if (form.quantity > maxStock.value && maxStock.value > 0) {
        form.quantity = maxStock.value;
    }
});

const displayPrice = computed(() => {
    if (selectedPosUnit.value && selectedPosUnit.value.unit_kind !== 'base' && parseFloat(selectedPosUnit.value.synced_standard_price) > 0) {
        return parseFloat(selectedPosUnit.value.synced_standard_price);
    }
    if (selectedVariant.value && selectedVariant.value.price) {
        return parseFloat(selectedVariant.value.price);
    }
    return props.product.discount_price 
        ? parseFloat(props.product.discount_price) 
        : parseFloat(props.product.price);
});

const maxStock = computed(() => {
    let baseStock = parseInt(props.product.stock || 0);
    if (selectedVariant.value && selectedVariant.value.stock !== undefined) {
        baseStock = parseInt(selectedVariant.value.stock);
    }
    if (selectedPosUnit.value && parseFloat(selectedPosUnit.value.quantity_in_base_units) > 1) {
        const factor = parseFloat(selectedPosUnit.value.quantity_in_base_units);
        return Math.max(0, Math.floor(baseStock / factor));
    }
    return Math.max(0, baseStock);
});

const isPurchasable = computed(() => {
    if (props.product.inventory_source === 'pos') {
        if (['missing_pos_item', 'inactive_pos_item', 'mapping_sync_failed'].includes(props.product.pos_sync_status)) {
            return false;
        }
    }
    return maxStock.value > 0;
});

const addToCart = () => {
    if (!isPurchasable.value) return;
    form.post(route('cart.store'), {
        preserveScroll: true,
        onSuccess: () => toast.success(`${props.product.name} added to cart!`),
    });
};

const comparing = ref(false);
const addToCompare = () => {
    comparing.value = true;
    useForm({ product_id: props.product.id }).post(route('compare.store'), {
        preserveScroll: true,
        onError: () => toast.error('Could not add to compare list.'),
        onFinish: () => { comparing.value = false; },
    });
};

const buyNow = () => {
    form.post(route('cart.store'), {
        preserveScroll: true,
        onSuccess: () => {
            router.get(route('checkout.index'));
        },
    });
};

const toggleWishlist = () => {
    if (!page.props.auth.customer) {
        toast.info('Please log in to save items to your wishlist.');
        return;
    }
    togglingWishlist.value = true;
    useForm({ product_id: props.product.id }).post(route('wishlist.toggle'), {
        preserveScroll: true,
        onSuccess: () => {
            isWishlisted.value = !isWishlisted.value;
            toast.success(isWishlisted.value ? 'Saved to wishlist!' : 'Removed from wishlist.');
        },
        onError: () => toast.error('Could not update wishlist.'),
        onFinish: () => { togglingWishlist.value = false; },
    });
};

const breadcrumbs = computed(() => {
    const crumbs = [
        { label: 'All Products', url: route('shop.index') }
    ];
    if (props.product.category) {
        crumbs.push({ label: props.product.category.name, url: route('shop.index', { category: props.product.category.slug }) });
    }
    crumbs.push({ label: props.product.name });
    return crumbs;
});
</script>

<template>
    <Head :title="product.name" />
    <Layout>
        <div class="container mx-auto px-4 pt-4 pb-8 text-black dark:text-white">
            <StorefrontBreadcrumb :items="breadcrumbs" />
            <div class="flex flex-col md:flex-row gap-8 bg-white dark:bg-gray-800 p-6 rounded-lg shadow-sm border dark:border-gray-700">
                <div class="w-full md:w-1/2">
                    <div class="mb-4">
                        <img :src="activeImage ? '/storage/' + activeImage : 'https://placehold.co/600x600?text=' + product.name" :alt="product.name" class="w-full rounded-md object-cover max-h-[500px]" />
                    </div>
                    <!-- Thumbnails -->
                    <div v-if="allImages.length > 1" class="flex gap-2 overflow-x-auto pb-2 scrollbar-hide">
                        <button 
                            v-for="(img, idx) in allImages" 
                            :key="idx" 
                            @click="activeImage = img"
                            class="flex-shrink-0 w-20 h-20 rounded-md overflow-hidden border-2 transition-all"
                            :class="activeImage === img ? 'border-blue-600 dark:border-blue-400' : 'border-transparent hover:border-gray-300 dark:hover:border-gray-600'"
                        >
                            <img :src="'/storage/' + img" :alt="product.name + ' thumbnail ' + (idx + 1)" class="w-full h-full object-cover" />
                        </button>
                    </div>
                </div>
                
                <div class="w-full md:w-1/2 flex flex-col justify-center">
                    <!-- Title + Wishlist -->
                    <div class="flex items-start justify-between gap-3 mb-2">
                        <h1 class="text-3xl font-bold flex-1">{{ product.name }}</h1>

                        <!-- Wishlist Heart (shown when enabled) -->
                        <button
                            v-if="wishlistEnabled"
                            @click="toggleWishlist"
                            :disabled="togglingWishlist"
                            :title="isWishlisted ? 'Remove from wishlist' : 'Save to wishlist'"
                            class="flex-shrink-0 w-11 h-11 rounded-full border-2 flex items-center justify-center transition-all hover:scale-110 disabled:opacity-60"
                            :class="isWishlisted
                                ? 'border-red-400 bg-red-50 dark:bg-red-900/20'
                                : 'border-gray-200 dark:border-gray-600 bg-white dark:bg-gray-700'">
                            <svg class="w-6 h-6 transition-colors"
                                :class="isWishlisted ? 'text-red-500 fill-red-500' : 'text-gray-400 fill-none'"
                                stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z"/>
                            </svg>
                        </button>
                    </div>

                    <div class="mb-4 text-gray-500 dark:text-gray-400">Category: <Link :href="route('shop.index', {category: product.category.slug})" class="text-blue-600 dark:text-blue-400 hover:underline">{{ product.category.name }}</Link></div>
                    
                    <div class="flex items-center gap-4 mb-4">
                        <div class="flex flex-col">
                            <span v-if="product.discount_price && (!selectedVariant || !selectedVariant.price)" class="text-sm font-medium text-gray-400 line-through">₦{{ parseFloat(product.price).toLocaleString() }}</span>
                            <span class="text-3xl font-black text-blue-600 dark:text-blue-400">₦{{ displayPrice.toLocaleString() }}</span>
                        </div>
                        <span v-if="isPurchasable && product.show_stock_level" class="px-3 py-1 bg-green-100 text-green-800 text-sm font-semibold rounded-full border border-green-200">In Stock ({{ maxStock }})</span>
                        <span v-else-if="isPurchasable && !product.show_stock_level" class="px-3 py-1 bg-green-100 text-green-800 text-sm font-semibold rounded-full border border-green-200">In Stock</span>
                        <span v-else class="px-3 py-1 bg-red-100 text-red-800 text-sm font-semibold rounded-full border border-red-200">Unavailable</span>
                    </div>

                    <!-- Unavailable POS item warning banner -->
                    <div v-if="!isPurchasable && product.inventory_source === 'pos'" class="mb-4 p-3 bg-amber-50 dark:bg-amber-900/20 border border-amber-300 dark:border-amber-700 rounded-lg text-sm text-amber-800 dark:text-amber-300 flex items-center gap-2">
                        <svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                        <span>This item is temporarily unavailable or out of stock at our connected fulfillment branch.</span>
                    </div>

                    <!-- POS Selling Units Selection -->
                    <div v-if="product.pos_units && product.pos_units.length > 0" class="mb-5 bg-gray-50 dark:bg-gray-900 border dark:border-gray-700 p-4 rounded-lg">
                        <label class="block text-sm font-bold text-gray-700 dark:text-gray-300 mb-2">Selling Unit</label>
                        <div class="flex flex-wrap gap-2">
                            <button 
                                v-for="u in product.pos_units" 
                                :key="u.id"
                                type="button"
                                @click="selectedPosUnit = u"
                                :class="selectedPosUnit?.id === u.id ? 'ring-2 ring-blue-600 bg-blue-50 dark:bg-blue-900/30 border-blue-500 text-blue-700 dark:text-blue-300 font-bold' : 'bg-white dark:bg-gray-800 border-gray-300 dark:border-gray-600 text-gray-700 dark:text-gray-300 hover:border-blue-400 font-medium'"
                                class="px-3.5 py-2 rounded-lg border text-sm transition text-left flex flex-col shadow-sm"
                            >
                                <span class="capitalize">{{ u.online_label || u.unit_name }}</span>
                                <span class="text-xs opacity-75">
                                    <template v-if="parseFloat(u.synced_standard_price) > 0 && u.unit_kind !== 'base'">₦{{ parseFloat(u.synced_standard_price).toLocaleString() }}</template>
                                    <template v-else>Standard Unit</template>
                                    <span v-if="parseFloat(u.quantity_in_base_units) > 1" class="ml-1">({{ parseFloat(u.quantity_in_base_units) }}x)</span>
                                </span>
                            </button>
                        </div>
                    </div>

                    <div v-if="product.variants && product.variants.length > 0" class="mb-6 bg-gray-50 dark:bg-gray-900 border dark:border-gray-700 p-4 rounded-lg">
                        <label class="block text-sm font-bold text-gray-700 dark:text-gray-300 mb-3">Available Options</label>
                        <div class="flex flex-wrap gap-3">
                            <button 
                                v-for="(v, index) in product.variants" 
                                :key="index"
                                @click="selectedVariant = v"
                                :class="selectedVariant?.name === v.name ? 'ring-2 ring-blue-600 bg-blue-50 dark:bg-blue-900/30 border-transparent dark:ring-blue-500' : 'bg-white dark:bg-gray-800 border-gray-300 dark:border-gray-600 hover:border-blue-400'"
                                class="px-4 py-2 rounded-md border text-sm font-semibold transition relative overflow-hidden"
                            >
                                {{ v.name }}
                                <div v-if="v.price && parseFloat(v.price) > 0" class="text-xs font-normal text-gray-500 dark:text-gray-400 mt-0.5">
                                    ₦{{ parseFloat(v.price).toLocaleString() }}
                                </div>
                            </button>
                        </div>
                    </div>
                    
                    <div v-if="product.short_description" class="prose dark:prose-invert max-w-none mb-6 text-gray-600 dark:text-gray-400 text-lg leading-relaxed">
                        <p class="whitespace-pre-line">{{ product.short_description }}</p>
                    </div>
                    
                    <div class="flex flex-col gap-3 border-t dark:border-gray-700 pt-6 mt-auto">
                        <div class="flex items-center gap-3">
                            <div class="flex items-center border dark:border-gray-600 rounded-md bg-gray-50 dark:bg-gray-900 relative h-12">
                                <button type="button" @click="form.quantity > 1 ? form.quantity-- : 1" class="px-4 h-full text-gray-600 dark:text-gray-400 hover:bg-gray-200 dark:hover:bg-gray-700 transition font-bold disabled:opacity-50">-</button>
                                <input type="number" v-model="form.quantity" min="1" :max="maxStock" class="w-12 text-center border-none focus:ring-0 bg-transparent font-medium p-0" />
                                <button type="button" @click="form.quantity < maxStock ? form.quantity++ : form.quantity" class="px-4 h-full text-gray-600 dark:text-gray-400 hover:bg-gray-200 dark:hover:bg-gray-700 transition font-bold disabled:opacity-50">+</button>
                            </div>
                            <button @click="addToCart" 
                                :disabled="form.processing || !isPurchasable || (product.variants?.length > 0 && !selectedVariant)" 
                                class="bg-gray-800 hover:bg-gray-900 dark:bg-blue-600 dark:hover:bg-blue-700 text-white font-bold transition flex-1 h-12 rounded-md disabled:opacity-50 disabled:cursor-not-allowed uppercase tracking-wide flex items-center justify-center gap-2 text-sm">
                                <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z"></path></svg>
                                {{ !isPurchasable ? 'Unavailable' : 'Add to Cart' }}
                            </button>

                            <button @click="addToCompare" :disabled="comparing"
                                title="Compare Product"
                                class="h-12 w-12 flex-shrink-0 rounded-md bg-white hover:bg-blue-50 text-gray-600 hover:text-blue-600 dark:bg-gray-800 dark:hover:bg-gray-700 dark:text-gray-300 transition disabled:opacity-50 flex items-center justify-center border border-gray-300 dark:border-gray-600 shadow-sm">
                                <svg v-if="!comparing" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4"/>
                                </svg>
                                <svg v-else class="w-5 h-5 animate-spin" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/>
                                </svg>
                            </button>
                        </div>
                        <button @click="buyNow" 
                            :disabled="form.processing || !isPurchasable || (product.variants?.length > 0 && !selectedVariant)" 
                            class="bg-blue-600 hover:bg-blue-700 dark:bg-green-600 dark:hover:bg-green-700 text-white font-bold transition w-full h-12 rounded-md disabled:opacity-50 disabled:cursor-not-allowed uppercase tracking-wide flex items-center justify-center gap-2 text-sm shadow-md hover:shadow-lg">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M13 10V3L4 14h7v7l9-11h-7z"></path></svg>
                            {{ !isPurchasable ? 'Unavailable' : ((product.variants?.length > 0 && !selectedVariant) ? 'Select Option to Buy Now' : 'Buy Now') }}
                        </button>
                    </div>
                </div>
            </div>
            
            <!-- Full Description Section -->
            <div v-if="product.description" class="mt-12 bg-white dark:bg-gray-800 p-8 rounded-xl shadow-sm border dark:border-gray-700">
                <h3 class="text-2xl font-bold mb-6 text-black dark:text-white border-b dark:border-gray-700 pb-4">Product Description</h3>
                <div class="ql-snow">
                    <div class="ql-editor prose prose-blue prose-lg dark:prose-invert max-w-none text-gray-800 dark:text-gray-200" v-html="product.description" style="padding: 0;"></div>
                </div>
            </div>

            <!-- Related Products -->
            <div v-if="related && related.length > 0" class="mt-16">
                <h3 class="text-2xl font-bold mb-6 text-black dark:text-white">You might also like</h3>
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6">
                    <div v-for="rel in related" :key="rel.id" class="border dark:border-gray-700 rounded-lg overflow-hidden shadow-sm hover:shadow-md transition bg-white dark:bg-gray-800 flex flex-col group">
                        <Link :href="route('shop.show', rel.url_key)" class="block flex-1">
                            <div class="overflow-hidden">
                                <img :src="rel.image ? '/storage/' + rel.image : 'https://placehold.co/400x300?text=' + rel.name" class="w-full h-48 object-cover group-hover:scale-105 transition duration-300" />
                            </div>
                            <div class="p-4">
                                <h4 class="font-semibold text-gray-900 dark:text-white mb-1 group-hover:text-blue-500 transition">{{ rel.name }}</h4>
                                <p class="text-blue-600 dark:text-blue-400 font-bold">₦{{ parseFloat(rel.price).toLocaleString() }}</p>
                            </div>
                        </Link>
                    </div>
                </div>
            </div>
        </div>
    </Layout>
</template>

<style>
@import '@vueup/vue-quill/dist/vue-quill.snow.css';

/* Remove default quill editor padding to align perfectly in the wrapper */
.ql-editor {
    padding: 0 !important;
}
</style>
