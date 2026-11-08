<script setup>
import { Head, Link, useForm, router } from '@inertiajs/vue3';
import { ref } from 'vue';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import Pagination from '@/Components/Pagination.vue';
import { useToast } from 'vue-toastification';

const props = defineProps({
    affiliates: Object,
    filters: Object,
    metrics: Object,
    available_customers: Array,
    store_defaults: Object,
});

const toast = useToast();
const search = ref(props.filters.search || '');
const showEnrollModal = ref(false);

const filterStatus = (status) => {
    router.get(route('admin.affiliates.index'), {
        ...props.filters,
        status: status || undefined,
        page: undefined,
    }, { preserveState: true, preserveScroll: true });
};

const handleSearch = () => {
    router.get(route('admin.affiliates.index'), {
        ...props.filters,
        search: search.value || undefined,
        page: undefined,
    }, { preserveState: true, preserveScroll: true });
};

const enrollForm = useForm({
    customer_id: '',
    commission_type: '',
    commission_rate: '',
    min_order_amount: '',
    bank_name: '',
    account_number: '',
    account_name: '',
    admin_notes: '',
});

const submitEnroll = () => {
    enrollForm.post(route('admin.affiliates.enroll'), {
        onSuccess: () => {
            showEnrollModal.value = false;
            enrollForm.reset();
            toast.success('Customer successfully enrolled as an affiliate!');
        },
        onError: () => toast.error('Failed to enroll customer. Please check the form.'),
    });
};

const quickUpdateStatus = (affiliate, newStatus) => {
    if (!confirm(`Are you sure you want to change this affiliate status to "${newStatus}"?`)) return;

    router.put(route('admin.affiliates.update', affiliate.id), {
        status: newStatus,
        commission_type: affiliate.commission_type,
        commission_rate: affiliate.commission_rate,
        min_order_amount: affiliate.min_order_amount,
        bank_name: affiliate.bank_name,
        account_number: affiliate.account_number,
        account_name: affiliate.account_name,
    }, {
        onSuccess: () => toast.success(`Affiliate marked as ${newStatus}.`),
        onError: () => toast.error('Failed to update affiliate status.'),
    });
};
</script>

