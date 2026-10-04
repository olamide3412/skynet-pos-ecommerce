<script setup>
import { computed } from 'vue'
import { Link } from '@inertiajs/vue3'
import { formatCurrency } from '../../../Utils/formatCurrency'

const props = defineProps({
  product: {
    type: Object,
    required: true
  },
  index: {
    type: Number,
    required: true
  },
  currentPage: {
    type: Number,
    required: true
  },
  perPage: {
    type: Number,
    required: true
  }
})

// Compute product serial number
const serial = computed(() => {
  return (props.currentPage - 1) * props.perPage + props.index + 1
})

// Default product image if photo not set
const productImage = computed(() => {
  return props.product.photo
    ? `/storage/${props.product.photo}`
    : '/images/default-product.png'
})
</script>

<template>
  <div class="bg-white dark:bg-gray-900 border rounded-lg p-4 mb-4 shadow-sm">
    <Link :href="route('product.show', product.id)" class="block">
      <div class="flex items-center space-x-4">
        <!-- Product Image -->
        <img
          :src="product.product_photo"
          alt="Product Image"
          class="w-16 h-16 object-cover rounded-lg border"
        />

        <!-- Product Info -->
        <div class="flex-1">
          <h3 class="font-bold text-lg">
            {{ serial }} -> {{ product.name }}
          </h3>
          <p class="text-sm text-gray-600 dark:text-gray-200">
            <strong>Price:</strong> ₦{{ formatCurrency(product.price) }}
          </p>
          <p class="text-sm text-gray-600 dark:text-gray-200">
            <strong>Description:</strong> {{ product.description ?? 'NIL' }}
          </p>
        </div>
      </div>
    </Link>

    <!-- Action Buttons -->
    <div class="mt-4 flex justify-end space-x-2">
      <Link :href="route('product.show', product.id)" class="rounded-element">
        <font-awesome-icon :icon="['fas', 'eye']" /> Show
      </Link>
    </div>
  </div>
</template>
