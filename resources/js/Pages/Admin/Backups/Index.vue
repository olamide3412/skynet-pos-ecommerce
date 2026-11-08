<script setup>
import { Head, router } from '@inertiajs/vue3';
import { ref, computed } from 'vue';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import SettingsNavTabs from '@/Components/Admin/SettingsNavTabs.vue';

const props = defineProps({
    backups: {
        type: Array,
        default: () => [],
    },
});

const isBackingUp = ref(false);

const latestBackup = computed(() => {
    return props.backups && props.backups.length > 0 ? props.backups[0] : null;
});

const totalStorageBytes = computed(() => {
    if (!props.backups || props.backups.length === 0) return '0 B';
    const total = props.backups.reduce((acc, b) => acc + (b.raw_size || 0), 0);
    const units = ['B', 'KB', 'MB', 'GB', 'TB'];
    const pow = total ? Math.floor(Math.log(total) / Math.log(1024)) : 0;
    const p = Math.min(pow, units.length - 1);
    return (total / Math.pow(1024, p)).toFixed(2) + ' ' + units[p];
});

const createBackup = () => {
    isBackingUp.value = true;
    router.post(route('admin.backups.store'), {}, {
        preserveScroll: true,
        onFinish: () => {
            isBackingUp.value = false;
        },
    });
};

const downloadBackup = (filename) => {
    window.location.href = route('admin.backups.download') + '?filename=' + encodeURIComponent(filename);
};

const deleteBackup = (filename) => {
    if (!confirm(`Are you sure you want to permanently delete backup '${filename}'?`)) {
        return;
    }
    router.delete(route('admin.backups.destroy') + '?filename=' + encodeURIComponent(filename), {
        preserveScroll: true,
    });
};
</script>

