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
const roleEnums = usePage().props.enums.roles;
const statusEnums = usePage().props.enums.statuses;
const stateEnums = usePage().props.enums.states;

const props = defineProps({
  staff: {
    type: Object,
    required: true
  }
});

// initialize form with staff values
const previewUrl = ref(null);
const form = useForm({
  last_name: props.staff.last_name ?? '',
  first_name: props.staff.first_name ?? '',
  other_name: props.staff.other_name ?? '',
  staff_number: props.staff.staff_number ?? '',
  email: props.staff.email ?? '',
  phone_number: props.staff.phone_number ?? '',
  date_of_birth: props.staff.date_of_birth ?? '',
  gender: props.staff.gender ?? (genderEnums?.[0]?.value ?? ''),
  residential_address: props.staff.residential_address ?? '',
  local_government: props.staff.local_government ?? '',
  state: props.staff.state ?? '',
  monthly_salary: props.staff.monthly_salary ?? '',
  nin: props.staff.nin ?? '',
  bvn: props.staff.bvn ?? '',
  bank_account: props.staff.bank_account ?? '',
  bank_name: props.staff.bank_name ?? '',
  date_of_appointment: props.staff.date_of_appointment ?? '',
  position: props.staff.position ?? '',
  department: props.staff.department ?? '',
  role: props.staff.user?.role ?? (roleEnums?.[0]?.value ?? ''),
  status: props.staff.user?.status ?? (statusEnums?.[0]?.value ?? ''),
  photo: null, // file input (new upload)
});

const isEnable = computed(() => {
  return props.staff.user?.status === statusEnums?.[0].value;
});

//console.log(statusEnums[0]);

function onPhotoChange(e) {
  const file = e.target.files?.[0];
  if (!file) return;
  form.photo = file;

  if (previewUrl.value) URL.revokeObjectURL(previewUrl.value);
  previewUrl.value = URL.createObjectURL(file);
}

const submit = () => {
  form.post(route('staff.update', props.staff.id), {
    forceFormData: true,
    onError: () => {
      toast.error('Validation error. Please check the fields.');
    },
    onSuccess: () => {
      //toast.success('Staff updated successfully');
      if (previewUrl.value) {
        URL.revokeObjectURL(previewUrl.value);
        previewUrl.value = null;
      }
    }
  });
};

const deleteStaff = () => {
  if (!confirm('Are you sure you want to delete this staff member?')) return;

  form.delete(route('staff.destroy', props.staff.id), {
    onError: () => {
      toast.error('Error deleting staff member');
    },
    onSuccess: () => {
      //toast.success('Staff member deleted successfully');
    }
  });
};

const resetPasswordForm = useForm({
    password: null,
    password_confirmation: null,
});

const resetPassword = (route_name) => {
    //router.post('register',form)
    resetPasswordForm.post(route('staff.resetPassword', props.staff.user),{
        onError: () => {
            toast.error('Validation error');
        },
        onSuccess: () => {
            //toast.success('User password updated succesfully');
        }
    });
}


</script>

