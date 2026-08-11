import { deepClone, getPath, setPath } from "./path";
import { normalizedSlotToken, resolveFormFields } from "./resource";
import type {
  FormDataValues,
  FormField,
  FormResource,
  FormUploadedFile,
} from "../types";

/**
 * Existing files are resources for display, but the form value submitted back
 * to Laravel is always their encrypted key. Keep that distinction at the form
 * boundary so untouched forms cannot accidentally submit display metadata.
 */
export function normalizeUploadData<TData extends FormDataValues>(
  resource: FormResource<TData>,
  options: { preferExplicitExistingFiles?: boolean } = {},
): TData {
  const data = deepClone(resource.data);

  for (const field of resolveFormFields(resource.fieldsets ?? [], data)) {
    if (!isFileField(field)) {
      continue;
    }

    const explicit = options.preferExplicitExistingFiles === false
      ? []
      : normalizeExistingFiles(field.existingFiles);
    const source = explicit.length > 0
      ? explicit
      : getPath(data, field.path);

    setPath(data, field.path, normalizeUploadValue(source, field.multiple === true));
  }

  return data;
}

/** Resolve browser-facing metadata without changing the selected form value. */
export function existingFilesForField(
  resource: FormResource,
  path: string,
  field: FormField | null,
): FormUploadedFile[] {
  const explicit = normalizeExistingFiles(field?.existingFiles);

  return explicit.length > 0
    ? explicit
    : normalizeExistingFiles(getPath(resource.data, path));
}

export function normalizeExistingFiles(value: unknown): FormUploadedFile[] {
  return valueArray(value)
    .map(normalizeExistingFile)
    .filter((file): file is FormUploadedFile => file !== null);
}

export function uploadSelectionKey(value: unknown): string | null {
  if (typeof value === "string" && value !== "") {
    return value;
  }

  if (isRecord(value) && typeof value.key === "string" && value.key !== "") {
    return value.key;
  }

  return null;
}

export function uploadValueArray(value: unknown): unknown[] {
  if (Array.isArray(value)) {
    return value;
  }

  return value === null || value === undefined ? [] : [value];
}

function normalizeUploadValue(value: unknown, multiple: boolean): unknown {
  const selected = uploadValueArray(value)
    .map((item) => {
      if (isNativeFile(item)) {
        return item;
      }

      return uploadSelectionKey(item);
    })
    .filter((item): item is File | string => item !== null);

  return multiple ? selected : (selected[0] ?? null);
}

function normalizeExistingFile(value: unknown): FormUploadedFile | null {
  if (!isRecord(value)) {
    return null;
  }

  const key = uploadSelectionKey(value);

  if (!key) {
    return null;
  }

  const name = firstString(
    value.name,
    value.fileName,
    value.filename,
    value.originalName,
  ) ?? key;
  const mimeType = firstString(value.mimeType, value.mime_type, value.type);
  const size = Number(value.size ?? 0);

  return {
    ...deepClone(value),
    key,
    name,
    mimeType,
    mime_type: mimeType,
    size: Number.isFinite(size) && size >= 0 ? size : 0,
  };
}

function isFileField(field: FormField): boolean {
  const component = normalizedSlotToken(field.component)?.replace(/-/g, "");

  return component === "file" || component === "fileupload";
}

function isNativeFile(value: unknown): value is File {
  return typeof File !== "undefined" && value instanceof File;
}

function valueArray(value: unknown): unknown[] {
  return Array.isArray(value)
    ? value
    : value === null || value === undefined
      ? []
      : [value];
}

function isRecord(value: unknown): value is Record<string, unknown> {
  return typeof value === "object" && value !== null;
}

function firstString(...values: unknown[]): string | null {
  const value = values.find(
    (candidate): candidate is string =>
      typeof candidate === "string" && candidate !== "",
  );

  return value ?? null;
}
