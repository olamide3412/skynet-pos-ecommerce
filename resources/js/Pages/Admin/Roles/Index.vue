<script setup>
import { Head, router } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { ref, computed } from 'vue';

const props = defineProps({
    roles: {
        type: Array,
        required: true,
    },
    permissionModules: {
        type: Object,
        required: true,
    },
    defaultRoleTemplates: {
        type: Object,
        default: () => ({}),
    },
});

// Modals
const showModal = ref(false);
const isEditing = ref(false);
const editingRoleId = ref(null);
const roleName = ref('');
const selectedPermissions = ref([]);
const isSubmitting = ref(false);
const searchPermission = ref('');

const allPermissionNames = computed(() => {
    const list = [];
    Object.values(props.permissionModules).forEach(perms => {
        Object.keys(perms).forEach(p => list.push(p));
    });
    return list;
});

const openCreateModal = () => {
    isEditing.value = false;
    editingRoleId.value = null;
    roleName.value = '';
    selectedPermissions.value = ['backoffice.access', 'dashboard.view'];
    searchPermission.value = '';
    showModal.value = true;
};

const openEditModal = (role) => {
    isEditing.value = true;
    editingRoleId.value = role.id;
    roleName.value = role.name;
    selectedPermissions.value = (role.permissions || []).map(p => p.name);
    searchPermission.value = '';
    showModal.value = true;
};

const applyTemplate = (templateName) => {
    const template = props.defaultRoleTemplates[templateName];
    if (!template) return;
    if (!isEditing.value) {
        roleName.value = templateName;
    }
    selectedPermissions.value = [...template.permissions];
};

const togglePermission = (permName) => {
    const idx = selectedPermissions.value.indexOf(permName);
    if (idx > -1) {
        selectedPermissions.value.splice(idx, 1);
    } else {
        selectedPermissions.value.push(permName);
    }
};

const toggleModuleAll = (modulePerms) => {
    const permKeys = Object.keys(modulePerms);
    const allSelected = permKeys.every(k => selectedPermissions.value.includes(k));
    if (allSelected) {
        // Unselect all in module
        selectedPermissions.value = selectedPermissions.value.filter(k => !permKeys.includes(k));
    } else {
        // Add missing
        permKeys.forEach(k => {
            if (!selectedPermissions.value.includes(k)) {
                selectedPermissions.value.push(k);
            }
        });
    }
};

const isModuleAllSelected = (modulePerms) => {
    const permKeys = Object.keys(modulePerms);
    return permKeys.length > 0 && permKeys.every(k => selectedPermissions.value.includes(k));
};

const selectAllGlobal = () => {
    selectedPermissions.value = [...allPermissionNames.value];
};

const clearAllGlobal = () => {
    selectedPermissions.value = ['backoffice.access'];
};

const submitForm = () => {
    if (!roleName.value.trim()) return;
    isSubmitting.value = true;

    if (isEditing.value) {
        router.put(route('admin.roles.update', editingRoleId.value), {
            name: roleName.value,
            permissions: selectedPermissions.value,
        }, {
            preserveScroll: true,
            onFinish: () => {
                isSubmitting.value = false;
                showModal.value = false;
            }
        });
    } else {
        router.post(route('admin.roles.store'), {
            name: roleName.value,
            permissions: selectedPermissions.value,
        }, {
            preserveScroll: true,
            onFinish: () => {
                isSubmitting.value = false;
                showModal.value = false;
            }
        });
    }
};

const deleteRole = (role) => {
    if (!confirm(`Are you sure you want to permanently delete the role "${role.name}"?`)) return;
    router.delete(route('admin.roles.destroy', role.id), { preserveScroll: true });
};
</script>

