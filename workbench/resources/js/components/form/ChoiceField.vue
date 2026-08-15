<script setup lang="ts">
import { computed } from "vue";
import type { FormField } from "@inertify/form-vue";
import Checkbox from "@/components/ui/Checkbox.vue";
import RadioGroup from "@/components/ui/RadioGroup.vue";
import FieldShell from "./FieldShell.vue";

defineOptions({ inheritAttrs: false });

type Choice = {
  value: unknown;
  label: string;
  description?: string | null;
  disabled?: boolean;
  disabledReason?: string | null;
};

const props = defineProps<{
  field: FormField;
  name: string;
  value: unknown;
  error: string | null;
  required: boolean;
  disabled: boolean;
  readonly: boolean;
  multiple?: boolean;
  setValue: (value: unknown) => void;
  blur: () => Promise<boolean>;
  registerElement: (element: HTMLElement | null) => void;
}>();

const inputId = `field-${props.name.replace(/[^a-z0-9_-]/gi, "-")}`;
const options = computed<Choice[]>(() =>
  Array.isArray(props.field.options)
    ? props.field.options.filter((option): option is Choice =>
        typeof option === "object" &&
        option !== null &&
        "value" in option &&
        "label" in option,
      )
    : [],
);
const selected = computed<unknown[]>(() =>
  Array.isArray(props.value) ? props.value : [],
);

function checked(option: Choice): boolean {
  return selected.value.some((value) => String(value) === String(option.value));
}

function toggle(option: Choice, enabled: boolean): void {
  const next = selected.value.filter((value) => String(value) !== String(option.value));

  if (enabled) {
    next.push(option.value);
  }

  props.setValue(next);
}
</script>

<template>
  <FieldShell
    :id="inputId"
    :label="field.label"
    :help="field.help"
    :error="error"
    :required="required"
  >
    <RadioGroup
      v-if="!multiple"
      :id="inputId"
      :ref="(element) => registerElement(element?.$el ?? element)"
      :model-value="value"
      :name="name"
      :options="options"
      :disabled="disabled || readonly"
      :required="required"
      @update:model-value="setValue"
      @blur="blur"
    />

    <div v-else class="grid gap-3 sm:grid-cols-2" role="group" :aria-labelledby="`${inputId}-label`">
      <label
        v-for="(option, index) in options"
        :key="String(option.value)"
        class="flex items-start gap-3 rounded-lg border bg-card p-3 text-sm transition has-[:checked]:border-primary has-[:checked]:bg-primary/5"
        :class="option.disabled ? 'cursor-not-allowed opacity-60' : 'cursor-pointer'"
      >
        <Checkbox
          :id="`${inputId}-${index}`"
          :model-value="checked(option)"
          :name="`${name}[]`"
          :value="String(option.value)"
          :disabled="disabled || readonly || option.disabled === true"
          @update:model-value="toggle(option, $event)"
          @blur="blur"
        />
        <span>
          <span class="block font-medium">{{ option.label }}</span>
          <span v-if="option.description" class="mt-0.5 block text-xs text-muted-foreground">
            {{ option.description }}
          </span>
          <span v-if="option.disabledReason" class="mt-0.5 block text-xs text-destructive">
            {{ option.disabledReason }}
          </span>
        </span>
      </label>
    </div>
  </FieldShell>
</template>
