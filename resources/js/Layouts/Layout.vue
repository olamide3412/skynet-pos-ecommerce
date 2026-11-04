<script setup>
import Navbar from '@/Components/Navigations/Navbar.vue'
import WhatsAppButton from '@/Components/WhatsAppButton.vue'
import ThemeToggle from '@/Components/ThemeToggle.vue';
import { onMounted, ref, watch } from 'vue';
import { usePage } from '@inertiajs/vue3';
import FlashMessages from '../Components/FlashMessages.vue';
import CartDrawer from '@/Components/Cart/CartDrawer.vue';
import { useThemeStore } from '../Stores/themeStore';

const page = usePage();
const themeStore = useThemeStore();

watch(
    () => [
        page.props.store_settings?.theme_primary_color,
        page.props.store_settings?.theme_secondary_color,
        page.props.store_settings?.theme_dark_mode_text_color,
    ],
    ([primary, secondary, darkText]) => {
        themeStore.applyBrandColors(primary, secondary, darkText);
    },
    { immediate: true } 
);

</script>
<template>
    <div class="min-h-screen flex flex-col bg-light text-black dark:bg-dark dark:text-white">
        <Navbar />
        <main class="flex-1">
            <slot />
            <WhatsAppButton v-if="!$page.props.auth.user" />
        </main>
        <FlashMessages/>
        <CartDrawer />
    </div>
</template>
