<script setup>
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import { useToast } from 'vue-toastification';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import SettingsNavTabs from '@/Components/Admin/SettingsNavTabs.vue';
import Pagination from '@/Components/Pagination.vue';

const toast = useToast();

const props = defineProps({
    pos_connection: Object,
    connection_logs: [Array, Object],
});

const logsList = computed(() => {
    if (Array.isArray(props.connection_logs)) return props.connection_logs;
    return props.connection_logs?.data || [];
});

const totalLogsCount = computed(() => {
    if (props.connection_logs?.total !== undefined) return props.connection_logs.total;
    return logsList.value.length;
});

const isRepairing = ref(!props.pos_connection?.is_connected);
const showApiSecret = ref(false);
const isTesting = ref(false);
const isReconciling = ref(false);

const pairingForm = useForm({
    pos_base_url: props.pos_connection?.pos_base_url || '',
    client_id: props.pos_connection?.client_id || '',
    api_secret: '',
});

const webhookUrl = computed(() => {
    return props.pos_connection?.webhook_url || (typeof window !== 'undefined' ? `${window.location.origin}/api/webhooks/pos-inventory` : '/api/webhooks/pos-inventory');
});

const copied = ref(false);
const copyWebhookUrl = () => {
    if (typeof navigator !== 'undefined' && navigator.clipboard) {
        navigator.clipboard.writeText(webhookUrl.value);
        copied.value = true;
        toast.success('Webhook URL copied to clipboard!');
        setTimeout(() => { copied.value = false; }, 2000);
    }
};

const saveAndTestConnection = () => {
    pairingForm.post(route('admin.pos-integration.save-and-test'), {
        preserveScroll: true,
        onSuccess: () => {
            pairingForm.api_secret = '';
            if (props.pos_connection?.is_connected) {
                isRepairing.value = false;
            }
        },
    });
};

const testConnection = () => {
    isTesting.value = true;
    router.post(route('admin.pos-integration.test'), {}, {
        preserveScroll: true,
        onFinish: () => {
            isTesting.value = false;
        }
    });
};

const reconcileLocks = () => {
    if (!props.pos_connection?.is_connected) return;
    isReconciling.value = true;
    router.post(route('admin.pos-integration.reconcile-mappings'), {}, {
        preserveScroll: true,
        onFinish: () => {
            isReconciling.value = false;
        }
    });
};
</script>

