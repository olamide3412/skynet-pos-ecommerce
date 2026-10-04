<script setup>
import { ref, onMounted } from 'vue'
import { useThemeStore } from '@/Stores/themeStore'

const themeStore = useThemeStore()
const theme = ref('system')

onMounted(() => {
  theme.value = themeStore.theme
})

const toggleTheme = () => {
  let newTheme = 'system';
  if (theme.value === 'system') {
    newTheme = 'light';
  } else if (theme.value === 'light') {
    newTheme = 'dark';
  } else if (theme.value === 'dark') {
    newTheme = 'system';
  }
  
  theme.value = newTheme;
  themeStore.setTheme(newTheme);
}
</script>

<template>
    <div class="flex items-center justify-end" data-aos="fade-right">
      <button
        @click="toggleTheme"
        class="p-2 rounded-full hover:bg-gray-200 dark:hover:bg-gray-700 transition"
        :aria-label="`Current theme: ${theme}. Click to switch theme.`"
      >
        <!-- System icon (Monitor) -->
        <svg v-if="theme === 'system'" xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
          <rect x="2" y="3" width="20" height="14" rx="2" ry="2"></rect>
          <line x1="8" y1="21" x2="16" y2="21"></line>
          <line x1="12" y1="17" x2="12" y2="21"></line>
        </svg>

        <!-- Light icon (Sun) -->
        <svg v-else-if="theme === 'light'" xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
          <circle cx="12" cy="12" r="5"></circle>
          <line x1="12" y1="1" x2="12" y2="3"></line>
          <line x1="12" y1="21" x2="12" y2="23"></line>
          <line x1="4.22" y1="4.22" x2="5.64" y2="5.64"></line>
          <line x1="18.36" y1="18.36" x2="19.78" y2="19.78"></line>
          <line x1="1" y1="12" x2="3" y2="12"></line>
          <line x1="21" y1="12" x2="23" y2="12"></line>
          <line x1="4.22" y1="19.78" x2="5.64" y2="18.36"></line>
          <line x1="18.36" y1="5.64" x2="19.78" y2="4.22"></line>
        </svg>

        <!-- Dark icon (Moon) -->
        <svg v-else-if="theme === 'dark'" xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
          <path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"></path>
        </svg>
      </button>
    </div>
</template>

