<script setup>
import { Head, Link, router } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import Pagination from '@/Components/Pagination.vue';
import { ref, computed, watch } from 'vue';
import debounce from 'lodash/debounce';

const props = defineProps({
    orders: Object,
    podCustody: {
        type: Object,
        default: () => ({ cash_in_hand: 0, count: 0 }),
    },
    statusCounts: {
        type: Object,
        default: () => ({
            all: 0,
            pending: 0,
            processing: 0,
            paid: 0,
            shipped: 0,
            completed: 0,
            cancelled: 0,
        }),
    },
    filters: {
        type: Object,
        default: () => ({}),
    }
});

const search = ref(props.filters.search || '');
const status = ref(props.filters.status || '');
const startDate = ref(props.filters.start_date || '');
const endDate = ref(props.filters.end_date || '');

const statusOptions = [
    {
        key: '',
        label: 'All Orders',
        countKey: 'all',
        badgeClass: 'bg-slate-700 text-white dark:bg-slate-200 dark:text-slate-900',
        pillClass: 'border-slate-200 dark:border-slate-700 text-slate-700 dark:text-slate-200 bg-slate-50/80 dark:bg-slate-800/60',
        activeClass: 'bg-slate-900 text-white dark:bg-white dark:text-slate-900 ring-2 ring-slate-400/40 border-slate-900 dark:border-white shadow-sm',
    },
    {
        key: 'pending',
        label: 'Pending',
        countKey: 'pending',
        badgeClass: 'bg-amber-500 text-white',
        pillClass: 'border-amber-200 dark:border-amber-800/80 text-amber-800 dark:text-amber-300 bg-amber-50/80 dark:bg-amber-950/40',
        activeClass: 'bg-amber-500 text-white border-amber-500 ring-2 ring-amber-400/50 shadow-sm',
    },
    {
        key: 'processing',
        label: 'Processing',
        countKey: 'processing',
        badgeClass: 'bg-blue-600 text-white',
        pillClass: 'border-blue-200 dark:border-blue-800/80 text-blue-800 dark:text-blue-300 bg-blue-50/80 dark:bg-blue-950/40',
        activeClass: 'bg-blue-600 text-white border-blue-600 ring-2 ring-blue-400/50 shadow-sm',
    },
    {
        key: 'paid',
        label: 'Paid',
        countKey: 'paid',
        badgeClass: 'bg-teal-600 text-white',
        pillClass: 'border-teal-200 dark:border-teal-800/80 text-teal-800 dark:text-teal-300 bg-teal-50/80 dark:bg-teal-950/40',
        activeClass: 'bg-teal-600 text-white border-teal-600 ring-2 ring-teal-400/50 shadow-sm',
    },
    {
        key: 'shipped',
        label: 'Shipped',
        countKey: 'shipped',
        badgeClass: 'bg-purple-600 text-white',
        pillClass: 'border-purple-200 dark:border-purple-800/80 text-purple-800 dark:text-purple-300 bg-purple-50/80 dark:bg-purple-950/40',
        activeClass: 'bg-purple-600 text-white border-purple-600 ring-2 ring-purple-400/50 shadow-sm',
    },
    {
        key: 'completed',
        label: 'Completed',
        countKey: 'completed',
        badgeClass: 'bg-emerald-600 text-white',
        pillClass: 'border-emerald-200 dark:border-emerald-800/80 text-emerald-800 dark:text-emerald-300 bg-emerald-50/80 dark:bg-emerald-950/40',
        activeClass: 'bg-emerald-600 text-white border-emerald-600 ring-2 ring-emerald-400/50 shadow-sm',
    },
    {
        key: 'cancelled',
        label: 'Cancelled',
        countKey: 'cancelled',
        badgeClass: 'bg-rose-600 text-white',
        pillClass: 'border-rose-200 dark:border-rose-800/80 text-rose-800 dark:text-rose-300 bg-rose-50/80 dark:bg-rose-950/40',
        activeClass: 'bg-rose-600 text-white border-rose-600 ring-2 ring-rose-400/50 shadow-sm',
    },
];

