import type {
  FormEndpoint,
  FormField,
  FormUploadDescriptor,
  FormUploadRequest,
  FormUploadTransport,
  FormUploadedFile,
} from "../types";

export function normalizeUploadDescriptor(
  field: FormField,
): FormUploadDescriptor | null {
  const nested = field.upload;
  const hasFlatDescriptor = Boolean(
    field.storeWithForm ||
      field.temporaryUploadUrl ||
      field.chunked ||
      field.directToStorage ||
      field.uploadUrl ||
      field.chunkUrl ||
      field.directUrl,
  );

  if (!hasFlatDescriptor && nested) {
    return nested;
  }

  if (!hasFlatDescriptor) {
    return null;
  }

  const strategy = field.storeWithForm
    ? "form"
    : field.directToStorage
      ? "direct"
      : field.chunked
        ? "chunked"
        : nested?.strategy ?? "temporary";
  const nestedEndpoints = nested?.endpoints;

  return {
    strategy,
    endpoints: {
      store: endpoint(
        field.temporaryUploadUrl ?? field.uploadUrl ?? nestedEndpoints?.store,
        "post",
      ),
      destroy: endpoint(
        field.temporaryUploadDeleteUrl ?? field.deleteUrl ?? nestedEndpoints?.destroy,
        "delete",
      ),
      chunked: normalizeEndpointMap(field.chunkedUrls, nestedEndpoints?.chunked, {
        start: "post",
        status: "get",
        append: "post",
        complete: "post",
        abort: "delete",
      }, {
        append: field.chunkUrl,
        complete: field.completeUrl,
      }),
      direct: normalizeEndpointMap(field.directUploadUrls, nestedEndpoints?.direct, {
        start: "post",
        object: "put",
        signPart: "post",
        status: "get",
        complete: "post",
        abort: "delete",
      }, { object: field.directUrl }),
    },
    limits: {
      ...nested?.limits,
      chunkSizeBytes:
        field.chunkSize ?? nested?.limits.chunkSizeBytes ?? null,
      partSizeBytes:
        field.uploadPartSize ?? nested?.limits.partSizeBytes ?? null,
      multipartThresholdBytes:
        field.uploadMultipartThreshold ??
        nested?.limits.multipartThresholdBytes ??
        null,
    },
    disk: field.uploadDisk ?? nested?.disk ?? null,
    rulesToken: field.uploadRulesToken ?? nested?.rulesToken ?? null,
    requiresRulesToken:
      field.requiresUploadRulesToken ??
      nested?.requiresRulesToken ??
      false,
  };
}

export const defaultUploadTransport: FormUploadTransport = {
  async upload(request) {
    if (request.descriptor.strategy === "form") {
      return request.files.map(localFileResource);
    }

    const results: FormUploadedFile[] = [];
    let completedBytes = 0;
    const totalBytes = request.files.reduce((sum, file) => sum + file.size, 0);

    for (const [fileIndex, file] of request.files.entries()) {
      const onProgress = (loaded: number) => {
        const aggregate = completedBytes + loaded;
        request.onProgress(
          totalBytes > 0 ? Math.round((aggregate / totalBytes) * 100) : null,
          aggregate,
          totalBytes,
        );
      };
      const fileRequest: FormUploadRequest = {
        ...request,
        resume:
          request.resume?.fileIndex === fileIndex ? request.resume : null,
      };
      const result = await uploadOne(
        fileRequest,
        file,
        onProgress,
        fileIndex,
      );
      results.push(result);
      completedBytes += file.size;
      request.onSession(null);
    }

    request.onProgress(100, totalBytes, totalBytes);

    return results;
  },

  async remove(request) {
    const endpoint = request.descriptor.endpoints.destroy;

    if (!endpoint) {
      return;
    }

    await requestJson(endpoint, { key: request.token }, request.signal);
  },
};

async function uploadOne(
  request: FormUploadRequest,
  file: File,
  onProgress: (loaded: number) => void,
  fileIndex: number,
): Promise<FormUploadedFile> {
  switch (request.descriptor.strategy) {
    case "chunked":
      return uploadChunked(request, file, onProgress, fileIndex);
    case "direct":
      return uploadDirect(request, file, onProgress, fileIndex);
    default:
      return uploadTemporary(request, file, onProgress);
  }
}

