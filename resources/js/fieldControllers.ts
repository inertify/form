import {
  computed,
  ref,
  watch,
  type ComputedRef,
  type WritableComputedRef,
} from "vue";
import { useFormContext } from "./context";
import { hasPath } from "./internal/path";
import { normalizedSlotToken } from "./internal/resource";
import { useFormField } from "./useFormFields";
import { useFormUploads } from "./useFormUploads";
import type {
  FormField,
  FormUploadState,
  FormUploadedFile,
  UseFormApi,
  UseFormFieldApi,
} from "./types";

export interface TextInputController extends UseFormFieldApi {
  raw: WritableComputedRef<unknown>;
  text: WritableComputedRef<string>;
  display: WritableComputedRef<string>;
  mask: ComputedRef<string | null>;
  input: (value: string) => string;
  format: (value?: unknown) => string;
  parse: (value: string) => unknown;
}

export interface SlugController extends TextInputController {
  source: ComputedRef<string | null>;
  locked: ReturnType<typeof ref<boolean>>;
  generate: (value?: unknown) => string;
  manualInput: (value: string) => string;
}

export interface ChoicesController extends UseFormFieldApi {
  selected: ComputedRef<unknown[]>;
  isSelected: (value: unknown) => boolean;
  select: (value: unknown) => void;
  deselect: (value: unknown) => void;
  toggle: (value: unknown) => void;
}

export interface DateController extends UseFormFieldApi {
  date: WritableComputedRef<string>;
  parsed: ComputedRef<Date | null>;
  setDate: (value: Date | string | null) => void;
}

export interface FileController extends UseFormFieldApi {
  uploadState: FormUploadState;
  upload: (files: File | File[] | FileList) => Promise<FormUploadedFile[]>;
  remove: (indexOrToken?: number | string) => Promise<boolean>;
  reorder: (from: number, to: number) => void;
  cancelUpload: () => void;
  retryUpload: () => Promise<FormUploadedFile[]>;
  pauseUpload: () => void;
  resumeUpload: () => Promise<FormUploadedFile[]>;
}

export interface RichTextController extends UseFormFieldApi {
  html: WritableComputedRef<string>;
  images: ComputedRef<string[]>;
  imageUploadState: FormUploadState;
  setHtml: (html: string) => void;
  clearContent: () => void;
  uploadImages: (files: File | File[] | FileList) => Promise<FormUploadedFile[]>;
  removeImage: (indexOrToken?: number | string) => Promise<boolean>;
  reorderImage: (from: number, to: number) => void;
}

export interface ComposerController extends UseFormFieldApi {
  allowAttachments: ComputedRef<boolean>;
  text: WritableComputedRef<string>;
  attachments: ComputedRef<Array<string | File>>;
  attachmentUploadState: FormUploadState;
  setText: (text: string) => void;
  setAttachments: (attachments: Array<string | File>) => void;
  uploadAttachments: (
    files: File | File[] | FileList,
  ) => Promise<FormUploadedFile[]>;
  removeAttachment: (indexOrToken?: number | string) => Promise<boolean>;
  reorderAttachment: (from: number, to: number) => void;
}

export interface OtpController extends UseFormFieldApi {
  length: ComputedRef<number>;
  digits: ComputedRef<string[]>;
  complete: ComputedRef<boolean>;
  setDigit: (index: number, value: string) => void;
}

export interface LinkController extends UseFormFieldApi {
  mode: ComputedRef<"plain" | "structured">;
  url: WritableComputedRef<string>;
  label: WritableComputedRef<string>;
  linkTarget: WritableComputedRef<string>;
  href: ComputedRef<string | null>;
  target: ComputedRef<string | null>;
  rel: ComputedRef<string | null>;
  allowedSchemes: ComputedRef<string[]>;
  validScheme: ComputedRef<boolean>;
  normalizeUrl: (value: string) => string | null;
  setUrl: (value: string) => void;
  setLabel: (value: string) => void;
  setTarget: (value: string) => void;
}

