<script setup lang="ts">
import { computed } from "vue";
import type { FormField } from "@inertify/form-vue";
import Button from "@/components/ui/Button.vue";
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
const color = computed(() =>
  typeof props.value === "string" && /^#[\da-f]{6}$/i.test(props.value)
    ? props.value
    : typeof props.field.defaultColor === "string"
      ? props.field.defaultColor
      : "#000000",
);
const swatches = computed<string[]>(() =>
  Array.isArray(props.field.swatches)
    ? props.field.swatches.filter((item): item is string => typeof item === "string")
    : [],
);
</script>

<template>
  <FieldShell
    :id="inputId"
    :label="field.label"
    :help="field.help"
    :error="error"
    :required="required"
  >
    <div class="flex items-center gap-2">
      <input
        :id="`${inputId}-picker`"
        type="color"
        :value="color"
        :disabled="disabled || readonly"
        class="h-9 w-12 cursor-pointer rounded-md border bg-card p-1 disabled:cursor-not-allowed disabled:opacity-50"
        aria-label="Choose color"
        @input="setValue(($event.target as HTMLInputElement).value)"
      />
      <Input
        :id="inputId"
        :ref="(element) => registerElement(element?.$el ?? element)"
        :name="name"
        :model-value="value"
        placeholder="#2563eb"
        :disabled="disabled"
        :readonly="readonly"
        :aria-invalid="Boolean(error)"
        v-bind="field.dataAttributes ?? {}"
        @update:model-value="setValue"
        @blur="blur"
      />
      <Button
        v-if="field.clearable === true && value"
        variant="ghost"
        :disabled="disabled || readonly"
        @click="setValue(null)"
      >
        Clear
      </Button>
    </div>

    <div v-if="swatches.length" class="flex flex-wrap gap-2" aria-label="Color swatches">
      <button
        v-for="swatch in swatches"
        :key="swatch"
        type="button"
        class="size-7 rounded-full border ring-offset-2 transition focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary"
        :class="{ 'ring-2 ring-primary': value === swatch }"
        :style="{ backgroundColor: swatch }"
        :aria-label="`Use ${swatch}`"
        :disabled="disabled || readonly"
        @click="setValue(swatch)"
      />
    </div>
  </FieldShell>
</template>
