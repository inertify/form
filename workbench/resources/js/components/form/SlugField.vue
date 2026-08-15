<script setup lang="ts">
import { ref, watch } from "vue";
import type { FormField, UseFormApi } from "@inertify/form-vue";
import Input from "@/components/ui/Input.vue";
import FieldShell from "./FieldShell.vue";

defineOptions({ inheritAttrs: false });

const props = defineProps<{
  field: FormField;
  form: UseFormApi;
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
const manuallyEdited = ref(false);

watch(
  () => typeof props.field.from === "string" ? props.form.getValue(props.field.from) : undefined,
  (source) => {
    if (
      typeof source !== "string" ||
      manuallyEdited.value && props.field.lockOnManualEdit !== false ||
      props.field.onlyWhenEmpty === true && Boolean(props.value)
    ) {
      return;
    }

    props.setValue(slugify(source));
  },
);

function slugify(value: string): string {
  const separator = typeof props.field.separator === "string" ? props.field.separator : "-";
  const slug = value
    .normalize("NFKD")
    .replace(/[\u0300-\u036f]/g, "")
    .trim()
    .replace(/[^a-zA-Z0-9]+/g, separator)
    .replace(new RegExp(`^${escapeRegExp(separator)}+|${escapeRegExp(separator)}+$`, "g"), "");

  return props.field.lowercase === false ? slug : slug.toLowerCase();
}

function escapeRegExp(value: string): string {
  return value.replace(/[.*+?^${}()|[\]\\]/g, "\\$&");
}

function update(value: string): void {
  manuallyEdited.value = true;
  props.setValue(slugify(value));
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
    <div class="flex rounded-md shadow-sm">
      <span class="inline-flex items-center rounded-l-md border border-r-0 bg-muted px-3 text-xs text-muted-foreground">
        /profiles/
      </span>
      <Input
        :id="inputId"
        :ref="(element) => registerElement(element?.$el ?? element)"
        :model-value="value"
        :name="name"
        :placeholder="field.placeholder ?? 'profile-slug'"
        :disabled="disabled"
        :readonly="readonly"
        :aria-invalid="Boolean(error)"
        class="rounded-l-none"
        v-bind="field.dataAttributes ?? {}"
        @update:model-value="update"
        @blur="blur"
      />
    </div>
  </FieldShell>
</template>
