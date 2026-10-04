<script setup>
import { Head, useForm, usePage } from '@inertiajs/vue3';
import Layout from '@/Layouts/Layout.vue';
import { computed, watch } from 'vue';

const props = defineProps({
    cart: Object,
    settings: Object,
    customer: Object,   // null if guest
});

// ─── Computed Fees ───────────────────────────────────────────────
const subtotal = computed(() =>
    props.cart.items.reduce((acc, item) => acc + item.price * item.quantity, 0)
);

const s = computed(() => props.settings || {});
const taxEnabled  = computed(() => s.value.tax_enabled === '1');
const taxRate     = computed(() => parseFloat(s.value.tax_rate || 0));
const taxAmount   = computed(() => taxEnabled.value ? (subtotal.value * taxRate.value) / 100 : 0);

const serviceFeeEnabled = computed(() => s.value.service_fee_enabled === '1');
const serviceFee        = computed(() => serviceFeeEnabled.value ? parseFloat(s.value.service_fee_amount || 0) : 0);

const shippingEnabled = computed(() => s.value.shipping_enabled === '1');
const waybillFee      = computed(() => parseFloat(s.value.waybill_fee || 0));
const freeThreshold   = computed(() => parseFloat(s.value.free_shipping_threshold || 0));
const pickupEnabled   = computed(() => s.value.pickup_enabled === '1');

const shippingFee = computed(() => {
    if (!shippingEnabled.value || form.delivery_method === 'pickup') return 0;
    if (freeThreshold.value > 0 && subtotal.value >= freeThreshold.value) return 0;
    return waybillFee.value;
});

const grandTotal = computed(() => subtotal.value + taxAmount.value + serviceFee.value + shippingFee.value);

// ─── Enabled Payment Methods ──────────────────────────────────────
const enabledGateways = computed(() => {
    const methods = [];
    if (s.value.paystack_enabled === '1')    methods.push({ value: 'paystack',    label: 'Paystack',    sub: 'Cards, bank transfer, USSD' });
    if (s.value.flutterwave_enabled === '1') methods.push({ value: 'flutterwave', label: 'Flutterwave', sub: 'Cards, mobile money, bank' });
    if (s.value.cod_enabled === '1')         methods.push({ value: 'cod',         label: 'Payment on Delivery', sub: 'Pay cash when your order arrives' });
    return methods;
});

// ─── Form ─────────────────────────────────────────────────────────
const form = useForm({
    name:            props.customer?.name  || '',
    email:           props.customer?.email || '',
    phone:           props.customer?.phone || '',
    address:         '',
    delivery_method: shippingEnabled.value ? 'delivery' : (pickupEnabled.value ? 'pickup' : 'delivery'),
    gateway:         enabledGateways.value[0]?.value ?? 'paystack',
});

// ─── Auto-fill toggle ─────────────────────────────────────────────
const fillFromAccount = (checked) => {
    if (checked && props.customer) {
        form.name  = props.customer.name;
        form.email = props.customer.email;
        form.phone = props.customer.phone || '';
    }
};

const submit = () => {
    form.post(route('checkout.process'));
};
</script>

