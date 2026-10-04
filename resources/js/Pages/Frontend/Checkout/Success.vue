<script setup>
import { Head, Link, usePage } from '@inertiajs/vue3';
import Layout from '@/Layouts/Layout.vue';
import { computed, ref } from 'vue';

const props = defineProps({
    order: Object,
});

const page = usePage();
const companyName = computed(() => page.props.store_settings?.company_name || 'Our Store');

const subtotal = computed(() => {
    return props.order.items.reduce((sum, item) => sum + (parseFloat(item.price) * item.quantity), 0);
});

const additionalFees = computed(() => {
    return parseFloat(props.order.total_amount) - subtotal.value;
});

const isDownloading = ref(false);

const downloadPdf = async () => {
    isDownloading.value = true;
    try {
        const html2pdf = (await import('html2pdf.js')).default;
        const element = document.getElementById('receipt-content');
        
        // Add a temporary class to ensure pure white background and black text
        element.classList.add('pdf-mode');
        
        const opt = {
            margin:       [0.2, 0, 0.2, 0], // Minimal margin so it aligns to top
            filename:     `receipt-${props.order.tracking_number}.pdf`,
            image:        { type: 'jpeg', quality: 0.98 },
            html2canvas:  { scale: 2, useCORS: true, windowWidth: 800, backgroundColor: '#ffffff' },
            jsPDF:        { unit: 'in', format: 'letter', orientation: 'portrait' }
        };
        
        await html2pdf().set(opt).from(element).save();
        
        element.classList.remove('pdf-mode');
    } catch (e) {
        console.error('Failed to generate PDF', e);
    } finally {
        isDownloading.value = false;
    }
};
</script>

