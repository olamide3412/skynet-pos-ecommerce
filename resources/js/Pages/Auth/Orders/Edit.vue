<script setup>
import { ref, computed } from 'vue';
import { useForm, usePage, Link } from '@inertiajs/vue3';
import TextInput from '@/Components/Forms/TextInput.vue';
import SelectInput from '@/Components/Forms/SelectInput.vue';
import { useToast } from 'vue-toastification';
import { formatCurrency } from '../../../Utils/formatCurrency';
import { formatDate } from '../../../Utils/dateFormat';

const props = defineProps({
  order: Object
});

const toast = useToast();
const currentUser = usePage().props.auth.user;

// Status options
const statusOptions = [
  { value: 'pending', label: 'Pending' },
  { value: 'rejected', label: 'Rejected' },
  { value: 'processing', label: 'Processing' },
  { value: 'completed', label: 'Completed' },
  { value: 'cancelled', label: 'Cancelled' }
];

// Payment method options
const paymentMethodOptions = [
  { value: 'cash', label: 'Cash' },
  { value: 'transfer', label: 'Transfer' },
  { value: 'pos', label: 'POS' }
];

// Form state
const form = useForm({
  status: props.order.status,
  // Payment fields
  payment_amount: '',
  payment_method: 'cash'
});

// Calculate maximum payment amount
const maxPaymentAmount = computed(() => {
  return props.order.remaining_balance > 0 ? props.order.remaining_balance : 0;
});

// Quick status update function
const updateStatus = (newStatus) => {
  const statusForm = useForm({
    status: newStatus
  });

  statusForm.put(route('order.update.status', props.order.id), {
    preserveScroll: true,
    onSuccess: () => {
      toast.success(`Order status updated to ${newStatus}`);
    },
    onError: () => {
      toast.error('Error updating order status');
    }
  });
};

// Submit payment
const submitPayment = () => {
  if (!form.payment_amount || form.payment_amount <= 0) {
    toast.error('Please enter a valid payment amount');
    return;
  }

  if (form.payment_amount > maxPaymentAmount.value) {
    toast.error(`Payment amount cannot exceed remaining balance of ₦${formatCurrency(maxPaymentAmount.value)}`);
    return;
  }

  form.post(route('order.update.payment', props.order.id), {
    preserveScroll: true,
    onSuccess: () => {
      //toast.success('Payment recorded successfully!');
      form.reset('payment_amount');
      form.payment_method = 'cash';
    },
    onError: () => {
      toast.error('Error recording payment');
    }
  });
};

// Complete order (when balance is zero)
const completeOrder = () => {
  if (props.order.remaining_balance > 0) {
    toast.error('Cannot complete order with outstanding balance');
    return;
  }

  const completeForm = useForm({
    status: 'completed'
  });

  completeForm.put(route('order.update.status', props.order.id), {
    preserveScroll: true,
    onSuccess: (response) => {
      //toast.success('Order marked as completed!');
    },
    onError: () => {
      toast.error('Error completing order');
    }
  });
};
</script>

