<script setup>
import { Head, Link, router } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import Pagination from '@/Components/Pagination.vue';
import { ref, watch, computed } from 'vue';
import debounce from 'lodash/debounce';

const props = defineProps({
    canReconcile:  Boolean,
    canViewAll:    Boolean,
    isRider:       Boolean,
    tab:           String,
    filters:       Object,
    kpis:          Object,
    riderFloats:   Array,
    allRiders:     Array,
    orders:        Object,
});

// State
const activeTab = ref(props.tab || 'in_hand');
const search = ref(props.filters.search || '');
const selectedRiderId = ref(props.filters.rider_id || '');
const datePreset = ref(props.filters.date_preset || 'all');

// Selection state for bulk operations
const selectedOrderIds = ref([]);
const selectAll = ref(false);

const toggleSelectAll = () => {
    if (selectAll.value) {
        selectedOrderIds.value = props.orders.data.map(o => o.id);
    } else {
        selectedOrderIds.value = [];
    }
};

watch(selectedOrderIds, (newVal) => {
    selectAll.value = props.orders.data && props.orders.data.length > 0 && newVal.length === props.orders.data.length;
});

// Modals
const showCollectModal = ref(false);
const showReconcileModal = ref(false);
const showBulkReconcileModal = ref(false);

const activeOrder = ref(null);
const collectedAmount = ref(0);
const collectionChannel = ref('cash');
const reconcileNote = ref('');
const isSubmitting = ref(false);

