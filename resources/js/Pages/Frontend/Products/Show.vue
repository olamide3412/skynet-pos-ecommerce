<script setup>
import { Head, Link, useForm, usePage, router } from '@inertiajs/vue3';
import Layout from '@/Layouts/Layout.vue';
import StorefrontBreadcrumb from '@/Components/StorefrontBreadcrumb.vue';
import { ref, computed, watch } from 'vue';
import { useToast } from 'vue-toastification';

const props = defineProps({
    product: Object,
    related: Array,
    is_wishlisted: {
        type: Boolean,
        default: false,
    },
    reviews: {
        type: Array,
        default: () => [],
    },
    average_rating: {
        type: Number,
        default: 5.0,
    },
    reviews_count: {
        type: Number,
        default: 0,
    },
    user_review: {
        type: Object,
        default: null,
    },
});

const page = usePage();
const toast = useToast();

const reviewsEnabled = computed(() => page.props.store_settings?.reviews_enabled === '1');
const reviewForm = useForm({
    rating: props.user_review?.rating || 5,
    comment: props.user_review?.comment || '',
});

const submitReview = () => {
    reviewForm.post(route('products.reviews.store', props.product.id), {
        preserveScroll: true,
    });
};

const wishlistEnabled = computed(() => page.props.store_settings?.wishlist_enabled === '1');
const isWishlisted = ref(props.is_wishlisted);
const togglingWishlist = ref(false);

const selectedVariant = ref(null);
const selectedPosUnit = ref(
    props.product.pos_units && props.product.pos_units.length > 0 
        ? (props.product.pos_units.find(u => u.unit_kind === 'base') || props.product.pos_units[0]) 
        : null
);

const allImages = computed(() => {
    let imgs = [];
    if (props.product.image) imgs.push(props.product.image);
    if (props.product.images && Array.isArray(props.product.images)) {
        imgs.push(...props.product.images);
    }
    return imgs;
});

const activeImage = ref(allImages.value.length ? allImages.value[0] : null);

const form = useForm({
    product_id: props.product.id,
    quantity: 1,
    variant: null,
    variant_key: null,
    product_variant_mapping_id: null,
    product_pos_unit_id: selectedPosUnit.value ? selectedPosUnit.value.id : null,
});

const availableVariants = computed(() => {
    if (props.product.variant_mappings && props.product.variant_mappings.length > 0) {
        return props.product.variant_mappings.map(m => {
            const isPos = m.inventory_source === 'pos' && !!m.pos_item_id;
            const stock = isPos && m.synced_pos_item
                ? Number(m.synced_pos_item.effective_availability)
                : (m.local_stock !== null && m.local_stock !== undefined ? Number(m.local_stock) : 0);
            const price = isPos && m.synced_pos_item
                ? parseFloat(m.synced_pos_item.standard_price)
                : (m.local_price !== null && m.local_price !== undefined ? parseFloat(m.local_price) : parseFloat(props.product.price));

            return {
                id: m.id,
                name: m.variant_label,
                variant_key: m.variant_key,
                price: price,
                stock: stock,
                inventory_source: m.inventory_source,
                pos_item_id: m.pos_item_id,
                is_pos: isPos,
            };
        });
    }

    if (props.product.variants && Array.isArray(props.product.variants)) {
        return props.product.variants.map(v => ({
            id: v.id || null,
            name: v.name,
            variant_key: v.key || null,
            price: v.price ? parseFloat(v.price) : null,
            stock: v.stock !== undefined && v.stock !== null ? Number(v.stock) : null,
            inventory_source: v.inventory_source || 'ecommerce',
            pos_item_id: v.pos_item_id || null,
            is_pos: v.inventory_source === 'pos' && !!v.pos_item_id,
        }));
    }

    return [];
});

watch(selectedVariant, (newVal) => {
    form.variant = newVal ? newVal.name : null;
    form.variant_key = newVal ? (newVal.variant_key || null) : null;
    form.product_variant_mapping_id = newVal ? (newVal.id || null) : null;
    if (form.quantity > maxStock.value && maxStock.value > 0) {
        form.quantity = maxStock.value;
    }
});

watch(selectedPosUnit, (newVal) => {
    form.product_pos_unit_id = newVal ? newVal.id : null;
    if (form.quantity > maxStock.value && maxStock.value > 0) {
        form.quantity = maxStock.value;
    }
});

const displayPrice = computed(() => {
    if (selectedPosUnit.value && selectedPosUnit.value.unit_kind !== 'base' && parseFloat(selectedPosUnit.value.synced_standard_price) > 0) {
        return parseFloat(selectedPosUnit.value.synced_standard_price);
    }
    if (selectedVariant.value && selectedVariant.value.price) {
        return parseFloat(selectedVariant.value.price);
    }
    if (props.product.is_on_flash_sale && props.product.special_price) {
        return parseFloat(props.product.special_price);
    }
    return props.product.discount_price 
        ? parseFloat(props.product.discount_price) 
        : parseFloat(props.product.price);
});

const maxStock = computed(() => {
    let baseStock = parseInt(props.product.stock || 0);
    if (selectedVariant.value && selectedVariant.value.stock !== undefined) {
        baseStock = parseInt(selectedVariant.value.stock);
    }
    if (selectedPosUnit.value && parseFloat(selectedPosUnit.value.quantity_in_base_units) > 1) {
        const factor = parseFloat(selectedPosUnit.value.quantity_in_base_units);
        return Math.max(0, Math.floor(baseStock / factor));
    }
    return Math.max(0, baseStock);
});

