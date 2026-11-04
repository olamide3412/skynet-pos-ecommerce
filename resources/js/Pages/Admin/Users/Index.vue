<script setup>
import { Head, useForm, router, usePage } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import Pagination from '@/Components/Pagination.vue';
import { ref, watch, computed } from 'vue';
import debounce from 'lodash/debounce';

const page = usePage();
const currentUserId = computed(() => page.props.auth?.user?.id);

const props = defineProps({
    users: Object,
    roles: {
        type: Array,
        default: () => ['staff', 'administrator', 'super administrator'],
    },
    allRoles: {
        type: Array,
        default: () => [],
    },
    isSuperAdmin: {
        type: Boolean,
        default: false,
    },
    statuses: {
        type: Array,
        default: () => ['enable', 'disable'],
    },
    stats: {
        type: Object,
        default: () => ({ total: 0, active: 0, disabled: 0, admins: 0, staff: 0 }),
    },
    filters: {
        type: Object,
        default: () => ({ search: '', role: '', status: '', per_page: '25' }),
    },
});

// ── Filters & Search ──────────────────────────────────────────
const search       = ref(props.filters?.search || '');
const filterRole   = ref(props.filters?.role || '');
const filterStatus = ref(props.filters?.status || '');
const perPage      = ref(props.filters?.per_page || '25');

const applyFilters = debounce(() => {
    router.get(route('admin.users.index'), {
        search:   search.value || undefined,
        role:     filterRole.value || undefined,
        status:   filterStatus.value || undefined,
        per_page: perPage.value !== '25' ? perPage.value : undefined,
    }, { preserveState: true, replace: true });
}, 300);

watch([search, filterRole, filterStatus, perPage], applyFilters);

const hasActiveFilters = computed(() => {
    return Boolean(search.value || filterRole.value || filterStatus.value || perPage.value !== '25');
});

const clearFilters = () => {
    search.value = '';
    filterRole.value = '';
    filterStatus.value = '';
    perPage.value = '25';
};

// ── Role Metadata & Helpers ───────────────────────────────────
const roleMeta = {
    'super administrator': {
        label: 'Super Administrator',
        short: 'Super Admin',
        desc: 'Full unrestricted access including system users, themes, and store settings.',
        badgeClass: 'theme-primary-badge',
    },
    'administrator': {
        label: 'Administrator',
        short: 'Admin',
        desc: 'Full operational access to catalog, orders, categories, POS sync, and reports.',
        badgeClass: 'theme-secondary-badge',
    },
    'staff': {
        label: 'Staff',
        short: 'Staff',
        desc: 'Day-to-day store operations, order management, and catalog assistance.',
        badgeClass: 'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/40 dark:text-emerald-300',
    },
    'user': {
        label: 'Standard User',
        short: 'User',
        desc: 'Basic registered account without elevated administrative privileges.',
        badgeClass: 'bg-gray-100 text-gray-700 dark:bg-gray-700 dark:text-gray-300',
    },
};

const getRoleInfo = (role) => roleMeta[role] || {
    label: role,
    short: role,
    desc: '',
    badgeClass: 'bg-gray-100 text-gray-700 dark:bg-gray-700 dark:text-gray-300',
};

const getInitials = (name) => {
    if (!name) return 'U';
    return name
        .split(' ')
        .filter(Boolean)
        .slice(0, 2)
        .map(part => part[0]?.toUpperCase())
        .join('');
};

// ── Create / Edit User Modal ──────────────────────────────────
const showFormModal = ref(false);
const isEditing     = ref(false);
const editId        = ref(null);
const showPassword  = ref(false);

const form = useForm({
    _method:  'POST',
    name:     '',
    email:    '',
    password: '',
    phone:    '',
    address:  '',
    role:     props.roles.includes('staff') ? 'staff' : (props.roles[0] || 'staff'),
    status:   props.statuses.includes('enable') ? 'enable' : (props.statuses[0] || 'enable'),
    roles:    [],
});

const openCreateModal = () => {
    isEditing.value = false;
    editId.value = null;
    showPassword.value = false;
    form.reset();
    form.clearErrors();
    form._method = 'POST';
    form.role = props.roles.includes('staff') ? 'staff' : (props.roles[0] || 'staff');
    form.status = 'enable';
    form.roles = [];
    showFormModal.value = true;
};

const openEditModal = (user) => {
    showViewModal.value = false;
    isEditing.value = true;
    editId.value = user.id;
    showPassword.value = false;
    form.clearErrors();
    form._method  = 'PUT';
    form.name     = user.name || '';
    form.email    = user.email || '';
    form.password = '';
    form.phone    = user.phone || '';
    form.address  = user.address || '';
    form.role     = user.role;
    form.status   = user.status;
    form.roles    = (user.roles || []).map(r => r.name);
    showFormModal.value = true;
};

const closeFormModal = () => {
    showFormModal.value = false;
    form.reset();
    form.clearErrors();
};

const saveUser = () => {
    const url = isEditing.value
        ? route('admin.users.update', editId.value)
        : route('admin.users.store');

    form.post(url, {
        preserveScroll: true,
        onSuccess: () => {
            closeFormModal();
        },
    });
};

// ── View User Details Modal (POS Show Profile Style) ──────────
const showViewModal = ref(false);
const selectedUser  = ref(null);

const openViewModal = (user) => {
    selectedUser.value = user;
    showViewModal.value = true;
};

// ── Reset Password Modal ──────────────────────────────────────
const showPasswordModal = ref(false);
const passwordTargetUser = ref(null);
const showResetPasswordText = ref(false);

