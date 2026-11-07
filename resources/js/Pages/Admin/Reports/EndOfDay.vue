<script setup>
import { Head, Link, router } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { ref, computed, watch } from 'vue';

const props = defineProps({
    date: String,
    formatted_date: String,
    prev_date: String,
    next_date: String,
    is_today: Boolean,
    metrics: Object,
    source_breakdown: Object,
    payment_methods: {
        type: Array,
        default: () => [],
    },
    status_stats: {
        type: Array,
        default: () => [],
    },
    hourly_stats: {
        type: Array,
        default: () => [],
    },
    products_sold: {
        type: Array,
        default: () => [],
    },
    orders: {
        type: Array,
        default: () => [],
    },
    store_info: {
        type: Object,
        default: () => ({}),
    },
    generated_at: String,
});

const selectedDate = ref(props.date);
const orderSearch = ref('');
const activeTab = ref('products'); // 'products' or 'orders'

const goToDate = (dateVal) => {
    if (!dateVal) return;
    router.get(route('admin.reports.end-of-day'), { date: dateVal }, {
        preserveState: true,
        preserveScroll: true,
    });
};

const isPrinting = ref(false);
const printReport = () => {
    isPrinting.value = true;
    setTimeout(() => {
        window.print();
        isPrinting.value = false;
    }, 150);
};

const productsPage = ref(1);
const ordersPage = ref(1);
const perPage = 25;

watch(orderSearch, () => {
    ordersPage.value = 1;
});

watch(() => props.date, (newDate) => {
    if (newDate) {
        selectedDate.value = newDate;
    }
});

const totalProductsPages = computed(() => Math.ceil((props.products_sold?.length || 0) / perPage));
const displayedProducts = computed(() => {
    if (isPrinting.value || totalProductsPages.value <= 1) return props.products_sold || [];
    const start = (productsPage.value - 1) * perPage;
    return (props.products_sold || []).slice(start, start + perPage);
});

const totalOrdersPages = computed(() => Math.ceil(filteredOrders.value.length / perPage));
const displayedOrders = computed(() => {
    if (isPrinting.value || totalOrdersPages.value <= 1) return filteredOrders.value;
    const start = (ordersPage.value - 1) * perPage;
    return filteredOrders.value.slice(start, start + perPage);
});

const formatCurrency = (val) => {
    return '₦' + parseFloat(val || 0).toLocaleString(undefined, {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    });
};

const formatMethodName = (method) => {
    if (!method) return 'Not Specified';
    if (method === 'cod' || method === 'pod') return 'Payment on Delivery (POD)';
    if (method === 'bank_transfer') return 'Direct Bank Transfer';
    return method
        .replace(/_/g, ' ')
        .replace(/\b\w/g, l => l.toUpperCase());
};

const maxHourlyRevenue = computed(() => {
    if (!props.hourly_stats || props.hourly_stats.length === 0) return 1;
    const max = Math.max(...props.hourly_stats.map(h => h.revenue));
    return max > 0 ? max : 1;
});

const filteredOrders = computed(() => {
    if (!orderSearch.value.trim()) return props.orders;
    const term = orderSearch.value.toLowerCase();
    return props.orders.filter(order =>
        (order.tracking_number && order.tracking_number.toLowerCase().includes(term)) ||
        (order.customer_name && order.customer_name.toLowerCase().includes(term)) ||
        (order.payment_method && order.payment_method.toLowerCase().includes(term)) ||
        (order.status && order.status.toLowerCase().includes(term))
    );
});
</script>

