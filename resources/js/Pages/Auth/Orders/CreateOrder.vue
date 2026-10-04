<script setup>
import { reactive, ref, computed, onMounted, watch } from "vue";
import { useForm, router } from "@inertiajs/vue3";
import TextInput from '@/Components/Forms/TextInput.vue';
import SelectInput from '@/Components/Forms/SelectInput.vue';
import { useToast } from 'vue-toastification'
import { formatCurrency } from '../../../Utils/formatCurrency';

const props = defineProps({
  client: Object,       // passed from controller (the client profile)
  products: Array       // passed from controller (all available products)
});

// Payment types from enum (sync with backend)
const paymentTypes = [
  { value: "instant", label: "Instant" },
  { value: "installment", label: "Installment" },
  { value: "on-site-collection", label: "On-Site Collection" }
];

const frequencies = [
  { value: "monthly", label: "Monthly" },
  { value: "weekly", label: "Weekly" }
];

const relationships = [
  "Brother", "Sister", "Father", "Mother", "Uncle", "Aunt",
  "Cousin", "Friend", "Colleague", "Spouse", "Other"
];

const toast = useToast();

// UI State
const activeStep = ref(1);
const steps = ref([]); // We'll dynamically set steps based on payment type

// Cart state
const cart = ref([]);
const searchQuery = ref('');

// Guarantors management
const guarantors = ref([]);

// Dynamically update steps based on payment type
const updateSteps = () => {
  const baseSteps = [
    { id: 1, title: "Products", icon: "🛒" },
    { id: 2, title: "Payment", icon: "💳" },
    { id: 4, title: "Review", icon: "📋" }
  ];

  // Only include employment step if payment is not instant
  if (form.payment_type !== 'instant') {
    baseSteps.splice(2, 0, { id: 3, title: "Employment", icon: "💼" });
  }

  steps.value = baseSteps;
};

// Filter products based on search
const filteredProducts = computed(() => {
  if (!searchQuery.value) return props.products;
  return props.products.filter(product =>
    product.name.toLowerCase().includes(searchQuery.value.toLowerCase())
  );
});

// Add product to cart
const addToCart = (product) => {
  const existingItem = cart.value.find(item => item.id === product.id);
  if (existingItem) {
    existingItem.quantity += 1;
  } else {
    cart.value.push({
      id: product.id,
      name: product.name,
      price: product.price,
      quantity: 1
    });
  }
  toast.success(`${product.name} added to cart`);
};

// Remove product from cart
const removeFromCart = (productId) => {
  const index = cart.value.findIndex(item => item.id === productId);
  if (index !== -1) {
    cart.value.splice(index, 1);
    toast.info('Product removed from cart');
  }
};

// Update quantity in cart
const updateQuantity = (productId, newQuantity) => {
  const item = cart.value.find(item => item.id === productId);
  if (item) {
    if (newQuantity <= 0) {
      removeFromCart(productId);
    } else {
      item.quantity = newQuantity;
    }
  }
};

// Add a new guarantor
const addGuarantor = () => {
  guarantors.value.push({
    name: '',
    phone: '',
    bvn: '',
    nin: '',
    relationship: '',
    address: ''
  });
};

// Remove a guarantor
const removeGuarantor = (index) => {
  guarantors.value.splice(index, 1);
};

// Convert guarantors to JSON format before submitting
const formatGuarantorsForSubmission = () => {
  const formattedGuarantors = {};

  guarantors.value.forEach((guarantor, index) => {
    // Only include guarantors with at least a name
    if (guarantor.name.trim()) {
      formattedGuarantors[index + 1] = {
        name: guarantor.name,
        phone: guarantor.phone,
        bvn: guarantor.bvn,
        nin: guarantor.nin,
        relationship: guarantor.relationship,
        address: guarantor.address
      };
    }
  });

  return Object.keys(formattedGuarantors).length > 0
    ? JSON.stringify(formattedGuarantors)
    : null;
};

// form state
const form = useForm({
  client_id: props.client.id,
  products: [],
  payment_type: "instant",
  repayment_frequency: "monthly",
  repayment_term: 1,
  employment: {
    monthly_salary: "",
    department: "",
    work_number_id: "",
    employer: "",
    address: "",
    guarantors: ""
  }
});

// Watch payment type changes to update steps
watch(() => form.payment_type, () => {
  updateSteps();
  // If switching to instant, skip employment step if we're on it
  if (form.payment_type === 'instant' && activeStep.value === 3) {
    activeStep.value = 4;
  }
});

