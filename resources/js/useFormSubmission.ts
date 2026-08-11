import { computed } from "vue";
import { useFormContext } from "./context";
import type { UseFormApi, UseFormSubmissionApi } from "./types";

export function useFormSubmission(
  formApi?: UseFormApi,
): UseFormSubmissionApi {
  const form = formApi ?? useFormContext();

  return {
    processing: computed(() => form.processing.value),
    progress: computed(() => form.progress.value),
    wasSuccessful: computed(() => form.wasSuccessful.value),
    recentlySuccessful: computed(() => form.recentlySuccessful.value),
    hasErrors: computed(() => form.hasErrors.value),
    firstErrorPath: computed(() => form.firstErrorPath.value),
    canSubmit: computed(
      () => form.resource.value.action !== null && !form.processing.value,
    ),
    submit: form.submit,
    cancel: form.cancel,
  };
}
