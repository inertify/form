import { afterEach, describe, expect, it, vi } from "vitest";
import {
  defaultUploadTransport,
  normalizeUploadDescriptor,
} from "../resources/js/transports/uploads";
import type {
  FormEndpoint,
  FormUploadDescriptor,
  FormUploadRequest,
} from "../resources/js/types";
import { makeField } from "./fixtures";

interface XhrCall {
  method: string;
  url: string;
  body: Document | XMLHttpRequestBodyInit | null;
}

const xhrCalls: XhrCall[] = [];
let xhrResponse: (call: XhrCall) => {
  status: number;
  body: string;
  etag: string | null;
} = (_call: XhrCall) => ({
  status: 200,
  body: "",
  etag: '"etag"',
});

class FakeXMLHttpRequest {
  status = 0;
  responseText = "";
  private method = "";
  private url = "";
  private readonly listeners = new Map<string, Array<() => void>>();
  private progress: ((event: { loaded: number }) => void) | null = null;
  private etag: string | null = null;

  upload = {
    addEventListener: (
      event: string,
      callback: (event: { loaded: number }) => void,
    ) => {
      if (event === "progress") {
        this.progress = callback;
      }
    },
  };

  open(method: string, url: string): void {
    this.method = method;
    this.url = url;
  }

  setRequestHeader(): void {}

  addEventListener(event: string, callback: () => void): void {
    const callbacks = this.listeners.get(event) ?? [];
    callbacks.push(callback);
    this.listeners.set(event, callbacks);
  }

  getResponseHeader(name: string): string | null {
    return name.toLowerCase() === "etag" ? this.etag : null;
  }

  send(body: Document | XMLHttpRequestBodyInit | null): void {
    const call = { method: this.method, url: this.url, body };
    xhrCalls.push(call);
    const response = xhrResponse(call);
    this.status = response.status;
    this.responseText = response.body;
    this.etag = response.etag;
    const size = body instanceof Blob ? body.size : 5;
    this.progress?.({ loaded: size });
    this.listeners.get("load")?.forEach((callback) => callback());
  }

  abort(): void {
    this.listeners.get("abort")?.forEach((callback) => callback());
  }
}

afterEach(() => {
  xhrCalls.length = 0;
  xhrResponse = () => ({ status: 200, body: "", etag: '"etag"' });
  vi.unstubAllGlobals();
});

