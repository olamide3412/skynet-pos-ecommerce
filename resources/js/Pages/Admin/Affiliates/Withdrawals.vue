<script setup>
import { Head, Link, useForm, router } from '@inertiajs/vue3';
import { ref } from 'vue';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import Pagination from '@/Components/Pagination.vue';
import { useToast } from 'vue-toastification';

const props = defineProps({
    withdrawals: Object,
    filters: Object,
    metrics: Object,
});

const toast = useToast();
const search = ref(props.filters.search || '');

const selectedWithdrawal = ref(null);
const showPaidModal = ref(false);
const showRejectModal = ref(false);

const paidForm = useForm({
    payment_reference: '',
    admin_note: '',
});

const rejectForm = useForm({
    admin_note: '',
});

const filterStatus = (status) => {
    router.get(route('admin.affiliates.withdrawals.index'), {
        ...props.filters,
        status: status || undefined,
        page: undefined,
    }, { preserveState: true, preserveScroll: true });
};

const handleSearch = () => {
    router.get(route('admin.affiliates.withdrawals.index'), {
        ...props.filters,
        search: search.value || undefined,
        page: undefined,
    }, { preserveState: true, preserveScroll: true });
};

const openPaidModal = (item) => {
    selectedWithdrawal.value = item;
    paidForm.reset();
    showPaidModal.value = true;
};

const openRejectModal = (item) => {
    selectedWithdrawal.value = item;
    rejectForm.reset();
    showRejectModal.value = true;
};

const submitMarkPaid = () => {
    if (!selectedWithdrawal.value) return;

    paidForm.post(route('admin.affiliates.withdrawals.mark-paid', selectedWithdrawal.value.id), {
        onSuccess: () => {
            showPaidModal.value = false;
            toast.success('Withdrawal marked as paid successfully!');
        },
        onError: () => toast.error('Failed to update withdrawal record.'),
    });
};

const submitReject = () => {
    if (!selectedWithdrawal.value) return;

    rejectForm.post(route('admin.affiliates.withdrawals.reject', selectedWithdrawal.value.id), {
        onSuccess: () => {
            showRejectModal.value = false;
            toast.success('Withdrawal rejected and funds refunded to affiliate wallet.');
        },
        onError: () => toast.error('Failed to reject withdrawal.'),
    });
};
</script>