const passwordForm = useForm({
    password: '',
    password_confirmation: '',
});

const openPasswordModal = (user) => {
    showViewModal.value = false;
    passwordTargetUser.value = user;
    showResetPasswordText.value = false;
    passwordForm.reset();
    passwordForm.clearErrors();
    showPasswordModal.value = true;
};

const submitPasswordReset = () => {
    if (!passwordTargetUser.value) return;
    passwordForm.put(route('admin.users.reset-password', passwordTargetUser.value.id), {
        preserveScroll: true,
        onSuccess: () => {
            showPasswordModal.value = false;
            passwordForm.reset();
        },
    });
};

// ── Quick Status Toggle ───────────────────────────────────────
const togglingUserId = ref(null);

const toggleUserStatus = (user) => {
    if (user.id === currentUserId.value) return;
    togglingUserId.value = user.id;
    router.patch(route('admin.users.toggle-status', user.id), {}, {
        preserveScroll: true,
        onFinish: () => {
            togglingUserId.value = null;
            if (selectedUser.value && selectedUser.value.id === user.id) {
                selectedUser.value = {
                    ...selectedUser.value,
                    status: selectedUser.value.status === 'enable' ? 'disable' : 'enable',
                };
            }
        },
    });
};

// ── Delete Confirmation Modal ─────────────────────────────────
const showDeleteModal = ref(false);
const deleteTargetUser = ref(null);
const deleteForm = useForm({});

const confirmDeleteUser = (user) => {
    if (user.id === currentUserId.value) return;
    showViewModal.value = false;
    deleteTargetUser.value = user;
    showDeleteModal.value = true;
};

const executeDeleteUser = () => {
    if (!deleteTargetUser.value) return;
    deleteForm.delete(route('admin.users.destroy', deleteTargetUser.value.id), {
        preserveScroll: true,
        onSuccess: () => {
            showDeleteModal.value = false;
            deleteTargetUser.value = null;
        },
    });
};
</script>

