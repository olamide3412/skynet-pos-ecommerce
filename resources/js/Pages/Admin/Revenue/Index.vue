<script setup>
import { Head, router, usePage } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { ref, watch, computed } from 'vue';
import debounce from 'lodash/debounce';

const props = defineProps({
    metrics: Object,
    chart_data: Array,
    method_stats: Array,
    filters: Object,
});

const page = usePage();

const startDate = ref(props.filters.start_date || '');
const endDate = ref(props.filters.end_date || '');

// Apply filters when dates change
const applyFilters = () => {
    router.get(route('admin.revenue.index'), {
        start_date: startDate.value || undefined,
        end_date: endDate.value || undefined,
    }, { preserveState: true, replace: true });
};

// Pagination Logic
const currentPage = ref(1);
const itemsPerPage = 30;

watch([startDate, endDate], () => {
    currentPage.value = 1;
});

const totalPages = computed(() => Math.ceil((props.chart_data || []).length / itemsPerPage));

const paginatedChartData = computed(() => {
    const start = (currentPage.value - 1) * itemsPerPage;
    const end = start + itemsPerPage;
    return (props.chart_data || []).slice(start, end);
});

const prevPage = () => { if (currentPage.value > 1) currentPage.value--; };
const nextPage = () => { if (currentPage.value < totalPages.value) currentPage.value++; };

// Format currency
const formatCurrency = (amount) => {
    return '₦' + parseFloat(amount).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
};
</script>

