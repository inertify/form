<script setup lang="ts">
import type { FormField } from "@inertify/form-vue";
import Input from "@/components/ui/Input.vue";
import Textarea from "@/components/ui/Textarea.vue";
import FieldShell from "./FieldShell.vue";

const props = defineProps<{
  field: FormField;
  name: string;
  value: unknown;
  error: string | null;
  required: boolean;
  disabled: boolean;
  readonly: boolean;
  multiline?: boolean;
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
    <Textarea
      v-if="multiline"
      :id="inputId"
      :ref="(element) => registerElement(element?.$el ?? element)"
      :model-value="value"
      :name="name"
      :placeholder="field.placeholder ?? undefined"
      :disabled="disabled"
      :readonly="readonly"
      :aria-invalid="Boolean(error)"
      v-bind="field.dataAttributes ?? {}"
      @update:model-value="setValue"
      @blur="blur"
    />
    <Input
      v-else
      :id="inputId"
      :ref="(element) => registerElement(element?.$el ?? element)"
      :model-value="value"
      :name="name"
      :type="String(field.inputType ?? 'text')"
      :placeholder="field.placeholder ?? undefined"
      :disabled="disabled"
      :readonly="readonly"
      :aria-invalid="Boolean(error)"
      v-bind="field.dataAttributes ?? {}"
      @update:model-value="setValue"
      @blur="blur"
    />
  </FieldShell>
</template>
