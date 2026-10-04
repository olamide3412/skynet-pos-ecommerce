<script setup>
import { computed } from 'vue'

const props = defineProps({
  staff: {
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

const isEnabled = computed(() => props.staff.user?.status === 'enable');

</script>

<template>
    <div>
        <div class="bg-white dark:bg-gray-900 border rounded-lg p-4 mb-4 shadow-sm">
            <Link :href="route('staff.show', staff.id)" class=" block">
                <div class="flex justify-between items-center">
                    <h3 class="font-bold text-lg">
                    {{ serial }} -> {{ staff.full_name }}
                    <span
                        class="rounded-xl p-1 text-xs"
                        :class="isEnabled ? 'bg-green-200 dark:bg-green-500' : 'bg-red-200 dark:bg-red-500'"
                    >
                        {{ isEnabled ? 'Enabled' : 'Disabled' }}
                    </span>
                    </h3>
                </div>

                <p class="text-sm text-gray-600 dark:text-gray-200"><strong>Staff ID:</strong> {{ staff.staff_number ?? 'N/A' }}</p>
                <p class="text-sm text-gray-600 dark:text-gray-200"><strong>Email:</strong> {{ staff.email ?? 'N/A' }}</p>
                <p class="text-sm text-gray-600 dark:text-gray-200"><strong>Position: </strong>{{ staff.position ?? 'N/A' }}</p>
                <p class="text-sm text-gray-600 dark:text-gray-200"><strong>Department: </strong>{{ staff.department ?? 'N/A' }}</p>
            </Link>
            <div class="mt-4 flex justify-end space-x-2">
                <Link :href="route('staff.show', staff.id)" class="rounded-element">
                    <font-awesome-icon :icon="['fas', 'eye']"/>  Show
                </Link>
            </div>
        </div>
  </div>
</template>