<template>
  <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 py-12 p-4">

    <!-- SHOW TAB -->
    <div v-if="activeTab === 'show'">
      <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-md rounded-lg p-6">
        <div class="flex flex-col sm:flex-row md:flex-row items-start gap-4">
          <div class="w-24 h-24 rounded overflow-hidden bg-gray-100">
            <img
              v-if="previewUrl"
              :src="previewUrl"
              alt="Photo"
              class="w-full h-full object-cover"
            />
            <img
              v-else-if="props.staff.photo"
              :src="props.staff.staff_photo"
              alt="Photo"
              class="w-full h-full object-cover"
            />
            <div v-else class="w-full h-full flex items-center justify-center text-gray-400">No Photo</div>
          </div>

          <div>
            <div class="flex items-center space-x-3">
              <h1 class="text-2xl font-semibold">{{ props.staff.full_name }}</h1>
              <span
                class="rounded-xl p-1 text-xs w-fit"
                :class="isEnable ? 'bg-green-200 dark:bg-green-500' : 'bg-red-200 dark:bg-red-500'"
              >
                {{ isEnable ? 'Enabled' : 'Disabled' }}
              </span>
            </div>

            <p class="text-gray-600 dark:text-gray-100">Staff ID: {{ props.staff.staff_number ?? 'N/A' }}</p>
            <p class="text-gray-600 dark:text-gray-100">Phone: {{ props.staff.phone_number ?? 'N/A' }}</p>
            <p class="text-gray-600 dark:text-gray-100">Email: {{ props.staff.email ?? 'N/A' }}</p>
            <p class="text-gray-600 dark:text-gray-100">Position: {{ props.staff.position ?? 'N/A' }}</p>
            <p class="text-gray-600 dark:text-gray-100">Department: {{ props.staff.department ?? 'N/A' }}</p>
          </div>
        </div>
      </div>

      <div class="py-3">
        <h3 class="text-2xl font-bold mb-3">Staff Details</h3>
        <div class="bg-white dark:bg-gray-800 p-6 rounded-lg shadow-md">
          <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <p><strong>Gender:</strong> {{ props.staff.gender ?? 'N/A' }}</p>
            <p><strong>Date of Birth:</strong> {{ $formatDate(props.staff.date_of_birth) ?? 'N/A' }}</p>
            <p><strong>NIN:</strong> {{ props.staff.nin ?? 'N/A' }}</p>
            <p><strong>BVN:</strong> {{ props.staff.bvn ?? 'N/A' }}</p>
            <p><strong>Monthly Salary:</strong> {{ formatCurrency(props.staff.monthly_salary ?? '0.00') }}</p>
            <p><strong>Date of Appointment:</strong> {{ $formatDate(props.staff.date_of_appointment) ?? 'N/A' }}</p>
          </div>

          <p class="mt-4"><strong>Residential Address:</strong> {{ props.staff.residential_address ?? 'N/A' }}</p>
          <p><strong>Local Government:</strong> {{ props.staff.local_government ?? 'N/A' }}</p>
          <p><strong>State:</strong> {{ props.staff.state ?? 'N/A' }}</p>

          <div class="mt-4 grid grid-cols-1 md:grid-cols-2 gap-4">
            <p><strong>Bank Account:</strong> {{ props.staff.bank_account ?? 'N/A' }}</p>
            <p><strong>Bank Name:</strong> {{ props.staff.bank_name ?? 'N/A' }}</p>
          </div>

          <div class="mt-4 grid grid-cols-1 md:grid-cols-2 gap-4">
            <p><strong>Role:</strong> {{ props.staff.user?.role ?? 'N/A' }}</p>
            <p><strong>Status:</strong> {{ props.staff.user?.status ?? 'N/A' }}</p>
          </div>

          <p class="mt-3"><strong>Created On:</strong> {{ $formatDate(props.staff.created_at) }}</p>
          <p><strong>Last Updated On:</strong> {{ $formatDate(props.staff.updated_at) }}</p>

          <div class="mt-4 flex flex-wrap gap-2">
            <button @click="activeTab = 'edit'" class="rounded-element">Edit</button>
            <button @click="activeTab = 'reset_password'" class="rounded-element">Reset Password</button>
            <button @click="deleteStaff" class="rounded-element bg-red-500 hover:bg-red-700">Delete</button>
          </div>
        </div>
      </div>
    </div>

    <!-- EDIT TAB -->
    <div v-if="activeTab === 'edit'">
      <div class="bg-white dark:bg-gray-800 shadow p-4 rounded-md">
        <h1 class="title dark:text-white">Modify Staff</h1>

        <form @submit.prevent="submit">
          <div class="grid grid-cols-1 md:grid-cols-2 gap-3">

            <!-- Names -->
            <TextInput name="first_name" label="First Name" v-model="form.first_name" :message="form.errors.first_name" :required="true" />
            <TextInput name="last_name" label="Last Name" v-model="form.last_name" :message="form.errors.last_name" :required="true" />
            <TextInput name="other_name" label="Other Name" v-model="form.other_name" :message="form.errors.other_name" />
            <TextInput name="staff_number" label="Staff Number" v-model="form.staff_number" :message="form.errors.staff_number" />

            <!-- Contact & biodata -->
            <TextInput name="email" label="Email" type="email" v-model="form.email" :message="form.errors.email" :required="true" />
            <TextInput name="phone_number" label="Phone Number" type="tel" v-model="form.phone_number" :message="form.errors.phone_number" />
            <TextInput name="date_of_birth" label="Date of Birth" type="date" v-model="form.date_of_birth" :message="form.errors.date_of_birth" />

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

            <SelectInput
              name="state"
              label="State"
              v-model="form.state"
              :options="stateEnums"
              :message="form.errors.state"
            />

            <!-- Salary and Bank Details -->
            <TextInput name="monthly_salary" label="Monthly Salary" type="number" step="0.01" v-model="form.monthly_salary" :message="form.errors.monthly_salary" />
            <TextInput name="nin" label="NIN" v-model="form.nin" :message="form.errors.nin" />
            <TextInput name="bvn" label="BVN" v-model="form.bvn" :message="form.errors.bvn" />
            <TextInput name="bank_account" label="Bank Account" v-model="form.bank_account" :message="form.errors.bank_account" />
            <TextInput name="bank_name" label="Bank Name" v-model="form.bank_name" :message="form.errors.bank_name" />

            <!-- Employment Details -->
            <TextInput name="date_of_appointment" label="Date of Appointment" type="date" v-model="form.date_of_appointment" :message="form.errors.date_of_appointment" />
            <TextInput name="position" label="Position" v-model="form.position" :message="form.errors.position" />
            <TextInput name="department" label="Department" v-model="form.department" :message="form.errors.department" />

            <!-- User Account Details -->
            <SelectInput
              name="role"
              label="Role"
              v-model="form.role"
              :options="roleEnums"
              :message="form.errors.role"
              :required="true"
            />

            <SelectInput
              name="status"
              label="Status"
              v-model="form.status"
              :options="statusEnums"
              :message="form.errors.status"
              :required="true"
            />

            <!-- Photo -->
            <div class="flex flex-col gap-2 md:col-span-2">
              <label class="text-sm font-medium">Photo (PNG/JPG, ≤ 5MB)</label>
              <input
                type="file"
                name="photo"
                accept="image/png,image/jpeg"
                @change="onPhotoChange"
                class="block w-full text-sm file:mr-4 file:py-2 file:px-4 file:rounded file:border-0 file:bg-gray-100 dark:file:bg-gray-800 file:text-gray-700 dark:file:text-gray-200 hover:file:bg-gray-200 dark:hover:file:bg-gray-700"
              />
              <p v-if="form.errors.photo" class="text-red-600 text-xs">{{ form.errors.photo }}</p>

              <div v-if="previewUrl || props.staff.photo" class="mt-2">
                <img
                  :src="previewUrl ? previewUrl : props.staff.staff_photo"
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

        <!--Reset Password View-->
     <div v-if="activeTab == 'reset_password'">
      <div class="bg-white dark:bg-gray-800 shadow p-4 rounded-md">
        <!-- Add your edit form here -->
        <h1 class="title dark:text-white">Reset Password</h1>
        <form @submit.prevent="resetPassword">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                <TextInput name="password" label="Password"  type="password" v-model="resetPasswordForm.password"
                            :message="resetPasswordForm.errors.password"  :required="true"/>
                <TextInput name="password" label="Password Confirmation"  type="password" v-model="resetPasswordForm.password_confirmation"
                            :message="resetPasswordForm.errors.password_confirmation" :required="true"/>
            </div>
            <div class=" flex justify-center py-4 space-x-5">
                <button class="btn-primary" :disabled="form.processing">Reset</button>
                <button class="btn-primary-red" type="button" @click="activeTab = 'show'">Cancel</button>
            </div>

        </form>
      </div>
    </div>

  </div>
</template>
