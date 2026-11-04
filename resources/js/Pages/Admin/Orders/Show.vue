<script setup>
import { Head, Link, useForm, router, usePage } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { ref, computed } from 'vue';

const props = defineProps({
    order: Object,
    allStaff: {
        type: Array,
        default: () => [],
    },
    canReconcile: {
        type: Boolean,
        default: false,
    },
    canUpdateStatus: {
        type: Boolean,
        default: false,
    },
});

const page = usePage();
const isAdmin = computed(() => {
    const role = page.props.auth?.user?.role;
    return role === 'administrator' || role === 'super administrator';
});
const userCanReconcile = computed(() => {
    if (props.canReconcile || isAdmin.value) return true;
    return !!page.props.auth?.permissions?.['pod.reconcile'];
});
const userCanUpdateStatus = computed(() => {
    if (props.canUpdateStatus || isAdmin.value) return true;
    return !!page.props.auth?.permissions?.['orders.update_status'];
});

const form = useForm({
    status: props.order.status
});

const selectedStaffId = ref(props.order.assigned_staff_id || '');
const assignStaff = () => {
    if (!selectedStaffId.value) return;
    router.post(route('admin.orders.assign-staff', props.order.id), {
        assigned_staff_id: selectedStaffId.value,
    }, { preserveScroll: true });
};

const showCollectModal = ref(false);
const collectedAmount = ref(props.order.pod_amount_collected || props.order.total_amount);
const collectionChannel = ref('cash');

const openCollectModal = () => {
    if (props.order.status === 'cancelled') return;
    collectedAmount.value = props.order.pod_amount_collected || props.order.total_amount;
    showCollectModal.value = true;
};

const submitCollectCash = () => {
    if (Number(collectedAmount.value) < Number(props.order.total_amount)) {
        alert(`Cannot collect less than full order total of ₦${Number(props.order.total_amount).toLocaleString()}. Full payment is required.`);
        return;
    }
    router.post(route('admin.orders.mark-cash-collected', props.order.id), {
        amount: collectedAmount.value,
        channel: collectionChannel.value,
    }, {
        preserveScroll: true,
        onSuccess: () => { showCollectModal.value = false; }
    });
};

const markCashRemitted = () => {
    const amountStr = Number(props.order.pod_amount_collected || props.order.total_amount).toLocaleString();
    if (!confirm(`Confirm receiving & reconciling ₦${amountStr} from rider directly into store custody?`)) return;
    router.post(route('admin.orders.mark-cash-remitted', props.order.id), {}, { preserveScroll: true });
};

const showReconcileModal = ref(false);
const reconcileNote = ref('');
const submitReconcile = () => {
    router.post(route('admin.orders.reconcile-pod', props.order.id), {
        reconciliation_note: reconcileNote.value,
    }, {
        preserveScroll: true,
        onSuccess: () => { showReconcileModal.value = false; }
    });
};

const updateStatus = () => {
    form.put(route('admin.orders.status', props.order.id));
};

const showCancelPrompt = ref(false);
const showCompletePrompt = ref(false);

const handleStatusSubmit = () => {
    if (form.status === 'cancelled') {
        showCancelPrompt.value = true;
        return;
    }
    if (form.status === 'completed') {
        showCompletePrompt.value = true;
        return;
    }
    updateStatus();
};

const confirmCancelOrder = () => {
    showCancelPrompt.value = false;
    updateStatus();
};

const dismissCancelPrompt = () => {
    showCancelPrompt.value = false;
    form.status = props.order.status;
};

const confirmCompleteOrder = () => {
    showCompletePrompt.value = false;
    updateStatus();
};

const dismissCompletePrompt = () => {
    showCompletePrompt.value = false;
    form.status = props.order.status;
};

const copied = ref(false);
const copyLink = () => {
    const url = window.location.origin + '/track/' + props.order.tracking_number;
    navigator.clipboard.writeText(url).then(() => {
        copied.value = true;
        setTimeout(() => { copied.value = false; }, 2500);
    });
};

const newNote = ref('');
const isSubmittingNote = ref(false);
const submitAddNote = () => {
    if (!newNote.value.trim()) return;
    isSubmittingNote.value = true;
    router.post(route('admin.orders.add-note', props.order.id), {
        note: newNote.value.trim(),
    }, {
        preserveScroll: true,
        onSuccess: () => {
            newNote.value = '';
            isSubmittingNote.value = false;
        },
        onError: () => {
            isSubmittingNote.value = false;
        }
    });
};

const formatEventDate = (dateStr) => {
    if (!dateStr) return '';
    const d = new Date(dateStr);
    return d.toLocaleString('en-US', {
        month: 'short',
        day: 'numeric',
        year: 'numeric',
        hour: 'numeric',
        minute: '2-digit',
        second: '2-digit',
        hour12: true,
    });
};

const getEventBadgeClass = (event, item = null) => {
    switch (event) {
        case 'order_placed':
            return 'bg-blue-100 text-blue-800 dark:bg-blue-900/50 dark:text-blue-300 border border-blue-200 dark:border-blue-800';
        case 'payment_collected':
            return 'bg-amber-100 text-amber-800 dark:bg-amber-900/50 dark:text-amber-300 border border-amber-200 dark:border-amber-800';
        case 'reconciled':
            return 'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/50 dark:text-emerald-300 border border-emerald-200 dark:border-emerald-800';
        case 'staff_assigned':
            return 'bg-purple-100 text-purple-800 dark:bg-purple-900/50 dark:text-purple-300 border border-purple-200 dark:border-purple-800';
        case 'note_added':
            return 'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-200 border border-gray-300 dark:border-gray-600';
        case 'status_change':
            if (item?.to_status === 'completed') {
                return 'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/50 dark:text-emerald-300 border border-emerald-200 dark:border-emerald-800';
            }
            if (item?.to_status === 'cancelled') {
                return 'bg-red-100 text-red-800 dark:bg-red-900/50 dark:text-red-300 border border-red-200 dark:border-red-800';
            }
            return 'bg-indigo-100 text-indigo-800 dark:bg-indigo-900/50 dark:text-indigo-300 border border-indigo-200 dark:border-indigo-800';
        default:
            return 'bg-indigo-100 text-indigo-800 dark:bg-indigo-900/50 dark:text-indigo-300 border border-indigo-200 dark:border-indigo-800';
    }
};

