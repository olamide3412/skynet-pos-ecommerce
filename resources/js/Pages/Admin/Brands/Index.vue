<script setup>
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import Pagination from '@/Components/Pagination.vue';
import { ref, watch } from 'vue';

const props = defineProps({
    brands: Object,
    filters: Object,
    stats: Object,
});

const search = ref(props.filters?.search || '');
const statusFilter = ref(props.filters?.status || '');
const sourceFilter = ref(props.filters?.source || '');

let searchTimeout = null;
const applyFilters = () => {
    router.get(
        route('admin.brands.index'),
        {
            search: search.value || undefined,
            status: statusFilter.value || undefined,
            source: sourceFilter.value || undefined,
        },
        { preserveState: true, replace: true }
    );
};

watch(search, () => {
    clearTimeout(searchTimeout);
    searchTimeout = setTimeout(applyFilters, 300);
});

watch([statusFilter, sourceFilter], applyFilters);

const generateSlug = (text) => {
    return (text || '')
        .toString()
        .toLowerCase()
        .replace(/\s+/g, '-')
        .replace(/[^\w\-]+/g, '')
        .replace(/\-\-+/g, '-')
        .replace(/^-+/, '')
        .replace(/-+$/, '');
};

const showModal = ref(false);
const isEditing = ref(false);
const editingBrand = ref(null);
const slugChangedManually = ref(false);
const logoPreview = ref(null);

const form = useForm({
    _method: 'POST',
    name: '',
    slug: '',
    description: '',
    logo: null,
    remove_logo: false,
    is_active: true,
    is_featured: false,
    sort_order: 0,
    meta_title: '',
    meta_description: '',
});

watch(
    () => form.name,
    (newName) => {
        if (!slugChangedManually.value && !isEditing.value) {
            form.slug = generateSlug(newName);
        }
    }
);

const openCreateModal = () => {
    isEditing.value = false;
    editingBrand.value = null;
    slugChangedManually.value = false;
    logoPreview.value = null;
    form.reset();
    form.clearErrors();
    form._method = 'POST';
    form.is_active = true;
    form.is_featured = false;
    form.sort_order = 0;
    showModal.value = true;
};

const openEditModal = (brand) => {
    isEditing.value = true;
    editingBrand.value = brand;
    slugChangedManually.value = true;
    form.clearErrors();
    form._method = 'PUT';
    form.name = brand.name;
    form.slug = brand.slug || '';
    form.description = brand.description || '';
    form.logo = null;
    form.remove_logo = false;
    form.is_active = Boolean(brand.is_active);
    form.is_featured = Boolean(brand.is_featured);
    form.sort_order = brand.sort_order ?? 0;
    form.meta_title = brand.meta_title || '';
    form.meta_description = brand.meta_description || '';
    logoPreview.value = brand.logo_url || (brand.logo ? `/storage/${brand.logo}` : null);
    showModal.value = true;
};

const handleLogoChange = (e) => {
    const file = e.target.files?.[0];
    if (file) {
        form.logo = file;
        form.remove_logo = false;
        logoPreview.value = URL.createObjectURL(file);
    }
};

const clearLogo = () => {
    form.logo = null;
    form.remove_logo = true;
    logoPreview.value = null;
};

const submitForm = () => {
    const opts = {
        preserveScroll: true,
        onSuccess: () => {
            showModal.value = false;
            form.reset();
        },
    };

    if (isEditing.value && editingBrand.value) {
        form.post(route('admin.brands.update', editingBrand.value.id), opts);
    } else {
        form.post(route('admin.brands.store'), opts);
    }
};

const deleteBrand = (brand) => {
    if (brand.products_count > 0) {
        const detachConfirmed = confirm(
            `Brand "${brand.name}" is currently assigned to ${brand.products_count} product(s).\n\nClick OK to detach those products and delete this brand, or Cancel to keep it.`
        );
        if (!detachConfirmed) return;
        router.delete(route('admin.brands.destroy', { brand: brand.id, detach: 1 }), {
            preserveScroll: true,
        });
    } else {
        if (!confirm(`Are you sure you want to delete the brand "${brand.name}"?`)) return;
        router.delete(route('admin.brands.destroy', brand.id), {
            preserveScroll: true,
        });
    }
};
</script>

