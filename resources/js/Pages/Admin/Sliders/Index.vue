<script setup>
import { ref } from 'vue';
import { Head, useForm } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { useToast } from 'vue-toastification';

const toast = useToast();

const props = defineProps({
    sliders: Array,
});

const form = useForm({
    _method: 'POST',
    title: '',
    subtitle: '',
    button_text: '',
    button_link: '',
    order: 0,
    is_active: true,
    image: null,
});

const isEditing = ref(false);
const editId = ref(null);
const showForm = ref(false);
const imagePreview = ref(null);

const openNewForm = () => {
    isEditing.value = false;
    editId.value = null;
    form.reset();
    form._method = 'POST';
    form.clearErrors();
    imagePreview.value = null;
    showForm.value = true;
};

const editMode = (slider) => {
    isEditing.value = true;
    editId.value = slider.id;
    form._method = 'PUT';
    form.title = slider.title || '';
    form.subtitle = slider.subtitle || '';
    form.button_text = slider.button_text || '';
    form.button_link = slider.button_link || '';
    form.order = slider.order || 0;
    form.is_active = !!slider.is_active;
    form.image = null;
    imagePreview.value = slider.image_path ? '/storage/' + slider.image_path : null;
    form.clearErrors();
    showForm.value = true;
};

const cancelForm = () => {
    showForm.value = false;
    form.reset();
    imagePreview.value = null;
    form.clearErrors();
};

const handleImageUpload = (e) => {
    form.image = e.target.files[0];
    if (form.image) {
        imagePreview.value = URL.createObjectURL(form.image);
    }
};

const save = () => {
    if (isEditing.value) {
        form.post(route('admin.sliders.update', editId.value), {
            preserveScroll: true,
            onSuccess: () => {
                cancelForm();
            },
        });
    } else {
        form.post(route('admin.sliders.store'), {
            preserveScroll: true,
            onSuccess: () => {
                cancelForm();
            },
        });
    }
};

const deleteSlider = (id) => {
    if (!confirm('Are you sure you want to delete this slider?')) return;
    useForm({}).delete(route('admin.sliders.destroy', id), {
        onSuccess: () => toast.success('Slider deleted.'),
    });
};

const toggleVisibility = (slider) => {
    useForm({
        _method: 'PUT',
        title: slider.title,
        subtitle: slider.subtitle,
        button_text: slider.button_text,
        button_link: slider.button_link,
        order: slider.order,
        is_active: !slider.is_active,
    }).post(route('admin.sliders.update', slider.id));
};
</script>