<template>
    <Head title="Revenue Report" />
    <AdminLayout>
        <div class="max-w-7xl mx-auto px-4 py-8 text-gray-900 dark:text-gray-100">
            <!-- Header & Date Filter -->
            <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 mb-8">
                <div>
                    <h1 class="text-2xl font-bold">Revenue Report</h1>
                    <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">Analytics for paid and completed orders within the selected period.</p>
                </div>
                
                <div class="flex items-center gap-3 bg-white dark:bg-gray-800 p-2 rounded-lg border dark:border-gray-700 shadow-sm">
                    <div class="flex items-center gap-2">
                        <label class="text-sm font-medium text-gray-600 dark:text-gray-300">From:</label>
                        <input type="date" v-model="startDate" @change="applyFilters"
                            class="rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white px-2 py-1.5 text-sm focus:ring-blue-500 focus:border-blue-500"/>
                    </div>
                    <div class="flex items-center gap-2">
                        <label class="text-sm font-medium text-gray-600 dark:text-gray-300">To:</label>
                        <input type="date" v-model="endDate" @change="applyFilters"
                            class="rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white px-2 py-1.5 text-sm focus:ring-blue-500 focus:border-blue-500"/>
                    </div>
                </div>
            </div>

            <!-- Top Metric Cards -->
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
                <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-100 dark:border-gray-700 p-6 flex flex-col items-center text-center">
                    <div class="w-12 h-12 bg-blue-100 dark:bg-blue-900/40 text-blue-600 dark:text-blue-400 rounded-full flex items-center justify-center mb-4">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    </div>
                    <p class="text-sm font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wide">Total Revenue</p>
                    <h3 class="text-3xl font-extrabold text-blue-600 dark:text-blue-400 mt-2">{{ formatCurrency(metrics.total_revenue) }}</h3>
                </div>
                
                <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-100 dark:border-gray-700 p-6 flex flex-col items-center text-center">
                    <div class="w-12 h-12 bg-purple-100 dark:bg-purple-900/40 text-purple-600 dark:text-purple-400 rounded-full flex items-center justify-center mb-4">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z"/></svg>
                    </div>
                    <p class="text-sm font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wide">Total Orders</p>
                    <h3 class="text-3xl font-extrabold text-purple-600 dark:text-purple-400 mt-2">{{ metrics.total_orders.toLocaleString() }}</h3>
                </div>
                
                <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-100 dark:border-gray-700 p-6 flex flex-col items-center text-center">
                    <div class="w-12 h-12 bg-green-100 dark:bg-green-900/40 text-green-600 dark:text-green-400 rounded-full flex items-center justify-center mb-4">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 7h6m0 10v-3m-3 3h.01M9 17h.01M9 14h.01M12 14h.01M15 11h.01M12 11h.01M9 11h.01M7 21h10a2 2 0 002-2V5a2 2 0 00-2-2H7a2 2 0 00-2 2v14a2 2 0 002 2z"/></svg>
                    </div>
                    <p class="text-sm font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wide">Average Order Value</p>
                    <h3 class="text-3xl font-extrabold text-green-600 dark:text-green-400 mt-2">{{ formatCurrency(metrics.average_value) }}</h3>
                </div>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
                
                <!-- Daily Revenue List -->
                <div class="lg:col-span-2 bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 overflow-hidden">
                    <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-900/50">
                        <h2 class="font-bold text-lg">Daily Breakdown</h2>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="w-full text-left font-medium">
                            <thead class="bg-gray-50 dark:bg-gray-800 text-xs text-gray-500 dark:text-gray-400 uppercase tracking-wider border-b border-gray-200 dark:border-gray-700">
                                <tr>
                                    <th class="px-6 py-3">Date</th>
                                    <th class="px-6 py-3 text-center">Orders</th>
                                    <th class="px-6 py-3 text-right">Revenue</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100 dark:divide-gray-700 text-sm">
                                <tr v-for="day in paginatedChartData" :key="day.date" class="hover:bg-gray-50 dark:hover:bg-gray-700/50 transition">
                                    <td class="px-6 py-4 whitespace-nowrap text-gray-700 dark:text-gray-300">{{ new Date(day.date).toLocaleDateString(undefined, { weekday: 'short', month: 'long', day: 'numeric', year: 'numeric'}) }}</td>
                                    <td class="px-6 py-4 whitespace-nowrap text-center">
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full bg-blue-100 dark:bg-blue-900/30 text-blue-800 dark:text-blue-300 text-xs">{{ day.orders }}</span>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-right font-bold text-gray-900 dark:text-white">{{ formatCurrency(day.revenue) }}</td>
                                </tr>
                                <tr v-if="!chart_data.length">
                                    <td colspan="3" class="px-6 py-10 text-center text-gray-400">No revenue data for this period.</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    
                    <!-- Pagination Controls -->
                    <div v-if="totalPages > 1" class="px-6 py-4 border-t border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-900/50 flex flex-col sm:flex-row items-center justify-between gap-4">
                        <span class="text-sm text-gray-500 dark:text-gray-400">
                            Showing <span class="font-medium text-gray-900 dark:text-white">{{ (currentPage - 1) * itemsPerPage + 1 }}</span> to <span class="font-medium text-gray-900 dark:text-white">{{ Math.min(currentPage * itemsPerPage, chart_data.length) }}</span> of <span class="font-medium text-gray-900 dark:text-white">{{ chart_data.length }}</span> days
                        </span>
                        <div class="flex items-center gap-2">
                            <button @click="prevPage" :disabled="currentPage === 1"
                                class="px-3 py-1.5 text-sm font-medium bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-600 rounded-md hover:bg-gray-50 dark:hover:bg-gray-700 disabled:opacity-50 disabled:cursor-not-allowed transition">
                                Previous
                            </button>
                            <span class="text-sm px-2 text-gray-600 dark:text-gray-300 font-medium">Page {{ currentPage }} of {{ totalPages }}</span>
                            <button @click="nextPage" :disabled="currentPage === totalPages"
                                class="px-3 py-1.5 text-sm font-medium bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-600 rounded-md hover:bg-gray-50 dark:hover:bg-gray-700 disabled:opacity-50 disabled:cursor-not-allowed transition">
                                Next
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Payment Method Stats -->
                <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 overflow-hidden">
                    <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-900/50">
                        <h2 class="font-bold text-lg">Payment Methods</h2>
                    </div>
                    <div class="p-6 space-y-4">
                        <div v-if="!method_stats.length" class="text-center text-gray-400 py-4">
                            No payment data available.
                        </div>
                        <template v-else>
                            <div v-for="method in method_stats" :key="method.payment_method" class="flex items-center justify-between p-4 bg-gray-50 dark:bg-gray-900/50 rounded-lg border border-gray-100 dark:border-gray-700">
                                <div class="flex items-center gap-3">
                                    <!-- Dynamic icons based on method -->
                                    <div class="w-10 h-10 rounded-full flex items-center justify-center bg-white dark:bg-gray-800 shadow-sm border dark:border-gray-600">
                                        <span v-if="method.payment_method === 'paystack'" class="font-bold text-blue-600 text-xs tracking-tighter">PAY</span>
                                        <span v-else-if="method.payment_method === 'flutterwave'" class="font-bold text-yellow-500 text-xs tracking-tighter">FLW</span>
                                        <span v-else-if="method.payment_method === 'cod'" class="font-bold text-green-600 text-xs tracking-tighter">COD</span>
                                        <span v-else class="font-bold text-gray-500 text-xs tracking-tighter">{{ method.payment_method }}</span>
                                    </div>
                                    <div>
                                        <p class="font-bold capitalize text-gray-900 dark:text-white">{{ method.payment_method }}</p>
                                        <p class="text-xs text-gray-500">{{ method.orders }} orders</p>
                                    </div>
                                </div>
                                <div class="text-right font-extrabold text-gray-900 dark:text-white">
                                    {{ formatCurrency(method.revenue) }}
                                </div>
                            </div>
                        </template>
                    </div>
                </div>

            </div>
        </div>
    </AdminLayout>
</template>
