<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import SettingsNavTabs from '@/Components/Admin/SettingsNavTabs.vue';
import { useToast } from 'vue-toastification';

const toast = useToast();

const props = defineProps({
    theme_settings: Object,
    company_name: String,
    company_tagline: String,
});

// Official Presets as shown in POS Super Admin
const presets = [
    {
        id: 'skynet',
        name: 'Skynet Digital Theme',
        primary: '#7B00FF',
        secondary: '#FBA43D',
        desc: 'Official Skynet Electric Purple & Gold brand theme',
    },
    {
        id: 'emerald',
        name: 'Emerald Green',
        primary: '#10b981',
        secondary: '#f59e0b',
        desc: 'Classic green with warm gold secondary accent',
    },
    {
        id: 'ocean',
        name: 'Ocean Blue',
        primary: '#3b82f6',
        secondary: '#f59e0b',
        desc: 'Professional corporate blue & amber theme',
    },
    {
        id: 'royal',
        name: 'Royal Indigo',
        primary: '#6366f1',
        secondary: '#ec4899',
        desc: 'Modern deep indigo & pink rose theme',
    },
    {
        id: 'violet',
        name: 'Deep Violet',
        primary: '#8b5cf6',
        secondary: '#f59e0b',
        desc: 'Vibrant violet & amber gold theme',
    },
    {
        id: 'teal',
        name: 'Cyber Teal',
        primary: '#14b8a6',
        secondary: '#f97316',
        desc: 'Sleek cyber teal & warm orange theme',
    },
    {
        id: 'cyan',
        name: 'Electric Cyan',
        primary: '#06b6d4',
        secondary: '#f59e0b',
        desc: 'Bright cyan & amber gold theme',
    },
    {
        id: 'amber',
        name: 'Amber Gold',
        primary: '#f59e0b',
        secondary: '#6366f1',
        desc: 'Warm gold & royal indigo secondary theme',
    },
    {
        id: 'rose',
        name: 'Rose Crimson',
        primary: '#f43f5e',
        secondary: '#3b82f6',
        desc: 'Bold crimson & ocean blue secondary theme',
    },
];

// Dark mode legibility options
const darkModeOptions = [
    {
        id: 'white',
        name: 'Crisp Pure White',
        color: '#FFFFFF',
        desc: 'Ultra-high contrast (#FFFFFF) for the highest readability of totals, headings, and prices on dark backgrounds.',
    },
    {
        id: 'tint',
        name: 'Luminous Brand Tint',
        color: '#C084FC',
        desc: 'Bright neon/pastel tint (#C084FC) tuned for vibrant brand presence in Dark Mode.',
    },
    {
        id: 'custom_dark',
        name: 'Custom Dark Color',
        color: props.theme_settings?.theme_dark_mode_text_color || '#FFFFFF',
        desc: 'Configure a bespoke text & total highlight color tailored for dark backgrounds.',
    },
];

const form = useForm({
    theme_preset:               props.theme_settings?.theme_preset || 'skynet',
    theme_primary_color:        props.theme_settings?.theme_primary_color || '#7B00FF',
    theme_secondary_color:      props.theme_settings?.theme_secondary_color || '#FBA43D',
    theme_dark_mode_preset:     props.theme_settings?.theme_dark_mode_preset || 'white',
    theme_dark_mode_text_color: props.theme_settings?.theme_dark_mode_text_color || '#FFFFFF',
    theme_dark_mode_tint_color: props.theme_settings?.theme_dark_mode_tint_color || '#C084FC',
});

// Interactive preview mode state (light or dark preview box)
const previewMode = ref('light');

const selectPreset = (preset) => {
    form.theme_preset = preset.id;
    form.theme_primary_color = preset.primary;
    form.theme_secondary_color = preset.secondary;
};

const selectCustomPreset = () => {
    form.theme_preset = 'custom';
};

const selectDarkModePreset = (opt) => {
    form.theme_dark_mode_preset = opt.id;
    if (opt.id === 'white') {
        form.theme_dark_mode_text_color = '#FFFFFF';
    } else if (opt.id === 'tint') {
        form.theme_dark_mode_text_color = form.theme_dark_mode_tint_color || '#C084FC';
    }
};

