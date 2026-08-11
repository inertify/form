import { router } from "@inertiajs/vue3";
import { computed, onScopeDispose, ref, watch } from "vue";
import { useFormContext } from "./context";
import { getPath } from "./internal/path";
import type {
  FormComboboxOption,
  FormComboboxPage,
  FormComboboxRequest,
  FormComboboxSource,
  FormComboboxTransport,
  FormField,
  UseFormApi,
  UseFormComboboxApi,
  UseFormComboboxOptions,
} from "./types";

export function useFormCombobox(
  fieldOrPath: FormField | string,
  formApi?: UseFormApi,
  options: UseFormComboboxOptions = {},
): UseFormComboboxApi {
  const form = formApi ?? useFormContext();
  const path =
    typeof fieldOrPath === "string" ? fieldOrPath : fieldOrPath.name;
  const field = computed(() =>
    typeof fieldOrPath === "string"
      ? form.resolveField(fieldOrPath)
      : form.resolveField(path) ?? fieldOrPath,
  );
  const source = computed(() => normalizeSource(field.value));
  const inertiaMode = computed(
    () => field.value?.optionsMode === "inertia",
  );
  const inertiaOptions = computed(() =>
    field.value?.optionsMode === "inertia"
      ? resourceOptions(form, field.value)
      : null,
  );
  const search = ref("");
  const initialPage = normalizeComboboxPage(
    inertiaOptions.value ?? [],
    source.value,
  );
  const loadedOptions = ref<FormComboboxOption[]>(initialPage.options);
  const selectedOptions = ref<FormComboboxOption[]>([]);
  const loading = ref(false);
  const creating = ref(false);
  const error = ref<string | null>(null);
  const page = ref(initialPage.page);
  const hasMore = ref(initialPage.hasMore);
  const nextCursor = ref(initialPage.nextCursor);
  const nextPageUrl = ref(initialPage.nextPageUrl);
  let controller: AbortController | null = null;
  let debounceTimer: ReturnType<typeof setTimeout> | null = null;
  let requestSequence = 0;

  async function request(
    nextSearch: string,
    nextPage: number,
    selected: unknown[] = [],
    create = false,
    continuation: { cursor: string | null; url: string | null } | null = null,
  ): Promise<unknown> {
    const currentField = field.value;
    const currentSource = source.value;
    const effectiveSource =
      selected.length > 0 && currentSource.selectedSource
        ? { ...currentSource, ...currentSource.selectedSource }
        : currentSource;
    const targetUrl = create
      ? effectiveSource.createUrl
      : continuation?.url ?? effectiveSource.url;

    if (!currentField || !targetUrl) {
      return [];
    }

    const requestSource: FormComboboxSource = {
      ...effectiveSource,
      ...(create ? {} : { url: targetUrl }),
      ...(continuation?.cursor && !continuation.url
        ? {
            params: {
              ...(effectiveSource.params ?? {}),
              cursor: continuation.cursor,
            },
          }
        : {}),
    };

    controller?.abort();
    controller = new AbortController();
    const transport = options.transport ?? defaultComboboxTransport;

    return transport({
      field: currentField,
      source: requestSource,
      search: nextSearch,
      page: nextPage,
      selected,
      create,
      signal: controller.signal,
    });
  }

  async function load(nextSearch = search.value): Promise<FormComboboxPage> {
    search.value = nextSearch;
    const currentSource = source.value;
    const minimum = Number(currentSource.minSearchLength ?? 0);
    const isPreload =
      nextSearch === "" &&
      (currentSource.preload === true ||
        (typeof currentSource.preload === "number" &&
          currentSource.preload > 0));

    if (nextSearch.length < minimum && !isPreload) {
      const empty = emptyPage();
      loadedOptions.value = [];
      page.value = 1;
      hasMore.value = false;
      nextCursor.value = null;
      nextPageUrl.value = null;

      return empty;
    }

    loading.value = true;
    error.value = null;
    const sequence = ++requestSequence;

    try {
      const raw = inertiaMode.value
        ? await reloadInertiaOptions(form, field.value, nextSearch, 1)
        : await request(nextSearch, 1);
      const normalized = normalizeComboboxPage(raw, currentSource, 1);

      if (sequence !== requestSequence) {
        return normalized;
      }

      loadedOptions.value = normalized.options;
      page.value = normalized.page;
      hasMore.value = normalized.hasMore;
      nextCursor.value = normalized.nextCursor;
      nextPageUrl.value = normalized.nextPageUrl;

      return normalized;
    } catch (caught) {
      if (!isAbortError(caught) && sequence === requestSequence) {
        error.value = caught instanceof Error ? caught.message : "Options failed to load.";
      }

      return emptyPage();
    } finally {
      if (sequence === requestSequence) {
        loading.value = false;
      }
    }
  }

  async function loadMore(): Promise<FormComboboxPage> {
    if (!hasMore.value || loading.value) {
      return {
        ...emptyPage(),
        options: loadedOptions.value,
        page: page.value,
      };
    }

    loading.value = true;
    const sequence = ++requestSequence;

    try {
      const raw = inertiaMode.value
        ? await reloadInertiaOptions(
            form,
            field.value,
            search.value,
            page.value + 1,
          )
        : await request(search.value, page.value + 1, [], false, {
            cursor: nextCursor.value,
            url: nextPageUrl.value,
          });
      const normalized = normalizeComboboxPage(
        raw,
        source.value,
        page.value + 1,
      );

      if (sequence !== requestSequence) {
        return normalized;
      }

      loadedOptions.value = mergeOptions(
        loadedOptions.value,
        normalized.options,
      );
      page.value = normalized.page;
      hasMore.value = normalized.hasMore;
      nextCursor.value = normalized.nextCursor;
      nextPageUrl.value = normalized.nextPageUrl;

      return normalized;
    } finally {
      if (sequence === requestSequence) {
        loading.value = false;
      }
    }
  }

  async function hydrateSelected(): Promise<FormComboboxOption[]> {
    const values = selectedValues(form.getValue(path));

    if (values.length === 0) {
      selectedOptions.value = [];

      return [];
    }

    const local = normalizeInlineOptions(field.value, source.value).filter(
      (option) => values.some((value) => sameValue(value, option.value)),
    );
    const serialized = normalizeSelectedOptions(field.value, source.value).filter(
      (option) => values.some((value) => sameValue(value, option.value)),
    );
    const known = mergeOptions(local, serialized);

    if (
      known.length === values.length ||
      (!source.value.url && !source.value.selectedSource?.url)
    ) {
      selectedOptions.value = known;

      return known;
    }

    loading.value = true;

    try {
      const raw = await request("", 1, values);
      const normalized = normalizeComboboxPage(raw, source.value, 1);
      selectedOptions.value = mergeOptions(known, normalized.options);

      return selectedOptions.value;
    } finally {
      loading.value = false;
    }
  }

  async function create(label: string): Promise<FormComboboxOption | null> {
    if (!source.value.createUrl || label.trim() === "") {
      return null;
    }

    creating.value = true;
    error.value = null;

    try {
      const raw = await request(label, 1, [], true);
      const payload =
        typeof raw === "object" && raw !== null && "item" in raw
          ? (raw as Record<string, unknown>).item
          : raw;
      const option = normalizeOption(payload, source.value);

      if (option) {
        loadedOptions.value = mergeOptions(loadedOptions.value, [option]);
      }

      return option;
    } catch (caught) {
      if (!isAbortError(caught)) {
        error.value = caught instanceof Error ? caught.message : "Option creation failed.";
      }

      return null;
    } finally {
      creating.value = false;
    }
  }

  function cancel(): void {
    requestSequence += 1;
    controller?.abort();
    controller = null;

    if (debounceTimer) {
      clearTimeout(debounceTimer);
      debounceTimer = null;
    }
  }

  watch(search, (value) => {
    if (debounceTimer) {
      clearTimeout(debounceTimer);
    }

    debounceTimer = setTimeout(() => {
      void load(value);
    }, Math.max(0, Number(source.value.debounce ?? 300)));
  });

  if (
    source.value.preload === true ||
    (typeof source.value.preload === "number" && source.value.preload > 0)
  ) {
    void load("");
  }

  onScopeDispose(cancel, true);

  return {
    field,
    search,
    options: loadedOptions,
    selectedOptions,
    loading,
    creating,
    error,
    page,
    hasMore,
    load,
    loadMore,
    hydrateSelected,
    create,
    cancel,
  };
}

