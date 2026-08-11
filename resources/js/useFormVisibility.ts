import { computed } from "vue";
import { useFormContext } from "./context";
import type { UseFormApi, UseFormVisibilityApi } from "./types";

export function useFormVisibility(
  formApi?: UseFormApi,
): UseFormVisibilityApi {
  const form = formApi ?? useFormContext();

  return {
    visibleFields: computed(() => form.visibleFields.value),
    hiddenFields: computed(() =>
      form.fields.value.filter((field) => !form.isVisible(field)),
    ),
    visibleFieldsets: computed(() => form.visibleFieldsets.value),
    hiddenFieldsets: computed(() =>
      form.fieldsets.value.filter(
        (fieldset) => !form.isFieldsetVisible(fieldset),
      ),
    ),
    isVisible: form.isVisible,
    isFieldsetVisible: form.isFieldsetVisible,
  };
}