describe("default upload transport", () => {
  it("resumes chunked uploads from the server-reported offset", async () => {
    vi.stubGlobal("XMLHttpRequest", FakeXMLHttpRequest);
    xhrResponse = (call) => {
      expect(call.body).toBeInstanceOf(FormData);
      expect((call.body as FormData).get("offset")).toBe("5");

      return {
        status: 200,
        body: JSON.stringify({ uploadId: "chunk-1", offset: 10, size: 10 }),
        etag: null,
      };
    };
    const fetchMock = vi.fn(
      async (input: RequestInfo | URL): Promise<Response> => {
        const url = String(input);

        if (url.startsWith("/chunk/status")) {
          expect(url).toContain("uploadId=chunk-1");

          return jsonResponse({ uploadId: "chunk-1", offset: 5, size: 10 });
        }

        if (url === "/chunk/complete") {
          return jsonResponse({
            key: "chunk-token",
            name: "ten.txt",
            mimeType: "text/plain",
            size: 10,
          });
        }

        throw new Error(`Unexpected fetch: ${url}`);
      },
    );
    vi.stubGlobal("fetch", fetchMock);
    const descriptor = uploadDescriptor("chunked", {
      chunked: {
        start: endpoint("POST", "/chunk/start"),
        status: endpoint("GET", "/chunk/status"),
        append: endpoint("POST", "/chunk/append"),
        complete: endpoint("POST", "/chunk/complete"),
        abort: endpoint("DELETE", "/chunk/abort"),
      },
    });
    const request = uploadRequest(descriptor, {
      strategy: "chunked",
      uploadId: "chunk-1",
      fileIndex: 0,
      offset: 5,
      chunkSizeBytes: 5,
      started: { uploadId: "chunk-1", chunkSize: 5 },
    });

    await expect(defaultUploadTransport.upload(request)).resolves.toMatchObject([
      { key: "chunk-token" },
    ]);
    expect(xhrCalls).toHaveLength(1);
    expect(xhrCalls[0]).toMatchObject({
      method: "POST",
      url: "/chunk/append",
    });
  });

  it("asks direct status and uploads only missing multipart parts", async () => {
    vi.stubGlobal("XMLHttpRequest", FakeXMLHttpRequest);
    const fetchMock = vi.fn(
      async (
        input: RequestInfo | URL,
        init?: RequestInit,
      ): Promise<Response> => {
        const url = String(input);

        if (url.startsWith("/direct/status")) {
          return jsonResponse({
            uploadId: "direct-1",
            parts: [{ partNumber: 1, size: 5, etag: '"first"' }],
          });
        }

        if (url === "/direct/sign") {
          expect(JSON.parse(String(init?.body))).toMatchObject({ partNumber: 2 });

          return jsonResponse({ url: "/signed/2", headers: {}, partNumber: 2 });
        }

        if (url === "/direct/complete") {
          return jsonResponse({
            key: "direct-token",
            name: "ten.txt",
            mimeType: "text/plain",
            size: 10,
          });
        }

        throw new Error(`Unexpected fetch: ${url}`);
      },
    );
    vi.stubGlobal("fetch", fetchMock);
    const descriptor = uploadDescriptor("direct", {
      direct: {
        start: endpoint("POST", "/direct/start"),
        object: endpoint("PUT", "/direct/object"),
        signPart: endpoint("POST", "/direct/sign"),
        status: endpoint("GET", "/direct/status"),
        complete: endpoint("POST", "/direct/complete"),
        abort: endpoint("DELETE", "/direct/abort"),
      },
    });
    const request = uploadRequest(descriptor, {
      strategy: "direct",
      uploadId: "direct-1",
      fileIndex: 0,
      offset: 5,
      partSizeBytes: 5,
      parts: [{ partNumber: 1, size: 5, etag: '"first"' }],
      started: {
        uploadId: "direct-1",
        mode: "multipart",
        partSize: 5,
      },
    });

    await expect(defaultUploadTransport.upload(request)).resolves.toMatchObject([
      { key: "direct-token" },
    ]);
    expect(xhrCalls).toHaveLength(1);
    expect(xhrCalls[0]).toMatchObject({ method: "PUT", url: "/signed/2" });
  });

  it("PUTs a single direct object without requesting a multipart signature", async () => {
    vi.stubGlobal("XMLHttpRequest", FakeXMLHttpRequest);
    const fetchMock = vi.fn(
      async (input: RequestInfo | URL): Promise<Response> => {
        const url = String(input);

        if (url === "/direct/start") {
          return jsonResponse({
            uploadId: "single-1",
            mode: "single",
            uploadUrl: "/direct/object?uploadId=single-1",
            headers: {},
            partSize: 10,
          }, 201);
        }

        if (url === "/direct/complete") {
          return jsonResponse({
            key: "single-token",
            name: "ten.txt",
            mimeType: "text/plain",
            size: 10,
          });
        }

        throw new Error(`Unexpected fetch: ${url}`);
      },
    );
    vi.stubGlobal("fetch", fetchMock);
    const descriptor = uploadDescriptor("direct", {
      direct: {
        start: endpoint("POST", "/direct/start"),
        object: endpoint("PUT", "/direct/object"),
        signPart: endpoint("POST", "/direct/sign"),
        complete: endpoint("POST", "/direct/complete"),
      },
    });

    await defaultUploadTransport.upload(uploadRequest(descriptor));
    expect(xhrCalls).toHaveLength(1);
    expect(xhrCalls[0]).toMatchObject({
      method: "PUT",
      url: "/direct/object?uploadId=single-1",
    });
    expect(fetchMock).not.toHaveBeenCalledWith(
      "/direct/sign",
      expect.anything(),
    );
  });

  it("prefers flat upload props while supplementing the nested descriptor", () => {
    const normalized = normalizeUploadDescriptor(
      makeField("file", "File", {
        directToStorage: true,
        directUploadUrls: { start: "/flat/start", complete: "/flat/complete" },
        upload: uploadDescriptor("direct", {
          direct: { object: endpoint("PUT", "/nested/object") },
        }),
      }),
    );
    expect(normalized).toMatchObject({
      strategy: "direct",
      endpoints: {
        direct: {
          start: { url: "/flat/start" },
          object: { method: "PUT", url: "/nested/object" },
          complete: { url: "/flat/complete" },
        },
      },
    });
  });
});

function endpoint(method: string, url: string): FormEndpoint {
  return { method, url };
}

function uploadDescriptor(
  strategy: "temporary" | "form" | "chunked" | "direct",
  endpoints: FormUploadDescriptor["endpoints"] = {},
): FormUploadDescriptor {
  return {
    strategy,
    endpoints,
    limits: { chunkSizeBytes: 5, partSizeBytes: 5 },
    disk: null,
    rulesToken: null,
    requiresRulesToken: false,
  };
}

function uploadRequest(
  descriptor: FormUploadDescriptor,
  resume: FormUploadRequest["resume"] = null,
): FormUploadRequest {
  return {
    path: "file",
    field: makeField("file", "File"),
    files: [new File(["0123456789"], "ten.txt", { type: "text/plain" })],
    descriptor,
    signal: new AbortController().signal,
    resume,
    onProgress: vi.fn(),
    onSession: vi.fn(),
  };
}

function jsonResponse(payload: unknown, status = 200): Response {
  return new Response(JSON.stringify(payload), {
    status,
    headers: { "Content-Type": "application/json" },
  });
}
