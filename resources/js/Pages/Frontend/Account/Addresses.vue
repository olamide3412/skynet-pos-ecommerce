<script setup>
import { ref } from 'vue';
import { useForm, Link, router } from '@inertiajs/vue3';
import Layout from '@/Layouts/Layout.vue';
import TextInput from '@/Components/Forms/TextInput.vue';

const props = defineProps({
    addresses: Array,
});

const isCreating = ref(false);
const editingAddress = ref(null);

const addressForm = useForm({
    name: '',
    phone: '',
    address: '',
    city: '',
    state: '',
    country: 'Nigeria',
    is_default: false,
});

const openCreate = () => {
    editingAddress.value = null;
    addressForm.reset();
    addressForm.country = 'Nigeria';
    addressForm.is_default = props.addresses.length === 0;
    isCreating.value = true;
};

const openEdit = (addr) => {
    editingAddress.value = addr;
    addressForm.name = addr.name;
    addressForm.phone = addr.phone;
    addressForm.address = addr.address;
    addressForm.city = addr.city;
    addressForm.state = addr.state;
    addressForm.country = addr.country || 'Nigeria';
    addressForm.is_default = addr.is_default;
    isCreating.value = true;
};

const cancelForm = () => {
    isCreating.value = false;
    editingAddress.value = null;
    addressForm.reset();
};

const saveAddress = () => {
    if (editingAddress.value) {
        addressForm.put(route('customer.addresses.update', editingAddress.value.id), {
            preserveScroll: true,
            onSuccess: () => cancelForm(),
        });
    } else {
        addressForm.post(route('customer.addresses.store'), {
            preserveScroll: true,
            onSuccess: () => cancelForm(),
        });
    }
};

const deleteAddress = (addr) => {
    if (confirm('Are you sure you want to delete this address?')) {
        router.delete(route('customer.addresses.delete', addr.id), {
            preserveScroll: true,
        });
    }
};

const makeDefault = (addr) => {
    router.post(route('customer.addresses.default', addr.id), {}, {
        preserveScroll: true,
    });
};
</script>

