import { computed, reactive, ref, watch } from "vue";
import { useFormContext } from "./context";
import {
  fieldPath,
  fieldsForFieldset,
  fieldsetId,
} from "./internal/resource";
import { useFormValidation } from "./useFormValidation";
import type {
  FormFieldset,
  FormWizardStep,
  UseFormApi,
  UseFormWizardApi,
  WizardLabels,
} from "./types";

const cache = new WeakMap<object, UseFormWizardApi>();

export function useFormWizard(formApi?: UseFormApi): UseFormWizardApi {
  const form = formApi ?? useFormContext();
  const cached = cache.get(form);

  if (cached) {
    return cached;
  }

  const validation = useFormValidation(form);
  const currentIndex = ref(0);
  const completed = reactive(new Set<string>());
  const enabled = computed(
    () => form.resource.value.wizard?.enabled === true,
  );
  const steps = computed<FormWizardStep[]>(() => {
    const wizardSteps = form.resource.value.wizard?.steps;

    return form.fieldsets.value
      .map((fieldset, originalIndex) => ({ fieldset, originalIndex }))
      .filter(({ fieldset }) => form.isFieldsetVisible(fieldset))
      .map(({ fieldset, originalIndex }, index) => {
        const metadata = findStepMetadata(wizardSteps, fieldset, originalIndex);

        return {
          id: fieldsetId(fieldset, originalIndex),
          index,
          label:
            stringValue(metadata?.label ?? metadata?.title) ??
            fieldset.legend ??
            `Step ${index + 1}`,
          description:
            stringValue(metadata?.description) ?? fieldset.description,
          fieldset,
          fields: fieldsForFieldset(form.fields.value, originalIndex).filter(
            (field) => form.isVisible(field),
          ),
        };
      });
  });
  const current = computed(() => steps.value[currentIndex.value] ?? null);
  const isFirst = computed(() => currentIndex.value <= 0);
  const isLast = computed(
    () => steps.value.length === 0 || currentIndex.value >= steps.value.length - 1,
  );
  const canPrevious = computed(() => !isFirst.value);
  const canNext = computed(() => !isLast.value);
  const labels = computed<WizardLabels>(() => {
    const wizard = form.resource.value.wizard;

    return {
      next: wizard?.nextLabel ?? wizard?.labels?.next ?? "Next",
      previous: wizard?.prevLabel ?? wizard?.labels?.previous ?? "Previous",
      submit: wizard?.submitLabel ?? wizard?.labels?.submit ?? "Submit",
    };
  });

  async function validateCurrent(): Promise<boolean> {
    const step = current.value;

    if (!step) {
      return true;
    }

    const paths = step.fields
      .filter((field) => field.precognitive === true)
      .map(fieldPath);
    const shouldValidate =
      form.resource.value.wizard?.validateOnStep === true;
    const valid = shouldValidate
      ? await validation.validateMany(paths)
      : true;

    return (
      valid &&
      !step.fields.some((field) =>
        Object.keys(form.errors.value).some(
          (key) =>
            key === fieldPath(field) || key.startsWith(`${fieldPath(field)}.`),
        ),
      )
    );
  }

  async function goTo(step: number | string): Promise<boolean> {
    const target =
      typeof step === "number"
        ? Math.max(0, Math.min(Math.floor(step), steps.value.length - 1))
        : steps.value.findIndex((candidate) => candidate.id === step);

    if (target < 0 || target === currentIndex.value) {
      return target === currentIndex.value;
    }

    if (target > currentIndex.value) {
      if (
        form.resource.value.wizard?.allowSkip !== true &&
        target > currentIndex.value + 1
      ) {
        return false;
      }

      if (!(await validateCurrent())) {
        return false;
      }

      if (current.value) {
        completed.add(current.value.id);
      }
    }

    currentIndex.value = target;

    return true;
  }

  async function next(): Promise<boolean> {
    if (!canNext.value) {
      return false;
    }

    return goTo(currentIndex.value + 1);
  }

  function previous(): boolean {
    if (!canPrevious.value) {
      return false;
    }

    currentIndex.value -= 1;

    return true;
  }

  function reset(): void {
    currentIndex.value = 0;
    completed.clear();
  }

  watch(
    () => steps.value.length,
    (length) => {
      currentIndex.value = Math.max(
        0,
        Math.min(currentIndex.value, Math.max(0, length - 1)),
      );
    },
  );

  const api: UseFormWizardApi = {
    enabled,
    steps,
    currentIndex,
    current,
    completed,
    isFirst,
    isLast,
    canPrevious,
    canNext,
    labels,
    goTo,
    next,
    previous,
    validateCurrent,
    reset,
  };
  cache.set(form, api);

  return api;
}

function findStepMetadata(
  candidates: unknown[] | undefined,
  fieldset: FormFieldset,
  originalIndex: number,
): Record<string, unknown> | null {
  if (!Array.isArray(candidates)) {
    return null;
  }

  return (
    candidates.find((candidate) => {
      if (typeof candidate !== "object" || candidate === null) {
        return false;
      }

      const item = candidate as Record<string, unknown>;

      return (
        item.fieldset === fieldset.id ||
        item.id === fieldset.id ||
        item.fieldsetIndex === originalIndex ||
        item.index === originalIndex
      );
    }) as Record<string, unknown> | undefined
  ) ?? null;
}

function stringValue(value: unknown): string | null {
  return typeof value === "string" && value !== "" ? value : null;
}
