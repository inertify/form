<script setup lang="ts">
import {
  FormUploads,
  type FormField,
  type UseFormApi,
} from "@inertify/form-vue";
import Button from "@/components/ui/Button.vue";
import FieldShell from "./FieldShell.vue";

defineOptions({ inheritAttrs: false });

const props = defineProps<{
  field: FormField;
  form: UseFormApi;
  name: string;
  error: string | null;
  required: boolean;
  disabled: boolean;
  registerElement: (element: HTMLElement | null) => void;
}>();

const inputId = `field-${props.name.replace(/[^a-z0-9_-]/gi, "-")}`;

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
    <FormUploads :form="form" :name="name">
      <template #default="{ state, upload, remove, cancel, clear }">
        <input
          :id="inputId"
          :ref="registerElement"
          type="file"
          :name="name"
          :accept="Array.isArray(field.accept) ? field.accept.join(',') : field.accept"
          :multiple="field.multiple === true"
          :disabled="disabled || state.status === 'uploading'"
          class="block w-full rounded-md border bg-card px-3 py-2 text-sm file:mr-3 file:rounded file:border-0 file:bg-muted file:px-3 file:py-1 file:text-sm"
          @change="selected($event, upload)"
        />

        <div v-if="state.status === 'uploading'" class="space-y-2">
          <div class="h-2 overflow-hidden rounded-full bg-muted" aria-hidden="true">
            <div
              class="h-full bg-primary transition-all"
              :style="{ width: `${state.progress ?? 0}%` }"
            />
          </div>
          <div class="flex items-center justify-between text-xs text-muted-foreground">
            <span>{{ state.progress ?? 0 }}% uploaded</span>
            <Button variant="ghost" @click="cancel">Cancel</Button>
          </div>
        </div>

        <p v-if="state.error" class="text-sm text-destructive" role="alert">
          {{ state.error }}
        </p>

        <ul v-if="state.files.length" class="space-y-2">
          <li
            v-for="(file, index) in state.files"
            :key="file.key"
            class="flex items-center justify-between gap-3 rounded-md border px-3 py-2 text-sm"
          >
            <span class="truncate">{{ file.name }}</span>
            <Button variant="ghost" @click="remove(index)">Remove</Button>
          </li>
        </ul>

        <Button v-if="state.files.length" variant="ghost" @click="clear">
          Clear all
        </Button>
      </template>
    </FormUploads>
  </FieldShell>
</template>
