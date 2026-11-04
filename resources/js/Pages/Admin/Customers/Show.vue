<script setup>
import { ref } from 'vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import Pagination from '@/Components/Pagination.vue';

const props = defineProps({
    customer: Object,
    orders: Object,
    addresses: Array,
});

// ─── Edit Modal ────────────────────────────────────────────────
const isEditModalOpen = ref(false);
const editForm = useForm({
    name: props.customer.name || '',
    email: props.customer.email || '',
    phone: props.customer.phone || '',
});

const openEditModal = () => {
    editForm.name = props.customer.name || '';
    editForm.email = props.customer.email || '';
    editForm.phone = props.customer.phone || '';
    editForm.clearErrors();
    isEditModalOpen.value = true;
};

const closeEditModal = () => {
    isEditModalOpen.value = false;
};

const submitEdit = () => {
    editForm.put(route('admin.customers.update', props.customer.id), {
        preserveScroll: true,
        onSuccess: () => closeEditModal(),
    });
};

// ─── Reset Password Modal ──────────────────────────────────────
const isPasswordModalOpen = ref(false);
const passwordForm = useForm({
    password: '',
    password_confirmation: '',
});

const openPasswordModal = () => {
    passwordForm.reset();
    passwordForm.clearErrors();
    isPasswordModalOpen.value = true;
};

const closePasswordModal = () => {
    isPasswordModalOpen.value = false;
};

const submitPasswordReset = () => {
    passwordForm.put(route('admin.customers.reset-password', props.customer.id), {
        preserveScroll: true,
        onSuccess: () => closePasswordModal(),
    });
};

