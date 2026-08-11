<script setup lang="ts">
defineProps<{
  htmlFor?: string;
}>();
</script>

<template>
  <label :for="htmlFor" class="text-sm font-medium leading-none">
    <slot />
  </label>
</template>
