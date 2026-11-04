<script setup>
import { useForm, Link } from '@inertiajs/vue3';
import Layout from '@/Layouts/Layout.vue';
import TextInput from '@/Components/Forms/TextInput.vue';

const props = defineProps({
    status: String,
});

const form = useForm({
    email: '',
});

const submit = () => {
    form.post(route('password.email'));
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

                <div v-if="status || $page.props.flash.status" class="p-3.5 rounded-lg bg-green-50 dark:bg-green-900/30 text-green-700 dark:text-green-300 text-sm font-medium">
                    {{ status || $page.props.flash.status }}
                </div>

                <form @submit.prevent="submit" class="space-y-5">
                    <TextInput
                        name="email"
                        label="Email Address"
                        v-model="form.email"
                        type="email"
                        placeholder="you@example.com"
                        autofocus
                        :required="true"
                    />
                    <p v-if="form.errors.email" class="text-red-500 text-xs mt-1">{{ form.errors.email }}</p>

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

