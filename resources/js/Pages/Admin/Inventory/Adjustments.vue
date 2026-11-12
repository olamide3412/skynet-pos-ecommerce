<script setup>
import { ref, computed, onMounted, onUnmounted, nextTick, watch } from 'vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import Pagination from '@/Components/Pagination.vue';

const props = defineProps({
    initialProducts: Array,
    selectedProduct: Object,
    recentAdjustments: Object,
});

const activeProduct = ref(props.selectedProduct || null);

const form = useForm({
    product_id: props.selectedProduct?.id || '',
    variant_id: null,
    mode: 'add',
    quantity: 1,
    reason: 'restock',
    notes: '',
});

// Searchable Product Picker state
const productDropdownOpen = ref(false);
const productSearchQuery = ref('');
const productSearchInputRef = ref(null);
const productDropdownRef = ref(null);
const productSearchResults = ref(props.initialProducts || []);
const isSearchingProducts = ref(false);
let searchDebounceTimer = null;

const onProductSearchInput = () => {
    clearTimeout(searchDebounceTimer);
    searchDebounceTimer = setTimeout(async () => {
        const q = productSearchQuery.value.trim();
        isSearchingProducts.value = true;
        try {
            const res = await window.axios.get(route('admin.purchases.products.search'), {
                params: { q, ecommerce_only: 1 }
            });
            productSearchResults.value = res.data.products || [];
        } catch (err) {
            console.error('Failed to search ecommerce products', err);
        } finally {
            isSearchingProducts.value = false;
        }
    }, 250);
};

const toggleProductDropdown = () => {
    productDropdownOpen.value = !productDropdownOpen.value;
    if (productDropdownOpen.value) {
        nextTick(() => {
            productSearchInputRef.value?.focus();
        });
    }
};

const selectProduct = (product) => {
    activeProduct.value = product;
    form.product_id = product ? product.id : '';
    form.variant_id = null;
    productDropdownOpen.value = false;
    productSearchQuery.value = '';
};

const clearProduct = (e) => {
    if (e) e.stopPropagation();
    activeProduct.value = null;
    form.product_id = '';
    form.variant_id = null;
    productSearchQuery.value = '';
    productDropdownOpen.value = false;
};

const handleClickOutside = (e) => {
    if (productDropdownOpen.value && productDropdownRef.value && !productDropdownRef.value.contains(e.target)) {
        productDropdownOpen.value = false;
    }
};

onMounted(() => {
    document.addEventListener('mousedown', handleClickOutside);
});

onUnmounted(() => {
    document.removeEventListener('mousedown', handleClickOutside);
});

// Calculate current available stock
const currentStock = computed(() => {
    if (!activeProduct.value) return 0;
    if (form.variant_id && activeProduct.value.variantMappings) {
        const variant = activeProduct.value.variantMappings.find(v => v.id === form.variant_id);
        if (variant) return Number(variant.local_stock || 0);
    }
    return Number(activeProduct.value.stock || 0);
});

// Calculate live resulting stock based on mode & qty
const calculation = computed(() => {
    const cur = currentStock.value;
    const qty = Number(form.quantity) || 0;

    if (form.mode === 'add') {
        return {
            change: +qty,
            resulting: cur + qty,
            changeStr: `+${qty}`,
            isValid: qty > 0,
            error: qty <= 0 ? 'Quantity must be greater than 0.' : null,
        };
    } else if (form.mode === 'subtract') {
        const res = cur - qty;
        return {
            change: -qty,
            resulting: res,
            changeStr: `-${qty}`,
            isValid: qty > 0 && res >= 0,
            error: qty <= 0 ? 'Quantity must be greater than 0.' : (res < 0 ? `Cannot subtract ${qty} units. Current stock is only ${cur}.` : null),
        };
    } else {
        // 'set' mode
        const diff = qty - cur;
        return {
            change: diff,
            resulting: qty,
            changeStr: diff >= 0 ? `+${diff}` : `${diff}`,
            isValid: qty >= 0,
            error: qty < 0 ? 'Stock count cannot be negative.' : null,
        };
    }
});

