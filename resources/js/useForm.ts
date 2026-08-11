import { router, useForm as useInertiaForm } from "@inertiajs/vue3";
import {
  computed,
  getCurrentScope,
  onScopeDispose,
  reactive,
  ref,
  unref,
  watch,
  type MaybeRef,
} from "vue";
import { getFormRuntime, setFormRuntime } from "./internal/runtime";
import {
  fieldEmptyValue,
  fieldPath,
  fieldsForFieldset,
  fieldsetId,
  normalizedSlotToken,
  resolveFormFields,
} from "./internal/resource";
import { deepClone, deepEqual, getPath, setPath } from "./internal/path";
import { normalizeUploadData } from "./internal/uploadValues";
import { evaluateVisibility } from "./internal/visibility";
import { defaultValidationTransport } from "./transports/precognition";
import type {
  FormDataValues,
  FormField,
  FormFieldInstance,
  FormFieldset,
  FormMethod,
  FormProgress,
  FormResource,
  FormSubmitOptions,
  FormValidationResult,
  InertiaFormAdapter,
  NormalizedFormMethod,
  UseFormApi,
  UseFormOptions,
} from "./types";

let generatedFormId = 0;

export function useForm<TData extends FormDataValues = FormDataValues>(
  formResource: MaybeRef<FormResource<TData>>,
  options: UseFormOptions<TData> = {},
): UseFormApi<TData> {
  const resource = computed(() => unref(formResource));
  const fallbackFormId = `inertify-form-${++generatedFormId}`;
  const readonlyOptions = Object.freeze({ ...options }) as Readonly<
    UseFormOptions<TData>
  >;
  const initialData = normalizeUploadData(resource.value);
  const defaultsState = ref<TData>(deepClone(initialData));
  const syncedResourceData = ref<TData>(deepClone(initialData));
  const inertia = useInertiaForm(initialData as never) as unknown as InertiaFormAdapter<TData> &
    TData;
  const touched = reactive(new Set<string>());
  const fieldElements = new Map<string, HTMLElement>();
  const validationPending = reactive<Record<string, boolean>>({});
  const validationControllers = new Map<string, AbortController>();
  const validationTimers = new Map<string, ReturnType<typeof setTimeout>>();
  let transformCallback: (data: TData) => FormDataValues = (data) => data;
  let submittingOwnVisit = false;

  const formId = computed(() => {
    const meta = resource.value.meta;
    const candidate =
      (meta && (meta.formId ?? meta.id)) ?? resource.value.formId ?? resource.value.id;

    return typeof candidate === "string" && candidate !== ""
      ? candidate
      : fallbackFormId;
  });
  const data = computed(() => inertia.data());
  const rootData = data;
  const fieldsets = computed(() => resource.value.fieldsets ?? []);
  const fields = computed(() =>
    resolveFormFields(fieldsets.value, data.value),
  );
  const errors = computed(() => inertia.errors ?? {});
  const rootErrors = errors;
  const processing = computed(() => inertia.processing === true);
  const progress = computed<FormProgress | null>(() => inertia.progress ?? null);
  const wasSuccessful = computed(() => inertia.wasSuccessful === true);
  const recentlySuccessful = computed(
    () => inertia.recentlySuccessful === true,
  );
  const hasErrors = computed(() => Object.keys(errors.value).length > 0);
  const isDirty = computed(() => !deepEqual(data.value, defaultsState.value));
  const firstErrorPath = computed(() => Object.keys(errors.value)[0] ?? null);
  const shouldWarnOnUnsavedChanges = computed(
    () => resource.value.unsavedWarning === true && isDirty.value,
  );

  function resolveField(path: string): FormFieldInstance | null {
    const normalizedPath = path.startsWith("$.") ? path.slice(2) : path;

    return (
      fields.value.find(
        (field) =>
          field.path === normalizedPath || field.attribute === normalizedPath,
      ) ?? null
    );
  }

  function resolveFieldset(id: string): FormFieldset | null {
    return (
      fieldsets.value.find(
        (fieldset, index) => fieldsetId(fieldset, index) === id,
      ) ?? null
    );
  }

  function rawFieldsetVisible(fieldset: FormFieldset): boolean {
    return (
      fieldset.visible !== false &&
      evaluateVisibility(
        fieldset.visibility ?? fieldset.conditions,
        data.value,
      )
    );
  }

  function containingFieldset(field: FormField): FormFieldset | null {
    const instance = resolveFieldInput(field);

    return instance
      ? (fieldsets.value[instance.fieldsetIndex] ?? null)
      : null;
  }

  function isVisible(fieldOrPath: FormField | string): boolean {
    const field = resolveFieldInput(fieldOrPath);

    if (!field || field.authorized === false || field.visible === false) {
      return false;
    }

    const fieldset = containingFieldset(field);
    const rowValue = field.rowPath
      ? getPath(data.value, field.rowPath)
      : undefined;
    const rowData = isDataRecord(rowValue) ? rowValue : undefined;
    const ancestorsVisible = field.ancestorVisibility.every((condition) =>
      evaluateVisibility(condition, data.value, rowData),
    );

    return (
      (!fieldset || rawFieldsetVisible(fieldset)) &&
      ancestorsVisible &&
      evaluateVisibility(
        field.visibility ?? field.conditions,
        data.value,
        rowData,
      )
    );
  }

  function isFieldsetVisible(fieldsetOrId: FormFieldset | string): boolean {
    const fieldset =
      typeof fieldsetOrId === "string"
        ? resolveFieldset(fieldsetOrId)
        : fieldsetOrId;

    if (!fieldset || !rawFieldsetVisible(fieldset)) {
      return false;
    }

    const index = fieldsets.value.indexOf(fieldset);
    const nested = fieldsForFieldset(fields.value, index);

    return nested.length === 0 || nested.some((field) => isVisible(field));
  }

  const visibleFieldsets = computed(() =>
    fieldsets.value.filter((fieldset) => isFieldsetVisible(fieldset)),
  );
  const visibleFields = computed(() =>
    fields.value.filter((field) => isVisible(field)),
  );

  function resolveFieldInput(
    fieldOrPath: FormField | string,
  ): FormFieldInstance | null {
    if (typeof fieldOrPath === "string") {
      return resolveField(fieldOrPath);
    }

    if (
      "path" in fieldOrPath &&
      typeof fieldOrPath.path === "string"
    ) {
      return resolveField(fieldOrPath.path) ?? (fieldOrPath as FormFieldInstance);
    }

    const sourceMatches = fields.value.filter(
      (candidate) => candidate.schemaField === fieldOrPath,
    );

    if (sourceMatches.length === 1) {
      return sourceMatches[0] ?? null;
    }

    return resolveField(fieldPath(fieldOrPath));
  }

  function getValue(path: string): unknown {
    return getPath(data.value, path);
  }

  function getDefaultValue(path: string): unknown {
    return getPath(defaultsState.value, path);
  }

  function setValue(path: string, value: unknown, markTouched = true): void {
    setPath(inertia as object, path, value);

    if (markTouched) {
      touch(path);
    }
  }

  function setData(path: string | Partial<TData>, value?: unknown): void {
    if (typeof path === "string") {
      setValue(path, value);

      return;
    }

    setValues(path);
  }

  function setValues(values: Partial<TData>, markTouched = true): void {
    for (const [path, value] of Object.entries(values)) {
      setValue(path, value, markTouched);
    }
  }

  function touch(path: string): void {
    touched.add(path);
  }

  function isTouched(path: string): boolean {
    return touched.has(path);
  }

  function registerFieldElement(
    path: string,
    element: HTMLElement | null,
  ): void {
    if (element) {
      fieldElements.set(path, element);

      return;
    }

    fieldElements.delete(path);
  }

  function reset(...paths: string[]): void {
    if (paths.length === 0) {
      replaceData(defaultsState.value);
      touched.clear();
      clearErrors();

      return;
    }

    for (const path of paths) {
      setValue(path, deepClone(getPath(defaultsState.value, path)), false);
      touched.delete(path);
    }

    clearErrors(...paths);
  }

  function resetField(path: string): void {
    reset(path);
  }

  function clearField(path: string): void {
    setValue(path, fieldEmptyValue(resolveField(path)), false);
    touched.delete(path);
    clearErrors(path);
  }

  function setError(
    path: string | Record<string, string>,
    message?: string,
  ): void {
    if (typeof path === "string") {
      if (message !== undefined) {
        inertia.setError(path, message);
      }

      return;
    }

    inertia.setError(path);
  }

  function clearErrors(...paths: string[]): void {
    inertia.clearErrors(...paths);
  }

  function defaults(values?: Partial<TData>): void {
    const source = values === undefined
      ? data.value
      : ({ ...defaultsState.value, ...values } as TData);
    const next = normalizeUploadData(
      { ...resource.value, data: source },
      { preferExplicitExistingFiles: false },
    );
    defaultsState.value = next;
    inertia.defaults(next);
  }

  function transform(
    callback: (data: TData) => FormDataValues,
  ): UseFormApi<TData> {
    transformCallback = callback;

    return api;
  }

  function submit(submitOptions: FormSubmitOptions = {}): boolean {
    const action = submitOptions.action ?? resource.value.action;

    if (!action) {
      return false;
    }

    const method = normalizeMethod(
      submitOptions.method ?? resource.value.method,
    );
    const submitter = submitOptions.submitter ?? null;
    const defaultSubmitOptions = readonlyOptions.submit ?? {};
    const visitOptions: Record<string, unknown> = {
      ...defaultSubmitOptions,
      ...submitOptions,
    };

    delete visitOptions.action;
    delete visitOptions.method;
    delete visitOptions.submitter;

    const defaultOnBefore = defaultSubmitOptions.onBefore;
    const defaultOnStart = defaultSubmitOptions.onStart;
    const defaultOnProgress = defaultSubmitOptions.onProgress;
    const defaultOnSuccess = defaultSubmitOptions.onSuccess;
    const defaultOnError = defaultSubmitOptions.onError;
    const defaultOnCancel = defaultSubmitOptions.onCancel;
    const defaultOnFinish = defaultSubmitOptions.onFinish;
    const consumerOnBefore = submitOptions.onBefore;
    const consumerOnStart = submitOptions.onStart;
    const consumerOnProgress = submitOptions.onProgress;
    const consumerOnSuccess = submitOptions.onSuccess;
    const consumerOnError = submitOptions.onError;
    const consumerOnCancel = submitOptions.onCancel;
    const consumerOnFinish = submitOptions.onFinish;

    visitOptions.onBefore = (visit: unknown) => {
      const results = callDistinctCallbacks(
        defaultOnBefore,
        consumerOnBefore,
        [visit],
      );
      const result = results.includes(false) ? false : undefined;

      if (result === false) {
        submittingOwnVisit = false;
      }

      return result;
    };
    visitOptions.onStart = (visit: unknown) => {
      callDistinctCallbacks(defaultOnStart, consumerOnStart, [visit]);
    };
    visitOptions.onProgress = (nextProgress: FormProgress | null) => {
      callDistinctCallbacks(defaultOnProgress, consumerOnProgress, [nextProgress]);
    };
    visitOptions.onSuccess = (page: unknown) => {
      defaults();
      touched.clear();
      callDistinctCallbacks(defaultOnSuccess, consumerOnSuccess, [page]);
    };
    visitOptions.onError = (nextErrors: Record<string, string>) => {
      if (resource.value.scrollToFirstError === true) {
        scrollFirstRegisteredError(nextErrors);
      }

      callDistinctCallbacks(defaultOnError, consumerOnError, [nextErrors]);
    };
    visitOptions.onCancel = () => {
      submittingOwnVisit = false;
      callDistinctCallbacks(defaultOnCancel, consumerOnCancel, []);
    };
    visitOptions.onFinish = (visit: unknown) => {
      submittingOwnVisit = false;

      callDistinctCallbacks(defaultOnFinish, consumerOnFinish, [visit]);
    };

    const transformed = deepClone(transformCallback(data.value));

    if (
      submitter &&
      !submitter.disabled &&
      typeof submitter.name === "string" &&
      submitter.name !== ""
    ) {
      setPath(transformed, submitter.name, submitter.value);
    }

    const containsFiles = containsNativeFile(transformed);
    const requiresMethodSpoof =
      containsFiles && ["put", "patch", "delete"].includes(method);

    if (requiresMethodSpoof) {
      setPath(transformed, "_method", method.toUpperCase());
    }

    if (containsFiles) {
      visitOptions.forceFormData = true;
    }

    inertia.transform(() => deepClone(transformed));
    submittingOwnVisit = true;
    inertia.submit(requiresMethodSpoof ? "post" : method, action, visitOptions);

    return true;
  }

  function cancel(): void {
    inertia.cancel();
    cancelValidation();
    getFormRuntime(api).cancelUploads?.();
  }

  async function validate(path: string): Promise<boolean> {
    const action = resource.value.action;

    if (!action) {
      return true;
    }

    validationControllers.get(path)?.abort();
    const controller = new AbortController();
    validationControllers.set(path, controller);
    validationPending[path] = true;

    try {
      const transport =
        readonlyOptions.validationTransport ?? defaultValidationTransport;
      const result = await transport({
        action,
        method: resource.value.method,
        path,
        data: deepClone(data.value),
        resource: resource.value,
        signal: controller.signal,
      });
      const normalized = normalizeValidationResult(result);

      clearErrors(path);

      if (normalized.errors) {
        const normalizedErrors = Object.fromEntries(
          Object.entries(normalized.errors).map(([key, messages]) => [
            key,
            Array.isArray(messages) ? (messages[0] ?? "Invalid value.") : messages,
          ]),
        );
        setError(normalizedErrors);
      }

      return normalized.valid && !Object.hasOwn(errors.value, path);
    } catch (error) {
      if (isAbortError(error)) {
        return false;
      }

      throw error;
    } finally {
      if (validationControllers.get(path) === controller) {
        validationControllers.delete(path);
        delete validationPending[path];
      }
    }
  }

  async function validateMany(paths: string[]): Promise<boolean> {
    const unique = [...new Set(paths.filter((path) => path !== ""))];
    const results = await Promise.all(unique.map((path) => validate(path)));

    return results.every(Boolean);
  }

  function cancelValidation(path?: string): void {
    if (path) {
      validationControllers.get(path)?.abort();
      validationControllers.delete(path);
      delete validationPending[path];
      const timer = validationTimers.get(path);

      if (timer) {
        clearTimeout(timer);
        validationTimers.delete(path);
      }

      return;
    }

    for (const key of [
      ...validationControllers.keys(),
      ...validationTimers.keys(),
    ]) {
      cancelValidation(key);
    }
  }

  const api: UseFormApi<TData> = {
    formId,
    resource,
    inertia,
    data,
    rootData,
    fieldsets,
    fields,
    visibleFieldsets,
    visibleFields,
    errors,
    rootErrors,
    processing,
    progress,
    wasSuccessful,
    recentlySuccessful,
    hasErrors,
    isDirty,
    touched,
    fieldElements,
    firstErrorPath,
    shouldWarnOnUnsavedChanges,
    options: readonlyOptions,
    getValue,
    getDefaultValue,
    setValue,
    setData,
    setValues,
    touch,
    isTouched,
    registerFieldElement,
    reset,
    resetField,
    clearField,
    setError,
    clearErrors,
    defaults,
    transform,
    submit,
    cancel,
    isVisible,
    isFieldsetVisible,
    resolveField,
    getField: resolveField,
    resolveFieldset,
    validate,
  };

  setFormRuntime(api, {
    validation: {
      pending: validationPending,
      controllers: validationControllers,
      timers: validationTimers,
      validateMany,
      cancel: cancelValidation,
    },
    fieldControllers: new Map(),
  });

  watch(
    () => normalizeUploadData(resource.value),
    (nextData) => {
      if (readonlyOptions.syncResourceData === false) {
        return;
      }

      const next = deepClone(nextData);

      if (deepEqual(next, syncedResourceData.value)) {
        return;
      }

      syncedResourceData.value = deepClone(next);
      defaultsState.value = next;
      inertia.defaults(next);
      replaceData(next);
      touched.clear();
      clearErrors();
    },
    { deep: true },
  );

  const previousVisibility = new Map<string, boolean>();
  watch(
    () =>
      fields.value.map((field) => ({
        field,
        path: fieldPath(field),
        visible: isVisible(field),
        clear:
          field.clearWhenHidden === true ||
          field.ancestorClearWhenHidden ||
          containingFieldset(field)?.clearWhenHidden === true,
      })),
    (entries) => {
      const clearedCollectionPaths: string[] = [];

      for (const entry of entries) {
        const previous = previousVisibility.get(entry.path);
        const ownedByClearedCollection = clearedCollectionPaths.some(
          (path) => entry.path.startsWith(`${path}.`),
        );

        if (
          entry.path &&
          previous === true &&
          !entry.visible &&
          entry.clear &&
          !ownedByClearedCollection
        ) {
          const empty = fieldEmptyValue(entry.field);

          if (!deepEqual(getValue(entry.path), empty)) {
            clearField(entry.path);

            if (isCollectionField(entry.field)) {
              clearedCollectionPaths.push(entry.path);
            }
          }
        }

        previousVisibility.set(entry.path, entry.visible);
      }
    },
    { deep: true, immediate: true },
  );

  let removeNavigationGuard: (() => void) | null = null;
  let beforeUnloadAttached = false;

  function beforeUnload(event: BeforeUnloadEvent): void {
    if (!shouldWarnOnUnsavedChanges.value || submittingOwnVisit) {
      return;
    }

    event.preventDefault();
    event.returnValue = "";
  }

  function attachUnsavedGuards(): void {
    if (typeof window === "undefined") {
      return;
    }

    if (!beforeUnloadAttached) {
      window.addEventListener("beforeunload", beforeUnload);
      beforeUnloadAttached = true;
    }

    if (!removeNavigationGuard) {
      removeNavigationGuard = router.on("before", (event) => {
        if (!shouldWarnOnUnsavedChanges.value || submittingOwnVisit) {
          return;
        }

        const meta = resource.value.meta;
        const message =
          meta && typeof meta.unsavedWarningMessage === "string"
            ? meta.unsavedWarningMessage
            : "You have unsaved changes. Leave this page?";

        if (!window.confirm(message)) {
          event.preventDefault();
        }
      });
    }
  }

  function detachUnsavedGuards(): void {
    if (typeof window !== "undefined" && beforeUnloadAttached) {
      window.removeEventListener("beforeunload", beforeUnload);
      beforeUnloadAttached = false;
    }

    removeNavigationGuard?.();
    removeNavigationGuard = null;
  }

  watch(
    shouldWarnOnUnsavedChanges,
    (active) => {
      if (active) {
        attachUnsavedGuards();
      } else {
        detachUnsavedGuards();
      }
    },
    { immediate: true },
  );

  if (getCurrentScope()) {
    onScopeDispose(() => {
      detachUnsavedGuards();
      cancelValidation();
    });
  }

  return api;

  function replaceData(nextData: TData): void {
    const current = data.value;

    for (const key of Object.keys(current)) {
      if (!Object.prototype.hasOwnProperty.call(nextData, key)) {
        delete (inertia as Record<string, unknown>)[key];
      }
    }

    for (const [key, value] of Object.entries(nextData)) {
      (inertia as Record<string, unknown>)[key] = deepClone(value);
    }
  }

  function scrollFirstRegisteredError(nextErrors: Record<string, string>): void {
    if (typeof window === "undefined") {
      return;
    }

    const path = Object.keys(nextErrors)[0];
    const element = path ? fieldElements.get(path) : undefined;

    if (!element) {
      return;
    }

    window.requestAnimationFrame(() => {
      const bounds = element.getBoundingClientRect();
      const outsideViewport = bounds.top < 0 || bounds.bottom > window.innerHeight;

      if (outsideViewport) {
        element.scrollIntoView({ behavior: "smooth", block: "center" });
      }
    });
  }
}

