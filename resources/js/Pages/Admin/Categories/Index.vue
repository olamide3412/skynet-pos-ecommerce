<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import Pagination from '@/Components/Pagination.vue';
import { ref, computed, nextTick, watch } from 'vue';
import { useToast } from 'vue-toastification';

const toast = useToast();
const props = defineProps({
    categories:        Object,
    attributes:        Array,
    parent_categories: Array,
});

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

// ── Computed splits ────────────────────────────────────────────
const parentRows = computed(() => props.categories?.data?.filter(c => !c.parent_id) ?? []);
const subRows    = computed(() => props.categories?.data?.filter(c =>  c.parent_id) ?? []);

// ── Form state ─────────────────────────────────────────────────
const isEditing    = ref(false);
const editId       = ref(null);
const imagePreview = ref(null);
const formRef      = ref(null);

const form = useForm({
    _method:         'POST',
    name:            '',
    slug:            '',
    description:     '',
    parent_id:       null,
    image:           null,
    remove_image:    false,
    visible_in_menu: true,
    menu_position:   0,
    attribute_ids:   [],
});

const save = () => {
    if (isEditing.value) {
        form.post(route('admin.categories.update', editId.value));
    } else {
        form.post(route('admin.categories.store'));
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
    showForm.value = true;
    nextTick(() => formRef.value?.scrollIntoView({ behavior: 'smooth', block: 'start' }));
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
    form.remove_image = false;
    imagePreview.value= category.image ? '/storage/' + category.image : null;
    form.visible_in_menu = category.visible_in_menu;
    form.menu_position   = category.menu_position;
    form.attribute_ids   = category.attributes?.map(a => a.id) ?? [];
    form.clearErrors();
    showForm.value = true;
    nextTick(() => formRef.value?.scrollIntoView({ behavior: 'smooth', block: 'start' }));
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
    form.remove_image    = false;
    form.parent_id       = null;
    form.visible_in_menu = true;
    form.menu_position   = 0;
    form.attribute_ids   = [];
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
    form.image = e.target.files[0];
    if (form.image) {
        imagePreview.value = URL.createObjectURL(form.image);
        form.remove_image = false;
    }
};
const removeImage = () => {
    form.image = null;
    imagePreview.value = null;
    form.remove_image = true;
};

const toggleAttr = (id) => {
    const idx = form.attribute_ids.indexOf(id);
    if (idx === -1) form.attribute_ids.push(id);
    else form.attribute_ids.splice(idx, 1);
};

const deleteCategory = (id) => {
    if (!confirm('Delete this category? Products and subcategories will lose their association.')) return;
    useForm({}).delete(route('admin.categories.destroy', id), {
        onSuccess: () => toast.success('Category deleted.'),
    });
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
                    class="inline-flex items-center gap-1.5 text-sm font-semibold text-blue-600 dark:text-blue-400 hover:underline">
                    ⚙️ Manage Attributes
                </Link>
            </div>

            <!-- ── Stats Strip ─────────────────────────────── -->
            <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 mb-5">
                <div class="bg-white dark:bg-gray-800 rounded-xl border dark:border-gray-700 px-4 py-3 flex items-center gap-3">
                    <span class="text-2xl">📁</span>
                    <div>
                        <p class="text-[11px] text-gray-500 dark:text-gray-400 leading-tight">Parent Categories</p>
                        <p class="text-xl font-bold text-blue-600 dark:text-blue-400">{{ parentRows.length }}</p>
                    </div>
                </div>
                <div class="bg-white dark:bg-gray-800 rounded-xl border dark:border-gray-700 px-4 py-3 flex items-center gap-3">
                    <span class="text-2xl">📂</span>
                    <div>
                        <p class="text-[11px] text-gray-500 dark:text-gray-400 leading-tight">Subcategories</p>
                        <p class="text-xl font-bold text-indigo-600 dark:text-indigo-400">{{ subRows.length }}</p>
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

            <!-- ── Tabs ─────────────────────────────────────── -->
            <div class="flex gap-0 border-b dark:border-gray-700">
                <button @click="switchTab('parents')"
                    class="px-6 py-3 text-sm font-semibold border-b-2 -mb-px transition-colors"
                    :class="activeTab === 'parents'
                        ? 'border-blue-600 text-blue-600 dark:text-blue-400 dark:border-blue-400'
                        : 'border-transparent text-gray-500 dark:text-gray-400 hover:text-gray-800 dark:hover:text-gray-200'">
                    <span class="flex items-center gap-2">
                        📁 Parent Categories
                        <span class="inline-flex items-center justify-center w-5 h-5 rounded-full text-[10px] font-bold"
                            :class="activeTab === 'parents' ? 'bg-blue-600 text-white' : 'bg-gray-100 dark:bg-gray-700 text-gray-600 dark:text-gray-300'">
                            {{ parentRows.length }}
                        </span>
                    </span>
                </button>
                <button @click="switchTab('subcategories')"
                    class="px-6 py-3 text-sm font-semibold border-b-2 -mb-px transition-colors"
                    :class="activeTab === 'subcategories'
                        ? 'border-indigo-600 text-indigo-600 dark:text-indigo-400 dark:border-indigo-400'
                        : 'border-transparent text-gray-500 dark:text-gray-400 hover:text-gray-800 dark:hover:text-gray-200'">
                    <span class="flex items-center gap-2">
                        📂 Subcategories
                        <span class="inline-flex items-center justify-center w-5 h-5 rounded-full text-[10px] font-bold"
                            :class="activeTab === 'subcategories' ? 'bg-indigo-600 text-white' : 'bg-gray-100 dark:bg-gray-700 text-gray-600 dark:text-gray-300'">
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
                        class="inline-flex items-center gap-2 px-4 py-2 rounded-lg text-sm font-bold transition shadow-sm"
                        :class="showForm && !isEditing
                            ? 'bg-gray-200 dark:bg-gray-600 text-gray-600 dark:text-gray-300 cursor-default'
                            : activeTab === 'parents'
                                ? 'bg-blue-600 hover:bg-blue-700 text-white'
                                : 'bg-indigo-600 hover:bg-indigo-700 text-white'"
                        :disabled="showForm && !isEditing">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 4v16m8-8H4"/>
                        </svg>
                        Add {{ activeTab === 'parents' ? 'Category' : 'Subcategory' }}
                    </button>
                </div>

                <!-- ══ Collapsible Form Panel ══════════════════ -->
                <transition
                    enter-active-class="transition-all duration-300 ease-out"
                    enter-from-class="opacity-0 -translate-y-2"
                    enter-to-class="opacity-100 translate-y-0"
                    leave-active-class="transition-all duration-200 ease-in"
                    leave-from-class="opacity-100 translate-y-0"
                    leave-to-class="opacity-0 -translate-y-2">

                    <div v-if="showForm" ref="formRef"
                        class="border-b dark:border-gray-700 bg-gray-50 dark:bg-gray-900/40 px-5 py-5">

                        <!-- Form header -->
                        <div class="flex items-center justify-between mb-4">
                            <h2 class="font-bold text-base flex items-center gap-2">
                                <span v-if="isEditing" class="text-amber-600 dark:text-amber-400">✏️ Editing: {{ form.name || '…' }}</span>
                                <span v-else class="text-gray-800 dark:text-white">
                                    ➕ New {{ activeTab === 'parents' ? 'Parent Category' : 'Subcategory' }}
                                </span>
                            </h2>
                            <button type="button" @click="cancelForm"
                                class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-200 transition p-1 rounded-lg hover:bg-gray-200 dark:hover:bg-gray-700"
                                title="Close form">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M6 18L18 6M6 6l12 12"/>
                                </svg>
                            </button>
                        </div>

                        <form @submit.prevent="save">
                            <!-- 2-col layout for form fields -->
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">

                                <!-- Name -->
                                <div>
                                    <label class="block text-sm font-semibold mb-1">
                                        {{ activeTab === 'parents' ? 'Category' : 'Subcategory' }} Name *
                                    </label>
                                    <input v-model="form.name" type="text" required
                                        :placeholder="activeTab === 'parents' ? 'e.g. Mobile Phones' : 'e.g. iPhones'"
                                        class="w-full rounded-lg border border-gray-300 dark:border-gray-600 dark:bg-gray-800 px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500"/>
                                    <p v-if="form.errors.name" class="text-red-500 text-xs mt-1">{{ form.errors.name }}</p>
                                </div>

                                <!-- Slug -->
                                <div>
                                    <label class="block text-sm font-semibold mb-1">
                                        URL Slug *
                                        <span class="font-normal text-gray-400 ml-1">— used for web link</span>
                                    </label>
                                    <input v-model="form.slug" type="text" required @input="handleSlugInput"
                                        placeholder="e.g. mobile-phones"
                                        class="w-full rounded-lg border border-gray-300 dark:border-gray-600 dark:bg-gray-800 px-3 py-2 text-sm font-mono focus:ring-2 focus:ring-blue-500"/>
                                    <p v-if="form.errors.slug" class="text-red-500 text-xs mt-1">{{ form.errors.slug }}</p>
                                </div>

                                <!-- Parent selector (subcategories only) -->
                                <div v-if="activeTab === 'subcategories'">
                                    <label class="block text-sm font-semibold mb-1">
                                        Parent Category *
                                    </label>
                                    <select v-model="form.parent_id" required
                                        class="w-full rounded-lg border border-gray-300 dark:border-gray-600 dark:bg-gray-800 px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500">
                                        <option :value="null" disabled>— Select parent —</option>
                                        <option v-for="parent in parent_categories" :key="parent.id" :value="parent.id"
                                            :disabled="editId === parent.id">
                                            {{ parent.name }}
                                        </option>
                                    </select>
                                    <p v-if="!parent_categories?.length" class="text-xs text-amber-600 dark:text-amber-400 mt-1">
                                        ⚠️ Create a parent category first.
                                    </p>
                                    <p v-if="form.errors.parent_id" class="text-red-500 text-xs mt-1">{{ form.errors.parent_id }}</p>
                                </div>

                                <!-- Description -->
                                <div :class="activeTab === 'parents' ? 'md:col-span-1' : 'md:col-span-1'">
                                    <label class="block text-sm font-semibold mb-1">Description</label>
                                    <textarea v-model="form.description" rows="2"
                                        placeholder="Optional short description"
                                        class="w-full rounded-lg border border-gray-300 dark:border-gray-600 dark:bg-gray-800 px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500 resize-none"></textarea>
                                </div>

                                <!-- Image -->
                                <div>
                                    <label class="block text-sm font-semibold mb-1">Image</label>
                                    <div class="flex items-center gap-3">
                                        <div v-if="imagePreview" class="relative w-12 h-12 rounded-lg overflow-hidden border dark:border-gray-600 flex-shrink-0">
                                            <img :src="imagePreview" class="w-full h-full object-cover"/>
                                            <button type="button" @click="removeImage"
                                                class="absolute top-0 right-0 bg-red-500 text-white w-4 h-4 text-[10px] flex items-center justify-center rounded-bl-md">✕</button>
                                        </div>
                                        <div v-else class="w-12 h-12 rounded-lg border-2 border-dashed border-gray-300 dark:border-gray-600 flex items-center justify-center text-gray-400 flex-shrink-0 text-lg">📷</div>
                                        <input type="file" accept="image/*" @change="handleImageUpload"
                                            class="text-xs text-gray-500 file:mr-2 file:py-1.5 file:px-2.5 file:rounded-md file:border-0 file:text-xs file:font-semibold file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100 flex-1"/>
                                    </div>
                                </div>

                                <!-- Visibility + Position -->
                                <div class="flex items-center gap-5">
                                    <div>
                                        <label class="block text-sm font-semibold mb-2">Menu Visibility</label>
                                        <button type="button" @click="form.visible_in_menu = !form.visible_in_menu"
                                            class="flex items-center gap-2">
                                            <span class="relative inline-flex h-6 w-11 items-center rounded-full transition"
                                                :class="form.visible_in_menu ? 'bg-blue-600' : 'bg-gray-300 dark:bg-gray-600'">
                                                <span class="inline-block h-4 w-4 rounded-full bg-white transition transform"
                                                    :class="form.visible_in_menu ? 'translate-x-6' : 'translate-x-1'"/>
                                            </span>
                                            <span class="text-sm font-medium" :class="form.visible_in_menu ? 'text-blue-600' : 'text-gray-400'">
                                                {{ form.visible_in_menu ? 'Visible' : 'Hidden' }}
                                            </span>
                                        </button>
                                    </div>
                                    <div class="flex-1">
                                        <label class="block text-sm font-semibold mb-1">Position</label>
                                        <input v-model.number="form.menu_position" type="number" min="0"
                                            placeholder="0 = first"
                                            class="w-full rounded-lg border border-gray-300 dark:border-gray-600 dark:bg-gray-800 px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500"/>
                                    </div>
                                </div>

                                <!-- Filterable Attributes (parents only) — full width -->
                                <div v-if="activeTab === 'parents'" class="md:col-span-2">
                                    <label class="block text-sm font-semibold mb-1">Filterable Attributes</label>
                                    <div v-if="attributes?.length" class="flex flex-wrap gap-1.5">
                                        <button v-for="attr in attributes" :key="attr.id"
                                            type="button" @click="toggleAttr(attr.id)"
                                            class="px-2.5 py-1 rounded-lg border text-xs font-medium transition"
                                            :class="form.attribute_ids.includes(attr.id)
                                                ? 'bg-blue-600 text-white border-blue-600'
                                                : 'bg-white dark:bg-gray-700 border-gray-300 dark:border-gray-600 hover:border-blue-400'">
                                            {{ attr.name }} <span class="opacity-50">({{ attr.type }})</span>
                                        </button>
                                    </div>
                                    <p v-else class="text-xs text-gray-400">
                                        No attributes yet.
                                        <Link :href="route('admin.attributes.index')" class="text-blue-500 hover:underline">Create some →</Link>
                                    </p>
                                    <p v-if="form.attribute_ids.length" class="text-xs text-blue-500 mt-1">
                                        {{ form.attribute_ids.length }} selected
                                    </p>
                                </div>
                            </div>

                            <!-- Submit row -->
                            <div class="flex items-center gap-3 mt-5 pt-4 border-t dark:border-gray-700">
                                <button type="submit" :disabled="form.processing"
                                    class="px-6 py-2 rounded-lg font-bold text-sm text-white transition disabled:opacity-60"
                                    :class="activeTab === 'parents' ? 'bg-blue-600 hover:bg-blue-700' : 'bg-indigo-600 hover:bg-indigo-700'">
                                    <span v-if="form.processing">Saving…</span>
                                    <span v-else>{{ isEditing ? 'Update' : 'Create' }} {{ activeTab === 'parents' ? 'Category' : 'Subcategory' }}</span>
                                </button>
                                <button type="button" @click="cancelForm"
                                    class="px-5 py-2 rounded-lg border dark:border-gray-600 hover:bg-gray-100 dark:hover:bg-gray-700 transition font-medium text-sm">
                                    Cancel
                                </button>
                                <p v-if="isEditing" class="text-xs text-gray-400 ml-auto">Editing ID #{{ editId }}</p>
                            </div>
                        </form>
                    </div>
                </transition>

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
                                class="border-b dark:border-gray-700 hover:bg-blue-50/50 dark:hover:bg-blue-900/10 transition group">
                                <td class="px-4 py-3">
                                    <span class="inline-flex items-center justify-center w-7 h-7 rounded-full bg-blue-50 dark:bg-blue-900/20 text-blue-700 dark:text-blue-400 text-xs font-bold border border-blue-100 dark:border-blue-800">
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
                                        :class="cat.children?.length ? 'bg-indigo-100 dark:bg-indigo-900/30 text-indigo-700 dark:text-indigo-400' : 'bg-gray-100 dark:bg-gray-700 text-gray-400'">
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
                                            class="text-xs font-semibold text-blue-600 dark:text-blue-400 hover:bg-blue-50 dark:hover:bg-blue-900/20 px-2.5 py-1.5 rounded-lg transition">
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
                                    <div class="text-4xl mb-3">📁</div>
                                    <p class="text-gray-500 dark:text-gray-400 font-medium">No parent categories yet.</p>
                                    <button @click="openNewForm"
                                        class="mt-3 text-sm text-blue-600 dark:text-blue-400 hover:underline font-semibold">
                                        + Add your first category
                                    </button>
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
                                <div class="flex items-center gap-2 px-4 py-2 bg-indigo-50 dark:bg-indigo-950/40 border-b dark:border-gray-700 sticky top-0 z-10">
                                    <span class="text-xs font-bold text-indigo-700 dark:text-indigo-400 uppercase tracking-wide">📁 {{ parent.name }}</span>
                                    <span class="text-[11px] text-indigo-400">
                                        · {{ subRows.filter(s => s.parent_id == parent.id).length }} subcategories
                                    </span>
                                </div>
                                <table class="w-full text-left text-sm">
                                    <tbody>
                                        <tr v-for="sub in subRows.filter(s => s.parent_id == parent.id)" :key="sub.id"
                                            class="border-b dark:border-gray-700 hover:bg-indigo-50/50 dark:hover:bg-indigo-900/10 transition group">
                                            <td class="px-4 py-3 w-10">
                                                <span class="inline-flex items-center justify-center w-7 h-7 rounded-full bg-indigo-50 dark:bg-indigo-900/20 text-indigo-600 dark:text-indigo-400 text-xs font-bold border border-indigo-100 dark:border-indigo-800">
                                                    {{ sub.menu_position }}
                                                </span>
                                            </td>
                                            <td class="px-4 py-3">
                                                <div class="flex items-center gap-2.5">
                                                    <div v-if="sub.image" class="w-9 h-9 rounded-lg overflow-hidden border dark:border-gray-600 flex-shrink-0">
                                                        <img :src="'/storage/' + sub.image" class="w-full h-full object-cover"/>
                                                    </div>
                                                    <div v-else class="w-9 h-9 rounded-lg bg-indigo-50 dark:bg-indigo-900/20 flex items-center justify-center text-indigo-300 flex-shrink-0">📂</div>
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
                                                        class="text-xs font-semibold text-blue-600 dark:text-blue-400 hover:bg-blue-50 dark:hover:bg-blue-900/20 px-2.5 py-1.5 rounded-lg transition">
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
                        <div class="text-4xl mb-3">📂</div>
                        <p class="text-gray-500 dark:text-gray-400 font-medium">No subcategories yet.</p>
                        <p class="text-xs text-gray-400 mt-1">
                            <span v-if="!parent_categories?.length" class="text-amber-600 dark:text-amber-400">
                                ⚠️ Create a parent category first.
                            </span>
                            <template v-else>
                                <button @click="openNewForm" class="text-indigo-600 dark:text-indigo-400 hover:underline font-semibold">
                                    + Add your first subcategory
                                </button>
                            </template>
                        </p>
                    </div>
                </div>

                <!-- Pagination (outside scrollable area) -->
                <div v-if="categories?.last_page > 1" class="px-5 py-3 border-t dark:border-gray-700">
                    <Pagination :paginator="categories" />
                </div>

            </div><!-- end tab body -->
        </div>
    </AdminLayout>
</template>

<style scoped>
.scrollbar-thin::-webkit-scrollbar      { width: 5px; height: 5px; }
.scrollbar-thin::-webkit-scrollbar-track { background: transparent; }
.scrollbar-thin::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 9999px; }
.dark .scrollbar-thin::-webkit-scrollbar-thumb { background: #475569; }
</style>
