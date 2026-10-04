<script setup>
import { Head, useForm } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { ref, computed } from 'vue';
import { useToast } from 'vue-toastification';

const toast = useToast();
const props = defineProps({
    attributes: Array,
});

// ── Create Form ─────────────────────────────────────────────
const showCreate = ref(false);
const createForm = useForm({
    name: '',
    type: 'select',
    options: [],
});
const newOption = ref('');

const addOption = () => {
    const opt = newOption.value.trim();
    if (opt && !createForm.options.includes(opt)) {
        createForm.options.push(opt);
    }
    newOption.value = '';
};
const removeOption = (i) => createForm.options.splice(i, 1);

const submitCreate = () => {
    createForm.post(route('admin.attributes.store'), {
        onSuccess: () => {
            //toast.success('Attribute created!');
            createForm.reset();
            showCreate.value = false;
        },
    });
};

// ── Edit Form ───────────────────────────────────────────────
const editing = ref(null);
const editForm = useForm({ name: '', type: 'select', options: [] });
const newEditOption = ref('');

const startEdit = (attr) => {
    editing.value = attr.id;
    editForm.name = attr.name;
    editForm.type = attr.type;
    editForm.options = attr.options ? [...attr.options] : [];
};
const addEditOption = () => {
    const opt = newEditOption.value.trim();
    if (opt && !editForm.options.includes(opt)) editForm.options.push(opt);
    newEditOption.value = '';
};
const removeEditOption = (i) => editForm.options.splice(i, 1);

const submitEdit = (id) => {
    editForm.put(route('admin.attributes.update', id), {
        //onSuccess: () => { toast.success('Attribute updated!'); editing.value = null; },
    });
};

// ── Delete ──────────────────────────────────────────────────
const deleteAttr = (id) => {
    if (!confirm('Delete this attribute? It will be removed from all categories.')) return;
    useForm({}).delete(route('admin.attributes.destroy', id), {
        //onSuccess: () => toast.success('Attribute deleted.'),
    });
};

const typeLabel = { select: 'Select / Dropdown', color: 'Color Picker', range: 'Price Range', text: 'Text Input' };
const typeColors = { select: 'blue', color: 'pink', range: 'green', text: 'gray' };
</script>

