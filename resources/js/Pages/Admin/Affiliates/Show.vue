<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import Pagination from '@/Components/Pagination.vue';
import { useToast } from 'vue-toastification';

const props = defineProps({
    affiliate: Object,
    commissions: Object,
    transactions: Object,
    withdrawals: Object,
    stats: Object,
    store_defaults: Object,
});

const toast = useToast();
const activeTab = ref('commissions');

const form = useForm({
    status: props.affiliate.status,
    commission_type: props.affiliate.commission_type || '',
    commission_rate: props.affiliate.commission_rate ?? '',
    min_order_amount: props.affiliate.min_order_amount ?? '',
    bank_name: props.affiliate.bank_name || '',
    account_number: props.affiliate.account_number || '',
    account_name: props.affiliate.account_name || '',
    admin_notes: props.affiliate.admin_notes || '',
});

const saveAffiliate = () => {
    form.put(route('admin.affiliates.update', props.affiliate.id), {
        preserveScroll: true,
        onSuccess: () => toast.success('Affiliate details updated successfully!'),
        onError: () => toast.error('Failed to update affiliate details.'),
    });
};
</script>

<template>
    <Head :title="`Affiliate: ${affiliate.customer?.name || affiliate.referral_code}`" />
    <AdminLayout>
        <div class="space-y-6">
            <!-- Back button & Header -->
            <div class="flex items-center justify-between">
                <Link
                    :href="route('admin.affiliates.index')"
                    class="text-xs font-bold text-gray-500 hover:text-gray-700 dark:hover:text-gray-300 flex items-center gap-1"
                >
                    ← Back to Affiliates
                </Link>
                <div class="flex items-center gap-2">
                    <span
                        :class="{
                            'bg-emerald-100 text-emerald-800 dark:bg-emerald-950/40 dark:text-emerald-300': affiliate.status === 'approved',
                            'bg-amber-100 text-amber-800 dark:bg-amber-950/40 dark:text-amber-300': affiliate.status === 'pending',
                            'bg-red-100 text-red-800 dark:bg-red-950/40 dark:text-red-300': affiliate.status === 'suspended' || affiliate.status === 'rejected',
                        }"
                        class="px-3 py-1 rounded-full text-xs font-black uppercase tracking-wider"
                    >
                        {{ affiliate.status }}
                    </span>
                </div>
            </div>

            <!-- Profile Summary Card -->
            <div class="bg-white dark:bg-gray-800 rounded-2xl border border-gray-100 dark:border-gray-700 p-6 shadow-xs">
                <div class="flex flex-col md:flex-row items-start md:items-center justify-between gap-6">
                    <div class="flex items-center gap-4">
                        <div class="w-14 h-14 rounded-2xl bg-primary text-white font-black text-xl flex items-center justify-center shrink-0 shadow-sm">
                            {{ affiliate.customer?.name ? affiliate.customer.name.substring(0, 2).toUpperCase() : 'AF' }}
                        </div>
                        <div>
                            <h1 class="text-xl font-black text-gray-900 dark:text-white">{{ affiliate.customer?.name }}</h1>
                            <p class="text-xs text-gray-500">{{ affiliate.customer?.email }} • {{ affiliate.customer?.phone || 'No phone' }}</p>
                            <div class="flex items-center gap-2 mt-2">
                                <span class="text-xs font-semibold text-gray-400">Referral Code:</span>
                                <code class="px-2 py-0.5 rounded font-mono font-bold text-xs bg-primary-soft text-primary border border-primary-soft">
                                    {{ affiliate.referral_code }}
                                </code>
                                <a
                                    :href="affiliate.referral_url"
                                    target="_blank"
                                    class="text-xs font-bold text-primary hover:underline"
                                >
                                    Open Link ↗
                                </a>
                            </div>
                        </div>
                    </div>

                    <!-- Financial Balances -->
                    <div class="grid grid-cols-2 sm:grid-cols-4 gap-4 w-full md:w-auto pt-4 md:pt-0 border-t md:border-t-0 dark:border-gray-700">
                        <div class="bg-gray-50 dark:bg-gray-900/50 p-3 rounded-xl text-center">
                            <span class="text-[10px] uppercase font-bold text-gray-400 block">Wallet Balance</span>
                            <span class="text-base font-black text-emerald-600 dark:text-emerald-400">₦{{ Number(affiliate.wallet_balance).toLocaleString('en-NG', { minimumFractionDigits: 2 }) }}</span>
                        </div>
                        <div class="bg-gray-50 dark:bg-gray-900/50 p-3 rounded-xl text-center">
                            <span class="text-[10px] uppercase font-bold text-gray-400 block">Pending Holding</span>
                            <span class="text-base font-black text-amber-600 dark:text-amber-400">₦{{ Number(stats.pending_earnings).toLocaleString('en-NG', { minimumFractionDigits: 2 }) }}</span>
                        </div>
                        <div class="bg-gray-50 dark:bg-gray-900/50 p-3 rounded-xl text-center">
                            <span class="text-[10px] uppercase font-bold text-gray-400 block">Total Earned</span>
                            <span class="text-base font-black text-gray-900 dark:text-white">₦{{ Number(affiliate.total_earned).toLocaleString('en-NG', { minimumFractionDigits: 2 }) }}</span>
                        </div>
                        <div class="bg-gray-50 dark:bg-gray-900/50 p-3 rounded-xl text-center">
                            <span class="text-[10px] uppercase font-bold text-gray-400 block">Total Withdrawn</span>
                            <span class="text-base font-black text-gray-900 dark:text-white">₦{{ Number(affiliate.total_withdrawn).toLocaleString('en-NG', { minimumFractionDigits: 2 }) }}</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Settings / Override Form -->
            <div class="bg-white dark:bg-gray-800 rounded-2xl border border-gray-100 dark:border-gray-700 p-6 shadow-xs">
                <h2 class="text-sm font-bold text-gray-900 dark:text-white uppercase tracking-wider mb-4">
                    Partner Settings & Rate Customization
                </h2>
                <form @submit.prevent="saveAffiliate" class="space-y-4">
                    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                        <div>
                            <label class="block text-xs font-bold uppercase tracking-wider text-gray-700 dark:text-gray-300 mb-1">Status</label>
                            <select v-model="form.status" class="w-full rounded-xl border border-gray-300 dark:border-gray-600 dark:bg-gray-700 px-3 py-2 text-sm font-semibold">
                                <option value="pending">Pending Approval</option>
                                <option value="approved">Approved & Active</option>
                                <option value="suspended">Suspended (Commissions Halted)</option>
                                <option value="rejected">Rejected</option>
                            </select>
                        </div>

                        <div>
                            <label class="block text-xs font-bold uppercase tracking-wider text-gray-700 dark:text-gray-300 mb-1">Commission Model</label>
                            <select v-model="form.commission_type" class="w-full rounded-xl border border-gray-300 dark:border-gray-600 dark:bg-gray-700 px-3 py-2 text-sm">
                                <option value="">Inherit Store Default ({{ store_defaults.commission_type }})</option>
                                <option value="percentage">Percentage (%)</option>
                                <option value="flat">Flat Fixed (₦)</option>
                            </select>
                        </div>

                        <div>
                            <label class="block text-xs font-bold uppercase tracking-wider text-gray-700 dark:text-gray-300 mb-1">Custom Rate Override</label>
                            <input
                                type="number"
                                step="0.1"
                                min="0"
                                v-model="form.commission_rate"
                                class="w-full rounded-xl border border-gray-300 dark:border-gray-600 dark:bg-gray-700 px-3 py-2 text-sm"
                                placeholder="e.g. 10 for 10% (empty = default)"
                            />
                        </div>

                        <div>
                            <label class="block text-xs font-bold uppercase tracking-wider text-gray-700 dark:text-gray-300 mb-1">Min. Order Threshold (₦)</label>
                            <input
                                type="number"
                                step="0.01"
                                min="0"
                                v-model="form.min_order_amount"
                                class="w-full rounded-xl border border-gray-300 dark:border-gray-600 dark:bg-gray-700 px-3 py-2 text-sm"
                                :placeholder="`Store default: ₦${Number(store_defaults.min_order_amount || 0).toLocaleString()} (empty = inherit)`"
                            />
                            <span class="text-[10px] text-gray-400 mt-0.5 block">
                                Empty = inherit store default. 0 = no minimum order.
                            </span>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 pt-2 border-t dark:border-gray-700">
                        <div>
                            <label class="block text-xs font-bold text-gray-700 dark:text-gray-300 mb-1">Payout Bank Name</label>
                            <input type="text" v-model="form.bank_name" class="w-full rounded-xl border border-gray-300 dark:border-gray-600 dark:bg-gray-700 px-3 py-2 text-xs" />
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-gray-700 dark:text-gray-300 mb-1">Account Number</label>
                            <input type="text" v-model="form.account_number" class="w-full rounded-xl border border-gray-300 dark:border-gray-600 dark:bg-gray-700 px-3 py-2 text-xs font-mono" />
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-gray-700 dark:text-gray-300 mb-1">Account Name</label>
                            <input type="text" v-model="form.account_name" class="w-full rounded-xl border border-gray-300 dark:border-gray-600 dark:bg-gray-700 px-3 py-2 text-xs" />
                        </div>
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-gray-700 dark:text-gray-300 mb-1">Admin Notes</label>
                        <textarea v-model="form.admin_notes" rows="2" class="w-full rounded-xl border border-gray-300 dark:border-gray-600 dark:bg-gray-700 px-3 py-2 text-xs" placeholder="Internal remarks regarding this partner..."></textarea>
                    </div>

                    <div class="flex justify-end">
                        <button type="submit" :disabled="form.processing" class="px-5 py-2.5 rounded-xl bg-primary hover:bg-primary-dark text-white text-xs font-bold transition shadow-xs">
                            {{ form.processing ? 'Saving...' : 'Save Partner Settings' }}
                        </button>
                    </div>
                </form>
            </div>

            <!-- Tabbed Tables -->
            <div class="flex items-center gap-2 border-b border-gray-200 dark:border-gray-700 pb-2">
                <button
                    @click="activeTab = 'commissions'"
                    :class="activeTab === 'commissions' ? 'bg-primary text-white font-bold shadow-xs' : 'text-gray-600 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700'"
                    class="px-4 py-2 rounded-xl text-xs sm:text-sm transition cursor-pointer"
                >
                    💰 Commissions & Sales ({{ stats.commissions_count }})
                </button>
                <button
                    @click="activeTab = 'wallet'"
                    :class="activeTab === 'wallet' ? 'bg-primary text-white font-bold shadow-xs' : 'text-gray-600 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700'"
                    class="px-4 py-2 rounded-xl text-xs sm:text-sm transition cursor-pointer"
                >
                    📒 Wallet Transactions Ledger
                </button>
                <button
                    @click="activeTab = 'withdrawals'"
                    :class="activeTab === 'withdrawals' ? 'bg-primary text-white font-bold shadow-xs' : 'text-gray-600 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700'"
                    class="px-4 py-2 rounded-xl text-xs sm:text-sm transition cursor-pointer"
                >
                    💳 Withdrawal History
                </button>
            </div>

            <!-- Commissions Tab -->
            <div v-if="activeTab === 'commissions'" class="bg-white dark:bg-gray-800 rounded-2xl border border-gray-100 dark:border-gray-700 shadow-xs overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-sm">
                        <thead class="bg-gray-50/75 dark:bg-gray-900/50 text-xs uppercase text-gray-400 font-semibold border-b dark:border-gray-700">
                            <tr>
                                <th class="px-5 py-3">Order #</th>
                                <th class="px-5 py-3 text-right">Order Amount</th>
                                <th class="px-5 py-3 text-center">Commission Rate</th>
                                <th class="px-5 py-3 text-right">Commission Earned</th>
                                <th class="px-5 py-3">Status</th>
                                <th class="px-5 py-3 text-right">Date</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-gray-700/60">
                            <tr v-for="c in commissions.data" :key="c.id" class="hover:bg-gray-50 dark:hover:bg-gray-700/40">
                                <td class="px-5 py-3.5 font-bold text-gray-900 dark:text-white">
                                    <Link :href="route('admin.orders.show', c.order_id)" class="text-primary hover:underline">
                                        {{ c.order?.tracking_number || `#${c.order_id}` }}
                                    </Link>
                                </td>
                                <td class="px-5 py-3.5 text-right font-medium">
                                    ₦{{ Number(c.order_amount_base).toLocaleString('en-NG', { minimumFractionDigits: 2 }) }}
                                </td>
                                <td class="px-5 py-3.5 text-center text-xs text-gray-500">
                                    {{ c.commission_type === 'flat' ? `₦${Number(c.commission_rate).toLocaleString()}` : `${c.commission_rate}%` }}
                                </td>
                                <td class="px-5 py-3.5 text-right font-extrabold text-emerald-600 dark:text-emerald-400">
                                    ₦{{ Number(c.commission_amount).toLocaleString('en-NG', { minimumFractionDigits: 2 }) }}
                                </td>
                                <td class="px-5 py-3.5">
                                    <span
                                        :class="{
                                            'bg-emerald-100 text-emerald-800 dark:bg-emerald-950/40 dark:text-emerald-300': c.status === 'available',
                                            'bg-amber-100 text-amber-800 dark:bg-amber-950/40 dark:text-amber-300': c.status === 'pending',
                                            'bg-red-100 text-red-800 dark:bg-red-950/40 dark:text-red-300': c.status === 'reversed',
                                        }"
                                        class="px-2 py-0.5 rounded-full text-xs font-bold capitalize"
                                    >
                                        {{ c.status }}
                                    </span>
                                </td>
                                <td class="px-5 py-3.5 text-right text-xs text-gray-400">
                                    {{ new Date(c.created_at).toLocaleString() }}
                                </td>
                            </tr>
                            <tr v-if="!commissions.data.length">
                                <td colspan="6" class="px-5 py-8 text-center text-gray-400 text-xs">No referral commissions recorded yet.</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <div v-if="commissions.links && commissions.data.length" class="p-4 border-t dark:border-gray-700">
                    <Pagination :paginator="commissions" />
                </div>
            </div>

            <!-- Ledger Tab -->
            <div v-if="activeTab === 'wallet'" class="bg-white dark:bg-gray-800 rounded-2xl border border-gray-100 dark:border-gray-700 shadow-xs overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-sm">
                        <thead class="bg-gray-50/75 dark:bg-gray-900/50 text-xs uppercase text-gray-400 font-semibold border-b dark:border-gray-700">
                            <tr>
                                <th class="px-5 py-3">Description</th>
                                <th class="px-5 py-3 text-center">Type</th>
                                <th class="px-5 py-3 text-right">Amount</th>
                                <th class="px-5 py-3 text-right">Balance After</th>
                                <th class="px-5 py-3 text-right">Date & Time</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-gray-700/60">
                            <tr v-for="t in transactions.data" :key="t.id" class="hover:bg-gray-50 dark:hover:bg-gray-700/40">
                                <td class="px-5 py-3.5 font-medium text-gray-900 dark:text-white">{{ t.description }}</td>
                                <td class="px-5 py-3.5 text-center">
                                    <span
                                        :class="t.type === 'credit' ? 'bg-emerald-100 text-emerald-800 dark:bg-emerald-950/40 dark:text-emerald-300' : 'bg-red-100 text-red-800 dark:bg-red-950/40 dark:text-red-300'"
                                        class="px-2 py-0.5 rounded-full text-xs font-bold uppercase"
                                    >
                                        {{ t.type }}
                                    </span>
                                </td>
                                <td class="px-5 py-3.5 text-right font-black" :class="t.type === 'credit' ? 'text-emerald-600 dark:text-emerald-400' : 'text-red-600 dark:text-red-400'">
                                    {{ t.type === 'credit' ? '+' : '-' }}₦{{ Number(t.amount).toLocaleString('en-NG', { minimumFractionDigits: 2 }) }}
                                </td>
                                <td class="px-5 py-3.5 text-right font-bold text-gray-900 dark:text-white">
                                    ₦{{ Number(t.balance_after).toLocaleString('en-NG', { minimumFractionDigits: 2 }) }}
                                </td>
                                <td class="px-5 py-3.5 text-right text-xs text-gray-400">
                                    {{ new Date(t.created_at).toLocaleString() }}
                                </td>
                            </tr>
                            <tr v-if="!transactions.data.length">
                                <td colspan="5" class="px-5 py-8 text-center text-gray-400 text-xs">No wallet transactions logged yet.</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <div v-if="transactions.links && transactions.data.length" class="p-4 border-t dark:border-gray-700">
                    <Pagination :paginator="transactions" />
                </div>
            </div>

            <!-- Withdrawals Tab -->
            <div v-if="activeTab === 'withdrawals'" class="bg-white dark:bg-gray-800 rounded-2xl border border-gray-100 dark:border-gray-700 shadow-xs overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-sm">
                        <thead class="bg-gray-50/75 dark:bg-gray-900/50 text-xs uppercase text-gray-400 font-semibold border-b dark:border-gray-700">
                            <tr>
                                <th class="px-5 py-3">Request #</th>
                                <th class="px-5 py-3 text-right">Amount</th>
                                <th class="px-5 py-3">Bank Details</th>
                                <th class="px-5 py-3">Status</th>
                                <th class="px-5 py-3">Payment Reference</th>
                                <th class="px-5 py-3 text-right">Processed At</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-gray-700/60">
                            <tr v-for="w in withdrawals.data" :key="w.id" class="hover:bg-gray-50 dark:hover:bg-gray-700/40">
                                <td class="px-5 py-3.5 font-mono font-bold text-gray-900 dark:text-white">#{{ w.id }}</td>
                                <td class="px-5 py-3.5 text-right font-black text-gray-900 dark:text-white">
                                    ₦{{ Number(w.amount).toLocaleString('en-NG', { minimumFractionDigits: 2 }) }}
                                </td>
                                <td class="px-5 py-3.5 text-xs text-gray-600 dark:text-gray-300">
                                    <p class="font-bold">{{ w.bank_name }}</p>
                                    <p class="font-mono">{{ w.account_number }} - {{ w.account_name }}</p>
                                </td>
                                <td class="px-5 py-3.5">
                                    <span
                                        :class="{
                                            'bg-emerald-100 text-emerald-800 dark:bg-emerald-950/40 dark:text-emerald-300': w.status === 'paid',
                                            'bg-amber-100 text-amber-800 dark:bg-amber-950/40 dark:text-amber-300': w.status === 'pending',
                                            'bg-red-100 text-red-800 dark:bg-red-950/40 dark:text-red-300': w.status === 'rejected',
                                        }"
                                        class="px-2 py-0.5 rounded-full text-xs font-bold capitalize"
                                    >
                                        {{ w.status }}
                                    </span>
                                </td>
                                <td class="px-5 py-3.5 text-xs font-mono text-gray-500">
                                    {{ w.payment_reference || '—' }}
                                </td>
                                <td class="px-5 py-3.5 text-right text-xs text-gray-400">
                                    {{ w.processed_at ? new Date(w.processed_at).toLocaleString() : 'Pending' }}
                                </td>
                            </tr>
                            <tr v-if="!withdrawals.data.length">
                                <td colspan="6" class="px-5 py-8 text-center text-gray-400 text-xs">No withdrawal requests filed yet.</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <div v-if="withdrawals.links && withdrawals.data.length" class="p-4 border-t dark:border-gray-700">
                    <Pagination :paginator="withdrawals" />
                </div>
            </div>
        </div>
    </AdminLayout>
</template>

