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
</script>

<template>
  <div v-if="hasPagination" class="flex flex-wrap justify-center items-center gap-1.5 mt-6 no-print">
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
</template>