export type FormFieldController =
  | UseFormFieldApi
  | TextInputController
  | SlugController
  | ChoicesController
  | DateController
  | FileController
  | RichTextController
  | ComposerController
  | OtpController
  | LinkController;

export function useTextInput(
  fieldOrPath: FormField | string,
  formApi?: UseFormApi,
): TextInputController {
  const base = useFormField(fieldOrPath, formApi);
  const mask = computed(() => maskProperty(base.field.value));
  const raw = computed({
    get: () => base.value.value,
    set: (value: unknown) => base.setValue(value),
  });

  function format(value: unknown = raw.value): string {
    if (mask.value) {
      return applyMask(String(value ?? ""), mask.value);
    }

    const numeric = numericFormat(base.field.value);

    if (numeric) {
      const parsed =
        typeof value === "number"
          ? value
          : parseLocalizedNumber(String(value ?? ""), numeric.locale);

      if (parsed !== null) {
        return new Intl.NumberFormat(numeric.locale, numeric.options).format(
          parsed,
        );
      }
    }

    return String(value ?? "");
  }

  function parse(value: string): unknown {
    if (mask.value) {
      return extractMaskValue(value, mask.value);
    }

    const numeric = numericFormat(base.field.value);

    if (numeric && base.field.value?.parseNumbers === true) {
      return parseLocalizedNumber(value, numeric.locale);
    }

    return value;
  }

  function input(value: string): string {
    const next = parse(value);
    base.setValue(next);

    return format(next);
  }

  const text = computed({
    get: () => format(),
    set: input,
  });

  return { ...base, raw, text, display: text, mask, input, format, parse };
}

export const useTextInputField = useTextInput;

export function useSlug(
  fieldOrPath: FormField | string,
  formApi?: UseFormApi,
): SlugController {
  const form = formApi ?? useFormContext();
  const text = useTextInput(fieldOrPath, form);
  const locked = ref(false);
  const source = computed(() =>
    stringProperty(text.field.value, "from", "source"),
  );
  const sourcePath = computed(() => {
    const configured = source.value;

    if (!configured) {
      return null;
    }

    if (configured.startsWith("$.")) {
      return configured.slice(2);
    }

    const rowPath = text.field.value?.rowPath;
    const row = typeof rowPath === "string" ? form.getValue(rowPath) : null;

    return rowPath && hasPath(row, configured)
      ? `${rowPath}.${configured}`
      : configured;
  });
  let generatedOnce = false;

  function generate(value?: unknown): string {
    if (locked.value && value === undefined) {
      return text.text.value;
    }

    const input =
      value ?? (sourcePath.value ? form.getValue(sourcePath.value) : "");
    const separator =
      stringProperty(text.field.value, "separator") ?? "-";
    const slug = slugify(
      String(input ?? ""),
      separator,
      text.field.value?.lowercase !== false,
    );
    text.setValue(slug);
    generatedOnce = true;

    return slug;
  }

  function manualInput(value: string): string {
    if (text.field.value?.lockOnManualEdit === true) {
      locked.value = true;
    }

    return text.input(value);
  }

  watch(
    () => (sourcePath.value ? form.getValue(sourcePath.value) : undefined),
    (value) => {
      if (!sourcePath.value || locked.value) {
        return;
      }

      if (
        text.field.value?.onlyWhenEmpty === true &&
        String(text.value.value ?? "") !== ""
      ) {
        return;
      }

      if (
        generatedOnce &&
        text.field.value?.updateOnEdit === false
      ) {
        return;
      }

      generate(value);
    },
    { immediate: true },
  );

  return { ...text, source, locked, generate, manualInput };
}

export const useSlugField = useSlug;

