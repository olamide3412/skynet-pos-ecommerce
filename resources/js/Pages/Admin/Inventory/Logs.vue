<script setup>
import { ref, computed, onMounted, onUnmounted, nextTick } from 'vue';
import { Head, router, Link } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import Pagination from '@/Components/Pagination.vue';

const props = defineProps({
    logs: Object,
    filters: Object,
    filterProducts: Array,
    initialProducts: Array,
    selectedProduct: Object,
    filterUsers: Array,
    metrics: Object,
});

const search = ref(props.filters?.search || '');
const selectedActionType = ref(props.filters?.action_type || '');
const selectedProductId = ref(props.filters?.product_id || '');
const activeProduct = ref(props.selectedProduct || null);
const selectedUserId = ref(props.filters?.user_id || '');
const startDate = ref(props.filters?.start_date || '');
const endDate = ref(props.filters?.end_date || '');

// Searchable Product Picker state
const productDropdownOpen = ref(false);
const productSearchQuery = ref('');
const productSearchInputRef = ref(null);
const productDropdownRef = ref(null);
const productSearchResults = ref(props.initialProducts || props.filterProducts || []);
const isSearchingProducts = ref(false);
let searchDebounceTimer = null;

const onProductSearchInput = () => {
    clearTimeout(searchDebounceTimer);
    searchDebounceTimer = setTimeout(async () => {
        const q = productSearchQuery.value.trim();
        isSearchingProducts.value = true;
        try {
            const res = await window.axios.get(route('admin.inventory.products.search'), {
                params: { q }
            });
            productSearchResults.value = res.data.products || [];
        } catch (err) {
            console.error('Failed to search products', err);
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

const selectProductFilter = (product) => {
    if (!product) {
        selectedProductId.value = '';
        activeProduct.value = null;
    } else {
        selectedProductId.value = product.id;
        activeProduct.value = product;
    }
    productDropdownOpen.value = false;
    productSearchQuery.value = '';
    applyFilters();
};

const clearProductFilter = (e) => {
    if (e) e.stopPropagation();
    selectedProductId.value = '';
    activeProduct.value = null;
    productSearchQuery.value = '';
    productDropdownOpen.value = false;
    applyFilters();
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

const selectedLogModal = ref(null);

const applyFilters = () => {
    router.get(route('admin.inventory.logs.index'), {
        search: search.value || undefined,
        action_type: selectedActionType.value || undefined,
        product_id: selectedProductId.value || undefined,
        user_id: selectedUserId.value || undefined,
        start_date: startDate.value || undefined,
        end_date: endDate.value || undefined,
        page: undefined,
    }, {
        preserveState: true,
        preserveScroll: true,
    });
};

const resetFilters = () => {
    search.value = '';
    selectedActionType.value = '';
    selectedProductId.value = '';
    activeProduct.value = null;
    productSearchQuery.value = '';
    selectedUserId.value = '';
    startDate.value = '';
    endDate.value = '';
    applyFilters();
};

const exportUrl = () => {
    const params = new URLSearchParams();
    if (search.value) params.append('search', search.value);
    if (selectedActionType.value) params.append('action_type', selectedActionType.value);
    if (selectedProductId.value) params.append('product_id', selectedProductId.value);
    return route('admin.inventory.logs.export') + '?' + params.toString();
};

const getActionBadge = (action) => {
    switch (action) {
        case 'sale':
            return { label: 'Sale (Order)', class: 'bg-rose-50 text-rose-700 dark:bg-rose-900/30 dark:text-rose-400 border-rose-200 dark:border-rose-800' };
        case 'cancellation':
            return { label: 'Cancelled / Return', class: 'bg-blue-50 text-blue-700 dark:bg-blue-900/30 dark:text-blue-400 border-blue-200 dark:border-blue-800' };
        case 'purchase':
            return { label: 'Supplier Purchase', class: 'bg-emerald-50 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-400 border-emerald-200 dark:border-emerald-800' };
        case 'adjustment':
            return { label: 'Stock Adjustment', class: 'bg-amber-50 text-amber-700 dark:bg-amber-900/30 dark:text-amber-400 border-amber-200 dark:border-amber-800' };
        default:
            return { label: action, class: 'bg-gray-50 text-gray-700 dark:bg-gray-800 dark:text-gray-300 border-gray-200 dark:border-gray-700' };
    }
};

const formatPrice = (val) => {
    if (!val && val !== 0) return '—';
    return '₦' + Number(val).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
};
</script>

<template>
    <Head title="Inventory Movement Ledger - Admin" />

    <AdminLayout>
        <div class="space-y-6">
            <!-- Header -->
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                <div>
                    <h1 class="text-2xl font-bold text-gray-900 dark:text-white flex items-center gap-2">
                        <svg class="w-7 h-7 text-primary" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4" />
                        </svg>
                        Inventory Movement Ledger
                    </h1>
                    <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">
                        Authoritative audit trail of every stock increase, sale, manual adjustment, and supplier purchase.
                    </p>
                </div>
                <div class="flex items-center gap-2.5">
                    <Link
                        :href="route('admin.inventory.adjustments.index')"
                        class="inline-flex items-center gap-2 px-3.5 py-2 border border-amber-300 dark:border-amber-700 bg-amber-50 dark:bg-amber-900/30 text-amber-800 dark:text-amber-200 text-sm font-semibold rounded-xl hover:bg-amber-100 dark:hover:bg-amber-900/50 shadow-2xs transition-all"
                    >
                        <svg class="w-4 h-4 text-amber-600 dark:text-amber-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                        </svg>
                        Adjust Stock
                    </Link>
                    <Link
                        :href="route('admin.purchases.create')"
                        class="inline-flex items-center gap-2 px-4 py-2 bg-primary text-white text-sm font-semibold rounded-xl hover:bg-primary/90 shadow-sm transition-all"
                    >
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                        </svg>
                        Receive Stock
                    </Link>
                    <a
                        :href="exportUrl()"
                        target="_blank"
                        class="inline-flex items-center gap-2 px-3.5 py-2 border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-gray-700 dark:text-gray-200 text-sm font-semibold rounded-xl hover:bg-gray-50 dark:hover:bg-gray-700 shadow-xs transition-all"
                    >
                        <svg class="w-4 h-4 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />
                        </svg>
                        Export CSV
                    </a>
                </div>
            </div>

            <!-- KPI Summary Cards -->
            <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                <div class="bg-white dark:bg-gray-800 p-4 rounded-2xl border border-gray-200/80 dark:border-gray-700/80 shadow-xs">
                    <p class="text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">Total Log Entries</p>
                    <p class="text-2xl font-bold text-gray-900 dark:text-white mt-1">{{ metrics.total_logs_count.toLocaleString() }}</p>
                    <p class="text-[11px] text-gray-400 mt-0.5">Recorded movements</p>
                </div>
                <div class="bg-white dark:bg-gray-800 p-4 rounded-2xl border border-gray-200/80 dark:border-gray-700/80 shadow-xs">
                    <p class="text-xs font-semibold uppercase tracking-wider text-rose-500">Units Sold (Checkout)</p>
                    <p class="text-2xl font-bold text-rose-600 dark:text-rose-400 mt-1">-{{ metrics.total_units_sold.toLocaleString() }}</p>
                    <p class="text-[11px] text-gray-400 mt-0.5">Dispatched to customers</p>
                </div>
                <div class="bg-white dark:bg-gray-800 p-4 rounded-2xl border border-gray-200/80 dark:border-gray-700/80 shadow-xs">
                    <p class="text-xs font-semibold uppercase tracking-wider text-emerald-500">Units Purchased</p>
                    <p class="text-2xl font-bold text-emerald-600 dark:text-emerald-400 mt-1">+{{ metrics.total_units_purchased.toLocaleString() }}</p>
                    <p class="text-[11px] text-gray-400 mt-0.5">Restocked from suppliers</p>
                </div>
                <div class="bg-white dark:bg-gray-800 p-4 rounded-2xl border border-gray-200/80 dark:border-gray-700/80 shadow-xs">
                    <p class="text-xs font-semibold uppercase tracking-wider text-indigo-500">Net Movement (This Month)</p>
                    <p class="text-2xl font-bold mt-1" :class="metrics.this_month_net >= 0 ? 'text-emerald-600 dark:text-emerald-400' : 'text-rose-600 dark:text-rose-400'">
                        {{ metrics.this_month_net >= 0 ? '+' : '' }}{{ metrics.this_month_net.toLocaleString() }}
                    </p>
                    <p class="text-[11px] text-gray-400 mt-0.5">Current calendar month</p>
                </div>
            </div>

            <!-- Filters Bar -->
            <div class="bg-white dark:bg-gray-800 p-4 rounded-2xl border border-gray-200/80 dark:border-gray-700/80 shadow-xs space-y-3">
                <div class="grid grid-cols-1 md:grid-cols-12 gap-3">
                    <div class="md:col-span-4">
                        <div class="relative">
                            <input
                                v-model="search"
                                @keyup.enter="applyFilters"
                                type="text"
                                placeholder="Search by SKU, Product, Order # or PO #..."
                                class="w-full pl-9 pr-3 py-2 text-sm bg-gray-50 dark:bg-gray-900 border border-gray-300 dark:border-gray-700 rounded-xl focus:ring-primary focus:border-primary text-gray-900 dark:text-gray-100 placeholder-gray-400"
                            />
                            <svg class="w-4 h-4 text-gray-400 absolute left-3 top-2.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                            </svg>
                        </div>
                    </div>

                    <div class="md:col-span-3">
                        <select
                            v-model="selectedActionType"
                            @change="applyFilters"
                            class="w-full py-2 px-3 text-sm bg-gray-50 dark:bg-gray-900 border border-gray-300 dark:border-gray-700 rounded-xl focus:ring-primary focus:border-primary text-gray-900 dark:text-gray-100"
                        >
                            <option value="">All Movement Types</option>
                            <option value="sale">Sale (Online Checkout)</option>
                            <option value="cancellation">Cancellation / Restock</option>
                            <option value="adjustment">Stock Adjustment (Manual)</option>
                            <option value="purchase">Supplier Purchase</option>
                        </select>
                    </div>

                    <!-- Searchable Product Picker -->
                    <div ref="productDropdownRef" class="relative md:col-span-3">
                        <!-- Trigger Button -->
                        <div
                            @click="toggleProductDropdown"
                            class="w-full h-[42px] px-3 border rounded-xl bg-gray-50 dark:bg-gray-900 cursor-pointer flex items-center justify-between gap-2 transition text-sm"
                            :class="productDropdownOpen ? 'border-primary ring-2 ring-primary/20' : 'border-gray-300 dark:border-gray-700 hover:border-gray-400 dark:hover:border-gray-600'"
                        >
                            <div v-if="activeProduct" class="flex-1 min-w-0 flex items-center gap-1.5 truncate">
                                <span
                                    class="text-[10px] font-bold px-1.5 py-0.5 rounded uppercase tracking-wider shrink-0"
                                    :class="activeProduct.inventory_source === 'pos' ? 'bg-purple-100 text-purple-700 dark:bg-purple-900/40 dark:text-purple-300' : 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-300'"
                                >
                                    {{ activeProduct.inventory_source === 'pos' ? 'POS' : 'Ecom' }}
                                </span>
                                <span class="font-medium text-gray-900 dark:text-gray-100 truncate">
                                    {{ activeProduct.name }}
                                </span>
                            </div>
                            <span v-else class="text-gray-500 dark:text-gray-400 truncate">
                                Filter by Product (All)
                            </span>

                            <div class="flex items-center gap-1 shrink-0">
                                <button
                                    v-if="selectedProductId"
                                    type="button"
                                    @click="clearProductFilter"
                                    class="p-1 rounded-md text-gray-400 hover:text-rose-500 hover:bg-gray-200 dark:hover:bg-gray-800 transition"
                                    title="Clear product filter"
                                >
                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                                    </svg>
                                </button>
                                <svg
                                    class="w-4 h-4 text-gray-400 transition-transform duration-150"
                                    :class="productDropdownOpen ? 'rotate-180 text-primary' : ''"
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
                            v-if="productDropdownOpen"
                            class="absolute left-0 right-0 z-50 mt-1.5 bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-xl shadow-2xl overflow-hidden min-w-[280px] sm:min-w-[340px]"
                        >
                            <!-- Search Input Header -->
                            <div class="p-2.5 border-b border-gray-200 dark:border-gray-700 bg-gray-50/80 dark:bg-gray-900/60">
                                <div class="relative">
                                    <input
                                        ref="productSearchInputRef"
                                        v-model="productSearchQuery"
                                        @input="onProductSearchInput"
                                        type="text"
                                        placeholder="Search by name, SKU, or ID..."
                                        @keydown.escape.stop="productDropdownOpen = false"
                                        class="w-full text-xs pl-8 pr-8 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-900 text-gray-900 dark:text-gray-100 focus:ring-2 focus:ring-primary focus:border-primary placeholder-gray-400"
                                    />
                                    <svg class="w-4 h-4 text-gray-400 absolute left-2.5 top-2.5 pointer-events-none" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                                    </svg>
                                    <button
                                        v-if="productSearchQuery"
                                        type="button"
                                        @click="productSearchQuery = ''; onProductSearchInput(); productSearchInputRef?.focus()"
                                        class="absolute right-2.5 top-2.5 text-gray-400 hover:text-gray-600 dark:hover:text-gray-200"
                                    >
                                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
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
                                        {{ productSearchResults.length }} product(s) found
                                    </span>
                                    <span class="text-gray-400">Showing first 25</span>
                                </div>
                            </div>

                            <!-- Options List -->
                            <div class="max-h-64 overflow-y-auto divide-y divide-gray-100 dark:divide-gray-700/60">
                                <!-- Option: All Products -->
                                <button
                                    type="button"
                                    @click="selectProductFilter(null)"
                                    class="w-full text-left px-3.5 py-2.5 flex items-center justify-between gap-2 text-xs font-semibold transition"
                                    :class="!selectedProductId ? 'bg-primary/10 text-primary font-bold' : 'hover:bg-gray-50 dark:hover:bg-gray-700/60 text-gray-700 dark:text-gray-300'"
                                >
                                    <span>All Products (No Filter)</span>
                                    <span v-if="!selectedProductId" class="text-primary font-bold">✓</span>
                                </button>

                                <!-- Individual Product Options -->
                                <button
                                    v-for="p in productSearchResults"
                                    :key="p.id"
                                    type="button"
                                    @click="selectProductFilter(p)"
                                    class="w-full text-left px-3.5 py-2.5 flex items-center justify-between gap-2 text-xs transition"
                                    :class="Number(selectedProductId) === Number(p.id) ? 'bg-primary/10 text-primary font-semibold' : 'hover:bg-gray-50 dark:hover:bg-gray-700/60 text-gray-800 dark:text-gray-200'"
                                >
                                    <div class="min-w-0 flex-1">
                                        <div class="flex items-center gap-1.5">
                                            <span
                                                class="text-[9px] font-bold px-1.5 py-0.5 rounded uppercase shrink-0"
                                                :class="p.inventory_source === 'pos' ? 'bg-purple-100 text-purple-700 dark:bg-purple-900/40 dark:text-purple-300' : 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-300'"
                                            >
                                                {{ p.inventory_source === 'pos' ? 'POS' : 'Ecom' }}
                                            </span>
                                            <span class="font-semibold text-gray-900 dark:text-white truncate">
                                                {{ p.name }}
                                            </span>
                                        </div>
                                        <div class="text-[11px] text-gray-500 dark:text-gray-400 mt-0.5 flex items-center gap-2">
                                            <span v-if="p.sku || p.product_number">SKU: <strong class="font-mono">{{ p.sku || p.product_number }}</strong></span>
                                            <span v-if="p.stock !== undefined">Stock: <strong>{{ p.stock }}</strong></span>
                                        </div>
                                    </div>
                                    <div class="text-right shrink-0">
                                        <span v-if="Number(selectedProductId) === Number(p.id)" class="text-primary font-bold text-sm">✓</span>
                                    </div>
                                </button>

                                <div v-if="!isSearchingProducts && !productSearchResults.length" class="p-4 text-center text-xs text-gray-400">
                                    No products found matching "{{ productSearchQuery }}".
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="md:col-span-2 flex items-center gap-2">
                        <button
                            @click="applyFilters"
                            class="flex-1 py-2 px-3 bg-gray-100 dark:bg-gray-700 hover:bg-gray-200 dark:hover:bg-gray-600 text-gray-800 dark:text-gray-200 text-sm font-semibold rounded-xl transition-all"
                        >
                            Filter
                        </button>
                        <button
                            v-if="search || selectedActionType || selectedProductId || startDate || endDate || selectedUserId"
                            @click="resetFilters"
                            class="py-2 px-3 text-sm text-rose-600 hover:text-rose-800 dark:text-rose-400 font-semibold"
                            title="Reset all filters"
                        >
                            Clear
                        </button>
                    </div>
                </div>
            </div>

            <!-- Ledger Table -->
            <div class="bg-white dark:bg-gray-800 rounded-2xl border border-gray-200/80 dark:border-gray-700/80 shadow-xs overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-sm">
                        <thead class="bg-gray-50/75 dark:bg-gray-700/50 text-[11px] uppercase tracking-wider text-gray-500 dark:text-gray-400 border-b border-gray-200 dark:border-gray-700">
                            <tr>
                                <th class="py-3 px-4">Date & Time</th>
                                <th class="py-3 px-4">Product</th>
                                <th class="py-3 px-4">Type</th>
                                <th class="py-3 px-4 text-center">Movement</th>
                                <th class="py-3 px-4 text-center">Stock Balance</th>
                                <th class="py-3 px-4 text-right">Unit Cost</th>
                                <th class="py-3 px-4">Reference & Reason</th>
                                <th class="py-3 px-4">Actor</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200/80 dark:divide-gray-700/80">
                            <tr v-if="!logs.data || logs.data.length === 0">
                                <td colspan="8" class="text-center py-12 text-gray-400 dark:text-gray-500">
                                    <svg class="w-10 h-10 mx-auto mb-2 text-gray-300 dark:text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4" />
                                    </svg>
                                    No inventory movements match your filter criteria.
                                </td>
                            </tr>
                            <tr
                                v-for="log in logs.data"
                                :key="log.id"
                                class="hover:bg-gray-50/50 dark:hover:bg-gray-700/30 transition-colors"
                            >
                                <!-- Date & Time -->
                                <td class="py-3.5 px-4 whitespace-nowrap text-xs text-gray-500 dark:text-gray-400">
                                    {{ new Date(log.created_at).toLocaleString('en-US', { month: 'short', day: 'numeric', year: 'numeric', hour: '2-digit', minute: '2-digit' }) }}
                                </td>

                                <!-- Product Info -->
                                <td class="py-3.5 px-4">
                                    <div class="flex items-center gap-3">
                                        <img
                                            :src="log.product?.image ? '/storage/' + log.product.image.replace(/^\/+/, '') : 'https://placehold.co/80x80?text=No+Img'"
                                            class="w-9 h-9 rounded-lg object-cover border border-gray-200 dark:border-gray-700 shrink-0 bg-gray-100 dark:bg-gray-700"
                                            alt=""
                                        />
                                        <div class="min-w-0">
                                            <p class="font-semibold text-gray-900 dark:text-gray-100 text-xs truncate max-w-xs">
                                                {{ log.product?.name || 'Deleted Product' }}
                                            </p>
                                            <p class="text-[11px] text-gray-400">
                                                SKU: {{ log.product?.sku || 'N/A' }}
                                                <span v-if="log.variant_mapping" class="text-primary font-medium ml-1">
                                                    ({{ log.variant_mapping.variant_label }})
                                                </span>
                                            </p>
                                        </div>
                                    </div>
                                </td>

                                <!-- Action Type -->
                                <td class="py-3.5 px-4 whitespace-nowrap">
                                    <span
                                        :class="['text-[11px] font-bold px-2 py-0.5 rounded-full border', getActionBadge(log.action_type).class]"
                                    >
                                        {{ getActionBadge(log.action_type).label }}
                                    </span>
                                </td>

                                <!-- Quantity Change (+/-) -->
                                <td class="py-3.5 px-4 text-center whitespace-nowrap">
                                    <span
                                        :class="[
                                            'text-xs font-bold px-2 py-1 rounded-lg',
                                            log.quantity_change > 0
                                                ? 'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/40 dark:text-emerald-300'
                                                : log.quantity_change < 0
                                                    ? 'bg-rose-100 text-rose-800 dark:bg-rose-900/40 dark:text-rose-300'
                                                    : 'bg-gray-100 text-gray-700 dark:bg-gray-700 dark:text-gray-300'
                                        ]"
                                    >
                                        {{ log.quantity_change > 0 ? '+' : '' }}{{ log.quantity_change }}
                                    </span>
                                </td>

                                <!-- Stock Transition (Prev -> New) -->
                                <td class="py-3.5 px-4 text-center whitespace-nowrap text-xs text-gray-600 dark:text-gray-300 font-mono">
                                    {{ log.previous_stock }} → <span class="font-bold text-gray-900 dark:text-white">{{ log.new_stock }}</span>
                                </td>

                                <!-- Unit Cost -->
                                <td class="py-3.5 px-4 text-right whitespace-nowrap text-xs text-gray-700 dark:text-gray-300 font-mono">
                                    {{ formatPrice(log.unit_cost) }}
                                </td>

                                <!-- Reference & Reason -->
                                <td class="py-3.5 px-4">
                                    <p class="text-xs font-semibold text-gray-800 dark:text-gray-200">
                                        {{ log.reason }}
                                    </p>
                                    <p v-if="log.reference_number" class="text-[11px] text-primary font-mono mt-0.5">
                                        Ref: {{ log.reference_number }}
                                    </p>
                                    <button
                                        v-if="log.notes"
                                        @click="selectedLogModal = log"
                                        class="text-[11px] text-gray-400 hover:text-gray-600 dark:hover:text-gray-300 underline mt-0.5"
                                    >
                                        View notes
                                    </button>
                                </td>

                                <!-- Actor -->
                                <td class="py-3.5 px-4 whitespace-nowrap text-xs text-gray-600 dark:text-gray-400">
                                    {{ log.user?.name || 'Online Customer' }}
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div v-if="logs.data && logs.data.length > 0" class="p-4 border-t border-gray-200 dark:border-gray-700">
                    <Pagination :paginator="logs" />
                </div>
            </div>

            <!-- Notes Detail Modal -->
            <div v-if="selectedLogModal" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/60 backdrop-blur-xs">
                <div class="bg-white dark:bg-gray-800 rounded-2xl max-w-md w-full p-6 shadow-2xl border border-gray-200 dark:border-gray-700">
                    <div class="flex items-center justify-between pb-3 border-b border-gray-200 dark:border-gray-700">
                        <h3 class="font-bold text-gray-900 dark:text-white text-base">Movement Detail & Notes</h3>
                        <button @click="selectedLogModal = null" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-200 text-lg">✕</button>
                    </div>
                    <div class="mt-4 space-y-3 text-sm">
                        <div>
                            <p class="text-xs text-gray-400 uppercase font-semibold">Product</p>
                            <p class="font-medium text-gray-900 dark:text-white mt-0.5">{{ selectedLogModal.product?.name }}</p>
                        </div>
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <p class="text-xs text-gray-400 uppercase font-semibold">Action</p>
                                <p class="font-medium text-gray-900 dark:text-white mt-0.5 capitalize">{{ selectedLogModal.action_type }}</p>
                            </div>
                            <div>
                                <p class="text-xs text-gray-400 uppercase font-semibold">Stock Delta</p>
                                <p class="font-medium text-gray-900 dark:text-white mt-0.5 font-mono">
                                    {{ selectedLogModal.previous_stock }} → {{ selectedLogModal.new_stock }} ({{ selectedLogModal.quantity_change > 0 ? '+' : '' }}{{ selectedLogModal.quantity_change }})
                                </p>
                            </div>
                        </div>
                        <div v-if="selectedLogModal.reference_number">
                            <p class="text-xs text-gray-400 uppercase font-semibold">Reference</p>
                            <p class="font-mono text-primary text-xs mt-0.5">{{ selectedLogModal.reference_number }}</p>
                        </div>
                        <div>
                            <p class="text-xs text-gray-400 uppercase font-semibold">Reason</p>
                            <p class="text-gray-800 dark:text-gray-200 mt-0.5">{{ selectedLogModal.reason }}</p>
                        </div>
                        <div v-if="selectedLogModal.notes">
                            <p class="text-xs text-gray-400 uppercase font-semibold">Notes / Explanation</p>
                            <div class="p-3 bg-gray-50 dark:bg-gray-900 rounded-xl text-xs text-gray-700 dark:text-gray-300 font-mono whitespace-pre-wrap mt-1 border border-gray-200 dark:border-gray-700">
                                {{ selectedLogModal.notes }}
                            </div>
                        </div>
                    </div>
                    <div class="mt-6 flex justify-end">
                        <button
                            @click="selectedLogModal = null"
                            class="px-4 py-2 bg-gray-100 dark:bg-gray-700 hover:bg-gray-200 dark:hover:bg-gray-600 rounded-xl text-sm font-semibold text-gray-800 dark:text-gray-200"
                        >
                            Close
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </AdminLayout>
</template>

