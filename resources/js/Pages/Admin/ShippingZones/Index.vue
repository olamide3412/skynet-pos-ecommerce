<script setup>
import { Head, Link, useForm, router } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import SettingsNavTabs from '@/Components/Admin/SettingsNavTabs.vue';
import Pagination from '@/Components/Pagination.vue';
import { ref, watch } from 'vue';

const props = defineProps({
    zones:   Object,
    filters: Object,
    stats:   Object,
});

const showForm   = ref(false);
const isEditing  = ref(false);
const editId     = ref(null);
const search     = ref(props.filters.search || '');
const status     = ref(props.filters.status || '');

const nigerianStates = [
    'Abia', 'Adamawa', 'Akwa Ibom', 'Anambra', 'Bauchi', 'Bayelsa', 'Benue', 'Borno',
    'Cross River', 'Delta', 'Ebonyi', 'Edo', 'Ekiti', 'Enugu', 'FCT - Abuja', 'Gombe',
    'Imo', 'Jigawa', 'Kaduna', 'Kano', 'Katsina', 'Kebbi', 'Kogi', 'Kwara', 'Lagos',
    'Nasarawa', 'Niger', 'Ogun', 'Ondo', 'Osun', 'Oyo', 'Plateau', 'Rivers', 'Sokoto',
    'Taraba', 'Yobe', 'Zamfara'
];

const form = useForm({
    _method:        'POST',
    state:          '',
    city:           '',
    fee:            '',
    estimated_days: '2-3 Business Days',
    is_active:      true,
    is_default:     false,
});

const openCreateForm = () => {
    isEditing.value = false;
    editId.value    = null;
    form.reset();
    form._method        = 'POST';
    form.estimated_days = '2-3 Business Days';
    form.is_active      = true;
    form.is_default     = false;
    showForm.value      = true;
};

const openEditForm = (zone) => {
    isEditing.value = true;
    editId.value    = zone.id;
    form.clearErrors();
    form._method        = 'PUT';
    form.state          = zone.state;
    form.city           = zone.city || '';
    form.fee            = zone.fee;
    form.estimated_days = zone.estimated_days || '2-3 Business Days';
    form.is_active      = !!zone.is_active;
    form.is_default     = !!zone.is_default;
    showForm.value      = true;
};

const save = () => {
    if (isEditing.value) {
        form.post(route('admin.shipping-zones.update', editId.value), {
            preserveScroll: true,
            onSuccess: () => {
                showForm.value = false;
                form.reset();
            },
        });
    } else {
        form.post(route('admin.shipping-zones.store'), {
            preserveScroll: true,
            onSuccess: () => {
                showForm.value = false;
                form.reset();
            },
        });
    }
};

const toggleStatus = (zone) => {
    router.patch(route('admin.shipping-zones.toggle-status', zone.id), {}, { preserveScroll: true });
};

const setDefault = (zone) => {
    router.patch(route('admin.shipping-zones.set-default', zone.id), {}, { preserveScroll: true });
};

const deleteZone = (zone) => {
    if (confirm(`Are you sure you want to delete shipping rates for "${zone.state}"?`)) {
        router.delete(route('admin.shipping-zones.destroy', zone.id), { preserveScroll: true });
    }
};

// Filter triggers with debounce
let timer = null;
watch([search, status], () => {
    clearTimeout(timer);
    timer = setTimeout(() => {
        router.get(
            route('admin.shipping-zones.index'),
            { search: search.value, status: status.value },
            { preserveState: true, replace: true }
        );
    }, 300);
});