export function useChoices(
  fieldOrPath: FormField | string,
  formApi?: UseFormApi,
): ChoicesController {
  const base = useFormField(fieldOrPath, formApi);
  const selected = computed(() => {
    const value = base.value.value;

    return Array.isArray(value)
      ? value
      : value === null || value === undefined || value === ""
        ? []
        : [value];
  });

  function isSelected(value: unknown): boolean {
    return selected.value.some(
      (item) => Object.is(item, value) || String(item) === String(value),
    );
  }

  function select(value: unknown): void {
    if (base.field.value?.multiple === true) {
      if (!isSelected(value)) {
        base.setValue([...selected.value, value]);
      }

      return;
    }

    base.setValue(value);
  }

  function deselect(value: unknown): void {
    if (base.field.value?.multiple === true) {
      base.setValue(selected.value.filter((item) => !sameValue(item, value)));

      return;
    }

    if (isSelected(value)) {
      base.clear();
    }
  }

  function toggle(value: unknown): void {
    if (isSelected(value)) {
      deselect(value);
    } else {
      select(value);
    }
  }

  return { ...base, selected, isSelected, select, deselect, toggle };
}

export const useChoicesField = useChoices;

export function useDate(
  fieldOrPath: FormField | string,
  formApi?: UseFormApi,
): DateController {
  const base = useFormField(fieldOrPath, formApi);
  const date = computed({
    get: () => String(base.value.value ?? ""),
    set: (value: string) => base.setValue(value === "" ? null : value),
  });
  const parsed = computed(() => {
    if (date.value === "") {
      return null;
    }

    const result = new Date(date.value);

    return Number.isNaN(result.getTime()) ? null : result;
  });

  function setDate(value: Date | string | null): void {
    base.setValue(
      value instanceof Date
        ? value.toISOString().slice(0, 10)
        : value === ""
          ? null
          : value,
    );
  }

  return { ...base, date, parsed, setDate };
}

export const useDateField = useDate;

export function useFile(
  fieldOrPath: FormField | string,
  formApi?: UseFormApi,
): FileController {
  const form = formApi ?? useFormContext();
  const base = useFormField(fieldOrPath, form);
  const uploads = useFormUploads(form);

  return {
    ...base,
    uploadState: uploads.state(base.path),
    upload: (files) => uploads.upload(base.path, files),
    remove: (indexOrToken) => uploads.remove(base.path, indexOrToken),
    reorder: (from, to) => uploads.reorder(base.path, from, to),
    cancelUpload: () => uploads.cancel(base.path),
    retryUpload: () => uploads.retry(base.path),
    pauseUpload: () => uploads.pause(base.path),
    resumeUpload: () => uploads.resume(base.path),
  };
}

export const useFileField = useFile;

export function useRichText(
  fieldOrPath: FormField | string,
  formApi?: UseFormApi,
): RichTextController {
  const form = formApi ?? useFormContext();
  const base = useFormField(fieldOrPath, form);
  const uploads = useFormUploads(form);
  const imagesPath = `${base.path}_images`;
  const html = computed({
    get: () => String(base.value.value ?? ""),
    set: (value: string) => base.setValue(value),
  });

  return {
    ...base,
    html,
    images: computed(() => {
      const value = form.getValue(imagesPath);

      return Array.isArray(value)
        ? value.filter((item): item is string => typeof item === "string")
        : [];
    }),
    imageUploadState: uploads.state(imagesPath),
    setHtml: (value: string) => base.setValue(value),
    clearContent: () => base.setValue(""),
    uploadImages: (files) => uploads.upload(imagesPath, files),
    removeImage: (indexOrToken) => uploads.remove(imagesPath, indexOrToken),
    reorderImage: (from, to) => uploads.reorder(imagesPath, from, to),
  };
}

export const useRichTextField = useRichText;

