<script setup>
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import Pagination from '@/Components/Pagination.vue';

const props = defineProps({
    connection: Object,
    items: Object,
    logs: Array,
    stats: Object,
    filters: Object,
});

const search = ref(props.filters?.search || '');
const mappingStatus = ref(props.filters?.mapping_status || '');
const activeTab = ref('catalog'); // 'catalog' | 'logs'
const selectedItems = ref([]);
const isSyncing = ref(false);

const pairingForm = useForm({
    pos_base_url: props.connection?.pos_base_url || '',
    client_id: props.connection?.client_id || '',
    api_secret: '',
});
const isRepairing = ref(!props.connection?.is_connected);
const showApiSecret = ref(false);

const handleFilter = () => {
    router.get(route('admin.pos-integration.index'), {
        search: search.value,
        mapping_status: mappingStatus.value,
    }, { preserveState: true, replace: true });
};

const resetFilter = () => {
    search.value = '';
    mappingStatus.value = '';
    handleFilter();
};

const saveAndTestConnection = () => {
    pairingForm.post(route('admin.pos-integration.save-and-test'), {
        preserveScroll: true,
        onSuccess: () => {
            pairingForm.api_secret = '';
            if (props.connection?.is_connected) {
                isRepairing.value = false;
            }
        },
    });
};

const testConnection = () => {
    isSyncing.value = true;
    router.post(route('admin.pos-integration.test'), {}, {
        onFinish: () => { isSyncing.value = false; }
    });
};

const syncCatalog = (forceFull = false) => {
    if (!props.connection?.is_connected) return;
    isSyncing.value = true;
    router.post(route('admin.pos-integration.sync-catalog'), { force_full: forceFull }, {
        onFinish: () => { isSyncing.value = false; }
    });
};

const syncAvailability = () => {
    if (!props.connection?.is_connected) return;
    isSyncing.value = true;
    router.post(route('admin.pos-integration.sync-availability'), {}, {
        onFinish: () => { isSyncing.value = false; }
    });
};

const reconcileMappings = () => {
    if (!props.connection?.is_connected) return;
    isSyncing.value = true;
    router.post(route('admin.pos-integration.reconcile-mappings'), {}, {
        onFinish: () => { isSyncing.value = false; }
    });
};

const importSingle = (posItemId) => {
    if (!props.connection?.is_connected) return;
    router.post(route('admin.pos-integration.import-simple'), { pos_item_id: posItemId }, {
        preserveScroll: true,
    });
};

const toggleSelectAll = (e) => {
    if (e.target.checked) {
        selectedItems.value = props.items.data.map(i => i.pos_item_id);
    } else {
        selectedItems.value = [];
    }
};

const bulkImportSelected = () => {
    if (!selectedItems.value.length || !props.connection?.is_connected) return;
    router.post(route('admin.pos-integration.bulk-import'), { pos_item_ids: selectedItems.value }, {
        preserveScroll: true,
        onSuccess: () => { selectedItems.value = []; }
    });
};

const importAllEligible = () => {
    if (!props.connection?.is_connected) return;
    if (confirm('Import all unmapped active POS items as draft online products?')) {
        router.post(route('admin.pos-integration.import-all'), {}, { preserveScroll: true });
    }
};
</script>

