<script setup>
import { Head, useForm, usePage, router } from '@inertiajs/vue3';
import Layout from '@/Layouts/Layout.vue';
import { ref, computed, watch } from 'vue';

const props = defineProps({
    cart: Object,
    settings: Object,
    customer: Object,       // null if guest
    coupon: Object,         // applied coupon from session
    shipping_zones: {
        type: Array,
        default: () => [],
    },
    addresses: {
        type: Array,
        default: () => [],
    },
});

// ─── Computed Fees ───────────────────────────────────────────────
const subtotal = computed(() =>
    props.cart.items.reduce((acc, item) => acc + item.price * item.quantity, 0)
);

const s = computed(() => props.settings || {});

// Coupon Discount
const discountAmount = computed(() => {
    if (!props.coupon) return 0;
    const isPercent = props.coupon.type === 'percent' || props.coupon.type === 'percentage';
    if (isPercent) {
        return (subtotal.value * parseFloat(props.coupon.value)) / 100;
    }
    return Math.min(subtotal.value, parseFloat(props.coupon.discount ?? props.coupon.value ?? 0));
});

const netSubtotal = computed(() => Math.max(0, subtotal.value - discountAmount.value));

const taxEnabled  = computed(() => s.value.tax_enabled === '1');
const taxRate     = computed(() => parseFloat(s.value.tax_rate || 0));
const taxAmount   = computed(() => taxEnabled.value ? (netSubtotal.value * taxRate.value) / 100 : 0);

const serviceFeeEnabled = computed(() => s.value.service_fee_enabled === '1');
const serviceFee        = computed(() => serviceFeeEnabled.value ? parseFloat(s.value.service_fee_amount || 0) : 0);

const shippingEnabled = computed(() => s.value.shipping_enabled === '1');
const waybillFee      = computed(() => parseFloat(s.value.waybill_fee || 0));
const freeThreshold   = computed(() => parseFloat(s.value.free_shipping_threshold || 0));
const pickupEnabled   = computed(() => s.value.pickup_enabled === '1');

// Selected Shipping Zone
const defaultShippingZone = computed(() => props.shipping_zones?.find(z => z.is_default) || props.shipping_zones?.[0]);
const selectedZoneId = ref(defaultShippingZone.value?.id || null);
const selectedZone   = computed(() => props.shipping_zones?.find(z => z.id === selectedZoneId.value));

const shippingFee = computed(() => {
    if (!shippingEnabled.value || form.delivery_method === 'pickup') return 0;
    if (freeThreshold.value > 0 && subtotal.value >= freeThreshold.value) return 0;
    if (selectedZone.value) return parseFloat(selectedZone.value.rate || 0);
    return waybillFee.value;
});

const grandTotal = computed(() => netSubtotal.value + taxAmount.value + serviceFee.value + shippingFee.value);

// ─── Enabled Payment Methods ──────────────────────────────────────
const enabledGateways = computed(() => {
    const methods = [];
    if (s.value.paystack_enabled === '1')      methods.push({ value: 'paystack',      label: 'Paystack',              sub: 'Cards, bank transfer, USSD' });
    if (s.value.flutterwave_enabled === '1')   methods.push({ value: 'flutterwave',   label: 'Flutterwave',           sub: 'Cards, mobile money, bank' });
    if (s.value.cod_enabled === '1')           methods.push({ value: 'pod',           label: 'Payment on Delivery (POD)', sub: 'Pay with cash, bank transfer, or card when your order arrives' });
    if (s.value.bank_transfer_enabled === '1') methods.push({ value: 'bank_transfer', label: 'Direct Bank Transfer', sub: 'Electronic transfer directly to our bank account' });
    return methods;
});

// Default Saved Address Auto-fill
const defaultAddress = computed(() => props.addresses?.find(a => a.is_default) || props.addresses?.[0]);

