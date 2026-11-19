<script setup>
import { Head, useForm } from '@inertiajs/vue3';
import Layout from '@/Layouts/Layout.vue';
import { ref, computed, nextTick, watch, onMounted } from 'vue';

const props = defineProps({
    order: Object,
    error: String,
    searched: String,
    settings: Object,
});

const form = useForm({
    tracking_input: props.searched || '',
    email: '',
});

const doScroll = () => {
    const el = document.getElementById('tracking-result') || document.getElementById('tracking-error');
    if (!el) return false;

    const nav = document.querySelector('header') || document.querySelector('nav');
    const navHeight = nav ? (nav.offsetHeight + 20) : 90;
    const rect = el.getBoundingClientRect();
    const currentScroll = window.scrollY || window.pageYOffset || document.documentElement.scrollTop || 0;
    const targetTop = rect.top + currentScroll - navHeight;

    window.scrollTo({
        top: Math.max(0, targetTop),
        behavior: 'smooth'
    });

    if (typeof el.scrollIntoView === 'function') {
        el.scrollIntoView({ behavior: 'smooth', block: 'start' });
    }
    return true;
};

const triggerScrollSweep = () => {
    nextTick(() => {
        doScroll();
        setTimeout(doScroll, 80);
        setTimeout(doScroll, 200);
        setTimeout(doScroll, 400);
        setTimeout(doScroll, 800);
    });
};

const submit = () => {
    form.post(route('order.track.search'), {
        preserveScroll: true,
        onSuccess: () => {
            triggerScrollSweep();
        },
        onError: () => {
            triggerScrollSweep();
        },
        onFinish: () => {
            triggerScrollSweep();
        },
    });
};

watch(
    () => [props.order, props.error],
    ([newOrder, newError]) => {
        if (newOrder || newError) {
            triggerScrollSweep();
        }
    },
    { deep: true }
);

onMounted(() => {
    if (props.order || props.error) {
        triggerScrollSweep();
    }
});

// Copy tracking link to clipboard
const copied = ref(false);
const copyLink = () => {
    if (!props.order?.tracking_number) return;
    const url = window.location.origin + '/track/' + props.order.tracking_number;
    navigator.clipboard.writeText(url).then(() => {
        copied.value = true;
        setTimeout(() => { copied.value = false; }, 2500);
    });
};

// Progress steps
const steps = ['pending', 'processing', 'shipped', 'completed'];
const stepLabels = { pending: 'Order Placed', processing: 'Processing', shipped: 'Shipped', completed: 'Delivered' };

const getStepIndex = (status) => {
    if (status === 'cancelled') return -1;
    if (status === 'paid') return 1;
    return steps.indexOf(status);
};

const progressWidth = computed(() => {
    const idx = getStepIndex(props.order?.status);
    return ['0%', '33%', '66%', '100%'][idx] || '0%';
});

const trackingPrefix = computed(() => {
    let p = String(props.settings?.tracking_number_prefix || 'SKY-').trim();
    if (!p.endsWith('-') && !p.endsWith('_')) p += '-';
    return p.toUpperCase();
});
</script>

