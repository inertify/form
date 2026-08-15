<script setup lang="ts">
import { onMounted } from "vue";
import {
  useFormCombobox,
  type FormField,
  type UseFormApi,
} from "@inertify/form-vue";
import Button from "@/components/ui/Button.vue";
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
  setValue: (value: unknown) => void;
  blur: () => Promise<boolean>;
  registerElement: (element: HTMLElement | null) => void;
}>();

const combobox = useFormCombobox(props.field, props.form);
const inputId = `field-${props.name.replace(/[^a-z0-9_-]/gi, "-")}`;

onMounted(async () => {
  await combobox.hydrateSelected();

  if (combobox.options.value.length === 0) {
    await combobox.load();
  }
});

function choose(value: unknown): void {
  props.setValue(value);
  void combobox.hydrateSelected();
}
</script>

<template>
  <FieldShell
    :id="inputId"
    :label="field.label"
    :help="field.help"
    :error="error ?? combobox.error.value"
    :required="required"
  >
    <Input
      :id="inputId"
      :ref="(element) => registerElement(element?.$el ?? element)"
      v-model="combobox.search.value"
      type="search"
      :disabled="disabled"
      :placeholder="field.placeholder ?? 'Search skills…'"
      autocomplete="off"
      role="combobox"
      :aria-expanded="combobox.options.value.length > 0"
      @blur="blur"
    />

    <div class="overflow-hidden rounded-md border bg-card">
      <button
        v-for="option in combobox.options.value"
        :key="String(option.value)"
        type="button"
        class="flex w-full items-center justify-between border-b px-3 py-2 text-left text-sm last:border-0 hover:bg-muted"
        :class="{ 'bg-muted font-medium': String(value) === String(option.value) }"
        @click="choose(option.value)"
      >
        <span>{{ option.label }}</span>
        <span v-if="String(value) === String(option.value)" aria-hidden="true">✓</span>
      </button>

      <p v-if="combobox.loading.value" class="px-3 py-2 text-sm text-muted-foreground">
        Loading options…
      </p>
      <p
        v-else-if="combobox.options.value.length === 0"
        class="px-3 py-2 text-sm text-muted-foreground"
      >
        No matching skills.
      </p>
    </div>

    <div class="flex items-center justify-between gap-3 text-xs text-muted-foreground">
      <span>Selected: {{ value || "none" }}</span>
      <Button
        v-if="combobox.hasMore.value"
        variant="ghost"
        :disabled="combobox.loading.value"
        @click="combobox.loadMore"
      >
        Load more
      </Button>
    </div>
  </FieldShell>
</template>
