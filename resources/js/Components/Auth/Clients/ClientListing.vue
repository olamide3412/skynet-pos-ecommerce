<script setup>
import { computed } from 'vue'


const props = defineProps({
  client: {
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
});


const serial = computed(() => {
  return (props.currentPage - 1) * props.perPage + props.index + 1
})

const isVerified = computed(() => props.client.verification_status === true);


</script>

<template>
    <div>

        <div class="bg-white dark:bg-gray-900 border rounded-lg p-4 mb-4 shadow-sm">
            <Link :href="route('client.show', client.id)" class=" block">

                <div class="flex justify-between items-center">
                    <h3 class="font-bold text-lg">
                    {{ serial }} -> {{ client.full_name }}
                    <span
                        class="rounded-xl p-1 text-xs"
                        :class="isVerified ? 'bg-green-200 dark:bg-green-500' : 'bg-red-200 dark:bg-red-500'"
                    >
                        <font-awesome-icon :icon="isVerified ? ['fas', 'certificate'] : ['fas', 'xmark'] "/>
                        {{ isVerified ? 'Verified' : 'Not Verified' }}
                    </span>
                    </h3>
                </div>

                <p class="text-sm text-gray-600 dark:text-gray-200"><strong>Phone:</strong> {{ client.phone_number ?? 'NIL' }}</p>
                <p class="text-sm text-gray-600 dark:text-gray-200"><strong>Occupation: </strong>{{ client.occupation  ?? 'NIL' }}</p>
            </Link>
            <div class="mt-4 flex justify-end space-x-2">

                <Link :href="route('client.show', client.id)" class="rounded-element">
                    <font-awesome-icon :icon="['fas', 'eye']"/>  Show
                </Link>
                <Link :href="route('order.create', client.id)" class="rounded-element">
                    <font-awesome-icon :icon="['fas', 'edit']"/>  Order
                </Link>

            </div>
        </div>


  </div>

</template>