// Compute active dark mode highlight color
const activeDarkModeTextColor = computed(() => {
    if (form.theme_dark_mode_preset === 'white') return '#FFFFFF';
    if (form.theme_dark_mode_preset === 'tint') return form.theme_dark_mode_tint_color || '#C084FC';
    return form.theme_dark_mode_text_color || '#FFFFFF';
});

// Dynamic live CSS variables update on page for instant gratification
const applyLiveStyles = () => {
    if (typeof document !== 'undefined') {
        const root = document.documentElement;
        root.style.setProperty('--color-primary', form.theme_primary_color);
        root.style.setProperty('--color-primary-dark', form.theme_primary_color);
        root.style.setProperty('--color-secondary-500', form.theme_secondary_color);
        root.style.setProperty('--color-secondary-600', form.theme_secondary_color);
        root.style.setProperty('--theme-primary', form.theme_primary_color);
        root.style.setProperty('--theme-secondary', form.theme_secondary_color);
        root.style.setProperty('--theme-dark-heading', activeDarkModeTextColor.value);
    }
};

watch(
    () => [form.theme_primary_color, form.theme_secondary_color, activeDarkModeTextColor.value],
    () => {
        applyLiveStyles();
    },
    { immediate: true }
);

const saveTheme = () => {
    form.put(route('admin.themes.update'), {
        preserveScroll: true,
        onSuccess: () => {
            applyLiveStyles();
        },
        onError: () => {
            toast.error('Failed to update theme. Please verify hex color codes.');
        },
    });
};
</script>

