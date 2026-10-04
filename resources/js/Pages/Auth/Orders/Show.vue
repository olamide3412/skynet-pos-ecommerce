<script setup>
import { ref, computed } from 'vue';
import { usePage, Link } from '@inertiajs/vue3';
import { formatCurrency } from '../../../Utils/formatCurrency';
import { formatDate } from '../../../Utils/dateFormat';

const props = defineProps({
  order: Object
});

const statusColors = {
  pending: 'bg-yellow-100 text-yellow-800',
  processing: 'bg-blue-100 text-blue-800',
  completed: 'bg-green-100 text-green-800',
  paid: 'bg-green-100 text-green-800',
  cancelled: 'bg-red-100 text-red-800'
};

const paymentTypeColors = {
  instant: 'bg-green-100 text-green-800',
  installment: 'bg-blue-100 text-blue-800',
  'on-site-collection': 'bg-purple-100 text-purple-800'
};

// Calculate order statistics
const orderStats = computed(() => {
  return {
    totalItems: props.order.items.reduce((sum, item) => sum + item.quantity, 0),
    uniqueProducts: props.order.items.length
  };
});
</script>

<template>
  <div class="p-6 bg-white dark:bg-gray-900 rounded-lg shadow">
    <!-- Header -->
    <div class="flex flex-col md:flex-row md:items-center md:justify-between mb-6">
      <div>
        <h1 class="text-2xl font-bold text-gray-800 dark:text-white">Order Details</h1>
        <p class="text-gray-600 dark:text-gray-400">Reference: {{ order.order_ref }}</p>
      </div>
      <div v-if="$page.props.auth.user" class="flex space-x-3 mt-4 md:mt-0">
        <Link
          :href="route('order.index')"
          class="btn-secondary"
        >
          ← Back to Orders
        </Link>
        <Link
          :href="route('order.edit', order.id)"
          class="btn-primary"
          v-if="$page.props.auth.user"
        >
          Edit Order
        </Link>
      </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
      <!-- Left Column - Order Info -->
      <div class="lg:col-span-2 space-y-6">
        <!-- Order Summary -->
        <div class="bg-gray-50 dark:bg-gray-800 p-6 rounded-lg">
          <h2 class="text-lg font-semibold mb-4 flex items-center">
            <span class="mr-2">📦</span> Order Summary
          </h2>

          <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <div>
              <h3 class="font-medium text-gray-700 dark:text-gray-300 mb-2">Client Information</h3>
              <div class="space-y-1 text-sm">
                <p><span class="text-gray-600 dark:text-gray-400">Name:</span> {{ order.client?.full_name || 'N/A' }}</p>
                <p><span class="text-gray-600 dark:text-gray-400">Phone:</span> {{ order.client?.phone_number || 'N/A' }}</p>
                <p><span class="text-gray-600 dark:text-gray-400">Email:</span> {{ order.client?.email || 'N/A' }}</p>
              </div>
            </div>

            <div>
              <h3 class="font-medium text-gray-700 dark:text-gray-300 mb-2">Order Details</h3>
              <div class="space-y-1 text-sm">
                <p><span class="text-gray-600 dark:text-gray-400">Status:</span>
                  <span class="px-2 py-1 text-xs font-medium rounded-full" :class="statusColors[order.status]">
                    {{ order.status }}
                  </span>
                </p>
                <p><span class="text-gray-600 dark:text-gray-400">Payment Type:</span>
                  <span class="px-2 py-1 text-xs font-medium rounded-full" :class="paymentTypeColors[order.payment_type]">
                    {{ order.payment_type }}
                  </span>
                </p>
                <p><span class="text-gray-600 dark:text-gray-400">Created:</span> {{ formatDate(order.created_at) }}</p>
              </div>
            </div>
          </div>
        </div>

        <!-- Products -->
        <div class="bg-white dark:bg-gray-800 p-6 rounded-lg border border-gray-200 dark:border-gray-700">
          <h2 class="text-lg font-semibold mb-4 flex items-center">
            <span class="mr-2">🛒</span> Products ({{ orderStats.totalItems }} items)
          </h2>

          <div class="space-y-4">
            <div v-for="item in order.items" :key="item.id" class="flex items-center justify-between p-4 border border-gray-100 dark:border-gray-700 rounded-lg">
              <div class="flex items-center">
                <div class="ml-4">
                  <h4 class="font-medium text-gray-900 dark:text-white">{{ item.product?.name || 'Unknown Product' }}</h4>
                  <p class="text-sm text-gray-500 dark:text-gray-400">₦{{ formatCurrency(item.product?.price || 0) }} each</p>
                </div>
              </div>
              <div class="text-right">
                <p class="font-medium text-gray-900 dark:text-white">₦{{ formatCurrency(item.total_price) }}</p>
                <p class="text-sm text-gray-500 dark:text-gray-400">Qty: {{ item.quantity }}</p>
              </div>
            </div>

            <!-- Order Totals -->
            <div class="border-t pt-4 mt-4 space-y-2">
              <div class="flex justify-between">
                <span class="font-medium">Subtotal:</span>
                <span>₦{{ formatCurrency(order.total_price) }}</span>
              </div>
              <div v-if="order.payment_type !== 'instant'" class="flex justify-between">
                <span class="font-medium">Amount Paid:</span>
                <span>₦{{ formatCurrency(order.amount_paid) }}</span>
              </div>
              <div v-if="order.payment_type !== 'instant'" class="flex justify-between">
                <span class="font-medium">Remaining Balance:</span>
                <span>₦{{ formatCurrency(order.remaining_balance) }}</span>
              </div>
              <div class="flex justify-between text-lg font-bold border-t pt-2 mt-2">
                <span>Total:</span>
                <span>₦{{ formatCurrency(order.total_price) }}</span>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Right Column - Additional Info -->
      <div class="space-y-6">
        <!-- Payment Information -->
        <div class="bg-gray-50 dark:bg-gray-800 p-6 rounded-lg">
          <h2 class="text-lg font-semibold mb-4 flex items-center">
            <span class="mr-2">💳</span> Payment Information
          </h2>

          <div class="space-y-3">
            <div>
              <span class="text-sm text-gray-600 dark:text-gray-400">Type:</span>
              <p class="font-medium">{{ order.payment_type }}</p>
            </div>

            <div v-if="order.payment_type !== 'instant'">
              <span class="text-sm text-gray-600 dark:text-gray-400">Repayment Frequency:</span>
              <p class="font-medium">{{ order.repayment_frequency }}</p>
            </div>

            <div v-if="order.payment_type !== 'instant'">
              <span class="text-sm text-gray-600 dark:text-gray-400">Repayment Term:</span>
              <p class="font-medium">{{ order.repayment_term }} months</p>
            </div>

            <div v-if="order.payment_type !== 'instant'">
              <span class="text-sm text-gray-600 dark:text-gray-400">Monthly Payment:</span>
              <p class="font-medium">₦{{ formatCurrency(order.total_price / order.repayment_term) }}</p>
            </div>
          </div>
        </div>

        <!-- Employment Details (if exists) -->
        <div v-if="order.employment_detail" class="bg-white dark:bg-gray-800 p-6 rounded-lg border border-gray-200 dark:border-gray-700">
          <h2 class="text-lg font-semibold mb-4 flex items-center">
            <span class="mr-2">💼</span> Employment Details
          </h2>

          <div class="space-y-3">
            <div>
              <span class="text-sm text-gray-600 dark:text-gray-400">Monthly Salary:</span>
              <p class="font-medium">₦{{ formatCurrency(order.employment_detail.monthly_salary) }}</p>
            </div>

            <div>
              <span class="text-sm text-gray-600 dark:text-gray-400">Employer:</span>
              <p class="font-medium">{{ order.employment_detail.employer || 'N/A' }}</p>
            </div>

            <div>
              <span class="text-sm text-gray-600 dark:text-gray-400">Department:</span>
              <p class="font-medium">{{ order.employment_detail.department || 'N/A' }}</p>
            </div>

            <div>
              <span class="text-sm text-gray-600 dark:text-gray-400">Work ID:</span>
              <p class="font-medium">{{ order.employment_detail.work_number_id || 'N/A' }}</p>
            </div>
          </div>
        </div>

        <!-- Guarantors (if exists) -->
        <div v-if="order.employment_detail?.guarantors" class="bg-white dark:bg-gray-800 p-6 rounded-lg border border-gray-200 dark:border-gray-700">
          <h2 class="text-lg font-semibold mb-4 flex items-center">
            <span class="mr-2">👥</span> Guarantors
          </h2>

          <div class="space-y-4">
            <div v-for="(guarantor, index) in JSON.parse(order.employment_detail.guarantors)" :key="index" class="p-3 bg-gray-50 dark:bg-gray-700 rounded">
              <h4 class="font-medium mb-2">Guarantor {{ parseInt(index) + 1 }}</h4>
              <div class="space-y-1 text-sm">
                <p><span class="text-gray-600 dark:text-gray-400">Name:</span> {{ guarantor.name }}</p>
                <p><span class="text-gray-600 dark:text-gray-400">Phone:</span> {{ guarantor.phone }}</p>
                <p><span class="text-gray-600 dark:text-gray-400">Relationship:</span> {{ guarantor.relationship }}</p>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>
