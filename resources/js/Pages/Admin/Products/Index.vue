<script setup>
import { Head, Link, useForm, router } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import Pagination from '@/Components/Pagination.vue';
import { ref, watch, computed } from 'vue';
import { useToast } from 'vue-toastification';
import debounce from 'lodash/debounce';
import { QuillEditor } from '@vueup/vue-quill';
import '@vueup/vue-quill/dist/vue-quill.snow.css';

const toast = useToast();
const props = defineProps({
    products:         Object,
    categories:       Array,
    synced_pos_items: {
        type: Array,
        default: () => []
    },
    store_settings:   Object,
    filters: {
        type: Object,
        default: () => ({})
    }
});

// Table Filters
const search = ref(props.filters?.search || '');
const filterCategory = ref(props.filters?.category_id || '');
const filterStatus = ref(props.filters?.status || '');

const applyFilters = debounce(() => {
    router.get(route('admin.products.index'), {
        search: search.value || undefined,
        category_id: filterCategory.value || undefined,
        status: filterStatus.value || undefined,
    }, { preserveState: true, replace: true });
}, 300);

watch([search, filterCategory, filterStatus], applyFilters);

const clearFilters = () => {
    search.value = '';
    filterCategory.value = '';
    filterStatus.value = '';
};

const isFormVisible = ref(false);
const isEditing     = ref(false);
const editId        = ref(null);
const imagePreview  = ref(null);
const galleryPreviews = ref([]);

const form = useForm({
    _method:   'POST',
    // Inventory Source & POS Mapping
    inventory_source:  'ecommerce', // 'pos' or 'ecommerce'
    pos_item_id:       null,
    // Identity
    name:              '',
    category_id:       '',
    url_key:           '',
    product_number:    '',
    sku:               '',
    short_description: '',
    description:       '',
    // Pricing
    price:             '',
    cost_price:        '',
    discount_price:    '',
    special_price:     '',
    special_price_from: '',
    special_price_to:  '',
    // Inventory
    stock:             '',
    // Flags
    status:            true,
    is_new:            false,
    is_featured:       false,
    visible_individually: true,
    show_stock_level:  true,
    // Related Products
    related_products:  [],
    // Media & Variants
    image:   null,
    images:  [],
    variants: [],
    attributes: {},
});

const onPosItemSelect = (e) => {
    const val = e.target.value;
    if (!val) {
        form.pos_item_id = null;
        return;
    }
    const id = parseInt(val);
    const item = props.synced_pos_items.find(i => i.pos_item_id === id);
    if (item) {
        form.pos_item_id = item.pos_item_id;
        form.price = item.standard_price;
        form.stock = Math.round(Number(item.effective_availability || 0));
        if (!form.name || !isEditing.value) {
            form.name = item.pos_item_name;
        }
        if (!form.sku) {
            form.sku = item.sku || '';
        }
    }
};

// ── Related products AJAX picker ─────────────────────────────
const relatedSearch          = ref('');
const relatedResults         = ref([]);   // AJAX results from server
const relatedLoading         = ref(false);
const selectedRelatedProducts = ref([]);  // full objects for chosen items

const isRelated  = (id) => form.related_products.includes(id);
const removeRelated = (id) => {
    form.related_products = form.related_products.filter(i => i !== id);
    selectedRelatedProducts.value = selectedRelatedProducts.value.filter(p => p.id !== id);
};
const toggleRelated = (product) => {
    if (isRelated(product.id)) {
        removeRelated(product.id);
    } else {
        form.related_products.push(product.id);
        if (!selectedRelatedProducts.value.find(p => p.id === product.id)) {
            selectedRelatedProducts.value.push(product);
        }
    }
};

let relatedDebounce = null;
const searchRelated = () => {
    clearTimeout(relatedDebounce);
    relatedDebounce = setTimeout(async () => {
        relatedLoading.value = true;
        try {
            const params = new URLSearchParams({ q: relatedSearch.value, exclude: editId.value || 0 });
            const res = await fetch(`/admin/products/search-related?${params}`, {
                headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' }
            });
            relatedResults.value = await res.json();
        } catch { relatedResults.value = []; }
        finally { relatedLoading.value = false; }
    }, 300);
};

// Load initial results when picker opens
const initRelatedSearch = async () => {
    relatedSearch.value = '';
    relatedLoading.value = true;
    try {
        const params = new URLSearchParams({ q: '', exclude: editId.value || 0 });
        const res = await fetch(`/admin/products/search-related?${params}`, {
            headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' }
        });
        relatedResults.value = await res.json();
    } catch { relatedResults.value = []; }
    finally { relatedLoading.value = false; }
};

// Load the full objects for pre-selected IDs (edit mode)
const loadSelectedRelated = async (ids) => {
    if (!ids?.length) { selectedRelatedProducts.value = []; return; }
    try {
        const res = await fetch(`/admin/products/related-by-ids?ids=${ids.join(',')}`, {
            headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' }
        });
        selectedRelatedProducts.value = await res.json();
    } catch { selectedRelatedProducts.value = []; }
};

// Dynamically fetch attributes for the selected category
const categoryAttributes = computed(() => {
    if (!form.category_id || !props.categories) return [];
    const cat = props.categories.find(c => c.id === form.category_id);
    return cat ? (cat.attributes || []) : [];
});

// Auto-generate url_key from name (only when empty)
watch(() => form.name, (val) => {
    if (!isEditing.value && !form.url_key) {
        form.url_key = val.toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/^-|-$/g, '');
    }
});

const handleImageUpload  = (e) => {
    form.image = e.target.files[0];
    if (form.image) imagePreview.value = URL.createObjectURL(form.image);
};
const handleGalleryUpload = (e) => {
    const files = Array.from(e.target.files).slice(0, 10);
    form.images = files;
    galleryPreviews.value = files.map(f => URL.createObjectURL(f));
};

const addVariant    = () => form.variants.push({ name: '', price: '', stock: '' });
const removeVariant = (i) => form.variants.splice(i, 1);

const save = () => {
    const url = isEditing.value
        ? route('admin.products.update', editId.value)
        : route('admin.products.store');

    form.transform(data => ({
        ...data,
        variants: JSON.stringify(data.variants),
        attributes: JSON.stringify(data.attributes),
        related_products: JSON.stringify(data.related_products),
    }))
        .post(url, {
            preserveScroll: true,
            forceFormData: true,
            onSuccess: () => { 
                //toast.success(isEditing.value ? 'Product updated!' : 'Product created!'); 
                closeForm();
             },
        });
};

const openCreateForm = () => {
    resetFormState();
    form._method = 'POST';
    isEditing.value = false;
    form.show_stock_level = props.store_settings?.show_stock_level_default === '1';
    isFormVisible.value = true;
    initRelatedSearch();
};

const openEditForm = (p) => {
    resetFormState();
    isEditing.value = true;
    editId.value    = p.id;
    form._method    = 'PUT';
    form.inventory_source  = p.inventory_source || (p.pos_item_id ? 'pos' : 'ecommerce');
    form.pos_item_id       = p.pos_item_id || null;
    form.name              = p.name;
    form.category_id       = p.category_id;
    form.url_key           = p.url_key || '';
    form.product_number    = p.product_number || '';
    form.sku               = p.sku || '';
    form.short_description = p.short_description || '';
    form.description       = p.description || '';
    form.price             = p.price;
    form.cost_price        = p.cost_price || '';
    form.discount_price    = p.discount_price || '';
    form.special_price     = p.special_price || '';
    form.special_price_from = p.special_price_from ? p.special_price_from.substring(0,10) : '';
    form.special_price_to   = p.special_price_to   ? p.special_price_to.substring(0,10)   : '';
    form.stock             = p.stock;
    form.status            = p.status;
    form.is_new            = p.is_new;
    form.is_featured       = p.is_featured;
    form.visible_individually = p.visible_individually;
    form.show_stock_level  = p.show_stock_level;
    form.related_products  = p.related_products || [];
    form.variants          = p.variants || [];
    form.attributes        = p.attributes || {};
    imagePreview.value     = p.image ? '/storage/' + p.image : null;
    if (p.images?.length) galleryPreviews.value = p.images.map(img => '/storage/' + img);
    isFormVisible.value    = true;
    // Load thumbnails for already-selected related products, then init search
    loadSelectedRelated(form.related_products).then(() => initRelatedSearch());
};

