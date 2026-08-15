<script setup lang="ts">
import { computed } from "vue";
import type { FormField } from "@inertify/form-vue";
import Input from "@/components/ui/Input.vue";
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
const step = computed(() => {
  if (typeof props.field.step === "number") {
    return props.field.step;
  }

  if (typeof props.field.minuteStep === "number") {
    return props.field.minuteStep * 60;
  }

  return props.field.showSeconds === true ? 1 : 60;
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
    <Input
      :id="inputId"
      :ref="(element) => registerElement(element?.$el ?? element)"
      type="time"
      :name="name"
      :model-value="value"
      :min="field.minTime ?? undefined"
      :max="field.maxTime ?? undefined"
      :step="step"
      :disabled="disabled"
      :readonly="readonly"
      :aria-invalid="Boolean(error)"
      v-bind="field.dataAttributes ?? {}"
      @update:model-value="setValue"
      @blur="blur"
    />
  </FieldShell>
</template>