const initialAddressText = computed(() => {
    if (defaultAddress.value) {
        return `${defaultAddress.value.address_line_1}${defaultAddress.value.address_line_2 ? ', ' + defaultAddress.value.address_line_2 : ''}, ${defaultAddress.value.city}, ${defaultAddress.value.state}`;
    }
    if (s.value.default_delivery_address_hint) {
        return s.value.default_delivery_address_hint;
    }
    return '';
});

// ─── Form ─────────────────────────────────────────────────────────
const form = useForm({
    name:             defaultAddress.value?.name  || props.customer?.name  || '',
    email:            props.customer?.email || '',
    phone:            defaultAddress.value?.phone || props.customer?.phone || '',
    address:          initialAddressText.value,
    delivery_method:  shippingEnabled.value ? 'delivery' : (pickupEnabled.value ? 'pickup' : 'delivery'),
    shipping_zone_id: selectedZoneId.value,
    gateway:          enabledGateways.value[0]?.value ?? 'paystack',
});

watch(selectedZoneId, (newVal) => {
    form.shipping_zone_id = newVal;
});

// ─── Saved Address Selection ─────────────────────────────────────
const selectSavedAddress = (addr) => {
    form.name    = addr.name || props.customer?.name || '';
    form.phone   = addr.phone || props.customer?.phone || '';
    form.address = `${addr.address_line_1}${addr.address_line_2 ? ', ' + addr.address_line_2 : ''}, ${addr.city}, ${addr.state}`;

    // Auto-match state with shipping zone if found
    if (props.shipping_zones?.length > 0) {
        const match = props.shipping_zones.find(z => z.state.toLowerCase() === addr.state.toLowerCase());
        if (match) {
            selectedZoneId.value = match.id;
        }
    }
};

// ─── Auto-fill toggle ─────────────────────────────────────────────
const fillFromAccount = (checked) => {
    if (checked && props.customer) {
        form.name  = props.customer.name;
        form.email = props.customer.email;
        form.phone = props.customer.phone || '';
    }
};

// ─── Coupon Form ──────────────────────────────────────────────────
const couponForm = useForm({
    code: '',
});

const applyCoupon = () => {
    couponForm.post(route('coupon.apply'), {
        preserveScroll: true,
        onSuccess: () => couponForm.reset(),
    });
};

