<script setup>
import { Head, Link, useForm, router } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import Pagination from '@/Components/Pagination.vue';
import { ref, watch, computed } from 'vue';

const props = defineProps({
    products:   Object,
    categories: Array,
    stats:      Object,
    filters:    Object,
});

// Filters
const activeTab   = ref(props.filters.tab || 'all');
const search      = ref(props.filters.search || '');
const categoryId  = ref(props.filters.category_id || '');

// Modals
const showApplyModal     = ref(false);
const showQuickEditModal = ref(false);
const showBulkDateModal  = ref(false);

// Quick edit product reference
const quickEditProduct = ref(null);

// Apply form
const applyForm = useForm({
    target_type:        'single',
    product_id:         null,
    product_ids:        [],
    category_id:        '',
    apply_markdown:     false,
    markdown_mode:      'percent',
    markdown_value:     '',
    apply_flash_sale:   true,
    flash_mode:         'percent',
    flash_value:        '',
    special_price_from: '',
    special_price_to:   '',
});

// Single product search state inside modal
const searchProductQuery   = ref('');
const searchProductResults = ref([]);
const selectedProduct      = ref(null);
const isSearchingProduct   = ref(false);

// Quick Edit form
const quickEditForm = useForm({
    discount_price:     '',
    special_price:      '',
    special_price_from: '',
    special_price_to:   '',
});

// Bulk extend date form
const bulkExtendForm = useForm({
    extend_date: '',
});

// Selection for bulk actions
const selectedIds = ref([]);
const isAllSelected = computed(() => {
    return props.products?.data?.length > 0 && selectedIds.value.length === props.products.data.length;
});

const toggleSelectAll = () => {
    if (isAllSelected.value) {
        selectedIds.value = [];
    } else {
        selectedIds.value = props.products.data.map(p => p.id);
    }
};

const toggleSelectProduct = (id) => {
    const idx = selectedIds.value.indexOf(id);
    if (idx > -1) {
        selectedIds.value.splice(idx, 1);
    } else {
        selectedIds.value.push(id);
    }
};

// Filter triggering
const applyFilters = () => {
    router.get(route('admin.discounts.index'), {
        tab:         activeTab.value,
        search:      search.value,
        category_id: categoryId.value,
    }, { preserveState: true, replace: true });
};

watch([activeTab, categoryId], () => {
    applyFilters();
});

let searchTimeout = null;
watch(search, () => {
    clearTimeout(searchTimeout);
    searchTimeout = setTimeout(() => {
        applyFilters();
    }, 300);
});

// Autocomplete search for products inside Apply Modal
let productSearchTimer = null;
const onProductSearchInput = () => {
    clearTimeout(productSearchTimer);
    if (searchProductQuery.value.trim().length < 2) {
        searchProductResults.value = [];
        return;
    }
    isSearchingProduct.value = true;
    productSearchTimer = setTimeout(async () => {
        try {
            const res = await fetch(route('admin.discounts.search-products') + '?q=' + encodeURIComponent(searchProductQuery.value));
            const data = await res.json();
            searchProductResults.value = data.products || [];
        } catch (e) {
            console.error('Failed to search products:', e);
        } finally {
            isSearchingProduct.value = false;
        }
    }, 250);
};

const selectProductForApply = (prod) => {
    selectedProduct.value = prod;
    applyForm.product_id = prod.id;
    searchProductQuery.value = prod.name;
    searchProductResults.value = [];
};

const clearSelectedProduct = () => {
    selectedProduct.value = null;
    applyForm.product_id = null;
    searchProductQuery.value = '';
    searchProductResults.value = [];
};

const openApplyModal = () => {
    applyForm.reset();
    applyForm.target_type = 'single';
    applyForm.apply_flash_sale = true;
    applyForm.flash_mode = 'percent';
    applyForm.apply_markdown = false;
    applyForm.markdown_mode = 'percent';
    clearSelectedProduct();
    showApplyModal.value = true;
};

const submitApply = () => {
    applyForm.post(route('admin.discounts.apply'), {
        preserveScroll: true,
        onSuccess: () => {
            showApplyModal.value = false;
            applyForm.reset();
            clearSelectedProduct();
        },
    });
};

const openQuickEdit = (product) => {
    quickEditProduct.value = product;
    quickEditForm.clearErrors();
    quickEditForm.discount_price     = product.discount_price ?? '';
    quickEditForm.special_price      = product.special_price ?? '';
    quickEditForm.special_price_from = product.special_price_from ?? '';
    quickEditForm.special_price_to   = product.special_price_to ?? '';
    showQuickEditModal.value = true;
};

const submitQuickEdit = () => {
    if (!quickEditProduct.value) return;
    quickEditForm.put(route('admin.discounts.update', quickEditProduct.value.id), {
        preserveScroll: true,
        onSuccess: () => {
            showQuickEditModal.value = false;
            quickEditProduct.value = null;
        },
    });
};

const deletePromotion = (product, scope = 'all') => {
    const label = scope === 'flash'
        ? 'flash sale'
        : (scope === 'markdown' ? 'standard discount' : 'all discounts');

    if (confirm(`Remove ${label} from "${product.name}"?`)) {
        router.delete(route('admin.discounts.destroy', product.id) + `?scope=${scope}`, {
            preserveScroll: true,
        });
    }
};

const executeBulkAction = (action) => {
    if (selectedIds.value.length === 0) return;

    if (action === 'extend_flash') {
        showBulkDateModal.value = true;
        return;
    }

    const actionLabels = {
        clear_all:      'remove ALL discounts and flash sales from',
        clear_flash:    'clear flash sales from',
        clear_markdown: 'clear regular discounts from',
    };

    if (confirm(`Are you sure you want to ${actionLabels[action]} the ${selectedIds.value.length} selected product(s)?`)) {
        router.post(route('admin.discounts.bulk-action'), {
            product_ids: selectedIds.value,
            action:      action,
        }, {
            preserveScroll: true,
            onSuccess: () => {
                selectedIds.value = [];
            },
        });
    }
};