const formatPrice = (val) => {
    if (val === null || val === undefined || isNaN(val)) return '₦0.00';
    return '₦' + Number(val).toLocaleString('en-NG', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
};

// Filter Navigation
const applyFilters = debounce(() => {
    router.get(route('admin.pod-cash.index'), {
        tab: activeTab.value,
        rider_id: selectedRiderId.value || undefined,
        search: search.value || undefined,
        date_preset: datePreset.value !== 'all' ? datePreset.value : undefined,
    }, { preserveState: true, replace: true });
}, 300);

const switchTab = (tabName) => {
    activeTab.value = tabName;
    selectedOrderIds.value = [];
    selectAll.value = false;
    applyFilters();
};

const filterByRider = (riderId) => {
    selectedRiderId.value = selectedRiderId.value === riderId ? '' : riderId;
    applyFilters();
};

watch([search, selectedRiderId, datePreset], applyFilters);

const clearFilters = () => {
    search.value = '';
    selectedRiderId.value = '';
    datePreset.value = 'all';
    applyFilters();
};

// Actions
const openCollectModal = (order) => {
    activeOrder.value = order;
    collectedAmount.value = order.pod_amount_collected || order.total_amount;
    collectionChannel.value = 'cash';
    showCollectModal.value = true;
};

const submitCollect = () => {
    if (!activeOrder.value) return;
    if (Number(collectedAmount.value) < Number(activeOrder.value.total_amount)) {
        alert(`Cannot collect less than full order total of ${formatPrice(activeOrder.value.total_amount)}. Full payment is required.`);
        return;
    }
    isSubmitting.value = true;
    router.post(route('admin.pod-cash.collect', activeOrder.value.id), {
        amount: collectedAmount.value,
        channel: collectionChannel.value,
    }, {
        preserveScroll: true,
        onFinish: () => {
            isSubmitting.value = false;
            showCollectModal.value = false;
        }
    });
};

const markRemitted = (order) => {
    if (props.isRider) {
        alert('Riders cannot remit orders. Only store cashiers and administrators can confirm receipt of cash remittance.');
        return;
    }
    const amountStr = formatPrice(order.pod_amount_collected || order.total_amount);
    const actionPrompt = `Confirm receiving & reconciling payment of ${amountStr} for Order #${order.id} directly into your store custody?`;
    if (!confirm(actionPrompt)) return;
    router.post(route('admin.pod-cash.remit', order.id), {}, { preserveScroll: true });
};

const openReconcileModal = (order) => {
    activeOrder.value = order;
    reconcileNote.value = 'Verified and deposited into cash drawer.';
    showReconcileModal.value = true;
};

const submitReconcile = () => {
    if (!activeOrder.value) return;
    isSubmitting.value = true;
    router.post(route('admin.pod-cash.reconcile', activeOrder.value.id), {
        reconciliation_note: reconcileNote.value,
    }, {
        preserveScroll: true,
        onFinish: () => {
            isSubmitting.value = false;
            showReconcileModal.value = false;
        }
    });
};

const bulkRemit = () => {
    if (props.isRider) {
        alert('Riders cannot remit orders. Only store cashiers and administrators can confirm receipt of cash remittance.');
        return;
    }
    if (selectedOrderIds.value.length === 0) return;
    const actionPrompt = `Receive & reconcile ${selectedOrderIds.value.length} selected orders directly into your store custody?`;
    if (!confirm(actionPrompt)) return;
    isSubmitting.value = true;
    router.post(route('admin.pod-cash.bulk-remit'), {
        order_ids: selectedOrderIds.value,
    }, {
        preserveScroll: true,
        onFinish: () => {
            isSubmitting.value = false;
            selectedOrderIds.value = [];
            selectAll.value = false;
        }
    });
};

const submitBulkReconcile = () => {
    if (selectedOrderIds.value.length === 0) return;
    isSubmitting.value = true;
    router.post(route('admin.pod-cash.bulk-reconcile'), {
        order_ids: selectedOrderIds.value,
        reconciliation_note: reconcileNote.value,
    }, {
        preserveScroll: true,
        onFinish: () => {
            isSubmitting.value = false;
            showBulkReconcileModal.value = false;
            selectedOrderIds.value = [];
        }
    });
};

const exportCsv = () => {
    const params = new URLSearchParams({
        tab: activeTab.value,
        rider_id: selectedRiderId.value || '',
        search: search.value || '',
        date_preset: datePreset.value || '',
    });
    window.location.href = route('admin.pod-cash.export') + '?' + params.toString();
};

const selectedTotalAmount = computed(() => {
    if (!props.orders.data) return 0;
    return props.orders.data
        .filter(o => selectedOrderIds.value.includes(o.id))
        .reduce((sum, o) => sum + Number(o.pod_amount_collected || o.total_amount), 0);
});
</script>

<template>
    <Head title="POD Cash Management & Till Audit" />
    <AdminLayout>
        <div class="container mx-auto px-4 py-8 max-w-7xl font-sans text-gray-900 dark:text-gray-100">

            <!-- ── Top Header ─────────────────────────────────────── -->
            <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4 mb-8">
                <div>
                    <div class="flex items-center gap-2.5 flex-wrap">
                        <span class="w-10 h-10 rounded-2xl bg-amber-500 text-white flex items-center justify-center font-bold text-xl shadow-xs">
                            🛵
                        </span>
                        <div>
                            <h1 class="text-2xl sm:text-3xl font-black text-gray-900 dark:text-white tracking-tight">
                                POD Collections & Remittance Station
                            </h1>
                            <p class="text-xs sm:text-sm text-gray-500 dark:text-gray-400 mt-0.5">
                                {{ isRider
                                    ? 'Track your collected doorstep payments (Cash, Transfer, POS), active delivery parcels, and remit collections to the store.'
                                    : 'Audit rider collection custody in real time, verify remittance handovers (Cash & Bank Transfers), and reconcile deposits into store accounts / till.' }}
                            </p>
                        </div>
                    </div>
                </div>

                <div class="flex items-center gap-2.5 flex-wrap">
                    <Link
                        :href="route('admin.collections-received.index')"
                        class="px-3.5 py-2 bg-emerald-50 dark:bg-emerald-950/40 hover:bg-emerald-100 border border-emerald-300 dark:border-emerald-700 text-emerald-800 dark:text-emerald-300 rounded-xl text-xs font-bold transition flex items-center gap-1.5 shadow-xs"
                    >
                        <span>🏪</span> Collections Received →
                    </Link>
                    <button
                        type="button"
                        @click="exportCsv"
                        class="px-4 py-2 bg-emerald-600 hover:bg-emerald-700 text-white rounded-xl text-xs font-bold shadow-xs flex items-center gap-1.5 transition cursor-pointer"
                    >
                        <span>📥</span> Export Audit CSV
                    </button>
                    <Link
                        :href="route('admin.reports.sales')"
                        class="px-3 py-2 bg-gray-100 dark:bg-gray-700 hover:bg-gray-200 text-gray-700 dark:text-gray-200 rounded-xl text-xs font-bold transition flex items-center gap-1"
                    >
                        <span>📊</span> View Sales Report
                    </Link>
                </div>
            </div>

            <!-- ── Top 4 KPI Metrics Cards ────────────────────────── -->
            <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-4 gap-3 mb-8">
                
                <!-- 1. Payments In Hand (Float with Riders) -->
                <div
                    @click="switchTab('in_hand')"
                    class="bg-white dark:bg-gray-800 p-4 rounded-2xl border-2 cursor-pointer transition shadow-xs"
                    :class="activeTab === 'in_hand' ? 'border-amber-500 bg-amber-50/40 dark:bg-amber-950/20' : 'border-amber-300 dark:border-amber-800/60 hover:border-amber-500'"
                >
                    <div class="flex items-center justify-between mb-1.5">
                        <span class="text-[11px] font-bold uppercase tracking-wider text-amber-800 dark:text-amber-300">Payments in Hand</span>
                        <span class="w-7 h-7 rounded-lg bg-amber-100 dark:bg-amber-900/50 text-amber-600 flex items-center justify-center font-bold text-xs">💰</span>
                    </div>
                    <div class="text-xl sm:text-2xl font-black text-amber-600 dark:text-amber-400">
                        {{ formatPrice(kpis.in_hand_cash) }}
                    </div>
                    <div class="mt-1 text-[11px] text-amber-700 dark:text-amber-300 font-medium">
                        <strong>{{ kpis.in_hand_count }}</strong> with riders awaiting store handover
                    </div>
                </div>

                <!-- 2. Reconciled in Drawer / Accounts -->
                <div
                    @click="switchTab('reconciled')"
                    class="bg-white dark:bg-gray-800 p-4 rounded-2xl border cursor-pointer transition shadow-xs"
                    :class="activeTab === 'reconciled' ? 'border-emerald-500 bg-emerald-50/40 dark:bg-emerald-950/20' : 'border-emerald-200 dark:border-emerald-800/60 hover:border-emerald-500'"
                >
                    <div class="flex items-center justify-between mb-1.5">
                        <span class="text-[11px] font-bold uppercase tracking-wider text-emerald-700 dark:text-emerald-300">Settled & Reconciled</span>
                        <span class="w-7 h-7 rounded-lg bg-emerald-100 dark:bg-emerald-900/40 text-emerald-600 flex items-center justify-center font-bold text-xs">🔒</span>
                    </div>
                    <div class="text-xl sm:text-2xl font-black text-emerald-600 dark:text-emerald-400">
                        {{ formatPrice(kpis.reconciled_cash) }}
                    </div>
                    <div class="mt-1 text-[11px] text-emerald-600 dark:text-emerald-300 font-medium">
                        <strong>{{ kpis.reconciled_count }}</strong> verified into store till & accounts
                    </div>
                </div>

                <!-- 3. In Transit (Assigned to Rider) -->
                <div
                    @click="switchTab('in_transit')"
                    class="bg-white dark:bg-gray-800 p-4 rounded-2xl border cursor-pointer transition shadow-xs"
                    :class="activeTab === 'in_transit' ? 'border-blue-500 bg-blue-50/40 dark:bg-blue-950/20' : 'border-blue-200 dark:border-blue-800/60 hover:border-blue-500'"
                >
                    <div class="flex items-center justify-between mb-1.5">
                        <span class="text-[11px] font-bold uppercase tracking-wider text-blue-700 dark:text-blue-300">In Transit</span>
                        <span class="w-7 h-7 rounded-lg bg-blue-100 dark:bg-blue-900/40 text-blue-600 flex items-center justify-center font-bold text-xs">🚚</span>
                    </div>
                    <div class="text-xl sm:text-2xl font-black text-blue-600 dark:text-blue-400">
                        {{ formatPrice(kpis.in_transit_cash) }}
                    </div>
                    <div class="mt-1 text-[11px] text-blue-600 dark:text-blue-300 font-medium">
                        <strong>{{ kpis.in_transit_count }}</strong> on bike / with dispatchers
                    </div>
                </div>

                <!-- 4. Pending Dispatch (Still in Store/Warehouse) -->
                <div
                    @click="switchTab('pending_dispatch')"
                    class="bg-white dark:bg-gray-800 p-4 rounded-2xl border cursor-pointer transition shadow-xs"
                    :class="activeTab === 'pending_dispatch' ? 'border-gray-500 bg-gray-50 dark:bg-gray-750' : 'border-gray-200 dark:border-gray-700 hover:border-gray-400'"
                >
                    <div class="flex items-center justify-between mb-1.5">
                        <span class="text-[11px] font-bold uppercase tracking-wider text-gray-700 dark:text-gray-300">In Store / Warehouse</span>
                        <span class="w-7 h-7 rounded-lg bg-gray-100 dark:bg-gray-700 text-gray-600 flex items-center justify-center font-bold text-xs">📦</span>
                    </div>
                    <div class="text-xl sm:text-2xl font-black text-gray-700 dark:text-gray-300">
                        {{ formatPrice(kpis.pending_dispatch_cash) }}
                    </div>
                    <div class="mt-1 text-[11px] text-gray-500 dark:text-gray-400 font-medium">
                        <strong>{{ kpis.pending_dispatch_count }}</strong> awaiting dispatch
                    </div>
                </div>

            </div>

            <!-- ── Active Rider Float Monitor (Admin & Cashier View) ── -->
            <div v-if="canViewAll && riderFloats && riderFloats.length > 0" class="mb-6 p-5 bg-white dark:bg-gray-800 rounded-2xl border border-amber-300 dark:border-amber-700/60 shadow-xs bg-amber-50/20">
                <div class="flex items-center justify-between mb-3">
                    <div>
                        <h2 class="text-sm font-bold uppercase tracking-wider text-amber-900 dark:text-amber-200 flex items-center gap-2">
                            <span>🛵</span> Active Dispatch Float Balances (Payments with Riders)
                        </h2>
                        <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">
                            Click any rider card below to immediately filter and inspect their specific collected parcels.
                        </p>
                    </div>
                    <span class="text-xs font-bold text-amber-700 dark:text-amber-300 bg-amber-100 dark:bg-amber-900/40 px-2.5 py-1 rounded-full">
                        {{ riderFloats.length }} Rider(s) Holding Collections
                    </span>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-3 pt-1">
                    <button
                        v-for="rf in riderFloats"
                        :key="rf.staff_id"
                        type="button"
                        @click="filterByRider(rf.staff_id)"
                        class="p-3.5 rounded-xl border text-left transition transform hover:-translate-y-0.5 cursor-pointer"
                        :class="selectedRiderId == rf.staff_id
                            ? 'bg-amber-500 text-white border-amber-600 shadow-md ring-2 ring-amber-400'
                            : 'bg-white dark:bg-gray-750 border-gray-200 dark:border-gray-700 hover:border-amber-400 shadow-xs'"
                    >
                        <div class="flex items-center justify-between mb-1">
                            <span class="font-bold text-sm truncate" :class="selectedRiderId == rf.staff_id ? 'text-white' : 'text-gray-900 dark:text-white'">
                                {{ rf.name }}
                            </span>
                            <span
                                class="text-[10px] font-extrabold px-1.5 py-0.2 rounded"
                                :class="selectedRiderId == rf.staff_id ? 'bg-white/20 text-white' : 'bg-amber-100 text-amber-800 dark:bg-amber-900/50 dark:text-amber-300'"
                            >
                                {{ rf.orders_count }} orders
                            </span>
                        </div>
                        <div class="text-lg font-black tracking-tight" :class="selectedRiderId == rf.staff_id ? 'text-white' : 'text-amber-600 dark:text-amber-400'">
                            {{ formatPrice(rf.total_float) }}
                        </div>
                        <div class="text-[11px] mt-1" :class="selectedRiderId == rf.staff_id ? 'text-amber-100' : 'text-gray-400'">
                            Click to {{ selectedRiderId == rf.staff_id ? 'clear filter' : 'filter parcels' }}
                        </div>
                    </button>
                </div>
            </div>

            <!-- ── Tab Navigation & Action Queue ──────────────────── -->
            <div class="bg-white dark:bg-gray-800 rounded-2xl border border-gray-200 dark:border-gray-700 shadow-xs mb-6 overflow-hidden">
                <div class="p-4 border-b border-gray-200 dark:border-gray-700 flex flex-col md:flex-row md:items-center justify-between gap-4 bg-gray-50/50 dark:bg-gray-850">
                    
                    <!-- Tabs -->
                    <div class="flex items-center gap-1.5 overflow-x-auto pb-1 md:pb-0 scrollbar-hide">
                        <button
                            type="button"
                            @click="switchTab('in_hand')"
                            class="px-3.5 py-2 rounded-xl text-xs font-bold transition flex items-center gap-1.5 whitespace-nowrap cursor-pointer"
                            :class="activeTab === 'in_hand'
                                ? 'bg-amber-500 text-white shadow-xs'
                                : 'text-gray-600 dark:text-gray-300 hover:bg-gray-200/60 dark:hover:bg-gray-700'"
                        >
                            <span>💰</span> Payments in Hand
                            <span class="px-1.5 py-0.2 rounded-full text-[10px]" :class="activeTab === 'in_hand' ? 'bg-white/20 text-white' : 'bg-amber-100 text-amber-800 dark:bg-amber-900 dark:text-amber-200'">
                                {{ kpis.in_hand_count }}
                            </span>
                        </button>

                        <button
                            type="button"
                            @click="switchTab('reconciled')"
                            class="px-3.5 py-2 rounded-xl text-xs font-bold transition flex items-center gap-1.5 whitespace-nowrap cursor-pointer"
                            :class="activeTab === 'reconciled'
                                ? 'bg-emerald-600 text-white shadow-xs'
                                : 'text-gray-600 dark:text-gray-300 hover:bg-gray-200/60 dark:hover:bg-gray-700'"
                        >
                            <span>🔒</span> Settled in Store
                            <span class="px-1.5 py-0.2 rounded-full text-[10px]" :class="activeTab === 'reconciled' ? 'bg-white/20 text-white' : 'bg-emerald-100 text-emerald-800 dark:bg-emerald-900 dark:text-emerald-200'">
                                {{ kpis.reconciled_count }}
                            </span>
                        </button>

                        <button
                            type="button"
                            @click="switchTab('in_transit')"
                            class="px-3.5 py-2 rounded-xl text-xs font-bold transition flex items-center gap-1.5 whitespace-nowrap cursor-pointer"
                            :class="activeTab === 'in_transit'
                                ? 'bg-blue-600 text-white shadow-xs'
                                : 'text-gray-600 dark:text-gray-300 hover:bg-gray-200/60 dark:hover:bg-gray-700'"
                        >
                            <span>🚚</span> In Transit
                            <span class="px-1.5 py-0.2 rounded-full text-[10px]" :class="activeTab === 'in_transit' ? 'bg-white/20 text-white' : 'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200'">
                                {{ kpis.in_transit_count }}
                            </span>
                        </button>

                        <button
                            type="button"
                            @click="switchTab('pending_dispatch')"
                            class="px-3.5 py-2 rounded-xl text-xs font-bold transition flex items-center gap-1.5 whitespace-nowrap cursor-pointer"
                            :class="activeTab === 'pending_dispatch'
                                ? 'bg-gray-800 text-white dark:bg-gray-200 dark:text-gray-900 shadow-xs'
                                : 'text-gray-600 dark:text-gray-300 hover:bg-gray-200/60 dark:hover:bg-gray-700'"
                        >
                            <span>📦</span> In Store / Warehouse
                            <span class="px-1.5 py-0.2 rounded-full text-[10px]" :class="activeTab === 'pending_dispatch' ? 'bg-white/20 text-white' : 'bg-gray-200 text-gray-700 dark:bg-gray-700 dark:text-gray-300'">
                                {{ kpis.pending_dispatch_count }}
                            </span>
                        </button>

                        <button
                            type="button"
                            @click="switchTab('all')"
                            class="px-3 py-2 rounded-xl text-xs font-bold transition whitespace-nowrap cursor-pointer"
                            :class="activeTab === 'all'
                                ? 'bg-gray-800 text-white dark:bg-white dark:text-gray-900 shadow-xs'
                                : 'text-gray-600 dark:text-gray-300 hover:bg-gray-200/60 dark:hover:bg-gray-700'"
                        >
                            All POD Records
                        </button>
                    </div>

                    <!-- Bulk Actions Bar -->
                    <div v-if="selectedOrderIds.length > 0" class="flex items-center gap-2">
                        <span class="text-xs font-bold text-gray-600 dark:text-gray-300">
                            {{ selectedOrderIds.length }} selected ({{ formatPrice(selectedTotalAmount) }})
                        </span>
                        
                        <!-- Bulk Remit / Settle directly into Store Custody (Cashiers/Admins only) -->
                        <button
                            v-if="activeTab === 'in_hand' && !isRider"
                            type="button"
                            @click="bulkRemit"
                            :disabled="isSubmitting"
                            class="px-3.5 py-1.5 bg-emerald-600 hover:bg-emerald-700 text-white rounded-xl text-xs font-bold shadow-xs transition cursor-pointer flex items-center gap-1.5"
                        >
                            <span>📥</span> Receive & Settle {{ selectedOrderIds.length }} Orders into Store Custody
                        </button>
                        <span v-else-if="activeTab === 'in_hand' && isRider" class="text-xs text-amber-700 dark:text-amber-400 font-bold px-3 py-1.5 bg-amber-50 dark:bg-amber-950/40 rounded-xl border border-amber-200 dark:border-amber-800">
                            ⚠️ Hand over collections to cashier
                        </span>
                    </div>
                </div>

                <!-- Secondary Filter Bar -->
                <div class="p-4 grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-3 bg-white dark:bg-gray-800 border-b border-gray-100 dark:border-gray-700">
                    <div>
                        <label class="block text-[10px] font-bold uppercase tracking-wider text-gray-500 mb-1">Search Delivery</label>
                        <input
                            v-model="search"
                            type="text"
                            placeholder="ID, tracking, customer, phone..."
                            class="w-full text-xs rounded-xl border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white py-2 px-3 focus:ring-primary"
                        />
                    </div>

                    <div v-if="canViewAll">
                        <label class="block text-[10px] font-bold uppercase tracking-wider text-gray-500 mb-1">Filter Rider / Dispatcher</label>
                        <select
                            v-model="selectedRiderId"
                            class="w-full text-xs rounded-xl border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white py-2 px-3 focus:ring-primary"
                        >
                            <option value="">All Dispatchers & Riders</option>
                            <option v-for="st in allRiders" :key="st.id" :value="st.id">{{ st.name }} ({{ st.role }})</option>
                        </select>
                    </div>

                    <div>
                        <label class="block text-[10px] font-bold uppercase tracking-wider text-gray-500 mb-1">Date Period</label>
                        <select
                            v-model="datePreset"
                            class="w-full text-xs rounded-xl border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white py-2 px-3 focus:ring-primary"
                        >
                            <option value="all">All Dates</option>
                            <option value="today">Today Only</option>
                            <option value="this_week">This Week</option>
                            <option value="this_month">This Month</option>
                        </select>
                    </div>

                    <div v-if="search || selectedRiderId || datePreset !== 'all'" class="sm:col-span-2 md:col-span-3 flex justify-end">
                        <button
                            type="button"
                            @click="clearFilters"
                            class="px-4 py-1.5 bg-gray-100 dark:bg-gray-700 hover:bg-gray-200 text-gray-600 dark:text-gray-300 rounded-xl text-xs font-bold transition cursor-pointer"
                        >
                            Clear Active Filters ✕
                        </button>
                    </div>
                </div>

                <!-- ── Orders Table ─────────────────────────────────── -->
                <div class="overflow-x-auto">
                    <table class="w-full text-left border-collapse text-xs">
                        <thead>
                            <tr class="bg-gray-50/80 dark:bg-gray-700/50 border-b border-gray-200 dark:border-gray-700 text-gray-500 uppercase font-bold text-[10px] tracking-wider">
                                <th class="py-3 px-4 w-10 text-center">
                                    <input
                                        type="checkbox"
                                        v-model="selectAll"
                                        @change="toggleSelectAll"
                                        class="rounded border-gray-300 text-primary focus:ring-primary"
                                    />
                                </th>
                                <th class="py-3 px-4">Order & Date</th>
                                <th class="py-3 px-4">Customer & Phone</th>
                                <th class="py-3 px-4">Courier & Cashier Audit</th>
                                <th class="py-3 px-4 text-right">Order Total</th>
                                <th class="py-3 px-4 text-right">Collected Amount</th>
                                <th class="py-3 px-4 text-center">Custody Status</th>
                                <th class="py-3 px-4 text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-gray-700/60">
                            <tr
                                v-for="order in orders.data"
                                :key="order.id"
                                class="hover:bg-gray-50/60 dark:hover:bg-gray-750/50 transition"
                            >
                                <!-- Checkbox -->
                                <td class="py-3 px-4 text-center">
                                    <input
                                        type="checkbox"
                                        :value="order.id"
                                        v-model="selectedOrderIds"
                                        class="rounded border-gray-300 text-primary focus:ring-primary"
                                    />
                                </td>

                                <!-- Order ID & Date -->
                                <td class="py-3 px-4">
                                    <div class="font-bold text-gray-900 dark:text-white">
                                        #{{ order.id }}
                                    </div>
                                    <div class="text-[10px] font-mono text-gray-400">
                                        {{ order.tracking_number || '—' }}
                                    </div>
                                    <div class="text-[10px] text-gray-500 mt-0.5">
                                        {{ new Date(order.created_at).toLocaleDateString() }}
                                    </div>
                                </td>

                                <!-- Customer -->
                                <td class="py-3 px-4">
                                    <div class="font-semibold text-gray-900 dark:text-white">
                                        {{ order.billing_name || order.customer?.name || 'Walk-in / Guest' }}
                                    </div>
                                    <div class="text-[10px] text-gray-500">
                                        {{ order.billing_phone || order.customer?.phone || order.guest_email || '—' }}
                                    </div>
                                </td>

                                <!-- Courier & Cashier Audit -->
                                <td class="py-3 px-4">
                                    <div>
                                        <span class="text-[9px] text-gray-400 font-bold uppercase block">Courier</span>
                                        <span v-if="order.assigned_staff" class="font-bold text-gray-800 dark:text-gray-200">
                                            {{ order.assigned_staff.name }}
                                        </span>
                                        <span v-else class="text-gray-400 text-[11px] italic">Unassigned</span>
                                    </div>
                                    <div v-if="order.reconciled_by_admin" class="mt-1 pt-1 border-t border-gray-100 dark:border-gray-700/60">
                                        <span class="text-[9px] text-emerald-600 dark:text-emerald-400 font-bold uppercase block">Audited By Cashier</span>
                                        <span class="font-semibold text-gray-700 dark:text-gray-300">
                                            {{ order.reconciled_by_admin.name }}
                                        </span>
                                        <span v-if="order.pod_reconciliation_note" class="block text-[9px] text-gray-400 italic truncate max-w-[150px]" :title="order.pod_reconciliation_note">
                                            {{ order.pod_reconciliation_note }}
                                        </span>
                                    </div>
                                </td>

                                <!-- Total Amount -->
                                <td class="py-3 px-4 text-right font-medium text-gray-700 dark:text-gray-300">
                                    {{ formatPrice(order.total_amount) }}
                                </td>

                                <!-- Collected Amount -->
                                <td class="py-3 px-4 text-right">
                                    <div class="font-black text-sm" :class="{
                                        'text-amber-600 dark:text-amber-400': order.pod_status === 'cash_collected',
                                        'text-emerald-600 dark:text-emerald-400': order.pod_status === 'reconciled',
                                        'text-gray-400': order.pod_status === 'awaiting_collection',
                                    }">
                                        {{ formatPrice(order.pod_amount_collected || order.total_amount) }}
                                    </div>
                                    <span v-if="order.pod_collected_at" class="text-[9px] text-gray-400 block">
                                        {{ new Date(order.pod_collected_at).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' }) }}
                                    </span>
                                </td>

                                <!-- Custody Status Badge -->
                                <td class="py-3 px-4 text-center">
                                    <span
                                        class="inline-block px-2.5 py-0.5 rounded-full text-[10px] font-extrabold uppercase tracking-wider"
                                        :class="{
                                            'bg-amber-100 text-amber-800 dark:bg-amber-900/50 dark:text-amber-300 border border-amber-300': order.pod_status === 'cash_collected',
                                            'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/50 dark:text-emerald-300 border border-emerald-300': order.pod_status === 'reconciled',
                                            'bg-blue-100 text-blue-800 dark:bg-blue-900/50 dark:text-blue-300 border border-blue-300': order.pod_status === 'awaiting_collection' && order.assigned_staff_id,
                                            'bg-gray-100 text-gray-700 dark:bg-gray-800 dark:text-gray-300 border border-gray-300': !order.assigned_staff_id || order.pod_status === 'pending_dispatch',
                                        }"
                                    >
                                        {{ order.pod_status === 'cash_collected' ? '💰 With Rider' : (order.pod_status === 'reconciled' ? '🔒 Settled in Store' : (order.assigned_staff_id ? '🚚 In Transit' : '📦 In Store')) }}
                                    </span>
                                </td>

                                <!-- Actions -->
                                <td class="py-3 px-4 text-right">
                                    <div class="flex items-center justify-end gap-1.5 flex-wrap">
                                        
                                        <!-- Step 1: Collect -->
                                        <button
                                            v-if="order.pod_status === 'awaiting_collection'"
                                            type="button"
                                            @click="openCollectModal(order)"
                                            class="px-2.5 py-1 bg-amber-500 hover:bg-amber-600 text-white rounded-lg text-[11px] font-bold shadow-2xs transition cursor-pointer"
                                            title="Record money received from customer"
                                        >
                                            💵 Record Payment
                                        </button>

                                        <!-- Step 2: Receive & Settle directly (Auto-Reconcile) — ONLY FOR CASHIERS/ADMINS -->
                                        <button
                                            v-if="!isRider && (order.pod_status === 'cash_collected' || order.pod_status === 'remitted_to_store')"
                                            type="button"
                                            @click="markRemitted(order)"
                                            class="px-2.5 py-1 bg-emerald-600 hover:bg-emerald-700 text-white rounded-lg text-[11px] font-bold shadow-2xs transition cursor-pointer"
                                            title="Receive & reconcile directly into store custody"
                                        >
                                            <span>📥</span> Receive & Settle
                                        </button>

                                        <span v-else-if="isRider && (order.pod_status === 'cash_collected' || order.pod_status === 'remitted_to_store')" class="text-[10px] text-amber-700 dark:text-amber-300 font-bold px-2 py-0.5 bg-amber-50 dark:bg-amber-950/40 rounded border border-amber-200 dark:border-amber-800">
                                            Hand to Cashier
                                        </span>

                                        <span v-else-if="order.pod_status === 'reconciled'" class="text-[10px] text-emerald-600 dark:text-emerald-400 font-bold px-1.5 py-0.5 bg-emerald-50 dark:bg-emerald-950/30 rounded border border-emerald-200 dark:border-emerald-800">
                                            ✓ Reconciled
                                        </span>

                                        <Link
                                            :href="route('admin.orders.show', order.id)"
                                            class="p-1 text-gray-500 hover:text-primary transition"
                                            title="View Order Details"
                                        >
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                                        </Link>
                                    </div>
                                </td>
                            </tr>

                            <tr v-if="!orders.data || orders.data.length === 0">
                                <td colspan="8" class="py-12 text-center text-gray-400">
                                    <div class="text-3xl mb-2">📦</div>
                                    <p class="font-bold">No POD records found in this view.</p>
                                    <p class="text-xs text-gray-500 mt-1">Try switching tabs or resetting your active search filters.</p>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div v-if="orders.data && orders.data.length > 0" class="p-4 border-t border-gray-100 dark:border-gray-700">
                    <Pagination :paginator="orders" />
                </div>
            </div>

        </div>

        <!-- ── Modal: Record Collected Payment ────────────────────── -->
        <div v-if="showCollectModal" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/60 backdrop-blur-xs">
            <div class="bg-white dark:bg-gray-800 rounded-2xl max-w-md w-full p-6 shadow-2xl border border-gray-200 dark:border-gray-700">
                <div class="flex items-center gap-2.5 mb-2">
                    <span class="w-8 h-8 rounded-xl bg-amber-100 dark:bg-amber-900/50 text-amber-600 flex items-center justify-center font-bold text-base">
                        💵
                    </span>
                    <h3 class="text-lg font-bold text-gray-900 dark:text-white">
                        Record Doorstep Payment
                    </h3>
                </div>
                <p class="text-xs text-gray-500 mb-4">
                    Confirm that payment has been collected from the customer for Order <strong>#{{ activeOrder?.id }}</strong>.
                </p>

                <div class="space-y-4 mb-6">
                    <div>
                        <label class="block text-xs font-bold uppercase tracking-wider text-gray-600 dark:text-gray-400 mb-1">
                            Amount Collected (₦)
                        </label>
                        <input
                            v-model="collectedAmount"
                            type="number"
                            step="0.01"
                            :min="activeOrder?.total_amount"
                            class="w-full text-base font-bold rounded-xl border-gray-300 dark:border-gray-600 dark:bg-gray-700 p-2.5 text-gray-900 dark:text-white focus:ring-primary"
                            placeholder="Enter amount"
                        />
                        <p class="text-[11px] mt-1" :class="Number(collectedAmount) < Number(activeOrder?.total_amount) ? 'text-red-500 font-bold' : 'text-gray-400'">
                            Expected full amount: {{ formatPrice(activeOrder?.total_amount) }}
                            <span v-if="Number(collectedAmount) < Number(activeOrder?.total_amount)" class="block text-red-500 font-bold">
                                ⚠️ Cannot collect less than order total!
                            </span>
                        </p>
                    </div>

                    <div>
                        <label class="block text-xs font-bold uppercase tracking-wider text-gray-600 dark:text-gray-400 mb-1">
                            Payment Mode Received
                        </label>
                        <select
                            v-model="collectionChannel"
                            class="w-full text-xs font-medium rounded-xl border-gray-300 dark:border-gray-600 dark:bg-gray-700 p-2.5"
                        >
                            <option value="cash">💵 Physical Cash</option>
                            <option value="bank_transfer">🏦 Instant Bank Transfer</option>
                            <option value="pos_terminal">💳 Handheld POS Terminal</option>
                        </select>
                    </div>
                </div>

                <div class="flex items-center justify-end gap-2">
                    <button
                        type="button"
                        @click="showCollectModal = false"
                        class="px-4 py-2 text-xs font-semibold text-gray-600 hover:bg-gray-100 dark:text-gray-300 dark:hover:bg-gray-700 rounded-xl"
                    >
                        Cancel
                    </button>
                    <button
                        type="button"
                        @click="submitCollect"
                        :disabled="isSubmitting"
                        class="px-4 py-2 text-xs font-bold text-white bg-amber-600 hover:bg-amber-700 rounded-xl shadow-xs"
                    >
                        Confirm Collection
                    </button>
                </div>
            </div>
        </div>

        <!-- ── Modal: Reconcile Single Order in Drawer ─────────────── -->
        <div v-if="showReconcileModal" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/60 backdrop-blur-xs">
            <div class="bg-white dark:bg-gray-800 rounded-2xl max-w-md w-full p-6 shadow-2xl border border-gray-200 dark:border-gray-700">
                <div class="flex items-center gap-2.5 mb-2">
                    <span class="w-8 h-8 rounded-xl bg-emerald-100 dark:bg-emerald-900/50 text-emerald-600 flex items-center justify-center font-bold text-base">
                        🔒
                    </span>
                    <h3 class="text-lg font-bold text-gray-900 dark:text-white">
                        Verify & Reconcile in Cash Drawer
                    </h3>
                </div>
                <p class="text-xs text-gray-500 mb-4">
                    Confirm that physical cash of <strong>{{ formatPrice(activeOrder?.pod_amount_collected || activeOrder?.total_amount) }}</strong> has been verified, counted, and deposited into the store cash drawer.
                </p>

                <div class="mb-4">
                    <label class="block text-xs font-bold uppercase tracking-wider text-gray-600 dark:text-gray-400 mb-1">
                        Audit & Reconciliation Note
                    </label>
                    <textarea
                        v-model="reconcileNote"
                        rows="2"
                        class="w-full text-xs rounded-xl border-gray-300 dark:border-gray-600 dark:bg-gray-700 p-2.5 text-gray-900 dark:text-white"
                        placeholder="e.g. Received envelope from rider, verified against POS drawer"
                    ></textarea>
                </div>

                <div class="flex items-center justify-end gap-2">
                    <button
                        type="button"
                        @click="showReconcileModal = false"
                        class="px-4 py-2 text-xs font-semibold text-gray-600 hover:bg-gray-100 dark:text-gray-300 dark:hover:bg-gray-700 rounded-xl"
                    >
                        Cancel
                    </button>
                    <button
                        type="button"
                        @click="submitReconcile"
                        :disabled="isSubmitting"
                        class="px-4 py-2 text-xs font-bold text-white bg-emerald-600 hover:bg-emerald-700 rounded-xl shadow-xs"
                    >
                        Confirm Till Deposit
                    </button>
                </div>
            </div>
        </div>

        <!-- ── Modal: Bulk Reconcile Multiple Orders ──────────────── -->
        <div v-if="showBulkReconcileModal" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/60 backdrop-blur-xs">
            <div class="bg-white dark:bg-gray-800 rounded-2xl max-w-md w-full p-6 shadow-2xl border border-gray-200 dark:border-gray-700">
                <div class="flex items-center gap-2.5 mb-2">
                    <span class="w-8 h-8 rounded-xl bg-emerald-100 dark:bg-emerald-900/50 text-emerald-600 flex items-center justify-center font-bold text-base">
                        🔒
                    </span>
                    <h3 class="text-lg font-bold text-gray-900 dark:text-white">
                        Bulk Reconcile {{ selectedOrderIds.length }} Orders
                    </h3>
                </div>
                <p class="text-xs text-gray-500 mb-4">
                    Confirm that total physical cash of <strong class="text-emerald-600 text-sm">{{ formatPrice(selectedTotalAmount) }}</strong> across {{ selectedOrderIds.length }} orders has been audited and deposited into the store cash drawer.
                </p>

                <div class="mb-4">
                    <label class="block text-xs font-bold uppercase tracking-wider text-gray-600 dark:text-gray-400 mb-1">
                        Batch Shift Audit Note
                    </label>
                    <textarea
                        v-model="reconcileNote"
                        rows="2"
                        class="w-full text-xs rounded-xl border-gray-300 dark:border-gray-600 dark:bg-gray-700 p-2.5 text-gray-900 dark:text-white"
                        placeholder="e.g. End of shift batch deposit reconciled"
                    ></textarea>
                </div>

                <div class="flex items-center justify-end gap-2">
                    <button
                        type="button"
                        @click="showBulkReconcileModal = false"
                        class="px-4 py-2 text-xs font-semibold text-gray-600 hover:bg-gray-100 dark:text-gray-300 dark:hover:bg-gray-700 rounded-xl"
                    >
                        Cancel
                    </button>
                    <button
                        type="button"
                        @click="submitBulkReconcile"
                        :disabled="isSubmitting"
                        class="px-4 py-2 text-xs font-bold text-white bg-emerald-600 hover:bg-emerald-700 rounded-xl shadow-xs"
                    >
                        Reconcile Batch ({{ formatPrice(selectedTotalAmount) }})
                    </button>
                </div>
            </div>
        </div>
    </AdminLayout>
</template>

