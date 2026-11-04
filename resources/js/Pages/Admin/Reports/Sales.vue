<script setup>
import { Head, Link, router } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import Pagination from '@/Components/Pagination.vue';
import { ref, computed, watch } from 'vue';
import debounce from 'lodash/debounce';

const props = defineProps({
    isAdmin: {
        type: Boolean,
        default: false,
    },
    orders: {
        type: Object,
        required: true,
    },
    allStaff: {
        type: Array,
        default: () => [],
    },
    filters: {
        type: Object,
        default: () => ({}),
    },
    summary: {
        type: Object,
        default: () => ({}),
    },
    staffPerformance: {
        type: Array,
        default: () => [],
    },
});

// Filters state
const preset = ref(props.filters.preset || 'today');
const startDate = ref(props.filters.start_date || '');
const endDate = ref(props.filters.end_date || '');
const staffId = ref(props.filters.staff_id || '');
const paymentMethod = ref(props.filters.payment_method || '');
const podStatus = ref(props.filters.pod_status || '');
const status = ref(props.filters.status || '');
const search = ref(props.filters.search || '');

// Modal state
const showAssignModal = ref(false);
const showReconcileModal = ref(false);
const selectedOrder = ref(null);
const assignStaffId = ref('');
const reconcileNote = ref('');
const isSubmitting = ref(false);

const applyFilters = debounce(() => {
    router.get(route('admin.reports.sales'), {
        preset: preset.value,
        start_date: preset.value === 'custom' ? startDate.value : undefined,
        end_date: preset.value === 'custom' ? endDate.value : undefined,
        staff_id: staffId.value || undefined,
        payment_method: paymentMethod.value || undefined,
        pod_status: podStatus.value || undefined,
        status: status.value || undefined,
        search: search.value || undefined,
    }, { preserveState: true, replace: true });
}, 300);

watch([preset, staffId, paymentMethod, podStatus, status], applyFilters);
watch(search, applyFilters);

const onPresetChange = (newPreset) => {
    preset.value = newPreset;
    if (newPreset !== 'custom') {
        applyFilters();
    }
};

const applyCustomDates = () => {
    if (startDate.value && endDate.value) {
        preset.value = 'custom';
        applyFilters();
    }
};

const clearFilters = () => {
    preset.value = 'today';
    startDate.value = '';
    endDate.value = '';
    staffId.value = '';
    paymentMethod.value = '';
    podStatus.value = '';
    status.value = '';
    search.value = '';
    router.get(route('admin.reports.sales'));
};

const exportCsv = () => {
    const params = new URLSearchParams({
        preset: preset.value,
        start_date: startDate.value || '',
        end_date: endDate.value || '',
        staff_id: staffId.value || '',
        payment_method: paymentMethod.value || '',
        pod_status: podStatus.value || '',
        status: status.value || '',
    });
    window.location.href = route('admin.reports.sales.export') + '?' + params.toString();
};

const formatPrice = (amount) => {
    return '₦' + Number(amount || 0).toLocaleString('en-NG', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    });
};

// Actions
const openAssignModal = (order) => {
    selectedOrder.value = order;
    assignStaffId.value = order.assigned_staff_id || '';
    showAssignModal.value = true;
};

const submitAssign = () => {
    if (!selectedOrder.value || !assignStaffId.value) return;
    isSubmitting.value = true;
    router.post(route('admin.orders.assign-staff', selectedOrder.value.id), {
        assigned_staff_id: assignStaffId.value,
    }, {
        preserveScroll: true,
        onFinish: () => {
            isSubmitting.value = false;
            showAssignModal.value = false;
        }
    });
};

const markCashCollected = (order) => {
    if (order.status === 'cancelled') return;
    if (!confirm(`Confirm that cash payment of ${formatPrice(order.total_amount)} has been collected from customer for Order #${order.id}?`)) return;
    router.post(route('admin.orders.mark-cash-collected', order.id), {}, { preserveScroll: true });
};

