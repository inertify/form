<script setup lang="ts">
import { Head, Link } from "@inertiajs/vue3";
import {
  Form,
  FormErrors,
  FormSubmit,
  FormWizard,
  type FormResource,
} from "@inertify/form-vue";
import { FormFields } from "@/components/form";
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

  <main class="mx-auto min-h-screen max-w-6xl px-4 py-10 sm:px-6">
    <header class="mb-8 flex flex-col gap-5 sm:flex-row sm:items-end sm:justify-between">
      <div>
        <p class="mb-2 text-sm font-medium text-primary">Inertify Form × Shadcn Vue workbench</p>
        <h1 class="text-3xl font-semibold tracking-tight">
          {{ mode === "create" ? "Create a profile" : "Edit the bound profile" }}
        </h1>
        <p class="mt-2 max-w-2xl text-sm leading-6 text-muted-foreground">
          Every package field is demonstrated with application-owned controls based on the
          Shadcn Vue form catalog. Inertify supplies schema, state, validation, uploads, and
          wizard behavior only.
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

    <Form
      :form="form"
      class="space-y-5"
      v-slot="{ form: formApi, isDirty, processing }"
    >
      <FormErrors :form="formApi" v-slot="{ entries, hasErrors }">
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
      </FormErrors>

      <FormWizard
        :form="formApi"
        v-slot="{
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
        <ol class="grid gap-2 sm:grid-cols-2 lg:grid-cols-4" aria-label="Form progress">
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
            <FormFields :form="formApi" :fields="current.fieldset.fields" />
          </div>

          <footer class="mt-8 flex items-center justify-between gap-3 border-t pt-5">
            <Button variant="outline" :disabled="isFirst || processing" @click="previous">
              {{ labels.previous }}
            </Button>

            <Button v-if="!isLast" :disabled="processing" @click="next">
              {{ labels.next }}
            </Button>

            <FormSubmit v-else :form="formApi" v-slot="{ processing: submitting, canSubmit }">
              <Button type="submit" :disabled="!canSubmit || submitting">
                {{ submitting ? "Saving…" : labels.submit }}
              </Button>
            </FormSubmit>
          </footer>
        </Card>
      </FormWizard>

      <p class="text-center text-xs text-muted-foreground">
        {{ isDirty ? "You have unsaved changes." : "Form values match their defaults." }}
      </p>
    </Form>
  </main>
</template>
