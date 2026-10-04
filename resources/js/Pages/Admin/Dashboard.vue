<script setup>
import { Head, Link } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';

defineProps({
    stats: Object,
    recentOrders: Array
});
</script>

<template>
    <Head title="Admin Dashboard" />
    <AdminLayout>
        <div class="container mx-auto px-4 py-8 max-w-7xl text-black dark:text-white">
            <h1 class="text-3xl font-bold mb-8">Admin Dashboard</h1>
            
            <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8">
                <div class="bg-white dark:bg-gray-800 p-6 rounded-lg shadow-sm border dark:border-gray-700">
                    <p class="text-gray-500 dark:text-gray-400 text-sm font-medium mb-1">Total Revenue</p>
                    <p class="text-2xl font-bold text-blue-600 dark:text-blue-400">₦{{ parseFloat(stats.total_revenue).toLocaleString() }}</p>
                </div>
                <div class="bg-white dark:bg-gray-800 p-6 rounded-lg shadow-sm border dark:border-gray-700">
                    <p class="text-gray-500 dark:text-gray-400 text-sm font-medium mb-1">Total Orders</p>
                    <p class="text-2xl font-bold">{{ stats.total_orders }}</p>
                </div>
                <div class="bg-white dark:bg-gray-800 p-6 rounded-lg shadow-sm border dark:border-gray-700">
                    <p class="text-gray-500 dark:text-gray-400 text-sm font-medium mb-1">Products</p>
                    <p class="text-2xl font-bold">{{ stats.total_products }}</p>
                </div>
                <div class="bg-white dark:bg-gray-800 p-6 rounded-lg shadow-sm border dark:border-gray-700">
                    <p class="text-gray-500 dark:text-gray-400 text-sm font-medium mb-1">Users</p>
                    <p class="text-2xl font-bold">{{ stats.total_users }}</p>
                </div>
            </div>
            
            <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm border dark:border-gray-700">
                <div class="p-6 border-b dark:border-gray-700 flex justify-between items-center">
                    <h2 class="text-xl font-bold">Recent Orders</h2>
                    <Link :href="route('admin.orders.index')" class="text-blue-500 hover:text-blue-700 text-sm font-medium">View All Orders</Link>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full text-left">
                        <thead class="bg-gray-50 dark:bg-gray-900/50">
                            <tr>
                                <th class="p-4 text-sm font-medium text-gray-500">Order ID</th>
                                <th class="p-4 text-sm font-medium text-gray-500">Customer</th>
                                <th class="p-4 text-sm font-medium text-gray-500">Amount</th>
                                <th class="p-4 text-sm font-medium text-gray-500">Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="order in recentOrders" :key="order.id" class="border-t dark:border-gray-700 hover:bg-gray-50 dark:hover:bg-gray-700/60 transition">
                                <td class="p-4 font-mono">#{{ order.id }}</td>
                                <td class="p-4">{{ order.customer ? order.customer.name : order.guest_email }}</td>
                                <td class="p-4 font-medium">₦{{ parseFloat(order.total_amount).toLocaleString() }}</td>
                                <td class="p-4">
                                    <span class="px-2 py-1 text-xs rounded font-bold uppercase" :class="{
                                        'bg-yellow-100 text-yellow-800': order.status === 'pending',
                                        'bg-blue-100 text-blue-800': order.status === 'paid',
                                        'bg-green-100 text-green-800': order.status === 'completed',
                                        'bg-red-100 text-red-800': order.status === 'cancelled'
                                    }">{{ order.status }}</span>
                                </td>
                            </tr>
                            <tr v-if="recentOrders.length === 0">
                                <td colspan="4" class="p-6 text-center text-gray-500">No recent orders.</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </AdminLayout>
</template>
