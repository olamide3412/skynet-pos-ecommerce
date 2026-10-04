<script setup>
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import Layout from '@/Layouts/Layout.vue';
import { ref, reactive } from 'vue';
import { useToast } from 'vue-toastification';
import Footer from '@/Components/Footer.vue';

const props = defineProps({
    items: Array,
});

const page = usePage();
const toast = useToast();
const removing = reactive({});

// Local copy so UI updates immediately
const items = ref(props.items);

const removeFromWishlist = (item) => {
    removing[item.id] = true;
    useForm({ product_id: item.product_id }).post(route('wishlist.toggle'), {
        preserveScroll: true,
        onSuccess: () => {
            items.value = items.value.filter(i => i.id !== item.id);
            toast.info(`${item.product?.name} removed from wishlist.`);
        },
        onError: () => toast.error('Could not remove item.'),
        onFinish: () => { removing[item.id] = false; },
    });
};

const addToCart = (item) => {
    useForm({ product_id: item.product_id, quantity: 1 }).post(route('cart.store'), {
        preserveScroll: true,
        onSuccess: () => toast.success(`${item.product?.name} added to cart!`),
        onError: () => toast.error('Could not add to cart.'),
    });
};
</script>

<template>
    <Head title="My Wishlist" />
    <Layout>
        <div class="container mx-auto px-4 py-12 max-w-5xl">
            <div class="flex items-center justify-between mb-8">
                <div>
                    <h1 class="text-3xl font-extrabold text-gray-900 dark:text-white">My Wishlist</h1>
                    <p class="text-gray-500 dark:text-gray-400 mt-1">{{ items.length }} saved item{{ items.length !== 1 ? 's' : '' }}</p>
                </div>
                <Link :href="route('shop.index')" class="text-sm text-blue-600 dark:text-blue-400 font-semibold hover:underline flex items-center gap-1">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z"/>
                    </svg>
                    Continue Shopping
                </Link>
            </div>

            <!-- Empty state -->
            <div v-if="items.length === 0"
                class="text-center py-24 bg-white dark:bg-gray-800 rounded-2xl border dark:border-gray-700">
                <svg class="w-16 h-16 mx-auto text-gray-300 dark:text-gray-600 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                        d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z"/>
                </svg>
                <h2 class="text-xl font-bold text-gray-600 dark:text-gray-300 mb-2">Your wishlist is empty</h2>
                <p class="text-gray-500 dark:text-gray-400 mb-6">Browse the shop and click the ❤️ icon to save items here.</p>
                <Link :href="route('shop.index')"
                    class="bg-blue-600 hover:bg-blue-700 text-white font-bold px-6 py-3 rounded-xl transition">
                    Browse Products
                </Link>
            </div>

            <!-- Wishlist Grid -->
            <div v-else class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6">
                <div v-for="item in items" :key="item.id"
                    class="bg-white dark:bg-gray-800 rounded-2xl border dark:border-gray-700 shadow-sm hover:shadow-md transition overflow-hidden flex flex-col">

                    <!-- Product Image -->
                    <Link :href="route('shop.show', item.product?.url_key)" class="block relative overflow-hidden">
                        <img
                            :src="item.product?.image ? '/storage/' + item.product.image : 'https://placehold.co/400x300?text=Product'"
                            :alt="item.product?.name"
                            class="w-full h-48 object-cover hover:scale-105 transition duration-300" />
                        <!-- Remove heart overlay -->
                        <button
                            @click.prevent="removeFromWishlist(item)"
                            :disabled="removing[item.id]"
                            title="Remove from wishlist"
                            class="absolute top-3 right-3 w-9 h-9 rounded-full bg-white dark:bg-gray-700 shadow flex items-center justify-center border border-red-200 hover:scale-110 transition-transform disabled:opacity-60">
                            <svg class="w-5 h-5 text-red-500 fill-red-500" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z"/>
                            </svg>
                        </button>
                    </Link>

                    <div class="p-5 flex flex-col flex-1">
                        <Link :href="route('shop.show', item.product?.url_key)" class="block mb-2">
                            <h3 class="font-bold text-gray-900 dark:text-white hover:text-blue-600 dark:hover:text-blue-400 transition line-clamp-2">
                                {{ item.product?.name }}
                            </h3>
                        </Link>

                        <p v-if="item.product?.category" class="text-xs text-gray-400 dark:text-gray-500 mb-3">
                            {{ item.product.category.name }}
                        </p>

                        <!-- Price -->
                        <div class="flex items-end gap-2 mb-4 mt-auto">
                            <span v-if="item.product?.discount_price" class="text-sm text-gray-400 line-through">
                                ₦{{ parseFloat(item.product.price).toLocaleString() }}
                            </span>
                            <span class="text-xl font-extrabold text-blue-600 dark:text-blue-400">
                                ₦{{ item.product?.discount_price
                                    ? parseFloat(item.product.discount_price).toLocaleString()
                                    : parseFloat(item.product?.price || 0).toLocaleString() }}
                            </span>
                        </div>

                        <!-- Stock -->
                        <div class="mb-4">
                            <span v-if="item.product?.stock > 0"
                                class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">
                                In Stock
                            </span>
                            <span v-else
                                class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-800">
                                Out of Stock
                            </span>
                        </div>

                        <!-- Actions -->
                        <div class="flex gap-2">
                            <button
                                @click="addToCart(item)"
                                :disabled="!item.product?.stock"
                                class="flex-1 py-2.5 rounded-xl bg-blue-600 hover:bg-blue-700 text-white font-bold text-sm transition disabled:opacity-50 disabled:cursor-not-allowed flex items-center justify-center gap-1.5">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z"/>
                                </svg>
                                Add to Cart
                            </button>
                            <button
                                @click="removeFromWishlist(item)"
                                :disabled="removing[item.id]"
                                class="p-2.5 rounded-xl border dark:border-gray-600 text-red-500 hover:bg-red-50 dark:hover:bg-red-900/20 transition disabled:opacity-50"
                                title="Remove">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                                </svg>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <Footer />
    </Layout>
</template>
