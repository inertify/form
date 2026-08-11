<script setup lang="ts">
import { Head, Link } from "@inertiajs/vue3";
import {
  HeadlessForm,
  HeadlessFormErrors,
  HeadlessFormFields,
  HeadlessFormSubmit,
  HeadlessFormWizard,
  type FormResource,
} from "@inertify/form-vue";
import CheckboxField from "@/components/form/CheckboxField.vue";
import FileField from "@/components/form/FileField.vue";
import RemoteComboboxField from "@/components/form/RemoteComboboxField.vue";
import RepeaterField from "@/components/form/RepeaterField.vue";
import TextField from "@/components/form/TextField.vue";
import Button from "@/components/ui/Button.vue";
import Card from "@/components/ui/Card.vue";

defineProps<{
  form: FormResource;
  mode: "create" | "edit";
  flash?: {
    success?: string | null;
  };
}>();
</script>

<template>
  <Head :title="mode === 'create' ? 'Create profile' : 'Edit profile'" />

  <main class="mx-auto min-h-screen max-w-4xl px-4 py-10 sm:px-6">
    <header class="mb-8 flex flex-col gap-5 sm:flex-row sm:items-end sm:justify-between">
      <div>
        <p class="mb-2 text-sm font-medium text-primary">Headless Inertify Form workbench</p>
        <h1 class="text-3xl font-semibold tracking-tight">
          {{ mode === "create" ? "Create a profile" : "Edit the bound profile" }}
        </h1>
        <p class="mt-2 max-w-2xl text-sm leading-6 text-muted-foreground">
          Every visible element below belongs to the example app. The package supplies schema,
          state, controllers, validation, uploads, and wizard behavior only.
        </p>
      </div>

      <nav class="flex rounded-lg border bg-card p-1" aria-label="Demo mode">
        <Link
          href="/"
          class="rounded-md px-3 py-2 text-sm font-medium"
          :class="mode === 'create' ? 'bg-primary text-primary-foreground' : 'hover:bg-muted'"
        >
          Create
        </Link>
        <Link
          href="/edit"
          class="rounded-md px-3 py-2 text-sm font-medium"
          :class="mode === 'edit' ? 'bg-primary text-primary-foreground' : 'hover:bg-muted'"
        >
          Edit
        </Link>
      </nav>
    </header>

    <div
      v-if="flash?.success"
      class="mb-5 rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-900"
      role="status"
    >
      {{ flash.success }}
    </div>

    <HeadlessForm :form="form">
      <template #default="{ form: formApi, submit, isDirty, processing }">
        <form class="space-y-5" novalidate @submit.prevent="submit()">
          <HeadlessFormErrors :form="formApi">
            <template #default="{ entries, hasErrors }">
              <Card v-if="hasErrors" class="border-destructive/30">
                <p class="font-medium text-destructive">Please review these fields:</p>
                <ul class="mt-2 list-disc space-y-1 pl-5 text-sm text-destructive">
                  <li v-for="entry in entries" :key="entry.name">
                    <a :href="`#field-${entry.name.replace(/[^a-z0-9_-]/gi, '-')}`">
                      {{ entry.message }}
                    </a>
                  </li>
                </ul>
              </Card>
            </template>
          </HeadlessFormErrors>

          <HeadlessFormWizard :form="formApi">
            <template
              #default="{
                steps,
                current,
                currentIndex,
                completed,
                isFirst,
                isLast,
                labels,
                goTo,
                next,
                previous,
              }"
            >
              <ol class="grid gap-2 sm:grid-cols-3" aria-label="Form progress">
                <li v-for="step in steps" :key="step.id">
                  <button
                    type="button"
                    class="w-full rounded-lg border px-3 py-3 text-left text-sm transition hover:bg-muted"
                    :class="{
                      'border-primary bg-primary/5': step.index === currentIndex,
                      'text-muted-foreground': step.index !== currentIndex,
                    }"
                    :aria-current="step.index === currentIndex ? 'step' : undefined"
                    @click="goTo(step.index)"
                  >
                    <span class="block text-xs uppercase tracking-wide">
                      {{ completed.has(step.id) ? "Complete" : `Step ${step.index + 1}` }}
                    </span>
                    <strong class="mt-1 block text-foreground">{{ step.label }}</strong>
                  </button>
                </li>
              </ol>

              <Card v-if="current">
                <header class="mb-6 border-b pb-4">
                  <h2 class="text-xl font-semibold">{{ current.label }}</h2>
                  <p v-if="current.description" class="mt-1 text-sm text-muted-foreground">
                    {{ current.description }}
                  </p>
                </header>

                <div class="space-y-6">
                  <HeadlessFormFields :form="formApi" :fields="current.fieldset.fields">
                    <template #type-text-input="slot">
                      <TextField v-bind="slot" />
                    </template>

                    <template #type-textarea="slot">
                      <TextField v-bind="slot" multiline />
                    </template>

                    <template #type-checkbox="slot">
                      <CheckboxField v-bind="slot" />
                    </template>

                    <template #type-combobox="slot">
                      <RemoteComboboxField v-bind="slot" />
                    </template>

                    <template #type-repeater="slot">
                      <RepeaterField v-bind="slot" />
                    </template>

                    <template #type-file="slot">
                      <FileField v-bind="slot" />
                    </template>

                    <template #type-submit />

                    <template #default="{ field }">
                      <p class="rounded-md border border-dashed p-3 text-sm text-muted-foreground">
                        Add app markup for <code>{{ field.component }}</code> using its type slot.
                      </p>
                    </template>
                  </HeadlessFormFields>
                </div>

                <footer class="mt-8 flex items-center justify-between gap-3 border-t pt-5">
                  <Button variant="outline" :disabled="isFirst || processing" @click="previous">
                    {{ labels.previous }}
                  </Button>

                  <Button v-if="!isLast" :disabled="processing" @click="next">
                    {{ labels.next }}
                  </Button>

                  <HeadlessFormSubmit v-else :form="formApi">
                    <template #default="{ processing: submitting, canSubmit }">
                      <Button type="submit" :disabled="!canSubmit || submitting">
                        {{ submitting ? "Saving…" : labels.submit }}
                      </Button>
                    </template>
                  </HeadlessFormSubmit>
                </footer>
              </Card>
            </template>
          </HeadlessFormWizard>

          <p class="text-center text-xs text-muted-foreground">
            {{ isDirty ? "You have unsaved changes." : "Form values match their defaults." }}
          </p>
        </form>
      </template>
    </HeadlessForm>
  </main>
</template>
