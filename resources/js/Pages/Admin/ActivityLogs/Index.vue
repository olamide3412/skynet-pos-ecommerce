<script setup>
import { ref, computed } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import Pagination from '@/Components/Pagination.vue';

const props = defineProps({
    logs: Object,
    filters: Object,
    metrics: Object,
    users: Array,
});

// Filters
const search = ref(props.filters?.search || '');
const selectedActor = ref(props.filters?.actor || '');
const selectedActionType = ref(props.filters?.action_type || '');
const startDate = ref(props.filters?.start_date || '');
const endDate = ref(props.filters?.end_date || '');
const activePreset = ref('all');

// Modal & Copied state
const selectedLogModal = ref(null);
const copied = ref(false);

const actionCategories = [
    { key: '', label: 'All Categories' },
    { key: 'auth', label: 'Auth & Security', color: 'indigo' },
    { key: 'customer', label: 'Customer Management', color: 'cyan' },
    { key: 'product', label: 'Products & Catalog', color: 'emerald' },
    { key: 'order', label: 'Orders & POD Cash', color: 'amber' },
    { key: 'webhook', label: 'Payment Webhooks', color: 'cyan' },
    { key: 'setting', label: 'Store Config', color: 'sky' },
    { key: 'affiliate', label: 'Affiliates & Payouts', color: 'purple' },
    { key: 'pos_sync', label: 'POS Integration', color: 'teal' },
    { key: 'system', label: 'System Bots', color: 'rose' },
];

const applyFilters = () => {
    router.get(route('admin.activity-logs.index'), {
        search: search.value || undefined,
        actor: selectedActor.value || undefined,
        action_type: selectedActionType.value || undefined,
        start_date: startDate.value || undefined,
        end_date: endDate.value || undefined,
        page: undefined,
    }, {
        preserveState: true,
        preserveScroll: true,
    });
};

const resetFilters = () => {
    search.value = '';
    selectedActor.value = '';
    selectedActionType.value = '';
    startDate.value = '';
    endDate.value = '';
    activePreset.value = 'all';
    applyFilters();
};

const setPreset = (preset) => {
    activePreset.value = preset;
    const today = new Date();
    const fmt = (d) => {
        const year = d.getFullYear();
        const month = String(d.getMonth() + 1).padStart(2, '0');
        const day = String(d.getDate()).padStart(2, '0');
        return `${year}-${month}-${day}`;
    };

    if (preset === 'today') {
        startDate.value = fmt(today);
        endDate.value = fmt(today);
    } else if (preset === 'yesterday') {
        const y = new Date();
        y.setDate(y.getDate() - 1);
        startDate.value = fmt(y);
        endDate.value = fmt(y);
    } else if (preset === 'week') {
        const curr = new Date();
        const day = curr.getDay();
        const diff = curr.getDate() - day + (day === 0 ? -6 : 1);
        const mon = new Date(curr.setDate(diff));
        startDate.value = fmt(mon);
        endDate.value = fmt(today);
    } else if (preset === 'month') {
        const firstDay = new Date(today.getFullYear(), today.getMonth(), 1);
        startDate.value = fmt(firstDay);
        endDate.value = fmt(today);
    } else {
        startDate.value = '';
        endDate.value = '';
    }
    applyFilters();
};

const exportCsvUrl = computed(() => {
    const params = new URLSearchParams();
    if (search.value) params.append('search', search.value);
    if (selectedActor.value) params.append('actor', selectedActor.value);
    if (selectedActionType.value) params.append('action_type', selectedActionType.value);
    if (startDate.value) params.append('start_date', startDate.value);
    if (endDate.value) params.append('end_date', endDate.value);
    const qs = params.toString();
    return route('admin.activity-logs.export') + (qs ? `?${qs}` : '');
});