// Initialize steps
updateSteps();

// Calculate total
const totalPrice = computed(() => {
  return cart.value.reduce((sum, item) => {
    return sum + (item.quantity * item.price);
  }, 0);
});

// Check if cart is empty
const isCartEmpty = computed(() => cart.value.length === 0);

// Navigation functions
const nextStep = () => {
  // Validate current step before proceeding
  if (activeStep.value === 1 && isCartEmpty.value) {
    toast.error('Please add at least one product to continue');
    return;
  }

  if (activeStep.value < steps.value.length) {
    activeStep.value++;
  }
};

const prevStep = () => {
  if (activeStep.value > 1) {
    activeStep.value--;
  }
};

// Submit
const submit = () => {
  // Prepare products data from cart
  form.products = cart.value.map(item => ({
    id: item.id,
    quantity: item.quantity
  }));

  // Format guarantors as JSON
  form.employment.guarantors = formatGuarantorsForSubmission();

  form.post(route("order.store"), {
    onSuccess: () => {
      form.reset();
      cart.value = []; // Clear cart after successful order
      guarantors.value = []; // Clear guarantors
      activeStep.value = 1; // Reset to first step
      //toast.success('Order created successfully!!!.');
    },
    onError: () => {
      toast.error('Validation error. Please check the highlighted fields.');
    },
  });
};
</script>

