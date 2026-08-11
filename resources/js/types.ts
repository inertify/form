import type {
  ComputedRef,
  Reactive,
  Ref,
  WritableComputedRef,
} from "vue";

export type FormMethod =
  | "get"
  | "post"
  | "put"
  | "patch"
  | "delete"
  | "GET"
  | "POST"
  | "PUT"
  | "PATCH"
  | "DELETE";

export type NormalizedFormMethod = Lowercase<FormMethod>;

export type FormDataValues = Record<string, unknown>;

export interface FormEndpoint {
  method: FormMethod | string;
  url: string;
}

export interface FormUploadEndpoints {
  store?: FormEndpoint | null;
  destroy?: FormEndpoint | null;
  chunked?: {
    start?: FormEndpoint | null;
    status?: FormEndpoint | null;
    append?: FormEndpoint | null;
    complete?: FormEndpoint | null;
    abort?: FormEndpoint | null;
    [key: string]: unknown;
  } | null;
  direct?: {
    start?: FormEndpoint | null;
    object?: FormEndpoint | null;
    signPart?: FormEndpoint | null;
    status?: FormEndpoint | null;
    complete?: FormEndpoint | null;
    abort?: FormEndpoint | null;
    [key: string]: unknown;
  } | null;
  [key: string]: unknown;
}

export interface FormUploadDescriptor {
  strategy: "temporary" | "form" | "chunked" | "direct" | string;
  endpoints: FormUploadEndpoints;
  limits: {
    maxSizeKiB?: number | null;
    chunkSizeBytes?: number | null;
    directMaxSizeKiB?: number | null;
    partSizeBytes?: number | null;
    multipartThresholdBytes?: number | null;
    [key: string]: unknown;
  };
  disk: string | null;
  rulesToken: string | null;
  requiresRulesToken: boolean;
  [key: string]: unknown;
}

export type FormVisibilityOperator =
  | "="
  | "!="
  | ">"
  | ">="
  | "<"
  | "<="
  | "is"
  | "equals"
  | "is_not"
  | "not_equals"
  | "contains"
  | "not_contains"
  | "in"
  | "not_in"
  | "greater_than"
  | "greater_than_or_equal"
  | "less_than"
  | "less_than_or_equal"
  | "filled"
  | "not_empty"
  | "blank"
  | "empty"
  | "truthy"
  | "falsy"
  | "starts_with"
  | "ends_with"
  | string;

export interface FormVisibilityClause {
  field?: string;
  attribute?: string;
  operator?: FormVisibilityOperator;
  value?: unknown;
  [key: string]: unknown;
}

export interface FormVisibilityGroup {
  mode?: "all" | "any" | "and" | "or" | "not" | string;
  conditions?: FormVisibilityCondition[];
  [key: string]: unknown;
}

export type FormVisibilityCondition =
  | FormVisibilityClause
  | FormVisibilityGroup
  | FormVisibilityCondition[]
  | Record<string, unknown>
  | boolean
  | null;

export interface FormField {
  name: string;
  attribute?: string;
  component: string;
  label: string | null;
  help: string | null;
  placeholder: string | null;
  default: unknown;
  rules: unknown;
  precognitive: boolean;
  disabled: boolean;
  readonly: boolean;
  autofocus: boolean;
  authorized?: boolean;
  modelBinding?: boolean;
  visible?: boolean;
  conditions?: FormVisibilityCondition;
  visibility?: FormVisibilityCondition;
  clearWhenHidden: boolean;
  dataAttributes: Record<string, string> | null;
  meta: Record<string, unknown> | null;
  upload?: FormUploadDescriptor | null;
  multiple?: boolean;
  fields?: Array<FormField | FormFieldset>;
  schema?: Array<FormField | FormFieldset>;
  children?: Array<FormField | FormFieldset>;
  sets?: FormBlockSet[];
  blocks?: FormBlockSet[];
  storeWithForm?: boolean;
  temporaryUploadUrl?: string | null;
  temporaryUploadDeleteUrl?: string | null;
  chunked?: boolean;
  chunkSize?: number | null;
  chunkedUrls?: Record<string, FormEndpoint | string | null> | null;
  directToStorage?: boolean;
  uploadDisk?: string | null;
  uploadPartSize?: number | null;
  uploadMultipartThreshold?: number | null;
  directUploadUrls?: Record<string, FormEndpoint | string | null> | null;
  uploadRulesToken?: string | null;
  requiresUploadRulesToken?: boolean;
  existingFiles?: FormUploadedFile[];
  imageUploads?: Record<string, unknown> | null;
  [key: string]: unknown;
}