<template>
    <Head title="Affiliate Withdrawal Requests" />
    <AdminLayout>
        <div class="space-y-6">
            <!-- Header -->
            <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
                <div>
                    <h1 class="text-2xl font-black text-gray-900 dark:text-white tracking-tight flex items-center gap-2.5">
                        <span class="w-9 h-9 rounded-xl bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 flex items-center justify-center text-lg">💳</span>
                        Affiliate Withdrawal Queue
                    </h1>
                    <p class="text-xs sm:text-sm text-gray-500 dark:text-gray-400 mt-1">
                        Review pending payout requests, make manual bank transfers, and log transfer references.
                    </p>
                </div>
                <Link
                    :href="route('admin.affiliates.index')"
                    class="px-4 py-2.5 rounded-xl border border-gray-300 dark:border-gray-600 text-gray-700 dark:text-gray-200 bg-white dark:bg-gray-800 hover:bg-gray-50 text-xs font-bold transition flex items-center gap-2 shadow-xs"
                >
                    <span>← Affiliates List</span>
                </Link>
            </div>

            <!-- KPI Cards -->
            <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
                <div class="bg-white dark:bg-gray-800 p-5 rounded-2xl border border-gray-100 dark:border-gray-700 shadow-xs">
                    <p class="text-xs font-semibold text-gray-400 uppercase tracking-wider">Pending Payout Requests</p>
                    <p class="text-2xl sm:text-3xl font-black text-amber-600 dark:text-amber-400 mt-1">{{ metrics.pending_count }}</p>
                    <span class="text-[11px] font-medium text-gray-500 mt-0.5 block">Awaiting bank settlement</span>
                </div>

                <div class="bg-white dark:bg-gray-800 p-5 rounded-2xl border border-gray-100 dark:border-gray-700 shadow-xs">
                    <p class="text-xs font-semibold text-gray-400 uppercase tracking-wider">Pending Amount Owed</p>
                    <p class="text-2xl sm:text-3xl font-black text-gray-900 dark:text-white mt-1">₦{{ Number(metrics.pending_amount).toLocaleString('en-NG', { minimumFractionDigits: 2 }) }}</p>
                    <span class="text-[11px] font-medium text-amber-600 dark:text-amber-400 mt-0.5 block">Queued for manual transfer</span>
                </div>

                <div class="bg-white dark:bg-gray-800 p-5 rounded-2xl border border-gray-100 dark:border-gray-700 shadow-xs">
                    <p class="text-xs font-semibold text-gray-400 uppercase tracking-wider">Total Amount Paid Out</p>
                    <p class="text-2xl sm:text-3xl font-black text-emerald-600 dark:text-emerald-400 mt-1">₦{{ Number(metrics.paid_amount).toLocaleString('en-NG', { minimumFractionDigits: 2 }) }}</p>
                    <span class="text-[11px] font-medium text-gray-500 mt-0.5 block">Successfully disbursed</span>
                </div>

                <div class="bg-white dark:bg-gray-800 p-5 rounded-2xl border border-gray-100 dark:border-gray-700 shadow-xs">
                    <p class="text-xs font-semibold text-gray-400 uppercase tracking-wider">Rejected Requests</p>
                    <p class="text-2xl sm:text-3xl font-black text-gray-500 mt-1">{{ metrics.rejected_count }}</p>
                    <span class="text-[11px] font-medium text-gray-400 mt-0.5 block">Refunded back to wallet</span>
                </div>
            </div>

            <!-- Filters -->
            <div class="bg-white dark:bg-gray-800 rounded-2xl border border-gray-100 dark:border-gray-700 shadow-xs p-4 flex flex-col md:flex-row items-center justify-between gap-4">
                <div class="flex flex-wrap items-center gap-1.5 w-full md:w-auto">
                    <button
                        @click="filterStatus('')"
                        :class="!filters.status ? 'bg-primary text-white font-bold shadow-xs' : 'text-gray-600 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700'"
                        class="px-3.5 py-1.5 rounded-xl text-xs transition cursor-pointer"
                    >
                        All
                    </button>
                    <button
                        @click="filterStatus('pending')"
                        :class="filters.status === 'pending' ? 'bg-amber-600 text-white font-bold' : 'text-gray-600 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700'"
                        class="px-3.5 py-1.5 rounded-xl text-xs transition cursor-pointer flex items-center gap-1.5"
                    >
                        <span>Pending</span>
                        <span v-if="metrics.pending_count" class="px-1.5 py-0.2 rounded-full text-[10px] bg-white/20">{{ metrics.pending_count }}</span>
                    </button>
                    <button
                        @click="filterStatus('paid')"
                        :class="filters.status === 'paid' ? 'bg-emerald-600 text-white font-bold' : 'text-gray-600 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700'"
                        class="px-3.5 py-1.5 rounded-xl text-xs transition cursor-pointer"
                    >
                        Paid
                    </button>
                    <button
                        @click="filterStatus('rejected')"
                        :class="filters.status === 'rejected' ? 'bg-red-600 text-white font-bold' : 'text-gray-600 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700'"
                        class="px-3.5 py-1.5 rounded-xl text-xs transition cursor-pointer"
                    >
                        Rejected
                    </button>
                </div>

                <div class="w-full md:w-72">
                    <input
                        type="text"
                        v-model="search"
                        @keyup.enter="handleSearch"
                        placeholder="Search ref, account, name..."
                        class="w-full rounded-xl border border-gray-200 dark:border-gray-700 dark:bg-gray-900 px-3.5 py-2 text-xs"
                    />
                </div>
            </div>

            <!-- Table -->
            <div class="bg-white dark:bg-gray-800 rounded-2xl border border-gray-100 dark:border-gray-700 shadow-xs overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-sm">
                        <thead class="bg-gray-50/75 dark:bg-gray-900/50 text-xs uppercase text-gray-400 font-semibold border-b dark:border-gray-700">
                            <tr>
                                <th class="px-5 py-3">ID</th>
                                <th class="px-5 py-3">Affiliate Partner</th>
                                <th class="px-5 py-3 text-right">Requested Amount</th>
                                <th class="px-5 py-3">Bank Destination</th>
                                <th class="px-5 py-3">Status</th>
                                <th class="px-5 py-3">Payment Reference</th>
                                <th class="px-5 py-3 text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-gray-700/60">
                            <tr v-for="item in withdrawals.data" :key="item.id" class="hover:bg-gray-50 dark:hover:bg-gray-700/40">
                                <td class="px-5 py-4 font-mono font-bold text-gray-400 text-xs">#{{ item.id }}</td>
                                <td class="px-5 py-4">
                                    <div class="flex items-center gap-2">
                                        <div>
                                            <p class="font-bold text-gray-900 dark:text-white">{{ item.affiliate?.customer?.name }}</p>
                                            <p class="text-xs text-gray-400">{{ item.affiliate?.customer?.email }}</p>
                                            <code class="text-[11px] font-mono text-primary font-semibold">
                                                {{ item.affiliate?.referral_code }}
                                            </code>
                                        </div>
                                    </div>
                                </td>

                                <td class="px-5 py-4 text-right">
                                    <span class="text-base font-black text-gray-900 dark:text-white">
                                        ₦{{ Number(item.amount).toLocaleString('en-NG', { minimumFractionDigits: 2 }) }}
                                    </span>
                                </td>

                                <td class="px-5 py-4">
                                    <div class="bg-gray-50 dark:bg-gray-900/50 p-2.5 rounded-xl border border-gray-100 dark:border-gray-800 text-xs">
                                        <p class="font-bold text-gray-900 dark:text-white">{{ item.bank_name }}</p>
                                        <p class="font-mono text-primary font-bold select-all">{{ item.account_number }}</p>
                                        <p class="text-gray-500 text-[11px]">{{ item.account_name }}</p>
                                    </div>
                                </td>

                                <td class="px-5 py-4">
                                    <span
                                        :class="{
                                            'bg-emerald-100 text-emerald-800 dark:bg-emerald-950/40 dark:text-emerald-300': item.status === 'paid',
                                            'bg-amber-100 text-amber-800 dark:bg-amber-950/40 dark:text-amber-300': item.status === 'pending',
                                            'bg-red-100 text-red-800 dark:bg-red-950/40 dark:text-red-300': item.status === 'rejected',
                                        }"
                                        class="px-2.5 py-1 rounded-full text-xs font-bold capitalize inline-flex items-center gap-1"
                                    >
                                        {{ item.status }}
                                    </span>
                                    <span v-if="item.processed_at" class="text-[10px] text-gray-400 block mt-1">
                                        {{ new Date(item.processed_at).toLocaleDateString() }}
                                    </span>
                                </td>

                                <td class="px-5 py-4 font-mono text-xs text-gray-600 dark:text-gray-300">
                                    <span v-if="item.payment_reference" class="font-bold text-emerald-600 dark:text-emerald-400">
                                        ✓ {{ item.payment_reference }}
                                    </span>
                                    <span v-else-if="item.admin_note" class="text-red-500 italic">
                                        {{ item.admin_note }}
                                    </span>
                                    <span v-else class="text-gray-400">—</span>
                                </td>

                                <td class="px-5 py-4 text-right">
                                    <div v-if="item.status === 'pending'" class="flex items-center justify-end gap-1.5">
                                        <button
                                            @click="openPaidModal(item)"
                                            class="px-3 py-1.5 rounded-lg text-xs font-bold bg-emerald-600 hover:bg-emerald-700 text-white transition cursor-pointer shadow-xs"
                                        >
                                            Mark Paid
                                        </button>
                                        <button
                                            @click="openRejectModal(item)"
                                            class="px-2.5 py-1.5 rounded-lg text-xs font-bold bg-red-100 dark:bg-red-950/40 text-red-700 dark:text-red-300 hover:bg-red-200 transition cursor-pointer"
                                        >
                                            Reject
                                        </button>
                                    </div>
                                    <div v-else>
                                        <Link :href="route('admin.affiliates.show', item.affiliate_id)" class="text-xs font-bold text-gray-400 hover:text-primary">
                                            Partner →
                                        </Link>
                                    </div>
                                </td>
                            </tr>

                            <tr v-if="!withdrawals.data.length">
                                <td colspan="7" class="px-5 py-12 text-center text-sm text-gray-400">
                                    No withdrawal requests found matching this filter.
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div v-if="withdrawals.links && withdrawals.data.length" class="p-4 border-t dark:border-gray-700">
                    <Pagination :paginator="withdrawals" />
                </div>
            </div>
        </div>

        <!-- Mark as Paid Modal -->
        <div v-if="showPaidModal" class="fixed inset-0 z-50 bg-black/50 backdrop-blur-xs flex items-center justify-center p-4">
            <div class="bg-white dark:bg-gray-800 rounded-2xl max-w-md w-full p-6 shadow-2xl border dark:border-gray-700 space-y-4">
                <div class="flex items-center justify-between border-b dark:border-gray-700 pb-3">
                    <h3 class="text-base font-bold text-gray-900 dark:text-white flex items-center gap-2">
                        <span>✓</span> Mark Withdrawal as Paid
                    </h3>
                    <button @click="showPaidModal = false" class="text-gray-400 hover:text-gray-600">✕</button>
                </div>

                <div class="bg-emerald-50 dark:bg-emerald-950/40 border border-emerald-200 dark:border-emerald-800/60 rounded-xl p-3 text-xs text-emerald-800 dark:text-emerald-200">
                    <p class="font-bold text-sm">₦{{ Number(selectedWithdrawal?.amount).toLocaleString('en-NG', { minimumFractionDigits: 2 }) }}</p>
                    <p class="mt-1">Recipient: <strong>{{ selectedWithdrawal?.account_name }}</strong></p>
                    <p>{{ selectedWithdrawal?.bank_name }} - {{ selectedWithdrawal?.account_number }}</p>
                </div>

                <form @submit.prevent="submitMarkPaid" class="space-y-4">
                    <div>
                        <label class="block text-xs font-bold uppercase tracking-wider text-gray-700 dark:text-gray-300 mb-1">
                            Bank Transfer Reference / Transaction ID *
                        </label>
                        <input
                            type="text"
                            required
                            v-model="paidForm.payment_reference"
                            placeholder="e.g. NIP-20261105-GTB-89218291"
                            class="w-full rounded-xl border border-gray-300 dark:border-gray-600 dark:bg-gray-700 px-3 py-2 text-xs font-mono"
                        />
                        <span class="text-[10px] text-gray-400 mt-1 block">Proof of payout that the affiliate can inspect.</span>
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-gray-700 dark:text-gray-300 mb-1">Admin Remark (Optional)</label>
                        <input
                            type="text"
                            v-model="paidForm.admin_note"
                            placeholder="e.g. Sent via corporate internet banking"
                            class="w-full rounded-xl border border-gray-300 dark:border-gray-600 dark:bg-gray-700 px-3 py-2 text-xs"
                        />
                    </div>

                    <div class="flex justify-end gap-2 pt-3 border-t dark:border-gray-700">
                        <button type="button" @click="showPaidModal = false" class="px-4 py-2 rounded-xl border border-gray-300 dark:border-gray-600 text-xs font-bold">
                            Cancel
                        </button>
                        <button type="submit" :disabled="paidForm.processing" class="px-5 py-2 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-bold transition">
                            {{ paidForm.processing ? 'Saving...' : 'Confirm Paid' }}
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Reject Modal -->
        <div v-if="showRejectModal" class="fixed inset-0 z-50 bg-black/50 backdrop-blur-xs flex items-center justify-center p-4">
            <div class="bg-white dark:bg-gray-800 rounded-2xl max-w-md w-full p-6 shadow-2xl border dark:border-gray-700 space-y-4">
                <div class="flex items-center justify-between border-b dark:border-gray-700 pb-3">
                    <h3 class="text-base font-bold text-red-600 flex items-center gap-2">
                        <span>⚠️</span> Reject Withdrawal Request
                    </h3>
                    <button @click="showRejectModal = false" class="text-gray-400 hover:text-gray-600">✕</button>
                </div>

                <p class="text-xs text-gray-500">
                    Rejecting this withdrawal will <strong>automatically refund ₦{{ Number(selectedWithdrawal?.amount).toLocaleString() }} back</strong> to the partner's virtual wallet.
                </p>

                <form @submit.prevent="submitReject" class="space-y-4">
                    <div>
                        <label class="block text-xs font-bold text-gray-700 dark:text-gray-300 mb-1">Reason for Rejection *</label>
                        <textarea
                            required
                            v-model="rejectForm.admin_note"
                            rows="3"
                            placeholder="e.g. Account name does not match the registered affiliate name. Please update bank details."
                            class="w-full rounded-xl border border-gray-300 dark:border-gray-600 dark:bg-gray-700 px-3 py-2 text-xs"
                        ></textarea>
                    </div>

                    <div class="flex justify-end gap-2 pt-3 border-t dark:border-gray-700">
                        <button type="button" @click="showRejectModal = false" class="px-4 py-2 rounded-xl border border-gray-300 dark:border-gray-600 text-xs font-bold">
                            Cancel
                        </button>
                        <button type="submit" :disabled="rejectForm.processing" class="px-5 py-2 rounded-xl bg-red-600 hover:bg-red-700 text-white text-xs font-bold transition">
                            {{ rejectForm.processing ? 'Rejecting...' : 'Reject & Refund Wallet' }}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </AdminLayout>
</template>

