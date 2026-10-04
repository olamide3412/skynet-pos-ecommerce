<script setup>
import { Head, Link, useForm, router } from '@inertiajs/vue3';
import Layout from '@/Layouts/Layout.vue';
import StorefrontBreadcrumb from '@/Components/StorefrontBreadcrumb.vue';
import { computed, reactive } from 'vue';
import { useToast } from 'vue-toastification';

const props = defineProps({
    compare: Object,
});

const toast = useToast();
const removing = reactive({});
const addingToCart = reactive({});

const hasItems = computed(() => {
    return props.compare && props.compare.items && props.compare.items.length > 0;
});

const items = computed(() => {
    return hasItems.value ? props.compare.items : [];
});

const breadcrumbs = [
    { label: 'Home', url: route('home') },
    { label: 'Compare Products' }
];

const removeFromCompare = (itemId) => {
    removing[itemId] = true;
    router.delete(route('compare.destroy', itemId), {
        preserveScroll: true,
        onError: () => toast.error('Could not remove product.'),
        onFinish: () => { removing[itemId] = false; },
    });
};

const addToCart = (product) => {
    addingToCart[product.id] = true;
    useForm({ product_id: product.id, quantity: 1 }).post(route('cart.store'), {
        preserveScroll: true,
        onError: () => toast.error('Could not add to cart.'),
        onFinish: () => { addingToCart[product.id] = false; },
    });
};
</script>