<style>
/* PDF generation specific overrides (Overrides Tailwind oklch for html2canvas compatibility) */
.pdf-mode {
    background-color: #ffffff !important;
    color: #000000 !important;
    padding: 0px !important;
    width: 100% !important;
    max-width: 800px !important;
    margin: 0 auto !important;
}
.pdf-mode * {
    background-color: transparent !important;
    border-color: #e5e7eb !important;
    box-shadow: none !important;
}
.pdf-mode *:not(svg):not(path) {
    color: #000000 !important;
}
.pdf-mode svg.text-green-600 { fill: none !important; color: #16a34a !important; }
.pdf-mode .bg-green-100 { background-color: #dcfce7 !important; border: 1px solid #16a34a !important; }
.pdf-mode .bg-blue-50 { background-color: #eff6ff !important; border: 1px solid #bfdbfe !important; }
.pdf-mode .text-blue-700 { color: #1d4ed8 !important; }
.pdf-mode .text-blue-600 { color: #2563eb !important; }
.pdf-mode .text-blue-400 { color: #60a5fa !important; }
.pdf-mode .text-gray-500 { color: #6b7280 !important; }
.pdf-mode .text-gray-600 { color: #4b5563 !important; }
/* Print overrides for regular Ctrl+P */
@media print {
    body, html { background: white !important; color: black !important; }
    header, footer, nav, .fixed { display: none !important; }
    .no-print { display: none !important; }
    .print-card-override { background: white !important; box-shadow: none !important; border: none !important; color: black !important; }
    .print-card-override * { color: black !important; border-color: #e5e7eb !important; }
}
</style>

<template>
    <Head title="Order Successful" />
    <Layout>
        <div class="container mx-auto px-4 py-8 md:py-12 flex flex-col items-center">

            <!-- Everything inside here gets captured as PDF -->
            <div id="receipt-content" class="w-full max-w-3xl">
                <!-- Header Checkmark -->
                <div class="text-center mb-8 pt-4">
                    <div class="inline-flex items-center justify-center w-20 h-20 rounded-full bg-green-100 mb-4">
                        <svg class="w-10 h-10 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"></path></svg>
                    </div>
                    <h2 class="text-xl font-black tracking-widest text-blue-600 dark:text-blue-400 mb-2 uppercase">{{ companyName }}</h2>
                    <h1 class="text-3xl font-bold text-gray-900 dark:text-white mb-2">Thank you for your order!</h1>
                    <p class="text-gray-500 dark:text-gray-400 max-w-lg mx-auto">
                        Your order has been placed successfully. We've sent a confirmation email to <span class="font-semibold text-gray-800 dark:text-gray-200">{{ order.billing_email }}</span> with your order details.
                    </p>
                </div>

                <!-- Main Order Card -->
                <div class="print-card-override bg-white dark:bg-gray-800 rounded-2xl shadow-sm border border-gray-200 dark:border-gray-700 overflow-hidden mb-4">
                    <!-- Order Top Info -->
                    <div class="print-card-override grid grid-cols-2 md:grid-cols-4 gap-6 p-6 md:p-8 bg-gray-50 dark:bg-gray-800/50 border-b border-gray-200 dark:border-gray-700">
                        <div>
                            <p class="text-xs text-gray-500 dark:text-gray-400 font-semibold uppercase tracking-wider mb-1">Tracking Number</p>
                            <p class="font-bold text-gray-900 dark:text-white text-lg">{{ order.tracking_number }}</p>
                        </div>
                        <div>
                            <p class="text-xs text-gray-500 dark:text-gray-400 font-semibold uppercase tracking-wider mb-1">Date</p>
                            <p class="font-bold text-gray-900 dark:text-white">{{ new Date(order.created_at).toLocaleDateString() }}</p>
                        </div>
                        <div>
                            <p class="text-xs text-gray-500 dark:text-gray-400 font-semibold uppercase tracking-wider mb-1">Total</p>
                            <p class="font-bold text-blue-600 dark:text-blue-400 text-lg">₦{{ parseFloat(order.total_amount).toLocaleString() }}</p>
                        </div>
                        <div>
                            <p class="text-xs text-gray-500 dark:text-gray-400 font-semibold uppercase tracking-wider mb-1">Payment Method</p>
                            <p class="font-bold text-gray-900 dark:text-white capitalize">{{ order.payment_method === 'cod' ? 'Pay on Delivery' : order.payment_method }}</p>
                        </div>
                    </div>

                    <div class="p-6 md:p-8">
                        <!-- Items -->
                        <h3 class="text-lg font-bold text-gray-900 dark:text-white mb-4">Order Items</h3>
                        <div class="space-y-4 mb-8">
                            <div v-for="item in order.items" :key="item.id" class="flex items-center gap-4 py-4 border-b dark:border-gray-100 dark:border-gray-700 last:border-0 last:pb-0">
                                <div class="w-16 h-16 flex-shrink-0 bg-gray-100 dark:bg-gray-700 rounded-lg overflow-hidden border border-gray-200 dark:border-gray-600 relative">
                                    <img :src="item.product?.image ? '/storage/' + item.product.image : 'https://placehold.co/100'" class="w-full h-full object-cover" crossorigin="anonymous" />
                                    <span class="absolute -top-1.5 -right-1.5 bg-gray-600 text-white text-[10px] font-bold w-5 h-5 flex items-center justify-center rounded-full border-2 border-white dark:border-gray-800">{{ item.quantity }}</span>
                                </div>
                                <div class="flex-1 min-w-0">
                                    <h4 class="font-semibold text-gray-900 dark:text-white leading-tight break-words pr-4">{{ item.product?.name || 'Unknown Item' }}</h4>
                                    <p v-if="item.variant" class="text-sm text-gray-500 dark:text-gray-400 mt-1">Variant: {{ item.variant }}</p>
                                </div>
                                <div class="text-right">
                                    <p class="font-bold text-gray-900 dark:text-white">₦{{ (parseFloat(item.price) * item.quantity).toLocaleString() }}</p>
                                </div>
                            </div>
                        </div>

                        <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
                            <!-- Left: Customer Info -->
                            <div class="space-y-6">
                                <div>
                                    <h3 class="text-sm font-bold text-gray-900 dark:text-white uppercase tracking-wider mb-3">Billing & Delivery</h3>
                                    <div class="bg-gray-50 dark:bg-gray-800/30 rounded-xl p-5 border border-gray-100 dark:border-gray-700">
                                        <div class="text-sm text-gray-600 dark:text-gray-400 space-y-1.5 mb-4">
                                            <p class="font-medium text-gray-900 dark:text-white text-base">{{ order.billing_name }}</p>
                                            <p>{{ order.billing_email }}</p>
                                            <p v-if="order.billing_phone">{{ order.billing_phone }}</p>
                                            <p v-if="order.billing_address" class="mt-2 text-gray-800 dark:text-gray-300">{{ order.billing_address }}</p>
                                        </div>
                                        <div class="pt-4 border-t border-gray-200 dark:border-gray-700">
                                            <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full bg-blue-50 text-blue-700 dark:bg-blue-900/30 dark:text-blue-400 font-medium text-xs capitalize uppercase tracking-wide">
                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 8h14M5 8a2 2 0 110-4h14a2 2 0 110 4M5 8v10a2 2 0 002 2h10a2 2 0 002-2V8m-9 4h4"></path></svg>
                                                {{ order.delivery_method === 'pickup' ? 'Store Pickup' : 'Home Delivery' }}
                                            </span>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Right: Totals -->
                            <div>
                                <h3 class="text-sm font-bold text-gray-900 dark:text-white uppercase tracking-wider mb-3 w-full text-right">Order Summary</h3>
                                <div class="bg-blue-50/50 dark:bg-gray-800/80 rounded-xl p-5 border border-blue-100 dark:border-gray-700">
                                    <div class="space-y-3 text-sm">
                                        <div class="flex justify-between text-gray-600 dark:text-gray-400">
                                            <span>Subtotal</span>
                                            <span class="font-medium text-gray-900 dark:text-white">₦{{ subtotal.toLocaleString() }}</span>
                                        </div>
                                        <div v-if="additionalFees > 0" class="flex justify-between text-gray-600 dark:text-gray-400">
                                            <span>Tax & Shipping Fees</span>
                                            <span class="font-medium text-gray-900 dark:text-white">₦{{ additionalFees.toLocaleString() }}</span>
                                        </div>
                                        <div class="border-t border-blue-200 dark:border-gray-600 pt-3 mt-3 flex justify-between items-center">
                                            <span class="text-base font-bold text-gray-900 dark:text-white uppercase tracking-wider">Total</span>
                                            <span class="text-2xl font-black text-blue-600 dark:text-blue-400">₦{{ parseFloat(order.total_amount).toLocaleString() }}</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Actions -->
            <div class="no-print mt-8 flex flex-col sm:flex-row items-center justify-center gap-4 w-full max-w-3xl">
                <button @click="downloadPdf" :disabled="isDownloading" class="w-full sm:w-auto px-8 py-3 bg-gray-100 dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-lg text-gray-700 dark:text-gray-200 font-bold hover:bg-gray-200 dark:hover:bg-gray-600 transition text-center shadow-sm flex items-center justify-center gap-2">
                    <svg v-if="!isDownloading" class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"></path></svg>
                    <svg v-else class="w-5 h-5 flex-shrink-0 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path></svg>
                    {{ isDownloading ? 'Generating...' : 'Download PDF' }}
                </button>
                <Link :href="route('shop.index')" class="w-full sm:w-auto px-8 py-3 bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-600 rounded-lg text-gray-700 dark:text-gray-200 font-bold hover:bg-gray-50 dark:hover:bg-gray-700 transition text-center shadow-sm">
                    Continue Shopping
                </Link>
                <Link :href="route('order.track.direct', order.tracking_number)" class="w-full sm:w-auto px-8 py-3 bg-blue-600 hover:bg-blue-700 text-white rounded-lg font-bold transition text-center shadow-md hover:shadow-lg flex justify-center items-center gap-2">
                    <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path></svg>
                    Track Order
                </Link>
            </div>
        </div>
    </Layout>
</template>
