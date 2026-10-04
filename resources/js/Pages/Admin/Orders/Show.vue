<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { ref } from 'vue';

const props = defineProps({
    order: Object
});

const form = useForm({
    status: props.order.status
});

const updateStatus = () => {
    form.put(route('admin.orders.status', props.order.id));
};

const copied = ref(false);
const copyLink = () => {
    const url = window.location.origin + '/track/' + props.order.tracking_number;
    navigator.clipboard.writeText(url).then(() => {
        copied.value = true;
        setTimeout(() => { copied.value = false; }, 2500);
    });
};
</script>

<template>
    <Head :title="'Order #' + order.id" />
    <AdminLayout>
        <div class="container mx-auto px-4 py-8 text-black dark:text-white max-w-5xl">
            <div class="flex items-center justify-between mb-8">
                <div>
                    <h1 class="text-3xl font-bold flex items-center gap-3">
                        <Link :href="route('admin.orders.index')" class="text-gray-400 hover:text-blue-500 transition">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" /></svg>
                        </Link>
                        Order #{{ order.id }}
                    </h1>
                    <!-- Tracking Number -->
                    <div v-if="order.tracking_number" class="flex items-center gap-3 mt-2 ml-11">
                        <span class="font-mono text-sm bg-blue-50 dark:bg-blue-900/20 text-blue-700 dark:text-blue-300 px-3 py-1 rounded-lg border border-blue-200 dark:border-blue-700 tracking-widest font-bold">
                            {{ order.tracking_number }}
                        </span>
                        <button @click="copyLink" class="text-xs text-gray-500 hover:text-blue-600 dark:hover:text-blue-400 transition font-medium flex items-center gap-1">
                            <svg v-if="!copied" class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 5H6a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2v-1M8 5a2 2 0 002 2h2a2 2 0 002-2M8 5a2 2 0 012-2h2a2 2 0 012 2m0 0h2a2 2 0 012 2v3m2 4H10m0 0l3-3m-3 3l3 3"/></svg>
                            <svg v-else class="w-3.5 h-3.5 text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                            {{ copied ? 'Copied!' : 'Copy tracking link' }}
                        </button>
                    </div>
                </div>
                <div class="flex items-center gap-3">
                    <Link :href="route('admin.orders.invoice', order.id)" class="flex items-center gap-2 bg-gray-100 dark:bg-gray-700 hover:bg-gray-200 dark:hover:bg-gray-600 text-gray-700 dark:text-gray-200 font-semibold px-4 py-2 rounded-lg transition">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/></svg>
                        Print Invoice
                    </Link>
                    <span class="px-4 py-2 rounded-lg font-bold uppercase tracking-wider shadow-sm"
                          :class="{
                            'bg-yellow-100 text-yellow-800 border border-yellow-200': order.status === 'pending',
                            'bg-blue-100 text-blue-800 border border-blue-200': order.status === 'paid',
                            'bg-purple-100 text-purple-800 border border-purple-200': order.status === 'shipped',
                            'bg-green-100 text-green-800 border border-green-200': order.status === 'completed',
                            'bg-red-100 text-red-800 border border-red-200': order.status === 'cancelled'
                          }">
                        {{ order.status }}
                    </span>
                </div>
            </div>
            
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
                <div class="lg:col-span-2 space-y-8">
                    <!-- Order Items -->
                    <div class="bg-white dark:bg-gray-800 shadow-sm rounded-xl overflow-hidden border dark:border-gray-700">
                        <div class="p-6 border-b dark:border-gray-700 bg-gray-50 dark:bg-gray-900/50">
                            <h2 class="text-xl font-bold">Order Items</h2>
                        </div>
                        <div class="p-6">
                            <div v-for="item in order.items" :key="item.id" class="flex justify-between items-center py-4 border-b dark:border-gray-700 last:border-0">
                                <div class="flex items-center gap-4">
                                    <div class="w-16 h-16 bg-gray-100 dark:bg-gray-700 rounded overflow-hidden flex-shrink-0">
                                        <img v-if="item.product && item.product.image" :src="'/storage/' + item.product.image" class="w-full h-full object-cover text-xs" :alt="item.product.name" />
                                        <span v-else class="flex h-full w-full items-center justify-center text-gray-400">No Img</span>
                                    </div>
                                    <div>
                                        <div class="flex items-center gap-2">
                                            <h4 class="font-bold">{{ item.product ? item.product.name : 'Unknown Product' }}</h4>
                                            <span v-if="item.inventory_source === 'pos'" class="px-2 py-0.5 text-xs font-semibold rounded bg-purple-100 text-purple-700 dark:bg-purple-900/40 dark:text-purple-300">
                                                POS Linked
                                            </span>
                                            <span v-else class="px-2 py-0.5 text-xs font-medium rounded bg-gray-100 text-gray-600 dark:bg-gray-700 dark:text-gray-300">
                                                Local Stock
                                            </span>
                                        </div>
                                        <div class="flex flex-wrap items-center gap-2 text-xs text-gray-500 mt-1">
                                            <span v-if="item.unit_name" class="font-medium text-blue-600 dark:text-blue-400">Unit: {{ item.unit_name }}</span>
                                            <span v-if="item.variant || item.variant_label" class="text-gray-500">• {{ item.variant_label || item.variant }}</span>
                                            <span v-if="item.quantity_in_base_units && parseFloat(item.quantity_in_base_units) > 1" class="text-gray-400">
                                                ({{ parseFloat(item.quantity_in_base_units) * item.quantity }} base units)
                                            </span>
                                        </div>
                                    </div>
                                </div>
                                <div class="text-right">
                                    <div class="font-bold text-blue-600 dark:text-blue-400">₦{{ parseFloat(item.price).toLocaleString() }} <span class="text-gray-400 text-sm font-normal">x {{ item.quantity }}</span></div>
                                    <div class="text-sm font-semibold mt-1">Sum: ₦{{ (item.price * item.quantity).toLocaleString() }}</div>
                                </div>
                            </div>
                        </div>
                        <div class="bg-gray-50 dark:bg-gray-900 p-6 flex justify-between items-center border-t dark:border-gray-700">
                            <span class="text-lg text-gray-600 dark:text-gray-400 font-medium">Grand Total</span>
                            <span class="text-2xl font-black text-blue-600 dark:text-blue-400">₦{{ parseFloat(order.total_amount).toLocaleString() }}</span>
                        </div>
                    </div>
                </div>
                
                <div class="lg:col-span-1 space-y-8">
                    <!-- Customer Details -->
                    <div class="bg-white dark:bg-gray-800 shadow-sm rounded-xl overflow-hidden border dark:border-gray-700">
                        <div class="p-6 border-b dark:border-gray-700 bg-gray-50 dark:bg-gray-900/50">
                            <h2 class="text-xl font-bold">Customer Info</h2>
                        </div>
                        <div class="p-6 space-y-4">
                            <div>
                                <p class="text-sm text-gray-500 mb-1">Name</p>
                                <p class="font-bold text-lg">{{ order.customer ? order.customer.name : 'Guest' }}</p>
                            </div>
                            <div>
                                <p class="text-sm text-gray-500 mb-1">Email</p>
                                <p class="font-medium">{{ order.customer ? order.customer.email : order.guest_email }}</p>
                            </div>
                            <div v-if="order.customer && order.customer.phone">
                                <p class="text-sm text-gray-500 mb-1">Phone</p>
                                <p class="font-medium">{{ order.customer.phone }}</p>
                            </div>
                        </div>
                    </div>

                    <!-- Billing Info -->
                    <div class="bg-white dark:bg-gray-800 shadow-sm rounded-xl overflow-hidden border dark:border-gray-700">
                        <div class="p-6 border-b dark:border-gray-700 bg-gray-50 dark:bg-gray-900/50">
                            <h2 class="text-xl font-bold">Billing Info</h2>
                        </div>
                        <div class="p-6 space-y-3 text-sm">
                            <div v-if="order.billing_name">
                                <p class="text-gray-500 mb-0.5">Billing Name</p>
                                <p class="font-semibold">{{ order.billing_name }}</p>
                            </div>
                            <div v-if="order.billing_email">
                                <p class="text-gray-500 mb-0.5">Billing Email</p>
                                <p class="font-semibold">{{ order.billing_email }}</p>
                            </div>
                            <div v-if="order.billing_phone">
                                <p class="text-gray-500 mb-0.5">Phone</p>
                                <p class="font-semibold">{{ order.billing_phone }}</p>
                            </div>
                            <div v-if="order.billing_address">
                                <p class="text-gray-500 mb-0.5">Address</p>
                                <p class="font-semibold">{{ order.billing_address }}</p>
                            </div>
                            <div>
                                <p class="text-gray-500 mb-0.5">Delivery Method</p>
                                <p class="font-semibold capitalize">{{ order.delivery_method || 'delivery' }}</p>
                            </div>
                            <div v-if="!order.billing_name && !order.billing_email" class="text-gray-400 text-xs italic">
                                No billing info saved (legacy order)
                            </div>
                        </div>
                    </div>
                    
                    <!-- Payment Details -->
                    <div class="bg-white dark:bg-gray-800 shadow-sm rounded-xl overflow-hidden border dark:border-gray-700">
                        <div class="p-6 border-b dark:border-gray-700 bg-gray-50 dark:bg-gray-900/50">
                            <h2 class="text-xl font-bold">Payment</h2>
                        </div>
                        <div class="p-6 space-y-4">
                            <div>
                                <p class="text-sm text-gray-500 mb-1">Method</p>
                                <p class="font-bold text-lg capitalize flex items-center gap-2">
                                    {{ order.payment_method }}
                                </p>
                            </div>
                            <div>
                                <p class="text-sm text-gray-500 mb-1">Reference</p>
                                <p class="font-mono text-sm bg-gray-100 dark:bg-gray-700 p-2 rounded">{{ order.payment_reference || 'N/A' }}</p>
                            </div>
                        </div>
                    </div>
                    
                    <!-- POS Fulfillment & Reservation -->
                    <div v-if="order.has_pos_items || order.pos_reservation_reference || order.inventory_reference" class="bg-white dark:bg-gray-800 shadow-sm rounded-xl overflow-hidden border dark:border-gray-700">
                        <div class="p-6 border-b dark:border-gray-700 bg-purple-50 dark:bg-purple-950/30 flex items-center justify-between">
                            <h2 class="text-xl font-bold flex items-center gap-2 text-purple-900 dark:text-purple-300">
                                <svg class="w-5 h-5 text-purple-600 dark:text-purple-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"/></svg>
                                POS Fulfillment
                            </h2>
                            <span class="px-2.5 py-1 text-xs font-bold uppercase rounded-full"
                                :class="{
                                    'bg-amber-100 text-amber-800 dark:bg-amber-900/40 dark:text-amber-300': order.pos_reservation_status === 'reserved',
                                    'bg-green-100 text-green-800 dark:bg-green-900/40 dark:text-green-300': order.pos_reservation_status === 'confirmed',
                                    'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-300': order.pos_reservation_status === 'released',
                                    'bg-red-100 text-red-800 dark:bg-red-900/40 dark:text-red-300': order.pos_reservation_status === 'expired',
                                    'bg-blue-100 text-blue-800 dark:bg-blue-900/40 dark:text-blue-300': !order.pos_reservation_status || order.pos_reservation_status === 'none',
                                }">
                                {{ order.pos_reservation_status || 'POS Linked' }}
                            </span>
                        </div>
                        <div class="p-6 space-y-3 text-sm">
                            <div v-if="order.pos_reservation_reference">
                                <p class="text-gray-500 mb-0.5">Reservation Ref</p>
                                <p class="font-mono text-xs bg-gray-100 dark:bg-gray-700 p-2 rounded break-all">{{ order.pos_reservation_reference }}</p>
                            </div>
                            <div v-if="order.inventory_reference">
                                <p class="text-gray-500 mb-0.5">Fulfillment Reference</p>
                                <p class="font-mono text-xs bg-gray-100 dark:bg-gray-700 p-2 rounded break-all">{{ order.inventory_reference }}</p>
                            </div>
                            <div v-if="order.pos_reservation_expires_at">
                                <p class="text-gray-500 mb-0.5">Hold Expiration</p>
                                <p class="font-medium text-xs text-gray-700 dark:text-gray-300">{{ order.pos_reservation_expires_at }}</p>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Actions -->
                    <div class="bg-white dark:bg-gray-800 shadow-sm rounded-xl overflow-hidden border dark:border-gray-700">
                        <div class="p-6 border-b dark:border-gray-700 bg-gray-50 dark:bg-gray-900/50">
                            <h2 class="text-xl font-bold">Update Status</h2>
                        </div>
                        <div class="p-6">
                            <form @submit.prevent="updateStatus" class="space-y-4">
                                <select v-model="form.status" class="w-full border-gray-300 dark:border-gray-600 dark:bg-gray-700 rounded-md font-medium text-gray-700 dark:text-gray-200 py-3">
                                    <option value="pending">Pending</option>
                                    <option value="paid">Paid</option>
                                    <option value="shipped">Shipped</option>
                                    <option value="completed">Completed</option>
                                    <option value="cancelled">Cancelled</option>
                                </select>
                                <button type="submit" :disabled="form.processing" class="w-full bg-blue-600 hover:bg-blue-700 text-white font-bold py-3 rounded-md transition shadow hover:shadow-md disabled:opacity-50">Update Order</button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AdminLayout>
</template>
