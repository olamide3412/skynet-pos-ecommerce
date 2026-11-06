<script setup>
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import { ref, computed } from 'vue';
import Layout from '@/Layouts/Layout.vue';
import Pagination from '@/Components/Pagination.vue';
import { useToast } from 'vue-toastification';

const props = defineProps({
    has_applied: Boolean,
    allow_applications: Boolean,
    referral_enabled: Boolean,
    default_commission: Object,
    affiliate: Object,
    status: String,
    referral_url: String,
    referral_code: String,
    commission_display: Object,
    stats: Object,
    min_withdrawal: Number,
    has_pending_withdrawal: Boolean,
    commissions: Object,
    transactions: Object,
    withdrawals: Object,
});

const page = usePage();
const companyName = computed(() => page.props.store_settings?.company_name || 'Our Store');

const toast = useToast();
const copied = ref(false);
const showWithdrawModal = ref(false);
const activeTab = ref('commissions');

const copyReferralLink = async () => {
    try {
        await navigator.clipboard.writeText(props.referral_url);
        copied.value = true;
        toast.success('Referral link copied to clipboard!');
        setTimeout(() => copied.value = false, 2500);
    } catch (err) {
        toast.info('Please copy this link: ' + props.referral_url);
    }
};

// Social Share links
const shareText = computed(() => encodeURIComponent(`Shop quality products on ${companyName.value} with great discounts!`));
const shareUrl = computed(() => encodeURIComponent(props.referral_url || ''));

const whatsappShare = computed(() => `https://api.whatsapp.com/send?text=${shareText.value}%20${shareUrl.value}`);
const twitterShare = computed(() => `https://twitter.com/intent/tweet?text=${shareText.value}&url=${shareUrl.value}`);
const facebookShare = computed(() => `https://www.facebook.com/sharer/sharer.php?u=${shareUrl.value}`);

// Application form
const applyForm = useForm({
    bank_name: '',
    account_number: '',
    account_name: '',
    application_note: '',
});

const submitApplication = () => {
    applyForm.post(route('customer.referral.apply'), {
        preserveScroll: true,
        onSuccess: () => toast.success('Application submitted successfully!'),
        onError: () => toast.error('Please check your application details.'),
    });
};

// Withdrawal form
const withdrawForm = useForm({
    amount: props.min_withdrawal || 5000,
    bank_name: props.affiliate?.bank_name || '',
    account_number: props.affiliate?.account_number || '',
    account_name: props.affiliate?.account_name || '',
});

const submitWithdrawal = () => {
    withdrawForm.post(route('customer.referral.withdraw'), {
        preserveScroll: true,
        onSuccess: () => {
            showWithdrawModal.value = false;
            toast.success('Withdrawal request submitted! You will receive a bank transfer once verified.');
        },
        onError: () => toast.error('Failed to submit withdrawal request.'),
    });
};
</script>

