<script setup>
import TextInput from '@/Components/Forms/TextInput.vue';
import { useForm } from '@inertiajs/vue3';
import { ref, computed } from 'vue';
import { useToast } from 'vue-toastification';
import { formatCurrency } from '../../../Utils/formatCurrency';

const activeTab = ref('show');
const toast = useToast();

const props = defineProps({
  product: {
    type: Object,
    required: true
  }
});

const previewUrl = ref(null);

const form = useForm({
  name: props.product.name ?? '',
  description: props.product.description ?? '',
  price: props.product.price ?? '',
  photo: null, // for new upload :src="`/storage/${props.product.photo}`"
});

const hasPhoto = computed(() => !!props.product.photo || !!previewUrl.value);

function onPhotoChange(e) {
  const file = e.target.files?.[0];
  if (!file) return;

  form.photo = file;

  if (previewUrl.value) URL.revokeObjectURL(previewUrl.value);
  previewUrl.value = URL.createObjectURL(file);
}

const submit = () => {
  form.post(route('product.update', props.product.id), {
    forceFormData: true,
    preserveScroll: true,
    data: {
      _method: 'PUT', // 👈 Laravel will now treat this as PUT
    },
    onError: () => {
      toast.error('Validation error. Please check the fields.');
    },
    onSuccess: () => {
      //toast.success('Product updated successfully');
      if (previewUrl.value) {
        URL.revokeObjectURL(previewUrl.value);
        previewUrl.value = null;
      }
      activeTab.value = 'show';
    }

  });
};


const deleteProduct = () => {
  if (!confirm('Are you sure you want to delete this product?')) return;

  form.delete(route('product.destroy', props.product.id), {
    onError: () => {
      toast.error('Error deleting product');
    },
    onSuccess: () => {
      //toast.success('Product deleted successfully');
    }
  });
};
</script>

<template>
  <div class="max-w-5xl mx-auto sm:px-6 lg:px-8 py-12 p-4">

    <!-- SHOW TAB -->
    <div v-if="activeTab === 'show'">
      <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-md rounded-lg p-6">
        <div class="flex flex-col sm:flex-row md:flex-row items-start gap-4">
          <div class="w-28 h-28 rounded overflow-hidden bg-gray-100">
            <img
              v-if="previewUrl"
              :src="previewUrl"
              alt="Photo"
              class="w-full h-full object-cover"
            />
            <img
              v-else-if="props.product.photo"
              :src="props.product.product_photo"
              alt="Photo"
              class="w-full h-full object-cover"
            />
            <div v-else class="w-full h-full flex items-center justify-center text-gray-400">No Photo</div>
          </div>

          <div>
            <h1 class="text-2xl font-semibold">{{ props.product.name }}</h1>
            <p class="text-gray-600 dark:text-gray-100">Price: ₦{{ formatCurrency(props.product.price)  }}</p>
            <p class="text-gray-600 dark:text-gray-100">Description: {{ props.product.description ?? 'N/A' }}</p>
          </div>
        </div>
      </div>

      <div class="py-3">
        <div class="bg-white dark:bg-gray-800 p-6 rounded-lg shadow-md">
          <p><strong>Created On:</strong> {{ $formatDate(props.product.created_at) }}</p>
          <p><strong>Last Updated On:</strong> {{ $formatDate(props.product.updated_at) }}</p>

          <div class="mt-4 flex flex-wrap gap-2">
            <button @click="activeTab = 'edit'" class="rounded-element">Edit</button>
            <button @click="deleteProduct" class="rounded-element bg-red-500 hover:bg-red-700">Delete</button>
          </div>
        </div>
      </div>
    </div>

    <!-- EDIT TAB -->
    <div v-if="activeTab === 'edit'">
      <div class="bg-white dark:bg-gray-800 shadow p-4 rounded-md">
        <h1 class="title dark:text-white">Modify Product</h1>

        <form @submit.prevent="submit">
          <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
            <TextInput name="name" label="Product Name" v-model="form.name" :message="form.errors.name" required />
            <TextInput name="price" label="Price" type="number" step="0.01" v-model="form.price" :message="form.errors.price" required />

            <div class="md:col-span-2">
              <label class="text-sm font-medium">Description</label>
              <textarea
                v-model="form.description"
                class="w-full rounded border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-200"
                rows="4"
              ></textarea>
              <p v-if="form.errors.description" class="text-red-600 text-xs">{{ form.errors.description }}</p>
            </div>

            <!-- Photo -->
            <div class="flex flex-col gap-2">
              <label class="text-sm font-medium">Photo (PNG/JPG, ≤ 5MB)</label>
              <input
                type="file"
                name="photo"
                accept="image/png,image/jpeg"
                @change="onPhotoChange"
                class="block w-full text-sm file:mr-4 file:py-2 file:px-4 file:rounded file:border-0 file:bg-gray-100 dark:file:bg-gray-800 file:text-gray-700 dark:file:text-gray-200 hover:file:bg-gray-200 dark:hover:file:bg-gray-700"
              />
              <p v-if="form.errors.photo" class="text-red-600 text-xs">{{ form.errors.photo }}</p>

              <div v-if="hasPhoto" class="mt-2">
                <img
                  :src="previewUrl ? previewUrl : props.product.product_photo"
                  alt="Preview"
                  class="h-24 w-24 rounded object-cover ring-1 ring-gray-200 dark:ring-gray-700"
                />
              </div>
            </div>
          </div>

          <div class="flex justify-center py-4 space-x-5">
            <button class="btn-primary" :disabled="form.processing">Update</button>
            <button class="btn-primary-red" type="button" @click="activeTab = 'show'">Cancel</button>
          </div>
        </form>
      </div>
    </div>

  </div>
</template>
