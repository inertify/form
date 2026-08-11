import { reactive, watch } from "vue";
import { useFormContext } from "./context";
import { getFormRuntime } from "./internal/runtime";
import {
  existingFilesForField,
  normalizeExistingFiles,
  uploadSelectionKey,
  uploadValueArray,
} from "./internal/uploadValues";
import {
  defaultUploadTransport,
  normalizeUploadDescriptor,
} from "./transports/uploads";
import type {
  FormField,
  FormUploadState,
  FormUploadResumeState,
  FormUploadedFile,
  UseFormApi,
  UseFormUploadsApi,
} from "./types";

const cache = new WeakMap<object, UseFormUploadsApi>();

export function useFormUploads(formApi?: UseFormApi): UseFormUploadsApi {
  const form = formApi ?? useFormContext();
  const existing = cache.get(form);

  if (existing) {
    return existing;
  }

  const states = reactive<Record<string, FormUploadState>>({});
  const controllers = new Map<string, AbortController>();
  const sessions = new Map<string, FormUploadResumeState>();
  const fileCatalogs = new Map<string, Map<string, FormUploadedFile>>();

  function state(path: string): FormUploadState {
    if (!states[path]) {
      const field = resolveUploadField(path);
      const existingFiles = existingFilesForField(
        form.resource.value,
        path,
        field,
      );
      rememberFiles(path, existingFiles);
      states[path] = {
        status: "idle",
        progress: null,
        loaded: 0,
        total: 0,
        error: null,
        files: filesForSelection(path, form.getValue(path)),
        pendingFiles: [],
      };
    }

    return states[path] as FormUploadState;
  }

  async function upload(
    path: string,
    input: File | File[] | FileList,
  ): Promise<FormUploadedFile[]> {
    return runUpload(path, normalizeFiles(input), null, true);
  }

  async function runUpload(
    path: string,
    files: File[],
    resumeSession: FormUploadResumeState | null,
    replaceSelection: boolean,
  ): Promise<FormUploadedFile[]> {
    const field = resolveUploadField(path);

    if (!field) {
      throw new Error(`Unknown form field: ${path}.`);
    }

    const descriptor = normalizeUploadDescriptor(field);

    if (!descriptor) {
      throw new Error(`No upload transport is configured for ${path}.`);
    }

    enforceLimits(files, descriptor.limits.maxSizeKiB);
    controllers.get(path)?.abort(cancelReason());
    const controller = new AbortController();
    controllers.set(path, controller);
    const current = state(path);
    current.pendingFiles = [...files];

    if (replaceSelection) {
      sessions.delete(path);
    }

    current.status = "uploading";
    current.progress = 0;
    current.loaded = 0;
    current.total = files.reduce((sum, file) => sum + file.size, 0);
    current.error = null;

    try {
      const transport = form.options.uploadTransport ?? defaultUploadTransport;
      const uploaded = await transport.upload({
        path,
        field,
        files,
        descriptor,
        signal: controller.signal,
        resume: resumeSession,
        onSession(session) {
          if (session) {
            sessions.set(path, session);
          } else {
            sessions.delete(path);
          }
        },
        onProgress(percentage, loaded = 0, total = current.total) {
          current.progress = percentage;
          current.loaded = loaded;
          current.total = total;
        },
      });
      const multiple = field.multiple === true;
      rememberFiles(path, uploaded);

      if (descriptor.strategy === "form") {
        const localFiles = uploaded
          .map((file) => file.file)
          .filter(isNativeFile);
        const previous = multiple ? uploadValueArray(form.getValue(path)) : [];
        form.setValue(
          path,
          multiple ? [...previous, ...localFiles] : (localFiles[0] ?? null),
        );
      } else {
        const previous = multiple ? tokenArray(form.getValue(path)) : [];
        const tokens = uploaded.map((file) => file.key);
        form.setValue(
          path,
          multiple ? [...previous, ...tokens] : (tokens[0] ?? null),
        );
      }

      current.files = filesForSelection(path, form.getValue(path));
      current.status = "success";
      current.progress = 100;
      current.pendingFiles = [];
      sessions.delete(path);

      return uploaded;
    } catch (error) {
      if (isPauseSignal(controller.signal)) {
        current.status = "paused";
        current.error = null;

        return [];
      }

      if (!isAbortError(error)) {
        current.status = "error";
        current.error = error instanceof Error ? error.message : "Upload failed.";
      } else {
        current.status = "idle";
        current.pendingFiles = [];
        sessions.delete(path);
      }

      throw error;
    } finally {
      if (controllers.get(path) === controller) {
        controllers.delete(path);
      }
    }
  }

  async function retry(path: string): Promise<FormUploadedFile[]> {
    const pending = [...state(path).pendingFiles];

    if (pending.length === 0) {
      return [];
    }

    return runUpload(path, pending, sessions.get(path) ?? null, false);
  }

  function pause(path: string): void {
    const current = state(path);

    if (!controllers.has(path)) {
      return;
    }

    current.status = "paused";
    controllers.get(path)?.abort(pauseReason());
  }

  function resume(path: string): Promise<FormUploadedFile[]> {
    return retry(path);
  }

  async function remove(
    path: string,
    indexOrToken: number | string = 0,
  ): Promise<boolean> {
    const field = resolveUploadField(path);
    const descriptor = field ? normalizeUploadDescriptor(field) : null;

    if (!field || !descriptor) {
      return false;
    }

    const values = uploadValueArray(form.getValue(path));
    const index =
      typeof indexOrToken === "number"
        ? indexOrToken
        : values.findIndex(
            (value) => selectionIdentifier(value) === indexOrToken,
          );

    if (index < 0 || index >= values.length) {
      return false;
    }

    const token = uploadSelectionKey(values[index]);

    if (typeof token === "string" && descriptor.strategy !== "form") {
      const transport = form.options.uploadTransport ?? defaultUploadTransport;

      if (transport.remove) {
        const controller = new AbortController();
        await transport.remove({
          path,
          field,
          file: state(path).files[index] ?? null,
          token,
          descriptor,
          signal: controller.signal,
        });
      }
    }

    const next = [...values];
    next.splice(index, 1);
    form.setValue(path, field.multiple === true ? next : null);
    state(path).files = filesForSelection(path, form.getValue(path));

    return true;
  }

  function reorder(path: string, from: number, to: number): void {
    const value = uploadValueArray(form.getValue(path));

    if (
      from < 0 ||
      to < 0 ||
      from >= value.length ||
      to >= value.length ||
      from === to
    ) {
      return;
    }

    const next = [...value];
    const [token] = next.splice(from, 1);

    if (token !== undefined) {
      next.splice(to, 0, token);
    }

    form.setValue(path, next);
    state(path).files = filesForSelection(path, next);
  }

  function cancel(path?: string): void {
    if (path) {
      controllers.get(path)?.abort(cancelReason());
      controllers.delete(path);
      sessions.delete(path);
      const current = state(path);
      current.status = "idle";
      current.pendingFiles = [];

      return;
    }

    for (const controller of controllers.values()) {
      controller.abort(cancelReason());
    }

    controllers.clear();
    sessions.clear();

    for (const current of Object.values(states)) {
      current.status = "idle";
      current.pendingFiles = [];
    }
  }

  function clear(path: string): void {
    cancel(path);
    form.clearField(path);
    const current = state(path);
    current.status = "idle";
    current.progress = null;
    current.loaded = 0;
    current.total = 0;
    current.error = null;
    current.files = [];
    current.pendingFiles = [];
  }

  watch(
    [() => form.data.value, () => form.resource.value],
    () => {
      for (const [path, current] of Object.entries(states)) {
        const field = resolveUploadField(path);
        rememberFiles(
          path,
          existingFilesForField(form.resource.value, path, field),
        );
        current.files = filesForSelection(path, form.getValue(path));
      }
    },
    { deep: true, flush: "sync" },
  );

  const api: UseFormUploadsApi = {
    states,
    state,
    upload,
    retry,
    pause,
    resume,
    remove,
    reorder,
    cancel,
    clear,
  };
  getFormRuntime(form).cancelUploads = () => cancel();
  cache.set(form, api);

  return api;

  function resolveUploadField(path: string): ReturnType<typeof form.resolveField> | FormField | null {
    const exact = form.resolveField(path);

    if (exact) {
      return exact;
    }

    if (path.endsWith(".attachments")) {
      const parent = form.resolveField(path.slice(0, -".attachments".length));

      return parent?.allowAttachments === true
        ? ({ ...parent, name: path, multiple: true } as FormField)
        : null;
    }

    if (path.endsWith("_images")) {
      const parent = form.resolveField(path.slice(0, -"_images".length));
      const imageUploads = parent?.imageUploads;

      if (parent && typeof imageUploads === "object" && imageUploads !== null) {
        return {
          ...parent,
          ...(imageUploads as Record<string, unknown>),
          name: path,
          multiple: true,
        } as FormField;
      }
    }

    return null;
  }

  function rememberFiles(path: string, files: FormUploadedFile[]): void {
    const catalog = fileCatalogs.get(path) ?? new Map<string, FormUploadedFile>();

    for (const file of files) {
      catalog.set(file.key, file);
    }

    fileCatalogs.set(path, catalog);
  }

  function filesForSelection(path: string, value: unknown): FormUploadedFile[] {
    const catalog = fileCatalogs.get(path) ?? new Map<string, FormUploadedFile>();
    const files: FormUploadedFile[] = [];

    for (const selected of uploadValueArray(value)) {
      if (isNativeFile(selected)) {
        const known = catalog.get(selected.name);
        const file = known ?? localFileResource(selected);
        catalog.set(file.key, file);
        files.push(file);
        continue;
      }

      const inline = normalizeExistingFiles(selected)[0];

      if (inline) {
        catalog.set(inline.key, inline);
      }

      const key = uploadSelectionKey(selected);

      if (!key) {
        continue;
      }

      const file = catalog.get(key) ?? unknownFileResource(key);
      catalog.set(key, file);
      files.push(file);
    }

    fileCatalogs.set(path, catalog);

    return files;
  }
}

