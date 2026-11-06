<script setup>
import { Link, usePage } from '@inertiajs/vue3';
import { ref, onMounted, onBeforeUnmount } from 'vue';

const page = usePage();
const navRef = ref(null);

const tabs = [
    {
        label: 'General Store Config',
        icon: '⚙️',
        route: 'admin.settings.index',
        match: 'Admin/Settings',
    },
    {
        label: 'AI Assistant',
        icon: '🤖',
        route: 'admin.ai-settings.index',
        match: 'Admin/AiSettings',
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
        label: 'Homepage & Sliders',
        icon: '🖼️',
        route: 'admin.sliders.index',
        match: 'Admin/Sliders',
    },
    {
        label: 'Promo Flyers & Popups',
        icon: '🎉',
        route: 'admin.promo-flyers.index',
        match: 'Admin/PromoFlyers',
    },
];

const canScrollLeft = ref(false);
const canScrollRight = ref(false);
const hasOverflow = ref(false);

const isTabActive = (tab) => {
    const comp = page.component;
    return comp === tab.match || comp.startsWith(tab.match);
};

const updateScrollButtons = () => {
    if (!navRef.value) return;
    const { scrollLeft, scrollWidth, clientWidth } = navRef.value;
    hasOverflow.value = scrollWidth > clientWidth + 4;
    canScrollLeft.value = scrollLeft > 8;
    canScrollRight.value = scrollLeft + clientWidth < scrollWidth - 8;
};

const scroll = (direction) => {
    if (!navRef.value) return;
    const distance = 240;
    navRef.value.scrollBy({
        left: direction === 'left' ? -distance : distance,
        behavior: 'smooth',
    });
    setTimeout(updateScrollButtons, 350);
};

const onWheel = (e) => {
    if (navRef.value && Math.abs(e.deltaY) > Math.abs(e.deltaX)) {
        navRef.value.scrollLeft += e.deltaY;
        updateScrollButtons();
    }
};

let resizeObserver = null;

onMounted(() => {
    if (navRef.value) {
        const activeEl = navRef.value.querySelector('[data-active="true"]');
        if (activeEl) {
            activeEl.scrollIntoView({ behavior: 'smooth', block: 'nearest', inline: 'center' });
        }
        updateScrollButtons();

        navRef.value.addEventListener('scroll', updateScrollButtons, { passive: true });

        if (typeof ResizeObserver !== 'undefined') {
            resizeObserver = new ResizeObserver(() => {
                updateScrollButtons();
            });
            resizeObserver.observe(navRef.value);
        } else {
            window.addEventListener('resize', updateScrollButtons);
        }
    }
});

onBeforeUnmount(() => {
    if (navRef.value) {
        navRef.value.removeEventListener('scroll', updateScrollButtons);
    }
    if (resizeObserver) {
        resizeObserver.disconnect();
    } else {
        window.removeEventListener('resize', updateScrollButtons);
    }
});
</script>

<template>
    <div class="w-full">
        <div class="relative w-full flex items-center gap-2">
            <!-- Left Arrow Button -->
            <button
                v-if="hasOverflow"
                type="button"
                @click="scroll('left')"
                :disabled="!canScrollLeft"
                :class="[
                    'w-9 h-9 rounded-xl flex items-center justify-center shrink-0 border transition-all duration-200 shadow-xs select-none',
                    canScrollLeft
                        ? 'bg-white dark:bg-gray-800 text-gray-700 dark:text-gray-200 border-gray-200 dark:border-gray-700 hover:bg-gray-50 dark:hover:bg-gray-700 hover:scale-105 active:scale-95 cursor-pointer text-primary'
                        : 'bg-gray-100/60 dark:bg-gray-800/40 text-gray-300 dark:text-gray-600 border-transparent cursor-not-allowed opacity-30'
                ]"
                title="Scroll left"
                aria-label="Scroll left"
            >
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M15 19l-7-7 7-7"/>
                </svg>
            </button>

            <!-- Modern Pill Dock Navigation -->
            <nav
                ref="navRef"
                @wheel.passive="onWheel"
                class="settings-nav-dock flex-1 flex items-center gap-1.5 overflow-x-auto p-1.5 bg-white/95 dark:bg-gray-800/95 backdrop-blur-md border border-gray-200/80 dark:border-gray-700/80 rounded-2xl shadow-xs scroll-smooth select-none"
                aria-label="Settings Navigation"
            >
                <Link
                    v-for="tab in tabs"
                    :key="tab.route"
                    :href="route(tab.route)"
                    :data-active="isTabActive(tab)"
                    :class="[
                        'group relative px-3.5 py-2 rounded-xl text-xs font-medium transition-all duration-200 flex items-center gap-2 shrink-0 whitespace-nowrap active:scale-[0.98]',
                        isTabActive(tab)
                            ? 'text-white font-bold shadow-sm'
                            : 'text-gray-600 dark:text-gray-300 hover:text-gray-900 dark:hover:text-white hover:bg-gray-100/80 dark:hover:bg-gray-700/70'
                    ]"
                    :style="isTabActive(tab) ? { background: `linear-gradient(135deg, var(--color-primary, #7B00FF), var(--color-secondary-500, #FBA43D))` } : {}"
                >
                    <!-- Icon badge -->
                    <span
                        :class="[
                            'w-6 h-6 rounded-lg flex items-center justify-center text-xs transition-transform duration-200 group-hover:scale-110 shrink-0',
                            isTabActive(tab)
                                ? 'bg-white/20 text-white backdrop-blur-xs shadow-2xs'
                                : 'bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-300'
                        ]"
                    >
                        {{ tab.icon }}
                    </span>

                    <!-- Tab Label -->
                    <span class="tracking-tight">{{ tab.label }}</span>

                    <!-- Active Indicator Pip -->
                    <span
                        v-if="isTabActive(tab)"
                        class="w-1.5 h-1.5 rounded-full bg-white/90 shadow-xs ml-0.5"
                    ></span>
                </Link>
            </nav>

            <!-- Right Arrow Button -->
            <button
                v-if="hasOverflow"
                type="button"
                @click="scroll('right')"
                :disabled="!canScrollRight"
                :class="[
                    'w-9 h-9 rounded-xl flex items-center justify-center shrink-0 border transition-all duration-200 shadow-xs select-none',
                    canScrollRight
                        ? 'bg-white dark:bg-gray-800 text-gray-700 dark:text-gray-200 border-gray-200 dark:border-gray-700 hover:bg-gray-50 dark:hover:bg-gray-700 hover:scale-105 active:scale-95 cursor-pointer text-primary'
                        : 'bg-gray-100/60 dark:bg-gray-800/40 text-gray-300 dark:text-gray-600 border-transparent cursor-not-allowed opacity-30'
                ]"
                title="Scroll right"
                aria-label="Scroll right"
            >
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 5l7 7-7 7"/>
                </svg>
            </button>
        </div>
    </div>
</template>

<style scoped>
/* Completely hide scrollbars across all modern browsers and platforms */
.settings-nav-dock {
    -ms-overflow-style: none; /* IE and Edge */
    scrollbar-width: none; /* Firefox */
}
.settings-nav-dock::-webkit-scrollbar {
    display: none; /* Chrome, Safari, Opera */
    width: 0;
    height: 0;
}
</style>
