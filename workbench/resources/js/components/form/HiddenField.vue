<script setup lang="ts">
import type { FormField } from "@inertify/form-vue";

defineOptions({ inheritAttrs: false });

defineProps<{
  field: FormField;
  name: string;
  value: unknown;
  disabled: boolean;
  registerElement: (element: HTMLElement | null) => void;
}>();
</script>

<template>
  <input
    :ref="registerElement"
    type="hidden"
    :name="name"
    :value="value == null ? '' : String(value)"
    :disabled="disabled"
    v-bind="field.dataAttributes ?? {}"
  />
</template>
