<script setup>
import { ref } from 'vue';
import { Head, useForm } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import SettingsNavTabs from '@/Components/Admin/SettingsNavTabs.vue';
import { useToast } from 'vue-toastification';

const toast = useToast();

const props = defineProps({
    sliders: {
        type: Array,
        default: () => [],
    },
    settings: {
        type: Object,
        default: () => ({}),
    },
});

// ─── Individual Slider Form ──────────────────────────────────────────
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

const saveSlider = () => {
    if (isEditing.value) {
        form.post(route('admin.sliders.update', editId.value), {
            preserveScroll: true,
            onSuccess: () => {
                cancelForm();
            },
            onError: () => toast.error('Failed to update slider.'),
        });
    } else {
        form.post(route('admin.sliders.store'), {
            preserveScroll: true,
            onSuccess: () => {
                cancelForm();
            },
            onError: () => toast.error('Failed to create slider.'),
        });
    }
};

const deleteSlider = (id) => {
    if (!confirm('Are you sure you want to delete this slider?')) return;
    useForm({}).delete(route('admin.sliders.destroy', id), {
        preserveScroll: true,
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
    }).post(route('admin.sliders.update', slider.id), {
        preserveScroll: true,
    });
};

// ─── Homepage Content & Hero Settings Form ────────────────────────────
const settingsForm = useForm({
    home_slider_enabled:  props.settings?.home_slider_enabled !== '0',
    home_marquee_enabled: props.settings?.home_marquee_enabled === '1',
    home_marquee_text:    props.settings?.home_marquee_text || '',
    hero_enabled:         props.settings?.hero_enabled !== '0',
    hero_badge:           props.settings?.hero_badge || 'New Collection 2026',
    hero_title:           props.settings?.hero_title || 'Discover Digital',
    hero_title_highlight: props.settings?.hero_title_highlight || 'Excellence',
    hero_subtitle:        props.settings?.hero_subtitle || 'Shop premium products, authentic brands, and quality gear built for your lifestyle.',
    hero_cta_primary:     props.settings?.hero_cta_primary || 'Shop Now',
    hero_cta_secondary:   props.settings?.hero_cta_secondary || 'Explore Categories',
    hero_image:           null,
    feature_1_title:      props.settings?.feature_1_title || 'Secure Payments',
    feature_1_desc:       props.settings?.feature_1_desc || '100% secure checkout via Paystack & Flutterwave.',
    feature_2_title:      props.settings?.feature_2_title || 'Fast Checkout',
    feature_2_desc:       props.settings?.feature_2_desc || 'Seamless integration and instantaneous validation.',
    feature_3_title:      props.settings?.feature_3_title || 'Premium Support',
    feature_3_desc:       props.settings?.feature_3_desc || 'Priority technical response round the clock 24/7.',
});

const handleHeroImageUpload = (e) => {
    settingsForm.hero_image = e.target.files[0];
};

const saveSettings = () => {
    settingsForm.post(route('admin.sliders.settings.update'), {
        forceFormData: true,
        preserveScroll: true,
        onSuccess: () => {
            settingsForm.hero_image = null;
        },
        onError: () => toast.error('Failed to save settings, please check the form.'),
    });
};

const toggleSwitch = `relative w-11 h-6 bg-gray-200 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-primary rounded-full peer dark:bg-gray-700 peer-checked:after:translate-x-full rtl:peer-checked:after:-translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:start-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all dark:border-gray-600 peer-checked:bg-primary`;
</script>

