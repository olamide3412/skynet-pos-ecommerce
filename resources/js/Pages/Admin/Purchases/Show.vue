<script setup>
import { Head, Link } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';

const props = defineProps({
    purchase: Object,
});

const formatCurrency = (val) => {
    return '₦' + Number(val || 0).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
};

const getPaymentStatusBadge = (status) => {
    switch (status) {
        case 'paid':
            return { label: 'Paid', class: 'bg-emerald-50 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-400 border-emerald-200 dark:border-emerald-800' };
        case 'pending':
            return { label: 'Unpaid / Pending', class: 'bg-amber-50 text-amber-700 dark:bg-amber-900/30 dark:text-amber-400 border-amber-200 dark:border-amber-800' };
        case 'partial':
            return { label: 'Partially Paid', class: 'bg-blue-50 text-blue-700 dark:bg-blue-900/30 dark:text-blue-400 border-blue-200 dark:border-blue-800' };
        default:
            return { label: status, class: 'bg-gray-50 text-gray-700 dark:bg-gray-800 dark:text-gray-300 border-gray-200 dark:border-gray-700' };
    }
};

const printVoucher = () => {
    window.print();
};
</script>

<template>
    <Head :title="`Purchase Order ${purchase.purchase_number} - Admin`" />

    <AdminLayout>
        <div class="max-w-4xl mx-auto space-y-6">
            <!-- Header -->
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 no-print">
                <div class="flex items-center gap-3">
                    <Link
                        :href="route('admin.purchases.index')"
                        class="p-2 border border-gray-300 dark:border-gray-600 rounded-xl hover:bg-gray-100 dark:hover:bg-gray-700 text-gray-600 dark:text-gray-300 transition-all"
                        title="Back to list"
                    >
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                        </svg>
                    </Link>
                    <div>
                        <div class="flex items-center gap-2">
                            <h1 class="text-2xl font-bold font-mono text-gray-900 dark:text-white">
                                {{ purchase.purchase_number }}
                            </h1>
                            <span :class="['text-xs font-bold px-2.5 py-0.5 rounded-full border', getPaymentStatusBadge(purchase.payment_status).class]">
                                {{ getPaymentStatusBadge(purchase.payment_status).label }}
                            </span>
                        </div>
                        <p class="text-xs text-gray-500 mt-0.5">
                            Received on {{ new Date(purchase.purchase_date).toLocaleDateString('en-US', { month: 'long', day: 'numeric', year: 'numeric' }) }}
                        </p>
                    </div>
                </div>

                <div class="flex items-center gap-2">
                    <button
                        @click="printVoucher"
                        class="inline-flex items-center gap-2 px-4 py-2 border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-gray-700 dark:text-gray-200 text-sm font-semibold rounded-xl hover:bg-gray-50 dark:hover:bg-gray-700 shadow-xs transition-all"
                    >
                        <svg class="w-4 h-4 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z" />
                        </svg>
                        Print Voucher
                    </button>
                    <Link
                        :href="route('admin.purchases.create')"
                        class="inline-flex items-center gap-2 px-4 py-2 bg-primary text-white text-sm font-semibold rounded-xl hover:bg-primary/90 shadow-sm transition-all"
                    >
                        New Purchase
                    </Link>
                </div>
            </div>

            <!-- Printable Voucher Container -->
            <div class="bg-white dark:bg-gray-800 rounded-2xl border border-gray-200/80 dark:border-gray-700/80 shadow-xs p-8 space-y-6">
                <!-- Voucher Top Meta -->
                <div class="grid grid-cols-2 md:grid-cols-4 gap-6 pb-6 border-b border-gray-200 dark:border-gray-700 text-sm">
                    <div>
                        <p class="text-xs uppercase font-bold text-gray-400">Supplier / Vendor</p>
                        <p class="font-bold text-gray-900 dark:text-white mt-1 text-base">{{ purchase.supplier_name }}</p>
                    </div>
                    <div>
                        <p class="text-xs uppercase font-bold text-gray-400">Invoice Reference</p>
                        <p class="font-mono text-gray-800 dark:text-gray-200 mt-1">{{ purchase.invoice_reference || 'N/A' }}</p>
                    </div>
                    <div>
                        <p class="text-xs uppercase font-bold text-gray-400">Received By</p>
                        <p class="text-gray-800 dark:text-gray-200 mt-1">{{ purchase.user?.name || 'Staff' }}</p>
                    </div>
                    <div>
                        <p class="text-xs uppercase font-bold text-gray-400">Payment Status</p>
                        <p class="font-semibold text-gray-800 dark:text-gray-200 mt-1 capitalize">{{ purchase.payment_status }}</p>
                    </div>
                </div>

                <div v-if="purchase.notes" class="p-4 bg-gray-50 dark:bg-gray-900/50 border border-gray-200 dark:border-gray-700 rounded-xl text-xs text-gray-600 dark:text-gray-300">
                    <span class="font-bold uppercase text-gray-400 block mb-1">Notes / Memo</span>
                    {{ purchase.notes }}
                </div>

                <!-- Items Breakdown Table -->
                <div class="space-y-3">
                    <h3 class="text-xs uppercase font-bold text-gray-400 tracking-wider">Line Items Received</h3>
                    <div class="overflow-x-auto border border-gray-200 dark:border-gray-700 rounded-xl overflow-hidden">
                        <table class="w-full text-left text-sm">
                            <thead class="bg-gray-50 dark:bg-gray-700/50 text-[11px] uppercase tracking-wider text-gray-500 dark:text-gray-400 border-b border-gray-200 dark:border-gray-700">
                                <tr>
                                    <th class="py-3 px-4">#</th>
                                    <th class="py-3 px-4">Product Details</th>
                                    <th class="py-3 px-4 text-center">Qty Received</th>
                                    <th class="py-3 px-4 text-right">Unit Cost</th>
                                    <th class="py-3 px-4 text-right">Line Subtotal</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                                <tr v-for="(item, idx) in purchase.items" :key="item.id" class="hover:bg-gray-50/50 dark:hover:bg-gray-700/20">
                                    <td class="py-3.5 px-4 text-xs font-mono text-gray-400">{{ idx + 1 }}</td>
                                    <td class="py-3.5 px-4">
                                        <p class="font-bold text-gray-900 dark:text-white text-xs">
                                            {{ item.product?.name }}
                                        </p>
                                        <p class="text-[11px] text-gray-400 font-mono mt-0.5">
                                            SKU: {{ item.product?.sku || 'N/A' }}
                                            <span v-if="item.variant_mapping" class="text-primary font-medium ml-1">
                                                • {{ item.variant_mapping.variant_label }}
                                            </span>
                                        </p>
                                    </td>
                                    <td class="py-3.5 px-4 text-center font-mono font-bold text-xs text-emerald-600 dark:text-emerald-400">
                                        +{{ item.quantity }}
                                    </td>
                                    <td class="py-3.5 px-4 text-right font-mono text-xs text-gray-700 dark:text-gray-300">
                                        {{ formatCurrency(item.unit_cost) }}
                                    </td>
                                    <td class="py-3.5 px-4 text-right font-mono font-bold text-xs text-gray-900 dark:text-white">
                                        {{ formatCurrency(item.subtotal) }}
                                    </td>
                                </tr>
                            </tbody>
                            <tfoot class="bg-gray-50/80 dark:bg-gray-700/40 font-bold border-t border-gray-200 dark:border-gray-700">
                                <tr>
                                    <td colspan="2" class="py-3.5 px-4 text-right text-xs uppercase text-gray-500">Totals</td>
                                    <td class="py-3.5 px-4 text-center text-xs font-mono text-emerald-600 dark:text-emerald-400">
                                        +{{ purchase.total_items_count }} units
                                    </td>
                                    <td class="py-3.5 px-4"></td>
                                    <td class="py-3.5 px-4 text-right text-base font-mono text-gray-900 dark:text-white">
                                        {{ formatCurrency(purchase.total_cost) }}
                                    </td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </AdminLayout>
</template>