export interface FormBlockSet {
  type?: string;
  name?: string;
  label?: string | null;
  description?: string | null;
  schema?: Array<FormField | FormFieldset>;
  fields?: Array<FormField | FormFieldset>;
  maxItems?: number | null;
  defaultData?: Record<string, unknown> | null;
  dataAttributes?: Record<string, string> | null;
  meta?: Record<string, unknown> | null;
  [key: string]: unknown;
}

export interface FormFieldset {
  id: string | null;
  legend: string | null;
  description: string | null;
  fields: Array<FormField | FormFieldset>;
  conditions?: FormVisibilityCondition;
  visibility?: FormVisibilityCondition;
  dataAttributes: Record<string, string> | null;
  meta: Record<string, unknown> | null;
  visible?: boolean;
  clearWhenHidden?: boolean;
  [key: string]: unknown;
}

/** A schema field resolved to one concrete root-data path. */
export interface FormFieldInstance extends FormField {
  path: string;
  schemaName: string;
  schemaField: FormField;
  rowPath: string | null;
  fieldsetIndex: number;
  ancestorVisibility: FormVisibilityCondition[];
  ancestorClearWhenHidden: boolean;
}

export interface WizardLabels {
  next: string;
  previous: string;
  submit: string;
  [key: string]: string;
}

export interface WizardResource {
  enabled: boolean;
  allowSkip: boolean;
  validateOnStep: boolean;
  labels?: Partial<WizardLabels>;
  steps?: unknown[];
  nextLabel?: string;
  prevLabel?: string;
  submitLabel?: string;
  [key: string]: unknown;
}

export interface FormResource<TData extends FormDataValues = FormDataValues> {
  action: string | null;
  method: FormMethod;
  fieldsets: FormFieldset[];
  data: TData;
  dataAttributes: Record<string, string> | null;
  meta: Record<string, unknown> | null;
  unsavedWarning: boolean;
  scrollToFirstError: boolean;
  wizard: WizardResource | null;
  [key: string]: unknown;
}

export interface FormProgress {
  percentage?: number | null;
  loaded?: number;
  total?: number;
  [key: string]: unknown;
}

export interface FormSubmitOptions {
  action?: string;
  method?: FormMethod;
  preserveScroll?: boolean | "errors" | ((page: unknown) => boolean);
  preserveState?: boolean | "errors" | ((page: unknown) => boolean);
  replace?: boolean;
  forceFormData?: boolean;
  headers?: Record<string, string>;
  only?: string[];
  except?: string[];
  reset?: string[];
  submitter?: HTMLButtonElement | HTMLInputElement | null;
  onBefore?: (visit: unknown) => boolean | void;
  onStart?: (visit: unknown) => void;
  onProgress?: (progress: FormProgress | null) => void;
  onSuccess?: (page: unknown) => void;
  onError?: (errors: Record<string, string>) => void;
  onCancel?: () => void;
  onFinish?: (visit: unknown) => void;
  [key: string]: unknown;
}

export interface FormValidationRequest<TData extends FormDataValues = FormDataValues> {
  action: string;
  method: FormMethod;
  path: string;
  data: TData;
  resource: FormResource<TData>;
  signal: AbortSignal;
}

export interface FormValidationResult {
  valid: boolean;
  errors?: Record<string, string | string[]>;
}

export type FormValidationTransport<TData extends FormDataValues = FormDataValues> = (
  request: FormValidationRequest<TData>,
) => Promise<FormValidationResult | Record<string, string | string[]> | boolean | void>;

