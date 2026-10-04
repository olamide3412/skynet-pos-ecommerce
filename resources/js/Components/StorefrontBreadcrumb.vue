<script setup>
import { Link } from '@inertiajs/vue3';

defineProps({
    items: {
        type: Array,
        required: true
    }
});
</script>

<template>
    <nav aria-label="Breadcrumb" class="mb-5 flex items-center text-sm font-medium text-gray-500 dark:text-gray-400 overflow-x-auto whitespace-nowrap scrollbar-hide py-1">
        <ol class="flex items-center gap-1.5 sm:gap-2">
            <li>
                <Link :href="route('home')" class="hover:text-blue-600 dark:hover:text-blue-400 transition flex items-center gap-1.5 focus:outline-none">
                    <svg class="w-4 h-4 mb-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"></path></svg>
                    Home
                </Link>
            </li>
            <li v-for="(item, index) in items" :key="index" class="flex items-center gap-1.5 sm:gap-2">
                <svg class="w-4 h-4 text-gray-300 dark:text-gray-600 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 5l7 7-7 7"></path></svg>
                <Link v-if="item.url" :href="item.url" class="hover:text-blue-600 dark:hover:text-blue-400 transition focus:outline-none truncate max-w-[140px] sm:max-w-[200px]" :title="item.label">
                    {{ item.label }}
                </Link>
                <span v-else class="text-gray-900 dark:text-gray-100 font-bold truncate max-w-[180px] sm:max-w-[300px]" :title="item.label" aria-current="page">
                    {{ item.label }}
                </span>
            </li>
        </ol>
    </nav>
</template>