async function uploadTemporary(
  request: FormUploadRequest,
  file: File,
  onProgress: (loaded: number) => void,
): Promise<FormUploadedFile> {
  const store = request.descriptor.endpoints.store;

  if (!store) {
    throw new Error(`No temporary upload endpoint is configured for ${request.path}.`);
  }

  const body = new FormData();
  body.append("file", file, file.name);
  appendDescriptorData(body, request.descriptor);
  const response = await xhrRequest(store, body, request.signal, onProgress);

  return normalizeUploadedFile(response, file);
}

async function uploadChunked(
  request: FormUploadRequest,
  file: File,
  onProgress: (loaded: number) => void,
  fileIndex: number,
): Promise<FormUploadedFile> {
  const endpoints = request.descriptor.endpoints.chunked;

  if (!endpoints?.start || !endpoints.append || !endpoints.complete) {
    throw new Error(`Incomplete chunked upload endpoints for ${request.path}.`);
  }

  const resume =
    request.resume?.strategy === "chunked" ? request.resume : null;
  const started = resume?.started ??
    (resume ? { uploadId: resume.uploadId } : null) ??
    await requestJson(
      endpoints.start,
      uploadMetadata(file, request.descriptor),
      request.signal,
    );
  const uploadId = identifier(started);

  if (!uploadId) {
    throw new Error("The chunked upload start response did not include uploadId.");
  }

  const chunkSize = positiveNumber(
    started.chunkSizeBytes ??
      started.chunkSize ??
      started.chunk_size ??
      resume?.chunkSizeBytes ??
      request.descriptor.limits.chunkSizeBytes,
    5 * 1024 * 1024,
  );
  const totalChunks = Math.max(1, Math.ceil(file.size / chunkSize));
  let offset = Math.max(0, Number(resume?.offset ?? started.offset ?? 0));

  if (resume && endpoints.status) {
    const status = await requestJson(
      endpoints.status,
      { uploadId },
      request.signal,
    );
    offset = Math.max(0, Number(status.offset ?? offset));
  }

  request.onSession({
    strategy: "chunked",
    uploadId,
    fileIndex,
    offset,
    chunkSizeBytes: chunkSize,
    started,
  });
  onProgress(offset);

  try {
    while (offset < file.size) {
      const index = Math.floor(offset / chunkSize);
      const chunk = file.slice(offset, Math.min(file.size, offset + chunkSize));
      const body = new FormData();
      body.append("uploadId", uploadId);
      body.append("index", String(index));
      body.append("offset", String(offset));
      body.append("totalChunks", String(totalChunks));
      body.append("chunk", chunk, file.name);
      appendDescriptorData(body, request.descriptor);
      const appended = await xhrRequest(endpoints.append, body, request.signal, (loaded) =>
        onProgress(offset + loaded),
      );
      offset = Math.max(
        offset + chunk.size,
        Number(appended.offset ?? offset + chunk.size),
      );
      request.onSession({
        strategy: "chunked",
        uploadId,
        fileIndex,
        offset,
        chunkSizeBytes: chunkSize,
        started,
      });
    }

    const completed = await requestJson(
      endpoints.complete,
      { uploadId, rulesToken: request.descriptor.rulesToken },
      request.signal,
    );

    request.onSession(null);

    return normalizeUploadedFile(completed, file);
  } catch (error) {
    if (endpoints.abort && isCancelSignal(request.signal)) {
      void requestJson(endpoints.abort, { uploadId }, new AbortController().signal).catch(
        () => undefined,
      );
    }

    throw error;
  }
}