export function useComposer(
  fieldOrPath: FormField | string,
  formApi?: UseFormApi,
): ComposerController {
  const form = formApi ?? useFormContext();
  const base = useFormField(fieldOrPath, form);
  const uploads = useFormUploads(form);
  const attachmentsPath = `${base.path}.attachments`;
  const allowAttachments = computed(
    () => base.field.value?.allowAttachments === true,
  );

  function composerValue(): { text: string; attachments: Array<string | File> } {
    const value = base.value.value;

    if (!allowAttachments.value) {
      return {
        text: typeof value === "string" ? value : "",
        attachments: [],
      };
    }

    return isRecord(value)
      ? {
          text: typeof value.text === "string" ? value.text : "",
          attachments: Array.isArray(value.attachments)
            ? value.attachments.filter(
                (item): item is string | File =>
                  typeof item === "string" || isNativeFile(item),
              )
            : [],
        }
      : { text: typeof value === "string" ? value : "", attachments: [] };
  }

  function setText(value: string): void {
    if (allowAttachments.value) {
      base.setValue({ ...composerValue(), text: value });
    } else {
      base.setValue(value === "" ? null : value);
    }
  }

  function setAttachments(attachments: Array<string | File>): void {
    base.setValue({ ...composerValue(), attachments: [...attachments] });
  }

  const text = computed({
    get: () => composerValue().text,
    set: setText,
  });

  return {
    ...base,
    allowAttachments,
    text,
    attachments: computed(() => composerValue().attachments),
    attachmentUploadState: uploads.state(attachmentsPath),
    setText,
    setAttachments,
    uploadAttachments: (files) => uploads.upload(attachmentsPath, files),
    removeAttachment: (indexOrToken) =>
      uploads.remove(attachmentsPath, indexOrToken),
    reorderAttachment: (from, to) =>
      uploads.reorder(attachmentsPath, from, to),
  };
}

export const useComposerField = useComposer;

export function useOtp(
  fieldOrPath: FormField | string,
  formApi?: UseFormApi,
): OtpController {
  const base = useFormField(fieldOrPath, formApi);
  const length = computed(() => {
    const candidate = Number(base.field.value?.length ?? 6);

    return Number.isFinite(candidate) && candidate > 0 ? Math.floor(candidate) : 6;
  });
  const digits = computed(() =>
    String(base.value.value ?? "")
      .slice(0, length.value)
      .padEnd(length.value, " ")
      .split("")
      .map((digit) => (digit === " " ? "" : digit)),
  );
  const complete = computed(
    () => digits.value.length === length.value && digits.value.every(Boolean),
  );

  function setDigit(index: number, value: string): void {
    if (index < 0 || index >= length.value) {
      return;
    }

    const next = [...digits.value];
    next[index] = value.slice(-1);
    base.setValue(next.join(""));
  }

  return { ...base, length, digits, complete, setDigit };
}

export const useOtpField = useOtp;

export function useLink(
  fieldOrPath: FormField | string,
  formApi?: UseFormApi,
): LinkController {
  const base = useFormField(fieldOrPath, formApi);
  const mode = computed<"plain" | "structured">(() =>
    base.field.value?.mode === "structured" || isRecord(base.value.value)
      ? "structured"
      : "plain",
  );
  const allowedSchemes = computed(() => {
    const configured = base.field.value?.allowedSchemes;

    return Array.isArray(configured)
      ? configured.filter(
          (scheme): scheme is string =>
            typeof scheme === "string" && scheme !== "",
        ).map((scheme) => scheme.replace(/:$/, "").toLowerCase())
      : ["http", "https", "mailto", "tel"];
  });

  function structuredValue(): Record<string, unknown> {
    return isRecord(base.value.value)
      ? { ...base.value.value }
      : { url: String(base.value.value ?? "") };
  }

  function rawUrl(): string {
    return mode.value === "structured"
      ? String(structuredValue().url ?? "")
      : String(base.value.value ?? "");
  }

  function normalizeUrl(value: string): string | null {
    const trimmed = value.trim();

    if (trimmed === "") {
      return "";
    }

    const match = trimmed.match(/^([a-z][a-z0-9+.-]*):/i);

    if (match?.[1]) {
      return allowedSchemes.value.includes(match[1].toLowerCase())
        ? trimmed
        : null;
    }

    if (base.field.value?.requireScheme !== true) {
      return trimmed;
    }

    const scheme = allowedSchemes.value.includes("https")
      ? "https"
      : allowedSchemes.value[0];

    return scheme ? `${scheme}://${trimmed.replace(/^\/\//, "")}` : null;
  }

  function setStructuredPart(key: string, value: string): void {
    base.setValue({ ...structuredValue(), [key]: value });
  }

  function setUrl(value: string): void {
    const normalized = normalizeUrl(value);
    const next = normalized ?? value.trim();

    if (mode.value === "structured") {
      setStructuredPart("url", next);
    } else {
      base.setValue(next);
    }
  }

  function setLabel(value: string): void {
    setStructuredPart("label", value);
  }

  function setTarget(value: string): void {
    setStructuredPart("target", value);
  }

  const url = computed({
    get: rawUrl,
    set: setUrl,
  });
  const label = computed({
    get: () => String(structuredValue().label ?? ""),
    set: setLabel,
  });
  const linkTarget = computed({
    get: () => String(structuredValue().target ?? ""),
    set: setTarget,
  });
  const href = computed(() => normalizeUrl(rawUrl()));
  const validScheme = computed(() => href.value !== null);

  return {
    ...base,
    mode,
    url,
    label,
    linkTarget,
    href,
    target: computed(() =>
      mode.value === "structured"
        ? linkTarget.value || null
        : stringProperty(base.field.value, "target"),
    ),
    rel: computed(() => stringProperty(base.field.value, "rel")),
    allowedSchemes,
    validScheme,
    normalizeUrl,
    setUrl,
    setLabel,
    setTarget,
  };
}