const closeForm = () => { isFormVisible.value = false; resetFormState(); };
const resetFormState = () => {
    form.reset(); form.clearErrors();
    form.inventory_source = 'ecommerce';
    form.pos_item_id = null;
    editId.value = null; imagePreview.value = null; galleryPreviews.value = [];
    form.status = true; form.visible_individually = true; form.show_stock_level = true;
    form.related_products = [];
    form.attributes = {};
    selectedRelatedProducts.value = [];
    relatedResults.value = [];
    relatedSearch.value = '';
};

const deleteProduct = (id) => {
    if (!confirm('Delete this product? This cannot be undone.')) return;
    Link; // placeholder — using useForm for delete
    useForm({}).delete(route('admin.products.destroy', id), {
        //onSuccess: () => toast.success('Product deleted.'),
    });
};

// ── Multi-select & Bulk Actions ───────────────────────────
const selectedIds = ref([]);
const bulkProcessing = ref(false);

const isAllSelected = computed(() => {
    const list = props.products.data || [];
    return list.length > 0 && list.every(p => selectedIds.value.includes(p.id));
});

const isIndeterminate = computed(() => {
    const list = props.products.data || [];
    const count = list.filter(p => selectedIds.value.includes(p.id)).length;
    return count > 0 && count < list.length;
});

const toggleSelectAll = () => {
    const list = props.products.data || [];
    if (isAllSelected.value) {
        selectedIds.value = [];
    } else {
        selectedIds.value = list.map(p => p.id);
    }
};

const toggleSelect = (id) => {
    const idx = selectedIds.value.indexOf(id);
    if (idx > -1) {
        selectedIds.value.splice(idx, 1);
    } else {
        selectedIds.value.push(id);
    }
};

const clearSelection = () => {
    selectedIds.value = [];
};

// Bulk Modals state
const showBulkCategoryModal = ref(false);
const targetCategoryId = ref('');

const showBulkPriceModal = ref(false);
const bulkPriceOperation = ref('increase');
const bulkPriceType = ref('percentage');
const bulkPriceAmount = ref('');

const showBulkDeleteModal = ref(false);

const executeBulkAction = (action, extraData = {}) => {
    if (!selectedIds.value.length) return;
    bulkProcessing.value = true;
    router.post(route('admin.products.bulk-action'), {
        ids: selectedIds.value,
        action,
        ...extraData
    }, {
        preserveScroll: true,
        onSuccess: () => {
            selectedIds.value = [];
            showBulkCategoryModal.value = false;
            showBulkPriceModal.value = false;
            showBulkDeleteModal.value = false;
        },
        onFinish: () => {
            bulkProcessing.value = false;
        }
    });
};

const submitBulkCategory = () => {
    if (!targetCategoryId.value) return;
    executeBulkAction('update_category', { category_id: targetCategoryId.value });
};

const submitBulkPrice = () => {
    if (!bulkPriceAmount.value || bulkPriceAmount.value <= 0) return;
    executeBulkAction('update_prices', {
        price_operation: bulkPriceOperation.value,
        price_type: bulkPriceType.value,
        price_amount: bulkPriceAmount.value
    });
};

const submitBulkDelete = () => {
    executeBulkAction('delete');
};
</script>

