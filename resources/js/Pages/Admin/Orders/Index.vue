<script setup>
import { Head, Link, router } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import Pagination from '@/Components/Pagination.vue';
import { ref, watch } from 'vue';
import debounce from 'lodash/debounce';

const props = defineProps({
    orders: Object,
    podCustody: {
        type: Object,
        default: () => ({ cash_in_hand: 0, count: 0 }),
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
            <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm border dark:border-gray-700 p-4 mb-6 flex flex-wrap gap-4 items-end">
                <div class="flex-1 min-w-[200px]">
                    <label class="block text-xs font-bold text-gray-500 dark:text-gray-400 uppercase tracking-wide mb-1">Search</label>
                    <input v-model="search" type="text" placeholder="ID, tracking, email, coupon..." 
                        class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white px-3 py-2 text-sm focus:ring-primary focus:border-primary" />
                </div>
                
                <div class="w-full sm:w-auto">
                    <label class="block text-xs font-bold text-gray-500 dark:text-gray-400 uppercase tracking-wide mb-1">Status</label>
                    <select v-model="status" 
                        class="w-full sm:w-40 rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white px-3 py-2 text-sm focus:ring-primary focus:border-primary">
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
                            class="w-full sm:w-auto rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white px-3 py-2 text-sm focus:ring-primary focus:border-primary" />
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-gray-500 dark:text-gray-400 uppercase tracking-wide mb-1">To Date</label>
                        <input v-model="endDate" type="date" 
                            class="w-full sm:w-auto rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white px-3 py-2 text-sm focus:ring-primary focus:border-primary" />
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
                                            'bg-yellow-100 text-yellow-800': order.status === 'pending',
                                            'theme-primary-badge': order.status === 'paid',
                                            'theme-secondary-badge': order.status === 'shipped',
                                            'bg-green-100 text-green-800': order.status === 'completed',
                                            'bg-red-100 text-red-800': order.status === 'cancelled'
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
