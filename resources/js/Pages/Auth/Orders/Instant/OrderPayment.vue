<script setup>
import { usePage } from '@inertiajs/vue3';

const props = defineProps({
  order: Object
});

// Get CSRF token for form submission
const csrfToken = usePage().props.csrf_token;

console.log(csrfToken)
</script>

<template>
  <div class="p-6 bg-white dark:bg-gray-900 rounded-lg shadow">
    <!-- Order details -->
    <!-- Payment button for instant orders -->
    <div v-if="order.payment_type === 'instant' && order.remaining_balance > 0" class="mt-6 p-4 rounded-lg">
      <h3 class="text-lg font-semibold mb-3">Instant Payment Required</h3>
      <p>Order Reference Number: {{ order.order_ref }}</p>
      <p class=" mb-4">This order requires immediate payment to be processed.</p>
      <form
        :action="route('order.payment.initiate', order.id)"
        method="POST"
        class="inline">
        <input type="hidden" name="_token" :value="csrfToken" />
        <button
          type="submit"
          class="px-6 py-3 bg-green-500 text-white rounded-lg hover:bg-green-600 transition"
        >
          Pay Now via Flutterwave
        </button>
      </form>
    </div>
  </div>
</template>