<template>
    <Head title="Roles & Permissions Management" />
    <AdminLayout>
        <div class="container mx-auto px-4 py-8 max-w-7xl">

            <!-- ── Header ─────────────────────────────────────────── -->
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-6">
                <div>
                    <div class="flex items-center gap-2">
                        <span class="px-2.5 py-0.5 rounded-full text-[10px] font-extrabold uppercase bg-rose-100 text-rose-800 dark:bg-rose-900/40 dark:text-rose-300">
                            🛡 Super Administrator Security
                        </span>
                    </div>
                    <h1 class="text-2xl sm:text-3xl font-extrabold text-gray-900 dark:text-white mt-1 flex items-center gap-2">
                        <span>👥</span> Roles & Permissions
                    </h1>
                    <p class="text-xs sm:text-sm text-gray-500 dark:text-gray-400 mt-0.5">
                        Define role templates and assign modular capabilities. Staff members can hold multiple roles simultaneously.
                    </p>
                </div>

                <div>
                    <button
                        type="button"
                        @click="openCreateModal"
                        class="px-4 py-2 bg-primary hover:bg-primary-dark text-white rounded-xl text-xs font-bold shadow-xs flex items-center gap-2 transition cursor-pointer"
                    >
                        <span>+</span> Create New Role
                    </button>
                </div>
            </div>

            <!-- ── Role Cards Grid ─────────────────────────────────── -->
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-5 mb-8">
                <div
                    v-for="role in roles"
                    :key="role.id"
                    class="bg-white dark:bg-gray-800 rounded-2xl border border-gray-200 dark:border-gray-700 p-5 shadow-xs flex flex-col justify-between hover:border-primary/50 transition"
                >
                    <div>
                        <div class="flex items-center justify-between mb-2">
                            <span class="text-xs font-bold uppercase tracking-wider text-primary">Role Template</span>
                            <span class="px-2 py-0.5 rounded-full text-[10px] font-extrabold bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-300">
                                {{ role.users_count }} staff {{ role.users_count === 1 ? 'member' : 'members' }}
                            </span>
                        </div>

                        <h3 class="text-lg font-black text-gray-900 dark:text-white mb-1">
                            {{ role.name }}
                        </h3>

                        <p class="text-xs text-gray-500 dark:text-gray-400 mb-3">
                            {{ defaultRoleTemplates[role.name]?.description || 'Custom modular staff role template.' }}
                        </p>

                        <div class="text-[11px] font-semibold text-gray-600 dark:text-gray-300 mb-2">
                            <strong class="text-primary">{{ role.permissions?.length || 0 }}</strong> capabilities granted
                        </div>

                        <!-- Pill previews of permissions -->
                        <div class="flex flex-wrap gap-1 max-h-24 overflow-y-auto mb-4">
                            <span
                                v-for="p in (role.permissions || []).slice(0, 8)"
                                :key="p.id"
                                class="text-[9px] font-bold px-2 py-0.5 rounded-md bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-300"
                            >
                                {{ p.name }}
                            </span>
                            <span
                                v-if="(role.permissions || []).length > 8"
                                class="text-[9px] font-bold px-1.5 py-0.5 rounded bg-primary/10 text-primary"
                            >
                                +{{ (role.permissions || []).length - 8 }} more
                            </span>
                        </div>
                    </div>

                    <div class="pt-3 border-t border-gray-100 dark:border-gray-700 flex items-center justify-end gap-2">
                        <button
                            type="button"
                            @click="openEditModal(role)"
                            class="px-3 py-1.5 rounded-lg bg-gray-100 dark:bg-gray-700 hover:bg-primary hover:text-white text-gray-700 dark:text-gray-300 text-xs font-bold transition cursor-pointer"
                        >
                            Edit Permissions
                        </button>
                        <button
                            v-if="!['Administrator', 'Super Administrator'].includes(role.name)"
                            type="button"
                            @click="deleteRole(role)"
                            class="px-2.5 py-1.5 rounded-lg bg-red-50 dark:bg-red-950/40 hover:bg-red-600 hover:text-white text-red-600 text-xs font-bold transition cursor-pointer"
                            title="Delete custom role"
                        >
                            Delete
                        </button>
                    </div>
                </div>
            </div>

        </div>

        <!-- ── Modal: Create / Edit Role with Internal Scroll ─────── -->
        <div v-if="showModal" class="fixed inset-0 z-50 flex items-center justify-center p-3 sm:p-4 bg-black/60 backdrop-blur-xs">
            <div class="bg-white dark:bg-gray-800 rounded-2xl max-w-3xl w-full max-h-[88vh] flex flex-col shadow-2xl border border-gray-200 dark:border-gray-700 overflow-hidden animate-fade-in">
                
                <!-- Fixed Header -->
                <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700 flex items-center justify-between shrink-0 bg-white dark:bg-gray-800">
                    <div>
                        <h3 class="text-lg font-bold text-gray-900 dark:text-white flex items-center gap-2">
                            <span>🛡</span> {{ isEditing ? 'Edit Role Capabilities' : 'Create Custom Role' }}
                        </h3>
                        <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">
                            Check the permissions this role should grant to assigned staff members.
                        </p>
                    </div>
                    <button
                        type="button"
                        @click="showModal = false"
                        class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-200 text-xl font-bold p-1 cursor-pointer"
                    >
                        ✕
                    </button>
                </div>

                <!-- Scrollable Body with Internal Scrollbar -->
                <form id="rolePermissionForm" @submit.prevent="submitForm" class="flex-1 overflow-y-auto p-6 space-y-5 overscroll-contain">
                    
                    <!-- Role Name -->
                    <div>
                        <label class="block text-xs font-bold uppercase tracking-wider text-gray-600 dark:text-gray-400 mb-1">
                            Role Name *
                        </label>
                        <input
                            v-model="roleName"
                            type="text"
                            placeholder="e.g. Warehouse & Dispatch Specialist"
                            required
                            class="w-full text-xs rounded-xl border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white py-2.5 px-3 focus:ring-primary shadow-xs font-semibold"
                        />
                    </div>

                    <!-- Quick Preset Templates -->
                    <div>
                        <label class="block text-[10px] font-bold uppercase tracking-wider text-gray-500 mb-1.5">
                            Load Capabilities from Template
                        </label>
                        <div class="flex flex-wrap gap-1.5">
                            <button
                                v-for="(tpl, name) in defaultRoleTemplates"
                                :key="name"
                                type="button"
                                @click="applyTemplate(name)"
                                class="px-2.5 py-1 rounded-lg text-[10px] font-bold border border-gray-300 dark:border-gray-600 hover:border-primary hover:text-primary transition cursor-pointer"
                            >
                                {{ name }}
                            </button>
                        </div>
                    </div>

                    <!-- Global Select Actions -->
                    <div class="flex items-center justify-between pt-2 pb-1 border-b border-gray-200 dark:border-gray-700">
                        <div class="text-xs font-bold text-gray-900 dark:text-white">
                            Selected Capabilities: <span class="text-primary font-black">{{ selectedPermissions.length }}</span> of {{ allPermissionNames.length }}
                        </div>
                        <div class="flex items-center gap-2">
                            <button
                                type="button"
                                @click="selectAllGlobal"
                                class="text-xs font-bold text-primary hover:underline cursor-pointer"
                            >
                                Select All
                            </button>
                            <span class="text-gray-300">|</span>
                            <button
                                type="button"
                                @click="clearAllGlobal"
                                class="text-xs font-semibold text-gray-500 hover:underline cursor-pointer"
                            >
                                Reset
                            </button>
                        </div>
                    </div>

                    <!-- Permission Modules Accordion / List -->
                    <div class="space-y-4">
                        <div
                            v-for="(perms, moduleName) in permissionModules"
                            :key="moduleName"
                            class="p-4 rounded-xl border border-gray-200 dark:border-gray-700 bg-gray-50/50 dark:bg-gray-700/30"
                        >
                            <div class="flex items-center justify-between mb-3 pb-2 border-b border-gray-200 dark:border-gray-600">
                                <span class="font-bold text-xs uppercase tracking-wider text-gray-800 dark:text-gray-200 flex items-center gap-2">
                                    📁 {{ moduleName }}
                                </span>
                                <button
                                    type="button"
                                    @click="toggleModuleAll(perms)"
                                    class="text-[11px] font-bold text-primary hover:underline cursor-pointer"
                                >
                                    {{ isModuleAllSelected(perms) ? 'Deselect Module' : 'Select All in Module' }}
                                </button>
                            </div>

                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-2.5">
                                <label
                                    v-for="(label, permName) in perms"
                                    :key="permName"
                                    class="flex items-start gap-2.5 p-2 rounded-lg hover:bg-white dark:hover:bg-gray-700 transition cursor-pointer border border-transparent hover:border-gray-200 dark:hover:border-gray-600"
                                >
                                    <input
                                        type="checkbox"
                                        :checked="selectedPermissions.includes(permName)"
                                        @change="togglePermission(permName)"
                                        class="rounded border-gray-300 text-primary focus:ring-primary h-4 w-4 mt-0.5"
                                    />
                                    <div class="min-w-0">
                                        <div class="text-xs font-bold text-gray-900 dark:text-white leading-tight">
                                            {{ permName }}
                                        </div>
                                        <div class="text-[10px] text-gray-500 dark:text-gray-400 mt-0.5">
                                            {{ label }}
                                        </div>
                                    </div>
                                </label>
                            </div>
                        </div>
                    </div>

                </form>

                <!-- Fixed Footer -->
                <div class="px-6 py-3.5 border-t border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-800/90 flex items-center justify-end gap-3 shrink-0">
                    <button
                        type="button"
                        @click="showModal = false"
                        class="px-4 py-2 text-xs font-semibold text-gray-600 dark:text-gray-300 hover:bg-gray-200 rounded-xl transition cursor-pointer"
                    >
                        Cancel
                    </button>
                    <button
                        type="submit"
                        form="rolePermissionForm"
                        :disabled="isSubmitting || !roleName.trim()"
                        class="px-5 py-2 text-xs font-bold text-white bg-primary hover:bg-primary-dark rounded-xl shadow-xs transition disabled:opacity-50 cursor-pointer"
                    >
                        {{ isEditing ? 'Save Changes' : 'Create Role' }}
                    </button>
                </div>

            </div>
        </div>

    </AdminLayout>
</template>