<template>
    <Head title="POS Branch Connection & Pairing" />
    <AdminLayout>
        <div class="max-w-5xl mx-auto space-y-6">

            <!-- Top Navigation Tabs -->
            <SettingsNavTabs />

            <!-- Header -->
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                <div>
                    <h1 class="text-2xl font-bold text-gray-900 dark:text-white flex items-center gap-2">
                        <span>🔗</span> POS Branch Connection & Pairing
                    </h1>
                    <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">
                        Connect, authenticate, and configure real-time inventory webhooks with your Skynet Digital POS physical branch.
                    </p>
                </div>
                <div class="flex items-center gap-2 shrink-0">
                    <button
                        v-if="pos_connection?.is_connected"
                        type="button"
                        @click="testConnection"
                        :disabled="isTesting"
                        class="px-3.5 py-2 border border-gray-300 dark:border-gray-600 rounded-lg text-sm font-semibold text-gray-700 dark:text-gray-200 bg-white dark:bg-gray-800 hover:bg-gray-50 dark:hover:bg-gray-700 transition flex items-center gap-2 shadow-xs disabled:opacity-50"
                    >
                        <svg class="w-4 h-4 text-primary" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>
                        {{ isTesting ? 'Testing...' : 'Test Connection' }}
                    </button>
                    <button
                        v-if="pos_connection?.is_connected"
                        type="button"
                        @click="reconcileLocks"
                        :disabled="isReconciling"
                        title="Synchronize deletion-protection locks with POS"
                        class="px-3.5 py-2 border border-secondary-soft rounded-lg text-sm font-semibold text-secondary bg-secondary-soft hover:opacity-90 transition flex items-center gap-2 shadow-xs disabled:opacity-50"
                    >
                        <svg class="w-4 h-4 text-secondary" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/></svg>
                        {{ isReconciling ? 'Reconciling...' : 'Reconcile Locks' }}
                    </button>
                </div>
            </div>

            <!-- Connection Status Summary Card -->
            <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-6 shadow-xs">
                <div class="flex flex-col md:flex-row md:items-center justify-between gap-5">
                    <div class="flex items-start space-x-4">
                        <div class="w-12 h-12 rounded-xl flex items-center justify-center bg-primary-soft text-primary font-bold text-xl shrink-0">
                            POS
                        </div>
                        <div>
                            <div class="flex items-center space-x-2">
                                <h3 class="text-base font-bold text-gray-900 dark:text-white">
                                    {{ pos_connection?.pos_branch_name ? `Branch: ${pos_connection.pos_branch_name}` : 'No Connected POS Branch' }}
                                </h3>
                                <span
                                    v-if="pos_connection?.is_connected"
                                    class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold bg-emerald-100 text-emerald-800 dark:bg-emerald-900/40 dark:text-emerald-300"
                                >
                                    ● Connected
                                </span>
                                <span
                                    v-else
                                    class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold bg-amber-100 text-amber-800 dark:bg-amber-900/40 dark:text-amber-300"
                                >
                                    ● Disconnected
                                </span>
                            </div>
                            <p class="text-xs text-gray-500 dark:text-gray-400 mt-1 font-mono">
                                Endpoint: <span class="text-gray-800 dark:text-gray-200">{{ pos_connection?.pos_base_url || 'Not configured' }}</span>
                                <span v-if="pos_connection?.pos_branch_id"> (Branch #{{ pos_connection.pos_branch_id }})</span>
                                <span v-if="pos_connection?.client_id" class="ml-2">| Client ID: {{ pos_connection.client_id }}</span>
                            </p>
                        </div>
                    </div>
                    <div class="grid grid-cols-2 sm:grid-cols-3 gap-4 text-xs text-gray-500 dark:text-gray-400 border-t md:border-t-0 pt-3 md:pt-0">
                        <div>
                            <span class="block text-gray-400">Last Request:</span>
                            <span class="font-medium text-gray-700 dark:text-gray-200">{{ pos_connection?.last_successful_request_at ? new Date(pos_connection.last_successful_request_at).toLocaleTimeString() : 'Never' }}</span>
                        </div>
                        <div>
                            <span class="block text-gray-400">Last Catalog Sync:</span>
                            <span class="font-medium text-gray-700 dark:text-gray-200">{{ pos_connection?.last_catalog_sync_at ? new Date(pos_connection.last_catalog_sync_at).toLocaleDateString() : 'Never' }}</span>
                        </div>
                        <div>
                            <span class="block text-gray-400">Last Stock Sync:</span>
                            <span class="font-medium text-gray-700 dark:text-gray-200">{{ pos_connection?.last_inventory_sync_at ? new Date(pos_connection.last_inventory_sync_at).toLocaleTimeString() : 'Never' }}</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Real-Time Webhook Configuration Banner -->
            <div class="rounded-xl border theme-gradient-banner p-5 shadow-xs">
                <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
                    <div class="flex items-start gap-3.5">
                        <div class="w-10 h-10 rounded-xl bg-primary text-white flex items-center justify-center shrink-0 shadow-xs">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>
                        </div>
                        <div>
                            <div class="flex items-center gap-2">
                                <h4 class="text-sm font-bold text-gray-900 dark:text-white uppercase tracking-wider">
                                    Real-Time Webhook Notification URL
                                </h4>
                                <span class="inline-flex items-center px-2 py-0.5 rounded text-[10px] font-bold bg-emerald-100 text-emerald-800 dark:bg-emerald-900/50 dark:text-emerald-300">
                                    ● LIVE RECEIVER ACTIVE
                                </span>
                            </div>
                            <p class="text-xs text-gray-600 dark:text-gray-400 mt-1">
                                Paste this webhook URL into <strong>POS Admin → Branches → [Branch] → Ecommerce tab → Webhook Notification URL</strong>. Physical sales, adjustments, and price changes will immediately push directly to this store.
                            </p>
                        </div>
                    </div>
                    <div class="flex items-center gap-2 shrink-0">
                        <code class="px-3 py-2 rounded-lg bg-white dark:bg-gray-800 border border-primary-soft text-xs font-mono text-primary select-all shadow-xs break-all">
                            {{ webhookUrl }}
                        </code>
                        <button
                            type="button"
                            @click="copyWebhookUrl"
                            class="px-4 py-2 bg-primary hover:bg-primary-dark text-white rounded-lg text-xs font-semibold shadow-xs transition flex items-center gap-1.5 shrink-0"
                        >
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"/></svg>
                            {{ copied ? 'Copied!' : 'Copy Link' }}
                        </button>
                    </div>
                </div>
            </div>

            <!-- Error Message Banner (if error exists) -->
            <div v-if="pos_connection?.last_error_message" class="p-4 rounded-xl bg-rose-50 dark:bg-rose-950/30 border border-rose-200 dark:border-rose-900/50 flex items-start gap-3">
                <svg class="w-5 h-5 text-rose-600 dark:text-rose-400 shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                <div class="flex-1">
                    <h4 class="text-sm font-semibold text-rose-800 dark:text-rose-300">Connection / Authentication Error</h4>
                    <p class="text-xs text-rose-700 dark:text-rose-400 mt-0.5 font-mono">{{ pos_connection.last_error_message }}</p>
                    <p v-if="pos_connection.last_error_at" class="text-[11px] text-rose-500 mt-1">Recorded: {{ new Date(pos_connection.last_error_at).toLocaleString() }}</p>
                </div>
            </div>

            <!-- Pairing Form Card -->
            <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-6 shadow-xs space-y-4">
                <div class="flex items-center justify-between border-b border-gray-100 dark:border-gray-700/60 pb-4">
                    <div>
                        <h3 class="text-sm font-bold text-gray-900 dark:text-white uppercase tracking-wider">
                            {{ pos_connection?.is_connected ? 'Branch Pairing & Credentials' : 'Pair New POS Connection' }}
                        </h3>
                        <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">
                            Enter the API Base URL, Client ID, and API Secret generated by POS Super Admin (Branches → Select Branch → Ecommerce Integration).
                        </p>
                    </div>
                    <button
                        v-if="pos_connection?.is_connected"
                        type="button"
                        @click="isRepairing = !isRepairing"
                        class="text-xs font-semibold text-primary hover:underline"
                    >
                        {{ isRepairing ? 'Hide Form' : 'Edit Credentials' }}
                    </button>
                </div>

                <form v-show="!pos_connection?.is_connected || isRepairing" @submit.prevent="saveAndTestConnection" class="space-y-4 pt-2">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div class="md:col-span-2">
                            <label class="block text-xs font-semibold text-gray-700 dark:text-gray-300 mb-1">
                                POS API Base URL *
                            </label>
                            <input
                                v-model="pairingForm.pos_base_url"
                                type="url"
                                placeholder="http://127.0.0.1:8000 or https://pos.yourcompany.com"
                                required
                                class="w-full px-3 py-2 text-sm border rounded-lg border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:ring-2 focus:ring-primary"
                            />
                            <p class="text-[11px] text-gray-400 mt-1">
                                Domain and protocol of the POS system without trailing slashes.
                            </p>
                        </div>

                        <div>
                            <label class="block text-xs font-semibold text-gray-700 dark:text-gray-300 mb-1">
                                Client ID *
                            </label>
                            <input
                                v-model="pairingForm.client_id"
                                type="text"
                                placeholder="e.g. client_pos_branch_10"
                                required
                                class="w-full px-3 py-2 text-sm border rounded-lg border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:ring-2 focus:ring-primary"
                            />
                        </div>

                        <div>
                            <label class="block text-xs font-semibold text-gray-700 dark:text-gray-300 mb-1">
                                API Secret Token *
                            </label>
                            <div class="relative">
                                <input
                                    v-model="pairingForm.api_secret"
                                    :type="showApiSecret ? 'text' : 'password'"
                                    placeholder="Paste API secret token from POS branch settings"
                                    :required="!pos_connection?.is_configured"
                                    class="w-full px-3 py-2 text-sm border rounded-lg border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:ring-2 focus:ring-primary pr-16"
                                />
                                <button
                                    type="button"
                                    @click="showApiSecret = !showApiSecret"
                                    class="absolute right-2 top-2 text-xs text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200"
                                >
                                    {{ showApiSecret ? 'Hide' : 'Show' }}
                                </button>
                            </div>
                        </div>
                    </div>

                    <div class="flex items-center justify-end gap-3 pt-4 border-t border-gray-100 dark:border-gray-700">
                        <button
                            v-if="pos_connection?.is_connected"
                            type="button"
                            @click="isRepairing = false"
                            class="px-4 py-2 text-sm font-semibold text-gray-600 dark:text-gray-400 hover:text-gray-800 dark:hover:text-gray-200"
                        >
                            Cancel
                        </button>
                        <button
                            type="submit"
                            :disabled="pairingForm.processing"
                            class="px-5 py-2.5 bg-primary hover:bg-primary-dark text-white rounded-lg text-sm font-semibold shadow-xs transition flex items-center gap-2 disabled:opacity-50"
                        >
                            <svg v-if="pairingForm.processing" class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path></svg>
                            <span>{{ pairingForm.processing ? 'Testing & Saving...' : 'Save & Verify Connection' }}</span>
                        </button>
                    </div>
                </form>
            </div>

            <!-- Recent Connection & Webhook Audit Trails -->
            <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 overflow-hidden shadow-xs">
                <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700 flex items-center justify-between">
                    <div>
                        <h3 class="text-sm font-bold text-gray-900 dark:text-white uppercase tracking-wider">
                            Recent Connection & Webhook Audit Logs
                        </h3>
                        <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">
                            Recent test pings, connection tests, and incoming webhook events received from the POS.
                        </p>
                    </div>
                    <span v-if="totalLogsCount > 0" class="px-2.5 py-1 text-xs font-semibold rounded-full bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-300">
                        {{ totalLogsCount }} Total Events
                    </span>
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700 text-left text-xs">
                        <thead class="bg-gray-50 dark:bg-gray-900/50 text-gray-600 dark:text-gray-400 font-medium">
                            <tr>
                                <th class="p-3">Time</th>
                                <th class="p-3">Type</th>
                                <th class="p-3">Status</th>
                                <th class="p-3">Reference</th>
                                <th class="p-3">Message</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 dark:divide-gray-700 text-gray-800 dark:text-gray-200 font-mono">
                            <tr v-for="log in logsList" :key="log.id" class="hover:bg-gray-50 dark:hover:bg-gray-700/60 transition">
                                <td class="p-3 whitespace-nowrap text-gray-500 font-sans">
                                    {{ new Date(log.created_at).toLocaleString() }}
                                </td>
                                <td class="p-3 font-semibold text-gray-700 dark:text-gray-300">
                                    {{ log.type }}
                                </td>
                                <td class="p-3">
                                    <span
                                        class="inline-flex px-1.5 py-0.5 rounded text-[10px] uppercase font-bold"
                                        :class="{
                                            'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/40 dark:text-emerald-300': log.status === 'success',
                                            'bg-rose-100 text-rose-800 dark:bg-rose-900/40 dark:text-rose-300': log.status === 'failed',
                                            'bg-amber-100 text-amber-800 dark:bg-amber-900/40 dark:text-amber-300': log.status === 'warning',
                                            'bg-blue-100 text-blue-800 dark:bg-blue-900/40 dark:text-blue-300': log.status === 'started'
                                        }"
                                    >
                                        {{ log.status }}
                                    </span>
                                </td>
                                <td class="p-3 text-gray-500">
                                    {{ log.reference || '—' }}
                                </td>
                                <td class="p-3 font-sans text-gray-700 dark:text-gray-300">
                                    {{ log.message }}
                                </td>
                            </tr>
                            <tr v-if="!logsList || !logsList.length">
                                <td colspan="5" class="p-6 text-center text-gray-500 font-sans">
                                    No connection or webhook events recorded yet.
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Pagination -->
                <div v-if="connection_logs?.links && connection_logs.links.length > 3" class="p-4 border-t border-gray-100 dark:border-gray-700/60">
                    <Pagination :links="connection_logs.links" />
                </div>
            </div>

        </div>
    </AdminLayout>
</template>
