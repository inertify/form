<script setup lang="ts">
import { ref } from "vue";

defineOptions({ inheritAttrs: false });

const props = withDefaults(
  defineProps<{
    modelValue?: boolean;
    id?: string;
    name?: string;
    disabled?: boolean;
    readonly?: boolean;
    required?: boolean;
  }>(),
  {
    modelValue: false,
    disabled: false,
    readonly: false,
    required: false,
  },
);

const emit = defineEmits<{
  "update:modelValue": [value: boolean];
  change: [value: boolean];
  blur: [event: FocusEvent];
}>();

const input = ref<HTMLInputElement | null>(null);

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
  <span class="relative inline-flex h-5 w-9 shrink-0 align-middle">
    <input
      :id="id"
      ref="input"
      v-bind="$attrs"
      type="checkbox"
      role="switch"
      :name="name"
      :checked="modelValue"
      :disabled="disabled"
      :required="required"
      :aria-checked="modelValue"
      :aria-readonly="readonly || undefined"
      class="peer absolute inset-0 z-10 size-full cursor-pointer opacity-0 disabled:cursor-not-allowed"
      @click="preventReadonlyToggle"
      @change="toggle"
      @blur="emit('blur', $event)"
    />
    <span
      aria-hidden="true"
      class="pointer-events-none inline-flex size-full rounded-full bg-muted shadow-inner transition-colors peer-checked:bg-primary peer-focus-visible:ring-2 peer-focus-visible:ring-primary/30 peer-disabled:opacity-50"
    >
      <span
        :class="[
          'm-0.5 size-4 rounded-full bg-card shadow-sm transition-transform',
          modelValue ? 'translate-x-4' : 'translate-x-0',
        ]"
      />
    </span>
  </span>
</template>
