<script setup>
import { Head, Link, usePage } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { computed } from 'vue';

const props = defineProps({
    userRole:            String,
    isAdmin:             Boolean,
    isRider:             Boolean,
    operationalStats:    Object,
    podCustody:          Object, // Rider / staff POD float in custody
    financialStats:      Object, // null for staff
    salesTrend:          Array,  // empty for staff
    channelSplit:        Object, // empty for staff
    paymentMethodsSplit: Array,  // empty for staff
    lowStockProducts:    Array,
    recentOrders:        Array,
    posStatus:           Object,
});

const page = usePage();
const authUser = computed(() => page.props.auth?.user || {});

const formatPrice = (val) => {
    if (val === null || val === undefined || isNaN(val)) return '—';
    return '₦' + Number(val).toLocaleString('en-NG', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
};

// Calculate max revenue for proportional 7-day chart bars
const maxChartRevenue = computed(() => {
    if (!props.salesTrend || props.salesTrend.length === 0) return 1;
    const max = Math.max(...props.salesTrend.map(d => d.revenue || 0));
    return max > 0 ? max : 1;
});
</script>

<template>
    <Head title="Store Dashboard" />
    <AdminLayout>
        <div class="container mx-auto px-4 py-8 max-w-7xl text-gray-900 dark:text-gray-100 font-sans">

            <!-- ── Top Header & Personalized Welcome ───────────────── -->
            <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4 mb-8">
                <div>
                    <div class="flex items-center gap-2.5 flex-wrap">
                        <h1 class="text-2xl sm:text-3xl font-extrabold text-gray-900 dark:text-white tracking-tight">
                            Welcome, {{ authUser.name }}
                        </h1>
                        <span
                            class="px-2.5 py-0.5 rounded-full text-xs font-bold uppercase tracking-wider"
                            :class="isAdmin
                                ? 'theme-primary-badge'
                                : (isRider ? 'bg-amber-100 text-amber-800 dark:bg-amber-900/40 dark:text-amber-300' : 'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/40 dark:text-emerald-300')"
                        >
                            {{ isAdmin ? (authUser.role === 'super administrator' ? 'Super Administrator' : 'Administrator') : (isRider ? '🛵 Dispatch / Rider' : 'Store Associate / Staff') }}
                        </span>
                    </div>
                    <p class="text-xs sm:text-sm text-gray-500 dark:text-gray-400 mt-1">
                        {{ isAdmin
                            ? 'Executive overview of real-time store revenue, order volume, and omni-channel POS sync.'
                            : (isRider ? 'Rider dispatch hub: track assigned deliveries, collect customer payments, and remit POD cash.' : 'Store operations dashboard: track fulfillment queues, inventory alerts, and customer orders.') }}
                    </p>
                </div>

                <!-- Status & Quick Actions -->
                <div class="flex flex-wrap items-center gap-2.5">
                    <!-- POS Status Badge -->
                    <div class="inline-flex items-center gap-2 px-3 py-1.5 rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 text-xs font-semibold shadow-xs">
                        <span
                            class="w-2.5 h-2.5 rounded-full"
                            :class="posStatus?.is_connected ? 'bg-emerald-500 animate-pulse' : 'bg-gray-400'"
                        ></span>
                        <span class="text-gray-600 dark:text-gray-300">
                            POS: <strong>{{ posStatus?.is_connected ? 'Connected' : 'Offline' }}</strong>
                        </span>
                    </div>

                    <!-- Contextual Quick Action Button -->
                    <Link
                        v-if="isAdmin"
                        :href="route('admin.reports.end-of-day')"
                        class="px-4 py-2 text-xs font-bold rounded-xl text-white bg-primary hover:bg-primary-dark transition shadow-xs flex items-center gap-1.5"
                    >
                        <span>📊</span> End of Day Report
                    </Link>
                    <Link
                        v-else
                        :href="route('admin.orders.index')"
                        class="px-4 py-2 text-xs font-bold rounded-xl text-white bg-primary hover:bg-primary-dark transition shadow-xs flex items-center gap-1.5"
                    >
                        <span>🛒</span> Fulfill Orders
                    </Link>
                </div>
            </div>

            <!-- ── Top KPI Stat Cards (Role-Tailored) ──────────────── -->
            <!-- 1. Administrator View: Revenue + Volume + AOV + Queue -->
            <div v-if="isAdmin && financialStats" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-8">
                <!-- Total Gross Revenue -->
                <div class="bg-white dark:bg-gray-800 p-5 rounded-2xl border border-gray-200 dark:border-gray-700 shadow-xs hover:border-primary transition">
                    <div class="flex items-center justify-between mb-2">
                        <span class="text-xs font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400">Total Revenue</span>
                        <span class="w-8 h-8 rounded-xl bg-primary/10 text-primary flex items-center justify-center font-bold text-sm">₦</span>
                    </div>
                    <div class="text-2xl font-black text-gray-900 dark:text-white">
                        {{ formatPrice(financialStats.total_revenue) }}
                    </div>
                    <div class="flex items-center gap-1.5 mt-2 text-xs text-emerald-600 dark:text-emerald-400 font-semibold">
                        <span>↑</span>
                        <span>{{ formatPrice(financialStats.today_revenue) }} generated today</span>
                    </div>
                </div>

                <!-- Total Orders -->
                <div class="bg-white dark:bg-gray-800 p-5 rounded-2xl border border-gray-200 dark:border-gray-700 shadow-xs hover:border-secondary transition">
                    <div class="flex items-center justify-between mb-2">
                        <span class="text-xs font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400">Total Orders</span>
                        <span class="w-8 h-8 rounded-xl bg-secondary/10 text-secondary flex items-center justify-center font-bold text-sm">🛒</span>
                    </div>
                    <div class="text-2xl font-black text-gray-900 dark:text-white">
                        {{ operationalStats.total_orders.toLocaleString() }}
                    </div>
                    <div class="mt-2 text-xs text-gray-500 dark:text-gray-400 font-medium">
                        <strong class="text-gray-800 dark:text-gray-200">{{ operationalStats.today_orders }}</strong> new orders today
                    </div>
                </div>

                <!-- Average Order Value (AOV) -->
                <div class="bg-white dark:bg-gray-800 p-5 rounded-2xl border border-gray-200 dark:border-gray-700 shadow-xs hover:border-emerald-500 transition">
                    <div class="flex items-center justify-between mb-2">
                        <span class="text-xs font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400">Avg. Order Value</span>
                        <span class="w-8 h-8 rounded-xl bg-emerald-50 dark:bg-emerald-950/40 text-emerald-600 flex items-center justify-center font-bold text-sm">📈</span>
                    </div>
                    <div class="text-2xl font-black text-emerald-600 dark:text-emerald-400">
                        {{ formatPrice(financialStats.average_order_value) }}
                    </div>
                    <div class="mt-2 text-xs text-gray-500 dark:text-gray-400 font-medium">
                        Per completed checkout
                    </div>
                </div>

                <!-- Orders Awaiting Fulfillment -->
                <div class="bg-white dark:bg-gray-800 p-5 rounded-2xl border border-gray-200 dark:border-gray-700 shadow-xs hover:border-amber-500 transition">
                    <div class="flex items-center justify-between mb-2">
                        <span class="text-xs font-bold uppercase tracking-wider text-amber-600 dark:text-amber-400">Fulfillment Queue</span>
                        <span
                            class="w-8 h-8 rounded-xl flex items-center justify-center font-bold text-sm"
                            :class="operationalStats.pending_orders > 0 ? 'bg-amber-100 dark:bg-amber-900/40 text-amber-600' : 'bg-gray-100 text-gray-400'"
                        >
                            ⏳
                        </span>
                    </div>
                    <div class="text-2xl font-black text-gray-900 dark:text-white">
                        {{ operationalStats.pending_orders }}
                    </div>
                    <div class="mt-2 text-xs font-semibold" :class="operationalStats.pending_orders > 0 ? 'text-amber-600 dark:text-amber-400' : 'text-gray-400'">
                        {{ operationalStats.pending_orders > 0 ? '● Orders pending dispatch' : '✓ All orders fulfilled' }}
                    </div>
                </div>
            </div>

            <!-- 2. Rider View: Dedicated POD Collections & Delivery Custody -->
            <div v-else-if="isRider" class="space-y-4 mb-8">
                <!-- Floating Collections Alert Banner if holding money -->
                <div
                    v-if="podCustody?.cash_in_hand > 0"
                    class="p-4 rounded-2xl bg-gradient-to-r from-amber-500/15 via-orange-500/10 to-transparent border-2 border-amber-400 dark:border-amber-600 flex flex-col sm:flex-row sm:items-center justify-between gap-4 shadow-sm"
                >
                    <div class="flex items-start gap-3">
                        <span class="w-10 h-10 rounded-xl bg-amber-500 text-white flex items-center justify-center font-bold text-lg flex-shrink-0">
                            💼
                        </span>
                        <div>
                            <h3 class="text-sm font-bold text-amber-900 dark:text-amber-200">
                                Payments in Hand Pending Remittance: {{ formatPrice(podCustody.cash_in_hand) }}
                            </h3>
                            <p class="text-xs text-amber-800/80 dark:text-amber-300/80 mt-0.5">
                                You have collected payments (Cash / Transfer / POS) for <strong>{{ podCustody.cash_in_hand_count }}</strong> order(s). Hand over collections to the store cashier to clear your float.
                            </p>
                        </div>
                    </div>
                    <Link
                        :href="route('admin.pod-cash.index', { tab: 'in_hand' })"
                        class="px-4 py-2 bg-amber-600 hover:bg-amber-700 text-white rounded-xl text-xs font-bold shadow-xs whitespace-nowrap self-start sm:self-auto transition"
                    >
                        View Orders to Remit →
                    </Link>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                    <!-- Payments In Hand (Collected & Awaiting Handover) -->
                    <div class="bg-white dark:bg-gray-800 p-5 rounded-2xl border-2 border-amber-400 dark:border-amber-600/70 shadow-xs bg-amber-50/30">
                        <div class="flex items-center justify-between mb-2">
                            <span class="text-xs font-bold uppercase tracking-wider text-amber-800 dark:text-amber-300">Payments in Hand (To Remit)</span>
                            <span class="w-8 h-8 rounded-xl bg-amber-100 dark:bg-amber-900/50 text-amber-600 flex items-center justify-center font-bold text-sm">💼</span>
                        </div>
                        <div class="text-2xl font-black text-amber-600 dark:text-amber-400">
                            {{ formatPrice(podCustody?.cash_in_hand) }}
                        </div>
                        <p class="text-xs text-amber-700 dark:text-amber-300 mt-2 font-semibold">
                            {{ podCustody?.cash_in_hand_count || 0 }} order(s) collected from shoppers (Cash/Transfer/POS)
                        </p>
                    </div>

                    <!-- Remitted to Store (Pending Reconcile in Register) -->
                    <div class="bg-white dark:bg-gray-800 p-5 rounded-2xl border border-purple-200 dark:border-purple-800/60 shadow-xs bg-purple-50/20">
                        <div class="flex items-center justify-between mb-2">
                            <span class="text-xs font-bold uppercase tracking-wider text-purple-700 dark:text-purple-300">Remitted to Store</span>
                            <span class="w-8 h-8 rounded-xl bg-purple-100 dark:bg-purple-900/40 text-purple-600 flex items-center justify-center font-bold text-sm">📥</span>
                        </div>
                        <div class="text-2xl font-black text-purple-700 dark:text-purple-400">
                            {{ formatPrice(podCustody?.remitted_pending_reconcile) }}
                        </div>
                        <p class="text-xs text-purple-600 dark:text-purple-300 mt-2">
                            Awaiting cashier till reconciliation
                        </p>
                    </div>

                    <!-- Assigned Deliveries -->
                    <div class="bg-white dark:bg-gray-800 p-5 rounded-2xl border border-blue-200 dark:border-blue-800/60 shadow-xs bg-blue-50/20">
                        <div class="flex items-center justify-between mb-2">
                            <span class="text-xs font-bold uppercase tracking-wider text-blue-700 dark:text-blue-300">Active Parcels</span>
                            <span class="w-8 h-8 rounded-xl bg-blue-100 dark:bg-blue-900/40 text-blue-600 flex items-center justify-center font-bold text-sm">🛵</span>
                        </div>
                        <div class="text-2xl font-black text-blue-600 dark:text-blue-400">
                            {{ operationalStats.pending_orders }}
                        </div>
                        <p class="text-xs text-blue-600 dark:text-blue-300 mt-2">
                            In transit or awaiting delivery
                        </p>
                    </div>

                    <!-- Completed Deliveries -->
                    <div class="bg-white dark:bg-gray-800 p-5 rounded-2xl border border-emerald-200 dark:border-emerald-800/60 shadow-xs bg-emerald-50/20">
                        <div class="flex items-center justify-between mb-2">
                            <span class="text-xs font-bold uppercase tracking-wider text-emerald-700 dark:text-emerald-300">Completed Parcels</span>
                            <span class="w-8 h-8 rounded-xl bg-emerald-100 dark:bg-emerald-900/40 text-emerald-600 flex items-center justify-center font-bold text-sm">✓</span>
                        </div>
                        <div class="text-2xl font-black text-emerald-600 dark:text-emerald-400">
                            {{ operationalStats.completed_orders }}
                        </div>
                        <p class="text-xs text-emerald-600 dark:text-emerald-300 mt-2">
                            Delivered successfully
                        </p>
                    </div>
                </div>
            </div>

            <!-- 3. General Staff View: Warehouse & Operations -->
            <div v-else class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-8">
                <!-- Action Queue -->
                <div class="bg-white dark:bg-gray-800 p-5 rounded-2xl border border-amber-200 dark:border-amber-800/60 shadow-xs">
                    <div class="flex items-center justify-between mb-2">
                        <span class="text-xs font-bold uppercase tracking-wider text-amber-700 dark:text-amber-400">Orders to Pack & Dispatch</span>
                        <span class="w-8 h-8 rounded-xl bg-amber-100 dark:bg-amber-900/40 text-amber-600 flex items-center justify-center font-bold text-sm">📦</span>
                    </div>
                    <div class="text-3xl font-black text-amber-600 dark:text-amber-400">
                        {{ operationalStats.pending_orders }}
                    </div>
                    <p class="text-xs text-gray-500 dark:text-gray-400 mt-2">Requires immediate packing & fulfillment</p>
                </div>

                <!-- Today's Orders -->
                <div class="bg-white dark:bg-gray-800 p-5 rounded-2xl border border-gray-200 dark:border-gray-700 shadow-xs">
                    <div class="flex items-center justify-between mb-2">
                        <span class="text-xs font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400">Today's Orders</span>
                        <span class="w-8 h-8 rounded-xl bg-primary/10 text-primary flex items-center justify-center font-bold text-sm">🛒</span>
                    </div>
                    <div class="text-3xl font-black text-gray-900 dark:text-white">
                        {{ operationalStats.today_orders }}
                    </div>
                    <p class="text-xs text-gray-500 dark:text-gray-400 mt-2">Placed by customers today</p>
                </div>

                <!-- Low Stock Items -->
                <div class="bg-white dark:bg-gray-800 p-5 rounded-2xl border border-gray-200 dark:border-gray-700 shadow-xs">
                    <div class="flex items-center justify-between mb-2">
                        <span class="text-xs font-bold uppercase tracking-wider text-amber-600 dark:text-amber-400">Low Stock Alert</span>
                        <span class="w-8 h-8 rounded-xl bg-amber-50 dark:bg-amber-950/40 text-amber-600 flex items-center justify-center font-bold text-sm">⚠️</span>
                    </div>
                    <div class="text-3xl font-black text-amber-600 dark:text-amber-400">
                        {{ operationalStats.low_stock_count }}
                    </div>
                    <p class="text-xs text-gray-500 dark:text-gray-400 mt-2">Items with ≤ 5 units in stock</p>
                </div>

                <!-- Out of Stock Items -->
                <div class="bg-white dark:bg-gray-800 p-5 rounded-2xl border border-gray-200 dark:border-gray-700 shadow-xs">
                    <div class="flex items-center justify-between mb-2">
                        <span class="text-xs font-bold uppercase tracking-wider text-rose-600 dark:text-rose-400">Out of Stock</span>
                        <span class="w-8 h-8 rounded-xl bg-rose-50 dark:bg-rose-950/40 text-rose-600 flex items-center justify-center font-bold text-sm">🛑</span>
                    </div>
                    <div class="text-3xl font-black text-rose-600 dark:text-rose-400">
                        {{ operationalStats.out_of_stock }}
                    </div>
                    <p class="text-xs text-gray-500 dark:text-gray-400 mt-2">Items completely depleted</p>
                </div>
            </div>

            <!-- ── Admin Section: 7-Day Revenue Trend & Omni-Channel Analytics ── -->
            <div v-if="isAdmin" class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-8">
                
                <!-- 7-Day Visual Sales Velocity (2 cols) -->
                <div class="lg:col-span-2 bg-white dark:bg-gray-800 p-6 rounded-2xl border border-gray-200 dark:border-gray-700 shadow-xs flex flex-col justify-between">
                    <div>
                        <div class="flex items-center justify-between mb-1">
                            <h2 class="text-base font-bold text-gray-900 dark:text-white flex items-center gap-2">
                                <span>📊</span> 7-Day Revenue Velocity
                            </h2>
                            <Link :href="route('admin.revenue.index')" class="text-xs font-semibold text-primary hover:underline">
                                Full Analytics →
                            </Link>
                        </div>
                        <p class="text-xs text-gray-500 dark:text-gray-400 mb-6">
                            Daily gross completed order revenues over the past week.
                        </p>
                    </div>

                    <!-- Micro Bar Chart -->
                    <div class="grid grid-cols-7 gap-2 sm:gap-4 items-end h-44 pt-4 border-b border-gray-100 dark:border-gray-700 pb-2">
                        <div
                            v-for="(day, idx) in salesTrend"
                            :key="idx"
                            class="flex flex-col items-center h-full justify-end group relative"
                        >
                            <!-- Tooltip on hover -->
                            <div class="opacity-0 group-hover:opacity-100 transition absolute -top-12 z-20 bg-gray-900 text-white text-[10px] font-bold py-1 px-2 rounded-lg pointer-events-none whitespace-nowrap shadow-lg">
                                <div>{{ day.date }}: {{ formatPrice(day.revenue) }}</div>
                                <div class="text-[9px] text-gray-400 font-normal">{{ day.orders }} order(s)</div>
                            </div>

                            <!-- Bar -->
                            <div class="w-full max-w-[36px] bg-gray-100 dark:bg-gray-700 rounded-t-xl h-full flex items-end overflow-hidden">
                                <div
                                    class="w-full bg-primary hover:bg-primary-dark transition-all rounded-t-xl"
                                    :style="{
                                        height: Math.max(8, (day.revenue / maxChartRevenue) * 100) + '%'
                                    }"
                                ></div>
                            </div>

                            <!-- Labels -->
                            <span class="text-[11px] font-bold text-gray-600 dark:text-gray-300 mt-2">{{ day.day }}</span>
                            <span class="text-[9px] text-gray-400">{{ day.date }}</span>
                        </div>
                    </div>

                    <div class="flex items-center justify-between pt-3 text-xs text-gray-500 dark:text-gray-400">
                        <span>Total 7-day orders: <strong>{{ salesTrend.reduce((sum, d) => sum + d.orders, 0) }}</strong></span>
                        <span>7-day Gross: <strong class="text-primary">{{ formatPrice(salesTrend.reduce((sum, d) => sum + d.revenue, 0)) }}</strong></span>
                    </div>
                </div>

                <!-- Channel Split & Payment Methods Breakdown (1 col) -->
                <div class="bg-white dark:bg-gray-800 p-6 rounded-2xl border border-gray-200 dark:border-gray-700 shadow-xs flex flex-col justify-between space-y-5">
                    <div>
                        <h2 class="text-base font-bold text-gray-900 dark:text-white mb-1 flex items-center gap-2">
                            <span>⚡</span> Omni-Channel Share
                        </h2>
                        <p class="text-xs text-gray-500 dark:text-gray-400 mb-4">
                            eCommerce Storefront vs POS Register sales.
                        </p>

                        <!-- Progress Bar Split -->
                        <div class="w-full h-3 bg-gray-100 dark:bg-gray-700 rounded-full overflow-hidden flex mb-2">
                            <div
                                class="bg-primary h-full transition-all"
                                :style="{ width: (channelSplit?.ecommerce_percent || 50) + '%' }"
                                title="eCommerce Share"
                            ></div>
                            <div
                                class="bg-secondary h-full transition-all"
                                :style="{ width: (channelSplit?.pos_percent || 50) + '%' }"
                                title="POS Store Share"
                            ></div>
                        </div>

                        <div class="grid grid-cols-2 gap-2 text-xs pt-2">
                            <div class="flex items-center gap-2">
                                <span class="w-2.5 h-2.5 rounded-full bg-primary"></span>
                                <span class="text-gray-600 dark:text-gray-300">
                                    Web: <strong>{{ channelSplit?.ecommerce_percent || 0 }}%</strong>
                                </span>
                            </div>
                            <div class="flex items-center gap-2">
                                <span class="w-2.5 h-2.5 rounded-full bg-secondary"></span>
                                <span class="text-gray-600 dark:text-gray-300">
                                    POS: <strong>{{ channelSplit?.pos_percent || 0 }}%</strong>
                                </span>
                            </div>
                        </div>
                    </div>

                    <!-- Payment Methods Summary -->
                    <div class="pt-4 border-t border-gray-100 dark:border-gray-700">
                        <h3 class="text-xs font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400 mb-2.5">
                            Payment Methods Distribution
                        </h3>
                        <div class="space-y-2">
                            <div
                                v-for="(pm, idx) in paymentMethodsSplit"
                                :key="idx"
                                class="flex items-center justify-between text-xs py-1"
                            >
                                <span class="text-gray-700 dark:text-gray-300 font-medium truncate max-w-[150px]">{{ pm.method }}</span>
                                <div class="text-right">
                                    <span class="font-bold text-gray-900 dark:text-white">{{ formatPrice(pm.revenue) }}</span>
                                    <span class="text-[10px] text-gray-400 ml-1">({{ pm.count }})</span>
                                </div>
                            </div>
                        </div>
                    </div>

                </div>

            </div>

            <!-- ── Grid: Low Stock Alert Watchlist & Recent Orders ── -->
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-8">
                
                <!-- Recent Orders Register (2 cols) -->
                <div class="lg:col-span-2 bg-white dark:bg-gray-800 rounded-2xl border border-gray-200 dark:border-gray-700 shadow-xs overflow-hidden flex flex-col justify-between">
                    <div>
                        <div class="p-5 border-b border-gray-200 dark:border-gray-700 flex items-center justify-between">
                            <div>
                                <h2 class="text-base font-bold text-gray-900 dark:text-white flex items-center gap-2">
                                    <span>🛒</span> Recent Orders Register
                                </h2>
                                <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">
                                    Latest customer transactions across all channels.
                                </p>
                            </div>
                            <Link
                                :href="route('admin.orders.index')"
                                class="px-3.5 py-1.5 rounded-xl bg-gray-100 dark:bg-gray-700 hover:bg-primary hover:text-white text-xs font-bold transition cursor-pointer"
                            >
                                View All Orders →
                            </Link>
                        </div>

                        <div class="overflow-x-auto">
                            <table class="w-full text-left text-xs text-gray-600 dark:text-gray-300">
                                <thead class="bg-gray-50 dark:bg-gray-700/50 uppercase font-bold text-[10px] text-gray-500 tracking-wider border-b border-gray-200 dark:border-gray-700">
                                    <tr>
                                        <th class="py-3 px-4">Order ID</th>
                                        <th class="py-3 px-4">Customer</th>
                                        <th class="py-3 px-4 text-center">Items</th>
                                        <th v-if="isAdmin || isRider" class="py-3 px-4 text-right">Amount</th>
                                        <th class="py-3 px-4 text-center">Status</th>
                                        <th class="py-3 px-4 text-right">Action</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                                    <tr
                                        v-for="order in recentOrders"
                                        :key="order.id"
                                        class="hover:bg-gray-50 dark:hover:bg-gray-700/40 transition"
                                    >
                                        <td class="py-3 px-4">
                                            <div class="flex items-center gap-1.5 font-mono font-bold text-gray-900 dark:text-white">
                                                <span>#{{ order.id }}</span>
                                                <span
                                                    v-if="order.is_pos_sale"
                                                    class="text-[9px] px-1.5 py-0.2 rounded font-sans font-bold bg-blue-100 dark:bg-blue-900/40 text-blue-700 dark:text-blue-300"
                                                >
                                                    POS
                                                </span>
                                            </div>
                                            <span class="text-[10px] text-gray-400">{{ order.created_at }}</span>
                                        </td>

                                        <td class="py-3 px-4">
                                            <div class="font-semibold text-gray-900 dark:text-white truncate max-w-[160px]">
                                                {{ order.customer_name }}
                                            </div>
                                        </td>

                                        <td class="py-3 px-4 text-center font-bold">
                                            {{ order.items_count }}
                                        </td>

                                        <td v-if="isAdmin || isRider" class="py-3 px-4 text-right">
                                            <div class="font-black text-gray-900 dark:text-white">
                                                {{ formatPrice(order.total_amount) }}
                                            </div>
                                            <div v-if="order.pod_status && order.pod_status !== 'none'" class="mt-0.5">
                                                <span
                                                    class="inline-block px-1.5 py-0.2 text-[9px] font-extrabold uppercase rounded"
                                                    :class="{
                                                        'bg-amber-100 text-amber-800 dark:bg-amber-900/50 dark:text-amber-300': order.pod_status === 'cash_collected',
                                                        'bg-purple-100 text-purple-800 dark:bg-purple-900/50 dark:text-purple-300': order.pod_status === 'remitted_to_store',
                                                        'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/50 dark:text-emerald-300': order.pod_status === 'reconciled',
                                                        'bg-yellow-100 text-yellow-800 dark:bg-yellow-900/50 dark:text-yellow-300': order.pod_status === 'awaiting_collection',
                                                    }"
                                                >
                                                    {{ order.pod_status === 'cash_collected' ? '💵 In Hand' : (order.pod_status === 'remitted_to_store' ? '📥 Remitted' : (order.pod_status === 'reconciled' ? '✓ Reconciled' : '⏳ Awaiting')) }}
                                                </span>
                                            </div>
                                        </td>

                                        <td class="py-3 px-4 text-center">
                                            <span
                                                class="px-2 py-0.5 rounded-full text-[10px] font-extrabold uppercase tracking-wider"
                                                :class="{
                                                    'bg-amber-100 text-amber-800 dark:bg-amber-900/40 dark:text-amber-300': order.status === 'pending',
                                                    'bg-blue-100 text-blue-800 dark:bg-blue-900/40 dark:text-blue-300': order.status === 'processing',
                                                    'bg-green-100 text-green-800 dark:bg-green-900/40 dark:text-green-300': order.status === 'completed',
                                                    'bg-rose-100 text-rose-800 dark:bg-rose-900/40 dark:text-rose-300': order.status === 'cancelled',
                                                }"
                                            >
                                                {{ order.status }}
                                            </span>
                                        </td>

                                        <td class="py-3 px-4 text-right">
                                            <Link
                                                :href="route('admin.orders.show', order.id)"
                                                class="text-xs font-bold text-primary hover:underline"
                                            >
                                                View →
                                            </Link>
                                        </td>
                                    </tr>

                                    <tr v-if="!recentOrders || recentOrders.length === 0">
                                        <td :colspan="isAdmin ? 6 : 5" class="py-8 text-center text-gray-400">
                                            No recent orders recorded yet.
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- Low Stock & Restock Alert Watchlist (1 col) -->
                <div class="bg-white dark:bg-gray-800 rounded-2xl border border-gray-200 dark:border-gray-700 shadow-xs p-5 flex flex-col justify-between">
                    <div>
                        <div class="flex items-center justify-between pb-3 border-b border-gray-100 dark:border-gray-700 mb-3">
                            <h2 class="text-sm font-bold text-gray-900 dark:text-white flex items-center gap-1.5">
                                <span>⚠️</span> Low Stock Watchlist
                            </h2>
                            <Link
                                :href="route('admin.products.index')"
                                class="text-[11px] font-semibold text-primary hover:underline"
                            >
                                Catalog →
                            </Link>
                        </div>

                        <div v-if="lowStockProducts?.length" class="space-y-3">
                            <div
                                v-for="prod in lowStockProducts"
                                :key="prod.id"
                                class="flex items-center justify-between gap-3 p-2 rounded-xl hover:bg-gray-50 dark:hover:bg-gray-700/30 transition border border-gray-100 dark:border-gray-700/50"
                            >
                                <div class="flex items-center gap-2.5 min-w-0">
                                    <div class="w-9 h-9 rounded-lg bg-gray-100 dark:bg-gray-700 overflow-hidden flex-shrink-0 flex items-center justify-center">
                                        <img v-if="prod.image" :src="'/storage/' + prod.image" class="w-full h-full object-cover" />
                                        <span v-else class="text-[10px] text-gray-400">📦</span>
                                    </div>
                                    <div class="min-w-0">
                                        <p class="text-xs font-bold text-gray-900 dark:text-white truncate max-w-[130px]">{{ prod.name }}</p>
                                        <p class="text-[10px] text-gray-400 font-mono">SKU: {{ prod.sku || 'N/A' }}</p>
                                    </div>
                                </div>

                                <div class="text-right shrink-0">
                                    <span
                                        class="inline-block px-2 py-0.5 rounded-full text-[10px] font-black"
                                        :class="prod.stock <= 0
                                            ? 'bg-rose-100 text-rose-700 dark:bg-rose-900/40 dark:text-rose-300'
                                            : 'bg-amber-100 text-amber-800 dark:bg-amber-900/40 dark:text-amber-300'"
                                    >
                                        {{ prod.stock <= 0 ? 'OUT OF STOCK' : prod.stock + ' left' }}
                                    </span>
                                </div>
                            </div>
                        </div>

                        <div v-else class="py-8 text-center text-gray-400 text-xs">
                            <span class="text-2xl mb-1 block">✅</span>
                            All product stocks are healthy!
                        </div>
                    </div>

                    <!-- Quick action at bottom of card -->
                    <div class="pt-4 border-t border-gray-100 dark:border-gray-700 mt-4">
                        <Link
                            :href="route('admin.products.index')"
                            class="w-full py-2 bg-gray-100 dark:bg-gray-700 hover:bg-gray-200 dark:hover:bg-gray-600 rounded-xl text-xs font-bold text-gray-700 dark:text-gray-200 text-center block transition"
                        >
                            Update Product Inventory
                        </Link>
                    </div>
                </div>

            </div>

        </div>
    </AdminLayout>
</template>
