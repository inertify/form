import { computed, ref } from "vue";
import { useFormContext } from "./context";
import { getFormRuntime } from "./internal/runtime";
import { deepEqual } from "./internal/path";
import { fieldPath } from "./internal/resource";
import type {
  FormField,
  UseFormApi,
  UseFormFieldApi,
  UseFormFieldsApi,
} from "./types";

export function useFormField(
  fieldOrPath: FormField | string,
  formApi?: UseFormApi,
): UseFormFieldApi {
  const form = formApi ?? useFormContext();
  const path =
    typeof fieldOrPath === "string" ? fieldOrPath : fieldPath(fieldOrPath);
  const field = computed(() =>
    typeof fieldOrPath === "string"
      ? form.resolveField(fieldOrPath)
      : form.resolveField(path) ?? fieldOrPath,
  );
  const value = computed({
    get: () => form.getValue(path),
    set: (next: unknown) => form.setValue(path, next),
  });
  const errors = computed(() => {
    const output: string[] = [];

    for (const [key, message] of Object.entries(form.errors.value)) {
      if (key === path || key.startsWith(`${path}.`)) {
        output.push(message);
      }
    }

    return output;
  });
  const error = computed(() => errors.value[0] ?? null);
  const visible = computed(() => form.isVisible(field.value ?? path));
  const dirty = computed(() =>
    !deepEqual(form.getValue(path), form.getDefaultValue(path)),
  );
  const touched = computed(() => form.isTouched(path));
  const disabled = computed(() => field.value?.disabled === true);
  const readonly = computed(() => field.value?.readonly === true);
  const required = computed(() => hasRequiredRule(field.value?.rules));
  const element = ref<HTMLElement | null>(null);

  function setValue(next: unknown, markTouched = true): void {
    form.setValue(path, next, markTouched);
  }

  function touch(): void {
    form.touch(path);
  }

  function registerElement(next: HTMLElement | null): void {
    element.value = next;
    form.registerFieldElement(path, next);
  }

  async function validate(): Promise<boolean> {
    return form.validate(path);
  }

  async function blur(): Promise<boolean> {
    touch();

    return field.value?.precognitive === true ? validate() : true;
  }

  return {
    field,
    path,
    value,
    errors,
    error,
    visible,
    dirty,
    touched,
    disabled,
    readonly,
    required,
    element,
    setValue,
    touch,
    registerElement,
    blur,
    validate,
    reset: () => form.resetField(path),
    clear: () => form.clearField(path),
    clearErrors: () => form.clearErrors(path),
  };
}

export function useFormFields(formApi?: UseFormApi): UseFormFieldsApi {
  const form = formApi ?? useFormContext();
  const controllers = getFormRuntime(form).fieldControllers;

  function controller(fieldOrPath: FormField | string): UseFormFieldApi {
    const path =
      typeof fieldOrPath === "string" ? fieldOrPath : fieldPath(fieldOrPath);
    const existing = controllers.get(path);

    if (existing) {
      return existing;
    }

    const next = useFormField(fieldOrPath, form);
    controllers.set(path, next);

    return next;
  }

  return {
    fieldsets: computed(() => form.fieldsets.value),
    fields: computed(() => form.fields.value),
    visibleFieldsets: computed(() => form.visibleFieldsets.value),
    visibleFields: computed(() => form.visibleFields.value),
    get: form.resolveField,
    controller,
  };
}

function hasRequiredRule(rules: unknown): boolean {
  if (typeof rules === "string") {
    return rules.split("|").some((rule) => rule.split(":")[0] === "required");
  }

  if (Array.isArray(rules)) {
    return rules.some((rule) => hasRequiredRule(rule));
  }

  return false;
}
