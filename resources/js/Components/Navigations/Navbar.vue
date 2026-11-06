<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue';
import { router, usePage, Link } from '@inertiajs/vue3';
import Logo from '../../../images/logo.png';
import ThemeToggle from '@/Components/ThemeToggle.vue';
import debounce from 'lodash/debounce';
import { useCartDrawerStore } from '@/Stores/cartDrawerStore';

const page = usePage();
const cartDrawer    = useCartDrawerStore();
const isOpen        = ref(false);   // mobile drawer
const megaMenuOpen  = ref(false);   // category mega menu
const toggle        = () => {
    isOpen.value = !isOpen.value;
    if (!isOpen.value) {
        showAllMobileCats.value = false;
        openMobileCat.value = null;
    }
};

// ── Search & Trending ──────────────────────────────────────────
const search       = ref('');
const suggestions  = ref([]);
const showDropdown = ref(false);
const loading      = ref(false);
const searchRef    = ref(null);

// Instant preloaded trending searches from initial page load props (0ms network delay!)
const trendingSearches = computed(() => {
    return page.props.top_searched_preview || { keywords: [], products: [] };
});

const onFocus = () => {
    showDropdown.value = true;
};

const fetchSuggestions = debounce(async (q) => {
    if (q.length < 2) {
        suggestions.value = [];
        return;
    }
    loading.value = true;
    try {
        const res = await fetch(`/search/suggestions?q=${encodeURIComponent(q)}`);
        suggestions.value = await res.json();
        showDropdown.value = true;
    } catch { suggestions.value = []; }
    finally { loading.value = false; }
}, 300);

const onInput = () => {
    if (search.value.trim().length < 2) {
        suggestions.value = [];
        showDropdown.value = true;
    } else {
        fetchSuggestions(search.value);
    }
};

const selectKeyword = (kw) => {
    search.value = kw;
    showDropdown.value = false;
    router.get(route('shop.index'), { search: kw });
};

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

// Mobile drawer category expand/collapse state
const showAllMobileCats = ref(false);
const displayedMobileCats = computed(() => {
    if (showAllMobileCats.value || !categories.value || categories.value.length <= 3) {
        return categories.value || [];
    }
    return categories.value.slice(0, 3);
});

// Mobile subcat accordion open state
const openMobileCat = ref(null);
const toggleMobileCat = (id) => {
    openMobileCat.value = openMobileCat.value === id ? null : id;
};

const isScrolled = ref(false);

const handleScroll = () => {
    isScrolled.value = window.scrollY > 30;
};

const handleClickOutside = (e) => {
    if (searchRef.value && !searchRef.value.contains(e.target)) showDropdown.value = false;
};
onMounted(() => {
    document.addEventListener('click', handleClickOutside);
    window.addEventListener('scroll', handleScroll, { passive: true });
    handleScroll();
});
onUnmounted(() => {
    document.removeEventListener('click', handleClickOutside);
    window.removeEventListener('scroll', handleScroll);
});

// ── Helpers ────────────────────────────────────────────────────
const cartCount       = computed(() => page.props.cart_count ?? 0);
const compareCount    = computed(() => page.props.compare_count ?? 0);
const isCustomer      = computed(() => !!page.props.auth?.customer);
const isStaff         = computed(() => !!page.props.auth?.user);
const wishlistEnabled = computed(() => page.props.store_settings?.wishlist_enabled === '1');
const categories      = computed(() => page.props.categories ?? []);
const gridCols        = computed(() => Math.min(categories.value.length, 4));