// Update default reason when mode changes
watch(() => form.mode, (newMode) => {
    if (newMode === 'add' && form.reason === 'damaged') {
        form.reason = 'restock';
    } else if (newMode === 'subtract' && form.reason === 'restock') {
        form.reason = 'damaged';
    } else if (newMode === 'set') {
        form.reason = 'count_correction';
    }
});

const submit = () => {
    form.post(route('admin.inventory.adjustments.store'), {
        preserveScroll: true,
        onSuccess: () => {
            form.reset('quantity', 'notes');
            form.quantity = 1;
        },
    });
};
</script>

<template>
    <Head title="Stock Adjustment - Admin" />

    <AdminLayout>
        <div class="max-w-5xl mx-auto space-y-6">
            <!-- Header -->
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                <div>
                    <div class="flex items-center gap-2 text-xs text-gray-500 mb-1">
                        <Link :href="route('admin.inventory.logs.index')" class="hover:underline">Inventory</Link>
                        <span>/</span>
                        <span class="text-gray-900 dark:text-gray-100 font-semibold">Stock Adjustment</span>
                    </div>
                    <h1 class="text-2xl font-bold text-gray-900 dark:text-white flex items-center gap-2">
                        <svg class="w-7 h-7 text-amber-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                        </svg>
                        Adjust Stock (Ecommerce Only)
                    </h1>
                    <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">
                        Increase, reduce, or recount local ecommerce inventory. Every adjustment is immutably logged to the ledger.
                    </p>
                </div>
                <div class="flex items-center gap-2.5">
                    <Link
                        :href="route('admin.inventory.logs.index')"
                        class="inline-flex items-center gap-2 px-3.5 py-2 border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-gray-700 dark:text-gray-200 text-sm font-semibold rounded-xl hover:bg-gray-50 dark:hover:bg-gray-700 shadow-xs transition-all"
                    >
                        <svg class="w-4 h-4 text-primary" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" />
                        </svg>
                        View Ledger
                    </Link>
                    <Link
                        :href="route('admin.purchases.create')"
                        class="inline-flex items-center gap-2 px-4 py-2 bg-primary text-white text-sm font-semibold rounded-xl hover:bg-primary/90 shadow-sm transition-all"
                    >
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                        </svg>
                        New Purchase (Supplier)
                    </Link>
                </div>
            </div>

            <!-- Form Card -->
            <form @submit.prevent="submit" class="bg-white dark:bg-gray-800 rounded-2xl border border-gray-200/80 dark:border-gray-700/80 shadow-xs p-6 space-y-6">
                <!-- Step 1: Select Product -->
                <div class="space-y-2">
                    <label class="block text-xs font-bold text-gray-700 dark:text-gray-300 uppercase tracking-wide">
                        1. Select Ecommerce Product <span class="text-rose-500">*</span>
                    </label>

                    <div ref="productDropdownRef" class="relative">
                        <!-- Trigger Button -->
                        <div
                            @click="toggleProductDropdown"
                            class="w-full min-h-[46px] px-3.5 py-2 border rounded-xl bg-gray-50 dark:bg-gray-900 cursor-pointer flex items-center justify-between gap-3 transition text-sm shadow-2xs"
                            :class="productDropdownOpen ? 'border-primary ring-2 ring-primary/20' : 'border-gray-300 dark:border-gray-700 hover:border-gray-400 dark:hover:border-gray-600'"
                        >
                            <div v-if="activeProduct" class="flex-1 min-w-0 flex items-center gap-2">
                                <span class="text-[10px] font-bold px-2 py-0.5 rounded bg-emerald-100 text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-300 uppercase shrink-0">
                                    Ecommerce
                                </span>
                                <div class="truncate">
                                    <span class="font-bold text-gray-900 dark:text-white truncate block">
                                        {{ activeProduct.name }}
                                    </span>
                                    <span class="text-xs text-gray-500 dark:text-gray-400 flex items-center gap-3">
                                        <span v-if="activeProduct.sku">SKU: <strong class="font-mono">{{ activeProduct.sku }}</strong></span>
                                        <span>Current Stock: <strong class="text-emerald-600 dark:text-emerald-400">{{ activeProduct.stock }} units</strong></span>
                                    </span>
                                </div>
                            </div>
                            <span v-else class="text-gray-400 dark:text-gray-500 text-sm">
                                -- Click to search & select an Ecommerce product --
                            </span>

                            <div class="flex items-center gap-1.5 shrink-0">
                                <button
                                    v-if="activeProduct"
                                    type="button"
                                    @click="clearProduct"
                                    class="p-1 rounded-md text-gray-400 hover:text-rose-500 hover:bg-gray-200 dark:hover:bg-gray-800 transition"
                                    title="Clear selected product"
                                >
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                                    </svg>
                                </button>
                                <svg
                                    class="w-4 h-4 text-gray-400 transition-transform duration-150"
                                    :class="productDropdownOpen ? 'rotate-180 text-primary' : ''"
                                    fill="none"
                                    stroke="currentColor"
                                    viewBox="0 0 24 24"
                                >
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                                </svg>
                            </div>
                        </div>

                        <!-- Dropdown Panel -->
                        <div
                            v-if="productDropdownOpen"
                            class="absolute left-0 right-0 z-50 mt-1.5 bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-xl shadow-2xl overflow-hidden"
                        >
                            <!-- Search Input Header -->
                            <div class="p-3 border-b border-gray-200 dark:border-gray-700 bg-gray-50/80 dark:bg-gray-900/60">
                                <div class="relative">
                                    <input
                                        ref="productSearchInputRef"
                                        v-model="productSearchQuery"
                                        @input="onProductSearchInput"
                                        type="text"
                                        placeholder="Search product by name, SKU, or ID..."
                                        @keydown.escape.stop="productDropdownOpen = false"
                                        class="w-full text-xs pl-8 pr-8 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-900 text-gray-900 dark:text-gray-100 focus:ring-2 focus:ring-primary focus:border-primary placeholder-gray-400"
                                    />
                                    <svg class="w-4 h-4 text-gray-400 absolute left-2.5 top-2.5 pointer-events-none" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                                    </svg>
                                    <button
                                        v-if="productSearchQuery"
                                        type="button"
                                        @click="productSearchQuery = ''; onProductSearchInput(); productSearchInputRef?.focus()"
                                        class="absolute right-2.5 top-2.5 text-gray-400 hover:text-gray-600 dark:hover:text-gray-200"
                                    >
                                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                                        </svg>
                                    </button>
                                </div>
                                <div class="flex items-center justify-between mt-1.5 px-1 text-[11px] text-gray-500 dark:text-gray-400">
                                    <span v-if="isSearchingProducts" class="inline-flex items-center gap-1 text-primary">
                                        <svg class="animate-spin w-3 h-3" fill="none" viewBox="0 0 24 24">
                                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                                        </svg>
                                        Searching...
                                    </span>
                                    <span v-else>
                                        {{ productSearchResults.length }} matching product(s)
                                    </span>
                                    <span class="text-gray-400">Showing first 25</span>
                                </div>
                            </div>

                            <!-- Options List -->
                            <div class="max-h-64 overflow-y-auto divide-y divide-gray-100 dark:divide-gray-700/60">
                                <button
                                    v-for="p in productSearchResults"
                                    :key="p.id"
                                    type="button"
                                    @click="selectProduct(p)"
                                    class="w-full text-left px-3.5 py-2.5 flex items-center justify-between gap-2 text-xs transition"
                                    :class="Number(form.product_id) === Number(p.id) ? 'bg-primary/10 text-primary font-semibold' : 'hover:bg-gray-50 dark:hover:bg-gray-700/60 text-gray-800 dark:text-gray-200'"
                                >
                                    <div class="min-w-0 flex-1">
                                        <div class="font-semibold text-gray-900 dark:text-white truncate">
                                            {{ p.name }}
                                        </div>
                                        <div class="text-[11px] text-gray-500 dark:text-gray-400 mt-0.5 flex items-center gap-3">
                                            <span v-if="p.sku">SKU: <strong class="font-mono">{{ p.sku }}</strong></span>
                                            <span>Current Stock: <strong>{{ p.stock }}</strong></span>
                                        </div>
                                    </div>
                                    <div class="text-right shrink-0">
                                        <span v-if="Number(form.product_id) === Number(p.id)" class="text-primary font-bold text-sm">✓</span>
                                        <span v-else class="text-[11px] text-emerald-600 dark:text-emerald-400 font-medium">Select</span>
                                    </div>
                                </button>

                                <div v-if="!isSearchingProducts && !productSearchResults.length" class="p-4 text-center text-xs text-gray-400">
                                    No ecommerce products found matching "{{ productSearchQuery }}".
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Variant selector (if product has variants) -->
                    <div v-if="activeProduct?.variantMappings?.length" class="pt-2">
                        <label class="block text-xs font-bold text-gray-600 dark:text-gray-400 uppercase tracking-wide mb-1">
                            Specific Variant
                        </label>
                        <select
                            v-model="form.variant_id"
                            class="w-full sm:w-80 text-sm bg-gray-50 dark:bg-gray-900 border border-gray-300 dark:border-gray-700 rounded-xl px-3 py-2 text-gray-900 dark:text-gray-100"
                        >
                            <option :value="null">Base Item (All Units)</option>
                            <option v-for="v in activeProduct.variantMappings" :key="v.id" :value="v.id">
                                {{ v.variant_label }} (Stock: {{ v.local_stock }})
                            </option>
                        </select>
                    </div>
                </div>

                <!-- Step 2: Choose Adjustment Mode -->
                <div class="space-y-2">
                    <label class="block text-xs font-bold text-gray-700 dark:text-gray-300 uppercase tracking-wide">
                        2. Adjustment Mode <span class="text-rose-500">*</span>
                    </label>

                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                        <!-- Add -->
                        <button
                            type="button"
                            @click="form.mode = 'add'"
                            class="p-4 rounded-xl border text-left transition flex flex-col justify-between"
                            :class="form.mode === 'add' ? 'border-emerald-500 bg-emerald-50/80 dark:bg-emerald-950/30 ring-2 ring-emerald-500/20' : 'border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 hover:border-gray-300 dark:hover:border-gray-600'"
                        >
                            <div class="flex items-center gap-2">
                                <span class="w-7 h-7 rounded-lg flex items-center justify-center font-bold text-base" :class="form.mode === 'add' ? 'bg-emerald-600 text-white' : 'bg-gray-100 dark:bg-gray-800 text-gray-600 dark:text-gray-300'">
                                    +
                                </span>
                                <span class="font-bold text-sm text-gray-900 dark:text-white">Increase Stock</span>
                            </div>
                            <p class="text-[11px] text-gray-500 dark:text-gray-400 mt-2">
                                Add units to current stock (restock, found items, surplus).
                            </p>
                        </button>

                        <!-- Subtract -->
                        <button
                            type="button"
                            @click="form.mode = 'subtract'"
                            class="p-4 rounded-xl border text-left transition flex flex-col justify-between"
                            :class="form.mode === 'subtract' ? 'border-rose-500 bg-rose-50/80 dark:bg-rose-950/30 ring-2 ring-rose-500/20' : 'border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 hover:border-gray-300 dark:hover:border-gray-600'"
                        >
                            <div class="flex items-center gap-2">
                                <span class="w-7 h-7 rounded-lg flex items-center justify-center font-bold text-base" :class="form.mode === 'subtract' ? 'bg-rose-600 text-white' : 'bg-gray-100 dark:bg-gray-800 text-gray-600 dark:text-gray-300'">
                                    -
                                </span>
                                <span class="font-bold text-sm text-gray-900 dark:text-white">Reduce Stock</span>
                            </div>
                            <p class="text-[11px] text-gray-500 dark:text-gray-400 mt-2">
                                Deduct units for damages, expired goods, theft, or shrinkage.
                            </p>
                        </button>

                        <!-- Set -->
                        <button
                            type="button"
                            @click="form.mode = 'set'"
                            class="p-4 rounded-xl border text-left transition flex flex-col justify-between"
                            :class="form.mode === 'set' ? 'border-blue-500 bg-blue-50/80 dark:bg-blue-950/30 ring-2 ring-blue-500/20' : 'border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 hover:border-gray-300 dark:hover:border-gray-600'"
                        >
                            <div class="flex items-center gap-2">
                                <span class="w-7 h-7 rounded-lg flex items-center justify-center font-bold text-base" :class="form.mode === 'set' ? 'bg-blue-600 text-white' : 'bg-gray-100 dark:bg-gray-800 text-gray-600 dark:text-gray-300'">
                                    =
                                </span>
                                <span class="font-bold text-sm text-gray-900 dark:text-white">Set Exact Count</span>
                            </div>
                            <p class="text-[11px] text-gray-500 dark:text-gray-400 mt-2">
                                Overwrite current stock to exact physical count from inventory audit.
                            </p>
                        </button>
                    </div>
                </div>

                <!-- Step 3: Quantity & Reason -->
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-bold text-gray-700 dark:text-gray-300 uppercase tracking-wide mb-1.5">
                            {{ form.mode === 'set' ? 'Target Stock Count' : 'Quantity Units' }} <span class="text-rose-500">*</span>
                        </label>
                        <input
                            v-model.number="form.quantity"
                            type="number"
                            :min="form.mode === 'set' ? 0 : 1"
                            required
                            class="w-full text-base font-bold font-mono bg-gray-50 dark:bg-gray-900 border border-gray-300 dark:border-gray-700 rounded-xl px-3.5 py-2.5 text-gray-900 dark:text-gray-100 focus:ring-primary focus:border-primary"
                            placeholder="Enter units..."
                        />
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-gray-700 dark:text-gray-300 uppercase tracking-wide mb-1.5">
                            Reason for Adjustment <span class="text-rose-500">*</span>
                        </label>
                        <select
                            v-model="form.reason"
                            required
                            class="w-full text-sm bg-gray-50 dark:bg-gray-900 border border-gray-300 dark:border-gray-700 rounded-xl px-3.5 py-2.5 text-gray-900 dark:text-gray-100 focus:ring-primary focus:border-primary"
                        >
                            <option value="restock">Restock / Found Inventory</option>
                            <option value="damaged">Damaged / Broken</option>
                            <option value="expired">Expired / Spoiled Goods</option>
                            <option value="theft_or_loss">Theft / Shrinkage / Missing</option>
                            <option value="count_correction">Audit Recount Correction</option>
                            <option value="internal_use">Internal Store / Office Use</option>
                            <option value="customer_return">Customer Return Without Order</option>
                            <option value="other">Other (Specify in notes)</option>
                        </select>
                    </div>

                    <div class="md:col-span-2">
                        <label class="block text-xs font-bold text-gray-700 dark:text-gray-300 uppercase tracking-wide mb-1.5">
                            Notes / Explanation (Optional)
                        </label>
                        <input
                            v-model="form.notes"
                            type="text"
                            placeholder="e.g. Broken in packaging during stock reorganize..."
                            class="w-full text-sm bg-gray-50 dark:bg-gray-900 border border-gray-300 dark:border-gray-700 rounded-xl px-3.5 py-2.5 text-gray-900 dark:text-gray-100 focus:ring-primary focus:border-primary"
                        />
                    </div>
                </div>

                <!-- Calculation Preview Banner -->
                <div v-if="activeProduct" class="p-4 rounded-xl border flex flex-col sm:flex-row sm:items-center justify-between gap-3"
                    :class="calculation.isValid ? 'bg-emerald-50/50 dark:bg-emerald-950/20 border-emerald-200 dark:border-emerald-800' : 'bg-rose-50/50 dark:bg-rose-950/20 border-rose-200 dark:border-rose-800'"
                >
                    <div class="flex items-center gap-3">
                        <div class="text-xs space-y-0.5">
                            <p class="text-gray-500 font-medium">Stock Calculation:</p>
                            <p class="text-sm font-semibold text-gray-900 dark:text-white">
                                Current: <span class="font-mono">{{ currentStock }}</span>
                                <span class="mx-1 text-gray-400">→</span>
                                Change: <span class="font-mono font-bold" :class="calculation.change >= 0 ? 'text-emerald-600' : 'text-rose-600'">{{ calculation.changeStr }}</span>
                                <span class="mx-1 text-gray-400">=</span>
                                New Stock: <span class="font-mono font-bold text-base" :class="calculation.isValid ? 'text-emerald-600 dark:text-emerald-400' : 'text-rose-600 dark:text-rose-400'">{{ calculation.resulting }}</span>
                            </p>
                        </div>
                    </div>
                    <div v-if="calculation.error" class="text-xs font-semibold text-rose-600 dark:text-rose-400">
                        ⚠️ {{ calculation.error }}
                    </div>
                </div>

                <!-- Submit Button -->
                <div class="flex items-center justify-end gap-3 pt-2">
                    <button
                        type="submit"
                        :disabled="!activeProduct || !calculation.isValid || form.processing"
                        class="px-6 py-2.5 bg-amber-500 hover:bg-amber-600 text-white font-bold text-sm rounded-xl shadow-sm transition disabled:opacity-50 disabled:cursor-not-allowed flex items-center gap-2"
                    >
                        <span v-if="form.processing">Applying Adjustment...</span>
                        <span v-else>Confirm & Apply Adjustment</span>
                    </button>
                </div>
            </form>

            <!-- Recent Adjustments Audit Preview -->
            <div class="bg-white dark:bg-gray-800 rounded-2xl border border-gray-200/80 dark:border-gray-700/80 shadow-xs overflow-hidden">
                <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700 flex items-center justify-between">
                    <div>
                        <h2 class="text-base font-bold text-gray-900 dark:text-white">Recent Stock Adjustments</h2>
                        <p class="text-xs text-gray-500 dark:text-gray-400">Last 15 manual adjustment ledger events.</p>
                    </div>
                    <Link
                        :href="route('admin.inventory.logs.index', { action_type: 'adjustment' })"
                        class="text-xs font-semibold text-primary hover:underline"
                    >
                        View All in Ledger →
                    </Link>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full text-xs text-left">
                        <thead class="bg-gray-50 dark:bg-gray-900/50 text-gray-500 uppercase tracking-wider font-semibold border-b border-gray-200 dark:border-gray-700">
                            <tr>
                                <th class="px-4 py-3">Date</th>
                                <th class="px-4 py-3">Product</th>
                                <th class="px-4 py-3 text-center">Movement</th>
                                <th class="px-4 py-3 text-center">Balance</th>
                                <th class="px-4 py-3">Reason</th>
                                <th class="px-4 py-3">User</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-gray-700/60">
                            <tr v-for="log in (recentAdjustments?.data || recentAdjustments)" :key="log.id" class="hover:bg-gray-50 dark:hover:bg-gray-700/30">
                                <td class="px-4 py-3 text-gray-500 whitespace-nowrap">
                                    {{ new Date(log.created_at).toLocaleString() }}
                                </td>
                                <td class="px-4 py-3 font-semibold text-gray-900 dark:text-white">
                                    {{ log.product?.name ?? '—' }}
                                    <span v-if="log.product?.sku" class="block font-mono text-[10px] text-gray-400">{{ log.product.sku }}</span>
                                </td>
                                <td class="px-4 py-3 text-center whitespace-nowrap font-mono font-bold"
                                    :class="log.quantity_change > 0 ? 'text-emerald-600 dark:text-emerald-400' : 'text-rose-600 dark:text-rose-400'"
                                >
                                    {{ log.quantity_change > 0 ? `+${log.quantity_change}` : log.quantity_change }}
                                </td>
                                <td class="px-4 py-3 text-center font-mono text-gray-600 dark:text-gray-300">
                                    {{ log.previous_stock }} → <strong>{{ log.new_stock }}</strong>
                                </td>
                                <td class="px-4 py-3 text-gray-700 dark:text-gray-300">
                                    <span class="inline-block px-2 py-0.5 rounded bg-gray-100 dark:bg-gray-700 font-medium text-[11px]">
                                        {{ log.reason }}
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-gray-500">
                                    {{ log.user?.name ?? 'System' }}
                                </td>
                            </tr>
                            <tr v-if="!(recentAdjustments?.data?.length || recentAdjustments?.length)">
                                <td colspan="6" class="px-6 py-8 text-center text-gray-400">
                                    No manual stock adjustments recorded yet.
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Pagination -->
                <div v-if="recentAdjustments?.data && recentAdjustments.data.length > 0" class="p-4 border-t border-gray-200 dark:border-gray-700">
                    <Pagination :paginator="recentAdjustments" />
                </div>
            </div>
        </div>
    </AdminLayout>
</template>

