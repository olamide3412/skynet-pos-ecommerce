<script setup>
import { ref, watch } from 'vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import Pagination from '@/Components/Pagination.vue';

const props = defineProps({
    customers: Object,
    filters: Object,
    stats: Object,
});

const search = ref(props.filters.search || '');
const provider = ref(props.filters.provider || 'all');
const sort = ref(props.filters.sort || 'latest');

let searchTimeout = null;

const applyFilters = () => {
    router.get(
        route('admin.customers.index'),
        {
            search: search.value || undefined,
            provider: provider.value !== 'all' ? provider.value : undefined,
            sort: sort.value !== 'latest' ? sort.value : undefined,
        },
        {
            preserveState: true,
            preserveScroll: true,
            replace: true,
        }
    );
};

watch(search, () => {
    clearTimeout(searchTimeout);
    searchTimeout = setTimeout(applyFilters, 350);
});

watch([provider, sort], () => {
    applyFilters();
});

const clearFilters = () => {
    search.value = '';
    provider.value = 'all';
    sort.value = 'latest';
    applyFilters();
};

// ─── Edit Customer Modal ───────────────────────────────────────
const isEditModalOpen = ref(false);
const editingCustomer = ref(null);
const editForm = useForm({
    name: '',
    email: '',
    phone: '',
});

const openEditModal = (customer) => {
    editingCustomer.value = customer;
    editForm.name = customer.name || '';
    editForm.email = customer.email || '';
    editForm.phone = customer.phone || '';
    editForm.clearErrors();
    isEditModalOpen.value = true;
};

const closeEditModal = () => {
    isEditModalOpen.value = false;
    editingCustomer.value = null;
    editForm.reset();
};

const submitEdit = () => {
    if (!editingCustomer.value) return;
    editForm.put(route('admin.customers.update', editingCustomer.value.id), {
        preserveScroll: true,
        onSuccess: () => closeEditModal(),
    });
};

// ─── Reset Password Modal ──────────────────────────────────────
const isPasswordModalOpen = ref(false);
const passwordCustomer = ref(null);
const passwordForm = useForm({
    password: '',
    password_confirmation: '',
});

const openPasswordModal = (customer) => {
    passwordCustomer.value = customer;
    passwordForm.reset();
    passwordForm.clearErrors();
    isPasswordModalOpen.value = true;
};

const closePasswordModal = () => {
    isPasswordModalOpen.value = false;
    passwordCustomer.value = null;
    passwordForm.reset();
};

const submitPasswordReset = () => {
    if (!passwordCustomer.value) return;
    passwordForm.put(route('admin.customers.reset-password', passwordCustomer.value.id), {
        preserveScroll: true,
        onSuccess: () => closePasswordModal(),
    });
};

// ─── Delete Customer ───────────────────────────────────────────
const deleteCustomer = (customer) => {
    if (confirm(`Are you sure you want to delete the account for "${customer.name}"? This action cannot be undone.`)) {
        router.delete(route('admin.customers.destroy', customer.id), {
            preserveScroll: true,
        });
    }
};

const formatCurrency = (amount) => {
    return '₦' + Number(amount || 0).toLocaleString('en-NG', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    });
};
</script>