export const defaultComboboxTransport: FormComboboxTransport = async (
  request,
) => {
  const source = request.source;
  const url = request.create ? source.createUrl : source.url;

  if (!url) {
    return [];
  }

  const method = request.create
    ? String(source.createMethod ?? "POST").toUpperCase()
    : String(source.method ?? "GET").toUpperCase();
  const data: Record<string, unknown> = {
    ...(source.params ?? {}),
    filters: source.filters ?? {},
    scopes: source.scopes ?? [],
  };

  if (request.create) {
    const key = source.primaryKey ?? source.searchParam ?? "q";
    data[key] = request.search;
    data.q = request.search;
  } else if (request.selected.length > 0) {
    data[source.valuesParam ?? source.valueParam ?? "values"] = request.selected;
    data.selected = 1;
  } else {
    const searchParam = source.searchParam ?? "search";
    data[searchParam] = request.search;
    data.search = request.search;
    data.q = request.search;
    data[source.pageParam ?? "page"] = request.page;
    data[source.perPageParam ?? "per_page"] =
      request.search === "" &&
      typeof source.preload === "number" &&
      source.preload > 0
        ? source.preload
        : source.perPage ?? 15;
  }

  return fetchCombobox(url, method, data, request.signal);
};

export function normalizeComboboxPage(
  raw: unknown,
  source: FormComboboxSource,
  fallbackPage = 1,
): FormComboboxPage {
  const record =
    typeof raw === "object" && raw !== null && !Array.isArray(raw)
      ? (raw as Record<string, unknown>)
      : null;
  const candidates = Array.isArray(raw)
    ? raw
    : Array.isArray(record?.data)
      ? record.data
      : [];
  const options = candidates
    .map((item) => normalizeOption(item, source))
    .filter((item): item is FormComboboxOption => item !== null);
  const currentPage = Number(record?.current_page ?? record?.page ?? fallbackPage);
  const lastPage = Number(record?.last_page ?? currentPage);
  const meta =
    typeof record?.meta === "object" && record.meta !== null
      ? (record.meta as Record<string, unknown>)
      : {};
  const nextCursor = record?.next_cursor ?? meta.next_cursor;
  const nextPageUrl = record?.next_page_url ?? meta.next_page_url;
  const hasMore = Boolean(
    record?.next_page_url ||
      meta.next_page ||
      meta.has_more ||
      nextCursor ||
      (Number.isFinite(lastPage) && currentPage < lastPage),
  );

  return {
    options,
    page: Number.isFinite(currentPage) && currentPage > 0 ? currentPage : fallbackPage,
    hasMore,
    nextCursor:
      typeof nextCursor === "string" && nextCursor !== "" ? nextCursor : null,
    nextPageUrl:
      typeof nextPageUrl === "string" && nextPageUrl !== ""
        ? nextPageUrl
        : null,
    raw,
  };
}

