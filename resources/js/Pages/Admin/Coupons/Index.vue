<script setup>
import { Head, Link, useForm, router } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import Pagination from '@/Components/Pagination.vue';
import { ref, watch } from 'vue';

const props = defineProps({
    coupons: Object,
    filters: Object,
    stats:   Object,
});

const showForm   = ref(false);
const isEditing  = ref(false);
const editId     = ref(null);
const search     = ref(props.filters.search || '');
const status     = ref(props.filters.status || '');

const form = useForm({
    _method:          'POST',
    code:             '',
    type:             'percent',
    value:            '',
    min_order_amount: '0',
    max_uses:         '',
    starts_at:          '',
    expires_at:         '',
    is_active:          true,
    exclude_sale_items: false,
});

const openCreateForm = () => {
    isEditing.value = false;
    editId.value    = null;
    form.reset();
    form._method   = 'POST';
    form.type               = 'percent';
    form.is_active          = true;
    form.exclude_sale_items = false;
    showForm.value          = true;
};

const openEditForm = (coupon) => {
    isEditing.value = true;
    editId.value    = coupon.id;
    form.clearErrors();
    form._method            = 'PUT';
    form.code               = coupon.code;
    form.type               = coupon.type;
    form.value              = coupon.value;
    form.min_order_amount   = coupon.min_order_amount;
    form.max_uses           = coupon.max_uses || '';
    form.starts_at          = coupon.starts_at ? coupon.starts_at.substring(0, 10) : '';
    form.expires_at         = coupon.expires_at ? coupon.expires_at.substring(0, 10) : '';
    form.is_active          = !!coupon.is_active;
    form.exclude_sale_items = !!coupon.exclude_sale_items;
    showForm.value          = true;
};

const save = () => {
    if (isEditing.value) {
        form.post(route('admin.coupons.update', editId.value), {
            preserveScroll: true,
            onSuccess: () => {
                showForm.value = false;
                form.reset();
            },
        });
    } else {
        form.post(route('admin.coupons.store'), {
            preserveScroll: true,
            onSuccess: () => {
                showForm.value = false;
                form.reset();
            },
        });
    }
};

const toggleStatus = (coupon) => {
    router.patch(route('admin.coupons.toggle-status', coupon.id), {}, { preserveScroll: true });
};

const deleteCoupon = (coupon) => {
    if (confirm(`Are you sure you want to delete coupon '${coupon.code}'?`)) {
        router.delete(route('admin.coupons.destroy', coupon.id), { preserveScroll: true });
    }
};

const applyFilter = () => {
    router.get(route('admin.coupons.index'), {
        search: search.value,
        status: status.value,
    }, { preserveState: true, replace: true });
};

watch([search, status], () => {
    applyFilter();
});
</script>

