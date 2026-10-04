<script setup>
import { useForm } from '@inertiajs/vue3'
import { ref, onMounted } from 'vue'
import Layout from '@/Layouts/Layout.vue';
import TextInput from '@/Components/Forms/TextInput.vue';
import { useToast } from 'vue-toastification';
import { usePage } from '@inertiajs/vue3';

const toast = useToast();
const form = useForm({
  name: '',
  email: '',
  password: '',
  password_confirmation: '',
  phone: '',
  cf_turnstile_response: '',
});

const turnstileWidgetId = ref(null);
const isTurnstileLoaded = ref(false);
const turnstileError = ref('');

const loadTurnstile = () => {
    if (window.turnstile) {
        renderTurnstile();
        return;
    }
    const script = document.createElement('script');
    script.src = 'https://challenges.cloudflare.com/turnstile/v0/api.js?render=explicit';
    script.async = true;
    script.defer = true;
    script.onload = () => { isTurnstileLoaded.value = true; renderTurnstile(); };
    script.onerror = () => { turnstileError.value = 'Failed to load CAPTCHA.'; };
    document.head.appendChild(script);
};

const renderTurnstile = () => {
    if (window.turnstile && document.getElementById('cf-turnstile-widget')) {
        turnstileWidgetId.value = window.turnstile.render('#cf-turnstile-widget', {
            sitekey: usePage().props.turnstileSiteKey,
            callback: (token) => { form.cf_turnstile_response = token; turnstileError.value = ''; },
            'expired-callback': () => { form.cf_turnstile_response = ''; turnstileError.value = 'CAPTCHA expired.'; resetTurnstile(); },
            'error-callback': () => { form.cf_turnstile_response = ''; turnstileError.value = 'CAPTCHA error.'; resetTurnstile(); }
        });
    }
};

const resetTurnstile = () => {
    if (window.turnstile && turnstileWidgetId.value) window.turnstile.reset(turnstileWidgetId.value);
};

onMounted(() => { loadTurnstile(); });

const handleRegister = () => {
    if (!form.cf_turnstile_response) {
        turnstileError.value = 'Please complete the CAPTCHA verification';
        return;
    }

    form.post(route('customer.register.submit'), {
        onFinish: () => {
            form.reset('password', 'password_confirmation');
            resetTurnstile();
        },
        onError: () => {
            toast.error('Registration failed. Check the form for errors.');
            if (form.errors.cf_turnstile_response) {
                resetTurnstile();
                form.cf_turnstile_response = '';
            }
        },
        onSuccess: () => {
            toast.success('Registration successful!');
            form.reset();
        }
    });
}
</script>
<template>
    <Layout>
        <div class="min-h-[70vh] flex items-center justify-center px-4 my-10">
            <div class="w-full max-w-lg bg-white dark:bg-slate-900 rounded-lg border border-gray-200 dark:border-gray-800 shadow-md p-8 space-y-6">
                <div class="text-center">
                    <h2 class="text-3xl font-bold text-gray-800 dark:text-gray-100">Create an Account</h2>
                    <p class="text-sm text-gray-500 dark:text-gray-400 mt-2">Join {{ $page.props.store_settings.company_name || 'Skynet' }} today.</p>
                </div>
                <form @submit.prevent="handleRegister" class="mt-6 space-y-6">
                    <TextInput name="name" label="Full Name" v-model="form.name" type="text" placeholder="John Doe" :message="form.errors.name" autofocus :required="true"/>
                    <TextInput name="email" label="Email Address" v-model="form.email" type="email" placeholder="you@example.com" :message="form.errors.email" :required="true"/>
                    <TextInput name="phone" label="Phone Number (Optional)" v-model="form.phone" type="text" placeholder="+234 800 000 0000" :message="form.errors.phone" />
                    <div class="grid grid-cols-2 gap-4">
                        <TextInput name="password" label="Password" type="password" v-model="form.password" placeholder="••••••••" :message="form.errors.password" :required="true"/>
                        <TextInput name="password_confirmation" label="Confirm Password" type="password" v-model="form.password_confirmation" placeholder="••••••••" :required="true"/>
                    </div>
                    <!-- Cloudflare Turnstile Widget -->
                    <div class="flex justify-left mt-2">
                        <div id="cf-turnstile-widget" class="cf-turnstile"></div>
                    </div>
                    <div v-if="turnstileError" class="text-red-600 text-sm mt-1">
                        {{ turnstileError }}
                    </div>
                    <div v-if="form.errors.cf_turnstile_response" class="text-red-600 text-sm mt-1">
                        {{ form.errors.cf_turnstile_response }}
                    </div>

                    <button class="w-full bg-blue-600 hover:bg-blue-700 text-white font-bold py-3 rounded-md transition" :disabled="form.processing">Register</button>
                    <!-- Divider -->
                    <div v-if="$page.props.store_settings.google_auth_enabled === '1' || $page.props.store_settings.facebook_auth_enabled === '1'" class="relative">
                        <div class="absolute inset-0 flex items-center">
                            <div class="w-full border-t border-gray-300 dark:border-gray-700"></div>
                        </div>
                        <div class="relative flex justify-center text-sm">
                            <span class="px-2 bg-white dark:bg-slate-900 text-gray-500 dark:text-gray-400">Or continue with</span>
                        </div>
                    </div>

                    <!-- Social Buttons -->
                    <div v-if="$page.props.store_settings.google_auth_enabled === '1' || $page.props.store_settings.facebook_auth_enabled === '1'" class="grid grid-cols-2 gap-3">
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
                
                <p class="text-center text-sm text-gray-600 dark:text-gray-400 mt-4 border-t dark:border-gray-800 pt-6">
                    Already have an account?
                    <a :href="route('login')" class="font-medium text-blue-600 dark:text-blue-400 hover:underline">Sign in</a>
                </p>
            </div>
        </div>
    </Layout>
</template>