<template>
    <Head title="Registered Customers — Admin" />

    <AdminLayout>
        <div class="space-y-6">
            <!-- Page Header -->
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                <div>
                    <h1 class="text-2xl font-black text-gray-900 dark:text-white tracking-tight flex items-center gap-2">
                        <span>👥</span> Storefront Customers
                    </h1>
                    <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">
                        View registered customer accounts, order history, lifetime spend, and account credentials.
                    </p>
                </div>

                <!-- Export CSV -->
                <div class="flex items-center gap-3">
                    <a
                        :href="route('admin.customers.export')"
                        target="_blank"
                        class="inline-flex items-center gap-2 px-4 py-2 bg-emerald-600 hover:bg-emerald-700 text-white rounded-lg text-xs font-bold shadow-xs transition"
                        title="Download customer list as CSV"
                    >
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                        </svg>
                        Export Customers (CSV)
                    </a>
                </div>
            </div>

            <!-- KPI Metric Cards -->
            <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
                <div class="bg-white dark:bg-gray-800 p-4 rounded-xl border border-gray-200 dark:border-gray-700 shadow-2xs">
                    <div class="text-xs font-bold text-gray-500 dark:text-gray-400 uppercase tracking-wider mb-1">
                        Total Customers
                    </div>
                    <div class="text-2xl font-black text-gray-900 dark:text-white">
                        {{ stats.total }}
                    </div>
                </div>

                <div class="bg-white dark:bg-gray-800 p-4 rounded-xl border border-gray-200 dark:border-gray-700 shadow-2xs">
                    <div class="text-xs font-bold text-emerald-600 dark:text-emerald-400 uppercase tracking-wider mb-1">
                        Joined This Month
                    </div>
                    <div class="text-2xl font-black text-emerald-600 dark:text-emerald-400">
                        {{ stats.new_this_month }}
                    </div>
                </div>

                <div class="bg-white dark:bg-gray-800 p-4 rounded-xl border border-gray-200 dark:border-gray-700 shadow-2xs">
                    <div class="text-xs font-bold text-blue-600 dark:text-blue-400 uppercase tracking-wider mb-1">
                        Active Buyers (With Orders)
                    </div>
                    <div class="text-2xl font-black text-blue-600 dark:text-blue-400">
                        {{ stats.with_orders }}
                    </div>
                </div>

                <div class="bg-white dark:bg-gray-800 p-4 rounded-xl border border-gray-200 dark:border-gray-700 shadow-2xs">
                    <div class="text-xs font-bold text-amber-600 dark:text-amber-400 uppercase tracking-wider mb-1">
                        Social Logins (Google)
                    </div>
                    <div class="text-2xl font-black text-amber-600 dark:text-amber-400">
                        {{ stats.social_logins }}
                    </div>
                </div>
            </div>

            <!-- Filters & Search -->
            <div class="bg-white dark:bg-gray-800 p-4 rounded-xl border border-gray-200 dark:border-gray-700 shadow-2xs flex flex-col md:flex-row items-center justify-between gap-3">
                <div class="flex-1 w-full md:max-w-md relative">
                    <input
                        v-model="search"
                        type="text"
                        placeholder="Search by customer name, email or phone..."
                        class="w-full pl-9 pr-4 py-2 rounded-lg border border-gray-300 dark:border-gray-600 bg-gray-50 dark:bg-gray-900 text-xs sm:text-sm text-gray-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-primary/40"
                    />
                    <svg class="w-4 h-4 text-gray-400 absolute left-3 top-1/2 -translate-y-1/2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                    </svg>
                </div>

                <div class="flex flex-wrap items-center gap-2 w-full md:w-auto">
                    <!-- Auth Provider Filter -->
                    <select
                        v-model="provider"
                        class="px-3 py-2 rounded-lg border border-gray-300 dark:border-gray-600 bg-gray-50 dark:bg-gray-900 text-xs sm:text-sm text-gray-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-primary/40 cursor-pointer"
                    >
                        <option value="all">All Signup Methods</option>
                        <option value="email">Email & Password</option>
                        <option value="google">Google Login</option>
                    </select>

                    <!-- Sort -->
                    <select
                        v-model="sort"
                        class="px-3 py-2 rounded-lg border border-gray-300 dark:border-gray-600 bg-gray-50 dark:bg-gray-900 text-xs sm:text-sm text-gray-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-primary/40 cursor-pointer"
                    >
                        <option value="latest">Newest Registered</option>
                        <option value="oldest">Oldest Registered</option>
                        <option value="orders_high">Most Orders</option>
                        <option value="spend_high">Highest Spend</option>
                        <option value="name_asc">Name (A-Z)</option>
                    </select>

                    <button
                        v-if="search || provider !== 'all' || sort !== 'latest'"
                        @click="clearFilters"
                        class="px-3 py-2 rounded-lg text-xs font-semibold text-gray-600 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700 transition"
                    >
                        Reset
                    </button>
                </div>
            </div>

            <!-- Customers Table -->
            <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 shadow-2xs overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-xs sm:text-sm">
                        <thead class="bg-gray-50 dark:bg-gray-900/60 border-b border-gray-200 dark:border-gray-700 text-gray-500 dark:text-gray-400 uppercase text-[10px] tracking-wider font-bold">
                            <tr>
                                <th class="px-4 py-3.5">Customer</th>
                                <th class="px-4 py-3.5">Contact Phone</th>
                                <th class="px-4 py-3.5">Auth Provider</th>
                                <th class="px-4 py-3.5 text-center">Orders</th>
                                <th class="px-4 py-3.5 text-right">Lifetime Spend</th>
                                <th class="px-4 py-3.5">Registered</th>
                                <th class="px-4 py-3.5 text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-gray-700/60 text-gray-700 dark:text-gray-300">
                            <tr
                                v-for="c in customers.data"
                                :key="c.id"
                                class="hover:bg-gray-50/60 dark:hover:bg-gray-700/40 transition"
                            >
                                <!-- Name & Email -->
                                <td class="px-4 py-3.5">
                                    <div class="flex items-center gap-3">
                                        <div class="w-8 h-8 rounded-full bg-primary/10 text-primary font-black flex items-center justify-center text-xs shrink-0 uppercase shadow-2xs">
                                            {{ c.name ? c.name.charAt(0) : 'C' }}
                                        </div>
                                        <div>
                                            <Link
                                                :href="route('admin.customers.show', c.id)"
                                                class="font-bold text-gray-900 dark:text-white hover:text-primary transition"
                                            >
                                                {{ c.name }}
                                            </Link>
                                            <div class="text-xs text-gray-500 dark:text-gray-400 font-normal">
                                                {{ c.email }}
                                            </div>
                                        </div>
                                    </div>
                                </td>

                                <!-- Phone -->
                                <td class="px-4 py-3.5 text-xs">
                                    <span v-if="c.phone" class="font-mono text-gray-700 dark:text-gray-300">
                                        {{ c.phone }}
                                    </span>
                                    <span v-else class="text-gray-400 italic">Not provided</span>
                                </td>

                                <!-- Provider -->
                                <td class="px-4 py-3.5">
                                    <span
                                        v-if="c.provider_name === 'google'"
                                        class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-bold bg-red-100 text-red-800 dark:bg-red-950/40 dark:text-red-300 uppercase tracking-wider"
                                    >
                                        Google
                                    </span>
                                    <span
                                        v-else
                                        class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-bold bg-blue-100 text-blue-800 dark:bg-blue-950/40 dark:text-blue-300 uppercase tracking-wider"
                                    >
                                        Direct Email
                                    </span>
                                </td>

                                <!-- Orders Count -->
                                <td class="px-4 py-3.5 text-center">
                                    <span
                                        :class="[
                                            'px-2 py-0.5 rounded-md text-xs font-bold',
                                            c.orders_count > 0
                                                ? 'bg-emerald-50 text-emerald-700 dark:bg-emerald-950/40 dark:text-emerald-300'
                                                : 'text-gray-400'
                                        ]"
                                    >
                                        {{ c.orders_count }}
                                    </span>
                                </td>

                                <!-- Lifetime Spend -->
                                <td class="px-4 py-3.5 text-right font-bold text-gray-900 dark:text-white">
                                    {{ formatCurrency(c.orders_sum_total_amount) }}
                                </td>

                                <!-- Registered Date -->
                                <td class="px-4 py-3.5 text-xs text-gray-500 dark:text-gray-400 whitespace-nowrap">
                                    {{ new Date(c.created_at).toLocaleDateString('en-US', { year: 'numeric', month: 'short', day: 'numeric' }) }}
                                </td>

                                <!-- Actions -->
                                <td class="px-4 py-3.5 text-right whitespace-nowrap">
                                    <div class="flex items-center justify-end gap-1.5">
                                        <!-- View Detail -->
                                        <Link
                                            :href="route('admin.customers.show', c.id)"
                                            class="p-1.5 rounded-lg text-gray-600 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700 transition"
                                            title="View customer profile & order history"
                                        >
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                                            </svg>
                                        </Link>

                                        <!-- Edit -->
                                        <button
                                            type="button"
                                            @click="openEditModal(c)"
                                            class="p-1.5 rounded-lg text-blue-600 hover:bg-blue-50 dark:hover:bg-blue-950/40 transition"
                                            title="Edit customer details"
                                        >
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                            </svg>
                                        </button>

                                        <!-- Reset Password -->
                                        <button
                                            type="button"
                                            @click="openPasswordModal(c)"
                                            class="p-1.5 rounded-lg text-amber-600 hover:bg-amber-50 dark:hover:bg-amber-950/40 transition"
                                            title="Reset customer password"
                                        >
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z" />
                                            </svg>
                                        </button>

                                        <!-- Delete -->
                                        <button
                                            type="button"
                                            @click="deleteCustomer(c)"
                                            class="p-1.5 rounded-lg text-red-500 hover:bg-red-50 dark:hover:bg-red-950/40 transition"
                                            title="Delete customer record"
                                        >
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                            </svg>
                                        </button>
                                    </div>
                                </td>
                            </tr>

                            <!-- Empty State -->
                            <tr v-if="!customers.data || customers.data.length === 0">
                                <td colspan="7" class="px-4 py-12 text-center text-gray-500 dark:text-gray-400">
                                    <div class="max-w-xs mx-auto space-y-2">
                                        <span class="text-3xl">👥</span>
                                        <p class="font-bold text-gray-800 dark:text-gray-200">No customers found</p>
                                        <p class="text-xs">
                                            No registered storefront customers matched your current filter criteria.
                                        </p>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Pagination -->
                <div v-if="customers.data && customers.data.length > 0" class="p-4 border-t border-gray-200 dark:border-gray-700">
                    <Pagination :links="customers.links" />
                </div>
            </div>
        </div>

        <!-- ─── Edit Customer Modal ─────────────────────────────────────── -->
        <div
            v-if="isEditModalOpen"
            class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/70 backdrop-blur-xs transition-opacity"
            @click.self="closeEditModal"
        >
            <div class="bg-white dark:bg-gray-900 rounded-2xl max-w-md w-full shadow-2xl border border-gray-200 dark:border-gray-800 overflow-hidden">
                <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-800 flex items-center justify-between">
                    <h3 class="text-base font-bold text-gray-900 dark:text-white">
                        Edit Customer Details
                    </h3>
                    <button @click="closeEditModal" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-200">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                    </button>
                </div>

                <form @submit.prevent="submitEdit" class="p-6 space-y-4 text-xs sm:text-sm">
                    <div>
                        <label class="block font-semibold text-gray-700 dark:text-gray-300 mb-1">Full Name</label>
                        <input
                            v-model="editForm.name"
                            type="text"
                            required
                            class="w-full px-3 py-2 rounded-lg border border-gray-300 dark:border-gray-700 bg-gray-50 dark:bg-gray-800 text-gray-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-primary/40"
                        />
                        <p v-if="editForm.errors.name" class="text-red-500 text-xs mt-1">{{ editForm.errors.name }}</p>
                    </div>

                    <div>
                        <label class="block font-semibold text-gray-700 dark:text-gray-300 mb-1">Email Address</label>
                        <input
                            v-model="editForm.email"
                            type="email"
                            required
                            class="w-full px-3 py-2 rounded-lg border border-gray-300 dark:border-gray-700 bg-gray-50 dark:bg-gray-800 text-gray-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-primary/40"
                        />
                        <p v-if="editForm.errors.email" class="text-red-500 text-xs mt-1">{{ editForm.errors.email }}</p>
                    </div>

                    <div>
                        <label class="block font-semibold text-gray-700 dark:text-gray-300 mb-1">Phone Number</label>
                        <input
                            v-model="editForm.phone"
                            type="text"
                            placeholder="+234..."
                            class="w-full px-3 py-2 rounded-lg border border-gray-300 dark:border-gray-700 bg-gray-50 dark:bg-gray-800 text-gray-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-primary/40"
                        />
                        <p v-if="editForm.errors.phone" class="text-red-500 text-xs mt-1">{{ editForm.errors.phone }}</p>
                    </div>

                    <div class="pt-2 flex items-center justify-end gap-2">
                        <button
                            type="button"
                            @click="closeEditModal"
                            class="px-4 py-2 rounded-lg border border-gray-300 dark:border-gray-700 text-gray-700 dark:text-gray-300 font-semibold hover:bg-gray-100 dark:hover:bg-gray-800 transition"
                        >
                            Cancel
                        </button>
                        <button
                            type="submit"
                            :disabled="editForm.processing"
                            class="px-5 py-2 bg-primary hover:opacity-90 disabled:opacity-50 text-white rounded-lg font-bold transition shadow-xs"
                        >
                            {{ editForm.processing ? 'Saving...' : 'Save Changes' }}
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- ─── Reset Password Modal ────────────────────────────────────── -->
        <div
            v-if="isPasswordModalOpen"
            class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/70 backdrop-blur-xs transition-opacity"
            @click.self="closePasswordModal"
        >
            <div class="bg-white dark:bg-gray-900 rounded-2xl max-w-md w-full shadow-2xl border border-gray-200 dark:border-gray-800 overflow-hidden">
                <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-800 flex items-center justify-between">
                    <div>
                        <h3 class="text-base font-bold text-gray-900 dark:text-white">
                            Reset Password
                        </h3>
                        <p class="text-xs text-gray-500 dark:text-gray-400">
                            For {{ passwordCustomer?.name }} ({{ passwordCustomer?.email }})
                        </p>
                    </div>
                    <button @click="closePasswordModal" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-200">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                    </button>
                </div>

                <form @submit.prevent="submitPasswordReset" class="p-6 space-y-4 text-xs sm:text-sm">
                    <div>
                        <label class="block font-semibold text-gray-700 dark:text-gray-300 mb-1">New Password</label>
                        <input
                            v-model="passwordForm.password"
                            type="password"
                            required
                            minlength="8"
                            placeholder="At least 8 characters"
                            class="w-full px-3 py-2 rounded-lg border border-gray-300 dark:border-gray-700 bg-gray-50 dark:bg-gray-800 text-gray-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-primary/40"
                        />
                        <p v-if="passwordForm.errors.password" class="text-red-500 text-xs mt-1">{{ passwordForm.errors.password }}</p>
                    </div>

                    <div>
                        <label class="block font-semibold text-gray-700 dark:text-gray-300 mb-1">Confirm New Password</label>
                        <input
                            v-model="passwordForm.password_confirmation"
                            type="password"
                            required
                            minlength="8"
                            placeholder="Repeat password"
                            class="w-full px-3 py-2 rounded-lg border border-gray-300 dark:border-gray-700 bg-gray-50 dark:bg-gray-800 text-gray-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-primary/40"
                        />
                    </div>

                    <div class="pt-2 flex items-center justify-end gap-2">
                        <button
                            type="button"
                            @click="closePasswordModal"
                            class="px-4 py-2 rounded-lg border border-gray-300 dark:border-gray-700 text-gray-700 dark:text-gray-300 font-semibold hover:bg-gray-100 dark:hover:bg-gray-800 transition"
                        >
                            Cancel
                        </button>
                        <button
                            type="submit"
                            :disabled="passwordForm.processing"
                            class="px-5 py-2 bg-amber-600 hover:bg-amber-700 disabled:opacity-50 text-white rounded-lg font-bold transition shadow-xs"
                        >
                            {{ passwordForm.processing ? 'Resetting...' : 'Update Password' }}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </AdminLayout>
</template>

