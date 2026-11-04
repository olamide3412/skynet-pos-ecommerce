// resources/js/stores/themeStore.js
import { defineStore } from 'pinia'

export const useThemeStore = defineStore('theme', {
  state: () => ({
    theme: 'system',
    _mediaListenerAttached: false,
  }),
  actions: {
    setTheme(newTheme) {
      this.theme = newTheme
      if (typeof localStorage !== 'undefined') {
        localStorage.setItem('user-theme', newTheme)
      }
      this.applyTheme()
    },
    applyTheme() {
      if (typeof document === 'undefined') return
      const root = document.documentElement
      const systemIsDark = typeof window !== 'undefined' && window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches

      const themeToApply = this.theme === 'system' ? (systemIsDark ? 'dark' : 'light') : this.theme

      root.setAttribute('data-theme', themeToApply)
      if (themeToApply === 'dark') {
        root.classList.add('dark')
      } else {
        root.classList.remove('dark')
      }

      if (typeof window !== 'undefined' && !this._mediaListenerAttached && window.matchMedia) {
        this._mediaListenerAttached = true
        window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change', () => {
          if (this.theme === 'system') {
            this.applyTheme()
          }
        })
      }
    },
    loadTheme() {
      const saved = typeof localStorage !== 'undefined' ? localStorage.getItem('user-theme') : null
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
