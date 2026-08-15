<script setup lang="ts">
import { computed } from "vue";
import type { FormField } from "@inertify/form-vue";
import Switch from "@/components/ui/Switch.vue";
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
const onValue = computed(() => props.field.onValue ?? props.field.trueValue ?? true);
const offValue = computed(() => props.field.offValue ?? props.field.falseValue ?? false);
const enabled = computed(() => props.value === onValue.value);

function update(value: boolean): void {
  props.setValue(value ? onValue.value : offValue.value);
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
    <div class="flex items-center justify-between gap-4 rounded-lg border bg-card p-4">
      <div>
        <p class="text-sm font-medium">
          {{ enabled ? (field.onLabel ?? "Enabled") : (field.offLabel ?? "Disabled") }}
        </p>
        <p class="mt-1 text-xs text-muted-foreground">Toggle this preference with a Shadcn Vue Switch.</p>
      </div>
      <Switch
        :id="inputId"
        :ref="(element) => registerElement(element?.$el ?? element)"
        :model-value="enabled"
        :name="name"
        :disabled="disabled"
        :readonly="readonly"
        :required="required"
        @update:model-value="update"
        @blur="blur"
      />
    </div>
  </FieldShell>
</template>
