<script setup>
import { Head, router } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { ref, watch, computed } from 'vue';

const props = defineProps({
    metrics: Object,
    source_breakdown: {
        type: Object,
        default: () => ({
            pos: { units_sold: 0, revenue: 0, cost: 0, profit: 0, margin: 0 },
            ecommerce: { units_sold: 0, revenue: 0, cost: 0, profit: 0, margin: 0 },
        }),
    },
    chart_data: {
        type: Array,
        default: () => [],
    },
    product_profits: {
        type: Array,
        default: () => [],
    },
    method_stats: {
        type: Array,
        default: () => [],
    },
    filters: {
        type: Object,
        default: () => ({}),
    },
});

const startDate       = ref(props.filters.start_date || '');
const endDate         = ref(props.filters.end_date || '');
const inventorySource = ref(props.filters.inventory_source || '');

// Apply filters when dates or inventory source change
const applyFilters = () => {
    router.get(route('admin.revenue.index'), {
        start_date:       startDate.value || undefined,
        end_date:         endDate.value || undefined,
        inventory_source: inventorySource.value || undefined,
    }, { preserveState: true, replace: true });
};

const selectSourceFilter = (source) => {
    inventorySource.value = inventorySource.value === source ? '' : source;
    applyFilters();
};

// Pagination Logic for Daily Breakdown
const currentPage = ref(1);
const itemsPerPage = 30;

