<script setup>
import { Head, Link } from '@inertiajs/vue3';
import Layout from '@/Layouts/Layout.vue';
import Pagination from '@/Components/Pagination.vue';

defineProps({
    orders: Object,
});

const statusBadge = (status) => ({
    pending:   'bg-yellow-100 text-yellow-800',
    paid:      'bg-blue-100 text-blue-800',
    shipped:   'bg-purple-100 text-purple-800',
    completed: 'bg-green-100 text-green-800',
    cancelled: 'bg-red-100 text-red-800',
}[status] || 'bg-gray-100 text-gray-800');
</script>

<template>
    <Head title="My Orders" />
    <Layout>
        <div class="container mx-auto px-4 py-12 max-w-5xl">
            <div class="flex items-center justify-between mb-8">
                <div>
                    <h1 class="text-3xl font-extrabold text-gray-900 dark:text-white">My Orders</h1>
                    <p class="text-gray-500 dark:text-gray-400 mt-1">All orders placed with your account</p>
                </div>
                <Link :href="route('order.track')" class="text-sm text-blue-600 dark:text-blue-400 font-semibold hover:underline flex items-center gap-1">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                    Track by ID
                </Link>
            </div>

            <!-- Empty state -->
            <div v-if="orders.data.length === 0" class="text-center py-24 bg-white dark:bg-gray-800 rounded-2xl border dark:border-gray-700">
                <svg class="w-16 h-16 mx-auto text-gray-300 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/></svg>
                <h2 class="text-xl font-bold text-gray-600 dark:text-gray-300 mb-2">No orders yet</h2>
                <p class="text-gray-500 dark:text-gray-400 mb-6">Start shopping to place your first order!</p>
                <Link :href="route('shop.index')" class="bg-blue-600 hover:bg-blue-700 text-white font-bold px-6 py-3 rounded-xl transition">Browse Products</Link>
            </div>

            <!-- Orders list -->
            <div v-else class="space-y-4">
                <div v-for="order in orders.data" :key="order.id"
                    class="bg-white dark:bg-gray-800 rounded-2xl border dark:border-gray-700 shadow-sm hover:shadow-md transition overflow-hidden">
                    <div class="flex items-center justify-between p-5 border-b dark:border-gray-700">
                        <div>
                            <p class="text-xs text-gray-500 dark:text-gray-400 uppercase tracking-wider">Order</p>
                            <h3 class="text-xl font-extrabold text-gray-900 dark:text-white">#{{ order.id }}</h3>
                        </div>
                        <div class="text-right">
                            <span :class="['px-3 py-1 rounded-full text-xs font-bold uppercase tracking-wider', statusBadge(order.status)]">
                                {{ order.status }}
                            </span>
                            <p class="text-xs text-gray-400 mt-1">{{ new Date(order.created_at).toLocaleDateString('en-NG', { day:'numeric', month:'short', year:'numeric' }) }}</p>
                        </div>
                    </div>

                    <!-- Items preview -->
                    <div class="px-5 py-3 flex items-center gap-3 flex-wrap">
                        <div v-for="item in order.items.slice(0, 3)" :key="item.id" class="flex items-center gap-2 bg-gray-50 dark:bg-gray-700 rounded-lg px-3 py-2">
                            <img :src="item.product?.image ? '/storage/' + item.product.image : 'https://placehold.co/32x32?text=P'" class="w-8 h-8 object-cover rounded" />
                            <span class="text-sm font-medium line-clamp-1 max-w-[120px]">{{ item.product?.name }}</span>
                            <span class="text-xs text-gray-400">×{{ item.quantity }}</span>
                        </div>
                        <span v-if="order.items.length > 3" class="text-sm text-gray-500 dark:text-gray-400">+{{ order.items.length - 3 }} more</span>
                    </div>

                    <div class="flex items-center justify-between px-5 pb-5 pt-1">
                        <div>
                            <span class="text-xl font-extrabold text-blue-600 dark:text-blue-400">₦{{ parseFloat(order.total_amount).toLocaleString() }}</span>
                            <span class="text-sm text-gray-400 ml-2 capitalize">· {{ order.payment_method }}</span>
                        </div>
                        <Link :href="route('customer.orders.show', order.id)" class="bg-blue-600 hover:bg-blue-700 text-white font-bold text-sm px-4 py-2 rounded-lg transition flex items-center gap-1">
                            View Details
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                        </Link>
                    </div>
                </div>
            </div>

            <Pagination v-if="orders.data.length > 0" :paginator="orders" class="mt-8" />
        </div>
    </Layout>
</template>