<template>
    <Head title="Affiliates & Referral Program" />
    <AdminLayout>
        <div class="space-y-6">
            <!-- Header -->
            <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
                <div>
                    <h1 class="text-2xl font-black text-gray-900 dark:text-white tracking-tight flex items-center gap-2.5">
                        <span class="w-9 h-9 rounded-xl bg-primary-soft text-primary flex items-center justify-center text-lg">🤝</span>
                        Affiliates & Referral Program
                    </h1>
                    <p class="text-xs sm:text-sm text-gray-500 dark:text-gray-400 mt-1">
                        Manage approved promoters, track referred customer purchases, and review pending applications.
                    </p>
                </div>
                <div class="flex items-center gap-2.5">
                    <Link
                        :href="route('admin.affiliates.withdrawals.index')"
                        class="px-4 py-2.5 rounded-xl border border-gray-300 dark:border-gray-600 text-gray-700 dark:text-gray-200 bg-white dark:bg-gray-800 hover:bg-gray-50 dark:hover:bg-gray-700 text-xs font-bold transition flex items-center gap-2 shadow-xs"
                    >
                        <span>💳</span>
                        <span>Withdrawal Requests</span>
                        <span v-if="metrics.pending_requests > 0" class="px-1.5 py-0.5 rounded-full text-[10px] bg-amber-500 text-white font-extrabold">
                            {{ metrics.pending_requests }}
                        </span>
                    </Link>
                    <button
                        @click="showEnrollModal = true"
                        class="bg-primary hover:bg-primary-dark text-white text-xs font-bold px-4 py-2.5 rounded-xl transition flex items-center gap-2 shadow-xs cursor-pointer"
                    >
                        <span>+ Enroll Customer</span>
                    </button>
                </div>
            </div>

            <!-- KPI Cards -->
            <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
                <div class="bg-white dark:bg-gray-800 p-5 rounded-2xl border border-gray-100 dark:border-gray-700 shadow-xs">
                    <p class="text-xs font-semibold text-gray-400 uppercase tracking-wider">Total Affiliates</p>
                    <p class="text-2xl sm:text-3xl font-black text-gray-900 dark:text-white mt-1">{{ metrics.total_affiliates }}</p>
                    <span class="text-[11px] font-medium text-primary mt-0.5 block">{{ metrics.approved_affiliates }} approved & active</span>
                </div>

                <div class="bg-white dark:bg-gray-800 p-5 rounded-2xl border border-gray-100 dark:border-gray-700 shadow-xs">
                    <p class="text-xs font-semibold text-gray-400 uppercase tracking-wider">Pending Applications</p>
                    <p class="text-2xl sm:text-3xl font-black text-amber-600 dark:text-amber-400 mt-1">{{ metrics.pending_requests }}</p>
                    <span class="text-[11px] font-medium text-gray-500 mt-0.5 block">Awaiting admin approval</span>
                </div>

                <div class="bg-white dark:bg-gray-800 p-5 rounded-2xl border border-gray-100 dark:border-gray-700 shadow-xs">
                    <p class="text-xs font-semibold text-gray-400 uppercase tracking-wider">Wallet Balance Owed</p>
                    <p class="text-2xl sm:text-3xl font-black text-emerald-600 dark:text-emerald-400 mt-1">₦{{ Number(metrics.total_wallet_owed).toLocaleString('en-NG', { minimumFractionDigits: 2 }) }}</p>
                    <span class="text-[11px] font-medium text-gray-500 mt-0.5 block">Available in affiliate wallets</span>
                </div>

                <div class="bg-white dark:bg-gray-800 p-5 rounded-2xl border border-gray-100 dark:border-gray-700 shadow-xs">
                    <p class="text-xs font-semibold text-gray-400 uppercase tracking-wider">Total Paid Out</p>
                    <p class="text-2xl sm:text-3xl font-black text-gray-900 dark:text-white mt-1">₦{{ Number(metrics.total_paid_out).toLocaleString('en-NG', { minimumFractionDigits: 2 }) }}</p>
                    <span class="text-[11px] font-medium text-gray-500 mt-0.5 block">Lifetime manual payouts</span>
                </div>
            </div>

            <!-- Filters & Tabs -->
            <div class="bg-white dark:bg-gray-800 rounded-2xl border border-gray-100 dark:border-gray-700 shadow-xs p-4 flex flex-col md:flex-row items-center justify-between gap-4">
                <div class="flex flex-wrap items-center gap-1.5 w-full md:w-auto">
                    <button
                        @click="filterStatus('')"
                        :class="!filters.status ? 'bg-primary text-white font-bold shadow-xs' : 'text-gray-600 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700'"
                        class="px-3.5 py-1.5 rounded-xl text-xs transition cursor-pointer"
                    >
                        All ({{ metrics.total_affiliates }})
                    </button>
                    <button
                        @click="filterStatus('pending')"
                        :class="filters.status === 'pending' ? 'bg-amber-600 text-white font-bold' : 'text-gray-600 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700'"
                        class="px-3.5 py-1.5 rounded-xl text-xs transition cursor-pointer flex items-center gap-1.5"
                    >
                        <span>Pending Approval</span>
                        <span v-if="metrics.pending_requests" class="px-1.5 py-0.2 rounded-full text-[10px] bg-white/20">{{ metrics.pending_requests }}</span>
                    </button>
                    <button
                        @click="filterStatus('approved')"
                        :class="filters.status === 'approved' ? 'bg-emerald-600 text-white font-bold' : 'text-gray-600 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700'"
                        class="px-3.5 py-1.5 rounded-xl text-xs transition cursor-pointer"
                    >
                        Approved ({{ metrics.approved_affiliates }})
                    </button>
                    <button
                        @click="filterStatus('suspended')"
                        :class="filters.status === 'suspended' ? 'bg-red-600 text-white font-bold' : 'text-gray-600 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700'"
                        class="px-3.5 py-1.5 rounded-xl text-xs transition cursor-pointer"
                    >
                        Suspended
                    </button>
                </div>

                <div class="w-full md:w-72">
                    <input
                        type="text"
                        v-model="search"
                        @keyup.enter="handleSearch"
                        placeholder="Search code, name, email..."
                        class="w-full rounded-xl border border-gray-200 dark:border-gray-700 dark:bg-gray-900 px-3.5 py-2 text-xs"
                    />
                </div>
            </div>

            <!-- Affiliates Table -->
            <div class="bg-white dark:bg-gray-800 rounded-2xl border border-gray-100 dark:border-gray-700 shadow-xs overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-sm">
                        <thead class="bg-gray-50/75 dark:bg-gray-900/50 text-xs uppercase text-gray-400 font-semibold border-b dark:border-gray-700">
                            <tr>
                                <th class="px-5 py-3">Affiliate & Code</th>
                                <th class="px-5 py-3">Status</th>
                                <th class="px-5 py-3 text-center">Commission Rate</th>
                                <th class="px-5 py-3 text-center">Referred Sales</th>
                                <th class="px-5 py-3 text-right">Wallet Balance</th>
                                <th class="px-5 py-3 text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-gray-700/60">
                            <tr v-for="item in affiliates.data" :key="item.id" class="hover:bg-gray-50 dark:hover:bg-gray-700/40 transition">
                                <td class="px-5 py-4">
                                    <div class="flex items-center gap-3">
                                        <div class="w-9 h-9 rounded-xl bg-primary-soft text-primary font-bold flex items-center justify-center text-xs shrink-0">
                                            {{ item.customer?.name ? item.customer.name.substring(0, 2).toUpperCase() : 'AF' }}
                                        </div>
                                        <div>
                                            <p class="font-bold text-gray-900 dark:text-white">{{ item.customer?.name || 'Unknown Customer' }}</p>
                                            <p class="text-xs text-gray-400">{{ item.customer?.email }}</p>
                                            <div class="flex items-center gap-1.5 mt-1">
                                                <code class="px-1.5 py-0.5 rounded text-[11px] font-mono font-bold bg-primary-soft text-primary border border-primary-soft">
                                                    {{ item.referral_code }}
                                                </code>
                                                <span class="text-[11px] text-gray-400">{{ item.visits_count || 0 }} clicks</span>
                                            </div>
                                        </div>
                                    </div>
                                </td>

                                <td class="px-5 py-4">
                                    <span
                                        :class="{
                                            'bg-emerald-100 text-emerald-800 dark:bg-emerald-950/40 dark:text-emerald-300': item.status === 'approved',
                                            'bg-amber-100 text-amber-800 dark:bg-amber-950/40 dark:text-amber-300': item.status === 'pending',
                                            'bg-red-100 text-red-800 dark:bg-red-950/40 dark:text-red-300': item.status === 'suspended' || item.status === 'rejected',
                                        }"
                                        class="px-2.5 py-1 rounded-full text-xs font-bold capitalize inline-flex items-center gap-1"
                                    >
                                        <span class="w-1.5 h-1.5 rounded-full" :class="{
                                            'bg-emerald-500': item.status === 'approved',
                                            'bg-amber-500': item.status === 'pending',
                                            'bg-red-500': item.status === 'suspended' || item.status === 'rejected',
                                        }"></span>
                                        {{ item.status }}
                                    </span>
                                </td>

                                <td class="px-5 py-4 text-center">
                                    <div v-if="item.commission_rate !== null">
                                        <span class="px-2 py-0.5 rounded-lg text-xs font-bold bg-primary-soft text-primary border border-primary-soft">
                                            {{ item.commission_type === 'flat' ? `₦${Number(item.commission_rate).toLocaleString()}` : `${item.commission_rate}%` }}
                                        </span>
                                        <span class="text-[10px] text-gray-400 block mt-0.5">Custom Override</span>
                                    </div>
                                    <div v-else>
                                        <span class="text-xs text-gray-500">
                                            {{ store_defaults.commission_type === 'flat' ? `₦${Number(store_defaults.commission_rate).toLocaleString()}` : `${store_defaults.commission_rate}%` }}
                                        </span>
                                        <span class="text-[10px] text-gray-400 block mt-0.5">Store Default</span>
                                    </div>

                                    <div class="mt-1 text-[10px]">
                                        <span v-if="item.min_order_amount !== null" class="text-primary font-semibold">
                                            Min: {{ Number(item.min_order_amount) > 0 ? `₦${Number(item.min_order_amount).toLocaleString()}` : 'None (₦0)' }}
                                        </span>
                                        <span v-else-if="Number(store_defaults.min_order_amount) > 0" class="text-gray-400">
                                            Min: ₦{{ Number(store_defaults.min_order_amount).toLocaleString() }}
                                        </span>
                                    </div>
                                </td>

                                <td class="px-5 py-4 text-center">
                                    <span class="font-bold text-gray-900 dark:text-white">{{ item.commissions_count || 0 }} orders</span>
                                    <span class="text-[11px] text-gray-400 block">₦{{ Number(item.total_earned).toLocaleString('en-NG', { minimumFractionDigits: 2 }) }} earned</span>
                                </td>

                                <td class="px-5 py-4 text-right">
                                    <span class="font-extrabold text-emerald-600 dark:text-emerald-400">
                                        ₦{{ Number(item.wallet_balance).toLocaleString('en-NG', { minimumFractionDigits: 2 }) }}
                                    </span>
                                    <span class="text-[11px] text-gray-400 block">₦{{ Number(item.total_withdrawn).toLocaleString('en-NG', { minimumFractionDigits: 2 }) }} paid</span>
                                </td>

                                <td class="px-5 py-4 text-right">
                                    <div class="flex items-center justify-end gap-1.5">
                                        <button
                                            v-if="item.status === 'pending'"
                                            @click="quickUpdateStatus(item, 'approved')"
                                            class="px-2.5 py-1 rounded-lg text-xs font-bold bg-emerald-600 text-white hover:bg-emerald-700 transition"
                                            title="Approve affiliate"
                                        >
                                            Approve
                                        </button>
                                        <button
                                            v-if="item.status === 'pending'"
                                            @click="quickUpdateStatus(item, 'rejected')"
                                            class="px-2.5 py-1 rounded-lg text-xs font-bold bg-red-100 text-red-700 hover:bg-red-200 transition"
                                            title="Reject application"
                                        >
                                            Reject
                                        </button>
                                        <Link
                                            :href="route('admin.affiliates.show', item.id)"
                                            class="px-3 py-1 rounded-lg text-xs font-bold bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-200 hover:bg-gray-200 transition"
                                        >
                                            Details →
                                        </Link>
                                    </div>
                                </td>
                            </tr>

                            <tr v-if="!affiliates.data || !affiliates.data.length">
                                <td colspan="6" class="px-5 py-12 text-center text-sm text-gray-400">
                                    No affiliates found matching this filter. Enroll a customer or wait for applications.
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div v-if="affiliates.links && affiliates.data.length" class="p-4 border-t dark:border-gray-700">
                    <Pagination :paginator="affiliates" />
                </div>
            </div>
        </div>

        <!-- Enroll Customer Modal -->
        <div v-if="showEnrollModal" class="fixed inset-0 z-50 bg-black/50 backdrop-blur-xs flex items-center justify-center p-4">
            <div class="bg-white dark:bg-gray-800 rounded-2xl max-w-lg w-full p-6 shadow-2xl border dark:border-gray-700 space-y-4">
                <div class="flex items-center justify-between border-b dark:border-gray-700 pb-3">
                    <h3 class="text-base font-bold text-gray-900 dark:text-white flex items-center gap-2">
                        <span>🤝</span> Enroll Customer as Affiliate
                    </h3>
                    <button @click="showEnrollModal = false" class="text-gray-400 hover:text-gray-600 text-lg">✕</button>
                </div>

                <form @submit.prevent="submitEnroll" class="space-y-4">
                    <div>
                        <label class="block text-xs font-bold uppercase tracking-wider text-gray-700 dark:text-gray-300 mb-1">Select Customer</label>
                        <select v-model="enrollForm.customer_id" required class="w-full rounded-xl border border-gray-300 dark:border-gray-600 dark:bg-gray-700 px-3 py-2 text-sm">
                            <option value="">-- Choose customer to approve --</option>
                            <option v-for="c in available_customers" :key="c.id" :value="c.id">
                                {{ c.name }} ({{ c.email }})
                            </option>
                        </select>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                        <div>
                            <label class="block text-xs font-bold text-gray-700 dark:text-gray-300 mb-1">Commission Model</label>
                            <select v-model="enrollForm.commission_type" class="w-full rounded-xl border border-gray-300 dark:border-gray-600 dark:bg-gray-700 px-3 py-2 text-sm">
                                <option value="">Store Default ({{ store_defaults.commission_type }})</option>
                                <option value="percentage">Percentage (%)</option>
                                <option value="flat">Flat Fixed (₦)</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-gray-700 dark:text-gray-300 mb-1">Custom Rate (Optional)</label>
                            <input
                                type="number"
                                step="0.1"
                                min="0"
                                v-model="enrollForm.commission_rate"
                                class="w-full rounded-xl border border-gray-300 dark:border-gray-600 dark:bg-gray-700 px-3 py-2 text-sm"
                                placeholder="Default"
                            />
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-gray-700 dark:text-gray-300 mb-1">Min. Order (₦)</label>
                            <input
                                type="number"
                                step="0.01"
                                min="0"
                                v-model="enrollForm.min_order_amount"
                                class="w-full rounded-xl border border-gray-300 dark:border-gray-600 dark:bg-gray-700 px-3 py-2 text-sm"
                                :placeholder="Number(store_defaults.min_order_amount) > 0 ? `₦${Number(store_defaults.min_order_amount).toLocaleString()}` : 'Default'"
                            />
                        </div>
                    </div>

                    <div class="pt-2 border-t dark:border-gray-700">
                        <p class="text-xs font-bold uppercase tracking-wider text-gray-500 mb-2">Bank Details for Payouts (Optional)</p>
                        <div class="space-y-2">
                            <input type="text" v-model="enrollForm.bank_name" placeholder="Bank Name (e.g. GTBank, Kuda)" class="w-full rounded-xl border border-gray-300 dark:border-gray-600 dark:bg-gray-700 px-3 py-2 text-xs" />
                            <div class="grid grid-cols-2 gap-2">
                                <input type="text" v-model="enrollForm.account_number" placeholder="Account Number" class="w-full rounded-xl border border-gray-300 dark:border-gray-600 dark:bg-gray-700 px-3 py-2 text-xs font-mono" />
                                <input type="text" v-model="enrollForm.account_name" placeholder="Account Name" class="w-full rounded-xl border border-gray-300 dark:border-gray-600 dark:bg-gray-700 px-3 py-2 text-xs" />
                            </div>
                        </div>
                    </div>

                    <div class="flex justify-end gap-2 pt-3 border-t dark:border-gray-700">
                        <button type="button" @click="showEnrollModal = false" class="px-4 py-2 rounded-xl border border-gray-300 dark:border-gray-600 text-xs font-bold">
                            Cancel
                        </button>
                        <button type="submit" :disabled="enrollForm.processing" class="px-5 py-2 rounded-xl bg-primary hover:bg-primary-dark text-white text-xs font-bold transition disabled:opacity-60 shadow-xs">
                            {{ enrollForm.processing ? 'Enrolling...' : 'Enroll & Generate Code' }}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </AdminLayout>
</template>

