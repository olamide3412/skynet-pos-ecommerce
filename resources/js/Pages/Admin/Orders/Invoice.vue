<script setup>
import { Head, Link } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';

const props = defineProps({
    order: Object,
    settings: Object,
});

const print = () => window.print();

const statusBadgeClass = (status) => ({
    pending:   'bg-yellow-100 text-yellow-800',
    paid:      'bg-blue-100 text-blue-800',
    shipped:   'bg-purple-100 text-purple-800',
    completed: 'bg-green-100 text-green-800',
    cancelled: 'bg-red-100 text-red-800',
}[status] || '');
</script>

<style>
@media print {
    .no-print { display: none !important; }
    body { background: white !important; }
}
</style>

<template>
    <Head :title="'Invoice #' + order.id" />
    <AdminLayout>
        <!-- Action Bar (hidden on print) -->
        <div class="no-print flex items-center justify-between mb-6">
            <Link :href="route('admin.orders.show', order.id)" class="flex items-center gap-2 text-gray-600 dark:text-gray-300 hover:text-blue-600 transition font-medium">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
                Back to Order
            </Link>
            <button @click="print" class="flex items-center gap-2 bg-blue-600 hover:bg-blue-700 text-white font-bold px-5 py-2.5 rounded-lg transition shadow">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/></svg>
                Print / Save PDF
            </button>
        </div>

        <!-- Invoice Document -->
        <div class="bg-white text-gray-900 rounded-2xl shadow-lg border border-gray-200 max-w-4xl mx-auto p-10">

            <!-- Header -->
            <div class="flex items-start justify-between border-b border-gray-200 pb-8 mb-8">
                <div>
                    <h1 class="text-3xl font-extrabold text-blue-700">{{ ($page.props.store_settings.company_name || 'SKYNET DIGITAL').toUpperCase() }}</h1>
                    <p class="text-gray-500 text-sm mt-1" v-if="$page.props.store_settings.company_tagline">{{ $page.props.store_settings.company_tagline }}</p>
                    <p class="text-gray-500 text-sm" v-if="$page.props.store_settings.company_email">{{ $page.props.store_settings.company_email }}</p>
                    <p class="text-gray-500 text-sm" v-if="$page.props.store_settings.company_phone">{{ $page.props.store_settings.company_phone }}</p>
                    <p class="text-gray-500 text-sm">+234 803 207 2831</p>
                    <p class="text-gray-500 text-sm mt-1">Delta State, Nigeria</p>
                </div>
                <div class="text-right">
                    <div class="inline-block bg-blue-50 border border-blue-200 rounded-xl px-6 py-4">
                        <p class="text-xs uppercase tracking-widest text-blue-500 font-bold mb-1">Invoice</p>
                        <p class="text-4xl font-black text-blue-700">#{{ order.id }}</p>
                    </div>
                    <p class="text-sm text-gray-500 mt-3">
                        Date: {{ new Date(order.created_at).toLocaleDateString('en-NG', { day:'numeric', month:'long', year:'numeric' }) }}
                    </p>
                    <span :class="['inline-block mt-2 px-3 py-1 rounded-full text-xs font-bold uppercase tracking-wider', statusBadgeClass(order.status)]">
                        {{ order.status }}
                    </span>
                </div>
            </div>

            <!-- Billing & Shipping Columns -->
            <div class="grid grid-cols-2 gap-8 mb-10">
                <div>
                    <p class="text-xs uppercase tracking-widest text-gray-400 font-bold mb-3">Billed To</p>
                    <p class="font-bold text-lg">{{ order.billing_name || order.customer?.name || 'Guest Customer' }}</p>
                    <p class="text-gray-600">{{ order.billing_email || order.customer?.email || order.guest_email }}</p>
                    <p v-if="order.billing_phone" class="text-gray-600">{{ order.billing_phone }}</p>
                    <p v-if="order.billing_address" class="text-gray-600 mt-1">{{ order.billing_address }}</p>
                </div>
                <div>
                    <p class="text-xs uppercase tracking-widest text-gray-400 font-bold mb-3">Payment Details</p>
                    <div class="space-y-1">
                        <div class="flex justify-between">
                            <span class="text-gray-500">Method</span>
                            <span class="font-semibold capitalize">{{ order.payment_method }}</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-gray-500">Delivery</span>
                            <span class="font-semibold capitalize">{{ order.delivery_method || 'delivery' }}</span>
                        </div>
                        <div v-if="order.payment_reference" class="flex justify-between">
                            <span class="text-gray-500">Ref</span>
                            <span class="font-mono text-xs">{{ order.payment_reference }}</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Items Table -->
            <table class="w-full mb-8">
                <thead>
                    <tr class="bg-gray-50 border-y border-gray-200">
                        <th class="py-3 px-4 text-left text-xs font-bold uppercase tracking-wider text-gray-500">Item</th>
                        <th class="py-3 px-4 text-center text-xs font-bold uppercase tracking-wider text-gray-500">Qty</th>
                        <th class="py-3 px-4 text-right text-xs font-bold uppercase tracking-wider text-gray-500">Unit Price</th>
                        <th class="py-3 px-4 text-right text-xs font-bold uppercase tracking-wider text-gray-500">Total</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    <tr v-for="item in order.items" :key="item.id">
                        <td class="py-4 px-4">
                            <p class="font-semibold">{{ item.product?.name ?? 'Product' }}</p>
                            <p v-if="item.variant" class="text-xs text-gray-400 mt-0.5">{{ item.variant }}</p>
                        </td>
                        <td class="py-4 px-4 text-center text-gray-600">{{ item.quantity }}</td>
                        <td class="py-4 px-4 text-right text-gray-600">₦{{ parseFloat(item.price).toLocaleString() }}</td>
                        <td class="py-4 px-4 text-right font-bold">₦{{ (item.price * item.quantity).toLocaleString() }}</td>
                    </tr>
                </tbody>
            </table>

            <!-- Totals -->
            <div class="flex justify-end">
                <div class="w-64 space-y-2 text-sm">
                    <div class="flex justify-between">
                        <span class="text-gray-500">Subtotal</span>
                        <span class="font-semibold">₦{{ order.items.reduce((a,i) => a + i.price * i.quantity, 0).toLocaleString() }}</span>
                    </div>
                    <div class="flex justify-between pt-3 border-t border-gray-300 text-lg font-extrabold">
                        <span>Grand Total</span>
                        <span class="text-blue-700">₦{{ parseFloat(order.total_amount).toLocaleString() }}</span>
                    </div>
                </div>
            </div>

            <!-- Footer -->
            <div class="mt-12 pt-6 border-t border-gray-200 text-center text-xs text-gray-400">
                <p>Thank you for shopping with {{ $page.props.store_settings.company_name || 'Skynet Digital' }}. For inquiries, contact {{ $page.props.store_settings.company_email || 'support@skynetdigitalhub.com.ng' }} or call {{ $page.props.store_settings.company_phone || '+234 803 207 2831' }}.</p>
                <p class="mt-1">This is a computer-generated invoice and requires no signature.</p>
            </div>
        </div>
    </AdminLayout>
</template>
