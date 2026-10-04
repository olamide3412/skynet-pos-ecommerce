<script setup>
import { Head, Link, router } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import Pagination from '@/Components/Pagination.vue';
import { ref, watch } from 'vue';
import debounce from 'lodash/debounce';

const props = defineProps({
    orders: Object,
    filters: {
        type: Object,
        default: () => ({}),
    }
});

const search = ref(props.filters.search || '');
const status = ref(props.filters.status || '');
const startDate = ref(props.filters.start_date || '');
const endDate = ref(props.filters.end_date || '');

const applyFilters = debounce(() => {
    router.get(route('admin.orders.index'), {
        search: search.value || undefined,
        status: status.value || undefined,
        start_date: startDate.value || undefined,
        end_date: endDate.value || undefined,
    }, { preserveState: true, replace: true });
}, 300);

watch([search, status, startDate, endDate], applyFilters);

const clearFilters = () => {
    search.value = '';
    status.value = '';
    startDate.value = '';
    endDate.value = '';
};
</script>

<template>
    <Head title="Manage Orders" />
    <AdminLayout>
        <div class="container mx-auto px-4 py-8 text-black dark:text-white max-w-7xl">
            <h1 class="text-3xl font-bold mb-6">Manage Orders</h1>
            
            <!-- Filters Bar -->
            <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm border dark:border-gray-700 p-4 mb-6 flex flex-wrap gap-4 items-end">
                <div class="flex-1 min-w-[200px]">
                    <label class="block text-xs font-bold text-gray-500 dark:text-gray-400 uppercase tracking-wide mb-1">Search</label>
                    <input v-model="search" type="text" placeholder="ID, tracking, email..." 
                        class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white px-3 py-2 text-sm focus:ring-blue-500 focus:border-blue-500" />
                </div>
                
                <div class="w-full sm:w-auto">
                    <label class="block text-xs font-bold text-gray-500 dark:text-gray-400 uppercase tracking-wide mb-1">Status</label>
                    <select v-model="status" 
                        class="w-full sm:w-40 rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white px-3 py-2 text-sm focus:ring-blue-500 focus:border-blue-500">
                        <option value="">All Statuses</option>
                        <option value="pending">Pending</option>
                        <option value="processing">Processing</option>
                        <option value="shipped">Shipped</option>
                        <option value="completed">Completed</option>
                        <option value="cancelled">Cancelled</option>
                    </select>
                </div>

                <div class="w-full sm:w-auto flex gap-3">
                    <div>
                        <label class="block text-xs font-bold text-gray-500 dark:text-gray-400 uppercase tracking-wide mb-1">From Date</label>
                        <input v-model="startDate" type="date" 
                            class="w-full sm:w-auto rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white px-3 py-2 text-sm focus:ring-blue-500 focus:border-blue-500" />
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-gray-500 dark:text-gray-400 uppercase tracking-wide mb-1">To Date</label>
                        <input v-model="endDate" type="date" 
                            class="w-full sm:w-auto rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white px-3 py-2 text-sm focus:ring-blue-500 focus:border-blue-500" />
                    </div>
                </div>

                <div v-if="search || status || startDate || endDate">
                    <button @click="clearFilters" class="px-4 py-2 text-sm font-semibold text-gray-600 dark:text-gray-300 bg-gray-100 dark:bg-gray-700 rounded-md hover:bg-gray-200 dark:hover:bg-gray-600 transition h-full">
                        Clear
                    </button>
                </div>
            </div>
            
            <div class="bg-white dark:bg-gray-800 rounded-lg shadow overflow-hidden border dark:border-gray-700">
                <div class="overflow-x-auto">
                    <table class="w-full text-left border-collapse min-w-[800px]">
                        <thead>
                            <tr class="bg-gray-100 dark:bg-gray-900 border-b dark:border-gray-700 text-sm text-gray-600 dark:text-gray-400">
                                <th class="p-4 font-semibold uppercase">Order ID</th>
                                <th class="p-4 font-semibold uppercase">Tracking #</th>
                                <th class="p-4 font-semibold uppercase">Customer</th>
                                <th class="p-4 font-semibold uppercase">Total</th>
                                <th class="p-4 font-semibold uppercase text-center">Status</th>
                                <th class="p-4 font-semibold uppercase text-right">Date</th>
                                <th class="p-4 font-semibold uppercase text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="order in orders.data" :key="order.id" class="border-b dark:border-gray-700 hover:bg-gray-50 dark:hover:bg-gray-700/60 transition">
                                <td class="p-4 font-mono font-bold text-gray-700 dark:text-gray-300">#{{ order.id }}</td>
                                <td class="p-4">
                                    <span class="font-mono text-xs bg-blue-50 dark:bg-blue-900/20 text-blue-700 dark:text-blue-300 px-2 py-1 rounded border border-blue-100 dark:border-blue-800">
                                        {{ order.tracking_number || '—' }}
                                    </span>
                                </td>
                                <td class="p-4 font-semibold">{{ order.customer ? order.customer.name : order.guest_email }}</td>
                                <td class="p-4 font-bold text-blue-600 dark:text-blue-400">₦{{ parseFloat(order.total_amount).toLocaleString() }}</td>
                                <td class="p-4 text-center">
                                    <span class="px-3 py-1 rounded text-xs font-bold uppercase tracking-wider" 
                                          :class="{
                                            'bg-yellow-100 text-yellow-800': order.status === 'pending',
                                            'bg-blue-100 text-blue-800': order.status === 'paid',
                                            'bg-purple-100 text-purple-800': order.status === 'shipped',
                                            'bg-green-100 text-green-800': order.status === 'completed',
                                            'bg-red-100 text-red-800': order.status === 'cancelled'
                                          }">
                                        {{ order.status }}
                                    </span>
                                </td>
                                <td class="p-4 text-right text-gray-500">{{ new Date(order.created_at).toLocaleDateString() }}</td>
                                <td class="p-4 text-right">
                                    <Link :href="route('admin.orders.show', order.id)" class="text-blue-500 hover:text-blue-700 font-semibold border border-blue-500 px-3 py-1 rounded hover:bg-blue-50 dark:hover:bg-gray-700 transition">View</Link>
                                </td>
                            </tr>
                            <tr v-if="!orders.data || orders.data.length === 0">
                                <td colspan="6" class="p-8 text-center text-gray-500 font-medium">No orders found.</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <Pagination v-if="orders.data && orders.data.length > 0" :paginator="orders" class="mt-6 mb-4" />
            </div>
        </div>
    </AdminLayout>
</template>