<template>
    <Head title="Product Brands" />
    <AdminLayout>
        <div class="space-y-6">
            <!-- Header -->
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                <div>
                    <h1 class="text-2xl font-bold text-gray-900 dark:text-white">Product Brands</h1>
                    <p class="text-sm text-gray-500 dark:text-gray-400">
                        Manage standalone and POS-synchronized product brands across your catalog.
                    </p>
                </div>
                <button
                    @click="openCreateModal"
                    class="inline-flex items-center gap-2 px-4 py-2.5 bg-primary hover:bg-primary-dark text-white text-sm font-semibold rounded-xl shadow-sm transition cursor-pointer"
                >
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                    </svg>
                    <span>Add Brand</span>
                </button>
            </div>

            <!-- Stats Row -->
            <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
                <div class="bg-white dark:bg-gray-800 rounded-xl p-4 border border-gray-200 dark:border-gray-700">
                    <div class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Total Brands</div>
                    <div class="text-2xl font-bold text-gray-900 dark:text-white mt-1">{{ stats?.total ?? 0 }}</div>
                </div>
                <div class="bg-white dark:bg-gray-800 rounded-xl p-4 border border-gray-200 dark:border-gray-700">
                    <div class="text-xs font-medium text-emerald-600 dark:text-emerald-400 uppercase">Active</div>
                    <div class="text-2xl font-bold text-emerald-600 dark:text-emerald-400 mt-1">{{ stats?.active ?? 0 }}</div>
                </div>
                <div class="bg-white dark:bg-gray-800 rounded-xl p-4 border border-gray-200 dark:border-gray-700">
                    <div class="text-xs font-medium text-amber-600 dark:text-amber-400 uppercase">Featured</div>
                    <div class="text-2xl font-bold text-amber-600 dark:text-amber-400 mt-1">{{ stats?.featured ?? 0 }}</div>
                </div>
                <div class="bg-white dark:bg-gray-800 rounded-xl p-4 border border-gray-200 dark:border-gray-700">
                    <div class="text-xs font-medium text-indigo-600 dark:text-indigo-400 uppercase">POS Linked</div>
                    <div class="text-2xl font-bold text-indigo-600 dark:text-indigo-400 mt-1">{{ stats?.pos_linked ?? 0 }}</div>
                </div>
            </div>

            <!-- Filters -->
            <div class="bg-white dark:bg-gray-800 rounded-xl p-4 border border-gray-200 dark:border-gray-700 flex flex-col sm:flex-row gap-3">
                <div class="relative flex-1">
                    <input
                        v-model="search"
                        type="text"
                        placeholder="Search brands by name or slug..."
                        class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white text-sm pl-9 pr-4 py-2"
                    />
                    <svg class="w-4 h-4 text-gray-400 absolute left-3 top-2.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                    </svg>
                </div>
                <select
                    v-model="statusFilter"
                    class="rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white text-sm px-3 py-2"
                >
                    <option value="">All Statuses</option>
                    <option value="active">Active Only</option>
                    <option value="inactive">Inactive Only</option>
                </select>
                <select
                    v-model="sourceFilter"
                    class="rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white text-sm px-3 py-2"
                >
                    <option value="">All Sources</option>
                    <option value="pos">Synced with POS</option>
                    <option value="local">eCommerce Local Only</option>
                </select>
            </div>

            <!-- Table -->
            <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                        <thead class="bg-gray-50 dark:bg-gray-900/50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase">Brand</th>
                                <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase">Slug</th>
                                <th class="px-6 py-3 text-center text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase">Products</th>
                                <th class="px-6 py-3 text-center text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase">POS Link</th>
                                <th class="px-6 py-3 text-center text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase">Status</th>
                                <th class="px-6 py-3 text-center text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase">Order</th>
                                <th class="px-6 py-3 text-right text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                            <tr
                                v-for="brand in brands?.data || []"
                                :key="brand.id"
                                class="hover:bg-gray-50 dark:hover:bg-gray-700/40 transition"
                            >
                                <td class="px-6 py-4">
                                    <div class="flex items-center gap-3">
                                        <div class="w-10 h-10 rounded-lg border border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-900 flex items-center justify-center overflow-hidden shrink-0">
                                            <img
                                                v-if="brand.logo_url"
                                                :src="brand.logo_url"
                                                :alt="brand.name"
                                                class="w-full h-full object-contain"
                                            />
                                            <span v-else class="text-xs font-bold text-gray-400 uppercase">
                                                {{ brand.name?.slice(0, 2) }}
                                            </span>
                                        </div>
                                        <div>
                                            <div class="font-semibold text-gray-900 dark:text-white flex items-center gap-1.5">
                                                <span>{{ brand.name }}</span>
                                                <span
                                                    v-if="brand.is_featured"
                                                    class="px-1.5 py-0.5 text-[10px] font-bold bg-amber-100 text-amber-800 dark:bg-amber-900/40 dark:text-amber-300 rounded"
                                                >
                                                    ★ Featured
                                                </span>
                                            </div>
                                            <p v-if="brand.description" class="text-xs text-gray-500 dark:text-gray-400 line-clamp-1 max-w-xs">
                                                {{ brand.description }}
                                            </p>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-6 py-4 text-xs font-mono text-gray-600 dark:text-gray-300">
                                    {{ brand.slug }}
                                </td>
                                <td class="px-6 py-4 text-center">
                                    <Link
                                        :href="route('admin.products.index', { brand_id: brand.id })"
                                        class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-semibold bg-gray-100 hover:bg-gray-200 dark:bg-gray-700 dark:hover:bg-gray-600 text-gray-800 dark:text-gray-200 transition"
                                    >
                                        {{ brand.products_count ?? 0 }}
                                    </Link>
                                </td>
                                <td class="px-6 py-4 text-center">
                                    <span
                                        v-if="brand.pos_brand_id"
                                        class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-xs font-medium bg-indigo-100 text-indigo-800 dark:bg-indigo-900/40 dark:text-indigo-300"
                                    >
                                        🏪 POS #{{ brand.pos_brand_id }}
                                    </span>
                                    <span v-else class="text-xs text-gray-400">Local</span>
                                </td>
                                <td class="px-6 py-4 text-center">
                                    <span
                                        :class="brand.is_active
                                            ? 'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/40 dark:text-emerald-300'
                                            : 'bg-gray-100 text-gray-600 dark:bg-gray-700 dark:text-gray-400'"
                                        class="px-2.5 py-0.5 rounded-full text-xs font-semibold"
                                    >
                                        {{ brand.is_active ? 'Active' : 'Inactive' }}
                                    </span>
                                </td>
                                <td class="px-6 py-4 text-center text-xs text-gray-600 dark:text-gray-300">
                                    {{ brand.sort_order ?? 0 }}
                                </td>
                                <td class="px-6 py-4 text-right space-x-2">
                                    <button
                                        @click="openEditModal(brand)"
                                        class="text-xs font-semibold text-indigo-600 hover:text-indigo-500 dark:text-indigo-400 cursor-pointer"
                                    >
                                        Edit
                                    </button>
                                    <button
                                        @click="deleteBrand(brand)"
                                        class="text-xs font-semibold text-red-600 hover:text-red-500 dark:text-red-400 cursor-pointer"
                                    >
                                        Delete
                                    </button>
                                </td>
                            </tr>
                            <tr v-if="!brands?.data?.length">
                                <td colspan="7" class="px-6 py-12 text-center text-sm text-gray-500 dark:text-gray-400">
                                    No brands found. Click "Add Brand" or import items from POS to get started.
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div v-if="brands?.links?.length > 3" class="px-6 py-4 border-t border-gray-200 dark:border-gray-700">
                    <Pagination :links="brands.links" />
                </div>
            </div>
        </div>

        <!-- Create / Edit Modal -->
        <div
            v-if="showModal"
            class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 backdrop-blur-xs p-4 overflow-y-auto"
        >
            <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-xl border border-gray-200 dark:border-gray-700 w-full max-w-lg overflow-hidden my-8">
                <div class="flex items-center justify-between px-6 py-4 border-b border-gray-200 dark:border-gray-700">
                    <h2 class="text-lg font-bold text-gray-900 dark:text-white">
                        {{ isEditing ? 'Edit Brand' : 'Create New Brand' }}
                    </h2>
                    <button @click="showModal = false" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-200">
                        ✕
                    </button>
                </div>

                <form @submit.prevent="submitForm" class="p-6 space-y-4 max-h-[80vh] overflow-y-auto">
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs font-semibold text-gray-700 dark:text-gray-300 mb-1">Brand Name *</label>
                            <input
                                v-model="form.name"
                                type="text"
                                required
                                placeholder="e.g. Samsung, Apple, Sony"
                                class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white text-sm px-3 py-2"
                            />
                            <p v-if="form.errors.name" class="text-xs text-red-500 mt-1">{{ form.errors.name }}</p>
                        </div>

                        <div>
                            <label class="block text-xs font-semibold text-gray-700 dark:text-gray-300 mb-1">URL Slug</label>
                            <input
                                v-model="form.slug"
                                @input="slugChangedManually = true"
                                type="text"
                                placeholder="auto-generated"
                                class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white text-sm px-3 py-2 font-mono"
                            />
                            <p v-if="form.errors.slug" class="text-xs text-red-500 mt-1">{{ form.errors.slug }}</p>
                        </div>
                    </div>

                    <!-- Logo Upload -->
                    <div>
                        <label class="block text-xs font-semibold text-gray-700 dark:text-gray-300 mb-1">Brand Logo</label>
                        <div class="flex items-center gap-4">
                            <div class="w-16 h-16 rounded-xl border border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-900 flex items-center justify-center overflow-hidden shrink-0">
                                <img v-if="logoPreview" :src="logoPreview" class="w-full h-full object-contain" />
                                <span v-else class="text-xs text-gray-400">No Logo</span>
                            </div>
                            <div class="flex-1 space-y-1.5">
                                <input
                                    type="file"
                                    accept="image/*"
                                    @change="handleLogoChange"
                                    class="block w-full text-xs text-gray-500 file:mr-3 file:py-1.5 file:px-3 file:rounded-lg file:border-0 file:text-xs file:font-semibold file:bg-indigo-50 file:text-indigo-700 hover:file:bg-indigo-100"
                                />
                                <button
                                    v-if="logoPreview"
                                    type="button"
                                    @click="clearLogo"
                                    class="text-xs text-red-600 hover:underline"
                                >
                                    Remove Logo
                                </button>
                            </div>
                        </div>
                        <p v-if="form.errors.logo" class="text-xs text-red-500 mt-1">{{ form.errors.logo }}</p>
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-gray-700 dark:text-gray-300 mb-1">Description</label>
                        <textarea
                            v-model="form.description"
                            rows="2"
                            placeholder="Optional short bio or brand story..."
                            class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white text-sm px-3 py-2"
                        ></textarea>
                    </div>

                    <div class="grid grid-cols-3 gap-4 items-center pt-1">
                        <div>
                            <label class="block text-xs font-semibold text-gray-700 dark:text-gray-300 mb-1">Sort Order</label>
                            <input
                                v-model.number="form.sort_order"
                                type="number"
                                min="0"
                                class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white text-sm px-3 py-2"
                            />
                        </div>
                        <label class="flex items-center gap-2 text-sm text-gray-700 dark:text-gray-300 pt-5 cursor-pointer">
                            <input v-model="form.is_active" type="checkbox" class="rounded border-gray-300 text-primary focus:ring-primary" />
                            <span>Active</span>
                        </label>
                        <label class="flex items-center gap-2 text-sm text-gray-700 dark:text-gray-300 pt-5 cursor-pointer">
                            <input v-model="form.is_featured" type="checkbox" class="rounded border-gray-300 text-primary focus:ring-primary" />
                            <span>Featured</span>
                        </label>
                    </div>

                    <div class="border-t border-gray-200 dark:border-gray-700 pt-3 space-y-3">
                        <div class="text-xs font-semibold text-gray-500 uppercase">SEO Metadata (Optional)</div>
                        <div>
                            <label class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">Meta Title</label>
                            <input
                                v-model="form.meta_title"
                                type="text"
                                class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white text-sm px-3 py-1.5"
                            />
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">Meta Description</label>
                            <textarea
                                v-model="form.meta_description"
                                rows="2"
                                class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white text-sm px-3 py-1.5"
                            ></textarea>
                        </div>
                    </div>

                    <div class="flex justify-end gap-3 pt-4 border-t border-gray-200 dark:border-gray-700">
                        <button
                            type="button"
                            @click="showModal = false"
                            class="px-4 py-2 text-sm font-medium text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700 rounded-lg"
                        >
                            Cancel
                        </button>
                        <button
                            type="submit"
                            :disabled="form.processing"
                            class="px-5 py-2 text-sm font-semibold text-white bg-primary hover:bg-primary-dark rounded-lg shadow-sm disabled:opacity-50 cursor-pointer"
                        >
                            {{ form.processing ? 'Saving...' : isEditing ? 'Update Brand' : 'Create Brand' }}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </AdminLayout>
</template>