function normalizeFiles(value: File | File[] | FileList): File[] {
  return isNativeFile(value)
    ? [value]
    : Array.isArray(value)
      ? value
      : Array.from(value);
}

function isNativeFile(value: unknown): value is File {
  return typeof File !== "undefined" && value instanceof File;
}

function enforceLimits(files: File[], maxSizeKiB: unknown): void {
  const limit = Number(maxSizeKiB);

  if (!Number.isFinite(limit) || limit <= 0) {
    return;
  }

  const maxBytes = limit * 1024;
  const oversized = files.find((file) => file.size > maxBytes);

  if (oversized) {
    throw new Error(`${oversized.name} exceeds the maximum upload size.`);
  }
}

function tokenArray(value: unknown): string[] {
  return uploadValueArray(value)
    .map(uploadSelectionKey)
    .filter((item): item is string => item !== null);
}

function selectionIdentifier(value: unknown): string | null {
  return isNativeFile(value) ? value.name : uploadSelectionKey(value);
}

function localFileResource(file: File): FormUploadedFile {
  return {
    key: file.name,
    name: file.name,
    mimeType: file.type || null,
    mime_type: file.type || null,
    size: file.size,
    file,
  };
}

function unknownFileResource(key: string): FormUploadedFile {
  return {
    key,
    name: key,
    mimeType: null,
    mime_type: null,
    size: 0,
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

function pauseReason(): Error {
  const error = new Error("Upload paused.");
  error.name = "UploadPaused";

  return error;
}

function cancelReason(): Error {
  const error = new Error("Upload cancelled.");
  error.name = "UploadCancelled";

  return error;
}

function isPauseSignal(signal: AbortSignal): boolean {
  return (
    signal.aborted &&
    typeof signal.reason === "object" &&
    signal.reason !== null &&
    "name" in signal.reason &&
    signal.reason.name === "UploadPaused"
  );
}