export const useLinkField = useLink;

export function useFormFieldController(
  fieldOrPath: FormField | string,
  formApi?: UseFormApi,
): FormFieldController {
  const form = formApi ?? useFormContext();
  const field =
    typeof fieldOrPath === "string"
      ? form.resolveField(fieldOrPath)
      : fieldOrPath;
  const component =
    normalizedSlotToken(field?.component)?.replace(/-/g, "") ?? "";

  if (["slug"].includes(component)) {
    return useSlug(fieldOrPath, form);
  }

  if (["checkbox", "checkboxgroup", "radio", "combobox", "toggle"].includes(component)) {
    return useChoices(fieldOrPath, form);
  }

  if (["datepicker", "date", "timepicker", "datetime"].includes(component)) {
    return useDate(fieldOrPath, form);
  }

  if (["file", "fileupload"].includes(component)) {
    return useFile(fieldOrPath, form);
  }

  if (component === "richtext") {
    return useRichText(fieldOrPath, form);
  }

  if (component === "composer") {
    return useComposer(fieldOrPath, form);
  }

  if (component === "otp") {
    return useOtp(fieldOrPath, form);
  }

  if (component === "link") {
    return useLink(fieldOrPath, form);
  }

  if (["text", "textarea", "hidden", "colorpicker"].includes(component)) {
    return useTextInput(fieldOrPath, form);
  }

  return useFormField(fieldOrPath, form);
}

function applyMask(value: string, mask: string | null): string {
  if (!mask) {
    return value;
  }

  const input = [...extractMaskValue(value, mask)];
  let cursor = 0;
  let output = "";

  for (const token of mask) {
    if (!isMaskToken(token)) {
      if (input.length > 0 && cursor < input.length) {
        output += token;
      }

      continue;
    }

    const candidate = input[cursor];

    if (candidate === undefined) {
      break;
    }

    output += candidate;
    cursor += 1;
  }

  return output;
}

function extractMaskValue(value: string, mask: string): string {
  const input = [...value];
  let cursor = 0;
  let output = "";

  for (const token of mask) {
    if (!isMaskToken(token)) {
      if (input[cursor] === token) {
        cursor += 1;
      }

      continue;
    }

    while (cursor < input.length) {
      const candidate = input[cursor] ?? "";
      cursor += 1;

      if (matchesMaskToken(candidate, token)) {
        output += candidate;
        break;
      }
    }
  }

  return output;
}

function isMaskToken(token: string): boolean {
  return ["9", "#", "a", "A", "*"].includes(token);
}

function matchesMaskToken(candidate: string, token: string): boolean {
  if (token === "9" || token === "#") {
    return /\d/.test(candidate);
  }

  if (token === "a" || token === "A") {
    return /[a-z]/i.test(candidate);
  }

  return /[a-z0-9]/i.test(candidate);
}

