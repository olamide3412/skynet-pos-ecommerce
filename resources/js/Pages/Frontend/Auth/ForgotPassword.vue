<script setup>
import { useForm, usePage, Link } from '@inertiajs/vue3';
import { ref, computed, onMounted } from 'vue';
import Layout from '@/Layouts/Layout.vue';
import TextInput from '@/Components/Forms/TextInput.vue';

const props = defineProps({
    status: String,
});

const page = usePage();
const isTurnstileEnabled = computed(() => page.props.store_settings?.turnstile_enabled !== '0');

const form = useForm({
    email: '',
    cf_turnstile_response: '',
});

const turnstileWidgetId = ref(null);
const isTurnstileLoaded = ref(false);
const turnstileError = ref('');

const loadTurnstile = () => {
    if (!isTurnstileEnabled.value) return;
    if (window.turnstile) {
        renderTurnstile();
        return;
    }

    const script = document.createElement('script');
    script.src = 'https://challenges.cloudflare.com/turnstile/v0/api.js?render=explicit';
    script.async = true;
    script.defer = true;
    script.onload = () => {
        isTurnstileLoaded.value = true;
        renderTurnstile();
    };
    script.onerror = () => {
        turnstileError.value = 'Failed to load CAPTCHA. Please refresh the page.';
    };
    document.head.appendChild(script);
};

const renderTurnstile = () => {
    if (window.turnstile && document.getElementById('cf-turnstile-widget')) {
        turnstileWidgetId.value = window.turnstile.render('#cf-turnstile-widget', {
            sitekey: page.props.turnstileSiteKey,
            callback: (token) => {
                form.cf_turnstile_response = token;
                turnstileError.value = '';
            },
            'expired-callback': () => {
                form.cf_turnstile_response = '';
                turnstileError.value = 'CAPTCHA expired. Please verify again.';
                resetTurnstile();
            },
            'error-callback': () => {
                form.cf_turnstile_response = '';
                turnstileError.value = 'CAPTCHA error. Please try again.';
                resetTurnstile();
            },
        });
    }
};

const resetTurnstile = () => {
    if (window.turnstile && turnstileWidgetId.value !== null) {
        window.turnstile.reset(turnstileWidgetId.value);
    }
};

onMounted(() => {
    if (isTurnstileEnabled.value) {
        loadTurnstile();
    }
});

const submit = () => {
    if (isTurnstileEnabled.value && !form.cf_turnstile_response) {
        turnstileError.value = 'Please complete the CAPTCHA verification';
        return;
    }

    form.post(route('password.email'), {
        onFinish: () => {
            if (isTurnstileEnabled.value) {
                resetTurnstile();
                form.cf_turnstile_response = '';
            }
        },
    });
};
</script>

<template>
    <Layout>
        <div class="min-h-[70vh] flex items-center justify-center px-4 my-10">
            <div class="w-full max-w-md bg-white dark:bg-slate-900 rounded-xl border border-gray-200 dark:border-gray-800 shadow-md p-8 space-y-6">
                <div class="text-center">
                    <div class="w-12 h-12 rounded-full bg-primary/10 text-primary flex items-center justify-center mx-auto mb-3">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z" />
                        </svg>
                    </div>
                    <h2 class="text-2xl font-bold text-gray-900 dark:text-gray-100">Forgot Password?</h2>
                    <p class="text-sm text-gray-500 dark:text-gray-400 mt-2">
                        Enter your registered email address and we'll send you a link to reset your password.
                    </p>
                </div>

                <div
                    v-if="status || $page.props.flash.status || $page.props.flash.success"
                    class="p-3.5 rounded-lg bg-green-50 dark:bg-green-900/30 border border-green-200 dark:border-green-800 text-green-700 dark:text-green-300 text-sm font-medium"
                >
                    {{ status || $page.props.flash.status || $page.props.flash.success }}
                </div>

                <div
                    v-if="$page.props.flash.error || $page.props.flash.warning"
                    class="p-3.5 rounded-lg bg-red-50 dark:bg-red-900/30 border border-red-200 dark:border-red-800 text-red-700 dark:text-red-300 text-sm font-medium"
                >
                    {{ $page.props.flash.error || $page.props.flash.warning }}
                </div>

                <form @submit.prevent="submit" class="space-y-5">
                    <TextInput
                        name="email"
                        label="Email Address"
                        v-model="form.email"
                        type="email"
                        placeholder="you@example.com"
                        :message="form.errors.email"
                        autofocus
                        :required="true"
                    />

                    <!-- Cloudflare Turnstile Widget (shown when enabled in Store Settings) -->
                    <div v-if="isTurnstileEnabled" class="space-y-1.5">
                        <div class="flex justify-left">
                            <div id="cf-turnstile-widget" class="cf-turnstile"></div>
                        </div>
                        <div v-if="turnstileError" class="text-red-600 text-sm text-center">
                            {{ turnstileError }}
                        </div>
                        <div v-if="form.errors.cf_turnstile_response" class="text-red-600 text-sm text-center">
                            {{ form.errors.cf_turnstile_response }}
                        </div>
                    </div>

                    <button
                        type="submit"
                        :disabled="form.processing"
                        class="w-full py-3 rounded-lg bg-primary hover:opacity-90 text-white font-bold transition shadow-md disabled:opacity-50"
                    >
                        {{ form.processing ? 'Sending Reset Link...' : 'Send Password Reset Link' }}
                    </button>
                </form>

                <div class="text-center pt-2">
                    <Link :href="route('login')" class="text-sm font-medium text-primary hover:underline inline-flex items-center gap-1.5">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                        </svg>
                        Back to Login
                    </Link>
                </div>
            </div>
        </div>
    </Layout>
</template>