<template>
    <Head title="Attributes" />
    <AdminLayout>
        <div class="max-w-5xl mx-auto">
            <!-- Header -->
            <div class="flex items-center justify-between mb-8">
                <div>
                    <h1 class="text-2xl font-bold text-gray-900 dark:text-white">Filterable Attributes</h1>
                    <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">Define attributes like Color, Size, Brand that can be assigned to categories as filters.</p>
                </div>
                <button @click="showCreate = !showCreate"
                    class="bg-blue-600 hover:bg-blue-700 text-white font-bold px-5 py-2.5 rounded-lg transition flex items-center gap-2">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                    New Attribute
                </button>
            </div>

            <!-- Create form -->
            <div v-if="showCreate" class="bg-white dark:bg-gray-800 rounded-xl border dark:border-gray-700 p-6 mb-8 shadow-sm">
                <h2 class="font-bold text-lg mb-4">Create New Attribute</h2>
                <form @submit.prevent="submitCreate" class="space-y-4">
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium mb-1">Attribute Name *</label>
                            <input v-model="createForm.name" type="text" required placeholder="e.g. Color, Size, Brand"
                                class="w-full rounded-lg border border-gray-300 dark:border-gray-600 dark:bg-gray-700 px-3 py-2 focus:ring-2 focus:ring-blue-500"/>
                            <p v-if="createForm.errors.name" class="text-red-500 text-xs mt-1">{{ createForm.errors.name }}</p>
                        </div>
                        <div>
                            <label class="block text-sm font-medium mb-1">Type *</label>
                            <select v-model="createForm.type"
                                class="w-full rounded-lg border border-gray-300 dark:border-gray-600 dark:bg-gray-700 px-3 py-2 focus:ring-2 focus:ring-blue-500">
                                <option value="select">Select / Dropdown</option>
                                <option value="color">Color Picker</option>
                                <option value="range">Price Range</option>
                                <option value="text">Text Input</option>
                            </select>
                        </div>
                    </div>

                    <!-- Options (for select / color types) -->
                    <div v-if="createForm.type === 'select' || createForm.type === 'color'">
                        <label class="block text-sm font-medium mb-2">Predefined Options</label>
                        <div class="flex flex-wrap gap-2 mb-2">
                            <span v-for="(opt, i) in createForm.options" :key="i"
                                class="inline-flex items-center gap-1.5 bg-blue-100 dark:bg-blue-900/30 text-blue-800 dark:text-blue-200 px-3 py-1 rounded-full text-sm">
                                <span v-if="createForm.type === 'color'" class="w-3.5 h-3.5 rounded-full border border-white/50 inline-block" :style="`background:${opt}`"></span>
                                {{ opt }}
                                <button type="button" @click="removeOption(i)" class="text-blue-500 hover:text-blue-800 font-bold leading-none">×</button>
                            </span>
                        </div>
                        <div class="flex gap-2">
                            <input v-model="newOption" @keydown.enter.prevent="addOption"
                                :type="createForm.type === 'color' ? 'color' : 'text'"
                                :placeholder="createForm.type === 'color' ? '' : 'Type option and press Add'"
                                class="flex-1 rounded-lg border border-gray-300 dark:border-gray-600 dark:bg-gray-700 px-3 py-2 text-sm"/>
                            <button type="button" @click="addOption"
                                class="px-4 py-2 bg-gray-100 dark:bg-gray-700 rounded-lg text-sm font-semibold hover:bg-gray-200 dark:hover:bg-gray-600 transition">
                                Add
                            </button>
                        </div>
                    </div>

                    <div class="flex gap-3 pt-2">
                        <button type="submit" :disabled="createForm.processing"
                            class="bg-blue-600 hover:bg-blue-700 text-white font-bold px-6 py-2 rounded-lg transition disabled:opacity-60">
                            Create Attribute
                        </button>
                        <button type="button" @click="showCreate = false"
                            class="px-6 py-2 rounded-lg border dark:border-gray-600 hover:bg-gray-100 dark:hover:bg-gray-700 transition">
                            Cancel
                        </button>
                    </div>
                </form>
            </div>

            <!-- Attributes List -->
            <div class="space-y-3">
                <div v-if="attributes.length === 0" class="text-center py-16 text-gray-400 dark:text-gray-500">
                    No attributes yet. Create your first one above.
                </div>

                <div v-for="attr in attributes" :key="attr.id"
                    class="bg-white dark:bg-gray-800 rounded-xl border dark:border-gray-700 overflow-hidden">

                    <!-- Row (not editing) -->
                    <div v-if="editing !== attr.id" class="flex items-center gap-4 px-5 py-4">
                        <div class="flex-1 min-w-0">
                            <div class="flex items-center gap-2 mb-1">
                                <p class="font-bold text-gray-900 dark:text-white">{{ attr.name }}</p>
                                <span class="text-xs px-2 py-0.5 rounded-full font-medium"
                                    :class="{
                                        'bg-blue-100 text-blue-800 dark:bg-blue-900/30 dark:text-blue-300': attr.type === 'select',
                                        'bg-pink-100 text-pink-800 dark:bg-pink-900/30 dark:text-pink-300': attr.type === 'color',
                                        'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-300': attr.type === 'range',
                                        'bg-gray-100 text-gray-700 dark:bg-gray-700 dark:text-gray-300': attr.type === 'text',
                                    }">
                                    {{ typeLabel[attr.type] }}
                                </span>
                                <span class="text-xs text-gray-400">· {{ attr.categories_count }} {{ attr.categories_count === 1 ? 'category' : 'categories' }}</span>
                            </div>
                            <div v-if="attr.options && attr.options.length" class="flex flex-wrap gap-1.5">
                                <span v-for="opt in attr.options.slice(0,8)" :key="opt"
                                    class="inline-flex items-center gap-1 bg-gray-100 dark:bg-gray-700 text-gray-600 dark:text-gray-300 px-2 py-0.5 rounded text-xs">
                                    <span v-if="attr.type === 'color'" class="w-2.5 h-2.5 rounded-full inline-block border" :style="`background:${opt}`"></span>
                                    {{ opt }}
                                </span>
                                <span v-if="attr.options.length > 8" class="text-xs text-gray-400 px-1 py-0.5">+{{ attr.options.length - 8 }} more</span>
                            </div>
                        </div>
                        <div class="flex items-center gap-2">
                            <button @click="startEdit(attr)"
                                class="text-sm font-semibold text-blue-600 dark:text-blue-400 hover:underline px-2 py-1">Edit</button>
                            <button @click="deleteAttr(attr.id)"
                                class="text-sm font-semibold text-red-500 hover:text-red-700 px-2 py-1">Delete</button>
                        </div>
                    </div>

                    <!-- Inline Edit Form -->
                    <div v-else class="p-5 space-y-4 bg-blue-50 dark:bg-blue-900/10">
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium mb-1">Name</label>
                                <input v-model="editForm.name" type="text" required
                                    class="w-full rounded-lg border border-gray-300 dark:border-gray-600 dark:bg-gray-700 px-3 py-2 focus:ring-2 focus:ring-blue-500"/>
                            </div>
                            <div>
                                <label class="block text-sm font-medium mb-1">Type</label>
                                <select v-model="editForm.type"
                                    class="w-full rounded-lg border border-gray-300 dark:border-gray-600 dark:bg-gray-700 px-3 py-2 focus:ring-2 focus:ring-blue-500">
                                    <option value="select">Select / Dropdown</option>
                                    <option value="color">Color Picker</option>
                                    <option value="range">Price Range</option>
                                    <option value="text">Text Input</option>
                                </select>
                            </div>
                        </div>
                        <div v-if="editForm.type === 'select' || editForm.type === 'color'">
                            <label class="block text-sm font-medium mb-2">Options</label>
                            <div class="flex flex-wrap gap-2 mb-2">
                                <span v-for="(opt, i) in editForm.options" :key="i"
                                    class="inline-flex items-center gap-1.5 bg-blue-100 dark:bg-blue-900/30 text-blue-800 dark:text-blue-200 px-3 py-1 rounded-full text-sm">
                                    <span v-if="editForm.type === 'color'" class="w-3.5 h-3.5 rounded-full border border-white/50 inline-block" :style="`background:${opt}`"></span>
                                    {{ opt }}
                                    <button type="button" @click="removeEditOption(i)" class="text-blue-500 hover:text-blue-800 font-bold">×</button>
                                </span>
                            </div>
                            <div class="flex gap-2">
                                <input v-model="newEditOption" @keydown.enter.prevent="addEditOption"
                                    :type="editForm.type === 'color' ? 'color' : 'text'"
                                    placeholder="Type and press Add"
                                    class="flex-1 rounded-lg border border-gray-300 dark:border-gray-600 dark:bg-gray-700 px-3 py-2 text-sm"/>
                                <button type="button" @click="addEditOption"
                                    class="px-4 py-2 bg-gray-100 dark:bg-gray-700 rounded-lg text-sm font-semibold hover:bg-gray-200 dark:hover:bg-gray-600 transition">Add</button>
                            </div>
                        </div>
                        <div class="flex gap-3">
                            <button @click="submitEdit(attr.id)" :disabled="editForm.processing"
                                class="bg-blue-600 hover:bg-blue-700 text-white font-bold px-5 py-2 rounded-lg transition disabled:opacity-60">Save</button>
                            <button @click="editing = null"
                                class="px-5 py-2 rounded-lg border dark:border-gray-600 hover:bg-gray-100 dark:hover:bg-gray-700 transition">Cancel</button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AdminLayout>
</template>