const submitBulkExtend = () => {
    if (!bulkExtendForm.extend_date || selectedIds.value.length === 0) return;
    bulkExtendForm.post(route('admin.discounts.bulk-action'), {
        product_ids: selectedIds.value,
        action:      'extend_flash',
        preserveScroll: true,
        onSuccess: () => {
            showBulkDateModal.value = false;
            selectedIds.value = [];
            bulkExtendForm.reset();
        },
    });
};

const formatPrice = (val) => {
    if (val === null || val === undefined || isNaN(val)) return '—';
    return '₦' + Number(val).toLocaleString('en-NG', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
};
</script>

<template>
    <Head title="Discounts & Flash Sales" />
    <AdminLayout>
        <div class="container mx-auto px-4 py-8 max-w-7xl">

            <!-- ── Top Header ──────────────────────────────────────── -->
            <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4 mb-6">
                <div>
                    <h1 class="text-2xl font-bold text-gray-900 dark:text-white flex items-center gap-2.5">
                        <span class="inline-flex items-center justify-center w-9 h-9 rounded-xl bg-primary/10 text-primary">
                            ⚡
                        </span>
                        Discounts & Flash Sales
                    </h1>
                    <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">
                        Centrally track, schedule, and manage promotional pricing, flash deals, and standard markdowns.
                    </p>
                </div>
                <div class="flex items-center gap-3">
                    <Link
                        :href="route('admin.products.index')"
                        class="px-4 py-2 text-sm font-semibold text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-600 rounded-xl hover:bg-gray-50 dark:hover:bg-gray-700 transition"
                    >
                        View Full Catalog
                    </Link>
                    <button
                        type="button"
                        @click="openApplyModal"
                        class="px-5 py-2.5 text-sm font-bold text-white bg-primary hover:bg-primary-dark rounded-xl shadow-sm transition flex items-center gap-2 cursor-pointer"
                    >
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                        + Create Promotion / Flash Sale
                    </button>
                </div>
            </div>

            <!-- ── Overview KPI Cards ──────────────────────────────── -->
            <div class="grid grid-cols-2 lg:grid-cols-5 gap-3.5 mb-6">
                <div
                    @click="activeTab = 'all'"
                    class="bg-white dark:bg-gray-800 rounded-xl p-4 border border-gray-200 dark:border-gray-700 shadow-xs cursor-pointer hover:border-primary transition"
                    :class="activeTab === 'all' ? 'ring-2 ring-primary border-primary' : ''"
                >
                    <p class="text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">All Promotions</p>
                    <p class="text-2xl font-black text-gray-900 dark:text-white mt-1">{{ stats.total_promotions }}</p>
                    <p class="text-[11px] text-gray-400 mt-1">Total discounted items</p>
                </div>

                <div
                    @click="activeTab = 'active_flash'"
                    class="bg-white dark:bg-gray-800 rounded-xl p-4 border border-gray-200 dark:border-gray-700 shadow-xs cursor-pointer hover:border-amber-500 transition"
                    :class="activeTab === 'active_flash' ? 'ring-2 ring-amber-500 border-amber-500' : ''"
                >
                    <div class="flex items-center justify-between">
                        <p class="text-xs font-semibold text-amber-600 dark:text-amber-400 uppercase tracking-wider">Active Flash Deals</p>
                        <span class="w-2.5 h-2.5 rounded-full bg-amber-500 animate-pulse"></span>
                    </div>
                    <p class="text-2xl font-black text-amber-600 dark:text-amber-400 mt-1">{{ stats.active_flash }}</p>
                    <p class="text-[11px] text-gray-400 mt-1">Live on storefront now</p>
                </div>

                <div
                    @click="activeTab = 'upcoming_flash'"
                    class="bg-white dark:bg-gray-800 rounded-xl p-4 border border-gray-200 dark:border-gray-700 shadow-xs cursor-pointer hover:border-blue-500 transition"
                    :class="activeTab === 'upcoming_flash' ? 'ring-2 ring-blue-500 border-blue-500' : ''"
                >
                    <p class="text-xs font-semibold text-blue-600 dark:text-blue-400 uppercase tracking-wider">Scheduled Deals</p>
                    <p class="text-2xl font-black text-blue-600 dark:text-blue-400 mt-1">{{ stats.upcoming_flash }}</p>
                    <p class="text-[11px] text-gray-400 mt-1">Starts in future</p>
                </div>

                <div
                    @click="activeTab = 'expired_flash'"
                    class="bg-white dark:bg-gray-800 rounded-xl p-4 border border-gray-200 dark:border-gray-700 shadow-xs cursor-pointer hover:border-gray-500 transition"
                    :class="activeTab === 'expired_flash' ? 'ring-2 ring-gray-500 border-gray-500' : ''"
                >
                    <p class="text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Expired Deals</p>
                    <p class="text-2xl font-black text-gray-600 dark:text-gray-300 mt-1">{{ stats.expired_flash }}</p>
                    <p class="text-[11px] text-gray-400 mt-1">Sale window ended</p>
                </div>

                <div
                    @click="activeTab = 'markdowns'"
                    class="bg-white dark:bg-gray-800 rounded-xl p-4 border border-gray-200 dark:border-gray-700 shadow-xs cursor-pointer hover:border-emerald-500 transition"
                    :class="activeTab === 'markdowns' ? 'ring-2 ring-emerald-500 border-emerald-500' : ''"
                >
                    <p class="text-xs font-semibold text-emerald-600 dark:text-emerald-400 uppercase tracking-wider">Standard Markdowns</p>
                    <p class="text-2xl font-black text-emerald-600 dark:text-emerald-400 mt-1">{{ stats.markdowns }}</p>
                    <p class="text-[11px] text-gray-400 mt-1">Regular discount price</p>
                </div>
            </div>

            <!-- ── Filters & Search Bar ────────────────────────────── -->
            <div class="bg-white dark:bg-gray-800 rounded-xl p-4 border border-gray-200 dark:border-gray-700 shadow-xs mb-5 flex flex-wrap items-center justify-between gap-4">
                <div class="flex flex-wrap items-center gap-2">
                    <button
                        type="button"
                        v-for="tab in [
                            { id: 'all', label: 'All Promotions' },
                            { id: 'active_flash', label: '⚡ Active Flash Deals' },
                            { id: 'upcoming_flash', label: '🕒 Scheduled Deals' },
                            { id: 'expired_flash', label: '⏹ Expired Deals' },
                            { id: 'markdowns', label: '🏷 Markdowns' },
                        ]"
                        :key="tab.id"
                        @click="activeTab = tab.id"
                        class="px-3.5 py-1.5 rounded-lg text-xs font-bold transition cursor-pointer"
                        :class="activeTab === tab.id
                            ? 'bg-primary text-white shadow-xs'
                            : 'bg-gray-100 dark:bg-gray-700/60 text-gray-600 dark:text-gray-300 hover:bg-gray-200 dark:hover:bg-gray-700'"
                    >
                        {{ tab.label }}
                    </button>
                </div>

                <div class="flex items-center gap-3 w-full sm:w-auto">
                    <!-- Search Input -->
                    <div class="relative flex-1 sm:w-64">
                        <input
                            v-model="search"
                            type="text"
                            placeholder="Search by product name, SKU..."
                            class="w-full text-xs rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white pl-8 pr-3 py-2 focus:ring-primary shadow-xs"
                        />
                        <svg class="w-3.5 h-3.5 text-gray-400 absolute left-2.5 top-2.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                    </div>

                    <!-- Category Filter -->
                    <select
                        v-model="categoryId"
                        class="text-xs rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white py-2 pl-3 pr-8 focus:ring-primary shadow-xs"
                    >
                        <option value="">All Categories</option>
                        <option v-for="cat in categories" :key="cat.id" :value="cat.id">{{ cat.name }}</option>
                    </select>
                </div>
            </div>

            <!-- ── Bulk Actions Bar (Sticky/Floating when items selected) -->
            <div
                v-if="selectedIds.length > 0"
                class="mb-4 bg-primary/10 border border-primary/20 rounded-xl p-3.5 flex flex-wrap items-center justify-between gap-3 animate-fade-in"
            >
                <div class="flex items-center gap-2">
                    <span class="inline-flex items-center justify-center bg-primary text-white text-xs font-bold px-2.5 py-1 rounded-full">
                        {{ selectedIds.length }}
                    </span>
                    <span class="text-sm font-semibold text-primary">
                        {{ selectedIds.length === 1 ? '1 product selected' : `${selectedIds.length} products selected` }}
                    </span>
                    <button
                        type="button"
                        @click="selectedIds = []"
                        class="text-xs text-primary hover:underline ml-2 cursor-pointer font-medium"
                    >
                        Deselect all
                    </button>
                </div>

                <div class="flex flex-wrap items-center gap-2">
                    <button
                        type="button"
                        @click="executeBulkAction('extend_flash')"
                        class="px-3 py-1.5 bg-white dark:bg-gray-800 hover:bg-gray-50 text-gray-800 dark:text-gray-200 border border-gray-300 dark:border-gray-600 rounded-lg text-xs font-semibold shadow-xs flex items-center gap-1.5 transition cursor-pointer"
                    >
                        <svg class="w-3.5 h-3.5 text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                        Extend Flash End Date
                    </button>

                    <button
                        type="button"
                        @click="executeBulkAction('clear_flash')"
                        class="px-3 py-1.5 bg-white dark:bg-gray-800 hover:bg-amber-50 dark:hover:bg-amber-950/40 text-amber-700 dark:text-amber-400 border border-amber-300 dark:border-amber-700 rounded-lg text-xs font-semibold shadow-xs flex items-center gap-1.5 transition cursor-pointer"
                    >
                        Clear Flash Sales Only
                    </button>

                    <button
                        type="button"
                        @click="executeBulkAction('clear_markdown')"
                        class="px-3 py-1.5 bg-white dark:bg-gray-800 hover:bg-emerald-50 dark:hover:bg-emerald-950/40 text-emerald-700 dark:text-emerald-400 border border-emerald-300 dark:border-emerald-700 rounded-lg text-xs font-semibold shadow-xs flex items-center gap-1.5 transition cursor-pointer"
                    >
                        Clear Regular Markdowns
                    </button>

                    <button
                        type="button"
                        @click="executeBulkAction('clear_all')"
                        class="px-3 py-1.5 bg-red-600 hover:bg-red-700 text-white rounded-lg text-xs font-semibold shadow-xs flex items-center gap-1.5 transition cursor-pointer"
                    >
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                        Remove All Discounts
                    </button>
                </div>
            </div>

            <!-- ── Products Table ──────────────────────────────────── -->
            <div class="bg-white dark:bg-gray-800 rounded-xl shadow-xs border border-gray-200 dark:border-gray-700 overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-sm text-gray-600 dark:text-gray-300">
                        <thead class="bg-gray-50 dark:bg-gray-700/50 text-xs font-bold text-gray-500 dark:text-gray-400 uppercase tracking-wider border-b border-gray-200 dark:border-gray-700">
                            <tr>
                                <th class="p-4 w-10 text-center">
                                    <input
                                        type="checkbox"
                                        :checked="isAllSelected"
                                        @change="toggleSelectAll"
                                        class="rounded border-gray-300 text-primary focus:ring-primary h-4 w-4"
                                    />
                                </th>
                                <th class="py-3 px-4">Product</th>
                                <th class="py-3 px-4">Category</th>
                                <th class="py-3 px-4 text-right">Original Price</th>
                                <th class="py-3 px-4 text-center">Standard Discount</th>
                                <th class="py-3 px-4 text-center">⚡ Flash Sale</th>
                                <th class="py-3 px-4 text-right">Shopper Pays Now</th>
                                <th class="py-3 px-4 text-center">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                            <tr
                                v-for="product in products.data"
                                :key="product.id"
                                class="hover:bg-gray-50 dark:hover:bg-gray-700/30 transition"
                                :class="selectedIds.includes(product.id) ? 'bg-primary/5 dark:bg-primary/10' : ''"
                            >
                                <!-- Checkbox -->
                                <td class="p-4 text-center">
                                    <input
                                        type="checkbox"
                                        :checked="selectedIds.includes(product.id)"
                                        @change="toggleSelectProduct(product.id)"
                                        class="rounded border-gray-300 text-primary focus:ring-primary h-4 w-4"
                                    />
                                </td>

                                <!-- Product Details -->
                                <td class="py-3.5 px-4">
                                    <div class="flex items-center gap-3">
                                        <div class="w-12 h-12 rounded-lg bg-gray-100 dark:bg-gray-700 overflow-hidden flex-shrink-0 border border-gray-200 dark:border-gray-600 flex items-center justify-center">
                                            <img
                                                v-if="product.image"
                                                :src="'/storage/' + product.image"
                                                class="w-full h-full object-cover"
                                                alt="Thumbnail"
                                            />
                                            <span v-else class="text-xs text-gray-400 font-bold">No img</span>
                                        </div>
                                        <div class="min-w-0">
                                            <div class="font-bold text-gray-900 dark:text-white truncate max-w-xs hover:text-primary transition">
                                                <a :href="'/products/' + product.slug" target="_blank" class="hover:underline">
                                                    {{ product.name }}
                                                </a>
                                            </div>
                                            <div class="flex items-center gap-2 mt-0.5 text-xs text-gray-500 dark:text-gray-400">
                                                <span>SKU: <code class="font-mono">{{ product.sku || 'N/A' }}</code></span>
                                                <span v-if="product.inventory_source === 'pos'" class="px-1.5 py-0.2 rounded text-[10px] font-bold bg-blue-100 dark:bg-blue-900/40 text-blue-700 dark:text-blue-300">
                                                    POS
                                                </span>
                                                <span class="text-gray-300 dark:text-gray-600">•</span>
                                                <span>Stock: {{ product.stock }}</span>
                                            </div>
                                        </div>
                                    </div>
                                </td>

                                <!-- Category -->
                                <td class="py-3.5 px-4 text-xs font-medium text-gray-600 dark:text-gray-300">
                                    {{ product.category?.name || 'Uncategorized' }}
                                </td>

                                <!-- Original Price -->
                                <td class="py-3.5 px-4 text-right font-medium text-gray-500 line-through">
                                    {{ formatPrice(product.price) }}
                                </td>

                                <!-- Standard Markdown -->
                                <td class="py-3.5 px-4 text-center">
                                    <div v-if="product.discount_price" class="inline-flex flex-col items-center">
                                        <span class="font-bold text-gray-900 dark:text-white">
                                            {{ formatPrice(product.discount_price) }}
                                        </span>
                                        <span class="text-[10px] font-semibold text-emerald-600 dark:text-emerald-400 bg-emerald-50 dark:bg-emerald-950/40 px-1.5 py-0.5 rounded mt-0.5">
                                            {{ Math.round(((product.price - product.discount_price) / product.price) * 100) }}% OFF
                                        </span>
                                    </div>
                                    <span v-else class="text-xs text-gray-400 italic">None</span>
                                </td>

                                <!-- Flash Sale Column -->
                                <td class="py-3.5 px-4 text-center">
                                    <div v-if="product.special_price" class="inline-flex flex-col items-center">
                                        <div class="flex items-center gap-1.5">
                                            <span class="font-bold text-amber-600 dark:text-amber-400">
                                                ⚡ {{ formatPrice(product.special_price) }}
                                            </span>
                                            <span
                                                v-if="product.flash_sale_status === 'active'"
                                                class="px-1.5 py-0.5 rounded-full text-[10px] font-extrabold bg-amber-100 dark:bg-amber-900/50 text-amber-800 dark:text-amber-300 uppercase tracking-wider"
                                            >
                                                Active
                                            </span>
                                            <span
                                                v-else-if="product.flash_sale_status === 'upcoming'"
                                                class="px-1.5 py-0.5 rounded-full text-[10px] font-bold bg-blue-100 dark:bg-blue-900/40 text-blue-700 dark:text-blue-300 uppercase tracking-wider"
                                            >
                                                Starts Soon
                                            </span>
                                            <span
                                                v-else-if="product.flash_sale_status === 'expired'"
                                                class="px-1.5 py-0.5 rounded-full text-[10px] font-bold bg-gray-100 dark:bg-gray-700 text-gray-500 uppercase tracking-wider"
                                            >
                                                Expired
                                            </span>
                                        </div>
                                        <div v-if="product.special_price_to_formatted" class="text-[10px] text-gray-400 mt-0.5">
                                            Until {{ product.special_price_to_formatted }}
                                        </div>
                                    </div>
                                    <span v-else class="text-xs text-gray-400 italic">None</span>
                                </td>

                                <!-- Shopper Pays Now (Hierarchy Result) -->
                                <td class="py-3.5 px-4 text-right">
                                    <div class="inline-flex flex-col items-end">
                                        <span class="text-base font-black text-primary">
                                            {{ formatPrice(product.effective_price) }}
                                        </span>
                                        <span v-if="product.savings_percent > 0" class="text-[10px] font-extrabold text-green-600 dark:text-green-400">
                                            SAVE {{ product.savings_percent }}%
                                        </span>
                                        <span v-if="product.is_on_flash_sale" class="text-[9px] uppercase font-bold text-amber-600 tracking-wider">
                                            (Flash Deal Priority)
                                        </span>
                                        <span v-else-if="product.discount_price" class="text-[9px] uppercase font-bold text-gray-400 tracking-wider">
                                            (Markdown Applied)
                                        </span>
                                    </div>
                                </td>

                                <!-- Actions -->
                                <td class="py-3.5 px-4 text-center whitespace-nowrap">
                                    <div class="inline-flex items-center gap-1.5">
                                        <!-- Quick Edit Button -->
                                        <button
                                            type="button"
                                            @click="openQuickEdit(product)"
                                            class="p-1.5 rounded-lg bg-gray-100 dark:bg-gray-700 hover:bg-primary hover:text-white text-gray-600 dark:text-gray-300 transition cursor-pointer"
                                            title="Quick Edit Discount & Dates"
                                        >
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                                        </button>

                                        <!-- Clear Flash Deal Button (if flash exists) -->
                                        <button
                                            v-if="product.special_price"
                                            type="button"
                                            @click="deletePromotion(product, 'flash')"
                                            class="p-1.5 rounded-lg bg-amber-50 dark:bg-amber-950/40 hover:bg-amber-600 hover:text-white text-amber-600 transition cursor-pointer"
                                            title="Remove Flash Sale only"
                                        >
                                            <span class="text-xs font-bold leading-none">⚡✕</span>
                                        </button>

                                        <!-- Clear Standard Discount Button (if markdown exists) -->
                                        <button
                                            v-if="product.discount_price"
                                            type="button"
                                            @click="deletePromotion(product, 'markdown')"
                                            class="p-1.5 rounded-lg bg-emerald-50 dark:bg-emerald-950/40 hover:bg-emerald-600 hover:text-white text-emerald-600 transition cursor-pointer"
                                            title="Remove Regular Discount only"
                                        >
                                            <span class="text-xs font-bold leading-none">🏷✕</span>
                                        </button>

                                        <!-- Remove All Discounts -->
                                        <button
                                            type="button"
                                            @click="deletePromotion(product, 'all')"
                                            class="p-1.5 rounded-lg bg-red-50 dark:bg-red-950/40 hover:bg-red-600 hover:text-white text-red-600 transition cursor-pointer"
                                            title="Remove All Promotions"
                                        >
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                        </button>
                                    </div>
                                </td>
                            </tr>

                            <tr v-if="!products.data || products.data.length === 0">
                                <td colspan="8" class="text-center py-12 text-gray-500">
                                    <div class="flex flex-col items-center justify-center">
                                        <span class="text-4xl mb-2">🏷</span>
                                        <p class="font-bold text-gray-700 dark:text-gray-300">No discounted products found</p>
                                        <p class="text-xs text-gray-400 mt-1 max-w-sm">
                                            There are currently no products with active discounts or flash deals matching this filter.
                                        </p>
                                        <button
                                            type="button"
                                            @click="openApplyModal"
                                            class="mt-4 px-4 py-2 bg-primary text-white rounded-lg text-xs font-bold hover:bg-primary-dark transition"
                                        >
                                            + Add Discount / Flash Sale
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Pagination -->
                <div v-if="products.links && products.links.length > 3" class="p-4 border-t border-gray-200 dark:border-gray-700">
                    <Pagination :links="products.links" />
                </div>
            </div>

        </div>

        <!-- ═════════════════════════════════════════════════════════════════ -->
        <!-- ── Modal: Create / Apply Promotion ──────────────────────────── -->
        <!-- ═════════════════════════════════════════════════════════════════ -->
        <div v-if="showApplyModal" class="fixed inset-0 z-50 flex items-center justify-center p-3 sm:p-4 bg-black/60 backdrop-blur-xs">
            <div class="bg-white dark:bg-gray-800 rounded-2xl max-w-lg w-full max-h-[88vh] flex flex-col shadow-2xl border border-gray-200 dark:border-gray-700 overflow-hidden animate-fade-in">
                
                <!-- Fixed Header -->
                <div class="px-5 py-3.5 border-b border-gray-200 dark:border-gray-700 flex items-center justify-between shrink-0 bg-white dark:bg-gray-800">
                    <div>
                        <h3 class="text-base font-bold text-gray-900 dark:text-white flex items-center gap-2">
                            <span>⚡</span> Create Discount or Flash Sale
                        </h3>
                        <p class="text-[11px] text-gray-500 dark:text-gray-400 mt-0.5">
                            Set markdown pricing or time-limited flash sales on products.
                        </p>
                    </div>
                    <button
                        type="button"
                        @click="showApplyModal = false"
                        class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-200 text-lg font-bold p-1 cursor-pointer"
                    >
                        ✕
                    </button>
                </div>

                <!-- Scrollable Body with internal scrollbar -->
                <form id="applyPromotionForm" @submit.prevent="submitApply" class="flex-1 overflow-y-auto p-4 sm:p-5 space-y-3.5 overscroll-contain">

                    <!-- Target Selection -->
                    <div>
                        <label class="block text-[11px] font-bold text-gray-600 dark:text-gray-400 uppercase tracking-wider mb-1.5">
                            Apply To
                        </label>
                        <div class="grid grid-cols-2 gap-2.5">
                            <button
                                type="button"
                                @click="applyForm.target_type = 'single'"
                                class="p-2.5 rounded-xl border text-left transition cursor-pointer"
                                :class="applyForm.target_type === 'single'
                                    ? 'border-primary bg-primary/5 ring-2 ring-primary/20 font-bold text-primary'
                                    : 'border-gray-200 dark:border-gray-700 hover:bg-gray-50 dark:hover:bg-gray-700 text-gray-700 dark:text-gray-300'"
                            >
                                <div class="text-xs font-bold">Single Product</div>
                                <div class="text-[10px] opacity-75 font-normal">Select a specific product</div>
                            </button>

                            <button
                                type="button"
                                @click="applyForm.target_type = 'category'"
                                class="p-2.5 rounded-xl border text-left transition cursor-pointer"
                                :class="applyForm.target_type === 'category'
                                    ? 'border-primary bg-primary/5 ring-2 ring-primary/20 font-bold text-primary'
                                    : 'border-gray-200 dark:border-gray-700 hover:bg-gray-50 dark:hover:bg-gray-700 text-gray-700 dark:text-gray-300'"
                            >
                                <div class="text-xs font-bold">Entire Category</div>
                                <div class="text-[10px] opacity-75 font-normal">Discount all products in category</div>
                            </button>
                        </div>
                    </div>

                    <!-- Single Product Search Autocomplete -->
                    <div v-if="applyForm.target_type === 'single'" class="relative">
                        <label class="block text-[11px] font-bold text-gray-600 dark:text-gray-400 uppercase tracking-wider mb-1">
                            Select Product *
                        </label>

                        <div v-if="selectedProduct" class="p-2.5 bg-gray-50 dark:bg-gray-700/50 rounded-xl border border-gray-200 dark:border-gray-600 flex items-center justify-between">
                            <div class="flex items-center gap-2.5">
                                <div class="w-9 h-9 rounded-lg bg-gray-200 dark:bg-gray-600 overflow-hidden flex-shrink-0">
                                    <img v-if="selectedProduct.image" :src="'/storage/' + selectedProduct.image" class="w-full h-full object-cover" />
                                </div>
                                <div class="min-w-0">
                                    <p class="font-bold text-xs text-gray-900 dark:text-white truncate max-w-[220px]">{{ selectedProduct.name }}</p>
                                    <p class="text-[11px] text-gray-500">Regular: <strong class="text-primary">{{ formatPrice(selectedProduct.price) }}</strong> | Stock: {{ selectedProduct.stock }}</p>
                                </div>
                            </div>
                            <button
                                type="button"
                                @click="clearSelectedProduct"
                                class="text-xs text-red-500 hover:underline font-semibold cursor-pointer shrink-0 ml-2"
                            >
                                Change
                            </button>
                        </div>

                        <div v-else>
                            <input
                                v-model="searchProductQuery"
                                @input="onProductSearchInput"
                                type="text"
                                placeholder="Type product name or SKU to search..."
                                class="w-full text-xs rounded-xl border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white py-2 focus:ring-primary shadow-xs"
                            />
                            <div v-if="isSearchingProduct" class="text-[10px] text-gray-400 mt-1 italic">Searching products...</div>

                            <!-- Dropdown Results -->
                            <div
                                v-if="searchProductResults.length > 0"
                                class="absolute z-30 left-0 right-0 mt-1 bg-white dark:bg-gray-700 rounded-xl shadow-xl border border-gray-200 dark:border-gray-600 max-h-48 overflow-y-auto divide-y divide-gray-100 dark:divide-gray-600"
                            >
                                <div
                                    v-for="p in searchProductResults"
                                    :key="p.id"
                                    @click="selectProductForApply(p)"
                                    class="p-2.5 hover:bg-primary/10 flex items-center justify-between cursor-pointer transition"
                                >
                                    <div class="flex items-center gap-2.5 min-w-0">
                                        <div class="w-7 h-7 rounded bg-gray-100 dark:bg-gray-800 overflow-hidden flex-shrink-0">
                                            <img v-if="p.image" :src="'/storage/' + p.image" class="w-full h-full object-cover" />
                                        </div>
                                        <div class="min-w-0 truncate">
                                            <div class="text-xs font-bold text-gray-900 dark:text-white truncate">{{ p.name }}</div>
                                            <div class="text-[10px] text-gray-400">SKU: {{ p.sku }}</div>
                                        </div>
                                    </div>
                                    <div class="text-xs font-black text-primary shrink-0 ml-2">
                                        {{ formatPrice(p.price) }}
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Category Selection -->
                    <div v-if="applyForm.target_type === 'category'">
                        <label class="block text-[11px] font-bold text-gray-600 dark:text-gray-400 uppercase tracking-wider mb-1">
                            Select Category *
                        </label>
                        <select
                            v-model="applyForm.category_id"
                            required
                            class="w-full text-xs rounded-xl border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white py-2 focus:ring-primary shadow-xs"
                        >
                            <option value="">-- Choose Category --</option>
                            <option v-for="cat in categories" :key="cat.id" :value="cat.id">{{ cat.name }}</option>
                        </select>
                    </div>

                    <!-- ── Flash Sale Option ── -->
                    <div class="p-3.5 rounded-xl border border-amber-200 dark:border-amber-900/50 bg-amber-50/50 dark:bg-amber-950/20">
                        <div class="flex items-center justify-between mb-2.5">
                            <label class="flex items-center gap-2 cursor-pointer">
                                <input
                                    type="checkbox"
                                    v-model="applyForm.apply_flash_sale"
                                    class="rounded border-amber-300 text-amber-600 focus:ring-amber-500 h-4 w-4"
                                />
                                <span class="text-xs font-bold text-amber-900 dark:text-amber-300 flex items-center gap-1.5">
                                    ⚡ Special Price / Flash Sale
                                </span>
                            </label>
                            <span class="text-[10px] text-amber-700 dark:text-amber-400 font-semibold">Overrides regular discount when active</span>
                        </div>

                        <div v-if="applyForm.apply_flash_sale" class="space-y-2.5 pt-1">
                            <div class="grid grid-cols-2 gap-2.5">
                                <div>
                                    <label class="block text-[10px] font-bold text-gray-600 dark:text-gray-400 uppercase mb-1">Discount Mode</label>
                                    <select
                                        v-model="applyForm.flash_mode"
                                        class="w-full text-xs rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white py-1.5 focus:ring-amber-500"
                                    >
                                        <option value="percent">Percentage Off (%)</option>
                                        <option value="fixed">Fixed Price (₦)</option>
                                    </select>
                                </div>
                                <div>
                                    <label class="block text-[10px] font-bold text-gray-600 dark:text-gray-400 uppercase mb-1">
                                        {{ applyForm.flash_mode === 'percent' ? 'Discount Percentage (%)' : 'New Flash Sale Price (₦)' }}
                                    </label>
                                    <input
                                        v-model.number="applyForm.flash_value"
                                        type="number"
                                        min="0"
                                        step="any"
                                        :placeholder="applyForm.flash_mode === 'percent' ? 'e.g. 25' : 'e.g. 15000'"
                                        required
                                        class="w-full text-xs rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white py-1.5 focus:ring-amber-500"
                                    />
                                </div>
                            </div>

                            <div class="grid grid-cols-2 gap-2.5">
                                <div>
                                    <label class="block text-[10px] font-bold text-gray-600 dark:text-gray-400 uppercase mb-1">Starts At (optional)</label>
                                    <input
                                        v-model="applyForm.special_price_from"
                                        type="datetime-local"
                                        class="w-full text-xs rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white py-1.5 focus:ring-amber-500"
                                    />
                                </div>
                                <div>
                                    <label class="block text-[10px] font-bold text-gray-600 dark:text-gray-400 uppercase mb-1">Ends At (optional)</label>
                                    <input
                                        v-model="applyForm.special_price_to"
                                        type="datetime-local"
                                        class="w-full text-xs rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white py-1.5 focus:ring-amber-500"
                                    />
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- ── Standard Regular Markdown Option ── -->
                    <div class="p-3.5 rounded-xl border border-emerald-200 dark:border-emerald-900/50 bg-emerald-50/50 dark:bg-emerald-950/20">
                        <div class="flex items-center justify-between mb-2">
                            <label class="flex items-center gap-2 cursor-pointer">
                                <input
                                    type="checkbox"
                                    v-model="applyForm.apply_markdown"
                                    class="rounded border-emerald-300 text-emerald-600 focus:ring-emerald-500 h-4 w-4"
                                />
                                <span class="text-xs font-bold text-emerald-900 dark:text-emerald-300 flex items-center gap-1.5">
                                    🏷 Standard Everyday Discount
                                </span>
                            </label>
                            <span class="text-[10px] text-emerald-700 dark:text-emerald-400 font-semibold">Standard price strikethrough</span>
                        </div>

                        <div v-if="applyForm.apply_markdown" class="grid grid-cols-2 gap-2.5 pt-1">
                            <div>
                                <label class="block text-[10px] font-bold text-gray-600 dark:text-gray-400 uppercase mb-1">Discount Mode</label>
                                <select
                                    v-model="applyForm.markdown_mode"
                                    class="w-full text-xs rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white py-1.5 focus:ring-emerald-500"
                                >
                                    <option value="percent">Percentage Off (%)</option>
                                    <option value="fixed">Fixed Price (₦)</option>
                                </select>
                            </div>
                            <div>
                                <label class="block text-[10px] font-bold text-gray-600 dark:text-gray-400 uppercase mb-1">
                                    {{ applyForm.markdown_mode === 'percent' ? 'Discount Percentage (%)' : 'New Discount Price (₦)' }}
                                </label>
                                <input
                                    v-model.number="applyForm.markdown_value"
                                    type="number"
                                    min="0"
                                    step="any"
                                    :placeholder="applyForm.markdown_mode === 'percent' ? 'e.g. 15' : 'e.g. 18000'"
                                    required
                                    class="w-full text-xs rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white py-1.5 focus:ring-emerald-500"
                                />
                            </div>
                        </div>
                    </div>

                </form>

                <!-- Fixed Footer (Always visible, never cut off) -->
                <div class="px-5 py-3 border-t border-gray-200 dark:border-gray-700 bg-gray-50/90 dark:bg-gray-800/90 flex items-center justify-end gap-2.5 shrink-0">
                    <button
                        type="button"
                        @click="showApplyModal = false"
                        class="px-4 py-2 text-xs font-semibold text-gray-600 dark:text-gray-300 hover:bg-gray-200/60 dark:hover:bg-gray-700 rounded-xl transition cursor-pointer"
                    >
                        Cancel
                    </button>
                    <button
                        type="submit"
                        form="applyPromotionForm"
                        :disabled="applyForm.processing || (applyForm.target_type === 'single' && !applyForm.product_id) || (!applyForm.apply_flash_sale && !applyForm.apply_markdown)"
                        class="px-5 py-2 text-xs font-bold text-white bg-primary hover:bg-primary-dark rounded-xl shadow-xs transition disabled:opacity-50 flex items-center gap-1.5 cursor-pointer"
                    >
                        <svg v-if="applyForm.processing" class="w-3.5 h-3.5 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path></svg>
                        Apply Promotion
                    </button>
                </div>

            </div>
        </div>

        <!-- ═════════════════════════════════════════════════════════════════ -->
        <!-- ── Modal: Quick Edit Promotion ──────────────────────────────── -->
        <!-- ═════════════════════════════════════════════════════════════════ -->
        <div v-if="showQuickEditModal && quickEditProduct" class="fixed inset-0 z-50 flex items-center justify-center p-3 sm:p-4 bg-black/60 backdrop-blur-xs">
            <div class="bg-white dark:bg-gray-800 rounded-2xl max-w-md w-full max-h-[88vh] flex flex-col shadow-2xl border border-gray-200 dark:border-gray-700 overflow-hidden animate-fade-in">
                
                <!-- Fixed Header -->
                <div class="px-5 py-3.5 border-b border-gray-200 dark:border-gray-700 flex items-center justify-between shrink-0 bg-white dark:bg-gray-800">
                    <div class="min-w-0 pr-2">
                        <h3 class="text-base font-bold text-gray-900 dark:text-white">Quick Edit Pricing</h3>
                        <p class="text-[11px] text-gray-500 dark:text-gray-400 mt-0.5 truncate">
                            {{ quickEditProduct.name }} (Original: <strong class="text-primary">{{ formatPrice(quickEditProduct.price) }}</strong>)
                        </p>
                    </div>
                    <button
                        type="button"
                        @click="showQuickEditModal = false"
                        class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-200 text-lg font-bold p-1 cursor-pointer shrink-0"
                    >
                        ✕
                    </button>
                </div>

                <!-- Scrollable Body -->
                <form id="quickEditForm" @submit.prevent="submitQuickEdit" class="flex-1 overflow-y-auto p-4 sm:p-5 space-y-3.5 overscroll-contain text-xs">
                    <div>
                        <label class="block text-[11px] font-bold text-gray-600 dark:text-gray-400 uppercase tracking-wider mb-1">
                            Standard Discount Price (₦)
                        </label>
                        <input
                            v-model.number="quickEditForm.discount_price"
                            type="number"
                            step="0.01"
                            min="0"
                            placeholder="Leave empty to clear"
                            class="w-full text-xs rounded-xl border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white py-2 focus:ring-primary shadow-xs"
                        />
                        <p class="text-[10px] text-gray-400 mt-1">Permanent markdown price. Leave blank to disable.</p>
                    </div>

                    <div class="p-3 rounded-xl border border-amber-200 dark:border-amber-900/50 bg-amber-50/40 dark:bg-amber-950/20 space-y-2.5">
                        <div>
                            <label class="block text-[11px] font-bold text-amber-900 dark:text-amber-300 uppercase tracking-wider mb-1">
                                ⚡ Special / Flash Sale Price (₦)
                            </label>
                            <input
                                v-model.number="quickEditForm.special_price"
                                type="number"
                                step="0.01"
                                min="0"
                                placeholder="Leave empty to clear flash sale"
                                class="w-full text-xs rounded-xl border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white py-2 focus:ring-amber-500 shadow-xs"
                            />
                        </div>

                        <div class="grid grid-cols-2 gap-2.5">
                            <div>
                                <label class="block text-[10px] font-bold text-gray-600 dark:text-gray-400 uppercase mb-1">Flash Starts</label>
                                <input
                                    v-model="quickEditForm.special_price_from"
                                    type="datetime-local"
                                    class="w-full text-xs rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white py-1.5 focus:ring-amber-500"
                                />
                            </div>
                            <div>
                                <label class="block text-[10px] font-bold text-gray-600 dark:text-gray-400 uppercase mb-1">Flash Ends</label>
                                <input
                                    v-model="quickEditForm.special_price_to"
                                    type="datetime-local"
                                    class="w-full text-xs rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white py-1.5 focus:ring-amber-500"
                                />
                            </div>
                        </div>
                    </div>
                </form>

                <!-- Fixed Footer -->
                <div class="px-5 py-3 border-t border-gray-200 dark:border-gray-700 bg-gray-50/90 dark:bg-gray-800/90 flex items-center justify-end gap-2.5 shrink-0">
                    <button
                        type="button"
                        @click="showQuickEditModal = false"
                        class="px-4 py-2 text-xs font-semibold text-gray-600 dark:text-gray-300 hover:bg-gray-200/60 dark:hover:bg-gray-700 rounded-xl transition cursor-pointer"
                    >
                        Cancel
                    </button>
                    <button
                        type="submit"
                        form="quickEditForm"
                        :disabled="quickEditForm.processing"
                        class="px-5 py-2 text-xs font-bold text-white bg-primary hover:bg-primary-dark rounded-xl shadow-xs transition disabled:opacity-50 cursor-pointer"
                    >
                        Save Changes
                    </button>
                </div>
            </div>
        </div>

        <!-- ═════════════════════════════════════════════════════════════════ -->
        <!-- ── Modal: Bulk Extend Flash Date ────────────────────────────── -->
        <!-- ═════════════════════════════════════════════════════════════════ -->
        <div v-if="showBulkDateModal" class="fixed inset-0 z-50 flex items-center justify-center p-3 sm:p-4 bg-black/60 backdrop-blur-xs">
            <div class="bg-white dark:bg-gray-800 rounded-2xl max-w-sm w-full max-h-[88vh] flex flex-col shadow-2xl border border-gray-200 dark:border-gray-700 overflow-hidden animate-fade-in">
                <div class="px-5 py-3.5 border-b border-gray-200 dark:border-gray-700 flex items-center justify-between shrink-0 bg-white dark:bg-gray-800">
                    <div>
                        <h3 class="text-base font-bold text-gray-900 dark:text-white">Extend Flash Deals</h3>
                        <p class="text-[11px] text-gray-500 dark:text-gray-400 mt-0.5">
                            Set new end date for {{ selectedIds.length }} selected products.
                        </p>
                    </div>
                    <button
                        type="button"
                        @click="showBulkDateModal = false"
                        class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-200 text-lg font-bold p-1 cursor-pointer"
                    >
                        ✕
                    </button>
                </div>

                <form id="bulkExtendForm" @submit.prevent="submitBulkExtend" class="flex-1 overflow-y-auto p-4 sm:p-5 space-y-3.5 overscroll-contain text-xs">
                    <div>
                        <label class="block text-[11px] font-bold text-gray-600 dark:text-gray-400 uppercase tracking-wider mb-1.5">
                            New End Date & Time *
                        </label>
                        <input
                            v-model="bulkExtendForm.extend_date"
                            type="datetime-local"
                            required
                            class="w-full text-xs rounded-xl border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white py-2 focus:ring-primary shadow-xs"
                        />
                    </div>
                </form>

                <div class="px-5 py-3 border-t border-gray-200 dark:border-gray-700 bg-gray-50/90 dark:bg-gray-800/90 flex items-center justify-end gap-2.5 shrink-0">
                    <button
                        type="button"
                        @click="showBulkDateModal = false"
                        class="px-4 py-2 text-xs font-semibold text-gray-600 dark:text-gray-300 hover:bg-gray-200/60 dark:hover:bg-gray-700 rounded-xl transition cursor-pointer"
                    >
                        Cancel
                    </button>
                    <button
                        type="submit"
                        form="bulkExtendForm"
                        :disabled="bulkExtendForm.processing || !bulkExtendForm.extend_date"
                        class="px-5 py-2 text-xs font-bold text-white bg-primary hover:bg-primary-dark rounded-xl shadow-xs transition disabled:opacity-50 cursor-pointer"
                    >
                        Update End Date
                    </button>
                </div>
            </div>
        </div>

    </AdminLayout>
</template>

