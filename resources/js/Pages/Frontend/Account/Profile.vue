<script setup>
import { useForm, Link } from '@inertiajs/vue3';
import Layout from '@/Layouts/Layout.vue';
import TextInput from '@/Components/Forms/TextInput.vue';

const props = defineProps({
    customer: Object,
});

const profileForm = useForm({
    name: props.customer.name,
    phone: props.customer.phone || '',
});

const passwordForm = useForm({
    current_password: '',
    password: '',
    password_confirmation: '',
});

const updateProfile = () => {
    profileForm.put(route('customer.profile.update'), {
        preserveScroll: true,
    });
};

const updatePassword = () => {
    passwordForm.put(route('customer.password.update'), {
        preserveScroll: true,
        onSuccess: () => passwordForm.reset(),
    });
};
</script>

<template>
    <Layout>
        <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 py-10">
            <!-- Account Navigation Breadcrumb / Tabs -->
            <div class="mb-8 flex flex-wrap items-center justify-between gap-4 border-b border-gray-200 dark:border-gray-800 pb-4">
                <div>
                    <h1 class="text-3xl font-bold text-gray-900 dark:text-white">My Account</h1>
                    <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">Manage your profile, password, and saved addresses.</p>
                </div>
                <div class="flex items-center gap-2">
                    <Link
                        :href="route('customer.profile')"
                        class="px-4 py-2 rounded-lg text-sm font-bold bg-primary text-white shadow-xs"
                    >
                        Profile & Security
                    </Link>
                    <Link
                        :href="route('customer.addresses')"
                        class="px-4 py-2 rounded-lg text-sm font-semibold bg-gray-100 dark:bg-gray-800 text-gray-700 dark:text-gray-300 hover:bg-gray-200 dark:hover:bg-gray-700 transition"
                    >
                        Address Book
                    </Link>
                    <Link
                        :href="route('customer.orders')"
                        class="px-4 py-2 rounded-lg text-sm font-semibold bg-gray-100 dark:bg-gray-800 text-gray-700 dark:text-gray-300 hover:bg-gray-200 dark:hover:bg-gray-700 transition"
                    >
                        Order History
                    </Link>
                    <Link
                        :href="route('customer.referral.index')"
                        class="px-4 py-2 rounded-lg text-sm font-semibold bg-gray-100 dark:bg-gray-800 text-gray-700 dark:text-gray-300 hover:bg-gray-200 dark:hover:bg-gray-700 transition"
                    >
                        Referral Program
                    </Link>
                </div>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
                <!-- Personal Info Card -->
                <div class="bg-white dark:bg-gray-800 rounded-2xl border border-gray-200 dark:border-gray-700 shadow-sm p-6 sm:p-7">
                    <div class="flex items-center gap-3 mb-6 pb-4 border-b border-gray-100 dark:border-gray-700">
                        <div class="w-10 h-10 rounded-full bg-primary/10 text-primary flex items-center justify-center font-bold">
                            {{ customer.name.charAt(0).toUpperCase() }}
                        </div>
                        <div>
                            <h2 class="text-lg font-bold text-gray-900 dark:text-white">Profile Details</h2>
                            <p class="text-xs text-gray-500 dark:text-gray-400">Member since {{ customer.created_at }}</p>
                        </div>
                    </div>

                    <form @submit.prevent="updateProfile" class="space-y-4">
                        <TextInput
                            name="name"
                            label="Full Name"
                            v-model="profileForm.name"
                            :required="true"
                        />
                        <p v-if="profileForm.errors.name" class="text-xs text-red-500">{{ profileForm.errors.name }}</p>

                        <!-- Read-only Email Field with Verified Badge and Lock Icon -->
                        <div>
                            <div class="flex items-center justify-between mb-1">
                                <label class="block text-xs font-semibold text-gray-700 dark:text-gray-300">Email Address</label>
                                <span class="inline-flex items-center gap-1 text-[11px] font-bold text-emerald-600 dark:text-emerald-400 bg-emerald-50 dark:bg-emerald-950/50 px-2 py-0.5 rounded-full border border-emerald-200 dark:border-emerald-800">
                                    <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/></svg>
                                    Verified
                                </span>
                            </div>
                            <div class="relative">
                                <input
                                    :value="customer.email"
                                    type="email"
                                    readonly
                                    disabled
                                    class="w-full text-sm rounded-lg border border-gray-200 dark:border-gray-700 bg-gray-100 dark:bg-gray-900/60 text-gray-500 dark:text-gray-400 cursor-not-allowed pl-3 pr-9 py-2.5 font-mono select-none"
                                />
                                <div class="absolute inset-y-0 right-3 flex items-center pointer-events-none text-gray-400">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/>
                                    </svg>
                                </div>
                            </div>
                            <p class="text-[11px] text-gray-400 dark:text-gray-500 mt-1">Email address is read-only to safeguard account identity and order history.</p>
                        </div>

                        <TextInput
                            name="phone"
                            label="Phone Number"
                            v-model="profileForm.phone"
                            placeholder="+234..."
                        />
                        <p v-if="profileForm.errors.phone" class="text-xs text-red-500">{{ profileForm.errors.phone }}</p>

                        <button
                            type="submit"
                            :disabled="profileForm.processing"
                            class="w-full py-2.5 rounded-xl bg-primary hover:opacity-90 text-white font-bold text-sm shadow-md transition disabled:opacity-50 mt-2"
                        >
                            {{ profileForm.processing ? 'Saving...' : 'Save Profile Changes' }}
                        </button>
                    </form>
                </div>

                <!-- Password Update Card -->
                <div class="bg-white dark:bg-gray-800 rounded-2xl border border-gray-200 dark:border-gray-700 shadow-sm p-6 sm:p-7">
                    <div class="flex items-center gap-3 mb-6 pb-4 border-b border-gray-100 dark:border-gray-700">
                        <div class="w-10 h-10 rounded-full bg-primary/10 text-primary flex items-center justify-center">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                            </svg>
                        </div>
                        <div>
                            <h2 class="text-lg font-bold text-gray-900 dark:text-white">Security & Password</h2>
                            <p class="text-xs text-gray-500 dark:text-gray-400">Ensure your account uses a secure password.</p>
                        </div>
                    </div>

                    <form @submit.prevent="updatePassword" class="space-y-4">
                        <TextInput
                            name="current_password"
                            label="Current Password"
                            type="password"
                            v-model="passwordForm.current_password"
                            :required="true"
                        />
                        <p v-if="passwordForm.errors.current_password" class="text-xs text-red-500">{{ passwordForm.errors.current_password }}</p>

                        <TextInput
                            name="password"
                            label="New Password"
                            type="password"
                            v-model="passwordForm.password"
                            :required="true"
                        />
                        <p v-if="passwordForm.errors.password" class="text-xs text-red-500">{{ passwordForm.errors.password }}</p>

                        <TextInput
                            name="password_confirmation"
                            label="Confirm New Password"
                            type="password"
                            v-model="passwordForm.password_confirmation"
                            :required="true"
                        />
                        <p v-if="passwordForm.errors.password_confirmation" class="text-xs text-red-500">{{ passwordForm.errors.password_confirmation }}</p>

                        <button
                            type="submit"
                            :disabled="passwordForm.processing"
                            class="w-full py-2.5 rounded-xl bg-gray-900 dark:bg-gray-700 hover:opacity-90 text-white font-bold text-sm shadow-md transition disabled:opacity-50 mt-2"
                        >
                            {{ passwordForm.processing ? 'Updating...' : 'Update Password' }}
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </Layout>
</template>

