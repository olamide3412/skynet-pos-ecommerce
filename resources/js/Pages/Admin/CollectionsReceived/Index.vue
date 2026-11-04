<script setup>
import { Head, Link, router } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import Pagination from '@/Components/Pagination.vue';
import { ref, watch } from 'vue';
import debounce from 'lodash/debounce';

const props = defineProps({
    canViewAll:       Boolean,
    filters:          Object,
    kpis:             Object,
    cashierBreakdown: Array,
    allCashiers:      Array,
    allRiders:        Array,
    orders:           Object,
});

// Filters
const preset = ref(props.filters.preset || 'today');
const startDate = ref(props.filters.start_date || '');
const endDate = ref(props.filters.end_date || '');
const selectedCashierId = ref(props.filters.cashier_id || '');
const selectedRiderId = ref(props.filters.rider_id || '');
const selectedChannel = ref(props.filters.channel || 'all');
const search = ref(props.filters.search || '');

const formatPrice = (val) => {
    if (val === null || val === undefined || isNaN(val)) return '₦0.00';
    return '₦' + Number(val).toLocaleString('en-NG', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
};

const applyFilters = debounce(() => {
    router.get(route('admin.collections-received.index'), {
        preset:     preset.value,
        start_date: preset.value === 'custom' ? startDate.value : undefined,
        end_date:   preset.value === 'custom' ? endDate.value : undefined,
        cashier_id: selectedCashierId.value || undefined,
        rider_id:   selectedRiderId.value || undefined,
        channel:    selectedChannel.value !== 'all' ? selectedChannel.value : undefined,
        search:     search.value || undefined,
    }, { preserveState: true, replace: true });
}, 300);

const selectPreset = (newPreset) => {
    preset.value = newPreset;
    if (newPreset !== 'custom') {
        startDate.value = '';
        endDate.value = '';
    }
    applyFilters();
};

const filterByCashier = (cashierId) => {
    selectedCashierId.value = selectedCashierId.value === cashierId ? '' : cashierId;
    applyFilters();
};

watch([selectedCashierId, selectedRiderId, selectedChannel, search], applyFilters);

const clearFilters = () => {
    preset.value = 'today';
    startDate.value = '';
    endDate.value = '';
    selectedCashierId.value = '';
    selectedRiderId.value = '';
    selectedChannel.value = 'all';
    search.value = '';
    applyFilters();
};

const exportCsv = () => {
    const params = new URLSearchParams({
        preset:     preset.value,
        start_date: startDate.value || '',
        end_date:   endDate.value || '',
        cashier_id: selectedCashierId.value || '',
        rider_id:   selectedRiderId.value || '',
        channel:    selectedChannel.value || 'all',
        search:     search.value || '',
    });
    window.location.href = route('admin.collections-received.export') + '?' + params.toString();
};
</script>

<template>
    <Head title="Collections Received - Audit & Settlement" />

    <AdminLayout>
        <div class="p-4 sm:p-6 lg:p-8 max-w-7xl mx-auto space-y-6">

            <!-- ── Top Header ─────────────────────────────────────── -->
            <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
                <div>
                    <h1 class="text-2xl sm:text-3xl font-black text-gray-900 dark:text-white flex items-center gap-2.5">
                        <span>🏪</span> Collections Received
                    </h1>
                    <p class="text-xs sm:text-sm text-gray-500 dark:text-gray-400 mt-1">
                        Audit trail of doorstep collections (Cash, Transfer, POS) verified and accepted into store till/accounts by cashiers and administrators.
                    </p>
                </div>

                <div class="flex items-center gap-2 flex-wrap">
                    <button
                        type="button"
                        @click="exportCsv"
                        class="px-3.5 py-2 bg-emerald-600 hover:bg-emerald-700 text-white rounded-xl text-xs font-bold transition flex items-center gap-1.5 shadow-xs cursor-pointer"
                    >
                        <span>📥</span> Export Audit CSV
                    </button>

                    <Link
                        :href="route('admin.pod-cash.index')"
                        class="px-3.5 py-2 bg-amber-500 hover:bg-amber-600 text-white rounded-xl text-xs font-bold transition flex items-center gap-1.5 shadow-xs"
                    >
                        <span>🛵</span> POD Dispatch & Floats →
                    </Link>
                </div>
            </div>

            <!-- ── Date Range Presets Bar ────────────────────────── -->
            <div class="p-4 bg-white dark:bg-gray-800 rounded-2xl border border-gray-200 dark:border-gray-700 shadow-xs space-y-3">
                <div class="flex items-center justify-between flex-wrap gap-2">
                    <span class="text-xs font-bold uppercase tracking-wider text-gray-500">Audit Period:</span>
                    <div class="flex items-center gap-1.5 flex-wrap">
                        <button
                            v-for="p in [
                                { id: 'today', label: 'Today' },
                                { id: 'yesterday', label: 'Yesterday' },
                                { id: 'this_week', label: 'This Week' },
                                { id: 'this_month', label: 'This Month' },
                                { id: 'last_month', label: 'Last Month' },
                                { id: 'custom', label: 'Custom Range' },
                                { id: 'all', label: 'All Dates' },
                            ]"
                            :key="p.id"
                            type="button"
                            @click="selectPreset(p.id)"
                            class="px-3 py-1.5 rounded-xl text-xs font-bold transition cursor-pointer"
                            :class="preset === p.id
                                ? 'bg-emerald-600 text-white shadow-xs'
                                : 'bg-gray-100 dark:bg-gray-750 text-gray-600 dark:text-gray-300 hover:bg-gray-200 dark:hover:bg-gray-700'"
                        >
                            {{ p.label }}
                        </button>
                    </div>
                </div>

                <!-- Custom Date Inputs -->
                <div v-if="preset === 'custom'" class="pt-2 border-t border-gray-100 dark:border-gray-700 grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-3">
                    <div>
                        <label class="block text-[10px] font-bold uppercase tracking-wider text-gray-500 mb-1">Start Date</label>
                        <input
                            v-model="startDate"
                            @change="applyFilters"
                            type="date"
                            class="w-full text-xs rounded-xl border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white py-2 px-3 focus:ring-emerald-500"
                        />
                    </div>
                    <div>
                        <label class="block text-[10px] font-bold uppercase tracking-wider text-gray-500 mb-1">End Date</label>
                        <input
                            v-model="endDate"
                            @change="applyFilters"
                            type="date"
                            class="w-full text-xs rounded-xl border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white py-2 px-3 focus:ring-emerald-500"
                        />
                    </div>
                    <div class="flex items-end">
                        <button
                            type="button"
                            @click="applyFilters"
                            class="w-full py-2 bg-emerald-600 hover:bg-emerald-700 text-white rounded-xl text-xs font-bold transition cursor-pointer"
                        >
                            Apply Custom Dates
                        </button>
                    </div>
                </div>
            </div>

            <!-- ── Top 4 KPI Metrics Cards ────────────────────────── -->
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3">
                
                <!-- Total Received -->
                <div class="bg-white dark:bg-gray-800 p-5 rounded-2xl border-2 border-emerald-500 dark:border-emerald-600 shadow-xs bg-emerald-50/20">
                    <div class="flex items-center justify-between mb-1.5">
                        <span class="text-xs font-bold uppercase tracking-wider text-emerald-800 dark:text-emerald-300">Total Received</span>
                        <span class="w-8 h-8 rounded-xl bg-emerald-100 dark:bg-emerald-900/50 text-emerald-600 flex items-center justify-center font-bold text-sm">💰</span>
                    </div>
                    <div class="text-2xl font-black text-emerald-600 dark:text-emerald-400">
                        {{ formatPrice(kpis.total_received) }}
                    </div>
                    <p class="text-[11px] text-emerald-700 dark:text-emerald-300 mt-1 font-semibold">
                        <strong>{{ kpis.orders_count }}</strong> orders verified & deposited
                    </p>
                </div>

                <!-- Cash Drawer Deposits -->
                <div class="bg-white dark:bg-gray-800 p-5 rounded-2xl border border-gray-200 dark:border-gray-700 shadow-xs">
                    <div class="flex items-center justify-between mb-1.5">
                        <span class="text-xs font-bold uppercase tracking-wider text-gray-600 dark:text-gray-300">Cash in Drawer</span>
                        <span class="w-8 h-8 rounded-xl bg-green-100 dark:bg-green-900/40 text-green-600 flex items-center justify-center font-bold text-sm">💵</span>
                    </div>
                    <div class="text-2xl font-black text-gray-900 dark:text-white">
                        {{ formatPrice(kpis.cash_amount) }}
                    </div>
                    <p class="text-[11px] text-gray-500 dark:text-gray-400 mt-1">
                        Physical banknotes deposited into till
                    </p>
                </div>

                <!-- Bank Transfers Confirmed -->
                <div class="bg-white dark:bg-gray-800 p-5 rounded-2xl border border-gray-200 dark:border-gray-700 shadow-xs">
                    <div class="flex items-center justify-between mb-1.5">
                        <span class="text-xs font-bold uppercase tracking-wider text-gray-600 dark:text-gray-300">Bank Transfers</span>
                        <span class="w-8 h-8 rounded-xl bg-blue-100 dark:bg-blue-900/40 text-blue-600 flex items-center justify-center font-bold text-sm">🏦</span>
                    </div>
                    <div class="text-2xl font-black text-blue-600 dark:text-blue-400">
                        {{ formatPrice(kpis.transfer_amount) }}
                    </div>
                    <p class="text-[11px] text-gray-500 dark:text-gray-400 mt-1">
                        Instant transfers credited to bank
                    </p>
                </div>

                <!-- POS Terminal Settlements -->
                <div class="bg-white dark:bg-gray-800 p-5 rounded-2xl border border-gray-200 dark:border-gray-700 shadow-xs">
                    <div class="flex items-center justify-between mb-1.5">
                        <span class="text-xs font-bold uppercase tracking-wider text-gray-600 dark:text-gray-300">POS Card Swipes</span>
                        <span class="w-8 h-8 rounded-xl bg-purple-100 dark:bg-purple-900/40 text-purple-600 flex items-center justify-center font-bold text-sm">💳</span>
                    </div>
                    <div class="text-2xl font-black text-purple-600 dark:text-purple-400">
                        {{ formatPrice(kpis.pos_amount) }}
                    </div>
                    <p class="text-[11px] text-gray-500 dark:text-gray-400 mt-1">
                        Doorstep POS card terminal settlements
                    </p>
                </div>

            </div>

            <!-- ── Cashiers & Admins Audit Breakdown ─────────────── -->
            <div v-if="canViewAll && cashierBreakdown && cashierBreakdown.length > 0" class="p-5 bg-white dark:bg-gray-800 rounded-2xl border border-emerald-300 dark:border-emerald-700/60 shadow-xs bg-emerald-50/20">
                <div class="flex items-center justify-between mb-3">
                    <div>
                        <h2 class="text-sm font-bold uppercase tracking-wider text-emerald-900 dark:text-emerald-200 flex items-center gap-2">
                            <span>🏪</span> Collections Claimed by Cashier / Administrator
                        </h2>
                        <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">
                            Click any staff card to filter orders claimed and reconciled under their profile for this date period.
                        </p>
                    </div>
                    <span class="text-xs font-bold text-emerald-700 dark:text-emerald-300 bg-emerald-100 dark:bg-emerald-900/40 px-2.5 py-1 rounded-full">
                        {{ cashierBreakdown.length }} Cashier/Admin Auditor(s)
                    </span>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-3 pt-1">
                    <button
                        v-for="ca in cashierBreakdown"
                        :key="ca.id"
                        type="button"
                        @click="filterByCashier(ca.id)"
                        class="p-3.5 rounded-xl border text-left transition transform hover:-translate-y-0.5 cursor-pointer"
                        :class="selectedCashierId == ca.id
                            ? 'bg-emerald-600 text-white border-emerald-700 shadow-md ring-2 ring-emerald-400'
                            : 'bg-white dark:bg-gray-750 border-gray-200 dark:border-gray-700 hover:border-emerald-400 shadow-xs'"
                    >
                        <div class="flex items-center justify-between mb-1">
                            <span class="font-bold text-sm truncate" :class="selectedCashierId == ca.id ? 'text-white' : 'text-gray-900 dark:text-white'">
                                {{ ca.name }}
                            </span>
                            <span
                                class="text-[10px] font-extrabold px-1.5 py-0.5 rounded capitalize"
                                :class="selectedCashierId == ca.id ? 'bg-white/20 text-white' : 'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/50 dark:text-emerald-300'"
                            >
                                {{ ca.role }}
                            </span>
                        </div>
                        <div class="text-lg font-black tracking-tight" :class="selectedCashierId == ca.id ? 'text-white' : 'text-emerald-600 dark:text-emerald-400'">
                            {{ formatPrice(ca.total_amount) }}
                        </div>
                        <div class="text-[11px] mt-0.5" :class="selectedCashierId == ca.id ? 'text-emerald-100' : 'text-gray-400'">
                            {{ ca.orders_count }} orders reconciled
                        </div>
                    </button>
                </div>
            </div>

            <!-- ── Main Audit Table & Filter Controls ────────────── -->
            <div class="bg-white dark:bg-gray-800 rounded-2xl border border-gray-200 dark:border-gray-700 shadow-xs overflow-hidden">
                
                <!-- Filters Bar -->
                <div class="p-4 grid grid-cols-1 sm:grid-cols-2 md:grid-cols-4 gap-3 bg-gray-50/50 dark:bg-gray-850 border-b border-gray-200 dark:border-gray-700">
                    <div>
                        <label class="block text-[10px] font-bold uppercase tracking-wider text-gray-500 mb-1">Search Audit</label>
                        <input
                            v-model="search"
                            type="text"
                            placeholder="Order #, tracking, customer, note..."
                            class="w-full text-xs rounded-xl border-gray-300 dark:border-gray-600 dark:bg-gray-750 dark:text-white py-2 px-3 focus:ring-emerald-500"
                        />
                    </div>

                    <div v-if="canViewAll">
                        <label class="block text-[10px] font-bold uppercase tracking-wider text-gray-500 mb-1">Audited By (Cashier/Admin)</label>
                        <select
                            v-model="selectedCashierId"
                            class="w-full text-xs rounded-xl border-gray-300 dark:border-gray-600 dark:bg-gray-750 dark:text-white py-2 px-3 focus:ring-emerald-500"
                        >
                            <option value="">All Cashiers & Admins</option>
                            <option v-for="st in allCashiers" :key="st.id" :value="st.id">{{ st.name }} ({{ st.role }})</option>
                        </select>
                    </div>

                    <div>
                        <label class="block text-[10px] font-bold uppercase tracking-wider text-gray-500 mb-1">Delivered By (Courier / Rider)</label>
                        <select
                            v-model="selectedRiderId"
                            class="w-full text-xs rounded-xl border-gray-300 dark:border-gray-600 dark:bg-gray-750 dark:text-white py-2 px-3 focus:ring-emerald-500"
                        >
                            <option value="">All Couriers & Staff</option>
                            <option v-for="st in allRiders" :key="st.id" :value="st.id">{{ st.name }} ({{ st.role }})</option>
                        </select>
                    </div>

                    <div>
                        <label class="block text-[10px] font-bold uppercase tracking-wider text-gray-500 mb-1">Payment Channel</label>
                        <select
                            v-model="selectedChannel"
                            class="w-full text-xs rounded-xl border-gray-300 dark:border-gray-600 dark:bg-gray-750 dark:text-white py-2 px-3 focus:ring-emerald-500"
                        >
                            <option value="all">All Payment Channels</option>
                            <option value="cash">💵 Physical Cash</option>
                            <option value="bank_transfer">🏦 Bank Transfer</option>
                            <option value="pos_terminal">💳 Handheld POS</option>
                        </select>
                    </div>
                </div>

                <div v-if="search || selectedCashierId || selectedRiderId || selectedChannel !== 'all' || preset !== 'today'" class="px-4 py-2 bg-emerald-50/50 dark:bg-emerald-950/20 border-b border-emerald-100 dark:border-emerald-800 flex items-center justify-between text-xs">
                    <span class="text-emerald-800 dark:text-emerald-300">
                        Filtering <strong>{{ orders.total }}</strong> reconciled orders matching criteria.
                    </span>
                    <button
                        type="button"
                        @click="clearFilters"
                        class="text-emerald-700 dark:text-emerald-300 font-bold hover:underline cursor-pointer"
                    >
                        Reset All Filters ✕
                    </button>
                </div>

                <!-- Table -->
                <div class="overflow-x-auto">
                    <table class="w-full text-left border-collapse text-xs">
                        <thead>
                            <tr class="bg-gray-50/80 dark:bg-gray-700/50 border-b border-gray-200 dark:border-gray-700 text-gray-500 uppercase font-bold text-[10px] tracking-wider">
                                <th class="py-3 px-4">Audited Date & Time</th>
                                <th class="py-3 px-4">Order Details</th>
                                <th class="py-3 px-4">Customer</th>
                                <th class="py-3 px-4">Audited By (Cashier/Admin)</th>
                                <th class="py-3 px-4">Courier (Rider)</th>
                                <th class="py-3 px-4 text-right">Amount Received</th>
                                <th class="py-3 px-4">Audit Note & Channel</th>
                                <th class="py-3 px-4 text-right">Action</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-gray-700/60">
                            <tr
                                v-for="order in orders.data"
                                :key="order.id"
                                class="hover:bg-gray-50/60 dark:hover:bg-gray-750/50 transition"
                            >
                                <!-- Audited Timestamp -->
                                <td class="py-3 px-4">
                                    <div class="font-bold text-gray-900 dark:text-white">
                                        {{ order.pod_reconciled_at ? new Date(order.pod_reconciled_at).toLocaleDateString() : '—' }}
                                    </div>
                                    <div class="text-[10px] text-gray-400">
                                        {{ order.pod_reconciled_at ? new Date(order.pod_reconciled_at).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' }) : '' }}
                                    </div>
                                </td>

                                <!-- Order # -->
                                <td class="py-3 px-4">
                                    <div class="font-bold text-gray-900 dark:text-white">
                                        #{{ order.id }}
                                    </div>
                                    <div class="text-[10px] font-mono text-gray-400">
                                        {{ order.tracking_number || '—' }}
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

                                <!-- Received & Audited By -->
                                <td class="py-3 px-4">
                                    <div v-if="order.reconciled_by_admin" class="flex items-center gap-1.5">
                                        <span class="w-6 h-6 rounded-lg bg-emerald-100 dark:bg-emerald-900/50 text-emerald-700 dark:text-emerald-300 font-black text-[10px] flex items-center justify-center">
                                            ✓
                                        </span>
                                        <div>
                                            <div class="font-bold text-gray-900 dark:text-white">
                                                {{ order.reconciled_by_admin.name }}
                                            </div>
                                            <div class="text-[9px] text-emerald-600 dark:text-emerald-400 font-semibold capitalize">
                                                {{ order.reconciled_by_admin.role || 'Cashier' }}
                                            </div>
                                        </div>
                                    </div>
                                    <span v-else class="text-gray-400 italic text-[11px]">Store Till</span>
                                </td>

                                <!-- Courier -->
                                <td class="py-3 px-4">
                                    <div v-if="order.assigned_staff" class="font-medium text-gray-800 dark:text-gray-200">
                                        {{ order.assigned_staff.name }}
                                    </div>
                                    <span v-else class="text-gray-400 text-[11px] italic">Unassigned</span>
                                </td>

                                <!-- Amount Received -->
                                <td class="py-3 px-4 text-right">
                                    <div class="font-black text-sm text-emerald-600 dark:text-emerald-400">
                                        {{ formatPrice(order.pod_amount_collected || order.total_amount) }}
                                    </div>
                                    <span class="text-[9px] font-bold text-emerald-700 dark:text-emerald-300 bg-emerald-100 dark:bg-emerald-900/40 px-1.5 py-0.2 rounded uppercase">
                                        Settled
                                    </span>
                                </td>

                                <!-- Audit Note & Channel -->
                                <td class="py-3 px-4 max-w-xs">
                                    <p class="text-[11px] text-gray-700 dark:text-gray-300 truncate" :title="order.pod_reconciliation_note">
                                        {{ order.pod_reconciliation_note || 'Direct cash drawer settle' }}
                                    </p>
                                </td>

                                <!-- Action -->
                                <td class="py-3 px-4 text-right">
                                    <Link
                                        :href="route('admin.orders.show', order.id)"
                                        class="px-2.5 py-1 bg-gray-100 dark:bg-gray-700 hover:bg-gray-200 text-gray-700 dark:text-gray-200 rounded-lg text-[11px] font-bold transition inline-flex items-center gap-1"
                                    >
                                        Inspect →
                                    </Link>
                                </td>
                            </tr>

                            <tr v-if="!orders.data || orders.data.length === 0">
                                <td colspan="8" class="py-12 text-center text-gray-400">
                                    <div class="text-3xl mb-2">🏪</div>
                                    <p class="font-bold">No collections received matching this date range.</p>
                                    <p class="text-xs text-gray-500 mt-1">Try selecting a different date preset (e.g. This Month or All Dates) or clearing filters.</p>
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
    </AdminLayout>
</template>

