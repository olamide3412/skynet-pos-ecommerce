<script setup>
import { Head, Link } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import Pagination from '@/Components/Pagination.vue';
import { ref } from 'vue';

const props = defineProps({
    top_searches: Object,
    zero_result_searches: Array,
    recent_searches: Array,
    metrics: Object,
});

const activeTab = ref('top');
</script>

<template>
    <Head title="Search Analytics & Customer Demand" />
    <AdminLayout>
        <div class="space-y-6">
            <!-- Header -->
            <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
                <div>
                    <h1 class="text-2xl font-black text-gray-900 dark:text-white tracking-tight flex items-center gap-2.5">
                        <span class="w-9 h-9 rounded-xl bg-primary/10 text-primary flex items-center justify-center">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                            </svg>
                        </span>
                        Search Analytics & Customer Demand
                    </h1>
                    <p class="text-xs sm:text-sm text-gray-500 dark:text-gray-400 mt-1">
                        Track customer search queries, discover what shoppers want most, and identify unstocked products.
                    </p>
                </div>
            </div>

            <!-- Summary KPI Cards -->
            <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
                <div class="bg-white dark:bg-gray-800 p-5 rounded-2xl border border-gray-100 dark:border-gray-700/80 shadow-xs">
                    <p class="text-xs font-semibold text-gray-400 uppercase tracking-wider">Total Searches</p>
                    <p class="text-2xl sm:text-3xl font-black text-gray-900 dark:text-white mt-1">
                        {{ metrics.total_searches?.toLocaleString() || 0 }}
                    </p>
                    <span class="text-[11px] font-medium text-emerald-600 dark:text-emerald-400 mt-0.5 block">Lifetime queries logged</span>
                </div>

                <div class="bg-white dark:bg-gray-800 p-5 rounded-2xl border border-gray-100 dark:border-gray-700/80 shadow-xs">
                    <p class="text-xs font-semibold text-gray-400 uppercase tracking-wider">Today's Searches</p>
                    <p class="text-2xl sm:text-3xl font-black text-primary mt-1">
                        {{ metrics.today_searches?.toLocaleString() || 0 }}
                    </p>
                    <span class="text-[11px] font-medium text-gray-500 mt-0.5 block">Searches logged today</span>
                </div>

                <div class="bg-white dark:bg-gray-800 p-5 rounded-2xl border border-gray-100 dark:border-gray-700/80 shadow-xs">
                    <p class="text-xs font-semibold text-gray-400 uppercase tracking-wider">Unique Keywords</p>
                    <p class="text-2xl sm:text-3xl font-black text-blue-600 dark:text-blue-400 mt-1">
                        {{ metrics.unique_terms?.toLocaleString() || 0 }}
                    </p>
                    <span class="text-[11px] font-medium text-gray-500 mt-0.5 block">Distinct phrases searched</span>
                </div>

                <div class="bg-white dark:bg-gray-800 p-5 rounded-2xl border border-gray-100 dark:border-gray-700/80 shadow-xs">
                    <p class="text-xs font-semibold text-gray-400 uppercase tracking-wider">Zero-Result Searches</p>
                    <p class="text-2xl sm:text-3xl font-black text-amber-600 dark:text-amber-400 mt-1">
                        {{ metrics.zero_results?.toLocaleString() || 0 }}
                    </p>
                    <span class="text-[11px] font-medium text-amber-600 dark:text-amber-400 mt-0.5 block">Unmet customer demand</span>
                </div>
            </div>

            <!-- Tab Buttons -->
            <div class="flex items-center gap-2 border-b border-gray-200 dark:border-gray-700 pb-2">
                <button
                    type="button"
                    @click="activeTab = 'top'"
                    :class="activeTab === 'top' ? 'bg-primary text-white font-bold' : 'text-gray-600 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700'"
                    class="px-4 py-2 rounded-xl text-xs sm:text-sm transition cursor-pointer"
                >
                    🔥 Top Searched Items
                </button>
                <button
                    type="button"
                    @click="activeTab = 'zero'"
                    :class="activeTab === 'zero' ? 'bg-amber-600 text-white font-bold' : 'text-gray-600 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700'"
                    class="px-4 py-2 rounded-xl text-xs sm:text-sm transition cursor-pointer flex items-center gap-1.5"
                >
                    <span>⚠️ Unmet Demand (Zero Results)</span>
                    <span v-if="zero_result_searches?.length" class="px-1.5 py-0.2 bg-white/20 rounded-full text-[10px]">{{ zero_result_searches.length }}</span>
                </button>
                <button
                    type="button"
                    @click="activeTab = 'recent'"
                    :class="activeTab === 'recent' ? 'bg-primary text-white font-bold' : 'text-gray-600 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700'"
                    class="px-4 py-2 rounded-xl text-xs sm:text-sm transition cursor-pointer"
                >
                    🕒 Real-time Search Stream
                </button>
            </div>

            <!-- TAB 1: Top Searches Table -->
            <div v-if="activeTab === 'top'" class="bg-white dark:bg-gray-800 rounded-2xl border border-gray-100 dark:border-gray-700/80 shadow-xs overflow-hidden">
                <div class="px-5 py-4 border-b border-gray-100 dark:border-gray-700">
                    <h2 class="text-sm font-bold text-gray-900 dark:text-white uppercase tracking-wider">Top Customer Searches</h2>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-sm">
                        <thead class="bg-gray-50/75 dark:bg-gray-900/50 text-xs uppercase text-gray-400 font-semibold border-b dark:border-gray-700">
                            <tr>
                                <th class="px-5 py-3">Search Term</th>
                                <th class="px-5 py-3 text-center">Frequency (Count)</th>
                                <th class="px-5 py-3 text-center">Catalog Matches</th>
                                <th class="px-5 py-3 text-right">Last Searched</th>
                                <th class="px-5 py-3 text-right">Quick Action</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-gray-700/60">
                            <tr v-for="(item, idx) in top_searches.data" :key="idx" class="hover:bg-gray-50 dark:hover:bg-gray-700/40 transition">
                                <td class="px-5 py-3.5 font-bold text-gray-900 dark:text-white flex items-center gap-2">
                                    <span class="text-gray-400 text-xs">#{{ (top_searches.current_page - 1) * top_searches.per_page + idx + 1 }}</span>
                                    <span>{{ item.normalized_query }}</span>
                                </td>
                                <td class="px-5 py-3.5 text-center">
                                    <span class="px-2.5 py-1 rounded-full text-xs font-black bg-primary/10 text-primary">
                                        {{ item.total_searches }} searches
                                    </span>
                                </td>
                                <td class="px-5 py-3.5 text-center">
                                    <span
                                        :class="item.last_results_count > 0 ? 'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/40 dark:text-emerald-300' : 'bg-red-100 text-red-800 dark:bg-red-900/40 dark:text-red-300'"
                                        class="px-2 py-0.5 rounded-full text-xs font-semibold"
                                    >
                                        {{ item.last_results_count > 0 ? `${item.last_results_count} products` : 'No products found' }}
                                    </span>
                                </td>
                                <td class="px-5 py-3.5 text-right text-xs text-gray-500">
                                    {{ new Date(item.last_searched_at).toLocaleString() }}
                                </td>
                                <td class="px-5 py-3.5 text-right">
                                    <a
                                        :href="route('shop.index', { search: item.normalized_query })"
                                        target="_blank"
                                        class="inline-flex items-center gap-1 text-xs font-bold text-primary hover:underline"
                                    >
                                        View on store →
                                    </a>
                                </td>
                            </tr>
                            <tr v-if="!top_searches.data || !top_searches.data.length">
                                <td colspan="5" class="px-5 py-8 text-center text-sm text-gray-400">
                                    No search queries recorded yet. Once shoppers search on the storefront, keywords will appear here.
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <div v-if="top_searches.links && top_searches.data.length" class="p-4 border-t dark:border-gray-700">
                    <Pagination :paginator="top_searches" />
                </div>
            </div>

            <!-- TAB 2: Zero Results Table (Unmet demand) -->
            <div v-if="activeTab === 'zero'" class="bg-white dark:bg-gray-800 rounded-2xl border border-amber-200 dark:border-amber-900/50 shadow-xs overflow-hidden">
                <div class="px-5 py-4 border-b border-amber-100 dark:border-amber-900/50 bg-amber-50/50 dark:bg-amber-950/20">
                    <h2 class="text-sm font-bold text-amber-900 dark:text-amber-200 uppercase tracking-wider flex items-center gap-2">
                        <span>⚠️ Unmet Customer Searches (Opportunities to add inventory)</span>
                    </h2>
                    <p class="text-xs text-amber-700 dark:text-amber-300/80 mt-0.5">
                        These are products customers searched for but your store had 0 results for. Consider stocking these items!
                    </p>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-sm">
                        <thead class="bg-gray-50/75 dark:bg-gray-900/50 text-xs uppercase text-gray-400 font-semibold border-b dark:border-gray-700">
                            <tr>
                                <th class="px-5 py-3">Unfound Search Term</th>
                                <th class="px-5 py-3 text-center">Search Attempts</th>
                                <th class="px-5 py-3 text-right">Last Attempted</th>
                                <th class="px-5 py-3 text-right">Action</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-gray-700/60">
                            <tr v-for="(item, idx) in zero_result_searches" :key="idx" class="hover:bg-gray-50 dark:hover:bg-gray-700/40 transition">
                                <td class="px-5 py-3.5 font-bold text-gray-900 dark:text-white">
                                    "{{ item.normalized_query }}"
                                </td>
                                <td class="px-5 py-3.5 text-center">
                                    <span class="px-2.5 py-1 rounded-full text-xs font-bold bg-amber-100 text-amber-800 dark:bg-amber-900/40 dark:text-amber-300">
                                        {{ item.search_count }} times
                                    </span>
                                </td>
                                <td class="px-5 py-3.5 text-right text-xs text-gray-500">
                                    {{ new Date(item.last_searched_at).toLocaleString() }}
                                </td>
                                <td class="px-5 py-3.5 text-right">
                                    <Link
                                        :href="route('admin.products.index', { search: item.normalized_query })"
                                        class="inline-flex items-center gap-1 text-xs font-bold text-primary hover:underline"
                                    >
                                        + Add to catalog
                                    </Link>
                                </td>
                            </tr>
                            <tr v-if="!zero_result_searches || !zero_result_searches.length">
                                <td colspan="4" class="px-5 py-8 text-center text-sm text-gray-400">
                                    Great job! No zero-result searches found. Every customer search matched catalog products.
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- TAB 3: Recent Searches Stream -->
            <div v-if="activeTab === 'recent'" class="bg-white dark:bg-gray-800 rounded-2xl border border-gray-100 dark:border-gray-700/80 shadow-xs overflow-hidden">
                <div class="px-5 py-4 border-b border-gray-100 dark:border-gray-700">
                    <h2 class="text-sm font-bold text-gray-900 dark:text-white uppercase tracking-wider">Live Search Query Stream</h2>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-sm">
                        <thead class="bg-gray-50/75 dark:bg-gray-900/50 text-xs uppercase text-gray-400 font-semibold border-b dark:border-gray-700">
                            <tr>
                                <th class="px-5 py-3">Query</th>
                                <th class="px-5 py-3 text-center">Results</th>
                                <th class="px-5 py-3">Shopper</th>
                                <th class="px-5 py-3">IP Address</th>
                                <th class="px-5 py-3 text-right">Time</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-gray-700/60">
                            <tr v-for="item in recent_searches" :key="item.id" class="hover:bg-gray-50 dark:hover:bg-gray-700/40 transition">
                                <td class="px-5 py-3 font-semibold text-gray-900 dark:text-white">
                                    {{ item.query }}
                                </td>
                                <td class="px-5 py-3 text-center">
                                    <span :class="item.results_count > 0 ? 'text-emerald-600 font-bold' : 'text-red-500 font-medium'" class="text-xs">
                                        {{ item.results_count }} found
                                    </span>
                                </td>
                                <td class="px-5 py-3 text-xs text-gray-600 dark:text-gray-300">
                                    {{ item.customer ? `${item.customer.name} (${item.customer.email})` : 'Guest Shopper' }}
                                </td>
                                <td class="px-5 py-3 text-xs font-mono text-gray-400">
                                    {{ item.ip_address || '—' }}
                                </td>
                                <td class="px-5 py-3 text-right text-xs text-gray-500">
                                    {{ new Date(item.created_at).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' }) }}
                                </td>
                            </tr>
                            <tr v-if="!recent_searches || !recent_searches.length">
                                <td colspan="5" class="px-5 py-8 text-center text-sm text-gray-400">
                                    No live queries yet.
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </AdminLayout>
</template>