const getCategoryBadgeClass = (type) => {
    switch (type) {
        case 'auth':
            return 'bg-blue-100 text-blue-800 border-blue-200 dark:bg-blue-950/40 dark:text-blue-300 dark:border-blue-800';
        case 'customer':
            return 'bg-primary-soft text-primary border border-primary-soft font-bold';
        case 'product':
            return 'bg-emerald-100 text-emerald-800 border-emerald-200 dark:bg-emerald-950/40 dark:text-emerald-300 dark:border-emerald-800';
        case 'order':
            return 'bg-amber-100 text-amber-800 border-amber-200 dark:bg-amber-950/40 dark:text-amber-300 dark:border-amber-800';
        case 'webhook':
            return 'bg-cyan-100 text-cyan-800 border-cyan-200 dark:bg-cyan-950/40 dark:text-cyan-300 dark:border-cyan-800';
        case 'setting':
            return 'bg-sky-100 text-sky-800 border-sky-200 dark:bg-sky-950/40 dark:text-sky-300 dark:border-sky-800';
        case 'affiliate':
            return 'bg-purple-100 text-purple-800 border-purple-200 dark:bg-purple-950/40 dark:text-purple-300 dark:border-purple-800';
        case 'pos_sync':
            return 'bg-teal-100 text-teal-800 border-teal-200 dark:bg-teal-950/40 dark:text-teal-300 dark:border-teal-800';
        case 'system':
            return 'bg-rose-100 text-rose-800 border-rose-200 dark:bg-rose-950/40 dark:text-rose-300 dark:border-rose-800';
        default:
            return 'bg-slate-100 text-slate-800 border-slate-200 dark:bg-slate-800 dark:text-slate-300 dark:border-slate-700';
    }
};

const copyPayload = async () => {
    if (!selectedLogModal.value?.details) return;
    try {
        await navigator.clipboard.writeText(JSON.stringify(selectedLogModal.value.details, null, 2));
        copied.value = true;
        setTimeout(() => {
            copied.value = false;
        }, 2000);
    } catch (e) {
        // Fallback
    }
};

const formatDate = (dateString) => {
    if (!dateString) return '—';
    const d = new Date(dateString);
    return d.toLocaleString(undefined, {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
        second: '2-digit',
    });
};
</script>