const showCartTotalHeader = computed(() => {
    return page.props.store_settings?.show_cart_total_header !== '0';
});
const cartTotal = computed(() => {
    return Number(page.props.cart?.total ?? 0);
});
const formattedCartTotal = computed(() => {
    return '₦' + cartTotal.value.toLocaleString('en-NG', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
});
const compactCartTotal = computed(() => {
    if (cartTotal.value >= 1000000) {
        return '₦' + (cartTotal.value / 1000000).toFixed(1) + 'M';
    }
    if (cartTotal.value >= 10000) {
        return '₦' + (cartTotal.value / 1000).toFixed(1) + 'k';
    }
    return '₦' + cartTotal.value.toLocaleString('en-NG', { minimumFractionDigits: 0, maximumFractionDigits: 0 });
});

// Mega menu position (fixed, calculated from button)
const catBtnRef     = ref(null);
const menuStyle     = ref({});

const openMegaMenu = (e) => {
    e.stopPropagation();
    if (megaMenuOpen.value) { megaMenuOpen.value = false; return; }
    const rect = catBtnRef.value.getBoundingClientRect();
    const isMobile = window.innerWidth < 640;
    const menuWidth = isMobile ? window.innerWidth - 16 : 660;
    const maxLeft = Math.max(8, window.innerWidth - menuWidth - 16);
    menuStyle.value = {
        top:  rect.bottom + 4 + 'px',
        // On mobile: pin to left edge with small margin
        // On desktop: align with button but prevent overflow past screen right edge
        left: isMobile ? '8px' : Math.min(Math.max(8, rect.left), maxLeft) + 'px',
    };
    megaMenuOpen.value = true; 
};
</script>

<template>
  <!-- fixed header: stays pinned to top of viewport during scroll -->
  <header class="fixed top-0 left-0 right-0 w-full z-50 bg-white dark:bg-gray-900 shadow-sm">

    <!-- ── ROW 1 : Logo | Icons | Hamburger ────────────────────── -->
    <div class="border-b dark:border-gray-800 transition-all duration-300">
      <div
        class="container mx-auto flex items-center justify-between px-3 sm:px-4 transition-all duration-300"
        :class="isScrolled ? 'h-11 sm:h-13' : 'h-16 sm:h-[72px]'"
      >

        <!-- Logo -->
        <Link href="/" class="flex-shrink-0 flex items-center gap-2 sm:gap-3 py-1 group">
          <img
            :src="$page.props.store_settings.company_logo ? '/storage/' + $page.props.store_settings.company_logo + '?v=4' : Logo"
            :alt="$page.props.store_settings.company_name || 'Skynet Digital'"
            class="object-contain transition-all duration-300 group-hover:scale-105"
            :class="isScrolled ? 'h-8 sm:h-9 max-h-[38px] w-auto max-w-[130px] sm:max-w-[160px]' : 'h-10 sm:h-12 md:h-[56px] max-h-[58px] w-auto max-w-[180px] sm:max-w-[220px]'"
          />
          <span
            v-if="!$page.props.store_settings.company_logo"
            class="font-black leading-tight hidden xs:block uppercase tracking-tight transition-all duration-300"
            :class="isScrolled ? 'text-xs sm:text-sm' : 'text-base sm:text-lg'"
            :style="{ color: 'var(--color-primary, #7B00FF)' }"
          >
            {{ $page.props.store_settings.company_name || 'SKYNET' }}<br/>
            <span v-if="$page.props.store_settings.company_tagline && !isScrolled" class="font-normal text-[11px] text-gray-500 dark:text-gray-400 capitalize block -mt-0.5">
              {{ $page.props.store_settings.company_tagline }}
            </span>
          </span>
        </Link>

        <!-- Right action icons -->
        <div class="flex items-center gap-1 sm:gap-2">
          <ThemeToggle/>

          <!-- Staff / Admin: Direct Dashboard button (Icon only on mobile, text on desktop) -->
          <template v-if="isStaff">
            <Link :href="route('admin.dashboard')"
              class="flex items-center justify-center gap-1.5 p-2 sm:px-3 sm:py-1.5 rounded-lg bg-primary text-white font-bold text-xs sm:text-sm hover:opacity-90 shadow-xs transition"
              title="Go to Management Dashboard"
              aria-label="Management Dashboard">
              <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/>
              </svg>
              <span class="hidden sm:inline">Management</span>
            </Link>
          </template>

          <!-- Logged-in customer buttons -->
          <template v-else-if="isCustomer">
            <Link :href="route('customer.profile')"
              class="hidden sm:flex flex-col items-center text-gray-600 dark:text-gray-300 hover:text-primary transition px-1"
              title="My Account">
              <svg class="w-5 h-5 mb-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
              </svg>
              <span class="text-[10px] font-medium">Account</span>
            </Link>
            <Link :href="route('customer.orders')"
              class="hidden sm:flex flex-col items-center text-gray-600 dark:text-gray-300 hover:text-primary transition px-1">
              <svg class="w-5 h-5 mb-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/>
              </svg>
              <span class="text-[10px] font-medium">Orders</span>
            </Link>
            <Link :href="route('customer.referral.index')"
              class="hidden sm:flex flex-col items-center text-gray-600 dark:text-gray-300 hover:text-primary transition px-1"
              title="Refer & Earn">
              <svg class="w-5 h-5 mb-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1"/>
              </svg>
              <span class="text-[10px] font-medium">Refer & Earn</span>
            </Link>
          </template>

          <!-- Guest: Sign in -->
          <template v-else>
            <Link :href="route('login')"
              class="hidden sm:flex flex-col items-center text-gray-600 dark:text-gray-300 hover:text-primary transition px-1">
              <svg class="w-5 h-5 mb-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5.121 17.804A13.937 13.937 0 0112 16c2.5 0 4.847.655 6.879 1.804M15 10a3 3 0 11-6 0 3 3 0 016 0zm6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
              </svg>
              <span class="text-[10px] font-medium">Sign In</span>
            </Link>
          </template>

          <!-- Wishlist button (visible for logged-in customers only) -->
          <Link v-if="wishlistEnabled && isCustomer" :href="route('wishlist.index')"
            class="flex flex-col items-center text-gray-600 dark:text-gray-300 hover:text-red-500 dark:hover:text-red-400 transition px-1"
            title="Wishlist">
            <svg class="w-5 h-5 mb-0.5"
              :class="$page.component.startsWith('Frontend/Wishlist') ? 'text-red-500 fill-red-500' : 'fill-none'"
              stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z"/>
            </svg>
            <span class="text-[10px] font-medium">Wishlist</span>
          </Link>

          <!-- Compare -->
          <Link :href="route('compare.index')"
            class="relative flex flex-col items-center text-gray-600 dark:text-gray-300 hover:text-primary transition px-1">
            <div class="relative">
              <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4"/>
              </svg>
              <span v-if="compareCount > 0"
                class="absolute -top-1.5 -right-1.5 min-w-[18px] h-[18px] px-1 flex items-center justify-center text-[10px] font-bold text-white bg-primary rounded-full ring-2 ring-white dark:ring-gray-900">
                {{ compareCount > 99 ? '99+' : compareCount }}
              </span>
            </div>
            <span class="text-[10px] font-medium mt-0.5">Compare</span>
          </Link>

          <!-- Cart -->
          <button type="button" @click="cartDrawer.open()"
            class="relative flex items-center gap-1.5 sm:gap-2 text-gray-700 dark:text-gray-200 hover:text-primary transition px-1.5 py-1 rounded-xl hover:bg-gray-100 dark:hover:bg-gray-800 cursor-pointer"
            :title="'Shopping Cart (' + cartCount + ' item' + (cartCount === 1 ? '' : 's') + ' - ' + formattedCartTotal + ')'"
            aria-label="View Shopping Cart">
            <div class="relative flex-shrink-0">
              <svg class="w-6 h-6 text-gray-700 dark:text-gray-200" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z"/>
              </svg>
              <span v-if="cartCount > 0"
                class="absolute -top-1.5 -right-1.5 min-w-[18px] h-[18px] px-1 flex items-center justify-center text-[10px] font-bold text-white bg-primary rounded-full ring-2 ring-white dark:ring-gray-900">
                {{ cartCount > 99 ? '99+' : cartCount }}
              </span>
            </div>
            <div v-if="showCartTotalHeader" class="flex flex-col text-left leading-none">
              <span class="text-[10px] text-gray-400 dark:text-gray-400 font-bold uppercase tracking-wider">Cart</span>
              <span class="text-xs sm:text-sm font-extrabold text-gray-900 dark:text-white font-mono mt-0.5 whitespace-nowrap">
                <span class="hidden sm:inline">{{ formattedCartTotal }}</span>
                <span class="sm:hidden text-[11px]">{{ compactCartTotal }}</span>
              </span>
            </div>
            <span v-else class="text-[10px] font-medium mt-0.5">Cart</span>
          </button>

          <!-- Mobile hamburger -->
          <button
            @click="toggle"
            class="ml-1 p-1 transition md:hidden cursor-pointer"
            :class="isOpen ? 'text-primary' : 'text-gray-600 dark:text-gray-300 hover:text-primary'"
            :style="isOpen ? { color: 'var(--color-primary, #ea580c)' } : {}"
            :aria-label="isOpen ? 'Close mobile menu' : 'Open mobile menu'"
          >
            <svg v-if="!isOpen" class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M4 6h16M4 12h16M4 18h16"/>
            </svg>
            <svg v-else class="w-6 h-6 transition hover:opacity-80" fill="none" stroke="currentColor" :style="{ stroke: 'var(--color-primary, #ea580c)', color: 'var(--color-primary, #ea580c)' }" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M6 18L18 6M6 6l12 12"/>
            </svg>
          </button>
        </div>
      </div>
    </div>

    <!-- ── ROW 2 : Search bar ──────────────────────────────────── -->
    <div class="bg-white dark:bg-gray-900 px-3 sm:px-4 py-2">
      <div class="container mx-auto">
        <div ref="searchRef" class="relative">
          <form @submit.prevent="submitSearch" class="flex items-center gap-0">
            <input
              v-model="search"
              @input="onInput"
              @focus="onFocus"
              type="text"
              placeholder="Search for products, brands..."
              class="flex-1 pl-4 pr-12 py-2.5 rounded-xl border border-gray-300 dark:border-gray-600 dark:bg-gray-800 dark:text-white text-sm focus:ring-2 focus:ring-primary focus:border-primary transition w-full"
              autocomplete="off"
            />
            <button type="submit"
              class="absolute right-0 top-0 bottom-0 px-4 rounded-r-xl bg-primary hover:opacity-90 transition flex items-center justify-center">
              <svg v-if="!loading" class="w-4 h-4 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
              </svg>
              <svg v-else class="w-4 h-4 text-white animate-spin" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/>
              </svg>
            </button>
          </form>

          <!-- ─── Most Searched Items Dropdown (Instant Preloaded on Focus) ─── -->
          <div
            v-if="showDropdown && search.trim().length < 2 && ((trendingSearches.products && trendingSearches.products.length > 0) || (trendingSearches.keywords && trendingSearches.keywords.length > 0))"
            class="absolute top-full left-0 right-0 mt-1.5 bg-white dark:bg-gray-800 rounded-xl shadow-2xl border border-gray-200 dark:border-gray-700 overflow-hidden z-50 p-3 sm:p-4 text-left"
          >
            <!-- Top Keywords / Popular Searches Chips -->
            <div v-if="trendingSearches.keywords && trendingSearches.keywords.length > 0" class="mb-3 pb-3 border-b border-gray-100 dark:border-gray-700">
              <div class="flex items-center gap-1.5 text-[11px] font-bold text-gray-400 dark:text-gray-400 mb-2 uppercase tracking-wider">
                <svg class="w-3.5 h-3.5 text-amber-500" fill="currentColor" viewBox="0 0 20 20">
                  <path fill-rule="evenodd" d="M12.395 2.553a1 1 0 00-1.45-.385c-.345.23-.614.558-.822.88-.316.492-.533 1.05-.726 1.62-.27.794-.533 1.696-.86 2.585-.306.83-.655 1.616-1.077 2.298-.42.678-.934 1.258-1.597 1.644a1 1 0 00-.472.932c.046.438.257.842.593 1.137.336.294.78.442 1.23.412 1.343-.09 2.59-.705 3.51-1.728.324.774.836 1.458 1.488 1.986.65.526 1.428.87 2.253.996a1 1 0 001.076-.576c.15-.38.163-.807.036-1.196-.28-.857-.75-1.636-1.365-2.268-.616-.63-1.353-1.127-2.158-1.457.25-.86.417-1.748.497-2.646.064-.716.035-1.442-.085-2.148a1 1 0 00-.573-.759z" clip-rule="evenodd" />
                </svg>
                <span>Popular Searches</span>
              </div>
              <div class="flex flex-wrap gap-1.5">
                <button
                  v-for="kw in trendingSearches.keywords"
                  :key="kw"
                  type="button"
                  @mousedown.prevent="selectKeyword(kw)"
                  class="px-2.5 py-1 rounded-full text-xs font-semibold bg-gray-100 hover:bg-primary/10 dark:bg-gray-700/60 dark:hover:bg-primary/20 text-gray-700 dark:text-gray-300 hover:text-primary transition flex items-center gap-1 cursor-pointer"
                >
                  <svg class="w-3 h-3 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                  </svg>
                  <span>{{ kw }}</span>
                </button>
              </div>
            </div>

            <!-- Top 5 Most Searched Products Header -->
            <div class="flex items-center justify-between mb-2">
              <div class="flex items-center gap-1.5 text-xs font-bold text-gray-900 dark:text-white">
                <span class="text-amber-500">🔥</span>
                <span>Most Searched Items</span>
              </div>
              <span class="text-[10px] uppercase font-black text-amber-600 dark:text-amber-400 tracking-wider">
                Top 5
              </span>
            </div>

            <!-- List of 5 Most Searched Items (Instant) -->
            <div class="space-y-1">
              <div
                v-for="(product, idx) in trendingSearches.products.slice(0, 5)"
                :key="product.id"
                @mousedown.prevent="goToProduct(product)"
                class="flex items-center gap-3 px-2.5 py-2 rounded-xl hover:bg-gray-50 dark:hover:bg-gray-700/60 cursor-pointer transition group"
              >
                <!-- Number Rank -->
                <span
                  class="w-5 h-5 rounded-full flex items-center justify-center text-[10px] font-black shrink-0"
                  :class="idx === 0 ? 'bg-amber-100 text-amber-800 dark:bg-amber-950 dark:text-amber-300' : idx === 1 ? 'bg-gray-200 text-gray-700 dark:bg-gray-700 dark:text-gray-300' : 'bg-gray-100 text-gray-500 dark:bg-gray-800 dark:text-gray-400'"
                >
                  {{ idx + 1 }}
                </span>

                <!-- Thumbnail -->
                <img
                  :src="product.image ? '/storage/' + product.image : 'https://placehold.co/48x48?text=P'"
                  class="w-9 h-9 sm:w-10 sm:h-10 object-cover rounded-lg flex-shrink-0 border border-gray-150 dark:border-gray-700 shadow-2xs"
                  :alt="product.name"
                />

                <!-- Name & Category -->
                <div class="flex-1 min-w-0">
                  <p class="text-xs sm:text-sm font-semibold text-gray-900 dark:text-white truncate group-hover:text-primary transition">
                    {{ product.name }}
                  </p>
                  <p v-if="product.category" class="text-[11px] text-gray-400 dark:text-gray-500 truncate">
                    {{ product.category.name }}
                  </p>
                </div>

                <!-- Price -->
                <div class="flex-shrink-0 text-right">
                  <span v-if="product.discount_price" class="block text-[10px] text-gray-400 line-through">
                    ₦{{ parseFloat(product.price).toLocaleString() }}
                  </span>
                  <span class="text-xs sm:text-sm font-bold text-primary">
                    ₦{{ parseFloat(product.discount_price || product.price).toLocaleString() }}
                  </span>
                </div>
              </div>
            </div>
          </div>

          <!-- ─── Typed Search Suggestions Dropdown (>= 2 chars) ─── -->
          <div v-if="showDropdown && search.trim().length >= 2 && suggestions.length > 0"
            class="absolute top-full left-0 right-0 mt-1.5 bg-white dark:bg-gray-800 rounded-xl shadow-2xl border dark:border-gray-700 overflow-hidden z-50">
            <div v-for="product in suggestions" :key="product.id"
              @mousedown.prevent="goToProduct(product)"
              class="flex items-center gap-3 px-4 py-3 hover:bg-gray-50 dark:hover:bg-gray-700 cursor-pointer border-b dark:border-gray-700 last:border-0 transition group">
              <img
                :src="product.image ? '/storage/' + product.image : 'https://placehold.co/48x48?text=P'"
                class="w-10 h-10 object-cover rounded-lg flex-shrink-0 border dark:border-gray-600"
                :alt="product.name"/>
              <div class="flex-1 min-w-0">
                <p class="text-sm font-semibold text-gray-900 dark:text-white truncate group-hover:text-primary transition">{{ product.name }}</p>
                <p v-if="product.category" class="text-xs text-gray-400 dark:text-gray-500">{{ product.category.name }}</p>
              </div>
              <div class="flex-shrink-0 text-right">
                <span v-if="product.discount_price" class="block text-xs text-gray-400 line-through">
                  ₦{{ parseFloat(product.price).toLocaleString() }}
                </span>
                <span class="text-sm font-bold text-primary">
                  ₦{{ parseFloat(product.discount_price || product.price).toLocaleString() }}
                </span>
              </div>
            </div>
            <button @mousedown.prevent="viewAll"
              class="w-full flex items-center justify-center gap-2 px-4 py-3 bg-gray-50 dark:bg-gray-900 hover:bg-primary/10 text-primary font-semibold text-sm transition cursor-pointer">
              View all results for "{{ search }}"
              <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 8l4 4m0 0l-4 4m4-4H3"/>
              </svg>
            </button>
          </div>

          <!-- No results (>= 2 chars) -->
          <div v-if="showDropdown && search.trim().length >= 2 && !loading && suggestions.length === 0"
            class="absolute top-full left-0 right-0 mt-1.5 bg-white dark:bg-gray-800 rounded-xl shadow-xl border dark:border-gray-700 px-4 py-5 text-center text-sm text-gray-500 dark:text-gray-400 z-50">
            No products found for "<strong>{{ search }}</strong>"
          </div>
        </div>
      </div>
    </div>

    <!-- ── ROW 3 : Nav bar with Categories mega menu (Filled with theme color) ─── -->
    <div class="bg-primary text-white shadow-xs">
      <div class="container mx-auto px-3 sm:px-4">
        <!-- NOTE: no overflow on this nav — mega menu must escape freely -->
        <nav class="flex items-center gap-1.5 py-1.5 scrollbar-hide text-white" style="overflow-x: auto; overflow-y: visible;">

          <!-- ☰ Categories button (no relative wrapper so overflow doesn't clip) -->
          <div id="mega-menu-wrapper" class="flex-shrink-0">
            <button
              ref="catBtnRef"
              @click="openMegaMenu"
              class="flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs sm:text-sm font-semibold transition whitespace-nowrap cursor-pointer"
              :class="megaMenuOpen
                ? 'bg-white text-primary shadow-xs font-bold'
                : 'bg-black/15 hover:bg-black/25 text-white border border-white/20'"
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
          <span class="h-4 w-px bg-white/25 mx-1 flex-shrink-0"></span>

          <!-- Home -->
          <Link :href="route('home')"
            class="flex-shrink-0 px-3 py-1.5 rounded-lg text-xs sm:text-sm font-semibold transition whitespace-nowrap"
            :class="$page.component === 'Home'
              ? 'bg-white text-primary shadow-xs font-bold'
              : 'text-white/90 hover:text-white hover:bg-white/15'">
             Home
          </Link>

          <!-- All Products -->
          <Link :href="route('shop.index')"
            class="flex-shrink-0 px-3 py-1.5 rounded-lg text-xs sm:text-sm font-semibold transition whitespace-nowrap"
            :class="$page.component.startsWith('Frontend/Products')
              ? 'bg-white text-primary shadow-xs font-bold'
              : 'text-white/90 hover:text-white hover:bg-white/15'">
             All Products
          </Link>

          <!-- Category pills — desktop only, max 3 -->
          <template v-for="cat in categories.slice(0, 3)" :key="cat.id">
            <Link
              :href="route('shop.index', { category: cat.slug })"
              class="hidden sm:inline-flex flex-shrink-0 px-3 py-1.5 rounded-lg text-xs sm:text-sm font-medium transition whitespace-nowrap text-white/90 hover:text-white hover:bg-white/15">
              {{ cat.name }}
            </Link>
          </template>

          <!-- Track Order -->
          <Link :href="route('order.track')"
            class="flex-shrink-0 ml-auto px-3 py-1.5 rounded-lg text-xs sm:text-sm font-medium transition whitespace-nowrap"
            :class="$page.component === 'Frontend/TrackOrder'
              ? 'bg-white text-primary shadow-xs font-bold'
              : 'text-white/90 hover:text-white hover:bg-white/15'">
            📦 Track Order
          </Link>

          <!-- Nav Bar Live Cart Total Pill (Row 3 End - Compact: Icon + Amount) -->
          <button
            v-if="showCartTotalHeader"
            type="button"
            @click="cartDrawer.open()"
            class="hidden md:inline-flex items-center gap-1.5 flex-shrink-0 px-2.5 py-1.5 rounded-lg text-xs sm:text-sm font-bold transition whitespace-nowrap bg-black/20 hover:bg-black/30 text-white border border-white/25 shadow-xs cursor-pointer ml-2 group"
            :title="'Open Cart Drawer (' + cartCount + ' item' + (cartCount === 1 ? '' : 's') + ' - ' + formattedCartTotal + ')'"
            aria-label="Open Cart Drawer">
            <div class="relative flex items-center">
              <svg class="w-4 h-4 transition group-hover:scale-110" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z"/>
              </svg>
              <span v-if="cartCount > 0" class="ml-1 px-1.5 py-0.2 rounded-full bg-white text-primary text-[10px] font-black leading-none">
                {{ cartCount }}
              </span>
            </div>
            <span class="font-extrabold text-amber-300 font-mono">{{ formattedCartTotal }}</span>
          </button>
        </nav>
      </div>
    </div>

    <!-- ── Mobile Drawer ────────────────────────────────────────── -->
    <transition name="slide">
      <div v-if="isOpen" class="md:hidden bg-white dark:bg-gray-900 shadow-lg border-t dark:border-gray-800">
        <ul class="px-4 py-3 space-y-1 max-h-[70vh] overflow-y-auto">
          <li>
            <Link :href="route('home')" @click="toggle"
              class="flex items-center gap-2 px-3 py-2.5 rounded-lg hover:bg-gray-100 dark:hover:bg-gray-800 transition font-medium">Home</Link>
          </li>
          <li>
            <Link :href="route('shop.index')" @click="toggle"
              class="flex items-center gap-2 px-3 py-2.5 rounded-lg hover:bg-gray-100 dark:hover:bg-gray-800 transition font-medium">All Products</Link>
          </li>
          <li class="text-xs font-semibold text-gray-400 uppercase px-3 pt-2">Categories</li>

          <!-- Mobile category with accordion for subcats (Shows 3 first, with See All toggle) -->
          <template v-for="cat in displayedMobileCats" :key="cat.id">
            <li>
              <div class="flex items-center">
                <Link :href="route('shop.index', { category: cat.slug })" @click="toggle"
                  class="flex-1 flex items-center gap-2 px-3 py-2 rounded-lg hover:bg-gray-100 dark:hover:bg-gray-800 transition text-sm text-gray-700 dark:text-gray-300 font-medium">
                  {{ cat.name }}
                </Link>
                <!-- Expand button if has subcats -->
                <button v-if="cat.children?.length"
                  @click.stop="toggleMobileCat(cat.id)"
                  class="px-2 py-2 text-gray-500 dark:text-gray-400 hover:text-primary">
                  <svg class="w-4 h-4 transition-transform" :class="openMobileCat === cat.id ? 'rotate-180' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                  </svg>
                </button>
              </div>
              <!-- Subcategories accordion -->
              <ul v-if="cat.children?.length && openMobileCat === cat.id" class="pl-6 mt-1 space-y-0.5 border-l-2 border-primary/30 ml-5">
                <li v-for="sub in cat.children" :key="sub.id">
                  <Link :href="route('shop.index', { category: sub.slug })" @click="toggle"
                    class="block px-3 py-1.5 rounded-lg text-sm text-gray-600 dark:text-gray-400 hover:bg-gray-100 dark:hover:bg-gray-800 hover:text-primary transition">
                    {{ sub.name }}
                  </Link>
                </li>
              </ul>
            </li>
          </template>

          <!-- Toggle See All / Show Fewer Categories button -->
          <li v-if="categories.length > 3">
            <button
              type="button"
              @click="showAllMobileCats = !showAllMobileCats"
              class="w-full flex items-center justify-between px-3 py-2 rounded-lg text-xs font-bold text-primary hover:bg-primary/10 transition cursor-pointer"
            >
              <span class="flex items-center gap-1.5">
                <svg class="w-3.5 h-3.5 transition-transform" :class="showAllMobileCats ? 'rotate-180' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M19 9l-7 7-7-7"/>
                </svg>
                {{ showAllMobileCats ? 'Show Fewer Categories' : 'See All Categories (' + categories.length + ')' }}
              </span>
              <span class="text-[10px] uppercase font-bold text-gray-400">
                {{ showAllMobileCats ? 'Collapse' : 'Expand' }}
              </span>
            </button>
          </li>

          <li class="border-t dark:border-gray-700 pt-2 mt-1">
            <button type="button" @click="() => { toggle(); cartDrawer.open(); }"
              class="w-full flex items-center justify-between gap-2 px-3 py-2.5 rounded-lg hover:bg-gray-100 dark:hover:bg-gray-800 transition font-medium text-left">
              <span class="flex items-center gap-2">
                <svg class="w-5 h-5 text-primary" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z"/>
                </svg>
                Cart
              </span>
              <div class="flex items-center gap-2">
                <span v-if="showCartTotalHeader" class="font-mono font-bold text-xs text-primary">{{ formattedCartTotal }}</span>
                <span v-if="cartCount > 0" class="bg-primary text-white text-xs font-bold px-2 py-0.5 rounded-full">{{ cartCount }}</span>
              </div>
            </button>
          </li>

          <!-- Wishlist link (visible for logged-in customers only) -->
          <li v-if="wishlistEnabled && isCustomer">
            <Link :href="route('wishlist.index')" @click="toggle"
              class="flex items-center gap-2 px-3 py-2.5 rounded-lg hover:bg-gray-100 dark:hover:bg-gray-800 transition font-medium">❤️ Wishlist</Link>
          </li>

          <template v-if="isCustomer">
            <li>
              <Link :href="route('customer.profile')" @click="toggle"
                class="flex items-center gap-2 px-3 py-2.5 rounded-lg hover:bg-gray-100 dark:hover:bg-gray-800 transition font-medium">👤 My Profile</Link>
            </li>
            <li>
              <Link :href="route('customer.addresses')" @click="toggle"
                class="flex items-center gap-2 px-3 py-2.5 rounded-lg hover:bg-gray-100 dark:hover:bg-gray-800 transition font-medium">📍 Address Book</Link>
            </li>
            <li>
              <Link :href="route('customer.orders')" @click="toggle"
                class="flex items-center gap-2 px-3 py-2.5 rounded-lg hover:bg-gray-100 dark:hover:bg-gray-800 transition font-medium">📦 My Orders</Link>
            </li>
            <li>
              <Link :href="route('customer.referral.index')" @click="toggle"
                class="flex items-center gap-2 px-3 py-2.5 rounded-lg hover:bg-gray-100 dark:hover:bg-gray-800 transition font-medium">🤝 Referral & Earn</Link>
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
            <li>
              <Link :href="route('admin.dashboard')" @click="toggle"
                class="flex items-center gap-2 px-3 py-2.5 rounded-lg text-primary font-bold hover:bg-primary/10 transition">
                <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/>
                </svg>
                Management Dashboard
              </Link>
            </li>
            <li>
              <Link :href="route('admin.logout')" method="post" as="button" @click="toggle"
                class="w-full flex items-center gap-2 px-3 py-2.5 rounded-lg text-red-600 hover:bg-red-50 dark:hover:bg-red-900/20 transition font-medium">
                🚪 Logout Admin
              </Link>
            </li>
          </template>
          <template v-else>
            <li>
              <Link :href="route('login')" @click="toggle"
                class="flex items-center gap-2 px-3 py-2.5 rounded-lg hover:bg-gray-100 dark:hover:bg-gray-800 transition font-medium text-primary">👤 Sign In</Link>
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

  <!-- In-flow spacer matching exact height of Row 1 + Row 2 + Row 3 -->
  <div class="h-[164px] sm:h-[174px] w-full shrink-0" aria-hidden="true"></div>

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
        class="fixed z-[100] bg-white dark:bg-gray-900 rounded-xl shadow-2xl border border-gray-200 dark:border-gray-800 overflow-y-auto cat-dropdown-scroll"
        :style="{
            top: menuStyle.top,
            left: menuStyle.left,
            // Compact width on desktop (660px) and full-width minus margins on mobile
            width: 'min(calc(100vw - 16px), 660px)',
            // Constrain height so it is compact and user scrolls smoothly
            maxHeight: 'min(calc(100vh - 190px), 340px)',
        }"
        @click.stop>

        <!-- Header on mobile -->
        <div class="flex items-center justify-between px-3.5 py-2.5 border-b border-gray-100 dark:border-gray-800 sm:hidden">
          <span class="font-bold text-xs text-gray-800 dark:text-white flex items-center gap-1.5">
            <span>☰</span> All Categories ({{ categories.length }})
          </span>
          <button @click="megaMenuOpen = false"
            class="text-primary hover:opacity-80 p-1 rounded transition"
            aria-label="Close categories menu">
            <svg class="w-4 h-4 text-primary" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M6 18L18 6M6 6l12 12"/>
            </svg>
          </button>
        </div>

        <!-- Responsive compact grid:
             mobile  : 1 col
             sm      : 2 col
             lg+     : 3 col -->
        <div class="p-3 sm:p-4 grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-2.5 sm:gap-3.5">

          <div v-for="cat in categories" :key="cat.id"
            class="group p-2 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-800/60 transition sm:border-0 border-b border-gray-100 dark:border-gray-800 pb-2 sm:pb-2 last:border-0">

            <!-- Parent category header -->
            <Link
              :href="route('shop.index', { category: cat.slug })"
              @click="megaMenuOpen = false"
              class="flex items-center gap-1.5 font-bold text-gray-900 dark:text-white text-xs sm:text-sm group-hover:text-primary transition mb-0.5">
              <span class="w-5 h-5 bg-primary/10 text-primary rounded flex items-center justify-center text-[10px] flex-shrink-0">📁</span>
              <span class="truncate">{{ cat.name }}</span>
            </Link>

            <!-- Subcategories -->
            <ul v-if="cat.children?.length" class="space-y-0.5 mb-1 border-l-2 border-gray-200 dark:border-gray-700 pl-2 ml-1">
              <li v-for="sub in cat.children" :key="sub.id">
                <Link
                  :href="route('shop.index', { category: sub.slug })"
                  @click="megaMenuOpen = false"
                  class="text-[11px] sm:text-xs text-gray-500 dark:text-gray-400 hover:text-primary transition block py-0.5 truncate">
                  {{ sub.name }}
                </Link>
              </li>
            </ul>

            <!-- View all -->
            <Link
              :href="route('shop.index', { category: cat.slug })"
              @click="megaMenuOpen = false"
              class="text-[11px] text-primary font-semibold hover:underline inline-flex items-center gap-0.5">
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

/* Custom smooth scrollbar for category dropdown */
.cat-dropdown-scroll {
  scrollbar-width: thin;
  scrollbar-color: rgba(156, 163, 175, 0.45) transparent;
}
.cat-dropdown-scroll::-webkit-scrollbar {
  width: 6px;
}
.cat-dropdown-scroll::-webkit-scrollbar-track {
  background: transparent;
}
.cat-dropdown-scroll::-webkit-scrollbar-thumb {
  background: rgba(156, 163, 175, 0.4);
  border-radius: 9999px;
}
.cat-dropdown-scroll::-webkit-scrollbar-thumb:hover {
  background: rgba(156, 163, 175, 0.7);
}
</style>