const isPurchasable = computed(() => {
    if (selectedVariant.value && selectedVariant.value.is_pos) {
        return maxStock.value > 0;
    }
    if (props.product.inventory_source === 'pos') {
        if (['missing_pos_item', 'inactive_pos_item', 'mapping_sync_failed'].includes(props.product.pos_sync_status)) {
            return false;
        }
    }
    return maxStock.value > 0;
});

const stockDisplayMode = computed(() => page.props.store_settings?.stock_display_mode || 'threshold_only');
const lowStockThreshold = computed(() => {
    const val = parseInt(page.props.store_settings?.low_stock_threshold);
    return isNaN(val) ? 5 : val;
});

const stockBadge = computed(() => {
    if (!isPurchasable.value || maxStock.value <= 0) {
        return {
            text: 'Out of Stock',
            class: 'bg-red-100 dark:bg-red-900/30 text-red-800 dark:text-red-300 border-red-200 dark:border-red-800 font-semibold',
        };
    }

    if (stockDisplayMode.value === 'threshold_only') {
        if (maxStock.value <= lowStockThreshold.value) {
            return {
                text: `Only ${maxStock.value} left in stock`,
                class: 'bg-amber-100 dark:bg-amber-900/40 text-amber-800 dark:text-amber-300 border-amber-300 dark:border-amber-700 animate-pulse font-bold',
            };
        }
        return {
            text: 'In Stock',
            class: 'bg-green-100 dark:bg-green-900/40 text-green-800 dark:text-green-300 border-green-200 dark:border-green-800 font-semibold',
        };
    }

    if (stockDisplayMode.value === 'always') {
        if (props.product.show_stock_level) {
            return {
                text: `In Stock (${maxStock.value})`,
                class: 'bg-green-100 dark:bg-green-900/40 text-green-800 dark:text-green-300 border-green-200 dark:border-green-800 font-semibold',
            };
        }
        return {
            text: 'In Stock',
            class: 'bg-green-100 dark:bg-green-900/40 text-green-800 dark:text-green-300 border-green-200 dark:border-green-800 font-semibold',
        };
    }

    // hidden / default
    return {
        text: 'In Stock',
        class: 'bg-green-100 dark:bg-green-900/40 text-green-800 dark:text-green-300 border-green-200 dark:border-green-800 font-semibold',
    };
});

const addToCart = () => {
    if (!isPurchasable.value) return;
    form.post(route('cart.store'), {
        preserveScroll: true,
    });
};

const comparing = ref(false);
const addToCompare = () => {
    comparing.value = true;
    useForm({ product_id: props.product.id }).post(route('compare.store'), {
        preserveScroll: true,
        onError: () => toast.error('Could not add to compare list.'),
        onFinish: () => { comparing.value = false; },
    });
};

const buyNow = () => {
    form.post(route('cart.store'), {
        preserveScroll: true,
        onSuccess: () => {
            router.get(route('checkout.index'));
        },
    });
};

const toggleWishlist = () => {
    if (!page.props.auth.customer) {
        router.visit(route('login'));
        return;
    }
    togglingWishlist.value = true;
    useForm({ product_id: props.product.id }).post(route('wishlist.toggle'), {
        preserveScroll: true,
        onSuccess: () => {
            isWishlisted.value = !isWishlisted.value;
        },
        onError: () => toast.error('Could not update wishlist.'),
        onFinish: () => { togglingWishlist.value = false; },
    });
};

// ─── Share Product ───────────────────────────────────────────
const showShareMenu = ref(false);
const copiedLink    = ref(false);

const shareData = computed(() => {
    const url = typeof window !== 'undefined' ? window.location.href : '';
    const title = props.product?.name || 'Product';
    const company = page.props.store_settings?.company_name || 'Skynet';
    const text = `Check out ${title} on ${company}!`;
    return { url, title, text };
});

const handleShare = async () => {
    // If mobile or browser supports native Web Share API
    if (typeof navigator !== 'undefined' && navigator.share) {
        try {
            await navigator.share({
                title: shareData.value.title,
                text: shareData.value.text,
                url: shareData.value.url,
            });
            return;
        } catch (err) {
            // Dismissed native share
            if (err.name === 'AbortError') return;
        }
    }
    // Fallback menu on desktop
    showShareMenu.value = !showShareMenu.value;
};

const copyShareLink = async () => {
    try {
        if (navigator.clipboard) {
            await navigator.clipboard.writeText(shareData.value.url);
        } else {
            const tempInput = document.createElement('input');
            tempInput.value = shareData.value.url;
            document.body.appendChild(tempInput);
            tempInput.select();
            document.execCommand('copy');
            document.body.removeChild(tempInput);
        }
        copiedLink.value = true;
        toast.success('Product link copied to clipboard!');
        setTimeout(() => {
            copiedLink.value = false;
        }, 2200);
    } catch (e) {
        toast.error('Could not copy link.');
    }
};

const shareTo = (platform) => {
    const url  = encodeURIComponent(shareData.value.url);
    const text = encodeURIComponent(shareData.value.text);
    let target = '';

    switch (platform) {
        case 'whatsapp':
            target = `https://api.whatsapp.com/send?text=${text}%20${url}`;
            break;
        case 'facebook':
            target = `https://www.facebook.com/sharer/sharer.php?u=${url}`;
            break;
        case 'twitter':
            target = `https://twitter.com/intent/tweet?url=${url}&text=${text}`;
            break;
        case 'telegram':
            target = `https://t.me/share/url?url=${url}&text=${text}`;
            break;
    }

    if (target) {
        window.open(target, '_blank', 'noopener,noreferrer,width=600,height=500');
        showShareMenu.value = false;
    }
};

