<script setup>
import { computed } from 'vue';
import { Link } from '@inertiajs/vue3';

const props = defineProps({
  paginator: {
    type: Object,
    default: () => null,
  },
  links: {
    type: Array,
    default: () => null,
  },
});

// Normalized links from either props.links or props.paginator.links
const rawLinks = computed(() => {
  if (props.links && Array.isArray(props.links) && props.links.length > 0) {
    return props.links;
  }
  if (props.paginator && Array.isArray(props.paginator.links) && props.paginator.links.length > 0) {
    return props.paginator.links;
  }
  return [];
});

const hasPagination = computed(() => {
  if (rawLinks.value.length > 3) return true;
  if (props.paginator && props.paginator.last_page > 1) return true;
  return false;
});

// Mobile Previous & Next links
const prevLink = computed(() => {
  if (rawLinks.value.length > 0) {
    return rawLinks.value[0];
  }
  if (props.paginator?.prev_page_url) {
    return { url: props.paginator.prev_page_url, label: 'Previous' };
  }
  return null;
});

const nextLink = computed(() => {
  if (rawLinks.value.length > 1) {
    return rawLinks.value[rawLinks.value.length - 1];
  }
  if (props.paginator?.next_page_url) {
    return { url: props.paginator.next_page_url, label: 'Next' };
  }
  return null;
});

const currentPage = computed(() => {
  if (props.paginator?.current_page) {
    return props.paginator.current_page;
  }
  const active = rawLinks.value.find(l => l.active);
  if (active) {
    return active.label;
  }
  return null;
});

const lastPage = computed(() => {
  if (props.paginator?.last_page) {
    return props.paginator.last_page;
  }
  const numLinks = rawLinks.value
    .map(l => parseInt(l.label, 10))
    .filter(n => !isNaN(n));
  return numLinks.length > 0 ? Math.max(...numLinks) : null;
});
</script>

<template>
  <div v-if="hasPagination" class="w-full mt-6 mb-24 sm:mb-6 no-print">
    <!-- Mobile View: Clean Previous / Next buttons with page indicator -->
    <div class="flex sm:hidden items-center justify-between w-full max-w-sm mx-auto px-2 gap-3">
      <!-- Previous -->
      <span
        v-if="!prevLink || !prevLink.url"
        class="flex-1 inline-flex items-center justify-center gap-1.5 px-3.5 py-2.5 text-xs font-semibold rounded-xl border border-gray-200 dark:border-gray-700 text-gray-400 dark:text-gray-500 bg-gray-50 dark:bg-gray-800/40 cursor-not-allowed select-none opacity-60"
      >
        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
        </svg>
        Previous
      </span>
      <Link
        v-else
        :href="prevLink.url"
        preserve-scroll
        preserve-state
        class="flex-1 inline-flex items-center justify-center gap-1.5 px-3.5 py-2.5 text-xs font-semibold rounded-xl border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-700 shadow-xs active:scale-95 transition-all select-none"
      >
        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
        </svg>
        Previous
      </Link>

      <!-- Page info -->
      <div v-if="currentPage" class="text-xs font-medium text-gray-500 dark:text-gray-400 whitespace-nowrap px-1 text-center select-none">
        Page <span class="font-bold text-gray-900 dark:text-gray-100">{{ currentPage }}</span>
        <span v-if="lastPage"> of <span class="font-bold text-gray-900 dark:text-gray-100">{{ lastPage }}</span></span>
      </div>

      <!-- Next -->
      <span
        v-if="!nextLink || !nextLink.url"
        class="flex-1 inline-flex items-center justify-center gap-1.5 px-3.5 py-2.5 text-xs font-semibold rounded-xl border border-gray-200 dark:border-gray-700 text-gray-400 dark:text-gray-500 bg-gray-50 dark:bg-gray-800/40 cursor-not-allowed select-none opacity-60"
      >
        Next
        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
        </svg>
      </span>
      <Link
        v-else
        :href="nextLink.url"
        preserve-scroll
        preserve-state
        class="flex-1 inline-flex items-center justify-center gap-1.5 px-3.5 py-2.5 text-xs font-semibold rounded-xl border border-primary bg-primary text-white hover:bg-primary/90 shadow-xs active:scale-95 transition-all select-none"
      >
        Next
        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
        </svg>
      </Link>
    </div>

    <!-- Desktop View: Full Numbered Links -->
    <div class="hidden sm:flex flex-wrap justify-center items-center gap-1.5">
      <template v-for="(link, index) in rawLinks" :key="index">
        <span
          v-if="!link.url"
          class="px-3 py-1.5 text-xs rounded-lg border border-gray-200 dark:border-gray-700 text-gray-400 dark:text-gray-500 bg-gray-50 dark:bg-gray-800/50 cursor-not-allowed select-none"
          v-html="link.label"
        />
        <Link
          v-else
          :href="link.url"
          preserve-scroll
          preserve-state
          :class="[
            'px-3 py-1.5 text-xs rounded-lg font-semibold transition-all select-none',
            link.active
              ? 'bg-primary text-white shadow-xs font-bold border border-primary'
              : 'bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-700 hover:border-gray-300 dark:hover:border-gray-600'
          ]"
          v-html="link.label"
        />
      </template>
    </div>
  </div>
</template>
