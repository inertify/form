import type { Reactive } from "vue";
import type {
  FormDataValues,
  UseFormApi,
  UseFormFieldApi,
} from "../types";

export interface ValidationRuntime {
  pending: Reactive<Record<string, boolean>>;
  controllers: Map<string, AbortController>;
  timers: Map<string, ReturnType<typeof setTimeout>>;
  validateMany: (paths: string[]) => Promise<boolean>;
  cancel: (path?: string) => void;
}

export interface FormRuntime {
  validation: ValidationRuntime;
  fieldControllers: Map<string, UseFormFieldApi>;
  cancelUploads?: () => void;
}

const runtimes = new WeakMap<object, FormRuntime>();

export function setFormRuntime<TData extends FormDataValues>(
  form: UseFormApi<TData>,
  runtime: FormRuntime,
): void {
  runtimes.set(form, runtime);
}

export function getFormRuntime<TData extends FormDataValues>(
  form: UseFormApi<TData>,
): FormRuntime {
  const runtime = runtimes.get(form);

  if (!runtime) {
    throw new Error("The form API was not initialized by useForm().");
  }

  return runtime;
}
