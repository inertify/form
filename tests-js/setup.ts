import { reactive } from "vue";
import { beforeEach, vi } from "vitest";
import { deepClone } from "../resources/js/internal/path";

interface MockGlobals {
  __inertifySubmissions?: Array<Record<string, unknown>>;
  __inertifyNextErrors?: Record<string, string> | null;
  __inertifyBeforeListeners?: Set<(event: { preventDefault: () => void }) => void>;
  __inertifyReloads?: Array<Record<string, unknown>>;
  __inertifyReloadResource?: unknown;
  __inertifyReloadHandler?: ((options: Record<string, unknown>) => void) | null;
  __inertifyHoldSubmission?: boolean;
}

const globals = globalThis as typeof globalThis & MockGlobals;
globals.__inertifySubmissions = [];
globals.__inertifyNextErrors = null;
globals.__inertifyBeforeListeners = new Set();
globals.__inertifyReloads = [];
globals.__inertifyReloadResource = null;
globals.__inertifyReloadHandler = null;
globals.__inertifyHoldSubmission = false;

vi.mock("@inertiajs/vue3", () => ({
  router: {
    on(event: string, callback: (event: { preventDefault: () => void }) => void) {
      if (event === "before") {
        globals.__inertifyBeforeListeners?.add(callback);
      }

      return () => globals.__inertifyBeforeListeners?.delete(callback);
    },
    reload(options: Record<string, unknown>) {
      globals.__inertifyReloads?.push(options);

      if (globals.__inertifyReloadHandler) {
        globals.__inertifyReloadHandler(options);

        return;
      }

      const success = options.onSuccess as
        | ((page: unknown) => void)
        | undefined;
      const finish = options.onFinish as (() => void) | undefined;
      success?.({ props: { form: globals.__inertifyReloadResource } });
      finish?.();
    },
  },
  useForm(initial: Record<string, unknown>) {
    let defaults = deepClone(initial);
    let transform = (data: Record<string, unknown>) => data;
    const dataKeys = new Set(Object.keys(initial));
    let activeOptions: Record<string, unknown> | null = null;
    const form = reactive({
      ...deepClone(initial),
      errors: {} as Record<string, string>,
      hasErrors: false,
      processing: false,
      progress: null,
      wasSuccessful: false,
      recentlySuccessful: false,
      isDirty: false,
      data() {
        return Object.fromEntries(
          [...dataKeys].map((key) => [key, deepClone(form[key])]),
        );
      },
      transform(callback: (data: Record<string, unknown>) => Record<string, unknown>) {
        transform = callback;
        return form;
      },
      defaults(values?: Record<string, unknown>) {
        defaults = deepClone(values ?? form.data());
        Object.keys(defaults).forEach((key) => dataKeys.add(key));
        return form;
      },
      reset(...fields: string[]) {
        const keys = fields.length > 0 ? fields : [...dataKeys];
        keys.forEach((key) => {
          form[key] = deepClone(defaults[key]);
        });
      },
      setError(field: string | Record<string, string>, value?: string) {
        if (typeof field === "string") {
          if (value !== undefined) form.errors[field] = value;
        } else {
          Object.assign(form.errors, field);
        }
        form.hasErrors = Object.keys(form.errors).length > 0;
      },
      clearErrors(...fields: string[]) {
        if (fields.length === 0) {
          Object.keys(form.errors).forEach((key) => delete form.errors[key]);
        } else {
          fields.forEach((key) => delete form.errors[key]);
        }
        form.hasErrors = Object.keys(form.errors).length > 0;
      },
      submit(method: string, url: string, options: Record<string, unknown> = {}) {
        const before = options.onBefore as ((visit: unknown) => boolean | void) | undefined;
        if (before?.({}) === false) return;
        form.processing = true;
        activeOptions = options;
        (options.onStart as ((visit: unknown) => void) | undefined)?.({});
        (options.onProgress as ((progress: unknown) => void) | undefined)?.({
          percentage: 50,
        });
        const payload = transform(form.data());
        globals.__inertifySubmissions?.push({ method, url, data: payload, options });

        if (globals.__inertifyHoldSubmission) {
          return;
        }

        const nextErrors = globals.__inertifyNextErrors;
        if (nextErrors) {
          form.setError(nextErrors);
          (options.onError as ((errors: Record<string, string>) => void) | undefined)?.(
            nextErrors,
          );
          globals.__inertifyNextErrors = null;
        } else {
          form.wasSuccessful = true;
          form.recentlySuccessful = true;
          (options.onSuccess as ((page: unknown) => void) | undefined)?.({});
        }
        form.processing = false;
        (options.onFinish as ((visit: unknown) => void) | undefined)?.({});
        activeOptions = null;
      },
      cancel() {
        form.processing = false;
        (activeOptions?.onCancel as (() => void) | undefined)?.();
        (activeOptions?.onFinish as ((visit: unknown) => void) | undefined)?.({});
        activeOptions = null;
      },
    }) as Record<string, any>;

    return form;
  },
}));

beforeEach(() => {
  globals.__inertifySubmissions = [];
  globals.__inertifyNextErrors = null;
  globals.__inertifyReloads = [];
  globals.__inertifyReloadResource = null;
  globals.__inertifyReloadHandler = null;
  globals.__inertifyHoldSubmission = false;
  globals.__inertifyBeforeListeners?.clear();
  vi.restoreAllMocks();
});
