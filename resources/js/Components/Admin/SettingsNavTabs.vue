<script setup>
import { Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

const page = usePage();

const tabs = [
    {
        label: 'General Store Config',
        icon: '⚙️',
        route: 'admin.settings.index',
        match: 'Admin/Settings',
    },
    {
        label: 'POS Connection & Pairing',
        icon: '🔗',
        route: 'admin.pos-connection.index',
        match: 'Admin/PosConnection',
    },
    {
        label: 'Shipping Zones & Rates',
        icon: '🚚',
        route: 'admin.shipping-zones.index',
        match: 'Admin/ShippingZones',
    },
    {
        label: 'Branding & Themes',
        icon: '🎨',
        route: 'admin.themes.index',
        match: 'Admin/Themes',
    },
    {
        label: 'Sliders & Banners',
        icon: '🖼️',
        route: 'admin.sliders.index',
        match: 'Admin/Sliders',
    },
];

const isTabActive = (tab) => {
    const comp = page.component;
    return comp === tab.match || comp.startsWith(tab.match);
};
</script>

<template>
    <div class="flex items-center gap-2 overflow-x-auto border-b border-gray-200 dark:border-gray-700 pb-3">
        <Link
            v-for="tab in tabs"
            :key="tab.route"
            :href="route(tab.route)"
            :class="[
                'px-4 py-2 rounded-lg text-xs transition flex items-center gap-2 shrink-0',
                isTabActive(tab)
                    ? 'font-bold text-white shadow-xs'
                    : 'font-semibold text-gray-600 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-800'
            ]"
            :style="isTabActive(tab) ? { background: `linear-gradient(135deg, var(--color-primary, #7B00FF), var(--color-secondary-500, #FBA43D))` } : {}"
        >
            <span>{{ tab.icon }}</span>
            <span>{{ tab.label }}</span>
        </Link>
    </div>
</template>