export interface FormUploadedFile {
  key: string;
  name: string;
  mimeType: string | null;
  mime_type?: string | null;
  size: number;
  [key: string]: unknown;
}

export interface FormUploadRequest {
  path: string;
  field: FormField;
  files: File[];
  descriptor: FormUploadDescriptor;
  signal: AbortSignal;
  onProgress: (percentage: number | null, loaded?: number, total?: number) => void;
  resume: FormUploadResumeState | null;
  onSession: (session: FormUploadResumeState | null) => void;
}

export interface FormUploadResumeState {
  strategy: string;
  uploadId: string;
  fileIndex: number;
  offset: number;
  chunkSizeBytes?: number;
  partSizeBytes?: number;
  parts?: Array<{ partNumber: number; etag: string | null; size?: number }>;
  started?: Record<string, unknown>;
}

export interface FormUploadRemovalRequest {
  path: string;
  field: FormField;
  file: FormUploadedFile | null;
  token: string;
  descriptor: FormUploadDescriptor;
  signal: AbortSignal;
}

export interface FormUploadTransport {
  upload: (request: FormUploadRequest) => Promise<FormUploadedFile[]>;
  remove?: (request: FormUploadRemovalRequest) => Promise<void>;
}

export interface UseFormOptions<TData extends FormDataValues = FormDataValues> {
  syncResourceData?: boolean;
  validationTransport?: FormValidationTransport<TData>;
  uploadTransport?: FormUploadTransport;
  submit?: FormSubmitOptions;
  propKey?: string;
}

export interface UseFormApi<TData extends FormDataValues = any> {
  formId: ComputedRef<string>;
  resource: ComputedRef<FormResource<TData>>;
  inertia: InertiaFormAdapter<TData> & TData;
  data: ComputedRef<TData>;
  rootData: ComputedRef<TData>;
  fieldsets: ComputedRef<FormFieldset[]>;
  fields: ComputedRef<FormFieldInstance[]>;
  visibleFieldsets: ComputedRef<FormFieldset[]>;
  visibleFields: ComputedRef<FormFieldInstance[]>;
  errors: ComputedRef<Record<string, string>>;
  rootErrors: ComputedRef<Record<string, string>>;
  processing: ComputedRef<boolean>;
  progress: ComputedRef<FormProgress | null>;
  wasSuccessful: ComputedRef<boolean>;
  recentlySuccessful: ComputedRef<boolean>;
  hasErrors: ComputedRef<boolean>;
  isDirty: ComputedRef<boolean>;
  touched: Reactive<Set<string>>;
  fieldElements: Map<string, HTMLElement>;
  firstErrorPath: ComputedRef<string | null>;
  shouldWarnOnUnsavedChanges: ComputedRef<boolean>;
  options: Readonly<UseFormOptions<TData>>;
  getValue: (path: string) => unknown;
  getDefaultValue: (path: string) => unknown;
  setValue: (path: string, value: unknown, touch?: boolean) => void;
  setData: (path: string | Partial<TData>, value?: unknown) => void;
  setValues: (values: Partial<TData>, touch?: boolean) => void;
  touch: (path: string) => void;
  isTouched: (path: string) => boolean;
  registerFieldElement: (path: string, element: HTMLElement | null) => void;
  reset: (...paths: string[]) => void;
  resetField: (path: string) => void;
  clearField: (path: string) => void;
  setError: (path: string | Record<string, string>, message?: string) => void;
  clearErrors: (...paths: string[]) => void;
  defaults: (values?: Partial<TData>) => void;
  transform: (callback: (data: TData) => FormDataValues) => UseFormApi<TData>;
  submit: (options?: FormSubmitOptions) => boolean;
  cancel: () => void;
  isVisible: (fieldOrPath: FormField | string) => boolean;
  isFieldsetVisible: (fieldsetOrId: FormFieldset | string) => boolean;
  resolveField: (path: string) => FormFieldInstance | null;
  getField: (path: string) => FormFieldInstance | null;
  resolveFieldset: (id: string) => FormFieldset | null;
  validate: (path: string) => Promise<boolean>;
}