<template>
    <Head title="Manage System Users" />
    <AdminLayout>
        <div class="max-w-7xl mx-auto px-4 py-6 text-gray-900 dark:text-gray-100 space-y-6">

            <!-- ── Header & Primary CTA ────────────────────────── -->
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                <div>
                    <h1 class="text-2xl font-bold tracking-tight">Manage System Users</h1>
                    <p class="text-sm text-gray-500 dark:text-gray-400 mt-0.5">
                        Create, manage, and control access for Super Administrators, Administrators, and Staff accounts.
                    </p>
                </div>
                <button
                    type="button"
                    @click="openCreateModal"
                    class="inline-flex items-center justify-center gap-2 bg-primary hover:bg-primary-dark text-white font-bold px-5 py-2.5 rounded-xl shadow-sm transition text-sm shrink-0 cursor-pointer"
                >
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 4v16m8-8H4"/>
                    </svg>
                    Add New User
                </button>
            </div>

            <!-- ── KPI Summary Cards ───────────────────────────── -->
            <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
                <div class="bg-white dark:bg-gray-800 p-4 rounded-xl border border-gray-200 dark:border-gray-700 shadow-xs flex items-center justify-between">
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">Total Users</p>
                        <p class="text-2xl font-bold mt-1">{{ stats.total }}</p>
                    </div>
                    <div class="w-11 h-11 rounded-xl bg-primary-soft text-primary flex items-center justify-center">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/>
                        </svg>
                    </div>
                </div>

                <div class="bg-white dark:bg-gray-800 p-4 rounded-xl border border-gray-200 dark:border-gray-700 shadow-xs flex items-center justify-between">
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">Active Accounts</p>
                        <div class="flex items-baseline gap-2 mt-1">
                            <p class="text-2xl font-bold text-emerald-600 dark:text-emerald-400">{{ stats.active }}</p>
                            <span v-if="stats.disabled > 0" class="text-xs font-medium text-rose-500">({{ stats.disabled }} disabled)</span>
                        </div>
                    </div>
                    <div class="w-11 h-11 rounded-xl bg-emerald-100 dark:bg-emerald-900/30 text-emerald-600 dark:text-emerald-400 flex items-center justify-center">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                    </div>
                </div>

                <div class="bg-white dark:bg-gray-800 p-4 rounded-xl border border-gray-200 dark:border-gray-700 shadow-xs flex items-center justify-between">
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">Administrators</p>
                        <p class="text-2xl font-bold text-primary mt-1">{{ stats.admins }}</p>
                    </div>
                    <div class="w-11 h-11 rounded-xl bg-primary-soft text-primary flex items-center justify-center">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/>
                        </svg>
                    </div>
                </div>

                <div class="bg-white dark:bg-gray-800 p-4 rounded-xl border border-gray-200 dark:border-gray-700 shadow-xs flex items-center justify-between">
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">Staff Members</p>
                        <p class="text-2xl font-bold text-secondary mt-1">{{ stats.staff }}</p>
                    </div>
                    <div class="w-11 h-11 rounded-xl bg-secondary-soft text-secondary flex items-center justify-center">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 13.255A23.931 23.931 0 0112 15c-3.183 0-6.22-.62-9-1.745M16 6V4a2 2 0 00-2-2h-4a2 2 0 00-2 2v2m4 6h.01M5 20h14a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
                        </svg>
                    </div>
                </div>
            </div>

            <!-- ── Search & Filters Toolbar ────────────────────── -->
            <div class="bg-white dark:bg-gray-800 p-4 rounded-xl border border-gray-200 dark:border-gray-700 shadow-xs flex flex-wrap items-end gap-3">
                <div class="flex-1 min-w-[220px]">
                    <label class="block text-xs font-bold text-gray-500 dark:text-gray-400 uppercase tracking-wider mb-1">Search Users</label>
                    <div class="relative">
                        <input
                            v-model="search"
                            type="text"
                            placeholder="Search by name, email, phone, or address..."
                            class="w-full text-sm pl-9 pr-8 py-2 rounded-lg border border-gray-300 dark:border-gray-600 bg-gray-50 dark:bg-gray-900 focus:ring-2 focus:ring-primary"
                        />
                        <svg class="w-4 h-4 text-gray-400 absolute left-3 top-2.5 pointer-events-none" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                        </svg>
                        <button
                            v-if="search"
                            type="button"
                            @click="search = ''"
                            class="absolute right-2.5 top-2.5 text-gray-400 hover:text-gray-600 dark:hover:text-gray-200"
                        >
                            ✕
                        </button>
                    </div>
                </div>

                <div class="w-full sm:w-48">
                    <label class="block text-xs font-bold text-gray-500 dark:text-gray-400 uppercase tracking-wider mb-1">Role</label>
                    <select
                        v-model="filterRole"
                        class="w-full text-sm py-2 px-3 rounded-lg border border-gray-300 dark:border-gray-600 bg-gray-50 dark:bg-gray-900 focus:ring-2 focus:ring-primary"
                    >
                        <option value="">All Roles</option>
                        <option v-for="role in roles" :key="role" :value="role">
                            {{ getRoleInfo(role).label }}
                        </option>
                    </select>
                </div>

                <div class="w-full sm:w-44">
                    <label class="block text-xs font-bold text-gray-500 dark:text-gray-400 uppercase tracking-wider mb-1">Status</label>
                    <select
                        v-model="filterStatus"
                        class="w-full text-sm py-2 px-3 rounded-lg border border-gray-300 dark:border-gray-600 bg-gray-50 dark:bg-gray-900 focus:ring-2 focus:ring-primary"
                    >
                        <option value="">All Statuses</option>
                        <option value="enable">Active (Enabled)</option>
                        <option value="disable">Disabled</option>
                    </select>
                </div>

                <div class="w-full sm:w-36">
                    <label class="block text-xs font-bold text-gray-500 dark:text-gray-400 uppercase tracking-wider mb-1">Per Page</label>
                    <select
                        v-model="perPage"
                        class="w-full text-sm py-2 px-3 rounded-lg border border-gray-300 dark:border-gray-600 bg-gray-50 dark:bg-gray-900 focus:ring-2 focus:ring-primary"
                    >
                        <option value="10">10 per page</option>
                        <option value="25">25 per page</option>
                        <option value="50">50 per page</option>
                        <option value="100">100 per page</option>
                        <option value="all">All</option>
                    </select>
                </div>

                <button
                    v-if="hasActiveFilters"
                    type="button"
                    @click="clearFilters"
                    class="px-4 py-2 text-sm font-semibold text-gray-600 dark:text-gray-300 bg-gray-100 dark:bg-gray-700 hover:bg-gray-200 dark:hover:bg-gray-600 rounded-lg transition h-[38px]"
                >
                    Clear
                </button>
            </div>

            <!-- ── Users Table ─────────────────────────────────── -->
            <div class="bg-white dark:bg-gray-800 rounded-xl shadow-xs border border-gray-200 dark:border-gray-700 overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="w-full text-left whitespace-nowrap">
                        <thead class="bg-gray-50 dark:bg-gray-900/60 border-b border-gray-200 dark:border-gray-700 text-xs uppercase text-gray-500 dark:text-gray-400">
                            <tr>
                                <th class="px-5 py-3.5 font-semibold">User</th>
                                <th class="px-5 py-3.5 font-semibold">Contact</th>
                                <th class="px-5 py-3.5 font-semibold">Role</th>
                                <th class="px-5 py-3.5 font-semibold">Status</th>
                                <th class="px-5 py-3.5 font-semibold">Joined</th>
                                <th class="px-5 py-3.5 font-semibold text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-gray-700/70 text-sm">
                            <tr
                                v-for="user in users.data"
                                :key="user.id"
                                class="hover:bg-gray-50/80 dark:hover:bg-gray-700/40 transition"
                            >
                                <!-- User Avatar + Name + Email -->
                                <td class="px-5 py-3.5">
                                    <div class="flex items-center gap-3.5">
                                        <button
                                            type="button"
                                            @click="openViewModal(user)"
                                            class="w-10 h-10 rounded-full bg-primary-soft text-primary font-bold text-sm flex items-center justify-center shrink-0 border border-primary-soft hover:scale-105 transition cursor-pointer"
                                        >
                                            {{ getInitials(user.name) }}
                                        </button>
                                        <div class="min-w-0">
                                            <div class="flex items-center gap-2">
                                                <button
                                                    type="button"
                                                    @click="openViewModal(user)"
                                                    class="font-bold text-gray-900 dark:text-white hover:text-primary transition truncate max-w-[220px] text-left cursor-pointer"
                                                >
                                                    {{ user.name }}
                                                </button>
                                                <span
                                                    v-if="user.id === currentUserId"
                                                    class="text-[10px] font-bold uppercase px-1.5 py-0.5 rounded bg-primary text-white"
                                                >
                                                    You
                                                </span>
                                            </div>
                                            <p class="text-xs text-gray-500 dark:text-gray-400 truncate max-w-[240px]">
                                                {{ user.email }}
                                            </p>
                                        </div>
                                    </div>
                                </td>

                                <!-- Contact -->
                                <td class="px-5 py-3.5">
                                    <div v-if="user.phone" class="text-sm text-gray-800 dark:text-gray-200 font-medium">
                                        {{ user.phone }}
                                    </div>
                                    <div v-else class="text-xs text-gray-400 italic">No phone</div>
                                    <div v-if="user.address" class="text-xs text-gray-500 dark:text-gray-400 truncate max-w-[180px]" :title="user.address">
                                        {{ user.address }}
                                    </div>
                                </td>

                                <!-- Role & Assigned Capabilities -->
                                <td class="px-5 py-3.5">
                                    <div class="flex flex-col gap-1 items-start">
                                        <span
                                            class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold tracking-wide"
                                            :class="getRoleInfo(user.role).badgeClass"
                                            :title="getRoleInfo(user.role).desc"
                                        >
                                            {{ getRoleInfo(user.role).label }}
                                        </span>
                                        <!-- Spatie Roles Badges -->
                                        <div v-if="user.roles && user.roles.length > 0" class="flex flex-wrap gap-1 max-w-[200px]">
                                            <span
                                                v-for="r in user.roles"
                                                :key="r.id"
                                                class="text-[9px] font-extrabold px-1.5 py-0.5 rounded bg-primary/10 text-primary border border-primary/20"
                                            >
                                                {{ r.name }}
                                            </span>
                                        </div>
                                    </div>
                                </td>

                                <!-- Quick Status Toggle -->
                                <td class="px-5 py-3.5">
                                    <div class="flex items-center gap-2.5">
                                        <button
                                            type="button"
                                            @click="toggleUserStatus(user)"
                                            :disabled="user.id === currentUserId || togglingUserId === user.id"
                                            :title="user.id === currentUserId ? 'You cannot disable your own account' : 'Click to toggle Active / Disabled status'"
                                            class="relative inline-flex h-5 w-9 shrink-0 items-center rounded-full transition disabled:opacity-50 disabled:cursor-not-allowed cursor-pointer"
                                            :class="user.status === 'enable' ? 'bg-emerald-500' : 'bg-gray-300 dark:bg-gray-600'"
                                        >
                                            <span
                                                class="inline-block h-3.5 w-3.5 transform rounded-full bg-white transition shadow"
                                                :class="user.status === 'enable' ? 'translate-x-4' : 'translate-x-1'"
                                            />
                                        </button>
                                        <span
                                            class="text-xs font-semibold"
                                            :class="user.status === 'enable' ? 'text-emerald-600 dark:text-emerald-400' : 'text-rose-600 dark:text-rose-400'"
                                        >
                                            {{ user.status === 'enable' ? 'Active' : 'Disabled' }}
                                        </span>
                                    </div>
                                </td>

                                <!-- Joined Date -->
                                <td class="px-5 py-3.5 text-xs text-gray-500 dark:text-gray-400">
                                    {{ new Date(user.created_at).toLocaleDateString('en-GB', { day: '2-digit', month: 'short', year: 'numeric' }) }}
                                </td>

                                <!-- Actions -->
                                <td class="px-5 py-3.5 text-right">
                                    <div class="inline-flex items-center justify-end gap-1">
                                        <button
                                            type="button"
                                            @click="openViewModal(user)"
                                            class="px-2.5 py-1.5 text-xs font-semibold text-gray-700 dark:text-gray-200 hover:bg-gray-100 dark:hover:bg-gray-700 rounded-lg transition inline-flex items-center gap-1 cursor-pointer"
                                            title="View User Profile & Actions"
                                        >
                                            <svg class="w-3.5 h-3.5 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                                            </svg>
                                            View
                                        </button>

                                        <button
                                            type="button"
                                            @click="openEditModal(user)"
                                            class="px-2.5 py-1.5 text-xs font-semibold text-primary hover:bg-primary-soft rounded-lg transition inline-flex items-center gap-1 cursor-pointer"
                                            title="Edit User Details"
                                        >
                                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                                            </svg>
                                            Edit
                                        </button>

                                        <button
                                            type="button"
                                            @click="openPasswordModal(user)"
                                            class="px-2.5 py-1.5 text-xs font-semibold text-amber-600 dark:text-amber-400 hover:bg-amber-50 dark:hover:bg-amber-950/40 rounded-lg transition inline-flex items-center gap-1 cursor-pointer"
                                            title="Reset User Password"
                                        >
                                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z"/>
                                            </svg>
                                            Password
                                        </button>

                                        <button
                                            type="button"
                                            @click="confirmDeleteUser(user)"
                                            :disabled="user.id === currentUserId"
                                            :title="user.id === currentUserId ? 'You cannot delete your own account' : 'Delete User'"
                                            class="p-1.5 text-rose-600 dark:text-rose-400 hover:bg-rose-50 dark:hover:bg-rose-950/40 rounded-lg transition disabled:opacity-30 disabled:cursor-not-allowed cursor-pointer"
                                        >
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                                            </svg>
                                        </button>
                                    </div>
                                </td>
                            </tr>

                            <tr v-if="!users.data?.length">
                                <td colspan="6" class="px-6 py-12 text-center text-gray-500 dark:text-gray-400">
                                    <p class="font-semibold text-base">No system users match your filters.</p>
                                    <p class="text-xs mt-1">Try clearing your search filters or click "Add New User" to create an account.</p>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Table Footer / Pagination -->
                <div v-if="users.data?.length" class="px-5 py-3.5 border-t border-gray-200 dark:border-gray-700 flex flex-col sm:flex-row items-center justify-between gap-4">
                    <div class="text-xs text-gray-500 dark:text-gray-400">
                        Showing <span class="font-semibold text-gray-700 dark:text-gray-200">{{ users.from || 0 }}</span> to
                        <span class="font-semibold text-gray-700 dark:text-gray-200">{{ users.to || 0 }}</span> of
                        <span class="font-semibold text-gray-700 dark:text-gray-200">{{ users.total || 0 }}</span> users
                    </div>
                    <Pagination :paginator="users" />
                </div>
            </div>
        </div>

        <!-- ══════════════════════════════════════════════════════════════ -->
        <!-- 1. CREATE / EDIT USER MODAL                                    -->
        <!-- ══════════════════════════════════════════════════════════════ -->
        <div v-if="showFormModal" class="fixed inset-0 z-50 flex items-center justify-center p-3 sm:p-4 bg-black/60 backdrop-blur-xs">
            <div class="bg-white dark:bg-gray-800 rounded-2xl max-w-xl w-full max-h-[88vh] flex flex-col shadow-2xl border border-gray-200 dark:border-gray-700 overflow-hidden animate-fade-in">
                
                <!-- Fixed Header -->
                <div class="px-5 py-3.5 border-b border-gray-100 dark:border-gray-700 flex items-center justify-between shrink-0 bg-white dark:bg-gray-800">
                    <div>
                        <h3 class="text-base font-bold text-gray-900 dark:text-white">
                            {{ isEditing ? 'Edit System User' : 'Create New System User' }}
                        </h3>
                        <p class="text-[11px] text-gray-500 dark:text-gray-400 mt-0.5">
                            {{ isEditing ? 'Update profile details, role permissions, or account status.' : 'Fill in the user details and assign an access role.' }}
                        </p>
                    </div>
                    <button type="button" @click="closeFormModal" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-200 text-lg font-bold p-1 cursor-pointer">
                        ✕
                    </button>
                </div>

                <!-- Scrollable Form Body -->
                <form id="userForm" @submit.prevent="saveUser" class="flex-1 overflow-y-auto p-4 sm:p-5 space-y-3.5 overscroll-contain">
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3.5">
                        <!-- Full Name -->
                        <div>
                            <label class="block text-[11px] font-bold uppercase tracking-wider text-gray-600 dark:text-gray-300 mb-1">Full Name *</label>
                            <input
                                v-model="form.name"
                                type="text"
                                required
                                placeholder="e.g. Jane Doe"
                                class="w-full rounded-lg border border-gray-300 dark:border-gray-600 dark:bg-gray-700 px-3 py-1.5 text-xs focus:ring-2 focus:ring-primary shadow-xs"
                            />
                            <p v-if="form.errors.name" class="text-red-500 text-xs mt-1">{{ form.errors.name }}</p>
                        </div>

                        <!-- Email Address -->
                        <div>
                            <label class="block text-[11px] font-bold uppercase tracking-wider text-gray-600 dark:text-gray-300 mb-1">Email Address *</label>
                            <input
                                v-model="form.email"
                                type="email"
                                required
                                placeholder="e.g. jane@example.com"
                                class="w-full rounded-lg border border-gray-300 dark:border-gray-600 dark:bg-gray-700 px-3 py-1.5 text-xs focus:ring-2 focus:ring-primary shadow-xs"
                            />
                            <p v-if="form.errors.email" class="text-red-500 text-xs mt-1">{{ form.errors.email }}</p>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3.5">
                        <!-- Phone Number -->
                        <div>
                            <label class="block text-[11px] font-bold uppercase tracking-wider text-gray-600 dark:text-gray-300 mb-1">Phone Number</label>
                            <input
                                v-model="form.phone"
                                type="tel"
                                placeholder="e.g. +234 800 000 0000"
                                class="w-full rounded-lg border border-gray-300 dark:border-gray-600 dark:bg-gray-700 px-3 py-1.5 text-xs focus:ring-2 focus:ring-primary shadow-xs"
                            />
                            <p v-if="form.errors.phone" class="text-red-500 text-xs mt-1">{{ form.errors.phone }}</p>
                        </div>

                        <!-- Password -->
                        <div>
                            <label class="block text-[11px] font-bold uppercase tracking-wider text-gray-600 dark:text-gray-300 mb-1">
                                {{ isEditing ? 'New Password (Optional)' : 'Password *' }}
                            </label>
                            <div class="relative">
                                <input
                                    v-model="form.password"
                                    :type="showPassword ? 'text' : 'password'"
                                    :required="!isEditing"
                                    :placeholder="isEditing ? 'Leave blank to keep current' : 'Minimum 8 characters'"
                                    class="w-full rounded-lg border border-gray-300 dark:border-gray-600 dark:bg-gray-700 px-3 py-1.5 pr-14 text-xs focus:ring-2 focus:ring-primary shadow-xs"
                                />
                                <button
                                    type="button"
                                    @click="showPassword = !showPassword"
                                    class="absolute inset-y-0 right-0 pr-3 flex items-center text-[11px] font-semibold text-gray-400 hover:text-gray-600 dark:hover:text-gray-200 cursor-pointer"
                                >
                                    {{ showPassword ? 'Hide' : 'Show' }}
                                </button>
                            </div>
                            <p v-if="form.errors.password" class="text-red-500 text-xs mt-1">{{ form.errors.password }}</p>
                        </div>
                    </div>

                    <!-- Address -->
                    <div>
                        <label class="block text-[11px] font-bold uppercase tracking-wider text-gray-600 dark:text-gray-300 mb-1">Address (Optional)</label>
                        <input
                            v-model="form.address"
                            type="text"
                            placeholder="Office or residential address..."
                            class="w-full rounded-lg border border-gray-300 dark:border-gray-600 dark:bg-gray-700 px-3 py-1.5 text-xs focus:ring-2 focus:ring-primary shadow-xs"
                        />
                        <p v-if="form.errors.address" class="text-red-500 text-xs mt-1">{{ form.errors.address }}</p>
                    </div>

                    <!-- Role Selector Cards -->
                    <div>
                        <label class="block text-[11px] font-bold uppercase tracking-wider text-gray-600 dark:text-gray-300 mb-1.5">Account Security Tier *</label>
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
                            <label
                                v-for="role in roles"
                                :key="role"
                                class="flex items-start gap-2 p-2.5 rounded-xl border cursor-pointer transition text-xs"
                                :class="form.role === role ? 'border-primary bg-primary-soft ring-1 ring-primary/30 font-bold' : 'border-gray-200 dark:border-gray-700 hover:bg-gray-50 dark:hover:bg-gray-700/40'"
                            >
                                <input
                                    type="radio"
                                    :value="role"
                                    v-model="form.role"
                                    class="mt-0.5 text-primary focus:ring-primary h-3.5 w-3.5"
                                />
                                <div class="min-w-0">
                                    <div class="font-bold text-gray-900 dark:text-white">
                                        {{ getRoleInfo(role).label }}
                                    </div>
                                    <p class="text-[10px] text-gray-500 dark:text-gray-400 leading-snug mt-0.5 font-normal">
                                        {{ getRoleInfo(role).desc }}
                                    </p>
                                </div>
                            </label>
                        </div>
                        <p v-if="form.errors.role" class="text-red-500 text-xs mt-1">{{ form.errors.role }}</p>
                    </div>

                    <!-- Multi-Role Capabilities Assignment (Super Admin Only) -->
                    <div v-if="isSuperAdmin && allRoles && allRoles.length > 0" class="p-4 rounded-xl border border-primary/20 bg-primary/5">
                        <div class="flex items-center justify-between mb-2">
                            <div>
                                <span class="text-xs font-bold text-gray-900 dark:text-white flex items-center gap-1.5">
                                    <span>🛡</span> Assign Role Capabilities
                                </span>
                                <p class="text-[10px] text-gray-500 mt-0.5">
                                    Select one or multiple roles (e.g. Warehouse + Rider).
                                </p>
                            </div>
                            <span class="text-[10px] font-bold px-2 py-0.5 rounded bg-primary text-white">
                                Super Admin Only
                            </span>
                        </div>

                        <div class="grid grid-cols-2 sm:grid-cols-3 gap-2 pt-1">
                            <label
                                v-for="r in allRoles"
                                :key="r.id"
                                class="flex items-center gap-2 p-2 rounded-lg border border-gray-200 dark:border-gray-600 hover:bg-white dark:hover:bg-gray-700 cursor-pointer transition text-xs"
                            >
                                <input
                                    type="checkbox"
                                    :value="r.name"
                                    v-model="form.roles"
                                    class="rounded border-gray-300 text-primary focus:ring-primary h-4 w-4"
                                />
                                <span class="font-bold text-gray-800 dark:text-gray-200 text-xs">
                                    {{ r.name }}
                                </span>
                            </label>
                        </div>
                    </div>

                    <!-- Account Status Switch -->
                    <div class="flex items-center justify-between p-3 rounded-xl bg-gray-50 dark:bg-gray-900/50 border border-gray-200 dark:border-gray-700">
                        <div>
                            <p class="text-xs font-bold text-gray-900 dark:text-white">Account Login Status</p>
                            <p class="text-[11px] text-gray-500 dark:text-gray-400">
                                {{ form.status === 'enable' ? 'Active — User can sign in to the system.' : 'Disabled — User login is suspended.' }}
                            </p>
                        </div>
                        <button
                            type="button"
                            @click="form.status = form.status === 'enable' ? 'disable' : 'enable'"
                            :disabled="isEditing && editId === currentUserId"
                            class="relative inline-flex h-6 w-11 items-center rounded-full transition disabled:opacity-50 cursor-pointer"
                            :class="form.status === 'enable' ? 'bg-emerald-500' : 'bg-gray-300 dark:bg-gray-600'"
                        >
                            <span
                                class="inline-block h-4 w-4 transform rounded-full bg-white transition shadow"
                                :class="form.status === 'enable' ? 'translate-x-6' : 'translate-x-1'"
                            />
                        </button>
                    </div>
                </form>

                <!-- Fixed Footer (Always visible, never cut off) -->
                <div class="px-5 py-3 border-t border-gray-100 dark:border-gray-700 bg-gray-50/90 dark:bg-gray-800/90 flex items-center justify-end gap-2.5 shrink-0">
                    <button
                        type="button"
                        @click="closeFormModal"
                        class="px-4 py-2 text-xs font-semibold text-gray-600 dark:text-gray-300 hover:bg-gray-200/60 dark:hover:bg-gray-700 rounded-xl transition cursor-pointer"
                    >
                        Cancel
                    </button>
                    <button
                        type="submit"
                        form="userForm"
                        :disabled="form.processing"
                        class="bg-primary hover:bg-primary-dark text-white font-bold px-5 py-2 rounded-xl text-xs shadow-xs transition disabled:opacity-60 flex items-center gap-1.5 cursor-pointer"
                    >
                        <svg v-if="form.processing" class="w-3.5 h-3.5 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path></svg>
                        {{ form.processing ? 'Saving...' : (isEditing ? 'Save Changes' : 'Create User') }}
                    </button>
                </div>

            </div>
        </div>

        <!-- ══════════════════════════════════════════════════════════════ -->
        <!-- 2. VIEW USER DETAILS MODAL (POS Show Style)                    -->
        <!-- ══════════════════════════════════════════════════════════════ -->
        <div v-if="showViewModal && selectedUser" class="fixed inset-0 z-50 flex items-center justify-center p-3 sm:p-4 bg-black/60 backdrop-blur-xs">
            <div class="bg-white dark:bg-gray-800 rounded-2xl max-w-lg w-full max-h-[88vh] flex flex-col shadow-2xl border border-gray-200 dark:border-gray-700 overflow-hidden animate-fade-in">
                
                <!-- Fixed Header -->
                <div class="px-5 py-3.5 border-b border-gray-100 dark:border-gray-700 flex items-center justify-between shrink-0 bg-white dark:bg-gray-800">
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 rounded-xl bg-primary-soft text-primary font-bold text-sm flex items-center justify-center border border-primary-soft">
                            {{ getInitials(selectedUser.name) }}
                        </div>
                        <div>
                            <div class="flex items-center gap-2 flex-wrap">
                                <h3 class="text-sm font-bold text-gray-900 dark:text-white">{{ selectedUser.name }}</h3>
                                <span
                                    class="inline-flex items-center px-2 py-0.2 rounded-full text-[10px] font-semibold"
                                    :class="selectedUser.status === 'enable' ? 'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/40 dark:text-emerald-300' : 'bg-rose-100 text-rose-800 dark:bg-rose-900/40 dark:text-rose-300'"
                                >
                                    ● {{ selectedUser.status === 'enable' ? 'Enabled' : 'Disabled' }}
                                </span>
                            </div>
                            <p class="text-xs text-gray-500 dark:text-gray-400">{{ selectedUser.email }}</p>
                        </div>
                    </div>
                    <button type="button" @click="showViewModal = false" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-200 text-lg font-bold p-1 cursor-pointer">
                        ✕
                    </button>
                </div>

                <!-- Scrollable Body -->
                <div class="flex-1 overflow-y-auto p-4 sm:p-5 space-y-4 overscroll-contain">
                    <!-- Details Grid -->
                    <div class="bg-gray-50 dark:bg-gray-900/50 rounded-xl p-3.5 border border-gray-200 dark:border-gray-700 grid grid-cols-2 gap-3 text-xs">
                        <div>
                            <span class="block text-[10px] text-gray-400 uppercase font-semibold">System Role</span>
                            <span class="inline-flex mt-1 px-2 py-0.5 rounded-full text-[10px] font-semibold" :class="getRoleInfo(selectedUser.role).badgeClass">
                                {{ getRoleInfo(selectedUser.role).label }}
                            </span>
                        </div>
                        <div>
                            <span class="block text-[10px] text-gray-400 uppercase font-semibold">Phone Number</span>
                            <span class="font-medium text-gray-900 dark:text-white mt-1 block">{{ selectedUser.phone || 'Not provided' }}</span>
                        </div>
                        <div class="col-span-2">
                            <span class="block text-[10px] text-gray-400 uppercase font-semibold">Address</span>
                            <span class="font-medium text-gray-900 dark:text-white mt-0.5 block">{{ selectedUser.address || 'Not provided' }}</span>
                        </div>
                        <div>
                            <span class="block text-[10px] text-gray-400 uppercase font-semibold">Created On</span>
                            <span class="font-medium text-gray-700 dark:text-gray-300 mt-0.5 block">
                                {{ new Date(selectedUser.created_at).toLocaleString() }}
                            </span>
                        </div>
                        <div>
                            <span class="block text-[10px] text-gray-400 uppercase font-semibold">Last Updated</span>
                            <span class="font-medium text-gray-700 dark:text-gray-300 mt-0.5 block">
                                {{ new Date(selectedUser.updated_at).toLocaleString() }}
                            </span>
                        </div>
                    </div>
                </div>

                <!-- Fixed Footer -->
                <div class="px-5 py-3 border-t border-gray-100 dark:border-gray-700 bg-gray-50/90 dark:bg-gray-800/90 flex flex-wrap items-center justify-between gap-2 shrink-0">
                    <div class="flex flex-wrap items-center gap-2">
                        <button
                            type="button"
                            @click="openEditModal(selectedUser)"
                            class="px-3 py-1.5 bg-primary hover:bg-primary-dark text-white rounded-lg text-xs font-bold transition cursor-pointer"
                        >
                            Edit Account
                        </button>
                        <button
                            type="button"
                            @click="openPasswordModal(selectedUser)"
                            class="px-3 py-1.5 bg-amber-500 hover:bg-amber-600 text-white rounded-lg text-xs font-bold transition cursor-pointer"
                        >
                            Reset Password
                        </button>
                        <button
                            v-if="selectedUser.id !== currentUserId"
                            type="button"
                            @click="toggleUserStatus(selectedUser)"
                            class="px-3 py-1.5 border border-gray-300 dark:border-gray-600 hover:bg-gray-100 dark:hover:bg-gray-700 rounded-lg text-xs font-semibold transition cursor-pointer"
                        >
                            {{ selectedUser.status === 'enable' ? 'Disable User' : 'Enable User' }}
                        </button>
                    </div>

                    <button
                        v-if="selectedUser.id !== currentUserId"
                        type="button"
                        @click="confirmDeleteUser(selectedUser)"
                        class="px-3 py-1.5 bg-rose-600 hover:bg-rose-700 text-white rounded-lg text-xs font-bold transition cursor-pointer"
                    >
                        Delete
                    </button>
                </div>
            </div>
        </div>

        <!-- ══════════════════════════════════════════════════════════════ -->
        <!-- 3. RESET PASSWORD MODAL                                        -->
        <!-- ══════════════════════════════════════════════════════════════ -->
        <div v-if="showPasswordModal && passwordTargetUser" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/50 backdrop-blur-xs">
            <div class="bg-white dark:bg-gray-800 rounded-2xl max-w-md w-full p-6 shadow-2xl border border-gray-200 dark:border-gray-700 space-y-4">
                <div class="flex items-center justify-between pb-3 border-b border-gray-100 dark:border-gray-700">
                    <div>
                        <h3 class="text-base font-bold text-gray-900 dark:text-white">Reset User Password</h3>
                        <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">
                            Setting a new password for <strong>{{ passwordTargetUser.name }}</strong>
                        </p>
                    </div>
                    <button type="button" @click="showPasswordModal = false" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-200">
                        ✕
                    </button>
                </div>

                <form @submit.prevent="submitPasswordReset" class="space-y-4">
                    <div>
                        <label class="block text-xs font-bold uppercase tracking-wider text-gray-600 dark:text-gray-300 mb-1">New Password *</label>
                        <div class="relative">
                            <input
                                v-model="passwordForm.password"
                                :type="showResetPasswordText ? 'text' : 'password'"
                                required
                                minlength="8"
                                placeholder="Minimum 8 characters"
                                class="w-full rounded-lg border border-gray-300 dark:border-gray-600 dark:bg-gray-700 px-3 py-2 pr-14 text-sm focus:ring-2 focus:ring-primary"
                            />
                            <button
                                type="button"
                                @click="showResetPasswordText = !showResetPasswordText"
                                class="absolute inset-y-0 right-0 pr-3 flex items-center text-xs font-semibold text-gray-400 hover:text-gray-600"
                            >
                                {{ showResetPasswordText ? 'Hide' : 'Show' }}
                            </button>
                        </div>
                        <p v-if="passwordForm.errors.password" class="text-red-500 text-xs mt-1">{{ passwordForm.errors.password }}</p>
                    </div>

                    <div>
                        <label class="block text-xs font-bold uppercase tracking-wider text-gray-600 dark:text-gray-300 mb-1">Confirm New Password *</label>
                        <input
                            v-model="passwordForm.password_confirmation"
                            :type="showResetPasswordText ? 'text' : 'password'"
                            required
                            minlength="8"
                            placeholder="Re-enter new password"
                            class="w-full rounded-lg border border-gray-300 dark:border-gray-600 dark:bg-gray-700 px-3 py-2 text-sm focus:ring-2 focus:ring-primary"
                        />
                    </div>

                    <div class="flex items-center justify-end gap-3 pt-3 border-t border-gray-100 dark:border-gray-700">
                        <button
                            type="button"
                            @click="showPasswordModal = false"
                            class="px-4 py-2 text-xs font-semibold text-gray-600 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700 rounded-lg transition"
                        >
                            Cancel
                        </button>
                        <button
                            type="submit"
                            :disabled="passwordForm.processing"
                            class="px-5 py-2 bg-primary hover:bg-primary-dark text-white text-xs font-bold rounded-lg shadow-sm transition disabled:opacity-60"
                        >
                            {{ passwordForm.processing ? 'Resetting...' : 'Reset Password' }}
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- ══════════════════════════════════════════════════════════════ -->
        <!-- 4. DELETE USER CONFIRMATION MODAL                              -->
        <!-- ══════════════════════════════════════════════════════════════ -->
        <div v-if="showDeleteModal && deleteTargetUser" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/50 backdrop-blur-xs">
            <div class="bg-white dark:bg-gray-800 rounded-2xl max-w-md w-full p-6 shadow-2xl border border-gray-200 dark:border-gray-700 space-y-4">
                <div class="flex items-center gap-3">
                    <div class="w-11 h-11 rounded-full bg-rose-100 dark:bg-rose-900/40 text-rose-600 dark:text-rose-400 flex items-center justify-center shrink-0">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                        </svg>
                    </div>
                    <div>
                        <h3 class="text-base font-bold text-gray-900 dark:text-white">Delete System User</h3>
                        <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">
                            Are you sure you want to permanently delete <strong>{{ deleteTargetUser.name }}</strong> ({{ deleteTargetUser.email }})?
                        </p>
                    </div>
                </div>

                <div class="flex items-center justify-end gap-3 pt-3 border-t border-gray-100 dark:border-gray-700">
                    <button
                        type="button"
                        @click="showDeleteModal = false"
                        class="px-4 py-2 text-xs font-semibold text-gray-600 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700 rounded-lg transition"
                    >
                        Cancel
                    </button>
                    <button
                        type="button"
                        @click="executeDeleteUser"
                        :disabled="deleteForm.processing"
                        class="px-5 py-2 bg-rose-600 hover:bg-rose-700 text-white text-xs font-bold rounded-lg shadow-sm transition disabled:opacity-60"
                    >
                        {{ deleteForm.processing ? 'Deleting...' : 'Yes, Delete User' }}
                    </button>
                </div>
            </div>
        </div>
    </AdminLayout>
</template>