const getPaymentMethod = (item) => {
    if (!item) return null;
    if (item.metadata?.channel_label) {
        return item.metadata.channel_label;
    }
    if (item.metadata?.channel) {
        const ch = String(item.metadata.channel).toLowerCase();
        if (ch === 'bank_transfer' || ch === 'transfer') return 'Instant Bank Transfer';
        if (ch === 'pos_terminal' || ch === 'pos') return 'Handheld POS Terminal';
        if (ch === 'cash') return 'Physical Cash';
        return item.metadata.channel;
    }
    if (item.comment) {
        if (/bank\s*transfer|instant\s*bank/i.test(item.comment)) return 'Instant Bank Transfer';
        if (/pos\s*terminal|handheld\s*pos|\bpos\b/i.test(item.comment)) return 'Handheld POS Terminal';
        if (/physical\s*cash|\bcash\b/i.test(item.comment)) return 'Physical Cash';
    }
    if (item.event === 'payment_collected') {
        return 'Physical Cash';
    }
    return null;
};

const getPaymentMethodIcon = (method) => {
    if (!method) return '💵';
    const m = String(method).toLowerCase();
    if (m.includes('transfer')) return '🏦';
    if (m.includes('pos')) return '💳';
    return '💵';
};

const getEventIcon = (event, item = null) => {
    switch (event) {
        case 'order_placed': return '🛍️';
        case 'payment_collected':
            return getPaymentMethodIcon(getPaymentMethod(item));
        case 'reconciled': return '🔒';
        case 'staff_assigned': return '👤';
        case 'note_added': return '📝';
        case 'status_change': return '🔄';
        default: return '⏱️';
    }
};

const formatEventTitle = (event, item) => {
    switch (event) {
        case 'order_placed': return 'Order Placed';
        case 'payment_collected': {
            const method = getPaymentMethod(item);
            return method ? `Payment Collected at Doorstep (${method})` : 'Payment Collected at Doorstep';
        }
        case 'reconciled': return 'Payment Remitted & Reconciled (Order Completed)';
        case 'staff_assigned': return 'Courier / Rider Assigned';
        case 'note_added': return 'Internal Audit Note';
        case 'status_change':
            if (item.from_status && item.to_status) {
                if (item.from_status.toLowerCase() === item.to_status.toLowerCase()) {
                    return `Status Confirmed: ${item.to_status}`;
                }
                return `Status Changed: ${item.from_status} → ${item.to_status}`;
            }
            return 'Status Changed';
        default: return 'Lifecycle Event';
    }
};
</script>