export interface InertiaFormAdapter<TData extends FormDataValues> {
  errors: Record<string, string>;
  hasErrors: boolean;
  processing: boolean;
  progress: FormProgress | null;
  wasSuccessful: boolean;
  recentlySuccessful: boolean;
  isDirty: boolean;
  data: () => TData;
  transform: (callback: (data: TData) => FormDataValues) => InertiaFormAdapter<TData>;
  defaults: (values?: Partial<TData>) => InertiaFormAdapter<TData>;
  reset: (...fields: string[]) => void;
  setError: (field: string | Record<string, string>, value?: string) => void;
  clearErrors: (...fields: string[]) => void;
  submit: (method: NormalizedFormMethod, url: string, options?: Record<string, unknown>) => void;
  cancel: () => void;
}

export interface UseFormFieldApi {
  field: ComputedRef<FormField | null>;
  path: string;
  value: WritableComputedRef<unknown>;
  errors: ComputedRef<string[]>;
  error: ComputedRef<string | null>;
  visible: ComputedRef<boolean>;
  dirty: ComputedRef<boolean>;
  touched: ComputedRef<boolean>;
  disabled: ComputedRef<boolean>;
  readonly: ComputedRef<boolean>;
  required: ComputedRef<boolean>;
  element: Ref<HTMLElement | null>;
  setValue: (value: unknown, touch?: boolean) => void;
  touch: () => void;
  registerElement: (element: HTMLElement | null) => void;
  blur: () => Promise<boolean>;
  validate: () => Promise<boolean>;
  reset: () => void;
  clear: () => void;
  clearErrors: () => void;
}

export interface UseFormFieldsApi {
  fieldsets: ComputedRef<FormFieldset[]>;
  fields: ComputedRef<FormFieldInstance[]>;
  visibleFieldsets: ComputedRef<FormFieldset[]>;
  visibleFields: ComputedRef<FormFieldInstance[]>;
  get: (path: string) => FormFieldInstance | null;
  controller: (fieldOrPath: FormField | string) => UseFormFieldApi;
}

export interface UseFormValidationApi {
  pending: Reactive<Record<string, boolean>>;
  validating: ComputedRef<boolean>;
  isValidating: (path: string) => boolean;
  validate: (path: string) => Promise<boolean>;
  validateMany: (paths: string[]) => Promise<boolean>;
  validateDebounced: (path: string, delay?: number) => Promise<boolean>;
  cancel: (path?: string) => void;
  clear: (path?: string) => void;
}

export interface UseFormVisibilityApi {
  visibleFields: ComputedRef<FormField[]>;
  hiddenFields: ComputedRef<FormField[]>;
  visibleFieldsets: ComputedRef<FormFieldset[]>;
  hiddenFieldsets: ComputedRef<FormFieldset[]>;
  isVisible: UseFormApi["isVisible"];
  isFieldsetVisible: UseFormApi["isFieldsetVisible"];
}

export interface UseFormSubmissionApi {
  processing: ComputedRef<boolean>;
  progress: ComputedRef<FormProgress | null>;
  wasSuccessful: ComputedRef<boolean>;
  recentlySuccessful: ComputedRef<boolean>;
  hasErrors: ComputedRef<boolean>;
  firstErrorPath: ComputedRef<string | null>;
  canSubmit: ComputedRef<boolean>;
  submit: UseFormApi["submit"];
  cancel: UseFormApi["cancel"];
}

export interface FormWizardStep {
  id: string;
  index: number;
  label: string;
  description: string | null;
  fieldset: FormFieldset;
  fields: FormFieldInstance[];
}

export interface UseFormWizardApi {
  enabled: ComputedRef<boolean>;
  steps: ComputedRef<FormWizardStep[]>;
  currentIndex: Ref<number>;
  current: ComputedRef<FormWizardStep | null>;
  completed: Reactive<Set<string>>;
  isFirst: ComputedRef<boolean>;
  isLast: ComputedRef<boolean>;
  canPrevious: ComputedRef<boolean>;
  canNext: ComputedRef<boolean>;
  labels: ComputedRef<WizardLabels>;
  goTo: (step: number | string) => Promise<boolean>;
  next: () => Promise<boolean>;
  previous: () => boolean;
  validateCurrent: () => Promise<boolean>;
  reset: () => void;
}

