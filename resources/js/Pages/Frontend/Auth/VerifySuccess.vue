<script setup>
import { ref, onMounted, onUnmounted } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import Layout from '@/Layouts/Layout.vue';

const props = defineProps({
    customer: {
        type: Object,
        required: true,
    },
    alreadyVerified: {
        type: Boolean,
        default: false,
    },
});

const countdown = ref(5);
let timer = null;

const loginUrl = computedLoginUrl();

function computedLoginUrl() {
    const params = new URLSearchParams();
    if (props.customer?.email) {
        params.set('email', props.customer.email);
    }
    params.set('verified', '1');
    return route('login') + '?' + params.toString();
}

const goToLogin = () => {
    if (timer) clearInterval(timer);
    router.visit(loginUrl);
};

onMounted(() => {
    timer = setInterval(() => {
        if (countdown.value > 1) {
            countdown.value--;
        } else {
            clearInterval(timer);
            goToLogin();
        }
    }, 1000);
});

onUnmounted(() => {
    if (timer) clearInterval(timer);
});
</script>

<template>
    <Layout>
        <div class="min-h-[70vh] flex items-center justify-center px-4 my-12">
            <div class="w-full max-w-md bg-white dark:bg-slate-900 rounded-2xl border border-gray-200 dark:border-gray-800 shadow-xl p-8 sm:p-9 space-y-6 text-center">
                
                <!-- Celebration / Success Icon -->
                <div class="mx-auto w-20 h-20 rounded-full bg-emerald-100 dark:bg-emerald-950/60 text-emerald-600 dark:text-emerald-400 flex items-center justify-center text-4xl shadow-inner border border-emerald-200 dark:border-emerald-800 animate-bounce-short">
                    ✓
                </div>

                <div class="space-y-2">
                    <h2 class="text-2xl sm:text-3xl font-extrabold text-gray-900 dark:text-white tracking-tight">
                        {{ alreadyVerified ? 'Email Already Verified' : 'Verification Successful!' }}
                    </h2>
                    <p class="text-sm text-gray-600 dark:text-gray-300 leading-relaxed">
                        {{ alreadyVerified
                            ? 'Your email address is already verified and your account is ready.'
                            : 'Thank you! Your email address has been confirmed and your account is now active.'
                        }}
                    </p>
                    <div class="inline-block px-3 py-1.5 rounded-lg bg-gray-100 dark:bg-gray-800 text-sm font-bold text-gray-900 dark:text-gray-100 font-mono break-all border border-gray-200 dark:border-gray-700 mt-2">
                        {{ customer.email }}
                    </div>
                </div>

                <div class="p-4 rounded-xl bg-emerald-50 dark:bg-emerald-950/40 border border-emerald-200 dark:border-emerald-900/60 text-xs text-emerald-900 dark:text-emerald-200 text-left space-y-1">
                    <div class="font-bold flex items-center gap-1.5 text-[13px]">
                        <span>🛒</span>
                        <span>Cart & Orders Saved</span>
                    </div>
                    <p class="leading-relaxed">
                        Any items you added to your cart are safely linked to your account. Sign in below to proceed to checkout.
                    </p>
                </div>

                <!-- Action Button & Auto-redirect -->
                <div class="space-y-3 pt-2">
                    <button
                        type="button"
                        @click="goToLogin"
                        class="w-full py-3.5 px-4 rounded-xl font-bold text-sm bg-primary hover:opacity-90 text-white shadow-lg transition flex items-center justify-center gap-2"
                    >
                        <span>Sign In to Your Account &rarr;</span>
                    </button>
                    <p class="text-xs text-gray-500 dark:text-gray-400">
                        Automatically taking you to sign in in <strong class="text-primary">{{ countdown }}s</strong>...
                    </p>
                </div>
            </div>
        </div>
    </Layout>
</template>

<style scoped>
@keyframes bounceShort {
    0%, 100% {
        transform: translateY(0);
    }
    50% {
        transform: translateY(-8px);
    }
}
.animate-bounce-short {
    animation: bounceShort 1.5s ease-in-out infinite;
}
</style>

