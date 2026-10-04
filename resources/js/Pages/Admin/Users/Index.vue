<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import Pagination from '@/Components/Pagination.vue';
import { ref } from 'vue';
import { useToast } from 'vue-toastification';

const toast = useToast();
const props = defineProps({
    users: Object,
    roles: Array,
    statuses: Array,
});

const isEditing = ref(false);
const editId = ref(null);

const form = useForm({
    _method: 'POST',
    name: '',
    email: '',
    password: '',
    phone: '',
    address: '',
    role: props.roles.includes('staff') ? 'staff' : props.roles[0],
    status: props.statuses.includes('enable') ? 'enable' : props.statuses[0],
});

const save = () => {
    if (isEditing.value) {
        form.post(route('admin.users.update', editId.value), {
            onSuccess: () => {
                toast.success('User updated successfully!');
                resetForm();
            },
        });
    } else {
        form.post(route('admin.users.store'), {
            onSuccess: () => {
                toast.success('User created successfully!');
                resetForm();
            },
        });
    }
};

const editMode = (user) => {
    isEditing.value = true;
    editId.value = user.id;
    form._method = 'PUT';
    form.name = user.name;
    form.email = user.email;
    form.password = ''; // Don't pre-fill password
    form.phone = user.phone || '';
    form.address = user.address || '';
    form.role = user.role;
    form.status = user.status;
    form.clearErrors();
    window.scrollTo({ top: 0, behavior: 'smooth' });
};

const resetForm = () => {
    isEditing.value = false;
    editId.value = null;
    form.reset();
    form.clearErrors();
    form._method = 'POST';
    form.password = '';
};

const deleteUser = (id) => {
    if (!confirm('Are you sure you want to delete this user? This action cannot be undone.')) return;
    useForm({}).delete(route('admin.users.destroy', id), {
        onSuccess: () => toast.success('User deleted successfully.'),
        onError: (e) => toast.error(e.error || 'Failed to delete user.')
    });
};
</script>

