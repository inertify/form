<script setup lang="ts">
import {
  FormUploads,
  type FormField,
  type UseFormApi,
} from "@inertify/form-vue";
import Textarea from "@/components/ui/Textarea.vue";
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

function composer(): { text: string; attachments: unknown[] } {
  if (typeof props.value === "object" && props.value !== null && !Array.isArray(props.value)) {
    const value = props.value as Record<string, unknown>;

    return {
      text: typeof value.text === "string" ? value.text : "",
      attachments: Array.isArray(value.attachments) ? value.attachments : [],
    };
  }

  return {
    text: typeof props.value === "string" ? props.value : "",
    attachments: [],
  };
}

function updateText(text: string): void {
  props.setValue(
    props.field.allowAttachments === true
      ? { ...composer(), text }
      : text,
  );
}

function selected(
  event: Event,
  upload: (files: FileList) => Promise<unknown>,
): void {
  const files = (event.target as HTMLInputElement).files;

  if (files?.length) {
    void upload(files).catch(() => undefined);
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
    <div class="overflow-hidden rounded-lg border bg-card shadow-sm">
      <Textarea
        :id="inputId"
        :ref="(element) => registerElement(element?.$el ?? element)"
        :name="field.allowAttachments === true ? `${name}.text` : name"
        :model-value="composer().text"
        :placeholder="field.placeholder ?? 'Write a message…'"
        :maxlength="field.maxLength ?? undefined"
        :disabled="disabled"
        :readonly="readonly"
        class="min-h-28 resize-y rounded-none border-0 shadow-none focus:ring-0"
        v-bind="field.dataAttributes ?? {}"
        @update:model-value="updateText"
        @blur="blur"
      />

      <FormUploads
        v-if="field.allowAttachments === true"
        :form="form"
        :name="`${name}.attachments`"
        v-slot="{ state, upload, remove }"
      >
        <div class="border-t bg-muted/30 p-3">
          <ul v-if="state.files.length" class="mb-3 flex flex-wrap gap-2">
            <li
              v-for="(file, index) in state.files"
              :key="file.key"
              class="inline-flex items-center gap-2 rounded-md border bg-card px-2 py-1 text-xs"
            >
              <span class="max-w-40 truncate">{{ file.name }}</span>
              <button type="button" aria-label="Remove attachment" @click="remove(index)">×</button>
            </li>
          </ul>
          <div class="flex items-center justify-between gap-3">
            <label class="cursor-pointer text-sm font-medium text-primary">
              Add attachment
              <input
                type="file"
                class="sr-only"
                :multiple="true"
                :disabled="disabled || readonly || state.status === 'uploading'"
                :accept="Array.isArray(field.accept) ? field.accept.join(',') : field.accept"
                @change="selected($event, upload)"
              />
            </label>
            <span class="text-xs text-muted-foreground">
              {{ state.status === "uploading" ? `${state.progress ?? 0}%` : `${state.files.length} attached` }}
            </span>
          </div>
        </div>
      </FormUploads>
    </div>
  </FieldShell>
</template>