<template>
    <Head title="Manage Products" />
    <AdminLayout>
        <div class="container mx-auto px-4 py-8 text-black dark:text-white max-w-7xl">

            <!-- ── Header ─────────────────────────────────── -->
            <div class="flex items-center justify-between mb-8" :class="isFormVisible ? 'pb-4 border-b dark:border-gray-700' : ''">
                <div>
                    <h1 class="text-2xl font-bold">{{ isFormVisible ? (isEditing ? 'Edit Product' : 'Create New Product') : 'Catalog Management' }}</h1>
                    <button v-if="isFormVisible" @click="closeForm" class="text-sm text-blue-500 hover:text-blue-700 font-medium mt-1 flex items-center gap-1">
                        ← Back to Catalog
                    </button>
                </div>
                <div class="flex gap-3">
                    <template v-if="isFormVisible">
                        <button @click="closeForm" class="border dark:border-gray-600 font-semibold py-2 px-5 rounded-lg hover:bg-gray-100 dark:hover:bg-gray-700 transition text-sm">Cancel</button>
                        <button @click="save" :disabled="form.processing" class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-7 rounded-lg shadow transition disabled:opacity-50 flex items-center gap-2 text-sm">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                            Save Product
                        </button>
                    </template>
                    <button v-else @click="openCreateForm" class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-5 rounded-lg shadow flex items-center gap-2 text-sm">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                        Add Product
                    </button>
                </div>
            </div>

            <!-- ── Filters ────────────────────────────────── -->
            <div v-if="!isFormVisible" class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border dark:border-gray-700 p-4 mb-6 flex flex-wrap gap-4 items-end">
                <div class="flex-1 min-w-[200px]">
                    <label class="block text-xs font-bold text-gray-500 dark:text-gray-400 uppercase tracking-wide mb-1">Search Products</label>
                    <input v-model="search" type="text" placeholder="Name, SKU, URL Key..." 
                        class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white px-3 py-2 text-sm focus:ring-blue-500 focus:border-blue-500" />
                </div>
                
                <div class="w-full sm:w-auto min-w-[150px]">
                    <label class="block text-xs font-bold text-gray-500 dark:text-gray-400 uppercase tracking-wide mb-1">Category</label>
                    <select v-model="filterCategory" 
                        class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white px-3 py-2 text-sm focus:ring-blue-500 focus:border-blue-500">
                        <option value="">All Categories</option>
                        <template v-for="parent in categories" :key="parent.id">
                            <optgroup v-if="parent.children?.length" :label="parent.name">
                                <option :value="parent.id">{{ parent.name }} (All)</option>
                                <option v-for="sub in parent.children" :key="sub.id" :value="sub.id">↳ {{ sub.name }}</option>
                            </optgroup>
                            <option v-else :value="parent.id">{{ parent.name }}</option>
                        </template>
                    </select>
                </div>

                <div class="w-full sm:w-auto min-w-[150px]">
                    <label class="block text-xs font-bold text-gray-500 dark:text-gray-400 uppercase tracking-wide mb-1">Status</label>
                    <select v-model="filterStatus" 
                        class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white px-3 py-2 text-sm focus:ring-blue-500 focus:border-blue-500">
                        <option value="">All Statuses</option>
                        <option value="active">Active</option>
                        <option value="inactive">Inactive</option>
                    </select>
                </div>

                <div v-if="search || filterCategory || filterStatus">
                    <button @click="clearFilters" class="px-4 py-2 text-sm font-semibold text-gray-600 dark:text-gray-300 bg-gray-100 dark:bg-gray-700 rounded-md hover:bg-gray-200 dark:hover:bg-gray-600 transition h-[38px]">
                        Clear
                    </button>
                </div>
            </div>

            <!-- ── Bulk Action Toolbar ────────────────────── -->
            <transition
                enter-active-class="transition duration-200 ease-out"
                enter-from-class="transform -translate-y-2 opacity-0"
                enter-to-class="transform translate-y-0 opacity-100"
                leave-active-class="transition duration-150 ease-in"
                leave-from-class="transform translate-y-0 opacity-100"
                leave-to-class="transform -translate-y-2 opacity-0"
            >
                <div v-if="!isFormVisible && selectedIds.length > 0" class="mb-4 bg-blue-50/90 dark:bg-blue-950/70 border border-blue-200 dark:border-blue-800 rounded-xl p-3.5 shadow-sm flex flex-wrap items-center justify-between gap-3">
                    <div class="flex items-center gap-2.5">
                        <span class="inline-flex items-center justify-center bg-blue-600 text-white text-xs font-bold px-2.5 py-1 rounded-full shadow-xs">
                            {{ selectedIds.length }}
                        </span>
                        <span class="text-sm font-semibold text-blue-900 dark:text-blue-100">
                            {{ selectedIds.length === 1 ? '1 product selected' : `${selectedIds.length} products selected` }}
                        </span>
                        <button
                            type="button"
                            @click="clearSelection"
                            class="text-xs text-blue-600 dark:text-blue-400 hover:text-blue-800 dark:hover:text-blue-200 underline cursor-pointer ml-1"
                        >
                            Deselect all
                        </button>
                    </div>

                    <div class="flex flex-wrap items-center gap-2">
                        <!-- Move Category -->
                        <button
                            type="button"
                            @click="showBulkCategoryModal = true"
                            :disabled="bulkProcessing"
                            class="px-3 py-1.5 bg-white dark:bg-gray-800 hover:bg-gray-50 dark:hover:bg-gray-700 text-gray-700 dark:text-gray-200 rounded-lg text-xs font-semibold border border-gray-300 dark:border-gray-600 transition flex items-center gap-1.5 shadow-xs cursor-pointer disabled:opacity-50"
                        >
                            <svg class="w-3.5 h-3.5 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z"/></svg>
                            Change Category
                        </button>

                        <!-- Make Active -->
                        <button
                            type="button"
                            @click="executeBulkAction('update_status', { status: true })"
                            :disabled="bulkProcessing"
                            class="px-3 py-1.5 bg-white dark:bg-gray-800 hover:bg-green-50 dark:hover:bg-green-950/40 text-green-700 dark:text-green-400 rounded-lg text-xs font-semibold border border-green-200 dark:border-green-800 transition flex items-center gap-1.5 shadow-xs cursor-pointer disabled:opacity-50"
                        >
                            <span class="w-2 h-2 rounded-full bg-green-500"></span>
                            Make Active
                        </button>

                        <!-- Make Inactive -->
                        <button
                            type="button"
                            @click="executeBulkAction('update_status', { status: false })"
                            :disabled="bulkProcessing"
                            class="px-3 py-1.5 bg-white dark:bg-gray-800 hover:bg-amber-50 dark:hover:bg-amber-950/40 text-amber-700 dark:text-amber-400 rounded-lg text-xs font-semibold border border-amber-200 dark:border-amber-800 transition flex items-center gap-1.5 shadow-xs cursor-pointer disabled:opacity-50"
                        >
                            <span class="w-2 h-2 rounded-full bg-gray-400"></span>
                            Make Inactive
                        </button>

                        <!-- Featured Toggle -->
                        <button
                            type="button"
                            @click="executeBulkAction('update_featured', { is_featured: true })"
                            :disabled="bulkProcessing"
                            class="px-3 py-1.5 bg-white dark:bg-gray-800 hover:bg-yellow-50 dark:hover:bg-yellow-950/40 text-yellow-700 dark:text-yellow-400 rounded-lg text-xs font-semibold border border-yellow-200 dark:border-yellow-800 transition flex items-center gap-1.5 shadow-xs cursor-pointer disabled:opacity-50"
                            title="Mark as Featured Products"
                        >
                            ★ Feature
                        </button>

                        <!-- New Tag Toggle -->
                        <button
                            type="button"
                            @click="executeBulkAction('update_new', { is_new: true })"
                            :disabled="bulkProcessing"
                            class="px-3 py-1.5 bg-white dark:bg-gray-800 hover:bg-indigo-50 dark:hover:bg-indigo-950/40 text-indigo-700 dark:text-indigo-400 rounded-lg text-xs font-semibold border border-indigo-200 dark:border-indigo-800 transition flex items-center gap-1.5 shadow-xs cursor-pointer disabled:opacity-50"
                            title="Mark with 'NEW' badge"
                        >
                            ✦ Tag New
                        </button>

                        <!-- Adjust Prices -->
                        <button
                            type="button"
                            @click="showBulkPriceModal = true"
                            :disabled="bulkProcessing"
                            class="px-3 py-1.5 bg-white dark:bg-gray-800 hover:bg-purple-50 dark:hover:bg-purple-950/40 text-purple-700 dark:text-purple-400 rounded-lg text-xs font-semibold border border-purple-200 dark:border-purple-800 transition flex items-center gap-1.5 shadow-xs cursor-pointer disabled:opacity-50"
                        >
                            ₦ Adjust Prices
                        </button>

                        <!-- Delete Selected -->
                        <button
                            type="button"
                            @click="showBulkDeleteModal = true"
                            :disabled="bulkProcessing"
                            class="px-3 py-1.5 bg-red-600 hover:bg-red-700 text-white rounded-lg text-xs font-semibold transition flex items-center gap-1.5 shadow-xs cursor-pointer disabled:opacity-50"
                        >
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                            Delete Selected
                        </button>
                    </div>
                </div>
            </transition>

            <!-- ── Product List ───────────────────────────── -->
            <div v-if="!isFormVisible" class="bg-white dark:bg-gray-800 rounded-xl shadow border dark:border-gray-700 overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="w-full text-left min-w-[800px]">
                        <thead class="bg-gray-50 dark:bg-gray-900/50 border-b dark:border-gray-700 text-xs text-gray-500 uppercase">
                            <tr>
                                <th class="w-10 px-4 py-3 text-center">
                                    <input
                                        type="checkbox"
                                        :checked="isAllSelected"
                                        :indeterminate.prop="isIndeterminate"
                                        @change="toggleSelectAll"
                                        class="w-4 h-4 rounded border-gray-300 dark:border-gray-600 text-blue-600 focus:ring-blue-500 bg-white dark:bg-gray-800 cursor-pointer"
                                        title="Select all products on this page"
                                    />
                                </th>
                                <th class="px-5 py-3 font-semibold">Product</th>
                                <th class="px-5 py-3 font-semibold">Category</th>
                                <th class="px-5 py-3 font-semibold">Pricing</th>
                                <th class="px-5 py-3 font-semibold">Stock</th>
                                <th class="px-5 py-3 font-semibold">Flags</th>
                                <th class="px-5 py-3 font-semibold text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                            <tr v-for="p in products.data" :key="p.id" 
                                class="hover:bg-gray-50 dark:hover:bg-gray-700/60 transition"
                                :class="selectedIds.includes(p.id) ? 'bg-blue-50/70 dark:bg-blue-900/25' : ''">
                                <td class="px-4 py-3 text-center" @click.stop>
                                    <input
                                        type="checkbox"
                                        :checked="selectedIds.includes(p.id)"
                                        @change="toggleSelect(p.id)"
                                        class="w-4 h-4 rounded border-gray-300 dark:border-gray-600 text-blue-600 focus:ring-blue-500 bg-white dark:bg-gray-800 cursor-pointer"
                                    />
                                </td>
                                <td class="px-5 py-3">
                                    <div class="flex items-center gap-3">
                                        <img :src="p.image ? '/storage/'+p.image : 'https://placehold.co/60x60'" class="w-11 h-11 rounded-lg object-cover border dark:border-gray-600 flex-shrink-0"/>
                                        <div>
                                            <div class="flex items-center gap-1.5">
                                                <span class="font-semibold line-clamp-1 text-gray-900 dark:text-white">{{ p.name }}</span>
                                                <span v-if="p.inventory_source === 'pos'" class="text-[10px] font-bold px-1.5 py-0.2 rounded bg-indigo-100 text-indigo-700 dark:bg-indigo-900/40 dark:text-indigo-300">
                                                    POS #{{ p.pos_item_id }}
                                                </span>
                                                <span v-if="p.pos_sync_status === 'missing_pos_item'" class="text-[10px] font-bold px-1.5 py-0.2 rounded bg-rose-100 text-rose-700">
                                                    Missing in POS
                                                </span>
                                            </div>
                                            <div class="text-xs text-gray-400 font-mono mt-0.5">{{ p.url_key || p.sku || '—' }}</div>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-5 py-3">
                                    <span class="text-xs bg-blue-100 dark:bg-blue-900/30 text-blue-700 dark:text-blue-300 px-2 py-0.5 rounded-full font-medium">{{ p.category?.name }}</span>
                                </td>
                                <td class="px-5 py-3">
                                    <div class="font-bold text-gray-900 dark:text-white">₦{{ parseFloat(p.price).toLocaleString() }}</div>
                                    <div v-if="p.discount_price" class="text-xs text-red-400 line-through">₦{{ parseFloat(p.discount_price).toLocaleString() }}</div>
                                    <div v-if="p.special_price" class="text-xs text-orange-500 font-semibold">Special: ₦{{ parseFloat(p.special_price).toLocaleString() }}</div>
                                </td>
                                <td class="px-5 py-3">
                                    <span class="text-sm font-bold flex items-center gap-1.5"
                                        :class="p.stock > 10 ? 'text-green-600' : (p.stock > 0 ? 'text-yellow-600' : 'text-red-600')">
                                        <span class="w-2 h-2 rounded-full inline-block"
                                            :class="p.stock > 10 ? 'bg-green-500' : (p.stock > 0 ? 'bg-yellow-500' : 'bg-red-500')"></span>
                                        {{ p.stock > 0 ? `${p.stock} in stock` : 'Out of stock' }}
                                    </span>
                                </td>
                                <td class="px-5 py-3">
                                    <div class="flex flex-wrap gap-1">
                                        <span v-if="p.is_new" class="text-[10px] bg-green-100 text-green-700 px-1.5 py-0.5 rounded font-bold">NEW</span>
                                        <span v-if="p.is_featured" class="text-[10px] bg-yellow-100 text-yellow-700 px-1.5 py-0.5 rounded font-bold">★ FEAT</span>
                                        <span class="text-[10px] px-1.5 py-0.5 rounded font-bold"
                                            :class="p.status ? 'bg-blue-100 text-blue-700' : 'bg-gray-100 text-gray-500'">
                                            {{ p.status ? 'Active' : 'Inactive' }}
                                        </span>
                                    </div>
                                </td>
                                <td class="px-5 py-3 text-right">
                                    <button @click="openEditForm(p)" class="text-blue-500 hover:text-blue-700 font-medium text-sm mr-3">Edit</button>
                                    <button @click="deleteProduct(p.id)" class="text-red-500 hover:text-red-700 font-medium text-sm">Delete</button>
                                </td>
                            </tr>
                            <tr v-if="!products.data?.length">
                                <td colspan="7" class="px-6 py-12 text-center text-gray-400">No products yet. Click "Add Product" to get started.</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <div class="px-5 py-3 border-t dark:border-gray-700">
                    <Pagination v-if="products.data?.length" :paginator="products"/>
                </div>
            </div>

            <!-- ── Product Form ───────────────────────────── -->
            <div v-if="isFormVisible" class="grid grid-cols-1 lg:grid-cols-3 gap-8 pb-20">

                <!-- Left: Main content -->
                <div class="lg:col-span-2 space-y-8">

                    <!-- General Information -->
                    <div class="bg-white dark:bg-gray-800 p-6 rounded-xl shadow-sm border dark:border-gray-700">
                        <h3 class="text-base font-bold mb-5 flex items-center gap-2">
                            <span class="w-6 h-6 bg-blue-100 dark:bg-blue-900/40 text-blue-600 rounded flex items-center justify-center text-sm">📋</span>
                            General Information
                        </h3>
                        <div class="space-y-4">
                            <div>
                                <label class="block text-sm font-semibold mb-1">Product Title *</label>
                                <input v-model="form.name" type="text" required placeholder="e.g. iPhone 16 Pro Max"
                                    class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 focus:ring-blue-500 shadow-sm"/>
                                <p v-if="form.errors.name" class="text-red-500 text-xs mt-1">{{ form.errors.name }}</p>
                            </div>

                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-sm font-semibold mb-1">Category *</label>
                                    <select v-model="form.category_id" required
                                        class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 focus:ring-blue-500 shadow-sm">
                                        <option value="" disabled>— Select a category —</option>
                                        <template v-for="parent in categories" :key="parent.id">
                                            <!-- If parent has subcategories, use optgroup -->
                                            <optgroup v-if="parent.children?.length" :label="'📁 ' + parent.name">
                                                <!-- Parent itself is selectable (products with no specific subcat) -->
                                                <option :value="parent.id">{{ parent.name }} (General)</option>
                                                <option v-for="sub in parent.children" :key="sub.id" :value="sub.id">
                                                    ↳ {{ sub.name }}
                                                </option>
                                            </optgroup>
                                            <!-- Parent with no subcategories: just a plain option -->
                                            <option v-else :value="parent.id">📁 {{ parent.name }}</option>
                                        </template>
                                    </select>
                                    <p v-if="form.errors.category_id" class="text-red-500 text-xs mt-1">{{ form.errors.category_id }}</p>
                                </div>
                                <div>
                                    <label class="block text-sm font-semibold mb-1">
                                        URL Key *
                                        <span class="font-normal text-gray-400 ml-1">— used for direct product link</span>
                                    </label>
                                    <div class="relative">
                                        <span class="absolute inset-y-0 left-3 flex items-center text-gray-400 text-xs pointer-events-none">/product/</span>
                                        <input v-model="form.url_key" type="text" required placeholder="iphone-16-pro-max"
                                            class="w-full pl-16 rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 focus:ring-blue-500 shadow-sm font-mono text-sm"/>
                                    </div>
                                    <p v-if="form.errors.url_key" class="text-red-500 text-xs mt-1">{{ form.errors.url_key }}</p>
                                </div>
                            </div>

                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-sm font-semibold mb-1">Product Number <span class="font-normal text-gray-400">(optional)</span></label>
                                    <input v-model="form.product_number" type="text" placeholder="e.g. PRD-001"
                                        class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 focus:ring-blue-500 shadow-sm font-mono text-sm"/>
                                    <p v-if="form.errors.product_number" class="text-red-500 text-xs mt-1">{{ form.errors.product_number }}</p>
                                </div>
                                <div>
                                    <label class="block text-sm font-semibold mb-1">SKU <span class="font-normal text-gray-400">(optional)</span></label>
                                    <input v-model="form.sku" type="text" placeholder="e.g. APPLE-IP16-PRO"
                                        class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 focus:ring-blue-500 shadow-sm font-mono text-sm"/>
                                    <p v-if="form.errors.sku" class="text-red-500 text-xs mt-1">{{ form.errors.sku }}</p>
                                </div>
                            </div>

                            <div>
                                <label class="block text-sm font-semibold mb-1">Short Description <span class="font-normal text-gray-400">(optional)</span></label>
                                <textarea v-model="form.short_description" rows="2" placeholder="Brief summary of the product..."
                                    class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 focus:ring-blue-500 shadow-sm resize-none"></textarea>
                            </div>

                            <div>
                                <label class="block text-sm font-semibold mb-1">Full Description</label>
                                <div class="bg-white dark:bg-gray-700 rounded-lg overflow-hidden border border-gray-300 dark:border-gray-600">
                                    <QuillEditor v-model:content="form.description" contentType="html" theme="snow" class="min-h-[200px]" />
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Filterable Category Attributes -->
                    <div v-if="categoryAttributes.length > 0" class="bg-white dark:bg-gray-800 p-6 rounded-xl shadow-sm border dark:border-gray-700 transition-all">
                        <h3 class="text-base font-bold mb-5 flex items-center gap-2">
                            <span class="w-6 h-6 bg-yellow-100 dark:bg-yellow-900/40 text-yellow-600 rounded flex items-center justify-center text-sm">✨</span>
                            Category Attributes
                        </h3>
                        <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-5">
                            <div v-for="attr in categoryAttributes" :key="attr.id">
                                <label class="block text-sm font-semibold mb-1">{{ attr.name }}</label>
                                
                                <!-- Select / Dropdown -->
                                <select v-if="attr.type === 'select'" v-model="form.attributes[attr.id]"
                                    class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 focus:ring-yellow-500 shadow-sm text-sm">
                                    <option value="">Choose an option...</option>
                                    <option v-for="opt in attr.options" :key="opt" :value="opt">{{ opt }}</option>
                                </select>
                                
                                <!-- Color Picker (Swatches) -->
                                <div v-else-if="attr.type === 'color'" class="flex flex-wrap gap-2">
                                    <button v-for="opt in attr.options" :key="opt"
                                        @click.prevent="form.attributes[attr.id] = (form.attributes[attr.id] === opt ? '' : opt)"
                                        class="w-7 h-7 rounded-full border-2 transition-transform duration-200"
                                        :class="form.attributes[attr.id] === opt ? 'scale-110 border-blue-500 shadow-md ring-2 ring-blue-200 dark:ring-blue-900' : 'border-gray-200 dark:border-gray-600 hover:scale-110'"
                                        :style="{ backgroundColor: opt.startsWith('#') ? opt : opt.toLowerCase().replace(/\s+/g, '') }"
                                        :title="opt">
                                    </button>
                                </div>
                                
                                <!-- Default Text Wrapper -->
                                <input v-else type="text" v-model="form.attributes[attr.id]" :placeholder="`Enter ${attr.name}...`"
                                    class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 focus:ring-yellow-500 shadow-sm text-sm" />
                            </div>
                        </div>
                    </div>

                    <!-- Pricing & Inventory -->
                    <div class="bg-white dark:bg-gray-800 p-6 rounded-xl shadow-sm border dark:border-gray-700">
                        <h3 class="text-base font-bold mb-4 flex items-center gap-2">
                            <span class="w-6 h-6 bg-green-100 dark:bg-green-900/40 text-green-600 rounded flex items-center justify-center text-sm">💰</span>
                            Pricing & Inventory
                        </h3>

                        <!-- Inventory Source Choice (Section 140D) -->
                        <div class="mb-5 p-4 rounded-xl bg-gray-50 dark:bg-gray-900/50 border border-gray-200 dark:border-gray-700">
                            <label class="block text-xs font-bold uppercase tracking-wider text-gray-500 mb-2">Inventory Source</label>
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                                <label
                                    class="flex items-center p-3 rounded-lg border cursor-pointer transition"
                                    :class="form.inventory_source === 'pos' ? 'border-indigo-600 bg-indigo-50/50 dark:bg-indigo-900/20' : 'border-gray-200 dark:border-gray-700'"
                                >
                                    <input type="radio" value="pos" v-model="form.inventory_source" class="text-indigo-600 focus:ring-indigo-500" />
                                    <div class="ml-3">
                                        <div class="text-sm font-semibold text-gray-900 dark:text-white">Connected POS Item</div>
                                        <div class="text-xs text-gray-500">Physical stock & standard price synced from POS</div>
                                    </div>
                                </label>
                                <label
                                    class="flex items-center p-3 rounded-lg border cursor-pointer transition"
                                    :class="form.inventory_source === 'ecommerce' ? 'border-indigo-600 bg-indigo-50/50 dark:bg-indigo-900/20' : 'border-gray-200 dark:border-gray-700'"
                                >
                                    <input type="radio" value="ecommerce" v-model="form.inventory_source" class="text-indigo-600 focus:ring-indigo-500" />
                                    <div class="ml-3">
                                        <div class="text-sm font-semibold text-gray-900 dark:text-white">Ecommerce Only Item</div>
                                        <div class="text-xs text-gray-500">Independent local stock & pricing</div>
                                    </div>
                                </label>
                            </div>

                            <!-- POS Item Picker if POS is selected -->
                            <div v-if="form.inventory_source === 'pos'" class="mt-4 pt-3 border-t border-gray-200 dark:border-gray-700">
                                <label class="block text-sm font-semibold mb-1">Select Synced POS Item *</label>
                                <select
                                    :value="form.pos_item_id"
                                    @change="onPosItemSelect"
                                    class="w-full text-sm py-2 px-3 border border-gray-300 dark:border-gray-600 rounded-lg dark:bg-gray-800"
                                >
                                    <option :value="null">-- Choose an item from connected POS branch --</option>
                                    <option v-for="item in synced_pos_items" :key="item.pos_item_id" :value="item.pos_item_id">
                                        {{ item.pos_item_name }} (Stock: {{ Math.round(Number(item.effective_availability || 0)) }} {{ item.base_unit_name }} | ₦{{ Number(item.standard_price).toLocaleString() }})
                                    </option>
                                </select>
                                <p v-if="form.pos_item_id" class="text-xs text-indigo-600 dark:text-indigo-400 mt-1.5 flex items-center">
                                    <svg class="w-3.5 h-3.5 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                    Linked to POS item #{{ form.pos_item_id }}. Base price & stock are controlled by POS.
                                </p>
                            </div>
                        </div>

                        <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-4">
                            <div>
                                <label class="block text-sm font-semibold mb-1">
                                    Base Price (₦) *
                                    <span v-if="form.inventory_source === 'pos'" class="text-[10px] text-indigo-600 font-bold ml-1">(POS)</span>
                                </label>
                                <input
                                    v-model="form.price"
                                    type="number"
                                    step="0.01"
                                    min="0"
                                    required
                                    placeholder="0.00"
                                    :readonly="form.inventory_source === 'pos'"
                                    :class="form.inventory_source === 'pos' ? 'bg-gray-100 dark:bg-gray-800/80 cursor-not-allowed text-gray-500' : ''"
                                    class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 focus:ring-blue-500 shadow-sm"
                                />
                                <p v-if="form.errors.price" class="text-red-500 text-xs mt-1">{{ form.errors.price }}</p>
                            </div>
                            <div>
                                <label class="block text-sm font-semibold mb-1">Cost Price (₦)</label>
                                <input v-model="form.cost_price" type="number" step="0.01" min="0" placeholder="Internal cost"
                                    class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 focus:ring-blue-500 shadow-sm"/>
                            </div>
                            <div>
                                <label class="block text-sm font-semibold mb-1">Discount Price (₦)</label>
                                <input v-model="form.discount_price" type="number" step="0.01" min="0" placeholder="Optional"
                                    class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 focus:ring-blue-500 shadow-sm"/>
                            </div>
                            <div>
                                <label class="block text-sm font-semibold mb-1">
                                    Stock Qty *
                                    <span v-if="form.inventory_source === 'pos'" class="text-[10px] text-indigo-600 font-bold ml-1">(POS)</span>
                                </label>
                                <input
                                    v-model="form.stock"
                                    type="number"
                                    min="0"
                                    required
                                    placeholder="0"
                                    :readonly="form.inventory_source === 'pos'"
                                    :class="form.inventory_source === 'pos' ? 'bg-gray-100 dark:bg-gray-800/80 cursor-not-allowed text-gray-500' : ''"
                                    class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 focus:ring-blue-500 shadow-sm"
                                />
                                <p v-if="form.errors.stock" class="text-red-500 text-xs mt-1">{{ form.errors.stock }}</p>
                            </div>
                        </div>

                        <!-- Special Price Section -->
                        <div class="bg-orange-50 dark:bg-orange-900/10 border border-orange-200 dark:border-orange-800 rounded-xl p-4">
                            <p class="text-sm font-bold text-orange-700 dark:text-orange-400 mb-3">⚡ Special / Flash Price (time-limited)</p>
                            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                                <div>
                                    <label class="block text-xs font-semibold mb-1 text-gray-600 dark:text-gray-400">Special Price (₦)</label>
                                    <input v-model="form.special_price" type="number" step="0.01" min="0" placeholder="e.g. 149000"
                                        class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 focus:ring-orange-400 shadow-sm text-sm"/>
                                </div>
                                <div>
                                    <label class="block text-xs font-semibold mb-1 text-gray-600 dark:text-gray-400">Active From</label>
                                    <input v-model="form.special_price_from" type="date"
                                        class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 focus:ring-orange-400 shadow-sm text-sm"/>
                                </div>
                                <div>
                                    <label class="block text-xs font-semibold mb-1 text-gray-600 dark:text-gray-400">Active Until</label>
                                    <input v-model="form.special_price_to" type="date"
                                        class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 focus:ring-orange-400 shadow-sm text-sm"/>
                                </div>
                            </div>
                            <p class="text-xs text-orange-600 dark:text-orange-400 mt-2 opacity-75">Leave dates empty for an always-active special price.</p>
                        </div>
                    </div>

                    <!-- Product Variants -->
                    <div class="bg-white dark:bg-gray-800 p-6 rounded-xl shadow-sm border dark:border-gray-700">
                        <div class="flex items-center justify-between mb-4">
                            <h3 class="text-base font-bold flex items-center gap-2">
                                <span class="w-6 h-6 bg-purple-100 dark:bg-purple-900/40 text-purple-600 rounded flex items-center justify-center text-sm">🎛️</span>
                                Product Variants
                            </h3>
                            <button @click.prevent="addVariant"
                                class="text-sm bg-indigo-50 hover:bg-indigo-100 dark:bg-indigo-900/30 dark:hover:bg-indigo-800/50 text-indigo-700 dark:text-indigo-300 px-3 py-1.5 rounded-lg font-semibold transition flex items-center gap-1">
                                + Add Variant
                            </button>
                        </div>
                        <p class="text-xs text-gray-500 dark:text-gray-400 mb-4">Add variations (Color, Size, Wattage…) with individual prices and stock counts.</p>
                        <div class="space-y-3">
                            <div v-for="(v, i) in form.variants" :key="i"
                                class="bg-gray-50 dark:bg-gray-900/50 p-4 rounded-lg border dark:border-gray-700 flex flex-wrap lg:flex-nowrap items-end gap-3 relative group">
                                <button @click.prevent="removeVariant(i)"
                                    class="absolute -top-2 -right-2 bg-red-100 text-red-600 rounded-full p-1 shadow opacity-0 group-hover:opacity-100 transition">
                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                                </button>
                                <div class="w-full lg:w-1/2">
                                    <label class="block text-xs font-semibold text-gray-500 mb-1">Variant Name</label>
                                    <input v-model="v.name" type="text" placeholder="e.g. Red / 60W" class="w-full text-sm rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700"/>
                                </div>
                                <div class="w-full sm:w-1/2 lg:w-1/4">
                                    <label class="block text-xs font-semibold text-gray-500 mb-1">Price (₦)</label>
                                    <input v-model="v.price" type="number" step="0.01" class="w-full text-sm rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700"/>
                                </div>
                                <div class="w-full sm:w-1/2 lg:w-1/4">
                                    <label class="block text-xs font-semibold text-gray-500 mb-1">Stock</label>
                                    <input v-model="v.stock" type="number" class="w-full text-sm rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700"/>
                                </div>
                            </div>
                            <div v-if="!form.variants.length" class="text-center py-6 border-2 border-dashed border-gray-200 dark:border-gray-700 rounded-xl text-gray-400 text-sm">
                                No variants. Click "+ Add Variant" above to get started.
                            </div>
                        </div>
                    </div>

                    <!-- Related Products Picker -->
                    <div class="bg-white dark:bg-gray-800 p-6 rounded-xl shadow-sm border dark:border-gray-700">
                        <h3 class="text-base font-bold mb-1 flex items-center gap-2">
                            <span class="w-6 h-6 bg-cyan-100 dark:bg-cyan-900/40 text-cyan-600 rounded flex items-center justify-center text-sm">🔗</span>
                            Related Products
                        </h3>
                        <p class="text-xs text-gray-500 dark:text-gray-400 mb-4">
                            Hand-pick products for the "You may also like" section. If none selected, same-category products are shown automatically.
                        </p>

                        <!-- Selected chips -->
                        <div v-if="selectedRelatedProducts.length" class="flex flex-wrap gap-2 mb-4">
                            <div v-for="rp in selectedRelatedProducts" :key="rp.id"
                                class="flex items-center gap-1.5 bg-cyan-50 dark:bg-cyan-900/30 text-cyan-800 dark:text-cyan-200 text-xs font-medium px-2 py-1 rounded-full border border-cyan-200 dark:border-cyan-700">
                                <img v-if="rp.image" :src="'/storage/' + rp.image" class="w-4 h-4 rounded-full object-cover"/>
                                {{ rp.name.length > 25 ? rp.name.substring(0,25) + '…' : rp.name }}
                                <button @click.prevent="removeRelated(rp.id)" class="text-cyan-600 hover:text-red-500 ml-1 font-bold leading-none">&times;</button>
                            </div>
                        </div>
                        <p v-else class="text-xs text-gray-400 italic mb-4">No products selected — using same-category fallback.</p>

                        <!-- Search -->
                        <input v-model="relatedSearch" @input="searchRelated" type="text"
                            placeholder="Search products by name…"
                            class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 focus:ring-cyan-400 shadow-sm text-sm mb-3"/>

                        <!-- Scrollable product list (AJAX results) -->
                        <div class="max-h-60 overflow-y-auto space-y-1 pr-1">
                            <div v-if="relatedLoading" class="flex items-center justify-center py-4 text-gray-400 text-sm gap-2">
                                <svg class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/>
                                </svg>
                                Searching…
                            </div>
                            <div v-else-if="!relatedResults.length" class="text-center py-4 text-gray-400 text-sm">No products found.</div>
                            <button v-else v-for="rp in relatedResults" :key="rp.id" type="button"
                                @click="toggleRelated(rp)"
                                :class="[
                                    'w-full flex items-center gap-3 px-3 py-2 rounded-lg text-sm text-left transition',
                                    isRelated(rp.id)
                                        ? 'bg-cyan-50 dark:bg-cyan-900/30 border border-cyan-300 dark:border-cyan-700 text-cyan-800 dark:text-cyan-200'
                                        : 'hover:bg-gray-50 dark:hover:bg-gray-700 border border-transparent text-gray-700 dark:text-gray-300'
                                ]">
                                <img :src="rp.image ? '/storage/' + rp.image : 'https://placehold.co/32x32?text=P'"
                                    class="w-8 h-8 rounded object-cover flex-shrink-0 border dark:border-gray-600"/>
                                <span class="flex-1 line-clamp-1">{{ rp.name }}</span>
                                <svg v-if="isRelated(rp.id)" class="w-4 h-4 text-cyan-500 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/>
                                </svg>
                            </button>
                        </div>
                    </div>
                </div>

                <!-- ── Right Column ─────────────────────── -->
                <div class="lg:col-span-1 space-y-6">

                    <!-- Publish / Flags Panel -->
                    <div class="bg-white dark:bg-gray-800 p-6 rounded-xl shadow-sm border dark:border-gray-700">
                        <h3 class="text-base font-bold mb-4 flex items-center gap-2">
                            <span class="w-6 h-6 bg-yellow-100 dark:bg-yellow-900/40 text-yellow-600 rounded flex items-center justify-center text-sm">🏷️</span>
                            Product Labels & Visibility
                        </h3>

                        <div class="space-y-3">
                            <!-- Status -->
                            <div class="flex items-center justify-between py-2.5 border-b dark:border-gray-700">
                                <div>
                                    <p class="text-sm font-semibold">Status</p>
                                    <p class="text-xs text-gray-400">Active products are visible in the store</p>
                                </div>
                                <button type="button" @click="form.status = !form.status"
                                    class="relative inline-flex h-6 w-11 items-center rounded-full transition"
                                    :class="form.status ? 'bg-blue-600' : 'bg-gray-200 dark:bg-gray-600'">
                                    <span class="inline-block h-4 w-4 transform rounded-full bg-white transition shadow"
                                        :class="form.status ? 'translate-x-6' : 'translate-x-1'"/>
                                </button>
                            </div>

                            <!-- New Badge -->
                            <div class="flex items-center justify-between py-2.5 border-b dark:border-gray-700">
                                <div>
                                    <p class="text-sm font-semibold">Mark as New</p>
                                    <p class="text-xs text-gray-400">Shows a "New" badge on the product</p>
                                </div>
                                <button type="button" @click="form.is_new = !form.is_new"
                                    class="relative inline-flex h-6 w-11 items-center rounded-full transition"
                                    :class="form.is_new ? 'bg-green-500' : 'bg-gray-200 dark:bg-gray-600'">
                                    <span class="inline-block h-4 w-4 transform rounded-full bg-white transition shadow"
                                        :class="form.is_new ? 'translate-x-6' : 'translate-x-1'"/>
                                </button>
                            </div>

                            <!-- Featured -->
                            <div class="flex items-center justify-between py-2.5 border-b dark:border-gray-700">
                                <div>
                                    <p class="text-sm font-semibold">Featured Product</p>
                                    <p class="text-xs text-gray-400">Shows on homepage featured section</p>
                                </div>
                                <button type="button" @click="form.is_featured = !form.is_featured"
                                    class="relative inline-flex h-6 w-11 items-center rounded-full transition"
                                    :class="form.is_featured ? 'bg-yellow-500' : 'bg-gray-200 dark:bg-gray-600'">
                                    <span class="inline-block h-4 w-4 transform rounded-full bg-white transition shadow"
                                        :class="form.is_featured ? 'translate-x-6' : 'translate-x-1'"/>
                                </button>
                            </div>

                            <!-- Visible Individually -->
                            <div class="flex items-center justify-between py-2.5 border-b dark:border-gray-700">
                                <div>
                                    <p class="text-sm font-semibold">Visible Individually</p>
                                    <p class="text-xs text-gray-400">Shows in category listings & search</p>
                                </div>
                                <button type="button" @click="form.visible_individually = !form.visible_individually"
                                    class="relative inline-flex h-6 w-11 items-center rounded-full transition"
                                    :class="form.visible_individually ? 'bg-blue-600' : 'bg-gray-200 dark:bg-gray-600'">
                                    <span class="inline-block h-4 w-4 transform rounded-full bg-white transition shadow"
                                        :class="form.visible_individually ? 'translate-x-6' : 'translate-x-1'"/>
                                </button>
                            </div>

                            <!-- Show Stock Level -->
                            <div class="flex items-center justify-between py-2.5">
                                <div>
                                    <p class="text-sm font-semibold">Show Stock Level</p>
                                    <p class="text-xs text-gray-400">Display qty available on storefront</p>
                                </div>
                                <button type="button" @click="form.show_stock_level = !form.show_stock_level"
                                    class="relative inline-flex h-6 w-11 items-center rounded-full transition"
                                    :class="form.show_stock_level ? 'bg-teal-500' : 'bg-gray-200 dark:bg-gray-600'">
                                    <span class="inline-block h-4 w-4 transform rounded-full bg-white transition shadow"
                                        :class="form.show_stock_level ? 'translate-x-6' : 'translate-x-1'"/>
                                </button>
                            </div>
                        </div>

                        <!-- Stock Status Summary -->
                        <div class="mt-4 pt-4 border-t dark:border-gray-700">
                            <p class="text-xs font-semibold text-gray-500 mb-1">Stock Status</p>
                            <div class="flex items-center gap-2">
                                <span class="w-2.5 h-2.5 rounded-full flex-shrink-0"
                                    :class="form.stock > 10 ? 'bg-green-500' : (form.stock > 0 ? 'bg-yellow-500' : 'bg-red-500')"></span>
                                <span class="text-sm font-bold"
                                    :class="form.stock > 10 ? 'text-green-600' : (form.stock > 0 ? 'text-yellow-600' : 'text-red-600')">
                                    {{ form.stock > 0 ? `In Stock (${form.stock})` : 'Out of Stock' }}
                                </span>
                            </div>
                        </div>
                    </div>

                    <!-- Media Panel -->
                    <div class="bg-white dark:bg-gray-800 p-6 rounded-xl shadow-sm border dark:border-gray-700">
                        <h3 class="text-base font-bold mb-4 flex items-center gap-2">
                            <span class="w-6 h-6 bg-pink-100 dark:bg-pink-900/40 text-pink-500 rounded flex items-center justify-center text-sm">🖼️</span>
                            Product Media
                        </h3>

                        <!-- Thumbnail -->
                        <div class="mb-5">
                            <label class="block text-sm font-semibold mb-2">Thumbnail Image</label>
                            <label class="relative block w-full aspect-square bg-gray-50 dark:bg-gray-900 border-2 border-dashed border-gray-300 dark:border-gray-600 rounded-xl hover:bg-gray-100 dark:hover:bg-gray-800 transition cursor-pointer overflow-hidden group">
                                <input type="file" @change="handleImageUpload" accept="image/*" class="absolute inset-0 opacity-0 cursor-pointer z-10"/>
                                <img v-if="imagePreview" :src="imagePreview" class="absolute inset-0 w-full h-full object-cover"/>
                                <div v-else class="absolute inset-0 flex flex-col items-center justify-center text-gray-400 group-hover:text-blue-500 transition">
                                    <svg class="w-9 h-9 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"/></svg>
                                    <span class="text-sm font-medium">Upload Thumbnail</span>
                                </div>
                                <div v-if="imagePreview" class="absolute bottom-0 inset-x-0 bg-black/50 text-white text-xs text-center py-1.5 opacity-0 group-hover:opacity-100 transition">Change</div>
                            </label>
                        </div>

                        <!-- Gallery -->
                        <div>
                            <label class="block text-sm font-semibold mb-2">Gallery Images <span class="font-normal text-gray-400">(max 10)</span></label>
                            <input type="file" multiple @change="handleGalleryUpload" accept="image/*"
                                class="w-full text-sm file:mr-3 file:py-1.5 file:px-3 file:rounded-lg file:border-0 file:font-semibold file:bg-gray-100 file:text-gray-700 hover:file:bg-gray-200 dark:file:bg-gray-700 dark:file:text-white cursor-pointer"/>
                            <div v-if="galleryPreviews.length" class="mt-3 grid grid-cols-4 gap-1.5">
                                <div v-for="(prev, i) in galleryPreviews" :key="i"
                                    class="aspect-square rounded-lg overflow-hidden border dark:border-gray-600 bg-gray-100 dark:bg-gray-700">
                                    <img :src="prev" class="w-full h-full object-cover"/>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- ── Bulk Change Category Modal ──────────────── -->
            <div v-if="showBulkCategoryModal" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/60 backdrop-blur-xs">
                <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-2xl max-w-md w-full p-6 border dark:border-gray-700 space-y-4">
                    <div class="flex items-center justify-between pb-3 border-b dark:border-gray-700">
                        <div class="flex items-center gap-2">
                            <span class="text-xl">📁</span>
                            <h3 class="font-bold text-lg text-gray-900 dark:text-white">Change Category</h3>
                        </div>
                        <button @click="showBulkCategoryModal = false" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-200 text-lg">✕</button>
                    </div>
                    <p class="text-sm text-gray-600 dark:text-gray-300">
                        Assign <span class="font-bold text-blue-600 dark:text-blue-400">{{ selectedIds.length }}</span> selected product(s) to a new category:
                    </p>
                    <div>
                        <label class="block text-xs font-bold text-gray-500 dark:text-gray-400 uppercase tracking-wide mb-1.5">Target Category</label>
                        <select v-model="targetCategoryId" class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white px-3 py-2 text-sm focus:ring-blue-500 focus:border-blue-500">
                            <option value="" disabled>Select target category...</option>
                            <template v-for="parent in categories" :key="parent.id">
                                <optgroup v-if="parent.children?.length" :label="parent.name">
                                    <option :value="parent.id">{{ parent.name }} (Parent)</option>
                                    <option v-for="sub in parent.children" :key="sub.id" :value="sub.id">↳ {{ sub.name }}</option>
                                </optgroup>
                                <option v-else :value="parent.id">{{ parent.name }}</option>
                            </template>
                        </select>
                    </div>
                    <div class="flex items-center justify-end gap-2.5 pt-3">
                        <button
                            type="button"
                            @click="showBulkCategoryModal = false"
                            class="px-4 py-2 rounded-lg border dark:border-gray-600 text-sm font-semibold text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700 transition"
                        >
                            Cancel
                        </button>
                        <button
                            type="button"
                            @click="submitBulkCategory"
                            :disabled="!targetCategoryId || bulkProcessing"
                            class="px-5 py-2 rounded-lg bg-blue-600 hover:bg-blue-700 text-white text-sm font-bold shadow transition disabled:opacity-50 flex items-center gap-1.5"
                        >
                            <svg v-if="bulkProcessing" class="animate-spin h-4 w-4 text-white" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path></svg>
                            Apply Category
                        </button>
                    </div>
                </div>
            </div>

            <!-- ── Bulk Adjust Price Modal ─────────────────── -->
            <div v-if="showBulkPriceModal" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/60 backdrop-blur-xs">
                <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-2xl max-w-md w-full p-6 border dark:border-gray-700 space-y-4">
                    <div class="flex items-center justify-between pb-3 border-b dark:border-gray-700">
                        <div class="flex items-center gap-2">
                            <span class="text-xl">💰</span>
                            <h3 class="font-bold text-lg text-gray-900 dark:text-white">Adjust Prices</h3>
                        </div>
                        <button @click="showBulkPriceModal = false" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-200 text-lg">✕</button>
                    </div>
                    <p class="text-sm text-gray-600 dark:text-gray-300">
                        Quickly update prices across <span class="font-bold text-blue-600 dark:text-blue-400">{{ selectedIds.length }}</span> selected product(s):
                    </p>
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-xs font-bold text-gray-500 dark:text-gray-400 uppercase tracking-wide mb-1.5">Action</label>
                            <select v-model="bulkPriceOperation" class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white px-3 py-2 text-sm focus:ring-blue-500">
                                <option value="increase">Increase (+)</option>
                                <option value="decrease">Decrease (-)</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-gray-500 dark:text-gray-400 uppercase tracking-wide mb-1.5">Type</label>
                            <select v-model="bulkPriceType" class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white px-3 py-2 text-sm focus:ring-blue-500">
                                <option value="percentage">Percentage (%)</option>
                                <option value="fixed">Fixed Amount (₦)</option>
                            </select>
                        </div>
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-gray-500 dark:text-gray-400 uppercase tracking-wide mb-1.5">Amount {{ bulkPriceType === 'percentage' ? '(%)' : '(₦)' }}</label>
                        <input
                            v-model.number="bulkPriceAmount"
                            type="number"
                            min="0"
                            step="any"
                            placeholder="e.g. 10"
                            class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white px-3 py-2 text-sm focus:ring-blue-500"
                        />
                    </div>
                    <div class="flex items-center justify-end gap-2.5 pt-3">
                        <button
                            type="button"
                            @click="showBulkPriceModal = false"
                            class="px-4 py-2 rounded-lg border dark:border-gray-600 text-sm font-semibold text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700 transition"
                        >
                            Cancel
                        </button>
                        <button
                            type="button"
                            @click="submitBulkPrice"
                            :disabled="!bulkPriceAmount || bulkPriceAmount <= 0 || bulkProcessing"
                            class="px-5 py-2 rounded-lg bg-blue-600 hover:bg-blue-700 text-white text-sm font-bold shadow transition disabled:opacity-50 flex items-center gap-1.5"
                        >
                            <svg v-if="bulkProcessing" class="animate-spin h-4 w-4 text-white" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path></svg>
                            Apply Price Adjustment
                        </button>
                    </div>
                </div>
            </div>

            <!-- ── Bulk Delete Modal ───────────────────────── -->
            <div v-if="showBulkDeleteModal" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/60 backdrop-blur-xs">
                <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-2xl max-w-md w-full p-6 border dark:border-gray-700 space-y-4">
                    <div class="flex items-center justify-between pb-3 border-b dark:border-gray-700">
                        <div class="flex items-center gap-2">
                            <span class="text-xl">⚠️</span>
                            <h3 class="font-bold text-lg text-gray-900 dark:text-white">Delete Products</h3>
                        </div>
                        <button @click="showBulkDeleteModal = false" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-200 text-lg">✕</button>
                    </div>
                    <p class="text-sm text-gray-600 dark:text-gray-300">
                        Are you sure you want to delete <span class="font-bold text-red-600">{{ selectedIds.length }}</span> selected product(s)?
                    </p>
                    <p class="text-xs text-gray-500 bg-red-50 dark:bg-red-950/40 p-3 rounded-lg border border-red-200 dark:border-red-900/60 text-red-700 dark:text-red-400">
                        This action cannot be undone. Any linked POS items will have their mapping status reset to unmapped.
                    </p>
                    <div class="flex items-center justify-end gap-2.5 pt-3">
                        <button
                            type="button"
                            @click="showBulkDeleteModal = false"
                            class="px-4 py-2 rounded-lg border dark:border-gray-600 text-sm font-semibold text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700 transition"
                        >
                            Cancel
                        </button>
                        <button
                            type="button"
                            @click="submitBulkDelete"
                            :disabled="bulkProcessing"
                            class="px-5 py-2 rounded-lg bg-red-600 hover:bg-red-700 text-white text-sm font-bold shadow transition disabled:opacity-50 flex items-center gap-1.5"
                        >
                            <svg v-if="bulkProcessing" class="animate-spin h-4 w-4 text-white" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path></svg>
                            Yes, Delete {{ selectedIds.length }} Product(s)
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </AdminLayout>
</template>