<template>
    <Head title="Branding & Color Themes" />
    <AdminLayout>
        <div class="max-w-6xl mx-auto space-y-6">

            <!-- Top Navigation Tabs -->
            <SettingsNavTabs />

            <!-- Page Header with Save Button -->
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                <div>
                    <h1 class="text-2xl font-bold text-gray-900 dark:text-white">
                        Color Themes & Dual-Color Palette
                    </h1>
                    <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">
                        Control how primary & secondary brand accents and dark mode legibility display across the Storefront and Admin.
                    </p>
                </div>
                <button
                    type="button"
                    @click="saveTheme"
                    :disabled="form.processing"
                    class="text-white hover:opacity-90 font-bold px-6 py-2.5 rounded-lg text-sm transition flex items-center gap-2 shadow-xs disabled:opacity-60 shrink-0"
                    :style="{ backgroundColor: form.theme_primary_color }"
                >
                    <svg v-if="form.processing" class="animate-spin -ml-1 mr-2 h-4 w-4 text-white" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path></svg>
                    <svg v-else class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                    {{ form.processing ? 'Saving Changes...' : 'Save Theme Changes' }}
                </button>
            </div>

            <!-- ─── SECTION 1: DUAL-COLOR PALETTE CONFIGURATION ────────────── -->
            <div class="bg-white dark:bg-gray-800 rounded-2xl border border-gray-200 dark:border-gray-700 p-6 shadow-xs space-y-6">
                <div>
                    <div class="flex items-center gap-2">
                        <span class="w-2.5 h-2.5 rounded-full" :style="{ backgroundColor: form.theme_primary_color }"></span>
                        <h2 class="text-sm font-bold tracking-wider uppercase text-gray-900 dark:text-white">
                            DUAL-COLOR PALETTE CONFIGURATION
                        </h2>
                    </div>
                    <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                        Select an official preset theme or configure custom <strong>Primary</strong> and <strong>Secondary</strong> brand colors for Storefront, Admin, Cart, Checkout, and Badges.
                    </p>
                </div>

                <!-- Palette Grid -->
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">

                    <!-- Official Preset Cards -->
                    <div
                        v-for="preset in presets"
                        :key="preset.id"
                        @click="selectPreset(preset)"
                        :class="[
                            'relative rounded-xl border p-4 cursor-pointer transition-all duration-150 flex flex-col justify-between select-none',
                            form.theme_preset === preset.id
                                ? 'bg-gray-50/50 dark:bg-gray-900/40 shadow-xs'
                                : 'border-gray-200 dark:border-gray-700 hover:border-gray-300 dark:hover:border-gray-600 bg-white dark:bg-gray-800'
                        ]"
                        :style="form.theme_preset === preset.id ? { borderColor: form.theme_primary_color, boxShadow: `0 0 0 2px color-mix(in srgb, ${form.theme_primary_color} 25%, transparent)` } : {}"
                    >
                        <!-- Active Check Badge -->
                        <div
                            v-if="form.theme_preset === preset.id"
                            class="absolute top-3.5 right-3.5 w-5 h-5 rounded-full text-white flex items-center justify-center text-[10px] font-bold shadow-xs"
                            :style="{ backgroundColor: form.theme_primary_color }"
                        >
                            ✓
                        </div>

                        <div>
                            <!-- Header with Dual Chips -->
                            <div class="flex items-center gap-2.5">
                                <div class="flex items-center">
                                    <div
                                        class="w-6 h-6 rounded-md flex items-center justify-center text-[11px] font-extrabold text-white shadow-xs z-10"
                                        :style="{ backgroundColor: preset.primary }"
                                    >
                                        P
                                    </div>
                                    <div
                                        class="w-6 h-6 rounded-md flex items-center justify-center text-[11px] font-extrabold text-white shadow-xs -ml-1.5"
                                        :style="{ backgroundColor: preset.secondary }"
                                    >
                                        S
                                    </div>
                                </div>
                                <div class="pr-6">
                                    <h3 class="text-xs font-bold text-gray-900 dark:text-white leading-tight">
                                        {{ preset.name }}
                                    </h3>
                                    <p class="text-[10px] text-gray-400 font-mono mt-0.5">
                                        {{ preset.primary }} • {{ preset.secondary }}
                                    </p>
                                </div>
                            </div>

                            <!-- Description -->
                            <p class="text-[11px] text-gray-500 dark:text-gray-400 mt-2.5 leading-relaxed">
                                {{ preset.desc }}
                            </p>
                        </div>

                        <!-- Bottom Dual-Color Bar -->
                        <div class="mt-4 flex h-2 rounded-full overflow-hidden w-full bg-gray-100 dark:bg-gray-700">
                            <div class="w-1/2 h-full" :style="{ backgroundColor: preset.primary }"></div>
                            <div class="w-1/2 h-full" :style="{ backgroundColor: preset.secondary }"></div>
                        </div>
                    </div>

                    <!-- Custom Theme Card -->
                    <div
                        @click="selectCustomPreset"
                        :class="[
                            'relative rounded-xl border p-4 cursor-pointer transition-all duration-150 flex flex-col justify-between sm:col-span-2 lg:col-span-1 select-none',
                            form.theme_preset === 'custom'
                                ? 'bg-gray-50/50 dark:bg-gray-900/40 shadow-xs'
                                : 'border-gray-200 dark:border-gray-700 hover:border-gray-300 dark:hover:border-gray-600 bg-white dark:bg-gray-800'
                        ]"
                        :style="form.theme_preset === 'custom' ? { borderColor: form.theme_primary_color, boxShadow: `0 0 0 2px color-mix(in srgb, ${form.theme_primary_color} 25%, transparent)` } : {}"
                    >
                        <!-- Active Check Badge -->
                        <div
                            v-if="form.theme_preset === 'custom'"
                            class="absolute top-3.5 right-3.5 w-5 h-5 rounded-full text-white flex items-center justify-center text-[10px] font-bold shadow-xs"
                            :style="{ backgroundColor: form.theme_primary_color }"
                        >
                            ✓
                        </div>

                        <div class="space-y-3">
                            <div class="flex items-center gap-2.5">
                                <div class="flex items-center">
                                    <div
                                        class="w-6 h-6 rounded-md flex items-center justify-center text-[11px] font-extrabold text-white shadow-xs z-10"
                                        :style="{ backgroundColor: form.theme_primary_color }"
                                    >
                                        P
                                    </div>
                                    <div
                                        class="w-6 h-6 rounded-md flex items-center justify-center text-[11px] font-extrabold text-white shadow-xs -ml-1.5"
                                        :style="{ backgroundColor: form.theme_secondary_color }"
                                    >
                                        S
                                    </div>
                                </div>
                                <div class="pr-6">
                                    <h3 class="text-xs font-bold text-gray-900 dark:text-white leading-tight">
                                        Custom Theme
                                    </h3>
                                    <p class="text-[10px] text-gray-400 font-mono mt-0.5">
                                        {{ form.theme_primary_color }} • {{ form.theme_secondary_color }}
                                    </p>
                                </div>
                            </div>

                            <!-- Custom Color Inputs -->
                            <div class="space-y-2 pt-1" @click.stop>
                                <div>
                                    <span class="block text-[10px] font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400 mb-1">
                                        PRIMARY ACCENT:
                                    </span>
                                    <div class="flex items-center gap-2">
                                        <div class="relative w-7 h-7 rounded-md border border-gray-300 dark:border-gray-600 overflow-hidden shrink-0 shadow-xs">
                                            <input
                                                type="color"
                                                v-model="form.theme_primary_color"
                                                @input="form.theme_preset = 'custom'"
                                                class="absolute -inset-2 w-12 h-12 cursor-pointer border-0"
                                            />
                                        </div>
                                        <input
                                            type="text"
                                            v-model="form.theme_primary_color"
                                            @input="form.theme_preset = 'custom'"
                                            maxlength="7"
                                            placeholder="#7B00FF"
                                            class="w-full text-xs font-mono uppercase px-2.5 py-1 rounded-md border border-gray-300 dark:border-gray-600 dark:bg-gray-700 text-gray-900 dark:text-white"
                                        />
                                    </div>
                                </div>

                                <div>
                                    <span class="block text-[10px] font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400 mb-1">
                                        SECONDARY ACCENT:
                                    </span>
                                    <div class="flex items-center gap-2">
                                        <div class="relative w-7 h-7 rounded-md border border-gray-300 dark:border-gray-600 overflow-hidden shrink-0 shadow-xs">
                                            <input
                                                type="color"
                                                v-model="form.theme_secondary_color"
                                                @input="form.theme_preset = 'custom'"
                                                class="absolute -inset-2 w-12 h-12 cursor-pointer border-0"
                                            />
                                        </div>
                                        <input
                                            type="text"
                                            v-model="form.theme_secondary_color"
                                            @input="form.theme_preset = 'custom'"
                                            maxlength="7"
                                            placeholder="#FBA43D"
                                            class="w-full text-xs font-mono uppercase px-2.5 py-1 rounded-md border border-gray-300 dark:border-gray-600 dark:bg-gray-700 text-gray-900 dark:text-white"
                                        />
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Bottom Custom Dual-Color Bar -->
                        <div class="mt-3 flex h-2 rounded-full overflow-hidden w-full bg-gray-100 dark:bg-gray-700">
                            <div class="w-1/2 h-full" :style="{ backgroundColor: form.theme_primary_color }"></div>
                            <div class="w-1/2 h-full" :style="{ backgroundColor: form.theme_secondary_color }"></div>
                        </div>
                    </div>

                </div>
            </div>

            <!-- ─── SECTION 2: DARK MODE THEME & TEXT LEGIBILITY ─────────── -->
            <div class="bg-white dark:bg-gray-800 rounded-2xl border border-gray-200 dark:border-gray-700 p-6 shadow-xs space-y-6">
                <div>
                    <div class="flex items-center gap-2">
                        <span class="w-2.5 h-2.5 rounded-full" :style="{ backgroundColor: form.theme_primary_color }"></span>
                        <h2 class="text-sm font-bold tracking-wider uppercase text-gray-900 dark:text-white">
                            DARK MODE THEME & TEXT LEGIBILITY
                        </h2>
                    </div>
                    <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                        Configure how brand names, total headers, price labels, and accents display when Dark Mode is active for high readability.
                    </p>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">

                    <!-- Crisp Pure White -->
                    <div
                        @click="selectDarkModePreset(darkModeOptions[0])"
                        :class="[
                            'relative rounded-xl border p-4 cursor-pointer transition-all duration-150 flex flex-col justify-between select-none',
                            form.theme_dark_mode_preset === 'white'
                                ? 'bg-gray-50/50 dark:bg-gray-900/40 shadow-xs'
                                : 'border-gray-200 dark:border-gray-700 hover:border-gray-300 dark:hover:border-gray-600 bg-white dark:bg-gray-800'
                        ]"
                        :style="form.theme_dark_mode_preset === 'white' ? { borderColor: form.theme_primary_color, boxShadow: `0 0 0 2px color-mix(in srgb, ${form.theme_primary_color} 25%, transparent)` } : {}"
                    >
                        <div
                            v-if="form.theme_dark_mode_preset === 'white'"
                            class="absolute top-3.5 right-3.5 w-5 h-5 rounded-full text-white flex items-center justify-center text-[10px] font-bold shadow-xs"
                            :style="{ backgroundColor: form.theme_primary_color }"
                        >
                            ✓
                        </div>

                        <div>
                            <div class="flex items-center gap-2.5">
                                <div class="w-6 h-6 rounded-full bg-white border border-gray-300 dark:border-gray-600 shadow-xs"></div>
                                <h3 class="text-xs font-bold text-gray-900 dark:text-white">
                                    Crisp Pure White
                                </h3>
                            </div>
                            <p class="text-[11px] text-gray-500 dark:text-gray-400 mt-2 leading-relaxed">
                                Ultra-high contrast (#FFFFFF) for the highest readability of totals, headings, and prices on dark backgrounds.
                            </p>
                        </div>

                        <!-- Dark Preview Badge -->
                        <div class="mt-4 bg-[#0B0F19] rounded-lg px-3.5 py-2.5 flex items-center justify-between text-xs shadow-inner">
                            <span class="text-gray-400 text-[11px]">Sample Total:</span>
                            <span class="font-bold font-mono text-white">₦24,500.00</span>
                        </div>
                    </div>

                    <!-- Luminous Brand Tint -->
                    <div
                        @click="selectDarkModePreset(darkModeOptions[1])"
                        :class="[
                            'relative rounded-xl border p-4 cursor-pointer transition-all duration-150 flex flex-col justify-between select-none',
                            form.theme_dark_mode_preset === 'tint'
                                ? 'bg-gray-50/50 dark:bg-gray-900/40 shadow-xs'
                                : 'border-gray-200 dark:border-gray-700 hover:border-gray-300 dark:hover:border-gray-600 bg-white dark:bg-gray-800'
                        ]"
                        :style="form.theme_dark_mode_preset === 'tint' ? { borderColor: form.theme_primary_color, boxShadow: `0 0 0 2px color-mix(in srgb, ${form.theme_primary_color} 25%, transparent)` } : {}"
                    >
                        <div
                            v-if="form.theme_dark_mode_preset === 'tint'"
                            class="absolute top-3.5 right-3.5 w-5 h-5 rounded-full text-white flex items-center justify-center text-[10px] font-bold shadow-xs"
                            :style="{ backgroundColor: form.theme_primary_color }"
                        >
                            ✓
                        </div>

                        <div>
                            <div class="flex items-center gap-2.5">
                                <div class="w-6 h-6 rounded-full bg-[#C084FC] shadow-xs"></div>
                                <h3 class="text-xs font-bold text-gray-900 dark:text-white">
                                    Luminous Brand Tint
                                </h3>
                            </div>
                            <p class="text-[11px] text-gray-500 dark:text-gray-400 mt-2 leading-relaxed">
                                Bright neon/pastel tint (#C084FC) tuned for vibrant brand presence in Dark Mode.
                            </p>
                        </div>

                        <!-- Dark Preview Badge -->
                        <div class="mt-4 bg-[#0B0F19] rounded-lg px-3.5 py-2.5 flex items-center justify-between text-xs shadow-inner">
                            <span class="text-gray-400 text-[11px]">Sample Total:</span>
                            <span class="font-bold font-mono text-[#C084FC]">₦24,500.00</span>
                        </div>
                    </div>

                    <!-- Custom Dark Color -->
                    <div
                        @click="selectDarkModePreset(darkModeOptions[2])"
                        :class="[
                            'relative rounded-xl border p-4 cursor-pointer transition-all duration-150 flex flex-col justify-between select-none',
                            form.theme_dark_mode_preset === 'custom_dark'
                                ? 'bg-gray-50/50 dark:bg-gray-900/40 shadow-xs'
                                : 'border-gray-200 dark:border-gray-700 hover:border-gray-300 dark:hover:border-gray-600 bg-white dark:bg-gray-800'
                        ]"
                        :style="form.theme_dark_mode_preset === 'custom_dark' ? { borderColor: form.theme_primary_color, boxShadow: `0 0 0 2px color-mix(in srgb, ${form.theme_primary_color} 25%, transparent)` } : {}"
                    >
                        <div
                            v-if="form.theme_dark_mode_preset === 'custom_dark'"
                            class="absolute top-3.5 right-3.5 w-5 h-5 rounded-full text-white flex items-center justify-center text-[10px] font-bold shadow-xs"
                            :style="{ backgroundColor: form.theme_primary_color }"
                        >
                            ✓
                        </div>

                        <div class="space-y-2">
                            <div class="flex items-center gap-2.5">
                                <div class="w-6 h-6 rounded-full flex items-center justify-center text-xs">
                                    🎨
                                </div>
                                <h3 class="text-xs font-bold text-gray-900 dark:text-white">
                                    Custom Dark Color
                                </h3>
                            </div>
                            <div @click.stop class="pt-1">
                                <span class="block text-[10px] font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400 mb-1">
                                    DARK MODE TEXT COLOR:
                                </span>
                                <div class="flex items-center gap-2">
                                    <div class="relative w-7 h-7 rounded-md border border-gray-300 dark:border-gray-600 overflow-hidden shrink-0 shadow-xs">
                                        <input
                                            type="color"
                                            v-model="form.theme_dark_mode_text_color"
                                            @input="form.theme_dark_mode_preset = 'custom_dark'"
                                            class="absolute -inset-2 w-12 h-12 cursor-pointer border-0"
                                        />
                                    </div>
                                    <input
                                        type="text"
                                        v-model="form.theme_dark_mode_text_color"
                                        @input="form.theme_dark_mode_preset = 'custom_dark'"
                                        maxlength="7"
                                        placeholder="#FFFFFF"
                                        class="w-full text-xs font-mono uppercase px-2.5 py-1 rounded-md border border-gray-300 dark:border-gray-600 dark:bg-gray-700 text-gray-900 dark:text-white"
                                    />
                                </div>
                            </div>
                        </div>

                        <!-- Dark Preview Badge -->
                        <div class="mt-4 bg-[#0B0F19] rounded-lg px-3.5 py-2.5 flex items-center justify-between text-xs shadow-inner">
                            <span class="text-gray-400 text-[11px]">Sample Total:</span>
                            <span
                                class="font-bold font-mono"
                                :style="{ color: form.theme_dark_mode_text_color || '#FFFFFF' }"
                            >
                                ₦24,500.00
                            </span>
                        </div>
                    </div>

                </div>
            </div>

            <!-- ─── SECTION 3: LIVE UI & CHART ELEMENT PREVIEW ────────────── -->
            <div class="bg-white dark:bg-gray-800 rounded-2xl border border-gray-200 dark:border-gray-700 p-6 shadow-xs space-y-4">
                <div class="flex items-center justify-between">
                    <div>
                        <h2 class="text-xs font-bold tracking-wider uppercase text-gray-900 dark:text-white">
                            LIVE UI & CHART ELEMENT PREVIEW
                        </h2>
                        <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">
                            Real-time rendering of your active palette across buttons, badges, and brand highlights.
                        </p>
                    </div>
                    <!-- Light / Dark Preview Switch -->
                    <div class="flex items-center bg-gray-100 dark:bg-gray-700 p-1 rounded-lg text-xs font-medium">
                        <button
                            type="button"
                            @click="previewMode = 'light'"
                            :class="[
                                'px-2.5 py-1 rounded-md transition',
                                previewMode === 'light' ? 'bg-white text-gray-900 shadow-xs font-bold' : 'text-gray-500 hover:text-gray-900 dark:text-gray-400 dark:hover:text-white'
                            ]"
                        >
                            ☀️ Light Box
                        </button>
                        <button
                            type="button"
                            @click="previewMode = 'dark'"
                            :class="[
                                'px-2.5 py-1 rounded-md transition',
                                previewMode === 'dark' ? 'bg-gray-900 text-white shadow-xs font-bold' : 'text-gray-500 hover:text-gray-900 dark:text-gray-400 dark:hover:text-white'
                            ]"
                        >
                            🌙 Dark Box
                        </button>
                    </div>
                </div>

                <!-- Preview Stage -->
                <div
                    :class="[
                        'rounded-xl border p-6 transition-all duration-200 space-y-6',
                        previewMode === 'light'
                            ? 'bg-slate-50 border-gray-200 text-gray-900'
                            : 'bg-[#0B0F19] border-gray-800 text-white shadow-inner'
                    ]"
                >
                    <!-- Brand Top Banner -->
                    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                        <div class="flex items-center gap-3">
                            <div
                                class="w-11 h-11 rounded-xl flex items-center justify-center text-white text-lg font-black shadow-md shrink-0 transition-colors"
                                :style="{ backgroundColor: form.theme_primary_color }"
                            >
                                {{ (company_name || 'S').charAt(0).toUpperCase() }}
                            </div>
                            <div>
                                <h3
                                    class="text-base font-extrabold tracking-tight transition-colors"
                                    :style="previewMode === 'dark' ? { color: activeDarkModeTextColor } : {}"
                                >
                                    {{ company_name || 'Skynet Digital Store' }}
                                </h3>
                                <p class="text-xs opacity-60">
                                    {{ company_tagline || 'Modern E-Commerce Storefront & Terminal' }}
                                </p>
                            </div>
                        </div>

                        <!-- Accent Outlined Chips -->
                        <div class="flex items-center gap-2.5">
                            <span
                                class="px-3.5 py-1 rounded-full text-xs font-semibold border-2 transition-colors"
                                :style="{
                                    borderColor: form.theme_primary_color,
                                    color: form.theme_primary_color
                                }"
                            >
                                Primary Accent
                            </span>
                            <span
                                class="px-3.5 py-1 rounded-full text-xs font-semibold border-2 transition-colors"
                                :style="{
                                    borderColor: form.theme_secondary_color,
                                    color: form.theme_secondary_color
                                }"
                            >
                                Secondary Accent
                            </span>
                        </div>
                    </div>

                    <!-- Action Elements Row -->
                    <div class="flex flex-wrap items-center gap-3 pt-2">
                        <!-- Primary Solid Button -->
                        <button
                            type="button"
                            class="px-5 py-2.5 rounded-lg text-xs font-bold text-white shadow-xs transition-opacity hover:opacity-90 active:scale-95"
                            :style="{ backgroundColor: form.theme_primary_color }"
                        >
                            Primary Button
                        </button>

                        <!-- Secondary Solid Button -->
                        <button
                            type="button"
                            class="px-5 py-2.5 rounded-lg text-xs font-bold text-white shadow-xs transition-opacity hover:opacity-90 active:scale-95"
                            :style="{ backgroundColor: form.theme_secondary_color }"
                        >
                            Secondary Button
                        </button>

                        <!-- Revenue Pill Badge -->
                        <span
                            class="px-3.5 py-1.5 rounded-md text-[11px] font-extrabold text-white uppercase tracking-wider shadow-xs"
                            :style="{ backgroundColor: form.theme_primary_color }"
                        >
                            REVENUE
                        </span>

                        <!-- Gross Profit Pill Badge -->
                        <span
                            class="px-3.5 py-1.5 rounded-md text-[11px] font-extrabold text-white uppercase tracking-wider shadow-xs"
                            :style="{ backgroundColor: form.theme_secondary_color }"
                        >
                            GROSS PROFIT
                        </span>
                    </div>
                </div>
            </div>

            <!-- Bottom Credits -->
            <div class="text-center pt-2 pb-6 text-xs text-gray-400">
                Developed by <span class="font-semibold text-gray-600 dark:text-gray-300">Skynet Digital Limited</span>.
            </div>

        </div>
    </AdminLayout>
</template>
