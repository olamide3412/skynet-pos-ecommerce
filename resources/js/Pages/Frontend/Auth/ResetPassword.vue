<script setup>
import { useForm, Link } from '@inertiajs/vue3';
import Layout from '@/Layouts/Layout.vue';
import TextInput from '@/Components/Forms/TextInput.vue';

const props = defineProps({
    token: String,
    email: String,
});

const form = useForm({
    token: props.token || '',
    email: props.email || '',
    password: '',
    password_confirmation: '',
});

const submit = () => {
    form.post(route('password.update'), {
        onFinish: () => form.reset('password', 'password_confirmation'),
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
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                        </svg>
                    </div>
                    <h2 class="text-2xl font-bold text-gray-900 dark:text-gray-100">Set New Password</h2>
                    <p class="text-sm text-gray-500 dark:text-gray-400 mt-2">
                        Please choose a strong new password for your account.
                    </p>
                </div>

                <form @submit.prevent="submit" class="space-y-4">
                    <TextInput
                        name="email"
                        label="Email Address"
                        v-model="form.email"
                        type="email"
                        :required="true"
                    />
                    <p v-if="form.errors.email" class="text-red-500 text-xs mt-1">{{ form.errors.email }}</p>

                    <TextInput
                        name="password"
                        label="New Password"
                        v-model="form.password"
                        type="password"
                        placeholder="••••••••"
                        :required="true"
                        autofocus
                    />
                    <p v-if="form.errors.password" class="text-red-500 text-xs mt-1">{{ form.errors.password }}</p>

                    <TextInput
                        name="password_confirmation"
                        label="Confirm New Password"
                        v-model="form.password_confirmation"
                        type="password"
                        placeholder="••••••••"
                        :required="true"
                    />
                    <p v-if="form.errors.password_confirmation" class="text-red-500 text-xs mt-1">{{ form.errors.password_confirmation }}</p>

                    <button
                        type="submit"
                        :disabled="form.processing"
                        class="w-full py-3 rounded-lg bg-primary hover:opacity-90 text-white font-bold transition shadow-md disabled:opacity-50 mt-2"
                    >
                        {{ form.processing ? 'Resetting Password...' : 'Reset Password' }}
                    </button>
                </form>

                <div class="text-center pt-2">
                    <Link :href="route('login')" class="text-sm font-medium text-primary hover:underline">
                        Remembered your password? Sign in
                    </Link>
                </div>
            </div>
        </div>
    </Layout>
</template>

