<script setup lang="ts">
import { computed } from "vue";
import type { FormField } from "@inertify/form-vue";
import Slider from "@/components/ui/Slider.vue";
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
const modelValue = computed<number | number[] | null>(() => {
  if (Array.isArray(props.value)) {
    return props.value.map(Number).filter(Number.isFinite);
  }

  const value = Number(props.value);

  return Number.isFinite(value) ? value : null;
});
const display = computed(() => {
  const unit = typeof props.field.unit === "string" ? props.field.unit : "";

  return Array.isArray(modelValue.value)
    ? modelValue.value.map((value) => `${value}${unit}`).join(" – ")
    : `${modelValue.value ?? Number(props.field.min ?? 0)}${unit}`;
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
    <div class="rounded-lg border bg-card p-4">
      <div class="mb-3 flex items-center justify-between text-sm">
        <span class="text-muted-foreground">{{ field.range === true ? "Selected range" : "Selected value" }}</span>
        <output class="font-medium" :for="inputId">{{ display }}</output>
      </div>
      <Slider
        :id="inputId"
        :ref="(element) => registerElement(element?.$el ?? element)"
        :model-value="modelValue"
        :name="name"
        :min="Number(field.min ?? 0)"
        :max="Number(field.max ?? 100)"
        :step="Number(field.step ?? 1)"
        :range="field.range === true"
        :disabled="disabled"
        :readonly="readonly"
        :required="required"
        @update:model-value="setValue"
        @blur="blur"
      />
    </div>
  </FieldShell>
</template>
