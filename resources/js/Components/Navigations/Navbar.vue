<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue';
import { router, usePage, Link } from '@inertiajs/vue3';
import Logo from '../../../images/logo.png';
import DesktopNavLinks from '@/Components/Navigations/Staff/DesktopNavLinks.vue';
import MobileNavLinks from '@/Components/Navigations/Staff/MobileNavLinks.vue';
import ThemeToggle from '@/Components/ThemeToggle.vue';
import debounce from 'lodash/debounce';

const page = usePage();
const isOpen        = ref(false);   // mobile drawer
const megaMenuOpen  = ref(false);   // category mega menu
const toggle        = () => (isOpen.value = !isOpen.value);

// ── Search ─────────────────────────────────────────────────────
const search       = ref('');
const suggestions  = ref([]);
const showDropdown = ref(false);
const loading      = ref(false);
const searchRef    = ref(null);

const fetchSuggestions = debounce(async (q) => {
    if (q.length < 2) { suggestions.value = []; showDropdown.value = false; return; }
    loading.value = true;
    try {
        const res = await fetch(`/search/suggestions?q=${encodeURIComponent(q)}`);
        suggestions.value = await res.json();
        showDropdown.value = true;
    } catch { suggestions.value = []; }
    finally { loading.value = false; }
}, 300);

const onInput      = () => fetchSuggestions(search.value);
const submitSearch = () => {
    if (!search.value.trim()) return;
    showDropdown.value = false;
    router.get(route('shop.index'), { search: search.value });
};
const goToProduct = (product) => {
    showDropdown.value = false;
    search.value = '';
    router.visit(route('shop.show', product.url_key));
};
const viewAll = () => {
    showDropdown.value = false;
    router.get(route('shop.index'), { search: search.value });
};

// Mobile subcat accordion open state
const openMobileCat = ref(null);
const toggleMobileCat = (id) => {
    openMobileCat.value = openMobileCat.value === id ? null : id;
};

const handleClickOutside = (e) => {
    if (searchRef.value && !searchRef.value.contains(e.target)) showDropdown.value = false;
};
onMounted(() => {
    document.addEventListener('click', handleClickOutside);
});
onUnmounted(() => {
    document.removeEventListener('click', handleClickOutside);
});

// ── Helpers ────────────────────────────────────────────────────
const cartCount       = computed(() => page.props.cart_count ?? 0);
const compareCount    = computed(() => page.props.compare_count ?? 0);
const isCustomer      = computed(() => !!page.props.auth?.customer);
const isStaff         = computed(() => !!page.props.auth?.user);
const wishlistEnabled = computed(() => page.props.store_settings?.wishlist_enabled === '1');
const categories      = computed(() => page.props.categories ?? []);
const gridCols        = computed(() => Math.min(categories.value.length, 4));

// Mega menu position (fixed, calculated from button)
const catBtnRef     = ref(null);
const menuStyle     = ref({});

const openMegaMenu = (e) => {
    e.stopPropagation();
    if (megaMenuOpen.value) { megaMenuOpen.value = false; return; }
    const rect = catBtnRef.value.getBoundingClientRect();
    const isMobile = window.innerWidth < 640;
    menuStyle.value = {
        top:  rect.bottom + 4 + 'px',
        // On mobile: pin to left edge with small margin
        // On desktop: align with the button
        left: isMobile ? '8px' : Math.max(8, rect.left) + 'px',
    };
    megaMenuOpen.value = true;
};
</script>

