<script setup>
import { ref, onMounted, onUnmounted, nextTick } from 'vue';
import { Head, router, Link } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import Pagination from '@/Components/Pagination.vue';

const props = defineProps({
    purchases: Object,
    filters: Object,
    initialProducts: Array,
    selectedProduct: Object,
    metrics: Object,
});

const search = ref(props.filters?.search || '');
const selectedPaymentStatus = ref(props.filters?.payment_status || '');
const selectedProductId = ref(props.filters?.product_id || '');
const activeProduct = ref(props.selectedProduct || null);
const startDate = ref(props.filters?.start_date || '');
const endDate = ref(props.filters?.end_date || '');

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
                params: { q, ecommerce_only: 0 }
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

const applyFilters = () => {
    router.get(route('admin.purchases.index'), {
        search: search.value || undefined,
        payment_status: selectedPaymentStatus.value || undefined,
        product_id: selectedProductId.value || undefined,
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
    selectedPaymentStatus.value = '';
    selectedProductId.value = '';
    activeProduct.value = null;
    productSearchQuery.value = '';
    startDate.value = '';
    endDate.value = '';
    applyFilters();
};

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
</script>

<template>
    <Head title="Stock Purchases & Receiving - Admin" />

    <AdminLayout>
        <div class="space-y-6">
            <!-- Header -->
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                <div>
                    <h1 class="text-2xl font-bold text-gray-900 dark:text-white flex items-center gap-2">
                        <svg class="w-7 h-7 text-primary" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z" />
                        </svg>
                        Stock Purchases & Receiving
                    </h1>
                    <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">
                        Record supplier inventory deliveries, update cost prices, and automatically restock Ecommerce products.
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
                        View Inventory Logs
                    </Link>
                    <Link
                        :href="route('admin.purchases.create')"
                        class="inline-flex items-center gap-2 px-4 py-2 bg-primary text-white text-sm font-semibold rounded-xl hover:bg-primary/90 shadow-sm transition-all"
                    >
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                        </svg>
                        New Stock Purchase
                    </Link>
                </div>
            </div>

            <!-- Summary KPI Cards -->
            <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                <div class="bg-white dark:bg-gray-800 p-4 rounded-2xl border border-gray-200/80 dark:border-gray-700/80 shadow-xs">
                    <p class="text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">Total Purchase Orders</p>
                    <p class="text-2xl font-bold text-gray-900 dark:text-white mt-1">{{ metrics.total_purchases_count.toLocaleString() }}</p>
                    <p class="text-[11px] text-gray-400 mt-0.5">Recorded deliveries</p>
                </div>
                <div class="bg-white dark:bg-gray-800 p-4 rounded-2xl border border-gray-200/80 dark:border-gray-700/80 shadow-xs">
                    <p class="text-xs font-semibold uppercase tracking-wider text-primary">All-Time Spend</p>
                    <p class="text-2xl font-bold text-gray-900 dark:text-white mt-1">{{ formatCurrency(metrics.total_spend) }}</p>
                    <p class="text-[11px] text-gray-400 mt-0.5">Inventory procurement</p>
                </div>
                <div class="bg-white dark:bg-gray-800 p-4 rounded-2xl border border-gray-200/80 dark:border-gray-700/80 shadow-xs">
                    <p class="text-xs font-semibold uppercase tracking-wider text-emerald-500">Units Received</p>
                    <p class="text-2xl font-bold text-emerald-600 dark:text-emerald-400 mt-1">+{{ metrics.total_units_received.toLocaleString() }}</p>
                    <p class="text-[11px] text-gray-400 mt-0.5">Total stock restocked</p>
                </div>
                <div class="bg-white dark:bg-gray-800 p-4 rounded-2xl border border-gray-200/80 dark:border-gray-700/80 shadow-xs">
                    <p class="text-xs font-semibold uppercase tracking-wider text-indigo-500">Spend This Month</p>
                    <p class="text-2xl font-bold text-indigo-600 dark:text-indigo-400 mt-1">{{ formatCurrency(metrics.this_month_spend) }}</p>
                    <p class="text-[11px] text-gray-400 mt-0.5">Current calendar month</p>
                </div>
            </div>

            <!-- Filters -->
            <div class="bg-white dark:bg-gray-800 p-4 rounded-2xl border border-gray-200/80 dark:border-gray-700/80 shadow-xs space-y-3">
                <div class="grid grid-cols-1 md:grid-cols-12 gap-3">
                    <div class="md:col-span-3">
                        <div class="relative">
                            <input
                                v-model="search"
                                @keyup.enter="applyFilters"
                                type="text"
                                placeholder="Search by PO #, Supplier, or Ref..."
                                class="w-full pl-9 pr-3 py-2 text-sm bg-gray-50 dark:bg-gray-900 border border-gray-300 dark:border-gray-700 rounded-xl focus:ring-primary focus:border-primary text-gray-900 dark:text-gray-100 placeholder-gray-400"
                            />
                            <svg class="w-4 h-4 text-gray-400 absolute left-3 top-2.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                            </svg>
                        </div>
                    </div>

                    <div class="md:col-span-2">
                        <select
                            v-model="selectedPaymentStatus"
                            @change="applyFilters"
                            class="w-full py-2 px-3 text-sm bg-gray-50 dark:bg-gray-900 border border-gray-300 dark:border-gray-700 rounded-xl focus:ring-primary focus:border-primary text-gray-900 dark:text-gray-100"
                        >
                            <option value="">All Statuses</option>
                            <option value="paid">Paid</option>
                            <option value="pending">Pending</option>
                            <option value="partial">Partial</option>
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

                    <div class="md:col-span-2">
                        <input
                            v-model="startDate"
                            @change="applyFilters"
                            type="date"
                            class="w-full py-2 px-3 text-sm bg-gray-50 dark:bg-gray-900 border border-gray-300 dark:border-gray-700 rounded-xl focus:ring-primary focus:border-primary text-gray-900 dark:text-gray-100"
                        />
                    </div>

                    <div class="md:col-span-2 flex items-center gap-2">
                        <button
                            @click="applyFilters"
                            class="flex-1 py-2 px-3 bg-gray-100 dark:bg-gray-700 hover:bg-gray-200 dark:hover:bg-gray-600 text-gray-800 dark:text-gray-200 text-sm font-semibold rounded-xl transition-all"
                        >
                            Filter
                        </button>
                        <button
                            v-if="search || selectedPaymentStatus || selectedProductId || startDate || endDate"
                            @click="resetFilters"
                            class="py-2 px-3 text-sm text-rose-600 hover:text-rose-800 dark:text-rose-400 font-semibold"
                        >
                            Clear
                        </button>
                    </div>
                </div>
            </div>

            <!-- Table -->
            <div class="bg-white dark:bg-gray-800 rounded-2xl border border-gray-200/80 dark:border-gray-700/80 shadow-xs overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-sm">
                        <thead class="bg-gray-50/75 dark:bg-gray-700/50 text-[11px] uppercase tracking-wider text-gray-500 dark:text-gray-400 border-b border-gray-200 dark:border-gray-700">
                            <tr>
                                <th class="py-3 px-4">PO Number</th>
                                <th class="py-3 px-4">Date</th>
                                <th class="py-3 px-4">Supplier / Vendor</th>
                                <th class="py-3 px-4">Invoice / Ref</th>
                                <th class="py-3 px-4 text-center">Units Received</th>
                                <th class="py-3 px-4 text-right">Total Cost</th>
                                <th class="py-3 px-4">Status</th>
                                <th class="py-3 px-4">Received By</th>
                                <th class="py-3 px-4 text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200/80 dark:divide-gray-700/80">
                            <tr v-if="!purchases.data || purchases.data.length === 0">
                                <td colspan="9" class="text-center py-12 text-gray-400 dark:text-gray-500">
                                    <svg class="w-10 h-10 mx-auto mb-2 text-gray-300 dark:text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4" />
                                    </svg>
                                    No purchase orders recorded yet.
                                </td>
                            </tr>
                            <tr
                                v-for="po in purchases.data"
                                :key="po.id"
                                class="hover:bg-gray-50/50 dark:hover:bg-gray-700/30 transition-colors"
                            >
                                <td class="py-3.5 px-4 font-mono font-bold text-xs text-primary">
                                    <Link :href="route('admin.purchases.show', po.id)" class="hover:underline">
                                        {{ po.purchase_number }}
                                    </Link>
                                </td>
                                <td class="py-3.5 px-4 whitespace-nowrap text-xs text-gray-500 dark:text-gray-400">
                                    {{ new Date(po.purchase_date).toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' }) }}
                                </td>
                                <td class="py-3.5 px-4 font-semibold text-xs text-gray-900 dark:text-gray-100">
                                    {{ po.supplier_name }}
                                </td>
                                <td class="py-3.5 px-4 font-mono text-xs text-gray-500 dark:text-gray-400">
                                    {{ po.invoice_reference || '—' }}
                                </td>
                                <td class="py-3.5 px-4 text-center whitespace-nowrap font-mono text-xs font-semibold text-emerald-600 dark:text-emerald-400">
                                    +{{ po.total_items_count }}
                                </td>
                                <td class="py-3.5 px-4 text-right whitespace-nowrap font-mono text-xs font-bold text-gray-900 dark:text-white">
                                    {{ formatCurrency(po.total_cost) }}
                                </td>
                                <td class="py-3.5 px-4 whitespace-nowrap">
                                    <span
                                        :class="['text-[11px] font-bold px-2 py-0.5 rounded-full border', getPaymentStatusBadge(po.payment_status).class]"
                                    >
                                        {{ getPaymentStatusBadge(po.payment_status).label }}
                                    </span>
                                </td>
                                <td class="py-3.5 px-4 text-xs text-gray-500 dark:text-gray-400 whitespace-nowrap">
                                    {{ po.user?.name || 'Staff' }}
                                </td>
                                <td class="py-3.5 px-4 text-right whitespace-nowrap">
                                    <Link
                                        :href="route('admin.purchases.show', po.id)"
                                        class="inline-flex items-center gap-1 px-2.5 py-1 text-xs font-semibold text-primary hover:bg-primary/10 rounded-lg transition-all"
                                    >
                                        View Details
                                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                                        </svg>
                                    </Link>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div v-if="purchases.data && purchases.data.length > 0" class="p-4 border-t border-gray-200 dark:border-gray-700">
                    <Pagination :paginator="purchases" />
                </div>
            </div>
        </div>
    </AdminLayout>
</template>

