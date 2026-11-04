<script setup>
import { usePage } from '@inertiajs/vue3';
import { watch } from 'vue';
import { useToast } from 'vue-toastification';

const toast = useToast();
const page = usePage();

// Watch for flash messages and show toasts
watch(() => page.props.flash, (flash) => {
    if (!flash) return;

    if (flash.success) {
        toast.success(flash.success);
        // Clear the flash message after displaying
        page.props.flash.success = null;
    }

    if (flash.error) {
        toast.error(flash.error);
        page.props.flash.error = null;
    }

    if (flash.warning) {
        toast.warning(flash.warning);
        page.props.flash.warning = null;
    }

    if (flash.info) {
        toast.info(flash.info);
        page.props.flash.info = null;
    }

    if (flash.message) {
        toast.info(flash.message);
        page.props.flash.message = null;
    }
}, { immediate: true, deep: true });
</script>

<template>
    <!-- This component doesn't render anything visually -->
    <!-- It just handles the flash messages -->
</template>
