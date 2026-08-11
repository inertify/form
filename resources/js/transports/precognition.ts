import type {
  FormDataValues,
  FormValidationResult,
  FormValidationTransport,
} from "../types";

export const defaultValidationTransport: FormValidationTransport = async (
  request,
) => {
  const method = request.method.toUpperCase();
  const headers: Record<string, string> = {
    Accept: "application/json",
    "Content-Type": "application/json",
    "X-Requested-With": "XMLHttpRequest",
    Precognition: "true",
    "Precognition-Validate-Only": request.path,
  };
  const csrf = csrfToken();

  if (csrf) {
    headers["X-CSRF-TOKEN"] = csrf;
  }

  const url =
    method === "GET"
      ? withQuery(request.action, request.data)
      : request.action;
  const response = await fetch(url, {
    method,
    headers,
    credentials: "same-origin",
    signal: request.signal,
    ...(method === "GET" ? {} : { body: JSON.stringify(request.data) }),
  });

  if (response.ok) {
    return { valid: true } satisfies FormValidationResult;
  }

  const payload = await readJson(response);

  if (response.status === 422) {
    return {
      valid: false,
      errors: normalizeResponseErrors(payload),
    } satisfies FormValidationResult;
  }

  const message =
    typeof payload.message === "string"
      ? payload.message
      : `Validation request failed with status ${response.status}.`;

  throw new Error(message);
};

async function readJson(response: Response): Promise<Record<string, unknown>> {
  try {
    const value: unknown = await response.json();

    return typeof value === "object" && value !== null
      ? (value as Record<string, unknown>)
      : {};
  } catch {
    return {};
  }
}

function normalizeResponseErrors(
  payload: Record<string, unknown>,
): Record<string, string | string[]> {
  const errors = payload.errors;

  if (typeof errors !== "object" || errors === null || Array.isArray(errors)) {
    return {};
  }

  return errors as Record<string, string | string[]>;
}

function csrfToken(): string | null {
  if (typeof document === "undefined") {
    return null;
  }

  return document
    .querySelector<HTMLMetaElement>('meta[name="csrf-token"]')
    ?.getAttribute("content") ?? null;
}

function withQuery(url: string, data: FormDataValues): string {
  const target = new URL(
    url,
    typeof window === "undefined" ? "http://localhost" : window.location.origin,
  );

  appendQuery(target.searchParams, data);

  return /^https?:\/\//i.test(url)
    ? target.toString()
    : `${target.pathname}${target.search}${target.hash}`;
}

function appendQuery(
  output: URLSearchParams,
  value: unknown,
  prefix?: string,
): void {
  if (Array.isArray(value)) {
    value.forEach((item, index) => appendQuery(output, item, `${prefix ?? ""}[${index}]`));

    return;
  }

  if (typeof value === "object" && value !== null) {
    Object.entries(value).forEach(([key, item]) => {
      const path = prefix ? `${prefix}[${key}]` : key;
      appendQuery(output, item, path);
    });

    return;
  }

  if (prefix && value !== null && value !== undefined) {
    output.append(prefix, String(value));
  }
}