const markCashRemitted = (order) => {
    if (!confirm(`Mark that collected cash for Order #${order.id} has been physically remitted to store cashier/desk?`)) return;
    router.post(route('admin.orders.mark-cash-remitted', order.id), {}, { preserveScroll: true });
};

const openReconcileModal = (order) => {
    selectedOrder.value = order;
    reconcileNote.value = '';
    showReconcileModal.value = true;
};

const submitReconcile = () => {
    if (!selectedOrder.value) return;
    isSubmitting.value = true;
    router.post(route('admin.orders.reconcile-pod', selectedOrder.value.id), {
        reconciliation_note: reconcileNote.value,
    }, {
        preserveScroll: true,
        onFinish: () => {
            isSubmitting.value = false;
            showReconcileModal.value = false;
        }
    });
};
</script>

<template>
    <Head title="Sales & POD Cash Report" />
    <AdminLayout>
        <div class="container mx-auto px-4 py-8 max-w-7xl">

            <!-- ── Header ─────────────────────────────────────────── -->
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-6">
                <div>
                    <h1 class="text-2xl sm:text-3xl font-extrabold text-gray-900 dark:text-white flex items-center gap-2.5">
                        <span>📊</span> Sales & Revenue Performance Report
                    </h1>
                    <p class="text-xs sm:text-sm text-gray-500 dark:text-gray-400 mt-1">
                        Track sales across all channels, query staff attribution, evaluate coupons and discounts, and audit order totals.
                    </p>
                </div>

                <div class="flex items-center gap-2 flex-wrap">
                    <Link
                        :href="route('admin.pod-cash.index')"
                        class="px-4 py-2 bg-amber-500 hover:bg-amber-600 text-white rounded-xl text-xs font-bold shadow-xs flex items-center gap-1.5 transition"
                    >
                        <span>🛵</span> POD Cash Station
                    </Link>
                    <button
                        type="button"
                        @click="exportCsv"
                        class="px-4 py-2 bg-emerald-600 hover:bg-emerald-700 text-white rounded-xl text-xs font-bold shadow-xs flex items-center gap-2 transition cursor-pointer"
                    >
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                        Export CSV
                    </button>
                </div>
            </div>

            <!-- ── Top KPI Cards ─────────────────────────────────── -->
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
                
                <!-- Gross Sales -->
                <div class="bg-white dark:bg-gray-800 p-5 rounded-2xl border border-gray-200 dark:border-gray-700 shadow-xs">
                    <div class="flex items-center justify-between mb-2">
                        <span class="text-xs font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400">Gross Sales</span>
                        <span class="w-8 h-8 rounded-xl bg-primary/10 text-primary flex items-center justify-center font-bold text-sm">💰</span>
                    </div>
                    <div class="text-2xl font-black text-gray-900 dark:text-white">
                        {{ formatPrice(summary.gross_sales) }}
                    </div>
                    <p class="text-xs text-gray-500 dark:text-gray-400 mt-2">
                        <strong class="text-gray-900 dark:text-white">{{ summary.total_orders }}</strong> orders in query
                    </p>
                </div>

                <!-- POD Payments in Hand (Rider Custody / Awaiting Remit) -->
                <div class="bg-white dark:bg-gray-800 p-5 rounded-2xl border-2 border-amber-400 dark:border-amber-600 shadow-xs bg-amber-50/30">
                    <div class="flex items-center justify-between mb-2">
                        <span class="text-xs font-bold uppercase tracking-wider text-amber-800 dark:text-amber-300">Payments in Hand (To Remit)</span>
                        <span class="w-8 h-8 rounded-xl bg-amber-100 dark:bg-amber-900/50 text-amber-600 flex items-center justify-center font-bold text-sm">💼</span>
                    </div>
                    <div class="text-2xl font-black text-amber-600 dark:text-amber-400">
                        {{ formatPrice(summary.pod_cash_in_hand) }}
                    </div>
                    <p class="text-xs text-amber-800 dark:text-amber-300 mt-2 font-medium">
                        Collected by rider (Cash/Transfer/POS), pending store remittance
                    </p>
                </div>

                <!-- POD Remitted to Store -->
                <div class="bg-white dark:bg-gray-800 p-5 rounded-2xl border border-purple-300 dark:border-purple-700/60 shadow-xs bg-purple-50/20">
                    <div class="flex items-center justify-between mb-2">
                        <span class="text-xs font-bold uppercase tracking-wider text-purple-700 dark:text-purple-300">Remitted to Store</span>
                        <span class="w-8 h-8 rounded-xl bg-purple-100 dark:bg-purple-900/40 text-purple-600 flex items-center justify-center font-bold text-sm">📥</span>
                    </div>
                    <div class="text-2xl font-black text-purple-700 dark:text-purple-400">
                        {{ formatPrice(summary.pod_remitted_cash) }}
                    </div>
                    <p class="text-xs text-purple-700 dark:text-purple-300 mt-2 font-medium">
                        Handed to cashier, pending till audit
                    </p>
                </div>

                <!-- POD Reconciled Cash -->
                <div class="bg-white dark:bg-gray-800 p-5 rounded-2xl border border-emerald-300 dark:border-emerald-700/60 shadow-xs bg-emerald-50/20">
                    <div class="flex items-center justify-between mb-2">
                        <span class="text-xs font-bold uppercase tracking-wider text-emerald-700 dark:text-emerald-400">Reconciled in Drawer</span>
                        <span class="w-8 h-8 rounded-xl bg-emerald-100 dark:bg-emerald-900/40 text-emerald-600 flex items-center justify-center font-bold text-sm">🔒</span>
                    </div>
                    <div class="text-2xl font-black text-emerald-600 dark:text-emerald-400">
                        {{ formatPrice(summary.pod_reconciled_cash) }}
                    </div>
                    <p class="text-xs text-emerald-700 dark:text-emerald-300 mt-2 font-medium">
                        Audited and locked into cash drawer
                    </p>
                </div>

            </div>

            <!-- ── Staff Performance Table (Admins Only) ─────────────── -->
            <div v-if="isAdmin && staffPerformance.length > 0" class="mb-8 bg-white dark:bg-gray-800 rounded-2xl border border-gray-200 dark:border-gray-700 p-5 shadow-xs">
                <div class="flex items-center justify-between mb-4">
                    <div>
                        <h2 class="text-sm font-bold text-gray-900 dark:text-white uppercase tracking-wider flex items-center gap-2">
                            <span>👥</span> Staff Sales & Cash Float Summary
                        </h2>
                        <p class="text-xs text-gray-400 mt-0.5">Performance breakdown for orders attributed to each staff member for this period.</p>
                    </div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-3.5">
                    <div
                        v-for="st in staffPerformance"
                        :key="st.id"
                        class="p-4 rounded-xl border border-gray-200 dark:border-gray-700 bg-gray-50/50 dark:bg-gray-700/30 hover:border-primary transition cursor-pointer"
                        @click="staffId = st.id; applyFilters()"
                    >
                        <div class="flex items-center justify-between mb-2">
                            <span class="font-bold text-xs text-gray-900 dark:text-white">{{ st.name }}</span>
                            <span class="text-[10px] uppercase font-bold px-2 py-0.5 rounded-full bg-gray-200 dark:bg-gray-600 text-gray-700 dark:text-gray-300">
                                {{ st.role }}
                            </span>
                        </div>
                        <div class="text-lg font-black text-primary">
                            {{ formatPrice(st.total_sales) }}
                        </div>
                        <div class="flex items-center justify-between text-xs text-gray-500 mt-2 pt-2 border-t border-gray-200 dark:border-gray-700">
                            <span>{{ st.orders_count }} orders</span>
                            <span v-if="st.floating_pod > 0" class="text-amber-600 dark:text-amber-400 font-bold">
                                🛵 {{ formatPrice(st.floating_pod) }}
                            </span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- ── Filter & Search Control Panel ─────────────────────── -->
            <div class="bg-white dark:bg-gray-800 rounded-2xl border border-gray-200 dark:border-gray-700 p-5 mb-6 shadow-xs">
                
                <!-- Date Presets Row -->
                <div class="flex flex-wrap items-center justify-between gap-3 pb-4 border-b border-gray-200 dark:border-gray-700 mb-4">
                    <div class="flex flex-wrap items-center gap-1.5">
                        <button
                            type="button"
                            @click="onPresetChange('today')"
                            class="px-3.5 py-1.5 rounded-lg text-xs font-bold transition cursor-pointer"
                            :class="preset === 'today' ? 'bg-primary text-white shadow-xs' : 'bg-gray-100 dark:bg-gray-700 text-gray-600 dark:text-gray-300 hover:bg-gray-200'"
                        >
                            Today
                        </button>
                        <button
                            type="button"
                            @click="onPresetChange('yesterday')"
                            class="px-3.5 py-1.5 rounded-lg text-xs font-bold transition cursor-pointer"
                            :class="preset === 'yesterday' ? 'bg-primary text-white shadow-xs' : 'bg-gray-100 dark:bg-gray-700 text-gray-600 dark:text-gray-300 hover:bg-gray-200'"
                        >
                            Yesterday
                        </button>
                        <button
                            type="button"
                            @click="onPresetChange('this_week')"
                            class="px-3.5 py-1.5 rounded-lg text-xs font-bold transition cursor-pointer"
                            :class="preset === 'this_week' ? 'bg-primary text-white shadow-xs' : 'bg-gray-100 dark:bg-gray-700 text-gray-600 dark:text-gray-300 hover:bg-gray-200'"
                        >
                            This Week
                        </button>
                        <button
                            type="button"
                            @click="onPresetChange('this_month')"
                            class="px-3.5 py-1.5 rounded-lg text-xs font-bold transition cursor-pointer"
                            :class="preset === 'this_month' ? 'bg-primary text-white shadow-xs' : 'bg-gray-100 dark:bg-gray-700 text-gray-600 dark:text-gray-300 hover:bg-gray-200'"
                        >
                            This Month
                        </button>
                        <button
                            type="button"
                            @click="preset = 'custom'"
                            class="px-3.5 py-1.5 rounded-lg text-xs font-bold transition cursor-pointer"
                            :class="preset === 'custom' ? 'bg-primary text-white shadow-xs' : 'bg-gray-100 dark:bg-gray-700 text-gray-600 dark:text-gray-300 hover:bg-gray-200'"
                        >
                            Custom Range
                        </button>
                    </div>

                    <div v-if="preset === 'custom'" class="flex items-center gap-2">
                        <input
                            v-model="startDate"
                            type="date"
                            class="text-xs rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white py-1.5 px-2.5"
                        />
                        <span class="text-xs text-gray-400">to</span>
                        <input
                            v-model="endDate"
                            type="date"
                            class="text-xs rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white py-1.5 px-2.5"
                        />
                        <button
                            type="button"
                            @click="applyCustomDates"
                            class="px-3 py-1.5 bg-primary text-white text-xs font-bold rounded-lg hover:bg-primary-dark transition cursor-pointer"
                        >
                            Apply
                        </button>
                    </div>
                </div>

                <!-- Secondary Filters Grid -->
                <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-5 gap-3">
                    
                    <!-- Search Input -->
                    <div>
                        <label class="block text-[10px] font-bold uppercase tracking-wider text-gray-500 mb-1">Search</label>
                        <input
                            v-model="search"
                            type="text"
                            placeholder="ID, tracking, customer..."
                            class="w-full text-xs rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white py-2 px-3 focus:ring-primary"
                        />
                    </div>

                    <!-- Staff Filter (Admins Only) -->
                    <div v-if="isAdmin">
                        <label class="block text-[10px] font-bold uppercase tracking-wider text-gray-500 mb-1">Filter Staff</label>
                        <select
                            v-model="staffId"
                            class="w-full text-xs rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white py-2 px-3 focus:ring-primary"
                        >
                            <option value="">All Staff Members</option>
                            <option v-for="st in allStaff" :key="st.id" :value="st.id">
                                {{ st.name }} ({{ st.role }})
                            </option>
                        </select>
                    </div>

                    <!-- Payment Method -->
                    <div>
                        <label class="block text-[10px] font-bold uppercase tracking-wider text-gray-500 mb-1">Payment Method</label>
                        <select
                            v-model="paymentMethod"
                            class="w-full text-xs rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white py-2 px-3 focus:ring-primary"
                        >
                            <option value="">All Methods</option>
                            <option value="pod">Payment on Delivery (POD)</option>
                            <option value="paystack">Paystack</option>
                            <option value="bank_transfer">Bank Transfer</option>
                        </select>
                    </div>

                    <!-- POD Cash Status -->
                    <div>
                        <label class="block text-[10px] font-bold uppercase tracking-wider text-amber-600 dark:text-amber-400 mb-1">POD Cash Status</label>
                        <select
                            v-model="podStatus"
                            class="w-full text-xs rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white py-2 px-3 focus:ring-primary"
                        >
                            <option value="">All POD States</option>
                            <option value="awaiting_collection">Awaiting Collection (Rider en route)</option>
                            <option value="cash_collected">Cash Collected (With Rider)</option>
                            <option value="remitted_to_store">Remitted to Store</option>
                            <option value="reconciled">Reconciled & Closed</option>
                        </select>
                    </div>

                    <!-- Order Status -->
                    <div class="flex items-end gap-2">
                        <div class="flex-1">
                            <label class="block text-[10px] font-bold uppercase tracking-wider text-gray-500 mb-1">Order Status</label>
                            <select
                                v-model="status"
                                class="w-full text-xs rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white py-2 px-3 focus:ring-primary"
                            >
                                <option value="">All Statuses</option>
                                <option value="pending">Pending</option>
                                <option value="processing">Processing</option>
                                <option value="shipped">Shipped</option>
                                <option value="completed">Completed</option>
                                <option value="cancelled">Cancelled</option>
                            </select>
                        </div>
                        <button
                            type="button"
                            @click="clearFilters"
                            class="px-3 py-2 bg-gray-100 dark:bg-gray-700 hover:bg-gray-200 text-gray-600 dark:text-gray-300 rounded-lg text-xs font-semibold transition cursor-pointer"
                            title="Reset all filters"
                        >
                            Reset
                        </button>
                    </div>

                </div>
            </div>

            <!-- ── Orders Table ────────────────────────────────────── -->
            <div class="bg-white dark:bg-gray-800 rounded-2xl border border-gray-200 dark:border-gray-700 shadow-xs overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="w-full text-left border-collapse text-xs">
                        <thead>
                            <tr class="bg-gray-50/80 dark:bg-gray-700/50 border-b border-gray-200 dark:border-gray-700 text-gray-600 dark:text-gray-300 uppercase font-bold text-[10px] tracking-wider">
                                <th class="py-3 px-4">Order ID & Date</th>
                                <th class="py-3 px-4">Customer</th>
                                <th class="py-3 px-4">Total Amount</th>
                                <th class="py-3 px-4 text-center">Payment / POD Status</th>
                                <th class="py-3 px-4 text-center">Assigned Rider / Staff</th>
                                <th class="py-3 px-4 text-center">Order Status</th>
                                <th class="py-3 px-4 text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-gray-700/60">
                            <tr
                                v-for="order in orders.data"
                                :key="order.id"
                                class="hover:bg-gray-50/60 dark:hover:bg-gray-700/30 transition"
                            >
                                <!-- Order ID & Date -->
                                <td class="py-3 px-4">
                                    <div class="font-bold text-gray-900 dark:text-white">
                                        #{{ order.id }}
                                    </div>
                                    <div class="text-[10px] text-gray-400">
                                        {{ order.tracking_number }}
                                    </div>
                                    <div class="text-[10px] text-gray-500 mt-0.5">
                                        {{ new Date(order.created_at).toLocaleDateString() }}
                                    </div>
                                </td>

                                <!-- Customer -->
                                <td class="py-3 px-4">
                                    <div class="font-semibold text-gray-900 dark:text-white">
                                        {{ order.billing_name || order.customer?.name || 'Guest Checkout' }}
                                    </div>
                                    <div class="text-[10px] text-gray-400">
                                        {{ order.billing_phone || order.customer?.phone || order.guest_email || 'No phone' }}
                                    </div>
                                </td>

                                <!-- Total Amount -->
                                <td class="py-3 px-4">
                                    <div class="font-black text-gray-900 dark:text-white text-sm">
                                        {{ formatPrice(order.total_amount) }}
                                    </div>
                                    <div v-if="order.discount_amount > 0" class="text-[10px] text-emerald-600 font-semibold">
                                        Saved {{ formatPrice(order.discount_amount) }}
                                    </div>
                                </td>

                                <!-- Payment / POD Status -->
                                <td class="py-3 px-4 text-center">
                                    <div class="inline-flex flex-col items-center gap-1">
                                        <span class="px-2 py-0.5 rounded-full text-[10px] font-bold uppercase tracking-wider"
                                            :class="order.payment_method === 'pod' ? 'bg-amber-100 text-amber-800 dark:bg-amber-900/40 dark:text-amber-300' : 'bg-blue-100 text-blue-800 dark:bg-blue-900/40 dark:text-blue-300'"
                                        >
                                            {{ order.payment_method === 'pod' ? '🛵 POD' : (order.payment_method || 'Online') }}
                                        </span>

                                        <!-- Specific POD Status Badge -->
                                        <div v-if="order.payment_method === 'pod'" class="flex flex-col items-center gap-0.5">
                                            <span
                                                class="text-[9px] font-extrabold uppercase px-1.5 py-0.5 rounded"
                                                :class="{
                                                    'bg-yellow-100 text-yellow-800 dark:bg-yellow-900/50 dark:text-yellow-300': order.pod_status === 'awaiting_collection',
                                                    'bg-amber-100 text-amber-800 dark:bg-amber-900/50 dark:text-amber-300': order.pod_status === 'cash_collected',
                                                    'bg-purple-100 text-purple-800 dark:bg-purple-900/50 dark:text-purple-300': order.pod_status === 'remitted_to_store',
                                                    'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/50 dark:text-emerald-300': order.pod_status === 'reconciled',
                                                }"
                                            >
                                                {{ order.pod_status === 'cash_collected' ? '💵 In Hand: ' + formatPrice(order.pod_amount_collected || order.total_amount) : (order.pod_status === 'remitted_to_store' ? '📥 Remitted: ' + formatPrice(order.pod_amount_collected || order.total_amount) : (order.pod_status === 'reconciled' ? '✓ Reconciled' : '⏳ Awaiting Collection')) }}
                                            </span>
                                        </div>
                                    </div>
                                </td>

                                <!-- Assigned Rider / Staff -->
                                <td class="py-3 px-4 text-center">
                                    <div v-if="order.assigned_staff" class="font-bold text-gray-800 dark:text-gray-200">
                                        {{ order.assigned_staff.name }}
                                    </div>
                                    <button
                                        v-else-if="isAdmin"
                                        type="button"
                                        @click="openAssignModal(order)"
                                        class="px-2 py-1 rounded-lg border border-dashed border-gray-300 dark:border-gray-600 hover:border-primary text-gray-500 hover:text-primary text-[10px] font-semibold transition cursor-pointer"
                                    >
                                        + Assign Staff
                                    </button>
                                    <span v-else class="text-gray-400 italic text-[11px]">Unassigned</span>
                                </td>

                                <!-- Order Status -->
                                <td class="py-3 px-4 text-center">
                                    <span
                                        class="px-2 py-0.5 rounded-full text-[10px] font-bold uppercase tracking-wider"
                                        :class="{
                                            'bg-gray-100 text-gray-700 dark:bg-gray-700 dark:text-gray-300': order.status === 'pending',
                                            'bg-blue-100 text-blue-700 dark:bg-blue-900/40 dark:text-blue-300': order.status === 'processing',
                                            'bg-amber-100 text-amber-700 dark:bg-amber-900/40 dark:text-amber-300': order.status === 'shipped',
                                            'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-300': order.status === 'completed',
                                            'bg-rose-100 text-rose-700 dark:bg-rose-900/40 dark:text-rose-300': order.status === 'cancelled',
                                        }"
                                    >
                                        {{ order.status }}
                                    </span>
                                </td>

                                <!-- Actions -->
                                <td class="py-3 px-4 text-right whitespace-nowrap">
                                    <div class="inline-flex items-center gap-1.5">
                                        
                                        <!-- Step 1: Rider marks Cash Collected -->
                                        <button
                                            v-if="order.payment_method === 'pod' && order.pod_status === 'awaiting_collection'"
                                            :disabled="order.status === 'cancelled'"
                                            type="button"
                                            @click="markCashCollected(order)"
                                            class="px-2 py-1 rounded-lg bg-orange-50 hover:bg-orange-600 hover:text-white text-orange-700 border border-orange-300 text-[10px] font-bold transition cursor-pointer disabled:opacity-40 disabled:cursor-not-allowed disabled:hover:bg-orange-50 disabled:hover:text-orange-700"
                                            :title="order.status === 'cancelled' ? 'Order is cancelled' : 'Record cash received from shopper'"
                                        >
                                            💵 Collected
                                        </button>

                                        <!-- Step 2: Rider marks Cash Remitted -->
                                        <button
                                            v-if="order.payment_method === 'pod' && order.pod_status === 'cash_collected'"
                                            type="button"
                                            @click="markCashRemitted(order)"
                                            class="px-2 py-1 rounded-lg bg-purple-50 hover:bg-purple-600 hover:text-white text-purple-700 border border-purple-300 text-[10px] font-bold transition cursor-pointer"
                                            title="Mark cash turned over to store"
                                        >
                                            📥 Remit Cash
                                        </button>

                                        <!-- Step 3: Admin Reconcile & Verify Cash -->
                                        <button
                                            v-if="isAdmin && order.payment_method === 'pod' && (order.pod_status === 'remitted_to_store' || order.pod_status === 'cash_collected')"
                                            type="button"
                                            @click="openReconcileModal(order)"
                                            class="px-2 py-1 rounded-lg bg-emerald-600 hover:bg-emerald-700 text-white text-[10px] font-bold shadow-xs transition cursor-pointer"
                                            title="Verify physical cash and reconcile"
                                        >
                                            ✓ Reconcile
                                        </button>

                                        <!-- View Order Link -->
                                        <Link
                                            :href="route('admin.orders.show', order.id)"
                                            class="p-1.5 rounded-lg bg-gray-100 dark:bg-gray-700 hover:bg-primary hover:text-white text-gray-600 dark:text-gray-300 transition"
                                            title="View Order Details"
                                        >
                                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                                        </Link>

                                    </div>
                                </td>
                            </tr>

                            <tr v-if="!orders.data || orders.data.length === 0">
                                <td colspan="7" class="text-center py-12 text-gray-500">
                                    <div class="flex flex-col items-center justify-center">
                                        <span class="text-3xl mb-2">📋</span>
                                        <p class="font-bold text-gray-700 dark:text-gray-300">No sales transactions found</p>
                                        <p class="text-xs text-gray-400 mt-1">Try widening your date range or clearing filters.</p>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Pagination -->
                <div v-if="orders && (orders.last_page > 1 || (orders.links && orders.links.length > 3))" class="p-4 border-t border-gray-200 dark:border-gray-700">
                    <Pagination :paginator="orders" :links="orders.links" />
                </div>
            </div>

        </div>

        <!-- ── Modal: Assign Staff / Rider ───────────────────────────── -->
        <div v-if="showAssignModal" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/60 backdrop-blur-xs">
            <div class="bg-white dark:bg-gray-800 rounded-2xl max-w-sm w-full p-5 shadow-2xl border border-gray-200 dark:border-gray-700 animate-fade-in">
                <h3 class="text-base font-bold text-gray-900 dark:text-white mb-1">
                    Assign Staff / Courier Rider
                </h3>
                <p class="text-xs text-gray-500 mb-4">
                    Select the staff member or rider responsible for Order #{{ selectedOrder?.id }}.
                </p>

                <div class="mb-4">
                    <label class="block text-xs font-bold uppercase text-gray-600 dark:text-gray-400 mb-1">Select Staff Member</label>
                    <select
                        v-model="assignStaffId"
                        class="w-full text-xs rounded-xl border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white py-2 focus:ring-primary"
                    >
                        <option value="">-- Choose Staff --</option>
                        <option v-for="st in allStaff" :key="st.id" :value="st.id">
                            {{ st.name }} ({{ st.role }})
                        </option>
                    </select>
                </div>

                <div class="flex items-center justify-end gap-2">
                    <button
                        type="button"
                        @click="showAssignModal = false"
                        class="px-3.5 py-2 text-xs font-semibold text-gray-600 hover:bg-gray-100 rounded-xl"
                    >
                        Cancel
                    </button>
                    <button
                        type="button"
                        @click="submitAssign"
                        :disabled="!assignStaffId || isSubmitting"
                        class="px-4 py-2 text-xs font-bold text-white bg-primary hover:bg-primary-dark rounded-xl disabled:opacity-50"
                    >
                        Save Assignment
                    </button>
                </div>
            </div>
        </div>

        <!-- ── Modal: Reconcile POD Cash ─────────────────────────────── -->
        <div v-if="showReconcileModal" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/60 backdrop-blur-xs">
            <div class="bg-white dark:bg-gray-800 rounded-2xl max-w-md w-full p-5 shadow-2xl border border-gray-200 dark:border-gray-700 animate-fade-in">
                <div class="flex items-center gap-2 mb-2">
                    <span class="w-8 h-8 rounded-xl bg-emerald-100 text-emerald-700 flex items-center justify-center font-bold text-sm">✓</span>
                    <div>
                        <h3 class="text-base font-bold text-gray-900 dark:text-white">
                            Verify & Reconcile POD Cash
                        </h3>
                        <p class="text-xs text-gray-500">Order #{{ selectedOrder?.id }}</p>
                    </div>
                </div>

                <div class="p-3 rounded-xl bg-emerald-50 dark:bg-emerald-950/30 border border-emerald-200 dark:border-emerald-800 my-3">
                    <div class="text-xs text-emerald-800 dark:text-emerald-300">
                        Total Amount Collected to Confirm:
                    </div>
                    <div class="text-2xl font-black text-emerald-700 dark:text-emerald-400 mt-0.5">
                        {{ formatPrice(selectedOrder?.total_amount) }}
                    </div>
                </div>

                <div class="mb-4">
                    <label class="block text-xs font-bold uppercase text-gray-600 dark:text-gray-400 mb-1">
                        Reconciliation Note (Optional)
                    </label>
                    <textarea
                        v-model="reconcileNote"
                        rows="2"
                        placeholder="e.g. Cash received by cashier John, receipt #4829 deposited into store till"
                        class="w-full text-xs rounded-xl border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white p-2.5 focus:ring-emerald-500"
                    ></textarea>
                </div>

                <div class="flex items-center justify-end gap-2">
                    <button
                        type="button"
                        @click="showReconcileModal = false"
                        class="px-3.5 py-2 text-xs font-semibold text-gray-600 hover:bg-gray-100 rounded-xl"
                    >
                        Cancel
                    </button>
                    <button
                        type="button"
                        @click="submitReconcile"
                        :disabled="isSubmitting"
                        class="px-4 py-2 text-xs font-bold text-white bg-emerald-600 hover:bg-emerald-700 rounded-xl shadow-xs disabled:opacity-50"
                    >
                        Confirm Cash in Drawer
                    </button>
                </div>
            </div>
        </div>

    </AdminLayout>
</template>

