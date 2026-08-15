<script setup lang="ts">
import { computed } from "vue";
import type { FormField } from "@inertify/form-vue";
import Input from "@/components/ui/Input.vue";
import Select from "@/components/ui/Select.vue";
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
const structured = computed(() =>
  props.field.mode === "structured" ||
  props.field.withLabel === true ||
  props.field.withTarget === true,
);

function record(): Record<string, unknown> {
  return typeof props.value === "object" && props.value !== null && !Array.isArray(props.value)
    ? (props.value as Record<string, unknown>)
    : { url: typeof props.value === "string" ? props.value : "" };
}

function part(key: string): string {
  const value = record()[key];

  return typeof value === "string" ? value : "";
}

function update(key: string, value: string): void {
  props.setValue({ ...record(), [key]: value });
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
    <Input
      v-if="!structured"
      :id="inputId"
      :ref="(element) => registerElement(element?.$el ?? element)"
      type="url"
      :name="name"
      :model-value="value"
      :placeholder="field.placeholder ?? 'https://example.com'"
      :disabled="disabled"
      :readonly="readonly"
      :aria-invalid="Boolean(error)"
      v-bind="field.dataAttributes ?? {}"
      @update:model-value="setValue"
      @blur="blur"
    />

    <div v-else class="grid gap-3 sm:grid-cols-2">
      <Input
        :id="inputId"
        :ref="(element) => registerElement(element?.$el ?? element)"
        type="url"
        :name="`${name}.url`"
        :model-value="part('url')"
        placeholder="https://example.com"
        :disabled="disabled"
        :readonly="readonly"
        :aria-invalid="Boolean(error)"
        v-bind="field.dataAttributes ?? {}"
        @update:model-value="update('url', $event)"
        @blur="blur"
      />
      <Input
        v-if="field.withLabel === true"
        :name="`${name}.label`"
        :model-value="part('label')"
        placeholder="Link label"
        :disabled="disabled"
        :readonly="readonly"
        @update:model-value="update('label', $event)"
      />
      <Select
        v-if="field.withTarget === true"
        :name="`${name}.target`"
        :model-value="part('target')"
        :options="[
          { value: '_self', label: 'Same frame' },
          { value: '_blank', label: 'New tab' },
          { value: '_parent', label: 'Parent frame' },
          { value: '_top', label: 'Top frame' },
        ]"
        placeholder="Same context"
        :disabled="disabled"
        :readonly="readonly"
        @update:model-value="update('target', typeof $event === 'string' ? $event : '')"
      />
    </div>
  </FieldShell>
</template>
