<script setup lang="ts">
import { computed } from "vue";
import type { FormField } from "@inertify/form-vue";
import Checkbox from "@/components/ui/Checkbox.vue";

defineOptions({ inheritAttrs: false });

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
const checked = computed(() => props.value === (props.field.trueValue ?? true));

function change(value: boolean): void {
  props.setValue(value ? (props.field.trueValue ?? true) : (props.field.falseValue ?? false));
}
</script>

<template>
  <div class="space-y-2">
    <label
      :for="inputId"
      class="flex cursor-pointer items-start gap-3 rounded-lg border bg-card p-4 text-sm"
    >
      <Checkbox
        :id="inputId"
        :ref="(element) => registerElement(element?.input ?? element?.$el ?? element)"
        :name="name"
        :model-value="checked"
        :disabled="disabled"
        :readonly="readonly"
        class="mt-0.5"
        v-bind="field.dataAttributes ?? {}"
        @update:model-value="change"
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