<template>
    <Head title="Manage Sliders" />
    <AdminLayout>
        <div class="max-w-7xl mx-auto space-y-6">
            
            <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
                <div>
                    <h1 class="text-2xl font-bold text-gray-900 dark:text-white">Homepage Sliders</h1>
                    <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">Add and manage image sliders for the homepage hero section.</p>
                </div>
                <button v-if="!showForm" @click="openNewForm" class="bg-blue-600 hover:bg-blue-700 text-white px-5 py-2.5 rounded-xl font-semibold shadow-sm hover:shadow transition-all flex items-center gap-2">
                    <svg class="w-5 h-5 pristine" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                    New Slider
                </button>
            </div>

            <!-- Form -->
            <div v-show="showForm" class="bg-white dark:bg-gray-800 rounded-2xl shadow-sm border border-gray-100 dark:border-gray-700 overflow-hidden transition-all">
                <div class="px-6 py-4 border-b border-gray-100 dark:border-gray-700 bg-gray-50 dark:bg-gray-900/50 flex justify-between items-center">
                    <h2 class="text-lg font-bold text-gray-800 dark:text-white">{{ isEditing ? 'Edit Slider' : 'Create New Slider' }}</h2>
                    <button @click="cancelForm" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-300">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                    </button>
                </div>
                
                <form @submit.prevent="save" class="p-6">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <!-- Image Upload -->
                        <div class="md:col-span-2">
                            <label class="block text-sm font-semibold mb-2">Slider Image (Required) <span class="text-xs text-gray-400 font-normal">Ideal size: 1920x800px or larger.</span></label>
                            <div class="flex items-center gap-6">
                                <div class="w-full max-w-lg shrink-0">
                                    <label class="flex flex-col items-center justify-center w-full h-40 border-2 border-gray-300 border-dashed rounded-xl cursor-pointer bg-gray-50 dark:bg-gray-800 hover:bg-gray-100 dark:border-gray-600 dark:hover:border-gray-500 dark:hover:bg-gray-700 overflow-hidden relative group transition">
                                        <div v-if="!imagePreview" class="flex flex-col items-center justify-center pt-5 pb-6 text-gray-500 dark:text-gray-400">
                                            <svg class="w-8 h-8 mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>
                                            <p class="mb-1 text-sm font-semibold">Click to upload image</p>
                                            <p class="text-xs">PNG, JPG or WEBP (Max 4MB)</p>
                                        </div>
                                        <img v-else :src="imagePreview" class="w-full h-full object-cover" />
                                        <input type="file" class="hidden" @change="handleImageUpload" accept="image/*" />
                                    </label>
                                    <p v-if="form.errors.image" class="mt-1 text-xs text-red-500">{{ form.errors.image }}</p>
                                </div>
                            </div>
                        </div>

                        <!-- Content Fields -->
                        <div class="space-y-4">
                            <div>
                                <label class="block text-sm font-semibold mb-1">Headline Text (Optional)</label>
                                <input v-model="form.title" type="text" placeholder="e.g. Your Trusted Partner for Solar Solutions" class="w-full rounded-lg border border-gray-300 dark:border-gray-600 dark:bg-gray-800 px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500"/>
                                <p v-if="form.errors.title" class="mt-1 text-xs text-red-500">{{ form.errors.title }}</p>
                            </div>
                            <div>
                                <label class="block text-sm font-semibold mb-1">Subheadline (Optional)</label>
                                <textarea v-model="form.subtitle" rows="2" placeholder="e.g. We sell all kinds of solar equipment..." class="w-full rounded-lg border border-gray-300 dark:border-gray-600 dark:bg-gray-800 px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500"></textarea>
                                <p v-if="form.errors.subtitle" class="mt-1 text-xs text-red-500">{{ form.errors.subtitle }}</p>
                            </div>
                        </div>
                        
                        <div class="space-y-4">
                            <div class="grid grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-sm font-semibold mb-1">Button Text</label>
                                    <input v-model="form.button_text" type="text" placeholder="e.g. Shop Now" class="w-full rounded-lg border border-gray-300 dark:border-gray-600 dark:bg-gray-800 px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500"/>
                                </div>
                                <div>
                                    <label class="block text-sm font-semibold mb-1">Button Link</label>
                                    <input v-model="form.button_link" type="text" placeholder="e.g. /shop" class="w-full rounded-lg border border-gray-300 dark:border-gray-600 dark:bg-gray-800 px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500"/>
                                </div>
                            </div>
                            <div class="flex items-center gap-6 mt-6">
                                <div class="flex-1">
                                    <label class="block text-sm font-semibold mb-1">Sort Order (0 = first)</label>
                                    <input v-model.number="form.order" type="number" min="0" class="w-full rounded-lg border border-gray-300 dark:border-gray-600 dark:bg-gray-800 px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500"/>
                                </div>
                                <div class="flex-1">
                                    <label class="block text-sm font-semibold mb-1">Status</label>
                                    <button type="button" @click="form.is_active = !form.is_active" :class="form.is_active ? 'bg-green-500' : 'bg-gray-300 dark:bg-gray-600'" class="relative inline-flex h-8 w-14 items-center rounded-full transition-colors focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2">
                                        <span :class="form.is_active ? 'translate-x-7' : 'translate-x-1'" class="inline-block h-6 w-6 transform rounded-full bg-white transition duration-200 ease-in-out shadow-sm"></span>
                                    </button>
                                </div>
                            </div>
                        </div>
                        
                    </div>

                    <div class="mt-8 flex justify-end gap-3 pt-6 border-t border-gray-100 dark:border-gray-800">
                        <button type="button" @click="cancelForm" class="px-5 py-2.5 text-sm font-semibold text-gray-700 bg-white border border-gray-300 rounded-xl hover:bg-gray-50 focus:ring-2 focus:ring-gray-200 transition dark:bg-gray-800 dark:text-gray-300 dark:border-gray-600 dark:hover:bg-gray-700">
                            Cancel
                        </button>
                        <button type="submit" :disabled="form.processing" class="px-5 py-2.5 text-sm font-bold text-white bg-blue-600 rounded-xl shadow-sm hover:bg-blue-700 focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 transition disabled:opacity-50 disabled:cursor-not-allowed">
                            {{ form.processing ? 'Saving...' : (isEditing ? 'Update Slider' : 'Create Slider') }}
                        </button>
                    </div>
                </form>
            </div>

            <!-- List -->
            <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-sm border border-gray-100 dark:border-gray-700 overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-sm whitespace-nowrap">
                        <thead class="bg-gray-50 dark:bg-gray-900/50 text-gray-600 dark:text-gray-400 font-semibold border-b border-gray-100 dark:border-gray-700">
                            <tr>
                                <th class="px-6 py-4 w-16">Order</th>
                                <th class="px-6 py-4">Image</th>
                                <th class="px-6 py-4">Text Overlay</th>
                                <th class="px-6 py-4 text-center">Status</th>
                                <th class="px-6 py-4 text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                            <tr v-for="slider in sliders" :key="slider.id" class="hover:bg-gray-50 dark:hover:bg-gray-700/60 transition">
                                <td class="px-6 py-4">
                                    <span class="inline-flex items-center justify-center w-7 h-7 rounded-full bg-blue-50 dark:bg-blue-900/20 text-blue-700 dark:text-blue-400 text-xs font-bold border border-blue-100 dark:border-blue-800">
                                        {{ slider.order }}
                                    </span>
                                </td>
                                <td class="px-6 py-4">
                                    <div class="w-32 h-16 rounded-lg overflow-hidden border dark:border-gray-600 shadow-sm relative group bg-gray-100">
                                        <img :src="'/storage/' + slider.image_path" class="w-full h-full object-cover" />
                                    </div>
                                </td>
                                <td class="px-6 py-4">
                                    <div v-if="slider.title || slider.subtitle" class="space-y-1">
                                        <p class="font-bold text-gray-900 dark:text-white truncate max-w-xs">{{ slider.title }}</p>
                                        <p class="text-xs text-gray-500 truncate max-w-xs">{{ slider.subtitle }}</p>
                                    </div>
                                    <span v-else class="text-gray-400 italic">No text</span>
                                </td>
                                <td class="px-6 py-4 text-center">
                                    <button @click="toggleVisibility(slider)" :title="slider.is_active ? 'Click to Disable' : 'Click to Enable'"
                                        :class="slider.is_active ? 'bg-green-100 text-green-700 border-green-200 dark:bg-green-900/30 dark:text-green-400 dark:border-green-800' : 'bg-gray-100 text-gray-600 border-gray-200 dark:bg-gray-800 dark:text-gray-400 dark:border-gray-700'"
                                        class="px-2.5 py-1 text-xs font-bold rounded-full border transition hover:opacity-80">
                                        {{ slider.is_active ? 'Active' : 'Disabled' }}
                                    </button>
                                </td>
                                <td class="px-6 py-4 text-right">
                                    <button @click="editMode(slider)" class="text-blue-600 hover:text-blue-800 dark:text-blue-400 dark:hover:text-blue-300 font-semibold px-2 py-1 rounded transition hover:bg-blue-50 dark:hover:bg-blue-900/20 mr-2">
                                        Edit
                                    </button>
                                    <button @click="deleteSlider(slider.id)" class="text-red-600 hover:text-red-800 dark:text-red-400 dark:hover:text-red-300 font-semibold px-2 py-1 rounded transition hover:bg-red-50 dark:hover:bg-red-900/20">
                                        Delete
                                    </button>
                                </td>
                            </tr>
                            <tr v-if="!sliders.length">
                                <td colspan="5" class="px-6 py-12 text-center text-gray-500 dark:text-gray-400">
                                    No sliders found. Click "New Slider" to create one.
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

        </div>
    </AdminLayout>
</template>