<template>
    <Layout>
        <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 py-10">
            <!-- Account Navigation Breadcrumb / Tabs -->
            <div class="mb-8 flex flex-wrap items-center justify-between gap-4 border-b border-gray-200 dark:border-gray-800 pb-4">
                <div>
                    <h1 class="text-3xl font-bold text-gray-900 dark:text-white">Address Book</h1>
                    <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">Manage delivery locations for quick, seamless checkout.</p>
                </div>
                <div class="flex items-center gap-2">
                    <Link
                        :href="route('customer.profile')"
                        class="px-4 py-2 rounded-lg text-sm font-semibold bg-gray-100 dark:bg-gray-800 text-gray-700 dark:text-gray-300 hover:bg-gray-200 dark:hover:bg-gray-700 transition"
                    >
                        Profile & Security
                    </Link>
                    <Link
                        :href="route('customer.addresses')"
                        class="px-4 py-2 rounded-lg text-sm font-bold bg-primary text-white shadow-xs"
                    >
                        Address Book
                    </Link>
                    <Link
                        :href="route('customer.orders')"
                        class="px-4 py-2 rounded-lg text-sm font-semibold bg-gray-100 dark:bg-gray-800 text-gray-700 dark:text-gray-300 hover:bg-gray-200 dark:hover:bg-gray-700 transition"
                    >
                        Order History
                    </Link>
                </div>
            </div>

            <!-- Header Action Button -->
            <div class="flex justify-between items-center mb-6">
                <p class="text-sm text-gray-600 dark:text-gray-400">
                    {{ addresses.length }} saved {{ addresses.length === 1 ? 'address' : 'addresses' }}
                </p>
                <button
                    v-if="!isCreating"
                    @click="openCreate"
                    class="px-4 py-2 rounded-xl bg-primary hover:opacity-90 text-white font-bold text-sm shadow-md transition flex items-center gap-2"
                >
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                    </svg>
                    Add New Address
                </button>
            </div>

            <!-- Address Creation / Editing Modal Form Box -->
            <div v-if="isCreating" class="bg-white dark:bg-gray-800 rounded-2xl border-2 border-primary/40 shadow-lg p-6 sm:p-7 mb-8 transition-all">
                <div class="flex items-center justify-between mb-5 pb-3 border-b border-gray-100 dark:border-gray-700">
                    <h2 class="text-lg font-bold text-gray-900 dark:text-white">
                        {{ editingAddress ? 'Edit Address' : 'Add New Shipping Address' }}
                    </h2>
                    <button @click="cancelForm" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-200">
                        ✕
                    </button>
                </div>

                <form @submit.prevent="saveAddress" class="space-y-4">
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <TextInput
                                name="name"
                                label="Contact Name"
                                v-model="addressForm.name"
                                placeholder="Recipient name"
                                :required="true"
                            />
                            <p v-if="addressForm.errors.name" class="text-xs text-red-500 mt-1">{{ addressForm.errors.name }}</p>
                        </div>
                        <div>
                            <TextInput
                                name="phone"
                                label="Phone Number"
                                v-model="addressForm.phone"
                                placeholder="Recipient phone"
                                :required="true"
                            />
                            <p v-if="addressForm.errors.phone" class="text-xs text-red-500 mt-1">{{ addressForm.errors.phone }}</p>
                        </div>
                    </div>

                    <div>
                        <label class="block text-sm font-semibold mb-1 text-gray-700 dark:text-gray-300">Street Address</label>
                        <textarea
                            v-model="addressForm.address"
                            rows="2"
                            required
                            placeholder="House number, street, landmark"
                            class="w-full px-3.5 py-2.5 rounded-lg border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-900 text-gray-900 dark:text-white focus:ring-2 focus:ring-primary focus:border-transparent text-sm"
                        ></textarea>
                        <p v-if="addressForm.errors.address" class="text-xs text-red-500 mt-1">{{ addressForm.errors.address }}</p>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <TextInput
                                name="city"
                                label="City"
                                v-model="addressForm.city"
                                placeholder="e.g. Asaba, Warri"
                                :required="true"
                            />
                            <p v-if="addressForm.errors.city" class="text-xs text-red-500 mt-1">{{ addressForm.errors.city }}</p>
                        </div>
                        <div>
                            <TextInput
                                name="state"
                                label="State"
                                v-model="addressForm.state"
                                placeholder="e.g. Delta State"
                                :required="true"
                            />
                            <p v-if="addressForm.errors.state" class="text-xs text-red-500 mt-1">{{ addressForm.errors.state }}</p>
                        </div>
                    </div>

                    <div class="flex items-center gap-2 pt-2">
                        <input
                            type="checkbox"
                            id="is_default"
                            v-model="addressForm.is_default"
                            class="rounded border-gray-300 text-primary focus:ring-primary h-4 w-4"
                        />
                        <label for="is_default" class="text-sm font-medium text-gray-700 dark:text-gray-300">
                            Set as default shipping address
                        </label>
                    </div>

                    <div class="flex items-center justify-end gap-3 pt-3">
                        <button
                            type="button"
                            @click="cancelForm"
                            class="px-5 py-2 rounded-lg border border-gray-300 dark:border-gray-700 text-sm font-semibold hover:bg-gray-100 dark:hover:bg-gray-700 transition"
                        >
                            Cancel
                        </button>
                        <button
                            type="submit"
                            :disabled="addressForm.processing"
                            class="px-6 py-2 rounded-lg bg-primary hover:opacity-90 text-white text-sm font-bold shadow-md transition disabled:opacity-50"
                        >
                            {{ addressForm.processing ? 'Saving...' : (editingAddress ? 'Update Address' : 'Save Address') }}
                        </button>
                    </div>
                </form>
            </div>

            <!-- Address Cards Grid -->
            <div v-if="addresses.length === 0 && !isCreating" class="text-center py-16 bg-white dark:bg-gray-800 rounded-2xl border border-gray-200 dark:border-gray-700 p-8">
                <div class="w-16 h-16 rounded-full bg-primary/10 text-primary flex items-center justify-center mx-auto mb-4">
                    <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" />
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z" />
                    </svg>
                </div>
                <h3 class="text-lg font-bold mb-1 text-gray-900 dark:text-white">No saved addresses yet</h3>
                <p class="text-sm text-gray-500 dark:text-gray-400 max-w-sm mx-auto mb-6">
                    Add your delivery addresses to speed up future checkout orders.
                </p>
                <button
                    @click="openCreate"
                    class="px-6 py-2.5 rounded-xl bg-primary hover:opacity-90 text-white font-bold text-sm shadow-md transition"
                >
                    Add Address Now
                </button>
            </div>

            <div v-else class="grid grid-cols-1 md:grid-cols-2 gap-5">
                <div
                    v-for="addr in addresses"
                    :key="addr.id"
                    :class="[
                        'p-5 rounded-2xl border transition relative flex flex-col justify-between bg-white dark:bg-gray-800',
                        addr.is_default
                            ? 'border-primary ring-1 ring-primary/40 shadow-sm'
                            : 'border-gray-200 dark:border-gray-700'
                    ]"
                >
                    <div>
                        <div class="flex items-center justify-between mb-2.5">
                            <h3 class="font-bold text-gray-900 dark:text-white text-base">{{ addr.name }}</h3>
                            <span
                                v-if="addr.is_default"
                                class="px-2.5 py-0.5 rounded-full text-xs font-bold bg-primary/10 text-primary border border-primary/20"
                            >
                                Default Address
                            </span>
                        </div>
                        <p class="text-sm text-gray-600 dark:text-gray-300 font-medium mb-1">{{ addr.phone }}</p>
                        <p class="text-sm text-gray-600 dark:text-gray-400 leading-relaxed mb-4">
                            {{ addr.address }}, {{ addr.city }}, {{ addr.state }}
                        </p>
                    </div>

                    <div class="flex items-center justify-between pt-3 border-t border-gray-100 dark:border-gray-700/60 text-xs font-semibold">
                        <div class="flex items-center gap-3">
                            <button
                                @click="openEdit(addr)"
                                class="text-primary hover:underline font-bold"
                            >
                                Edit
                            </button>
                            <button
                                @click="deleteAddress(addr)"
                                class="text-red-500 hover:underline"
                            >
                                Delete
                            </button>
                        </div>
                        <button
                            v-if="!addr.is_default"
                            @click="makeDefault(addr)"
                            class="text-gray-500 hover:text-gray-900 dark:hover:text-white hover:underline"
                        >
                            Set as default
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </Layout>
</template>

