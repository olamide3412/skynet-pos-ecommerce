<script setup>
import TextInput from '@/Components/Forms/TextInput.vue'
import SelectInput from '@/Components/Forms/SelectInput.vue'
import { useForm, usePage } from '@inertiajs/vue3'
import { ref, nextTick } from 'vue'
import { useToast } from 'vue-toastification'

const toast = useToast()
const emit = defineEmits(['change-active-tab'])

const genderEnums = usePage().props.enums.genders;
const roleEnums = usePage().props.enums.roles;
const stateEnums = usePage().props.enums.states;

const previewUrl = ref(null)
const activeSection = ref(1) // Track active section for step navigation
const sections = [
  { id: 1, title: "Personal Info", icon: "👤" },
  { id: 2, title: "Contact & Address", icon: "🏠" },
  { id: 3, title: "Employment Details", icon: "💼" },
  { id: 4, title: "Account Details", icon: "🔐" }
]

const form = useForm({
  // names
  last_name: '',
  first_name: '',
  other_name: '',
  staff_number: '',
  // contact
  email: '',
  phone_number: '',
  // biodata
  date_of_birth: '',
  gender: '',
  // address
  residential_address: '',
  local_government: '',
  state: '',
  // employment
  monthly_salary: '',
  nin: '',
  bvn: '',
  bank_account: '',
  bank_name: '',
  date_of_appointment: '',
  position: '',
  department: '',
  // account
  role: '',
  // file
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

// Function to scroll to top
const scrollToTop = () => {
  nextTick(() => {
    // Smooth scroll to top of the form container
    const formContainer = document.querySelector('.container-xl');
    if (formContainer) {
      formContainer.scrollIntoView({
        behavior: 'smooth',
        block: 'start'
      });
    }

    // Alternative: Scroll to very top of page
    // window.scrollTo({ top: 0, behavior: 'smooth' });
  });
}

// Navigation functions
const nextSection = () => {
  // Basic validation before proceeding
  if (activeSection.value === 1 && (!form.first_name || !form.last_name || !form.gender)) {
    toast.error('Please fill in all required personal information')
    return
  }
  if (activeSection.value === 2 && (!form.email || !form.phone_number)) {
    toast.error('Please fill in all required contact information')
    return
  }
  if (activeSection.value === 3 && (!form.position || !form.department)) {
    toast.error('Please fill in all required employment information')
    return
  }
  if (activeSection.value < sections.length) {
    activeSection.value++
     scrollToTop();
  }
}

const prevSection = () => {
  if (activeSection.value > 1) {
    activeSection.value--
     scrollToTop();
  }
}

const submit = () => {
  // Final validation
  if (!form.role) {
    toast.error('Please select a role for the staff member')
    return
  }

  form.post(route('staff.store'), {
    forceFormData: true,
    onError: () => {
      toast.error('Validation error. Please check the highlighted fields.')
    },
    onSuccess: () => {
      //toast.success('Staff member registered successfully!')
      if (previewUrl.value) URL.revokeObjectURL(previewUrl.value)
      previewUrl.value = null
      form.reset()
      activeSection.value = 1
      emit('change-active-tab')
    },
  })
}
</script>

<template>
  <div class="container-xl lg:container m-auto p-0">
    <h1 class="title dark:text-white mb-6">Register Staff</h1>

    <!-- Progress Indicator -->
    <div class="mb-6">
      <div class="flex justify-between items-center relative">
        <div class="absolute top-1/2 left-0 right-0 h-1 bg-gray-200 transform -translate-y-1/2 -z-10"></div>
        <div
          class="absolute top-1/2 left-0 h-1 bg-blue-500 transform -translate-y-1/2 -z-10 transition-all duration-300"
          :style="{ width: `${((activeSection - 1) / (sections.length - 1)) * 100}%` }"
        ></div>

        <div
          v-for="section in sections"
          :key="section.id"
          class="flex flex-col items-center relative"
          :class="activeSection >= section.id ? 'text-blue-600' : 'text-gray-500'"
        >
          <div
            class="w-8 h-8 rounded-full flex items-center justify-center border-2 text-sm"
            :class="{
              'bg-blue-500 text-white border-blue-500': activeSection >= section.id,
              'bg-white border-gray-300': activeSection < section.id
            }"
          >
            {{ section.icon }}
          </div>
          <span class="text-xs mt-1 font-medium hidden sm:block">
            {{ section.title }}
          </span>
        </div>
      </div>
    </div>

    <form @submit.prevent="submit">
      <!-- Section 1: Personal Information -->
      <div v-if="activeSection === 1" class="mb-6">
        <h2 class="text-md font-semibold mb-3 flex items-center">
          <span class="mr-2">👤</span> Personal Information
        </h2>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-3 p-3  rounded-lg">
          <TextInput
            name="last_name"
            label="Last Name"
            v-model="form.last_name"
            :message="form.errors.last_name"
            :required="true"
            placeholder="e.g. Musa"
          />
          <TextInput
            name="first_name"
            label="First Name"
            v-model="form.first_name"
            :message="form.errors.first_name"
            :required="true"
            placeholder="e.g. Aliyu"
          />
          <TextInput
            name="other_name"
            label="Other Name (optional)"
            v-model="form.other_name"
            :message="form.errors.other_name"
            placeholder="Single word only"
          />
          <TextInput
            name="staff_number"
            label="Staff Number (optional)"
            v-model="form.staff_number"
            :message="form.errors.staff_number"
            placeholder="e.g. STF001"
          />
          <SelectInput
            name="gender"
            label="Gender"
            v-model="form.gender"
            :options="genderEnums"
            :message="form.errors.gender"
            :required="true"
          />
          <TextInput
            name="date_of_birth"
            label="Date of Birth (optional)"
            type="date"
            v-model="form.date_of_birth"
            :message="form.errors.date_of_birth"
          />
        </div>
      </div>

      <!-- Section 2: Contact & Address -->
      <div v-if="activeSection === 2" class="mb-6">
        <h2 class="text-md font-semibold mb-3 flex items-center">
          <span class="mr-2">🏠</span> Contact & Address
        </h2>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-3 p-3  rounded-lg">
          <TextInput
            name="email"
            label="Email"
            type="email"
            v-model="form.email"
            :message="form.errors.email"
            placeholder="name@example.com"
            :required="true"
          />
          <TextInput
            name="phone_number"
            label="Phone Number"
            type="tel"
            inputmode="numeric"
            v-model="form.phone_number"
            :message="form.errors.phone_number"
            placeholder="11+ digits"
            :required="true"
          />
          <TextInput
            name="residential_address"
            label="Residential Address (optional)"
            v-model="form.residential_address"
            :message="form.errors.residential_address"
            class="md:col-span-2"
          />
          <TextInput
            name="local_government"
            label="Local Government (optional)"
            v-model="form.local_government"
            :message="form.errors.local_government"
          />
          <SelectInput
            name="state"
            label="State (optional)"
            v-model="form.state"
            :options="stateEnums"
            :message="form.errors.state"
          />
        </div>
      </div>

      <!-- Section 3: Employment Details -->
      <div v-if="activeSection === 3" class="mb-6">
        <h2 class="text-md font-semibold mb-3 flex items-center">
          <span class="mr-2">💼</span> Employment Details
        </h2>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-3 p-3  rounded-lg">
          <TextInput
            name="position"
            label="Position"
            v-model="form.position"
            :message="form.errors.position"
            :required="true"
            placeholder="e.g. Accountant"
          />
          <TextInput
            name="department"
            label="Department"
            v-model="form.department"
            :message="form.errors.department"
            :required="true"
            placeholder="e.g. Finance"
          />
          <TextInput
            name="date_of_appointment"
            label="Date of Appointment (optional)"
            type="date"
            v-model="form.date_of_appointment"
            :message="form.errors.date_of_appointment"
          />
          <TextInput
            name="monthly_salary"
            label="Monthly Salary (optional)"
            type="number"
            step="0.01"
            v-model="form.monthly_salary"
            :message="form.errors.monthly_salary"
            placeholder="e.g. 50000"
          />
          <TextInput
            name="nin"
            label="NIN (optional)"
            v-model="form.nin"
            :message="form.errors.nin"
            placeholder="11 digits"
          />
          <TextInput
            name="bvn"
            label="BVN (optional)"
            v-model="form.bvn"
            :message="form.errors.bvn"
            placeholder="11 digits"
          />
          <TextInput
            name="bank_account"
            label="Bank Account (optional)"
            v-model="form.bank_account"
            :message="form.errors.bank_account"
            placeholder="Account number"
          />
          <TextInput
            name="bank_name"
            label="Bank Name (optional)"
            v-model="form.bank_name"
            :message="form.errors.bank_name"
            placeholder="Bank name"
          />
        </div>
      </div>

      <!-- Section 4: Account Details & Photo -->
      <div v-if="activeSection === 4" class="mb-6">
        <h2 class="text-md font-semibold mb-3 flex items-center">
          <span class="mr-2">🔐</span> Account Details & Photo
        </h2>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-3 p-3  rounded-lg">
          <SelectInput
            name="role"
            label="Role"
            v-model="form.role"
            :options="roleEnums"
            :message="form.errors.role"
            :required="true"
            class="md:col-span-2"
          />

          <div class="md:col-span-2">
            <label class="text-sm font-medium block mb-2">Photo (PNG/JPG, ≤ 5MB)</label>
            <input
              type="file"
              accept="image/png,image/jpeg"
              @change="onPhotoChange"
              class="block w-full text-sm file:mr-4 file:py-2 file:px-4
                    file:rounded file:border-0 file:bg-gray-100 dark:file:bg-gray-800
                    file:text-gray-700 dark:file:text-gray-200 hover:file:bg-gray-200
                    dark:hover:file:bg-gray-700"
            />
            <p v-if="form.errors.photo" class="text-red-600 text-xs mt-1">{{ form.errors.photo }}</p>

            <div v-if="previewUrl" class="mt-3 flex flex-col items-center">
              <img :src="previewUrl" alt="Preview" class="h-24 w-24 rounded object-cover ring-2 ring-gray-300" />
              <p class="text-xs text-gray-500 mt-1">Image Preview</p>
            </div>
          </div>
        </div>
        <h3 class="text-md mb-3 flex items-center">
          <span class="mr-2">Default Staff Password:</span> Texas@123
        </h3>
      </div>

      <!-- Navigation Buttons -->
      <div class="flex justify-between pt-4">
        <button
          v-if="activeSection > 1"
          @click="prevSection"
          class="px-4 py-2 bg-gray-200 text-gray-700 rounded-lg hover:bg-gray-300 transition text-sm"
          type="button"
        >
          ← Previous
        </button>
        <div v-else></div> <!-- Empty div for spacing -->

        <button
          v-if="activeSection < sections.length"
          @click="nextSection"
          class="px-4 py-2 bg-blue-500 text-white rounded-lg hover:bg-blue-600 transition text-sm"
          type="button"
        >
          Next →
        </button>

        <button
          v-if="activeSection === sections.length"
          class="px-4 py-2 bg-green-500 text-white rounded-lg hover:bg-green-600 transition text-sm"
          :disabled="form.processing"
          type="submit"
        >
          <span v-if="form.processing">Registering...</span>
          <span v-else>Register Staff</span>
        </button>
      </div>
    </form>
  </div>
</template>

<style scoped>
.container-xl {
  max-width: 1200px;
}
</style>