<template>
  <!-- fixed header -->
  <header class="fixed top-0 left-0 w-full z-50 bg-white dark:bg-gray-900 shadow-sm">

    <!-- ── ROW 1 : Logo | Icons | Hamburger ────────────────────── -->
    <div class="border-b dark:border-gray-800">
      <div class="container mx-auto flex items-center justify-between px-3 sm:px-4 h-14">

        <!-- Logo -->
        <Link href="/" class="flex-shrink-0 flex items-center gap-2">
          <img :src="$page.props.store_settings.company_logo ? '/storage/' + $page.props.store_settings.company_logo : Logo" width="38" height="28" :alt="$page.props.store_settings.company_name || 'Skynet Digital'" class="object-contain max-h-[28px]"/>
          <span class="font-extrabold text-sm leading-tight text-blue-700 dark:text-blue-400 hidden xs:block uppercase">
            {{ $page.props.store_settings.company_name || 'SKYNET' }}<br/><span v-if="$page.props.store_settings.company_tagline" class="font-normal text-[10px] text-gray-500 dark:text-gray-400 capitalize">{{ $page.props.store_settings.company_tagline }}</span>
          </span>
        </Link>

        <!-- Right action icons -->
        <div class="flex items-center gap-1 sm:gap-2">
          <ThemeToggle/>

          <!-- Logged-in customer buttons -->
          <template v-if="isCustomer">
            <Link :href="route('customer.orders')"
              class="hidden sm:flex flex-col items-center text-gray-600 dark:text-gray-300 hover:text-blue-600 dark:hover:text-blue-400 transition px-1">
              <svg class="w-5 h-5 mb-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/>
              </svg>
              <span class="text-[10px] font-medium">Orders</span>
            </Link>
            <Link v-if="wishlistEnabled" :href="route('wishlist.index')"
              class="flex flex-col items-center text-gray-600 dark:text-gray-300 hover:text-red-500 dark:hover:text-red-400 transition px-1">
              <svg class="w-5 h-5 mb-0.5"
                :class="$page.component.startsWith('Frontend/Wishlist') ? 'text-red-500 fill-red-500' : 'fill-none'"
                stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                  d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z"/>
              </svg>
              <span class="text-[10px] font-medium">Wishlist</span>
            </Link>
          </template>

          <!-- Guest buttons -->
          <template v-else-if="!isStaff">
            <Link :href="route('login')"
              class="hidden sm:flex flex-col items-center text-gray-600 dark:text-gray-300 hover:text-blue-600 dark:hover:text-blue-400 transition px-1">
              <svg class="w-5 h-5 mb-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5.121 17.804A13.937 13.937 0 0112 16c2.5 0 4.847.655 6.879 1.804M15 10a3 3 0 11-6 0 3 3 0 016 0zm6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
              </svg>
              <span class="text-[10px] font-medium">Sign In</span>
            </Link>
          </template>

          <!-- Staff -->
          <template v-else>
            <DesktopNavLinks class="hidden sm:flex"/>
          </template>

          <!-- Compare -->
          <Link :href="route('compare.index')"
            class="relative flex flex-col items-center text-gray-600 dark:text-gray-300 hover:text-blue-600 dark:hover:text-blue-400 transition px-1">
            <div class="relative">
              <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4"/>
              </svg>
              <span v-if="compareCount > 0"
                class="absolute -top-1.5 -right-1.5 min-w-[18px] h-[18px] px-1 flex items-center justify-center text-[10px] font-bold text-white bg-blue-500 rounded-full ring-2 ring-white dark:ring-gray-900">
                {{ compareCount > 99 ? '99+' : compareCount }}
              </span>
            </div>
            <span class="text-[10px] font-medium mt-0.5">Compare</span>
          </Link>

          <!-- Cart -->
          <Link :href="route('cart.index')"
            class="relative flex flex-col items-center text-gray-600 dark:text-gray-300 hover:text-blue-600 dark:hover:text-blue-400 transition px-1">
            <div class="relative">
              <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z"/>
              </svg>
              <span v-if="cartCount > 0"
                class="absolute -top-1.5 -right-1.5 min-w-[18px] h-[18px] px-1 flex items-center justify-center text-[10px] font-bold text-white bg-red-500 rounded-full ring-2 ring-white dark:ring-gray-900">
                {{ cartCount > 99 ? '99+' : cartCount }}
              </span>
            </div>
            <span class="text-[10px] font-medium mt-0.5">Cart</span>
          </Link>

          <!-- Mobile hamburger -->
          <button @click="toggle" class="ml-1 p-1 text-gray-600 dark:text-gray-300 md:hidden">
            <svg v-if="!isOpen" class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M4 6h16M4 12h16M4 18h16"/>
            </svg>
            <svg v-else class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M6 18L18 6M6 6l12 12"/>
            </svg>
          </button>
        </div>
      </div>
    </div>

    <!-- ── ROW 2 : Search bar ──────────────────────────────────── -->
    <div class="bg-white dark:bg-gray-900 border-b dark:border-gray-800 px-3 sm:px-4 py-2">
      <div class="container mx-auto">
        <div ref="searchRef" class="relative">
          <form @submit.prevent="submitSearch" class="flex items-center gap-0">
            <input
              v-model="search"
              @input="onInput"
              @focus="search.length >= 2 && (showDropdown.value = true)"
              type="text"
              placeholder="Search for products, brands..."
              class="flex-1 pl-4 pr-12 py-2.5 rounded-xl border border-gray-300 dark:border-gray-600 dark:bg-gray-800 dark:text-white text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition w-full"
              autocomplete="off"
            />
            <button type="submit"
              class="absolute right-0 top-0 bottom-0 px-4 rounded-r-xl bg-blue-600 hover:bg-blue-700 transition flex items-center justify-center">
              <svg v-if="!loading" class="w-4 h-4 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
              </svg>
              <svg v-else class="w-4 h-4 text-white animate-spin" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/>
              </svg>
            </button>
          </form>

          <!-- Suggestions dropdown -->
          <div v-if="showDropdown && suggestions.length > 0"
            class="absolute top-full left-0 right-0 mt-1.5 bg-white dark:bg-gray-800 rounded-xl shadow-2xl border dark:border-gray-700 overflow-hidden z-50">
            <div v-for="product in suggestions" :key="product.id"
              @click="goToProduct(product)"
              class="flex items-center gap-3 px-4 py-3 hover:bg-gray-50 dark:hover:bg-gray-700 cursor-pointer border-b dark:border-gray-700 last:border-0 transition">
              <img
                :src="product.image ? '/storage/' + product.image : 'https://placehold.co/48x48?text=P'"
                class="w-10 h-10 object-cover rounded-lg flex-shrink-0 border dark:border-gray-600"
                :alt="product.name"/>
              <div class="flex-1 min-w-0">
                <p class="text-sm font-semibold text-gray-900 dark:text-white truncate">{{ product.name }}</p>
                <p v-if="product.category" class="text-xs text-gray-400 dark:text-gray-500">{{ product.category.name }}</p>
              </div>
              <div class="flex-shrink-0 text-right">
                <span v-if="product.discount_price" class="block text-xs text-gray-400 line-through">
                  ₦{{ parseFloat(product.price).toLocaleString() }}
                </span>
                <span class="text-sm font-bold text-blue-600 dark:text-blue-400">
                  ₦{{ parseFloat(product.discount_price || product.price).toLocaleString() }}
                </span>
              </div>
            </div>
            <button @click="viewAll"
              class="w-full flex items-center justify-center gap-2 px-4 py-3 bg-gray-50 dark:bg-gray-900 hover:bg-blue-50 dark:hover:bg-blue-900/30 text-blue-600 dark:text-blue-400 font-semibold text-sm transition">
              View all results for "{{ search }}"
              <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 8l4 4m0 0l-4 4m4-4H3"/>
              </svg>
            </button>
          </div>

          <!-- No results -->
          <div v-if="showDropdown && search.length >= 2 && !loading && suggestions.length === 0"
            class="absolute top-full left-0 right-0 mt-1.5 bg-white dark:bg-gray-800 rounded-xl shadow-xl border dark:border-gray-700 px-4 py-5 text-center text-sm text-gray-500 dark:text-gray-400 z-50">
            No products found for "<strong>{{ search }}</strong>"
          </div>
        </div>
      </div>
    </div>

    <!-- ── ROW 3 : Nav bar with Categories mega menu ─────────── -->
    <div class="bg-gray-50 dark:bg-gray-800 border-b dark:border-gray-700">
      <div class="container mx-auto px-3 sm:px-4">
        <!-- NOTE: no overflow on this nav — mega menu must escape freely -->
        <nav class="flex items-center gap-1 py-1.5 scrollbar-hide" style="overflow-x: auto; overflow-y: visible;">

          <!-- ☰ Categories button (no relative wrapper so overflow doesn't clip) -->
          <div id="mega-menu-wrapper" class="flex-shrink-0">
            <button
              ref="catBtnRef"
              @click="openMegaMenu"
              class="flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs sm:text-sm font-semibold transition whitespace-nowrap"
              :class="megaMenuOpen ? 'bg-blue-600 text-white' : 'bg-white dark:bg-gray-700 text-gray-800 dark:text-gray-200 border border-gray-200 dark:border-gray-600 hover:bg-gray-100 dark:hover:bg-gray-600'"
            >
              <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M4 6h16M4 12h16M4 18h16"/>
              </svg>
              Categories
              <svg class="w-3 h-3 transition-transform" :class="megaMenuOpen ? 'rotate-180' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
              </svg>
            </button>
          </div>

          <!-- Separator -->
          <span class="h-4 w-px bg-gray-300 dark:bg-gray-600 mx-1 flex-shrink-0"></span>

          <!-- Home -->
          <Link :href="route('home')"
            class="flex-shrink-0 px-3 py-1.5 rounded-lg text-xs sm:text-sm font-semibold transition whitespace-nowrap"
            :class="$page.component === 'Home' ? 'bg-blue-600 text-white' : 'text-gray-700 dark:text-gray-300 hover:bg-gray-200 dark:hover:bg-gray-700'">
            🏠 Home
          </Link>

          <!-- All Products -->
          <Link :href="route('shop.index')"
            class="flex-shrink-0 px-3 py-1.5 rounded-lg text-xs sm:text-sm font-semibold transition whitespace-nowrap"
            :class="$page.component.startsWith('Frontend/Products') ? 'bg-blue-600 text-white' : 'text-gray-700 dark:text-gray-300 hover:bg-gray-200 dark:hover:bg-gray-700'">
            🛍️ All Products
          </Link>

          <!-- Category pills — desktop only, max 3 -->
          <template v-for="cat in categories.slice(0, 3)" :key="cat.id">
            <Link
              :href="route('shop.index', { category: cat.slug })"
              class="hidden sm:inline-flex flex-shrink-0 px-3 py-1.5 rounded-lg text-xs sm:text-sm font-medium transition whitespace-nowrap text-gray-600 dark:text-gray-400 hover:bg-gray-200 dark:hover:bg-gray-700 hover:text-gray-900 dark:hover:text-white">
              {{ cat.name }}
            </Link>
          </template>

          <!-- Track Order -->
          <Link :href="route('order.track')"
            class="flex-shrink-0 ml-auto px-3 py-1.5 rounded-lg text-xs sm:text-sm font-medium text-gray-600 dark:text-gray-400 hover:bg-gray-200 dark:hover:bg-gray-700 transition whitespace-nowrap">
            📦 Track Order
          </Link>
        </nav>
      </div>
    </div>

    <!-- ── Mobile Drawer ────────────────────────────────────────── -->
    <transition name="slide">
      <div v-if="isOpen" class="md:hidden bg-white dark:bg-gray-900 shadow-lg border-t dark:border-gray-800">
        <ul class="px-4 py-3 space-y-1 max-h-[70vh] overflow-y-auto">
          <li>
            <Link :href="route('home')" @click="toggle"
              class="flex items-center gap-2 px-3 py-2.5 rounded-lg hover:bg-gray-100 dark:hover:bg-gray-800 transition font-medium">🏠 Home</Link>
          </li>
          <li>
            <Link :href="route('shop.index')" @click="toggle"
              class="flex items-center gap-2 px-3 py-2.5 rounded-lg hover:bg-gray-100 dark:hover:bg-gray-800 transition font-medium">🛍️ All Products</Link>
          </li>
          <li class="text-xs font-semibold text-gray-400 uppercase px-3 pt-2">Categories</li>

          <!-- Mobile category with accordion for subcats -->
          <template v-for="cat in categories" :key="cat.id">
            <li>
              <div class="flex items-center">
                <Link :href="route('shop.index', { category: cat.slug })" @click="toggle"
                  class="flex-1 flex items-center gap-2 px-3 py-2 rounded-lg hover:bg-gray-100 dark:hover:bg-gray-800 transition text-sm text-gray-700 dark:text-gray-300 font-medium">
                  {{ cat.name }}
                </Link>
                <!-- Expand button if has subcats -->
                <button v-if="cat.children?.length"
                  @click.stop="toggleMobileCat(cat.id)"
                  class="px-2 py-2 text-gray-500 dark:text-gray-400 hover:text-blue-600 dark:hover:text-blue-400">
                  <svg class="w-4 h-4 transition-transform" :class="openMobileCat === cat.id ? 'rotate-180' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                  </svg>
                </button>
              </div>
              <!-- Subcategories accordion -->
              <ul v-if="cat.children?.length && openMobileCat === cat.id" class="pl-6 mt-1 space-y-0.5 border-l-2 border-blue-200 dark:border-blue-800 ml-5">
                <li v-for="sub in cat.children" :key="sub.id">
                  <Link :href="route('shop.index', { category: sub.slug })" @click="toggle"
                    class="block px-3 py-1.5 rounded-lg text-sm text-gray-600 dark:text-gray-400 hover:bg-gray-100 dark:hover:bg-gray-800 hover:text-blue-600 dark:hover:text-blue-400 transition">
                    {{ sub.name }}
                  </Link>
                </li>
              </ul>
            </li>
          </template>

          <li class="border-t dark:border-gray-700 pt-2 mt-1">
            <Link :href="route('cart.index')" @click="toggle"
              class="flex items-center justify-between gap-2 px-3 py-2.5 rounded-lg hover:bg-gray-100 dark:hover:bg-gray-800 transition font-medium">
              🛒 Cart
              <span v-if="cartCount > 0" class="bg-red-500 text-white text-xs font-bold px-2 py-0.5 rounded-full">{{ cartCount }}</span>
            </Link>
          </li>

          <template v-if="isCustomer">
            <li v-if="wishlistEnabled">
              <Link :href="route('wishlist.index')" @click="toggle"
                class="flex items-center gap-2 px-3 py-2.5 rounded-lg hover:bg-gray-100 dark:hover:bg-gray-800 transition font-medium">❤️ Wishlist</Link>
            </li>
            <li>
              <Link :href="route('customer.orders')" @click="toggle"
                class="flex items-center gap-2 px-3 py-2.5 rounded-lg hover:bg-gray-100 dark:hover:bg-gray-800 transition font-medium">📦 My Orders</Link>
            </li>
            <li>
              <Link :href="route('order.track')" @click="toggle"
                class="flex items-center gap-2 px-3 py-2.5 rounded-lg hover:bg-gray-100 dark:hover:bg-gray-800 transition font-medium">🔍 Track Order</Link>
            </li>
            <li>
              <Link :href="route('customer.logout')" method="post" as="button"
                class="w-full flex items-center gap-2 px-3 py-2.5 rounded-lg text-red-600 hover:bg-red-50 dark:hover:bg-red-900/20 transition font-medium">🚪 Logout</Link>
            </li>
          </template>
          <template v-else-if="isStaff">
            <MobileNavLinks/>
          </template>
          <template v-else>
            <li>
              <Link :href="route('login')" @click="toggle"
                class="flex items-center gap-2 px-3 py-2.5 rounded-lg hover:bg-gray-100 dark:hover:bg-gray-800 transition font-medium text-blue-600">👤 Sign In</Link>
            </li>
            <li>
              <Link :href="route('register')" @click="toggle"
                class="flex items-center gap-2 px-3 py-2.5 rounded-lg hover:bg-gray-100 dark:hover:bg-gray-800 transition font-medium">✨ Create Account</Link>
            </li>
            <li>
              <Link :href="route('order.track')" @click="toggle"
                class="flex items-center gap-2 px-3 py-2.5 rounded-lg hover:bg-gray-100 dark:hover:bg-gray-800 transition font-medium">🔍 Track Order</Link>
            </li>
          </template>
        </ul>
      </div>
    </transition>

  </header>

  <!-- ── Mega Menu Teleport (rendered at body level to escape all overflow clipping) ── -->
  <Teleport to="body">
    <!-- Backdrop -->
    <div v-if="megaMenuOpen"
      class="fixed inset-0 z-[99] bg-black/20 dark:bg-black/40"
      @click="megaMenuOpen = false">
    </div>

    <!-- Panel -->
    <transition name="mega">
      <div v-if="megaMenuOpen && categories.length"
        class="fixed z-[100] bg-white dark:bg-gray-900 rounded-xl shadow-2xl border dark:border-gray-700 overflow-y-auto"
        :style="{
            top: menuStyle.top,
            left: menuStyle.left,
            // Mobile: full-width minus margins; desktop: max 900px
            width: 'min(calc(100vw - 16px), 900px)',
            maxHeight: '80vh',
        }"
        @click.stop>

        <!-- Header on mobile -->
        <div class="flex items-center justify-between px-4 py-3 border-b dark:border-gray-700 sm:hidden">
          <span class="font-bold text-sm text-gray-800 dark:text-white">☰ All Categories</span>
          <button @click="megaMenuOpen = false"
            class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-200 p-1 rounded">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M6 18L18 6M6 6l12 12"/>
            </svg>
          </button>
        </div>

        <!-- Responsive grid:
             mobile  : 1 col (stacked list)
             sm/md   : 2 col
             lg+     : 3 or 4 col based on category count -->
        <div class="p-4 sm:p-6 grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-3 sm:gap-6">

          <div v-for="cat in categories" :key="cat.id"
            class="sm:border-0 border-b dark:border-gray-800 pb-3 sm:pb-0 last:border-0">

            <!-- Parent category header -->
            <Link
              :href="route('shop.index', { category: cat.slug })"
              @click="megaMenuOpen = false"
              class="flex items-center gap-2 font-bold text-gray-900 dark:text-white text-xs sm:text-sm hover:text-blue-600 dark:hover:text-blue-400 transition mb-2">
              <span class="w-5 h-5 sm:w-6 sm:h-6 bg-blue-100 dark:bg-blue-900/30 text-blue-600 dark:text-blue-400 rounded flex items-center justify-center text-[10px] flex-shrink-0">📁</span>
              <span class="truncate">{{ cat.name }}</span>
            </Link>

            <!-- Subcategories -->
            <ul v-if="cat.children?.length" class="space-y-0.5 sm:space-y-1 mb-2 border-l-2 border-gray-100 dark:border-gray-700 pl-2 sm:pl-3">
              <li v-for="sub in cat.children" :key="sub.id">
                <Link
                  :href="route('shop.index', { category: sub.slug })"
                  @click="megaMenuOpen = false"
                  class="text-[11px] sm:text-sm text-gray-500 dark:text-gray-400 hover:text-blue-600 dark:hover:text-blue-400 transition block py-0.5 truncate">
                  {{ sub.name }}
                </Link>
              </li>
            </ul>

            <!-- View all -->
            <Link
              :href="route('shop.index', { category: cat.slug })"
              @click="megaMenuOpen = false"
              class="text-[11px] sm:text-xs text-blue-600 dark:text-blue-400 font-semibold hover:underline inline-flex items-center gap-0.5">
              View all →
            </Link>
          </div>
        </div>
      </div>
    </transition>
  </Teleport>
</template>

<style scoped>
.slide-enter-active, .slide-leave-active { transition: all 0.25s ease; }
.slide-enter-from, .slide-leave-to { transform: translateY(-8px); opacity: 0; }

.mega-enter-active, .mega-leave-active { transition: all 0.2s ease; }
.mega-enter-from, .mega-leave-to { transform: translateY(-6px); opacity: 0; }

.scrollbar-hide::-webkit-scrollbar { display: none; }
.scrollbar-hide { -ms-overflow-style: none; scrollbar-width: none; }
</style>