<template>
    <Head title="Checkout" />
    <Layout>
        <div class="container mx-auto px-4 py-12 max-w-6xl text-black dark:text-white">
            <h1 class="text-3xl font-bold mb-8 text-center pb-4 border-b dark:border-gray-800">Complete Your Order</h1>

            <div class="flex flex-col lg:flex-row gap-12">
                <!-- ─── Left: Form ─── -->
                <div class="w-full lg:w-3/5 order-2 lg:order-1">
                    <form @submit.prevent="submit" class="space-y-6">

                        <!-- Billing Info -->
                        <div class="bg-white dark:bg-gray-800 shadow-sm rounded-xl p-6 border dark:border-gray-700">
                            <div class="flex items-center justify-between mb-4">
                                <h2 class="text-xl font-semibold">Billing Information</h2>
                                <!-- Auto-fill checkbox for authenticated customers -->
                                <label v-if="customer" class="flex items-center gap-2 cursor-pointer bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-700 rounded-lg px-3 py-2">
                                    <input type="checkbox" @change="(e) => fillFromAccount(e.target.checked)" class="w-4 h-4 text-blue-600 rounded" />
                                    <span class="text-sm font-medium text-blue-700 dark:text-blue-300">Use my account info</span>
                                </label>
                            </div>
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Full Name *</label>
                                    <input type="text" v-model="form.name" required class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 px-3 py-2.5 focus:ring-blue-500 focus:border-blue-500" />
                                    <p v-if="form.errors.name" class="text-red-500 text-xs mt-1">{{ form.errors.name }}</p>
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Email Address *</label>
                                    <input type="email" v-model="form.email" required class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 px-3 py-2.5 focus:ring-blue-500 focus:border-blue-500" />
                                    <p v-if="form.errors.email" class="text-red-500 text-xs mt-1">{{ form.errors.email }}</p>
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Phone Number</label>
                                    <input type="tel" v-model="form.phone" class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 px-3 py-2.5" placeholder="+234..." />
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Delivery Address</label>
                                    <input type="text" v-model="form.address" class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 px-3 py-2.5" placeholder="Street, City, State" />
                                </div>
                            </div>
                        </div>

                        <!-- Delivery Method -->
                        <div v-if="shippingEnabled || pickupEnabled" class="bg-white dark:bg-gray-800 shadow-sm rounded-xl p-6 border dark:border-gray-700">
                            <h2 class="text-xl font-semibold mb-4">Delivery Method</h2>
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                                <label v-if="shippingEnabled" :class="['flex items-start gap-3 p-4 rounded-xl border-2 cursor-pointer transition', form.delivery_method === 'delivery' ? 'border-blue-500 bg-blue-50 dark:bg-blue-900/20' : 'border-gray-200 dark:border-gray-700']">
                                    <input type="radio" v-model="form.delivery_method" value="delivery" class="mt-0.5 text-blue-600" />
                                    <div>
                                        <p class="font-semibold">🚚 Home Delivery</p>
                                        <p class="text-sm text-gray-500 dark:text-gray-400">
                                            <template v-if="freeThreshold > 0 && subtotal >= freeThreshold">
                                                <span class="text-green-600 font-bold">FREE</span> — order qualifies!
                                            </template>
                                            <template v-else>₦{{ waybillFee.toLocaleString() }} waybill fee</template>
                                        </p>
                                    </div>
                                </label>
                                <label v-if="pickupEnabled" :class="['flex items-start gap-3 p-4 rounded-xl border-2 cursor-pointer transition', form.delivery_method === 'pickup' ? 'border-green-500 bg-green-50 dark:bg-green-900/20' : 'border-gray-200 dark:border-gray-700']">
                                    <input type="radio" v-model="form.delivery_method" value="pickup" class="mt-0.5 text-green-600" />
                                    <div>
                                        <p class="font-semibold">🏪 Store Pickup <span class="text-green-600 font-bold">(FREE)</span></p>
                                        <p class="text-sm text-gray-500 dark:text-gray-400">{{ settings.pickup_address || 'Contact us for address' }}</p>
                                    </div>
                                </label>
                            </div>
                        </div>

                        <!-- Payment Gateway -->
                        <div class="bg-white dark:bg-gray-800 shadow-sm rounded-xl p-6 border dark:border-gray-700">
                            <h2 class="text-xl font-semibold mb-4">Payment Method</h2>
                            <div v-if="enabledGateways.length === 0" class="text-center py-4 text-gray-500">
                                No payment methods are currently available. Please contact support.
                            </div>
                            <div class="space-y-3">
                                <label v-for="method in enabledGateways" :key="method.value"
                                    :class="['flex items-center p-4 border-2 rounded-xl cursor-pointer transition', form.gateway === method.value ? 'border-blue-500 bg-blue-50 dark:bg-blue-900/20' : 'border-gray-200 dark:border-gray-600']">
                                    <input type="radio" v-model="form.gateway" :value="method.value" class="text-blue-600 focus:ring-blue-500 h-5 w-5" />
                                    <div class="ml-4 flex-1">
                                        <p class="font-bold">{{ method.label }}</p>
                                        <p class="text-sm text-gray-500 dark:text-gray-400">{{ method.sub }}</p>
                                    </div>
                                    <span v-if="method.value === 'cod'" class="text-xs bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400 font-bold px-2 py-1 rounded-full">No upfront payment</span>
                                </label>
                                <p v-if="form.errors.gateway" class="text-red-500 text-sm">{{ form.errors.gateway }}</p>
                            </div>
                        </div>

                        <!-- Submit -->
                        <button type="submit" :disabled="form.processing || enabledGateways.length === 0"
                            class="w-full py-4 rounded-xl bg-blue-600 hover:bg-blue-700 text-white font-extrabold text-lg transition disabled:opacity-50 uppercase tracking-wide shadow-lg">
                            {{ form.processing ? 'Processing...' : (form.gateway === 'cod' ? '✓ Place Order (Pay on Delivery)' : '🔒 Pay ₦' + Math.round(grandTotal).toLocaleString()) }}
                        </button>
                    </form>
                </div>

                <!-- ─── Right: Order Summary ─── -->
                <div class="w-full lg:w-2/5 order-1 lg:order-2">
                    <div class="bg-gray-50 dark:bg-gray-800/80 p-6 rounded-xl sticky top-24 border dark:border-gray-700">
                        <h2 class="text-xl font-bold mb-4 pb-3 border-b dark:border-gray-700 text-center">Order Summary</h2>

                        <div class="space-y-4 max-h-[350px] overflow-y-auto pr-2 mb-4">
                            <div v-for="item in cart.items" :key="item.id" class="flex justify-between items-center bg-white dark:bg-gray-800 p-3 rounded-lg shadow-sm border dark:border-gray-700">
                                <div class="flex items-center">
                                    <img :src="item.product.image ? '/storage/' + item.product.image : 'https://placehold.co/60x60?text=P'" class="w-12 h-12 object-cover rounded shadow-sm border dark:border-gray-600" />
                                    <div class="ml-3">
                                        <p class="font-semibold text-sm line-clamp-1">{{ item.product.name }}</p>
                                        <div class="flex items-center flex-wrap gap-1.5 text-gray-500 dark:text-gray-400 text-xs mt-0.5">
                                            <span>Qty: {{ item.quantity }}</span>
                                            <span v-if="item.unit_name" class="font-medium text-blue-600 dark:text-blue-400">({{ item.unit_name }})</span>
                                            <span v-if="item.variant || item.variant_label" class="text-gray-400">• {{ item.variant_label || item.variant }}</span>
                                        </div>
                                    </div>
                                </div>
                                <p class="font-bold text-blue-600 dark:text-blue-400 text-sm">₦{{ (item.price * item.quantity).toLocaleString() }}</p>
                            </div>
                        </div>

                        <!-- Fee Breakdown -->
                        <div class="border-t dark:border-gray-700 pt-4 space-y-2 text-sm">
                            <div class="flex justify-between">
                                <span class="text-gray-600 dark:text-gray-400">Subtotal</span>
                                <span class="font-semibold">₦{{ subtotal.toLocaleString() }}</span>
                            </div>
                            <div v-if="taxEnabled" class="flex justify-between">
                                <span class="text-gray-600 dark:text-gray-400">VAT ({{ taxRate }}%)</span>
                                <span class="font-semibold">₦{{ Math.round(taxAmount).toLocaleString() }}</span>
                            </div>
                            <div v-if="serviceFeeEnabled" class="flex justify-between">
                                <span class="text-gray-600 dark:text-gray-400">Service Fee</span>
                                <span class="font-semibold">₦{{ serviceFee.toLocaleString() }}</span>
                            </div>
                            <div v-if="shippingEnabled || pickupEnabled" class="flex justify-between">
                                <span class="text-gray-600 dark:text-gray-400">{{ form.delivery_method === 'pickup' ? 'Pickup' : 'Delivery' }}</span>
                                <span :class="shippingFee === 0 ? 'font-bold text-green-600' : 'font-semibold'">
                                    {{ shippingFee === 0 ? 'FREE' : '₦' + shippingFee.toLocaleString() }}
                                </span>
                            </div>
                            <div class="flex justify-between text-lg font-extrabold text-blue-600 dark:text-blue-400 pt-3 border-t dark:border-gray-700">
                                <span>Total</span>
                                <span>₦{{ Math.round(grandTotal).toLocaleString() }}</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </Layout>
</template>