async function fetchCombobox(
  url: string,
  method: string,
  data: Record<string, unknown>,
  signal: AbortSignal,
): Promise<unknown> {
  const target = new URL(
    url,
    typeof window === "undefined" ? "http://localhost" : window.location.origin,
  );
  const headers: Record<string, string> = {
    Accept: "application/json",
    "X-Requested-With": "XMLHttpRequest",
  };
  const csrf = csrfToken();
  const xsrf = xsrfToken();

  if (csrf) {
    headers["X-CSRF-TOKEN"] = csrf;
  } else if (xsrf) {
    headers["X-XSRF-TOKEN"] = xsrf;
  }
  const init: RequestInit = {
    method,
    headers,
    credentials: "same-origin",
    signal,
  };

  if (method === "GET") {
    appendParams(target.searchParams, data);
  } else {
    headers["Content-Type"] = "application/json";
    init.body = JSON.stringify(data);
  }

  const response = await fetch(
    /^https?:\/\//i.test(url)
      ? target.toString()
      : `${target.pathname}${target.search}${target.hash}`,
    init,
  );
  const payload: unknown = await response.json();

  if (!response.ok) {
    const message =
      typeof payload === "object" &&
      payload !== null &&
      "message" in payload &&
      typeof payload.message === "string"
        ? payload.message
        : `Option request failed with status ${response.status}.`;
    throw new Error(message);
  }

  return payload;
}

