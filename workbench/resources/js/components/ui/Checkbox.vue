<script setup lang="ts">
import { ref, watchEffect } from "vue";

defineOptions({ inheritAttrs: false });

const props = withDefaults(
  defineProps<{
    modelValue?: boolean;
    id?: string;
    name?: string;
    value?: string | number;
    disabled?: boolean;
    readonly?: boolean;
    required?: boolean;
    indeterminate?: boolean;
  }>(),
  {
    modelValue: false,
    disabled: false,
    readonly: false,
    required: false,
    indeterminate: false,
  },
);

const emit = defineEmits<{
  "update:modelValue": [value: boolean];
  change: [value: boolean];
  blur: [event: FocusEvent];
}>();

const input = ref<HTMLInputElement | null>(null);

watchEffect(() => {
  if (input.value) {
    input.value.indeterminate = props.indeterminate;
  }
});

function toggle(event: Event): void {
  const target = event.target as HTMLInputElement;

  if (props.readonly) {
    target.checked = props.modelValue;

    return;
  }

  emit("update:modelValue", target.checked);
  emit("change", target.checked);
}

function preventReadonlyToggle(event: Event): void {
  if (props.readonly) {
    event.preventDefault();
  }
}

defineExpose({
  input,
  focus: () => input.value?.focus(),
});
</script>

<template>
  <input
    :id="id"
    ref="input"
    v-bind="$attrs"
    type="checkbox"
    :name="name"
    :value="value"
    :checked="modelValue"
    :disabled="disabled"
    :required="required"
    :aria-checked="indeterminate ? 'mixed' : modelValue"
    :aria-readonly="readonly || undefined"
    class="size-4 shrink-0 cursor-pointer rounded border border-border bg-card accent-primary shadow-sm outline-none transition focus-visible:ring-2 focus-visible:ring-primary/30 disabled:cursor-not-allowed disabled:opacity-50"
    @click="preventReadonlyToggle"
    @change="toggle"
    @blur="emit('blur', $event)"
  />
</template>
