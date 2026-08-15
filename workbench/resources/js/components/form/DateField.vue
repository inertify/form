<script setup lang="ts">
import { computed } from "vue";
import type { FormField } from "@inertify/form-vue";
import DatePicker from "@/components/ui/DatePicker.vue";
import FieldShell from "./FieldShell.vue";

defineOptions({ inheritAttrs: false });

const props = defineProps<{
  field: FormField;
  name: string;
  value: unknown;
  error: string | null;
  required: boolean;
  disabled: boolean;
  readonly: boolean;
  setValue: (value: unknown) => void;
  blur: () => Promise<boolean>;
  registerElement: (element: HTMLElement | null) => void;
}>();

const inputId = `field-${props.name.replace(/[^a-z0-9_-]/gi, "-")}`;
const type = computed<"date" | "datetime-local" | "month">(() => {
  if (props.field.mode === "month") {
    return "month";
  }

  return props.field.withTime === true ? "datetime-local" : "date";
});
const modelValue = computed<string | string[] | null>(() => {
  if (Array.isArray(props.value)) {
    return props.value.filter((value): value is string => typeof value === "string");
  }

  return typeof props.value === "string" ? props.value : null;
});
</script>

<template>
  <FieldShell
    :id="inputId"
    :label="field.label"
    :help="field.help"
    :error="error"
    :required="required"
  >
    <DatePicker
      :id="inputId"
      :ref="(element) => registerElement(element?.$el ?? element)"
      :model-value="modelValue"
      :name="name"
      :type="type"
      :min="field.minDate ?? undefined"
      :max="field.maxDate ?? undefined"
      :range="field.mode === 'range'"
      :disabled="disabled"
      :readonly="readonly"
      :required="required"
      :clearable="field.clearable === true"
      @update:model-value="setValue"
      @blur="blur"
    />
  </FieldShell>
</template>