const removeCoupon = () => {
    router.post(route('coupon.remove'), {}, {
        preserveScroll: true,
    });
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

                        <!-- Saved Addresses Picker (if available) -->
                        <div v-if="addresses && addresses.length > 0" class="bg-white dark:bg-gray-800 shadow-sm rounded-xl p-6 border dark:border-gray-700">
                            <h2 class="text-lg font-semibold mb-3 flex items-center justify-between">
                                <span>Saved Delivery Addresses</span>
                                <span class="text-xs font-normal text-gray-500">Click to auto-fill</span>
                            </h2>
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                                <button
                                    v-for="addr in addresses"
                                    :key="addr.id"
                                    type="button"
                                    @click="selectSavedAddress(addr)"
                                    class="p-3.5 text-left rounded-lg border dark:border-gray-700 hover:border-primary transition bg-gray-50 dark:bg-gray-900/50 flex flex-col justify-between"
                                >
                                    <div>
                                        <div class="flex items-center gap-2 mb-1">
                                            <span class="font-bold text-sm">{{ addr.name }}</span>
                                            <span v-if="addr.is_default" class="text-[10px] bg-primary/10 text-primary font-bold px-1.5 py-0.5 rounded">Default</span>
                                        </div>
                                        <p class="text-xs text-gray-600 dark:text-gray-400 line-clamp-2">{{ addr.address_line_1 }}, {{ addr.city }}, {{ addr.state }}</p>
                                    </div>
                                    <p class="text-xs font-semibold text-primary mt-2">Use this address →</p>
                                </button>
                            </div>
                        </div>

                        <!-- Billing Info -->
                        <div class="bg-white dark:bg-gray-800 shadow-sm rounded-xl p-6 border dark:border-gray-700">
                            <div class="flex items-center justify-between mb-4">
                                <h2 class="text-xl font-semibold">Billing & Delivery Info</h2>
                                <!-- Auto-fill checkbox for authenticated customers -->
                                <label v-if="customer" class="flex items-center gap-2 cursor-pointer bg-primary/10 border border-primary/20 rounded-lg px-3 py-2">
                                    <input type="checkbox" @change="(e) => fillFromAccount(e.target.checked)" class="w-4 h-4 text-primary rounded" />
                                    <span class="text-sm font-medium text-primary">Use my account info</span>
                                </label>
                            </div>
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Full Name *</label>
                                    <input type="text" v-model="form.name" required class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 px-3 py-2.5 focus:ring-primary focus:border-primary" />
                                    <p v-if="form.errors.name" class="text-red-500 text-xs mt-1">{{ form.errors.name }}</p>
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Email Address *</label>
                                    <input type="email" v-model="form.email" required class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 px-3 py-2.5 focus:ring-primary focus:border-primary" />
                                    <p v-if="form.errors.email" class="text-red-500 text-xs mt-1">{{ form.errors.email }}</p>
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Phone Number</label>
                                    <input type="tel" v-model="form.phone" class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 px-3 py-2.5" placeholder="+234..." />
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Delivery Address *</label>
                                    <input type="text" v-model="form.address" class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 px-3 py-2.5" :placeholder="s.default_delivery_address_hint || 'Street name, City, State'" />
                                </div>
                            </div>
                        </div>

                        <!-- Delivery Method & Dynamic Zones -->
                        <div v-if="shippingEnabled || pickupEnabled" class="bg-white dark:bg-gray-800 shadow-sm rounded-xl p-6 border dark:border-gray-700">
                            <h2 class="text-xl font-semibold mb-4">Delivery Method</h2>
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 mb-4">
                                <label v-if="shippingEnabled" :class="['flex items-start gap-3 p-4 rounded-xl border-2 cursor-pointer transition', form.delivery_method === 'delivery' ? 'border-primary bg-primary/10' : 'border-gray-200 dark:border-gray-700']">
                                    <input type="radio" v-model="form.delivery_method" value="delivery" class="mt-0.5 text-primary" />
                                    <div>
                                        <p class="font-semibold">🚚 Home Delivery</p>
                                        <p class="text-sm text-gray-500 dark:text-gray-400">
                                            <template v-if="freeThreshold > 0 && subtotal >= freeThreshold">
                                                <span class="text-green-600 font-bold">FREE</span> — order qualifies!
                                            </template>
                                            <template v-else-if="selectedZone">
                                                <span v-if="parseFloat(selectedZone.rate) === 0" class="text-green-600 font-bold">FREE Delivery</span>
                                                <span v-else>₦{{ parseFloat(selectedZone.rate).toLocaleString() }}</span>
                                                ({{ selectedZone.delivery_estimate_days || 'Estimated' }})
                                            </template>
                                            <template v-else>₦{{ waybillFee.toLocaleString() }} standard fee</template>
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

                            <!-- State/Zone Selector if Home Delivery is selected -->
                            <div v-if="form.delivery_method === 'delivery' && shipping_zones?.length > 0" class="mt-4 pt-4 border-t border-gray-100 dark:border-gray-700">
                                <label class="block text-sm font-semibold text-gray-700 dark:text-gray-300 mb-2">Delivery State / Shipping Zone *</label>
                                <select v-model="selectedZoneId" class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 px-3 py-2.5 focus:ring-primary focus:border-primary text-sm font-medium">
                                    <option v-for="zone in shipping_zones" :key="zone.id" :value="zone.id">
                                        {{ zone.state }}{{ zone.city ? ' — ' + zone.city : '' }} • {{ parseFloat(zone.rate) === 0 ? 'FREE Delivery' : '₦' + parseFloat(zone.rate).toLocaleString() }} ({{ zone.delivery_estimate_days || 'Standard delivery' }})
                                    </option>
                                </select>
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
                                    :class="['flex items-center p-4 border-2 rounded-xl cursor-pointer transition', form.gateway === method.value ? 'border-primary bg-primary/10' : 'border-gray-200 dark:border-gray-600']">
                                    <input type="radio" v-model="form.gateway" :value="method.value" class="text-primary focus:ring-primary h-5 w-5" />
                                    <div class="ml-4 flex-1">
                                        <p class="font-bold">{{ method.label }}</p>
                                        <p class="text-sm text-gray-500 dark:text-gray-400">{{ method.sub }}</p>
                                    </div>
                                    <span v-if="method.value === 'pod' || method.value === 'cod'" class="text-xs bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400 font-bold px-2 py-1 rounded-full">Pay on Delivery</span>
                                    <span v-else-if="method.value === 'bank_transfer'" class="text-xs bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-400 font-bold px-2 py-1 rounded-full">Bank Transfer</span>
                                </label>
                                <p v-if="form.errors.gateway" class="text-red-500 text-sm">{{ form.errors.gateway }}</p>

                                <!-- Bank Transfer Details Preview -->
                                <div v-if="form.gateway === 'bank_transfer' && (settings.bank_name || settings.bank_account_number)" class="mt-4 p-4 rounded-xl bg-blue-50 dark:bg-blue-950/40 border border-blue-200 dark:border-blue-800 text-sm space-y-1.5">
                                    <p class="font-bold text-blue-900 dark:text-blue-200 text-base mb-1">Bank Payment Details</p>
                                    <p class="text-gray-700 dark:text-gray-300"><span class="font-medium text-gray-500">Bank:</span> {{ settings.bank_name || 'Our Bank' }}</p>
                                    <p class="text-gray-700 dark:text-gray-300"><span class="font-medium text-gray-500">Account Number:</span> <span class="font-mono font-bold text-base text-blue-700 dark:text-blue-300">{{ settings.bank_account_number }}</span></p>
                                    <p class="text-gray-700 dark:text-gray-300"><span class="font-medium text-gray-500">Account Name:</span> {{ settings.bank_account_name || settings.company_name }}</p>
                                    <p v-if="settings.bank_instructions" class="text-xs text-blue-800 dark:text-blue-300 pt-2 border-t border-blue-200 dark:border-blue-800/60">{{ settings.bank_instructions }}</p>
                                </div>
                            </div>
                        </div>

                        <!-- Submit Button -->
                        <button type="submit" :disabled="form.processing || enabledGateways.length === 0"
                            class="w-full py-4 rounded-xl bg-primary hover:opacity-90 text-white font-extrabold text-lg transition disabled:opacity-50 uppercase tracking-wide shadow-lg">
                            <template v-if="form.processing">Processing...</template>
                            <template v-else-if="form.gateway === 'pod' || form.gateway === 'cod'">✓ Place Order (Payment on Delivery — POD)</template>
                            <template v-else-if="form.gateway === 'bank_transfer'">✓ Place Order (Direct Bank Transfer)</template>
                            <template v-else>🔒 Pay ₦{{ Math.round(grandTotal).toLocaleString() }}</template>
                        </button>
                    </form>
                </div>

                <!-- ─── Right: Order Summary ─── -->
                <div class="w-full lg:w-2/5 order-1 lg:order-2">
                    <div class="bg-gray-50 dark:bg-gray-800/80 p-6 rounded-xl sticky top-24 border dark:border-gray-700">
                        <h2 class="text-xl font-bold mb-4 pb-3 border-b dark:border-gray-700 text-center">Order Summary</h2>

                        <div class="space-y-4 max-h-[300px] overflow-y-auto pr-2 mb-4">
                            <div v-for="item in cart.items" :key="item.id" class="flex justify-between items-center bg-white dark:bg-gray-800 p-3 rounded-lg shadow-sm border dark:border-gray-700">
                                <div class="flex items-center">
                                    <img :src="item.product.image ? '/storage/' + item.product.image : 'https://placehold.co/60x60?text=P'" class="w-12 h-12 object-cover rounded shadow-sm border dark:border-gray-600" />
                                    <div class="ml-3">
                                        <p class="font-semibold text-sm line-clamp-1">{{ item.product.name }}</p>
                                        <div class="flex items-center flex-wrap gap-1.5 text-gray-500 dark:text-gray-400 text-xs mt-0.5">
                                            <span>Qty: {{ item.quantity }}</span>
                                            <span v-if="item.unit_name" class="font-medium text-primary">({{ item.unit_name }})</span>
                                            <span v-if="item.variant || item.variant_label" class="text-gray-400">• {{ item.variant_label || item.variant }}</span>
                                        </div>
                                    </div>
                                </div>
                                <p class="font-bold text-primary text-sm">₦{{ (item.price * item.quantity).toLocaleString() }}</p>
                            </div>
                        </div>

                        <!-- Coupon Input / Active Banner -->
                        <div class="mb-4 pt-3 border-t dark:border-gray-700">
                            <div v-if="coupon" class="flex items-center justify-between bg-green-50 dark:bg-green-950/40 border border-green-200 dark:border-green-800 p-3 rounded-lg text-sm">
                                <div>
                                    <p class="font-bold text-green-700 dark:text-green-300 flex items-center gap-1.5">
                                        <span>🎟️ {{ coupon.code }}</span>
                                        <span class="text-xs font-normal">({{ (coupon.type === 'percent' || coupon.type === 'percentage') ? parseFloat(coupon.value) + '% off' : '₦' + parseFloat(coupon.value).toLocaleString() + ' off' }})</span>
                                    </p>
                                    <p class="text-xs text-green-600 dark:text-green-400">Saving ₦{{ Math.round(discountAmount).toLocaleString() }}</p>
                                </div>
                                <button type="button" @click="removeCoupon" class="text-xs text-red-500 hover:text-red-700 font-bold px-2 py-1 rounded bg-red-50 dark:bg-red-950/40 hover:bg-red-100 transition">
                                    Remove
                                </button>
                            </div>

                            <form v-else @submit.prevent="applyCoupon" class="flex gap-2">
                                <input
                                    type="text"
                                    v-model="couponForm.code"
                                    placeholder="Enter coupon code"
                                    class="flex-1 rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 text-sm px-3 py-2 uppercase placeholder:normal-case font-mono focus:ring-primary focus:border-primary"
                                />
                                <button
                                    type="submit"
                                    :disabled="couponForm.processing || !couponForm.code"
                                    class="px-4 py-2 bg-gray-800 hover:bg-gray-900 dark:bg-gray-700 dark:hover:bg-gray-600 text-white text-sm font-bold rounded-lg transition disabled:opacity-50"
                                >
                                    Apply
                                </button>
                            </form>
                        </div>

                        <!-- Fee Breakdown -->
                        <div class="border-t dark:border-gray-700 pt-4 space-y-2 text-sm">
                            <div class="flex justify-between">
                                <span class="text-gray-600 dark:text-gray-400">Subtotal</span>
                                <span class="font-semibold">₦{{ subtotal.toLocaleString() }}</span>
                            </div>
                            <div v-if="discountAmount > 0" class="flex justify-between text-green-600 font-semibold">
                                <span>Coupon Discount</span>
                                <span>-₦{{ Math.round(discountAmount).toLocaleString() }}</span>
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
                                <span class="text-gray-600 dark:text-gray-400">
                                    {{ form.delivery_method === 'pickup' ? 'Pickup' : (selectedZone ? selectedZone.state + ' Delivery' : 'Delivery') }}
                                </span>
                                <span :class="shippingFee === 0 ? 'font-bold text-green-600' : 'font-semibold'">
                                    {{ shippingFee === 0 ? 'FREE' : '₦' + shippingFee.toLocaleString() }}
                                </span>
                            </div>
                            <div class="flex justify-between text-lg font-extrabold text-primary pt-3 border-t dark:border-gray-700">
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