<template>
    <Head title="Manage System Users" />
    <AdminLayout>
        <div class="max-w-7xl mx-auto px-4 py-8 text-black dark:text-white">
            <div class="mb-8">
                <h1 class="text-2xl font-bold">Manage System Users</h1>
                <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">Add or edit Staff, Administrators, and Super Administrators who can access the backend.</p>
            </div>

            <div class="flex flex-col lg:flex-row gap-8">
                <!-- ── Form Panel ────────────────────────────── -->
                <div class="w-full lg:w-1/3">
                    <div class="bg-white dark:bg-gray-800 p-6 rounded-xl shadow border dark:border-gray-700 sticky top-28">
                        <h2 class="text-lg font-bold mb-5">{{ isEditing ? '✏️ Edit User' : '➕ Add New User' }}</h2>
                        <form @submit.prevent="save" class="space-y-4">
                            <!-- Name -->
                            <div>
                                <label class="block text-sm font-semibold mb-1">Full Name *</label>
                                <input v-model="form.name" type="text" required
                                    class="w-full rounded-lg border border-gray-300 dark:border-gray-600 dark:bg-gray-700 px-3 py-2 focus:ring-2 focus:ring-blue-500"/>
                                <p v-if="form.errors.name" class="text-red-500 text-xs mt-1">{{ form.errors.name }}</p>
                            </div>

                            <!-- Email -->
                            <div>
                                <label class="block text-sm font-semibold mb-1">Email Address *</label>
                                <input v-model="form.email" type="email" required
                                    class="w-full rounded-lg border border-gray-300 dark:border-gray-600 dark:bg-gray-700 px-3 py-2 focus:ring-2 focus:ring-blue-500"/>
                                <p v-if="form.errors.email" class="text-red-500 text-xs mt-1">{{ form.errors.email }}</p>
                            </div>

                            <!-- Password -->
                            <div>
                                <label class="block text-sm font-semibold mb-1">
                                    Password <span v-if="!isEditing">*</span>
                                </label>
                                <input v-model="form.password" type="password" :required="!isEditing"
                                    placeholder="••••••••"
                                    class="w-full rounded-lg border border-gray-300 dark:border-gray-600 dark:bg-gray-700 px-3 py-2 focus:ring-2 focus:ring-blue-500"/>
                                <p v-if="isEditing" class="text-xs text-gray-400 mt-1">Leave blank to keep existing password.</p>
                                <p v-if="form.errors.password" class="text-red-500 text-xs mt-1">{{ form.errors.password }}</p>
                            </div>

                            <div class="grid grid-cols-2 gap-4">
                                <!-- Role -->
                                <div>
                                    <label class="block text-sm font-semibold mb-1">Role *</label>
                                    <select v-model="form.role" required class="w-full rounded-lg border border-gray-300 dark:border-gray-600 dark:bg-gray-700 px-3 py-2 focus:ring-2 focus:ring-blue-500 capitalize">
                                        <option v-for="role in roles" :key="role" :value="role">{{ role }}</option>
                                    </select>
                                    <p v-if="form.errors.role" class="text-red-500 text-xs mt-1">{{ form.errors.role }}</p>
                                </div>

                                <!-- Status -->
                                <div>
                                    <label class="block text-sm font-semibold mb-1">Status *</label>
                                    <select v-model="form.status" required class="w-full rounded-lg border border-gray-300 dark:border-gray-600 dark:bg-gray-700 px-3 py-2 focus:ring-2 focus:ring-blue-500 capitalize">
                                        <option v-for="status in statuses" :key="status" :value="status">{{ status }}</option>
                                    </select>
                                    <p v-if="form.errors.status" class="text-red-500 text-xs mt-1">{{ form.errors.status }}</p>
                                </div>
                            </div>
                            
                            <!-- Phone -->
                            <div>
                                <label class="block text-sm font-semibold mb-1">Phone Number</label>
                                <input v-model="form.phone" type="text"
                                    class="w-full rounded-lg border border-gray-300 dark:border-gray-600 dark:bg-gray-700 px-3 py-2 focus:ring-2 focus:ring-blue-500"/>
                                <p v-if="form.errors.phone" class="text-red-500 text-xs mt-1">{{ form.errors.phone }}</p>
                            </div>

                            <!-- Actions -->
                            <div class="flex gap-3 pt-4">
                                <button type="submit" :disabled="form.processing"
                                    class="bg-blue-600 hover:bg-blue-700 text-white font-bold px-5 py-2.5 rounded-lg transition disabled:opacity-60 flex-1 text-center">
                                    {{ isEditing ? 'Update User' : 'Create User' }}
                                </button>
                                <button v-if="isEditing" type="button" @click="resetForm"
                                    class="px-5 py-2.5 rounded-lg border dark:border-gray-600 hover:bg-gray-100 dark:hover:bg-gray-700 transition font-medium">
                                    Cancel
                                </button>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- ── Table ─────────────────────────────────── -->
                <div class="w-full lg:w-2/3">
                    <div class="bg-white dark:bg-gray-800 rounded-xl shadow border dark:border-gray-700 overflow-hidden">
                        <div class="overflow-x-auto">
                            <table class="w-full text-left whitespace-nowrap">
                                <thead class="bg-gray-50 dark:bg-gray-900 border-b dark:border-gray-700 text-xs uppercase text-gray-500 dark:text-gray-400">
                                    <tr>
                                        <th class="px-5 py-4 font-semibold">User</th>
                                        <th class="px-5 py-4 font-semibold">Role</th>
                                        <th class="px-5 py-4 font-semibold">Status</th>
                                        <th class="px-5 py-4 font-semibold">Joined Date</th>
                                        <th class="px-5 py-4 font-semibold text-right">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr v-for="user in users.data" :key="user.id"
                                        class="border-b dark:border-gray-700 hover:bg-gray-50 dark:hover:bg-gray-700/60 transition">
                                        <td class="px-5 py-4">
                                            <p class="font-bold text-gray-900 dark:text-white truncate max-w-[200px]">{{ user.name }}</p>
                                            <p class="text-sm text-gray-500 dark:text-gray-400 truncate max-w-[200px]">{{ user.email }}</p>
                                        </td>
                                        <td class="px-5 py-4">
                                            <span class="inline-flex items-center px-2.5 py-1 rounded-md text-xs font-semibold uppercase tracking-wider"
                                                :class="{
                                                    'bg-purple-100 text-purple-700 dark:bg-purple-900/30 dark:text-purple-400': user.role === 'super administrator',
                                                    'bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-400': user.role === 'administrator',
                                                    'bg-indigo-100 text-indigo-700 dark:bg-indigo-900/30 dark:text-indigo-400': user.role === 'staff',
                                                    'bg-gray-100 text-gray-700 dark:bg-gray-700 dark:text-gray-300': user.role === 'user'
                                                }">
                                                {{ user.role }}
                                            </span>
                                        </td>
                                        <td class="px-5 py-4">
                                            <span class="inline-flex items-center gap-1.5 text-sm font-medium"
                                                :class="user.status === 'enable' ? 'text-green-600 dark:text-green-400' : 'text-red-600 dark:text-red-400'">
                                                <span class="w-1.5 h-1.5 rounded-full" :class="user.status === 'enable' ? 'bg-green-500' : 'bg-red-500'"></span>
                                                {{ user.status === 'enable' ? 'Active' : 'Disabled' }}
                                            </span>
                                        </td>
                                        <td class="px-5 py-4 text-sm text-gray-500 dark:text-gray-400">
                                            {{ new Date(user.created_at).toLocaleDateString() }}
                                        </td>
                                        <td class="px-5 py-4 text-right">
                                            <div class="flex items-center justify-end gap-2">
                                                <button @click="editMode(user)"
                                                    class="p-2 text-blue-600 hover:bg-blue-50 dark:text-blue-400 dark:hover:bg-gray-700 rounded transition" title="Edit">
                                                    ✏️
                                                </button>
                                                <button @click="deleteUser(user.id)"
                                                    class="p-2 text-red-600 hover:bg-red-50 dark:text-red-400 dark:hover:bg-gray-700 rounded transition" title="Delete">
                                                    🗑️
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                    <tr v-if="!users.data.length">
                                        <td colspan="5" class="px-5 py-10 text-center text-gray-500 dark:text-gray-400">
                                            No users found.
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                        <div v-if="users.data.length" class="border-t dark:border-gray-700 p-4">
                            <Pagination :paginator="users" />
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AdminLayout>
</template>
