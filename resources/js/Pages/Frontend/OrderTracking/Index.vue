<script setup>
import { Head, useForm } from '@inertiajs/vue3';
import Layout from '@/Layouts/Layout.vue';
import { ref, computed } from 'vue';

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

const submit = () => form.post(route('order.track.search'));

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
const steps = ['pending', 'paid', 'shipped', 'completed'];
const stepLabels = { pending: 'Order Placed', paid: 'Payment Confirmed', shipped: 'Shipped', completed: 'Delivered' };

const getStepIndex = (status) => {
    if (status === 'cancelled') return -1;
    return steps.indexOf(status);
};

const progressWidth = computed(() => {
    const idx = getStepIndex(props.order?.status);
    return ['0%', '33%', '66%', '100%'][idx] || '0%';
});
</script>

<template>
    <Head title="Track Your Order" />
    <Layout>
        <div class="min-h-screen bg-gray-50 dark:bg-gray-900 py-16">
            <div class="container mx-auto px-4 max-w-3xl">

                <!-- Header -->
                <div class="text-center mb-12">
                    <div class="w-16 h-16 bg-blue-100 dark:bg-blue-900/30 rounded-full flex items-center justify-center mx-auto mb-4">
                        <svg class="w-8 h-8 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01"/>
                        </svg>
                    </div>
                    <h1 class="text-4xl font-extrabold text-gray-900 dark:text-white mb-2">Track Your Order</h1>
                    <p class="text-gray-600 dark:text-gray-400">Enter your Tracking Number (e.g. SKY-AB12CD34) or Order ID</p>
                </div>

                <!-- Search Form -->
                <form @submit.prevent="submit" class="bg-white dark:bg-gray-800 rounded-2xl shadow-sm border dark:border-gray-700 p-8 mb-8">
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 mb-6">
                        <div class="sm:col-span-2">
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                Tracking Number or Order ID *
                            </label>
                            <input type="text" v-model="form.tracking_input" required
                                placeholder="e.g. SKY-AB12CD34  or  1234"
                                class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 px-4 py-3 text-lg font-mono uppercase tracking-widest focus:ring-blue-500 focus:border-blue-500" />
                            <p v-if="form.errors.tracking_input" class="text-red-500 text-xs mt-1">{{ form.errors.tracking_input }}</p>
                        </div>
                        <div class="sm:col-span-2">
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                Email (optional — for additional security)
                            </label>
                            <input type="email" v-model="form.email"
                                placeholder="Email used at checkout"
                                class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 px-4 py-3 focus:ring-blue-500 focus:border-blue-500" />
                        </div>
                    </div>
                    <button type="submit" :disabled="form.processing"
                        class="w-full py-3 rounded-xl bg-blue-600 hover:bg-blue-700 text-white font-bold text-lg transition disabled:opacity-60">
                        {{ form.processing ? 'Searching...' : '🔍 Track Order' }}
                    </button>
                </form>

                <!-- Error -->
                <div v-if="error" class="bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-700 text-red-700 dark:text-red-400 rounded-xl p-6 text-center mb-8">
                    <svg class="w-8 h-8 mx-auto mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    <p class="font-semibold">{{ error }}</p>
                    <p class="text-sm mt-1 opacity-75">Make sure you're entering the correct tracking number or Order ID.</p>
                </div>

                <!-- Order Result -->
                <div v-if="order" class="space-y-6">

                    <!-- Tracking Number Card -->
                    <div class="bg-gradient-to-r from-blue-600 to-blue-700 text-white rounded-2xl p-6 flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                        <div>
                            <p class="text-blue-200 text-sm font-medium uppercase tracking-wider mb-1">Tracking Number</p>
                            <p class="text-3xl font-black font-mono tracking-widest">{{ order.tracking_number }}</p>
                            <p class="text-blue-200 text-sm mt-1">Order #{{ order.id }} · {{ new Date(order.created_at).toLocaleDateString('en-NG', { day:'numeric', month:'long', year:'numeric' }) }}</p>
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
                            <span class="px-4 py-2 rounded-full font-bold text-sm uppercase tracking-wider"
                                :class="{
                                    'bg-yellow-100 text-yellow-800': order.status === 'pending',
                                    'bg-blue-100 text-blue-800': order.status === 'paid',
                                    'bg-purple-100 text-purple-800': order.status === 'shipped',
                                    'bg-green-100 text-green-800': order.status === 'completed',
                                    'bg-red-100 text-red-800': order.status === 'cancelled',
                                }">
                                {{ order.status }}
                            </span>
                        </div>

                        <!-- Cancelled -->
                        <div v-if="order.status === 'cancelled'" class="p-6 text-center text-red-600 dark:text-red-400 font-semibold">
                            ❌ This order has been cancelled.
                        </div>

                        <!-- Progress bar -->
                        <div v-else class="p-6">
                            <div class="relative flex items-start justify-between">
                                <div class="absolute top-5 left-0 right-0 h-1 bg-gray-200 dark:bg-gray-700 z-0 rounded-full"></div>
                                <div class="absolute top-5 left-0 h-1 bg-blue-500 z-0 transition-all duration-700 rounded-full" :style="{ width: progressWidth }"></div>
                                <div v-for="(step, i) in steps" :key="step" class="relative z-10 flex flex-col items-center flex-1">
                                    <div :class="['w-10 h-10 rounded-full flex items-center justify-center border-2 text-sm font-bold transition-all', i <= getStepIndex(order.status) ? 'bg-blue-600 border-blue-600 text-white shadow-lg' : 'bg-white dark:bg-gray-800 border-gray-300 dark:border-gray-600 text-gray-400']">
                                        {{ i + 1 }}
                                    </div>
                                    <p class="mt-2 text-xs font-semibold text-center px-1 leading-tight"
                                        :class="i <= getStepIndex(order.status) ? 'text-blue-600 dark:text-blue-400' : 'text-gray-400'">
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
                                    <p class="font-semibold truncate">{{ item.product?.name ?? 'Product' }}</p>
                                    <p class="text-sm text-gray-500 dark:text-gray-400">Qty: {{ item.quantity }}</p>
                                </div>
                                <p class="font-bold text-blue-600 dark:text-blue-400 whitespace-nowrap">₦{{ (item.price * item.quantity).toLocaleString() }}</p>
                            </div>
                        </div>
                        <div class="p-5 bg-gray-50 dark:bg-gray-900/50 flex justify-between font-extrabold text-lg border-t dark:border-gray-700">
                            <span>Total</span>
                            <span class="text-blue-600 dark:text-blue-400">₦{{ parseFloat(order.total_amount).toLocaleString() }}</span>
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
