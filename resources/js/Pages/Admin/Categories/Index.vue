<script setup>
import { Head, Link, useForm, router } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import Pagination from '@/Components/Pagination.vue';
import { ref, computed, nextTick, watch } from 'vue';
import { useToast } from 'vue-toastification';

const toast = useToast();
const props = defineProps({
    categories:        Object,
    attributes:        Array,
    parent_categories: Array,
    filters:           Object,
});

const search = ref(props.filters?.search || '');
let searchTimeout = null;

const applySearch = () => {
    router.get(
        route('admin.categories.index'),
        {
            search: search.value || undefined,
        },
        { preserveState: true, preserveScroll: true, replace: true }
    );
};

watch(search, (newVal, oldVal) => {
    if (newVal === oldVal) return;
    clearTimeout(searchTimeout);
    searchTimeout = setTimeout(applySearch, 350);
});

const clearSearch = () => {
    search.value = '';
    applySearch();
};

const generateSlug = (text) => {
    return text.toString().toLowerCase()
        .replace(/\s+/g, '-')           
        .replace(/[^\w\-]+/g, '')       
        .replace(/\-\-+/g, '-')         
        .replace(/^-+/, '')             
        .replace(/-+$/, '');            
};

const slugChangedManually = ref(false);
const handleSlugInput = () => {
    slugChangedManually.value = true;
};

// ── Active Tab ─────────────────────────────────────────────────
const activeTab = ref('parents');   // 'parents' | 'subcategories'
const showForm  = ref(false);       // form panel toggle

// ── Filtered & Computed splits ─────────────────────────────────
const filteredCategories = computed(() => {
    const list = props.categories?.data ?? [];
    const q = search.value.trim().toLowerCase();
    if (!q) return list;
    return list.filter(c => {
        const name = (c.name || '').toLowerCase();
        const slug = (c.slug || '').toLowerCase();
        const desc = (c.description || '').toLowerCase();
        const parentName = (c.parent?.name || '').toLowerCase();
        const childMatch = (c.children || []).some(ch => (ch.name || '').toLowerCase().includes(q));
        return name.includes(q) || slug.includes(q) || desc.includes(q) || parentName.includes(q) || childMatch;
    });
});

const parentRows = computed(() => filteredCategories.value.filter(c => !c.parent_id));
const subRows    = computed(() => filteredCategories.value.filter(c =>  c.parent_id));

// ── Form state ─────────────────────────────────────────────────
const isEditing             = ref(false);
const editId                = ref(null);
const imagePreview          = ref(null);
const formRef               = ref(null);
const categoryImageInputRef = ref(null);

const form = useForm({
    _method:         'POST',
    name:            '',
    slug:            '',
    description:     '',
    parent_id:       null,
    image:           null,
    compress_images: true,
    remove_image:    false,
    visible_in_menu: true,
    menu_position:   0,
    attribute_ids:   [],
});

const save = () => {
    const opts = {
        preserveScroll: true,
        onSuccess: () => {
            cancelForm();
        },
    };
    if (isEditing.value) {
        form.post(route('admin.categories.update', editId.value), opts);
    } else {
        form.post(route('admin.categories.store'), opts);
    }
};

watch(() => form.name, (newName) => {
    if (!slugChangedManually.value && !isEditing.value) {
        form.slug = generateSlug(newName);
    }
});

// Open blank form for a new entry
const openNewForm = () => {
    resetForm();
    if (activeTab.value === 'subcategories') {
        form.parent_id = props.parent_categories?.[0]?.id ?? null;
    }
    form.compress_images = true;
    showForm.value = true;
};

const editMode = (category) => {
    activeTab.value   = category.parent_id ? 'subcategories' : 'parents';
    isEditing.value   = true;
    editId.value      = category.id;
    form._method      = 'PUT';
    form.name         = category.name;
    form.slug         = category.slug || generateSlug(category.name);
    form.description  = category.description || '';
    form.parent_id    = category.parent_id ?? null;
    form.image        = null;
    form.compress_images = true;
    form.remove_image = false;
    imagePreview.value= category.image ? '/storage/' + category.image : null;
    form.visible_in_menu = category.visible_in_menu;
    form.menu_position   = category.menu_position;
    form.attribute_ids   = category.attributes?.map(a => a.id) ?? [];
    if (categoryImageInputRef.value) {
        categoryImageInputRef.value.value = '';
    }
    form.clearErrors();
    showForm.value = true;
};

const resetForm = () => {
    isEditing.value    = false;
    editId.value       = null;
    imagePreview.value = null;
    form.reset();
    form.clearErrors();
    form._method         = 'POST';
    slugChangedManually.value = false;
    form.image           = null;
    form.compress_images = true;
    form.remove_image    = false;
    form.parent_id       = null;
    form.visible_in_menu = true;
    form.menu_position   = 0;
    form.attribute_ids   = [];
    if (categoryImageInputRef.value) {
        categoryImageInputRef.value.value = '';
    }
};

