<script setup lang="ts">
import { ref, useId } from "vue";

defineOptions({ inheritAttrs: false });

interface RadioOption {
  value: unknown;
  label: string;
  description?: string | null;
  disabled?: boolean;
  disabledReason?: string | null;
}

const props = withDefaults(
  defineProps<{
    modelValue?: unknown;
    options: RadioOption[];
    id?: string;
    name?: string;
    disabled?: boolean;
    required?: boolean;
    orientation?: "horizontal" | "vertical";
  }>(),
  {
    disabled: false,
    required: false,
    orientation: "vertical",
  },
);

const emit = defineEmits<{
  "update:modelValue": [value: unknown];
  change: [value: unknown];
  blur: [event: FocusEvent];
}>();

const generatedId = useId();
const group = ref<HTMLElement | null>(null);

function baseId(): string {
  return props.id ?? `radio-group-${generatedId}`;
}

function optionId(index: number): string {
  return `${baseId()}-${index}`;
}

function optionDescriptionId(index: number): string | undefined {
  const option = props.options[index];

  return option?.description || option?.disabledReason
    ? `${optionId(index)}-description`
    : undefined;
}

function selected(value: unknown): boolean {
  return Object.is(props.modelValue, value);
}

function choose(option: RadioOption): void {
  if (props.disabled || option.disabled) {
    return;
  }

  emit("update:modelValue", option.value);
  emit("change", option.value);
}

function leaveGroup(event: FocusEvent): void {
  const next = event.relatedTarget;

  if (!(next instanceof Node) || !group.value?.contains(next)) {
    emit("blur", event);
  }
}

defineExpose({
  group,
  focus: () =>
    group.value
      ?.querySelector<HTMLInputElement>('input[type="radio"]:not(:disabled)')
      ?.focus(),
});
</script>

<template>
  <div
    :id="baseId()"
    ref="group"
    v-bind="$attrs"
    role="radiogroup"
    :aria-disabled="disabled || undefined"
    :aria-orientation="orientation"
    :class="[
      'flex gap-3',
      orientation === 'horizontal' ? 'flex-row flex-wrap' : 'flex-col',
    ]"
    @focusout="leaveGroup"
  >
    <label
      v-for="(option, index) in options"
      :key="`${index}-${String(option.value)}`"
      :for="optionId(index)"
      :class="[
        'flex items-start gap-3 rounded-md border bg-card px-3 py-2 text-sm transition',
        disabled || option.disabled
          ? 'cursor-not-allowed opacity-50'
          : 'cursor-pointer hover:bg-muted',
      ]"
    >
      <input
        :id="optionId(index)"
        type="radio"
        :name="name ?? baseId()"
        :value="String(index)"
        :checked="selected(option.value)"
        :disabled="disabled || option.disabled"
        :required="required"
        :aria-describedby="optionDescriptionId(index)"
        class="mt-0.5 size-4 shrink-0 accent-primary"
        @change="choose(option)"
      />
      <span class="min-w-0">
        <span class="block font-medium">{{ option.label }}</span>
        <span
          v-if="option.description || option.disabledReason"
          :id="optionDescriptionId(index)"
          class="mt-0.5 block text-xs text-muted-foreground"
        >
          {{ option.disabledReason ?? option.description }}
        </span>
      </span>
    </label>
  </div>
</template>