async function uploadDirect(
  request: FormUploadRequest,
  file: File,
  onProgress: (loaded: number) => void,
  fileIndex: number,
): Promise<FormUploadedFile> {
  const endpoints = request.descriptor.endpoints.direct;

  if (!endpoints?.start || !endpoints.complete) {
    throw new Error(`Incomplete direct upload endpoints for ${request.path}.`);
  }

  const resume = request.resume?.strategy === "direct" ? request.resume : null;
  const started = resume?.started ??
    (resume ? { uploadId: resume.uploadId } : null) ??
    await requestJson(
      endpoints.start,
      uploadMetadata(file, request.descriptor),
      request.signal,
    );
  const uploadId = identifier(started);

  if (!uploadId) {
    throw new Error("The direct upload start response did not include uploadId.");
  }

  const partSize = positiveNumber(
    started.partSizeBytes ??
      started.partSize ??
      started.part_size ??
      resume?.partSizeBytes ??
      request.descriptor.limits.partSizeBytes,
    Math.max(file.size, 1),
  );
  const single = started.mode === "single";
  const totalParts = single ? 1 : Math.max(1, Math.ceil(file.size / partSize));
  let parts = resume?.parts ? [...resume.parts] : [];

  if (resume && endpoints.status) {
    const status = await requestJson(
      endpoints.status,
      { uploadId },
      request.signal,
    );
    parts = normalizeUploadedParts(status.parts ?? parts);
  }

  let uploadedBytes = single
    ? 0
    : parts.reduce(
        (sum, part) => sum + (part.size ?? partSize),
        0,
      );
  request.onSession({
    strategy: "direct",
    uploadId,
    fileIndex,
    offset: uploadedBytes,
    partSizeBytes: partSize,
    parts,
    started,
  });
  onProgress(uploadedBytes);

  try {
    for (let index = 0; index < totalParts; index += 1) {
      const partNumber = index + 1;
      const offset = index * partSize;
      const blob = file.slice(offset, Math.min(file.size, offset + partSize));

      if (!single && parts.some((part) => part.partNumber === partNumber)) {
        continue;
      }

      const signed = single
        ? directObject(started, endpoints.object, uploadId)
        : await signedPart(
            endpoints.signPart,
            endpoints.object,
            uploadId,
            partNumber,
            request.signal,
          );
      const result = await xhrRawUpload(
        signed,
        blob,
        request.signal,
        (loaded) => onProgress(offset + loaded),
      );
      parts = [
        ...parts.filter((part) => part.partNumber !== partNumber),
        { partNumber, etag: result.etag, size: blob.size },
      ].sort((left, right) => left.partNumber - right.partNumber);
      uploadedBytes = Math.min(file.size, uploadedBytes + blob.size);
      request.onSession({
        strategy: "direct",
        uploadId,
        fileIndex,
        offset: uploadedBytes,
        partSizeBytes: partSize,
        parts,
        started,
      });
    }

    const completed = await requestJson(
      endpoints.complete,
      { uploadId, parts, rulesToken: request.descriptor.rulesToken },
      request.signal,
    );

    request.onSession(null);

    return normalizeUploadedFile(completed, file);
  } catch (error) {
    if (endpoints.abort && isCancelSignal(request.signal)) {
      void requestJson(endpoints.abort, { uploadId }, new AbortController().signal).catch(
        () => undefined,
      );
    }

    throw error;
  }
}

async function signedPart(
  signEndpoint: FormEndpoint | null | undefined,
  objectEndpoint: FormEndpoint | null | undefined,
  uploadId: string,
  partNumber: number,
  signal: AbortSignal,
): Promise<Record<string, unknown>> {
  if (signEndpoint) {
    return requestJson(signEndpoint, { uploadId, partNumber }, signal);
  }

  if (objectEndpoint) {
    return endpointUploadTarget(objectEndpoint, uploadId, partNumber);
  }

  throw new Error("No direct object or sign-part endpoint is configured.");
}

function directObject(
  started: Record<string, unknown>,
  objectEndpoint: FormEndpoint | null | undefined,
  uploadId: string,
): Record<string, unknown> {
  const url = started.uploadUrl ?? started.url ?? started.objectUrl;

  if (typeof url === "string") {
    return { ...started, url, method: started.method ?? "PUT" };
  }

  if (objectEndpoint) {
    return endpointUploadTarget(objectEndpoint, uploadId);
  }

  throw new Error("No direct object endpoint is configured.");
}

function endpointUploadTarget(
  endpoint: FormEndpoint,
  uploadId: string,
  partNumber?: number,
): Record<string, unknown> {
  const target = new URL(
    endpoint.url,
    typeof window === "undefined" ? "http://localhost" : window.location.origin,
  );
  target.searchParams.set("uploadId", uploadId);

  if (partNumber !== undefined) {
    target.searchParams.set("partNumber", String(partNumber));
  }

  return {
    url: absoluteOrRelative(endpoint.url, target),
    method: endpoint.method || "PUT",
    headers: {},
  };
}

