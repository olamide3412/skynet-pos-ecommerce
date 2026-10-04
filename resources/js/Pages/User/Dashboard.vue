<script setup>
import { Head, Link } from '@inertiajs/vue3';
import Layout from '@/Layouts/Layout.vue';

defineProps({
    orders: Array
});
</script>

<template>
    <Head title="My Dashboard" />
    <Layout>
        <div class="container mx-auto px-4 py-8 max-w-5xl text-black dark:text-white">
            <div class="flex items-center justify-between mb-8 pb-4 border-b dark:border-gray-800">
                <h1 class="text-3xl font-bold">My Account</h1>
                <div class="text-gray-500 dark:text-gray-400">Welcome back, {{ $page.props.auth.user.name }}</div>
            </div>
            
            <h2 class="text-2xl font-bold mb-6">Order History</h2>
            
            <div class="space-y-6">
                <div v-for="order in orders" :key="order.id" class="bg-white dark:bg-gray-800 rounded-xl shadow-sm overflow-hidden border dark:border-gray-700">
                    <div class="bg-gray-50 dark:bg-gray-900/50 p-4 border-b dark:border-gray-700 flex flex-wrap justify-between items-center gap-4">
                        <div class="flex gap-8">
                            <div>
                                <p class="text-xs text-gray-500 uppercase font-bold mb-1">Order Placed</p>
                                <p class="font-medium">{{ new Date(order.created_at).toLocaleDateString() }}</p>
                            </div>
                            <div>
                                <p class="text-xs text-gray-500 uppercase font-bold mb-1">Total</p>
                                <p class="font-bold text-blue-600 dark:text-blue-400">₦{{ parseFloat(order.total_amount).toLocaleString() }}</p>
                            </div>
                        </div>
                        <div class="text-right flex items-center gap-4">
                            <span class="px-3 py-1 text-xs rounded font-bold uppercase tracking-wider" :class="{
                                'bg-yellow-100 text-yellow-800': order.status === 'pending',
                                'bg-blue-100 text-blue-800': order.status === 'paid',
                                'bg-purple-100 text-purple-800': order.status === 'shipped',
                                'bg-green-100 text-green-800': order.status === 'completed',
                                'bg-red-100 text-red-800': order.status === 'cancelled'
                            }">{{ order.status }}</span>
                            <div class="text-sm">
                                <span class="text-gray-500">Order #</span><span class="font-mono">{{ order.id }}</span>
                            </div>
                        </div>
                    </div>
                    
                    <div class="p-6">
                        <div class="space-y-4">
                            <div v-for="item in order.items" :key="item.id" class="flex gap-6 items-center">
                                <img :src="item.product && item.product.image ? '/storage/' + item.product.image : 'https://placehold.co/100x100?text=Product'" class="w-20 h-20 object-cover rounded shadow-sm border dark:border-gray-600" />
                                <div class="flex-1 border-b dark:border-gray-700 pb-4 last:border-0 last:pb-0">
                                    <h4 class="font-bold text-lg">
                                        <Link v-if="item.product" :href="route('shop.show', item.product?.url_key)" class="hover:text-blue-600 transition">{{ item.product.name }}</Link>
                                        <span v-else>Unknown Product</span>
                                    </h4>
                                    <p class="text-gray-500 text-sm mt-1">₦{{ parseFloat(item.price).toLocaleString() }} x {{ item.quantity }}</p>
                                    <p class="font-semibold text-blue-600 dark:text-blue-400 mt-2">Sum: ₦{{ (item.price * item.quantity).toLocaleString() }}</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div v-if="orders.length === 0" class="text-center py-16 bg-white dark:bg-gray-800 rounded-xl shadow-sm border dark:border-gray-700">
                    <p class="text-gray-500 text-lg mb-4">You have not placed any orders yet.</p>
                    <Link :href="route('shop.index')" class="bg-blue-600 text-white px-6 py-2 rounded shadow hover:bg-blue-700 transition">Start Shopping</Link>
                </div>
            </div>
        </div>
    </Layout>
</template>