const breadcrumbs = computed(() => {
    const crumbs = [
        { label: 'All Products', url: route('shop.index') }
    ];
    if (props.product.category) {
        crumbs.push({ label: props.product.category.name, url: route('shop.index', { category: props.product.category.slug }) });
    }
    crumbs.push({ label: props.product.name });
    return crumbs;
});

// SEO & Structured Data
const pageUrl = computed(() => {
    if (typeof window !== 'undefined') return window.location.href;
    return route('shop.show', props.product.url_key);
});

const seoTitle = computed(() => {
    const store = page.props.store_settings?.company_name || 'Skynet';
    return props.product.meta_title ? `${props.product.meta_title} | ${store}` : `${props.product.name} | ${store}`;
});

const seoDescription = computed(() => {
    if (props.product.meta_description) return props.product.meta_description;
    if (props.product.description) {
        return props.product.description.replace(/<[^>]*>?/gm, '').substring(0, 160).trim();
    }
    return `Buy ${props.product.name} online at best price in Nigeria. Fast nationwide delivery.`;
});

const seoImage = computed(() => {
    const img = props.product.image || activeImage.value;
    if (!img) return '';
    if (img.startsWith('http')) return img;
    const origin = typeof window !== 'undefined' ? window.location.origin : (page.props.app_url || '');
    return `${origin}/storage/${img}`;
});

const structuredData = computed(() => {
    const schema = {
        '@context': 'https://schema.org/',
        '@type': 'Product',
        'name': props.product.name,
        'image': seoImage.value ? [seoImage.value] : [],
        'description': seoDescription.value,
        'sku': props.product.sku || props.product.product_number || `SKU-${props.product.id}`,
        'offers': {
            '@type': 'Offer',
            'url': pageUrl.value,
            'priceCurrency': 'NGN',
            'price': Number(props.product.discount_price > 0 ? props.product.discount_price : props.product.price),
            'availability': isPurchasable.value ? 'https://schema.org/InStock' : 'https://schema.org/OutOfStock',
            'itemCondition': 'https://schema.org/NewCondition'
        }
    };
    if (props.product.brand?.name) {
        schema.brand = {
            '@type': 'Brand',
            'name': props.product.brand.name
        };
    }
    return JSON.stringify(schema);
});
</script>