<template>
    <Head :title="'Order #' + order.id" />
    <AdminLayout>
        <div class="container mx-auto px-4 py-8 text-black dark:text-white max-w-5xl">
            <div class="flex items-center justify-between mb-8">
                <div>
                    <h1 class="text-3xl font-bold flex items-center gap-3">
                        <Link :href="route('admin.orders.index')" class="text-gray-400 hover:text-primary transition">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" /></svg>
                        </Link>
                        Order #{{ order.id }}
                    </h1>
                    <!-- Tracking Number & Applied Coupon -->
                    <div class="flex flex-wrap items-center gap-3 mt-2 ml-11">
                        <span v-if="order.tracking_number" class="font-mono text-sm bg-primary-soft text-primary px-3 py-1 rounded-lg border border-primary-soft tracking-widest font-bold">
                            {{ order.tracking_number }}
                        </span>
                        <button v-if="order.tracking_number" @click="copyLink" class="text-xs text-gray-500 hover:text-primary transition font-medium flex items-center gap-1">
                            <svg v-if="!copied" class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 5H6a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2v-1M8 5a2 2 0 002 2h2a2 2 0 002-2M8 5a2 2 0 012-2h2a2 2 0 012 2m0 0h2a2 2 0 012 2v3m2 4H10m0 0l3-3m-3 3l3 3"/></svg>
                            <svg v-else class="w-3.5 h-3.5 text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                            {{ copied ? 'Copied!' : 'Copy tracking link' }}
                        </button>
                        <span v-if="order.coupon_code" class="inline-flex items-center gap-1.5 text-xs px-2.5 py-1 rounded-lg font-bold bg-emerald-100 text-emerald-800 dark:bg-emerald-950/70 dark:text-emerald-300 border border-emerald-300 dark:border-emerald-700 shadow-xs">
                            <span>🎟️ Coupon:</span>
                            <span class="font-mono font-extrabold uppercase">{{ order.coupon_code }}</span>
                            <span v-if="parseFloat(order.discount_amount) > 0" class="font-normal opacity-90">(-₦{{ parseFloat(order.discount_amount).toLocaleString('en-NG', { minimumFractionDigits: 2 }) }})</span>
                        </span>
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
                            'theme-primary-badge border border-primary-soft': order.status === 'paid',
                            'theme-secondary-badge border border-secondary-soft': order.status === 'shipped',
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
                                            <span v-if="item.inventory_source === 'pos'" class="px-2 py-0.5 text-xs font-semibold rounded theme-primary-badge">
                                                POS Linked
                                            </span>
                                            <span v-else class="px-2 py-0.5 text-xs font-medium rounded bg-gray-100 text-gray-600 dark:bg-gray-700 dark:text-gray-300">
                                                Local Stock
                                            </span>
                                        </div>
                                        <div class="flex flex-wrap items-center gap-2 text-xs text-gray-500 mt-1">
                                            <span v-if="item.unit_name" class="font-medium text-primary">Unit: {{ item.unit_name }}</span>
                                            <span v-if="item.variant || item.variant_label" class="text-gray-500">• {{ item.variant_label || item.variant }}</span>
                                            <span v-if="item.quantity_in_base_units && parseFloat(item.quantity_in_base_units) > 1" class="text-gray-400">
                                                ({{ parseFloat(item.quantity_in_base_units) * item.quantity }} base units)
                                            </span>
                                        </div>
                                    </div>
                                </div>
                                <div class="text-right">
                                    <div class="font-bold text-primary">₦{{ parseFloat(item.price).toLocaleString() }} <span class="text-gray-400 text-sm font-normal">x {{ item.quantity }}</span></div>
                                    <div class="text-sm font-semibold mt-1">Sum: ₦{{ (item.price * item.quantity).toLocaleString() }}</div>
                                </div>
                            </div>
                        </div>
                        <div class="bg-gray-50 dark:bg-gray-900 p-6 border-t dark:border-gray-700 space-y-2.5">
                            <div class="flex justify-between items-center text-sm text-gray-600 dark:text-gray-400">
                                <span>Items Subtotal</span>
                                <span class="font-semibold text-gray-900 dark:text-white">
                                    ₦{{ order.items ? order.items.reduce((sum, item) => sum + (parseFloat(item.price) * item.quantity), 0).toLocaleString('en-NG', { minimumFractionDigits: 2 }) : (parseFloat(order.subtotal || 0).toLocaleString('en-NG', { minimumFractionDigits: 2 })) }}
                                </span>
                            </div>
                            <div v-if="order.coupon_code || parseFloat(order.discount_amount) > 0" class="flex justify-between items-center text-sm text-emerald-600 dark:text-emerald-400 font-medium py-1">
                                <span class="flex items-center gap-2">
                                    <span>🎟️ Coupon Discount</span>
                                    <span v-if="order.coupon_code" class="px-2 py-0.5 rounded text-xs font-mono font-bold bg-emerald-100 dark:bg-emerald-900/60 text-emerald-800 dark:text-emerald-200 border border-emerald-300 dark:border-emerald-700">
                                        {{ order.coupon_code }}
                                    </span>
                                </span>
                                <span class="font-bold">-₦{{ parseFloat(order.discount_amount || 0).toLocaleString('en-NG', { minimumFractionDigits: 2 }) }}</span>
                            </div>
                            <div class="flex justify-between items-center text-sm text-gray-600 dark:text-gray-400">
                                <span class="flex items-center gap-1.5">
                                    <span>🚚 Shipping / Delivery Fee</span>
                                    <span class="text-xs text-gray-400">({{ order.delivery_method || 'delivery' }})</span>
                                </span>
                                <span class="font-semibold text-gray-900 dark:text-white">
                                    {{ parseFloat(order.shipping_fee) > 0 ? '+₦' + parseFloat(order.shipping_fee).toLocaleString('en-NG', { minimumFractionDigits: 2 }) : (order.delivery_method === 'pickup' ? 'Store Pickup (Free)' : '₦0.00 (Free Shipping)') }}
                                </span>
                            </div>
                            <div v-if="parseFloat(order.tax_amount) > 0" class="flex justify-between items-center text-sm text-gray-600 dark:text-gray-400">
                                <span>Tax / VAT</span>
                                <span class="font-semibold text-gray-900 dark:text-white">
                                    +₦{{ parseFloat(order.tax_amount).toLocaleString('en-NG', { minimumFractionDigits: 2 }) }}
                                </span>
                            </div>
                            <div v-if="parseFloat(order.service_fee) > 0" class="flex justify-between items-center text-sm text-gray-600 dark:text-gray-400">
                                <span>Service Fee</span>
                                <span class="font-semibold text-gray-900 dark:text-white">
                                    +₦{{ parseFloat(order.service_fee).toLocaleString('en-NG', { minimumFractionDigits: 2 }) }}
                                </span>
                            </div>
                            <div class="flex justify-between items-center pt-3 border-t border-gray-200 dark:border-gray-700">
                                <span class="text-lg text-gray-800 dark:text-gray-200 font-bold">Grand Total</span>
                                <span class="text-2xl font-black text-primary">₦{{ parseFloat(order.total_amount).toLocaleString('en-NG', { minimumFractionDigits: 2 }) }}</span>
                            </div>
                        </div>
                    </div>

                    <!-- Payment & POD Lifecycle -->
                    <div class="bg-white dark:bg-gray-800 shadow-sm rounded-xl overflow-hidden border dark:border-gray-700">
                        <div class="p-6 border-b dark:border-gray-700 bg-gray-50 dark:bg-gray-900/50 flex items-center justify-between">
                            <div class="flex items-center gap-3">
                                <span class="w-9 h-9 rounded-xl bg-amber-100 dark:bg-amber-900/40 text-amber-600 dark:text-amber-400 flex items-center justify-center font-bold text-base">
                                    💳
                                </span>
                                <div>
                                    <h2 class="text-xl font-bold">Payment & POD Lifecycle</h2>
                                    <p class="text-xs text-gray-500">Payment settlement details, doorstep collection, and store remittance flow.</p>
                                </div>
                            </div>
                            <span
                                v-if="order.payment_method === 'pod'"
                                class="inline-block px-3 py-1 rounded-full text-xs font-bold uppercase tracking-wider"
                                :class="{
                                    'bg-yellow-100 text-yellow-800 dark:bg-yellow-900/50 dark:text-yellow-300 border border-yellow-200 dark:border-yellow-800': order.pod_status === 'awaiting_collection',
                                    'bg-amber-100 text-amber-800 dark:bg-amber-900/50 dark:text-amber-300 border border-amber-200 dark:border-amber-800': order.pod_status === 'cash_collected',
                                    'bg-purple-100 text-purple-800 dark:bg-purple-900/50 dark:text-purple-300 border border-purple-200 dark:border-purple-800': order.pod_status === 'remitted_to_store',
                                    'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/50 dark:text-emerald-300 border border-emerald-200 dark:border-emerald-800': order.pod_status === 'reconciled',
                                }"
                            >
                                {{ order.pod_status ? order.pod_status.replace(/_/g, ' ') : 'Pending Dispatch' }}
                            </span>
                        </div>
                        <div class="p-6 space-y-4">
                            <!-- Row 1: Payment Method & Payment Reference -->
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div class="p-4 bg-gray-50 dark:bg-gray-900/40 rounded-xl border border-gray-100 dark:border-gray-700/60">
                                    <p class="text-xs font-bold uppercase tracking-wider text-gray-500 mb-2">Payment Method</p>
                                    <p class="font-bold text-sm sm:text-base capitalize flex items-center gap-1.5 text-gray-900 dark:text-white">
                                        <span>{{ ['cod', 'pod'].includes(order.payment_method) ? '💵 Payment on Delivery (POD)' : (order.payment_method === 'bank_transfer' ? '🏦 Direct Bank Transfer' : '💳 ' + order.payment_method) }}</span>
                                    </p>
                                </div>
                                <div class="p-4 bg-gray-50 dark:bg-gray-900/40 rounded-xl border border-gray-100 dark:border-gray-700/60">
                                    <p class="text-xs font-bold uppercase tracking-wider text-gray-500 mb-2">Payment Reference</p>
                                    <p class="font-mono text-sm bg-gray-100 dark:bg-gray-800 px-2.5 py-1 rounded-lg truncate text-gray-800 dark:text-gray-200 inline-block max-w-full">
                                        {{ order.payment_reference || 'N/A' }}
                                    </p>
                                </div>
                            </div>

                            <!-- Row 2: Delivery & Shipping Fee + Coupon Applied -->
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div class="p-4 bg-gray-50 dark:bg-gray-900/40 rounded-xl border border-gray-100 dark:border-gray-700/60"
                                     :class="{ 'md:col-span-2': !order.coupon_code }">
                                    <p class="text-xs font-bold uppercase tracking-wider text-gray-500 mb-2 flex items-center gap-1.5">
                                        <span>🚚</span> Delivery & Shipping Fee
                                    </p>
                                    <div class="flex flex-wrap items-center justify-between gap-2">
                                        <p class="font-bold text-sm sm:text-base capitalize text-gray-800 dark:text-gray-200">
                                            {{ order.delivery_method || 'Standard Delivery' }}
                                        </p>
                                        <span class="text-xs font-bold px-2.5 py-1 rounded-lg"
                                            :class="parseFloat(order.shipping_fee) > 0 ? 'bg-blue-100 text-blue-800 dark:bg-blue-950/70 dark:text-blue-300 border border-blue-200 dark:border-blue-800' : 'bg-gray-200 text-gray-700 dark:bg-gray-700 dark:text-gray-300'">
                                            {{ parseFloat(order.shipping_fee) > 0 ? '₦' + parseFloat(order.shipping_fee).toLocaleString('en-NG', { minimumFractionDigits: 2 }) : (order.delivery_method === 'pickup' ? 'Free Pickup' : 'Free Shipping') }}
                                        </span>
                                    </div>
                                </div>
                                <div v-if="order.coupon_code" class="p-4 bg-emerald-50/70 dark:bg-emerald-950/40 rounded-xl border border-emerald-200 dark:border-emerald-800/60">
                                    <p class="text-xs font-bold uppercase tracking-wider text-emerald-700 dark:text-emerald-400 mb-2 flex items-center gap-1.5">
                                        <span>🎟️</span> Coupon Applied
                                    </p>
                                    <div class="flex flex-wrap items-center justify-between gap-2">
                                        <span class="font-mono font-extrabold text-sm sm:text-base text-emerald-900 dark:text-emerald-200 px-2.5 py-0.5 rounded-lg bg-emerald-100 dark:bg-emerald-900/60 border border-emerald-300 dark:border-emerald-700">
                                            {{ order.coupon_code }}
                                        </span>
                                        <span class="text-sm sm:text-base font-black text-emerald-700 dark:text-emerald-300">
                                            -₦{{ parseFloat(order.discount_amount || 0).toLocaleString('en-NG', { minimumFractionDigits: 2 }) }}
                                        </span>
                                    </div>
                                </div>
                            </div>

                            <!-- POD Custody & Cash Management -->
                            <div v-if="order.payment_method === 'pod'" class="pt-4 border-t border-gray-200 dark:border-gray-700 space-y-4">
                                <!-- Assigned Staff / Rider -->
                                <div class="p-4 bg-gray-50 dark:bg-gray-900/40 rounded-xl border border-gray-200/80 dark:border-gray-700/60">
                                    <p class="text-xs font-bold uppercase tracking-wider text-gray-500 mb-2 flex items-center gap-1.5">
                                        <span>🛵</span> Assigned Courier / Dispatch Rider
                                    </p>
                                    <div v-if="order.assigned_staff" class="font-semibold text-sm text-gray-900 dark:text-white flex items-center justify-between">
                                        <div class="flex items-center gap-2">
                                            <span class="w-7 h-7 rounded-full bg-primary/10 text-primary flex items-center justify-center font-bold text-xs">
                                                👤
                                            </span>
                                            <div>
                                                <span>{{ order.assigned_staff.name }}</span>
                                                <span class="ml-2 px-2 py-0.5 rounded text-[10px] font-bold uppercase tracking-wider bg-gray-200 dark:bg-gray-700 text-gray-600 dark:text-gray-300">{{ order.assigned_staff.role }}</span>
                                            </div>
                                        </div>
                                        <button v-if="isAdmin" @click="order.assigned_staff = null" class="text-xs text-primary hover:underline font-semibold">Change Rider</button>
                                    </div>
                                    <div v-else-if="isAdmin" class="flex flex-col sm:flex-row gap-2 items-stretch sm:items-center">
                                        <select v-model="selectedStaffId" class="text-xs rounded-xl border-gray-300 dark:border-gray-600 dark:bg-gray-700 py-2 flex-1">
                                            <option value="">-- Assign Rider / Staff Member --</option>
                                            <option v-for="st in allStaff" :key="st.id" :value="st.id">{{ st.name }} ({{ st.role }})</option>
                                        </select>
                                        <button @click="assignStaff" class="px-4 py-2 bg-primary text-white text-xs font-bold rounded-xl hover:bg-primary-dark transition shrink-0">Assign Rider</button>
                                    </div>
                                    <span v-else class="text-gray-400 text-xs italic">No rider assigned yet</span>
                                </div>

                                <!-- State 1: Awaiting Collection from Shopper -->
                                <div v-if="order.pod_status === 'awaiting_collection'" class="space-y-3">
                                    <div class="p-4 rounded-xl bg-yellow-50 dark:bg-yellow-950/30 border border-yellow-200 dark:border-yellow-800/50 text-xs text-yellow-800 dark:text-yellow-300">
                                        <div class="flex flex-wrap items-center justify-between gap-2 font-bold mb-1">
                                            <span>Expected Doorstep Collection:</span>
                                            <span class="text-base font-black text-amber-700 dark:text-amber-400">₦{{ Number(order.total_amount).toLocaleString('en-NG', { minimumFractionDigits: 2 }) }}</span>
                                        </div>
                                        <p class="text-[11px] text-yellow-700 dark:text-yellow-400">
                                            Parcel is in delivery transit. Record cash or transfer received after handing the parcel to the customer.
                                        </p>
                                    </div>
                                    <button
                                        @click="openCollectModal"
                                        :disabled="order.status === 'cancelled'"
                                        type="button"
                                        class="w-full py-3 bg-amber-500 hover:bg-amber-600 text-white rounded-xl text-xs font-bold shadow-xs transition flex items-center justify-center gap-2 cursor-pointer disabled:opacity-50 disabled:cursor-not-allowed disabled:hover:bg-amber-500"
                                    >
                                        <span>💵</span> Record Collected Doorstep Payment (₦{{ Number(order.total_amount).toLocaleString() }})
                                    </button>
                                    <p v-if="order.status === 'cancelled'" class="text-[11px] text-red-600 dark:text-red-400 font-semibold text-center">
                                        🔒 Doorstep payment collection is disabled because this order is cancelled.
                                    </p>
                                </div>

                                <!-- State 2: Payment Collected — In Rider Custody / Floating -->
                                <div v-else-if="order.pod_status === 'cash_collected'" class="p-4 rounded-xl bg-amber-50 dark:bg-amber-950/40 border-2 border-amber-400 dark:border-amber-600 space-y-3 shadow-xs">
                                    <div class="flex items-center justify-between">
                                        <span class="text-xs font-bold uppercase tracking-wider text-amber-800 dark:text-amber-300 flex items-center gap-1.5">
                                            <span>💼</span> Payment in Rider Custody (Float)
                                        </span>
                                        <span class="px-2 py-0.5 rounded text-[10px] font-extrabold bg-amber-200 dark:bg-amber-800 text-amber-900 dark:text-amber-100 uppercase">
                                            Awaiting Store Remittance
                                        </span>
                                    </div>
                                    
                                    <div class="text-2xl font-black text-amber-700 dark:text-amber-300">
                                        ₦{{ Number(order.pod_amount_collected || order.total_amount).toLocaleString('en-NG', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) }}
                                    </div>

                                    <div class="text-xs text-amber-800 dark:text-amber-300 space-y-1">
                                        <p>
                                            Collected on <strong>{{ order.pod_collected_at ? new Date(order.pod_collected_at).toLocaleString() : 'Recently' }}</strong>
                                            <span v-if="order.remitted_by_staff"> by <strong>{{ order.remitted_by_staff.name }}</strong></span>.
                                        </p>
                                        <p v-if="order.pod_reconciliation_note" class="font-medium text-amber-900 dark:text-amber-200">
                                            Method: {{ order.pod_reconciliation_note }}
                                        </p>
                                        <p class="text-amber-700/80 dark:text-amber-400/80 text-[11px]">
                                            ⚠️ Rider is holding this payment. Please hand over to store cashier to settle and clear custody.
                                        </p>
                                    </div>

                                    <div v-if="userCanReconcile" class="pt-2">
                                        <button
                                            @click="markCashRemitted"
                                            type="button"
                                            class="w-full py-3 bg-emerald-600 hover:bg-emerald-700 text-white rounded-xl text-xs font-bold shadow-sm transition flex items-center justify-center gap-2 cursor-pointer"
                                        >
                                            <span>📥</span> Confirm Receiving ₦{{ Number(order.pod_amount_collected || order.total_amount).toLocaleString() }} into Store Custody
                                        </button>
                                    </div>
                                    <div v-else class="pt-2">
                                        <div class="p-2.5 rounded-lg bg-amber-100/70 dark:bg-amber-900/40 text-amber-900 dark:text-amber-200 text-xs font-semibold flex items-center gap-2">
                                            <span>⚠️</span> Hand over this money to store cashier or manager to clear your custody.
                                        </div>
                                    </div>
                                </div>

                                <!-- State 3: Remitted to Store — Awaiting Reconcile -->
                                <div v-else-if="order.pod_status === 'remitted_to_store'" class="p-4 rounded-xl bg-purple-50 dark:bg-purple-950/40 border-2 border-purple-300 dark:border-purple-700 space-y-3 shadow-xs">
                                    <div class="flex items-center justify-between">
                                        <span class="text-xs font-bold uppercase tracking-wider text-purple-800 dark:text-purple-300 flex items-center gap-1.5">
                                            <span>📥</span> Remitted to Store
                                        </span>
                                        <span class="px-2 py-0.5 rounded text-[10px] font-extrabold bg-purple-200 dark:bg-purple-800 text-purple-900 dark:text-purple-100 uppercase">
                                            Pending Drawer Lock
                                        </span>
                                    </div>
                                    
                                    <div class="text-2xl font-black text-purple-700 dark:text-purple-300">
                                        ₦{{ Number(order.pod_amount_collected || order.total_amount).toLocaleString('en-NG', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) }}
                                    </div>

                                    <p class="text-xs text-purple-800 dark:text-purple-300">
                                        Handed over to store cashier on <strong>{{ order.pod_remitted_at ? new Date(order.pod_remitted_at).toLocaleString() : 'Recently' }}</strong>. Awaiting drawer reconciliation.
                                    </p>

                                    <div v-if="isAdmin" class="pt-2">
                                        <button
                                            @click="showReconcileModal = true"
                                            type="button"
                                            class="w-full py-3 bg-emerald-600 hover:bg-emerald-700 text-white rounded-xl text-xs font-bold shadow-sm transition flex items-center justify-center gap-2 cursor-pointer"
                                        >
                                            <span>🔒</span> Verify & Reconcile in Cash Drawer
                                        </button>
                                    </div>
                                </div>

                                <!-- State 4: Fully Reconciled -->
                                <div v-else-if="order.pod_status === 'reconciled'" class="p-4 rounded-xl bg-emerald-50 dark:bg-emerald-950/40 border-2 border-emerald-300 dark:border-emerald-700 space-y-2.5 shadow-xs">
                                    <div class="flex items-center justify-between">
                                        <span class="text-xs font-bold uppercase tracking-wider text-emerald-800 dark:text-emerald-300 flex items-center gap-1.5">
                                            <span>🔒</span> Reconciled & Audited in Cash Drawer
                                        </span>
                                        <span class="px-2 py-0.5 rounded text-[10px] font-extrabold bg-emerald-200 dark:bg-emerald-800 text-emerald-900 dark:text-emerald-100 uppercase">
                                            Settled & Cleared
                                        </span>
                                    </div>
                                    
                                    <div class="text-2xl font-black text-emerald-700 dark:text-emerald-300">
                                        ₦{{ Number(order.pod_amount_collected || order.total_amount).toLocaleString('en-NG', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) }}
                                    </div>

                                    <p class="text-xs text-emerald-800 dark:text-emerald-300">
                                        Verified and locked into till by <strong>{{ order.reconciled_by_admin?.name || 'Administrator' }}</strong> on {{ order.pod_reconciled_at ? new Date(order.pod_reconciled_at).toLocaleString() : 'Recently' }}.
                                    </p>
                                    <p v-if="order.pod_reconciliation_note" class="text-xs italic text-emerald-700 dark:text-emerald-400">
                                        "{{ order.pod_reconciliation_note }}"
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Order Lifecycle & Status History Timeline -->
                    <div class="bg-white dark:bg-gray-800 shadow-sm rounded-xl overflow-hidden border dark:border-gray-700">
                        <div class="p-6 border-b dark:border-gray-700 bg-gray-50 dark:bg-gray-900/50 flex items-center justify-between">
                            <div class="flex items-center gap-3">
                                <span class="w-9 h-9 rounded-xl bg-primary/10 text-primary flex items-center justify-center font-bold text-base">
                                    📜
                                </span>
                                <div>
                                    <h2 class="text-xl font-bold">Status Lifecycle & Audit History</h2>
                                    <p class="text-xs text-gray-500">Track all operational changes, status transitions, and user actions on this order.</p>
                                </div>
                            </div>
                            <span class="text-xs px-2.5 py-1 rounded-full bg-gray-100 dark:bg-gray-700 font-bold text-gray-600 dark:text-gray-300">
                                {{ order.status_histories?.length || 0 }} Event(s)
                            </span>
                        </div>

                        <div class="p-6 space-y-6">
                            <!-- Quick Add Audit Note Input -->
                            <div class="p-3 bg-gray-50 dark:bg-gray-900/40 rounded-xl border border-gray-200 dark:border-gray-700/60">
                                <label class="block text-xs font-bold uppercase tracking-wider text-gray-600 dark:text-gray-400 mb-1.5 flex items-center gap-1.5">
                                    <span>✍️</span> Add Internal Audit / Operational Note
                                </label>
                                <div class="flex flex-col sm:flex-row gap-2">
                                    <input
                                        v-model="newNote"
                                        @keydown.enter.prevent="submitAddNote"
                                        type="text"
                                        placeholder="e.g., Customer confirmed doorstep delivery for 4 PM..."
                                        class="flex-1 text-xs rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-800 dark:text-white px-3 py-2 focus:ring-primary"
                                    />
                                    <button
                                        @click="submitAddNote"
                                        type="button"
                                        :disabled="isSubmittingNote || !newNote.trim()"
                                        class="px-4 py-2 bg-primary hover:opacity-90 disabled:opacity-40 disabled:cursor-not-allowed text-white rounded-lg text-xs font-bold transition flex items-center justify-center gap-1.5 shrink-0"
                                    >
                                        <span>Add Note</span>
                                    </button>
                                </div>
                            </div>

                            <!-- Timeline List -->
                            <div v-if="order.status_histories && order.status_histories.length > 0" class="relative pl-6 sm:pl-8 space-y-6 before:absolute before:left-3 before:top-2 before:bottom-2 before:w-0.5 before:bg-gray-200 dark:before:bg-gray-700">
                                <div
                                    v-for="(history, idx) in order.status_histories"
                                    :key="history.id || idx"
                                    class="relative group"
                                >
                                    <!-- Node Icon -->
                                    <div
                                        class="absolute -left-6 sm:-left-8 top-0.5 w-6 h-6 rounded-full flex items-center justify-center text-xs shadow-xs border-2 border-white dark:border-gray-800"
                                        :class="getEventBadgeClass(history.event, history)"
                                    >
                                        {{ getEventIcon(history.event, history) }}
                                    </div>

                                    <!-- Content Card -->
                                    <div class="bg-gray-50/70 dark:bg-gray-900/40 rounded-xl p-3.5 border border-gray-100 dark:border-gray-700/60 transition hover:border-gray-300 dark:hover:border-gray-600">
                                        <div class="flex flex-wrap items-center justify-between gap-2 mb-1.5">
                                            <div class="flex flex-wrap items-center gap-2">
                                                <span class="font-bold text-xs capitalize px-2.5 py-0.5 rounded-full" :class="getEventBadgeClass(history.event, history)">
                                                    {{ formatEventTitle(history.event, history) }}
                                                </span>
                                                <span
                                                    v-if="history.event === 'payment_collected' && getPaymentMethod(history)"
                                                    class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-xs font-bold bg-amber-100 dark:bg-amber-900/60 text-amber-900 dark:text-amber-200 border border-amber-300 dark:border-amber-700 shadow-xs"
                                                >
                                                    <span>{{ getPaymentMethodIcon(getPaymentMethod(history)) }}</span>
                                                    <span>Rider Collection Method: <strong class="underline decoration-amber-400">{{ getPaymentMethod(history) }}</strong></span>
                                                </span>
                                            </div>
                                            <span class="text-[11px] font-mono text-gray-500 dark:text-gray-400">
                                                {{ formatEventDate(history.created_at) }}
                                            </span>
                                        </div>

                                        <!-- Comment description -->
                                        <p class="text-xs text-gray-700 dark:text-gray-200 leading-relaxed font-medium">
                                            {{ history.comment }}
                                        </p>

                                        <!-- Actor / User badge -->
                                        <div class="mt-2.5 pt-2 border-t border-gray-200/60 dark:border-gray-700/40 flex items-center justify-between text-[11px] text-gray-500">
                                            <div class="flex items-center gap-1.5">
                                                <span class="w-4 h-4 rounded-full bg-gray-200 dark:bg-gray-700 flex items-center justify-center text-[9px] font-bold">
                                                    {{ history.event === 'order_placed' ? '⚙️' : '👤' }}
                                                </span>
                                                <span class="font-semibold text-gray-800 dark:text-gray-200">
                                                    {{ history.event === 'order_placed' ? 'System' : (history.user ? history.user.name : 'System') }}
                                                </span>
                                                <span v-if="history.event !== 'order_placed' && history.user?.role" class="px-1.5 py-0.2 rounded text-[9px] font-bold uppercase tracking-wider bg-gray-200/80 dark:bg-gray-700 text-gray-600 dark:text-gray-300">
                                                    {{ history.user.role }}
                                                </span>
                                            </div>
                                            <span v-if="history.to_status" class="font-mono text-[10px] text-gray-400 uppercase">
                                                Status: {{ history.to_status }}
                                            </span>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div v-else class="text-center py-8 text-gray-400 text-xs">
                                No history recorded for this order yet.
                            </div>
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

                    <!-- POS Fulfillment & Reservation -->
                    <div v-if="order.has_pos_items || order.pos_reservation_reference || order.inventory_reference" class="bg-white dark:bg-gray-800 shadow-sm rounded-xl overflow-hidden border dark:border-gray-700">
                        <div class="p-6 border-b dark:border-gray-700 bg-primary-soft flex items-center justify-between">
                            <h2 class="text-xl font-bold flex items-center gap-2 text-primary">
                                <svg class="w-5 h-5 text-primary" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"/></svg>
                                POS Fulfillment
                            </h2>
                            <span class="px-2.5 py-1 text-xs font-bold uppercase rounded-full"
                                :class="{
                                    'bg-amber-100 text-amber-800 dark:bg-amber-900/40 dark:text-amber-300': order.pos_reservation_status === 'reserved',
                                    'bg-green-100 text-green-800 dark:bg-green-900/40 dark:text-green-300': order.pos_reservation_status === 'confirmed',
                                    'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-300': order.pos_reservation_status === 'released',
                                    'bg-red-100 text-red-800 dark:bg-red-900/40 dark:text-red-300': order.pos_reservation_status === 'expired',
                                    'theme-primary-badge': !order.pos_reservation_status || order.pos_reservation_status === 'none',
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
                            <!-- Locked if already cancelled -->
                            <div v-if="order.status === 'cancelled'" class="p-4 rounded-xl bg-red-50 dark:bg-red-950/40 border border-red-200 dark:border-red-900/50 text-red-700 dark:text-red-400 space-y-1.5">
                                <div class="font-bold flex items-center gap-2 text-sm">
                                    <span class="text-base">🔒</span> Order Permanently Cancelled
                                </div>
                                <p class="text-xs text-red-600/80 dark:text-red-400/80 leading-relaxed">
                                    This order has been cancelled and its inventory restored. The status is permanently locked and can no longer be modified.
                                </p>
                            </div>
                            <!-- Locked if already completed -->
                            <div v-else-if="order.status === 'completed'" class="p-4 rounded-xl bg-emerald-50 dark:bg-emerald-950/40 border border-emerald-200 dark:border-emerald-900/50 text-emerald-800 dark:text-emerald-300 space-y-1.5">
                                <div class="font-bold flex items-center gap-2 text-sm">
                                    <span class="text-base">✅</span> Order Completed & Closed
                                </div>
                                <p class="text-xs text-emerald-700/80 dark:text-emerald-400/80 leading-relaxed">
                                    This order has been fulfilled, stock deductions are finalized, and payments are settled. The status is permanently closed and cannot be modified.
                                </p>
                            </div>
                            <!-- Restricted if lacking permission (e.g. rider role) -->
                            <div v-else-if="!userCanUpdateStatus" class="p-4 rounded-xl bg-gray-50 dark:bg-gray-900/40 border border-gray-200 dark:border-gray-700 text-gray-600 dark:text-gray-300 space-y-1.5">
                                <div class="font-bold flex items-center gap-2 text-sm text-gray-800 dark:text-gray-200">
                                    <span class="text-base">🔒</span> Status Update Restricted
                                </div>
                                <p class="text-xs text-gray-500 dark:text-gray-400 leading-relaxed">
                                    Only managers and store fulfillment staff are authorized to update order fulfillment status. Riders are restricted to recording doorstep payment collections and cash remittance.
                                </p>
                            </div>
                            <form v-else @submit.prevent="handleStatusSubmit" class="space-y-4">
                                <select v-model="form.status" class="w-full border-gray-300 dark:border-gray-600 dark:bg-gray-700 rounded-md font-medium text-gray-700 dark:text-gray-200 py-3">
                                    <option value="pending">Pending</option>
                                    <option value="processing">Processing</option>
                                    <option value="paid">Paid</option>
                                    <option value="shipped">Shipped</option>
                                    <option value="completed">Completed</option>
                                    <option value="cancelled">Cancelled</option>
                                </select>
                                <button type="submit" :disabled="form.processing" class="w-full bg-primary hover:bg-primary-dark text-white font-bold py-3 rounded-md transition shadow hover:shadow-md disabled:opacity-50">Update Order</button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- ── Modal: Record Collected POD Payment ──────────────── -->
        <div v-if="showCollectModal" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/60 backdrop-blur-xs">
            <div class="bg-white dark:bg-gray-800 rounded-2xl max-w-md w-full p-6 shadow-2xl border border-gray-200 dark:border-gray-700">
                <div class="flex items-center gap-2.5 mb-2">
                    <span class="w-8 h-8 rounded-xl bg-amber-100 dark:bg-amber-900/50 text-amber-600 flex items-center justify-center font-bold text-base">
                        💵
                    </span>
                    <h3 class="text-lg font-bold text-gray-900 dark:text-white">
                        Record Doorstep Payment
                    </h3>
                </div>
                <p class="text-xs text-gray-500 mb-4">
                    Confirm that payment has been received from the customer for Order <strong>#{{ order.id }}</strong>.
                </p>

                <div class="space-y-4 mb-6">
                    <div>
                        <label class="block text-xs font-bold uppercase tracking-wider text-gray-600 dark:text-gray-400 mb-1">
                            Amount Collected (₦)
                        </label>
                        <input
                            v-model="collectedAmount"
                            type="number"
                            step="0.01"
                            :min="order.total_amount"
                            class="w-full text-base font-bold rounded-xl border-gray-300 dark:border-gray-600 dark:bg-gray-700 p-2.5 text-gray-900 dark:text-white focus:ring-primary"
                            placeholder="Enter amount collected"
                        />
                        <p class="text-[11px] mt-1" :class="Number(collectedAmount) < Number(order.total_amount) ? 'text-red-500 font-bold' : 'text-gray-400'">
                            Expected full amount: ₦{{ Number(order.total_amount).toLocaleString() }}
                            <span v-if="Number(collectedAmount) < Number(order.total_amount)" class="block text-red-500 font-bold">
                                ⚠️ Cannot collect less than order total!
                            </span>
                        </p>
                    </div>

                    <div>
                        <label class="block text-xs font-bold uppercase tracking-wider text-gray-600 dark:text-gray-400 mb-1">
                            Payment Mode Received
                        </label>
                        <select
                            v-model="collectionChannel"
                            class="w-full text-xs font-medium rounded-xl border-gray-300 dark:border-gray-600 dark:bg-gray-700 p-2.5"
                        >
                            <option value="cash">💵 Physical Cash</option>
                            <option value="bank_transfer">🏦 Instant Bank Transfer</option>
                            <option value="pos_terminal">💳 Handheld POS Terminal</option>
                        </select>
                    </div>
                </div>

                <div class="flex items-center justify-end gap-2">
                    <button
                        type="button"
                        @click="showCollectModal = false"
                        class="px-4 py-2 text-xs font-semibold text-gray-600 hover:bg-gray-100 dark:text-gray-300 dark:hover:bg-gray-700 rounded-xl"
                    >
                        Cancel
                    </button>
                    <button
                        type="button"
                        @click="submitCollectCash"
                        class="px-4 py-2 text-xs font-bold text-white bg-amber-600 hover:bg-amber-700 rounded-xl shadow-xs"
                    >
                        Confirm Collection
                    </button>
                </div>
            </div>
        </div>

        <!-- ── Modal: Reconcile POD Cash ─────────────────────────── -->
        <div v-if="showReconcileModal" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/60 backdrop-blur-xs">
            <div class="bg-white dark:bg-gray-800 rounded-2xl max-w-md w-full p-6 shadow-2xl border border-gray-200 dark:border-gray-700">
                <h3 class="text-lg font-bold text-gray-900 dark:text-white mb-2">
                    Verify & Reconcile POD Cash
                </h3>
                <p class="text-xs text-gray-500 mb-4">
                    Confirm that physical cash of ₦{{ Number(order.total_amount).toLocaleString() }} has been verified and deposited into the store cash drawer.
                </p>

                <div class="mb-4">
                    <label class="block text-xs font-bold uppercase text-gray-500 mb-1">Reconciliation Note</label>
                    <textarea
                        v-model="reconcileNote"
                        rows="2"
                        placeholder="e.g. Received from dispatch rider, deposited in till"
                        class="w-full text-xs rounded-xl border-gray-300 dark:border-gray-600 dark:bg-gray-700 p-2.5"
                    ></textarea>
                </div>

                <div class="flex items-center justify-end gap-2">
                    <button
                        type="button"
                        @click="showReconcileModal = false"
                        class="px-4 py-2 text-xs font-semibold text-gray-600 hover:bg-gray-100 rounded-xl"
                    >
                        Cancel
                    </button>
                    <button
                        type="button"
                        @click="submitReconcile"
                        class="px-4 py-2 text-xs font-bold text-white bg-emerald-600 hover:bg-emerald-700 rounded-xl"
                    >
                        Confirm Reconciled
                    </button>
                </div>
            </div>
        </div>

        <!-- ── Modal: Warning Prompt for Order Cancellation ──────────────── -->
        <div v-if="showCancelPrompt" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/60 backdrop-blur-xs">
            <div class="bg-white dark:bg-gray-800 rounded-2xl max-w-md w-full p-6 shadow-2xl border border-red-200 dark:border-red-900/60 space-y-4">
                <div class="flex items-center gap-3 text-red-600 dark:text-red-400">
                    <span class="w-10 h-10 rounded-xl bg-red-100 dark:bg-red-950/60 flex items-center justify-center text-xl flex-shrink-0">
                        ⚠️
                    </span>
                    <div>
                        <h3 class="text-base font-bold text-gray-900 dark:text-white">
                            Permanently Cancel Order #{{ order.id }}?
                        </h3>
                        <p class="text-xs text-red-600 dark:text-red-400 font-semibold">
                            This action cannot be undone
                        </p>
                    </div>
                </div>

                <div class="p-3.5 bg-red-50/80 dark:bg-red-950/30 rounded-xl border border-red-100 dark:border-red-900/40 text-xs text-gray-700 dark:text-gray-300 space-y-2">
                    <p class="font-medium">
                        Are you sure you want to cancel this order?
                    </p>
                    <ul class="list-disc pl-4 space-y-1 text-gray-600 dark:text-gray-400 text-[11px]">
                        <li>All eCommerce & physical POS store inventory will be automatically restored to shelves.</li>
                        <li>The order will be permanently locked and cannot be changed back to active.</li>
                    </ul>
                </div>

                <div class="flex items-center justify-end gap-2.5 pt-2">
                    <button
                        @click="dismissCancelPrompt"
                        type="button"
                        class="px-4 py-2 bg-gray-100 hover:bg-gray-200 dark:bg-gray-700 dark:hover:bg-gray-600 text-gray-700 dark:text-gray-200 rounded-xl text-xs font-semibold transition"
                    >
                        Nevermind, Keep Active
                    </button>
                    <button
                        @click="confirmCancelOrder"
                        :disabled="form.processing"
                        type="button"
                        class="px-4 py-2 bg-red-600 hover:bg-red-700 text-white rounded-xl text-xs font-bold transition shadow-sm disabled:opacity-50 flex items-center gap-1.5"
                    >
                        <span>✓ Yes, Cancel Permanently</span>
                    </button>
                </div>
            </div>
        </div>

        <!-- ── Modal: Warning Prompt for Order Completion ──────────────── -->
        <div v-if="showCompletePrompt" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/60 backdrop-blur-xs">
            <div class="bg-white dark:bg-gray-800 rounded-2xl max-w-md w-full p-6 shadow-2xl border border-emerald-200 dark:border-emerald-900/60 space-y-4">
                <div class="flex items-center gap-3 text-emerald-600 dark:text-emerald-400">
                    <span class="w-10 h-10 rounded-xl bg-emerald-100 dark:bg-emerald-950/60 flex items-center justify-center text-xl flex-shrink-0">
                        📦
                    </span>
                    <div>
                        <h3 class="text-base font-bold text-gray-900 dark:text-white">
                            Mark Order #{{ order.id }} as Completed?
                        </h3>
                        <p class="text-xs text-emerald-600 dark:text-emerald-400 font-semibold">
                            Once completed, this order cannot be undone
                        </p>
                    </div>
                </div>

                <div class="p-3.5 bg-emerald-50/80 dark:bg-emerald-950/30 rounded-xl border border-emerald-100 dark:border-emerald-900/40 text-xs text-gray-700 dark:text-gray-300 space-y-2">
                    <p class="font-medium text-emerald-900 dark:text-emerald-200">
                        Please confirm order fulfillment completion:
                    </p>
                    <ul class="list-disc pl-4 space-y-1 text-gray-600 dark:text-gray-400 text-[11px]">
                        <li>Customer delivery and payment collection are fully settled.</li>
                        <li>Physical stock deductions on POS counter are permanently committed.</li>
                        <li>The order will be closed and its status cannot be changed again.</li>
                    </ul>
                </div>

                <div class="flex items-center justify-end gap-2.5 pt-2">
                    <button
                        @click="dismissCompletePrompt"
                        type="button"
                        class="px-4 py-2 bg-gray-100 hover:bg-gray-200 dark:bg-gray-700 dark:hover:bg-gray-600 text-gray-700 dark:text-gray-200 rounded-xl text-xs font-semibold transition cursor-pointer"
                    >
                        Nevermind, Go Back
                    </button>
                    <button
                        @click="confirmCompleteOrder"
                        :disabled="form.processing"
                        type="button"
                        class="px-4 py-2 bg-emerald-600 hover:bg-emerald-700 text-white rounded-xl text-xs font-bold transition shadow-sm disabled:opacity-50 flex items-center gap-1.5 cursor-pointer"
                    >
                        <span>✓ Yes, Complete & Close Order</span>
                    </button>
                </div>
            </div>
        </div>
    </AdminLayout>
</template>