<template>
  <div class="p-6 bg-white dark:bg-gray-900 rounded-lg shadow">
    <!-- Header -->
    <div class="flex flex-col md:flex-row md:items-center md:justify-between mb-6">
      <div>
        <h1 class="text-2xl font-bold text-gray-800 dark:text-white">Manage Order</h1>
        <p class="text-gray-600 dark:text-gray-400">Reference: {{ order.order_ref }}</p>
        <p class="text-sm text-gray-500">Client: {{ order.client?.full_name }}</p>
      </div>
      <div class="flex space-x-3 mt-4 md:mt-0">
        <Link
          :href="route('order.show', order.id)"
          class="btn-secondary"
        >
          ← View Order
        </Link>
        <Link
          :href="route('order.index')"
          class="btn-secondary"
        >
          All Orders
        </Link>
      </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
      <!-- Left Column - Order Management -->
      <div class="lg:col-span-2">
        <!-- Quick Status Actions -->
        <div class="bg-gray-50 dark:bg-gray-800 p-6 rounded-lg mb-6">
          <h2 class="text-lg font-semibold mb-4">Order Status</h2>

          <div class="grid grid-cols-2 md:grid-cols-4 gap-3 mb-4">
            <button
              @click="updateStatus('processing')"
              :disabled="order.status !== 'pending' "
              class="px-4 py-2 bg-blue-500 text-white rounded hover:bg-blue-600 disabled:opacity-50 disabled:cursor-not-allowed"
            >
              Approve
            </button>
            <button
              @click="completeOrder"
              :disabled="order.status === 'completed' || order.remaining_balance > 0"
              class="px-4 py-2 bg-green-500 text-white rounded hover:bg-green-600 disabled:opacity-50 disabled:cursor-not-allowed"
            >
              Complete
            </button>
            <button
              @click="updateStatus('rejected')"
              :disabled="order.status !== 'pending'"
              class="px-4 py-2 bg-red-500 text-white rounded hover:bg-red-600 disabled:opacity-50 disabled:cursor-not-allowed"
            >
              Reject
            </button>
            <button
              @click="updateStatus('cancelled')"
              :disabled="order.status !== 'pending'"
              class="px-4 py-2 bg-red-600 text-white rounded hover:bg-red-700 disabled:opacity-50 disabled:cursor-not-allowed"
            >
              Cancelled
            </button>
          </div>

          <div class="mt-4 hidden">
            <SelectInput
              label="Change Status"
              v-model="form.status"
              :options="statusOptions"
              :error="form.errors.status"
            />
            <button
              @click="updateStatus(form.status)"
              class="mt-2 px-4 py-2 bg-gray-500 text-white rounded hover:bg-gray-600 w-full"
            >
              Update Status
            </button>
          </div>
        </div>

        <!-- Payment Section - Show only for non-instant payments with balance -->
        <div v-if="order.payment_type !== 'instant' && order.remaining_balance > 0"
             class="bg-gray-50 dark:bg-gray-800 p-6 rounded-lg mb-6">
          <h2 class="text-lg font-semibold mb-4">Record Payment</h2>

          <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-4">
            <TextInput
              label="Amount"
              type="number"
              v-model="form.payment_amount"
              :error="form.errors.payment_amount"
              :required="true"
              step="0.01"
              :min="0"
              :max="maxPaymentAmount"
              placeholder="Enter payment amount"
            />

            <SelectInput
              name="Payment Method"
              v-model="form.payment_method"
              :options="paymentMethodOptions"
              :error="form.errors.payment_method"
              :required="true"
            />

            <div class="flex items-center mt-5">
              <button
                @click="submitPayment"
                :disabled="form.processing"
                class="px-4 py-2 bg-green-500 text-white rounded hover:bg-green-600 disabled:opacity-50 w-full"
              >
                Record Payment
              </button>
            </div>
          </div>

          <p class="text-sm text-gray-600 dark:text-gray-400">
            Maximum payment: ₦{{ formatCurrency(maxPaymentAmount) }}
          </p>
        </div>

        <!-- Payment History -->
        <div v-if="order.payments && order.payments.length > 0" class="bg-white dark:bg-gray-800 p-6 rounded-lg border border-gray-200 dark:border-gray-700">
          <h2 class="text-lg font-semibold mb-4">Payment History</h2>

          <div class="space-y-3">
            <div v-for="payment in order.payments" :key="payment.id" class="border-b pb-3 last:border-b-0">
              <div class="flex justify-between items-start">
                <div>
                  <p class="font-medium">₦{{ formatCurrency(payment.amount) }}</p>
                  <p class="text-sm text-gray-600 dark:text-gray-400 capitalize">{{ payment.payment_method }}</p>
                  <p class="text-xs text-gray-500 dark:text-gray-400">
                    {{ formatDate(payment.payment_date) }}
                  </p>
                </div>
                <span class="px-2 py-1 text-xs rounded-full"
                      :class="payment.payment_status === 'success' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'">
                  {{ payment.payment_status }}
                </span>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Right Column - Order Information -->
      <div class="space-y-6">
        <!-- Order Summary -->
        <div class="bg-white dark:bg-gray-800 p-6 rounded-lg border border-gray-200 dark:border-gray-700">
          <h2 class="text-lg font-semibold mb-4">Order Summary</h2>

          <div class="space-y-3">
            <div>
              <span class="text-sm text-gray-600 dark:text-gray-400">Current Status:</span>
              <span class="ml-2 px-2 py-1 text-xs font-medium rounded-full"
                    :class="{
                      'bg-yellow-100 text-yellow-800': order.status === 'pending',
                      'bg-blue-100 text-blue-800': order.status === 'processing',
                      'bg-green-100 text-green-800': order.status === 'completed',
                      'bg-red-100 text-red-800': order.status === 'cancelled'
                    }">
                {{ order.status }}
              </span>
            </div>

            <div>
              <span class="text-sm text-gray-600 dark:text-gray-400">Total Amount:</span>
              <p class="font-medium">₦{{ formatCurrency(order.total_price) }}</p>
            </div>

            <div>
              <span class="text-sm text-gray-600 dark:text-gray-400">Amount Paid:</span>
              <p class="font-medium">₦{{ formatCurrency(order.amount_paid) }}</p>
            </div>

            <div>
              <span class="text-sm text-gray-600 dark:text-gray-400">Balance:</span>
              <p class="font-medium" :class="{ 'text-red-600': order.remaining_balance > 0, 'text-green-600': order.remaining_balance === 0 }">
                ₦{{ formatCurrency(order.remaining_balance) }}
              </p>
            </div>

            <div>
              <span class="text-sm text-gray-600 dark:text-gray-400">Payment Type:</span>
              <p class="font-medium capitalize">{{ order.payment_type }}</p>
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

        <!-- User Actions -->
        <div class="bg-white dark:bg-gray-800 p-6 rounded-lg border border-gray-200 dark:border-gray-700">
          <h2 class="text-lg font-semibold mb-4">User Actions</h2>

          <div class="space-y-3">
            <div v-if="order.approved_by">
              <span class="text-sm text-gray-600 dark:text-gray-400">Approved By:</span>
              <p class="font-medium">{{ order.approved_by.staff.full_name }}</p>
              <p class="text-xs text-gray-500">{{ formatDate(order.updated_at) }}</p>
            </div>

            <div v-if="order.rejected_by">
              <span class="text-sm text-gray-600 dark:text-gray-400">Rejected By:</span>
              <p class="font-medium">{{ order.rejected_by.staff.full_name }}</p>
              <p class="text-xs text-gray-500">{{ formatDate(order.updated_at) }}</p>
            </div>

            <div v-if="order.given_by">
              <span class="text-sm text-gray-600 dark:text-gray-400">Completed By:</span>
              <p class="font-medium">{{ order.given_by.staff.full_name }}</p>
              <p class="text-xs text-gray-500">{{ formatDate(order.updated_at) }}</p>
            </div>

            <div>
              <span class="text-sm text-gray-600 dark:text-gray-400">Created By:</span>
              <p class="font-medium">{{ order.created_by.staff.full_name || 'System' }}</p>
              <p class="text-xs text-gray-500">{{ formatDate(order.created_at) }}</p>
            </div>
          </div>
        </div>

        <!-- Completion Notice -->
        <div v-if="order.payment_type !== 'instant' && order.remaining_balance === 0 && order.status !== 'completed'"
             class="bg-green-50 dark:bg-green-900 p-4 rounded-lg border border-green-200 dark:border-green-700">
          <h3 class="font-semibold text-green-800 dark:text-green-200 mb-2">Ready for Completion</h3>
          <p class="text-sm text-green-700 dark:text-green-300">
            All payments have been received. You can now mark this order as completed.
          </p>
          <button
            @click="completeOrder"
            class="mt-2 px-3 py-1 bg-green-600 text-white text-sm rounded hover:bg-green-700"
          >
            Mark as Completed
          </button>
        </div>
      </div>
    </div>
  </div>
</template>