<template>
    <Head title="Compare Products" />
    <Layout>
        <div class="container mx-auto px-4 pt-4 pb-16 text-black dark:text-white">
            <StorefrontBreadcrumb :items="breadcrumbs" />
            
            <div class="flex items-center justify-between mb-8">
                <h1 class="text-3xl font-extrabold text-blue-900 dark:text-blue-100 uppercase tracking-tight">Compare Products</h1>
                <Link :href="route('shop.index')" class="text-sm font-semibold text-blue-600 hover:text-blue-800 dark:text-blue-400 dark:hover:text-blue-300 flex items-center gap-1 bg-blue-50 dark:bg-gray-800 px-4 py-2 rounded-full border border-blue-200 dark:border-gray-700 transition">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
                    Continue Shopping
                </Link>
            </div>

            <!-- Empty State -->
            <div v-if="!hasItems" class="text-center py-20 bg-white dark:bg-gray-800 rounded-2xl shadow-sm border border-gray-100 dark:border-gray-700">
                <div class="w-24 h-24 mx-auto bg-blue-50 dark:bg-gray-700 rounded-full flex items-center justify-center mb-6 border border-blue-100 dark:border-gray-600">
                    <svg class="w-12 h-12 text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4"/>
                    </svg>
                </div>
                <h2 class="text-2xl font-bold mb-3 text-gray-900 dark:text-gray-100">No products to compare</h2>
                <p class="text-gray-500 mb-8 max-w-md mx-auto">You haven't added any products to your comparison list yet. Browse our store and click the compare icon on products you want to evaluate side-by-side.</p>
                <Link :href="route('shop.index')" class="inline-flex items-center justify-center px-8 py-3.5 bg-blue-600 hover:bg-blue-700 text-white font-bold rounded-xl transition shadow-lg hover:shadow-blue-500/30">
                    Discover Products
                </Link>
            </div>

            <!-- Compare Table/Grid -->
            <div v-else class="bg-white dark:bg-gray-800 rounded-2xl shadow-xl border border-gray-100 dark:border-gray-700 overflow-hidden overflow-x-auto relative">
                
                <table class="w-full text-left border-collapse table-fixed min-w-[800px]">
                    <tbody>
                        <!-- Product Preview Row (Images, Names, Price, Actions) -->
                        <tr>
                            <th class="w-48 sm:w-64 p-6 align-top bg-gray-50/50 dark:bg-gray-900/50 border-b border-r dark:border-gray-700">
                                <span class="text-sm uppercase tracking-widest font-bold text-gray-400 dark:text-gray-500">Overview</span>
                            </th>
                            <td v-for="item in items" :key="'header-'+item.id" class="w-64 sm:w-80 p-6 align-top border-b border-r dark:border-gray-700 relative group">
                                <!-- Remove Button -->
                                <button @click="removeFromCompare(item.id)" :disabled="removing[item.id]" title="Remove from list" class="absolute top-4 right-4 z-10 w-8 h-8 flex items-center justify-center bg-white dark:bg-gray-700 rounded-full border shadow-sm text-gray-400 hover:text-red-500 hover:border-red-200 hover:bg-red-50 dark:hover:bg-red-900/40 transition disabled:opacity-50">
                                    <svg class="w-4 h-4 text-inherit" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                                </button>
                                
                                <div class="flex flex-col h-full relative">
                                    <Link :href="route('shop.show', item.product.url_key)" class="block mb-4 overflow-hidden rounded-xl bg-white border dark:border-gray-600 relative group">
                                        <div class="aspect-video sm:aspect-square w-full">
                                            <img :src="item.product.image ? '/storage/' + item.product.image : 'https://placehold.co/400x300?text=' + item.product.name" class="w-full h-full object-cover group-hover:scale-105 transition duration-500" :alt="item.product.name" />
                                        </div>
                                    </Link>
                                    
                                    <p v-if="item.product.category" class="text-[10px] sm:text-xs font-bold text-blue-600 dark:text-blue-400 mb-1 uppercase tracking-wider">{{ item.product.category.name }}</p>
                                    <Link :href="route('shop.show', item.product.url_key)">
                                        <h3 class="text-base sm:text-lg font-bold text-gray-900 dark:text-white line-clamp-2 leading-tight mb-2 hover:text-blue-600 transition">{{ item.product.name }}</h3>
                                    </Link>
                                    
                                    <!-- Price -->
                                    <div class="mt-auto pt-2 pb-4">
                                        <div class="flex flex-col">
                                            <span v-if="item.product.discount_price" class="text-xs text-gray-400 line-through">₦{{ parseFloat(item.product.price).toLocaleString() }}</span>
                                            <span class="text-xl sm:text-2xl font-black text-blue-700 dark:text-blue-400">₦{{ item.product.discount_price ? parseFloat(item.product.discount_price).toLocaleString() : parseFloat(item.product.price).toLocaleString() }}</span>
                                        </div>
                                    </div>
                                    
                                    <!-- Add to Cart -->
                                    <button @click="addToCart(item.product)" :disabled="addingToCart[item.product.id] || item.product.stock < 1"
                                        class="w-full py-2.5 rounded-lg bg-gray-900 hover:bg-black dark:bg-blue-600 dark:hover:bg-blue-700 text-white text-sm font-bold transition flex items-center justify-center gap-2 group/btn disabled:opacity-50 mt-2">
                                        <svg v-if="!addingToCart[item.product.id]" class="w-4 h-4 group-hover/btn:-translate-y-0.5 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z"/></svg>
                                        <svg v-else class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
                                        {{ addingToCart[item.product.id] ? 'Adding...' : (item.product.stock < 1 ? 'Out of Stock' : 'Add to Cart') }}
                                    </button>
                                </div>
                            </td>
                        </tr>

                        <!-- Description Row -->
                        <tr>
                            <th class="p-4 sm:p-6 text-sm font-semibold text-gray-600 dark:text-gray-300 bg-gray-50/50 dark:bg-gray-900/50 border-b border-r dark:border-gray-700">Description</th>
                            <td v-for="item in items" :key="'desc-'+item.id" class="p-4 sm:p-6 align-top text-sm text-gray-600 dark:text-gray-400 border-b border-r dark:border-gray-700">
                                <p class="line-clamp-4 leading-relaxed">{{ item.product.short_description || 'No description provided.' }}</p>
                            </td>
                        </tr>

                        <!-- Stock Status Row -->
                        <tr>
                            <th class="p-4 sm:p-6 text-sm font-semibold text-gray-600 dark:text-gray-300 bg-gray-50/50 dark:bg-gray-900/50 border-b border-r dark:border-gray-700">Availability</th>
                            <td v-for="item in items" :key="'stock-'+item.id" class="p-4 sm:p-6 border-b border-r dark:border-gray-700">
                                <span v-if="item.product.stock > 0" class="inline-flex items-center gap-1.5 px-3 py-1 bg-green-50 dark:bg-green-900/30 text-green-700 dark:text-green-400 rounded-full text-xs font-bold border border-green-200 dark:border-green-800">
                                    <span class="w-1.5 h-1.5 rounded-full bg-green-500"></span> In Stock
                                </span>
                                <span v-else class="inline-flex items-center gap-1.5 px-3 py-1 bg-red-50 dark:bg-red-900/30 text-red-700 dark:text-red-400 rounded-full text-xs font-bold border border-red-200 dark:border-red-800">
                                    <span class="w-1.5 h-1.5 rounded-full bg-red-500"></span> Out of Stock
                                </span>
                            </td>
                        </tr>

                    </tbody>
                </table>
            </div>
            
        </div>
    </Layout>
</template>
