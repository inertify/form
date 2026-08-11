<script setup lang="ts">
defineOptions({ inheritAttrs: false });

defineProps<{
  modelValue?: unknown;
}>();

const emit = defineEmits<{
  "update:modelValue": [value: string];
  blur: [event: FocusEvent];
}>();
</script>

<template>
  <textarea
    v-bind="$attrs"
    :value="modelValue ?? ''"
    class="flex min-h-24 w-full rounded-md border bg-card px-3 py-2 text-sm shadow-sm outline-none transition placeholder:text-muted-foreground focus:ring-2 focus:ring-primary/30 disabled:cursor-not-allowed disabled:opacity-50"
    @input="emit('update:modelValue', ($event.target as HTMLTextAreaElement).value)"
    @blur="emit('blur', $event)"
  />
</template>
