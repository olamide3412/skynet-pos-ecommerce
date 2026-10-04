<script setup>
import { useForm } from '@inertiajs/vue3';
import { useToast } from 'vue-toastification';
import Layout from '@/Layouts/Layout.vue';
import TextInput from '@/Components/Forms/TextInput.vue';

const toast = useToast();

const form = useForm({
    current_password: '',
    password: '',
    password_confirmation: '',
});

const updatePassword = () => {
    form.put(route('profile.password.update'), {
        preserveScroll: true,
        onSuccess: () => {
            form.reset();
            toast.success('Password updated successfully.');
        },
        onError: () => {
            if (form.errors.password) {
                form.reset('password', 'password_confirmation');
            }
            if (form.errors.current_password) {
                form.reset('current_password');
            }
        },
    });
};
</script>

<template>
    <Head title="Profile" />

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
            <div class="p-4 sm:p-8 bg-white dark:bg-gray-800 shadow sm:rounded-lg">
                <section class="max-w-xl">
                    <header>
                        <h2 class="text-lg font-medium text-gray-900 dark:text-gray-100">Update Password</h2>
                        <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
                            Ensure your account is using a long, random password to stay secure.
                        </p>
                    </header>

                    <form @submit.prevent="updatePassword" class="mt-6 space-y-6">
                        <div>
                            <TextInput
                                id="current_password"
                                v-model="form.current_password"
                                type="password"
                                class="mt-1 block w-full"
                                label="Current Password"
                                :message="form.errors.current_password"
                                autocomplete="current-password"
                            />
                        </div>

                        <div>
                            <TextInput
                                id="password"
                                v-model="form.password"
                                type="password"
                                class="mt-1 block w-full"
                                label="New Password"
                                :message="form.errors.password"
                                autocomplete="new-password"
                            />
                        </div>

                        <div>
                            <TextInput
                                id="password_confirmation"
                                v-model="form.password_confirmation"
                                type="password"
                                class="mt-1 block w-full"
                                label="Confirm Password"
                                :message="form.errors.password_confirmation"
                                autocomplete="new-password"
                            />
                        </div>

                        <div class="flex items-center gap-4">
                            <button class="btn-primary" :disabled="form.processing">Save</button>

                            <Transition
                                enter-active-class="transition ease-in-out"
                                enter-from-class="opacity-0"
                                leave-active-class="transition ease-in-out"
                                leave-to-class="opacity-0"
                            >
                                <p v-if="form.recentlySuccessful" class="text-sm text-gray-600 dark:text-gray-400">Saved.</p>
                            </Transition>
                        </div>
                    </form>
                </section>
            </div>
        </div>
    </div>
</template>