<template>
    <AdminLayout>
        <Head title="Activity Logs Audit Trail" />

        <div class="space-y-6 max-w-7xl mx-auto pb-12">
            <!-- Header Section -->
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-3xl p-6 shadow-xs">
                <div class="flex items-start gap-4">
                    <div class="w-12 h-12 rounded-2xl bg-primary-soft border border-primary-soft text-primary flex items-center justify-center text-2xl shrink-0 shadow-xs">
                        🛡️
                    </div>
                    <div>
                        <div class="flex items-center gap-2.5">
                            <h1 class="text-xl font-bold text-slate-900 dark:text-white">Activity Logs Audit Trail</h1>
                            <span class="px-2.5 py-0.5 rounded-full text-[11px] font-bold bg-primary-soft text-primary border border-primary-soft">
                                Superadmin Only
                            </span>
                        </div>
                        <p class="text-xs text-slate-500 dark:text-slate-400 mt-1">
                            Tamper-evident system & user activity journal tracking logins, catalog edits, order lifecycle changes, and automated background jobs.
                        </p>
                    </div>
                </div>

                <div class="flex items-center gap-2.5 shrink-0">
                    <button
                        @click="router.reload({ preserveScroll: true })"
                        type="button"
                        class="px-3.5 py-2 text-xs font-semibold rounded-xl bg-slate-100 hover:bg-slate-200 dark:bg-slate-800 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-200 transition cursor-pointer flex items-center gap-2 border border-slate-200 dark:border-slate-700"
                        title="Refresh Event Log"
                    >
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                        </svg>
                        <span>Refresh</span>
                    </button>

                    <a
                        :href="exportCsvUrl"
                        class="px-4 py-2 text-xs font-bold rounded-xl bg-primary hover:bg-primary-dark text-white shadow-xs transition flex items-center gap-2 cursor-pointer"
                    >
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                        </svg>
                        <span>Export CSV</span>
                    </a>
                </div>
            </div>

            <!-- KPI Summary Cards -->
            <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
                <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-3xl p-5 shadow-xs">
                    <div class="flex items-center justify-between">
                        <span class="text-xs font-bold uppercase tracking-wider text-slate-500">Total Events</span>
                        <span class="w-8 h-8 rounded-xl bg-primary-soft text-primary flex items-center justify-center text-sm font-bold">📋</span>
                    </div>
                    <div class="mt-2 text-2xl font-black text-primary">
                        {{ metrics?.total_events?.toLocaleString() || 0 }}
                    </div>
                    <p class="text-[11px] text-slate-400 mt-1">All recorded audit entries</p>
                </div>

                <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-3xl p-5 shadow-xs">
                    <div class="flex items-center justify-between">
                        <span class="text-xs font-bold uppercase tracking-wider text-slate-500">Today's Activity</span>
                        <span class="w-8 h-8 rounded-xl bg-emerald-100 dark:bg-emerald-950/50 text-emerald-600 dark:text-emerald-400 flex items-center justify-center text-sm font-bold">⚡</span>
                    </div>
                    <div class="mt-2 text-2xl font-black text-emerald-600 dark:text-emerald-400">
                        {{ metrics?.today_events?.toLocaleString() || 0 }}
                    </div>
                    <p class="text-[11px] text-slate-400 mt-1">Events logged today</p>
                </div>

                <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-3xl p-5 shadow-xs">
                    <div class="flex items-center justify-between">
                        <span class="text-xs font-bold uppercase tracking-wider text-slate-500">Staff Actions</span>
                        <span class="w-8 h-8 rounded-xl bg-blue-100 dark:bg-blue-950/50 text-blue-600 dark:text-blue-400 flex items-center justify-center text-sm font-bold">👤</span>
                    </div>
                    <div class="mt-2 text-2xl font-black text-blue-600 dark:text-blue-400">
                        {{ metrics?.user_events?.toLocaleString() || 0 }}
                    </div>
                    <p class="text-[11px] text-slate-400 mt-1">Authenticated user activities</p>
                </div>

                <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-3xl p-5 shadow-xs">
                    <div class="flex items-center justify-between">
                        <span class="text-xs font-bold uppercase tracking-wider text-slate-500">System Bot Tasks</span>
                        <span class="w-8 h-8 rounded-xl bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-300 flex items-center justify-center text-sm font-bold">🤖</span>
                    </div>
                    <div class="mt-2 text-2xl font-black text-slate-700 dark:text-slate-200">
                        {{ metrics?.system_events?.toLocaleString() || 0 }}
                    </div>
                    <p class="text-[11px] text-slate-400 mt-1">Cron & background sweeps</p>
                </div>
            </div>

            <!-- Filters Bar -->
            <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-3xl p-5 shadow-xs space-y-4">
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3">
                    <!-- Actor Selector -->
                    <div>
                        <label class="block text-[11px] font-bold uppercase tracking-wider text-slate-500 mb-1">Actor (User / System)</label>
                        <select
                            v-model="selectedActor"
                            @change="applyFilters"
                            class="w-full bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-xl px-3 py-2 text-xs text-slate-900 dark:text-slate-100 outline-none focus:border-primary transition cursor-pointer"
                        >
                            <option value="">👥 All Actors (Users & System)</option>
                            <option value="system">🤖 System Bot (Crons / Webhooks)</option>
                            <optgroup label="Staff & Administrators">
                                <option v-for="user in users" :key="user.id" :value="user.id">
                                    {{ user.name }} ({{ user.role }})
                                </option>
                            </optgroup>
                        </select>
                    </div>

                    <!-- Action Category Selector -->
                    <div>
                        <label class="block text-[11px] font-bold uppercase tracking-wider text-slate-500 mb-1">Action Category</label>
                        <select
                            v-model="selectedActionType"
                            @change="applyFilters"
                            class="w-full bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-xl px-3 py-2 text-xs text-slate-900 dark:text-slate-100 outline-none focus:border-primary transition cursor-pointer"
                        >
                            <option v-for="cat in actionCategories" :key="cat.key" :value="cat.key">
                                {{ cat.label }}
                            </option>
                        </select>
                    </div>

                    <!-- Search Input -->
                    <div class="lg:col-span-2">
                        <label class="block text-[11px] font-bold uppercase tracking-wider text-slate-500 mb-1">Search Keywords</label>
                        <div class="flex items-center gap-2">
                            <div class="relative flex-1">
                                <input
                                    v-model="search"
                                    @keyup.enter="applyFilters"
                                    type="text"
                                    placeholder="Search by description, IP address, user name, or email..."
                                    class="w-full bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-xl px-3 py-2 pr-8 text-xs text-slate-900 dark:text-slate-100 placeholder-slate-400 outline-none focus:border-primary transition"
                                />
                                <button
                                    v-if="search"
                                    @click="search = ''; applyFilters()"
                                    type="button"
                                    class="absolute right-2.5 top-2 text-slate-400 hover:text-slate-600 text-xs font-bold"
                                >
                                    &times;
                                </button>
                            </div>
                            <button
                                @click="applyFilters"
                                type="button"
                                class="px-4 py-2 bg-primary hover:bg-primary-dark text-white rounded-xl text-xs font-bold transition shadow-xs shrink-0 cursor-pointer"
                            >
                                Filter
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Date Range & Quick Presets -->
                <div class="flex flex-col md:flex-row md:items-center justify-between gap-3 pt-3 border-t border-slate-100 dark:border-slate-800/80 text-xs">
                    <div class="flex items-center gap-1.5 flex-wrap">
                        <span class="text-slate-400 font-semibold mr-1">Period:</span>
                        <button
                            v-for="p in [
                                { key: 'all', label: 'All Time' },
                                { key: 'today', label: 'Today' },
                                { key: 'yesterday', label: 'Yesterday' },
                                { key: 'week', label: 'This Week' },
                                { key: 'month', label: 'This Month' },
                            ]"
                            :key="p.key"
                            @click="setPreset(p.key)"
                            type="button"
                            :class="activePreset === p.key
                                ? 'bg-primary text-white font-bold shadow-xs'
                                : 'bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-300 hover:bg-slate-200 dark:hover:bg-slate-700'"
                            class="px-2.5 py-1 rounded-lg text-[11px] transition cursor-pointer"
                        >
                            {{ p.label }}
                        </button>
                    </div>

                    <div class="flex items-center gap-2 flex-wrap">
                        <div class="flex items-center gap-1">
                            <span class="text-slate-400 text-[11px]">From:</span>
                            <input
                                v-model="startDate"
                                @change="activePreset = 'custom'; applyFilters()"
                                type="date"
                                class="bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-lg px-2 py-1 text-[11px] text-slate-900 dark:text-slate-100 outline-none focus:border-primary"
                            />
                        </div>
                        <div class="flex items-center gap-1">
                            <span class="text-slate-400 text-[11px]">To:</span>
                            <input
                                v-model="endDate"
                                @change="activePreset = 'custom'; applyFilters()"
                                type="date"
                                class="bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-lg px-2 py-1 text-[11px] text-slate-900 dark:text-slate-100 outline-none focus:border-primary"
                            />
                        </div>

                        <button
                            v-if="search || selectedActor || selectedActionType || startDate || endDate"
                            @click="resetFilters"
                            type="button"
                            class="text-[11px] font-semibold text-rose-600 dark:text-rose-400 hover:underline px-1 cursor-pointer"
                        >
                            Reset All
                        </button>
                    </div>
                </div>
            </div>

            <!-- Table Card -->
            <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-3xl overflow-hidden shadow-xs">
                <div v-if="logs.data && logs.data.length > 0" class="overflow-x-auto">
                    <table class="w-full text-left text-xs">
                        <thead class="bg-slate-50 dark:bg-slate-800/60 text-slate-600 dark:text-slate-400 font-bold border-b border-slate-200 dark:border-slate-800">
                            <tr>
                                <th class="px-5 py-3.5">Timestamp</th>
                                <th class="px-5 py-3.5">Actor</th>
                                <th class="px-5 py-3.5">Category</th>
                                <th class="px-5 py-3.5">Action Description</th>
                                <th class="px-5 py-3.5">IP Address</th>
                                <th class="px-5 py-3.5 text-right">Details</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                            <tr
                                v-for="log in logs.data"
                                :key="log.id"
                                class="hover:bg-slate-50/60 dark:hover:bg-slate-800/30 transition"
                            >
                                <!-- Timestamp -->
                                <td class="px-5 py-3 font-mono text-[11px] text-slate-500 whitespace-nowrap">
                                    <div>{{ formatDate(log.created_at) }}</div>
                                    <div class="text-[10px] text-slate-400">ID: #{{ log.id }}</div>
                                </td>

                                <!-- Actor -->
                                <td class="px-5 py-3 whitespace-nowrap">
                                    <div v-if="log.user_id && log.user" class="flex items-center gap-2.5">
                                        <div class="w-7 h-7 rounded-full bg-primary-soft text-primary font-bold flex items-center justify-center text-[11px] shrink-0 border border-primary-soft">
                                            {{ log.user.name.charAt(0).toUpperCase() }}
                                        </div>
                                        <div>
                                            <div class="font-semibold text-slate-900 dark:text-slate-100 flex items-center gap-1.5">
                                                <span>{{ log.user.name }}</span>
                                                <span class="text-[10px] font-bold px-1.5 py-0.2 rounded uppercase"
                                                    :class="log.user.role === 'super administrator' ? 'bg-primary-soft text-primary border border-primary-soft' : 'bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-300'"
                                                >
                                                    {{ log.user.role === 'super administrator' ? 'Superadmin' : log.user.role }}
                                                </span>
                                            </div>
                                            <div class="text-[10px] text-slate-400">{{ log.user.email }}</div>
                                        </div>
                                    </div>
                                    <div v-else class="flex items-center gap-2">
                                        <div class="w-7 h-7 rounded-full bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300 font-bold flex items-center justify-center text-xs shrink-0 border border-slate-200 dark:border-slate-700">
                                            🤖
                                        </div>
                                        <div>
                                            <div class="font-bold text-slate-800 dark:text-slate-200 text-xs">System Bot</div>
                                            <div class="text-[10px] text-slate-400 font-mono">Automated / Sweeper</div>
                                        </div>
                                    </div>
                                </td>

                                <!-- Category Badge -->
                                <td class="px-5 py-3 whitespace-nowrap">
                                    <span
                                        :class="getCategoryBadgeClass(log.action_type)"
                                        class="px-2.5 py-0.5 rounded-full text-[10px] font-bold border uppercase tracking-wider"
                                    >
                                        {{ log.action_type || 'GENERAL' }}
                                    </span>
                                </td>

                                <!-- Description -->
                                <td class="px-5 py-3 text-slate-700 dark:text-slate-300 max-w-md">
                                    <div class="line-clamp-2 leading-relaxed text-xs">
                                        {{ log.description }}
                                    </div>
                                </td>

                                <!-- IP Address & Client -->
                                <td class="px-5 py-3 whitespace-nowrap font-mono text-[11px] text-slate-400">
                                    <div>{{ log.ip_address || '127.0.0.1 (CLI)' }}</div>
                                    <div v-if="log.user_agent" class="text-[10px] text-slate-500 font-sans truncate max-w-[150px]" :title="log.user_agent">
                                        {{ log.user_agent }}
                                    </div>
                                </td>

                                <!-- Details Modal Button -->
                                <td class="px-5 py-3 text-right whitespace-nowrap">
                                    <button
                                        v-if="log.details && Object.keys(log.details).length > 0"
                                        @click="selectedLogModal = log; copied = false"
                                        type="button"
                                        class="px-2.5 py-1 text-[11px] font-semibold text-primary bg-primary-soft hover:opacity-90 rounded-lg transition cursor-pointer border border-primary-soft"
                                    >
                                        Inspect
                                    </button>
                                    <span v-else class="text-slate-300 dark:text-slate-700 text-[11px]">—</span>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Empty State -->
                <div v-else class="py-16 text-center space-y-3">
                    <div class="w-12 h-12 rounded-2xl bg-primary-soft text-primary flex items-center justify-center mx-auto text-xl">
                        📜
                    </div>
                    <h3 class="text-base font-bold text-slate-900 dark:text-white">No Activity Logs Found</h3>
                    <p class="text-xs text-slate-500 max-w-sm mx-auto">
                        No activity log events match your current filter parameters. Try clearing the filters or searching for different terms.
                    </p>
                    <button
                        @click="resetFilters"
                        type="button"
                        class="px-4 py-2 bg-primary hover:bg-primary-dark text-white rounded-xl text-xs font-bold transition shadow-xs cursor-pointer"
                    >
                        Clear Filters
                    </button>
                </div>

                <!-- Pagination Footer -->
                <div v-if="logs.data && logs.data.length > 0" class="px-6 py-4 border-t border-slate-200 dark:border-slate-800">
                    <Pagination :paginator="logs" />
                </div>
            </div>
        </div>

        <!-- Details Inspection Modal -->
        <div
            v-if="selectedLogModal"
            class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-xs"
            @click.self="selectedLogModal = null"
        >
            <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-3xl max-w-2xl w-full p-6 shadow-2xl space-y-4 max-h-[90vh] flex flex-col">
                <div class="flex items-center justify-between pb-3 border-b border-slate-100 dark:border-slate-800">
                    <div class="flex items-center gap-2.5">
                        <span class="w-8 h-8 rounded-xl bg-primary-soft text-primary flex items-center justify-center text-sm font-bold">
                            🔍
                        </span>
                        <div>
                            <h3 class="text-sm font-bold text-slate-900 dark:text-white">
                                Event Payload Details #{{ selectedLogModal.id }}
                            </h3>
                            <p class="text-[11px] text-slate-400">
                                {{ formatDate(selectedLogModal.created_at) }} &bull; {{ selectedLogModal.action_type?.toUpperCase() }}
                            </p>
                        </div>
                    </div>
                    <button
                        @click="selectedLogModal = null"
                        type="button"
                        class="text-slate-400 hover:text-slate-600 dark:hover:text-slate-200 text-xl font-bold p-1 cursor-pointer"
                    >
                        &times;
                    </button>
                </div>

                <div class="space-y-3 overflow-y-auto flex-1 pr-1">
                    <div class="bg-slate-50 dark:bg-slate-800/60 rounded-2xl p-3 border border-slate-200 dark:border-slate-800 text-xs space-y-1.5">
                        <div class="flex items-center justify-between">
                            <span class="text-slate-500 font-semibold">Actor:</span>
                            <span class="font-bold text-slate-900 dark:text-slate-100">
                                {{ selectedLogModal.user?.name || selectedLogModal.actor_name || 'System Bot' }}
                                <span v-if="selectedLogModal.user?.email" class="text-slate-400 font-normal">({{ selectedLogModal.user.email }})</span>
                            </span>
                        </div>
                        <div class="flex items-center justify-between">
                            <span class="text-slate-500 font-semibold">IP Address:</span>
                            <span class="font-mono text-slate-700 dark:text-slate-300">{{ selectedLogModal.ip_address || '127.0.0.1 (CLI)' }}</span>
                        </div>
                        <div class="flex items-start justify-between gap-4">
                            <span class="text-slate-500 font-semibold shrink-0">Description:</span>
                            <span class="text-slate-800 dark:text-slate-200 text-right">{{ selectedLogModal.description }}</span>
                        </div>
                        <div v-if="selectedLogModal.user_agent" class="pt-1 border-t border-slate-200 dark:border-slate-700/60">
                            <span class="text-slate-500 font-semibold block text-[11px]">User Agent:</span>
                            <span class="text-[11px] text-slate-600 dark:text-slate-400 font-mono break-all">{{ selectedLogModal.user_agent }}</span>
                        </div>
                    </div>

                    <div>
                        <div class="flex items-center justify-between mb-1.5">
                            <span class="text-xs font-bold uppercase tracking-wider text-slate-500">Structured Payload Data (JSON)</span>
                            <button
                                @click="copyPayload"
                                type="button"
                                class="text-[11px] font-bold text-primary hover:underline flex items-center gap-1 cursor-pointer"
                            >
                                <span>{{ copied ? '✓ Copied!' : '📋 Copy JSON' }}</span>
                            </button>
                        </div>
                        <pre class="bg-slate-950 text-emerald-400 p-4 rounded-2xl text-[11px] font-mono overflow-x-auto border border-slate-800 max-h-72 leading-relaxed selection:bg-emerald-800 selection:text-white">{{ JSON.stringify(selectedLogModal.details, null, 2) }}</pre>
                    </div>
                </div>

                <div class="pt-3 border-t border-slate-100 dark:border-slate-800 flex justify-end">
                    <button
                        @click="selectedLogModal = null"
                        type="button"
                        class="px-4 py-2 bg-slate-100 hover:bg-slate-200 dark:bg-slate-800 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-200 rounded-xl text-xs font-bold transition cursor-pointer"
                    >
                        Close
                    </button>
                </div>
            </div>
        </div>
    </AdminLayout>
</template>

