<script setup>
import { useForm, usePage } from '@inertiajs/vue3'
import { ref, onMounted } from 'vue'
import Layout from '@/Layouts/Layout.vue';
import TextInput from '@/Components/Forms/TextInput.vue';
import { useToast } from 'vue-toastification';

const toast = useToast();
const flashMsg = usePage().props.flash.message;
const form = useForm({
  email: '',
  password: '',
  remember: false,
  cf_turnstile_response: '',
});

const turnstileWidgetId = ref(null);
const isTurnstileLoaded = ref(false);
const turnstileError = ref('');

// Load Cloudflare Turnstile script
const loadTurnstile = () => {
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

// Render Turnstile widget
const renderTurnstile = () => {
    if (window.turnstile && document.getElementById('cf-turnstile-widget')) {
        turnstileWidgetId.value = window.turnstile.render('#cf-turnstile-widget', {
            sitekey: usePage().props.turnstileSiteKey,
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
            }
        });
    }
};

// Reset Turnstile widget
const resetTurnstile = () => {
    if (window.turnstile && turnstileWidgetId.value) {
        window.turnstile.reset(turnstileWidgetId.value);
    }
};

// Reload Turnstile
const reloadTurnstile = () => {
    resetTurnstile();
    form.cf_turnstile_response = '';
    turnstileError.value = '';
};

// Load Turnstile when component mounts
onMounted(() => {
    loadTurnstile();
});

const handleLogin = () => {
    if (!form.cf_turnstile_response) {
        turnstileError.value = 'Please complete the CAPTCHA verification';
        return;
    }

    form.post(route('customer.login.submit'), {
        onFinish: () => {
            form.reset('password');
            resetTurnstile();
        },
        onError: () => {
            toast.error(form.errors.email ?? 'Login failed. Please check credentials.');
            if (form.errors.cf_turnstile_response) {
                resetTurnstile();
                form.cf_turnstile_response = '';
            }
        },
        onSuccess: () => {
            form.reset();
        }
    });
}
</script>
<template>
    <Layout>
        <div class="min-h-[70vh] flex items-center justify-center px-4 my-10">
            <div class="w-full max-w-md bg-white dark:bg-slate-900 rounded-lg border border-gray-200 dark:border-gray-800 shadow-md p-8 space-y-6">
                <div class="text-center">
                    <h2 class="text-3xl font-bold text-gray-800 dark:text-gray-100">Customer Login</h2>
                    <p class="text-sm text-gray-500 dark:text-gray-400 mt-2">Welcome back! Access your orders and cart.</p>
                </div>
                <form @submit.prevent="handleLogin" class="mt-6 space-y-6">
                    <TextInput name="email" label="Email Address" v-model="form.email" type="email" placeholder="you@example.com" autofocus autocomplete="username" :required="true"/>
                    <TextInput name="password" label="Password" type="password" v-model="form.password" placeholder="••••••••" :required="true" autocomplete="current-password"/>
                    
                    <!-- Cloudflare Turnstile Widget -->
                    <div class="flex justify-left">
                        <div id="cf-turnstile-widget" class="cf-turnstile"></div>
                    </div>
                    <div v-if="turnstileError" class="text-red-600 text-sm text-center">
                        {{ turnstileError }}
                    </div>
                    <div v-if="form.errors.cf_turnstile_response" class="text-red-600 text-sm text-center">
                        {{ form.errors.cf_turnstile_response }}
                    </div>

                    <div class="flex items-center justify-between">
                        <label class="flex items-center">
                            <input type="checkbox" class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded" v-model="form.remember" />
                            <span class="ml-2 text-sm text-gray-600 dark:text-gray-300">Remember me</span>
                        </label>
                    </div>
                    <button class="w-full bg-blue-600 hover:bg-blue-700 text-white font-bold py-3 rounded-md transition" :disabled="form.processing">Sign in</button>

                    <!-- Divider -->
                    <div v-if="$page.props.store_settings.google_auth_enabled === '1' || $page.props.store_settings.facebook_auth_enabled === '1'" class="relative mt-6">
                        <div class="absolute inset-0 flex items-center">
                            <div class="w-full border-t border-gray-300 dark:border-gray-700"></div>
                        </div>
                        <div class="relative flex justify-center text-sm">
                            <span class="px-2 bg-white dark:bg-slate-900 text-gray-500 dark:text-gray-400">Or continue with</span>
                        </div>
                    </div>

                    <!-- Social Buttons -->
                    <div v-if="$page.props.store_settings.google_auth_enabled === '1' || $page.props.store_settings.facebook_auth_enabled === '1'" class="grid grid-cols-2 gap-3 mt-4">
                        <a v-if="$page.props.store_settings.google_auth_enabled === '1'" :href="route('social.redirect', 'google')" class="inline-flex items-center justify-center py-2.5 px-4 border border-gray-300 dark:border-gray-700 rounded-md shadow-sm bg-white dark:bg-gray-800 text-sm font-medium text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-700 transition">
                            <svg class="h-5 w-5" viewBox="0 0 24 24" fill="currentColor">
                                <path d="M21.35,11.1H12.18V13.83H18.69C18.36,17.64 15.19,19.27 12.19,19.27C8.36,19.27 5,16.25 5,12C5,7.9 8.2,4.73 12.2,4.73C15.29,4.73 17.1,6.7 17.1,6.7L19,4.72C19,4.72 16.56,2 12.1,2C6.42,2 2.03,6.8 2.03,12C2.03,17.05 6.16,22 12.25,22C17.6,22 21.5,18.33 21.5,12.91C21.5,11.76 21.35,11.1 21.35,11.1V11.1Z" fill="#EA4335" />
                            </svg>
                            <span class="ml-2 font-bold">Google</span>
                        </a>
                        <a v-if="$page.props.store_settings.facebook_auth_enabled === '1'" :href="route('social.redirect', 'facebook')" class="inline-flex items-center justify-center py-2.5 px-4 border border-gray-300 dark:border-gray-700 rounded-md shadow-sm bg-white dark:bg-gray-800 text-sm font-medium text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-700 transition">
                            <svg class="h-5 w-5" viewBox="0 0 24 24" fill="currentColor">
                                <path d="M12 2.04C6.5 2.04 2 6.53 2 12.06C2 17.06 5.66 21.21 10.44 21.96V14.96H7.9V12.06H10.44V9.85C10.44 7.34 11.93 5.96 14.22 5.96C15.31 5.96 16.45 6.15 16.45 6.15V8.62H15.19C13.95 8.62 13.56 9.39 13.56 10.18V12.06H16.34L15.89 14.96H13.56V21.96A10 10 0 0 0 22 12.06C22 6.53 17.5 2.04 12 2.04Z" fill="#1877F2"/>
                            </svg>
                            <span class="ml-2 font-bold">Facebook</span>
                        </a>
                    </div>
                </form>
                <p class="text-center text-sm text-gray-600 dark:text-gray-400 mt-4">
                    Don't have an account?
                    <a :href="route('register')" class="font-medium text-blue-600 dark:text-blue-400 hover:underline">Sign up</a>
                </p>
            </div>
        </div>
    </Layout>
</template>