async function requestJson(
  endpoint: FormEndpoint,
  data: Record<string, unknown>,
  signal: AbortSignal,
): Promise<Record<string, unknown>> {
  const method = endpoint.method.toUpperCase();
  const target = new URL(
    endpoint.url,
    typeof window === "undefined" ? "http://localhost" : window.location.origin,
  );
  const headers: Record<string, string> = {
    Accept: "application/json",
    "X-Requested-With": "XMLHttpRequest",
  };
  const csrf = csrfToken();

  if (csrf) {
    headers["X-CSRF-TOKEN"] = csrf;
  }

  const init: RequestInit = {
    method,
    headers,
    credentials: "same-origin",
    signal,
  };

  if (method === "GET") {
    Object.entries(data).forEach(([key, value]) => {
      if (value !== null && value !== undefined) {
        target.searchParams.set(key, String(value));
      }
    });
  } else {
    headers["Content-Type"] = "application/json";
    init.body = JSON.stringify(data);
  }

  const response = await fetch(absoluteOrRelative(endpoint.url, target), init);
  const payload = await responseJson(response);

  if (!response.ok) {
    throw new Error(
      typeof payload.message === "string"
        ? payload.message
        : `Upload request failed with status ${response.status}.`,
    );
  }

  return payload;
}

function xhrRequest(
  endpoint: FormEndpoint,
  body: FormData,
  signal: AbortSignal,
  onProgress: (loaded: number) => void,
): Promise<Record<string, unknown>> {
  return new Promise((resolve, reject) => {
    const xhr = new XMLHttpRequest();
    xhr.open(endpoint.method.toUpperCase(), endpoint.url);
    xhr.setRequestHeader("Accept", "application/json");
    xhr.setRequestHeader("X-Requested-With", "XMLHttpRequest");
    const csrf = csrfToken();

    if (csrf) {
      xhr.setRequestHeader("X-CSRF-TOKEN", csrf);
    }

    xhr.upload.addEventListener("progress", (event) => onProgress(event.loaded));
    xhr.addEventListener("load", () => {
      const payload = parseText(xhr.responseText);

      if (xhr.status >= 200 && xhr.status < 300) {
        resolve(payload);
      } else {
        reject(
          new Error(
            typeof payload.message === "string"
              ? payload.message
              : `Upload request failed with status ${xhr.status}.`,
          ),
        );
      }
    });
    xhr.addEventListener("error", () => reject(new Error("Upload request failed.")));
    xhr.addEventListener("abort", () => reject(abortError()));
    signal.addEventListener("abort", () => xhr.abort(), { once: true });
    xhr.send(body);
  });
}

function xhrRawUpload(
  signed: Record<string, unknown>,
  body: Blob,
  signal: AbortSignal,
  onProgress: (loaded: number) => void,
): Promise<{ etag: string | null }> {
  const url = signed.url;

  if (typeof url !== "string") {
    throw new Error("The signed upload response did not include a URL.");
  }

  return new Promise((resolve, reject) => {
    const xhr = new XMLHttpRequest();
    xhr.open(typeof signed.method === "string" ? signed.method : "PUT", url);

    if (typeof signed.headers === "object" && signed.headers !== null) {
      Object.entries(signed.headers).forEach(([key, value]) => {
        if (typeof value === "string") {
          xhr.setRequestHeader(key, value);
        }
      });
    }

    xhr.upload.addEventListener("progress", (event) => onProgress(event.loaded));
    xhr.addEventListener("load", () => {
      if (xhr.status >= 200 && xhr.status < 300) {
        resolve({ etag: xhr.getResponseHeader("ETag") });
      } else {
        reject(new Error(`Direct upload failed with status ${xhr.status}.`));
      }
    });
    xhr.addEventListener("error", () => reject(new Error("Direct upload failed.")));
    xhr.addEventListener("abort", () => reject(abortError()));
    signal.addEventListener("abort", () => xhr.abort(), { once: true });
    xhr.send(body);
  });
}

function endpoint(
  value: unknown,
  fallbackMethod: string,
): FormEndpoint | null {
  if (typeof value === "string" && value !== "") {
    return { method: fallbackMethod, url: value };
  }

  if (
    typeof value === "object" &&
    value !== null &&
    "url" in value &&
    typeof value.url === "string"
  ) {
    return {
      ...value,
      method:
        "method" in value && typeof value.method === "string"
          ? value.method
          : fallbackMethod,
      url: value.url,
    };
  }

  return null;
}