<template>
    <Head>
        <title>{{ seoTitle }}</title>
        <meta name="description" :content="seoDescription" />
        <meta v-if="product.meta_keywords" name="keywords" :content="product.meta_keywords" />
        <link rel="canonical" :href="pageUrl" />

        <!-- Open Graph / Social Media -->
        <meta property="og:type" content="product" />
        <meta property="og:title" :content="seoTitle" />
        <meta property="og:description" :content="seoDescription" />
        <meta property="og:url" :content="pageUrl" />
        <meta v-if="seoImage" property="og:image" :content="seoImage" />
        <meta property="product:price:amount" :content="String(product.discount_price > 0 ? product.discount_price : product.price)" />
        <meta property="product:price:currency" content="NGN" />
        <meta property="product:availability" :content="isPurchasable ? 'in stock' : 'out of stock'" />

        <!-- Twitter Card -->
        <meta name="twitter:card" content="summary_large_image" />
        <meta name="twitter:title" :content="seoTitle" />
        <meta name="twitter:description" :content="seoDescription" />
        <meta v-if="seoImage" name="twitter:image" :content="seoImage" />

        <!-- JSON-LD Structured Data for Rich Snippets -->
        <component is="script" type="application/ld+json">{{ structuredData }}</component>
    </Head>
    <Layout>
        <div class="container mx-auto px-4 pt-4 pb-8 text-black dark:text-white">
            <StorefrontBreadcrumb :items="breadcrumbs" />
            <div class="flex flex-col md:flex-row gap-8 bg-white dark:bg-gray-800 p-6 rounded-lg shadow-sm border dark:border-gray-700">
                <div class="w-full md:w-1/2">
                    <!-- Main Product Image (Full view, no cropping or excessive zoom) -->
                    <div class="mb-4 bg-white dark:bg-gray-900/60 rounded-xl p-4 sm:p-6 flex items-center justify-center border border-gray-100 dark:border-gray-700/80 shadow-xs h-[320px] sm:h-[420px] md:h-[480px] overflow-hidden">
                        <img
                            :src="activeImage ? '/storage/' + activeImage : 'https://placehold.co/600x600?text=' + product.name"
                            :alt="product.name"
                            class="max-w-full max-h-full w-auto h-auto object-contain transition-transform duration-300 hover:scale-105"
                        />
                    </div>
                    <!-- Thumbnails -->
                    <div v-if="allImages.length > 1" class="flex gap-2 overflow-x-auto pb-2 scrollbar-hide">
                        <button 
                            v-for="(img, idx) in allImages" 
                            :key="idx" 
                            @click="activeImage = img"
                            class="flex-shrink-0 w-16 h-16 sm:w-20 sm:h-20 rounded-lg overflow-hidden border-2 bg-white dark:bg-gray-900/60 p-1 flex items-center justify-center transition-all"
                            :class="activeImage === img ? 'border-primary ring-2 ring-primary/30' : 'border-gray-200 dark:border-gray-700 hover:border-gray-300 dark:hover:border-gray-600'"
                        >
                            <img :src="'/storage/' + img" :alt="product.name + ' thumbnail ' + (idx + 1)" class="max-w-full max-h-full w-auto h-auto object-contain" />
                        </button>
                    </div>
                </div>
                
                <div class="w-full md:w-1/2 flex flex-col justify-center">
                    <!-- Title + Actions (Share & Wishlist) -->
                    <div class="flex items-start justify-between gap-3 mb-2">
                        <h1 class="text-3xl font-bold flex-1">{{ product.name }}</h1>

                        <div class="flex items-center gap-2 shrink-0">
                            <!-- Share Button & Popover Menu -->
                            <div class="relative">
                                <button
                                    type="button"
                                    @click="handleShare"
                                    title="Share this product"
                                    class="w-11 h-11 rounded-full border-2 border-gray-200 dark:border-gray-600 bg-white dark:bg-gray-700 hover:bg-gray-50 dark:hover:bg-gray-600 text-gray-600 dark:text-gray-300 flex items-center justify-center transition-all hover:scale-110 shadow-xs focus:outline-none"
                                >
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.684 13.342C8.886 12.938 9 12.482 9 12c0-.482-.114-.938-.316-1.342m0 2.684a3 3 0 110-2.684m0 2.684l6.632 3.316m-6.632-6l6.632-3.316m0 0a3 3 0 105.367-2.684 3 3 0 00-5.367 2.684zm0 9.316a3 3 0 105.368 2.684 3 3 0 00-5.368-2.684z" />
                                    </svg>
                                </button>

                                <!-- Dropdown Backdrop -->
                                <div
                                    v-if="showShareMenu"
                                    @click="showShareMenu = false"
                                    class="fixed inset-0 z-30"
                                ></div>

                                <!-- Share Menu Popover -->
                                <div
                                    v-if="showShareMenu"
                                    class="absolute right-0 mt-2 w-56 bg-white dark:bg-gray-800 rounded-xl shadow-xl border border-gray-200 dark:border-gray-700 py-2 z-40"
                                >
                                    <div class="px-3 py-1.5 border-b border-gray-100 dark:border-gray-700 text-[11px] font-bold text-gray-400 uppercase tracking-wider">
                                        Share Product
                                    </div>

                                    <!-- Copy Link -->
                                    <button
                                        type="button"
                                        @click="copyShareLink"
                                        class="w-full px-3 py-2 text-left text-xs font-semibold flex items-center gap-2.5 hover:bg-gray-50 dark:hover:bg-gray-700 text-gray-700 dark:text-gray-200 transition"
                                    >
                                        <svg v-if="!copiedLink" class="w-4 h-4 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 5H6a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2v-1M8 5a2 2 0 002 2h2a2 2 0 002-2M8 5a2 2 0 012-2h2a2 2 0 012 2m0 0h2a2 2 0 012 2v3m2 4H10m0 0l3-3m-3 3l3 3" />
                                        </svg>
                                        <svg v-else class="w-4 h-4 text-emerald-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7" />
                                        </svg>
                                        <span :class="copiedLink ? 'text-emerald-600 dark:text-emerald-400' : ''">
                                            {{ copiedLink ? 'Copied to clipboard!' : 'Copy Link' }}
                                        </span>
                                    </button>

                                    <!-- WhatsApp -->
                                    <button
                                        type="button"
                                        @click="shareTo('whatsapp')"
                                        class="w-full px-3 py-2 text-left text-xs font-semibold flex items-center gap-2.5 hover:bg-gray-50 dark:hover:bg-gray-700 text-gray-700 dark:text-gray-200 transition"
                                    >
                                        <span class="w-5 h-5 rounded-full bg-emerald-500 text-white flex items-center justify-center text-[10px] font-bold">💬</span>
                                        <span>WhatsApp</span>
                                    </button>

                                    <!-- X / Twitter -->
                                    <button
                                        type="button"
                                        @click="shareTo('twitter')"
                                        class="w-full px-3 py-2 text-left text-xs font-semibold flex items-center gap-2.5 hover:bg-gray-50 dark:hover:bg-gray-700 text-gray-700 dark:text-gray-200 transition"
                                    >
                                        <span class="w-5 h-5 rounded-full bg-black text-white flex items-center justify-center text-[10px] font-bold">𝕏</span>
                                        <span>X (Twitter)</span>
                                    </button>

                                    <!-- Facebook -->
                                    <button
                                        type="button"
                                        @click="shareTo('facebook')"
                                        class="w-full px-3 py-2 text-left text-xs font-semibold flex items-center gap-2.5 hover:bg-gray-50 dark:hover:bg-gray-700 text-gray-700 dark:text-gray-200 transition"
                                    >
                                        <span class="w-5 h-5 rounded-full bg-blue-600 text-white flex items-center justify-center text-[10px] font-bold">f</span>
                                        <span>Facebook</span>
                                    </button>

                                    <!-- Telegram -->
                                    <button
                                        type="button"
                                        @click="shareTo('telegram')"
                                        class="w-full px-3 py-2 text-left text-xs font-semibold flex items-center gap-2.5 hover:bg-gray-50 dark:hover:bg-gray-700 text-gray-700 dark:text-gray-200 transition"
                                    >
                                        <span class="w-5 h-5 rounded-full bg-sky-500 text-white flex items-center justify-center text-[10px] font-bold">✈</span>
                                        <span>Telegram</span>
                                    </button>
                                </div>
                            </div>

                            <!-- Wishlist Heart (shown when enabled) -->
                            <button
                                v-if="wishlistEnabled"
                                @click="toggleWishlist"
                                :disabled="togglingWishlist"
                                :title="isWishlisted ? 'Remove from wishlist' : 'Save to wishlist'"
                                class="flex-shrink-0 w-11 h-11 rounded-full border-2 flex items-center justify-center transition-all hover:scale-110 disabled:opacity-60"
                                :class="isWishlisted
                                    ? 'border-red-400 bg-red-50 dark:bg-red-900/20'
                                    : 'border-gray-200 dark:border-gray-600 bg-white dark:bg-gray-700'">
                                <svg class="w-6 h-6 transition-colors"
                                    :class="isWishlisted ? 'text-red-500 fill-red-500' : 'text-gray-400 fill-none'"
                                    stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z"/>
                                </svg>
                            </button>
                        </div>
                    </div>

                    <div class="mb-4 flex flex-wrap items-center gap-4 text-sm text-gray-500 dark:text-gray-400">
                        <div>
                            Category: <Link :href="route('shop.index', {category: product.category.slug})" class="text-primary hover:underline font-medium">{{ product.category.name }}</Link>
                        </div>
                        <div v-if="product.brand" class="flex items-center gap-1.5">
                            <span>•</span>
                            <span>Brand:</span>
                            <Link :href="route('shop.brand', product.brand.slug)" class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full bg-gray-100 dark:bg-gray-700 text-gray-800 dark:text-gray-200 hover:bg-primary/10 hover:text-primary font-semibold transition">
                                <img v-if="product.brand.logo_url" :src="product.brand.logo_url" :alt="product.brand.name" class="w-4 h-4 rounded-full object-contain" />
                                <span>{{ product.brand.name }}</span>
                            </Link>
                        </div>
                    </div>

                    <!-- Star Rating Summary -->
                    <div v-if="reviewsEnabled" class="flex items-center gap-2 mb-4">
                        <div class="flex items-center text-amber-400">
                            <svg v-for="star in 5" :key="star" class="w-4 h-4 fill-current" :class="star <= Math.round(average_rating) ? 'text-amber-400' : 'text-gray-300 dark:text-gray-600'" viewBox="0 0 20 20">
                                <path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z" />
                            </svg>
                        </div>
                        <span class="text-sm font-bold text-gray-800 dark:text-gray-200">{{ Number(average_rating).toFixed(1) }}</span>
                        <a href="#customer-reviews" class="text-xs text-primary hover:underline">
                            ({{ reviews_count }} {{ reviews_count === 1 ? 'customer review' : 'customer reviews' }})
                        </a>
                    </div>
                    
                    <div class="flex items-center flex-wrap gap-4 mb-4">
                        <div class="flex flex-col">
                            <span v-if="(props.product.is_on_flash_sale || props.product.discount_price) && (!selectedVariant || !selectedVariant.price) && (!selectedPosUnit || selectedPosUnit.unit_kind === 'base')"
                                  class="text-sm font-medium text-gray-400 line-through">
                                ₦{{ parseFloat(product.price).toLocaleString() }}
                            </span>
                            <span class="text-3xl font-black text-primary">₦{{ displayPrice.toLocaleString() }}</span>
                        </div>
                        <span v-if="props.product.is_on_flash_sale && (!selectedPosUnit || selectedPosUnit.unit_kind === 'base')"
                              class="px-3 py-1 bg-amber-500 text-white text-xs font-black rounded-full shadow-xs animate-pulse flex items-center gap-1">
                            ⚡ FLASH DEAL
                        </span>
                        <span v-else-if="product.discount_price && (!selectedPosUnit || selectedPosUnit.unit_kind === 'base')"
                              class="px-3 py-1 bg-red-500 text-white text-xs font-bold rounded-full shadow-xs">
                            SALE
                        </span>

                        <span :class="['px-3 py-1 text-sm rounded-full border shadow-xs transition-all', stockBadge.class]">
                            {{ stockBadge.text }}
                        </span>
                    </div>

                    <!-- POS Selling Units Selection -->
                    <div v-if="product.pos_units && product.pos_units.length > 0" class="mb-5 bg-gray-50 dark:bg-gray-900 border dark:border-gray-700 p-4 rounded-lg">
                        <label class="block text-sm font-bold text-gray-700 dark:text-gray-300 mb-2">Selling Unit</label>
                        <div class="flex flex-wrap gap-2">
                            <button 
                                v-for="u in product.pos_units" 
                                :key="u.id"
                                type="button"
                                @click="selectedPosUnit = u"
                                :class="selectedPosUnit?.id === u.id ? 'ring-2 ring-primary bg-primary/10 border-primary text-primary font-bold' : 'bg-white dark:bg-gray-800 border-gray-300 dark:border-gray-600 text-gray-700 dark:text-gray-300 hover:border-primary font-medium'"
                                class="px-3.5 py-2 rounded-lg border text-sm transition text-left flex flex-col shadow-sm"
                            >
                                <span class="capitalize">{{ u.online_label || u.unit_name }}</span>
                                <span class="text-xs opacity-75">
                                    <template v-if="parseFloat(u.synced_standard_price) > 0 && u.unit_kind !== 'base'">₦{{ parseFloat(u.synced_standard_price).toLocaleString() }}</template>
                                    <template v-else>Standard Unit</template>
                                    <span v-if="parseFloat(u.quantity_in_base_units) > 1" class="ml-1">({{ parseFloat(u.quantity_in_base_units) }}x)</span>
                                </span>
                            </button>
                        </div>
                    </div>

                    <div v-if="availableVariants && availableVariants.length > 0" class="mb-6 bg-gray-50 dark:bg-gray-900 border dark:border-gray-700 p-4 rounded-lg">
                        <div class="flex items-center justify-between mb-3">
                            <label class="block text-sm font-bold text-gray-700 dark:text-gray-300">Available Options</label>
                            <span v-if="selectedVariant" class="text-xs font-semibold">
                                <span v-if="selectedVariant.stock !== null && selectedVariant.stock <= 0" class="text-red-500 font-bold">Out of stock</span>
                                <span v-else-if="selectedVariant.stock !== null && selectedVariant.stock <= 5" class="text-amber-600 font-bold">Only {{ selectedVariant.stock }} left in stock</span>
                                <span v-else-if="selectedVariant.stock !== null" class="text-emerald-600 font-bold">In Stock ({{ selectedVariant.stock }} available)</span>
                            </span>
                        </div>
                        <div class="flex flex-wrap gap-3">
                            <button 
                                v-for="(v, index) in availableVariants" 
                                :key="index"
                                type="button"
                                @click="selectedVariant = v"
                                :class="selectedVariant?.name === v.name ? 'ring-2 ring-primary bg-primary/10 border-primary text-primary font-bold' : 'bg-white dark:bg-gray-800 border-gray-300 dark:border-gray-600 hover:border-primary'"
                                class="px-4 py-2 rounded-md border text-sm font-semibold transition relative overflow-hidden text-left"
                            >
                                <div class="flex items-center gap-1.5">
                                    <span>{{ v.name }}</span>
                                    <span v-if="v.stock !== null && v.stock <= 0" class="text-[10px] uppercase font-bold px-1.5 py-0.5 rounded bg-red-100 text-red-700 dark:bg-red-900/40 dark:text-red-300">
                                        Sold out
                                    </span>
                                </div>
                                <div v-if="v.price && parseFloat(v.price) > 0" class="text-xs font-normal text-gray-500 dark:text-gray-400 mt-0.5">
                                    ₦{{ parseFloat(v.price).toLocaleString() }}
                                </div>
                            </button>
                        </div>
                    </div>
                    
                    <div v-if="product.short_description" class="prose dark:prose-invert max-w-none mb-6 text-gray-600 dark:text-gray-400 text-lg leading-relaxed">
                        <p class="whitespace-pre-line">{{ product.short_description }}</p>
                    </div>
                    
                    <div class="flex flex-col gap-3 border-t dark:border-gray-700 pt-6 mt-auto">
                        <div class="flex items-center gap-3">
                            <div class="flex items-center border dark:border-gray-600 rounded-md bg-gray-50 dark:bg-gray-900 relative h-12">
                                <button type="button" @click="form.quantity > 1 ? form.quantity-- : 1" class="px-4 h-full text-gray-600 dark:text-gray-400 hover:bg-gray-200 dark:hover:bg-gray-700 transition font-bold disabled:opacity-50 select-none">-</button>
                                <input type="number" v-model="form.quantity" min="1" :max="maxStock" class="w-12 text-center border-none focus:ring-0 bg-transparent font-medium p-0 no-spinner [appearance:textfield] [&::-webkit-outer-spin-button]:appearance-none [&::-webkit-inner-spin-button]:appearance-none" />
                                <button type="button" @click="form.quantity < maxStock ? form.quantity++ : form.quantity" class="px-4 h-full text-gray-600 dark:text-gray-400 hover:bg-gray-200 dark:hover:bg-gray-700 transition font-bold disabled:opacity-50 select-none">+</button>
                            </div>
                            <button @click="addToCart" 
                                :disabled="form.processing || !isPurchasable || (availableVariants?.length > 0 && !selectedVariant)" 
                                class="bg-gray-800 hover:bg-gray-900 dark:bg-gray-700 dark:hover:bg-gray-600 text-white font-bold transition flex-1 h-12 rounded-md disabled:opacity-50 disabled:cursor-not-allowed uppercase tracking-wide flex items-center justify-center gap-2 text-sm shadow-sm">
                                <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z"></path></svg>
                                {{ !isPurchasable ? 'Out of Stock' : 'Add to Cart' }}
                            </button>

                            <button @click="addToCompare" :disabled="comparing"
                                title="Compare Product"
                                class="h-12 w-12 flex-shrink-0 rounded-md bg-white hover:bg-primary/10 text-gray-600 hover:text-primary dark:bg-gray-800 dark:hover:bg-gray-700 dark:text-gray-300 transition disabled:opacity-50 flex items-center justify-center border border-gray-300 dark:border-gray-600 shadow-sm">
                                <svg v-if="!comparing" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4"/>
                                </svg>
                                <svg v-else class="w-5 h-5 animate-spin" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/>
                                </svg>
                            </button>
                        </div>
                        <button @click="buyNow" 
                            :disabled="form.processing || !isPurchasable || (availableVariants?.length > 0 && !selectedVariant)" 
                            class="bg-primary hover:opacity-90 text-white font-bold transition w-full h-12 rounded-md disabled:opacity-50 disabled:cursor-not-allowed uppercase tracking-wide flex items-center justify-center gap-2 text-sm shadow-md hover:shadow-lg">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M13 10V3L4 14h7v7l9-11h-7z"></path></svg>
                            {{ !isPurchasable ? 'Out of Stock' : ((availableVariants?.length > 0 && !selectedVariant) ? 'Select Option to Buy Now' : 'Buy Now') }}
                        </button>
                    </div>

                    <!-- Share Strip Below Actions -->
                    <div class="mt-4 pt-4 border-t dark:border-gray-700/80 flex flex-wrap items-center justify-between gap-2 text-xs text-gray-500 dark:text-gray-400">
                        <span class="font-semibold text-gray-700 dark:text-gray-300 flex items-center gap-1.5">
                            <svg class="w-4 h-4 text-primary" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.684 13.342C8.886 12.938 9 12.482 9 12c0-.482-.114-.938-.316-1.342m0 2.684a3 3 0 110-2.684m0 2.684l6.632 3.316m-6.632-6l6.632-3.316m0 0a3 3 0 105.367-2.684 3 3 0 00-5.367 2.684zm0 9.316a3 3 0 105.368 2.684 3 3 0 00-5.368-2.684z" />
                            </svg>
                            Share this product:
                        </span>
                        <div class="flex items-center gap-3">
                            <button @click="shareTo('whatsapp')" type="button" class="hover:text-emerald-500 transition font-medium flex items-center gap-1 cursor-pointer" title="Share on WhatsApp">
                                💬 WhatsApp
                            </button>
                            <span class="text-gray-300 dark:text-gray-600">•</span>
                            <button @click="shareTo('twitter')" type="button" class="hover:text-black dark:hover:text-white transition font-medium flex items-center gap-1 cursor-pointer" title="Post on X">
                                𝕏 Post
                            </button>
                            <span class="text-gray-300 dark:text-gray-600">•</span>
                            <button @click="shareTo('facebook')" type="button" class="hover:text-blue-600 transition font-medium flex items-center gap-1 cursor-pointer" title="Share on Facebook">
                                Facebook
                            </button>
                            <span class="text-gray-300 dark:text-gray-600">•</span>
                            <button @click="copyShareLink" type="button" class="hover:text-primary transition font-medium flex items-center gap-1 cursor-pointer" title="Copy product link">
                                🔗 {{ copiedLink ? 'Copied!' : 'Copy Link' }}
                            </button>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Full Description Section -->
            <div v-if="product.description" class="mt-12 bg-white dark:bg-gray-800 p-8 rounded-xl shadow-sm border dark:border-gray-700">
                <h3 class="text-2xl font-bold mb-6 text-black dark:text-white border-b dark:border-gray-700 pb-4">Product Description</h3>
                <div class="ql-snow">
                    <div class="ql-editor prose prose-blue prose-lg dark:prose-invert max-w-none text-gray-800 dark:text-gray-200" v-html="product.description" style="padding: 0;"></div>
                </div>
            </div>

            <!-- Customer Reviews & Ratings Section -->
            <div v-if="reviewsEnabled" id="customer-reviews" class="mt-12 bg-white dark:bg-gray-800 p-8 rounded-xl shadow-sm border dark:border-gray-700">
                <div class="flex flex-col md:flex-row md:items-center justify-between pb-6 border-b dark:border-gray-700 gap-4">
                    <div>
                        <h3 class="text-2xl font-bold text-black dark:text-white">Customer Reviews</h3>
                        <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">Verified buyer experiences and feedback</p>
                    </div>
                    <div class="flex items-center gap-4 bg-gray-50 dark:bg-gray-900/60 px-5 py-3 rounded-xl border dark:border-gray-700">
                        <div class="text-3xl font-black text-amber-500">{{ Number(average_rating).toFixed(1) }}</div>
                        <div>
                            <div class="flex text-amber-400 text-sm">
                                <svg v-for="star in 5" :key="star" class="w-4 h-4 fill-current" :class="star <= Math.round(average_rating) ? 'text-amber-400' : 'text-gray-300 dark:text-gray-600'" viewBox="0 0 20 20">
                                    <path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z" />
                                </svg>
                            </div>
                            <span class="text-xs text-gray-500 dark:text-gray-400 font-medium">Based on {{ reviews_count }} {{ reviews_count === 1 ? 'review' : 'reviews' }}</span>
                        </div>
                    </div>
                </div>

                <div class="grid grid-cols-1 lg:grid-cols-12 gap-8 mt-8">
                    <!-- Left: Reviews List (7 cols) -->
                    <div class="lg:col-span-7 space-y-4">
                        <div v-if="reviews.length === 0" class="text-center py-12 bg-gray-50 dark:bg-gray-900/40 rounded-xl border border-dashed dark:border-gray-700">
                            <svg class="w-12 h-12 mx-auto text-gray-400 mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"/></svg>
                            <p class="font-bold text-gray-700 dark:text-gray-300">No reviews yet</p>
                            <p class="text-xs text-gray-500 mt-1">Be the first to share your thoughts on this product!</p>
                        </div>

                        <div v-for="rev in reviews" :key="rev.id" class="p-5 bg-gray-50 dark:bg-gray-900/40 rounded-xl border dark:border-gray-700">
                            <div class="flex items-center justify-between mb-2">
                                <div class="flex items-center gap-2">
                                    <div class="w-8 h-8 rounded-full bg-primary/10 text-primary font-bold flex items-center justify-center text-xs uppercase">
                                        {{ (rev.customer?.name || 'Customer').charAt(0) }}
                                    </div>
                                    <div>
                                        <p class="font-bold text-sm text-gray-900 dark:text-white">{{ rev.customer?.name || 'Verified Buyer' }}</p>
                                        <span v-if="rev.is_verified_buyer" class="inline-flex items-center gap-1 text-[11px] text-green-600 font-semibold">
                                            ✓ Verified Buyer
                                        </span>
                                    </div>
                                </div>
                                <span class="text-xs text-gray-400">{{ new Date(rev.created_at).toLocaleDateString() }}</span>
                            </div>

                            <div class="flex text-amber-400 my-2">
                                <svg v-for="s in 5" :key="s" class="w-4 h-4 fill-current" :class="s <= rev.rating ? 'text-amber-400' : 'text-gray-300 dark:text-gray-600'" viewBox="0 0 20 20">
                                    <path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z" />
                                </svg>
                            </div>

                            <p v-if="rev.comment" class="text-sm text-gray-700 dark:text-gray-300 mt-2 whitespace-pre-line leading-relaxed">
                                {{ rev.comment }}
                            </p>
                        </div>
                    </div>

                    <!-- Right: Review Form / Login Prompt (5 cols) -->
                    <div class="lg:col-span-5">
                        <div class="bg-gray-50 dark:bg-gray-900/60 p-6 rounded-xl border dark:border-gray-700">
                            <h4 class="font-bold text-lg text-gray-900 dark:text-white mb-2">Write a Review</h4>
                            <p class="text-xs text-gray-500 mb-5">Share your rating and feedback with other shoppers.</p>

                            <!-- Authenticated Customer Review Form -->
                            <form v-if="$page.props.auth?.customer" @submit.prevent="submitReview" class="space-y-4">
                                <div>
                                    <label class="block text-xs font-semibold text-gray-700 dark:text-gray-300 mb-1.5">Your Rating *</label>
                                    <div class="flex items-center gap-1 text-amber-400 cursor-pointer">
                                        <button
                                            v-for="star in 5"
                                            :key="star"
                                            type="button"
                                            @click="reviewForm.rating = star"
                                            class="p-1 hover:scale-125 transition"
                                        >
                                            <svg class="w-6 h-6 fill-current" :class="star <= reviewForm.rating ? 'text-amber-400' : 'text-gray-300 dark:text-gray-600'" viewBox="0 0 20 20">
                                                <path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z" />
                                            </svg>
                                        </button>
                                        <span class="ml-2 text-xs font-bold text-gray-600 dark:text-gray-300">{{ reviewForm.rating }} of 5 Stars</span>
                                    </div>
                                    <p v-if="reviewForm.errors.rating" class="text-red-500 text-xs mt-1">{{ reviewForm.errors.rating }}</p>
                                </div>

                                <div>
                                    <label class="block text-xs font-semibold text-gray-700 dark:text-gray-300 mb-1.5">Review Comment (Optional)</label>
                                    <textarea
                                        v-model="reviewForm.comment"
                                        rows="4"
                                        maxlength="1000"
                                        class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-800 text-sm px-3 py-2.5 focus:ring-primary focus:border-primary"
                                        placeholder="What did you like or dislike? How did this product perform?"
                                    ></textarea>
                                    <p v-if="reviewForm.errors.comment" class="text-red-500 text-xs mt-1">{{ reviewForm.errors.comment }}</p>
                                </div>

                                <button
                                    type="submit"
                                    :disabled="reviewForm.processing"
                                    class="w-full py-3 bg-primary hover:opacity-90 text-white font-bold rounded-lg transition disabled:opacity-50 text-sm uppercase tracking-wider shadow-sm"
                                >
                                    {{ reviewForm.processing ? 'Submitting...' : 'Submit Review' }}
                                </button>
                            </form>

                            <!-- Guest Prompt -->
                            <div v-else class="text-center py-6">
                                <p class="text-sm text-gray-600 dark:text-gray-300 mb-4">You must be logged in as a customer to submit a review.</p>
                                <Link :href="route('login')" class="inline-block px-6 py-2.5 bg-primary text-white font-bold text-sm rounded-lg hover:opacity-90 transition shadow-sm uppercase tracking-wide">
                                    Sign In to Review
                                </Link>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Related Products -->
            <div v-if="related && related.length > 0" class="mt-16">
                <h3 class="text-xl sm:text-2xl font-bold mb-4 sm:mb-6 text-black dark:text-white">You might also like</h3>
                <div class="grid grid-cols-2 sm:grid-cols-2 lg:grid-cols-4 gap-3 sm:gap-6">
                    <div v-for="rel in related" :key="rel.id" class="border border-gray-100 dark:border-gray-700/80 rounded-xl overflow-hidden shadow-xs hover:shadow-md hover:border-gray-200 dark:hover:border-gray-600 transition-all bg-white dark:bg-gray-800 flex flex-col group">
                        <Link :href="route('shop.show', rel.url_key)" class="block flex-1 flex flex-col">
                            <div class="overflow-hidden bg-gray-50 dark:bg-gray-900/50 flex items-center justify-center p-2 h-36 sm:h-48">
                                <img :src="rel.image ? '/storage/' + rel.image : 'https://placehold.co/400x300?text=' + rel.name" class="max-w-full max-h-full w-auto h-auto object-contain group-hover:scale-105 transition duration-300" />
                            </div>
                            <div class="p-2.5 sm:p-4 flex-1 flex flex-col">
                                <h4 class="text-xs sm:text-sm font-semibold text-gray-900 dark:text-white mb-1 group-hover:text-primary transition line-clamp-2">{{ rel.name }}</h4>
                                <p class="text-primary font-bold text-sm sm:text-base mt-auto">₦{{ parseFloat(rel.price).toLocaleString() }}</p>
                            </div>
                        </Link>
                    </div>
                </div>
            </div>
        </div>
    </Layout>
</template>

<style>
@import '@vueup/vue-quill/dist/vue-quill.snow.css';

/* Remove default quill editor padding and paragraph margins to align tightly */
.ql-editor {
    padding: 0 !important;
}
.ql-editor p {
    margin-top: 0 !important;
    margin-bottom: 0 !important;
}
.ql-editor h3 {
    font-size: 1.15em !important;
    font-weight: 700 !important;
    margin-top: 0.75rem !important;
    margin-bottom: 0.35rem !important;
    line-height: 1.4 !important;
}
.ql-editor ul,
.ql-editor ol {
    margin-top: 0.25rem !important;
    margin-bottom: 0.5rem !important;
}
.ql-editor li {
    margin-top: 0.15rem !important;
    margin-bottom: 0.15rem !important;
}
.ql-editor strong {
    font-weight: 700 !important;
}

/* Remove default number input spinner arrows */
input[type=number]::-webkit-inner-spin-button,
input[type=number]::-webkit-outer-spin-button {
    -webkit-appearance: none;
    margin: 0;
}
input[type=number] {
    -moz-appearance: textfield;
    appearance: textfield;
}
</style>