function appendParams(
  output: URLSearchParams,
  value: unknown,
  prefix?: string,
): void {
  if (Array.isArray(value)) {
    value.forEach((item, index) => appendParams(output, item, `${prefix ?? ""}[${index}]`));

    return;
  }

  if (typeof value === "object" && value !== null) {
    Object.entries(value).forEach(([key, item]) =>
      appendParams(output, item, prefix ? `${prefix}[${key}]` : key),
    );

    return;
  }

  if (prefix && value !== null && value !== undefined) {
    output.append(prefix, String(value));
  }
}

function normalizeSource(field: FormField | null): FormComboboxSource {
  if (!field) {
    return {};
  }

  const source = field.source;
  const nested =
    typeof source === "object" && source !== null
      ? (source as FormComboboxSource)
      : {};
  const topLevel = Object.fromEntries(
    [
      "createUrl",
      "method",
      "params",
      "filters",
      "scopes",
      "searchParam",
      "valueParam",
      "valuesParam",
      "pageParam",
      "perPageParam",
      "perPage",
      "minSearchLength",
      "debounce",
      "preload",
      "valueKey",
      "labelKey",
      "primaryKey",
    ]
      .map((key) => [key, field[key]] as const)
      .filter((entry) => entry[1] !== undefined),
  );
  const selectedSource = normalizeRemoteSource(field.selectedSource);
  const createRecordUsing = isRecord(field.createRecordUsing)
    ? field.createRecordUsing
    : null;
  const create = createRecordUsing
    ? {
        ...(typeof createRecordUsing.url === "string"
          ? { createUrl: createRecordUsing.url }
          : {}),
        ...(typeof createRecordUsing.method === "string"
          ? { createMethod: createRecordUsing.method }
          : {}),
        ...(typeof createRecordUsing.param === "string"
          ? { primaryKey: createRecordUsing.param }
          : {}),
      }
    : {};
  const additions = {
    ...topLevel,
    ...create,
    ...(selectedSource ? { selectedSource } : {}),
  };

  if (typeof source === "string") {
    return { url: source, ...additions };
  }

  return { ...nested, ...additions };
}

function normalizeRemoteSource(value: unknown): FormComboboxSource | null {
  if (typeof value === "string" && value !== "") {
    return { url: value };
  }

  return isRecord(value) ? (value as FormComboboxSource) : null;
}

function resourceOptions(
  form: UseFormApi,
  field: FormField | null,
): unknown {
  const key = field?.optionsKey;
  const meta = form.resource.value.meta;
  const options =
    meta && typeof meta.options === "object" && meta.options !== null
      ? (meta.options as Record<string, unknown>)
      : null;

  if (!options || typeof key !== "string" || key === "") {
    return null;
  }

  return options[key] ?? getPath(options, key) ?? null;
}

async function reloadInertiaOptions(
  form: UseFormApi,
  field: FormField | null,
  search: string,
  page: number,
): Promise<unknown> {
  const optionsKey = field?.optionsKey;

  if (typeof optionsKey !== "string" || optionsKey === "") {
    return [];
  }

  const propKey = form.options.propKey ?? "form";

  return new Promise((resolve, reject) => {
    let settled = false;
    const finish = (value: unknown) => {
      if (!settled) {
        settled = true;
        resolve(value);
      }
    };
    const inertiaRouter = router as unknown as {
      reload: (options: Record<string, unknown>) => void;
    };

    inertiaRouter.reload({
      only: [propKey],
      data: {
        _inertify_form: {
          prop: propKey,
          field: optionsKey,
          search,
          page,
        },
      },
      preserveScroll: true,
      preserveState: true,
      onSuccess: (pageResponse: unknown) => {
        finish(
          optionsFromPage(pageResponse, propKey, optionsKey) ??
            resourceOptions(form, field),
        );
      },
      onError: (errors: unknown) => {
        settled = true;
        reject(
          new Error(
            typeof errors === "object" &&
            errors !== null &&
            "message" in errors &&
            typeof errors.message === "string"
              ? errors.message
              : "Inertia options failed to load.",
          ),
        );
      },
      onFinish: () => finish(resourceOptions(form, field)),
    });
  });
}

