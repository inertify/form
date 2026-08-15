<script setup lang="ts">
import { computed, ref, useId } from "vue";

defineOptions({ inheritAttrs: false });

const props = withDefaults(
  defineProps<{
    modelValue?: number | number[] | null;
    id?: string;
    name?: string;
    min?: number;
    max?: number;
    step?: number;
    range?: boolean;
    disabled?: boolean;
    readonly?: boolean;
    required?: boolean;
    label?: string;
    minimumLabel?: string;
    maximumLabel?: string;
  }>(),
  {
    min: 0,
    max: 100,
    step: 1,
    range: false,
    disabled: false,
    readonly: false,
    required: false,
    label: "Value",
    minimumLabel: "Minimum value",
    maximumLabel: "Maximum value",
  },
);

const emit = defineEmits<{
  "update:modelValue": [value: number | number[]];
  change: [value: number | number[]];
  blur: [event: FocusEvent];
}>();

const generatedId = useId();
const root = ref<HTMLElement | null>(null);
const isRange = computed(() => props.range || Array.isArray(props.modelValue));
const values = computed<[number, number]>(() => {
  if (Array.isArray(props.modelValue)) {
    return [
      clamp(Number(props.modelValue[0] ?? props.min)),
      clamp(Number(props.modelValue[1] ?? props.max)),
    ];
  }

  return [clamp(Number(props.modelValue ?? props.min)), props.max];
});

function clamp(value: number): number {
  if (!Number.isFinite(value)) {
    return props.min;
  }

  return Math.min(props.max, Math.max(props.min, value));
}

function nextValue(index: number, value: number): number | number[] {
  const normalized = clamp(value);

  if (!isRange.value) {
    return normalized;
  }

  const next: [number, number] = [values.value[0], values.value[1]];
  next[index] = normalized;

  if (index === 0 && next[0] > next[1]) {
    next[0] = next[1];
  }

  if (index === 1 && next[1] < next[0]) {
    next[1] = next[0];
  }

  return next;
}

function update(index: number, event: Event): void {
  const value = nextValue(index, (event.target as HTMLInputElement).valueAsNumber);
  emit("update:modelValue", value);
}

function commit(index: number, event: Event): void {
  emit("change", nextValue(index, (event.target as HTMLInputElement).valueAsNumber));
}

function inputId(index: number): string {
  const base = props.id ?? `slider-${generatedId}`;

  return isRange.value ? `${base}-${index === 0 ? "minimum" : "maximum"}` : base;
}

function inputName(index: number): string | undefined {
  if (!props.name) {
    return undefined;
  }

  return isRange.value ? `${props.name}[${index}]` : props.name;
}

defineExpose({
  root,
  focus: () => root.value?.querySelector<HTMLInputElement>('input[type="range"]')?.focus(),
});
</script>

<template>
  <div
    ref="root"
    v-bind="$attrs"
    :role="isRange ? 'group' : undefined"
    :aria-label="isRange ? label : undefined"
    class="flex w-full items-center gap-3"
  >
    <input
      v-for="index in isRange ? 2 : 1"
      :id="inputId(index - 1)"
      :key="inputId(index - 1)"
      type="range"
      :name="inputName(index - 1)"
      :value="values[index - 1]"
      :min="min"
      :max="max"
      :step="step"
      :disabled="disabled || readonly"
      :required="required"
      :aria-label="isRange ? (index === 1 ? minimumLabel : maximumLabel) : label"
      :aria-readonly="readonly || undefined"
      class="h-2 min-w-0 flex-1 cursor-pointer accent-primary disabled:cursor-not-allowed disabled:opacity-50"
      @input="update(index - 1, $event)"
      @change="commit(index - 1, $event)"
      @blur="emit('blur', $event)"
    />
  </div>
</template>