const visibleStatusOptions = computed(() => {
    return statusOptions.filter(st => (props.statusCounts?.[st.countKey] || 0) > 0);
});

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
        <div class="container mx-auto px-3 sm:px-4 py-4 sm:py-8 text-black dark:text-white max-w-7xl">
            <h1 class="text-2xl sm:text-3xl font-bold mb-3 sm:mb-4">Manage Orders</h1>

            <!-- Status Counts Quick Navigation (Circular colored labels) -->
            <div v-if="visibleStatusOptions.length > 0" class="flex items-center gap-2 overflow-x-auto pb-2 mb-6 scrollbar-thin">
                <button
                    v-for="st in visibleStatusOptions"
                    :key="st.key"
                    @click="status = st.key"
                    type="button"
                    class="inline-flex items-center gap-2 px-3.5 py-1.5 rounded-full text-xs font-bold transition-all border shadow-2xs shrink-0 cursor-pointer"
                    :class="[
                        status === st.key
                            ? st.activeClass
                            : (st.pillClass + ' hover:opacity-90')
                    ]"
                >
                    <span class="w-2 h-2 rounded-full" :class="status === st.key ? 'bg-white' : st.badgeClass"></span>
                    <span>{{ st.label }}</span>
                    <span
                        class="inline-flex items-center justify-center min-w-[20px] h-5 px-1.5 text-[11px] font-black rounded-full transition-colors"
                        :class="status === st.key ? 'bg-white/20 text-white' : st.badgeClass"
                    >
                        {{ statusCounts[st.countKey] ?? 0 }}
                    </span>
                </button>
            </div>

            <!-- Floating Payments in Custody Alert -->
            <div
                v-if="podCustody?.cash_in_hand > 0"
                class="mb-6 p-4 rounded-2xl bg-amber-500/10 border-2 border-amber-400 dark:border-amber-600 flex flex-col sm:flex-row sm:items-center justify-between gap-4"
            >
                <div class="flex items-center gap-3">
                    <span class="w-10 h-10 rounded-xl bg-amber-500 text-white flex items-center justify-center font-bold text-lg flex-shrink-0">
                        💼
                    </span>
                    <div>
                        <h3 class="text-sm font-bold text-amber-900 dark:text-amber-200">
                            Payments in Hand (Awaiting Remittance): ₦{{ Number(podCustody.cash_in_hand).toLocaleString('en-NG', { minimumFractionDigits: 2 }) }}
                        </h3>
                        <p class="text-xs text-amber-800/80 dark:text-amber-300/80">
                            You have collected payment for <strong>{{ podCustody.count }}</strong> order(s). Hand these collections (Cash / Transfer / POS) over to the store cashier to clear your float.
                        </p>
                    </div>
                </div>
                <Link
                    :href="route('admin.pod-cash.index', { tab: 'in_hand' })"
                    class="px-4 py-2 bg-amber-600 hover:bg-amber-700 text-white text-xs font-bold rounded-xl shadow-xs self-start sm:self-auto transition"
                >
                    View in POD Station →
                </Link>
            </div>
            
            <!-- Filters Bar -->
            <div class="bg-white dark:bg-gray-800 rounded-xl shadow-xs border dark:border-gray-700 p-3.5 sm:p-4 mb-6 flex flex-col md:flex-row md:items-end gap-3 sm:gap-4">
                <div class="flex-1 w-full">
                    <label class="block text-xs font-bold text-gray-500 dark:text-gray-400 uppercase tracking-wide mb-1">Search</label>
                    <input v-model="search" type="text" placeholder="ID, tracking, email, coupon..." 
                        class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white px-3 py-2 text-sm focus:ring-primary focus:border-primary" />
                </div>
                
                <div class="w-full md:w-auto">
                    <label class="block text-xs font-bold text-gray-500 dark:text-gray-400 uppercase tracking-wide mb-1">Status</label>
                    <select v-model="status" 
                        class="w-full md:w-44 rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white px-3 py-2 text-sm focus:ring-primary focus:border-primary">
                        <option value="">All Statuses</option>
                        <option value="pending">Pending</option>
                        <option value="processing">Processing</option>
                        <option value="paid">Paid</option>
                        <option value="shipped">Shipped</option>
                        <option value="completed">Completed</option>
                        <option value="cancelled">Cancelled</option>
                    </select>
                </div>

                <div class="grid grid-cols-2 md:flex gap-2 sm:gap-3 w-full md:w-auto">
                    <div>
                        <label class="block text-xs font-bold text-gray-500 dark:text-gray-400 uppercase tracking-wide mb-1">From Date</label>
                        <input v-model="startDate" type="date" 
                            class="w-full md:w-auto rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white px-2.5 sm:px-3 py-2 text-xs sm:text-sm focus:ring-primary focus:border-primary" />
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-gray-500 dark:text-gray-400 uppercase tracking-wide mb-1">To Date</label>
                        <input v-model="endDate" type="date" 
                            class="w-full md:w-auto rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white px-2.5 sm:px-3 py-2 text-xs sm:text-sm focus:ring-primary focus:border-primary" />
                    </div>
                </div>

                <div v-if="search || status || startDate || endDate" class="w-full md:w-auto">
                    <button @click="clearFilters" class="w-full md:w-auto px-4 py-2 text-sm font-semibold text-gray-600 dark:text-gray-300 bg-gray-100 dark:bg-gray-700 rounded-md hover:bg-gray-200 dark:hover:bg-gray-600 transition">
                        Clear
                    </button>
                </div>
            </div>
            
            <div class="bg-white dark:bg-gray-800 rounded-xl shadow-xs overflow-hidden border dark:border-gray-700">
                <!-- Desktop Table View (md and above) -->
                <div class="hidden md:block overflow-x-auto">
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
                                    <span class="font-mono text-xs bg-primary-soft text-primary px-2 py-1 rounded border border-primary-soft">
                                        {{ order.tracking_number || '—' }}
                                    </span>
                                </td>
                                <td class="p-4 font-semibold">{{ order.customer ? order.customer.name : order.guest_email }}</td>
                                <td class="p-4">
                                    <div class="font-bold text-primary">
                                        ₦{{ parseFloat(order.total_amount).toLocaleString('en-NG', { minimumFractionDigits: 2 }) }}
                                    </div>
                                    <div v-if="order.coupon_code" class="mt-1">
                                        <span class="inline-flex items-center gap-1 px-1.5 py-0.5 rounded text-[10px] font-mono font-bold bg-emerald-100 text-emerald-800 dark:bg-emerald-950/60 dark:text-emerald-300 border border-emerald-300 dark:border-emerald-800" :title="'Coupon: ' + order.coupon_code + (parseFloat(order.discount_amount) > 0 ? ' (-₦' + parseFloat(order.discount_amount).toLocaleString() + ')' : '')">
                                            <span>🎟️</span>
                                            <span>{{ order.coupon_code }}</span>
                                            <span v-if="parseFloat(order.discount_amount) > 0" class="font-sans font-normal opacity-85">(-₦{{ parseFloat(order.discount_amount).toLocaleString() }})</span>
                                        </span>
                                    </div>
                                    <div v-if="parseFloat(order.shipping_fee) > 0" class="mt-1">
                                        <span class="inline-flex items-center gap-1 px-1.5 py-0.5 rounded text-[10px] font-semibold bg-blue-50 text-blue-700 dark:bg-blue-950/60 dark:text-blue-300 border border-blue-200 dark:border-blue-800" :title="'Shipping Fee: ₦' + parseFloat(order.shipping_fee).toLocaleString()">
                                            <span>🚚</span>
                                            <span>Ship: ₦{{ parseFloat(order.shipping_fee).toLocaleString() }}</span>
                                        </span>
                                    </div>
                                    <div v-if="order.payment_method === 'pod' && order.pod_status && order.pod_status !== 'none'" class="mt-1">
                                        <span
                                            class="inline-block px-1.5 py-0.5 rounded text-[10px] font-extrabold uppercase"
                                            :class="{
                                                'bg-amber-100 text-amber-800 dark:bg-amber-900/50 dark:text-amber-300': order.pod_status === 'cash_collected',
                                                'bg-purple-100 text-purple-800 dark:bg-purple-900/50 dark:text-purple-300': order.pod_status === 'remitted_to_store',
                                                'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/50 dark:text-emerald-300': order.pod_status === 'reconciled',
                                                'bg-yellow-100 text-yellow-800 dark:bg-yellow-900/50 dark:text-yellow-300': order.pod_status === 'awaiting_collection',
                                            }"
                                        >
                                            {{ order.pod_status === 'cash_collected' ? '💵 In Hand: ₦' + Number(order.pod_amount_collected || order.total_amount).toLocaleString() : (order.pod_status === 'remitted_to_store' ? '📥 Remitted' : (order.pod_status === 'reconciled' ? '✓ Reconciled' : '⏳ Awaiting Collect')) }}
                                        </span>
                                    </div>
                                </td>
                                <td class="p-4 text-center">
                                    <span class="px-3 py-1 rounded text-xs font-bold uppercase tracking-wider" 
                                          :class="{
                                            'bg-amber-100 text-amber-800 dark:bg-amber-950/60 dark:text-amber-300': order.status === 'pending',
                                            'bg-blue-100 text-blue-800 dark:bg-blue-950/60 dark:text-blue-300': order.status === 'processing',
                                            'bg-teal-100 text-teal-800 dark:bg-teal-950/60 dark:text-teal-300': order.status === 'paid',
                                            'bg-purple-100 text-purple-800 dark:bg-purple-950/60 dark:text-purple-300': order.status === 'shipped',
                                            'bg-emerald-100 text-emerald-800 dark:bg-emerald-950/60 dark:text-emerald-300': order.status === 'completed',
                                            'bg-rose-100 text-rose-800 dark:bg-rose-950/60 dark:text-rose-300': order.status === 'cancelled'
                                          }">
                                        {{ order.status }}
                                    </span>
                                </td>
                                <td class="p-4 text-right text-gray-500">{{ new Date(order.created_at).toLocaleDateString() }}</td>
                                <td class="p-4 text-right">
                                    <Link :href="route('admin.orders.show', order.id)" class="text-primary font-semibold border border-primary px-3 py-1 rounded hover:bg-primary-soft transition">View</Link>
                                </td>
                            </tr>
                            <tr v-if="!orders.data || orders.data.length === 0">
                                <td colspan="7" class="p-8 text-center text-gray-500 font-medium">No orders found.</td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Mobile Card List View (Phones & small screens) -->
                <div class="block md:hidden divide-y divide-gray-100 dark:divide-gray-700">
                    <div
                        v-for="order in orders.data"
                        :key="order.id"
                        class="p-4 space-y-2.5 hover:bg-gray-50/60 dark:hover:bg-gray-700/30 transition"
                    >
                        <!-- Top Row: Order ID, Tracking Badge & Status -->
                        <div class="flex items-center justify-between gap-2 flex-wrap">
                            <div class="flex items-center gap-2">
                                <span class="font-mono font-bold text-sm text-gray-900 dark:text-gray-100">
                                    #{{ order.id }}
                                </span>
                                <span v-if="order.tracking_number" class="font-mono text-[11px] bg-primary-soft text-primary px-2 py-0.5 rounded border border-primary-soft">
                                    {{ order.tracking_number }}
                                </span>
                            </div>

                            <span class="px-2.5 py-0.5 rounded text-[11px] font-bold uppercase tracking-wider" 
                                  :class="{
                                    'bg-amber-100 text-amber-800 dark:bg-amber-950/60 dark:text-amber-300': order.status === 'pending',
                                    'bg-blue-100 text-blue-800 dark:bg-blue-950/60 dark:text-blue-300': order.status === 'processing',
                                    'bg-teal-100 text-teal-800 dark:bg-teal-950/60 dark:text-teal-300': order.status === 'paid',
                                    'bg-purple-100 text-purple-800 dark:bg-purple-950/60 dark:text-purple-300': order.status === 'shipped',
                                    'bg-emerald-100 text-emerald-800 dark:bg-emerald-950/60 dark:text-emerald-300': order.status === 'completed',
                                    'bg-rose-100 text-rose-800 dark:bg-rose-950/60 dark:text-rose-300': order.status === 'cancelled'
                                  }">
                                {{ order.status }}
                            </span>
                        </div>

                        <!-- Customer & Date Info -->
                        <div class="flex items-center justify-between text-xs">
                            <div class="flex items-center gap-1.5 font-semibold text-gray-800 dark:text-gray-200 truncate max-w-[200px]">
                                <span>👤</span>
                                <span>{{ order.customer ? order.customer.name : (order.guest_email || 'Guest') }}</span>
                            </div>
                            <div class="text-gray-400 text-[11px] shrink-0 font-mono">
                                {{ new Date(order.created_at).toLocaleDateString() }}
                            </div>
                        </div>

                        <!-- Amount & Payment / Shipping Badges -->
                        <div class="flex flex-wrap items-center justify-between gap-2 pt-2 border-t border-gray-100 dark:border-gray-700/60">
                            <div>
                                <div class="font-black text-primary text-base">
                                    ₦{{ parseFloat(order.total_amount).toLocaleString('en-NG', { minimumFractionDigits: 2 }) }}
                                </div>
                                <div class="flex flex-wrap items-center gap-1.5 mt-1">
                                    <span v-if="order.coupon_code" class="inline-flex items-center gap-1 px-1.5 py-0.5 rounded text-[10px] font-mono font-bold bg-emerald-100 text-emerald-800 dark:bg-emerald-950/60 dark:text-emerald-300 border border-emerald-300 dark:border-emerald-800">
                                        🎟️ {{ order.coupon_code }}
                                    </span>
                                    <span v-if="parseFloat(order.shipping_fee) > 0" class="inline-flex items-center gap-1 px-1.5 py-0.5 rounded text-[10px] font-semibold bg-blue-50 text-blue-700 dark:bg-blue-950/60 dark:text-blue-300 border border-blue-200 dark:border-blue-800">
                                        🚚 ₦{{ parseFloat(order.shipping_fee).toLocaleString() }}
                                    </span>
                                    <span v-if="order.payment_method === 'pod' && order.pod_status && order.pod_status !== 'none'"
                                          class="inline-block px-1.5 py-0.5 rounded text-[10px] font-extrabold uppercase"
                                          :class="{
                                              'bg-amber-100 text-amber-800 dark:bg-amber-900/50 dark:text-amber-300': order.pod_status === 'cash_collected',
                                              'bg-purple-100 text-purple-800 dark:bg-purple-900/50 dark:text-purple-300': order.pod_status === 'remitted_to_store',
                                              'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/50 dark:text-emerald-300': order.pod_status === 'reconciled',
                                              'bg-yellow-100 text-yellow-800 dark:bg-yellow-900/50 dark:text-yellow-300': order.pod_status === 'awaiting_collection',
                                          }">
                                        {{ order.pod_status === 'cash_collected' ? '💵 In Hand' : (order.pod_status === 'remitted_to_store' ? '📥 Remitted' : (order.pod_status === 'reconciled' ? '✓ Reconciled' : '⏳ Awaiting Collect')) }}
                                    </span>
                                </div>
                            </div>

                            <!-- Action Button -->
                            <Link
                                :href="route('admin.orders.show', order.id)"
                                class="px-3.5 py-1.5 text-xs font-bold text-primary border border-primary hover:bg-primary-soft rounded-lg transition shrink-0"
                            >
                                View Order →
                            </Link>
                        </div>
                    </div>

                    <div v-if="!orders.data || orders.data.length === 0" class="p-8 text-center text-gray-500 font-medium text-xs">
                        No orders found.
                    </div>
                </div>

                <Pagination v-if="orders.data && orders.data.length > 0" :paginator="orders" class="mt-6 mb-4" />
            </div>
        </div>
    </AdminLayout>
</template>