<template>
    <Head title="Database Backups - Admin" />

    <AdminLayout>
        <div class="max-w-7xl mx-auto space-y-6">

            <!-- Sub-navigation Tabs under Settings -->
            <SettingsNavTabs />

            <!-- Page Header -->
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 pb-2">
                <div>
                    <div class="flex items-center gap-2">
                        <span class="text-2xl">💾</span>
                        <h1 class="text-2xl font-bold text-gray-900 dark:text-white">Database Backups &amp; Snapshots</h1>
                    </div>
                    <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">
                        Automated daily compressed MySQL snapshots with 30-day retention and one-click manual backups.
                    </p>
                </div>

                <button
                    type="button"
                    @click="createBackup"
                    :disabled="isBackingUp"
                    class="px-5 py-2.5 text-xs font-bold rounded-xl bg-primary hover:bg-primary-dark text-white transition flex items-center gap-2 shrink-0 disabled:opacity-60 cursor-pointer shadow-xs"
                >
                    <svg v-if="isBackingUp" class="animate-spin h-3.5 w-3.5 text-white" viewBox="0 0 24 24" fill="none"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path></svg>
                    <span v-else>⚡</span>
                    <span>{{ isBackingUp ? 'Creating Backup...' : 'Create Backup Now' }}</span>
                </button>
            </div>

            <!-- Summary KPI Cards -->
            <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
                <div class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-2xl p-5 shadow-xs">
                    <div class="flex items-center justify-between">
                        <span class="text-xs font-bold uppercase tracking-wider text-gray-500">Total Backups</span>
                        <span class="w-8 h-8 rounded-xl bg-primary-soft text-primary flex items-center justify-center text-sm font-bold">🗄️</span>
                    </div>
                    <div class="mt-2 text-2xl font-black text-primary">
                        {{ props.backups?.length || 0 }}
                    </div>
                    <p class="text-[11px] text-gray-400 mt-1">Total snapshots preserved</p>
                </div>

                <div class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-2xl p-5 shadow-xs">
                    <div class="flex items-center justify-between">
                        <span class="text-xs font-bold uppercase tracking-wider text-gray-500">Storage Used</span>
                        <span class="w-8 h-8 rounded-xl bg-blue-100 dark:bg-blue-950/50 text-blue-600 dark:text-blue-400 flex items-center justify-center text-sm font-bold">📊</span>
                    </div>
                    <div class="mt-2 text-2xl font-black text-blue-600 dark:text-blue-400">
                        {{ totalStorageBytes }}
                    </div>
                    <p class="text-[11px] text-gray-400 mt-1">Gzip compressed disk size</p>
                </div>

                <div class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-2xl p-5 shadow-xs">
                    <div class="flex items-center justify-between">
                        <span class="text-xs font-bold uppercase tracking-wider text-gray-500">Automated Schedule</span>
                        <span class="w-8 h-8 rounded-xl bg-emerald-100 dark:bg-emerald-950/50 text-emerald-600 dark:text-emerald-400 flex items-center justify-center text-sm font-bold">⏰</span>
                    </div>
                    <div class="mt-2 text-lg font-black text-emerald-600 dark:text-emerald-400">
                        Daily at 02:00 AM
                    </div>
                    <p class="text-[11px] text-gray-400 mt-1">Retains 30 days of history</p>
                </div>

                <div class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-2xl p-5 shadow-xs">
                    <div class="flex items-center justify-between">
                        <span class="text-xs font-bold uppercase tracking-wider text-gray-500">Latest Snapshot</span>
                        <span class="w-8 h-8 rounded-xl bg-amber-100 dark:bg-amber-950/50 text-amber-600 dark:text-amber-400 flex items-center justify-center text-sm font-bold">📦</span>
                    </div>
                    <div class="mt-2 text-sm font-black text-gray-900 dark:text-white truncate" :title="latestBackup?.name || 'No backups yet'">
                        {{ latestBackup ? latestBackup.size : 'None' }}
                    </div>
                    <p class="text-[11px] text-gray-400 mt-1">
                        {{ latestBackup ? latestBackup.age : 'No backup created yet' }}
                    </p>
                </div>
            </div>

            <!-- Automation & Safety Callout -->
            <div class="p-4 rounded-xl bg-primary-soft/20 border border-primary-soft flex items-start gap-3">
                <span class="text-xl">🛡️</span>
                <div class="text-xs text-gray-600 dark:text-gray-300 space-y-1">
                    <p class="font-bold text-gray-900 dark:text-white">Enterprise Database Reliability</p>
                    <p>
                        Backups use native <code>mysqldump</code> with <code>--single-transaction</code>, <code>--quick</code>, <code>--routines</code>, and <code>--triggers</code> to guarantee zero site downtime and consistent data without locking tables. Backups are streamed directly to disk and compressed with Gzip to minimize storage usage. Backups older than 30 days are pruned automatically.
                    </p>
                </div>
            </div>

            <!-- Backups Table -->
            <section class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 overflow-hidden shadow-xs">
                <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-800/60 flex items-center justify-between">
                    <div>
                        <h2 class="text-base font-bold text-gray-900 dark:text-white">Existing Database Snapshots</h2>
                        <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">Stored in <code>storage/app/backup/</code></p>
                    </div>
                </div>

                <div class="p-6">
                    <div v-if="props.backups && props.backups.length > 0" class="overflow-x-auto">
                        <table class="w-full text-left text-xs">
                            <thead class="text-gray-400 uppercase tracking-wider border-b dark:border-gray-700 bg-gray-50/50 dark:bg-gray-900/40">
                                <tr>
                                    <th class="py-3 px-4">Filename</th>
                                    <th class="py-3 px-4">File Size</th>
                                    <th class="py-3 px-4">Date Created</th>
                                    <th class="py-3 px-4 text-right">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                                <tr v-for="b in props.backups" :key="b.name" class="hover:bg-gray-50/70 dark:hover:bg-gray-700/30 transition">
                                    <td class="py-3 px-4 font-mono font-semibold text-gray-800 dark:text-gray-200 flex items-center gap-2">
                                        <span>🗄️</span>
                                        <span>{{ b.name }}</span>
                                    </td>
                                    <td class="py-3 px-4 text-gray-700 dark:text-gray-300 font-bold">
                                        {{ b.size }}
                                    </td>
                                    <td class="py-3 px-4 text-gray-500 dark:text-gray-400">
                                        {{ b.created_at }} <span class="text-[11px] text-gray-400">({{ b.age }})</span>
                                    </td>
                                    <td class="py-3 px-4 text-right space-x-2">
                                        <button
                                            type="button"
                                            @click="downloadBackup(b.name)"
                                            class="inline-flex items-center gap-1 px-3 py-1.5 rounded-lg bg-primary-soft text-primary hover:opacity-80 font-bold text-xs transition cursor-pointer"
                                            title="Download Backup File"
                                        >
                                            <span>⬇️ Download</span>
                                        </button>
                                        <button
                                            type="button"
                                            @click="deleteBackup(b.name)"
                                            class="inline-flex items-center gap-1 px-2.5 py-1.5 rounded-lg bg-red-50 hover:bg-red-100 dark:bg-red-950/40 text-red-600 dark:text-red-400 font-bold text-xs transition cursor-pointer"
                                            title="Delete Backup File"
                                        >
                                            <span>🗑️ Delete</span>
                                        </button>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    <div v-else class="text-center py-12 text-gray-400 text-xs">
                        <span class="text-3xl block mb-2">📦</span>
                        No database backup files found yet. Click <strong>"Create Backup Now"</strong> above to generate your first snapshot.
                    </div>
                </div>
            </section>
        </div>
    </AdminLayout>
</template>

