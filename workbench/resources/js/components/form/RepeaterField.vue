<script setup lang="ts">
import {
  HeadlessFormCollection,
  type FormField,
  type UseFormApi,
} from "@inertify/form-vue";
import Button from "@/components/ui/Button.vue";
import Input from "@/components/ui/Input.vue";
import Textarea from "@/components/ui/Textarea.vue";
import FieldShell from "./FieldShell.vue";

const props = defineProps<{
  field: FormField;
  form: UseFormApi;
  name: string;
  error: string | null;
  required: boolean;
}>();

function row(item: unknown): Record<string, unknown> {
  return typeof item === "object" && item !== null
    ? (item as Record<string, unknown>)
    : {};
}

function updateRow(
  update: (index: number, value: unknown) => void,
  item: unknown,
  index: number,
  key: string,
  value: unknown,
): void {
  update(index, { ...row(item), [key]: value });
}

function nestedError(index: number, key: string): string | null {
  return props.form.errors.value[`${props.name}.${index}.${key}`] ?? null;
}
</script>

<template>
  <FieldShell
    :id="`field-${name}`"
    :label="field.label"
    :help="field.help"
    :error="error"
    :required="required"
  >
    <HeadlessFormCollection :form="form" :name="name">
      <template #default="{ items, keys, append, update, remove, move }">
        <div class="space-y-3">
          <article
            v-for="(item, index) in items"
            :key="keys[index]"
            class="space-y-3 rounded-lg border bg-muted/30 p-4"
          >
            <div class="flex items-center justify-between gap-3">
              <strong class="text-sm">Project {{ index + 1 }}</strong>
              <div class="flex gap-1">
                <Button
                  variant="ghost"
                  :disabled="index === 0"
                  aria-label="Move project up"
                  @click="move(index, index - 1)"
                >
                  ↑
                </Button>
                <Button
                  variant="ghost"
                  :disabled="index === items.length - 1"
                  aria-label="Move project down"
                  @click="move(index, index + 1)"
                >
                  ↓
                </Button>
                <Button
                  variant="destructive"
                  :disabled="items.length <= Number(field.minItems ?? 0)"
                  @click="remove(index)"
                >
                  Remove
                </Button>
              </div>
            </div>

            <Input
              :model-value="row(item).title"
              :name="`${name}.${index}.title`"
              placeholder="Project title"
              :aria-invalid="Boolean(nestedError(index, 'title'))"
              @update:model-value="updateRow(update, item, index, 'title', $event)"
            />
            <p v-if="nestedError(index, 'title')" class="text-sm text-destructive">
              {{ nestedError(index, "title") }}
            </p>
            <Textarea
              :model-value="row(item).summary"
              :name="`${name}.${index}.summary`"
              placeholder="A short summary"
              @update:model-value="updateRow(update, item, index, 'summary', $event)"
            />
          </article>

          <Button
            variant="outline"
            :disabled="items.length >= Number(field.maxItems ?? Infinity)"
            @click="append({ title: '', summary: '' })"
          >
            Add project
          </Button>
        </div>
      </template>
    </HeadlessFormCollection>
  </FieldShell>
</template>