<template>
    <Head title="Homepage & Sliders" />
    <AdminLayout>
        <div class="max-w-7xl mx-auto space-y-6">
            <!-- Top Navigation Tabs -->
            <SettingsNavTabs />

            <!-- Page Title Bar -->
            <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
                <div>
                    <h1 class="text-2xl font-bold text-gray-900 dark:text-white flex items-center gap-2.5">
                        <span class="p-2 rounded-xl bg-primary/10 text-primary">🖼️</span>
                        Homepage & Sliders
                    </h1>
                    <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">
                        Control hero image sliders, scrolling announcement marquee, static fallback hero, and trust badges.
                    </p>
                </div>
                <div class="flex items-center gap-3">
                    <button
                        @click="saveSettings"
                        :disabled="settingsForm.processing"
                        class="bg-emerald-600 hover:bg-emerald-700 text-white px-5 py-2.5 rounded-xl font-bold shadow-xs transition flex items-center gap-2 disabled:opacity-60 text-sm"
                    >
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                        {{ settingsForm.processing ? 'Saving...' : 'Save Settings' }}
                    </button>
                    <button
                        v-if="!showForm"
                        @click="openNewForm"
                        class="bg-primary hover:bg-primary-dark text-white px-5 py-2.5 rounded-xl font-bold shadow-xs hover:shadow transition-all flex items-center gap-2 text-sm"
                    >
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                        New Slider
                    </button>
                </div>
            </div>

            <!-- ─── 1. Top Controls: Dynamic Slider & Marquee Toggles ─────────────── -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                <!-- Slider Master Toggle Card -->
                <div class="bg-white dark:bg-gray-800 rounded-2xl p-5 border border-gray-100 dark:border-gray-700 shadow-xs flex items-center justify-between">
                    <div>
                        <div class="flex items-center gap-2">
                            <span class="text-base">🎞️</span>
                            <h3 class="text-sm font-bold text-gray-900 dark:text-white">Dynamic Hero Sliders</h3>
                        </div>
                        <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                            Cycle through full-width hero slides at the top of the homepage.
                        </p>
                    </div>
                    <label class="relative inline-flex items-center cursor-pointer ml-4 shrink-0">
                        <input type="checkbox" v-model="settingsForm.home_slider_enabled" class="sr-only peer" />
                        <div :class="toggleSwitch"></div>
                        <span class="ml-2.5 text-xs font-bold text-gray-700 dark:text-gray-300">
                            {{ settingsForm.home_slider_enabled ? 'Active' : 'Off' }}
                        </span>
                    </label>
                </div>

                <!-- Marquee Announcement Card -->
                <div class="bg-white dark:bg-gray-800 rounded-2xl p-5 border border-gray-100 dark:border-gray-700 shadow-xs space-y-3">
                    <div class="flex items-center justify-between">
                        <div>
                            <div class="flex items-center gap-2">
                                <span class="text-base">📢</span>
                                <h3 class="text-sm font-bold text-gray-900 dark:text-white">Announcement Marquee</h3>
                            </div>
                            <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">
                                Scrolling headline bar above product listings.
                            </p>
                        </div>
                        <label class="relative inline-flex items-center cursor-pointer ml-4 shrink-0">
                            <input type="checkbox" v-model="settingsForm.home_marquee_enabled" class="sr-only peer" />
                            <div :class="toggleSwitch"></div>
                            <span class="ml-2.5 text-xs font-bold text-gray-700 dark:text-gray-300">
                                {{ settingsForm.home_marquee_enabled ? 'Active' : 'Off' }}
                            </span>
                        </label>
                    </div>
                    <div v-if="settingsForm.home_marquee_enabled">
                        <input
                            type="text"
                            v-model="settingsForm.home_marquee_text"
                            placeholder="e.g. ⚡ Mega Sale! Free shipping across Nigeria on orders over ₦50,000"
                            class="w-full text-xs rounded-xl border border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-900 px-3 py-2 text-gray-900 dark:text-white"
                        />
                    </div>
                </div>
            </div>

            <!-- ─── 2. Create / Edit Slider Form ──────────────────────────────────── -->
            <div v-show="showForm" class="bg-white dark:bg-gray-800 rounded-2xl shadow-sm border border-gray-100 dark:border-gray-700 overflow-hidden transition-all">
                <div class="px-6 py-4 border-b border-gray-100 dark:border-gray-700 bg-gray-50 dark:bg-gray-900/50 flex justify-between items-center">
                    <h2 class="text-base font-bold text-gray-800 dark:text-white flex items-center gap-2">
                        <span>✨</span> {{ isEditing ? 'Edit Slider Slide' : 'Create New Slider Slide' }}
                    </h2>
                    <button @click="cancelForm" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-300">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                    </button>
                </div>
                
                <form @submit.prevent="saveSlider" class="p-6">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <!-- Image Upload -->
                        <div class="md:col-span-2">
                            <label class="block text-xs font-bold uppercase text-gray-700 dark:text-gray-300 mb-2">Slider Image * <span class="text-xs text-gray-400 font-normal">(Ideal ratio: 1920x800px or larger, max 4MB)</span></label>
                            <div class="flex items-center gap-6">
                                <div class="w-full max-w-lg shrink-0">
                                    <label class="flex flex-col items-center justify-center w-full h-44 border-2 border-gray-300 border-dashed rounded-2xl cursor-pointer bg-gray-50 dark:bg-gray-900 hover:bg-gray-100 dark:border-gray-700 dark:hover:bg-gray-800 overflow-hidden relative group transition">
                                        <div v-if="!imagePreview" class="flex flex-col items-center justify-center pt-5 pb-6 text-gray-500 dark:text-gray-400">
                                            <svg class="w-8 h-8 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>
                                            <p class="mb-1 text-xs font-semibold">Click to upload slide image</p>
                                            <p class="text-[11px] text-gray-400">PNG, JPG or WEBP (Max 4MB)</p>
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
                                <label class="block text-xs font-semibold text-gray-700 dark:text-gray-300 mb-1">Headline Text (Optional)</label>
                                <input v-model="form.title" type="text" placeholder="e.g. Your Trusted Partner for Premium Tech" class="w-full rounded-xl border border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-900 px-3.5 py-2.5 text-sm"/>
                                <p v-if="form.errors.title" class="mt-1 text-xs text-red-500">{{ form.errors.title }}</p>
                            </div>
                            <div>
                                <label class="block text-xs font-semibold text-gray-700 dark:text-gray-300 mb-1">Subheadline (Optional)</label>
                                <textarea v-model="form.subtitle" rows="2" placeholder="e.g. Explore cutting-edge digital accessories and quality gear..." class="w-full rounded-xl border border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-900 px-3.5 py-2.5 text-sm"></textarea>
                                <p v-if="form.errors.subtitle" class="mt-1 text-xs text-red-500">{{ form.errors.subtitle }}</p>
                            </div>
                        </div>
                        
                        <div class="space-y-4">
                            <div class="grid grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-xs font-semibold text-gray-700 dark:text-gray-300 mb-1">Button Text</label>
                                    <input v-model="form.button_text" type="text" placeholder="e.g. Shop Now" class="w-full rounded-xl border border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-900 px-3.5 py-2.5 text-sm"/>
                                </div>
                                <div>
                                    <label class="block text-xs font-semibold text-gray-700 dark:text-gray-300 mb-1">Button Link</label>
                                    <input v-model="form.button_link" type="text" placeholder="e.g. /shop" class="w-full rounded-xl border border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-900 px-3.5 py-2.5 text-sm font-mono"/>
                                </div>
                            </div>
                            <div class="flex items-center gap-6 mt-4">
                                <div class="flex-1">
                                    <label class="block text-xs font-semibold text-gray-700 dark:text-gray-300 mb-1">Sort Order (0 = first)</label>
                                    <input v-model.number="form.order" type="number" min="0" class="w-full rounded-xl border border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-900 px-3.5 py-2.5 text-sm font-bold text-center"/>
                                </div>
                                <div class="flex-1">
                                    <label class="block text-xs font-semibold text-gray-700 dark:text-gray-300 mb-1">Status</label>
                                    <button type="button" @click="form.is_active = !form.is_active" :class="form.is_active ? 'bg-primary' : 'bg-gray-300 dark:bg-gray-600'" class="relative inline-flex h-8 w-14 items-center rounded-full transition-colors">
                                        <span :class="form.is_active ? 'translate-x-7' : 'translate-x-1'" class="inline-block h-6 w-6 transform rounded-full bg-white transition duration-200 ease-in-out shadow-sm"></span>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="mt-6 flex justify-end gap-3 pt-5 border-t border-gray-100 dark:border-gray-800">
                        <button type="button" @click="cancelForm" class="px-5 py-2.5 text-xs font-bold text-gray-700 bg-white border border-gray-300 rounded-xl hover:bg-gray-50 transition dark:bg-gray-800 dark:text-gray-300 dark:border-gray-600">
                            Cancel
                        </button>
                        <button type="submit" :disabled="form.processing" class="px-6 py-2.5 text-xs font-bold text-white bg-primary hover:bg-primary-dark rounded-xl shadow-xs transition disabled:opacity-50">
                            {{ form.processing ? 'Saving...' : (isEditing ? 'Update Slider' : 'Create Slider') }}
                        </button>
                    </div>
                </form>
            </div>

            <!-- ─── 3. Existing Sliders Grid ──────────────────────────────────────── -->
            <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-xs border border-gray-100 dark:border-gray-700 p-6 space-y-4">
                <div class="flex items-center justify-between">
                    <div>
                        <h2 class="text-base font-bold text-gray-900 dark:text-white">Active Slides ({{ sliders.length }})</h2>
                        <p class="text-xs text-gray-500 dark:text-gray-400">Order, toggle visibility, and update your slider images.</p>
                    </div>
                </div>

                <div v-if="sliders.length > 0" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-5">
                    <div v-for="slider in sliders" :key="slider.id" class="rounded-2xl border border-gray-100 dark:border-gray-700/80 bg-gray-50/50 dark:bg-gray-900/30 overflow-hidden group shadow-2xs hover:shadow-md transition">
                        <div class="relative h-44 bg-gray-900 overflow-hidden">
                            <img :src="'/storage/' + slider.image_path" class="w-full h-full object-cover group-hover:scale-105 transition duration-500" alt="Slider image" />
                            <div class="absolute inset-0 bg-black/40 flex items-center justify-center opacity-0 group-hover:opacity-100 transition gap-2">
                                <button @click="editMode(slider)" class="p-2.5 bg-white text-gray-800 rounded-xl hover:bg-primary hover:text-white transition shadow-lg">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"/></svg>
                                </button>
                                <button @click="deleteSlider(slider.id)" class="p-2.5 bg-white text-red-600 rounded-xl hover:bg-red-600 hover:text-white transition shadow-lg">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                </button>
                            </div>
                            <span :class="slider.is_active ? 'bg-emerald-500' : 'bg-gray-500'" class="absolute top-3 left-3 text-white text-[10px] font-bold px-2 py-0.5 rounded-full shadow-sm">
                                {{ slider.is_active ? 'Active' : 'Hidden' }}
                            </span>
                            <span class="absolute top-3 right-3 bg-black/60 backdrop-blur-xs text-white text-[10px] font-bold px-2 py-0.5 rounded-full">
                                Order: {{ slider.order }}
                            </span>
                        </div>
                        <div class="p-4 space-y-2">
                            <h3 class="font-bold text-sm text-gray-900 dark:text-white truncate">{{ slider.title || 'Untitled Slide' }}</h3>
                            <p class="text-xs text-gray-500 dark:text-gray-400 line-clamp-1">{{ slider.subtitle || 'No subheadline' }}</p>
                            <div class="flex items-center justify-between pt-2 border-t border-gray-100 dark:border-gray-800 text-xs">
                                <span class="text-gray-400 truncate max-w-[150px] font-mono">{{ slider.button_link || 'No link' }}</span>
                                <button @click="toggleVisibility(slider)" class="text-xs font-semibold text-primary hover:underline">
                                    {{ slider.is_active ? 'Hide' : 'Show' }}
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <div v-else class="text-center py-12 border-2 border-dashed border-gray-200 dark:border-gray-700 rounded-2xl">
                    <p class="text-3xl mb-2">🖼️</p>
                    <p class="text-sm font-semibold text-gray-700 dark:text-gray-300">No sliders created yet</p>
                    <p class="text-xs text-gray-400 mt-1">Click "New Slider" above to add your first hero image.</p>
                </div>
            </div>

            <!-- ─── 4. Static Hero Section Fallback ───────────────────────────────── -->
            <section class="bg-white dark:bg-gray-800 rounded-2xl border border-gray-100 dark:border-gray-700 shadow-xs overflow-hidden">
                <div class="px-6 py-4 border-b border-gray-100 dark:border-gray-700 bg-gray-50/50 dark:bg-gray-800/60 flex items-center justify-between">
                    <div class="flex items-center gap-3">
                        <span class="w-10 h-10 rounded-xl bg-amber-500/10 text-amber-600 dark:text-amber-400 flex items-center justify-center shrink-0">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                        </span>
                        <div>
                            <h2 class="text-base font-bold text-gray-900 dark:text-white">Static Hero Section (Fallback)</h2>
                            <p class="text-xs text-gray-500 dark:text-gray-400">
                                Displays whenever Dynamic Hero Sliders are disabled or empty.
                            </p>
                        </div>
                    </div>
                    <label class="relative inline-flex items-center cursor-pointer">
                        <input type="checkbox" v-model="settingsForm.hero_enabled" class="sr-only peer" />
                        <div :class="toggleSwitch"></div>
                        <span class="ml-2.5 text-xs font-bold text-gray-700 dark:text-gray-300">
                            {{ settingsForm.hero_enabled ? 'Active' : 'Disabled' }}
                        </span>
                    </label>
                </div>

                <div v-if="settingsForm.hero_enabled" class="p-6 space-y-5">
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs font-semibold text-gray-700 dark:text-gray-300 mb-1">Badge Text (Top Pill)</label>
                            <input type="text" v-model="settingsForm.hero_badge" class="w-full rounded-xl border border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-900 px-3.5 py-2.5 text-sm" placeholder="New Collection 2026" />
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-gray-700 dark:text-gray-300 mb-1">Highlighted Word (Gradient Text)</label>
                            <input type="text" v-model="settingsForm.hero_title_highlight" class="w-full rounded-xl border border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-900 px-3.5 py-2.5 text-sm" placeholder="Excellence" />
                        </div>
                        <div class="sm:col-span-2">
                            <label class="block text-xs font-semibold text-gray-700 dark:text-gray-300 mb-1">Main Heading (Line before highlight)</label>
                            <input type="text" v-model="settingsForm.hero_title" class="w-full rounded-xl border border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-900 px-3.5 py-2.5 text-sm" placeholder="Discover Digital" />
                        </div>
                        <div class="sm:col-span-2">
                            <label class="block text-xs font-semibold text-gray-700 dark:text-gray-300 mb-1">Hero Subtitle / Description</label>
                            <textarea v-model="settingsForm.hero_subtitle" rows="2" class="w-full rounded-xl border border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-900 px-3.5 py-2.5 text-sm"></textarea>
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-gray-700 dark:text-gray-300 mb-1">Primary CTA Button</label>
                            <input type="text" v-model="settingsForm.hero_cta_primary" class="w-full rounded-xl border border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-900 px-3.5 py-2.5 text-sm" placeholder="Shop Now" />
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-gray-700 dark:text-gray-300 mb-1">Secondary CTA Button</label>
                            <input type="text" v-model="settingsForm.hero_cta_secondary" class="w-full rounded-xl border border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-900 px-3.5 py-2.5 text-sm" placeholder="Explore Categories" />
                        </div>
                    </div>

                    <!-- Static Hero Image Upload -->
                    <div class="pt-4 border-t border-gray-100 dark:border-gray-700">
                        <label class="block text-xs font-bold text-gray-700 dark:text-gray-300 mb-2">Static Hero Image (Max 4MB)</label>
                        <div class="flex flex-col sm:flex-row items-start gap-4">
                            <div class="w-full sm:w-48 h-32 rounded-xl overflow-hidden border dark:border-gray-700 bg-gray-100 dark:bg-gray-900 flex-shrink-0">
                                <img v-if="props.settings.hero_image" :src="'/storage/' + props.settings.hero_image" class="w-full h-full object-cover" alt="Hero image" />
                                <div v-else class="w-full h-full flex items-center justify-center text-gray-400 text-xs">No image set</div>
                            </div>
                            <div class="flex-1 w-full">
                                <div class="border-2 border-dashed border-gray-300 dark:border-gray-600 rounded-xl p-4 text-center bg-gray-50 dark:bg-gray-900/50 hover:bg-gray-100 dark:hover:bg-gray-800 transition">
                                    <svg class="w-7 h-7 text-gray-400 mx-auto mb-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"/></svg>
                                    <p class="text-xs text-gray-500 mb-2">Upload hero fallback image (PNG, JPG, max 4MB)</p>
                                    <input type="file" @change="handleHeroImageUpload" accept="image/*" class="text-xs text-gray-500 file:mr-2 file:py-1 file:px-3 file:rounded-md file:border-0 file:text-xs file:font-semibold file:bg-primary/10 file:text-primary cursor-pointer" />
                                </div>
                                <p v-if="settingsForm.hero_image" class="text-xs text-green-600 mt-2 font-semibold">✓ New hero image selected — click Save Settings to apply.</p>
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            <!-- ─── 5. Homepage Feature Trust Blocks (Bottom of Homepage) ─────────── -->
            <section class="bg-white dark:bg-gray-800 rounded-2xl border border-gray-100 dark:border-gray-700 shadow-xs overflow-hidden">
                <div class="px-6 py-4 border-b border-gray-100 dark:border-gray-700 bg-gray-50/50 dark:bg-gray-800/60 flex items-center justify-between">
                    <div class="flex items-center gap-3">
                        <span class="w-10 h-10 rounded-xl bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 flex items-center justify-center shrink-0">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/></svg>
                        </span>
                        <div>
                            <h2 class="text-base font-bold text-gray-900 dark:text-white">Feature Trust Blocks</h2>
                            <p class="text-xs text-gray-500 dark:text-gray-400">
                                3 trust value propositions displayed at the bottom of the homepage.
                            </p>
                        </div>
                    </div>
                </div>

                <div class="p-6">
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-5">
                        <div v-for="n in 3" :key="n" class="bg-gray-50 dark:bg-gray-900/40 rounded-xl p-4 border dark:border-gray-700/80 space-y-3">
                            <p class="text-xs font-bold text-gray-500 uppercase tracking-wider flex items-center gap-1.5">
                                <span>🛡️</span> Feature {{ n }}
                            </p>
                            <div>
                                <label class="block text-xs font-semibold text-gray-700 dark:text-gray-300 mb-1">Title</label>
                                <input type="text" v-model="settingsForm[`feature_${n}_title`]" class="w-full rounded-lg border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 px-3 py-2 text-xs" />
                            </div>
                            <div>
                                <label class="block text-xs font-semibold text-gray-700 dark:text-gray-300 mb-1">Description</label>
                                <textarea v-model="settingsForm[`feature_${n}_desc`]" rows="2" class="w-full rounded-lg border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 px-3 py-2 text-xs"></textarea>
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            <!-- Bottom Save Settings Button -->
            <div class="flex justify-end pt-2 pb-6">
                <button
                    @click="saveSettings"
                    :disabled="settingsForm.processing"
                    class="bg-emerald-600 hover:bg-emerald-700 text-white font-bold px-8 py-3 rounded-xl transition disabled:opacity-60 shadow-xs flex items-center gap-2"
                >
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                    {{ settingsForm.processing ? 'Saving...' : 'Save All Homepage Settings' }}
                </button>
            </div>
        </div>
    </AdminLayout>
</template>
