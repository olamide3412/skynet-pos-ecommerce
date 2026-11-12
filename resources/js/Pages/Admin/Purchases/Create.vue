<script setup>
import { ref, computed, onMounted, onUnmounted, nextTick } from 'vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';

const props = defineProps({
    eligibleProducts: Array,
    initialProducts: Array,
    recentSuppliers: Array,
    suggestedPoNumber: String,
});

const todayStr = new Date().toISOString().split('T')[0];

const form = useForm({
    purchase_number: props.suggestedPoNumber || '',
    supplier_name: '',
    invoice_reference: '',
    purchase_date: todayStr,
    payment_status: 'paid',
    notes: '',
    items: [
        {
            product_id: '',
            selected_product: null,
            variant_id: null,
            quantity: 1,
            unit_cost: 0,
        }
    ],
});

// Row-level Searchable Product Picker state
const openDropdownIndex = ref(null);
const rowSearchQuery = ref('');
const rowSearchInputRef = ref(null);
const rowSearchResults = ref(props.initialProducts || props.eligibleProducts || []);
const isSearching = ref(false);
let rowSearchDebounce = null;

const onRowSearchInput = () => {
    clearTimeout(rowSearchDebounce);
    rowSearchDebounce = setTimeout(async () => {
        const q = rowSearchQuery.value.trim();
        isSearching.value = true;
        try {
            const res = await window.axios.get(route('admin.purchases.products.search'), {
                params: { q, ecommerce_only: 1 }
            });
            rowSearchResults.value = res.data.products || [];
        } catch (err) {
            console.error('Failed to search ecommerce products', err);
        } finally {
            isSearching.value = false;
        }
    }, 250);
};

const toggleRowDropdown = (index) => {
    if (openDropdownIndex.value === index) {
        openDropdownIndex.value = null;
    } else {
        openDropdownIndex.value = index;
        rowSearchQuery.value = '';
        rowSearchResults.value = props.initialProducts || props.eligibleProducts || [];
        nextTick(() => {
            rowSearchInputRef.value?.focus();
        });
    }
};

const selectRowProduct = (item, product) => {
    item.product_id = product.id;
    item.selected_product = product;
    item.unit_cost = product.cost_price ? Number(product.cost_price) : 0;
    item.variant_id = null;
    openDropdownIndex.value = null;
    rowSearchQuery.value = '';
};

const clearRowProduct = (item) => {
    item.product_id = '';
    item.selected_product = null;
    item.variant_id = null;
    item.unit_cost = 0;
};

const handleClickOutside = (e) => {
    if (openDropdownIndex.value !== null && !e.target.closest('.row-product-picker-container')) {
        openDropdownIndex.value = null;
    }
};

onMounted(() => {
    document.addEventListener('mousedown', handleClickOutside);
});

onUnmounted(() => {
    document.removeEventListener('mousedown', handleClickOutside);
});

const addItemRow = () => {
    form.items.push({
        product_id: '',
        selected_product: null,
        variant_id: null,
        quantity: 1,
        unit_cost: 0,
    });
};

const removeItemRow = (index) => {
    if (form.items.length > 1) {
        form.items.splice(index, 1);
        if (openDropdownIndex.value === index) {
            openDropdownIndex.value = null;
        }
    }
};

const getProduct = (item) => {
    if (item.selected_product) return item.selected_product;
    return (props.initialProducts || props.eligibleProducts || []).find(p => p.id === Number(item.product_id)) || null;
};

const totalCost = computed(() => {
    return form.items.reduce((sum, item) => {
        const qty = Number(item.quantity) || 0;
        const cost = Number(item.unit_cost) || 0;
        return sum + (qty * cost);
    }, 0);
});

const totalUnits = computed(() => {
    return form.items.reduce((sum, item) => {
        return sum + (Number(item.quantity) || 0);
    }, 0);
});

const formatCurrency = (val) => {
    return '₦' + Number(val || 0).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
};

const submit = () => {
    form.post(route('admin.purchases.store'));
};
</script>

