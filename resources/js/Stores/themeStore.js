// resources/js/stores/themeStore.js
import { defineStore } from 'pinia'

export const useThemeStore = defineStore('theme', {
  state: () => ({
    theme: 'system',
  }),
  actions: {
    setTheme(newTheme) {
      this.theme = newTheme
      localStorage.setItem('user-theme', newTheme)
      this.applyTheme()
    },
    applyTheme() {
      const root = document.documentElement
      const systemIsDark = window.matchMedia('(prefers-color-scheme: dark)').matches

      const themeToApply = this.theme === 'system' ? (systemIsDark ? 'dark' : 'light') : this.theme

      root.setAttribute('data-theme', themeToApply)
    },
    loadTheme() {
      const saved = localStorage.getItem('user-theme')
      if (saved) this.theme = saved
      this.applyTheme()
    },
    applyBrandColors(primary, secondary, darkText) {
      if (typeof document === 'undefined') return
      const root = document.documentElement
      if (primary) {
        root.style.setProperty('--color-primary', primary)
        root.style.setProperty('--color-primary-dark', primary)
        root.style.setProperty('--theme-primary', primary)
      }
      if (secondary) {
        root.style.setProperty('--color-secondary-500', secondary)
        root.style.setProperty('--color-secondary-600', secondary)
        root.style.setProperty('--theme-secondary', secondary)
      }
      if (darkText) {
        root.style.setProperty('--theme-dark-heading', darkText)
      }
    },
  },
})
