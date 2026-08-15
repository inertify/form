<script setup lang="ts">
import { computed, ref, useId } from "vue";

defineOptions({ inheritAttrs: false });

interface SelectOption {
  value: unknown;
  label: string;
  description?: string | null;
  disabled?: boolean;
  disabledReason?: string | null;
  group?: string | null;
}

interface IndexedOption extends SelectOption {
  index: number;
  token: string;
}

interface OptionGroup {
  label: string | null;
  options: IndexedOption[];
}

const props = withDefaults(
  defineProps<{
    modelValue?: unknown;
    options: SelectOption[];
    id?: string;
    name?: string;
    placeholder?: string;
    disabled?: boolean;
    readonly?: boolean;
    required?: boolean;
    multiple?: boolean;
    size?: number;
  }>(),
  {
    placeholder: "Select an option",
    disabled: false,
    readonly: false,
    required: false,
    multiple: false,
  },
);

const emit = defineEmits<{
  "update:modelValue": [value: unknown | unknown[] | null];
  change: [value: unknown | unknown[] | null];
  blur: [event: FocusEvent];
}>();

const generatedId = useId();
const select = ref<HTMLSelectElement | null>(null);

const indexedOptions = computed<IndexedOption[]>(() =>
  props.options.map((option: SelectOption, index: number) => ({
    ...option,
    index,
    token: String(index),
  })),
);

const groups = computed<OptionGroup[]>(() => {
  const grouped = new Map<string | null, IndexedOption[]>();

  for (const option of indexedOptions.value) {
    const label = option.group ?? null;
    const entries = grouped.get(label) ?? [];
    entries.push(option);
    grouped.set(label, entries);
  }

  return [...grouped.entries()].map(([label, options]) => ({ label, options }));
});

const selectedTokens = computed<string | string[]>(() => {
  if (props.multiple) {
    const values: unknown[] = Array.isArray(props.modelValue) ? props.modelValue : [];

    return indexedOptions.value
      .filter((option) =>
        values.some((value: unknown) => sameValue(value, option.value)),
      )
      .map((option) => option.token);
  }

  return indexedOptions.value.find((option) => sameValue(props.modelValue, option.value))
    ?.token ?? "";
});

function sameValue(left: unknown, right: unknown): boolean {
  if (Object.is(left, right)) {
    return true;
  }

  return (
    (typeof left === "string" || typeof left === "number") &&
    (typeof right === "string" || typeof right === "number") &&
    String(left) === String(right)
  );
}

function tokenSelected(token: string): boolean {
  return Array.isArray(selectedTokens.value)
    ? selectedTokens.value.includes(token)
    : selectedTokens.value === token;
}

function resolveSelection(target: HTMLSelectElement): unknown | unknown[] | null {
  if (props.multiple) {
    return [...target.selectedOptions]
      .map((option) => indexedOptions.value[Number(option.value)]?.value)
      .filter((value): value is unknown => value !== undefined);
  }

  if (target.value === "") {
    return null;
  }

  return indexedOptions.value[Number(target.value)]?.value ?? null;
}

function update(event: Event): void {
  const value = resolveSelection(event.target as HTMLSelectElement);
  emit("update:modelValue", value);
  emit("change", value);
}

defineExpose({
  select,
  focus: () => select.value?.focus(),
});
</script>

<template>
  <select
    :id="id ?? `select-${generatedId}`"
    ref="select"
    v-bind="$attrs"
    :name="name"
    :value="selectedTokens"
    :disabled="disabled || readonly"
    :required="required"
    :multiple="multiple"
    :size="size"
    :aria-readonly="readonly || undefined"
    class="flex min-h-9 w-full rounded-md border bg-card px-3 py-1 text-sm shadow-sm outline-none transition focus:ring-2 focus:ring-primary/30 disabled:cursor-not-allowed disabled:opacity-50"
    @change="update"
    @blur="emit('blur', $event)"
  >
    <option v-if="!multiple" value="" :disabled="required">
      {{ placeholder }}
    </option>

    <template v-for="optionGroup in groups" :key="optionGroup.label ?? '__ungrouped'">
      <optgroup v-if="optionGroup.label" :label="optionGroup.label">
        <option
          v-for="option in optionGroup.options"
          :key="option.token"
          :value="option.token"
          :selected="tokenSelected(option.token)"
          :disabled="option.disabled"
          :title="option.disabledReason ?? option.description ?? undefined"
        >
          {{ option.label }}
        </option>
      </optgroup>
      <template v-else>
        <option
          v-for="option in optionGroup.options"
          :key="option.token"
          :value="option.token"
          :selected="tokenSelected(option.token)"
          :disabled="option.disabled"
          :title="option.disabledReason ?? option.description ?? undefined"
        >
          {{ option.label }}
        </option>
      </template>
    </template>
  </select>
</template>