function normalizeEndpointMap(
  values: Record<string, FormEndpoint | string | null> | null | undefined,
  fallbackValues: Record<string, unknown> | null | undefined,
  defaults: Record<string, string>,
  aliases: Record<string, unknown> = {},
): Record<string, FormEndpoint | null> | null {
  if (!values && !fallbackValues && Object.keys(aliases).length === 0) {
    return null;
  }

  return Object.fromEntries(
    Object.entries(defaults).map(([key, method]) => [
      key,
      endpoint(values?.[key] ?? fallbackValues?.[key] ?? aliases[key], method),
    ]),
  );
}

function uploadMetadata(
  file: File,
  descriptor: FormUploadDescriptor,
): Record<string, unknown> {
  return {
    name: file.name,
    size: file.size,
    mimeType: file.type,
    mime_type: file.type,
    disk: descriptor.disk,
    rulesToken: descriptor.rulesToken,
  };
}

function appendDescriptorData(
  body: FormData,
  descriptor: FormUploadDescriptor,
): void {
  if (descriptor.rulesToken) {
    body.append("rulesToken", descriptor.rulesToken);
  }

  if (descriptor.disk) {
    body.append("disk", descriptor.disk);
  }
}

function normalizeUploadedFile(
  payload: Record<string, unknown>,
  fallback: File,
): FormUploadedFile {
  const nested = [payload.file, payload.upload, payload.data].find(
    (value) => typeof value === "object" && value !== null && !Array.isArray(value),
  );
  const source = (nested as Record<string, unknown> | undefined) ?? payload;
  const key = source.key ?? source.token;

  if (typeof key !== "string" || key === "") {
    throw new Error("The upload response did not include an encrypted file key.");
  }

  const mime = source.mimeType ?? source.mime_type ?? fallback.type;

  return {
    ...source,
    key,
    name: typeof source.name === "string" ? source.name : fallback.name,
    mimeType: typeof mime === "string" && mime !== "" ? mime : null,
    mime_type: typeof mime === "string" && mime !== "" ? mime : null,
    size: Number(source.size ?? fallback.size),
  };
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

function identifier(payload: Record<string, unknown>): string | null {
  const value = payload.uploadId ?? payload.upload_id ?? payload.id;

  return typeof value === "string" || typeof value === "number"
    ? String(value)
    : null;
}

function positiveNumber(value: unknown, fallback: number): number {
  const number = Number(value);

  return Number.isFinite(number) && number > 0 ? number : fallback;
}

function normalizeUploadedParts(
  value: unknown,
): Array<{ partNumber: number; etag: string | null; size?: number }> {
  if (!Array.isArray(value)) {
    return [];
  }

  return value.flatMap((candidate) => {
    if (typeof candidate !== "object" || candidate === null) {
      return [];
    }

    const record = candidate as Record<string, unknown>;
    const partNumber = Number(record.partNumber ?? record.part_number);
    const size = Number(record.size);

    if (!Number.isInteger(partNumber) || partNumber < 1) {
      return [];
    }

    return [{
      partNumber,
      etag: typeof record.etag === "string" ? record.etag : null,
      ...(Number.isFinite(size) && size >= 0 ? { size } : {}),
    }];
  });
}

async function responseJson(response: Response): Promise<Record<string, unknown>> {
  return parseText(await response.text());
}

function parseText(value: string): Record<string, unknown> {
  try {
    const parsed: unknown = value === "" ? {} : JSON.parse(value);

    return typeof parsed === "object" && parsed !== null
      ? (parsed as Record<string, unknown>)
      : {};
  } catch {
    return {};
  }
}

function absoluteOrRelative(original: string, target: URL): string {
  return /^https?:\/\//i.test(original)
    ? target.toString()
    : `${target.pathname}${target.search}${target.hash}`;
}

function csrfToken(): string | null {
  if (typeof document === "undefined") {
    return null;
  }

  return document
    .querySelector<HTMLMetaElement>('meta[name="csrf-token"]')
    ?.getAttribute("content") ?? null;
}

function abortError(): Error {
  const error = new Error("Upload aborted.");
  error.name = "AbortError";

  return error;
}

function isCancelSignal(signal: AbortSignal): boolean {
  return (
    signal.aborted &&
    typeof signal.reason === "object" &&
    signal.reason !== null &&
    "name" in signal.reason &&
    signal.reason.name === "UploadCancelled"
  );
}