<template>
    <Head title="Referral Program & Affiliate Earnings" />
    <Layout>
        <div class="container mx-auto px-4 py-8 max-w-5xl">
            <!-- Top Account Navigation Tabs -->
            <div class="mb-8 flex flex-wrap items-center justify-between gap-4 border-b border-gray-200 dark:border-gray-800 pb-4">
                <div>
                    <h1 class="text-3xl font-extrabold text-gray-900 dark:text-white flex items-center gap-2.5">
                        <span class="w-10 h-10 rounded-2xl bg-indigo-500/10 text-indigo-600 dark:text-indigo-400 flex items-center justify-center text-xl">🤝</span>
                        Referral Program
                    </h1>
                    <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">Recommend products, earn commission on purchases, and withdraw cash to your bank account.</p>
                </div>
                <div class="flex flex-wrap items-center gap-2">
                    <Link
                        :href="route('customer.profile')"
                        class="px-4 py-2 rounded-lg text-sm font-semibold bg-gray-100 dark:bg-gray-800 text-gray-700 dark:text-gray-300 hover:bg-gray-200 dark:hover:bg-gray-700 transition"
                    >
                        Profile & Security
                    </Link>
                    <Link
                        :href="route('customer.addresses')"
                        class="px-4 py-2 rounded-lg text-sm font-semibold bg-gray-100 dark:bg-gray-800 text-gray-700 dark:text-gray-300 hover:bg-gray-200 dark:hover:bg-gray-700 transition"
                    >
                        Address Book
                    </Link>
                    <Link
                        :href="route('customer.orders')"
                        class="px-4 py-2 rounded-lg text-sm font-semibold bg-gray-100 dark:bg-gray-800 text-gray-700 dark:text-gray-300 hover:bg-gray-200 dark:hover:bg-gray-700 transition"
                    >
                        Order History
                    </Link>
                    <Link
                        :href="route('customer.referral.index')"
                        class="px-4 py-2 rounded-lg text-sm font-bold bg-indigo-600 text-white shadow-xs"
                    >
                        Referral Program
                    </Link>
                </div>
            </div>

            <!-- STATE 1: NOT APPLIED YET -->
            <div v-if="!has_applied" class="space-y-8">
                <!-- Promo Hero Banner -->
                <div class="bg-gradient-to-br from-indigo-900 via-indigo-800 to-primary text-white rounded-3xl p-8 sm:p-10 shadow-xl relative overflow-hidden">
                    <div class="max-w-2xl space-y-4 relative z-10">
                        <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-extrabold uppercase tracking-wider bg-white/10 backdrop-blur-md border border-white/20 text-indigo-100">
                            Partner with {{ companyName }}
                        </span>
                        <h2 class="text-3xl sm:text-4xl font-black tracking-tight leading-tight">
                            Share Products. <br />
                            <span class="text-amber-300">Earn Cash Commissions.</span>
                        </h2>
                        <p class="text-sm sm:text-base text-indigo-100/90 leading-relaxed">
                            Join our selective affiliate program. When friends, clients, or followers purchase through your unique referral link, you receive
                            <strong>{{ default_commission.type === 'flat' ? `₦${Number(default_commission.rate).toLocaleString()} per order` : `${default_commission.rate}% on every item` }}</strong> credited straight to your virtual wallet<span v-if="Number(default_commission.min_order_amount) > 0"> (on orders of <strong>₦{{ Number(default_commission.min_order_amount).toLocaleString() }}</strong> or more)</span>.
                        </p>
                    </div>

                    <!-- Steps Grid -->
                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mt-8 pt-8 border-t border-white/10 relative z-10">
                        <div class="bg-white/10 backdrop-blur-md p-4 rounded-2xl border border-white/10">
                            <span class="text-2xl font-black text-amber-300">1</span>
                            <h3 class="font-bold text-sm mt-1">Apply & Get Approved</h3>
                            <p class="text-xs text-indigo-100/80 mt-1">Submit your payout bank details. Our management team reviews and activates your account.</p>
                        </div>
                        <div class="bg-white/10 backdrop-blur-md p-4 rounded-2xl border border-white/10">
                            <span class="text-2xl font-black text-amber-300">2</span>
                            <h3 class="font-bold text-sm mt-1">Share Your Link</h3>
                            <p class="text-xs text-indigo-100/80 mt-1">Share your link via WhatsApp, social media, or directly with buyers. Clicks stay tracked for 30 days.</p>
                        </div>
                        <div class="bg-white/10 backdrop-blur-md p-4 rounded-2xl border border-white/10">
                            <span class="text-2xl font-black text-amber-300">3</span>
                            <h3 class="font-bold text-sm mt-1">Withdraw Cash</h3>
                            <p class="text-xs text-indigo-100/80 mt-1">Once orders are fulfilled, withdraw your available earnings directly to your Nigerian bank.</p>
                        </div>
                    </div>
                </div>

                <!-- Application Form -->
                <div v-if="allow_applications" class="bg-white dark:bg-gray-800 rounded-3xl border border-gray-100 dark:border-gray-700 p-8 shadow-xs max-w-2xl mx-auto space-y-6">
                    <div>
                        <h3 class="text-xl font-bold text-gray-900 dark:text-white">Apply for the Referral Program</h3>
                        <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                            Provide your bank account destination for future commission payouts. All applications are reviewed by store management.
                        </p>
                    </div>

                    <form @submit.prevent="submitApplication" class="space-y-4">
                        <div>
                            <label class="block text-xs font-bold uppercase tracking-wider text-gray-700 dark:text-gray-300 mb-1">
                                Bank Name *
                            </label>
                            <input
                                type="text"
                                required
                                v-model="applyForm.bank_name"
                                placeholder="e.g. GTBank, Zenith Bank, Kuda, Moniepoint"
                                class="w-full rounded-xl border border-gray-300 dark:border-gray-600 dark:bg-gray-700 px-3.5 py-2.5 text-sm"
                            />
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-xs font-bold uppercase tracking-wider text-gray-700 dark:text-gray-300 mb-1">
                                    Account Number *
                                </label>
                                <input
                                    type="text"
                                    required
                                    maxlength="10"
                                    v-model="applyForm.account_number"
                                    placeholder="10-digit NUBAN"
                                    class="w-full rounded-xl border border-gray-300 dark:border-gray-600 dark:bg-gray-700 px-3.5 py-2.5 text-sm font-mono"
                                />
                            </div>

                            <div>
                                <label class="block text-xs font-bold uppercase tracking-wider text-gray-700 dark:text-gray-300 mb-1">
                                    Account Name *
                                </label>
                                <input
                                    type="text"
                                    required
                                    v-model="applyForm.account_name"
                                    placeholder="Account holder name"
                                    class="w-full rounded-xl border border-gray-300 dark:border-gray-600 dark:bg-gray-700 px-3.5 py-2.5 text-sm"
                                />
                            </div>
                        </div>

                        <div>
                            <label class="block text-xs font-bold uppercase tracking-wider text-gray-700 dark:text-gray-300 mb-1">
                                How do you plan to promote our products? (Optional)
                            </label>
                            <textarea
                                v-model="applyForm.application_note"
                                rows="3"
                                placeholder="Tell us about your audience, social media channels, or how you intend to share your link..."
                                class="w-full rounded-xl border border-gray-300 dark:border-gray-600 dark:bg-gray-700 px-3.5 py-2.5 text-sm"
                            ></textarea>
                        </div>

                        <button
                            type="submit"
                            :disabled="applyForm.processing"
                            class="w-full py-3 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white font-bold text-sm transition shadow-sm disabled:opacity-60 cursor-pointer"
                        >
                            {{ applyForm.processing ? 'Submitting Application...' : 'Submit Affiliate Application' }}
                        </button>
                    </form>
                </div>

                <div v-else class="text-center py-12 bg-gray-50 dark:bg-gray-800 rounded-3xl border dark:border-gray-700">
                    <p class="text-sm font-bold text-gray-600 dark:text-gray-300">Public affiliate applications are currently closed.</p>
                    <p class="text-xs text-gray-500 mt-1">If you have been invited by store management, contact support to have your account enrolled directly.</p>
                </div>
            </div>

            <!-- STATE 2: PENDING APPROVAL -->
            <div v-else-if="status === 'pending'" class="max-w-2xl mx-auto space-y-6">
                <div class="bg-amber-50 dark:bg-amber-950/30 border border-amber-200 dark:border-amber-900/60 rounded-3xl p-8 text-center space-y-4">
                    <span class="w-16 h-16 rounded-full bg-amber-100 dark:bg-amber-900/50 text-amber-600 dark:text-amber-300 inline-flex items-center justify-center text-3xl">
                        ⏳
                    </span>
                    <h2 class="text-2xl font-black text-amber-900 dark:text-amber-200">Application Under Review</h2>
                    <p class="text-sm text-amber-800 dark:text-amber-300/80 leading-relaxed max-w-md mx-auto">
                        Thank you for applying! Our store management team is currently reviewing your affiliate application. Once approved, your custom referral link and partner dashboard will become active here.
                    </p>
                    <div class="pt-4 border-t border-amber-200 dark:border-amber-900/40 text-xs text-gray-600 dark:text-gray-400">
                        Registered Payout Destination: <strong>{{ affiliate.bank_name }}</strong> ({{ affiliate.account_number }})
                    </div>
                </div>
            </div>

            <!-- STATE 3: SUSPENDED OR REJECTED -->
            <div v-else-if="status === 'suspended' || status === 'rejected'" class="max-w-2xl mx-auto space-y-6">
                <div class="bg-red-50 dark:bg-red-950/30 border border-red-200 dark:border-red-900/60 rounded-3xl p-8 text-center space-y-3">
                    <span class="text-3xl">⚠️</span>
                    <h2 class="text-xl font-black text-red-900 dark:text-red-200">Affiliate Status: {{ status.toUpperCase() }}</h2>
                    <p class="text-xs text-red-800 dark:text-red-300/80">
                        {{ status === 'suspended' ? 'Your affiliate commissions and link are currently suspended. Please contact store customer service for assistance.' : 'Your affiliate application was not approved at this time.' }}
                    </p>
                </div>
            </div>

            <!-- STATE 4: APPROVED & ACTIVE AFFILIATE DASHBOARD -->
            <div v-else-if="status === 'approved'" class="space-y-6">
                <!-- Referral Link & Social Share Card -->
                <div class="bg-white dark:bg-gray-800 rounded-3xl border border-gray-100 dark:border-gray-700 p-6 sm:p-7 shadow-xs space-y-5">
                    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 pb-4 border-b dark:border-gray-700">
                        <div>
                            <span class="text-xs font-bold uppercase tracking-wider text-indigo-600 dark:text-indigo-400">Your Affiliate Link</span>
                            <h2 class="text-lg font-black text-gray-900 dark:text-white mt-0.5">Share and Earn</h2>
                        </div>
                        <div class="flex items-center gap-2">
                            <span class="text-xs font-semibold text-gray-400">Your Partner Code:</span>
                            <code class="px-2.5 py-1 rounded-lg font-mono font-black text-sm bg-indigo-50 dark:bg-indigo-950/50 text-indigo-600 dark:text-indigo-300 border border-indigo-200 dark:border-indigo-800">
                                {{ referral_code }}
                            </code>
                        </div>
                    </div>

                    <!-- Copy link input bar -->
                    <div class="flex flex-col sm:flex-row items-stretch gap-2.5">
                        <input
                            type="text"
                            readonly
                            :value="referral_url"
                            class="flex-1 rounded-2xl border border-gray-200 dark:border-gray-700 dark:bg-gray-900 px-4 py-3 text-xs sm:text-sm font-mono select-all text-gray-800 dark:text-gray-200"
                        />
                        <button
                            @click="copyReferralLink"
                            class="px-6 py-3 rounded-2xl bg-indigo-600 hover:bg-indigo-700 text-white font-bold text-xs sm:text-sm transition flex items-center justify-center gap-2 shadow-xs cursor-pointer shrink-0"
                        >
                            <span>{{ copied ? '✓ Copied!' : '📋 Copy Link' }}</span>
                        </button>
                    </div>

                    <!-- Direct Social Share buttons -->
                    <div class="flex flex-wrap items-center gap-2 pt-2">
                        <span class="text-xs font-bold text-gray-400 mr-1">Quick Share:</span>
                        <a
                            :href="whatsappShare"
                            target="_blank"
                            class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl bg-emerald-500 hover:bg-emerald-600 text-white text-xs font-bold transition shadow-2xs"
                        >
                            <span>💬 WhatsApp</span>
                        </a>
                        <a
                            :href="twitterShare"
                            target="_blank"
                            class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl bg-black hover:bg-gray-900 text-white text-xs font-bold transition shadow-2xs"
                        >
                            <span>𝕏 Post</span>
                        </a>
                        <a
                            :href="facebookShare"
                            target="_blank"
                            class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl bg-blue-600 hover:bg-blue-700 text-white text-xs font-bold transition shadow-2xs"
                        >
                            <span>Facebook</span>
                        </a>
                    </div>
                </div>

                <!-- Financial Balances & KPI Summary -->
                <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
                    <!-- Available Wallet -->
                    <div class="bg-gradient-to-br from-emerald-500 to-emerald-700 text-white p-5 rounded-3xl shadow-sm space-y-2 flex flex-col justify-between">
                        <div>
                            <p class="text-xs uppercase font-extrabold tracking-wider text-emerald-100">Available to Withdraw</p>
                            <p class="text-2xl sm:text-3xl font-black mt-1">₦{{ Number(stats.wallet_balance).toLocaleString('en-NG', { minimumFractionDigits: 2 }) }}</p>
                        </div>
                        <button
                            @click="showWithdrawModal = true"
                            :disabled="stats.wallet_balance < min_withdrawal || has_pending_withdrawal"
                            class="w-full py-2 px-3 rounded-xl bg-white text-emerald-800 font-black text-xs hover:bg-emerald-50 transition shadow-xs disabled:opacity-50 disabled:cursor-not-allowed cursor-pointer"
                        >
                            {{ has_pending_withdrawal ? 'Payout Pending...' : 'Withdraw Cash →' }}
                        </button>
                    </div>

                    <!-- Holding / Pending -->
                    <div class="bg-white dark:bg-gray-800 p-5 rounded-3xl border border-gray-100 dark:border-gray-700 shadow-xs flex flex-col justify-between">
                        <div>
                            <p class="text-xs uppercase font-semibold text-gray-400">Pending Holding</p>
                            <p class="text-2xl sm:text-3xl font-black text-amber-600 dark:text-amber-400 mt-1">
                                ₦{{ Number(stats.pending_earnings).toLocaleString('en-NG', { minimumFractionDigits: 2 }) }}
                            </p>
                        </div>
                        <span class="text-[11px] text-gray-400">Matures after order completion holding window</span>
                    </div>

                    <!-- Lifetime Earned -->
                    <div class="bg-white dark:bg-gray-800 p-5 rounded-3xl border border-gray-100 dark:border-gray-700 shadow-xs flex flex-col justify-between">
                        <div>
                            <p class="text-xs uppercase font-semibold text-gray-400">Lifetime Earned</p>
                            <p class="text-2xl sm:text-3xl font-black text-gray-900 dark:text-white mt-1">
                                ₦{{ Number(stats.total_earned).toLocaleString('en-NG', { minimumFractionDigits: 2 }) }}
                            </p>
                        </div>
                        <span class="text-[11px] text-gray-400">{{ stats.referred_orders }} orders referred</span>
                    </div>

                    <!-- Lifetime Withdrawn -->
                    <div class="bg-white dark:bg-gray-800 p-5 rounded-3xl border border-gray-100 dark:border-gray-700 shadow-xs flex flex-col justify-between">
                        <div>
                            <p class="text-xs uppercase font-semibold text-gray-400">Total Withdrawn</p>
                            <p class="text-2xl sm:text-3xl font-black text-gray-900 dark:text-white mt-1">
                                ₦{{ Number(stats.total_withdrawn).toLocaleString('en-NG', { minimumFractionDigits: 2 }) }}
                            </p>
                        </div>
                        <span class="text-[11px] text-gray-400">Disbursed to your bank</span>
                    </div>
                </div>

                <!-- Rate & Performance Bar -->
                <div class="bg-indigo-50 dark:bg-indigo-950/20 border border-indigo-100 dark:border-indigo-900/40 rounded-2xl p-4 flex flex-wrap items-center justify-between gap-4 text-xs">
                    <div class="flex items-center gap-2 flex-wrap">
                        <span class="font-bold text-indigo-900 dark:text-indigo-200">Your Commission Rate:</span>
                        <span class="px-2.5 py-1 rounded-lg bg-indigo-600 text-white font-black">
                            {{ commission_display.type === 'flat' ? `₦${Number(commission_display.rate).toLocaleString()} per Order` : `${commission_display.rate}% of Sales` }}
                        </span>
                        <span
                            v-if="Number(commission_display.min_order_amount) > 0"
                            class="px-2 py-0.5 rounded-md bg-amber-100 dark:bg-amber-950/60 text-amber-800 dark:text-amber-300 font-semibold text-[11px]"
                        >
                            Min. Order: ₦{{ Number(commission_display.min_order_amount).toLocaleString() }}
                        </span>
                        <span
                            v-else
                            class="text-gray-400 dark:text-gray-500 text-[11px]"
                        >
                            (All orders qualify)
                        </span>
                    </div>
                    <div class="flex items-center gap-4 text-indigo-700 dark:text-indigo-300">
                        <span><strong>{{ stats.visits_count }}</strong> Link Clicks</span>
                        <span>•</span>
                        <span><strong>{{ stats.referred_orders }}</strong> Referred Orders</span>
                        <span>•</span>
                        <span>Min Payout: <strong>₦{{ Number(min_withdrawal).toLocaleString() }}</strong></span>
                    </div>
                </div>

                <!-- Tabbed Data Tables -->
                <div class="flex items-center gap-2 border-b border-gray-200 dark:border-gray-700 pb-2">
                    <button
                        @click="activeTab = 'commissions'"
                        :class="activeTab === 'commissions' ? 'bg-indigo-600 text-white font-bold' : 'text-gray-600 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700'"
                        class="px-4 py-2 rounded-xl text-xs sm:text-sm transition cursor-pointer"
                    >
                        💰 Commissions ({{ commissions.total || 0 }})
                    </button>
                    <button
                        @click="activeTab = 'ledger'"
                        :class="activeTab === 'ledger' ? 'bg-indigo-600 text-white font-bold' : 'text-gray-600 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700'"
                        class="px-4 py-2 rounded-xl text-xs sm:text-sm transition cursor-pointer"
                    >
                        📒 Wallet Ledger
                    </button>
                    <button
                        @click="activeTab = 'withdrawals'"
                        :class="activeTab === 'withdrawals' ? 'bg-indigo-600 text-white font-bold' : 'text-gray-600 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700'"
                        class="px-4 py-2 rounded-xl text-xs sm:text-sm transition cursor-pointer"
                    >
                        💳 Payouts ({{ withdrawals.total || 0 }})
                    </button>
                </div>

                <!-- TAB 1: Commissions -->
                <div v-if="activeTab === 'commissions'" class="bg-white dark:bg-gray-800 rounded-3xl border border-gray-100 dark:border-gray-700 shadow-xs overflow-hidden">
                    <div class="overflow-x-auto">
                        <table class="w-full text-left text-sm">
                            <thead class="bg-gray-50/75 dark:bg-gray-900/50 text-xs uppercase text-gray-400 font-semibold border-b dark:border-gray-700">
                                <tr>
                                    <th class="px-5 py-3">Order Ref</th>
                                    <th class="px-5 py-3 text-right">Order Amount</th>
                                    <th class="px-5 py-3 text-right">Commission Earned</th>
                                    <th class="px-5 py-3 text-center">Status</th>
                                    <th class="px-5 py-3 text-right">Date</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100 dark:divide-gray-700/60">
                                <tr v-for="c in commissions.data" :key="c.id" class="hover:bg-gray-50 dark:hover:bg-gray-700/40">
                                    <td class="px-5 py-3.5 font-bold text-gray-900 dark:text-white">
                                        {{ c.order?.tracking_number || `#${c.order_id}` }}
                                    </td>
                                    <td class="px-5 py-3.5 text-right font-medium">
                                        ₦{{ Number(c.order_amount_base).toLocaleString('en-NG', { minimumFractionDigits: 2 }) }}
                                    </td>
                                    <td class="px-5 py-3.5 text-right font-black text-emerald-600 dark:text-emerald-400">
                                        +₦{{ Number(c.commission_amount).toLocaleString('en-NG', { minimumFractionDigits: 2 }) }}
                                    </td>
                                    <td class="px-5 py-3.5 text-center">
                                        <span
                                            :class="{
                                                'bg-emerald-100 text-emerald-800 dark:bg-emerald-950/40 dark:text-emerald-300': c.status === 'available',
                                                'bg-amber-100 text-amber-800 dark:bg-amber-950/40 dark:text-amber-300': c.status === 'pending',
                                                'bg-red-100 text-red-800 dark:bg-red-950/40 dark:text-red-300': c.status === 'reversed',
                                            }"
                                            class="px-2.5 py-0.5 rounded-full text-xs font-bold capitalize"
                                        >
                                            {{ c.status }}
                                        </span>
                                    </td>
                                    <td class="px-5 py-3.5 text-right text-xs text-gray-400">
                                        {{ new Date(c.created_at).toLocaleDateString() }}
                                    </td>
                                </tr>
                                <tr v-if="!commissions.data.length">
                                    <td colspan="5" class="px-5 py-12 text-center text-xs text-gray-400">
                                        No sales referred yet. Start sharing your link to earn!
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    <div v-if="commissions.links && commissions.data.length" class="p-4 border-t dark:border-gray-700">
                        <Pagination :paginator="commissions" />
                    </div>
                </div>

                <!-- TAB 2: Wallet Ledger -->
                <div v-if="activeTab === 'ledger'" class="bg-white dark:bg-gray-800 rounded-3xl border border-gray-100 dark:border-gray-700 shadow-xs overflow-hidden">
                    <div class="overflow-x-auto">
                        <table class="w-full text-left text-sm">
                            <thead class="bg-gray-50/75 dark:bg-gray-900/50 text-xs uppercase text-gray-400 font-semibold border-b dark:border-gray-700">
                                <tr>
                                    <th class="px-5 py-3">Description</th>
                                    <th class="px-5 py-3 text-right">Amount</th>
                                    <th class="px-5 py-3 text-right">Balance After</th>
                                    <th class="px-5 py-3 text-right">Date</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100 dark:divide-gray-700/60">
                                <tr v-for="t in transactions.data" :key="t.id" class="hover:bg-gray-50 dark:hover:bg-gray-700/40">
                                    <td class="px-5 py-3.5 font-medium text-gray-900 dark:text-white">{{ t.description }}</td>
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
                                    <td colspan="4" class="px-5 py-12 text-center text-xs text-gray-400">
                                        No ledger transactions yet.
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    <div v-if="transactions.links && transactions.data.length" class="p-4 border-t dark:border-gray-700">
                        <Pagination :paginator="transactions" />
                    </div>
                </div>

                <!-- TAB 3: Withdrawals -->
                <div v-if="activeTab === 'withdrawals'" class="bg-white dark:bg-gray-800 rounded-3xl border border-gray-100 dark:border-gray-700 shadow-xs overflow-hidden">
                    <div class="overflow-x-auto">
                        <table class="w-full text-left text-sm">
                            <thead class="bg-gray-50/75 dark:bg-gray-900/50 text-xs uppercase text-gray-400 font-semibold border-b dark:border-gray-700">
                                <tr>
                                    <th class="px-5 py-3">Reference</th>
                                    <th class="px-5 py-3 text-right">Amount</th>
                                    <th class="px-5 py-3">Destination Bank</th>
                                    <th class="px-5 py-3 text-center">Status</th>
                                    <th class="px-5 py-3 text-right">Date</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100 dark:divide-gray-700/60">
                                <tr v-for="w in withdrawals.data" :key="w.id" class="hover:bg-gray-50 dark:hover:bg-gray-700/40">
                                    <td class="px-5 py-3.5 font-mono text-xs">
                                        <p class="font-bold text-gray-900 dark:text-white">#{{ w.id }}</p>
                                        <p v-if="w.payment_reference" class="text-emerald-600 font-semibold mt-0.5">Ref: {{ w.payment_reference }}</p>
                                    </td>
                                    <td class="px-5 py-3.5 text-right font-black text-gray-900 dark:text-white">
                                        ₦{{ Number(w.amount).toLocaleString('en-NG', { minimumFractionDigits: 2 }) }}
                                    </td>
                                    <td class="px-5 py-3.5 text-xs text-gray-600 dark:text-gray-300">
                                        <p class="font-bold">{{ w.bank_name }}</p>
                                        <p class="font-mono text-gray-400">{{ w.account_number }} ({{ w.account_name }})</p>
                                    </td>
                                    <td class="px-5 py-3.5 text-center">
                                        <span
                                            :class="{
                                                'bg-emerald-100 text-emerald-800 dark:bg-emerald-950/40 dark:text-emerald-300': w.status === 'paid',
                                                'bg-amber-100 text-amber-800 dark:bg-amber-950/40 dark:text-amber-300': w.status === 'pending',
                                                'bg-red-100 text-red-800 dark:bg-red-950/40 dark:text-red-300': w.status === 'rejected',
                                            }"
                                            class="px-2.5 py-0.5 rounded-full text-xs font-bold capitalize"
                                        >
                                            {{ w.status }}
                                        </span>
                                    </td>
                                    <td class="px-5 py-3.5 text-right text-xs text-gray-400">
                                        {{ new Date(w.created_at).toLocaleDateString() }}
                                    </td>
                                </tr>
                                <tr v-if="!withdrawals.data.length">
                                    <td colspan="5" class="px-5 py-12 text-center text-xs text-gray-400">
                                        No withdrawal requests yet.
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
        </div>

        <!-- Request Withdrawal Modal -->
        <div v-if="showWithdrawModal" class="fixed inset-0 z-50 bg-black/50 backdrop-blur-xs flex items-center justify-center p-4">
            <div class="bg-white dark:bg-gray-800 rounded-3xl max-w-md w-full p-6 sm:p-7 shadow-2xl border dark:border-gray-700 space-y-4">
                <div class="flex items-center justify-between border-b dark:border-gray-700 pb-3">
                    <h3 class="text-lg font-black text-gray-900 dark:text-white flex items-center gap-2">
                        <span>💳</span> Request Bank Withdrawal
                    </h3>
                    <button @click="showWithdrawModal = false" class="text-gray-400 hover:text-gray-600">✕</button>
                </div>

                <div class="bg-indigo-50 dark:bg-indigo-950/40 border border-indigo-200 dark:border-indigo-800/60 rounded-2xl p-4 text-xs text-indigo-900 dark:text-indigo-200">
                    <p class="font-bold">Available Balance: ₦{{ Number(stats.wallet_balance).toLocaleString('en-NG', { minimumFractionDigits: 2 }) }}</p>
                    <p class="mt-0.5 text-[11px] text-indigo-700 dark:text-indigo-300">Minimum withdrawal amount is ₦{{ Number(min_withdrawal).toLocaleString() }}.</p>
                </div>

                <form @submit.prevent="submitWithdrawal" class="space-y-4">
                    <div>
                        <label class="block text-xs font-bold uppercase tracking-wider text-gray-700 dark:text-gray-300 mb-1">
                            Amount to Withdraw (₦) *
                        </label>
                        <input
                            type="number"
                            step="100"
                            :min="min_withdrawal"
                            :max="stats.wallet_balance"
                            required
                            v-model="withdrawForm.amount"
                            class="w-full rounded-xl border border-gray-300 dark:border-gray-600 dark:bg-gray-700 px-3.5 py-2.5 text-base font-black text-gray-900 dark:text-white"
                        />
                    </div>

                    <div>
                        <label class="block text-xs font-bold uppercase tracking-wider text-gray-700 dark:text-gray-300 mb-1">Bank Name *</label>
                        <input
                            type="text"
                            required
                            v-model="withdrawForm.bank_name"
                            placeholder="e.g. GTBank, Zenith, Kuda"
                            class="w-full rounded-xl border border-gray-300 dark:border-gray-600 dark:bg-gray-700 px-3.5 py-2 text-xs"
                        />
                    </div>

                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-xs font-bold uppercase tracking-wider text-gray-700 dark:text-gray-300 mb-1">Account Number *</label>
                            <input
                                type="text"
                                required
                                maxlength="10"
                                v-model="withdrawForm.account_number"
                                placeholder="10-digit NUBAN"
                                class="w-full rounded-xl border border-gray-300 dark:border-gray-600 dark:bg-gray-700 px-3.5 py-2 text-xs font-mono"
                            />
                        </div>
                        <div>
                            <label class="block text-xs font-bold uppercase tracking-wider text-gray-700 dark:text-gray-300 mb-1">Account Name *</label>
                            <input
                                type="text"
                                required
                                v-model="withdrawForm.account_name"
                                placeholder="Account Name"
                                class="w-full rounded-xl border border-gray-300 dark:border-gray-600 dark:bg-gray-700 px-3.5 py-2 text-xs"
                            />
                        </div>
                    </div>

                    <div class="flex justify-end gap-2 pt-3 border-t dark:border-gray-700">
                        <button type="button" @click="showWithdrawModal = false" class="px-4 py-2 rounded-xl border border-gray-300 dark:border-gray-600 text-xs font-bold">
                            Cancel
                        </button>
                        <button
                            type="submit"
                            :disabled="withdrawForm.processing"
                            class="px-5 py-2 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-bold transition shadow-xs"
                        >
                            {{ withdrawForm.processing ? 'Submitting...' : 'Confirm Withdrawal Request' }}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </Layout>
</template>

