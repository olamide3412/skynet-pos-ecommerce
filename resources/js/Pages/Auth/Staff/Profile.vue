<script setup>
import TextInput from '@/Components/Forms/TextInput.vue';
import { useForm, usePage } from '@inertiajs/vue3';
import { ref, computed } from 'vue';
import { useToast } from 'vue-toastification';

const toast = useToast();
const page = usePage();

// Get the authenticated staff member's data
const staff = computed(() => page.props.auth.user.staff || {});

// Password change form
const passwordForm = useForm({
    current_password: '',
    password: '',
    password_confirmation: '',
});

const changePassword = () => {
    passwordForm.post(route('staff.password.update'), {
        preserveScroll: true,
        onSuccess: () => {
            //toast.success('Password updated successfully');
            passwordForm.reset();
        },
        onError: (errors) => {
            toast.error('Failed to update password. Please check your inputs.');
        },
    });
};
</script>

<template>
    <div class="max-w-4xl mx-auto py-8 px-4 sm:px-6 lg:px-8">
        <Head title="Staff Profile" />

        <div class="bg-white dark:bg-gray-800 shadow rounded-lg overflow-hidden">
            <!-- Profile Header -->
            <div class="bg-gradient-to-r from-blue-500 to-indigo-600 px-6 py-8 text-white">
                <div class="flex flex-col md:flex-row items-center">
                    <div class="w-24 h-24 rounded-full bg-white bg-opacity-20 p-1 mb-4 md:mb-0">
                        <img
                            v-if="staff.photo"
                            :src="staff.photo_url"
                            alt="Profile Photo"
                            class="w-full h-full rounded-full object-cover"
                        />
                        <div v-else class="w-full h-full rounded-full bg-gray-300 flex items-center justify-center">
                            <span class="text-2xl font-bold text-gray-600">
                                {{ staff.first_name?.[0] }}{{ staff.last_name?.[0] }}
                            </span>
                        </div>
                    </div>
                    <div class="md:ml-6 text-center md:text-left">
                        <h1 class="text-2xl font-bold">{{ staff.full_name }}</h1>
                        <p class="text-blue-100">{{ staff.position }}</p>
                        <p class="text-blue-100">{{ staff.department }}</p>
                        <p class="text-blue-100 mt-2">Staff ID: {{ staff.staff_number }}</p>
                    </div>
                </div>
            </div>

            <!-- Profile Details -->
            <div class="px-6 py-6">
                <h2 class="text-xl font-semibold mb-4 text-gray-800 dark:text-white">Personal Information</h2>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-8">
                    <div>
                        <h3 class="text-sm font-medium text-gray-500 dark:text-gray-400">Full Name</h3>
                        <p class="text-gray-900 dark:text-white">{{ staff.full_name }}</p>
                    </div>

                    <div>
                        <h3 class="text-sm font-medium text-gray-500 dark:text-gray-400">Email Address</h3>
                        <p class="text-gray-900 dark:text-white">{{ staff.email }}</p>
                    </div>

                    <div>
                        <h3 class="text-sm font-medium text-gray-500 dark:text-gray-400">Phone Number</h3>
                        <p class="text-gray-900 dark:text-white">{{ staff.phone_number || 'Not provided' }}</p>
                    </div>

                    <div>
                        <h3 class="text-sm font-medium text-gray-500 dark:text-gray-400">Gender</h3>
                        <p class="text-gray-900 dark:text-white">{{ staff.gender || 'Not provided' }}</p>
                    </div>

                    <div>
                        <h3 class="text-sm font-medium text-gray-500 dark:text-gray-400">Date of Birth</h3>
                        <p class="text-gray-900 dark:text-white">{{ $formatDate(staff.date_of_birth) || 'Not provided' }}</p>
                    </div>

                    <div>
                        <h3 class="text-sm font-medium text-gray-500 dark:text-gray-400">Date of Appointment</h3>
                        <p class="text-gray-900 dark:text-white">{{ $formatDate(staff.date_of_appointment) || 'Not provided' }}</p>
                    </div>
                </div>

                <h2 class="text-xl font-semibold mb-4 text-gray-800 dark:text-white">Contact Information</h2>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-8">
                    <div>
                        <h3 class="text-sm font-medium text-gray-500 dark:text-gray-400">Residential Address</h3>
                        <p class="text-gray-900 dark:text-white">{{ staff.residential_address || 'Not provided' }}</p>
                    </div>

                    <div>
                        <h3 class="text-sm font-medium text-gray-500 dark:text-gray-400">Local Government</h3>
                        <p class="text-gray-900 dark:text-white">{{ staff.local_government || 'Not provided' }}</p>
                    </div>

                    <div>
                        <h3 class="text-sm font-medium text-gray-500 dark:text-gray-400">State</h3>
                        <p class="text-gray-900 dark:text-white">{{ staff.state || 'Not provided' }}</p>
                    </div>
                </div>

                <h2 class="text-xl font-semibold mb-4 text-gray-800 dark:text-white">Employment Details</h2>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-8">
                    <div>
                        <h3 class="text-sm font-medium text-gray-500 dark:text-gray-400">Position</h3>
                        <p class="text-gray-900 dark:text-white">{{ staff.position || 'Not provided' }}</p>
                    </div>

                    <div>
                        <h3 class="text-sm font-medium text-gray-500 dark:text-gray-400">Department</h3>
                        <p class="text-gray-900 dark:text-white">{{ staff.department || 'Not provided' }}</p>
                    </div>

                    <div>
                        <h3 class="text-sm font-medium text-gray-500 dark:text-gray-400">Monthly Salary</h3>
                        <p class="text-gray-900 dark:text-white">{{ staff.monthly_salary ? formatCurrency(staff.monthly_salary) : 'Not provided' }}</p>
                    </div>
                </div>

                <h2 class="text-xl font-semibold mb-4 text-gray-800 dark:text-white">Identification</h2>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-8">
                    <div>
                        <h3 class="text-sm font-medium text-gray-500 dark:text-gray-400">NIN</h3>
                        <p class="text-gray-900 dark:text-white">{{ staff.nin || 'Not provided' }}</p>
                    </div>

                    <div>
                        <h3 class="text-sm font-medium text-gray-500 dark:text-gray-400">BVN</h3>
                        <p class="text-gray-900 dark:text-white">{{ staff.bvn || 'Not provided' }}</p>
                    </div>

                    <div>
                        <h3 class="text-sm font-medium text-gray-500 dark:text-gray-400">Bank Account</h3>
                        <p class="text-gray-900 dark:text-white">{{ staff.bank_account || 'Not provided' }}</p>
                    </div>

                    <div>
                        <h3 class="text-sm font-medium text-gray-500 dark:text-gray-400">Bank Name</h3>
                        <p class="text-gray-900 dark:text-white">{{ staff.bank_name || 'Not provided' }}</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Password Change Form -->
        <div class="bg-white dark:bg-gray-800 shadow rounded-lg mt-8 overflow-hidden">
            <div class="px-6 py-5 border-b border-gray-200 dark:border-gray-700">
                <h2 class="text-xl font-semibold text-gray-800 dark:text-white">Change Password</h2>
            </div>

            <div class="px-6 py-6">
                <form @submit.prevent="changePassword">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <TextInput
                            name="current_password"
                            label="Current Password"
                            type="password"
                            v-model="passwordForm.current_password"
                            :message="passwordForm.errors.current_password"
                            :required="true"
                        />

                        <div></div> <!-- Empty column for layout -->

                        <TextInput
                            name="password"
                            label="New Password"
                            type="password"
                            v-model="passwordForm.password"
                            :message="passwordForm.errors.password"
                            :required="true"
                        />

                        <TextInput
                            name="password_confirmation"
                            label="Confirm New Password"
                            type="password"
                            v-model="passwordForm.password_confirmation"
                            :message="passwordForm.errors.password_confirmation"
                            :required="true"
                        />
                    </div>

                    <div class="mt-6 flex justify-end">
                        <button
                            type="submit"
                            class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 transition-colors"
                            :disabled="passwordForm.processing"
                        >
                            <span v-if="passwordForm.processing">Updating...</span>
                            <span v-else>Update Password</span>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</template>

<style scoped>
.bg-gradient-to-r {
    background-image: linear-gradient(to right, var(--tw-gradient-stops));
}

.from-blue-500 {
    --tw-gradient-from: #3b82f6;
    --tw-gradient-stops: var(--tw-gradient-from), var(--tw-gradient-to, rgb(59 130 246 / 0));
}

.to-indigo-600 {
    --tw-gradient-to: #4f46e5;
}
</style>
