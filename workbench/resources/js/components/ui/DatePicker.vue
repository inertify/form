<script setup lang="ts">
import { computed, ref, useId } from "vue";

defineOptions({ inheritAttrs: false });

const props = withDefaults(
  defineProps<{
    modelValue?: string | string[] | null;
    id?: string;
    name?: string;
    type?: "date" | "datetime-local" | "month";
    min?: string;
    max?: string;
    range?: boolean;
    disabled?: boolean;
    readonly?: boolean;
    required?: boolean;
    clearable?: boolean;
    label?: string;
    startLabel?: string;
    endLabel?: string;
  }>(),
  {
    type: "date",
    range: false,
    disabled: false,
    readonly: false,
    required: false,
    clearable: false,
    label: "Date",
    startLabel: "Start date",
    endLabel: "End date",
  },
);

const emit = defineEmits<{
  "update:modelValue": [value: string | string[] | null];
  change: [value: string | string[] | null];
  blur: [event: FocusEvent];
}>();

const generatedId = useId();
const root = ref<HTMLElement | null>(null);
const isRange = computed(() => props.range || Array.isArray(props.modelValue));
const values = computed<[string, string]>(() => {
  if (Array.isArray(props.modelValue)) {
    return [String(props.modelValue[0] ?? ""), String(props.modelValue[1] ?? "")];
  }

  return [props.modelValue ?? "", ""];
});

function nextValue(index: number, value: string): string | string[] | null {
  if (!isRange.value) {
    return value || null;
  }

  const next: [string, string] = [values.value[0], values.value[1]];
  next[index] = value;

  return next;
}

function update(index: number, event: Event): void {
  emit("update:modelValue", nextValue(index, (event.target as HTMLInputElement).value));
}

function commit(index: number, event: Event): void {
  emit("change", nextValue(index, (event.target as HTMLInputElement).value));
}

function clear(): void {
  const value = isRange.value ? ["", ""] : null;
  emit("update:modelValue", value);
  emit("change", value);
}

function inputId(index: number): string {
  const base = props.id ?? `date-picker-${generatedId}`;

  return isRange.value ? `${base}-${index === 0 ? "start" : "end"}` : base;
}

function inputName(index: number): string | undefined {
  if (!props.name) {
    return undefined;
  }

  return isRange.value ? `${props.name}[${index}]` : props.name;
}

function leaveGroup(event: FocusEvent): void {
  const next = event.relatedTarget;

  if (!(next instanceof Node) || !root.value?.contains(next)) {
    emit("blur", event);
  }
}

defineExpose({
  root,
  focus: () => root.value?.querySelector<HTMLInputElement>("input")?.focus(),
});
</script>

<template>
  <div
    ref="root"
    v-bind="$attrs"
    :role="isRange ? 'group' : undefined"
    :aria-label="isRange ? label : undefined"
    class="flex w-full flex-col gap-2 sm:flex-row sm:items-center"
    @focusout="leaveGroup"
  >
    <label
      v-for="index in isRange ? 2 : 1"
      :key="inputId(index - 1)"
      class="relative min-w-0 flex-1"
    >
      <span class="sr-only">
        {{ isRange ? (index === 1 ? startLabel : endLabel) : label }}
      </span>
      <input
        :id="inputId(index - 1)"
        :type="type"
        :name="inputName(index - 1)"
        :value="values[index - 1]"
        :min="min"
        :max="max"
        :disabled="disabled"
        :readonly="readonly"
        :required="required"
        :aria-label="isRange ? (index === 1 ? startLabel : endLabel) : label"
        class="flex h-9 w-full rounded-md border bg-card px-3 py-1 text-sm shadow-sm outline-none transition focus:ring-2 focus:ring-primary/30 disabled:cursor-not-allowed disabled:opacity-50"
        @input="update(index - 1, $event)"
        @change="commit(index - 1, $event)"
      />
    </label>

    <button
      v-if="clearable && values.some(Boolean)"
      type="button"
      :disabled="disabled || readonly"
      class="inline-flex h-9 items-center justify-center rounded-md border bg-card px-3 text-sm font-medium transition hover:bg-muted disabled:pointer-events-none disabled:opacity-50"
      aria-label="Clear date"
      @click="clear"
    >
      Clear
    </button>
  </div>
</template>