function normalizeMethod(method: FormMethod): NormalizedFormMethod {
  return method.toLowerCase() as NormalizedFormMethod;
}

function normalizeValidationResult(
  result:
    | FormValidationResult
    | Record<string, string | string[]>
    | boolean
    | void,
): FormValidationResult {
  if (result === undefined) {
    return { valid: true };
  }

  if (typeof result === "boolean") {
    return { valid: result };
  }

  const possibleResult = result as Partial<FormValidationResult>;

  if (typeof possibleResult.valid === "boolean") {
    return {
      valid: possibleResult.valid,
      ...(possibleResult.errors ? { errors: possibleResult.errors } : {}),
    };
  }

  return {
    valid: Object.keys(result).length === 0,
    errors: result as Record<string, string | string[]>,
  };
}

function isAbortError(error: unknown): boolean {
  return (
    (typeof DOMException !== "undefined" &&
      error instanceof DOMException &&
      error.name === "AbortError") ||
    (typeof error === "object" &&
      error !== null &&
      "name" in error &&
      error.name === "AbortError")
  );
}

function isDataRecord(value: unknown): value is FormDataValues {
  return typeof value === "object" && value !== null && !Array.isArray(value);
}

function callDistinctCallbacks(
  first: unknown,
  second: unknown,
  arguments_: unknown[],
): unknown[] {
  const callbacks = [first, second].filter(
    (callback, index, all) =>
      typeof callback === "function" && all.indexOf(callback) === index,
  ) as Array<(...args: unknown[]) => unknown>;

  return callbacks.map((callback) => callback(...arguments_));
}

function containsNativeFile(
  value: unknown,
  seen = new WeakSet<object>(),
): boolean {
  if (
    (typeof File !== "undefined" && value instanceof File) ||
    (typeof Blob !== "undefined" && value instanceof Blob)
  ) {
    return true;
  }

  if (typeof value !== "object" || value === null || seen.has(value)) {
    return false;
  }

  seen.add(value);

  return Array.isArray(value)
    ? value.some((item) => containsNativeFile(item, seen))
    : Object.values(value).some((item) => containsNativeFile(item, seen));
}

function isCollectionField(field: FormField): boolean {
  const component =
    normalizedSlotToken(field.component)?.replace(/-/g, "") ?? "";

  return component === "repeater" || component === "blocks";
}