<template>
    <Head title="Track Your Order" />
    <Layout>
        <div class="min-h-screen bg-gray-50 dark:bg-gray-900 py-16">
            <div class="container mx-auto px-4 max-w-3xl">

                <!-- Header -->
                <div class="text-center mb-12">
                    <div class="w-16 h-16 bg-primary/10 rounded-full flex items-center justify-center mx-auto mb-4">
                        <svg class="w-8 h-8 text-primary" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01"/>
                        </svg>
                    </div>
                    <h1 class="text-4xl font-extrabold text-gray-900 dark:text-white mb-2">Track Your Order</h1>
                    <p class="text-gray-600 dark:text-gray-400">Enter your Tracking Number (e.g. {{ trackingPrefix }}AB12CD34) or Order ID</p>
                </div>

                <!-- Search Form -->
                <form @submit.prevent="submit" class="bg-white dark:bg-gray-800 rounded-2xl shadow-sm border dark:border-gray-700 p-8 mb-8">
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 mb-6">
                        <div class="sm:col-span-2">
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                Tracking Number or Order ID *
                            </label>
                            <input type="text" v-model="form.tracking_input" required
                                :placeholder="'e.g. ' + trackingPrefix + 'AB12CD34  or  1234'"
                                class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 px-4 py-3 text-lg font-mono uppercase tracking-widest focus:ring-primary focus:border-primary" />
                            <p v-if="form.errors.tracking_input" class="text-red-500 text-xs mt-1">{{ form.errors.tracking_input }}</p>
                        </div>
                        <div class="sm:col-span-2">
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                Email (optional — for additional security)
                            </label>
                            <input type="email" v-model="form.email"
                                placeholder="Email used at checkout"
                                class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 px-4 py-3 focus:ring-primary focus:border-primary" />
                        </div>
                    </div>
                    <button type="submit" :disabled="form.processing"
                        class="w-full py-3 rounded-xl bg-primary hover:opacity-90 text-white font-bold text-lg transition disabled:opacity-60 shadow-md">
                        {{ form.processing ? 'Searching...' : '🔍 Track Order' }}
                    </button>
                </form>

                <!-- Error -->
                <div v-if="error" id="tracking-error" class="scroll-mt-24 sm:scroll-mt-28 bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-700 text-red-700 dark:text-red-400 rounded-xl p-6 text-center mb-8">
                    <svg class="w-8 h-8 mx-auto mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    <p class="font-semibold">{{ error }}</p>
                    <p class="text-sm mt-1 opacity-75">Make sure you're entering the correct tracking number or Order ID.</p>
                </div>

                <!-- Order Result -->
                <div v-if="order" id="tracking-result" class="scroll-mt-24 sm:scroll-mt-28 space-y-6">

                    <!-- Tracking Number Card -->
                    <div class="bg-gradient-to-r from-[var(--color-primary)] to-[var(--color-secondary-500,var(--color-primary))] text-white rounded-2xl p-6 flex flex-col sm:flex-row sm:items-center justify-between gap-4 shadow-lg">
                        <div>
                            <p class="text-white/80 text-sm font-medium uppercase tracking-wider mb-1">Tracking Number</p>
                            <p class="text-3xl font-black font-mono tracking-widest">{{ order.tracking_number }}</p>
                            <p class="text-white/80 text-sm mt-1">Order #{{ order.id }} · {{ new Date(order.created_at).toLocaleDateString('en-NG', { day:'numeric', month:'long', year:'numeric' }) }}</p>
                        </div>
                        <div class="flex flex-col gap-2">
                            <!-- Copy shareable link -->
                            <button @click="copyLink"
                                class="flex items-center gap-2 bg-white/20 hover:bg-white/30 text-white font-semibold px-4 py-2 rounded-lg transition text-sm">
                                <svg v-if="!copied" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 5H6a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2v-1M8 5a2 2 0 002 2h2a2 2 0 002-2M8 5a2 2 0 012-2h2a2 2 0 012 2m0 0h2a2 2 0 012 2v3m2 4H10m0 0l3-3m-3 3l3 3"/></svg>
                                <svg v-else class="w-4 h-4 text-green-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                                {{ copied ? 'Link Copied!' : 'Copy Tracking Link' }}
                            </button>
                        </div>
                    </div>

                    <!-- Status Banner + Progress Tracker -->
                    <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-sm border dark:border-gray-700 overflow-hidden">
                        <div class="p-6 border-b dark:border-gray-700 flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                            <h2 class="text-xl font-bold text-gray-900 dark:text-white">Order Status</h2>
                            <div class="flex flex-wrap items-center gap-2">
                                <span class="px-3.5 py-1.5 rounded-full font-bold text-xs uppercase tracking-wider border"
                                    :class="{
                                        'bg-emerald-100 text-emerald-800 border-emerald-200 dark:bg-emerald-950/60 dark:text-emerald-300 dark:border-emerald-800': order.payment_status === 'paid',
                                        'bg-amber-100 text-amber-800 border-amber-200 dark:bg-amber-950/60 dark:text-amber-300 dark:border-amber-800': order.payment_status === 'pending',
                                        'bg-rose-100 text-rose-800 border-rose-200 dark:bg-rose-950/60 dark:text-rose-300 dark:border-rose-800': order.payment_status === 'failed'
                                    }">
                                    {{ order.payment_status === 'paid' ? '✓ Payment: Paid' : (order.payment_status === 'failed' ? '✕ Payment: Failed' : (['cod', 'pod'].includes(order.payment_method) ? '💵 Pay on Delivery' : '⏳ Payment: Pending')) }}
                                </span>
                                <span class="px-4 py-1.5 rounded-full font-bold text-sm uppercase tracking-wider"
                                    :class="{
                                        'bg-yellow-100 text-yellow-800': order.status === 'pending',
                                        'bg-blue-100 text-blue-800 dark:bg-blue-950/60 dark:text-blue-300': order.status === 'processing' || order.status === 'paid',
                                        'bg-purple-100 text-purple-800': order.status === 'shipped',
                                        'bg-green-100 text-green-800': order.status === 'completed',
                                        'bg-red-100 text-red-800': order.status === 'cancelled',
                                    }">
                                    {{ order.status === 'paid' ? 'processing' : order.status }}
                                </span>
                            </div>
                        </div>

                        <!-- Cancelled -->
                        <div v-if="order.status === 'cancelled'" class="p-6 text-center text-red-600 dark:text-red-400 font-semibold">
                            ❌ This order has been cancelled.
                        </div>

                        <!-- Progress bar -->
                        <div v-else class="p-6">
                            <div class="relative flex items-start justify-between">
                                <div class="absolute top-5 left-0 right-0 h-1 bg-gray-200 dark:bg-gray-700 z-0 rounded-full"></div>
                                <div class="absolute top-5 left-0 h-1 bg-primary z-0 transition-all duration-700 rounded-full" :style="{ width: progressWidth }"></div>
                                <div v-for="(step, i) in steps" :key="step" class="relative z-10 flex flex-col items-center flex-1">
                                    <div :class="['w-10 h-10 rounded-full flex items-center justify-center border-2 text-sm font-bold transition-all', i <= getStepIndex(order.status) ? 'bg-primary border-primary text-white shadow-lg' : 'bg-white dark:bg-gray-800 border-gray-300 dark:border-gray-600 text-gray-400']">
                                        <svg v-if="i <= getStepIndex(order.status)" class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="3" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                                        </svg>
                                        <span v-else>{{ i + 1 }}</span>
                                    </div>
                                    <p class="mt-2 text-xs font-semibold text-center px-1 leading-tight"
                                        :class="i <= getStepIndex(order.status) ? 'text-primary font-bold' : 'text-gray-400'">
                                        {{ stepLabels[step] }}
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Order Items -->
                    <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-sm border dark:border-gray-700 overflow-hidden">
                        <div class="p-5 border-b dark:border-gray-700 bg-gray-50 dark:bg-gray-900/50">
                            <h3 class="font-bold text-lg">Items in Your Order</h3>
                        </div>
                        <div class="divide-y dark:divide-gray-700">
                            <div v-for="item in order.items" :key="item.id" class="flex items-center gap-4 p-5">
                                <img :src="item.product?.image ? '/storage/' + item.product.image : 'https://placehold.co/64x64?text=P'"
                                    class="w-16 h-16 object-cover rounded-lg border dark:border-gray-700 flex-shrink-0" />
                                <div class="flex-1 min-w-0">
                                    <div class="flex flex-wrap items-center gap-2">
                                        <p class="font-semibold">{{ item.product?.name ?? 'Product' }}</p>
                                        <span v-if="item.unit_name" class="inline-flex items-center px-2 py-0.5 rounded text-xs font-bold bg-primary/10 text-primary border border-primary/20">
                                            {{ item.unit_name }}
                                        </span>
                                    </div>
                                    <div class="flex flex-wrap items-center gap-2 text-xs text-gray-500 dark:text-gray-400 mt-1">
                                        <span>Qty: {{ item.quantity }}</span>
                                        <span v-if="item.unit_name">• Selling Unit: <strong class="text-gray-700 dark:text-gray-300">{{ item.unit_name }}</strong></span>
                                        <span v-if="item.variant || item.variant_label">• Variant: {{ item.variant_label || item.variant }}</span>
                                    </div>
                                </div>
                                <p class="font-bold text-primary whitespace-nowrap">₦{{ (item.price * item.quantity).toLocaleString() }}</p>
                            </div>
                        </div>
                        <div class="p-5 bg-gray-50 dark:bg-gray-900/50 flex justify-between font-extrabold text-lg border-t dark:border-gray-700">
                            <span>Total</span>
                            <span class="text-primary">₦{{ parseFloat(order.total_amount).toLocaleString() }}</span>
                        </div>
                    </div>

                    <!-- Billing Info -->
                    <div v-if="order.billing_name || order.billing_email" class="bg-white dark:bg-gray-800 rounded-2xl shadow-sm border dark:border-gray-700 p-6">
                        <h3 class="font-bold text-lg mb-4">Billing Details</h3>
                        <div class="grid grid-cols-2 sm:grid-cols-3 gap-4 text-sm">
                            <div v-if="order.billing_name">
                                <p class="text-gray-500 dark:text-gray-400">Name</p>
                                <p class="font-semibold">{{ order.billing_name }}</p>
                            </div>
                            <div v-if="order.billing_email">
                                <p class="text-gray-500 dark:text-gray-400">Email</p>
                                <p class="font-semibold">{{ order.billing_email }}</p>
                            </div>
                            <div v-if="order.billing_phone">
                                <p class="text-gray-500 dark:text-gray-400">Phone</p>
                                <p class="font-semibold">{{ order.billing_phone }}</p>
                            </div>
                            <div v-if="order.delivery_method">
                                <p class="text-gray-500 dark:text-gray-400">Delivery</p>
                                <p class="font-semibold capitalize">{{ order.delivery_method }}</p>
                            </div>
                        </div>
                    </div>

                </div>
            </div>
        </div>
    </Layout>
</template>
