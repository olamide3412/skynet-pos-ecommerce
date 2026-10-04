<script setup>
import TextInput from '@/Components/Forms/TextInput.vue'
import { useForm } from '@inertiajs/vue3'
import { ref } from 'vue'
import { useToast } from 'vue-toastification'

const toast = useToast()
const emit = defineEmits(['change-active-tab'])

const previewUrl = ref(null)

const form = useForm({
  name: '',
  description: '',
  price: '',
  photo: null,
})

function onPhotoChange(e) {
  const file = e.target.files?.[0]
  if (!file) return
  form.photo = file
  // preview
  if (previewUrl.value) URL.revokeObjectURL(previewUrl.value)
  previewUrl.value = URL.createObjectURL(file)
}

const submit = () => {
  form.post(route('product.store'), {
    forceFormData: true, // required for file upload
    onError: () => {
      toast.error('Validation error. Please check the highlighted fields.')
    },
    onSuccess: () => {
      //toast.success('Product added successfully!')
      if (previewUrl.value) URL.revokeObjectURL(previewUrl.value)
      previewUrl.value = null
      form.reset()
      emit('change-active-tab')
    },
  })
}
</script>

<template>
  <div class="container-xl lg:container m-auto p-0">
    <h1 class="title dark:text-white">Add Product</h1>

    <form @submit.prevent="submit">
      <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
        <!-- Product Name -->
        <TextInput
          name="name"
          label="Product Name"
          v-model="form.name"
          :message="form.errors.name"
          :required="true"
          placeholder="e.g. iPhone 15"
        />

        <!-- Price -->
        <TextInput
          name="price"
          label="Price"
          v-model="form.price"
          inputmode="decimal"
          :message="form.errors.price"
          :required="true"
          placeholder="e.g. 499.99"
        />

      </div>
        <!-- Description -->
        <div class="col-span-2 flex flex-col gap-1">
            <label class="text-sm font-medium">Description (optional)</label>
            <textarea
            v-model="form.description"
            rows="3"
            class="rounded-md border-gray-300 dark:border-gray-600
                    dark:bg-gray-800 dark:text-gray-100 text-sm w-full"
            placeholder="Short description about the product..."></textarea>
            <p v-if="form.errors.description" class="text-red-600 text-xs">{{ form.errors.description }}</p>
        </div>

        <!-- Photo -->
        <div class="col-span-2 flex flex-col gap-2">
            <label class="text-sm font-medium">Photo (PNG/JPG, ≤ 5MB)</label>
            <input
            type="file"
            accept="image/png,image/jpeg"
            @change="onPhotoChange"
            class="block w-full text-sm file:mr-4 file:py-2 file:px-4
                    file:rounded file:border-0 file:bg-gray-100 dark:file:bg-gray-800
                    file:text-gray-700 dark:file:text-gray-200 hover:file:bg-gray-200
                    dark:hover:file:bg-gray-700"
            />
            <p v-if="form.errors.photo" class="text-red-600 text-xs">{{ form.errors.photo }}</p>

            <div v-if="previewUrl" class="mt-2">
            <img :src="previewUrl" alt="Preview" class="h-24 w-24 rounded object-cover ring-1 ring-gray-200 dark:ring-gray-700" />
            </div>
        </div>

      <div class="flex justify-center py-6">
        <button class="btn-primary" :disabled="form.processing">
          <span v-if="form.processing">Saving...</span>
          <span v-else>Add Product</span>
        </button>
      </div>

    </form>
  </div>
</template>
