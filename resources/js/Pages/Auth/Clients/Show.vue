<script setup>
import SelectInput from '@/Components/Forms/SelectInput.vue';
import TextInput from '@/Components/Forms/TextInput.vue';
import { useForm, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import { useToast } from 'vue-toastification';
import { formatCurrency } from '../../../Utils/formatCurrency';

const activeTab = ref('show');
const toast = useToast();

// enums provided from server via Ziggy / Inertia props
const genderEnums = usePage().props.enums.genders;
const occupationEnums = usePage().props.enums.occupations;

const props = defineProps({
  client: {
    type: Object,
    required: true
  },
  isShowProfile: {
    type: Boolean,
    default: false
  }
});

// initialize form with client values
const previewUrl = ref(null);
const form = useForm({
  last_name: props.client.last_name ?? '',
  first_name: props.client.first_name ?? '',
  other_name: props.client.other_name ?? '',
  email: props.client.email ?? '',
  phone_number: props.client.phone_number ?? '',
  date_of_birth: props.client.date_of_birth ?? '',
  gender: props.client.gender ?? (genderEnums?.[0]?.value ?? ''),
  residential_address: props.client.residential_address ?? '',
  local_government: props.client.local_government ?? '',
  state: props.client.state ?? '',
  occupation: props.client.occupation ?? (occupationEnums?.[0]?.value ?? ''),
  nin: props.client.nin ?? '',
  bvn: props.client.bvn ?? '',
  annual_income: props.client.annual_income ?? '',
  photo: null, // file input (new upload)
});

const isEnable = computed(() => {
  // if you have a status field for clients, this will reflect it; otherwise false
  return props.client.verification_status === true;
});


function onPhotoChange(e) {
  const file = e.target.files?.[0];
  if (!file) return;
  form.photo = file;

  if (previewUrl.value) URL.revokeObjectURL(previewUrl.value);
  previewUrl.value = URL.createObjectURL(file);
}

const submit = () => {
  // Use forceFormData so file uploads work
  form.post(route('client.update', props.client.id), {
    forceFormData: true,
    onError: () => {
      toast.error('Validation error. Please check the fields.');
    },
    onSuccess: () => {
      //toast.success('Client updated successfully');
      // cleanup preview if any
      if (previewUrl.value) {
        URL.revokeObjectURL(previewUrl.value);
        previewUrl.value = null;
      }
    }
  });
};

const deleteClient = () => {
  if (!confirm('Are you sure you want to delete this client?')) return;

  form.delete(route('client.destroy', props.client.id), {
    onError: () => {
      toast.error('Error deleting client');
    },
    onSuccess: () => {
      //toast.success('Client deleted successfully');
    }
  });
};
</script>

<template>
  <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 py-12 p-4">

    <!-- SHOW TAB -->
    <div v-if="activeTab === 'show'">
      <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-md rounded-lg p-6">
        <div class="flex flex-col sm:flex-row md:flex-row items-start gap-4">
          <div class="w-24 h-24 rounded overflow-hidden bg-gray-100">
            <!-- try showing existing photo from storage, fallback to placeholder -->
            <img
              v-if="previewUrl"
              :src="previewUrl"
              alt="Photo"
              class="w-full h-full object-cover"
            />
            <img
              v-else-if="props.client.photo"
              :src="props.client.client_photo"
              alt="Photo"
              class="w-full h-full object-cover"
            />
            <div v-else class="w-full h-full flex items-center justify-center text-gray-400">No Photo</div>
          </div>

          <div>
            <div class="flex items-center space-x-3">
              <h1 class="text-2xl font-semibold">{{ props.client.full_name ?? (props.client.first_name + ' ' + props.client.last_name) }}</h1>
              <span
                class="rounded-xl p-1 text-xs w-fit"
                :class="isEnable ? 'bg-green-200 dark:bg-green-500' : 'bg-red-200 dark:bg-red-500'"
              >
                {{ isEnable ? 'Enabled' : (props.client.status ?? 'N/A') }}
              </span>
            </div>

            <p class="text-gray-600 dark:text-gray-100">Phone: {{ props.client.phone_number ?? 'Nill' }}</p>
            <p class="text-gray-600 dark:text-gray-100">Email: {{ props.client.email ?? 'Nill' }}</p>
            <p class="text-gray-600 dark:text-gray-100">Occupation: {{ props.client.occupation ?? 'Nill' }}</p>
          </div>
        </div>
      </div>

      <div class="py-3">
        <h3 class="text-2xl font-bold mb-3">Other Details</h3>
        <div class="bg-white dark:bg-gray-800 p-6 rounded-lg shadow-md">
          <p><strong>NIN:</strong> {{ props.client.nin ?? 'Nill' }}</p>
          <p><strong>BVN:</strong> {{ props.client.bvn ?? 'Nill' }}</p>
          <p><strong>Residential Address:</strong> {{ props.client.residential_address ?? 'Nill' }}</p>
          <p><strong>Local Government:</strong> {{ props.client.local_government ?? 'Nill' }}</p>
          <p><strong>State:</strong> {{ props.client.state ?? 'Nill' }}</p>
          <p><strong>Annual Income:</strong> {{ formatCurrency(props.client.annual_income ?? '0.00') }}</p>

          <p class="mt-3"><strong>Created On:</strong> {{ $formatDate(props.client.created_at) }}</p>
          <p><strong>Last Updated On:</strong> {{ $formatDate(props.client.updated_at) }}</p>

          <div class="mt-4 flex flex-wrap gap-2">
            <button @click="activeTab = 'edit'" class="rounded-element">Edit</button>
            <button @click="deleteClient" class="rounded-element bg-red-500 hover:bg-red-700">Delete</button>
          </div>
        </div>
      </div>
    </div>

    <!-- EDIT TAB -->
    <div v-if="activeTab === 'edit'">
      <div class="bg-white dark:bg-gray-800 shadow p-4 rounded-md">
        <h1 class="title dark:text-white">Modify Client</h1>

        <form @submit.prevent="submit">
          <div class="grid grid-cols-1 md:grid-cols-2 gap-3">

            <!-- Names -->
            <TextInput name="first_name" label="First Name" v-model="form.first_name" :message="form.errors.first_name" :required="true" />
            <TextInput name="last_name" label="Last Name" v-model="form.last_name" :message="form.errors.last_name" :required="true" />
            <TextInput name="other_name" label="Other Name" v-model="form.other_name" :message="form.errors.other_name" />

            <!-- Contact & biodata -->
            <TextInput name="email" label="Email" type="email" v-model="form.email" :message="form.errors.email" />
            <TextInput name="phone_number" label="Phone Number" type="tel" v-model="form.phone_number" :message="form.errors.phone_number" />
            <TextInput name="date_of_birth" label="Date of Birth" type="date" v-model="form.date_of_birth" :message="form.errors.date_of_birth" :required="true"/>

            <SelectInput
              name="gender"
              label="Gender"
              v-model="form.gender"
              :options="genderEnums"
              :message="form.errors.gender"
              :required="true"
            />

            <!-- Address -->
            <TextInput name="residential_address" label="Residential Address" v-model="form.residential_address" :message="form.errors.residential_address" />
            <TextInput name="local_government" label="Local Government" v-model="form.local_government" :message="form.errors.local_government" />
            <TextInput name="state" label="State" v-model="form.state" :message="form.errors.state" />

            <SelectInput
              name="occupation"
              label="Occupation"
              v-model="form.occupation"
              :options="occupationEnums"
              :message="form.errors.occupation"
            />

            <!-- IDs & finance -->
            <TextInput name="nin" label="NIN" v-model="form.nin" :message="form.errors.nin" />
            <TextInput name="bvn" label="BVN" v-model="form.bvn" :message="form.errors.bvn" />
            <TextInput name="annual_income" label="Annual Income" v-model="form.annual_income" :message="form.errors.annual_income" />

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

              <div v-if="previewUrl || props.client.photo" class="mt-2">
                <img
                  :src="previewUrl ? previewUrl : props.client.client_photo"
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
