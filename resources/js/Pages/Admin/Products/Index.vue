<script setup>
import { Head, Link, useForm, router, usePage } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import Pagination from '@/Components/Pagination.vue';
import { ref, watch, computed, nextTick, onMounted, onUnmounted } from 'vue';
import { useToast } from 'vue-toastification';
import debounce from 'lodash/debounce';
import { QuillEditor } from '@vueup/vue-quill';
import '@vueup/vue-quill/dist/vue-quill.snow.css';

const toast = useToast();
const page = usePage();
const props = defineProps({
    products:         Object,
    categories:       Array,
    brands: {
        type: Array,
        default: () => []
    },
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

// Local mutable copy of brands so quick-created brands appear immediately
const localBrands = ref([...(props.brands || [])]);
watch(() => props.brands, (val) => {
    localBrands.value = [...(val || [])];
});

// Table Filters
const search = ref(props.filters?.search || '');
const filterCategory = ref(props.filters?.category_id || '');
const filterBrand = ref(props.filters?.brand_id || '');
const filterStatus = ref(props.filters?.status || '');
const filterInventorySource = ref(props.filters?.inventory_source || '');
const filterSort = ref(props.filters?.sort || 'latest');
const perPage = ref(props.filters?.per_page || '25');

const applyFilters = debounce(() => {
    router.get(route('admin.products.index'), {
        search: search.value || undefined,
        category_id: filterCategory.value || undefined,
        brand_id: filterBrand.value || undefined,
        status: filterStatus.value || undefined,
        inventory_source: filterInventorySource.value || undefined,
        sort: filterSort.value !== 'latest' ? filterSort.value : undefined,
        per_page: perPage.value !== '25' ? perPage.value : undefined,
    }, { preserveState: true, replace: true });
}, 300);

watch([search, filterCategory, filterBrand, filterStatus, filterInventorySource, filterSort, perPage], applyFilters);

const clearFilters = () => {
    search.value = '';
    filterCategory.value = '';
    filterBrand.value = '';
    filterStatus.value = '';
    filterInventorySource.value = '';
    filterSort.value = 'latest';
    perPage.value = '25';
};

const toggleSort = (field) => {
    if (field === 'name') {
        filterSort.value = filterSort.value === 'name_asc' ? 'name_desc' : 'name_asc';
    } else if (field === 'price') {
        filterSort.value = filterSort.value === 'price_asc' ? 'price_desc' : 'price_asc';
    } else if (field === 'stock') {
        filterSort.value = filterSort.value === 'qty_desc' ? 'qty_asc' : 'qty_desc';
    }
};

// ── Export Dropdown State ─────────────────────────────────────
const showExportMenu = ref(false);
const exportDropdownRef = ref(null);

const getExportUrl = (source) => {
    const params = new URLSearchParams();
    if (source && source !== 'both') {
        params.append('source', source);
    } else {
        params.append('source', 'both');
    }
    if (search.value) params.append('search', search.value);
    if (filterCategory.value) params.append('category_id', filterCategory.value);
    if (filterBrand.value) params.append('brand_id', filterBrand.value);
    if (filterStatus.value) params.append('status', filterStatus.value);
    if (filterSort.value && filterSort.value !== 'latest') params.append('sort', filterSort.value);

    const qs = params.toString();
    return route('admin.products.export') + (qs ? `?${qs}` : '');
};

// ── Import Modal State ────────────────────────────────────────
const showImportModal = ref(false);
const importFileRef   = ref(null);
const importForm      = useForm({
    file: null,
    import_mode: 'both', // 'both', 'ecommerce', 'pos'
});

const handleImportFileChange = (e) => {
    const file = e.target.files?.[0];
    importForm.file = file || null;
};

const submitImport = () => {
    if (!importForm.file) {
        toast.warning('Please select a CSV file to import.');
        return;
    }
    importForm.post(route('admin.products.import'), {
        preserveScroll: true,
        onSuccess: () => {
            showImportModal.value = false;
            importForm.reset();
            if (importFileRef.value) importFileRef.value.value = '';
        },
        onError: (errors) => {
            const firstErr = Object.values(errors)[0];
            if (firstErr) toast.error(firstErr);
        }
    });
};

const isFormVisible = ref(false);
const isEditing     = ref(false);
const editId        = ref(null);
const imagePreview  = ref(null);
const galleryItems  = ref([]);
const thumbnailInputRef = ref(null);
const galleryInputRef   = ref(null);

const form = useForm({
    _method:   'POST',
    // Inventory Source & POS Mapping
    inventory_source:  'ecommerce', // 'pos' or 'ecommerce'
    pos_item_id:       null,
    // Identity
    name:              '',
    category_id:       '',
    brand_id:          '',
    url_key:           '',
    product_number:    '',
    sku:               '',
    short_description: '',
    description:       '',
    // SEO
    meta_title:        '',
    meta_description:  '',
    meta_keywords:     '',
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
    remove_thumbnail: false,
    compress_images: true,
    variants: [],
    pos_units: [],
    attributes: {},
});

// ── Searchable POS Item Picker ────────────────────────────────
const posDropdownOpen   = ref(false);
const posSearchQuery    = ref('');
const posSearchInputRef = ref(null);
const posDropdownRef    = ref(null);

const selectedPosItem = computed(() => {
    if (!form.pos_item_id) return null;
    return props.synced_pos_items.find(i => Number(i.pos_item_id) === Number(form.pos_item_id)) || null;
});

const filteredPosItemsAll = computed(() => {
    const q = posSearchQuery.value.trim().toLowerCase();
    if (!q) return props.synced_pos_items;
    return props.synced_pos_items.filter(item => {
        const name = (item.pos_item_name || '').toLowerCase();
        const sku  = (item.sku || '').toLowerCase();
        const bar  = (item.barcode || '').toLowerCase();
        const ref  = (item.item_reference || '').toLowerCase();
        const cat  = (item.pos_category_name || '').toLowerCase();
        const id   = String(item.pos_item_id || '');
        return name.includes(q) || sku.includes(q) || bar.includes(q) || ref.includes(q) || cat.includes(q) || id.includes(q);
    });
});

const filteredPosItems = computed(() => filteredPosItemsAll.value.slice(0, 25));

const togglePosDropdown = () => {
    posDropdownOpen.value = !posDropdownOpen.value;
    if (posDropdownOpen.value) {
        nextTick(() => {
            posSearchInputRef.value?.focus();
        });
    }
};

const selectPosItem = (item) => {
    if (!item) {
        form.pos_item_id = null;
        posDropdownOpen.value = false;
        return;
    }
    form.pos_item_id = item.pos_item_id;
    form.price = item.standard_price;
    if (item.buying_price !== undefined && item.buying_price !== null) {
        form.cost_price = item.buying_price;
    }
    form.stock = Math.round(Number(item.effective_availability || 0));
    if (!form.name || !isEditing.value) {
        form.name = item.pos_item_name;
    }
    if (!form.sku) {
        form.sku = item.sku || '';
    }
    if (!form.brand_id && item.pos_brand_name) {
        const matchedBrand = localBrands.value.find(b =>
            (item.pos_brand_id && Number(b.pos_brand_id) === Number(item.pos_brand_id))
            || (b.name || '').toLowerCase() === item.pos_brand_name.toLowerCase()
        );
        if (matchedBrand) {
            form.brand_id = matchedBrand.id;
        }
    }
    posDropdownOpen.value = false;
    posSearchQuery.value = '';
    openVariantDropdownIndex.value = null;
    variantPosSearchQuery.value = '';
};

const clearPosItem = () => {
    form.pos_item_id = null;
    posSearchQuery.value = '';
};

// ── Searchable Brand Picker & Inline Quick-Add ────────────────
const brandDropdownOpen   = ref(false);
const brandSearchQuery    = ref('');
const brandSearchInputRef = ref(null);
const brandDropdownRef    = ref(null);

const showQuickAddBrand   = ref(false);
const quickBrandName      = ref('');
const quickBrandSaving    = ref(false);

const selectedBrandObj = computed(() => {
    if (!form.brand_id) return null;
    return localBrands.value.find(b => Number(b.id) === Number(form.brand_id)) || null;
});

const filteredBrandsAll = computed(() => {
    const q = brandSearchQuery.value.trim().toLowerCase();
    if (!q) return localBrands.value;
    return localBrands.value.filter(b =>
        (b.name || '').toLowerCase().includes(q)
        || (b.slug || '').toLowerCase().includes(q)
    );
});

const filteredBrands = computed(() => filteredBrandsAll.value.slice(0, 25));

const toggleBrandDropdown = () => {
    brandDropdownOpen.value = !brandDropdownOpen.value;
    if (brandDropdownOpen.value) {
        nextTick(() => {
            brandSearchInputRef.value?.focus();
        });
    }
};

const selectBrandOption = (brand) => {
    form.brand_id = brand ? brand.id : '';
    brandDropdownOpen.value = false;
    brandSearchQuery.value = '';
};

const clearBrandSelection = () => {
    form.brand_id = '';
    brandSearchQuery.value = '';
};

const createQuickBrand = async () => {
    const name = quickBrandName.value.trim();
    if (!name || quickBrandSaving.value) return;
    quickBrandSaving.value = true;
    try {
        const res = await window.axios.post(route('admin.brands.store'), {
            name,
            status: true,
        }, {
            headers: { 'Accept': 'application/json' }
        });
        if (res.data?.brand) {
            const newBrand = res.data.brand;
            if (!localBrands.value.find(b => Number(b.id) === Number(newBrand.id))) {
                localBrands.value.push(newBrand);
                localBrands.value.sort((a, b) => (a.name || '').localeCompare(b.name || ''));
            }
            form.brand_id = newBrand.id;
            quickBrandName.value = '';
            showQuickAddBrand.value = false;
            brandDropdownOpen.value = false;
            toast.success(`Brand "${newBrand.name}" created and selected!`);
        }
    } catch (e) {
        const msg = e.response?.data?.errors?.name?.[0] || e.response?.data?.message || 'Failed to create brand.';
        toast.error(msg);
    } finally {
        quickBrandSaving.value = false;
    }
};

const handleClickOutsidePosDropdown = (e) => {
    if (showExportMenu.value && exportDropdownRef.value && !exportDropdownRef.value.contains(e.target)) {
        showExportMenu.value = false;
    }
    if (posDropdownOpen.value && posDropdownRef.value && !posDropdownRef.value.contains(e.target)) {
        posDropdownOpen.value = false;
    }
    if (brandDropdownOpen.value && brandDropdownRef.value && !brandDropdownRef.value.contains(e.target)) {
        brandDropdownOpen.value = false;
    }
    if (openVariantDropdownIndex.value !== null && !e.target.closest('.variant-pos-dropdown-container')) {
        openVariantDropdownIndex.value = null;
        variantPosSearchQuery.value = '';
    }
};

onMounted(() => {
    document.addEventListener('mousedown', handleClickOutsidePosDropdown);
});

onUnmounted(() => {
    document.removeEventListener('mousedown', handleClickOutsidePosDropdown);
});

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
            const params = new URLSearchParams({
                q: relatedSearch.value,
                exclude: editId.value || 0,
                category_id: form.category_id || 0,
            });
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
        const params = new URLSearchParams({
            q: '',
            exclude: editId.value || 0,
            category_id: form.category_id || 0,
        });
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

const removeThumbnail = () => {
    imagePreview.value = null;
    form.image = null;
    form.remove_thumbnail = true;
    if (thumbnailInputRef.value) thumbnailInputRef.value.value = '';
};

const handleImageUpload = (e) => {
    const file = e.target.files?.[0];
    if (file) {
        form.image = file;
        form.remove_thumbnail = false;
        imagePreview.value = URL.createObjectURL(file);
    }
};

const handleGalleryUpload = (e) => {
    const files = Array.from(e.target.files || []);
    const remainingSlots = 10 - galleryItems.value.length;
    if (remainingSlots <= 0) {
        toast.warning('Maximum 10 gallery images allowed.');
        if (e.target) e.target.value = '';
        return;
    }
    const toAdd = files.slice(0, remainingSlots);
    for (const f of toAdd) {
        galleryItems.value.push({
            type: 'new',
            file: f,
            url: URL.createObjectURL(f),
        });
    }
    if (e.target) e.target.value = '';
};

const removeGalleryItem = (index) => {
    galleryItems.value.splice(index, 1);
};

const addVariant = () => form.variants.push({
    id: null,
    name: '',
    price: '',
    stock: '',
    image: '',
    inventory_source: form.inventory_source === 'pos' ? 'pos' : 'ecommerce',
    pos_item_id: null,
    absorb_standalone: true,
});
const removeVariant = (i) => {
    if (openVariantDropdownIndex.value === i) {
        openVariantDropdownIndex.value = null;
        variantPosSearchQuery.value = '';
    }
    form.variants.splice(i, 1);
};

// ── Variant & Selling Unit Image Mapping ──────────────────────
const showImagePickerModal = ref(false);
const imagePickerTarget = ref(null); // { type: 'variant'|'unit', index: number, title: string }

const availableGalleryImages = computed(() => {
    const list = [];
    if (imagePreview.value && typeof imagePreview.value === 'string' && !imagePreview.value.startsWith('blob:') && !imagePreview.value.startsWith('data:')) {
        const rawPath = imagePreview.value.replace(/^\/storage\//, '');
        list.push({ path: rawPath, url: imagePreview.value, label: 'Main Thumbnail' });
    }
    if (galleryItems.value?.length) {
        galleryItems.value.forEach((item, idx) => {
            if (item.type === 'existing' && item.path && !list.some(x => x.path === item.path)) {
                list.push({ path: item.path, url: item.url, label: `Gallery Photo #${idx + 1}` });
            }
        });
    }
    return list;
});

const openImagePicker = (type, index, title) => {
    imagePickerTarget.value = { type, index, title };
    showImagePickerModal.value = true;
};

const selectMappedImage = (imgPath) => {
    if (!imagePickerTarget.value) return;
    const { type, index } = imagePickerTarget.value;
    if (type === 'variant' && form.variants[index]) {
        form.variants[index].image = imgPath;
    } else if (type === 'unit' && form.pos_units[index]) {
        form.pos_units[index].image = imgPath;
    }
    showImagePickerModal.value = false;
};

const clearMappedImage = () => {
    if (!imagePickerTarget.value) return;
    const { type, index } = imagePickerTarget.value;
    if (type === 'variant' && form.variants[index]) {
        form.variants[index].image = '';
    } else if (type === 'unit' && form.pos_units[index]) {
        form.pos_units[index].image = '';
    }
    showImagePickerModal.value = false;
};

const currentTargetImage = computed(() => {
    if (!imagePickerTarget.value) return '';
    const { type, index } = imagePickerTarget.value;
    if (type === 'variant' && form.variants[index]) {
        return form.variants[index].image || '';
    } else if (type === 'unit' && form.pos_units[index]) {
        return form.pos_units[index].image || '';
    }
    return '';
});

const enableEcommerceSellingUnits = () => {
    if (!form.pos_units) form.pos_units = [];
    if (!form.pos_units.some(u => u.unit_kind === 'base')) {
        form.pos_units.unshift({
            _uid: 'unit_' + Date.now() + '_' + Math.random().toString(36).substr(2, 9),
            id: null,
            unit_name: 'Single Item',
            unit_kind: 'base',
            quantity_in_base_units: 1,
            synced_standard_price: form.price || 0,
            online_label: 'Single',
            is_enabled_online: true,
            image: '',
        });
    }
    if (form.pos_units.length === 1) {
        form.pos_units.push({
            _uid: 'unit_' + (Date.now() + 1) + '_' + Math.random().toString(36).substr(2, 9),
            id: null,
            unit_name: 'Pack of 6',
            unit_kind: 'additional',
            quantity_in_base_units: 6,
            synced_standard_price: Number((Number(form.price || 0) * 6).toFixed(2)),
            online_label: 'Pack of 6',
            is_enabled_online: true,
            image: '',
        });
    }
    form.pos_units = [...form.pos_units];
};

const addEcommerceSellingUnit = () => {
    if (!form.pos_units) form.pos_units = [];
    const count = form.pos_units.length;
    const factor = count >= 2 ? count * 12 : 12;
    form.pos_units.push({
        _uid: 'unit_' + Date.now() + '_' + Math.random().toString(36).substr(2, 9),
        id: null,
        unit_name: `Carton of ${factor}`,
        unit_kind: 'additional',
        quantity_in_base_units: factor,
        synced_standard_price: Number((Number(form.price || 0) * factor).toFixed(2)),
        online_label: `Carton (${factor} Units)`,
        is_enabled_online: true,
        image: '',
    });
    form.pos_units = [...form.pos_units];
};

const removeSellingUnit = (index) => {
    if (form.pos_units && form.pos_units[index] !== undefined) {
        form.pos_units.splice(index, 1);
        form.pos_units = [...form.pos_units];
    }
};

const disableEcommerceSellingUnits = () => {
    form.pos_units = [];
};

const openVariantDropdownIndex = ref(null);
const variantPosSearchQuery    = ref('');
const variantPosSearchInputRef = ref(null);

const filteredVariantPosItemsAll = computed(() => {
    const q = variantPosSearchQuery.value.trim().toLowerCase();
    if (!q) return props.synced_pos_items || [];
    return (props.synced_pos_items || []).filter(item => {
        const name = (item.pos_item_name || '').toLowerCase();
        const sku  = (item.sku || '').toLowerCase();
        const bar  = (item.barcode || '').toLowerCase();
        const ref  = (item.item_reference || '').toLowerCase();
        const cat  = (item.pos_category_name || '').toLowerCase();
        const id   = String(item.pos_item_id || '');
        return name.includes(q) || sku.includes(q) || bar.includes(q) || ref.includes(q) || cat.includes(q) || id.includes(q);
    });
});

const filteredVariantPosItems = computed(() => filteredVariantPosItemsAll.value.slice(0, 25));

const toggleVariantPosDropdown = (index) => {
    if (openVariantDropdownIndex.value === index) {
        openVariantDropdownIndex.value = null;
        variantPosSearchQuery.value = '';
    } else {
        openVariantDropdownIndex.value = index;
        variantPosSearchQuery.value = '';
        nextTick(() => {
            variantPosSearchInputRef.value?.focus();
        });
    }
};

const selectVariantPosItemOption = (v, item) => {
    v.pos_item_id = item ? item.pos_item_id : null;
    handleVariantPosItemSelect(v);
    openVariantDropdownIndex.value = null;
    variantPosSearchQuery.value = '';
};

const clearVariantPosItem = (v) => {
    v.pos_item_id = null;
    variantPosSearchQuery.value = '';
};

const isBasePosItemInVariants = computed(() => {
    if (form.inventory_source !== 'pos' || !form.pos_item_id) return true;
    return form.variants.some(v => v.inventory_source === 'pos' && Number(v.pos_item_id) === Number(form.pos_item_id));
});

const includeBasePosItemAsVariant = () => {
    if (!form.pos_item_id) return;
    const baseItem = getPosItemDetails(form.pos_item_id);
    form.variants.unshift({
        id: null,
        name: baseItem ? baseItem.pos_item_name : (form.name || 'Standard'),
        price: baseItem ? baseItem.standard_price : form.price,
        stock: baseItem ? baseItem.effective_availability : form.stock,
        image: '',
        inventory_source: 'pos',
        pos_item_id: Number(form.pos_item_id),
        absorb_standalone: false,
    });
};

const handleVariantSourceChange = (v) => {
    if (v.inventory_source === 'ecommerce') {
        v.pos_item_id = null;
    }
};

const handleVariantPosItemSelect = (v) => {
    if (!v.pos_item_id) return;
    const item = props.synced_pos_items.find(i => Number(i.pos_item_id) === Number(v.pos_item_id));
    if (item) {
        if (!v.name) {
            v.name = item.pos_item_name;
        }
        v.price = item.standard_price;
        v.stock = item.effective_availability;
        if (v.absorb_standalone === undefined) {
            v.absorb_standalone = true;
        }
    }
};

const getPosItemDetails = (posItemId) => {
    if (!posItemId) return null;
    return props.synced_pos_items.find(i => Number(i.pos_item_id) === Number(posItemId)) || null;
};

const getVariantStandaloneConflict = (posItemId) => {
    const item = getPosItemDetails(posItemId);
    if (!item || !item.mapped_product) return null;
    if (editId.value && Number(item.mapped_product.id) === Number(editId.value)) return null;
    return item.mapped_product;
};

const formatVariantPosOptionLabel = (item) => {
    let prefix = '🟢 ';
    let statusTag = '';
    if (form.pos_item_id && Number(item.pos_item_id) === Number(form.pos_item_id)) {
        prefix = '🔵 ';
        statusTag = ' • [Base Item of This Product]';
    } else if (item.mapped_product && (!editId.value || Number(item.mapped_product.id) !== Number(editId.value))) {
        prefix = '🟡 ';
        statusTag = ` • [Active Store Product: ${item.mapped_product.name}]`;
    } else if (item.mapped_variant && (!editId.value || Number(item.mapped_variant.product_id) !== Number(editId.value))) {
        prefix = '🟣 ';
        statusTag = ` • [Variant of: ${item.mapped_variant.product_name}]`;
    }
    return `${prefix}${item.pos_item_name} (SKU: ${item.sku || 'N/A'} | ₦${Number(item.standard_price || 0).toLocaleString()} | Stock: ${item.effective_availability})${statusTag}`;
};

const save = () => {
    const url = isEditing.value
        ? route('admin.products.update', editId.value)
        : route('admin.products.store');

    const existingImages = galleryItems.value
        .filter(item => item.type === 'existing')
        .map(item => item.path);
    const newFiles = galleryItems.value
        .filter(item => item.type === 'new' && item.file)
        .map(item => item.file);

    form.transform(data => ({
        ...data,
        remove_thumbnail: data.remove_thumbnail ? 1 : 0,
        compress_images: data.compress_images ? 1 : 0,
        existing_images: JSON.stringify(existingImages),
        images: newFiles,
        variants: JSON.stringify(data.variants),
        pos_units: JSON.stringify(data.pos_units),
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

const fullDescQuillRef = ref(null);

const openCreateForm = () => {
    resetFormState();
    form._method = 'POST';
    isEditing.value = false;
    form.show_stock_level = props.store_settings?.show_stock_level_default === '1';
    form.compress_images = true;
    isFormVisible.value = true;
    initRelatedSearch();
    nextTick(() => {
        if (fullDescQuillRef.value) {
            fullDescQuillRef.value.pasteHTML('');
        }
    });
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
    form.brand_id          = p.brand_id || '';
    form.url_key           = p.url_key || '';
    form.product_number    = p.product_number || '';
    form.sku               = p.sku || '';
    form.short_description = p.short_description || '';
    form.description       = p.description || '';
    nextTick(() => {
        if (fullDescQuillRef.value) {
            fullDescQuillRef.value.pasteHTML(p.description || '');
        }
    });
    form.meta_title        = p.meta_title || '';
    form.meta_description  = p.meta_description || '';
    form.meta_keywords     = p.meta_keywords || '';
    form.price             = p.price;
    form.cost_price        = (p.cost_price !== null && p.cost_price !== undefined && p.cost_price !== '') ? p.cost_price : (p.synced_pos_item?.buying_price ?? '');
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
    form.compress_images   = true;
    form.related_products  = p.related_products || [];
    if (p.variant_mappings && p.variant_mappings.length > 0) {
        form.variants = p.variant_mappings.map(m => ({
            id: m.id,
            name: m.variant_label || '',
            price: m.local_price !== null && m.local_price !== undefined ? m.local_price : (m.synced_pos_item?.standard_price ?? ''),
            stock: m.local_stock !== null && m.local_stock !== undefined ? m.local_stock : (m.synced_pos_item?.effective_availability ?? ''),
            inventory_source: m.inventory_source || (m.pos_item_id ? 'pos' : 'ecommerce'),
            pos_item_id: m.pos_item_id || null,
            image: m.image || '',
            absorb_standalone: true,
        }));
    } else {
        form.variants = (p.variants || []).map(v => ({
            id: v.id || null,
            name: v.name || '',
            price: v.price ?? '',
            stock: v.stock ?? '',
            inventory_source: v.inventory_source || (v.pos_item_id ? 'pos' : 'ecommerce'),
            pos_item_id: v.pos_item_id || null,
            image: v.image || '',
            absorb_standalone: true,
        }));
    }
    form.pos_units = (p.pos_units || []).map((u, idx) => ({
        _uid: 'unit_exist_' + (u.id || idx) + '_' + Math.random().toString(36).substr(2, 9),
        id: u.id,
        unit_name: u.unit_name,
        unit_kind: u.unit_kind,
        quantity_in_base_units: u.quantity_in_base_units,
        synced_standard_price: u.synced_standard_price,
        online_label: u.online_label || '',
        is_enabled_online: u.is_enabled_online !== false,
        image: u.image || '',
    }));
    form.attributes        = p.attributes || {};
    form.remove_thumbnail  = false;
    imagePreview.value     = p.image ? '/storage/' + p.image : null;
    if (p.images?.length) {
        galleryItems.value = p.images.map(img => ({
            type: 'existing',
            path: img,
            url: '/storage/' + img,
        }));
    } else {
        galleryItems.value = [];
    }
    isFormVisible.value    = true;
    // Load thumbnails for already-selected related products, then init search
    loadSelectedRelated(form.related_products).then(() => initRelatedSearch());
};

const closeForm = () => { isFormVisible.value = false; resetFormState(); };
const resetFormState = () => {
    form.reset(); form.clearErrors();
    form.inventory_source = 'ecommerce';
    form.pos_item_id = null;
    form.brand_id = '';
    posDropdownOpen.value = false;
    posSearchQuery.value = '';
    brandDropdownOpen.value = false;
    brandSearchQuery.value = '';
    showQuickAddBrand.value = false;
    quickBrandName.value = '';
    openVariantDropdownIndex.value = null;
    variantPosSearchQuery.value = '';
    editId.value = null;
    imagePreview.value = null;
    form.remove_thumbnail = false;
    galleryItems.value = [];
    if (thumbnailInputRef.value) thumbnailInputRef.value.value = '';
    if (galleryInputRef.value) galleryInputRef.value.value = '';
    form.status = true; form.visible_individually = true; form.show_stock_level = true;
    form.compress_images = true;
    form.meta_title = '';
    form.meta_description = '';
    form.meta_keywords = '';
    form.related_products = [];
    form.variants = [];
    form.pos_units = [];
    form.attributes = {};
    selectedRelatedProducts.value = [];
    relatedResults.value = [];
    relatedSearch.value = '';
    aiSuggestingRelated.value = false;
};

// ── AI Product Description & SEO Generator ───────────────
const isAiEnabled = computed(() => {
    return (props.store_settings?.ai_enabled ?? page.props.store_settings?.ai_enabled) === '1';
});

const storeUrlDomain = computed(() => {
    return typeof window !== 'undefined' ? (window.location.host || 'yourstore.com') : 'yourstore.com';
});

const aiGenerating = ref({
    short: false,
    full: false,
    seo: false,
});

const getSelectedCategoryName = () => {
    if (!form.category_id || !props.categories) return '';
    const cat = props.categories.find(c => c.id == form.category_id);
    return cat ? cat.name : '';
};

const getSelectedBrandName = () => {
    if (!form.brand_id) return '';
    const b = localBrands.value.find(br => Number(br.id) === Number(form.brand_id));
    return b ? b.name : '';
};

const generateAiDescription = async (type = 'short') => {
    if (!form.name || !form.name.trim()) {
        toast.warning('Please enter the Product Name first before generating descriptions.');
        return;
    }

    aiGenerating.value[type] = true;
    try {
        const res = await window.axios.post(route('admin.ai.generate-product-description'), {
            type,
            product_name: form.name.trim(),
            category_name: getSelectedCategoryName(),
            brand_name: getSelectedBrandName() || undefined,
            price: type === 'short' ? undefined : (form.price ? Number(form.price) : null),
            keywords: (type === 'seo' && form.meta_keywords) ? form.meta_keywords : undefined,
        });

        if (res.data.success) {
            if (type === 'short') {
                form.short_description = res.data.content;
                toast.success('Generated short description successfully!');
            } else if (type === 'full') {
                form.description = res.data.content;
                nextTick(() => {
                    if (fullDescQuillRef.value) {
                        fullDescQuillRef.value.pasteHTML(res.data.content || '');
                    }
                });
                toast.success('Generated AI description successfully!');
            } else if (type === 'seo') {
                if (res.data.seo) {
                    form.meta_title = res.data.seo.meta_title || '';
                    form.meta_description = res.data.seo.meta_description || '';
                    form.meta_keywords = res.data.seo.meta_keywords || '';
                }
                toast.success('Generated SEO metadata (Title, Description, Keywords) successfully!');
            }
        } else {
            toast.error(res.data.message || 'Failed to generate AI content.');
        }
    } catch (e) {
        const msg = e.response?.data?.message || 'Failed to generate AI content. Please verify your AI settings.';
        toast.error(msg);
    } finally {
        aiGenerating.value[type] = false;
    }
};

const aiSuggestingRelated = ref(false);

const suggestAiRelatedProducts = async () => {
    if (!form.name || !form.name.trim()) {
        toast.warning('Please enter the Product Name first so AI can identify relevant matches.');
        return;
    }

    aiSuggestingRelated.value = true;
    try {
        const res = await window.axios.post(route('admin.ai.suggest-related-products'), {
            product_name: form.name.trim(),
            category_id: form.category_id ? Number(form.category_id) : undefined,
            category_name: getSelectedCategoryName(),
            current_product_id: editId.value || undefined,
            description: form.short_description || undefined,
            limit: 10,
        });

        if (res.data.success && res.data.products && res.data.products.length > 0) {
            let addedCount = 0;
            res.data.products.forEach(p => {
                if (!form.related_products.includes(p.id)) {
                    form.related_products.push(p.id);
                    addedCount++;
                }
                const existingIdx = selectedRelatedProducts.value.findIndex(existing => existing.id === p.id);
                if (existingIdx === -1) {
                    selectedRelatedProducts.value.push(p);
                } else if (p.ai_reason) {
                    selectedRelatedProducts.value[existingIdx].ai_reason = p.ai_reason;
                }
            });

            if (addedCount > 0) {
                toast.success(`✨ Added ${addedCount} related products (category & smart cross-sells)!`);
            } else {
                toast.info('AI analyzed catalog; suggested products were already selected.');
            }
        } else {
            toast.warning(res.data.message || 'No related products found in the catalog.');
        }
    } catch (e) {
        const msg = e.response?.data?.message || 'Failed to suggest related products with AI.';
        toast.error(msg);
    } finally {
        aiSuggestingRelated.value = false;
    }
};

const deleteProduct = (id) => {
    if (!confirm('Delete this product? This cannot be undone.')) return;
    Link; // placeholder — using useForm for delete
    useForm({}).delete(route('admin.products.destroy', id), {
        //onSuccess: () => toast.success('Product deleted.'),
    });
};

// ── Stock Adjustment (Ecommerce Only) ────────────────────────
const showStockAdjustModal = ref(false);
const adjustingProduct = ref(null);
const stockAdjustForm = useForm({
    product_id: null,
    variant_id: null,
    mode: 'add', // 'add', 'subtract', 'set'
    quantity: 1,
    reason: 'Physical Stock Audit / Recount',
    notes: '',
});

const openStockAdjustModal = (product) => {
    adjustingProduct.value = product;
    stockAdjustForm.product_id = product.id;
    stockAdjustForm.variant_id = null;
    stockAdjustForm.mode = 'add';
    stockAdjustForm.quantity = 1;
    stockAdjustForm.reason = 'Physical Stock Audit / Recount';
    stockAdjustForm.notes = '';
    showStockAdjustModal.value = true;
};

const closeStockAdjustModal = () => {
    showStockAdjustModal.value = false;
    adjustingProduct.value = null;
};

const submitStockAdjustment = () => {
    stockAdjustForm.post(route('admin.inventory.adjustments.store'), {
        preserveScroll: true,
        onSuccess: () => {
            closeStockAdjustModal();
        },
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

const showBulkBrandModal = ref(false);
const targetBrandId = ref('');

const showBulkPriceModal = ref(false);
const bulkPriceOperation = ref('increase');
const bulkPriceType = ref('percentage');
const bulkPriceAmount = ref('');

const showBulkDeleteModal = ref(false);
const showBulkMoveToEcommerceModal = ref(false);

const selectedPosProducts = computed(() => {
    const list = props.products.data || [];
    return list.filter(p => selectedIds.value.includes(p.id) && p.inventory_source === 'pos');
});

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
            showBulkBrandModal.value = false;
            showBulkPriceModal.value = false;
            showBulkDeleteModal.value = false;
            showBulkMoveToEcommerceModal.value = false;
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

const submitBulkBrand = () => {
    executeBulkAction('update_brand', { brand_id: targetBrandId.value === 'none' ? null : targetBrandId.value });
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

const submitBulkMoveToEcommerce = () => {
    executeBulkAction('move_to_ecommerce');
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
                    <button v-if="isFormVisible" @click="closeForm" class="text-sm text-primary hover:opacity-80 font-medium mt-1 flex items-center gap-1">
                        ← Back to Catalog
                    </button>
                </div>
                <div class="flex gap-3">
                    <template v-if="isFormVisible">
                        <button @click="closeForm" class="border dark:border-gray-600 font-semibold py-2 px-5 rounded-lg hover:bg-gray-100 dark:hover:bg-gray-700 transition text-sm">Cancel</button>
                        <button @click="save" :disabled="form.processing" class="bg-primary hover:bg-primary-dark text-white font-bold py-2 px-7 rounded-lg shadow transition disabled:opacity-50 flex items-center gap-2 text-sm">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                            Save Product
                        </button>
                    </template>
                    <template v-else>
                        <!-- Export Dropdown -->
                        <div ref="exportDropdownRef" class="relative">
                            <button
                                @click="showExportMenu = !showExportMenu"
                                type="button"
                                class="border border-gray-300 dark:border-gray-600 font-semibold py-2 px-4 rounded-lg hover:bg-gray-100 dark:hover:bg-gray-700 transition text-sm flex items-center gap-2 text-gray-700 dark:text-gray-200 shadow-xs cursor-pointer"
                                title="Export products to CSV"
                            >
                                <svg class="w-4 h-4 text-emerald-600 dark:text-emerald-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                </svg>
                                <span>Export</span>
                                <svg class="w-3.5 h-3.5 text-gray-400 transition-transform duration-150" :class="showExportMenu ? 'rotate-180' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                                </svg>
                            </button>

                            <!-- Dropdown Menu -->
                            <transition
                                enter-active-class="transition duration-100 ease-out"
                                enter-from-class="transform scale-95 opacity-0"
                                enter-to-class="transform scale-100 opacity-100"
                                leave-active-class="transition duration-75 ease-in"
                                leave-from-class="transform scale-100 opacity-100"
                                leave-to-class="transform scale-95 opacity-0"
                            >
                                <div
                                    v-if="showExportMenu"
                                    class="absolute right-0 mt-2 w-64 bg-white dark:bg-gray-800 rounded-xl shadow-xl border border-gray-200 dark:border-gray-700 py-1.5 z-50 text-left"
                                >
                                    <div class="px-3.5 py-2 text-xs font-bold uppercase tracking-wider text-gray-400 dark:text-gray-500 border-b border-gray-100 dark:border-gray-700 flex items-center justify-between">
                                        <span>Export Options</span>
                                        <span class="text-[10px] text-emerald-600 font-mono font-bold">.csv</span>
                                    </div>
                                    <div class="p-1 space-y-0.5">
                                        <a
                                            :href="getExportUrl('both')"
                                            target="_blank"
                                            @click="showExportMenu = false"
                                            class="flex items-start gap-2.5 px-3 py-2 rounded-lg text-sm hover:bg-emerald-50 dark:hover:bg-emerald-950/30 transition group cursor-pointer"
                                        >
                                            <span class="text-base mt-0.5">📦</span>
                                            <div>
                                                <div class="font-semibold text-gray-900 dark:text-white group-hover:text-emerald-600 dark:group-hover:text-emerald-400">All Items (Both)</div>
                                                <div class="text-[11px] text-gray-500 dark:text-gray-400">Export both Ecommerce & POS products</div>
                                            </div>
                                        </a>
                                        <a
                                            :href="getExportUrl('ecommerce')"
                                            target="_blank"
                                            @click="showExportMenu = false"
                                            class="flex items-start gap-2.5 px-3 py-2 rounded-lg text-sm hover:bg-blue-50 dark:hover:bg-blue-950/30 transition group cursor-pointer"
                                        >
                                            <span class="text-base mt-0.5">🌐</span>
                                            <div>
                                                <div class="font-semibold text-gray-900 dark:text-white group-hover:text-blue-600 dark:group-hover:text-blue-400">Ecommerce Items Only</div>
                                                <div class="text-[11px] text-gray-500 dark:text-gray-400">Online warehouse products only</div>
                                            </div>
                                        </a>
                                        <a
                                            :href="getExportUrl('pos')"
                                            target="_blank"
                                            @click="showExportMenu = false"
                                            class="flex items-start gap-2.5 px-3 py-2 rounded-lg text-sm hover:bg-purple-50 dark:hover:bg-purple-950/30 transition group cursor-pointer"
                                        >
                                            <span class="text-base mt-0.5">🏬</span>
                                            <div>
                                                <div class="font-semibold text-gray-900 dark:text-white group-hover:text-primary dark:group-hover:text-primary">POS Items Only</div>
                                                <div class="text-[11px] text-gray-500 dark:text-gray-400">Products linked to POS branch inventory</div>
                                            </div>
                                        </a>
                                    </div>
                                </div>
                            </transition>
                        </div>

                        <!-- Import Button -->
                        <button
                            @click="showImportModal = true"
                            type="button"
                            class="border border-gray-300 dark:border-gray-600 font-semibold py-2 px-4 rounded-lg hover:bg-gray-100 dark:hover:bg-gray-700 transition text-sm flex items-center gap-2 text-gray-700 dark:text-gray-200 shadow-xs cursor-pointer"
                            title="Import products from CSV"
                        >
                            <svg class="w-4 h-4 text-blue-600 dark:text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12" />
                            </svg>
                            <span>Import</span>
                        </button>

                        <button @click="openCreateForm" class="bg-primary hover:bg-primary-dark text-white font-bold py-2 px-5 rounded-lg shadow flex items-center gap-2 text-sm cursor-pointer">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                            Add Product
                        </button>
                    </template>
                </div>
            </div>

            <!-- ── Filters ────────────────────────────────── -->
            <div v-if="!isFormVisible" class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border dark:border-gray-700 p-4 mb-6 flex flex-wrap gap-4 items-end">
                <div class="flex-1 min-w-[200px]">
                    <label class="block text-xs font-bold text-gray-500 dark:text-gray-400 uppercase tracking-wide mb-1">Search Products</label>
                    <input v-model="search" type="text" placeholder="Name, SKU, URL Key..." 
                        class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white px-3 py-2 text-sm focus:ring-primary focus:border-primary" />
                </div>
                
                <div class="w-full sm:w-auto min-w-[150px]">
                    <label class="block text-xs font-bold text-gray-500 dark:text-gray-400 uppercase tracking-wide mb-1">Category</label>
                    <select v-model="filterCategory" 
                        class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white px-3 py-2 text-sm focus:ring-primary focus:border-primary">
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
                    <label class="block text-xs font-bold text-gray-500 dark:text-gray-400 uppercase tracking-wide mb-1">Brand</label>
                    <select v-model="filterBrand" 
                        class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white px-3 py-2 text-sm focus:ring-primary focus:border-primary">
                        <option value="">All Brands</option>
                        <option value="none">— No Brand —</option>
                        <option v-for="b in localBrands" :key="b.id" :value="b.id">{{ b.name }}</option>
                    </select>
                </div>

                <div class="w-full sm:w-auto min-w-[150px]">
                    <label class="block text-xs font-bold text-gray-500 dark:text-gray-400 uppercase tracking-wide mb-1">Status</label>
                    <select v-model="filterStatus" 
                        class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white px-3 py-2 text-sm focus:ring-primary focus:border-primary">
                        <option value="">All Statuses</option>
                        <option value="active">Active</option>
                        <option value="inactive">Inactive</option>
                    </select>
                </div>

                <div class="w-full sm:w-auto min-w-[160px]">
                    <label class="block text-xs font-bold text-gray-500 dark:text-gray-400 uppercase tracking-wide mb-1">Source</label>
                    <select v-model="filterInventorySource" 
                        class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white px-3 py-2 text-sm focus:ring-primary focus:border-primary">
                        <option value="">All Sources (Both)</option>
                        <option value="ecommerce">Ecommerce Only</option>
                        <option value="pos">POS Branch Items</option>
                    </select>
                </div>

                <div class="w-full sm:w-auto min-w-[170px]">
                    <label class="block text-xs font-bold text-gray-500 dark:text-gray-400 uppercase tracking-wide mb-1">Sort By</label>
                    <select v-model="filterSort" 
                        class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white px-3 py-2 text-sm focus:ring-primary focus:border-primary">
                        <option value="latest">Newest First</option>
                        <option value="oldest">Oldest First</option>
                        <option value="name_asc">Name: A to Z</option>
                        <option value="name_desc">Name: Z to A</option>
                        <option value="qty_desc">Stock: High to Low</option>
                        <option value="qty_asc">Stock: Low to High</option>
                        <option value="price_asc">Price: Low to High</option>
                        <option value="price_desc">Price: High to Low</option>
                    </select>
                </div>

                <div class="w-full sm:w-auto min-w-[130px]">
                    <label class="block text-xs font-bold text-gray-500 dark:text-gray-400 uppercase tracking-wide mb-1">Per Page</label>
                    <select v-model="perPage" 
                        class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white px-3 py-2 text-sm focus:ring-primary focus:border-primary">
                        <option value="25">25</option>
                        <option value="50">50</option>
                        <option value="100">100</option>
                        <option value="all">All</option>
                    </select>
                </div>

                <div v-if="search || filterCategory || filterBrand || filterStatus || filterInventorySource || filterSort !== 'latest' || perPage !== '25'">
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
                <div v-if="!isFormVisible && selectedIds.length > 0" class="mb-4 bg-primary-soft border border-primary-soft rounded-xl p-3.5 shadow-sm flex flex-wrap items-center justify-between gap-3">
                    <div class="flex items-center gap-2.5">
                        <span class="inline-flex items-center justify-center bg-primary text-white text-xs font-bold px-2.5 py-1 rounded-full shadow-xs">
                            {{ selectedIds.length }}
                        </span>
                        <span class="text-sm font-semibold text-primary">
                            {{ selectedIds.length === 1 ? '1 product selected' : `${selectedIds.length} products selected` }}
                        </span>
                        <button
                            type="button"
                            @click="clearSelection"
                            class="text-xs text-primary hover:opacity-80 underline cursor-pointer ml-1"
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
                            <svg class="w-3.5 h-3.5 text-primary" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z"/></svg>
                            Change Category
                        </button>

                        <!-- Set Brand -->
                        <button
                            type="button"
                            @click="showBulkBrandModal = true"
                            :disabled="bulkProcessing"
                            class="px-3 py-1.5 bg-white dark:bg-gray-800 hover:bg-gray-50 dark:hover:bg-gray-700 text-gray-700 dark:text-gray-200 rounded-lg text-xs font-semibold border border-gray-300 dark:border-gray-600 transition flex items-center gap-1.5 shadow-xs cursor-pointer disabled:opacity-50"
                        >
                            <span class="text-primary">🏷️</span>
                            Set Brand
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
                            class="px-3 py-1.5 bg-white dark:bg-gray-800 hover:bg-primary-soft text-primary rounded-lg text-xs font-semibold border border-primary-soft transition flex items-center gap-1.5 shadow-xs cursor-pointer disabled:opacity-50"
                            title="Mark with 'NEW' badge"
                        >
                            ✦ Tag New
                        </button>

                        <!-- Adjust Prices -->
                        <button
                            type="button"
                            @click="showBulkPriceModal = true"
                            :disabled="bulkProcessing"
                            class="px-3 py-1.5 bg-white dark:bg-gray-800 hover:bg-secondary-soft text-secondary rounded-lg text-xs font-semibold border border-secondary-soft transition flex items-center gap-1.5 shadow-xs cursor-pointer disabled:opacity-50"
                        >
                            ₦ Adjust Prices
                        </button>

                        <!-- Move to Ecommerce (Only enabled when selected POS items > 0) -->
                        <button
                            type="button"
                            @click="showBulkMoveToEcommerceModal = true"
                            :disabled="bulkProcessing || selectedPosProducts.length === 0"
                            :class="selectedPosProducts.length > 0 
                                ? 'bg-white dark:bg-gray-800 hover:bg-indigo-50 dark:hover:bg-indigo-950/40 text-indigo-600 dark:text-indigo-400 border-indigo-200 dark:border-indigo-800 cursor-pointer shadow-xs' 
                                : 'bg-gray-100 dark:bg-gray-800 text-gray-400 dark:text-gray-500 border-gray-200 dark:border-gray-700 cursor-not-allowed opacity-60'"
                            class="px-3 py-1.5 rounded-lg text-xs font-semibold border transition flex items-center gap-1.5"
                            :title="selectedPosProducts.length === 0 ? 'Only POS-linked items can be moved to Ecommerce' : 'Move selected POS items to Ecommerce Only'"
                        >
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4" />
                            </svg>
                            Move to Ecommerce
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
                                        class="w-4 h-4 rounded border-gray-300 dark:border-gray-600 text-primary focus:ring-primary bg-white dark:bg-gray-800 cursor-pointer"
                                        title="Select all products on this page"
                                    />
                                </th>
                                <th class="px-5 py-3 font-semibold">
                                    <button type="button" @click="toggleSort('name')" class="group inline-flex items-center gap-1.5 uppercase hover:text-gray-900 dark:hover:text-white focus:outline-none transition">
                                        <span :class="{'text-primary font-bold': filterSort === 'name_asc' || filterSort === 'name_desc'}">Product</span>
                                        <span class="text-xs">
                                            <span v-if="filterSort === 'name_asc'" class="text-primary font-bold">▲</span>
                                            <span v-else-if="filterSort === 'name_desc'" class="text-primary font-bold">▼</span>
                                            <span v-else class="text-gray-300 dark:text-gray-600 group-hover:text-gray-400">⇅</span>
                                        </span>
                                    </button>
                                </th>
                                <th class="px-5 py-3 font-semibold">Category</th>
                                <th class="px-5 py-3 font-semibold">Brand</th>
                                <th class="px-5 py-3 font-semibold">
                                    <button type="button" @click="toggleSort('price')" class="group inline-flex items-center gap-1.5 uppercase hover:text-gray-900 dark:hover:text-white focus:outline-none transition">
                                        <span :class="{'text-primary font-bold': filterSort === 'price_asc' || filterSort === 'price_desc'}">Pricing</span>
                                        <span class="text-xs">
                                            <span v-if="filterSort === 'price_asc'" class="text-primary font-bold">▲</span>
                                            <span v-else-if="filterSort === 'price_desc'" class="text-primary font-bold">▼</span>
                                            <span v-else class="text-gray-300 dark:text-gray-600 group-hover:text-gray-400">⇅</span>
                                        </span>
                                    </button>
                                </th>
                                <th class="px-5 py-3 font-semibold">
                                    <button type="button" @click="toggleSort('stock')" class="group inline-flex items-center gap-1.5 uppercase hover:text-gray-900 dark:hover:text-white focus:outline-none transition">
                                        <span :class="{'text-primary font-bold': filterSort === 'qty_desc' || filterSort === 'qty_asc'}">Stock</span>
                                        <span class="text-xs">
                                            <span v-if="filterSort === 'qty_desc'" class="text-primary font-bold">▼</span>
                                            <span v-else-if="filterSort === 'qty_asc'" class="text-primary font-bold">▲</span>
                                            <span v-else class="text-gray-300 dark:text-gray-600 group-hover:text-gray-400">⇅</span>
                                        </span>
                                    </button>
                                </th>
                                <th class="px-5 py-3 font-semibold">Flags</th>
                                <th class="px-5 py-3 font-semibold text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                            <tr v-for="p in products.data" :key="p.id" 
                                class="hover:bg-gray-50 dark:hover:bg-gray-700/60 transition"
                                :class="selectedIds.includes(p.id) ? 'bg-primary-soft' : ''">
                                <td class="px-4 py-3 text-center" @click.stop>
                                    <input
                                        type="checkbox"
                                        :checked="selectedIds.includes(p.id)"
                                        @change="toggleSelect(p.id)"
                                        class="w-4 h-4 rounded border-gray-300 dark:border-gray-600 text-primary focus:ring-primary bg-white dark:bg-gray-800 cursor-pointer"
                                    />
                                </td>
                                <td class="px-5 py-3">
                                    <div class="flex items-center gap-3">
                                        <img :src="p.image ? '/storage/'+p.image : 'https://placehold.co/60x60'" class="w-11 h-11 rounded-lg object-cover border dark:border-gray-600 flex-shrink-0"/>
                                        <div>
                                            <div class="flex items-center gap-1.5">
                                                <span class="font-semibold line-clamp-1 text-gray-900 dark:text-white">{{ p.name }}</span>
                                                <span v-if="p.inventory_source === 'pos'" class="text-[10px] font-bold px-1.5 py-0.2 rounded theme-primary-badge">
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
                                    <span class="text-xs theme-primary-badge px-2 py-0.5 rounded-full font-medium">{{ p.category?.name }}</span>
                                </td>
                                <td class="px-5 py-3">
                                    <span v-if="p.brand?.name" class="inline-flex items-center gap-1.5 text-xs bg-gray-100 dark:bg-gray-700 text-gray-800 dark:text-gray-200 px-2.5 py-0.5 rounded-full font-medium">
                                        <img v-if="p.brand.logo_url" :src="p.brand.logo_url" class="w-3.5 h-3.5 rounded-full object-contain" />
                                        {{ p.brand.name }}
                                    </span>
                                    <span v-else class="text-xs text-gray-400">—</span>
                                </td>
                                <td class="px-5 py-3">
                                    <div class="font-bold text-gray-900 dark:text-white">₦{{ parseFloat(p.price).toLocaleString() }}</div>
                                    <div class="text-xs text-gray-500 dark:text-gray-400">
                                        Cost: <span class="font-semibold text-gray-700 dark:text-gray-300">₦{{ parseFloat(p.cost_price || 0).toLocaleString() }}</span>
                                    </div>
                                    <div v-if="p.discount_price" class="text-xs text-red-400 line-through">₦{{ parseFloat(p.discount_price).toLocaleString() }}</div>
                                    <div v-if="p.special_price" class="text-xs text-secondary font-semibold">Special: ₦{{ parseFloat(p.special_price).toLocaleString() }}</div>
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
                                            :class="p.status ? 'theme-primary-badge' : 'bg-gray-100 text-gray-500'">
                                            {{ p.status ? 'Active' : 'Inactive' }}
                                        </span>
                                    </div>
                                </td>
                                <td class="px-5 py-3 text-right whitespace-nowrap">
                                    <button
                                        v-if="p.inventory_source === 'ecommerce'"
                                        @click="openStockAdjustModal(p)"
                                        class="text-emerald-600 hover:text-emerald-800 dark:text-emerald-400 font-semibold text-xs mr-3 transition-colors"
                                        title="Adjust manual inventory count"
                                    >
                                        Adjust Stock
                                    </button>
                                    <button @click="openEditForm(p)" class="text-primary hover:opacity-80 font-semibold text-sm mr-3">Edit</button>
                                    <button @click="deleteProduct(p.id)" class="text-red-500 hover:text-red-700 font-medium text-sm">Delete</button>
                                </td>
                            </tr>
                            <tr v-if="!products.data?.length">
                                <td colspan="8" class="px-6 py-12 text-center text-gray-400">No products yet. Click "Add Product" to get started.</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <div class="px-5 py-3 border-t dark:border-gray-700 flex flex-col sm:flex-row items-center justify-between gap-4">
                    <div class="text-xs text-gray-500 dark:text-gray-400">
                        Showing <span class="font-semibold text-gray-700 dark:text-gray-200">{{ products.from || 0 }}</span> to <span class="font-semibold text-gray-700 dark:text-gray-200">{{ products.to || 0 }}</span> of <span class="font-semibold text-gray-700 dark:text-gray-200">{{ products.total || 0 }}</span> products
                        <span v-if="perPage === 'all'" class="ml-2 inline-flex items-center px-2 py-0.5 rounded text-[10px] font-semibold theme-primary-badge">
                            Showing All Records
                        </span>
                    </div>
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
                            <span class="w-6 h-6 bg-primary-soft text-primary rounded flex items-center justify-center text-sm">📋</span>
                            General Information
                        </h3>
                        <div class="space-y-4">
                            <div>
                                <label class="block text-sm font-semibold mb-1">Product Title *</label>
                                <input v-model="form.name" type="text" required placeholder="e.g. iPhone 16 Pro Max"
                                    class="w-full h-11 px-3.5 py-2.5 text-sm rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 focus:ring-primary shadow-sm"/>
                                <p v-if="form.errors.name" class="text-red-500 text-xs mt-1">{{ form.errors.name }}</p>
                            </div>

                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-sm font-semibold mb-1">Category *</label>
                                    <div class="relative">
                                        <select v-model="form.category_id" required
                                            class="w-full h-11 appearance-none pl-3.5 pr-10 py-2.5 text-sm rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 focus:ring-2 focus:ring-primary focus:border-primary shadow-xs cursor-pointer">
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
                                        <div class="pointer-events-none absolute inset-y-0 right-0 flex items-center pr-3.5 text-gray-400">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                                            </svg>
                                        </div>
                                    </div>
                                    <p v-if="form.errors.category_id" class="text-red-500 text-xs mt-1">{{ form.errors.category_id }}</p>
                                </div>
                                <div>
                                    <label class="block text-sm font-semibold mb-1 flex items-center justify-between">
                                        <span>URL Key *</span>
                                        <span class="font-normal text-xs text-gray-400">— used for direct product link</span>
                                    </label>
                                    <div class="relative">
                                        <span class="absolute inset-y-0 left-3 flex items-center text-gray-400 text-xs pointer-events-none font-mono">/product/</span>
                                        <input v-model="form.url_key" type="text" required placeholder="iphone-16-pro-max"
                                            class="w-full h-11 pl-16 px-3.5 py-2.5 rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 focus:ring-primary shadow-sm font-mono text-sm"/>
                                    </div>
                                    <p v-if="form.errors.url_key" class="text-red-500 text-xs mt-1">{{ form.errors.url_key }}</p>
                                </div>
                            </div>

                            <!-- Brand Selector + Quick Add -->
                            <div ref="brandDropdownRef" class="relative">
                                <div class="flex items-center justify-between mb-1">
                                    <label class="block text-sm font-semibold">
                                        Brand <span class="font-normal text-gray-400">(optional)</span>
                                    </label>
                                    <button
                                        type="button"
                                        @click="showQuickAddBrand = !showQuickAddBrand"
                                        class="text-xs font-semibold text-primary hover:opacity-80 flex items-center gap-1"
                                    >
                                        <span>{{ showQuickAddBrand ? '✕ Cancel' : '+ New Brand' }}</span>
                                    </button>
                                </div>

                                <!-- Quick-add inline form -->
                                <div v-if="showQuickAddBrand" class="mb-2.5 p-3 rounded-xl bg-gray-50 dark:bg-gray-900/60 border border-gray-200 dark:border-gray-700 flex items-center gap-2">
                                    <input
                                        v-model="quickBrandName"
                                        type="text"
                                        placeholder="Enter new brand name (e.g. Samsung, Nike)..."
                                        @keydown.enter.prevent="createQuickBrand"
                                        class="flex-1 text-sm rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-800 px-3 py-1.5 focus:ring-primary"
                                    />
                                    <button
                                        type="button"
                                        @click="createQuickBrand"
                                        :disabled="!quickBrandName.trim() || quickBrandSaving"
                                        class="px-3.5 py-1.5 rounded-lg bg-primary text-white text-xs font-bold shadow hover:bg-primary-dark disabled:opacity-50"
                                    >
                                        {{ quickBrandSaving ? 'Adding...' : 'Create & Select' }}
                                    </button>
                                </div>

                                <!-- Searchable Brand Trigger -->
                                <div
                                    @click="toggleBrandDropdown"
                                    class="w-full h-11 text-sm py-2.5 px-3.5 border rounded-lg bg-white dark:bg-gray-700 cursor-pointer flex items-center justify-between gap-2 transition shadow-xs"
                                    :class="brandDropdownOpen ? 'border-primary ring-2 ring-primary/20' : 'border-gray-300 dark:border-gray-600 hover:border-gray-400'"
                                >
                                    <div v-if="selectedBrandObj" class="flex items-center gap-2 min-w-0">
                                        <img v-if="selectedBrandObj.logo_url" :src="selectedBrandObj.logo_url" class="w-5 h-5 rounded object-contain bg-white border dark:border-gray-600" />
                                        <span v-else class="text-sm">🏷️</span>
                                        <span class="font-semibold text-gray-900 dark:text-white truncate">{{ selectedBrandObj.name }}</span>
                                        <span v-if="selectedBrandObj.pos_brand_id" class="text-[10px] font-bold px-1.5 py-0.5 rounded theme-primary-badge">POS #{{ selectedBrandObj.pos_brand_id }}</span>
                                    </div>
                                    <span v-else class="text-gray-400 dark:text-gray-400">— Select or search brand (optional) —</span>

                                    <div class="flex items-center gap-1.5 shrink-0">
                                        <button
                                            v-if="form.brand_id"
                                            type="button"
                                            @click.stop="clearBrandSelection"
                                            class="p-1 rounded-md text-gray-400 hover:text-rose-500 hover:bg-gray-100 dark:hover:bg-gray-600 transition"
                                            title="Clear brand"
                                        >
                                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                                        </button>
                                        <svg class="w-4 h-4 text-gray-400 transition-transform duration-150" :class="brandDropdownOpen ? 'rotate-180 text-primary' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                                        </svg>
                                    </div>
                                </div>

                                <!-- Searchable Brand Dropdown Panel -->
                                <div
                                    v-if="brandDropdownOpen"
                                    class="absolute left-0 right-0 z-40 mt-1.5 bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-xl shadow-2xl overflow-hidden"
                                >
                                    <div class="p-2.5 border-b border-gray-200 dark:border-gray-700 bg-gray-50/70 dark:bg-gray-900/50">
                                        <input
                                            ref="brandSearchInputRef"
                                            v-model="brandSearchQuery"
                                            type="text"
                                            placeholder="Search brands by name..."
                                            @keydown.escape.stop="brandDropdownOpen = false"
                                            class="w-full text-sm px-3 py-1.5 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-900 text-gray-900 dark:text-gray-100 focus:ring-2 focus:ring-primary"
                                        />
                                        <div class="flex items-center justify-between mt-1 px-1 text-[11px] text-gray-500 dark:text-gray-400">
                                            <span>{{ filteredBrandsAll.length }} matching brand(s)</span>
                                            <span v-if="filteredBrandsAll.length > 25">Showing first 25 — type to refine</span>
                                        </div>
                                    </div>
                                    <div class="max-h-60 overflow-y-auto divide-y divide-gray-100 dark:divide-gray-700/60">
                                        <button
                                            type="button"
                                            @click="selectBrandOption(null)"
                                            class="w-full text-left px-3.5 py-2 text-xs text-gray-500 hover:bg-gray-50 dark:hover:bg-gray-700/60"
                                        >
                                            — No Brand —
                                        </button>
                                        <button
                                            v-for="b in filteredBrands"
                                            :key="b.id"
                                            type="button"
                                            @click="selectBrandOption(b)"
                                            class="w-full text-left px-3.5 py-2 flex items-center justify-between gap-2 transition"
                                            :class="Number(form.brand_id) === Number(b.id) ? 'bg-primary-soft text-primary font-semibold' : 'hover:bg-gray-50 dark:hover:bg-gray-700/60 text-gray-800 dark:text-gray-200'"
                                        >
                                            <div class="flex items-center gap-2 min-w-0">
                                                <img v-if="b.logo_url" :src="b.logo_url" class="w-5 h-5 rounded object-contain bg-white border dark:border-gray-600" />
                                                <span v-else class="text-xs">🏷️</span>
                                                <span class="text-sm truncate">{{ b.name }}</span>
                                            </div>
                                            <span v-if="b.pos_brand_id" class="text-[10px] px-1.5 py-0.5 rounded theme-primary-badge shrink-0">POS #{{ b.pos_brand_id }}</span>
                                        </button>
                                        <div v-if="!filteredBrands.length" class="p-4 text-center text-xs text-gray-400">
                                            No brands match "{{ brandSearchQuery }}". Click "+ New Brand" above to create it.
                                        </div>
                                    </div>
                                </div>

                                <p v-if="form.inventory_source === 'pos' && selectedPosItem?.pos_brand_name" class="text-xs text-primary mt-1.5 flex items-center gap-1">
                                    <span>🏷️ POS Brand: <strong>{{ selectedPosItem.pos_brand_name }}</strong> (auto-matched/created on save if left empty)</span>
                                </p>
                                <p v-if="form.errors.brand_id" class="text-red-500 text-xs mt-1">{{ form.errors.brand_id }}</p>
                            </div>

                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-sm font-semibold mb-1">Product Number <span class="font-normal text-gray-400">(optional)</span></label>
                                    <input v-model="form.product_number" type="text" placeholder="e.g. PRD-001"
                                        class="w-full h-11 px-3.5 py-2.5 rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 focus:ring-primary shadow-sm font-mono text-sm"/>
                                    <p v-if="form.errors.product_number" class="text-red-500 text-xs mt-1">{{ form.errors.product_number }}</p>
                                </div>
                                <div>
                                    <label class="block text-sm font-semibold mb-1">SKU <span class="font-normal text-gray-400">(optional)</span></label>
                                    <input v-model="form.sku" type="text" placeholder="e.g. APPLE-IP16-PRO"
                                        class="w-full h-11 px-3.5 py-2.5 rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 focus:ring-primary shadow-sm font-mono text-sm"/>
                                    <p v-if="form.errors.sku" class="text-red-500 text-xs mt-1">{{ form.errors.sku }}</p>
                                </div>
                            </div>

                            <div>
                                <div class="flex items-center justify-between mb-1">
                                    <label class="block text-sm font-semibold">Short Description <span class="font-normal text-gray-400">(optional)</span></label>
                                    <button
                                        v-if="isAiEnabled"
                                        type="button"
                                        @click="generateAiDescription('short')"
                                        :disabled="aiGenerating.short"
                                        class="inline-flex items-center gap-1.5 px-2.5 py-1 text-xs font-semibold rounded-lg bg-purple-50 hover:bg-purple-100 text-purple-700 dark:bg-purple-950/40 dark:text-purple-300 dark:hover:bg-purple-900/60 border border-purple-200 dark:border-purple-800 transition disabled:opacity-50"
                                        title="Generate punchy 1-2 sentence plain text short description using AI"
                                    >
                                        <svg v-if="aiGenerating.short" class="animate-spin h-3.5 w-3.5 text-purple-600" viewBox="0 0 24 24" fill="none"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path></svg>
                                        <span v-else>✨</span>
                                        <span>{{ aiGenerating.short ? 'Generating...' : 'AI Generate' }}</span>
                                    </button>
                                </div>
                                <textarea v-model="form.short_description" rows="2" placeholder="Brief summary of the product..."
                                    class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 focus:ring-primary shadow-sm resize-none"></textarea>
                            </div>

                            <div>
                                <div class="flex items-center justify-between mb-1">
                                    <label class="block text-sm font-semibold">Full Description</label>
                                    <button
                                        v-if="isAiEnabled"
                                        type="button"
                                        @click="generateAiDescription('full')"
                                        :disabled="aiGenerating.full"
                                        class="inline-flex items-center gap-1.5 px-2.5 py-1 text-xs font-semibold rounded-lg bg-purple-50 hover:bg-purple-100 text-purple-700 dark:bg-purple-950/40 dark:text-purple-300 dark:hover:bg-purple-900/60 border border-purple-200 dark:border-purple-800 transition disabled:opacity-50"
                                        title="Generate product description and details using AI"
                                    >
                                        <svg v-if="aiGenerating.full" class="animate-spin h-3.5 w-3.5 text-purple-600" viewBox="0 0 24 24" fill="none"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path></svg>
                                        <span v-else>✨</span>
                                        <span>{{ aiGenerating.full ? 'Generating Description...' : 'Generate AI Description' }}</span>
                                    </button>
                                </div>
                                <div class="bg-white dark:bg-gray-700 rounded-lg overflow-hidden border border-gray-300 dark:border-gray-600">
                                    <QuillEditor ref="fullDescQuillRef" v-model:content="form.description" contentType="html" theme="snow" class="min-h-[200px]" />
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
                                <div v-if="attr.type === 'select'" class="relative">
                                    <select v-model="form.attributes[attr.id]"
                                        class="w-full h-10 appearance-none pl-3 pr-9 py-2 rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 focus:ring-2 focus:ring-yellow-500 shadow-xs text-sm cursor-pointer">
                                        <option value="">Choose an option...</option>
                                        <option v-for="opt in attr.options" :key="opt" :value="opt">{{ opt }}</option>
                                    </select>
                                    <div class="pointer-events-none absolute inset-y-0 right-0 flex items-center pr-3 text-gray-400">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                                        </svg>
                                    </div>
                                </div>
                                
                                <!-- Color Picker (Swatches) -->
                                <div v-else-if="attr.type === 'color'" class="flex flex-wrap gap-2">
                                    <button v-for="opt in attr.options" :key="opt"
                                        @click.prevent="form.attributes[attr.id] = (form.attributes[attr.id] === opt ? '' : opt)"
                                        class="w-7 h-7 rounded-full border-2 transition-transform duration-200"
                                        :class="form.attributes[attr.id] === opt ? 'scale-110 border-primary shadow-md ring-2 ring-primary' : 'border-gray-200 dark:border-gray-600 hover:scale-110'"
                                        :style="{ backgroundColor: opt.startsWith('#') ? opt : opt.toLowerCase().replace(/\s+/g, '') }"
                                        :title="opt">
                                    </button>
                                </div>
                                
                                <!-- Default Text Wrapper -->
                                <input v-else type="text" v-model="form.attributes[attr.id]" :placeholder="`Enter ${attr.name}...`"
                                    class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 focus:ring-primary shadow-sm text-sm" />
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
                                    :class="form.inventory_source === 'pos' ? 'border-primary bg-primary-soft' : 'border-gray-200 dark:border-gray-700'"
                                >
                                    <input type="radio" value="pos" v-model="form.inventory_source" class="text-primary focus:ring-primary" />
                                    <div class="ml-3">
                                        <div class="text-sm font-semibold text-gray-900 dark:text-white">Connected POS Item</div>
                                        <div class="text-xs text-gray-500">Physical stock & standard price synced from POS</div>
                                    </div>
                                </label>
                                <label
                                    class="flex items-center p-3 rounded-lg border cursor-pointer transition"
                                    :class="form.inventory_source === 'ecommerce' ? 'border-primary bg-primary-soft' : 'border-gray-200 dark:border-gray-700'"
                                >
                                    <input type="radio" value="ecommerce" v-model="form.inventory_source" class="text-primary focus:ring-primary" />
                                    <div class="ml-3">
                                        <div class="text-sm font-semibold text-gray-900 dark:text-white">Ecommerce Only Item</div>
                                        <div class="text-xs text-gray-500">Independent local stock & pricing</div>
                                    </div>
                                </label>
                            </div>

                            <!-- POS Item Picker if POS is selected -->
                            <div v-if="form.inventory_source === 'pos'" ref="posDropdownRef" class="mt-4 pt-3 border-t border-gray-200 dark:border-gray-700 relative">
                                <label class="block text-sm font-semibold mb-1">Select Synced POS Item *</label>

                                <!-- Trigger Button -->
                                <div
                                    @click="togglePosDropdown"
                                    class="w-full text-sm py-2.5 px-3 border rounded-lg bg-white dark:bg-gray-800 cursor-pointer flex items-center justify-between gap-2 transition shadow-xs"
                                    :class="posDropdownOpen ? 'border-primary ring-2 ring-primary/20' : 'border-gray-300 dark:border-gray-600 hover:border-gray-400 dark:hover:border-gray-500'"
                                >
                                    <div v-if="selectedPosItem" class="flex-1 min-w-0 flex flex-wrap items-center gap-2">
                                        <span class="text-[10px] font-mono font-bold px-1.5 py-0.5 rounded theme-primary-badge shrink-0">
                                            #{{ selectedPosItem.pos_item_id }}
                                        </span>
                                        <span class="font-semibold text-gray-900 dark:text-white truncate">
                                            {{ selectedPosItem.pos_item_name }}
                                        </span>
                                        <span class="text-xs text-gray-500 dark:text-gray-400 shrink-0">
                                            (Stock: {{ Math.round(Number(selectedPosItem.effective_availability || 0)) }} {{ selectedPosItem.base_unit_name }} | ₦{{ Number(selectedPosItem.standard_price).toLocaleString() }})
                                        </span>
                                    </div>
                                    <span v-else-if="form.pos_item_id" class="text-gray-700 dark:text-gray-200 font-medium">
                                        POS Item #{{ form.pos_item_id }}
                                    </span>
                                    <span v-else class="text-gray-400 dark:text-gray-500">
                                        -- Search or choose an item from connected POS branch --
                                    </span>

                                    <div class="flex items-center gap-1.5 shrink-0">
                                        <button
                                            v-if="form.pos_item_id"
                                            type="button"
                                            @click.stop="clearPosItem"
                                            class="p-1 rounded-md text-gray-400 hover:text-rose-500 hover:bg-gray-100 dark:hover:bg-gray-700 transition"
                                            title="Clear selected POS item"
                                        >
                                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                                        </button>
                                        <svg class="w-4 h-4 text-gray-400 transition-transform duration-150" :class="posDropdownOpen ? 'rotate-180 text-primary' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                                        </svg>
                                    </div>
                                </div>

                                <!-- Searchable Dropdown Panel -->
                                <div
                                    v-if="posDropdownOpen"
                                    class="absolute left-0 right-0 z-40 mt-1.5 bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-xl shadow-2xl overflow-hidden"
                                >
                                    <!-- Search Input Header (matches POS All Items search bar) -->
                                    <div class="p-2.5 border-b border-gray-200 dark:border-gray-700 bg-gray-50/70 dark:bg-gray-900/50">
                                        <div class="relative">
                                            <input
                                                ref="posSearchInputRef"
                                                v-model="posSearchQuery"
                                                type="text"
                                                placeholder="Search by name, barcode, SKU, category, or POS #ID..."
                                                @keydown.escape.stop="posDropdownOpen = false"
                                                class="w-full text-sm pl-9 pr-8 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-900 text-gray-900 dark:text-gray-100 focus:ring-2 focus:ring-primary"
                                            />
                                            <svg class="w-4 h-4 text-gray-400 absolute left-3 top-2.5 pointer-events-none" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                                            </svg>
                                            <button
                                                v-if="posSearchQuery"
                                                type="button"
                                                @click="posSearchQuery = ''; posSearchInputRef?.focus()"
                                                class="absolute right-2.5 top-2.5 text-gray-400 hover:text-gray-600 dark:hover:text-gray-200"
                                            >
                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                                            </button>
                                        </div>
                                        <div class="flex items-center justify-between mt-1.5 px-1 text-[11px] text-gray-500 dark:text-gray-400">
                                            <span>{{ filteredPosItemsAll.length.toLocaleString() }} matching POS item(s)</span>
                                            <span v-if="filteredPosItemsAll.length > 25">Showing first 25 — type to refine</span>
                                        </div>
                                    </div>

                                    <!-- Options List -->
                                    <div class="max-h-72 overflow-y-auto divide-y divide-gray-100 dark:divide-gray-700/60">
                                        <button
                                            v-for="item in filteredPosItems"
                                            :key="item.pos_item_id"
                                            type="button"
                                            @click="selectPosItem(item)"
                                            class="w-full text-left px-3.5 py-2.5 flex items-center justify-between gap-3 transition"
                                            :class="Number(form.pos_item_id) === Number(item.pos_item_id) ? 'bg-primary-soft text-primary' : 'hover:bg-gray-50 dark:hover:bg-gray-700/60 text-gray-800 dark:text-gray-200'"
                                        >
                                            <div class="min-w-0 flex-1">
                                                <div class="flex items-center gap-2 flex-wrap">
                                                    <span class="font-mono text-xs text-gray-500 dark:text-gray-400">#{{ item.pos_item_id }}</span>
                                                    <span class="font-semibold text-sm text-gray-900 dark:text-white truncate">{{ item.pos_item_name }}</span>
                                                    <span v-if="item.pos_category_name" class="text-[10px] px-1.5 py-0.5 rounded theme-primary-badge">
                                                        {{ item.pos_category_name }}
                                                    </span>
                                                </div>
                                                <div class="text-xs text-gray-500 dark:text-gray-400 mt-0.5 flex flex-wrap items-center gap-3">
                                                    <span v-if="item.sku">SKU: <strong class="font-mono">{{ item.sku }}</strong></span>
                                                    <span v-if="item.barcode">Bar: <strong class="font-mono">{{ item.barcode }}</strong></span>
                                                    <span v-if="item.mapping_status && item.mapping_status !== 'unmapped'" class="text-[10px] font-medium text-amber-600 dark:text-amber-400">
                                                        ● Already Mapped
                                                    </span>
                                                </div>
                                            </div>
                                            <div class="text-right shrink-0">
                                                <div class="text-sm font-bold text-emerald-600 dark:text-emerald-400">
                                                    ₦{{ Number(item.standard_price).toLocaleString('en-US', { minimumFractionDigits: 2 }) }}
                                                </div>
                                                <div
                                                    class="text-[11px] font-semibold"
                                                    :class="Number(item.effective_availability) > 0 ? 'text-gray-600 dark:text-gray-300' : 'text-rose-500'"
                                                >
                                                    {{ Math.round(Number(item.effective_availability || 0)) }} {{ item.base_unit_name }}
                                                </div>
                                            </div>
                                        </button>

                                        <div v-if="!filteredPosItems.length" class="p-6 text-center text-sm text-gray-400">
                                            No synced POS items match "{{ posSearchQuery }}".
                                        </div>
                                    </div>
                                </div>

                                <p v-if="form.errors.pos_item_id" class="text-red-500 text-xs mt-1">{{ form.errors.pos_item_id }}</p>
                                <p v-if="form.pos_item_id" class="text-xs text-primary mt-1.5 flex items-center">
                                    <svg class="w-3.5 h-3.5 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                    Linked to POS item #{{ form.pos_item_id }}. Base price & stock are controlled by POS.
                                </p>
                            </div>
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4.5 mb-5">
                            <div>
                                <label class="block text-sm font-semibold mb-1 flex items-center justify-between">
                                    <span>Base Price (₦) *</span>
                                    <span v-if="form.inventory_source === 'pos'" class="text-[10px] font-bold text-primary bg-primary/10 px-1.5 py-0.5 rounded">Synced from POS</span>
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
                                    class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 focus:ring-primary shadow-sm"
                                />
                                <p v-if="form.errors.price" class="text-red-500 text-xs mt-1">{{ form.errors.price }}</p>
                            </div>
                            <div>
                                <label class="block text-sm font-semibold mb-1">
                                    Discount Price (₦)
                                    <span class="text-xs font-normal text-gray-400 ml-1">(Optional)</span>
                                </label>
                                <input v-model="form.discount_price" type="number" step="0.01" min="0" placeholder="Optional sale price"
                                    class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 focus:ring-primary shadow-sm"/>
                                <p v-if="form.errors.discount_price" class="text-red-500 text-xs mt-1">{{ form.errors.discount_price }}</p>
                            </div>
                            <div>
                                <label class="block text-sm font-semibold mb-1 flex items-center justify-between">
                                    <span>Cost Price (₦) <span class="text-xs font-normal text-gray-400 ml-1">(Internal COGS)</span></span>
                                    <span v-if="form.inventory_source === 'pos'" class="text-[10px] font-bold text-primary bg-primary/10 px-1.5 py-0.5 rounded">Synced from POS</span>
                                </label>
                                <input
                                    v-model="form.cost_price"
                                    type="number"
                                    step="0.01"
                                    min="0"
                                    placeholder="e.g. 5000"
                                    :readonly="form.inventory_source === 'pos'"
                                    :class="form.inventory_source === 'pos' ? 'bg-gray-100 dark:bg-gray-800/80 cursor-not-allowed text-gray-500 font-mono' : ''"
                                    class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 focus:ring-primary shadow-sm"
                                />
                                <p v-if="form.errors.cost_price" class="text-red-500 text-xs mt-1">{{ form.errors.cost_price }}</p>
                                <p v-if="form.inventory_source === 'pos'" class="text-[11px] text-gray-400 mt-1">
                                    Buying cost synced from POS branch inventory. Read-only.
                                </p>
                            </div>
                            <div>
                                <label class="block text-sm font-semibold mb-1 flex items-center justify-between">
                                    <span>Stock Qty *</span>
                                    <span v-if="form.inventory_source === 'pos'" class="text-[10px] font-bold text-primary bg-primary/10 px-1.5 py-0.5 rounded">Synced from POS</span>
                                </label>
                                <input
                                    v-model="form.stock"
                                    type="number"
                                    min="0"
                                    required
                                    placeholder="0"
                                    :readonly="form.inventory_source === 'pos'"
                                    :class="form.inventory_source === 'pos' ? 'bg-gray-100 dark:bg-gray-800/80 cursor-not-allowed text-gray-500' : ''"
                                    class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 focus:ring-primary shadow-sm"
                                />
                                <p v-if="form.errors.stock" class="text-red-500 text-xs mt-1">{{ form.errors.stock }}</p>
                            </div>
                        </div>

                        <!-- Special Price Section -->
                        <div class="bg-secondary-soft border border-secondary-soft rounded-xl p-4">
                            <p class="text-sm font-bold text-secondary mb-3">⚡ Special / Flash Price (time-limited)</p>
                            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                                <div>
                                    <label class="block text-xs font-semibold mb-1 text-gray-600 dark:text-gray-400">Special Price (₦)</label>
                                    <input v-model="form.special_price" type="number" step="0.01" min="0" placeholder="e.g. 149000"
                                        class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 focus:ring-primary shadow-sm text-sm"/>
                                </div>
                                <div>
                                    <label class="block text-xs font-semibold mb-1 text-gray-600 dark:text-gray-400">Active From</label>
                                    <input v-model="form.special_price_from" type="date"
                                        class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 focus:ring-primary shadow-sm text-sm"/>
                                </div>
                                <div>
                                    <label class="block text-xs font-semibold mb-1 text-gray-600 dark:text-gray-400">Active Until</label>
                                    <input v-model="form.special_price_to" type="date"
                                        class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 focus:ring-primary shadow-sm text-sm"/>
                                </div>
                            </div>
                            <p class="text-xs text-secondary mt-2 opacity-75">Leave dates empty for an always-active special price.</p>
                        </div>
                    </div>

                    <!-- Product Variants -->
                    <div class="bg-white dark:bg-gray-800 p-6 rounded-xl shadow-sm border dark:border-gray-700">
                        <div class="flex items-center justify-between mb-4 flex-wrap gap-2">
                            <h3 class="text-base font-bold flex items-center gap-2">
                                <span class="w-6 h-6 bg-primary-soft text-primary rounded flex items-center justify-center text-sm">🎛️</span>
                                Product Variants
                            </h3>
                            <div class="flex items-center gap-2">
                                <button
                                    v-if="form.inventory_source === 'pos' && form.pos_item_id && !isBasePosItemInVariants"
                                    @click.prevent="includeBasePosItemAsVariant"
                                    type="button"
                                    class="text-xs bg-indigo-50 hover:bg-indigo-100 dark:bg-indigo-900/40 dark:hover:bg-indigo-900/60 text-indigo-700 dark:text-indigo-300 border border-indigo-200 dark:border-indigo-700 px-3 py-1.5 rounded-lg font-semibold transition flex items-center gap-1"
                                    title="Include this product's base POS item as the first selectable variant option"
                                >
                                    <span>🏬</span> + Include Base POS Item as Variant #1
                                </button>
                                <button @click.prevent="addVariant"
                                    class="text-sm bg-primary-soft hover:bg-primary-soft text-primary px-3 py-1.5 rounded-lg font-semibold transition flex items-center gap-1">
                                    + Add Variant
                                </button>
                            </div>
                        </div>
                        <p class="text-xs text-gray-500 dark:text-gray-400 mb-4">Add variations (Color, Size, Wattage…) with individual prices and stock counts.</p>
                        <div class="space-y-4">
                            <div v-for="(v, i) in form.variants" :key="i"
                                class="bg-gray-50 dark:bg-gray-900/50 p-4 rounded-xl border dark:border-gray-700 relative group space-y-3">
                                <button @click.prevent="removeVariant(i)"
                                    type="button"
                                    class="absolute -top-2 -right-2 bg-red-100 hover:bg-red-200 dark:bg-red-900/60 dark:hover:bg-red-900 text-red-600 dark:text-red-300 rounded-full p-1.5 shadow transition z-10"
                                    title="Remove Variant">
                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                                </button>

                                <div class="grid grid-cols-1 md:grid-cols-12 gap-3 items-start">
                                    <div class="md:col-span-6">
                                        <label class="block text-xs font-semibold text-gray-600 dark:text-gray-400 mb-1">Variant Name / Option</label>
                                        <input v-model="v.name" type="text" placeholder="e.g. Red / Large, 128GB Blue" class="w-full h-10 px-3 py-2 text-sm rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 focus:ring-primary shadow-xs"/>
                                    </div>
                                    <div class="md:col-span-6">
                                        <label class="block text-xs font-semibold text-gray-600 dark:text-gray-400 mb-1">Inventory Source</label>
                                        <div class="relative">
                                            <select
                                                v-model="v.inventory_source"
                                                @change="handleVariantSourceChange(v)"
                                                class="w-full h-10 appearance-none pl-3 pr-9 py-2 text-sm rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 font-medium focus:ring-2 focus:ring-primary focus:border-primary shadow-xs cursor-pointer">
                                                <option value="ecommerce">Online Warehouse (Ecommerce Only)</option>
                                                <option value="pos">Physical POS Branch Item</option>
                                            </select>
                                            <div class="pointer-events-none absolute inset-y-0 right-0 flex items-center pr-3 text-gray-400">
                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                                                </svg>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- POS Item Selector (if inventory_source === 'pos') -->
                                <div v-if="v.inventory_source === 'pos'" class="p-3 bg-indigo-50/70 dark:bg-indigo-900/20 border border-indigo-200 dark:border-indigo-800 rounded-lg space-y-2 relative variant-pos-dropdown-container">
                                    <div class="flex items-center justify-between">
                                        <label class="block text-xs font-bold text-indigo-900 dark:text-indigo-300 flex items-center gap-1.5">
                                            <span>🏬</span> Linked POS Inventory Item
                                        </label>
                                        <span v-if="getPosItemDetails(v.pos_item_id)" class="text-[11px] font-bold text-indigo-700 dark:text-indigo-300">
                                            Branch Stock: {{ getPosItemDetails(v.pos_item_id).effective_availability }} {{ getPosItemDetails(v.pos_item_id).base_unit_name || 'Units' }}
                                        </span>
                                    </div>

                                    <!-- Searchable Trigger Button -->
                                    <div
                                        @click="toggleVariantPosDropdown(i)"
                                        class="w-full text-sm py-2 px-3 border rounded-lg bg-white dark:bg-gray-800 cursor-pointer flex items-center justify-between gap-2 transition shadow-2xs"
                                        :class="openVariantDropdownIndex === i ? 'border-indigo-500 ring-2 ring-indigo-500/20' : 'border-indigo-300 dark:border-indigo-700 hover:border-indigo-400 dark:hover:border-indigo-500'"
                                    >
                                        <div v-if="getPosItemDetails(v.pos_item_id)" class="flex-1 min-w-0 flex flex-wrap items-center gap-2">
                                            <span class="text-[10px] font-mono font-bold px-1.5 py-0.5 rounded bg-indigo-100 text-indigo-800 dark:bg-indigo-900/60 dark:text-indigo-300 shrink-0">
                                                #{{ getPosItemDetails(v.pos_item_id).pos_item_id }}
                                            </span>
                                            <span class="font-semibold text-gray-900 dark:text-white truncate">
                                                {{ getPosItemDetails(v.pos_item_id).pos_item_name }}
                                            </span>
                                            <span class="text-xs text-gray-500 dark:text-gray-400 shrink-0">
                                                (Stock: {{ Math.round(Number(getPosItemDetails(v.pos_item_id).effective_availability || 0)) }} {{ getPosItemDetails(v.pos_item_id).base_unit_name || 'Units' }} | ₦{{ Number(getPosItemDetails(v.pos_item_id).standard_price || 0).toLocaleString() }})
                                            </span>
                                        </div>
                                        <span v-else-if="v.pos_item_id" class="text-gray-700 dark:text-gray-200 font-medium text-xs">
                                            POS Item #{{ v.pos_item_id }}
                                        </span>
                                        <span v-else class="text-gray-400 dark:text-gray-500 text-xs">
                                            -- Search or select POS Item to link --
                                        </span>

                                        <div class="flex items-center gap-1.5 shrink-0">
                                            <button
                                                v-if="v.pos_item_id"
                                                type="button"
                                                @click.stop="clearVariantPosItem(v)"
                                                class="p-1 rounded-md text-gray-400 hover:text-rose-500 hover:bg-gray-100 dark:hover:bg-gray-700 transition"
                                                title="Clear selected POS item"
                                            >
                                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                                            </button>
                                            <svg class="w-4 h-4 text-indigo-400 transition-transform duration-150" :class="openVariantDropdownIndex === i ? 'rotate-180 text-indigo-600' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                                            </svg>
                                        </div>
                                    </div>

                                    <!-- Searchable Dropdown Popup -->
                                    <div
                                        v-if="openVariantDropdownIndex === i"
                                        class="absolute left-0 right-0 z-50 mt-1 bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-xl shadow-2xl overflow-hidden"
                                    >
                                        <!-- Search Input Header -->
                                        <div class="p-2 border-b border-gray-200 dark:border-gray-700 bg-gray-50/70 dark:bg-gray-900/50">
                                            <div class="relative">
                                                <input
                                                    ref="variantPosSearchInputRef"
                                                    v-model="variantPosSearchQuery"
                                                    type="text"
                                                    placeholder="Search by name, barcode, SKU, category, or POS #ID..."
                                                    @keydown.escape.stop="openVariantDropdownIndex = null"
                                                    class="w-full text-xs pl-8 pr-8 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-900 text-gray-900 dark:text-gray-100 focus:ring-2 focus:ring-indigo-500"
                                                />
                                                <svg class="w-3.5 h-3.5 text-gray-400 absolute left-2.5 top-2.5 pointer-events-none" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                                                </svg>
                                                <button
                                                    v-if="variantPosSearchQuery"
                                                    type="button"
                                                    @click="variantPosSearchQuery = ''; variantPosSearchInputRef?.focus()"
                                                    class="absolute right-2.5 top-2 text-gray-400 hover:text-gray-600 dark:hover:text-gray-200"
                                                >
                                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                                                </button>
                                            </div>
                                            <div class="flex items-center justify-between mt-1 px-1 text-[11px] text-gray-500 dark:text-gray-400">
                                                <span>{{ filteredVariantPosItemsAll.length.toLocaleString() }} matching item(s)</span>
                                                <span v-if="filteredVariantPosItemsAll.length > 25">Showing first 25 — type to refine</span>
                                            </div>
                                        </div>

                                        <!-- Options List -->
                                        <div class="max-h-60 overflow-y-auto divide-y divide-gray-100 dark:divide-gray-700/60">
                                            <div
                                                v-if="filteredVariantPosItems.length === 0"
                                                class="p-4 text-center text-xs text-gray-400 dark:text-gray-500"
                                            >
                                                No POS items found matching "{{ variantPosSearchQuery }}".
                                            </div>
                                            <button
                                                v-for="item in filteredVariantPosItems"
                                                :key="item.pos_item_id"
                                                type="button"
                                                @click="selectVariantPosItemOption(v, item)"
                                                class="w-full text-left p-2.5 hover:bg-indigo-50/70 dark:hover:bg-indigo-900/30 transition flex items-center justify-between gap-3 text-xs"
                                                :class="Number(v.pos_item_id) === Number(item.pos_item_id) ? 'bg-indigo-50 dark:bg-indigo-950/40 font-bold' : ''"
                                            >
                                                <div class="min-w-0 flex-1 space-y-0.5">
                                                    <div class="flex items-center gap-1.5 flex-wrap">
                                                        <span class="font-mono text-[10px] font-bold px-1.5 py-0.2 rounded bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-300">
                                                            #{{ item.pos_item_id }}
                                                        </span>
                                                        <span class="font-semibold text-gray-900 dark:text-white truncate">
                                                            {{ item.pos_item_name }}
                                                        </span>
                                                        <span v-if="form.pos_item_id && Number(item.pos_item_id) === Number(form.pos_item_id)" class="text-[10px] px-1.5 py-0.2 rounded font-bold bg-blue-100 text-blue-800 dark:bg-blue-900/50 dark:text-blue-200">
                                                            Base Item
                                                        </span>
                                                        <span v-else-if="item.mapped_product && (!editId || Number(item.mapped_product.id) !== Number(editId))" class="text-[10px] px-1.5 py-0.2 rounded font-bold bg-amber-100 text-amber-800 dark:bg-amber-900/50 dark:text-amber-200">
                                                            Active Standalone
                                                        </span>
                                                        <span v-else-if="item.mapped_variant && (!editId || Number(item.mapped_variant.product_id) !== Number(editId))" class="text-[10px] px-1.5 py-0.2 rounded font-bold bg-purple-100 text-purple-800 dark:bg-purple-900/50 dark:text-purple-200">
                                                            Variant
                                                        </span>
                                                    </div>
                                                    <div class="text-[11px] text-gray-500 dark:text-gray-400 flex flex-wrap items-center gap-2">
                                                        <span v-if="item.sku">SKU: <code class="font-mono">{{ item.sku }}</code></span>
                                                        <span v-if="item.barcode">Barcode: <code class="font-mono">{{ item.barcode }}</code></span>
                                                        <span v-if="item.pos_category_name">Cat: {{ item.pos_category_name }}</span>
                                                    </div>
                                                </div>
                                                <div class="text-right shrink-0">
                                                    <div class="font-bold text-gray-900 dark:text-white">
                                                        ₦{{ Number(item.standard_price || 0).toLocaleString() }}
                                                    </div>
                                                    <div class="text-[11px]" :class="Number(item.effective_availability || 0) > 0 ? 'text-emerald-600 dark:text-emerald-400 font-semibold' : 'text-rose-500'">
                                                        Stock: {{ Math.round(Number(item.effective_availability || 0)) }} {{ item.base_unit_name || 'Units' }}
                                                    </div>
                                                </div>
                                            </button>
                                        </div>
                                    </div>

                                    <!-- Safeguard Prompt when selecting a POS item that is already an active standalone product -->
                                    <div
                                        v-if="getVariantStandaloneConflict(v.pos_item_id)"
                                        class="p-2.5 bg-amber-50 dark:bg-amber-950/40 border border-amber-300 dark:border-amber-700/80 rounded-lg text-xs space-y-2"
                                    >
                                        <div class="flex items-start gap-2 text-amber-900 dark:text-amber-200 font-semibold">
                                            <span>⚠️</span>
                                            <span>
                                                This POS item is currently active on the store as standalone product
                                                <strong class="underline">"{{ getVariantStandaloneConflict(v.pos_item_id).name }}"</strong>
                                                (#{{ getVariantStandaloneConflict(v.pos_item_id).id }}).
                                            </span>
                                        </div>
                                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-2 pt-0.5">
                                            <label
                                                class="flex items-start gap-2 p-2 rounded-md border cursor-pointer transition"
                                                :class="v.absorb_standalone !== false
                                                    ? 'bg-amber-100/80 dark:bg-amber-900/50 border-amber-400 dark:border-amber-600 text-amber-950 dark:text-amber-100 font-semibold'
                                                    : 'bg-white dark:bg-gray-800 border-gray-200 dark:border-gray-700 text-gray-600 dark:text-gray-300'"
                                            >
                                                <input
                                                    type="radio"
                                                    :name="`absorb_standalone_${i}`"
                                                    :value="true"
                                                    v-model="v.absorb_standalone"
                                                    class="mt-0.5 text-amber-600 focus:ring-amber-500"
                                                />
                                                <div>
                                                    <div>Consolidate into Variant (Recommended)</div>
                                                    <div class="text-[11px] font-normal opacity-80">
                                                        Removes duplicate standalone card & moves POS sync here.
                                                    </div>
                                                </div>
                                            </label>
                                            <label
                                                class="flex items-start gap-2 p-2 rounded-md border cursor-pointer transition"
                                                :class="v.absorb_standalone === false
                                                    ? 'bg-amber-100/80 dark:bg-amber-900/50 border-amber-400 dark:border-amber-600 text-amber-950 dark:text-amber-100 font-semibold'
                                                    : 'bg-white dark:bg-gray-800 border-gray-200 dark:border-gray-700 text-gray-600 dark:text-gray-300'"
                                            >
                                                <input
                                                    type="radio"
                                                    :name="`absorb_standalone_${i}`"
                                                    :value="false"
                                                    v-model="v.absorb_standalone"
                                                    class="mt-0.5 text-amber-600 focus:ring-amber-500"
                                                />
                                                <div>
                                                    <div>Keep Both Active on Store</div>
                                                    <div class="text-[11px] font-normal opacity-80">
                                                        Keeps standalone product & syncs both to POS.
                                                    </div>
                                                </div>
                                            </label>
                                        </div>
                                    </div>
                                </div>

                                <div class="grid grid-cols-1 sm:grid-cols-3 gap-3 items-end">
                                    <div>
                                        <label class="block text-xs font-semibold text-gray-600 dark:text-gray-400 mb-1">
                                            Price (₦)
                                            <span v-if="v.inventory_source === 'pos'" class="text-[10px] text-indigo-600 dark:text-indigo-400 font-normal ml-1">(POS standard)</span>
                                        </label>
                                        <input v-model="v.price" type="number" step="0.01" class="w-full h-10 px-3 py-2 text-sm rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 focus:ring-primary shadow-xs"/>
                                    </div>
                                    <div>
                                        <label class="block text-xs font-semibold text-gray-600 dark:text-gray-400 mb-1">
                                            Stock
                                            <span v-if="v.inventory_source === 'pos'" class="text-[10px] text-indigo-600 dark:text-indigo-400 font-normal ml-1">(Live POS count)</span>
                                        </label>
                                        <input v-model="v.stock" type="number" class="w-full h-10 px-3 py-2 text-sm rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 focus:ring-primary shadow-xs"/>
                                    </div>
                                    <div>
                                        <label class="block text-xs font-semibold text-gray-600 dark:text-gray-400 mb-1">
                                            Variant Photo
                                        </label>
                                        <div class="flex items-center gap-2">
                                            <div v-if="v.image" class="relative group w-10 h-10 shrink-0 rounded-lg overflow-hidden border border-gray-200 dark:border-gray-600 bg-gray-50">
                                                <img :src="'/storage/' + v.image" class="w-full h-full object-cover" alt="Variant"/>
                                                <button
                                                    type="button"
                                                    @click="v.image = ''"
                                                    class="absolute inset-0 bg-black/60 text-white text-[10px] font-bold flex items-center justify-center opacity-0 group-hover:opacity-100 transition cursor-pointer"
                                                    title="Remove assigned photo"
                                                >✕</button>
                                            </div>
                                            <button
                                                type="button"
                                                @click="openImagePicker('variant', i, v.name || ('Variant #' + (i + 1)))"
                                                class="flex-1 h-10 px-3 text-xs font-medium rounded-lg border border-dashed border-gray-300 dark:border-gray-600 hover:border-primary text-gray-600 dark:text-gray-300 hover:text-primary dark:hover:text-primary transition flex items-center justify-center gap-1.5 cursor-pointer bg-gray-50/50 dark:bg-gray-800"
                                            >
                                                <span>📷</span>
                                                <span class="truncate">{{ v.image ? 'Change Photo' : 'Map Photo' }}</span>
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div v-if="!form.variants.length" class="text-center py-6 border-2 border-dashed border-gray-200 dark:border-gray-700 rounded-xl text-gray-400 text-sm">
                                No variants. Click "+ Add Variant" above to configure color/size options.
                            </div>
                        </div>
                    </div>

                    <!-- Selling Units & Packaging Sizes (Units of Measure) -->
                    <div class="bg-white dark:bg-gray-800 p-6 rounded-xl shadow-sm border border-indigo-100 dark:border-indigo-900/50">
                        <div class="flex items-center justify-between mb-2 flex-wrap gap-2">
                            <div>
                                <h3 class="text-base font-bold flex items-center gap-2 text-gray-900 dark:text-white">
                                    <span class="w-6 h-6 bg-indigo-50 dark:bg-indigo-950/50 text-indigo-600 dark:text-indigo-400 rounded flex items-center justify-center text-sm">📏</span>
                                    Selling Units & Packaging Sizes
                                </h3>
                                <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">
                                    Sell in multiple packaging sizes (e.g. Single, Pack of 6, Carton of 24) sharing the same warehouse inventory pool.
                                </p>
                            </div>
                            <div class="flex items-center gap-2">
                                <span v-if="form.pos_units && form.pos_units.length > 0" class="text-xs font-semibold px-2.5 py-1 rounded-full bg-indigo-50 dark:bg-indigo-950/40 text-indigo-700 dark:text-indigo-300 border border-indigo-200 dark:border-indigo-800">
                                    {{ form.pos_units.length }} Unit(s)
                                </span>
                                <button
                                    v-if="form.inventory_source === 'ecommerce' && form.pos_units && form.pos_units.length > 0"
                                    type="button"
                                    @click="addEcommerceSellingUnit"
                                    class="inline-flex items-center gap-1 px-3 py-1.5 text-xs font-bold rounded-lg bg-indigo-600 hover:bg-indigo-700 text-white transition shadow-xs cursor-pointer"
                                >
                                    + Add Packaging Unit
                                </button>
                                <button
                                    v-if="form.inventory_source === 'ecommerce' && form.pos_units && form.pos_units.length > 0"
                                    type="button"
                                    @click="disableEcommerceSellingUnits"
                                    class="px-2.5 py-1.5 text-xs font-semibold rounded-lg border border-gray-300 dark:border-gray-600 text-gray-600 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700 transition cursor-pointer"
                                    title="Disable packaging units and sell only as standard single product"
                                >
                                    Disable Units
                                </button>
                            </div>
                        </div>

                        <!-- Empty state for Ecommerce products -->
                        <div v-if="(!form.pos_units || form.pos_units.length === 0) && form.inventory_source === 'ecommerce'" class="text-center py-6 border-2 border-dashed border-gray-200 dark:border-gray-700 rounded-xl mt-3">
                            <span class="text-2xl block mb-1">📦</span>
                            <p class="text-xs font-bold text-gray-700 dark:text-gray-300">Selling Units Not Configured</p>
                            <p class="text-[11px] text-gray-500 dark:text-gray-400 mt-0.5 max-w-sm mx-auto mb-3">
                                Offer bulk or packaging options (like Single, Pack of 6, Carton of 24) that automatically deduct from this product's unified stock pool.
                            </p>
                            <button
                                type="button"
                                @click="enableEcommerceSellingUnits"
                                class="inline-flex items-center gap-1.5 px-3.5 py-1.5 text-xs font-bold rounded-lg bg-indigo-50 dark:bg-indigo-950/50 hover:bg-indigo-100 text-indigo-700 dark:text-indigo-300 border border-indigo-300 dark:border-indigo-700 transition cursor-pointer"
                            >
                                + Enable Selling Units & Packaging
                            </button>
                        </div>

                        <!-- Empty state for POS products without units -->
                        <div v-else-if="(!form.pos_units || form.pos_units.length === 0) && form.inventory_source === 'pos'" class="text-center py-4 text-xs text-gray-400">
                            No additional selling units defined on POS branch. Only Base Unit will be sold.
                        </div>

                        <!-- Units list -->
                        <div v-else class="space-y-3 mt-4">
                            <div
                                v-for="(u, uIdx) in form.pos_units"
                                :key="u._uid || u.id || uIdx"
                                class="p-4 rounded-xl border border-gray-200 dark:border-gray-700 bg-gray-50/60 dark:bg-gray-700/30 space-y-3 relative"
                            >
                                <!-- Unit Card Header: Checkbox + Name + Badge + Remove Action -->
                                <div class="flex items-center justify-between gap-3 flex-wrap">
                                    <div class="flex items-center gap-2.5 flex-1 min-w-[200px]">
                                        <input
                                            v-model="u.is_enabled_online"
                                            type="checkbox"
                                            :id="'pos_unit_online_' + uIdx"
                                            class="rounded border-gray-300 dark:border-gray-600 text-primary focus:ring-primary h-4 w-4 shrink-0 cursor-pointer"
                                            title="Toggle online visibility"
                                        />
                                        <!-- Ecommerce Unit Name -->
                                        <div v-if="form.inventory_source === 'ecommerce'" class="flex items-center gap-2 flex-1 max-w-sm">
                                            <input
                                                v-model="u.unit_name"
                                                type="text"
                                                placeholder="Unit Name (e.g. Pack of 6)"
                                                class="h-8 px-2.5 text-xs font-bold rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white focus:ring-primary flex-1"
                                            />
                                            <span v-if="u.unit_kind === 'base'" class="text-[10px] uppercase font-bold px-2 py-0.5 rounded bg-emerald-100 text-emerald-700 dark:bg-emerald-950/50 dark:text-emerald-400 shrink-0">
                                                Base Unit (1x)
                                            </span>
                                        </div>
                                        <!-- POS Unit Header -->
                                        <div v-else class="flex items-center gap-2">
                                            <span class="text-sm font-bold text-gray-900 dark:text-white">{{ u.unit_name }}</span>
                                            <span v-if="Number(u.quantity_in_base_units) === 1" class="text-[10px] uppercase font-bold px-2 py-0.5 rounded bg-emerald-100 text-emerald-700 dark:bg-emerald-950/50 dark:text-emerald-400">
                                                Base Unit
                                            </span>
                                            <span v-else class="text-[10px] font-semibold text-gray-500 dark:text-gray-400">
                                                ({{ u.quantity_in_base_units }}x base)
                                            </span>
                                        </div>
                                    </div>

                                    <!-- Top-Right Actions -->
                                    <div class="flex items-center gap-2">
                                        <span v-if="form.inventory_source === 'pos'" class="text-xs font-bold text-indigo-600 dark:text-indigo-400">
                                            ₦{{ Number(u.price || u.synced_standard_price || 0).toLocaleString() }}
                                        </span>
                                        <button
                                            v-if="form.inventory_source === 'ecommerce' && u.unit_kind !== 'base'"
                                            type="button"
                                            @click.stop.prevent="removeSellingUnit(uIdx)"
                                            class="inline-flex items-center gap-1.5 px-2.5 py-1 text-xs font-semibold rounded-lg text-rose-600 dark:text-rose-400 hover:bg-rose-50 dark:hover:bg-rose-950/50 border border-rose-200 dark:border-rose-900/50 transition cursor-pointer"
                                            title="Delete this packaging unit"
                                        >
                                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" /></svg>
                                            <span>Remove</span>
                                        </button>
                                    </div>
                                </div>

                                <!-- Fields Grid: fits 100% inside the card width -->
                                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-12 gap-3 pt-2 border-t border-gray-200/60 dark:border-gray-700/60 items-end">
                                    <!-- Multiplier -->
                                    <div :class="form.inventory_source === 'ecommerce' ? 'lg:col-span-3' : 'hidden'">
                                        <label class="block text-[11px] font-semibold text-gray-500 dark:text-gray-400 mb-1">
                                            Multiplier (Base Qty)
                                        </label>
                                        <input
                                            v-if="u.unit_kind !== 'base'"
                                            v-model="u.quantity_in_base_units"
                                            type="number"
                                            min="1"
                                            step="1"
                                            class="w-full h-9 px-3 text-xs rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white"
                                        />
                                        <div v-else class="h-9 px-3 text-xs rounded-lg border border-gray-200 dark:border-gray-600 bg-gray-100 dark:bg-gray-800 text-gray-500 flex items-center font-mono font-bold">
                                            1.0 (Fixed Base)
                                        </div>
                                    </div>

                                    <!-- Unit Price -->
                                    <div :class="form.inventory_source === 'ecommerce' ? 'lg:col-span-3' : 'hidden'">
                                        <label class="block text-[11px] font-semibold text-gray-500 dark:text-gray-400 mb-1">
                                            Unit Price (₦)
                                        </label>
                                        <input
                                            v-model="u.synced_standard_price"
                                            type="number"
                                            step="0.01"
                                            min="0"
                                            placeholder="₦ Price"
                                            class="w-full h-9 px-3 text-xs font-bold rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white"
                                        />
                                    </div>

                                    <!-- Online Display Label -->
                                    <div :class="form.inventory_source === 'ecommerce' ? 'lg:col-span-3' : 'lg:col-span-7 sm:col-span-1'">
                                        <label class="block text-[11px] font-semibold text-gray-500 dark:text-gray-400 mb-1">
                                            Online Display Label
                                        </label>
                                        <input
                                            v-model="u.online_label"
                                            type="text"
                                            :placeholder="u.unit_name || 'Display label'"
                                            class="w-full h-9 px-3 text-xs rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 focus:ring-primary shadow-xs"
                                        />
                                    </div>

                                    <!-- Assigned Photo -->
                                    <div :class="form.inventory_source === 'ecommerce' ? 'lg:col-span-3' : 'lg:col-span-5 sm:col-span-1'">
                                        <label class="block text-[11px] font-semibold text-gray-500 dark:text-gray-400 mb-1">
                                            Assigned Photo
                                        </label>
                                        <div class="flex items-center gap-2">
                                            <div v-if="u.image" class="relative group w-9 h-9 shrink-0 rounded-lg overflow-hidden border border-gray-200 dark:border-gray-600 bg-gray-50">
                                                <img :src="'/storage/' + u.image" class="w-full h-full object-cover" alt="Unit"/>
                                                <button
                                                    type="button"
                                                    @click="u.image = ''"
                                                    class="absolute inset-0 bg-black/60 text-white text-[10px] font-bold flex items-center justify-center opacity-0 group-hover:opacity-100 transition cursor-pointer"
                                                    title="Remove assigned photo"
                                                >✕</button>
                                            </div>
                                            <button
                                                type="button"
                                                @click="openImagePicker('unit', uIdx, u.online_label || u.unit_name || ('Unit #' + (uIdx + 1)))"
                                                class="flex-1 h-9 px-2.5 text-xs font-medium rounded-lg border border-dashed border-gray-300 dark:border-gray-600 hover:border-primary text-gray-600 dark:text-gray-300 hover:text-primary dark:hover:text-primary transition flex items-center justify-center gap-1.5 cursor-pointer bg-white dark:bg-gray-800"
                                            >
                                                <span>📷</span>
                                                <span class="truncate">{{ u.image ? 'Change Photo' : 'Map Photo' }}</span>
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Related Products Picker -->
                    <div class="bg-white dark:bg-gray-800 p-6 rounded-xl shadow-sm border dark:border-gray-700">
                        <div class="flex items-center justify-between mb-2 flex-wrap gap-2">
                            <div>
                                <h3 class="text-base font-bold flex items-center gap-2">
                                    <span class="w-6 h-6 bg-primary-soft text-primary rounded flex items-center justify-center text-sm">🔗</span>
                                    Related Products
                                </h3>
                                <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">
                                    Hand-pick products for the "You may also like" section. If none selected, same-category products are shown automatically.
                                </p>
                            </div>
                            <button
                                v-if="isAiEnabled"
                                type="button"
                                @click="suggestAiRelatedProducts"
                                :disabled="aiSuggestingRelated"
                                class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-semibold rounded-lg bg-purple-50 hover:bg-purple-100 text-purple-700 dark:bg-purple-950/40 dark:text-purple-300 dark:hover:bg-purple-900/60 border border-purple-200 dark:border-purple-800 transition disabled:opacity-50 shadow-xs"
                                title="AI scans your catalog to pick complementary accessories and cross-sells"
                            >
                                <svg v-if="aiSuggestingRelated" class="animate-spin h-3.5 w-3.5 text-purple-600" viewBox="0 0 24 24" fill="none"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path></svg>
                                <span v-else>✨</span>
                                <span>{{ aiSuggestingRelated ? 'Matching...' : 'AI Suggest Related' }}</span>
                            </button>
                        </div>

                        <!-- Selected chips -->
                        <div v-if="selectedRelatedProducts.length" class="flex flex-wrap gap-2 mb-4">
                            <div v-for="rp in selectedRelatedProducts" :key="rp.id"
                                class="flex items-center gap-1.5 bg-primary-soft text-primary text-xs font-medium px-2.5 py-1 rounded-full border border-primary-soft"
                                :title="rp.ai_reason ? `AI Reason: ${rp.ai_reason}` : rp.name">
                                <img v-if="rp.image" :src="'/storage/' + rp.image" class="w-4 h-4 rounded-full object-cover"/>
                                <span>{{ rp.name.length > 25 ? rp.name.substring(0,25) + '…' : rp.name }}</span>
                                <span v-if="rp.ai_reason" class="text-[11px] font-bold text-purple-600 dark:text-purple-400" :title="rp.ai_reason">✨</span>
                                <button @click.prevent="removeRelated(rp.id)" class="text-primary hover:text-red-500 ml-1 font-bold leading-none">&times;</button>
                            </div>
                        </div>
                        <p v-else class="text-xs text-gray-400 italic mb-4">No products selected — using same-category fallback.</p>

                        <!-- Search -->
                        <input v-model="relatedSearch" @input="searchRelated" type="text"
                            placeholder="Search products by name…"
                            class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 focus:ring-primary shadow-sm text-sm mb-3"/>

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
                                        ? 'bg-primary-soft border border-primary-soft text-primary'
                                        : 'hover:bg-gray-50 dark:hover:bg-gray-700 border border-transparent text-gray-700 dark:text-gray-300'
                                ]">
                                <img :src="rp.image ? '/storage/' + rp.image : 'https://placehold.co/32x32?text=P'"
                                    class="w-8 h-8 rounded object-cover flex-shrink-0 border dark:border-gray-600"/>
                                <span class="flex-1 line-clamp-1">{{ rp.name }}</span>
                                <svg v-if="isRelated(rp.id)" class="w-4 h-4 text-primary flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/>
                                </svg>
                            </button>
                        </div>
                    </div>

                    <!-- Search Engine Optimization (SEO) -->
                    <div class="bg-white dark:bg-gray-800 p-6 rounded-xl shadow-sm border dark:border-gray-700">
                        <div class="flex items-center justify-between mb-4 flex-wrap gap-2">
                            <div>
                                <h3 class="text-base font-bold flex items-center gap-2">
                                    <span class="w-6 h-6 bg-blue-100 dark:bg-blue-900/40 text-blue-600 rounded flex items-center justify-center text-sm">🔍</span>
                                    Search Engine Optimization (SEO)
                                </h3>
                                <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                                    Control how this product appears on Google, search engines, and social media shares.
                                </p>
                            </div>
                            <button
                                v-if="isAiEnabled"
                                type="button"
                                @click="generateAiDescription('seo')"
                                :disabled="aiGenerating.seo"
                                class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-semibold rounded-lg bg-purple-50 hover:bg-purple-100 text-purple-700 dark:bg-purple-950/40 dark:text-purple-300 dark:hover:bg-purple-900/60 border border-purple-200 dark:border-purple-800 transition disabled:opacity-50 shadow-xs"
                                title="Generate high-ranking Meta Title, Meta Description & Keywords using AI"
                            >
                                <svg v-if="aiGenerating.seo" class="animate-spin h-3.5 w-3.5 text-purple-600" viewBox="0 0 24 24" fill="none"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path></svg>
                                <span v-else>✨</span>
                                <span>{{ aiGenerating.seo ? 'Generating SEO...' : 'AI Generate SEO' }}</span>
                            </button>
                        </div>

                        <!-- Google Search Live Preview -->
                        <div class="mb-5 p-4 rounded-lg bg-gray-50 dark:bg-gray-900/60 border border-gray-200 dark:border-gray-700">
                            <div class="flex items-center gap-2 mb-2 text-xs text-gray-400 font-medium">
                                <svg class="w-3.5 h-3.5 text-gray-400" viewBox="0 0 24 24" fill="currentColor"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-1 17.93c-3.95-.49-7-3.85-7-7.93 0-.62.08-1.21.21-1.79L9 15v1c0 1.1.9 2 2 2v1.93zm6.9-2.54c-.26-.81-1-1.39-1.9-1.39h-1v-3c0-.55-.45-1-1-1H8v-2h2c.55 0 1-.45 1-1V7h2c1.1 0 2-.9 2-2v-.41c2.93 1.19 5 4.06 5 7.41 0 2.08-.8 3.97-2.1 5.39z"/></svg>
                                <span>Google Search Result Preview</span>
                            </div>
                            <div class="space-y-1">
                                <div class="text-xs text-gray-600 dark:text-gray-400 truncate">
                                    https://{{ storeUrlDomain }}/product/{{ form.url_key || 'product-slug' }}
                                </div>
                                <div class="text-base font-medium text-blue-600 dark:text-blue-400 hover:underline line-clamp-1 cursor-pointer">
                                    {{ form.meta_title || form.name || 'Your Product Title Will Appear Here' }}
                                </div>
                                <p class="text-xs text-gray-600 dark:text-gray-300 line-clamp-2">
                                    {{ form.meta_description || form.short_description || 'Write a meta description or click "AI Generate SEO" to automatically produce an optimized snippet for search engines.' }}
                                </p>
                            </div>
                        </div>

                        <div class="space-y-4">
                            <!-- Meta Title -->
                            <div>
                                <div class="flex items-center justify-between mb-1">
                                    <label class="block text-sm font-semibold text-gray-700 dark:text-gray-300">
                                        SEO Meta Title
                                    </label>
                                    <span class="text-xs" :class="(form.meta_title?.length || 0) > 60 ? 'text-amber-500 font-semibold' : 'text-gray-400'">
                                        {{ form.meta_title?.length || 0 }} / 60 chars (recommended: 50-60)
                                    </span>
                                </div>
                                <input
                                    v-model="form.meta_title"
                                    type="text"
                                    maxlength="70"
                                    placeholder="e.g. Wireless Noise Cancelling Headphones - Premium Audio"
                                    class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 focus:ring-primary shadow-sm text-sm"
                                />
                                <p class="text-[11px] text-gray-500 dark:text-gray-400 mt-1">
                                    Leave blank to automatically use the product name.
                                </p>
                                <p v-if="form.errors.meta_title" class="text-red-500 text-xs mt-1">{{ form.errors.meta_title }}</p>
                            </div>

                            <!-- Meta Description -->
                            <div>
                                <div class="flex items-center justify-between mb-1">
                                    <label class="block text-sm font-semibold text-gray-700 dark:text-gray-300">
                                        SEO Meta Description
                                    </label>
                                    <span class="text-xs" :class="(form.meta_description?.length || 0) > 160 ? 'text-amber-500 font-semibold' : 'text-gray-400'">
                                        {{ form.meta_description?.length || 0 }} / 160 chars (recommended: 120-160)
                                    </span>
                                </div>
                                <textarea
                                    v-model="form.meta_description"
                                    rows="3"
                                    maxlength="255"
                                    placeholder="Brief summary displayed under the link in search results (approx 150-160 characters)..."
                                    class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 focus:ring-primary shadow-sm text-sm resize-none"
                                ></textarea>
                                <p class="text-[11px] text-gray-500 dark:text-gray-400 mt-1">
                                    Leave blank to automatically use the product's short description.
                                </p>
                                <p v-if="form.errors.meta_description" class="text-red-500 text-xs mt-1">{{ form.errors.meta_description }}</p>
                            </div>

                            <!-- Meta Keywords -->
                            <div>
                                <label class="block text-sm font-semibold text-gray-700 dark:text-gray-300 mb-1">
                                    SEO Meta Keywords <span class="font-normal text-gray-400">(comma-separated)</span>
                                </label>
                                <input
                                    v-model="form.meta_keywords"
                                    type="text"
                                    maxlength="255"
                                    placeholder="e.g. bluetooth headphones, wireless audio, noise cancelling, skynet"
                                    class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 focus:ring-primary shadow-sm text-sm"
                                />
                                <p class="text-[11px] text-gray-500 dark:text-gray-400 mt-1">
                                    Target search keywords separated by commas.
                                </p>
                                <p v-if="form.errors.meta_keywords" class="text-red-500 text-xs mt-1">{{ form.errors.meta_keywords }}</p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- ── Right Column ─────────────────────── -->
                <div class="lg:col-span-1 space-y-6">

                    <!-- Publish / Flags Panel -->
                    <div class="bg-white dark:bg-gray-800 p-6 rounded-xl shadow-sm border dark:border-gray-700">
                        <h3 class="text-base font-bold mb-4 flex items-center gap-2">
                            <span class="w-6 h-6 bg-secondary-soft text-secondary rounded flex items-center justify-center text-sm">🏷️</span>
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
                                    :class="form.status ? 'bg-primary' : 'bg-gray-200 dark:bg-gray-600'">
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
                                    :class="form.is_featured ? 'bg-secondary' : 'bg-gray-200 dark:bg-gray-600'">
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
                                    :class="form.visible_individually ? 'bg-primary' : 'bg-gray-200 dark:bg-gray-600'">
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
                                    :class="form.show_stock_level ? 'bg-primary' : 'bg-gray-200 dark:bg-gray-600'">
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
                        <div class="flex items-center justify-between mb-4">
                            <h3 class="text-base font-bold flex items-center gap-2">
                                <span class="w-6 h-6 bg-primary-soft text-primary rounded flex items-center justify-center text-sm">🖼️</span>
                                Product Media
                            </h3>
                        </div>

                        <!-- Image Compression Toggle (On by Default) -->
                        <div class="mb-5 p-3 rounded-xl bg-emerald-50/70 dark:bg-emerald-950/20 border border-emerald-200/80 dark:border-emerald-800/40">
                            <div class="flex items-center justify-between gap-3">
                                <div class="flex items-start gap-2">
                                    <span class="text-emerald-600 dark:text-emerald-400 text-sm mt-0.5">⚡</span>
                                    <div>
                                        <p class="text-xs font-bold text-gray-800 dark:text-gray-200">
                                            Compress Images
                                        </p>
                                        <p class="text-[11px] text-gray-500 dark:text-gray-400 leading-tight mt-0.5">
                                            Auto-optimize thumbnail & gallery to modern WebP for faster page load.
                                        </p>
                                    </div>
                                </div>
                                <button type="button" @click="form.compress_images = !form.compress_images"
                                    class="relative inline-flex h-5 w-9 shrink-0 items-center rounded-full transition"
                                    :class="form.compress_images ? 'bg-emerald-600' : 'bg-gray-200 dark:bg-gray-600'"
                                    :title="form.compress_images ? 'Image compression enabled' : 'Image compression disabled'">
                                    <span class="inline-block h-3.5 w-3.5 transform rounded-full bg-white transition shadow"
                                        :class="form.compress_images ? 'translate-x-4' : 'translate-x-0.5'"/>
                                </button>
                            </div>
                        </div>

                        <!-- Thumbnail -->
                        <div class="mb-5">
                            <div class="flex items-center justify-between mb-2">
                                <label class="block text-sm font-semibold">Thumbnail Image</label>
                                <button
                                    v-if="imagePreview"
                                    type="button"
                                    @click="removeThumbnail"
                                    class="text-xs font-semibold text-red-500 hover:text-red-700 dark:text-red-400 dark:hover:text-red-300 flex items-center gap-1 transition"
                                >
                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                    Remove
                                </button>
                            </div>
                            <div class="relative block w-full aspect-square bg-gray-50 dark:bg-gray-900 border-2 border-dashed border-gray-300 dark:border-gray-600 rounded-xl overflow-hidden group">
                                <!-- Floating Red X Button on Image -->
                                <button
                                    v-if="imagePreview"
                                    type="button"
                                    @click.stop.prevent="removeThumbnail"
                                    class="absolute top-2.5 right-2.5 z-20 w-7 h-7 rounded-full bg-red-600 hover:bg-red-700 text-white flex items-center justify-center shadow-lg transition transform hover:scale-110 focus:outline-none"
                                    title="Remove thumbnail image"
                                >
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M6 18L18 6M6 6l12 12"/>
                                    </svg>
                                </button>

                                <label class="block w-full h-full cursor-pointer">
                                    <input ref="thumbnailInputRef" type="file" @change="handleImageUpload" accept="image/*" class="absolute inset-0 opacity-0 cursor-pointer z-10"/>
                                    <img v-if="imagePreview" :src="imagePreview" class="absolute inset-0 w-full h-full object-cover"/>
                                    <div v-else class="absolute inset-0 flex flex-col items-center justify-center text-gray-400 group-hover:text-primary transition">
                                        <svg class="w-9 h-9 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"/></svg>
                                        <span class="text-sm font-medium">Upload Thumbnail</span>
                                    </div>
                                    <div v-if="imagePreview" class="absolute bottom-0 inset-x-0 bg-black/60 text-white text-xs text-center py-1.5 opacity-0 group-hover:opacity-100 transition z-10 pointer-events-none">Change</div>
                                </label>
                            </div>
                        </div>

                        <!-- Gallery -->
                        <div>
                            <div class="flex items-center justify-between mb-2">
                                <label class="block text-sm font-semibold">Gallery Images <span class="font-normal text-gray-400">({{ galleryItems.length }}/10)</span></label>
                                <button
                                    v-if="galleryItems.length"
                                    type="button"
                                    @click="galleryItems = []"
                                    class="text-xs font-semibold text-red-500 hover:text-red-700 dark:text-red-400 dark:hover:text-red-300 flex items-center gap-1 transition"
                                >
                                    Clear all
                                </button>
                            </div>
                            <input ref="galleryInputRef" type="file" multiple @change="handleGalleryUpload" accept="image/*"
                                :disabled="galleryItems.length >= 10"
                                class="w-full text-sm file:mr-3 file:py-1.5 file:px-3 file:rounded-lg file:border-0 file:font-semibold file:bg-gray-100 file:text-gray-700 hover:file:bg-gray-200 dark:file:bg-gray-700 dark:file:text-white cursor-pointer disabled:opacity-50"/>
                            <div v-if="galleryItems.length" class="mt-3 grid grid-cols-4 gap-2">
                                <div v-for="(item, i) in galleryItems" :key="i"
                                    class="group relative aspect-square rounded-lg overflow-hidden border border-gray-200 dark:border-gray-600 bg-gray-100 dark:bg-gray-700 shadow-xs">
                                    <img :src="item.url" class="w-full h-full object-cover"/>
                                    <!-- Floating Red X Button on Gallery Item -->
                                    <button
                                        type="button"
                                        @click.stop.prevent="removeGalleryItem(i)"
                                        class="absolute top-1 right-1 z-10 w-5 h-5 rounded-full bg-red-600 hover:bg-red-700 text-white flex items-center justify-center shadow transition transform hover:scale-110 focus:outline-none"
                                        title="Remove image"
                                    >
                                        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M6 18L18 6M6 6l12 12"/>
                                        </svg>
                                    </button>
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
                        Assign <span class="font-bold text-primary">{{ selectedIds.length }}</span> selected product(s) to a new category:
                    </p>
                    <div>
                        <label class="block text-xs font-bold text-gray-500 dark:text-gray-400 uppercase tracking-wide mb-1.5">Target Category</label>
                        <select v-model="targetCategoryId" class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white px-3 py-2 text-sm focus:ring-primary focus:border-primary">
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
                            class="px-5 py-2 rounded-lg bg-primary hover:bg-primary-dark text-white text-sm font-bold shadow transition disabled:opacity-50 flex items-center gap-1.5"
                        >
                            <svg v-if="bulkProcessing" class="animate-spin h-4 w-4 text-white" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path></svg>
                            Apply Category
                        </button>
                    </div>
                </div>
            </div>

            <!-- ── Bulk Set Brand Modal ────────────────────── -->
            <div v-if="showBulkBrandModal" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/60 backdrop-blur-xs">
                <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-2xl max-w-md w-full p-6 border dark:border-gray-700 space-y-4">
                    <div class="flex items-center justify-between pb-3 border-b dark:border-gray-700">
                        <div class="flex items-center gap-2">
                            <span class="text-xl">🏷️</span>
                            <h3 class="font-bold text-lg text-gray-900 dark:text-white">Set Brand</h3>
                        </div>
                        <button @click="showBulkBrandModal = false" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-200 text-lg">✕</button>
                    </div>
                    <p class="text-sm text-gray-600 dark:text-gray-300">
                        Assign or clear brand for <span class="font-bold text-primary">{{ selectedIds.length }}</span> selected product(s):
                    </p>
                    <div>
                        <label class="block text-xs font-bold text-gray-500 dark:text-gray-400 uppercase tracking-wide mb-1.5">Target Brand</label>
                        <select v-model="targetBrandId" class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white px-3 py-2 text-sm focus:ring-primary focus:border-primary">
                            <option value="" disabled>Select brand action...</option>
                            <option value="none">— Remove Brand (Set None) —</option>
                            <option v-for="b in localBrands" :key="b.id" :value="b.id">{{ b.name }}</option>
                        </select>
                    </div>
                    <div class="flex items-center justify-end gap-2.5 pt-3">
                        <button
                            type="button"
                            @click="showBulkBrandModal = false"
                            class="px-4 py-2 rounded-lg border dark:border-gray-600 text-sm font-semibold text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700 transition"
                        >
                            Cancel
                        </button>
                        <button
                            type="button"
                            @click="submitBulkBrand"
                            :disabled="!targetBrandId || bulkProcessing"
                            class="px-5 py-2 rounded-lg bg-primary hover:bg-primary-dark text-white text-sm font-bold shadow transition disabled:opacity-50 flex items-center gap-1.5"
                        >
                            <svg v-if="bulkProcessing" class="animate-spin h-4 w-4 text-white" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path></svg>
                            Apply Brand
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
                        Quickly update prices across <span class="font-bold text-primary">{{ selectedIds.length }}</span> selected product(s):
                    </p>
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-xs font-bold text-gray-500 dark:text-gray-400 uppercase tracking-wide mb-1.5">Action</label>
                            <select v-model="bulkPriceOperation" class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white px-3 py-2 text-sm focus:ring-primary">
                                <option value="increase">Increase (+)</option>
                                <option value="decrease">Decrease (-)</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-gray-500 dark:text-gray-400 uppercase tracking-wide mb-1.5">Type</label>
                            <select v-model="bulkPriceType" class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white px-3 py-2 text-sm focus:ring-primary">
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
                            class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white px-3 py-2 text-sm focus:ring-primary"
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
                            class="px-5 py-2 rounded-lg bg-primary hover:bg-primary-dark text-white text-sm font-bold shadow transition disabled:opacity-50 flex items-center gap-1.5"
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

            <!-- ── Bulk Move to Ecommerce Modal ───────────────────────── -->
            <div v-if="showBulkMoveToEcommerceModal" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/60 backdrop-blur-xs">
                <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-2xl max-w-lg w-full p-6 border dark:border-gray-700 space-y-4">
                    <div class="flex items-center justify-between pb-3 border-b dark:border-gray-700">
                        <div class="flex items-center gap-2">
                            <span class="text-xl">📦</span>
                            <h3 class="font-bold text-lg text-gray-900 dark:text-white">Move to Ecommerce Only</h3>
                        </div>
                        <button @click="showBulkMoveToEcommerceModal = false" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-200 text-lg">✕</button>
                    </div>

                    <p class="text-sm text-gray-600 dark:text-gray-300">
                        Are you sure you want to move <span class="font-bold text-primary">{{ selectedPosProducts.length }}</span> POS-linked product(s) to Ecommerce Only?
                    </p>

                    <div v-if="selectedIds.length > selectedPosProducts.length" class="text-xs text-amber-700 dark:text-amber-400 bg-amber-50 dark:bg-amber-950/40 p-3 rounded-lg border border-amber-200 dark:border-amber-900/60 flex items-start gap-2">
                        <span>ℹ️</span>
                        <span>You selected {{ selectedIds.length }} total products. The <strong>{{ selectedIds.length - selectedPosProducts.length }}</strong> product(s) already marked as Ecommerce Only will remain unaffected.</span>
                    </div>

                    <div class="space-y-2.5 text-xs text-gray-600 dark:text-gray-300 bg-gray-50 dark:bg-gray-700/50 p-3.5 rounded-xl border border-gray-200 dark:border-gray-700">
                        <div class="flex items-start gap-2">
                            <span class="text-emerald-500 font-bold">✓</span>
                            <span><strong>Price & Stock Retained:</strong> Current selling price, cost price, and stock levels will be retained as baseline numbers and become directly editable here.</span>
                        </div>
                        <div class="flex items-start gap-2">
                            <span class="text-indigo-500 font-bold">✓</span>
                            <span><strong>POS Sync Disconnected:</strong> These items will no longer receive price or inventory overrides from POS branch sync.</span>
                        </div>
                        <div class="flex items-start gap-2">
                            <span class="text-purple-500 font-bold">✓</span>
                            <span><strong>POS Lock Released:</strong> Deletion protection mappings on the POS branch app will be unmapped and released immediately.</span>
                        </div>
                    </div>

                    <div class="flex items-center justify-end gap-2.5 pt-3 border-t dark:border-gray-700">
                        <button
                            type="button"
                            @click="showBulkMoveToEcommerceModal = false"
                            class="px-4 py-2 rounded-lg border dark:border-gray-600 text-sm font-semibold text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700 transition cursor-pointer"
                        >
                            Cancel
                        </button>
                        <button
                            type="button"
                            @click="submitBulkMoveToEcommerce"
                            :disabled="bulkProcessing || selectedPosProducts.length === 0"
                            class="px-5 py-2 rounded-lg bg-primary hover:bg-primary-dark text-white text-sm font-bold shadow transition disabled:opacity-50 flex items-center gap-1.5 cursor-pointer"
                        >
                            <svg v-if="bulkProcessing" class="animate-spin h-4 w-4 text-white" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path></svg>
                            Yes, Move {{ selectedPosProducts.length }} Product(s)
                        </button>
                    </div>
                </div>
            </div>

            <!-- ── Stock Adjustment Modal (Ecommerce Only) ─────────────── -->
            <div v-if="showStockAdjustModal" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/60 backdrop-blur-xs">
                <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-2xl max-w-md w-full p-6 border dark:border-gray-700 space-y-4">
                    <div class="flex items-center justify-between pb-3 border-b dark:border-gray-700">
                        <div class="flex items-center gap-2">
                            <span class="text-xl">📦</span>
                            <div>
                                <h3 class="font-bold text-base text-gray-900 dark:text-white leading-tight">Adjust Stock</h3>
                                <p class="text-xs text-primary font-medium truncate max-w-xs">{{ adjustingProduct?.name }}</p>
                            </div>
                        </div>
                        <button @click="closeStockAdjustModal" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-200 text-lg">✕</button>
                    </div>

                    <!-- Current Stock Display -->
                    <div class="p-3 bg-gray-50 dark:bg-gray-900 rounded-xl border border-gray-200 dark:border-gray-700 flex items-center justify-between text-xs">
                        <span class="text-gray-500 font-semibold uppercase">Current Stock:</span>
                        <span class="font-bold font-mono text-sm text-gray-900 dark:text-white">
                            {{ adjustingProduct?.stock }} units
                        </span>
                    </div>

                    <form @submit.prevent="submitStockAdjustment" class="space-y-4 text-sm">
                        <!-- Mode Selector (Add / Subtract / Set) -->
                        <div>
                            <label class="block text-xs font-bold text-gray-700 dark:text-gray-300 uppercase mb-1.5">Adjustment Action</label>
                            <div class="grid grid-cols-3 gap-2">
                                <button
                                    type="button"
                                    @click="stockAdjustForm.mode = 'add'"
                                    :class="[
                                        'py-2 px-3 text-xs font-bold rounded-xl border transition-all text-center',
                                        stockAdjustForm.mode === 'add'
                                            ? 'bg-emerald-50 dark:bg-emerald-950/40 text-emerald-700 dark:text-emerald-300 border-emerald-500 shadow-xs'
                                            : 'border-gray-200 dark:border-gray-700 text-gray-600 dark:text-gray-400 hover:bg-gray-50 dark:hover:bg-gray-700'
                                    ]"
                                >
                                    + Add Stock
                                </button>
                                <button
                                    type="button"
                                    @click="stockAdjustForm.mode = 'subtract'"
                                    :class="[
                                        'py-2 px-3 text-xs font-bold rounded-xl border transition-all text-center',
                                        stockAdjustForm.mode === 'subtract'
                                            ? 'bg-rose-50 dark:bg-rose-950/40 text-rose-700 dark:text-rose-300 border-rose-500 shadow-xs'
                                            : 'border-gray-200 dark:border-gray-700 text-gray-600 dark:text-gray-400 hover:bg-gray-50 dark:hover:bg-gray-700'
                                    ]"
                                >
                                    - Subtract
                                </button>
                                <button
                                    type="button"
                                    @click="stockAdjustForm.mode = 'set'"
                                    :class="[
                                        'py-2 px-3 text-xs font-bold rounded-xl border transition-all text-center',
                                        stockAdjustForm.mode === 'set'
                                            ? 'bg-primary/10 text-primary border-primary shadow-xs'
                                            : 'border-gray-200 dark:border-gray-700 text-gray-600 dark:text-gray-400 hover:bg-gray-50 dark:hover:bg-gray-700'
                                    ]"
                                >
                                    = Set Exact
                                </button>
                            </div>
                        </div>

                        <!-- Quantity input -->
                        <div>
                            <label class="block text-xs font-bold text-gray-700 dark:text-gray-300 uppercase mb-1">
                                {{ stockAdjustForm.mode === 'set' ? 'New Exact Stock Level' : 'Quantity to ' + stockAdjustForm.mode }}
                            </label>
                            <input
                                v-model.number="stockAdjustForm.quantity"
                                type="number"
                                :min="stockAdjustForm.mode === 'set' ? 0 : 1"
                                required
                                class="w-full text-sm font-mono bg-gray-50 dark:bg-gray-900 border border-gray-300 dark:border-gray-700 rounded-xl px-3 py-2 text-gray-900 dark:text-gray-100"
                            />
                        </div>

                        <!-- Result Preview -->
                        <div class="text-xs text-gray-500 flex items-center justify-between font-mono">
                            <span>Projected Balance:</span>
                            <span class="font-bold text-primary">
                                {{ stockAdjustForm.mode === 'add' ? (adjustingProduct?.stock + (stockAdjustForm.quantity || 0)) : (stockAdjustForm.mode === 'subtract' ? Math.max(0, adjustingProduct?.stock - (stockAdjustForm.quantity || 0)) : stockAdjustForm.quantity) }} units
                            </span>
                        </div>

                        <!-- Reason dropdown -->
                        <div>
                            <label class="block text-xs font-bold text-gray-700 dark:text-gray-300 uppercase mb-1">Reason / Event</label>
                            <select
                                v-model="stockAdjustForm.reason"
                                required
                                class="w-full text-xs bg-gray-50 dark:bg-gray-900 border border-gray-300 dark:border-gray-700 rounded-xl px-3 py-2 text-gray-900 dark:text-gray-100"
                            >
                                <option value="Physical Stock Audit / Recount">Physical Stock Audit / Recount</option>
                                <option value="Damaged Goods / Broken">Damaged Goods / Broken</option>
                                <option value="Expired / Spoilage">Expired / Spoilage</option>
                                <option value="Loss / Theft / Shrinkage">Loss / Theft / Shrinkage</option>
                                <option value="Internal Store Use / Marketing Samples">Internal Store Use / Marketing Samples</option>
                                <option value="Customer Return (Manual)">Customer Return (Manual)</option>
                                <option value="Found Extra Warehouse Stock">Found Extra Warehouse Stock</option>
                                <option value="Other">Other</option>
                            </select>
                        </div>

                        <!-- Notes memo -->
                        <div>
                            <label class="block text-xs font-bold text-gray-700 dark:text-gray-300 uppercase mb-1">Notes / Explanation</label>
                            <textarea
                                v-model="stockAdjustForm.notes"
                                rows="2"
                                placeholder="Optional details for ledger tracking..."
                                class="w-full text-xs bg-gray-50 dark:bg-gray-900 border border-gray-300 dark:border-gray-700 rounded-xl p-2.5 text-gray-900 dark:text-gray-100 placeholder-gray-400"
                            ></textarea>
                        </div>

                        <div class="flex items-center justify-end gap-2.5 pt-2 border-t dark:border-gray-700">
                            <button
                                type="button"
                                @click="closeStockAdjustModal"
                                class="px-4 py-2 rounded-xl border dark:border-gray-600 text-xs font-semibold text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700 transition"
                            >
                                Cancel
                            </button>
                            <button
                                type="submit"
                                :disabled="stockAdjustForm.processing"
                                class="px-5 py-2 rounded-xl bg-primary hover:bg-primary-dark text-white text-xs font-bold shadow-sm transition disabled:opacity-50 flex items-center gap-1.5"
                            >
                                <svg v-if="stockAdjustForm.processing" class="animate-spin h-3.5 w-3.5 text-white" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path></svg>
                                Save Adjustment
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- ── Image Picker Modal (Map Photo to Variant or Unit) ── -->
            <div
                v-if="showImagePickerModal"
                class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/60 backdrop-blur-xs"
                @click.self="showImagePickerModal = false"
            >
                <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-2xl max-w-lg w-full p-6 border dark:border-gray-700 space-y-4">
                    <div class="flex items-center justify-between pb-3 border-b dark:border-gray-700">
                        <div class="flex items-center gap-2">
                            <span class="text-xl">📷</span>
                            <div>
                                <h3 class="font-bold text-base text-gray-900 dark:text-white leading-tight">
                                    Map Photo
                                </h3>
                                <p class="text-xs text-primary font-medium truncate max-w-xs">
                                    {{ imagePickerTarget?.title || 'Selected option' }}
                                </p>
                            </div>
                        </div>
                        <button
                            type="button"
                            @click="showImagePickerModal = false"
                            class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-200 text-lg cursor-pointer"
                        >✕</button>
                    </div>

                    <p class="text-xs text-gray-500 dark:text-gray-400">
                        Click on a gallery photo to link it. When customers select this variant or unit on the storefront, the product view switches to this photo.
                    </p>

                    <!-- Gallery Grid -->
                    <div v-if="availableGalleryImages && availableGalleryImages.length > 0" class="max-h-72 overflow-y-auto pr-1">
                        <div class="grid grid-cols-3 sm:grid-cols-4 gap-3">
                            <button
                                v-for="(img, idx) in availableGalleryImages"
                                :key="img.path || idx"
                                type="button"
                                @click="selectMappedImage(img.path)"
                                :class="[
                                    'relative aspect-square rounded-xl overflow-hidden border-2 transition-all p-0.5 group cursor-pointer flex items-center justify-center bg-gray-50 dark:bg-gray-900',
                                    currentTargetImage === img.path
                                        ? 'border-primary ring-2 ring-primary/40 shadow-sm'
                                        : 'border-gray-200 dark:border-gray-700 hover:border-primary/60'
                                ]"
                            >
                                <img :src="img.url" class="w-full h-full object-cover rounded-lg" :alt="img.label"/>
                                <span
                                    v-if="currentTargetImage === img.path"
                                    class="absolute top-1.5 right-1.5 bg-primary text-white rounded-full p-0.5 shadow"
                                >
                                    <svg class="w-3.5 h-3.5" viewBox="0 0 20 20" fill="currentColor">
                                        <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd" />
                                    </svg>
                                </span>
                            </button>
                        </div>
                    </div>

                    <!-- Empty Gallery State -->
                    <div v-else class="text-center py-8 border-2 border-dashed border-gray-200 dark:border-gray-700 rounded-xl text-gray-400">
                        <span class="text-3xl block mb-2">🖼️</span>
                        <p class="text-sm font-semibold text-gray-700 dark:text-gray-300">No Gallery Photos Available</p>
                        <p class="text-xs text-gray-500 dark:text-gray-400 mt-1 max-w-sm mx-auto">
                            Please upload product photos in the "Product Images" gallery section above and save first, then select them here.
                        </p>
                    </div>

                    <!-- Modal Actions -->
                    <div class="flex items-center justify-between pt-3 border-t dark:border-gray-700">
                        <button
                            v-if="currentTargetImage"
                            type="button"
                            @click="clearMappedImage"
                            class="px-3 py-1.5 text-xs font-semibold text-rose-600 dark:text-rose-400 hover:bg-rose-50 dark:hover:bg-rose-950/40 rounded-lg transition cursor-pointer"
                        >
                            Remove Photo Link
                        </button>
                        <span v-else></span>

                        <button
                            type="button"
                            @click="showImagePickerModal = false"
                            class="px-4 py-2 rounded-lg border dark:border-gray-600 text-sm font-semibold text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700 transition cursor-pointer"
                        >
                            Close
                        </button>
                    </div>
                </div>
            </div>

            <!-- ── CSV Import Modal ──────────────────────────────────────── -->
            <div v-if="showImportModal" class="fixed inset-0 z-50 flex items-center justify-center p-3 sm:p-4 bg-black/60 backdrop-blur-xs">
                <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-2xl max-w-lg w-full max-h-[88vh] flex flex-col border border-gray-200 dark:border-gray-700 overflow-hidden animate-in fade-in zoom-in duration-150">
                    <!-- Modal Header (Fixed) -->
                    <div class="p-4 sm:px-5 sm:py-3.5 border-b border-gray-200 dark:border-gray-700 flex items-center justify-between shrink-0 bg-gray-50/70 dark:bg-gray-800/80">
                        <div class="flex items-center gap-2.5">
                            <div class="w-8 h-8 rounded-lg bg-blue-100 dark:bg-blue-900/40 text-blue-600 dark:text-blue-400 flex items-center justify-center text-base">
                                📥
                            </div>
                            <div>
                                <h3 class="text-base font-bold text-gray-900 dark:text-white leading-tight">Import Products (CSV)</h3>
                                <p class="text-[11px] text-gray-500 dark:text-gray-400">Upload CSV to create or bulk-update catalog items</p>
                            </div>
                        </div>
                        <button @click="showImportModal = false" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-200 text-lg font-bold p-1 rounded-md hover:bg-gray-100 dark:hover:bg-gray-700 transition cursor-pointer leading-none">
                            ✕
                        </button>
                    </div>

                    <!-- Modal Body (Scrollable if height constrained) -->
                    <form @submit.prevent="submitImport" class="flex flex-col flex-1 min-h-0 overflow-hidden">
                        <div class="p-4 sm:p-5 overflow-y-auto space-y-3.5 flex-1 text-left">
                            <!-- Mode Selection -->
                            <div>
                                <label class="block text-[11px] font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400 mb-1.5">Import Target Source</label>
                                <div class="grid grid-cols-3 gap-2">
                                    <label
                                        class="flex flex-col items-center justify-center p-2 rounded-xl border text-center cursor-pointer transition select-none"
                                        :class="importForm.import_mode === 'both' ? 'border-primary bg-primary-soft text-primary ring-2 ring-primary/20' : 'border-gray-200 dark:border-gray-700 hover:border-gray-300 dark:hover:border-gray-600'"
                                    >
                                        <input type="radio" value="both" v-model="importForm.import_mode" class="sr-only" />
                                        <span class="text-base mb-0.5">📦</span>
                                        <span class="text-xs font-bold leading-tight">Both (All)</span>
                                        <span class="text-[10px] text-gray-400 leading-tight">Auto-detect</span>
                                    </label>

                                    <label
                                        class="flex flex-col items-center justify-center p-2 rounded-xl border text-center cursor-pointer transition select-none"
                                        :class="importForm.import_mode === 'ecommerce' ? 'border-blue-500 bg-blue-50 dark:bg-blue-950/30 text-blue-600 dark:text-blue-400 ring-2 ring-blue-500/20' : 'border-gray-200 dark:border-gray-700 hover:border-gray-300 dark:hover:border-gray-600'"
                                    >
                                        <input type="radio" value="ecommerce" v-model="importForm.import_mode" class="sr-only" />
                                        <span class="text-base mb-0.5">🌐</span>
                                        <span class="text-xs font-bold leading-tight">Ecommerce Only</span>
                                        <span class="text-[10px] text-gray-400 leading-tight">Online items</span>
                                    </label>

                                    <label
                                        class="flex flex-col items-center justify-center p-2 rounded-xl border text-center cursor-pointer transition select-none"
                                        :class="importForm.import_mode === 'pos' ? 'border-purple-500 bg-purple-50 dark:bg-purple-950/30 text-purple-600 dark:text-purple-400 ring-2 ring-purple-500/20' : 'border-gray-200 dark:border-gray-700 hover:border-gray-300 dark:hover:border-gray-600'"
                                    >
                                        <input type="radio" value="pos" v-model="importForm.import_mode" class="sr-only" />
                                        <span class="text-base mb-0.5">🏬</span>
                                        <span class="text-xs font-bold leading-tight">POS Only</span>
                                        <span class="text-[10px] text-gray-400 leading-tight">Branch items</span>
                                    </label>
                                </div>
                            </div>

                            <!-- Notice on POS Availability -->
                            <div class="p-2.5 bg-amber-50 dark:bg-amber-950/30 border border-amber-200 dark:border-amber-800/60 rounded-xl text-xs text-amber-800 dark:text-amber-300 flex items-start gap-2">
                                <span class="text-sm shrink-0 mt-0.5">ℹ️</span>
                                <div class="text-[11px] leading-relaxed">
                                    <strong>POS items availability:</strong> Any POS product whose <code class="font-mono bg-amber-100 dark:bg-amber-900/50 px-1 py-0.5 rounded text-[10px]">POS Item ID</code> is missing or not found in the connected POS branch will be <strong>safely skipped</strong> and reported in the notification summary.
                                </div>
                            </div>

                            <!-- File Upload Area (Compact) -->
                            <div>
                                <label class="block text-[11px] font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400 mb-1">Select CSV File *</label>
                                <div class="p-4 border-2 border-gray-300 dark:border-gray-600 border-dashed rounded-xl hover:border-primary dark:hover:border-primary transition bg-gray-50/60 dark:bg-gray-900/30 text-center">
                                    <svg class="mx-auto h-7 w-7 text-gray-400 mb-1" stroke="currentColor" fill="none" viewBox="0 0 48 48">
                                        <path d="M28 8H12a4 4 0 00-4 4v20m32-12v8m0 0v8a4 4 0 01-4 4H12a4 4 0 01-4-4v-4m32-4l-3.172-3.172a4 4 0 00-5.656 0L28 28M8 32l9.172-9.172a4 4 0 015.656 0L28 28m0 0l4 4m4-24h8m-4-4v8m-12 4h.02" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
                                    </svg>
                                    <div class="flex text-xs text-gray-600 dark:text-gray-400 justify-center items-center gap-1">
                                        <label class="relative cursor-pointer rounded font-bold text-primary hover:opacity-80">
                                            <span>Click to choose CSV</span>
                                            <input ref="importFileRef" type="file" accept=".csv,text/csv,text/plain" @change="handleImportFileChange" class="sr-only" required />
                                        </label>
                                        <span>or drag & drop</span>
                                    </div>
                                    <p class="text-xs text-emerald-600 dark:text-emerald-400 font-mono font-bold mt-1.5 truncate max-w-sm mx-auto" v-if="importForm.file">
                                        ✓ {{ importForm.file.name }} ({{ Math.round(importForm.file.size / 1024) }} KB)
                                    </p>
                                    <p class="text-[11px] text-gray-400 mt-1" v-else>Supports standard UTF-8 CSV up to 10MB</p>
                                </div>
                            </div>
                        </div>

                        <!-- Modal Footer (Fixed at bottom) -->
                        <div class="px-4 py-3 sm:px-5 bg-gray-50/80 dark:bg-gray-800/90 border-t border-gray-200 dark:border-gray-700 flex items-center justify-between shrink-0 gap-2">
                            <a :href="getExportUrl('both')" target="_blank" class="text-xs text-primary hover:underline flex items-center gap-1 font-medium">
                                <span>⬇️ Sample CSV</span>
                            </a>

                            <div class="flex items-center gap-2">
                                <button type="button" @click="showImportModal = false" class="px-3.5 py-1.5 border border-gray-300 dark:border-gray-600 rounded-lg text-xs font-semibold hover:bg-gray-100 dark:hover:bg-gray-700 transition cursor-pointer">
                                    Cancel
                                </button>
                                <button
                                    type="submit"
                                    :disabled="!importForm.file || importForm.processing"
                                    class="px-4 py-1.5 bg-primary hover:bg-primary-dark text-white rounded-lg text-xs font-bold shadow transition disabled:opacity-50 flex items-center gap-1.5 cursor-pointer"
                                >
                                    <svg v-if="importForm.processing" class="animate-spin -ml-0.5 mr-1.5 h-3.5 w-3.5 text-white" fill="none" viewBox="0 0 24 24">
                                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path>
                                    </svg>
                                    <span>{{ importForm.processing ? 'Importing...' : 'Upload & Import' }}</span>
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </AdminLayout>
</template>

<style scoped>
:deep(.ql-editor) {
    min-height: 220px;
    font-size: 0.95rem;
    line-height: 1.65;
    color: inherit;
}
:deep(.ql-editor h2) {
    font-size: 1.35rem;
    font-weight: 700;
    margin-top: 1.25rem;
    margin-bottom: 0.5rem;
    color: inherit;
}
:deep(.ql-editor h3) {
    font-size: 1.15rem;
    font-weight: 700;
    margin-top: 1rem;
    margin-bottom: 0.4rem;
    color: inherit;
}
:deep(.ql-editor p) {
    margin-top: 0;
    margin-bottom: 0;
}
:deep(.ql-editor ul) {
    list-style-type: disc !important;
    padding-left: 1.5rem !important;
    margin-bottom: 0.75rem;
}
:deep(.ql-editor ol) {
    list-style-type: decimal !important;
    padding-left: 1.5rem !important;
    margin-bottom: 0.75rem;
}
:deep(.ql-editor li) {
    margin-bottom: 0.35rem;
}
:deep(.ql-editor strong) {
    font-weight: 700;
}
:deep(.dark .ql-toolbar.ql-snow) {
    background-color: #1f2937;
    border-color: #374151;
}
:deep(.dark .ql-toolbar.ql-snow .ql-stroke) {
    stroke: #e5e7eb;
}
:deep(.dark .ql-toolbar.ql-snow .ql-fill) {
    fill: #e5e7eb;
}
:deep(.dark .ql-toolbar.ql-snow .ql-picker) {
    color: #e5e7eb;
}
:deep(.dark .ql-container.ql-snow) {
    border-color: #374151;
    background-color: #374151;
    color: #f3f4f6;
}
</style>