<template>
    <Head title="POS Integration & Catalog Synchronization" />
    <AdminLayout>
        <div class="space-y-6">
            <!-- Header & Actions -->
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                <div>
                    <h1 class="text-2xl font-bold tracking-tight text-gray-900 dark:text-white">
                        POS Branch Integration
                    </h1>
                    <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">
                        Authoritative physical inventory and standard price synchronization from your connected POS branch.
                    </p>
                </div>
                <div class="flex flex-wrap items-center gap-2">
                    <button
                        @click="testConnection"
                        :disabled="isSyncing || !connection.is_configured"
                        :title="!connection.is_configured ? 'No POS connection configured' : 'Test connection with stored credentials'"
                        class="inline-flex items-center px-3.5 py-2 border border-gray-300 dark:border-gray-600 rounded-lg text-sm font-medium text-gray-700 dark:text-gray-200 bg-white dark:bg-gray-800 hover:bg-gray-50 dark:hover:bg-gray-700 transition disabled:opacity-50 disabled:cursor-not-allowed"
                    >
                        <svg class="w-4 h-4 mr-2 text-indigo-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>
                        Test Connection
                    </button>
                    <button
                        @click="syncAvailability"
                        :disabled="isSyncing || !connection.is_connected"
                        :title="!connection.is_connected ? 'Store must be connected to a POS branch to sync stock' : 'Sync real-time stock levels'"
                        class="inline-flex items-center px-3.5 py-2 border border-gray-300 dark:border-gray-600 rounded-lg text-sm font-medium text-gray-700 dark:text-gray-200 bg-white dark:bg-gray-800 hover:bg-gray-50 dark:hover:bg-gray-700 transition disabled:opacity-50 disabled:cursor-not-allowed"
                    >
                        <svg class="w-4 h-4 mr-2 text-emerald-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                        Sync Stock
                    </button>
                    <button
                        @click="reconcileMappings"
                        :disabled="isSyncing || !connection.is_connected"
                        :title="!connection.is_connected ? 'Store must be connected to a POS branch to reconcile deletion locks' : 'Push and reconcile deletion-protection locks with POS for all active online products'"
                        class="inline-flex items-center px-3.5 py-2 border border-gray-300 dark:border-gray-600 rounded-lg text-sm font-medium text-gray-700 dark:text-gray-200 bg-white dark:bg-gray-800 hover:bg-gray-50 dark:hover:bg-gray-700 transition disabled:opacity-50 disabled:cursor-not-allowed"
                    >
                        <svg class="w-4 h-4 mr-2 text-amber-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/></svg>
                        Reconcile Locks
                    </button>
                    <button
                        @click="syncCatalog(false)"
                        :disabled="isSyncing || !connection.is_connected"
                        :title="!connection.is_connected ? 'Store must be connected to a POS branch to sync catalog' : 'Sync catalog from connected branch'"
                        class="inline-flex items-center px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white rounded-lg text-sm font-medium shadow-sm transition disabled:opacity-50 disabled:cursor-not-allowed disabled:bg-gray-400"
                    >
                        <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M9 19l3 3m0 0l3-3m-3 3V10"/></svg>
                        Sync Catalog
                    </button>
                </div>
            </div>

            <!-- Connection Status Banner -->
            <div class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-xl p-5 shadow-xs">
                <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
                    <div class="flex items-center space-x-3.5">
                        <div class="w-12 h-12 rounded-xl flex items-center justify-center bg-indigo-50 dark:bg-indigo-900/30 text-indigo-600 dark:text-indigo-400 font-bold text-xl">
                            POS
                        </div>
                        <div>
                            <div class="flex items-center space-x-2">
                                <h3 class="text-base font-semibold text-gray-900 dark:text-white">
                                    {{ connection.pos_branch_name ? `Branch: ${connection.pos_branch_name}` : 'No Connected POS Branch' }}
                                </h3>
                                <span
                                    v-if="connection.is_connected"
                                    class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-emerald-100 text-emerald-800 dark:bg-emerald-900/40 dark:text-emerald-300"
                                >
                                    ● Connected
                                </span>
                                <span
                                    v-else
                                    class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-amber-100 text-amber-800 dark:bg-amber-900/40 dark:text-amber-300"
                                >
                                    ● Disconnected
                                </span>
                                <button
                                    type="button"
                                    @click="isRepairing = !isRepairing"
                                    class="text-xs font-semibold text-indigo-600 dark:text-indigo-400 hover:underline ml-2"
                                >
                                    {{ isRepairing ? 'Hide Pairing Form' : (connection.is_connected ? 'Re-pair / Rotate Credentials' : 'Configure Connection') }}
                                </button>
                            </div>
                            <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">
                                Endpoint: <code class="font-mono text-gray-700 dark:text-gray-300">{{ connection.pos_base_url || 'Not configured' }}</code>
                                <span v-if="connection.pos_branch_id"> (Branch #{{ connection.pos_branch_id }})</span>
                                <span v-if="connection.client_id" class="ml-2 font-mono">| Client ID: {{ connection.client_id }}</span>
                            </p>
                        </div>
                    </div>
                    <div class="grid grid-cols-2 sm:grid-cols-3 gap-3 text-xs text-gray-500 dark:text-gray-400">
                        <div>
                            <span class="block text-gray-400">Last Request:</span>
                            <span class="font-medium text-gray-700 dark:text-gray-200">{{ connection.last_successful_request_at ? new Date(connection.last_successful_request_at).toLocaleTimeString() : 'Never' }}</span>
                        </div>
                        <div>
                            <span class="block text-gray-400">Last Catalog Sync:</span>
                            <span class="font-medium text-gray-700 dark:text-gray-200">{{ connection.last_catalog_sync_at ? new Date(connection.last_catalog_sync_at).toLocaleDateString() : 'Never' }}</span>
                        </div>
                        <div>
                            <span class="block text-gray-400">Last Stock Sync:</span>
                            <span class="font-medium text-gray-700 dark:text-gray-200">{{ connection.last_inventory_sync_at ? new Date(connection.last_inventory_sync_at).toLocaleTimeString() : 'Never' }}</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Clear Error Message Alert Banner -->
            <div v-if="connection.last_error_message" class="p-4 rounded-xl bg-rose-50 dark:bg-rose-950/30 border border-rose-200 dark:border-rose-900/50 flex items-start gap-3">
                <svg class="w-5 h-5 text-rose-600 dark:text-rose-400 shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                <div class="flex-1">
                    <h4 class="text-sm font-semibold text-rose-800 dark:text-rose-300">Connection / Authentication Error</h4>
                    <p class="text-xs text-rose-700 dark:text-rose-400 mt-0.5 font-mono">{{ connection.last_error_message }}</p>
                    <p v-if="connection.last_error_at" class="text-[11px] text-rose-500 mt-1">Recorded: {{ new Date(connection.last_error_at).toLocaleString() }}</p>
                </div>
            </div>

            <!-- Pairing Form Card (§2.2) -->
            <div v-if="!connection.is_connected || isRepairing" class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-xl p-5 shadow-xs space-y-4">
                <div class="flex items-center justify-between">
                    <div>
                        <h3 class="text-sm font-bold text-gray-900 dark:text-white uppercase tracking-wider">
                            {{ connection.is_connected ? 'Re-pair POS Connection / Rotate Credentials' : 'Pair New POS Connection' }}
                        </h3>
                        <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">
                            Enter the POS API Base URL, Client ID, and API Secret provided by POS Super Admin (Branch Directory → Open Branch → Ecommerce Integration).
                        </p>
                    </div>
                    <button
                        v-if="connection.is_connected"
                        type="button"
                        @click="isRepairing = false"
                        class="text-xs text-gray-400 hover:text-gray-600 dark:hover:text-gray-200"
                    >
                        Cancel
                    </button>
                </div>

                <form @submit.prevent="saveAndTestConnection" class="space-y-4">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div class="md:col-span-2">
                            <label class="block text-xs font-semibold text-gray-700 dark:text-gray-300 mb-1">
                                POS API Base URL *
                            </label>
                            <input
                                type="url"
                                v-model="pairingForm.pos_base_url"
                                required
                                placeholder="e.g. http://127.0.0.1:8000"
                                class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500"
                            />
                            <p class="text-[11px] text-gray-400 mt-1">
                                The base URL where the POS API is reachable (e.g., http://127.0.0.1:8000).
                            </p>
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-gray-700 dark:text-gray-300 mb-1">
                                Client ID *
                            </label>
                            <input
                                type="text"
                                v-model="pairingForm.client_id"
                                required
                                placeholder="e.g. branch-1 or client ID"
                                class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500"
                            />
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-gray-700 dark:text-gray-300 mb-1">
                                API Secret / Access Token *
                            </label>
                            <div class="relative">
                                <input
                                    :type="showApiSecret ? 'text' : 'password'"
                                    v-model="pairingForm.api_secret"
                                    required
                                    placeholder="Paste the secret or token generated by POS"
                                    class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 px-3 py-2 pr-10 text-sm focus:ring-2 focus:ring-indigo-500 font-mono"
                                />
                                <button
                                    type="button"
                                    @click="showApiSecret = !showApiSecret"
                                    class="absolute inset-y-0 right-0 pr-3 flex items-center text-gray-400 hover:text-gray-600 dark:hover:text-gray-200"
                                >
                                    <span class="text-xs">{{ showApiSecret ? 'Hide' : 'Show' }}</span>
                                </button>
                            </div>
                            <p class="text-[11px] text-gray-400 mt-1">Encrypted immediately on save. Never stored or displayed in plain text.</p>
                        </div>
                    </div>

                    <div class="flex items-center justify-between pt-2">
                        <span class="text-xs text-gray-500">
                            Submitting will immediately make an authenticated handshake request to verify branch connection.
                        </span>
                        <button
                            type="submit"
                            :disabled="pairingForm.processing"
                            class="bg-indigo-600 hover:bg-indigo-700 text-white font-bold px-5 py-2.5 rounded-lg text-sm transition flex items-center gap-2 disabled:opacity-60 shadow-xs"
                        >
                            <svg v-if="pairingForm.processing" class="animate-spin -ml-1 mr-2 h-4 w-4 text-white" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path></svg>
                            {{ pairingForm.processing ? 'Saving & Testing...' : 'Save & Test Connection' }}
                        </button>
                    </div>
                </form>
            </div>

            <!-- Stats Overview -->
            <div class="grid grid-cols-2 sm:grid-cols-4 gap-4">
                <div class="bg-white dark:bg-gray-800 p-4 rounded-xl border border-gray-200 dark:border-gray-700 shadow-xs">
                    <p class="text-xs font-medium text-gray-500 dark:text-gray-400">Synced Items</p>
                    <p class="text-2xl font-bold text-gray-900 dark:text-white mt-1">{{ stats.total_synced }}</p>
                </div>
                <div class="bg-white dark:bg-gray-800 p-4 rounded-xl border border-gray-200 dark:border-gray-700 shadow-xs">
                    <p class="text-xs font-medium text-gray-500 dark:text-gray-400">Mapped Products</p>
                    <p class="text-2xl font-bold text-indigo-600 dark:text-indigo-400 mt-1">{{ stats.mapped_simple + stats.mapped_variant }}</p>
                </div>
                <div class="bg-white dark:bg-gray-800 p-4 rounded-xl border border-gray-200 dark:border-gray-700 shadow-xs">
                    <p class="text-xs font-medium text-gray-500 dark:text-gray-400">Unmapped Items</p>
                    <p class="text-2xl font-bold text-gray-700 dark:text-gray-300 mt-1">{{ stats.unmapped }}</p>
                </div>
                <div class="bg-white dark:bg-gray-800 p-4 rounded-xl border border-gray-200 dark:border-gray-700 shadow-xs">
                    <p class="text-xs font-medium text-gray-500 dark:text-gray-400">Missing from POS</p>
                    <p class="text-2xl font-bold" :class="stats.missing_pos > 0 ? 'text-rose-600 dark:text-rose-400' : 'text-gray-400 mt-1'">
                        {{ stats.missing_pos }}
                    </p>
                </div>
            </div>

            <!-- Tabs: Catalog vs Sync Logs -->
            <div class="border-b border-gray-200 dark:border-gray-700 flex space-x-6">
                <button
                    @click="activeTab = 'catalog'"
                    class="pb-3 text-sm font-medium border-b-2 transition"
                    :class="activeTab === 'catalog' ? 'border-indigo-600 text-indigo-600 dark:text-indigo-400' : 'border-transparent text-gray-500 hover:text-gray-700 dark:text-gray-400'"
                >
                    Synced POS Catalog ({{ stats.total_synced }})
                </button>
                <button
                    @click="activeTab = 'logs'"
                    class="pb-3 text-sm font-medium border-b-2 transition"
                    :class="activeTab === 'logs' ? 'border-indigo-600 text-indigo-600 dark:text-indigo-400' : 'border-transparent text-gray-500 hover:text-gray-700 dark:text-gray-400'"
                >
                    Synchronization Logs ({{ logs.length }})
                </button>
            </div>

            <!-- TAB 1: Catalog Browser -->
            <div v-if="activeTab === 'catalog'" class="space-y-4">
                <!-- Filters & Bulk Actions -->
                <div class="flex flex-col sm:flex-row items-center justify-between gap-3 bg-white dark:bg-gray-800 p-4 rounded-xl border border-gray-200 dark:border-gray-700">
                    <div class="flex flex-1 items-center space-x-3 w-full sm:w-auto">
                        <div class="relative flex-1 max-w-sm">
                            <input
                                v-model="search"
                                @keyup.enter="handleFilter"
                                type="text"
                                placeholder="Search by name, barcode, SKU..."
                                class="w-full text-sm pl-9 pr-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-gray-50 dark:bg-gray-900 text-gray-900 dark:text-gray-100 focus:ring-2 focus:ring-indigo-500"
                            />
                            <svg class="w-4 h-4 text-gray-400 absolute left-3 top-2.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                        </div>
                        <select
                            v-model="mappingStatus"
                            @change="handleFilter"
                            class="text-sm py-2 px-3 border border-gray-300 dark:border-gray-600 rounded-lg bg-gray-50 dark:bg-gray-900 text-gray-900 dark:text-gray-100"
                        >
                            <option value="">All Statuses</option>
                            <option value="unmapped">Unmapped</option>
                            <option value="mapped_simple">Mapped Simple</option>
                            <option value="mapped_variant">Mapped Variant</option>
                            <option value="ignored">Ignored</option>
                        </select>
                        <button
                            v-if="search || mappingStatus"
                            @click="resetFilter"
                            class="text-xs text-gray-500 hover:text-gray-700 dark:text-gray-400 underline"
                        >
                            Clear
                        </button>
                    </div>

                    <div class="flex items-center space-x-2 w-full sm:w-auto justify-end">
                        <button
                            v-if="selectedItems.length > 0"
                            @click="bulkImportSelected"
                            :disabled="!connection.is_connected"
                            class="px-3 py-1.5 bg-indigo-600 hover:bg-indigo-700 text-white rounded-lg text-xs font-semibold shadow-xs disabled:opacity-50 disabled:cursor-not-allowed"
                        >
                            Import Selected ({{ selectedItems.length }})
                        </button>
                        <button
                            @click="importAllEligible"
                            :disabled="!connection.is_connected"
                            :title="!connection.is_connected ? 'Store must be connected to a POS branch to import' : 'Import all unmapped active items'"
                            class="px-3 py-1.5 border border-indigo-600 text-indigo-600 dark:text-indigo-400 hover:bg-indigo-50 dark:hover:bg-indigo-900/30 rounded-lg text-xs font-semibold disabled:opacity-50 disabled:cursor-not-allowed disabled:border-gray-300 disabled:text-gray-400"
                        >
                            Import All Eligible
                        </button>
                    </div>
                </div>

                <!-- Catalog Items Table -->
                <div class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-xl overflow-hidden shadow-xs">
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700 text-left text-sm">
                            <thead class="bg-gray-50 dark:bg-gray-900/50 text-gray-600 dark:text-gray-400 font-medium">
                                <tr>
                                    <th class="p-3 w-10">
                                        <input type="checkbox" @change="toggleSelectAll" class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500" />
                                    </th>
                                    <th class="p-3">POS ID</th>
                                    <th class="p-3">Item Name</th>
                                    <th class="p-3">Units & Price</th>
                                    <th class="p-3">Availability</th>
                                    <th class="p-3">Mapping</th>
                                    <th class="p-3 text-right">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200 dark:divide-gray-700 text-gray-800 dark:text-gray-200">
                                <tr v-for="item in items.data" :key="item.id" class="hover:bg-gray-50 dark:hover:bg-gray-700/60 transition">
                                    <td class="p-3">
                                        <input
                                            type="checkbox"
                                            :value="item.pos_item_id"
                                            v-model="selectedItems"
                                            class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500"
                                        />
                                    </td>
                                    <td class="p-3 font-mono text-xs text-gray-500">
                                        #{{ item.pos_item_id }}
                                    </td>
                                    <td class="p-3">
                                        <div class="font-semibold text-gray-900 dark:text-white">{{ item.pos_item_name }}</div>
                                        <div class="text-xs text-gray-500">
                                            <span v-if="item.sku">SKU: {{ item.sku }}</span>
                                            <span v-if="item.barcode" class="ml-2 font-mono">Bar: {{ item.barcode }}</span>
                                        </div>
                                    </td>
                                    <td class="p-3">
                                        <div class="font-medium text-emerald-600 dark:text-emerald-400">
                                            ₦{{ Number(item.standard_price).toLocaleString('en-US', { minimumFractionDigits: 2 }) }}
                                            <span class="text-xs text-gray-500 font-normal">/ {{ item.base_unit_name }}</span>
                                        </div>
                                        <div v-if="item.units && item.units.length > 1" class="text-xs text-gray-400 mt-0.5">
                                            + {{ item.units.length - 1 }} additional selling unit(s)
                                        </div>
                                    </td>
                                    <td class="p-3">
                                        <span
                                            class="inline-flex items-center px-2 py-0.5 rounded text-xs font-semibold"
                                            :class="item.effective_availability > 0 ? 'bg-emerald-50 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-300' : 'bg-rose-50 text-rose-700 dark:bg-rose-900/30 dark:text-rose-300'"
                                        >
                                            {{ item.effective_availability }} {{ item.base_unit_name }}
                                        </span>
                                    </td>
                                    <td class="p-3">
                                        <span
                                            v-if="item.mapping_status === 'mapped_simple'"
                                            class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800 dark:bg-blue-900/40 dark:text-blue-300"
                                        >
                                            Simple Product
                                        </span>
                                        <span
                                            v-else-if="item.mapping_status === 'mapped_variant'"
                                            class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-purple-100 text-purple-800 dark:bg-purple-900/40 dark:text-purple-300"
                                        >
                                            Variant Mapped
                                        </span>
                                        <span
                                            v-else
                                            class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-700 dark:bg-gray-800 dark:text-gray-400"
                                        >
                                            Unmapped
                                        </span>
                                    </td>
                                    <td class="p-3 text-right">
                                        <div v-if="item.mapped_product">
                                            <Link
                                                :href="route('admin.products.index', { search: item.mapped_product.name })"
                                                class="text-xs font-medium text-indigo-600 hover:text-indigo-800 dark:text-indigo-400 dark:hover:text-indigo-300 underline"
                                            >
                                                Edit Product #{{ item.mapped_product.id }}
                                            </Link>
                                        </div>
                                        <button
                                            v-else
                                            @click="importSingle(item.pos_item_id)"
                                            :disabled="!connection.is_connected"
                                            :title="!connection.is_connected ? 'Store must be connected to import' : 'Import as draft product'"
                                            class="px-2.5 py-1 text-xs font-medium text-indigo-700 dark:text-indigo-300 bg-indigo-50 dark:bg-indigo-900/40 hover:bg-indigo-100 rounded-md transition disabled:opacity-50 disabled:cursor-not-allowed"
                                        >
                                            Import as Draft
                                        </button>
                                    </td>
                                </tr>
                                <tr v-if="!items.data || !items.data.length">
                                    <td colspan="7" class="p-8 text-center text-gray-500">
                                        No synchronized POS items found. Click "Sync Catalog" to pull items from your connected POS branch.
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    <div v-if="items && items.data && items.data.length" class="p-4 border-t border-gray-200 dark:border-gray-700">
                        <Pagination :paginator="items" />
                    </div>
                </div>
            </div>

            <!-- TAB 2: Sync Logs -->
            <div v-if="activeTab === 'logs'" class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-xl overflow-hidden shadow-xs">
                <div class="p-4 border-b border-gray-200 dark:border-gray-700 font-semibold text-gray-900 dark:text-white">
                    Recent Integration Audit Trails
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
                            <tr v-for="log in logs" :key="log.id" class="hover:bg-gray-50 dark:hover:bg-gray-700/60 transition">
                                <td class="p-3 whitespace-nowrap text-gray-500 font-sans">
                                    {{ new Date(log.created_at).toLocaleTimeString() }}
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
                            <tr v-if="!logs || !logs.length">
                                <td colspan="5" class="p-6 text-center text-gray-500 font-sans">
                                    No synchronization logs recorded yet.
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </AdminLayout>
</template>
