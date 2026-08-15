<script setup lang="ts">
import { computed, ref } from "vue";
import type { FormField } from "@inertify/form-vue";
import Button from "@/components/ui/Button.vue";
import Input from "@/components/ui/Input.vue";
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
  multiline?: boolean;
  setValue: (value: unknown) => void;
  blur: () => Promise<boolean>;
  registerElement: (element: HTMLElement | null) => void;
}>();

const inputId = `field-${props.name.replace(/[^a-z0-9_-]/gi, "-")}`;
const passwordVisible = ref(false);
const inputType = computed(() => {
  const configured = String(props.field.inputType ?? "text");

  return configured === "password" && passwordVisible.value ? "text" : configured;
});
const autocomplete = computed(() => {
  if (props.field.autocomplete === true) {
    return "on";
  }

  if (props.field.autocomplete === false) {
    return "off";
  }

  return typeof props.field.autocomplete === "string"
    ? props.field.autocomplete
    : undefined;
});

async function copy(): Promise<void> {
  if (typeof navigator !== "undefined" && navigator.clipboard && props.value != null) {
    await navigator.clipboard.writeText(String(props.value));
  }
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
    <Textarea
      v-if="multiline"
      :id="inputId"
      :ref="(element) => registerElement(element?.$el ?? element)"
      :model-value="value"
      :name="name"
      :placeholder="field.placeholder ?? undefined"
      :minlength="field.minLength ?? undefined"
      :maxlength="field.maxLength ?? undefined"
      :disabled="disabled"
      :readonly="readonly"
      :aria-invalid="Boolean(error)"
      v-bind="field.dataAttributes ?? {}"
      @update:model-value="setValue"
      @blur="blur"
    />
    <div v-else class="flex rounded-md shadow-sm">
      <Input
        :id="inputId"
        :ref="(element) => registerElement(element?.$el ?? element)"
        :model-value="value"
        :name="name"
        :type="inputType"
        :placeholder="field.placeholder ?? undefined"
        :min="field.min ?? undefined"
        :max="field.max ?? undefined"
        :step="field.step ?? undefined"
        :minlength="field.minLength ?? undefined"
        :maxlength="field.maxLength ?? undefined"
        :pattern="field.pattern ?? undefined"
        :autocomplete="autocomplete"
        :inputmode="field.inputMode ?? undefined"
        :enterkeyhint="field.enterKeyHint ?? undefined"
        :autofocus="field.autofocus === true"
        :disabled="disabled"
        :readonly="readonly"
        :aria-invalid="Boolean(error)"
        :class="field.clearable === true || field.copyable === true || field.viewable === true ? 'rounded-r-none' : undefined"
        v-bind="field.dataAttributes ?? {}"
        @update:model-value="setValue"
        @blur="blur"
      />
      <div
        v-if="field.clearable === true || field.copyable === true || field.viewable === true"
        class="flex items-center rounded-r-md border border-l-0 bg-card p-0.5"
      >
        <Button
          v-if="field.viewable === true && field.inputType === 'password'"
          variant="ghost"
          :aria-label="passwordVisible ? 'Hide value' : 'Show value'"
          class="h-7 px-2 text-xs"
          @click="passwordVisible = !passwordVisible"
        >
          {{ passwordVisible ? "Hide" : "Show" }}
        </Button>
        <Button
          v-if="field.copyable === true"
          variant="ghost"
          aria-label="Copy value"
          class="h-7 px-2 text-xs"
          @click="copy"
        >
          Copy
        </Button>
        <Button
          v-if="field.clearable === true && value"
          variant="ghost"
          aria-label="Clear value"
          class="h-7 px-2 text-xs"
          @click="setValue('')"
        >
          Clear
        </Button>
      </div>
    </div>
  </FieldShell>
</template>
