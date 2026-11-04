<script setup>
import { ref, watch } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import Pagination from '@/Components/Pagination.vue';

const props = defineProps({
    subscribers: Object,
    filters: Object,
    stats: Object,
});

const search = ref(props.filters.search || '');
const status = ref(props.filters.status || 'all');

let searchTimeout = null;

const applyFilters = () => {
    router.get(
        route('admin.newsletter.index'),
        {
            search: search.value || undefined,
            status: status.value !== 'all' ? status.value : undefined,
        },
        {
            preserveState: true,
            preserveScroll: true,
            replace: true,
        }
    );
};

watch(search, () => {
    clearTimeout(searchTimeout);
    searchTimeout = setTimeout(applyFilters, 350);
});

watch(status, () => {
    applyFilters();
});

const clearFilters = () => {
    search.value = '';
    status.value = 'all';
    applyFilters();
};

const toggleStatus = (subscriber) => {
    router.patch(route('admin.newsletter.toggle-status', subscriber.id), {}, {
        preserveScroll: true,
    });
};

const deleteSubscriber = (subscriber) => {
    if (confirm(`Are you sure you want to remove ${subscriber.email} from the newsletter subscriber list?`)) {
        router.delete(route('admin.newsletter.destroy', subscriber.id), {
            preserveScroll: true,
        });
    }
};
</script>