export type FormUploadStatus =
  | "idle"
  | "uploading"
  | "paused"
  | "success"
  | "error";

export interface FormUploadState {
  status: FormUploadStatus;
  progress: number | null;
  loaded: number;
  total: number;
  error: string | null;
  files: FormUploadedFile[];
  pendingFiles: File[];
}

export interface UseFormUploadsApi {
  states: Reactive<Record<string, FormUploadState>>;
  state: (path: string) => FormUploadState;
  upload: (path: string, files: File | File[] | FileList) => Promise<FormUploadedFile[]>;
  retry: (path: string) => Promise<FormUploadedFile[]>;
  pause: (path: string) => void;
  resume: (path: string) => Promise<FormUploadedFile[]>;
  remove: (path: string, indexOrToken?: number | string) => Promise<boolean>;
  reorder: (path: string, from: number, to: number) => void;
  cancel: (path?: string) => void;
  clear: (path: string) => void;
}

export interface UseFormCollectionApi {
  path: string;
  field: ComputedRef<FormField | null>;
  items: ComputedRef<unknown[]>;
  keys: ComputedRef<string[]>;
  canAppend: ComputedRef<boolean>;
  append: (value?: unknown) => void;
  prepend: (value?: unknown) => void;
  insert: (index: number, value?: unknown) => void;
  update: (index: number, value: unknown) => void;
  remove: (index: number) => unknown;
  move: (from: number, to: number) => void;
  swap: (first: number, second: number) => void;
  duplicate: (index: number) => void;
  appendBlock: (type: string) => boolean;
  clear: () => void;
}

export interface UseFormCollectionsApi {
  forField: (path: string) => UseFormCollectionApi;
}

export interface FormComboboxOption {
  value: unknown;
  label: string;
  [key: string]: unknown;
}

export interface FormComboboxSource {
  url?: string;
  selectedSource?: FormComboboxSource;
  createUrl?: string | null;
  createMethod?: FormMethod | string;
  method?: FormMethod | string;
  params?: Record<string, unknown>;
  filters?: Record<string, unknown>;
  scopes?: unknown[];
  searchParam?: string;
  valueParam?: string;
  valuesParam?: string;
  pageParam?: string;
  perPageParam?: string;
  perPage?: number;
  minSearchLength?: number;
  debounce?: number;
  preload?: boolean | number;
  valueKey?: string;
  labelKey?: string;
  primaryKey?: string;
  [key: string]: unknown;
}

export interface FormComboboxRequest {
  field: FormField;
  source: FormComboboxSource;
  search: string;
  page: number;
  selected: unknown[];
  create: boolean;
  signal: AbortSignal;
}

export interface FormComboboxPage {
  options: FormComboboxOption[];
  page: number;
  hasMore: boolean;
  nextCursor: string | null;
  nextPageUrl: string | null;
  raw: unknown;
}

export type FormComboboxTransport = (
  request: FormComboboxRequest,
) => Promise<unknown>;

export interface UseFormComboboxOptions {
  transport?: FormComboboxTransport;
}

export interface UseFormComboboxApi {
  field: ComputedRef<FormField | null>;
  search: Ref<string>;
  options: Ref<FormComboboxOption[]>;
  selectedOptions: Ref<FormComboboxOption[]>;
  loading: Ref<boolean>;
  creating: Ref<boolean>;
  error: Ref<string | null>;
  page: Ref<number>;
  hasMore: Ref<boolean>;
  load: (search?: string) => Promise<FormComboboxPage>;
  loadMore: () => Promise<FormComboboxPage>;
  hydrateSelected: () => Promise<FormComboboxOption[]>;
  create: (label: string) => Promise<FormComboboxOption | null>;
  cancel: () => void;
}
