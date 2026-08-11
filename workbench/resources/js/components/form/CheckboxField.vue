<script setup lang="ts">
import type { FormField } from "@inertify/form-vue";

const props = defineProps<{
  field: FormField;
  name: string;
  value: unknown;
  error: string | null;
  disabled: boolean;
  readonly: boolean;
  setValue: (value: unknown) => void;
  blur: () => Promise<boolean>;
  registerElement: (element: HTMLElement | null) => void;
}>();

const inputId = `field-${props.name.replace(/[^a-z0-9_-]/gi, "-")}`;

function change(event: Event): void {
  const input = event.target as HTMLInputElement;
  props.setValue(input.checked ? (props.field.trueValue ?? true) : (props.field.falseValue ?? false));
}
</script>

<template>
  <div class="space-y-2">
    <label
      :for="inputId"
      class="flex cursor-pointer items-start gap-3 rounded-lg border bg-card p-4 text-sm"
    >
      <input
        :id="inputId"
        :ref="registerElement"
        type="checkbox"
        :name="name"
        :checked="value === (field.trueValue ?? true)"
        :disabled="disabled || readonly"
        class="mt-0.5 size-4 accent-primary"
        v-bind="field.dataAttributes ?? {}"
        @change="change"
        @blur="blur"
      />
      <span>
        <span class="block font-medium">{{ field.label }}</span>
        <span v-if="field.help" class="mt-1 block text-muted-foreground">{{ field.help }}</span>
      </span>
    </label>
    <p v-if="error" class="text-sm text-destructive" role="alert">{{ error }}</p>
  </div>
</template>