const cancelForm = () => {
    resetForm();
    showForm.value = false;
};

// Switch tab: hide form + reset
const switchTab = (tab) => {
    activeTab.value = tab;
    cancelForm();
};

const handleImageUpload = (e) => {
    const file = e.target.files?.[0];
    if (!file) return;

    // Check max upload size: 1MB = 1024 * 1024 bytes (1,048,576 bytes)
    const maxSizeBytes = 1024 * 1024;
    if (file.size > maxSizeBytes) {
        const sizeMb = (file.size / (1024 * 1024)).toFixed(2);
        toast.error(`The image file is too large (${sizeMb} MB). Maximum allowed upload size is 1MB.`);
        e.target.value = '';
        form.image = null;
        imagePreview.value = null;
        return;
    }

    form.image = file;
    imagePreview.value = URL.createObjectURL(file);
    form.remove_image = false;
};

const removeImage = () => {
    form.image = null;
    imagePreview.value = null;
    form.remove_image = true;
    if (categoryImageInputRef.value) {
        categoryImageInputRef.value.value = '';
    }
};

const toggleAttr = (id) => {
    const idx = form.attribute_ids.indexOf(id);
    if (idx === -1) form.attribute_ids.push(id);
    else form.attribute_ids.splice(idx, 1);
};

const deleteCategory = (id) => {
    if (!confirm('Delete this category? Products and subcategories will lose their association.')) return;
    useForm({}).delete(route('admin.categories.destroy', id));
};

const toggleVisibility = (cat) => {
    useForm({
        _method:         'PUT',
        name:            cat.name,
        slug:            cat.slug,
        description:     cat.description ?? '',
        parent_id:       cat.parent_id ?? null,
        visible_in_menu: !cat.visible_in_menu,
        menu_position:   cat.menu_position ?? 0,
        attribute_ids:   cat.attributes?.map(a => a.id) ?? [],
    }).post(route('admin.categories.update', cat.id));
};
</script>

