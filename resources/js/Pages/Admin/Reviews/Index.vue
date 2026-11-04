<script setup>
import { Head, Link, router } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import Pagination from '@/Components/Pagination.vue';
import { ref, watch } from 'vue';

const props = defineProps({
    reviews: Object,
    filters: Object,
    stats:   Object,
});

const search = ref(props.filters.search || '');
const status = ref(props.filters.status || '');
const rating = ref(props.filters.rating || '');

const toggleApproval = (review) => {
    router.patch(route('admin.reviews.toggle-approval', review.id), {}, { preserveScroll: true });
};

const deleteReview = (review) => {
    if (confirm(`Are you sure you want to delete review #${review.id}?`)) {
        router.delete(route('admin.reviews.destroy', review.id), { preserveScroll: true });
    }
};

const applyFilter = () => {
    router.get(route('admin.reviews.index'), {
        search: search.value,
        status: status.value,
        rating: rating.value,
    }, { preserveState: true, replace: true });
};

watch([search, status, rating], () => {
    applyFilter();
});
</script>

<template>
    <Head title="Customer Reviews Moderation" />
    <AdminLayout>
        <div class="space-y-6">
            <!-- Header -->
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                <div>
                    <h1 class="text-2xl font-black text-gray-900 dark:text-white tracking-tight">Customer Reviews</h1>
                    <p class="text-sm text-gray-500 dark:text-gray-400">Moderate and approve product ratings submitted by verified customers.</p>
                </div>
            </div>

            <!-- Stats Overview -->
            <div class="grid grid-cols-1 sm:grid-cols-4 gap-4">
                <div class="bg-white dark:bg-gray-800 p-5 rounded-xl border border-gray-200 dark:border-gray-700 shadow-sm flex items-center gap-4">
                    <div class="w-12 h-12 rounded-lg bg-primary/10 text-primary flex items-center justify-center font-bold text-xl">⭐</div>
                    <div>
                        <p class="text-xs uppercase font-bold text-gray-500 tracking-wider">Average Rating</p>
                        <p class="text-2xl font-black text-amber-500">{{ stats.average }} / 5.0</p>
                    </div>
                </div>
                <div class="bg-white dark:bg-gray-800 p-5 rounded-xl border border-gray-200 dark:border-gray-700 shadow-sm flex items-center gap-4">
                    <div class="w-12 h-12 rounded-lg bg-gray-100 text-gray-700 dark:bg-gray-700 dark:text-gray-300 flex items-center justify-center font-bold text-xl">💬</div>
                    <div>
                        <p class="text-xs uppercase font-bold text-gray-500 tracking-wider">Total Reviews</p>
                        <p class="text-2xl font-black text-gray-900 dark:text-white">{{ stats.total }}</p>
                    </div>
                </div>
                <div class="bg-white dark:bg-gray-800 p-5 rounded-xl border border-gray-200 dark:border-gray-700 shadow-sm flex items-center gap-4">
                    <div class="w-12 h-12 rounded-lg bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400 flex items-center justify-center font-bold text-xl">✓</div>
                    <div>
                        <p class="text-xs uppercase font-bold text-gray-500 tracking-wider">Published</p>
                        <p class="text-2xl font-black text-green-600 dark:text-green-400">{{ stats.approved }}</p>
                    </div>
                </div>
                <div class="bg-white dark:bg-gray-800 p-5 rounded-xl border border-gray-200 dark:border-gray-700 shadow-sm flex items-center gap-4">
                    <div class="w-12 h-12 rounded-lg bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-400 flex items-center justify-center font-bold text-xl">⏳</div>
                    <div>
                        <p class="text-xs uppercase font-bold text-gray-500 tracking-wider">Pending / Hidden</p>
                        <p class="text-2xl font-black text-amber-600 dark:text-amber-400">{{ stats.pending }}</p>
                    </div>
                </div>
            </div>

            <!-- Filters Bar -->
            <div class="bg-white dark:bg-gray-800 p-4 rounded-xl border border-gray-200 dark:border-gray-700 flex flex-col sm:flex-row gap-3">
                <div class="flex-1 relative">
                    <input
                        type="text"
                        v-model="search"
                        placeholder="Search product, customer, or comment..."
                        class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 text-sm pl-9 pr-3 py-2"
                    />
                    <svg class="w-4 h-4 text-gray-400 absolute left-3 top-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                </div>
                <select v-model="status" class="rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 text-sm px-3 py-2">
                    <option value="">All Statuses</option>
                    <option value="approved">Approved & Published</option>
                    <option value="pending">Hidden / Pending</option>
                </select>
                <select v-model="rating" class="rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 text-sm px-3 py-2">
                    <option value="">All Star Ratings</option>
                    <option value="5">5 Stars</option>
                    <option value="4">4 Stars</option>
                    <option value="3">3 Stars</option>
                    <option value="2">2 Stars</option>
                    <option value="1">1 Star</option>
                </select>
            </div>

            <!-- Reviews Table -->
            <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 shadow-sm overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-sm text-gray-700 dark:text-gray-300">
                        <thead class="bg-gray-50 dark:bg-gray-900/60 text-xs font-bold uppercase text-gray-500 border-b dark:border-gray-700">
                            <tr>
                                <th class="px-5 py-3.5">Product</th>
                                <th class="px-5 py-3.5">Customer</th>
                                <th class="px-5 py-3.5">Rating</th>
                                <th class="px-5 py-3.5">Comment</th>
                                <th class="px-5 py-3.5">Date</th>
                                <th class="px-5 py-3.5 text-center">Status</th>
                                <th class="px-5 py-3.5 text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y dark:divide-gray-700">
                            <tr v-if="!reviews.data || reviews.data.length === 0">
                                <td colspan="7" class="px-5 py-8 text-center text-gray-500">
                                    No customer reviews found matching current filters.
                                </td>
                            </tr>
                            <tr v-for="rev in reviews.data" :key="rev.id" class="hover:bg-gray-50/50 dark:hover:bg-gray-700/30 transition">
                                <!-- Product -->
                                <td class="px-5 py-4">
                                    <div class="flex items-center gap-3 max-w-[240px]">
                                        <img
                                            :src="rev.product?.image ? '/storage/' + rev.product.image : 'https://placehold.co/60x60?text=P'"
                                            class="w-10 h-10 object-cover rounded-lg border dark:border-gray-600 flex-shrink-0"
                                        />
                                        <Link
                                            v-if="rev.product"
                                            :href="route('shop.show', rev.product.url_key)"
                                            target="_blank"
                                            class="font-semibold text-gray-900 dark:text-white hover:text-primary transition line-clamp-1 text-xs"
                                        >
                                            {{ rev.product.name }}
                                        </Link>
                                        <span v-else class="text-xs text-gray-400">Deleted Product</span>
                                    </div>
                                </td>

                                <!-- Customer -->
                                <td class="px-5 py-4">
                                    <div class="text-xs">
                                        <p class="font-bold text-gray-900 dark:text-white">{{ rev.customer?.name || 'Guest/Deleted' }}</p>
                                        <p class="text-gray-400">{{ rev.customer?.email }}</p>
                                        <span v-if="rev.order_id" class="inline-flex items-center gap-1 text-[10px] text-green-600 font-bold mt-0.5">
                                            ✓ Verified Buyer
                                        </span>
                                    </div>
                                </td>

                                <!-- Rating Stars -->
                                <td class="px-5 py-4">
                                    <div class="flex items-center gap-1 text-amber-400">
                                        <svg v-for="s in 5" :key="s" class="w-3.5 h-3.5 fill-current" :class="s <= rev.rating ? 'text-amber-400' : 'text-gray-300 dark:text-gray-600'" viewBox="0 0 20 20">
                                            <path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z" />
                                        </svg>
                                        <span class="text-xs font-bold text-gray-700 dark:text-gray-300 ml-1">{{ rev.rating }}</span>
                                    </div>
                                </td>

                                <!-- Comment -->
                                <td class="px-5 py-4 max-w-[300px]">
                                    <p class="text-xs text-gray-700 dark:text-gray-300 line-clamp-2 italic">
                                        {{ rev.comment || '— No written comment —' }}
                                    </p>
                                </td>

                                <!-- Date -->
                                <td class="px-5 py-4 text-xs text-gray-500 whitespace-nowrap">
                                    {{ new Date(rev.created_at).toLocaleDateString() }}
                                </td>

                                <!-- Status Toggle -->
                                <td class="px-5 py-4 text-center">
                                    <button
                                        @click="toggleApproval(rev)"
                                        :class="rev.is_approved ? 'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400' : 'bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-400'"
                                        class="px-2.5 py-1 rounded-full text-xs font-bold transition hover:opacity-80"
                                    >
                                        {{ rev.is_approved ? 'Published' : 'Hidden' }}
                                    </button>
                                </td>

                                <!-- Actions -->
                                <td class="px-5 py-4 text-right">
                                    <div class="flex items-center justify-end gap-2">
                                        <button
                                            @click="toggleApproval(rev)"
                                            :class="rev.is_approved ? 'text-amber-600 hover:text-amber-800' : 'text-green-600 hover:text-green-800'"
                                            class="text-xs font-bold px-2 py-1 rounded border dark:border-gray-600 transition"
                                        >
                                            {{ rev.is_approved ? 'Hide' : 'Publish' }}
                                        </button>
                                        <button
                                            @click="deleteReview(rev)"
                                            class="p-1.5 text-red-500 hover:text-red-700 hover:bg-red-50 dark:hover:bg-red-900/30 rounded transition"
                                            title="Delete Review"
                                        >
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div v-if="reviews.links && reviews.links.length > 3" class="p-4 border-t dark:border-gray-700">
                    <Pagination :links="reviews.links" />
                </div>
            </div>
        </div>
    </AdminLayout>
</template>

