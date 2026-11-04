<script setup>
import { Head, Link, useForm, router } from '@inertiajs/vue3';
import Layout from '@/Layouts/Layout.vue';
import { computed } from 'vue';

const props = defineProps({
    cart: Object,
    coupon: Object,
});

const subtotal = computed(() => {
    if (!props.cart?.items) return 0;
    return props.cart.items.reduce((acc, item) => acc + (item.price * item.quantity), 0);
});

const discountAmount = computed(() => {
    if (!props.coupon) return 0;
    const isPercent = props.coupon.type === 'percent' || props.coupon.type === 'percentage';
    if (isPercent) {
        return (subtotal.value * parseFloat(props.coupon.value)) / 100;
    }
    return Math.min(subtotal.value, parseFloat(props.coupon.discount ?? props.coupon.value ?? 0));
});

const finalTotal = computed(() => Math.max(0, subtotal.value - discountAmount.value));

const updateQuantity = (item, quantity) => {
    if (quantity < 1) return;
    useForm({ quantity }).put(route('cart.update', item.id), { preserveScroll: true });
};

const removeItem = (item) => {
    useForm({}).delete(route('cart.destroy', item.id), { preserveScroll: true });
};

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
</script>

<template>
    <Head title="Shopping Cart" />
    <Layout>
        <div class="container mx-auto px-4 py-8 max-w-5xl text-black dark:text-white min-h-[60vh]">
            <h1 class="text-3xl font-bold mb-8 flex items-center gap-2">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8 text-primary" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z" />
                </svg>
                Your Shopping Cart
            </h1>
            
            <div v-if="cart && cart.items && cart.items.length > 0" class="bg-white dark:bg-gray-800 shadow-lg rounded-xl overflow-hidden border dark:border-gray-700">
                <div class="p-6 md:p-8">
                    <div class="hidden md:grid grid-cols-12 gap-4 text-sm font-semibold text-gray-500 uppercase pb-4 border-b dark:border-gray-700 mb-4">
                        <div class="col-span-6">Product</div>
                        <div class="col-span-2 text-center">Price</div>
                        <div class="col-span-2 text-center">Quantity</div>
                        <div class="col-span-2 text-right">Subtotal</div>
                    </div>
                    
                    <div v-for="item in cart.items" :key="item.id" class="grid grid-cols-1 md:grid-cols-12 gap-4 items-center py-5 border-b dark:border-gray-700 last:border-b-0 relative">
                        <div class="col-span-1 md:col-span-6 flex items-center gap-4">
                            <img :src="item.product.image ? '/storage/' + item.product.image : 'https://placehold.co/100x100?text=Product'" class="w-20 h-20 object-cover rounded-lg border dark:border-gray-700 flex-shrink-0" />
                            <div>
                                <Link :href="route('shop.show', item.product.url_key)" class="font-bold text-gray-900 dark:text-white hover:text-primary transition line-clamp-1">
                                    {{ item.product.name }}
                                </Link>
                                <div class="flex items-center flex-wrap gap-2 text-xs text-gray-500 dark:text-gray-400 mt-1">
                                    <span v-if="item.unit_name" class="px-2 py-0.5 bg-primary/10 text-primary font-semibold rounded">
                                        {{ item.unit_name }}
                                    </span>
                                    <span v-if="item.variant || item.variant_label" class="text-gray-500">
                                        Variant: {{ item.variant_label || item.variant }}
                                    </span>
                                </div>
                                <div class="md:hidden text-primary font-bold mt-1">₦{{ parseFloat(item.price).toLocaleString() }}</div>
                            </div>
                        </div>
                        
                        <div class="hidden md:block col-span-2 text-center font-medium">
                            ₦{{ parseFloat(item.price).toLocaleString() }}
                        </div>
                        
                        <div class="col-span-1 md:col-span-2 flex justify-start md:justify-center items-center mt-2 md:mt-0">
                            <div class="flex items-center border dark:border-gray-600 rounded-md bg-gray-50 dark:bg-gray-900 shadow-sm">
                                <button @click="updateQuantity(item, item.quantity - 1)" class="px-3 py-1 hover:bg-gray-200 dark:hover:bg-gray-700 transition font-bold">-</button>
                                <span class="px-4 py-1 text-center font-medium w-12 border-x dark:border-gray-600">{{ item.quantity }}</span>
                                <button @click="updateQuantity(item, item.quantity + 1)" class="px-3 py-1 hover:bg-gray-200 dark:hover:bg-gray-700 transition font-bold">+</button>
                            </div>
                        </div>
                        
                        <div class="hidden md:block col-span-2 text-right font-bold text-lg text-primary">
                            ₦{{ (item.price * item.quantity).toLocaleString() }}
                        </div>
                        
                        <button @click="removeItem(item)" class="absolute top-4 right-0 md:static md:col-span-12 ml-auto mt-2 md:mt-4 text-red-500 hover:text-red-700 bg-red-50 hover:bg-red-100 dark:bg-red-900/20 dark:hover:bg-red-900/40 px-3 py-1 rounded inline-flex items-center gap-1 text-sm transition">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor">
                              <path fill-rule="evenodd" d="M9 2a1 1 0 00-.894.553L7.382 4H4a1 1 0 000 2v10a2 2 0 002 2h8a2 2 0 002-2V6a1 1 0 100-2h-3.382l-.724-1.447A1 1 0 0011 2H9zM7 8a1 1 0 012 0v6a1 1 0 11-2 0V8zm5-1a1 1 0 00-1 1v6a1 1 0 102 0V8a1 1 0 00-1-1z" clip-rule="evenodd" />
                            </svg>
                            Remove
                        </button>
                    </div>
                </div>
                
                <div class="bg-gray-50 dark:bg-gray-900/80 p-6 md:p-8 border-t dark:border-gray-700 mt-auto">
                    <div class="flex flex-col lg:flex-row justify-between items-start lg:items-center gap-6">
                        
                        <!-- Left: Continue Shopping & Coupon Form -->
                        <div class="w-full lg:w-1/2 space-y-4">
                            <Link :href="route('shop.index')" class="text-gray-600 dark:text-gray-400 hover:text-primary hover:underline inline-flex items-center font-medium transition">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 mr-1 pb-px" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                                </svg>
                                Continue Shopping
                            </Link>

                            <!-- Coupon Input / Banner -->
                            <div class="pt-2">
                                <div v-if="coupon" class="flex items-center justify-between bg-green-50 dark:bg-green-950/40 border border-green-200 dark:border-green-800 p-3 rounded-lg text-sm max-w-md">
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

                                <form v-else @submit.prevent="applyCoupon" class="flex gap-2 max-w-md">
                                    <input
                                        type="text"
                                        v-model="couponForm.code"
                                        placeholder="Have a promo / coupon code?"
                                        class="flex-1 rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 text-sm px-3 py-2 uppercase placeholder:normal-case font-mono focus:ring-primary focus:border-primary"
                                    />
                                    <button
                                        type="submit"
                                        :disabled="couponForm.processing || !couponForm.code"
                                        class="px-5 py-2 bg-gray-800 hover:bg-gray-900 dark:bg-gray-700 dark:hover:bg-gray-600 text-white text-sm font-bold rounded-lg transition disabled:opacity-50"
                                    >
                                        Apply
                                    </button>
                                </form>
                            </div>
                        </div>
                        
                        <!-- Right: Subtotal & Checkout -->
                        <div class="text-right w-full lg:w-auto p-6 lg:p-0 bg-white dark:bg-gray-800 lg:bg-transparent rounded-lg shadow-sm lg:shadow-none border dark:border-gray-700 lg:border-transparent">
                            <div class="space-y-1.5 mb-4 pb-4 border-b dark:border-gray-700">
                                <div class="flex justify-between lg:justify-end items-center gap-8 text-sm text-gray-600 dark:text-gray-400">
                                    <span>Subtotal</span>
                                    <span class="font-semibold text-gray-900 dark:text-white">₦{{ subtotal.toLocaleString() }}</span>
                                </div>
                                <div v-if="discountAmount > 0" class="flex justify-between lg:justify-end items-center gap-8 text-sm text-green-600 font-semibold">
                                    <span>Coupon Discount ({{ coupon.code }})</span>
                                    <span>-₦{{ Math.round(discountAmount).toLocaleString() }}</span>
                                </div>
                                <div class="flex justify-between lg:justify-end items-end gap-12 text-lg font-medium pt-2">
                                    <span class="text-gray-600 dark:text-gray-400 font-bold">Estimated Total</span>
                                    <span class="text-3xl font-extrabold text-primary">
                                        ₦{{ Math.round(finalTotal).toLocaleString() }}
                                    </span>
                                </div>
                            </div>
                            <Link :href="route('checkout.index')" class="block w-full lg:w-auto bg-primary hover:opacity-90 text-white px-10 py-4 rounded-lg font-bold text-lg transition shadow-md hover:shadow-lg text-center tracking-wide uppercase">
                                Check Out Now
                            </Link>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Empty state -->
            <div v-else class="text-center py-24 bg-white dark:bg-gray-800 rounded-xl shadow-sm border dark:border-gray-700">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-24 w-24 mx-auto text-gray-300 dark:text-gray-600 mb-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z" />
                </svg>
                <h2 class="text-2xl font-bold text-gray-800 dark:text-white mb-3">Your cart is completely empty.</h2>
                <p class="text-gray-500 mb-8 max-w-md mx-auto">Looks like you haven't added anything yet. Explore our top products and find exactly what you're looking for!</p>
                <Link :href="route('shop.index')" class="inline-block bg-primary text-white px-8 py-3 rounded-lg font-bold shadow-md hover:opacity-90 transition hover:shadow-lg uppercase tracking-wider">Start Shopping</Link>
            </div>
        </div>
    </Layout>
</template>