<template>
    <Head title="Manage Categories" />
    <AdminLayout>
        <div class="max-w-6xl mx-auto px-4 py-8 text-black dark:text-white">

            <!-- ── Page Header ──────────────────────────────── -->
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 mb-5">
                <div>
                    <h1 class="text-2xl font-bold">📂 Manage Categories</h1>
                    <p class="text-sm text-gray-500 dark:text-gray-400 mt-0.5">
                        Organize your store's navigation with parent categories and subcategories.
                    </p>
                </div>
                <Link :href="route('admin.attributes.index')"
                    class="inline-flex items-center gap-1.5 text-sm font-semibold text-primary hover:underline">
                    ⚙️ Manage Attributes
                </Link>
            </div>

            <!-- ── Stats Strip ─────────────────────────────── -->
            <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 mb-5">
                <div class="bg-white dark:bg-gray-800 rounded-xl border dark:border-gray-700 px-4 py-3 flex items-center gap-3">
                    <span class="text-2xl">📁</span>
                    <div>
                        <p class="text-[11px] text-gray-500 dark:text-gray-400 leading-tight">Parent Categories</p>
                        <p class="text-xl font-bold text-primary">{{ parentRows.length }}</p>
                    </div>
                </div>
                <div class="bg-white dark:bg-gray-800 rounded-xl border dark:border-gray-700 px-4 py-3 flex items-center gap-3">
                    <span class="text-2xl">📂</span>
                    <div>
                        <p class="text-[11px] text-gray-500 dark:text-gray-400 leading-tight">Subcategories</p>
                        <p class="text-xl font-bold text-secondary">{{ subRows.length }}</p>
                    </div>
                </div>
                <div class="bg-white dark:bg-gray-800 rounded-xl border dark:border-gray-700 px-4 py-3 flex items-center gap-3">
                    <span class="text-2xl">✅</span>
                    <div>
                        <p class="text-[11px] text-gray-500 dark:text-gray-400 leading-tight">Visible in Menu</p>
                        <p class="text-xl font-bold text-green-600 dark:text-green-400">
                            {{ categories?.data?.filter(c => c.visible_in_menu).length ?? 0 }}
                        </p>
                    </div>
                </div>
                <div class="bg-white dark:bg-gray-800 rounded-xl border dark:border-gray-700 px-4 py-3 flex items-center gap-3">
                    <span class="text-2xl">📦</span>
                    <div>
                        <p class="text-[11px] text-gray-500 dark:text-gray-400 leading-tight">Total</p>
                        <p class="text-xl font-bold text-gray-700 dark:text-gray-300">{{ categories?.data?.length ?? 0 }}</p>
                    </div>
                </div>
            </div>

            <!-- ── Search & Filter Bar ──────────────────────── -->
            <div class="bg-white dark:bg-gray-800 rounded-xl border dark:border-gray-700 p-3 mb-5 shadow-xs flex flex-col sm:flex-row items-stretch sm:items-center justify-between gap-3">
                <div class="relative flex-1">
                    <input
                        v-model="search"
                        type="text"
                        placeholder="Search categories & subcategories by name, slug, description, or parent..."
                        class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white text-sm pl-9 pr-9 py-2 focus:ring-primary focus:border-primary shadow-xs"
                    />
                    <svg class="w-4 h-4 text-gray-400 absolute left-3 top-2.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                    </svg>
                    <button
                        v-if="search"
                        @click="clearSearch"
                        type="button"
                        class="absolute right-2.5 top-2 text-gray-400 hover:text-gray-600 dark:hover:text-gray-200 p-0.5 rounded-full hover:bg-gray-100 dark:hover:bg-gray-600 transition"
                        title="Clear search"
                    >
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>
                <div v-if="search" class="flex items-center gap-2 text-xs text-gray-500 dark:text-gray-400 shrink-0">
                    <span class="bg-primary/10 text-primary font-semibold px-2.5 py-1 rounded-md">
                        {{ parentRows.length }} parent{{ parentRows.length === 1 ? '' : 's' }}, {{ subRows.length }} subcategor{{ subRows.length === 1 ? 'y' : 'ies' }}
                    </span>
                    <button @click="clearSearch" class="text-xs text-red-500 hover:underline font-semibold cursor-pointer">
                        Clear
                    </button>
                </div>
            </div>

            <!-- ── Tabs ─────────────────────────────────────── -->
            <div class="flex gap-0 border-b dark:border-gray-700">
                <button @click="switchTab('parents')"
                    class="px-6 py-3 text-sm font-semibold border-b-2 -mb-px transition-colors"
                    :class="activeTab === 'parents'
                        ? 'border-primary text-primary'
                        : 'border-transparent text-gray-500 dark:text-gray-400 hover:text-gray-800 dark:hover:text-gray-200'">
                    <span class="flex items-center gap-2">
                        📁 Parent Categories
                        <span class="inline-flex items-center justify-center w-5 h-5 rounded-full text-[10px] font-bold"
                            :class="activeTab === 'parents' ? 'bg-primary text-white' : 'bg-gray-100 dark:bg-gray-700 text-gray-600 dark:text-gray-300'">
                            {{ parentRows.length }}
                        </span>
                    </span>
                </button>
                <button @click="switchTab('subcategories')"
                    class="px-6 py-3 text-sm font-semibold border-b-2 -mb-px transition-colors"
                    :class="activeTab === 'subcategories'
                        ? 'border-secondary text-secondary'
                        : 'border-transparent text-gray-500 dark:text-gray-400 hover:text-gray-800 dark:hover:text-gray-200'">
                    <span class="flex items-center gap-2">
                        📂 Subcategories
                        <span class="inline-flex items-center justify-center w-5 h-5 rounded-full text-[10px] font-bold"
                            :class="activeTab === 'subcategories' ? 'bg-secondary text-white' : 'bg-gray-100 dark:bg-gray-700 text-gray-600 dark:text-gray-300'">
                            {{ subRows.length }}
                        </span>
                    </span>
                </button>
            </div>

            <!-- ── Tab Body ──────────────────────────────────── -->
            <div class="bg-white dark:bg-gray-800 rounded-b-xl rounded-tr-xl border border-t-0 dark:border-gray-700 shadow-sm">

                <!-- Tab toolbar: title + Add New button -->
                <div class="flex items-center justify-between px-5 py-3 border-b dark:border-gray-700">
                    <p class="text-sm text-gray-500 dark:text-gray-400">
                        <template v-if="activeTab === 'parents'">
                            Top-level categories shown in the mega menu.
                        </template>
                        <template v-else>
                            Sub-groups nested under a parent category.
                        </template>
                    </p>
                    <button @click="openNewForm"
                        class="inline-flex items-center gap-2 px-4 py-2 rounded-xl text-xs font-bold transition shadow-xs cursor-pointer"
                        :class="activeTab === 'parents'
                            ? 'bg-primary hover:bg-primary-dark text-white'
                            : 'bg-secondary hover:bg-secondary-dark text-white'">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 4v16m8-8H4"/>
                        </svg>
                        Add {{ activeTab === 'parents' ? 'Category' : 'Subcategory' }}
                    </button>
                </div>

                <!-- ══ Scrollable List ══════════════════════════ -->

                <!-- ─── Parent Categories ─── -->
                <div v-if="activeTab === 'parents'" class="overflow-y-auto max-h-[60vh] scrollbar-thin">
                    <table class="w-full text-left text-sm">
                        <thead class="bg-gray-50 dark:bg-gray-900 text-xs uppercase text-gray-400 dark:text-gray-500 sticky top-0 z-10">
                            <tr>
                                <th class="px-4 py-3 font-semibold w-10">Pos</th>
                                <th class="px-4 py-3 font-semibold">Name</th>
                                <th class="px-4 py-3 font-semibold text-center">Subcats</th>
                                <th class="px-4 py-3 font-semibold text-center">Products</th>
                                <th class="px-4 py-3 font-semibold text-center">Menu</th>
                                <th class="px-4 py-3 font-semibold text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="cat in parentRows" :key="cat.id"
                                class="border-b dark:border-gray-700 hover:bg-primary-soft transition group">
                                <td class="px-4 py-3">
                                    <span class="inline-flex items-center justify-center w-7 h-7 rounded-full bg-primary-soft text-primary text-xs font-bold border border-primary-soft">
                                        {{ cat.menu_position }}
                                    </span>
                                </td>
                                <td class="px-4 py-3">
                                    <div class="flex items-center gap-2.5">
                                        <div v-if="cat.image" class="w-9 h-9 rounded-lg overflow-hidden border dark:border-gray-600 flex-shrink-0">
                                            <img :src="'/storage/' + cat.image" class="w-full h-full object-cover"/>
                                        </div>
                                        <div v-else class="w-9 h-9 rounded-lg bg-gray-100 dark:bg-gray-700 flex items-center justify-center text-gray-400 flex-shrink-0">📁</div>
                                        <div>
                                            <p class="font-semibold text-gray-900 dark:text-white leading-tight">{{ cat.name }}</p>
                                            <p v-if="cat.description" class="text-[11px] text-gray-400 truncate max-w-[150px]">{{ cat.description }}</p>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-4 py-3 text-center">
                                    <span class="inline-flex items-center justify-center min-w-[22px] h-6 px-1.5 rounded-full text-xs font-bold"
                                        :class="cat.children?.length ? 'theme-secondary-badge' : 'bg-gray-100 dark:bg-gray-700 text-gray-400'">
                                        {{ cat.children?.length ?? 0 }}
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-center">
                                    <span class="bg-gray-100 dark:bg-gray-700 px-2 py-0.5 rounded text-xs font-bold">{{ cat.products_count }}</span>
                                </td>
                                <td class="px-4 py-3 text-center">
                                    <button @click="toggleVisibility(cat)"
                                        class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-semibold transition hover:opacity-70"
                                        :class="cat.visible_in_menu
                                            ? 'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400'
                                            : 'bg-gray-100 text-gray-400 dark:bg-gray-700'">
                                        {{ cat.visible_in_menu ? '✓ On' : '✕ Off' }}
                                    </button>
                                </td>
                                <td class="px-4 py-3">
                                    <div class="flex items-center justify-end gap-1.5">
                                        <button @click="editMode(cat)"
                                            class="text-xs font-semibold text-primary hover:bg-primary-soft px-2.5 py-1.5 rounded-lg transition">
                                            ✏️ Edit
                                        </button>
                                        <button @click="deleteCategory(cat.id)"
                                            class="text-xs font-semibold text-red-500 hover:bg-red-50 dark:hover:bg-red-900/20 px-2.5 py-1.5 rounded-lg transition">
                                            🗑 Delete
                                        </button>
                                    </div>
                                </td>
                            </tr>
                            <tr v-if="!parentRows.length">
                                <td colspan="6" class="px-4 py-14 text-center">
                                    <template v-if="search">
                                        <div class="text-4xl mb-3">🔍</div>
                                        <p class="text-gray-500 dark:text-gray-400 font-medium">No parent categories found matching "{{ search }}".</p>
                                        <button @click="clearSearch" class="mt-2 text-xs font-semibold text-primary hover:underline">
                                            Clear search filter
                                        </button>
                                    </template>
                                    <template v-else>
                                        <div class="text-4xl mb-3">📁</div>
                                        <p class="text-gray-500 dark:text-gray-400 font-medium">No parent categories yet.</p>
                                        <button @click="openNewForm"
                                            class="mt-3 text-sm text-primary hover:underline font-semibold">
                                            + Add your first category
                                        </button>
                                    </template>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- ─── Subcategories (grouped by parent) ─── -->
                <div v-if="activeTab === 'subcategories'" class="overflow-y-auto max-h-[60vh] scrollbar-thin">
                    <div v-if="subRows.length">
                        <template v-for="parent in parent_categories" :key="parent.id">
                            <template v-if="subRows.filter(s => s.parent_id == parent.id).length">
                                <!-- Group header (sticky per group) -->
                                <div class="flex items-center gap-2 px-4 py-2 bg-primary-soft border-b dark:border-gray-700 sticky top-0 z-10">
                                    <span class="text-xs font-bold text-primary uppercase tracking-wide">📁 {{ parent.name }}</span>
                                    <span class="text-[11px] text-gray-500 dark:text-gray-400">
                                        · {{ subRows.filter(s => s.parent_id == parent.id).length }} subcategories
                                    </span>
                                </div>
                                <table class="w-full text-left text-sm">
                                    <tbody>
                                        <tr v-for="sub in subRows.filter(s => s.parent_id == parent.id)" :key="sub.id"
                                            class="border-b dark:border-gray-700 hover:bg-primary-soft transition group">
                                            <td class="px-4 py-3 w-10">
                                                <span class="inline-flex items-center justify-center w-7 h-7 rounded-full bg-secondary-soft text-secondary text-xs font-bold border border-secondary-soft">
                                                    {{ sub.menu_position }}
                                                </span>
                                            </td>
                                            <td class="px-4 py-3">
                                                <div class="flex items-center gap-2.5">
                                                    <div v-if="sub.image" class="w-9 h-9 rounded-lg overflow-hidden border dark:border-gray-600 flex-shrink-0">
                                                        <img :src="'/storage/' + sub.image" class="w-full h-full object-cover"/>
                                                    </div>
                                                    <div v-else class="w-9 h-9 rounded-lg bg-secondary-soft flex items-center justify-center text-secondary flex-shrink-0">📂</div>
                                                    <div>
                                                        <p class="font-semibold text-gray-900 dark:text-white leading-tight">{{ sub.name }}</p>
                                                        <p v-if="sub.description" class="text-[11px] text-gray-400 truncate max-w-[150px]">{{ sub.description }}</p>
                                                    </div>
                                                </div>
                                            </td>
                                            <td class="px-4 py-3 text-center">
                                                <span class="bg-gray-100 dark:bg-gray-700 px-2 py-0.5 rounded text-xs font-bold">{{ sub.products_count }}</span>
                                            </td>
                                            <td class="px-4 py-3 text-center">
                                                <button @click="toggleVisibility(sub)"
                                                    class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-semibold transition hover:opacity-70"
                                                    :class="sub.visible_in_menu
                                                        ? 'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400'
                                                        : 'bg-gray-100 text-gray-400 dark:bg-gray-700'">
                                                    {{ sub.visible_in_menu ? '✓ On' : '✕ Off' }}
                                                </button>
                                            </td>
                                            <td class="px-4 py-3">
                                                <div class="flex items-center justify-end gap-1.5">
                                                    <button @click="editMode(sub)"
                                                        class="text-xs font-semibold text-primary hover:bg-primary-soft px-2.5 py-1.5 rounded-lg transition">
                                                        ✏️ Edit
                                                    </button>
                                                    <button @click="deleteCategory(sub.id)"
                                                        class="text-xs font-semibold text-red-500 hover:bg-red-50 dark:hover:bg-red-900/20 px-2.5 py-1.5 rounded-lg transition">
                                                        🗑 Delete
                                                    </button>
                                                </div>
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </template>
                        </template>
                    </div>

                    <!-- Empty state -->
                    <div v-else class="px-4 py-14 text-center">
                        <template v-if="search">
                            <div class="text-4xl mb-3">🔍</div>
                            <p class="text-gray-500 dark:text-gray-400 font-medium">No subcategories found matching "{{ search }}".</p>
                            <button @click="clearSearch" class="mt-2 text-xs font-semibold text-secondary hover:underline">
                                Clear search filter
                            </button>
                        </template>
                        <template v-else>
                            <div class="text-4xl mb-3">📂</div>
                            <p class="text-gray-500 dark:text-gray-400 font-medium">No subcategories yet.</p>
                            <p class="text-xs text-gray-400 mt-1">
                                <span v-if="!parent_categories?.length" class="text-amber-600 dark:text-amber-400">
                                    ⚠️ Create a parent category first.
                                </span>
                                <template v-else>
                                    <button @click="openNewForm" class="text-secondary hover:underline font-semibold">
                                        + Add your first subcategory
                                    </button>
                                </template>
                            </p>
                        </template>
                    </div>
                </div>

                <!-- Pagination (outside scrollable area) -->
                <div v-if="categories?.last_page > 1" class="px-5 py-3 border-t dark:border-gray-700">
                    <Pagination :paginator="categories" />
                </div>

            </div><!-- end tab body -->
        </div>

        <!-- ══════════════════════════════════════════════════════════════ -->
        <!-- ── Modal: Add / Edit Category Dialog ────────────────────────── -->
        <!-- ══════════════════════════════════════════════════════════════ -->
        <div v-if="showForm" class="fixed inset-0 z-50 flex items-center justify-center p-3 sm:p-4 bg-black/60 backdrop-blur-xs">
            <div class="bg-white dark:bg-gray-800 rounded-2xl max-w-xl w-full max-h-[88vh] flex flex-col shadow-2xl border border-gray-200 dark:border-gray-700 overflow-hidden animate-fade-in">
                
                <!-- Fixed Header -->
                <div class="px-5 py-3.5 border-b border-gray-100 dark:border-gray-700 flex items-center justify-between shrink-0 bg-white dark:bg-gray-800">
                    <div>
                        <h3 class="text-base font-bold text-gray-900 dark:text-white flex items-center gap-2">
                            <span>{{ activeTab === 'parents' ? '📁' : '📂' }}</span>
                            {{ isEditing ? 'Edit ' : 'Add New ' }}{{ activeTab === 'parents' ? 'Category' : 'Subcategory' }}
                        </h3>
                        <p class="text-[11px] text-gray-500 dark:text-gray-400 mt-0.5">
                            {{ activeTab === 'parents' ? 'Top-level category displayed in your storefront mega menu.' : 'Sub-group nested under a parent category.' }}
                        </p>
                    </div>
                    <button type="button" @click="cancelForm" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-200 text-lg font-bold p-1 cursor-pointer">
                        ✕
                    </button>
                </div>

                <!-- Scrollable Form Body -->
                <form id="categoryForm" @submit.prevent="save" class="flex-1 overflow-y-auto p-4 sm:p-5 space-y-3.5 overscroll-contain text-xs">
                    
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3.5">
                        <!-- Category Name -->
                        <div>
                            <label class="block text-[11px] font-bold uppercase tracking-wider text-gray-600 dark:text-gray-300 mb-1">
                                {{ activeTab === 'parents' ? 'Category' : 'Subcategory' }} Name *
                            </label>
                            <input
                                v-model="form.name"
                                type="text"
                                required
                                :placeholder="activeTab === 'parents' ? 'e.g. Mobile Phones' : 'e.g. iPhones'"
                                class="w-full rounded-lg border border-gray-300 dark:border-gray-600 dark:bg-gray-700 px-3 py-1.5 text-xs focus:ring-2 focus:ring-primary shadow-xs"
                            />
                            <p v-if="form.errors.name" class="text-red-500 text-xs mt-1">{{ form.errors.name }}</p>
                        </div>

                        <!-- Slug -->
                        <div>
                            <label class="block text-[11px] font-bold uppercase tracking-wider text-gray-600 dark:text-gray-300 mb-1">
                                URL Slug *
                                <span class="font-normal text-gray-400 ml-1">— used for web link</span>
                            </label>
                            <input
                                v-model="form.slug"
                                type="text"
                                required
                                @input="handleSlugInput"
                                placeholder="e.g. mobile-phones"
                                class="w-full rounded-lg border border-gray-300 dark:border-gray-600 dark:bg-gray-700 px-3 py-1.5 text-xs font-mono focus:ring-2 focus:ring-primary shadow-xs"
                            />
                            <p v-if="form.errors.slug" class="text-red-500 text-xs mt-1">{{ form.errors.slug }}</p>
                        </div>
                    </div>

                    <!-- Parent selector (subcategories only) -->
                    <div v-if="activeTab === 'subcategories'">
                        <label class="block text-[11px] font-bold uppercase tracking-wider text-gray-600 dark:text-gray-300 mb-1">
                            Parent Category *
                        </label>
                        <select
                            v-model="form.parent_id"
                            required
                            class="w-full rounded-lg border border-gray-300 dark:border-gray-600 dark:bg-gray-700 px-3 py-1.5 text-xs focus:ring-2 focus:ring-primary shadow-xs"
                        >
                            <option :value="null" disabled>— Select parent category —</option>
                            <option
                                v-for="parent in parent_categories"
                                :key="parent.id"
                                :value="parent.id"
                                :disabled="editId === parent.id"
                            >
                                {{ parent.name }}
                            </option>
                        </select>
                        <p v-if="!parent_categories?.length" class="text-[11px] text-amber-600 dark:text-amber-400 mt-1">
                            ⚠️ Create a parent category first.
                        </p>
                        <p v-if="form.errors.parent_id" class="text-red-500 text-xs mt-1">{{ form.errors.parent_id }}</p>
                    </div>

                    <!-- Description -->
                    <div>
                        <label class="block text-[11px] font-bold uppercase tracking-wider text-gray-600 dark:text-gray-300 mb-1">
                            Description
                        </label>
                        <textarea
                            v-model="form.description"
                            rows="2"
                            placeholder="Optional category summary..."
                            class="w-full rounded-lg border border-gray-300 dark:border-gray-600 dark:bg-gray-700 px-3 py-1.5 text-xs focus:ring-2 focus:ring-primary shadow-xs resize-none"
                        ></textarea>
                        <p v-if="form.errors.description" class="text-red-500 text-xs mt-1">{{ form.errors.description }}</p>
                    </div>

                    <!-- Image Upload & Compression -->
                    <div class="space-y-2">
                        <div class="flex items-center justify-between">
                            <label class="block text-[11px] font-bold uppercase tracking-wider text-gray-600 dark:text-gray-300">
                                Category Thumbnail
                                <span class="font-normal text-gray-400 ml-1">(Max 1MB)</span>
                            </label>
                            <span class="text-[10px] text-gray-400">JPG, PNG, WebP up to 1MB</span>
                        </div>

                        <!-- Image Compression Toggle (On by Default) -->
                        <div class="p-2.5 rounded-xl bg-emerald-50/70 dark:bg-emerald-950/20 border border-emerald-200/80 dark:border-emerald-800/40 flex items-center justify-between gap-3">
                            <div class="flex items-start gap-2">
                                <span class="text-emerald-600 dark:text-emerald-400 text-xs mt-0.5">⚡</span>
                                <div>
                                    <p class="text-xs font-bold text-gray-800 dark:text-gray-200 leading-tight">
                                        Compress & Optimize Image
                                    </p>
                                    <p class="text-[10px] text-gray-500 dark:text-gray-400 leading-tight mt-0.5">
                                        Auto-scales and converts to modern WebP format for faster store loading.
                                    </p>
                                </div>
                            </div>
                            <button
                                type="button"
                                @click="form.compress_images = !form.compress_images"
                                class="relative inline-flex h-5 w-9 shrink-0 items-center rounded-full transition cursor-pointer"
                                :class="form.compress_images ? 'bg-emerald-600' : 'bg-gray-200 dark:bg-gray-600'"
                                :title="form.compress_images ? 'Image compression enabled' : 'Image compression disabled'"
                            >
                                <span
                                    class="inline-block h-3.5 w-3.5 transform rounded-full bg-white transition shadow"
                                    :class="form.compress_images ? 'translate-x-4' : 'translate-x-0.5'"
                                />
                            </button>
                        </div>

                        <div class="flex items-center gap-3 p-2.5 rounded-xl border border-gray-200 dark:border-gray-700 bg-gray-50/50 dark:bg-gray-900/30">
                            <div v-if="imagePreview" class="relative w-12 h-12 rounded-lg overflow-hidden border dark:border-gray-600 flex-shrink-0">
                                <img :src="imagePreview" class="w-full h-full object-cover"/>
                                <button
                                    type="button"
                                    @click="removeImage"
                                    class="absolute top-0 right-0 bg-red-500 text-white w-4 h-4 text-[10px] flex items-center justify-center rounded-bl-md cursor-pointer"
                                    title="Remove image"
                                >
                                    ✕
                                </button>
                            </div>
                            <div v-else class="w-12 h-12 rounded-lg border-2 border-dashed border-gray-300 dark:border-gray-600 flex items-center justify-center text-gray-400 flex-shrink-0 text-base">
                                📷
                            </div>
                            <div class="flex-1 min-w-0">
                                <input
                                    ref="categoryImageInputRef"
                                    type="file"
                                    accept="image/*"
                                    @change="handleImageUpload"
                                    class="text-xs text-gray-500 file:mr-2 file:py-1 file:px-2.5 file:rounded-md file:border-0 file:text-[11px] file:font-semibold file:bg-gray-100 dark:file:bg-gray-700 file:text-primary cursor-pointer w-full"
                                />
                                <p v-if="form.errors.image" class="text-red-500 text-xs mt-1 font-semibold">{{ form.errors.image }}</p>
                            </div>
                        </div>
                    </div>

                    <!-- Visibility & Menu Position -->
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3.5 p-3 rounded-xl bg-gray-50 dark:bg-gray-900/50 border border-gray-200 dark:border-gray-700">
                        <div>
                            <p class="text-xs font-bold text-gray-900 dark:text-white">Storefront Menu Visibility</p>
                            <p class="text-[10px] text-gray-500 dark:text-gray-400 mb-2">Show in storefront navigation</p>
                            <button
                                type="button"
                                @click="form.visible_in_menu = !form.visible_in_menu"
                                class="flex items-center gap-2 cursor-pointer"
                            >
                                <span
                                    class="relative inline-flex h-5 w-9 items-center rounded-full transition"
                                    :class="form.visible_in_menu ? 'bg-primary' : 'bg-gray-300 dark:bg-gray-600'"
                                >
                                    <span
                                        class="inline-block h-3.5 w-3.5 rounded-full bg-white transition transform shadow"
                                        :class="form.visible_in_menu ? 'translate-x-4.5' : 'translate-x-1'"
                                    />
                                </span>
                                <span class="text-xs font-semibold" :class="form.visible_in_menu ? 'text-primary' : 'text-gray-400'">
                                    {{ form.visible_in_menu ? 'Visible in Menu' : 'Hidden from Menu' }}
                                </span>
                            </button>
                        </div>

                        <div>
                            <label class="block text-[11px] font-bold uppercase tracking-wider text-gray-600 dark:text-gray-300 mb-1">
                                Menu Sort Order
                            </label>
                            <input
                                v-model.number="form.menu_position"
                                type="number"
                                min="0"
                                placeholder="0 = first"
                                class="w-full rounded-lg border border-gray-300 dark:border-gray-600 dark:bg-gray-700 px-3 py-1.5 text-xs focus:ring-2 focus:ring-primary shadow-xs"
                            />
                            <p class="text-[10px] text-gray-400 mt-1">Lower numbers display first (e.g. 0, 1, 2)</p>
                        </div>
                    </div>

                    <!-- Filterable Attributes (Parents only) -->
                    <div v-if="activeTab === 'parents'" class="p-3 rounded-xl border border-gray-200 dark:border-gray-700">
                        <label class="block text-[11px] font-bold uppercase tracking-wider text-gray-600 dark:text-gray-300 mb-1.5">
                            Filterable Product Attributes
                        </label>
                        <div v-if="attributes?.length" class="flex flex-wrap gap-1.5 max-h-32 overflow-y-auto">
                            <button
                                v-for="attr in attributes"
                                :key="attr.id"
                                type="button"
                                @click="toggleAttr(attr.id)"
                                class="px-2.5 py-1 rounded-lg border text-[11px] font-medium transition cursor-pointer"
                                :class="form.attribute_ids.includes(attr.id)
                                    ? 'bg-primary text-white border-primary shadow-xs'
                                    : 'bg-white dark:bg-gray-700 border-gray-300 dark:border-gray-600 hover:border-primary text-gray-700 dark:text-gray-300'"
                            >
                                {{ attr.name }} <span class="opacity-50">({{ attr.type }})</span>
                            </button>
                        </div>
                        <p v-else class="text-xs text-gray-400">
                            No attributes yet.
                            <Link :href="route('admin.attributes.index')" class="text-primary hover:underline">Create some →</Link>
                        </p>
                        <p v-if="form.attribute_ids.length" class="text-[11px] text-primary font-semibold mt-1.5">
                            {{ form.attribute_ids.length }} attribute(s) assigned
                        </p>
                    </div>

                </form>

                <!-- Fixed Footer (Always visible) -->
                <div class="px-5 py-3 border-t border-gray-100 dark:border-gray-700 bg-gray-50/90 dark:bg-gray-800/90 flex items-center justify-end gap-2.5 shrink-0">
                    <button
                        type="button"
                        @click="cancelForm"
                        class="px-4 py-2 text-xs font-semibold text-gray-600 dark:text-gray-300 hover:bg-gray-200/60 dark:hover:bg-gray-700 rounded-xl transition cursor-pointer"
                    >
                        Cancel
                    </button>
                    <button
                        type="submit"
                        form="categoryForm"
                        :disabled="form.processing"
                        class="text-white font-bold px-5 py-2 rounded-xl text-xs shadow-xs transition disabled:opacity-60 flex items-center gap-1.5 cursor-pointer"
                        :class="activeTab === 'parents' ? 'bg-primary hover:bg-primary-dark' : 'bg-secondary hover:bg-secondary-dark'"
                    >
                        <svg v-if="form.processing" class="w-3.5 h-3.5 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path></svg>
                        {{ form.processing ? 'Saving...' : (isEditing ? 'Update ' : 'Create ') + (activeTab === 'parents' ? 'Category' : 'Subcategory') }}
                    </button>
                </div>

            </div>
        </div>
    </AdminLayout>
</template>

<style scoped>
.scrollbar-thin::-webkit-scrollbar      { width: 5px; height: 5px; }
.scrollbar-thin::-webkit-scrollbar-track { background: transparent; }
.scrollbar-thin::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 9999px; }
.dark .scrollbar-thin::-webkit-scrollbar-thumb { background: #475569; }
</style>
