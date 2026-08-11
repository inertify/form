<script setup lang="ts">
defineProps<{
  id: string;
  label: string | null;
  help?: string | null;
  error?: string | null;
  required?: boolean;
}>();
</script>

<template>
  <div class="space-y-2">
    <div class="flex items-center justify-between gap-3">
      <label :for="id" class="text-sm font-medium">
        {{ label }}
        <span v-if="required" class="text-destructive" aria-hidden="true">*</span>
      </label>
      <span v-if="error" class="text-xs font-medium text-destructive">Invalid</span>
    </div>

    <slot />

    <p v-if="error" class="text-sm text-destructive" role="alert">{{ error }}</p>
    <p v-else-if="help" class="text-sm text-muted-foreground">{{ help }}</p>
  </div>
</template>