<template>
    <Head title="Receive Stock (New Purchase) - Admin" />

    <AdminLayout>
        <div class="max-w-5xl mx-auto space-y-6">
            <!-- Header -->
            <div class="flex items-center justify-between">
                <div>
                    <div class="flex items-center gap-2 text-xs text-gray-500 mb-1">
                        <Link :href="route('admin.purchases.index')" class="hover:underline">Stock Purchases</Link>
                        <span>/</span>
                        <span class="text-gray-900 dark:text-gray-100 font-semibold">New Receiving Order</span>
                    </div>
                    <h1 class="text-2xl font-bold text-gray-900 dark:text-white flex items-center gap-2">
                        Receive Stock & Restock Inventory
                    </h1>
                </div>
                <Link
                    :href="route('admin.purchases.index')"
                    class="px-4 py-2 border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-gray-700 dark:text-gray-200 text-sm font-semibold rounded-xl hover:bg-gray-50 dark:hover:bg-gray-700 shadow-xs transition-all"
                >
                    Cancel
                </Link>
            </div>

            <form @submit.prevent="submit" class="space-y-6">
                <!-- Supplier & Purchase Order Meta Details -->
                <div class="bg-white dark:bg-gray-800 p-6 rounded-2xl border border-gray-200/80 dark:border-gray-700/80 shadow-xs space-y-4">
                    <h2 class="text-base font-bold text-gray-900 dark:text-white pb-3 border-b border-gray-200 dark:border-gray-700 flex items-center gap-2">
                        <svg class="w-5 h-5 text-primary" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                        </svg>
                        Purchase Header Details
                    </h2>

                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <div>
                            <label class="block text-xs font-bold text-gray-700 dark:text-gray-300 uppercase tracking-wide mb-1.5">
                                Purchase / PO Number <span class="text-rose-500">*</span>
                            </label>
                            <input
                                v-model="form.purchase_number"
                                type="text"
                                required
                                placeholder="e.g. PO-202611-001"
                                class="w-full text-sm font-mono bg-gray-50 dark:bg-gray-900 border border-gray-300 dark:border-gray-700 rounded-xl px-3 py-2.5 focus:ring-primary focus:border-primary text-gray-900 dark:text-gray-100"
                            />
                            <p v-if="form.errors.purchase_number" class="text-xs text-rose-500 mt-1">{{ form.errors.purchase_number }}</p>
                        </div>

                        <div>
                            <label class="block text-xs font-bold text-gray-700 dark:text-gray-300 uppercase tracking-wide mb-1.5">
                                Supplier / Vendor Name <span class="text-rose-500">*</span>
                            </label>
                            <input
                                v-model="form.supplier_name"
                                type="text"
                                list="suppliersList"
                                required
                                placeholder="Type or select supplier..."
                                class="w-full text-sm bg-gray-50 dark:bg-gray-900 border border-gray-300 dark:border-gray-700 rounded-xl px-3 py-2.5 focus:ring-primary focus:border-primary text-gray-900 dark:text-gray-100"
                            />
                            <datalist id="suppliersList">
                                <option v-for="sup in recentSuppliers" :key="sup" :value="sup" />
                            </datalist>
                            <p v-if="form.errors.supplier_name" class="text-xs text-rose-500 mt-1">{{ form.errors.supplier_name }}</p>
                        </div>

                        <div>
                            <label class="block text-xs font-bold text-gray-700 dark:text-gray-300 uppercase tracking-wide mb-1.5">
                                Invoice / Receipt Ref #
                            </label>
                            <input
                                v-model="form.invoice_reference"
                                type="text"
                                placeholder="Paper bill / invoice #"
                                class="w-full text-sm bg-gray-50 dark:bg-gray-900 border border-gray-300 dark:border-gray-700 rounded-xl px-3 py-2.5 focus:ring-primary focus:border-primary text-gray-900 dark:text-gray-100"
                            />
                        </div>

                        <div>
                            <label class="block text-xs font-bold text-gray-700 dark:text-gray-300 uppercase tracking-wide mb-1.5">
                                Purchase / Receiving Date <span class="text-rose-500">*</span>
                            </label>
                            <input
                                v-model="form.purchase_date"
                                type="date"
                                required
                                class="w-full text-sm bg-gray-50 dark:bg-gray-900 border border-gray-300 dark:border-gray-700 rounded-xl px-3 py-2.5 focus:ring-primary focus:border-primary text-gray-900 dark:text-gray-100"
                            />
                        </div>

                        <div>
                            <label class="block text-xs font-bold text-gray-700 dark:text-gray-300 uppercase tracking-wide mb-1.5">
                                Payment Status <span class="text-rose-500">*</span>
                            </label>
                            <select
                                v-model="form.payment_status"
                                class="w-full text-sm bg-gray-50 dark:bg-gray-900 border border-gray-300 dark:border-gray-700 rounded-xl px-3 py-2.5 focus:ring-primary focus:border-primary text-gray-900 dark:text-gray-100"
                            >
                                <option value="paid">Paid (Fully Settled)</option>
                                <option value="pending">Pending (Unpaid / Credit)</option>
                                <option value="partial">Partially Paid</option>
                            </select>
                        </div>

                        <div>
                            <label class="block text-xs font-bold text-gray-700 dark:text-gray-300 uppercase tracking-wide mb-1.5">
                                Notes / Memo
                            </label>
                            <input
                                v-model="form.notes"
                                type="text"
                                placeholder="Optional delivery notes..."
                                class="w-full text-sm bg-gray-50 dark:bg-gray-900 border border-gray-300 dark:border-gray-700 rounded-xl px-3 py-2.5 focus:ring-primary focus:border-primary text-gray-900 dark:text-gray-100"
                            />
                        </div>
                    </div>
                </div>

                <!-- Received Line Items -->
                <div class="bg-white dark:bg-gray-800 p-6 rounded-2xl border border-gray-200/80 dark:border-gray-700/80 shadow-xs space-y-4">
                    <div class="flex items-center justify-between pb-3 border-b border-gray-200 dark:border-gray-700">
                        <div>
                            <h2 class="text-base font-bold text-gray-900 dark:text-white flex items-center gap-2">
                                <svg class="w-5 h-5 text-emerald-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4" />
                                </svg>
                                Products Received (Ecommerce Only)
                            </h2>
                            <p class="text-xs text-gray-400 mt-0.5">
                                Select goods received from this supplier. Their available stock and cost prices will update automatically upon saving.
                            </p>
                        </div>
                        <button
                            type="button"
                            @click="addItemRow"
                            class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-emerald-50 dark:bg-emerald-900/30 text-emerald-700 dark:text-emerald-300 border border-emerald-200 dark:border-emerald-800 text-xs font-bold rounded-xl hover:bg-emerald-100 dark:hover:bg-emerald-900/50 transition-all"
                        >
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                            </svg>
                            Add Line Item
                        </button>
                    </div>

                    <div class="space-y-3">
                        <div
                            v-for="(item, index) in form.items"
                            :key="index"
                            class="p-4 bg-gray-50/70 dark:bg-gray-900/50 border border-gray-200 dark:border-gray-700 rounded-xl space-y-3"
                        >
                            <div class="grid grid-cols-1 md:grid-cols-12 gap-3 items-center">
                                <!-- Searchable Ecommerce Product Picker -->
                                <div class="relative row-product-picker-container" :class="getProduct(item)?.variantMappings?.length ? 'md:col-span-4' : 'md:col-span-5'">
                                    <label class="block text-[11px] font-bold text-gray-500 uppercase mb-1">
                                        Product <span class="text-rose-500">*</span>
                                    </label>

                                    <!-- Trigger Button -->
                                    <div
                                        @click="toggleRowDropdown(index)"
                                        class="w-full min-h-[42px] px-3 py-1.5 border rounded-xl bg-white dark:bg-gray-800 cursor-pointer flex items-center justify-between gap-2 transition text-sm shadow-2xs"
                                        :class="openDropdownIndex === index ? 'border-primary ring-2 ring-primary/20' : 'border-gray-300 dark:border-gray-600 hover:border-gray-400 dark:hover:border-gray-500'"
                                    >
                                        <div v-if="getProduct(item)" class="flex-1 min-w-0 flex items-center gap-1.5">
                                            <span class="text-[9px] font-bold px-1.5 py-0.5 rounded bg-emerald-100 text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-300 uppercase shrink-0">
                                                Ecom
                                            </span>
                                            <div class="truncate">
                                                <span class="font-semibold text-gray-900 dark:text-white truncate block">
                                                    {{ getProduct(item).name }}
                                                </span>
                                                <span class="text-[11px] text-gray-500 dark:text-gray-400 flex items-center gap-2">
                                                    <span v-if="getProduct(item).sku">SKU: <strong class="font-mono">{{ getProduct(item).sku }}</strong></span>
                                                    <span>Stock: <strong>{{ getProduct(item).stock }}</strong></span>
                                                    <span v-if="getProduct(item).cost_price">Last Cost: <strong>₦{{ Number(getProduct(item).cost_price).toLocaleString() }}</strong></span>
                                                </span>
                                            </div>
                                        </div>
                                        <span v-else class="text-gray-400 dark:text-gray-500 text-xs truncate">
                                            -- Search & Select Ecommerce Product --
                                        </span>

                                        <div class="flex items-center gap-1 shrink-0">
                                            <button
                                                v-if="item.product_id"
                                                type="button"
                                                @click.stop="clearRowProduct(item)"
                                                class="p-1 rounded-md text-gray-400 hover:text-rose-500 hover:bg-gray-100 dark:hover:bg-gray-700 transition"
                                                title="Clear product"
                                            >
                                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                                                </svg>
                                            </button>
                                            <svg
                                                class="w-4 h-4 text-gray-400 transition-transform duration-150"
                                                :class="openDropdownIndex === index ? 'rotate-180 text-primary' : ''"
                                                fill="none"
                                                stroke="currentColor"
                                                viewBox="0 0 24 24"
                                            >
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                                            </svg>
                                        </div>
                                    </div>

                                    <!-- Dropdown Panel -->
                                    <div
                                        v-if="openDropdownIndex === index"
                                        class="absolute left-0 right-0 z-50 mt-1.5 bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-xl shadow-2xl overflow-hidden min-w-[300px] sm:min-w-[360px]"
                                    >
                                        <!-- Search Input Header -->
                                        <div class="p-2.5 border-b border-gray-200 dark:border-gray-700 bg-gray-50/80 dark:bg-gray-900/60">
                                            <div class="relative">
                                                <input
                                                    ref="rowSearchInputRef"
                                                    v-model="rowSearchQuery"
                                                    @input="onRowSearchInput"
                                                    type="text"
                                                    placeholder="Search by name, SKU, or ID..."
                                                    @keydown.escape.stop="openDropdownIndex = null"
                                                    class="w-full text-xs pl-8 pr-8 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-900 text-gray-900 dark:text-gray-100 focus:ring-2 focus:ring-primary focus:border-primary placeholder-gray-400"
                                                />
                                                <svg class="w-4 h-4 text-gray-400 absolute left-2.5 top-2.5 pointer-events-none" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                                                </svg>
                                                <button
                                                    v-if="rowSearchQuery"
                                                    type="button"
                                                    @click="rowSearchQuery = ''; onRowSearchInput(); rowSearchInputRef?.focus()"
                                                    class="absolute right-2.5 top-2.5 text-gray-400 hover:text-gray-600 dark:hover:text-gray-200"
                                                >
                                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                                                    </svg>
                                                </button>
                                            </div>
                                            <div class="flex items-center justify-between mt-1.5 px-1 text-[11px] text-gray-500 dark:text-gray-400">
                                                <span v-if="isSearching" class="inline-flex items-center gap-1 text-primary">
                                                    <svg class="animate-spin w-3 h-3" fill="none" viewBox="0 0 24 24">
                                                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                                                    </svg>
                                                    Searching...
                                                </span>
                                                <span v-else>
                                                    {{ rowSearchResults.length }} matching item(s)
                                                </span>
                                                <span class="text-gray-400">Showing first 25</span>
                                            </div>
                                        </div>

                                        <!-- Options List -->
                                        <div class="max-h-60 overflow-y-auto divide-y divide-gray-100 dark:divide-gray-700/60">
                                            <button
                                                v-for="p in rowSearchResults"
                                                :key="p.id"
                                                type="button"
                                                @click="selectRowProduct(item, p)"
                                                class="w-full text-left px-3.5 py-2.5 flex items-center justify-between gap-2 text-xs transition"
                                                :class="Number(item.product_id) === Number(p.id) ? 'bg-primary/10 text-primary font-semibold' : 'hover:bg-gray-50 dark:hover:bg-gray-700/60 text-gray-800 dark:text-gray-200'"
                                            >
                                                <div class="min-w-0 flex-1">
                                                    <div class="font-semibold text-gray-900 dark:text-white truncate">
                                                        {{ p.name }}
                                                    </div>
                                                    <div class="text-[11px] text-gray-500 dark:text-gray-400 mt-0.5 flex items-center gap-3">
                                                        <span v-if="p.sku">SKU: <strong class="font-mono">{{ p.sku }}</strong></span>
                                                        <span>Stock: <strong>{{ p.stock }}</strong></span>
                                                        <span v-if="p.cost_price">Cost: <strong>₦{{ Number(p.cost_price).toLocaleString() }}</strong></span>
                                                    </div>
                                                </div>
                                                <div class="text-right shrink-0">
                                                    <span v-if="Number(item.product_id) === Number(p.id)" class="text-primary font-bold text-sm">✓</span>
                                                    <span v-else class="text-[11px] text-emerald-600 dark:text-emerald-400 font-medium">Select</span>
                                                </div>
                                            </button>

                                            <div v-if="!isSearching && !rowSearchResults.length" class="p-4 text-center text-xs text-gray-400">
                                                No ecommerce products found matching "{{ rowSearchQuery }}".
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Variant (if any) -->
                                <div class="md:col-span-2" v-if="getProduct(item)?.variantMappings?.length">
                                    <label class="block text-[11px] font-bold text-gray-500 uppercase mb-1">Variant</label>
                                    <select
                                        v-model="item.variant_id"
                                        class="w-full text-xs bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-600 rounded-xl px-2 py-2 text-gray-900 dark:text-gray-100"
                                    >
                                        <option :value="null">Base Item</option>
                                        <option
                                            v-for="v in getProduct(item).variantMappings"
                                            :key="v.id"
                                            :value="v.id"
                                        >
                                            {{ v.variant_label }} (Stock: {{ v.local_stock }})
                                        </option>
                                    </select>
                                </div>

                                <!-- Quantity Received -->
                                <div :class="getProduct(item)?.variantMappings?.length ? 'md:col-span-2' : 'md:col-span-2'">
                                    <label class="block text-[11px] font-bold text-gray-500 uppercase mb-1">
                                        Qty Received <span class="text-rose-500">*</span>
                                    </label>
                                    <input
                                        v-model.number="item.quantity"
                                        type="number"
                                        min="1"
                                        required
                                        class="w-full text-sm font-mono bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-600 rounded-xl px-3 py-2 text-gray-900 dark:text-gray-100"
                                    />
                                </div>

                                <!-- Unit Cost Price -->
                                <div :class="getProduct(item)?.variantMappings?.length ? 'md:col-span-2' : 'md:col-span-3'">
                                    <label class="block text-[11px] font-bold text-gray-500 uppercase mb-1">
                                        Unit Cost (₦) <span class="text-rose-500">*</span>
                                    </label>
                                    <input
                                        v-model.number="item.unit_cost"
                                        type="number"
                                        step="0.01"
                                        min="0"
                                        required
                                        placeholder="0.00"
                                        class="w-full text-sm font-mono bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-600 rounded-xl px-3 py-2 text-gray-900 dark:text-gray-100"
                                    />
                                </div>

                                <!-- Line Total & Remove Button -->
                                <div class="md:col-span-2 flex items-center justify-between gap-2 pt-4 md:pt-0">
                                    <div class="text-right flex-1">
                                        <p class="text-[10px] text-gray-400 uppercase font-semibold">Subtotal</p>
                                        <p class="text-sm font-bold font-mono text-gray-900 dark:text-white">
                                            {{ formatCurrency((item.quantity || 0) * (item.unit_cost || 0)) }}
                                        </p>
                                    </div>
                                    <button
                                        type="button"
                                        @click="removeItemRow(index)"
                                        :disabled="form.items.length <= 1"
                                        class="text-gray-400 hover:text-rose-600 disabled:opacity-30 disabled:hover:text-gray-400 p-1.5 transition-colors"
                                        title="Remove row"
                                    >
                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                        </svg>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Footer Summary & Submission -->
                <div class="bg-white dark:bg-gray-800 p-6 rounded-2xl border border-gray-200/80 dark:border-gray-700/80 shadow-xs flex flex-col sm:flex-row items-center justify-between gap-4">
                    <div>
                        <p class="text-xs text-gray-400 font-semibold uppercase">Grand Total Summary</p>
                        <p class="text-2xl font-black text-gray-900 dark:text-white font-mono mt-0.5">
                            {{ formatCurrency(totalCost) }}
                        </p>
                        <p class="text-xs text-emerald-600 dark:text-emerald-400 font-semibold mt-0.5">
                            Total Units to be Added: +{{ totalUnits }} units
                        </p>
                    </div>

                    <div class="flex items-center gap-3 w-full sm:w-auto">
                        <Link
                            :href="route('admin.purchases.index')"
                            class="flex-1 sm:flex-none px-5 py-2.5 border border-gray-300 dark:border-gray-600 text-gray-700 dark:text-gray-200 font-semibold text-sm rounded-xl hover:bg-gray-50 dark:hover:bg-gray-700 text-center transition-all"
                        >
                            Cancel
                        </Link>
                        <button
                            type="submit"
                            :disabled="form.processing || form.items.length === 0"
                            class="flex-1 sm:flex-none inline-flex items-center justify-center gap-2 px-6 py-2.5 bg-primary text-white font-bold text-sm rounded-xl hover:bg-primary/90 shadow-md active:scale-95 disabled:opacity-50 transition-all"
                        >
                            <svg v-if="form.processing" class="animate-spin h-4 w-4 text-white" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path>
                            </svg>
                            Confirm & Restock Inventory
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </AdminLayout>
</template>