<template>
    <Head :title="`End of Day Report — ${formatted_date}`" />

    <AdminLayout>
        <div class="max-w-7xl mx-auto px-4 py-8 text-gray-900 dark:text-gray-100 space-y-6">

            <!-- ── Printable Header (Appears only on printed page) ── -->
            <div class="print-only border-b-2 border-gray-900 pb-4 mb-6">
                <div class="flex items-center justify-between">
                    <div>
                        <h1 class="text-2xl font-black uppercase tracking-wide">{{ store_info.company_name || 'Skynet POS' }}</h1>
                        <p class="text-xs text-gray-600">{{ store_info.company_tagline }}</p>
                        <p v-if="store_info.company_address" class="text-xs text-gray-600">{{ store_info.company_address }}</p>
                    </div>
                    <div class="text-right">
                        <div class="inline-block bg-black text-white px-3 py-1 font-bold text-xs uppercase tracking-wider rounded">
                            Z-REPORT / EOD CLOSING
                        </div>
                        <p class="text-sm font-black mt-1">{{ formatted_date }}</p>
                        <p class="text-[10px] text-gray-500">Printed: {{ generated_at }}</p>
                    </div>
                </div>
            </div>

            <!-- ── Top Bar: Title, Date Navigator & Actions (Screen only) ── -->
            <div class="no-print flex flex-col md:flex-row md:items-center justify-between gap-4 bg-white dark:bg-gray-800 p-5 rounded-2xl border dark:border-gray-700 shadow-xs">
                <div>
                    <div class="flex items-center gap-2">
                        <span class="w-8 h-8 rounded-lg bg-primary/10 text-primary flex items-center justify-center font-bold text-lg">
                            📊
                        </span>
                        <h1 class="text-2xl font-black tracking-tight">End of Day (EOD) Report</h1>
                    </div>
                    <p class="text-xs sm:text-sm text-gray-500 dark:text-gray-400 mt-1">
                        Daily sales closing, payment method reconciliation, and channel performance for
                        <strong class="text-gray-800 dark:text-gray-200">{{ formatted_date }}</strong>
                    </p>
                </div>

                <!-- Date Controls -->
                <div class="flex flex-wrap items-center gap-2">
                    <button
                        type="button"
                        @click="goToDate(prev_date)"
                        class="px-2.5 py-1.5 rounded-lg border border-gray-300 dark:border-gray-600 hover:bg-gray-50 dark:hover:bg-gray-700 text-xs font-semibold flex items-center gap-1 transition"
                        title="Previous Day">
                        &larr; Prev
                    </button>

                    <input
                        type="date"
                        v-model="selectedDate"
                        @change="goToDate(selectedDate)"
                        class="px-3 py-1.5 rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-xs sm:text-sm font-semibold focus:ring-primary focus:border-primary"
                    />

                    <button
                        type="button"
                        @click="goToDate(next_date)"
                        class="px-2.5 py-1.5 rounded-lg border border-gray-300 dark:border-gray-600 hover:bg-gray-50 dark:hover:bg-gray-700 text-xs font-semibold flex items-center gap-1 transition"
                        title="Next Day">
                        Next &rarr;
                    </button>

                    <button
                        type="button"
                        @click="goToDate(new Date().toISOString().split('T')[0])"
                        :class="is_today ? 'bg-primary text-white shadow-xs' : 'border border-gray-300 dark:border-gray-600 hover:bg-gray-50 dark:hover:bg-gray-700 text-gray-700 dark:text-gray-200'"
                        class="px-3 py-1.5 rounded-lg text-xs font-bold transition">
                        Today
                    </button>

                    <!-- Print Button -->
                    <button
                        type="button"
                        @click="printReport"
                        class="px-3.5 py-1.5 rounded-lg bg-gray-900 text-white dark:bg-gray-100 dark:text-gray-900 hover:opacity-90 font-bold text-xs flex items-center gap-1.5 shadow-xs transition">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z" />
                        </svg>
                        Print EOD
                    </button>
                </div>
            </div>

            <!-- ── Primary KPI Cards ── -->
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                <!-- Gross Sales -->
                <div class="bg-white dark:bg-gray-800 p-5 rounded-2xl border dark:border-gray-700 shadow-xs relative overflow-hidden">
                    <div class="flex items-center justify-between text-gray-500 dark:text-gray-400 mb-2">
                        <span class="text-xs font-bold uppercase tracking-wider">Gross Sales</span>
                        <span class="text-xs px-2 py-0.5 rounded-full bg-emerald-100 dark:bg-emerald-900/30 text-emerald-700 dark:text-emerald-300 font-bold">
                            {{ metrics.paid_orders_count }} Paid Orders
                        </span>
                    </div>
                    <div class="text-2xl font-black text-gray-900 dark:text-white">
                        {{ formatCurrency(metrics.total_revenue) }}
                    </div>
                    <div class="mt-2 text-xs text-gray-500 dark:text-gray-400 flex items-center justify-between">
                        <span>Total Orders: <strong>{{ metrics.total_orders_count }}</strong></span>
                        <span v-if="metrics.pending_orders_count > 0" class="text-amber-600 dark:text-amber-400 font-semibold">
                            {{ metrics.pending_orders_count }} Pending
                        </span>
                    </div>
                </div>

                <!-- Net Profit -->
                <div class="bg-white dark:bg-gray-800 p-5 rounded-2xl border dark:border-gray-700 shadow-xs">
                    <div class="flex items-center justify-between text-gray-500 dark:text-gray-400 mb-2">
                        <span class="text-xs font-bold uppercase tracking-wider">Net Profit</span>
                        <span class="text-xs px-2 py-0.5 rounded-full bg-primary/10 text-primary font-bold">
                            {{ metrics.profit_margin }}% Margin
                        </span>
                    </div>
                    <div class="text-2xl font-black text-emerald-600 dark:text-emerald-400">
                        {{ formatCurrency(metrics.total_profit) }}
                    </div>
                    <div class="mt-2 text-xs text-gray-500 dark:text-gray-400">
                        Cost of Goods: <strong>{{ formatCurrency(metrics.total_cost) }}</strong>
                    </div>
                </div>

                <!-- Units Sold & AOV -->
                <div class="bg-white dark:bg-gray-800 p-5 rounded-2xl border dark:border-gray-700 shadow-xs">
                    <div class="flex items-center justify-between text-gray-500 dark:text-gray-400 mb-2">
                        <span class="text-xs font-bold uppercase tracking-wider">Units Sold</span>
                        <span class="text-xs font-bold text-gray-500 dark:text-gray-400">Physical Items</span>
                    </div>
                    <div class="text-2xl font-black text-gray-900 dark:text-white">
                        {{ (metrics.items_sold || 0).toLocaleString() }}
                    </div>
                    <div class="mt-2 text-xs text-gray-500 dark:text-gray-400">
                        Avg Order Value: <strong>{{ formatCurrency(metrics.average_order_value) }}</strong>
                    </div>
                </div>

                <!-- Discounts & Deductions -->
                <div class="bg-white dark:bg-gray-800 p-5 rounded-2xl border dark:border-gray-700 shadow-xs">
                    <div class="flex items-center justify-between text-gray-500 dark:text-gray-400 mb-2">
                        <span class="text-xs font-bold uppercase tracking-wider">Coupons / Discounts</span>
                        <span v-if="metrics.cancelled_orders_count > 0" class="text-xs text-red-500 font-semibold">
                            {{ metrics.cancelled_orders_count }} Cancelled
                        </span>
                    </div>
                    <div class="text-2xl font-black text-amber-600 dark:text-amber-400">
                        {{ formatCurrency(metrics.total_discounts) }}
                    </div>
                    <div class="mt-2 text-xs text-gray-500 dark:text-gray-400">
                        Total discounts absorbed today
                    </div>
                </div>
            </div>

            <!-- ── POS vs Ecommerce Channel Performance ── -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <!-- POS Branch Sales -->
                <div class="bg-white dark:bg-gray-800 p-5 rounded-2xl border dark:border-gray-700 shadow-xs">
                    <div class="flex items-center justify-between border-b dark:border-gray-700 pb-3 mb-3">
                        <div class="flex items-center gap-2">
                            <span class="w-6 h-6 rounded-md bg-indigo-50 dark:bg-indigo-900/40 text-indigo-600 dark:text-indigo-400 flex items-center justify-center text-xs font-bold">
                                🏬
                            </span>
                            <h2 class="font-bold text-base">POS Branch Sourced</h2>
                        </div>
                        <span class="text-xs px-2.5 py-0.5 rounded-full bg-indigo-100 dark:bg-indigo-900/30 text-indigo-700 dark:text-indigo-300 font-bold">
                            {{ source_breakdown.pos?.units_sold || 0 }} Units
                        </span>
                    </div>
                    <div class="grid grid-cols-3 gap-2 text-center">
                        <div class="p-2 rounded-lg bg-gray-50 dark:bg-gray-700/50">
                            <div class="text-[11px] text-gray-500 dark:text-gray-400">Revenue</div>
                            <div class="text-sm font-black text-gray-900 dark:text-white mt-0.5">{{ formatCurrency(source_breakdown.pos?.revenue) }}</div>
                        </div>
                        <div class="p-2 rounded-lg bg-gray-50 dark:bg-gray-700/50">
                            <div class="text-[11px] text-gray-500 dark:text-gray-400">Cost</div>
                            <div class="text-sm font-semibold text-gray-600 dark:text-gray-300 mt-0.5">{{ formatCurrency(source_breakdown.pos?.cost) }}</div>
                        </div>
                        <div class="p-2 rounded-lg bg-emerald-50 dark:bg-emerald-900/20">
                            <div class="text-[11px] text-emerald-600 dark:text-emerald-400">Profit ({{ source_breakdown.pos?.margin }}%)</div>
                            <div class="text-sm font-black text-emerald-600 dark:text-emerald-400 mt-0.5">{{ formatCurrency(source_breakdown.pos?.profit) }}</div>
                        </div>
                    </div>
                </div>

                <!-- Ecommerce Warehouse Sales -->
                <div class="bg-white dark:bg-gray-800 p-5 rounded-2xl border dark:border-gray-700 shadow-xs">
                    <div class="flex items-center justify-between border-b dark:border-gray-700 pb-3 mb-3">
                        <div class="flex items-center gap-2">
                            <span class="w-6 h-6 rounded-md bg-purple-50 dark:bg-purple-900/40 text-purple-600 dark:text-purple-400 flex items-center justify-center text-xs font-bold">
                                🌐
                            </span>
                            <h2 class="font-bold text-base">Ecommerce Warehouse Sourced</h2>
                        </div>
                        <span class="text-xs px-2.5 py-0.5 rounded-full bg-purple-100 dark:bg-purple-900/30 text-purple-700 dark:text-purple-300 font-bold">
                            {{ source_breakdown.ecommerce?.units_sold || 0 }} Units
                        </span>
                    </div>
                    <div class="grid grid-cols-3 gap-2 text-center">
                        <div class="p-2 rounded-lg bg-gray-50 dark:bg-gray-700/50">
                            <div class="text-[11px] text-gray-500 dark:text-gray-400">Revenue</div>
                            <div class="text-sm font-black text-gray-900 dark:text-white mt-0.5">{{ formatCurrency(source_breakdown.ecommerce?.revenue) }}</div>
                        </div>
                        <div class="p-2 rounded-lg bg-gray-50 dark:bg-gray-700/50">
                            <div class="text-[11px] text-gray-500 dark:text-gray-400">Cost</div>
                            <div class="text-sm font-semibold text-gray-600 dark:text-gray-300 mt-0.5">{{ formatCurrency(source_breakdown.ecommerce?.cost) }}</div>
                        </div>
                        <div class="p-2 rounded-lg bg-emerald-50 dark:bg-emerald-900/20">
                            <div class="text-[11px] text-emerald-600 dark:text-emerald-400">Profit ({{ source_breakdown.ecommerce?.margin }}%)</div>
                            <div class="text-sm font-black text-emerald-600 dark:text-emerald-400 mt-0.5">{{ formatCurrency(source_breakdown.ecommerce?.profit) }}</div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- ── Payment Reconciliation & Hourly Distribution ── -->
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">

                <!-- 1. Payment Reconciliation Table -->
                <div class="bg-white dark:bg-gray-800 p-5 rounded-2xl border dark:border-gray-700 shadow-xs">
                    <div class="flex items-center justify-between mb-4">
                        <div>
                            <h2 class="font-bold text-base">Payment Method Reconciliation</h2>
                            <p class="text-xs text-gray-500 dark:text-gray-400">Verify till cash & online gateway settlement</p>
                        </div>
                        <span class="text-xs bg-gray-100 dark:bg-gray-700 text-gray-600 dark:text-gray-300 px-2 py-0.5 rounded font-mono font-bold">
                            {{ payment_methods.length }} Methods
                        </span>
                    </div>

                    <div v-if="payment_methods.length" class="overflow-x-auto">
                        <table class="w-full text-xs text-left">
                            <thead class="bg-gray-50 dark:bg-gray-700/50 text-gray-500 uppercase tracking-wider text-[10px]">
                                <tr>
                                    <th class="py-2.5 px-3 rounded-l-lg">Payment Method</th>
                                    <th class="py-2.5 px-3 text-center">Orders</th>
                                    <th class="py-2.5 px-3 text-right">Settled Amount</th>
                                    <th class="py-2.5 px-3 text-right">Pending</th>
                                    <th class="py-2.5 px-3 text-right rounded-r-lg">Share</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y dark:divide-gray-700/60 font-medium">
                                <tr v-for="pm in payment_methods" :key="pm.method" class="hover:bg-gray-50/50 dark:hover:bg-gray-700/20">
                                    <td class="py-2.5 px-3">
                                        <div class="font-bold text-gray-900 dark:text-white">
                                            {{ formatMethodName(pm.method) }}
                                        </div>
                                    </td>
                                    <td class="py-2.5 px-3 text-center">
                                        <span class="px-2 py-0.5 rounded bg-gray-100 dark:bg-gray-700 font-semibold">
                                            {{ pm.paid_orders }} / {{ pm.total_orders }}
                                        </span>
                                    </td>
                                    <td class="py-2.5 px-3 text-right font-bold text-emerald-600 dark:text-emerald-400">
                                        {{ formatCurrency(pm.paid_amount) }}
                                    </td>
                                    <td class="py-2.5 px-3 text-right text-gray-500">
                                        <span v-if="pm.pending_amount > 0" class="text-amber-600 dark:text-amber-400 font-semibold">
                                            {{ formatCurrency(pm.pending_amount) }}
                                        </span>
                                        <span v-else>—</span>
                                    </td>
                                    <td class="py-2.5 px-3 text-right">
                                        <div class="inline-flex items-center gap-1.5">
                                            <span class="font-bold text-gray-700 dark:text-gray-300">{{ pm.percentage }}%</span>
                                            <div class="w-12 bg-gray-200 dark:bg-gray-700 rounded-full h-1.5 overflow-hidden">
                                                <div class="bg-primary h-1.5 rounded-full" :style="{ width: `${Math.min(pm.percentage, 100)}%` }"></div>
                                            </div>
                                        </div>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    <div v-else class="text-center py-8 text-gray-400 text-xs">
                        No orders recorded on this date.
                    </div>
                </div>

                <!-- 2. Hourly Activity Breakdown -->
                <div class="bg-white dark:bg-gray-800 p-5 rounded-2xl border dark:border-gray-700 shadow-xs">
                    <div class="flex items-center justify-between mb-4">
                        <div>
                            <h2 class="font-bold text-base">Hourly Sales Distribution</h2>
                            <p class="text-xs text-gray-500 dark:text-gray-400">Peak transaction hours during the day</p>
                        </div>
                        <div class="flex items-center gap-2">
                            <span v-for="st in status_stats" :key="st.status"
                                class="text-[10px] px-2 py-0.5 rounded-full font-bold uppercase bg-gray-100 dark:bg-gray-700 text-gray-600 dark:text-gray-300">
                                {{ st.status }}: {{ st.count }}
                            </span>
                        </div>
                    </div>

                    <!-- Mini Hourly Bar Chart -->
                    <div class="space-y-1.5 max-h-72 overflow-y-auto pr-1">
                        <template v-for="slot in hourly_stats" :key="slot.hour">
                            <div v-if="slot.orders > 0 || slot.revenue > 0" class="flex items-center gap-3 text-xs py-1">
                                <span class="w-16 font-mono text-[11px] text-gray-500 font-semibold shrink-0">{{ slot.label }}</span>
                                <div class="flex-1 bg-gray-100 dark:bg-gray-700/60 rounded-full h-4 overflow-hidden relative">
                                    <div
                                        class="bg-gradient-to-r from-primary to-emerald-500 h-full rounded-full transition-all duration-300"
                                        :style="{ width: `${Math.max(5, (slot.revenue / maxHourlyRevenue) * 100)}%` }">
                                    </div>
                                    <span v-if="slot.revenue > 0" class="absolute inset-y-0 right-2 flex items-center text-[10px] font-bold text-gray-700 dark:text-gray-200">
                                        {{ formatCurrency(slot.revenue) }}
                                    </span>
                                </div>
                                <span class="w-16 text-right font-bold text-gray-700 dark:text-gray-300 shrink-0">
                                    {{ slot.orders }} {{ slot.orders === 1 ? 'order' : 'orders' }}
                                </span>
                            </div>
                        </template>
                        <div v-if="!hourly_stats.some(s => s.orders > 0)" class="text-center py-8 text-gray-400 text-xs">
                            No hourly activity recorded on this date.
                        </div>
                    </div>
                </div>
            </div>

            <!-- ── Products Sold vs All Orders (Tabbed View) ── -->
            <div class="bg-white dark:bg-gray-800 rounded-2xl border dark:border-gray-700 shadow-xs overflow-hidden">
                <div class="border-b dark:border-gray-700 px-5 pt-4 flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                    <div class="flex items-center gap-4">
                        <button
                            type="button"
                            @click="activeTab = 'products'"
                            :class="activeTab === 'products' ? 'border-primary text-primary font-black' : 'border-transparent text-gray-500 hover:text-gray-700 dark:hover:text-gray-300 font-semibold'"
                            class="pb-3 text-sm border-b-2 transition flex items-center gap-2">
                            <span>📦 Products Sold</span>
                            <span class="text-xs px-2 py-0.5 rounded-full bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-300 font-bold">
                                {{ products_sold.length }}
                            </span>
                        </button>
                        <button
                            type="button"
                            @click="activeTab = 'orders'"
                            :class="activeTab === 'orders' ? 'border-primary text-primary font-black' : 'border-transparent text-gray-500 hover:text-gray-700 dark:hover:text-gray-300 font-semibold'"
                            class="pb-3 text-sm border-b-2 transition flex items-center gap-2">
                            <span>🧾 Orders Register</span>
                            <span class="text-xs px-2 py-0.5 rounded-full bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-300 font-bold">
                                {{ orders.length }}
                            </span>
                        </button>
                    </div>

                    <div v-if="activeTab === 'orders'" class="no-print pb-3 sm:pb-0">
                        <input
                            type="text"
                            v-model="orderSearch"
                            placeholder="Search orders, customers..."
                            class="px-3 py-1.5 text-xs rounded-lg border border-gray-300 dark:border-gray-600 bg-gray-50 dark:bg-gray-700 text-gray-900 dark:text-white focus:ring-primary focus:border-primary w-full sm:w-64"
                        />
                    </div>
                </div>

                <!-- Tab 1: Products Sold -->
                <div v-if="activeTab === 'products'" class="p-5">
                    <div v-if="products_sold.length" class="overflow-x-auto">
                        <table class="w-full text-xs text-left">
                            <thead class="bg-gray-50 dark:bg-gray-700/50 text-gray-500 uppercase tracking-wider text-[10px]">
                                <tr>
                                    <th class="py-2.5 px-3 rounded-l-lg">Product Name</th>
                                    <th class="py-2.5 px-3">Channel</th>
                                    <th class="py-2.5 px-3">Unit / Variant</th>
                                    <th class="py-2.5 px-3 text-center">Qty Sold</th>
                                    <th class="py-2.5 px-3 text-right">Total Revenue</th>
                                    <th class="py-2.5 px-3 text-right">Total Cost</th>
                                    <th class="py-2.5 px-3 text-right rounded-r-lg">Gross Profit</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y dark:divide-gray-700/60 font-medium">
                                <tr v-for="item in displayedProducts" :key="item.product_id + '-' + item.inventory_source" class="hover:bg-gray-50/50 dark:hover:bg-gray-700/20">
                                    <td class="py-2.5 px-3">
                                        <span class="font-bold text-gray-900 dark:text-white">{{ item.product_name }}</span>
                                    </td>
                                    <td class="py-2.5 px-3">
                                        <span v-if="item.inventory_source === 'pos'" class="px-2 py-0.5 rounded text-[10px] font-bold bg-indigo-100 text-indigo-700 dark:bg-indigo-900/30 dark:text-indigo-300">
                                            POS
                                        </span>
                                        <span v-else class="px-2 py-0.5 rounded text-[10px] font-bold bg-purple-100 text-purple-700 dark:bg-purple-900/30 dark:text-purple-300">
                                            ECOMMERCE
                                        </span>
                                    </td>
                                    <td class="py-2.5 px-3 text-gray-500">{{ item.unit_name }}</td>
                                    <td class="py-2.5 px-3 text-center font-bold">{{ item.qty_sold }}</td>
                                    <td class="py-2.5 px-3 text-right font-bold text-gray-900 dark:text-white">{{ formatCurrency(item.revenue) }}</td>
                                    <td class="py-2.5 px-3 text-right text-gray-500">{{ formatCurrency(item.cost) }}</td>
                                    <td class="py-2.5 px-3 text-right font-bold text-emerald-600 dark:text-emerald-400">{{ formatCurrency(item.profit) }}</td>
                                </tr>
                            </tbody>
                        </table>

                        <!-- Products Pagination (Screen only) -->
                        <div v-if="totalProductsPages > 1" class="no-print flex items-center justify-between pt-4 border-t border-gray-100 dark:border-gray-700 text-xs">
                            <span class="text-gray-500">
                                Page {{ productsPage }} of {{ totalProductsPages }} ({{ products_sold.length }} products)
                            </span>
                            <div class="flex items-center gap-1.5">
                                <button
                                    type="button"
                                    :disabled="productsPage <= 1"
                                    @click="productsPage--"
                                    class="px-2.5 py-1 rounded border border-gray-300 dark:border-gray-600 disabled:opacity-40 disabled:cursor-not-allowed hover:bg-gray-100 dark:hover:bg-gray-700 font-semibold transition">
                                    &larr; Prev
                                </button>
                                <button
                                    type="button"
                                    :disabled="productsPage >= totalProductsPages"
                                    @click="productsPage++"
                                    class="px-2.5 py-1 rounded border border-gray-300 dark:border-gray-600 disabled:opacity-40 disabled:cursor-not-allowed hover:bg-gray-100 dark:hover:bg-gray-700 font-semibold transition">
                                    Next &rarr;
                                </button>
                            </div>
                        </div>
                    </div>
                    <div v-else class="text-center py-10 text-gray-400 text-xs">
                        No product sales recorded on this date.
                    </div>
                </div>

                <!-- Tab 2: Orders Register -->
                <div v-if="activeTab === 'orders'" class="p-5">
                    <div v-if="filteredOrders.length" class="overflow-x-auto">
                        <table class="w-full text-xs text-left">
                            <thead class="bg-gray-50 dark:bg-gray-700/50 text-gray-500 uppercase tracking-wider text-[10px]">
                                <tr>
                                    <th class="py-2.5 px-3 rounded-l-lg">Order #</th>
                                    <th class="py-2.5 px-3">Time</th>
                                    <th class="py-2.5 px-3">Customer</th>
                                    <th class="py-2.5 px-3">Items</th>
                                    <th class="py-2.5 px-3">Payment</th>
                                    <th class="py-2.5 px-3 text-center">Status</th>
                                    <th class="py-2.5 px-3 text-right">Amount</th>
                                    <th class="py-2.5 px-3 text-right rounded-r-lg no-print">Action</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y dark:divide-gray-700/60 font-medium">
                                <tr v-for="order in displayedOrders" :key="order.id" class="hover:bg-gray-50/50 dark:hover:bg-gray-700/20">
                                    <td class="py-2.5 px-3 font-mono font-bold text-primary">
                                        <Link :href="route('admin.orders.show', order.id)" class="hover:underline">
                                            {{ order.tracking_number || `#${order.id}` }}
                                        </Link>
                                    </td>
                                    <td class="py-2.5 px-3 text-gray-500 font-mono">{{ order.time }}</td>
                                    <td class="py-2.5 px-3">
                                        <div class="font-bold text-gray-900 dark:text-white">{{ order.customer_name }}</div>
                                        <div class="text-[10px] text-gray-400 truncate max-w-[150px]">{{ order.customer_email }}</div>
                                    </td>
                                    <td class="py-2.5 px-3">
                                        <span class="px-2 py-0.5 rounded bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-300 font-semibold">
                                            {{ order.items_count }} items
                                        </span>
                                    </td>
                                    <td class="py-2.5 px-3 font-medium">{{ formatMethodName(order.payment_method) }}</td>
                                    <td class="py-2.5 px-3 text-center">
                                        <span
                                            :class="{
                                                'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/30 dark:text-emerald-300': ['completed', 'delivered'].includes(order.status),
                                                'bg-blue-100 text-blue-800 dark:bg-blue-900/30 dark:text-blue-300': ['processing', 'shipped'].includes(order.status),
                                                'bg-amber-100 text-amber-800 dark:bg-amber-900/30 dark:text-amber-300': order.status === 'pending',
                                                'bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-300': order.status === 'cancelled',
                                            }"
                                            class="px-2 py-0.5 rounded-full text-[10px] font-bold uppercase tracking-wider">
                                            {{ order.status }}
                                        </span>
                                    </td>
                                    <td class="py-2.5 px-3 text-right font-black text-gray-900 dark:text-white">
                                        {{ formatCurrency(order.total_amount) }}
                                    </td>
                                    <td class="py-2.5 px-3 text-right no-print">
                                        <div class="flex items-center justify-end gap-1.5">
                                            <Link :href="route('admin.orders.show', order.id)"
                                                class="px-2 py-1 rounded bg-gray-100 dark:bg-gray-700 hover:bg-primary hover:text-white text-[11px] font-bold transition">
                                                View
                                            </Link>
                                            <a :href="route('admin.orders.invoice', order.id)" target="_blank"
                                                class="px-2 py-1 rounded bg-gray-100 dark:bg-gray-700 hover:bg-gray-200 dark:hover:bg-gray-600 text-[11px] font-bold transition">
                                                Invoice
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                            </tbody>
                        </table>

                        <!-- Orders Pagination (Screen only) -->
                        <div v-if="totalOrdersPages > 1" class="no-print flex items-center justify-between pt-4 border-t border-gray-100 dark:border-gray-700 text-xs">
                            <span class="text-gray-500">
                                Page {{ ordersPage }} of {{ totalOrdersPages }} ({{ filteredOrders.length }} orders)
                            </span>
                            <div class="flex items-center gap-1.5">
                                <button
                                    type="button"
                                    :disabled="ordersPage <= 1"
                                    @click="ordersPage--"
                                    class="px-2.5 py-1 rounded border border-gray-300 dark:border-gray-600 disabled:opacity-40 disabled:cursor-not-allowed hover:bg-gray-100 dark:hover:bg-gray-700 font-semibold transition">
                                    &larr; Prev
                                </button>
                                <button
                                    type="button"
                                    :disabled="ordersPage >= totalOrdersPages"
                                    @click="ordersPage++"
                                    class="px-2.5 py-1 rounded border border-gray-300 dark:border-gray-600 disabled:opacity-40 disabled:cursor-not-allowed hover:bg-gray-100 dark:hover:bg-gray-700 font-semibold transition">
                                    Next &rarr;
                                </button>
                            </div>
                        </div>
                    </div>
                    <div v-else class="text-center py-10 text-gray-400 text-xs">
                        No orders match the filter on this date.
                    </div>
                </div>
            </div>

            <!-- ── Printable Sign-Off Footer ── -->
            <div class="print-only border-t-2 border-gray-900 pt-8 mt-12">
                <div class="grid grid-cols-3 gap-8 text-center text-xs">
                    <div>
                        <div class="border-b border-gray-400 pb-1 mb-2 font-mono">&nbsp;</div>
                        <div class="font-bold uppercase tracking-wider">Prepared By (Cashier)</div>
                    </div>
                    <div>
                        <div class="border-b border-gray-400 pb-1 mb-2 font-mono">&nbsp;</div>
                        <div class="font-bold uppercase tracking-wider">Verified By (Manager)</div>
                    </div>
                    <div>
                        <div class="border-b border-gray-400 pb-1 mb-2 font-mono">&nbsp;</div>
                        <div class="font-bold uppercase tracking-wider">Audited By (Accountant)</div>
                    </div>
                </div>
                <div class="text-center text-[10px] text-gray-500 mt-6">
                    End of Day Closing Reconciliation &bull; Generated on {{ generated_at }} &bull; System ID: SKYNET-EOD-{{ date }}
                </div>
            </div>

        </div>
    </AdminLayout>
</template>

<style scoped>
@media print {
    :deep(aside),
    :deep(header),
    :deep(nav),
    .no-print {
        display: none !important;
    }

    .print-only {
        display: block !important;
    }

    body {
        background: white !important;
        color: black !important;
        font-size: 11pt !important;
    }
}

@media screen {
    .print-only {
        display: none !important;
    }
}
</style>

