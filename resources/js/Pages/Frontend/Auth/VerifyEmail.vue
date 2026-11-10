<script setup>
import { ref, onMounted, onUnmounted, computed } from 'vue';
import { usePage, Link, router } from '@inertiajs/vue3';
import Layout from '@/Layouts/Layout.vue';
import { useToast } from 'vue-toastification';

const props = defineProps({
    email: {
        type: String,
        required: true,
    },
    cooldownSeconds: {
        type: Number,
        default: 30,
    },
});

const toast = useToast();
const page = usePage();

// Cooldown state
const countdown = ref(Number(props.cooldownSeconds) > 0 ? Number(props.cooldownSeconds) : 0);
const isResending = ref(false);
let timer = null;

const startTimer = (seconds) => {
    countdown.value = seconds;
    if (timer) clearInterval(timer);

    timer = setInterval(() => {
        if (countdown.value > 0) {
            countdown.value--;
        } else {
            clearInterval(timer);
            timer = null;
        }
    }, 1000);
};

onMounted(() => {
    if (countdown.value > 0) {
        startTimer(countdown.value);
    }
});

onUnmounted(() => {
    if (timer) clearInterval(timer);
});

const resendVerificationEmail = () => {
    if (countdown.value > 0 || isResending.value) return;

    isResending.value = true;

    router.post(
        route('verification.resend'),
        { email: props.email },
        {
            preserveScroll: true,
            onFinish: () => {
                isResending.value = false;
            },
            onSuccess: () => {
                startTimer(30);
                toast.success('A fresh verification link has been sent to your email.');
            },
            onError: (errors) => {
                toast.error(errors.email || 'Unable to resend verification email.');
            },
        }
    );
};
</script>

<template>
    <Layout>
        <div class="min-h-[70vh] flex items-center justify-center px-4 my-12">
            <div class="w-full max-w-md bg-white dark:bg-slate-900 rounded-2xl border border-gray-200 dark:border-gray-800 shadow-xl p-8 sm:p-9 space-y-6 text-center">
                
                <!-- Animated Mail Icon Badge -->
                <div class="mx-auto w-16 h-16 rounded-full bg-primary/10 text-primary flex items-center justify-center text-3xl shadow-inner">
                    ✉️
                </div>

                <div class="space-y-2">
                    <h2 class="text-2xl sm:text-3xl font-extrabold text-gray-900 dark:text-white tracking-tight">
                        Verify Your Email
                    </h2>
                    <p class="text-sm text-gray-600 dark:text-gray-300 leading-relaxed">
                        We've sent a verification link to
                    </p>
                    <div class="inline-block px-3 py-1.5 rounded-lg bg-gray-100 dark:bg-gray-800 text-sm font-bold text-gray-900 dark:text-gray-100 font-mono break-all border border-gray-200 dark:border-gray-700">
                        {{ email }}
                    </div>
                </div>

                <div class="p-4 rounded-xl bg-amber-50 dark:bg-amber-950/40 border border-amber-200 dark:border-amber-900/60 text-xs text-amber-900 dark:text-amber-200 text-left space-y-1.5">
                    <div class="font-bold flex items-center gap-1.5 text-[13px]">
                        <span>⏳</span>
                        <span>Almost there!</span>
                    </div>
                    <p class="leading-relaxed">
                        Please check your inbox (and spam/junk folder) and click the verification button to activate your account.
                        Your shopping cart items are securely saved.
                    </p>
                </div>

                <!-- Resend Action with Countdown -->
                <div class="space-y-3 pt-2">
                    <button
                        type="button"
                        @click="resendVerificationEmail"
                        :disabled="countdown > 0 || isResending"
                        class="w-full py-3 px-4 rounded-xl font-bold text-sm transition shadow-md flex items-center justify-center gap-2"
                        :class="countdown > 0 || isResending
                            ? 'bg-gray-100 dark:bg-gray-800 text-gray-400 dark:text-gray-500 cursor-not-allowed border border-gray-200 dark:border-gray-700'
                            : 'bg-primary hover:opacity-90 text-white'"
                    >
                        <svg v-if="isResending" class="animate-spin h-4 w-4 text-white" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path>
                        </svg>
                        <span v-if="isResending">Sending...</span>
                        <span v-else-if="countdown > 0">Resend email in {{ countdown }}s</span>
                        <span v-else>Resend Verification Email</span>
                    </button>
                    <p v-if="countdown > 0" class="text-[11px] text-gray-400 dark:text-gray-500">
                        Did not receive the email? You can request a new link once the timer reaches 0.
                    </p>
                </div>

                <!-- Footer Navigation -->
                <div class="pt-4 border-t border-gray-100 dark:border-gray-800 space-y-2 text-xs">
                    <p class="text-gray-600 dark:text-gray-400">
                        Already clicked the link?
                        <Link :href="route('login')" class="font-bold text-primary hover:underline ml-1">
                            Sign in to your account &rarr;
                        </Link>
                    </p>
                    <p class="text-gray-500 dark:text-gray-400">
                        Wrong email address?
                        <Link :href="route('register')" class="font-medium text-gray-700 dark:text-gray-300 hover:underline ml-1">
                            Register with a different email
                        </Link>
                    </p>
                </div>
            </div>
        </div>
    </Layout>
</template>

