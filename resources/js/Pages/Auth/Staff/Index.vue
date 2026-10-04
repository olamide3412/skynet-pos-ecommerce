<script setup>
import AddStaff from '@/Components/Auth/Staff/AddStaff.vue';
import StaffListings from '@/Components/Auth/Staff/StaffListings.vue';
import { ref } from 'vue';

const props = defineProps({
    staff: Object
})

const activeTab = ref('listings');
const handleChangeActiveTab = (tabName = 'listings') => {
    activeTab.value = tabName;
}
</script>
<template>
  <Head :title=" ` | ${$page.component}` "/>
  <div class="container-xl lg:container m-auto  p-5">
    <!-- Tab Buttons -->
    <div class="flex space-x-2 mb-4">
      <button
        :class="[
          'w-fit py-2 px-4 text-sm font-medium rounded-full transition-colors',
          activeTab === 'listings'
            ? 'bg-secondary-400 text-white'
            : 'bg-gray-200 text-gray-700 hover:bg-gray-300'
        ]"
        @click="activeTab = 'listings'"
      >
        Staff
      </button>
      <button
        :class="[
          'w-fit py-2 px-4 text-sm font-medium rounded-full transition-colors',
          activeTab === 'add'
            ? 'bg-secondary-500 text-white'
            : 'bg-gray-200 text-gray-700 hover:bg-gray-300'
        ]"
        @click="activeTab = 'add'"
      >
        Add Staff
      </button>
    </div>

    <!-- Tab Content -->
    <div class="p-1  rounded-lg shadow-inner">
      <StaffListings v-if="activeTab === 'listings'" :staff="staff"/>
      <AddStaff v-if="activeTab === 'add'"  @change-active-tab="handleChangeActiveTab"/>
    </div>
  </div>
</template>