const formatCurrency = (val) => {
    const num = Number(val) || 0;
    return '₦' + num.toLocaleString('en-NG', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
};
</script>

<template>
    <Head title="Shipping Zones & Rates" />
    <AdminLayout>
        <div class="space-y-6">
            <!-- Header Tabs & Action -->
            <SettingsNavTabs />

            <!-- Page Title Bar -->
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                <div>
                    <h1 class="text-2xl font-bold text-gray-900 dark:text-white flex items-center gap-2.5">
                        <span class="p-2 rounded-xl bg-primary/10 text-primary">🚚</span>
                        Shipping Zones & Delivery Rates
                    </h1>
                    <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">
                        Define automated destination-based delivery fees and timelines per Nigerian state.
                    </p>
                </div>
                <button
                    @click="openCreateForm"
                    class="inline-flex items-center gap-2 px-5 py-2.5 rounded-xl bg-primary hover:bg-primary-dark text-white font-semibold text-sm shadow-sm transition"
                >
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                    Add Shipping Zone
                </button>
            </div>

            <!-- Stats Overview Cards -->
            <div class="grid grid-cols-1 sm:grid-cols-4 gap-4">
                <div class="p-5 rounded-2xl bg-white dark:bg-gray-800 border border-gray-100 dark:border-gray-700 shadow-xs flex items-center gap-4">
                    <div class="w-12 h-12 rounded-xl bg-blue-500/10 text-blue-500 flex items-center justify-center text-2xl font-bold">🗺️</div>
                    <div>
                        <div class="text-2xl font-black text-gray-900 dark:text-white">{{ stats.total }}</div>
                        <div class="text-xs text-gray-500 dark:text-gray-400 font-medium">Configured Zones</div>
                    </div>
                </div>
                <div class="p-5 rounded-2xl bg-white dark:bg-gray-800 border border-gray-100 dark:border-gray-700 shadow-xs flex items-center gap-4">
                    <div class="w-12 h-12 rounded-xl bg-emerald-500/10 text-emerald-500 flex items-center justify-center text-2xl font-bold">✅</div>
                    <div>
                        <div class="text-2xl font-black text-emerald-600 dark:text-emerald-400">{{ stats.active }}</div>
                        <div class="text-xs text-gray-500 dark:text-gray-400 font-medium">Active at Checkout</div>
                    </div>
                </div>
                <div class="p-5 rounded-2xl bg-white dark:bg-gray-800 border border-gray-100 dark:border-gray-700 shadow-xs flex items-center gap-4">
                    <div class="w-12 h-12 rounded-xl bg-amber-500/10 text-amber-500 flex items-center justify-center text-2xl font-bold">⭐</div>
                    <div>
                        <div class="text-sm font-black text-amber-600 dark:text-amber-400 truncate max-w-[140px]" :title="stats.default ? `${stats.default.state}${stats.default.city ? ' - ' + stats.default.city : ''}` : 'None Set'">
                            {{ stats.default ? `${stats.default.state}${stats.default.city ? ' - ' + stats.default.city : ''}` : 'None Set' }}
                        </div>
                        <div class="text-xs text-gray-500 dark:text-gray-400 font-medium">Checkout Default</div>
                    </div>
                </div>
                <div class="p-5 rounded-2xl bg-white dark:bg-gray-800 border border-gray-100 dark:border-gray-700 shadow-xs flex items-center gap-4">
                    <div class="w-12 h-12 rounded-xl bg-gray-500/10 text-gray-500 flex items-center justify-center text-2xl font-bold">⏸️</div>
                    <div>
                        <div class="text-2xl font-black text-gray-600 dark:text-gray-400">{{ stats.total - stats.active }}</div>
                        <div class="text-xs text-gray-500 dark:text-gray-400 font-medium">Disabled / Inactive</div>
                    </div>
                </div>
            </div>

            <!-- Filter & Search Toolbar -->
            <div class="p-4 rounded-2xl bg-white dark:bg-gray-800 border border-gray-100 dark:border-gray-700 shadow-xs flex flex-col md:flex-row items-center gap-4">
                <div class="relative flex-1 w-full">
                    <input
                        v-model="search"
                        type="text"
                        placeholder="Search destination state or city..."
                        class="w-full pl-10 pr-4 py-2.5 rounded-xl border border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-900/50 text-gray-900 dark:text-white text-sm focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary transition"
                    />
                    <svg class="w-4 h-4 text-gray-400 absolute left-3.5 top-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                </div>
                <div class="w-full md:w-56 shrink-0">
                    <select
                        v-model="status"
                        class="w-full py-2.5 px-3 rounded-xl border border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-900/50 text-gray-900 dark:text-white text-sm focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary transition"
                    >
                        <option value="">All Statuses</option>
                        <option value="active">Active Only</option>
                        <option value="inactive">Inactive Only</option>
                    </select>
                </div>
            </div>

            <!-- Shipping Zones Table -->
            <div class="bg-white dark:bg-gray-800 rounded-2xl border border-gray-100 dark:border-gray-700 shadow-xs overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="w-full text-left border-collapse text-sm">
                        <thead>
                            <tr class="border-b border-gray-100 dark:border-gray-700/60 bg-gray-50/70 dark:bg-gray-900/30 text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">
                                <th class="py-4 px-6">Destination State</th>
                                <th class="py-4 px-6">Coverage / City</th>
                                <th class="py-4 px-6">Delivery Fee</th>
                                <th class="py-4 px-6">Est. Timeline</th>
                                <th class="py-4 px-6">Status</th>
                                <th class="py-4 px-6 text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-gray-700/60 text-gray-700 dark:text-gray-300">
                            <tr v-for="zone in zones.data" :key="zone.id" class="hover:bg-gray-50/50 dark:hover:bg-gray-900/20 transition">
                                <td class="py-4 px-6 font-bold text-gray-900 dark:text-white">
                                    <div class="flex items-center gap-2">
                                        <span class="text-base">📍</span>
                                        <span>{{ zone.state }}</span>
                                        <span v-if="zone.is_default" class="text-[10px] px-2 py-0.5 rounded-full bg-amber-100 text-amber-800 dark:bg-amber-900/40 dark:text-amber-300 font-bold border border-amber-300 shadow-xs">
                                            ⭐ Default
                                        </span>
                                    </div>
                                </td>
                                <td class="py-4 px-6">
                                    <span v-if="zone.city" class="text-xs px-2.5 py-1 rounded-md bg-gray-100 dark:bg-gray-700 font-medium text-gray-800 dark:text-gray-200">
                                        {{ zone.city }}
                                    </span>
                                    <span v-else class="text-xs text-gray-400 italic">
                                        All Cities (Statewide)
                                    </span>
                                </td>
                                <td class="py-4 px-6 font-bold text-gray-900 dark:text-white">
                                    <span v-if="parseFloat(zone.fee) === 0" class="text-emerald-600 dark:text-emerald-400 font-black">
                                        FREE (₦0.00)
                                    </span>
                                    <span v-else>
                                        {{ formatCurrency(zone.fee) }}
                                    </span>
                                </td>
                                <td class="py-4 px-6 text-xs text-gray-600 dark:text-gray-400">
                                    <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-md bg-blue-50 dark:bg-blue-900/30 text-blue-700 dark:text-blue-300 font-medium">
                                        ⏱️ {{ zone.estimated_days || '2-3 Business Days' }}
                                    </span>
                                </td>
                                <td class="py-4 px-6">
                                    <button
                                        @click="toggleStatus(zone)"
                                        :class="zone.is_active ? 'bg-emerald-500' : 'bg-gray-300 dark:bg-gray-600'"
                                        class="relative inline-flex h-6 w-11 flex-shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-200 ease-in-out focus:outline-none"
                                        title="Click to toggle active status"
                                    >
                                        <span
                                            :class="zone.is_active ? 'translate-x-5' : 'translate-x-0'"
                                            class="pointer-events-none inline-block h-5 w-5 transform rounded-full bg-white shadow ring-0 transition duration-200 ease-in-out"
                                        />
                                    </button>
                                </td>
                                <td class="py-4 px-6 text-right">
                                    <div class="flex items-center justify-end gap-2">
                                        <button
                                            v-if="!zone.is_default"
                                            @click="setDefault(zone)"
                                            class="px-2.5 py-1 rounded-lg text-amber-600 dark:text-amber-400 hover:bg-amber-50 dark:hover:bg-amber-900/30 transition text-xs font-bold border border-amber-300 dark:border-amber-700/60 flex items-center gap-1"
                                            title="Make this the default shipping zone for checkout"
                                        >
                                            ⭐ Set Default
                                        </button>
                                        <button
                                            @click="openEditForm(zone)"
                                            class="p-2 rounded-lg text-gray-600 dark:text-gray-400 hover:text-primary hover:bg-gray-100 dark:hover:bg-gray-700 transition"
                                            title="Edit Rate"
                                        >
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"/></svg>
                                        </button>
                                        <button
                                            @click="deleteZone(zone)"
                                            class="p-2 rounded-lg text-red-500 hover:bg-red-50 dark:hover:bg-red-900/30 transition"
                                            title="Delete Zone"
                                        >
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                            <tr v-if="!zones.data || zones.data.length === 0">
                                <td colspan="6" class="py-12 text-center text-gray-400">
                                    <div class="text-4xl mb-3">📦</div>
                                    <p class="font-medium">No shipping zones found</p>
                                    <p class="text-xs text-gray-500 mt-1">Click "Add Shipping Zone" above to configure your first destination rate.</p>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Pagination -->
                <div v-if="zones.links && zones.links.length > 3" class="p-4 border-t border-gray-100 dark:border-gray-700/60">
                    <Pagination :links="zones.links" />
                </div>
            </div>
        </div>

        <!-- Create / Edit Shipping Zone Modal -->
        <div v-if="showForm" class="fixed inset-0 z-50 overflow-y-auto flex items-center justify-center p-4">
            <div class="fixed inset-0 bg-black/60 backdrop-blur-xs transition-opacity" @click="showForm = false"></div>

            <div class="relative bg-white dark:bg-gray-800 rounded-3xl max-w-lg w-full p-6 sm:p-8 shadow-2xl border border-gray-100 dark:border-gray-700 z-10">
                <div class="flex items-center justify-between pb-4 border-b border-gray-100 dark:border-gray-700 mb-6">
                    <div>
                        <h3 class="text-xl font-bold text-gray-900 dark:text-white">
                            {{ isEditing ? 'Edit Shipping Rate' : 'Add New Shipping Rate' }}
                        </h3>
                        <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">
                            Set up fixed delivery rate and fulfillment timing for this state.
                        </p>
                    </div>
                    <button @click="showForm = false" class="p-2 text-gray-400 hover:text-gray-600 dark:hover:text-gray-200">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                    </button>
                </div>

                <form @submit.prevent="save" class="space-y-4">
                    <!-- State Selection / Input -->
                    <div>
                        <label class="block text-xs font-semibold uppercase text-gray-600 dark:text-gray-300 mb-1.5">Destination State *</label>
                        <input
                            v-model="form.state"
                            type="text"
                            list="states-list"
                            required
                            placeholder="e.g. Lagos, Delta, Abuja FCT"
                            class="w-full px-4 py-2.5 rounded-xl border border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-900 text-gray-900 dark:text-white text-sm focus:ring-2 focus:ring-primary focus:border-primary"
                        />
                        <datalist id="states-list">
                            <option v-for="stateName in nigerianStates" :key="stateName" :value="stateName" />
                        </datalist>
                        <p v-if="form.errors.state" class="text-xs text-red-500 mt-1">{{ form.errors.state }}</p>
                    </div>

                    <!-- City / Coverage Area -->
                    <div>
                        <label class="block text-xs font-semibold uppercase text-gray-600 dark:text-gray-300 mb-1.5">Town / LGA / Specific Area (Optional)</label>
                        <input
                            v-model="form.city"
                            type="text"
                            placeholder="e.g. Abraka, Ethiope East, Warri, Benin City, Ikeja (or blank for Statewide)"
                            class="w-full px-4 py-2.5 rounded-xl border border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-900 text-gray-900 dark:text-white text-sm focus:ring-2 focus:ring-primary focus:border-primary"
                        />
                        <p class="text-[11px] text-gray-400 mt-1">Allows you to create distinct delivery fees for different towns or LGAs in the same state.</p>
                        <p v-if="form.errors.city" class="text-xs text-red-500 mt-1">{{ form.errors.city }}</p>
                    </div>

                    <!-- Fee (NGN) & Estimated Days -->
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs font-semibold uppercase text-gray-600 dark:text-gray-300 mb-1.5">Delivery Fee (₦) *</label>
                            <div class="relative">
                                <span class="absolute left-3.5 top-2.5 text-gray-400 font-bold">₦</span>
                                <input
                                    v-model="form.fee"
                                    type="number"
                                    min="0"
                                    step="0.01"
                                    required
                                    placeholder="2500"
                                    class="w-full pl-9 pr-4 py-2.5 rounded-xl border border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-900 text-gray-900 dark:text-white text-sm focus:ring-2 focus:ring-primary focus:border-primary"
                                />
                            </div>
                            <p class="text-[11px] text-emerald-600 dark:text-emerald-400 mt-1">Enter 0 for Free Delivery in this zone.</p>
                            <p v-if="form.errors.fee" class="text-xs text-red-500 mt-1">{{ form.errors.fee }}</p>
                        </div>

                        <div>
                            <label class="block text-xs font-semibold uppercase text-gray-600 dark:text-gray-300 mb-1.5">Est. Delivery Days *</label>
                            <input
                                v-model="form.estimated_days"
                                type="text"
                                required
                                placeholder="e.g. Same Day, 1-2 Days, 24-48 Hours"
                                class="w-full px-4 py-2.5 rounded-xl border border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-900 text-gray-900 dark:text-white text-sm focus:ring-2 focus:ring-primary focus:border-primary"
                            />
                            <p v-if="form.errors.estimated_days" class="text-xs text-red-500 mt-1">{{ form.errors.estimated_days }}</p>
                        </div>
                    </div>

                    <!-- Set as Default Zone Checkbox -->
                    <div class="p-3.5 rounded-xl border border-amber-200 dark:border-amber-800/60 bg-amber-50/50 dark:bg-amber-950/20 flex items-center justify-between">
                        <div>
                            <p class="text-xs font-bold text-amber-900 dark:text-amber-200 flex items-center gap-1.5">
                                ⭐ Set as Default Shipping Zone
                            </p>
                            <p class="text-[11px] text-amber-700/80 dark:text-amber-300/70 mt-0.5">
                                Automatically pre-selected for home delivery when customers open checkout.
                            </p>
                        </div>
                        <input type="checkbox" v-model="form.is_default" class="w-4 h-4 text-amber-600 rounded focus:ring-amber-500" />
                    </div>

                    <!-- Active Toggle -->
                    <div class="flex items-center gap-3 pt-2">
                        <label class="relative inline-flex items-center cursor-pointer">
                            <input type="checkbox" v-model="form.is_active" class="sr-only peer">
                            <div class="w-11 h-6 bg-gray-200 peer-focus:outline-none rounded-full peer dark:bg-gray-700 peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all dark:border-gray-600 peer-checked:bg-primary"></div>
                        </label>
                        <span class="text-sm font-medium text-gray-700 dark:text-gray-300">Active (Applicable on Checkout)</span>
                    </div>

                    <!-- Modal Actions -->
                    <div class="flex items-center justify-end gap-3 pt-6 border-t border-gray-100 dark:border-gray-700">
                        <button
                            type="button"
                            @click="showForm = false"
                            class="px-5 py-2.5 rounded-xl border border-gray-200 dark:border-gray-700 text-gray-700 dark:text-gray-300 font-semibold text-sm hover:bg-gray-50 dark:hover:bg-gray-900 transition"
                        >
                            Cancel
                        </button>
                        <button
                            type="submit"
                            :disabled="form.processing"
                            class="px-6 py-2.5 rounded-xl bg-primary hover:bg-primary-dark text-white font-semibold text-sm shadow-sm transition disabled:opacity-50"
                        >
                            {{ form.processing ? 'Saving...' : (isEditing ? 'Save Changes' : 'Create Zone') }}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </AdminLayout>
</template>

