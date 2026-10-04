<script setup>

defineEmits(['close']);
const liClasses = 'block px-2 py-0 text-gray-700 hover:bg-gray-100 dark:text-gray-100 dark:hover:bg-gray-700';
</script>

<template>
  <li>
    <Link :href="route('client.dashboard')" :class="liClasses" @click="$emit('close')">
      Dashboard
    </Link>
  </li>

  <li>
    <Link :href="route('order.create', $page.props.auth.client.id)" :class="liClasses" @click="$emit('close')">
      + New Order
    </Link>
  </li>

  <li>
    <Link :href="route('client.profile')" :class="liClasses" @click="$emit('close')">
      Profile
    </Link>
  </li>
  <li>
    <Link :href="route('client.password.change')" :class="liClasses" @click="$emit('close')">
      Change Password
    </Link>
  </li>
  <li>
    <Link
      :href="route('logout')"
      as="button"
      method="post"
      class="block px-4 py-2 text-red-500 hover:bg-gray-100 w-full dark:hover:bg-gray-700"
      @click="$emit('close')"
    >
      Logout
    </Link>
  </li>
</template>