// ─── Delete Customer ───────────────────────────────────────────
const deleteCustomer = () => {
    if (confirm(`Are you sure you want to delete the account for "${props.customer.name}"? This action cannot be undone.`)) {
        router.delete(route('admin.customers.destroy', props.customer.id));
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
    <Head :title="customer.name + ' — Customer Profile'" />

    <AdminLayout>
        <div class="space-y-6">
            <!-- Breadcrumbs / Back button -->
            <div class="flex items-center justify-between">
                <Link
                    :href="route('admin.customers.index')"
                    class="inline-flex items-center gap-1.5 text-xs font-semibold text-gray-500 dark:text-gray-400 hover:text-primary transition"
                >
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
                    Back to All Customers
                </Link>

                <div class="flex items-center gap-2">
                    <button
                        type="button"
                        @click="openEditModal"
                        class="px-3 py-1.5 rounded-lg border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-800 text-xs font-semibold text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-700 transition"
                    >
                        Edit Profile
                    </button>
                    <button
                        type="button"
                        @click="openPasswordModal"
                        class="px-3 py-1.5 rounded-lg border border-amber-300 dark:border-amber-700 bg-amber-50 dark:bg-amber-950/40 text-xs font-semibold text-amber-700 dark:text-amber-300 hover:bg-amber-100 dark:hover:bg-amber-900/40 transition"
                    >
                        Reset Password
                    </button>
                    <button
                        type="button"
                        @click="deleteCustomer"
                        class="px-3 py-1.5 rounded-lg border border-red-300 dark:border-red-700 bg-red-50 dark:bg-red-950/40 text-xs font-semibold text-red-700 dark:text-red-300 hover:bg-red-100 dark:hover:bg-red-900/40 transition"
                    >
                        Delete Account
                    </button>
                </div>
            </div>

            <!-- Customer Overview Profile Card -->
            <div class="bg-white dark:bg-gray-800 rounded-2xl border border-gray-200 dark:border-gray-700 p-6 shadow-2xs">
                <div class="flex flex-col md:flex-row md:items-center justify-between gap-6">
                    <div class="flex items-center gap-4">
                        <div class="w-16 h-16 rounded-2xl bg-gradient-to-br from-primary to-primary-dark text-white font-black text-2xl flex items-center justify-center uppercase shadow-md">
                            {{ customer.name ? customer.name.charAt(0) : 'C' }}
                        </div>
                        <div>
                            <div class="flex items-center gap-2">
                                <h1 class="text-xl sm:text-2xl font-black text-gray-900 dark:text-white tracking-tight">
                                    {{ customer.name }}
                                </h1>
                                <span
                                    v-if="customer.provider_name === 'google'"
                                    class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-red-100 text-red-800 dark:bg-red-950/40 dark:text-red-300 uppercase"
                                >
                                    Google Login
                                </span>
                                <span
                                    v-else
                                    class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-blue-100 text-blue-800 dark:bg-blue-950/40 dark:text-blue-300 uppercase"
                                >
                                    Email Account
                                </span>
                            </div>
                            <div class="flex flex-wrap items-center gap-4 text-xs text-gray-500 dark:text-gray-400 mt-1">
                                <span>📧 {{ customer.email }}</span>
                                <span v-if="customer.phone">📱 {{ customer.phone }}</span>
                                <span>📅 Registered {{ new Date(customer.created_at).toLocaleDateString('en-US', { year: 'numeric', month: 'short', day: 'numeric' }) }}</span>
                            </div>
                        </div>
                    </div>

                    <!-- Lifetime Stats -->
                    <div class="flex items-center gap-6 divide-x divide-gray-200 dark:divide-gray-700 pt-4 md:pt-0 border-t md:border-t-0 border-gray-100 dark:border-gray-700">
                        <div class="text-center px-3">
                            <div class="text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                Orders
                            </div>
                            <div class="text-xl font-black text-gray-900 dark:text-white mt-0.5">
                                {{ customer.orders_count || 0 }}
                            </div>
                        </div>

                        <div class="text-center pl-6">
                            <div class="text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                Lifetime Spend
                            </div>
                            <div class="text-xl font-black text-emerald-600 dark:text-emerald-400 mt-0.5">
                                {{ formatCurrency(customer.orders_sum_total_amount) }}
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Grid: Orders History & Saved Addresses -->
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                <!-- Orders Table (2 Cols) -->
                <div class="lg:col-span-2 bg-white dark:bg-gray-800 rounded-2xl border border-gray-200 dark:border-gray-700 shadow-2xs overflow-hidden">
                    <div class="p-4 sm:p-5 border-b border-gray-200 dark:border-gray-700 flex items-center justify-between">
                        <h2 class="font-bold text-base text-gray-900 dark:text-white flex items-center gap-2">
                            <span>📦</span> Order History ({{ orders.total || 0 }})
                        </h2>
                    </div>

                    <div class="overflow-x-auto">
                        <table class="w-full text-left text-xs sm:text-sm">
                            <thead class="bg-gray-50 dark:bg-gray-900/60 border-b border-gray-200 dark:border-gray-700 text-gray-500 dark:text-gray-400 uppercase text-[10px] tracking-wider font-bold">
                                <tr>
                                    <th class="px-4 py-3">Order #</th>
                                    <th class="px-4 py-3">Date</th>
                                    <th class="px-4 py-3">Payment</th>
                                    <th class="px-4 py-3">Status</th>
                                    <th class="px-4 py-3 text-right">Total</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100 dark:divide-gray-700/60 text-gray-700 dark:text-gray-300">
                                <tr v-for="order in orders.data" :key="order.id" class="hover:bg-gray-50/60 dark:hover:bg-gray-700/40 transition">
                                    <td class="px-4 py-3 font-bold">
                                        <Link :href="route('admin.orders.show', order.id)" class="text-primary hover:underline font-mono">
                                            #{{ order.id }}
                                        </Link>
                                        <div v-if="order.tracking_number" class="text-[10px] text-gray-400 font-mono font-normal">
                                            {{ order.tracking_number }}
                                        </div>
                                    </td>
                                    <td class="px-4 py-3 text-xs text-gray-500 whitespace-nowrap">
                                        {{ new Date(order.created_at).toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' }) }}
                                    </td>
                                    <td class="px-4 py-3 text-xs capitalize">
                                        <span class="font-medium">{{ order.payment_method || '—' }}</span>
                                    </td>
                                    <td class="px-4 py-3">
                                        <span class="px-2 py-0.5 rounded-full text-[10px] font-bold uppercase tracking-wider bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-300">
                                            {{ order.status }}
                                        </span>
                                    </td>
                                    <td class="px-4 py-3 text-right font-bold text-gray-900 dark:text-white">
                                        {{ formatCurrency(order.total_amount) }}
                                    </td>
                                </tr>

                                <tr v-if="!orders.data || orders.data.length === 0">
                                    <td colspan="5" class="px-4 py-8 text-center text-gray-400 italic">
                                        No orders placed yet by this customer.
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <div v-if="orders.data && orders.data.length > 0" class="p-3 border-t border-gray-200 dark:border-gray-700">
                        <Pagination :links="orders.links" />
                    </div>
                </div>

                <!-- Saved Shipping Addresses (1 Col) -->
                <div class="bg-white dark:bg-gray-800 rounded-2xl border border-gray-200 dark:border-gray-700 shadow-2xs p-5 space-y-4">
                    <h2 class="font-bold text-base text-gray-900 dark:text-white flex items-center gap-2">
                        <span>📍</span> Saved Addresses ({{ addresses?.length || 0 }})
                    </h2>

                    <div v-if="addresses && addresses.length > 0" class="space-y-3">
                        <div
                            v-for="addr in addresses"
                            :key="addr.id"
                            class="p-3 rounded-xl border border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-900 text-xs space-y-1"
                        >
                            <div class="font-bold text-gray-900 dark:text-white flex items-center justify-between">
                                <span>{{ addr.first_name }} {{ addr.last_name }}</span>
                                <span v-if="addr.is_default" class="text-[10px] text-primary uppercase font-black">Default</span>
                            </div>
                            <p class="text-gray-600 dark:text-gray-300">{{ addr.address_line_1 }}</p>
                            <p v-if="addr.address_line_2" class="text-gray-500">{{ addr.address_line_2 }}</p>
                            <p class="text-gray-500">{{ addr.city }}, {{ addr.state }}</p>
                            <p v-if="addr.phone" class="text-gray-500 font-mono">📞 {{ addr.phone }}</p>
                        </div>
                    </div>
                    <div v-else class="text-xs text-gray-400 italic text-center py-6">
                        No saved shipping addresses on file.
                    </div>
                </div>
            </div>
        </div>

        <!-- ─── Edit Modal ──────────────────────────────────────────────── -->
        <div
            v-if="isEditModalOpen"
            class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/70 backdrop-blur-xs transition-opacity"
            @click.self="closeEditModal"
        >
            <div class="bg-white dark:bg-gray-900 rounded-2xl max-w-md w-full shadow-2xl border border-gray-200 dark:border-gray-800 overflow-hidden">
                <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-800 flex items-center justify-between">
                    <h3 class="text-base font-bold text-gray-900 dark:text-white">Edit Customer</h3>
                    <button @click="closeEditModal" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-200">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                    </button>
                </div>
                <form @submit.prevent="submitEdit" class="p-6 space-y-4 text-xs sm:text-sm">
                    <div>
                        <label class="block font-semibold mb-1">Full Name</label>
                        <input v-model="editForm.name" type="text" required class="w-full px-3 py-2 rounded-lg border border-gray-300 dark:border-gray-700 bg-gray-50 dark:bg-gray-800 focus:outline-none" />
                    </div>
                    <div>
                        <label class="block font-semibold mb-1">Email</label>
                        <input v-model="editForm.email" type="email" required class="w-full px-3 py-2 rounded-lg border border-gray-300 dark:border-gray-700 bg-gray-50 dark:bg-gray-800 focus:outline-none" />
                    </div>
                    <div>
                        <label class="block font-semibold mb-1">Phone</label>
                        <input v-model="editForm.phone" type="text" class="w-full px-3 py-2 rounded-lg border border-gray-300 dark:border-gray-700 bg-gray-50 dark:bg-gray-800 focus:outline-none" />
                    </div>
                    <div class="flex justify-end gap-2 pt-2">
                        <button type="button" @click="closeEditModal" class="px-4 py-2 border rounded-lg">Cancel</button>
                        <button type="submit" :disabled="editForm.processing" class="px-5 py-2 bg-primary text-white rounded-lg font-bold">Save</button>
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
                    <h3 class="text-base font-bold text-gray-900 dark:text-white">Reset Customer Password</h3>
                    <button @click="closePasswordModal" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-200">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                    </button>
                </div>
                <form @submit.prevent="submitPasswordReset" class="p-6 space-y-4 text-xs sm:text-sm">
                    <div>
                        <label class="block font-semibold mb-1">New Password</label>
                        <input v-model="passwordForm.password" type="password" required minlength="8" class="w-full px-3 py-2 rounded-lg border border-gray-300 dark:border-gray-700 bg-gray-50 dark:bg-gray-800 focus:outline-none" />
                    </div>
                    <div>
                        <label class="block font-semibold mb-1">Confirm New Password</label>
                        <input v-model="passwordForm.password_confirmation" type="password" required minlength="8" class="w-full px-3 py-2 rounded-lg border border-gray-300 dark:border-gray-700 bg-gray-50 dark:bg-gray-800 focus:outline-none" />
                    </div>
                    <div class="flex justify-end gap-2 pt-2">
                        <button type="button" @click="closePasswordModal" class="px-4 py-2 border rounded-lg">Cancel</button>
                        <button type="submit" :disabled="passwordForm.processing" class="px-5 py-2 bg-amber-600 text-white rounded-lg font-bold">Update Password</button>
                    </div>
                </form>
            </div>
        </div>
    </AdminLayout>
</template>

