<script setup>
import { Head, Link } from '@inertiajs/vue3';
import Layout from '@/Layouts/Layout.vue';

const props = defineProps({
    order: Object,
});

const steps = ['pending', 'paid', 'shipped', 'completed'];
const stepLabels = { pending: 'Order Placed', paid: 'Payment Confirmed', shipped: 'Shipped', completed: 'Delivered' };

const getStepIndex = (status) => {
    if (status === 'cancelled') return -1;
    return steps.indexOf(status);
};

const statusBadgeClass = (status) => ({
    pending:   'bg-yellow-100 text-yellow-800 border-yellow-200',
    paid:      'bg-blue-100 text-blue-800 border-blue-200',
    shipped:   'bg-purple-100 text-purple-800 border-purple-200',
    completed: 'bg-green-100 text-green-800 border-green-200',
    cancelled: 'bg-red-100 text-red-800 border-red-200',
}[status] || '');
</script>

<template>
    <Head :title="'Order #' + order.id" />
    <Layout>
        <div class="container mx-auto px-4 py-12 max-w-5xl">
            <!-- Header -->
            <div class="flex items-center gap-4 mb-8">
                <Link :href="route('customer.orders')" class="text-gray-400 hover:text-blue-600 transition">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
                </Link>
                <div class="flex-1">
                    <h1 class="text-3xl font-extrabold text-gray-900 dark:text-white">Order #{{ order.id }}</h1>
                    <p class="text-gray-500 dark:text-gray-400 text-sm mt-1">
                        Placed {{ new Date(order.created_at).toLocaleDateString('en-NG', { day:'numeric', month:'long', year:'numeric' }) }}
                    </p>
                </div>
                <span :class="['px-4 py-2 rounded-full font-bold text-sm uppercase tracking-wider border', statusBadgeClass(order.status)]">
                    {{ order.status }}
                </span>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
                <!-- Left: Items + Tracker -->
                <div class="lg:col-span-2 space-y-6">
                    <!-- Progress Tracker -->
                    <div v-if="order.status !== 'cancelled'" class="bg-white dark:bg-gray-800 rounded-2xl border dark:border-gray-700 p-6">
                        <h2 class="font-bold text-lg mb-6">Order Progress</h2>
                        <div class="relative flex items-start justify-between">
                            <div class="absolute top-5 left-0 right-0 h-1 bg-gray-200 dark:bg-gray-700 z-0"></div>
                            <div class="absolute top-5 left-0 h-1 bg-blue-500 z-0 transition-all duration-700"
                                 :style="{ width: ['0%','33%','66%','100%'][getStepIndex(order.status)] || '0%' }"></div>
                            <div v-for="(step, i) in steps" :key="step" class="relative z-10 flex flex-col items-center flex-1">
                                <div :class="['w-10 h-10 rounded-full flex items-center justify-center border-2 transition-all font-bold text-sm', i <= getStepIndex(order.status) ? 'bg-blue-600 border-blue-600 text-white' : 'bg-white dark:bg-gray-800 border-gray-300 dark:border-gray-600 text-gray-400']">
                                    {{ i + 1 }}
                                </div>
                                <p class="mt-2 text-xs font-semibold text-center leading-tight px-1" :class="i <= getStepIndex(order.status) ? 'text-blue-600 dark:text-blue-400' : 'text-gray-400'">
                                    {{ stepLabels[step] }}
                                </p>
                            </div>
                        </div>
                    </div>

                    <div v-else class="bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-700 rounded-2xl p-6 text-center text-red-700 dark:text-red-400 font-semibold">
                        ❌ This order has been cancelled.
                    </div>

                    <!-- Order Items -->
                    <div class="bg-white dark:bg-gray-800 rounded-2xl border dark:border-gray-700 overflow-hidden">
                        <div class="p-5 border-b dark:border-gray-700 bg-gray-50 dark:bg-gray-900/50">
                            <h2 class="font-bold text-lg">Order Items</h2>
                        </div>
                        <div class="divide-y dark:divide-gray-700">
                            <div v-for="item in order.items" :key="item.id" class="flex items-center gap-4 p-5">
                                <img :src="item.product?.image ? '/storage/'+item.product.image : 'https://placehold.co/64x64?text=P'"
                                    class="w-16 h-16 object-cover rounded-lg border dark:border-gray-700" />
                                <div class="flex-1">
                                    <p class="font-semibold">{{ item.product?.name ?? 'Product' }}</p>
                                    <p class="text-sm text-gray-500 dark:text-gray-400 mt-0.5">Unit price: ₦{{ parseFloat(item.price).toLocaleString() }}</p>
                                </div>
                                <div class="text-right">
                                    <p class="text-sm text-gray-500 dark:text-gray-400">Qty: {{ item.quantity }}</p>
                                    <p class="font-bold text-blue-600 dark:text-blue-400">₦{{ (item.price * item.quantity).toLocaleString() }}</p>
                                </div>
                            </div>
                        </div>
                        <div class="p-5 bg-gray-50 dark:bg-gray-900/50 flex justify-between font-extrabold text-lg border-t dark:border-gray-700">
                            <span>Total</span>
                            <span class="text-blue-600 dark:text-blue-400">₦{{ parseFloat(order.total_amount).toLocaleString() }}</span>
                        </div>
                    </div>
                </div>

                <!-- Right: Info Cards -->
                <div class="space-y-6">
                    <!-- Billing Info -->
                    <div class="bg-white dark:bg-gray-800 rounded-2xl border dark:border-gray-700 overflow-hidden">
                        <div class="p-4 border-b dark:border-gray-700 bg-gray-50 dark:bg-gray-900/50">
                            <h2 class="font-bold">Billing Information</h2>
                        </div>
                        <div class="p-5 space-y-3 text-sm">
                            <div>
                                <p class="text-gray-500 dark:text-gray-400">Name</p>
                                <p class="font-semibold">{{ order.billing_name || order.customer?.name || '—' }}</p>
                            </div>
                            <div>
                                <p class="text-gray-500 dark:text-gray-400">Email</p>
                                <p class="font-semibold">{{ order.billing_email || order.customer?.email || order.guest_email || '—' }}</p>
                            </div>
                            <div v-if="order.billing_phone">
                                <p class="text-gray-500 dark:text-gray-400">Phone</p>
                                <p class="font-semibold">{{ order.billing_phone }}</p>
                            </div>
                            <div v-if="order.billing_address">
                                <p class="text-gray-500 dark:text-gray-400">Address</p>
                                <p class="font-semibold">{{ order.billing_address }}</p>
                            </div>
                            <div>
                                <p class="text-gray-500 dark:text-gray-400">Delivery</p>
                                <p class="font-semibold capitalize">{{ order.delivery_method || 'delivery' }}</p>
                            </div>
                        </div>
                    </div>

                    <!-- Payment Info -->
                    <div class="bg-white dark:bg-gray-800 rounded-2xl border dark:border-gray-700 overflow-hidden">
                        <div class="p-4 border-b dark:border-gray-700 bg-gray-50 dark:bg-gray-900/50">
                            <h2 class="font-bold">Payment</h2>
                        </div>
                        <div class="p-5 space-y-3 text-sm">
                            <div>
                                <p class="text-gray-500 dark:text-gray-400">Method</p>
                                <p class="font-semibold capitalize">{{ order.payment_method }}</p>
                            </div>
                            <div v-if="order.payment_reference">
                                <p class="text-gray-500 dark:text-gray-400">Reference</p>
                                <p class="font-mono text-xs bg-gray-100 dark:bg-gray-700 p-2 rounded">{{ order.payment_reference }}</p>
                            </div>
                        </div>
                    </div>

                    <!-- Need help -->
                    <div class="bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-700 rounded-2xl p-5 text-center">
                        <p class="text-sm font-semibold text-blue-800 dark:text-blue-300 mb-2">Need help with this order?</p>
                        <a :href="'https://wa.me/' + $page.props.support.phone_whatsapp + '?text=Hello, I need help with Order %23' + order.id"
                            target="_blank"
                            class="text-sm text-blue-600 dark:text-blue-400 font-bold hover:underline">
                            Contact Support →
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </Layout>
</template>
