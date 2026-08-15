<script setup lang="ts">
import type { FormField } from "@inertify/form-vue";
import Textarea from "@/components/ui/Textarea.vue";
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
    :help="field.help ?? 'The workbench keeps rich-text markup application-owned and edits its HTML source.'"
    :error="error"
    :required="required"
  >
    <div class="overflow-hidden rounded-lg border bg-card shadow-sm">
      <div class="flex items-center gap-1 border-b bg-muted/40 px-3 py-2" aria-hidden="true">
        <span class="rounded px-2 py-1 text-xs font-bold">B</span>
        <span class="rounded px-2 py-1 text-xs italic">I</span>
        <span class="rounded px-2 py-1 text-xs">Link</span>
        <span class="ml-auto text-xs text-muted-foreground">HTML source</span>
      </div>
      <Textarea
        :id="inputId"
        :ref="(element) => registerElement(element?.$el ?? element)"
        :name="name"
        :model-value="value"
        :placeholder="field.placeholder ?? '<p>Introduce yourself…</p>'"
        :maxlength="field.maxLength ?? undefined"
        :disabled="disabled"
        :readonly="readonly"
        :aria-invalid="Boolean(error)"
        class="min-h-36 resize-y rounded-none border-0 font-mono shadow-none focus:ring-0"
        v-bind="field.dataAttributes ?? {}"
        @update:model-value="setValue"
        @blur="blur"
      />
    </div>
  </FieldShell>
</template>