<template>
    <Head title="Newsletter Subscribers — Admin" />

    <AdminLayout>
        <div class="space-y-6">
            <!-- Page Header -->
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                <div>
                    <h1 class="text-2xl font-black text-gray-900 dark:text-white tracking-tight flex items-center gap-2">
                        <span>📧</span> Newsletter Subscribers
                    </h1>
                    <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">
                        View, manage, and export audience members who signed up through the website footer.
                    </p>
                </div>

                <!-- Export CSV Button -->
                <div class="flex items-center gap-3">
                    <a
                        :href="route('admin.newsletter.export')"
                        target="_blank"
                        class="inline-flex items-center gap-2 px-4 py-2 bg-emerald-600 hover:bg-emerald-700 text-white rounded-lg text-xs font-bold shadow-xs transition"
                        title="Export subscribers to CSV for Mailchimp, Brevo, or spreadsheet"
                    >
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                        </svg>
                        Export CSV
                    </a>
                </div>
            </div>

            <!-- KPI Metric Cards -->
            <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
                <div class="bg-white dark:bg-gray-800 p-4 rounded-xl border border-gray-200 dark:border-gray-700 shadow-2xs">
                    <div class="text-xs font-bold text-gray-500 dark:text-gray-400 uppercase tracking-wider mb-1">
                        Total Audience
                    </div>
                    <div class="text-2xl font-black text-gray-900 dark:text-white">
                        {{ stats.total }}
                    </div>
                </div>

                <div class="bg-white dark:bg-gray-800 p-4 rounded-xl border border-gray-200 dark:border-gray-700 shadow-2xs">
                    <div class="text-xs font-bold text-emerald-600 dark:text-emerald-400 uppercase tracking-wider mb-1">
                        Active Subscribers
                    </div>
                    <div class="text-2xl font-black text-emerald-600 dark:text-emerald-400">
                        {{ stats.active }}
                    </div>
                </div>

                <div class="bg-white dark:bg-gray-800 p-4 rounded-xl border border-gray-200 dark:border-gray-700 shadow-2xs">
                    <div class="text-xs font-bold text-blue-600 dark:text-blue-400 uppercase tracking-wider mb-1">
                        New (Last 7 Days)
                    </div>
                    <div class="text-2xl font-black text-blue-600 dark:text-blue-400">
                        {{ stats.new_this_week }}
                    </div>
                </div>

                <div class="bg-white dark:bg-gray-800 p-4 rounded-xl border border-gray-200 dark:border-gray-700 shadow-2xs">
                    <div class="text-xs font-bold text-gray-500 dark:text-gray-400 uppercase tracking-wider mb-1">
                        Unsubscribed
                    </div>
                    <div class="text-2xl font-black text-gray-500 dark:text-gray-400">
                        {{ stats.unsubscribed }}
                    </div>
                </div>
            </div>

            <!-- Filter Controls -->
            <div class="bg-white dark:bg-gray-800 p-4 rounded-xl border border-gray-200 dark:border-gray-700 shadow-2xs flex flex-col sm:flex-row items-center justify-between gap-3">
                <div class="flex-1 w-full sm:max-w-md relative">
                    <input
                        v-model="search"
                        type="text"
                        placeholder="Search by email address..."
                        class="w-full pl-9 pr-4 py-2 rounded-lg border border-gray-300 dark:border-gray-600 bg-gray-50 dark:bg-gray-900 text-xs sm:text-sm text-gray-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-primary/40"
                    />
                    <svg class="w-4 h-4 text-gray-400 absolute left-3 top-1/2 -translate-y-1/2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                    </svg>
                </div>

                <div class="flex items-center gap-2 w-full sm:w-auto">
                    <select
                        v-model="status"
                        class="px-3 py-2 rounded-lg border border-gray-300 dark:border-gray-600 bg-gray-50 dark:bg-gray-900 text-xs sm:text-sm text-gray-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-primary/40 cursor-pointer"
                    >
                        <option value="all">All Statuses</option>
                        <option value="subscribed">Subscribed (Active)</option>
                        <option value="unsubscribed">Unsubscribed</option>
                    </select>

                    <button
                        v-if="search || status !== 'all'"
                        @click="clearFilters"
                        class="px-3 py-2 rounded-lg text-xs font-semibold text-gray-600 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700 transition"
                    >
                        Reset
                    </button>
                </div>
            </div>

            <!-- Subscribers Table -->
            <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 shadow-2xs overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-xs sm:text-sm">
                        <thead class="bg-gray-50 dark:bg-gray-900/60 border-b border-gray-200 dark:border-gray-700 text-gray-500 dark:text-gray-400 uppercase text-[10px] tracking-wider font-bold">
                            <tr>
                                <th class="px-4 py-3.5">Subscriber Email</th>
                                <th class="px-4 py-3.5">Status</th>
                                <th class="px-4 py-3.5">Source</th>
                                <th class="px-4 py-3.5">IP Address</th>
                                <th class="px-4 py-3.5">Subscribed Date</th>
                                <th class="px-4 py-3.5 text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-gray-700/60 text-gray-700 dark:text-gray-300">
                            <tr
                                v-for="sub in subscribers.data"
                                :key="sub.id"
                                class="hover:bg-gray-50/60 dark:hover:bg-gray-700/40 transition"
                            >
                                <!-- Email -->
                                <td class="px-4 py-3.5">
                                    <div class="flex items-center gap-2.5">
                                        <div class="w-7 h-7 rounded-full bg-primary/10 text-primary font-bold flex items-center justify-center text-xs shrink-0 uppercase">
                                            {{ sub.email.charAt(0) }}
                                        </div>
                                        <span class="font-semibold text-gray-900 dark:text-white">
                                            {{ sub.email }}
                                        </span>
                                    </div>
                                </td>

                                <!-- Status Badge -->
                                <td class="px-4 py-3.5">
                                    <span
                                        :class="[
                                            'px-2.5 py-0.5 rounded-full text-[10px] font-bold uppercase tracking-wider',
                                            sub.status === 'subscribed'
                                                ? 'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/40 dark:text-emerald-300'
                                                : 'bg-gray-100 text-gray-600 dark:bg-gray-700 dark:text-gray-300'
                                        ]"
                                    >
                                        {{ sub.status }}
                                    </span>
                                </td>

                                <!-- Source -->
                                <td class="px-4 py-3.5 text-xs text-gray-500 dark:text-gray-400 capitalize">
                                    {{ sub.source || 'Website' }}
                                </td>

                                <!-- IP -->
                                <td class="px-4 py-3.5 text-xs font-mono text-gray-500 dark:text-gray-400">
                                    {{ sub.ip_address || '—' }}
                                </td>

                                <!-- Date -->
                                <td class="px-4 py-3.5 text-xs text-gray-500 dark:text-gray-400 whitespace-nowrap">
                                    {{ new Date(sub.created_at).toLocaleDateString('en-US', { year: 'numeric', month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit' }) }}
                                </td>

                                <!-- Actions -->
                                <td class="px-4 py-3.5 text-right whitespace-nowrap">
                                    <div class="flex items-center justify-end gap-1.5">
                                        <!-- Toggle Status -->
                                        <button
                                            type="button"
                                            @click="toggleStatus(sub)"
                                            class="px-2.5 py-1 rounded text-[11px] font-semibold border border-gray-200 dark:border-gray-700 hover:bg-gray-100 dark:hover:bg-gray-700 transition"
                                            :title="sub.status === 'subscribed' ? 'Mark as unsubscribed' : 'Mark as subscribed'"
                                        >
                                            {{ sub.status === 'subscribed' ? 'Unsubscribe' : 'Resubscribe' }}
                                        </button>

                                        <!-- Delete -->
                                        <button
                                            type="button"
                                            @click="deleteSubscriber(sub)"
                                            class="p-1.5 rounded text-red-500 hover:bg-red-50 dark:hover:bg-red-950/40 transition"
                                            title="Delete subscriber"
                                        >
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                            </svg>
                                        </button>
                                    </div>
                                </td>
                            </tr>

                            <!-- Empty State -->
                            <tr v-if="!subscribers.data || subscribers.data.length === 0">
                                <td colspan="6" class="px-4 py-12 text-center text-gray-500 dark:text-gray-400">
                                    <div class="max-w-xs mx-auto space-y-2">
                                        <span class="text-3xl">📭</span>
                                        <p class="font-bold text-gray-800 dark:text-gray-200">No subscribers found</p>
                                        <p class="text-xs">
                                            When visitors enter their email into the footer newsletter box, they will appear here.
                                        </p>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Pagination -->
                <div v-if="subscribers.data && subscribers.data.length > 0" class="p-4 border-t border-gray-200 dark:border-gray-700">
                    <Pagination :links="subscribers.links" />
                </div>
            </div>
        </div>
    </AdminLayout>
</template>

