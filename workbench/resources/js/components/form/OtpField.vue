<script setup lang="ts">
import type { FormField } from "@inertify/form-vue";
import InputOtp from "@/components/ui/InputOtp.vue";
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
</script>

<template>
  <FieldShell
    :id="inputId"
    :label="field.label"
    :help="field.help"
    :error="error"
    :required="required"
  >
    <InputOtp
      :id="inputId"
      :ref="(element) => registerElement(element?.$el ?? element)"
      :model-value="value == null ? '' : String(value)"
      :name="name"
      :length="Number(field.length ?? 6)"
      :numeric="field.numeric !== false"
      :masked="field.mask === true || field.password === true"
      :disabled="disabled"
      :readonly="readonly"
      :required="required"
      :autocomplete="field.webOtp === false ? 'off' : 'one-time-code'"
      @update:model-value="setValue"
      @blur="blur"
    />
  </FieldShell>
</template>