function optionsFromPage(
  page: unknown,
  propKey: string,
  optionsKey: string,
): unknown {
  if (typeof page !== "object" || page === null) {
    return null;
  }

  const props =
    "props" in page && typeof page.props === "object" && page.props !== null
      ? (page.props as Record<string, unknown>)
      : (page as Record<string, unknown>);
  const resource = props[propKey] ?? getPath(props, propKey);

  if (typeof resource !== "object" || resource === null) {
    return null;
  }

  const meta = (resource as Record<string, unknown>).meta;

  if (typeof meta !== "object" || meta === null || !("options" in meta)) {
    return null;
  }

  const options = meta.options;

  return typeof options === "object" && options !== null
    ? (options as Record<string, unknown>)[optionsKey] ??
        getPath(options, optionsKey)
    : null;
}

function normalizeInlineOptions(
  field: FormField | null,
  source: FormComboboxSource,
): FormComboboxOption[] {
  const options = field?.options;

  return Array.isArray(options)
    ? options
        .map((item) => normalizeOption(item, source))
        .filter((item): item is FormComboboxOption => item !== null)
    : [];
}

function normalizeSelectedOptions(
  field: FormField | null,
  source: FormComboboxSource,
): FormComboboxOption[] {
  const selected = field?.selected;

  if (Array.isArray(selected)) {
    return selected
      .map((item) => normalizeOption(item, source))
      .filter((item): item is FormComboboxOption => item !== null);
  }

  return isRecord(selected) && Array.isArray(selected.data)
    ? normalizeComboboxPage(selected, source).options
    : [];
}

function normalizeOption(
  item: unknown,
  source: FormComboboxSource,
): FormComboboxOption | null {
  if (typeof item !== "object" || item === null) {
    return item === null || item === undefined
      ? null
      : { value: item, label: String(item) };
  }

  const record = item as Record<string, unknown>;
  const value = record[source.valueKey ?? "value"] ?? record.id;
  const label = record[source.labelKey ?? "label"] ?? record.name ?? value;

  if (value === undefined || label === undefined) {
    return null;
  }

  return { ...record, value, label: String(label) };
}

function mergeOptions(
  left: FormComboboxOption[],
  right: FormComboboxOption[],
): FormComboboxOption[] {
  const output = [...left];

  for (const option of right) {
    if (!output.some((item) => sameValue(item.value, option.value))) {
      output.push(option);
    }
  }

  return output;
}

function selectedValues(value: unknown): unknown[] {
  return Array.isArray(value)
    ? value
    : value === null || value === undefined || value === ""
      ? []
      : [value];
}

function sameValue(left: unknown, right: unknown): boolean {
  return Object.is(left, right) || String(left) === String(right);
}

function emptyPage(): FormComboboxPage {
  return {
    options: [],
    page: 1,
    hasMore: false,
    nextCursor: null,
    nextPageUrl: null,
    raw: [],
  };
}

function isAbortError(error: unknown): boolean {
  return (
    typeof error === "object" &&
    error !== null &&
    "name" in error &&
    error.name === "AbortError"
  );
}

function csrfToken(): string | null {
  if (typeof document === "undefined") {
    return null;
  }

  return document
    .querySelector<HTMLMetaElement>('meta[name="csrf-token"]')
    ?.getAttribute("content") ?? null;
}

function xsrfToken(): string | null {
  if (typeof document === "undefined") {
    return null;
  }

  const cookie = document.cookie
    .split(";")
    .map((part) => part.trim())
    .find((part) => part.startsWith("XSRF-TOKEN="));

  return cookie ? decodeURIComponent(cookie.slice("XSRF-TOKEN=".length)) : null;
}

function isRecord(value: unknown): value is Record<string, unknown> {
  return typeof value === "object" && value !== null && !Array.isArray(value);
}
