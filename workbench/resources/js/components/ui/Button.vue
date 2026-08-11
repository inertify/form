<script setup lang="ts">
import { computed } from "vue";

const props = withDefaults(
  defineProps<{
    type?: "button" | "submit" | "reset";
    variant?: "default" | "outline" | "ghost" | "destructive";
    disabled?: boolean;
  }>(),
  {
    type: "button",
    variant: "default",
    disabled: false,
  },
);

const classes = computed(() => ({
  default: "bg-primary text-primary-foreground hover:opacity-90",
  outline: "border bg-card hover:bg-muted",
  ghost: "hover:bg-muted",
  destructive: "bg-destructive text-white hover:opacity-90",
})[props.variant]);
</script>

<template>
  <button
    :type="type"
    :disabled="disabled"
    :class="[
      'inline-flex h-9 items-center justify-center rounded-md px-4 text-sm font-medium transition disabled:pointer-events-none disabled:opacity-50',
      classes,
    ]"
  >
    <slot />
  </button>
</template>
