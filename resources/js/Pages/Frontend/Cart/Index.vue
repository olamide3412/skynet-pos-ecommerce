<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import Layout from '@/Layouts/Layout.vue';

const props = defineProps({
    cart: Object
});

const updateQuantity = (item, quantity) => {
    if (quantity < 1) return;
    useForm({ quantity }).put(route('cart.update', item.id), { preserveScroll: true });
};

const removeItem = (item) => {
    useForm({}).delete(route('cart.destroy', item.id), { preserveScroll: true });
};
</script>

<template>
    <Head title="Shopping Cart" />
    <Layout>
        <div class="container mx-auto px-4 py-8 max-w-5xl text-black dark:text-white min-h-[60vh]">
            <h1 class="text-3xl font-bold mb-8 flex items-center gap-2">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8 text-blue-600 dark:text-blue-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z" />
                </svg>
                Your Shopping Cart
            </h1>
            
            <div v-if="cart && cart.items && cart.items.length > 0" class="bg-white dark:bg-gray-800 shadow-lg rounded-xl overflow-hidden border dark:border-gray-700">
                <div class="p-6 md:p-8">
                    <div class="hidden md:grid grid-cols-12 gap-4 text-sm font-semibold text-gray-500 uppercase pb-4 border-b dark:border-gray-700 mb-4">
                        <div class="col-span-6">Product</div>
                        <div class="col-span-2 text-center">Price</div>
                        <div class="col-span-2 text-center">Quantity</div>
                        <div class="col-span-2 text-right">Total</div>
                    </div>
                    
                    <div v-for="item in cart.items" :key="item.id" class="grid grid-cols-1 md:grid-cols-12 gap-4 items-center py-6 border-b dark:border-gray-700 last:border-0 relative">
                        <div class="col-span-1 md:col-span-6 flex items-center">
                            <img :src="item.product.image ? '/storage/' + item.product.image : 'https://placehold.co/100x100?text=' + item.product.name" class="w-20 h-20 md:w-24 md:h-24 object-cover rounded-lg shadow-sm border dark:border-gray-600 shrink-0" />
                            <div class="ml-4">
                                <h3 class="font-bold text-lg leading-tight hover:text-blue-600 transition"><Link :href="route('shop.show', item.product?.url_key)">{{ item.product.name }}</Link></h3>
                                <div class="flex flex-wrap items-center gap-1.5 mt-1.5">
                                    <span v-if="item.unit_name" class="inline-flex items-center px-2 py-0.5 rounded text-xs font-semibold bg-blue-100 text-blue-800 dark:bg-blue-900/40 dark:text-blue-300">
                                        Unit: {{ item.unit_name }}
                                    </span>
                                    <span v-if="item.variant || item.variant_label" class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-gray-100 text-gray-700 dark:bg-gray-700 dark:text-gray-300">
                                        {{ item.variant_label || item.variant }}
                                    </span>
                                </div>
                                <div class="md:hidden text-blue-600 dark:text-blue-400 font-semibold mt-1">₦{{ parseFloat(item.price).toLocaleString() }}</div>
                            </div>
                        </div>
                        
                        <div class="hidden md:block col-span-2 text-center font-medium">
                            ₦{{ parseFloat(item.price).toLocaleString() }}
                        </div>
                        
                        <div class="col-span-1 md:col-span-2 flex justify-start md:justify-center items-center mt-2 md:mt-0">
                            <div class="flex items-center border dark:border-gray-600 rounded-md bg-gray-50 dark:bg-gray-900 shadow-sm">
                                <button @click="updateQuantity(item, item.quantity - 1)" class="px-3 py-1 hover:bg-gray-200 dark:hover:bg-gray-700 transition font-bold">-</button>
                                <span class="px-4 py-1 text-center font-medium w-12 border-x dark:border-gray-600">{{ item.quantity }}</span>
                                <button @click="updateQuantity(item, item.quantity + 1)" class="px-3 py-1 hover:bg-gray-200 dark:hover:bg-gray-700 transition font-bold">+</button>
                            </div>
                        </div>
                        
                        <div class="hidden md:block col-span-2 text-right font-bold text-lg text-blue-600 dark:text-blue-400">
                            ₦{{ (item.price * item.quantity).toLocaleString() }}
                        </div>
                        
                        <button @click="removeItem(item)" class="absolute top-4 right-0 md:static md:col-span-12 ml-auto mt-2 md:mt-4 text-red-500 hover:text-red-700 bg-red-50 hover:bg-red-100 dark:bg-red-900/20 dark:hover:bg-red-900/40 px-3 py-1 rounded inline-flex items-center gap-1 text-sm transition">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor">
                              <path fill-rule="evenodd" d="M9 2a1 1 0 00-.894.553L7.382 4H4a1 1 0 000 2v10a2 2 0 002 2h8a2 2 0 002-2V6a1 1 0 100-2h-3.382l-.724-1.447A1 1 0 0011 2H9zM7 8a1 1 0 012 0v6a1 1 0 11-2 0V8zm5-1a1 1 0 00-1 1v6a1 1 0 102 0V8a1 1 0 00-1-1z" clip-rule="evenodd" />
                            </svg>
                            Remove
                        </button>
                    </div>
                </div>
                
                <div class="bg-gray-50 dark:bg-gray-900/80 p-6 md:p-8 border-t dark:border-gray-700 mt-auto">
                    <div class="flex flex-col md:flex-row justify-between items-center gap-6">
                        <Link :href="route('shop.index')" class="text-gray-600 dark:text-gray-400 hover:text-blue-600 dark:hover:text-blue-400 hover:underline flex items-center font-medium">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 mr-1 pb-px" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                            </svg>
                            Continue Shopping
                        </Link>
                        
                        <div class="text-right w-full md:w-auto p-6 md:p-0 bg-white dark:bg-gray-800 md:bg-transparent rounded-lg shadow-sm md:shadow-none border dark:border-gray-700 md:border-transparent">
                            <div class="flex justify-between md:justify-end items-end gap-12 text-lg font-medium mb-4 pb-4 md:border-b-0 border-b dark:border-gray-700">
                                <span class="text-gray-600 dark:text-gray-400">Total</span>
                                <span class="text-3xl font-bold text-gray-900 dark:text-white">
                                    ₦{{ cart.items.reduce((acc, item) => acc + (item.price * item.quantity), 0).toLocaleString() }}
                                </span>
                            </div>
                            <Link :href="route('checkout.index')" class="block w-full md:w-auto bg-blue-600 hover:bg-blue-700 text-white px-10 py-4 rounded-lg font-bold text-lg transition shadow-md hover:shadow-lg text-center tracking-wide uppercase">
                                Check Out Now
                            </Link>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Empty state -->
            <div v-else class="text-center py-24 bg-white dark:bg-gray-800 rounded-xl shadow-sm border dark:border-gray-700">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-24 w-24 mx-auto text-gray-300 dark:text-gray-600 mb-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z" />
                </svg>
                <h2 class="text-2xl font-bold text-gray-800 dark:text-white mb-3">Your cart is completely empty.</h2>
                <p class="text-gray-500 mb-8 max-w-md mx-auto">Looks like you haven't added anything yet. Explore our top products and find exactly what you're looking for!</p>
                <Link :href="route('shop.index')" class="inline-block bg-blue-600 text-white px-8 py-3 rounded-lg font-bold shadow-md hover:bg-blue-700 transition hover:shadow-lg uppercase tracking-wider">Start Shopping</Link>
            </div>
        </div>
    </Layout>
</template>
