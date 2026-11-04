import { defineStore } from 'pinia';

export const useCartDrawerStore = defineStore('cartDrawer', {
  state: () => ({
    isOpen: false,
  }),
  actions: {
    open() {
      this.isOpen = true;
      if (typeof document !== 'undefined') {
        document.body.classList.add('overflow-hidden');
      }
    },
    close() {
      this.isOpen = false;
      if (typeof document !== 'undefined') {
        document.body.classList.remove('overflow-hidden');
      }
    },
    toggle() {
      if (this.isOpen) {
        this.close();
      } else {
        this.open();
      }
    },
  },
});