function maskProperty(field: FormField | null): string | null {
  const value = field?.mask;

  if (typeof value === "string" && value !== "") {
    return value;
  }

  if (Array.isArray(value)) {
    return value.find(
      (candidate): candidate is string =>
        typeof candidate === "string" && candidate !== "",
    ) ?? null;
  }

  return null;
}

interface NumericFormat {
  locale: string;
  options: Intl.NumberFormatOptions;
}

function numericFormat(field: FormField | null): NumericFormat | null {
  const currency = field?.currency;
  const number = field?.numberFormat;
  const configured = isRecord(currency)
    ? currency
    : isRecord(number)
      ? number
      : null;

  if (!configured) {
    return null;
  }

  const locale =
    typeof configured.locale === "string" && configured.locale !== ""
      ? configured.locale
      : "en-US";
  const decimals = Number(configured.decimals ?? 0);
  const options: Intl.NumberFormatOptions = {
    minimumFractionDigits:
      Number.isInteger(decimals) && decimals >= 0 ? decimals : 0,
    maximumFractionDigits:
      Number.isInteger(decimals) && decimals >= 0 ? decimals : 0,
  };

  if (isRecord(currency)) {
    options.style = "currency";
    options.currency =
      typeof currency.currency === "string" && currency.currency !== ""
        ? currency.currency
        : "USD";
  }

  return { locale, options };
}

function parseLocalizedNumber(value: string, locale: string): number | null {
  const formatter = new Intl.NumberFormat(locale);
  const parts = formatter.formatToParts(12345.6);
  const group = parts.find((part) => part.type === "group")?.value ?? ",";
  const decimal = parts.find((part) => part.type === "decimal")?.value ?? ".";
  let normalized = value.trim();

  for (let digit = 0; digit <= 9; digit += 1) {
    const localized = new Intl.NumberFormat(locale, {
      useGrouping: false,
    }).format(digit);
    normalized = normalized.replace(
      new RegExp(escapeRegex(localized), "g"),
      String(digit),
    );
  }

  const negative = /^\s*\(/.test(value) && /\)\s*$/.test(value);
  normalized = normalized
    .replace(new RegExp(escapeRegex(group), "g"), "")
    .replace(new RegExp(escapeRegex(decimal), "g"), ".")
    .replace(/[^0-9+\-.]/g, "");
  const parsed = Number(`${negative ? "-" : ""}${normalized}`);

  return Number.isFinite(parsed) ? parsed : null;
}

function slugify(
  value: string,
  separator: string,
  lowercase: boolean,
): string {
  const normalized = value
    .normalize("NFKD")
    .replace(/[\u0300-\u036f]/g, "")
    .trim();
  const cased = lowercase ? normalized.toLowerCase() : normalized;

  return cased
    .replace(/[^a-z0-9]+/gi, separator)
    .replace(new RegExp(`${escapeRegex(separator)}+`, "g"), separator)
    .replace(new RegExp(`^${escapeRegex(separator)}|${escapeRegex(separator)}$`, "g"), "");
}

function isRecord(value: unknown): value is Record<string, unknown> {
  return typeof value === "object" && value !== null && !Array.isArray(value);
}

function isNativeFile(value: unknown): value is File {
  return typeof File !== "undefined" && value instanceof File;
}

/*
 * Kept below the behavior helpers so custom controllers can reuse the same
 * field option lookup without interpreting null or empty strings as values.
 */
function stringProperty(
  field: FormField | null,
  ...keys: string[]
): string | null {
  for (const key of keys) {
    const value = field?.[key];

    if (typeof value === "string" && value !== "") {
      return value;
    }
  }

  return null;
}

function sameValue(left: unknown, right: unknown): boolean {
  return Object.is(left, right) || String(left) === String(right);
}

function escapeRegex(value: string): string {
  return value.replace(/[.*+?^${}()|[\]\\]/g, "\\$&");
}