watch([startDate, endDate, inventorySource], () => {
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
    return '₦' + parseFloat(amount || 0).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
};
</script>

<template>
    <Head title="Revenue & Profit Report" />
    <AdminLayout>
        <div class="max-w-7xl mx-auto px-4 py-8 text-gray-900 dark:text-gray-100 space-y-8">
            <!-- Header, Source Filter & Date Filter -->
            <div class="flex flex-col lg:flex-row lg:items-center justify-between gap-4">
                <div>
                    <h1 class="text-2xl font-bold">Revenue & Profit Report</h1>
                    <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">
                        Track revenue, buying cost, and net profit separated by <strong>POS Branch Items</strong> and <strong>Ecommerce-Only Items</strong>.
                    </p>
                </div>

                <div class="flex flex-wrap items-center gap-3 bg-white dark:bg-gray-800 p-2.5 rounded-xl border dark:border-gray-700 shadow-xs">
                    <div class="flex items-center gap-2">
                        <label class="text-xs font-bold uppercase text-gray-500 dark:text-gray-400">Source:</label>
                        <select
                            v-model="inventorySource"
                            @change="applyFilters"
                            class="rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white px-3 py-1.5 text-sm focus:ring-primary focus:border-primary"
                        >
                            <option value="">All Sources (POS + Ecommerce)</option>
                            <option value="pos">POS Branch Items Only</option>
                            <option value="ecommerce">Ecommerce-Only Items</option>
                        </select>
                    </div>
                    <div class="flex items-center gap-2">
                        <label class="text-xs font-bold uppercase text-gray-500 dark:text-gray-400">From:</label>
                        <input
                            type="date"
                            v-model="startDate"
                            @change="applyFilters"
                            class="rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white px-2.5 py-1.5 text-sm focus:ring-primary focus:border-primary"
                        />
                    </div>
                    <div class="flex items-center gap-2">
                        <label class="text-xs font-bold uppercase text-gray-500 dark:text-gray-400">To:</label>
                        <input
                            type="date"
                            v-model="endDate"
                            @change="applyFilters"
                            class="rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white px-2.5 py-1.5 text-sm focus:ring-primary focus:border-primary"
                        />
                    </div>
                </div>
            </div>

            <!-- ── POS vs Ecommerce Profit Breakdown Cards ─────────────────── -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <!-- POS Branch Items Profit Card -->
                <div
                    @click="selectSourceFilter('pos')"
                    class="bg-white dark:bg-gray-800 rounded-2xl p-6 border-2 transition cursor-pointer shadow-xs"
                    :class="inventorySource === 'pos' ? 'border-primary ring-2 ring-primary/20' : 'border-gray-200 dark:border-gray-700 hover:border-primary/50'"
                >
                    <div class="flex items-center justify-between mb-4">
                        <div class="flex items-center gap-3">
                            <div class="w-10 h-10 rounded-xl bg-primary-soft text-primary font-bold text-sm flex items-center justify-center">
                                POS
                            </div>
                            <div>
                                <h3 class="font-bold text-base text-gray-900 dark:text-white">POS Branch Items Profit</h3>
                                <p class="text-xs text-gray-500 dark:text-gray-400">
                                    {{ (source_breakdown?.pos?.units_sold || 0).toLocaleString() }} unit(s) sold from connected POS catalog
                                </p>
                            </div>
                        </div>
                        <span class="text-xs font-bold px-2.5 py-1 rounded-full theme-primary-badge">
                            {{ source_breakdown?.pos?.margin || 0 }}% Margin
                        </span>
                    </div>

                    <div class="grid grid-cols-3 gap-3 pt-3 border-t border-gray-100 dark:border-gray-700 text-sm">
                        <div>
                            <span class="block text-xs text-gray-500 dark:text-gray-400">POS Sales Revenue</span>
                            <span class="font-bold text-gray-900 dark:text-white mt-0.5 block">
                                {{ formatCurrency(source_breakdown?.pos?.revenue) }}
                            </span>
                        </div>
                        <div>
                            <span class="block text-xs text-gray-500 dark:text-gray-400">POS Buying Cost</span>
                            <span class="font-bold text-amber-600 dark:text-amber-400 mt-0.5 block">
                                {{ formatCurrency(source_breakdown?.pos?.cost) }}
                            </span>
                        </div>
                        <div>
                            <span class="block text-xs text-gray-500 dark:text-gray-400">POS Net Profit</span>
                            <span class="font-extrabold text-emerald-600 dark:text-emerald-400 text-base mt-0.5 block">
                                {{ formatCurrency(source_breakdown?.pos?.profit) }}
                            </span>
                        </div>
                    </div>
                </div>

                <!-- Ecommerce-Only Items Profit Card -->
                <div
                    @click="selectSourceFilter('ecommerce')"
                    class="bg-white dark:bg-gray-800 rounded-2xl p-6 border-2 transition cursor-pointer shadow-xs"
                    :class="inventorySource === 'ecommerce' ? 'border-secondary ring-2 ring-secondary/20' : 'border-gray-200 dark:border-gray-700 hover:border-secondary/50'"
                >
                    <div class="flex items-center justify-between mb-4">
                        <div class="flex items-center gap-3">
                            <div class="w-10 h-10 rounded-xl bg-secondary-soft text-secondary font-bold text-sm flex items-center justify-center">
                                WEB
                            </div>
                            <div>
                                <h3 class="font-bold text-base text-gray-900 dark:text-white">Ecommerce-Only Items Profit</h3>
                                <p class="text-xs text-gray-500 dark:text-gray-400">
                                    {{ (source_breakdown?.ecommerce?.units_sold || 0).toLocaleString() }} unit(s) sold from local store inventory
                                </p>
                            </div>
                        </div>
                        <span class="text-xs font-bold px-2.5 py-1 rounded-full theme-secondary-badge">
                            {{ source_breakdown?.ecommerce?.margin || 0 }}% Margin
                        </span>
                    </div>

                    <div class="grid grid-cols-3 gap-3 pt-3 border-t border-gray-100 dark:border-gray-700 text-sm">
                        <div>
                            <span class="block text-xs text-gray-500 dark:text-gray-400">Ecommerce Revenue</span>
                            <span class="font-bold text-gray-900 dark:text-white mt-0.5 block">
                                {{ formatCurrency(source_breakdown?.ecommerce?.revenue) }}
                            </span>
                        </div>
                        <div>
                            <span class="block text-xs text-gray-500 dark:text-gray-400">Ecommerce Cost</span>
                            <span class="font-bold text-amber-600 dark:text-amber-400 mt-0.5 block">
                                {{ formatCurrency(source_breakdown?.ecommerce?.cost) }}
                            </span>
                        </div>
                        <div>
                            <span class="block text-xs text-gray-500 dark:text-gray-400">Ecommerce Net Profit</span>
                            <span class="font-extrabold text-emerald-600 dark:text-emerald-400 text-base mt-0.5 block">
                                {{ formatCurrency(source_breakdown?.ecommerce?.profit) }}
                            </span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- ── Top Metric Cards ────────────────────────────────────────── -->
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-4">
                <div class="bg-white dark:bg-gray-800 rounded-xl shadow-xs border border-gray-100 dark:border-gray-700 p-5 flex flex-col items-center text-center">
                    <div class="w-11 h-11 bg-primary-soft text-primary rounded-full flex items-center justify-center mb-3">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    </div>
                    <p class="text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wide">Total Revenue</p>
                    <h3 class="text-2xl font-extrabold text-primary mt-1.5">{{ formatCurrency(metrics.total_revenue) }}</h3>
                </div>

                <div class="bg-white dark:bg-gray-800 rounded-xl shadow-xs border border-gray-100 dark:border-gray-700 p-5 flex flex-col items-center text-center">
                    <div class="w-11 h-11 bg-amber-100 dark:bg-amber-900/40 text-amber-600 dark:text-amber-400 rounded-full flex items-center justify-center mb-3">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/></svg>
                    </div>
                    <p class="text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wide">Cost of Goods</p>
                    <h3 class="text-2xl font-extrabold text-amber-600 dark:text-amber-400 mt-1.5">{{ formatCurrency(metrics.total_cost || 0) }}</h3>
                </div>

                <div class="bg-white dark:bg-gray-800 rounded-xl shadow-xs border border-gray-100 dark:border-gray-700 p-5 flex flex-col items-center text-center">
                    <div class="w-11 h-11 bg-emerald-100 dark:bg-emerald-900/40 text-emerald-600 dark:text-emerald-400 rounded-full flex items-center justify-center mb-3">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"/></svg>
                    </div>
                    <p class="text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wide">Net Profit ({{ metrics.profit_margin || 0 }}%)</p>
                    <h3 class="text-2xl font-extrabold text-emerald-600 dark:text-emerald-400 mt-1.5">{{ formatCurrency(metrics.total_profit || 0) }}</h3>
                </div>

                <div class="bg-white dark:bg-gray-800 rounded-xl shadow-xs border border-gray-100 dark:border-gray-700 p-5 flex flex-col items-center text-center">
                    <div class="w-11 h-11 bg-secondary-soft text-secondary rounded-full flex items-center justify-center mb-3">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z"/></svg>
                    </div>
                    <p class="text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wide">Total Orders</p>
                    <h3 class="text-2xl font-extrabold text-secondary mt-1.5">{{ (metrics.total_orders || 0).toLocaleString() }}</h3>
                </div>

                <div class="bg-white dark:bg-gray-800 rounded-xl shadow-xs border border-gray-100 dark:border-gray-700 p-5 flex flex-col items-center text-center">
                    <div class="w-11 h-11 bg-green-100 dark:bg-green-900/40 text-green-600 dark:text-green-400 rounded-full flex items-center justify-center mb-3">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 7h6m0 10v-3m-3 3h.01M9 17h.01M9 14h.01M12 14h.01M15 11h.01M12 11h.01M9 11h.01M7 21h10a2 2 0 002-2V5a2 2 0 00-2-2H7a2 2 0 00-2 2v14a2 2 0 002 2z"/></svg>
                    </div>
                    <p class="text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wide">Average Order Value</p>
                    <h3 class="text-2xl font-extrabold text-green-600 dark:text-green-400 mt-1.5">{{ formatCurrency(metrics.average_value) }}</h3>
                </div>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
                <!-- Daily Revenue & Profit List -->
                <div class="lg:col-span-2 bg-white dark:bg-gray-800 rounded-xl shadow-xs border border-gray-200 dark:border-gray-700 overflow-hidden">
                    <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-900/50 flex items-center justify-between">
                        <h2 class="font-bold text-lg">Daily Revenue & Profit Breakdown</h2>
                        <span v-if="inventorySource" class="text-xs font-bold px-2.5 py-1 rounded-full theme-primary-badge uppercase">
                            Filtered: {{ inventorySource === 'pos' ? 'POS Items Only' : 'Ecommerce Items Only' }}
                        </span>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="w-full text-left font-medium">
                            <thead class="bg-gray-50 dark:bg-gray-800 text-xs text-gray-500 dark:text-gray-400 uppercase tracking-wider border-b border-gray-200 dark:border-gray-700">
                                <tr>
                                    <th class="px-5 py-3">Date</th>
                                    <th class="px-4 py-3 text-center">Orders</th>
                                    <th class="px-5 py-3 text-right">Revenue</th>
                                    <th class="px-5 py-3 text-right">Buying Cost</th>
                                    <th class="px-5 py-3 text-right">POS Profit</th>
                                    <th class="px-5 py-3 text-right">Ecom Profit</th>
                                    <th class="px-5 py-3 text-right">Net Profit</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100 dark:divide-gray-700 text-sm">
                                <tr v-for="day in paginatedChartData" :key="day.date" class="hover:bg-gray-50 dark:hover:bg-gray-700/50 transition">
                                    <td class="px-5 py-3.5 whitespace-nowrap text-gray-700 dark:text-gray-300">
                                        {{ new Date(day.date).toLocaleDateString(undefined, { weekday: 'short', month: 'short', day: 'numeric', year: 'numeric' }) }}
                                    </td>
                                    <td class="px-4 py-3.5 whitespace-nowrap text-center">
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full theme-primary-badge text-xs">{{ day.orders }}</span>
                                    </td>
                                    <td class="px-5 py-3.5 whitespace-nowrap text-right font-bold text-gray-900 dark:text-white">{{ formatCurrency(day.revenue) }}</td>
                                    <td class="px-5 py-3.5 whitespace-nowrap text-right text-amber-600 dark:text-amber-400">{{ formatCurrency(day.cost || 0) }}</td>
                                    <td class="px-5 py-3.5 whitespace-nowrap text-right text-primary font-semibold">{{ formatCurrency(day.pos_profit || 0) }}</td>
                                    <td class="px-5 py-3.5 whitespace-nowrap text-right text-secondary font-semibold">{{ formatCurrency(day.ecommerce_profit || 0) }}</td>
                                    <td class="px-5 py-3.5 whitespace-nowrap text-right font-extrabold text-emerald-600 dark:text-emerald-400">{{ formatCurrency(day.profit ?? day.revenue) }}</td>
                                </tr>
                                <tr v-if="!chart_data.length">
                                    <td colspan="7" class="px-6 py-10 text-center text-gray-400">No sales data for this period.</td>
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
                <div class="bg-white dark:bg-gray-800 rounded-xl shadow-xs border border-gray-200 dark:border-gray-700 overflow-hidden">
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
                                    <div class="w-10 h-10 rounded-full flex items-center justify-center bg-white dark:bg-gray-800 shadow-xs border dark:border-gray-600">
                                        <span v-if="method.payment_method === 'paystack'" class="font-bold text-blue-600 text-xs tracking-tighter">PAY</span>
                                        <span v-else-if="method.payment_method === 'flutterwave'" class="font-bold text-yellow-500 text-xs tracking-tighter">FLW</span>
                                        <span v-else-if="['cod', 'pod', 'Payment on Delivery (POD)'].includes(method.payment_method)" class="font-bold text-green-600 text-xs tracking-tighter">POD</span>
                                        <span v-else class="font-bold text-gray-500 text-xs tracking-tighter">{{ method.payment_method }}</span>
                                    </div>
                                    <div>
                                        <p class="font-bold capitalize text-gray-900 dark:text-white">
                                            {{ ['cod', 'pod'].includes(method.payment_method) ? 'Payment on Delivery (POD)' : (method.payment_method === 'bank_transfer' ? 'Direct Bank Transfer' : method.payment_method) }}
                                        </p>
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

            <!-- ── Product-Level Profit Breakdown (POS vs Ecommerce) ───────── -->
            <div class="bg-white dark:bg-gray-800 rounded-xl shadow-xs border border-gray-200 dark:border-gray-700 overflow-hidden">
                <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-900/50 flex items-center justify-between">
                    <div>
                        <h2 class="font-bold text-lg">Product Profit Breakdown (POS vs. Ecommerce Items)</h2>
                        <p class="text-xs text-gray-500 dark:text-gray-400">
                            Shows exact revenue, buying cost, and profit earned per product during the selected period.
                        </p>
                    </div>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-sm">
                        <thead class="bg-gray-50 dark:bg-gray-800 text-xs text-gray-500 dark:text-gray-400 uppercase tracking-wider border-b border-gray-200 dark:border-gray-700">
                            <tr>
                                <th class="px-6 py-3">Product</th>
                                <th class="px-6 py-3">Inventory Source</th>
                                <th class="px-6 py-3 text-center">Qty Sold</th>
                                <th class="px-6 py-3 text-right">Sales Revenue</th>
                                <th class="px-6 py-3 text-right">Buying Cost</th>
                                <th class="px-6 py-3 text-right">Net Profit</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                            <tr v-for="(item, idx) in product_profits" :key="idx" class="hover:bg-gray-50 dark:hover:bg-gray-700/50 transition">
                                <td class="px-6 py-3.5 font-semibold text-gray-900 dark:text-white">
                                    {{ item.product_name }}
                                </td>
                                <td class="px-6 py-3.5">
                                    <span
                                        v-if="item.inventory_source === 'pos'"
                                        class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-bold theme-primary-badge"
                                    >
                                        POS Branch {{ item.pos_item_id ? `#${item.pos_item_id}` : '' }}
                                    </span>
                                    <span
                                        v-else
                                        class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-bold theme-secondary-badge"
                                    >
                                        Ecommerce Only
                                    </span>
                                </td>
                                <td class="px-6 py-3.5 text-center font-bold">
                                    {{ Number(item.qty_sold || 0).toLocaleString() }}
                                </td>
                                <td class="px-6 py-3.5 text-right font-bold text-gray-900 dark:text-white">
                                    {{ formatCurrency(item.revenue) }}
                                </td>
                                <td class="px-6 py-3.5 text-right text-amber-600 dark:text-amber-400 font-medium">
                                    {{ formatCurrency(item.cost) }}
                                </td>
                                <td class="px-6 py-3.5 text-right font-extrabold text-emerald-600 dark:text-emerald-400">
                                    {{ formatCurrency(item.profit) }}
                                </td>
                            </tr>
                            <tr v-if="!product_profits?.length">
                                <td colspan="6" class="px-6 py-10 text-center text-gray-400">
                                    No product sales recorded in this date range.
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </AdminLayout>
</template>
