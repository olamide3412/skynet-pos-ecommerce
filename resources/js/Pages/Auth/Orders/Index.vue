<script setup>
import { ref, computed, watch } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import { formatCurrency } from '../../../Utils/formatCurrency';
import { formatDate } from '../../../Utils/dateFormat';

const props = defineProps({
  orders: Object,
  filters: Object
});

const statusColors = {
  pending: 'bg-yellow-100 text-yellow-800',
  processing: 'bg-blue-100 text-blue-800',
  completed: 'bg-green-100 text-green-800',
  cancelled: 'bg-red-100 text-red-800'
};

const paymentTypeColors = {
  instant: 'bg-green-100 text-green-800',
  installment: 'bg-blue-100 text-blue-800',
  'on-site-collection': 'bg-purple-100 text-purple-800'
};

// Local filter state
const filters = ref({
  search: props.filters.search || '',
  status: props.filters.status || 'all',
  payment_type: props.filters.payment_type || 'all',
  date_from: props.filters.date_from || '',
  date_to: props.filters.date_to || ''
});

// Watch for filter changes and update the URL
watch(filters.value, (newFilters) => {
  router.get(route('order.index'), newFilters, {
    preserveState: true,
    replace: true
  });
}, { deep: true });

// Reset all filters
const resetFilters = () => {
  filters.value = {
    search: '',
    status: 'all',
    payment_type: 'all',
    date_from: '',
    date_to: ''
  };
};
</script>

