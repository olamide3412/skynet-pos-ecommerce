<script setup>
import { computed, onMounted, onUnmounted } from 'vue';
import { Link } from '@inertiajs/vue3';
import { useCartDrawerStore } from '@/Stores/cartDrawerStore';
import { useCart } from '@/Composables/useCart';

const cartDrawer = useCartDrawerStore();
const { cart, cartCount, cartItems, updateQuantity, removeFromCart, isUpdatingItem } = useCart();

const subtotal = computed(() => {
  return cartItems.value.reduce((acc, item) => acc + (parseFloat(item.price) * item.quantity), 0);
});

const close = () => {
  cartDrawer.close();
};

const handleKeyDown = (e) => {
  if (e.key === 'Escape' && cartDrawer.isOpen) {
    close();
  }
};

onMounted(() => {
  if (typeof window !== 'undefined') {
    window.addEventListener('keydown', handleKeyDown);
  }
});

onUnmounted(() => {
  if (typeof window !== 'undefined') {
    window.removeEventListener('keydown', handleKeyDown);
  }
});
</script>

<template>
  <!-- Teleport to body for z-index isolation -->
  <Teleport to="body">
    <div v-show="cartDrawer.isOpen" class="relative z-[9999]" aria-labelledby="slide-over-title" role="dialog" aria-modal="true">
      <!-- Backdrop -->
      <transition
        enter-active-class="ease-out duration-300"
        enter-from-class="opacity-0"
        enter-to-class="opacity-100"
        leave-active-class="ease-in duration-200"
        leave-from-class="opacity-100"
        leave-to-class="opacity-0"
      >
        <div
          v-show="cartDrawer.isOpen"
          @click="close"
          class="fixed inset-0 bg-black/60 backdrop-blur-[2px] transition-opacity"
        />
      </transition>

      <div class="fixed inset-0 overflow-hidden pointer-events-none">
        <div class="absolute inset-0 overflow-hidden">
          <div class="pointer-events-none fixed inset-y-0 right-0 flex max-w-full pl-10">
            <!-- Slide-over panel -->
            <transition
              enter-active-class="transform transition ease-in-out duration-300"
              enter-from-class="translate-x-full"
              enter-to-class="translate-x-0"
              leave-active-class="transform transition ease-in-out duration-200"
              leave-from-class="translate-x-0"
              leave-to-class="translate-x-full"
            >
              <div
                v-show="cartDrawer.isOpen"
                class="pointer-events-auto w-screen max-w-md bg-white dark:bg-gray-900 shadow-2xl flex flex-col border-l border-gray-200 dark:border-gray-800 text-gray-900 dark:text-gray-100"
              >
                <!-- Drawer Header -->
                <div class="px-5 py-4 border-b border-gray-200 dark:border-gray-800 flex items-center justify-between bg-gray-50/80 dark:bg-gray-850/70">
                  <div class="flex items-center gap-2.5">
                    <div class="w-9 h-9 rounded-full bg-primary/10 text-primary flex items-center justify-center">
                      <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z" />
                      </svg>
                    </div>
                    <div>
                      <h2 id="slide-over-title" class="text-lg font-bold leading-none text-gray-900 dark:text-white">Shopping Cart</h2>
                      <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                        {{ cartCount }} {{ cartCount === 1 ? 'item' : 'items' }} selected
                      </p>
                    </div>
                  </div>

                  <button
                    @click="close"
                    class="p-2 rounded-lg text-gray-400 hover:text-gray-700 dark:hover:text-gray-200 hover:bg-gray-100 dark:hover:bg-gray-800 transition"
                    title="Close Cart"
                  >
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                  </button>
                </div>

                <!-- Drawer Content -->
                <div class="flex-1 overflow-y-auto p-4 sm:p-5 space-y-4 bg-gray-50/40 dark:bg-gray-900">
                  <!-- Empty Cart State -->
                  <div v-if="cartItems.length === 0" class="h-full flex flex-col items-center justify-center text-center py-16 px-4">
                    <div class="w-20 h-20 rounded-full bg-primary/10 text-primary flex items-center justify-center mb-4">
                      <svg class="w-10 h-10" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z" />
                      </svg>
                    </div>
                    <h3 class="text-xl font-bold mb-1 text-gray-900 dark:text-white">Your cart is empty</h3>
                    <p class="text-sm text-gray-500 dark:text-gray-400 max-w-xs mb-6">
                      Looks like you haven't added anything yet. Explore our products and discover amazing deals!
                    </p>
                    <Link
                      :href="route('shop.index')"
                      @click="close"
                      class="px-6 py-2.5 rounded-xl bg-primary hover:opacity-90 text-white font-bold text-sm shadow-md transition"
                    >
                      Start Shopping
                    </Link>
                  </div>

                  <!-- Cart Items List -->
                  <div v-else class="space-y-3.5">
                    <div
                      v-for="item in cartItems"
                      :key="item.id"
                      class="p-3.5 rounded-xl border border-gray-200 dark:border-gray-700/80 bg-white dark:bg-gray-800 shadow-xs flex gap-3.5 items-center relative group hover:border-gray-300 dark:hover:border-gray-600 transition"
                    >
                      <!-- Product Image -->
                      <Link
                        v-if="item.product?.url_key"
                        :href="route('shop.show', item.product.url_key)"
                        @click="close"
                        class="w-18 h-18 rounded-lg overflow-hidden bg-gray-100 dark:bg-gray-900/60 border border-gray-200 dark:border-gray-700 shrink-0"
                      >
                        <img
                          :src="item.product?.image ? '/storage/' + item.product.image : 'https://placehold.co/100x100?text=' + (item.product?.name || 'Item')"
                          :alt="item.product?.name || 'Product'"
                          class="w-full h-full object-cover group-hover:scale-105 transition"
                        />
                      </Link>
                      <div v-else class="w-18 h-18 rounded-lg overflow-hidden bg-gray-100 dark:bg-gray-900/60 border border-gray-200 dark:border-gray-700 shrink-0">
                        <img src="https://placehold.co/100x100?text=Product" class="w-full h-full object-cover" />
                      </div>

                      <!-- Product Info & Adjuster -->
                      <div class="flex-1 min-w-0 pr-6">
                        <Link
                          v-if="item.product?.url_key"
                          :href="route('shop.show', item.product.url_key)"
                          @click="close"
                          class="text-sm font-bold text-gray-900 dark:text-gray-100 hover:text-primary dark:hover:text-primary line-clamp-1 transition"
                        >
                          {{ item.product?.name || 'Product' }}
                        </Link>
                        <p v-else class="text-sm font-bold truncate text-gray-900 dark:text-gray-100">{{ item.product?.name || 'Product' }}</p>

                        <div class="flex items-center gap-1.5 mt-1 text-xs text-gray-500 dark:text-gray-400">
                          <span v-if="item.unit_name" class="px-1.5 py-0.5 rounded bg-primary/10 dark:bg-primary/20 text-primary dark:text-primary font-medium">
                            {{ item.unit_name }}
                          </span>
                          <span v-if="item.variant || item.variant_label" class="px-1.5 py-0.5 rounded bg-gray-100 dark:bg-gray-700/80 text-gray-600 dark:text-gray-300">
                            {{ item.variant_label || item.variant }}
                          </span>
                          <span class="font-semibold text-gray-700 dark:text-gray-300">
                            ₦{{ parseFloat(item.price).toLocaleString() }}
                          </span>
                        </div>

                        <!-- Quantity Adjuster Controls -->
                        <div class="flex items-center justify-between mt-2.5">
                          <div class="inline-flex items-center border border-gray-300 dark:border-gray-700 rounded-lg bg-gray-50 dark:bg-gray-900/80 overflow-hidden shadow-2xs">
                            <button
                              type="button"
                              @click="updateQuantity(item, item.quantity - 1)"
                              :disabled="isUpdatingItem(item.id)"
                              class="w-7 h-7 flex items-center justify-center font-bold text-gray-600 dark:text-gray-300 hover:bg-gray-200 dark:hover:bg-gray-700 hover:text-primary dark:hover:text-primary transition disabled:opacity-50 text-sm"
                              title="Decrease quantity"
                            >
                              -
                            </button>
                            <span class="w-7 text-center text-xs font-bold text-gray-800 dark:text-gray-100">
                              {{ item.quantity }}
                            </span>
                            <button
                              type="button"
                              @click="updateQuantity(item, item.quantity + 1)"
                              :disabled="isUpdatingItem(item.id)"
                              class="w-7 h-7 flex items-center justify-center font-bold text-gray-600 dark:text-gray-300 hover:bg-gray-200 dark:hover:bg-gray-700 hover:text-primary dark:hover:text-primary transition disabled:opacity-50 text-sm"
                              title="Increase quantity"
                            >
                              +
                            </button>
                          </div>

                          <div class="text-right">
                            <span class="text-sm font-black text-primary">
                              ₦{{ (item.price * item.quantity).toLocaleString() }}
                            </span>
                          </div>
                        </div>
                      </div>

                      <!-- Remove Item Button -->
                      <button
                        type="button"
                        @click="removeFromCart(item)"
                        :disabled="isUpdatingItem(item.id)"
                        class="absolute top-3 right-3 text-gray-400 hover:text-red-500 dark:text-gray-500 dark:hover:text-red-400 p-1 transition"
                        title="Remove item"
                      >
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                        </svg>
                      </button>
                    </div>
                  </div>
                </div>

                <!-- Drawer Footer -->
                <div v-if="cartItems.length > 0" class="p-5 border-t border-gray-200 dark:border-gray-800 bg-gray-50/70 dark:bg-gray-850/70 space-y-3">
                  <div class="flex items-center justify-between text-base">
                    <span class="font-medium text-gray-600 dark:text-gray-400">Subtotal</span>
                    <span class="text-2xl font-black text-primary">₦{{ subtotal.toLocaleString() }}</span>
                  </div>
                  <p class="text-xs text-gray-500 dark:text-gray-400 text-center">
                    Taxes and shipping calculated during checkout.
                  </p>

                  <div class="flex flex-col gap-2 pt-1">
                    <Link
                      :href="route('checkout.index')"
                      @click="close"
                      class="w-full py-3.5 rounded-xl bg-primary hover:opacity-90 text-white font-extrabold text-sm uppercase tracking-wider text-center shadow-md hover:shadow-lg transition flex items-center justify-center gap-2"
                    >
                      <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7" />
                      </svg>
                      Proceed to Checkout
                    </Link>

                    <Link
                      :href="route('cart.index')"
                      @click="close"
                      class="w-full py-2.5 rounded-xl bg-white dark:bg-gray-800 hover:bg-gray-100 dark:hover:bg-gray-700 text-gray-700 dark:text-gray-200 font-bold text-xs uppercase tracking-wider text-center border border-gray-300 dark:border-gray-700 transition"
                    >
                      View Full Cart Page
                    </Link>
                  </div>
                </div>
              </div>
            </transition>
          </div>
        </div>
      </div>
    </div>
  </Teleport>
</template>
