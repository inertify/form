import { computed } from "vue";
import { useFormContext } from "./context";
import { getFormRuntime } from "./internal/runtime";
import type { UseFormApi, UseFormValidationApi } from "./types";

const cache = new WeakMap<object, UseFormValidationApi>();

export function useFormValidation(
  formApi?: UseFormApi,
): UseFormValidationApi {
  const form = formApi ?? useFormContext();
  const cached = cache.get(form);

  if (cached) {
    return cached;
  }

  const runtime = getFormRuntime(form).validation;
  const resolvers = new Map<string, (valid: boolean) => void>();
  const validating = computed(() => Object.keys(runtime.pending).length > 0);

  function isValidating(path: string): boolean {
    return runtime.pending[path] === true;
  }

  function validateDebounced(path: string, delay = 300): Promise<boolean> {
    const existing = runtime.timers.get(path);

    if (existing) {
      clearTimeout(existing);
      runtime.timers.delete(path);
      resolvers.get(path)?.(false);
    }

    return new Promise((resolve) => {
      resolvers.set(path, resolve);
      const timer = setTimeout(() => {
        runtime.timers.delete(path);
        resolvers.delete(path);
        void form.validate(path).then(resolve);
      }, Math.max(0, delay));
      runtime.timers.set(path, timer);
    });
  }

  function cancel(path?: string): void {
    if (path) {
      resolvers.get(path)?.(false);
      resolvers.delete(path);
    } else {
      for (const resolve of resolvers.values()) {
        resolve(false);
      }

      resolvers.clear();
    }

    runtime.cancel(path);
  }

  function clear(path?: string): void {
    if (path) {
      form.clearErrors(path);

      return;
    }

    form.clearErrors();
  }

  const api: UseFormValidationApi = {
    pending: runtime.pending,
    validating,
    isValidating,
    validate: form.validate,
    validateMany: runtime.validateMany,
    validateDebounced,
    cancel,
    clear,
  };
  cache.set(form, api);

  return api;
}