<template>
  <div class="p-6 bg-white dark:bg-gray-900 rounded-lg shadow">
    <div class="flex flex-col md:flex-row md:items-center md:justify-between mb-6">
      <h1 class="text-2xl font-bold text-gray-800 dark:text-white">Orders Management</h1>
      <Link
        :href="route('client.index')"
        class="btn-primary mt-4 md:mt-0"
      >
        + Create New Order
      </Link>
    </div>

    <!-- Enhanced Filters -->
    <div class="bg-gray-50 dark:bg-gray-800 p-4 rounded-lg mb-6">
      <div class="grid grid-cols-1 md:grid-cols-5 gap-4 mb-4">
        <!-- Search -->
        <div>
          <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Search</label>
          <input
            type="text"
            v-model="filters.search"
            placeholder="Client name, phone, or order ref..."
            class="w-full p-2 border border-gray-300 rounded-md dark:bg-gray-700 dark:border-gray-600 dark:text-white"
          >
        </div>

        <!-- Status Filter -->
        <div>
          <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Status</label>
          <select
            v-model="filters.status"
            class="w-full p-2 border border-gray-300 rounded-md dark:bg-gray-700 dark:border-gray-600 dark:text-white"
          >
            <option value="all">All Statuses</option>
            <option value="pending">Pending</option>
            <option value="processing">Processing</option>
            <option value="completed">Completed</option>
            <option value="cancelled">Cancelled</option>
          </select>
        </div>

        <!-- Payment Type Filter -->
        <div>
          <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Payment Type</label>
          <select
            v-model="filters.payment_type"
            class="w-full p-2 border border-gray-300 rounded-md dark:bg-gray-700 dark:border-gray-600 dark:text-white"
          >
            <option value="all">All Payment Types</option>
            <option value="instant">Instant</option>
            <option value="installment">Installment</option>
            <option value="on-site-collection">On-Site Collection</option>
          </select>
        </div>

        <!-- Date From -->
        <div>
          <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">From Date</label>
          <input
            type="date"
            v-model="filters.date_from"
            class="w-full p-2 border border-gray-300 rounded-md dark:bg-gray-700 dark:border-gray-600 dark:text-white"
          >
        </div>

        <!-- Date To -->
        <div>
          <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">To Date</label>
          <input
            type="date"
            v-model="filters.date_to"
            class="w-full p-2 border border-gray-300 rounded-md dark:bg-gray-700 dark:border-gray-600 dark:text-white"
          >
        </div>
      </div>

      <!-- Filter Actions -->
      <div class="flex justify-between items-center">
        <div class="text-sm text-gray-600 dark:text-gray-400">
          Showing {{ orders.from }} to {{ orders.to }} of {{ orders.total }} orders
        </div>
        <button
          @click="resetFilters"
          class="px-4 py-2 bg-gray-200 text-gray-700 rounded-md hover:bg-gray-300 dark:bg-gray-700 dark:text-gray-300 dark:hover:bg-gray-600"
        >
          Reset Filters
        </button>
      </div>
    </div>

    <!-- Orders Table -->
    <div class="overflow-x-auto bg-white dark:bg-gray-800 rounded-lg shadow">
      <table class="w-full">
        <thead>
          <tr class="bg-gray-50 dark:bg-gray-700">
            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
              Order Ref
            </th>
            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
              Client
            </th>
            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
              Amount
            </th>
            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
              Payment Type
            </th>
            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
              Status
            </th>
            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
              Date
            </th>
            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
              Actions
            </th>
          </tr>
        </thead>
        <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
          <tr v-for="order in orders.data" :key="order.id" class="hover:bg-gray-50 dark:hover:bg-gray-700">
            <td class="px-6 py-4 whitespace-nowrap">
              <div class="text-sm font-medium text-gray-900 dark:text-white">{{ order.order_ref }}</div>
            </td>
            <td class="px-6 py-4 whitespace-nowrap">
              <div class="flex items-center">
                <div class="ml-4">
                  <div class="text-sm font-medium text-gray-900 dark:text-white">
                    {{ order.client?.full_name || 'N/A' }}
                  </div>
                  <div class="text-sm text-gray-500 dark:text-gray-400">
                    {{ order.client?.phone_number || 'No phone' }}
                  </div>
                </div>
              </div>
            </td>
            <td class="px-6 py-4 whitespace-nowrap">
              <div class="text-sm text-gray-900 dark:text-white font-medium">
                ₦{{ formatCurrency(order.total_price) }}
              </div>
              <div v-if="order.payment_type !== 'instant'" class="text-xs text-gray-500 dark:text-gray-400">
                Paid: ₦{{ formatCurrency(order.amount_paid) }}
              </div>
            </td>
            <td class="px-6 py-4 whitespace-nowrap">
              <span class="px-2 py-1 text-xs font-medium rounded-full" :class="paymentTypeColors[order.payment_type] || 'bg-gray-100 text-gray-800'">
                {{ order.payment_type }}
              </span>
            </td>
            <td class="px-6 py-4 whitespace-nowrap">
              <span class="px-2 py-1 text-xs font-medium rounded-full" :class="statusColors[order.status] || 'bg-gray-100 text-gray-800'">
                {{ order.status }}
              </span>
            </td>
            <td class="px-6 py-4 whitespace-nowrap">
              <div class="text-sm text-gray-900 dark:text-white">{{ formatDate(order.created_at) }}</div>
            </td>
            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
              <Link
                :href="route('order.show', order.id)"
                class="text-blue-600 hover:text-blue-900 dark:text-blue-400 dark:hover:text-blue-300 mr-3"
              >
                View
              </Link>
              <Link
                :href="route('order.edit', order.id)"
                class="text-green-600 hover:text-green-900 dark:text-green-400 dark:hover:text-green-300"
              >
                Edit
              </Link>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- Pagination -->
    <div class="mt-6 flex items-center justify-between">
      <div class="text-sm text-gray-700 dark:text-gray-300">
        Showing {{ orders.from }} to {{ orders.to }} of {{ orders.total }} results
      </div>
      <div class="flex space-x-2">
        <Link
          v-for="(link, index) in orders.links"
          :key="index"
          :href="link.url || '#'"
          :class="{
            'px-3 py-1 rounded-md': true,
            'bg-blue-500 text-white': link.active,
            'bg-gray-200 dark:bg-gray-700 text-gray-700 dark:text-gray-300 hover:bg-gray-300 dark:hover:bg-gray-600': !link.active && link.url,
            'text-gray-400 dark:text-gray-600 cursor-not-allowed': !link.url
          }"
          v-html="link.label"
        />
      </div>
    </div>

    <!-- Empty State -->
    <div v-if="orders.data.length === 0" class="text-center py-12">
      <div class="text-gray-400 dark:text-gray-500 text-6xl mb-4">📦</div>
      <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-2">No orders found</h3>
      <p class="text-gray-500 dark:text-gray-400">Try adjusting your search or filter criteria.</p>
      <button
        @click="resetFilters"
        class="mt-4 px-4 py-2 bg-blue-500 text-white rounded-md hover:bg-blue-600"
      >
        Reset Filters
      </button>
    </div>
  </div>
</template>