<template>
  <div class="p-6 pb-24 bg-white dark:bg-gray-900 rounded-lg shadow">
    <h2 class="text-xl font-bold mb-6">Create Order for {{ client.full_name }}</h2>

    <!-- Progress Steps -->
    <div class="mb-8">
      <div class="flex justify-between items-center relative">
        <div class="absolute top-1/2 left-0 right-0 h-1 bg-gray-200 transform -translate-y-1/2 -z-10"></div>
        <div
          class="absolute top-1/2 left-0 h-1 bg-blue-500 transform -translate-y-1/2 -z-10 transition-all duration-300"
          :style="{ width: `${((activeStep - 1) / (steps.length - 1)) * 100}%` }"
        ></div>

        <div
          v-for="step in steps"
          :key="step.id"
          class="flex flex-col items-center relative"
        >
          <div
            class="w-10 h-10 rounded-full flex items-center justify-center border-2 text-lg"
            :class="{
              'bg-blue-500 text-white border-blue-500': activeStep >= step.id,
              'bg-white border-gray-300': activeStep < step.id
            }"
          >
            {{ step.icon }}
          </div>
          <span
            class="text-sm mt-2 font-medium"
            :class="{
              'text-blue-600': activeStep >= step.id,
              'text-gray-500': activeStep < step.id
            }"
          >
            {{ step.title }}
          </span>
        </div>
      </div>
    </div>

    <!-- Step 1: Product Selection -->
    <div v-if="activeStep === 1" class="mb-6">
      <h3 class="text-lg font-semibold mb-4 flex items-center">
        <span class="mr-2">🛒</span> Select Products
      </h3>

      <!-- Search Bar -->
      <div class="mb-6 flex justify-center">
        <div class="w-full max-w-md">
          <TextInput
            label="Search Products"
            name="search_products"
            v-model="searchQuery"
            placeholder="Type to search products..."
            class="w-full"
          />
        </div>
      </div>

      <!-- Product Grid -->
      <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-4 mb-6">
        <div
          v-for="product in filteredProducts"
          :key="product.id"
          class="border p-4 rounded-lg cursor-pointer transition-all duration-200 hover:shadow-md hover:border-blue-300 bg-white"
          @click="addToCart(product)"
        >
          <p class="font-medium text-gray-800">{{ product.name }}</p>
          <p class="text-sm text-gray-600 mt-1">₦{{ formatCurrency(product.price) }}</p>
          <p class="text-xs text-blue-500 mt-2">Click to add to cart</p>
        </div>
      </div>

      <!-- Cart Preview -->
      <div class=" p-4 rounded-lg" v-if="!isCartEmpty">
        <h4 class="font-medium mb-3">Your Cart ({{ cart.length }} items)</h4>
        <div class="space-y-2">
          <div v-for="item in cart" :key="item.id" class="flex justify-between text-sm">
            <span>{{ item.name }} × {{ item.quantity }}</span>
            <span>₦{{ (item.quantity * item.price).toLocaleString() }}</span>
          </div>
          <div class="border-t pt-2 mt-2 font-medium">
            Total: ₦{{ totalPrice.toLocaleString() }}
          </div>
        </div>
      </div>

      <div v-else class="bg-yellow-50 border border-yellow-200 p-4 rounded-lg text-center">
        <p class="text-yellow-700">Your cart is empty. Select products above to continue.</p>
      </div>
    </div>

    <!-- Step 2: Payment Details -->
    <div v-if="activeStep === 2" class="mb-6">
      <h3 class="text-lg font-semibold mb-4 flex items-center">
        <span class="mr-2">💳</span> Payment Details
      </h3>

      <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
        <SelectInput
          name="Payment Type "
          v-model="form.payment_type"
          :options="paymentTypes"
          :required="true"
        />

        <SelectInput
          v-if="form.payment_type !== 'instant'"
          label="Repayment Frequency *"
          v-model="form.repayment_frequency"
          :options="frequencies"
          :required="true"
        />

        <TextInput
          v-if="form.payment_type !== 'instant'"
          label="Repayment Term (months) *"
          type="number"
          v-model="form.repayment_term"
          :required="true"
          min="1"
        />
      </div>

      <div v-if="form.payment_type !== 'instant'" class="bg-blue-50 p-4 rounded-lg">
        <h4 class="font-medium text-blue-800 mb-2">Installment Plan Summary</h4>
        <p class="text-sm text-blue-700">
          Total: ₦{{ totalPrice.toLocaleString() }} |
          Monthly Payment: ₦{{ (totalPrice / form.repayment_term).toLocaleString() }} |
          Term: {{ form.repayment_term }} months
        </p>
      </div>
    </div>

    <!-- Step 3: Employment & Guarantors (Only for non-instant payments) -->
    <div v-if="activeStep === 3 && form.payment_type !== 'instant'" class="mb-6">
      <h3 class="text-lg font-semibold mb-4 flex items-center">
        <span class="mr-2">💼</span> Employment Details
      </h3>

      <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-8">
        <TextInput label="Monthly Salary" type="number" v-model="form.employment.monthly_salary" :required="true" />
        <TextInput label="Department" v-model="form.employment.department" />
        <TextInput label="Work Number ID" v-model="form.employment.work_number_id" />
        <TextInput label="Employer" v-model="form.employment.employer" :required="true" />
        <TextInput label="Address" v-model="form.employment.address" :required="true" class="md:col-span-2" />
      </div>

      <!-- Guarantors Section -->
      <div class="mt-8">
        <div class="flex justify-between items-center mb-6">
          <h4 class="text-lg font-semibold">Guarantors</h4>
          <button
            type="button"
            @click="addGuarantor"
            class="px-4 py-2 bg-blue-500 text-white rounded-lg text-sm hover:bg-blue-600 transition"
          >
            + Add Guarantor
          </button>
        </div>

        <div v-if="guarantors.length === 0" class="bg-gray-50 p-6 rounded-lg text-center">
          <p class="text-gray-500">No guarantors added yet. Click "Add Guarantor" to add one.</p>
        </div>

        <div v-for="(guarantor, index) in guarantors" :key="index" class="border border-gray-200 p-5 rounded-lg mb-5 relative bg-white dark:bg-gray-700">
          <button
            type="button"
            @click="removeGuarantor(index)"
            class="absolute top-3 right-3 w-7 h-7 flex items-center justify-center bg-red-100 text-red-600 rounded-full hover:bg-red-200 transition"
            title="Remove guarantor"
          >
            &times;
          </button>

          <h5 class="font-medium mb-4 ">Guarantor {{ index + 1 }}</h5>

          <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
            <TextInput label="Full Name" v-model="guarantor.name" :required="true" />
            <TextInput label="Phone Number" v-model="guarantor.phone" :required="true" />
            <TextInput label="BVN" v-model="guarantor.bvn" :required="true"/>
            <TextInput label="NIN" v-model="guarantor.nin" :required="true"/>
            <SelectInput
              name="Relationship"
              v-model="guarantor.relationship"
              :options="relationships.map(r => ({ value: r, label: r }))"
              :required="true"
            />
            <TextInput label="Address" v-model="guarantor.address" :required="true" />
          </div>
        </div>
      </div>
    </div>

    <!-- Step 4: Review & Submit -->
    <div v-if="activeStep === 4" class="mb-6">
      <h3 class="text-lg font-semibold mb-6 flex items-center">
        <span class="mr-2">📋</span> Review Order
      </h3>

      <div class=" bg-gray-50 dark:bg-gray-700 p-5 rounded-lg mb-6">
        <h4 class="font-medium  mb-4 border-b pb-2">Order Summary</h4>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
          <div>
            <h5 class="font-medium mb-2">Products</h5>
            <div class="space-y-2">
              <div v-for="item in cart" :key="item.id" class="flex justify-between text-sm">
                <span>{{ item.name }} × {{ item.quantity }}</span>
                <span>₦{{ (item.quantity * item.price).toLocaleString() }}</span>
              </div>
            </div>
            <div class="border-t pt-2 mt-2 font-medium">
              Total: ₦{{ totalPrice.toLocaleString() }}
            </div>
          </div>

          <div>
            <h5 class="font-medium mb-2">Payment Details</h5>
            <div class="space-y-1 text-sm">
              <p><span >Type:</span> {{ form.payment_type }}</p>
              <p v-if="form.payment_type !== 'instant'">
                <span>Frequency:</span> {{ form.repayment_frequency }}
              </p>
              <p v-if="form.payment_type !== 'instant'">
                <span >Term:</span> {{ form.repayment_term }} months
              </p>
            </div>
          </div>
        </div>
      </div>

      <!-- Only show employment details for non-instant payments -->
      <div class="bg-gray-50  dark:bg-gray-700 p-5 rounded-lg mb-6" v-if="form.payment_type !== 'instant'">
        <h4 class="font-medium mb-4 border-b pb-2">Employment Details</h4>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-6" >
          <div class="space-y-1 text-sm">
            <p><span >Monthly Salary:</span> ₦{{ form.employment.monthly_salary || '0' }}</p>
            <p><span >Department:</span> {{ form.employment.department || 'N/A' }}</p>
            <p><span >Work ID:</span> {{ form.employment.work_number_id || 'N/A' }}</p>
          </div>

          <div class="space-y-1 text-sm">
            <p><span>Employer:</span> {{ form.employment.employer || 'N/A' }}</p>
            <p><span>Address:</span> {{ form.employment.address || 'N/A' }}</p>
          </div>
        </div>
      </div>

      <!-- Only show guarantors for non-instant payments -->
      <div class="bg-gray-50  dark:bg-gray-700 p-5 rounded-lg" v-if="guarantors.length > 0 && form.payment_type !== 'instant'">
        <h4 class="font-medium mb-4 border-b pb-2">Guarantors ({{ guarantors.length }})</h4>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
          <div v-for="(guarantor, index) in guarantors" :key="index" class="text-sm">
            <h5 class="font-medium mb-2">Guarantor {{ index + 1 }}</h5>
            <div class="space-y-1">
              <p><span>Name:</span> {{ guarantor.name }}</p>
              <p><span>Phone:</span> {{ guarantor.phone }}</p>
              <p><span>Relationship:</span> {{ guarantor.relationship }}</p>
              <p v-if="guarantor.bvn"><span>BVN:</span> {{ guarantor.bvn }}</p>
              <p v-if="guarantor.nin"><span>NIN:</span> {{ guarantor.nin }}</p>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Navigation Buttons -->
    <div class="flex justify-between pt-6 border-t">
      <button
        v-if="activeStep > 1"
        @click="prevStep"
        class="px-5 py-2 bg-gray-200 text-gray-500 dark:bg-gray-600 dark:text-gray-200 rounded-lg hover:bg-gray-700 transition"
      >
        ← Previous
      </button>
      <div v-else></div> <!-- Empty div for spacing -->

      <button
        v-if="activeStep < steps.length"
        @click="nextStep"
        class="px-5 py-2 bg-blue-500 text-white rounded-lg hover:bg-blue-600 transition"
        :disabled="activeStep === 1 && isCartEmpty"
        :class="{ 'opacity-50 cursor-not-allowed': activeStep === 1 && isCartEmpty }"
      >
        Next →
      </button>

      <button
        v-if="activeStep === steps.length"
        class="px-5 py-2 bg-green-500 text-white rounded-lg hover:bg-green-600 transition"
        :disabled="form.processing"
        @click="submit"
      >
        <span v-if="form.processing">Processing...</span>
        <span v-else>Create Order</span>
      </button>
    </div>
  </div>
</template>