<template>
    <Head title="Coupon Management" />
    <AdminLayout>
        <div class="space-y-6">
            <!-- Header & Action -->
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                <div>
                    <h1 class="text-2xl font-black text-gray-900 dark:text-white tracking-tight">Coupons & Discounts</h1>
                    <p class="text-sm text-gray-500 dark:text-gray-400">Create promotional discount codes for your customers.</p>
                </div>
                <button
                    @click="openCreateForm"
                    class="inline-flex items-center gap-2 bg-primary hover:opacity-90 text-white px-4 py-2.5 rounded-lg text-sm font-bold shadow-sm transition"
                >
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                    Create Coupon
                </button>
            </div>

            <!-- Stats Overview Cards -->
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                <div class="bg-white dark:bg-gray-800 p-5 rounded-xl border border-gray-200 dark:border-gray-700 shadow-sm flex items-center gap-4">
                    <div class="w-12 h-12 rounded-lg bg-primary/10 text-primary flex items-center justify-center font-bold text-xl">🎟️</div>
                    <div>
                        <p class="text-xs uppercase font-bold text-gray-500 tracking-wider">Total Coupons</p>
                        <p class="text-2xl font-black text-gray-900 dark:text-white">{{ stats.total }}</p>
                    </div>
                </div>
                <div class="bg-white dark:bg-gray-800 p-5 rounded-xl border border-gray-200 dark:border-gray-700 shadow-sm flex items-center gap-4">
                    <div class="w-12 h-12 rounded-lg bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400 flex items-center justify-center font-bold text-xl">✓</div>
                    <div>
                        <p class="text-xs uppercase font-bold text-gray-500 tracking-wider">Active Codes</p>
                        <p class="text-2xl font-black text-green-600 dark:text-green-400">{{ stats.active }}</p>
                    </div>
                </div>
                <div class="bg-white dark:bg-gray-800 p-5 rounded-xl border border-gray-200 dark:border-gray-700 shadow-sm flex items-center gap-4">
                    <div class="w-12 h-12 rounded-lg bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-400 flex items-center justify-center font-bold text-xl">🛒</div>
                    <div>
                        <p class="text-xs uppercase font-bold text-gray-500 tracking-wider">Times Redeemed</p>
                        <p class="text-2xl font-black text-blue-600 dark:text-blue-400">{{ stats.total_used }}</p>
                    </div>
                </div>
            </div>

            <!-- Form Card (slide down when open) -->
            <div v-if="showForm" class="bg-white dark:bg-gray-800 p-6 rounded-xl border-2 border-primary shadow-lg transition">
                <div class="flex items-center justify-between pb-4 border-b dark:border-gray-700 mb-5">
                    <h2 class="text-lg font-bold text-gray-900 dark:text-white">
                        {{ isEditing ? 'Edit Coupon' : 'Create New Coupon' }}
                    </h2>
                    <button @click="showForm = false" class="text-gray-400 hover:text-gray-600 text-sm font-bold">✕ Close</button>
                </div>

                <form @submit.prevent="save" class="space-y-4">
                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                        <div>
                            <label class="block text-xs font-bold text-gray-700 dark:text-gray-300 uppercase mb-1">Coupon Code *</label>
                            <input
                                type="text"
                                v-model="form.code"
                                required
                                placeholder="e.g. SUMMER25"
                                class="w-full uppercase font-mono rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 text-sm px-3 py-2"
                            />
                            <p v-if="form.errors.code" class="text-red-500 text-xs mt-1">{{ form.errors.code }}</p>
                        </div>

                        <div>
                            <label class="block text-xs font-bold text-gray-700 dark:text-gray-300 uppercase mb-1">Discount Type *</label>
                            <select v-model="form.type" class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 text-sm px-3 py-2">
                                <option value="percent">Percentage (%)</option>
                                <option value="fixed">Fixed Amount (₦)</option>
                            </select>
                            <p v-if="form.errors.type" class="text-red-500 text-xs mt-1">{{ form.errors.type }}</p>
                        </div>

                        <div>
                            <label class="block text-xs font-bold text-gray-700 dark:text-gray-300 uppercase mb-1">
                                Discount Value * ({{ form.type === 'percent' ? '%' : '₦' }})
                            </label>
                            <input
                                type="number"
                                step="0.01"
                                v-model="form.value"
                                required
                                placeholder="e.g. 15 or 2000"
                                class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 text-sm px-3 py-2"
                            />
                            <p v-if="form.errors.value" class="text-red-500 text-xs mt-1">{{ form.errors.value }}</p>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                        <div>
                            <label class="block text-xs font-bold text-gray-700 dark:text-gray-300 uppercase mb-1">Min. Order Subtotal (₦)</label>
                            <input
                                type="number"
                                step="0.01"
                                v-model="form.min_order_amount"
                                placeholder="0 = no minimum"
                                class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 text-sm px-3 py-2"
                            />
                            <p v-if="form.errors.min_order_amount" class="text-red-500 text-xs mt-1">{{ form.errors.min_order_amount }}</p>
                        </div>

                        <div>
                            <label class="block text-xs font-bold text-gray-700 dark:text-gray-300 uppercase mb-1">Max Total Uses</label>
                            <input
                                type="number"
                                v-model="form.max_uses"
                                placeholder="Leave empty for unlimited"
                                class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 text-sm px-3 py-2"
                            />
                            <p v-if="form.errors.max_uses" class="text-red-500 text-xs mt-1">{{ form.errors.max_uses }}</p>
                        </div>

                        <div>
                            <label class="block text-xs font-bold text-gray-700 dark:text-gray-300 uppercase mb-1">Expiry Date</label>
                            <input
                                type="date"
                                v-model="form.expires_at"
                                class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 text-sm px-3 py-2"
                            />
                            <p v-if="form.errors.expires_at" class="text-red-500 text-xs mt-1">{{ form.errors.expires_at }}</p>
                        </div>
                    </div>

                    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 pt-4 border-t dark:border-gray-700">
                        <div class="flex flex-col sm:flex-row sm:items-center gap-4">
                            <label class="flex items-center gap-2 cursor-pointer">
                                <input type="checkbox" v-model="form.is_active" class="w-4 h-4 text-primary rounded" />
                                <span class="text-sm font-semibold text-gray-700 dark:text-gray-300">Active and ready for use</span>
                            </label>
                            <label class="flex items-center gap-2 cursor-pointer">
                                <input type="checkbox" v-model="form.exclude_sale_items" class="w-4 h-4 text-primary rounded" />
                                <span class="text-xs font-semibold text-gray-600 dark:text-gray-400">Exclude Sale & Flash Sale items</span>
                            </label>
                        </div>

                        <div class="flex items-center gap-2">
                            <button
                                type="button"
                                @click="showForm = false"
                                class="px-4 py-2 border rounded-lg text-sm font-semibold hover:bg-gray-100 dark:hover:bg-gray-700 transition"
                            >
                                Cancel
                            </button>
                            <button
                                type="submit"
                                :disabled="form.processing"
                                class="px-6 py-2 bg-primary hover:opacity-90 text-white rounded-lg text-sm font-bold shadow-sm transition disabled:opacity-50"
                            >
                                {{ form.processing ? 'Saving...' : (isEditing ? 'Update Coupon' : 'Create Coupon') }}
                            </button>
                        </div>
                    </div>
                </form>
            </div>

            <!-- Filters Bar -->
            <div class="bg-white dark:bg-gray-800 p-4 rounded-xl border border-gray-200 dark:border-gray-700 flex flex-col sm:flex-row gap-3">
                <div class="flex-1 relative">
                    <input
                        type="text"
                        v-model="search"
                        placeholder="Search coupon code..."
                        class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 text-sm pl-9 pr-3 py-2"
                    />
                    <svg class="w-4 h-4 text-gray-400 absolute left-3 top-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                </div>
                <select v-model="status" class="rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 text-sm px-3 py-2">
                    <option value="">All Statuses</option>
                    <option value="active">Active Only</option>
                    <option value="inactive">Inactive Only</option>
                </select>
            </div>

            <!-- Coupons Table -->
            <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 shadow-sm overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-sm text-gray-700 dark:text-gray-300">
                        <thead class="bg-gray-50 dark:bg-gray-900/60 text-xs font-bold uppercase text-gray-500 border-b dark:border-gray-700">
                            <tr>
                                <th class="px-5 py-3.5">Code</th>
                                <th class="px-5 py-3.5">Discount</th>
                                <th class="px-5 py-3.5">Min. Order</th>
                                <th class="px-5 py-3.5">Usage</th>
                                <th class="px-5 py-3.5">Expires</th>
                                <th class="px-5 py-3.5 text-center">Status</th>
                                <th class="px-5 py-3.5 text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y dark:divide-gray-700">
                            <tr v-if="!coupons.data || coupons.data.length === 0">
                                <td colspan="7" class="px-5 py-8 text-center text-gray-500">
                                    No coupons found. Click "Create Coupon" to add your first promotion.
                                </td>
                            </tr>
                            <tr v-for="coupon in coupons.data" :key="coupon.id" class="hover:bg-gray-50/50 dark:hover:bg-gray-700/30 transition">
                                <td class="px-5 py-4 font-mono font-black text-primary text-base">
                                    {{ coupon.code }}
                                </td>
                                <td class="px-5 py-4 font-bold text-gray-900 dark:text-white">
                                    <span v-if="coupon.type === 'percent'" class="text-amber-600 font-extrabold">{{ parseFloat(coupon.value) }}% OFF</span>
                                    <span v-else class="text-green-600 font-extrabold">₦{{ parseFloat(coupon.value).toLocaleString() }} OFF</span>
                                </td>
                                <td class="px-5 py-4 text-gray-600 dark:text-gray-400">
                                    {{ parseFloat(coupon.min_order_amount) > 0 ? '₦' + parseFloat(coupon.min_order_amount).toLocaleString() : 'None' }}
                                </td>
                                <td class="px-5 py-4">
                                    <div class="flex items-center gap-1.5">
                                        <span class="font-bold text-gray-900 dark:text-white">{{ coupon.used_count }}</span>
                                        <span class="text-gray-400 text-xs">/ {{ coupon.max_uses ? coupon.max_uses : '∞' }}</span>
                                    </div>
                                </td>
                                <td class="px-5 py-4 text-xs text-gray-500">
                                    <span v-if="coupon.expires_at">{{ new Date(coupon.expires_at).toLocaleDateString() }}</span>
                                    <span v-else class="text-gray-400">No expiration</span>
                                </td>
                                <td class="px-5 py-4 text-center">
                                    <button
                                        @click="toggleStatus(coupon)"
                                        :class="coupon.is_active ? 'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400' : 'bg-gray-200 text-gray-600 dark:bg-gray-700 dark:text-gray-400'"
                                        class="px-2.5 py-1 rounded-full text-xs font-bold transition hover:opacity-80"
                                    >
                                        {{ coupon.is_active ? 'Active' : 'Disabled' }}
                                    </button>
                                </td>
                                <td class="px-5 py-4 text-right">
                                    <div class="flex items-center justify-end gap-2">
                                        <button
                                            @click="openEditForm(coupon)"
                                            class="p-1.5 text-blue-600 hover:text-blue-800 dark:hover:text-blue-400 hover:bg-blue-50 dark:hover:bg-blue-900/30 rounded transition"
                                            title="Edit Coupon"
                                        >
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"/></svg>
                                        </button>
                                        <button
                                            @click="deleteCoupon(coupon)"
                                            class="p-1.5 text-red-500 hover:text-red-700 hover:bg-red-50 dark:hover:bg-red-900/30 rounded transition"
                                            title="Delete Coupon"
                                        >
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div v-if="coupons.links && coupons.links.length > 3" class="p-4 border-t dark:border-gray-700">
                    <Pagination :links="coupons.links" />
                </div>
            </div>
        </div>
    </AdminLayout>
</template>

