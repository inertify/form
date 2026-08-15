import { nextTick, shallowRef } from "vue";
import { describe, expect, it, vi } from "vitest";
import {
  defaultComboboxTransport,
  normalizeComboboxPage,
  useFormCombobox,
} from "../resources/js/useFormCombobox";
import { useFormCollections } from "../resources/js/useFormCollections";
import { useFormUploads } from "../resources/js/useFormUploads";
import { useFormWizard } from "../resources/js/useFormWizard";
import { useForm } from "../resources/js/useForm";
import type {
  FormUploadRequest,
  FormUploadedFile,
} from "../resources/js/types";
import { makeField, makeFieldset, makeResource } from "./fixtures";

describe("feature composables", () => {
  it("navigates visible wizard steps and validates precognitive fields", async () => {
    const validate = vi.fn(async () => true);
    const resource = makeResource(
      { name: "Ada", secret: "", email: "a@example.com" },
      [],
      {
        fieldsets: [
          makeFieldset([makeField("name", "Text", { precognitive: true })], {
            id: null,
            legend: "Identity",
          }),
          makeFieldset([makeField("secret")], {
            id: "hidden",
            visibility: false,
          }),
          makeFieldset([makeField("email")], { id: "contact" }),
        ],
        wizard: {
          enabled: true,
          allowSkip: false,
          validateOnStep: true,
          nextLabel: "Continue",
          prevLabel: "Back",
          submitLabel: "Save",
        },
      },
    );
    const form = useForm(resource, { validationTransport: validate });
    const wizard = useFormWizard(form);

    expect(wizard.steps.value.map((step) => step.id)).toEqual([
      "fieldset-1",
      "contact",
    ]);
    expect(wizard.labels.value).toEqual({
      next: "Continue",
      previous: "Back",
      submit: "Save",
    });
    await expect(wizard.next()).resolves.toBe(true);
    expect(validate).toHaveBeenCalledOnce();
    expect(wizard.current.value?.id).toBe("contact");
    expect(wizard.completed.has("fieldset-1")).toBe(true);
  });

  it("manages collections without leaking stable keys into submitted data", () => {
    const form = useForm(
      makeResource({ items: [{ name: "a" }] }, [
        makeField("items", "Repeater"),
      ]),
    );
    const collection = useFormCollections(form).forField("items");
    const firstKey = collection.keys.value[0];
    collection.append({ name: "b" });
    collection.duplicate(0);
    collection.move(2, 0);
    expect(collection.items.value).toHaveLength(3);
    expect(collection.keys.value).toHaveLength(3);
    expect(collection.keys.value).toContain(firstKey);
    expect(form.data.value.items).toEqual([
      { name: "b" },
      { name: "a" },
      { name: "a" },
    ]);
    expect(JSON.stringify(form.data.value)).not.toContain("form-item-");
  });

  it("creates repeater defaults and canonical block-set data headlessly", () => {
    const form = useForm(
      makeResource(
        { generated: [], configured: [], content: [] },
        [
          makeField("generated", "Repeater", {
            schema: [makeField("title"), makeField("details.summary")],
          }),
          makeField("configured", "Repeater", {
            defaultItem: { title: "Default", enabled: true },
          }),
          makeField("content", "Blocks", {
            maxItems: 2,
            sets: [
              {
                type: "hero",
                schema: [makeField("title")],
                defaultData: { title: "Hero" },
                maxItems: 1,
              },
            ],
          }),
        ],
      ),
    );
    const collections = useFormCollections(form);
    collections.forField("generated").append();
    collections.forField("configured").append();
    expect(form.getValue("generated")).toEqual([
      { title: null, details: { summary: null } },
    ]);
    expect(form.getValue("configured")).toEqual([
      { title: "Default", enabled: true },
    ]);

    const blocks = collections.forField("content");
    expect(blocks.appendBlock("hero")).toBe(true);
    expect(blocks.appendBlock("hero")).toBe(false);
    expect(form.getValue("content")).toEqual([
      { type: "hero", data: { title: "Hero" } },
    ]);
  });

  it("uploads encrypted tokens through an injected transport and reorders/removes them", async () => {
    const remove = vi.fn(async () => undefined);
    const upload = vi.fn(async ({ files }) =>
      files.map((file: File, index: number) => ({
        key: `token-${index}`,
        name: file.name,
        mimeType: file.type,
        size: file.size,
      })),
    );
    const form = useForm(
      makeResource({ files: [] as string[] }, [
        makeField("files", "File", {
          multiple: true,
          temporaryUploadUrl: "/uploads",
        }),
      ]),
      { uploadTransport: { upload, remove } },
    );
    const uploads = useFormUploads(form);
    await uploads.upload("files", [
      new File(["a"], "a.txt", { type: "text/plain" }),
      new File(["b"], "b.txt", { type: "text/plain" }),
    ]);
    expect(form.getValue("files")).toEqual(["token-0", "token-1"]);
    uploads.reorder("files", 0, 1);
    expect(form.getValue("files")).toEqual(["token-1", "token-0"]);
    await uploads.remove("files", "token-1");
    expect(remove).toHaveBeenCalledOnce();
    expect(form.getValue("files")).toEqual(["token-0"]);
  });

  it("keeps bound existing-file metadata while values remain encrypted keys", async () => {
    const first = {
      key: "existing-first",
      name: "first.pdf",
      mimeType: "application/pdf",
      size: 100,
      url: "/files/first.pdf",
    };
    const second = {
      key: "existing-second",
      name: "second.pdf",
      mimeType: "application/pdf",
      size: 200,
      url: "/files/second.pdf",
    };
    const remove = vi.fn(async () => undefined);
    const upload = vi.fn(async () => [{
      key: "uploaded-third",
      name: "third.pdf",
      mimeType: "application/pdf",
      size: 300,
      url: "/files/third.pdf",
    }]);
    const form = useForm(
      makeResource({ files: [first, second] }, [
        makeField("files", "File", {
          multiple: true,
          temporaryUploadUrl: "/uploads",
        }),
      ]),
      { uploadTransport: { upload, remove } },
    );
    const uploads = useFormUploads(form);

    expect(form.getValue("files")).toEqual([
      "existing-first",
      "existing-second",
    ]);
    expect(uploads.state("files").files.map((file) => file.name)).toEqual([
      "first.pdf",
      "second.pdf",
    ]);

    await uploads.upload(
      "files",
      new File(["third"], "third.pdf", { type: "application/pdf" }),
    );
    expect(form.getValue("files")).toEqual([
      "existing-first",
      "existing-second",
      "uploaded-third",
    ]);
    expect(uploads.state("files").files.map((file) => file.name)).toEqual([
      "first.pdf",
      "second.pdf",
      "third.pdf",
    ]);

    form.defaults();
    uploads.reorder("files", 2, 0);
    expect(form.getValue("files")).toEqual([
      "uploaded-third",
      "existing-first",
      "existing-second",
    ]);
    expect(uploads.state("files").files.map((file) => file.name)).toEqual([
      "third.pdf",
      "first.pdf",
      "second.pdf",
    ]);

    await uploads.remove("files", "existing-first");
    expect(remove).toHaveBeenCalledWith(
      expect.objectContaining({
        token: "existing-first",
        file: expect.objectContaining({ name: "first.pdf" }),
      }),
    );
    expect(form.getValue("files")).toEqual([
      "uploaded-third",
      "existing-second",
    ]);
    expect(uploads.state("files").files.map((file) => file.name)).toEqual([
      "third.pdf",
      "second.pdf",
    ]);

    form.reset();
    expect(form.getValue("files")).toEqual([
      "existing-first",
      "existing-second",
      "uploaded-third",
    ]);
    expect(uploads.state("files").files.map((file) => file.name)).toEqual([
      "first.pdf",
      "second.pdf",
      "third.pdf",
    ]);
  });

  it("prefers explicit existing files and supports a single-file reset", async () => {
    const bound = {
      key: "bound-key",
      name: "bound.txt",
      mimeType: "text/plain",
      size: 10,
    };
    const explicit = {
      key: "explicit-key",
      name: "explicit.txt",
      mimeType: "text/plain",
      size: 20,
      preview: "/files/explicit.txt",
    };
    const form = useForm(
      makeResource({ file: bound }, [
        makeField("file", "File", {
          existingFiles: [explicit],
          temporaryUploadUrl: "/uploads",
        }),
      ]),
    );
    const uploads = useFormUploads(form);

    expect(form.getValue("file")).toBe("explicit-key");
    expect(uploads.state("file").files).toEqual([
      expect.objectContaining({
        key: "explicit-key",
        name: "explicit.txt",
        preview: "/files/explicit.txt",
      }),
    ]);

    await expect(uploads.remove("file")).resolves.toBe(true);
    expect(form.getValue("file")).toBeNull();
    expect(uploads.state("file").files).toEqual([]);
    form.reset();
    expect(form.getValue("file")).toBe("explicit-key");
    expect(uploads.state("file").files[0]?.name).toBe("explicit.txt");
  });

  it("synchronizes replacement existing-file resources and their metadata", async () => {
    const field = makeField("file", "File", {
      temporaryUploadUrl: "/uploads",
    });
    const resource = shallowRef(
      makeResource(
        {
          file: {
            key: "before-key",
            name: "before.txt",
            mimeType: "text/plain",
            size: 10,
          },
        },
        [field],
      ),
    );
    const form = useForm(resource);
    const uploads = useFormUploads(form);
    expect(uploads.state("file").files[0]?.name).toBe("before.txt");

    resource.value = makeResource(
      {
        file: {
          key: "after-key",
          name: "after.txt",
          mimeType: "text/plain",
          size: 20,
        },
      },
      [field],
    );
    await nextTick();

    expect(form.getValue("file")).toBe("after-key");
    expect(form.isDirty.value).toBe(false);
    expect(uploads.state("file").files[0]).toEqual(
      expect.objectContaining({ key: "after-key", name: "after.txt" }),
    );
  });

  it("retains pending files and resumable sessions for retry", async () => {
    let attempt = 0;
    const upload = vi.fn(async (request) => {
      attempt += 1;

      if (attempt === 1) {
        request.onSession({
          strategy: "chunked",
          uploadId: "pending-1",
          fileIndex: 0,
          offset: 5,
          chunkSizeBytes: 5,
          started: { uploadId: "pending-1", chunkSize: 5 },
        });
        throw new Error("Connection lost.");
      }

      expect(request.resume).toMatchObject({
        uploadId: "pending-1",
        offset: 5,
      });

      return [{
        key: "resumed-token",
        name: request.files[0]?.name ?? "file.txt",
        mimeType: "text/plain",
        size: request.files[0]?.size ?? 0,
      }];
    });
    const form = useForm(
      makeResource({ file: null as string | null }, [
        makeField("file", "File", { chunked: true, chunkSize: 5 }),
      ]),
      { uploadTransport: { upload } },
    );
    const uploads = useFormUploads(form);
    await expect(
      uploads.upload("file", new File(["ten bytes!"], "file.txt")),
    ).rejects.toThrow("Connection lost");
    expect(uploads.state("file").status).toBe("error");
    expect(uploads.state("file").pendingFiles).toHaveLength(1);
    await expect(uploads.retry("file")).resolves.toHaveLength(1);
    expect(form.getValue("file")).toBe("resumed-token");
    expect(uploads.state("file").pendingFiles).toEqual([]);
  });

  it("pauses and resumes an in-flight upload without losing its File", async () => {
    let attempt = 0;
    const upload = vi.fn((request: FormUploadRequest): Promise<FormUploadedFile[]> => {
      attempt += 1;

      if (attempt > 1) {
        expect(request.resume?.uploadId).toBe("paused-1");

        return Promise.resolve([{
          key: "paused-token",
          name: "paused.txt",
          mimeType: "text/plain",
          size: 6,
        }]);
      }

      request.onSession({
        strategy: "chunked",
        uploadId: "paused-1",
        fileIndex: 0,
        offset: 3,
      });

      return new Promise<FormUploadedFile[]>((_, reject) => {
        request.signal.addEventListener("abort", () => {
          const error = new Error("aborted");
          error.name = "AbortError";
          reject(error);
        });
      });
    });
    const form = useForm(
      makeResource({ file: null as string | null }, [
        makeField("file", "File", { chunked: true, chunkSize: 3 }),
      ]),
      { uploadTransport: { upload } },
    );
    const uploads = useFormUploads(form);
    const pending = uploads.upload(
      "file",
      new File(["resume"], "paused.txt", { type: "text/plain" }),
    );
    await Promise.resolve();
    uploads.pause("file");
    await expect(pending).resolves.toEqual([]);
    expect(uploads.state("file").status).toBe("paused");
    expect(uploads.state("file").pendingFiles[0]?.name).toBe("paused.txt");
    await uploads.resume("file");
    expect(form.getValue("file")).toBe("paused-token");
  });

  it("normalizes paginator, cursor, meta, and bare combobox responses", () => {
    expect(
      normalizeComboboxPage(
        { data: [{ id: 1, name: "Ada" }], current_page: 1, last_page: 2 },
        { valueKey: "id", labelKey: "name" },
      ),
    ).toMatchObject({
      options: [{ id: 1, name: "Ada", value: 1, label: "Ada" }],
      hasMore: true,
    });
    expect(
      normalizeComboboxPage(
        {
          data: [],
          next_cursor: "cursor",
          next_page_url: "/users?cursor=cursor",
        },
        {},
      ).nextCursor,
    ).toBe("cursor");
    expect(
      normalizeComboboxPage(
        { data: [], next_page_url: "/users?page=2" },
        {},
      ).nextPageUrl,
    ).toBe("/users?page=2");
    expect(normalizeComboboxPage(["one"], {}).options[0]).toEqual({
      value: "one",
      label: "one",
    });
  });

  it("follows remote paginator and cursor continuations", async () => {
    const calls: Array<{ source: { url?: string; params?: Record<string, unknown> } }> = [];
    const transport = vi.fn(async (request) => {
      calls.push(request);

      if (calls.length === 1) {
        return {
          data: [{ value: 1, label: "First" }],
          next_cursor: "cursor-2",
          next_page_url: "/users?cursor=cursor-2",
        };
      }

      if (calls.length === 2) {
        return {
          data: [{ value: 2, label: "Second" }],
          next_cursor: "cursor-3",
        };
      }

      return { data: [{ value: 3, label: "Third" }] };
    });
    const form = useForm(
      makeResource({ user_id: null }, [
        makeField("user_id", "Combobox", { source: "/users" }),
      ]),
    );
    const combo = useFormCombobox("user_id", form, { transport });

    await combo.load();
    await combo.loadMore();
    await combo.loadMore();

    expect(calls[1]?.source.url).toBe("/users?cursor=cursor-2");
    expect(calls[2]?.source).toMatchObject({
      url: "/users",
      params: { cursor: "cursor-3" },
    });
    expect(combo.options.value.map((option) => option.value)).toEqual([
      1,
      2,
      3,
    ]);
  });

  it("loads, hydrates, and creates remote combobox options", async () => {
    const transport = vi.fn(async ({ selected, create, source }) => {
      if (create) {
        expect(source).toMatchObject({
          createUrl: "/users/create",
          createMethod: "PUT",
          primaryKey: "name",
        });

        return { item: { id: 3, name: "New" } };
      }
      if (selected.length) {
        expect(source.url).toBe("/users/selected");

        return [{ id: 2, name: "Selected" }];
      }
      return { data: [{ id: 1, name: "Ada" }], meta: { has_more: false } };
    });
    const form = useForm(
      makeResource({ user_id: 2 }, [
        makeField("user_id", "Combobox", {
          source: "/users",
          selectedSource: "/users/selected",
          createRecordUsing: {
            url: "/users/create",
            method: "PUT",
            param: "name",
          },
          valueKey: "id",
          labelKey: "name",
        }),
      ]),
    );
    const combo = useFormCombobox("user_id", form, { transport });
    await combo.load("Ad");
    expect(combo.options.value[0]?.label).toBe("Ada");
    await combo.hydrateSelected();
    expect(combo.selectedOptions.value[0]?.value).toBe(2);
    await expect(combo.create("New")).resolves.toMatchObject({ value: 3 });
  });

  it("reloads and merges query-backed Inertia combobox pages", async () => {
    const optionsKey = "filters.owner_id";
    const field = makeField("owner_id", "Combobox", {
      optionsMode: "inertia",
      optionsKey,
      valueKey: "id",
      labelKey: "name",
      selected: [{ id: 9, name: "Off-page owner" }],
    });
    const resource = makeResource({ owner_id: null }, [field], {
      meta: {
        options: {
          [optionsKey]: {
            data: [{ id: 1, name: "Ada" }],
            current_page: 1,
            last_page: 2,
          },
        },
      },
    });
    const form = useForm(resource);
    const combo = useFormCombobox("owner_id", form);
    expect(combo.options.value.map((option) => option.label)).toEqual(["Ada"]);
    form.setValue("owner_id", 9);
    await expect(combo.hydrateSelected()).resolves.toMatchObject([
      { value: 9, label: "Off-page owner" },
    ]);

    const globals = globalThis as typeof globalThis & {
      __inertifyReloads: Array<Record<string, unknown>>;
      __inertifyReloadResource: unknown;
    };
    globals.__inertifyReloadResource = {
      meta: {
        options: {
          [optionsKey]: {
            data: [{ id: 2, name: "Grace" }],
            current_page: 1,
            last_page: 2,
          },
        },
      },
    };
    await combo.load("Gra");
    expect(combo.options.value.map((option) => option.label)).toEqual(["Grace"]);
    expect(globals.__inertifyReloads[0]).toMatchObject({
      only: ["form"],
      data: {
        _inertify_form: {
          prop: "form",
          field: optionsKey,
          search: "Gra",
          page: 1,
        },
      },
    });

    globals.__inertifyReloadResource = {
      meta: {
        options: {
          [optionsKey]: {
            data: [
              { id: 2, name: "Grace" },
              { id: 3, name: "Linus" },
            ],
            current_page: 2,
            last_page: 2,
          },
        },
      },
    };
    await combo.loadMore();
    expect(combo.options.value.map((option) => option.label)).toEqual([
      "Grace",
      "Linus",
    ]);
  });

  it("ignores stale Inertia option reload responses", async () => {
    const optionsKey = "owner_id";
    const form = useForm(
      makeResource(
        { owner_id: null },
        [
          makeField("owner_id", "Combobox", {
            optionsMode: "inertia",
            optionsKey,
            valueKey: "id",
            labelKey: "name",
          }),
        ],
        { meta: { options: { [optionsKey]: { data: [] } } } },
      ),
    );
    const callbacks: Array<Record<string, unknown>> = [];
    const globals = globalThis as typeof globalThis & {
      __inertifyReloadHandler: ((options: Record<string, unknown>) => void) | null;
    };
    globals.__inertifyReloadHandler = (options) => callbacks.push(options);
    const combo = useFormCombobox("owner_id", form);
    const first = combo.load("a");
    const second = combo.load("ab");
    const respond = (index: number, id: number, name: string) => {
      const options = callbacks[index];
      const page = {
        props: {
          form: {
            meta: {
              options: {
                [optionsKey]: { data: [{ id, name }], current_page: 1 },
              },
            },
          },
        },
      };
      (options?.onSuccess as ((page: unknown) => void) | undefined)?.(page);
      (options?.onFinish as (() => void) | undefined)?.();
    };
    respond(1, 2, "Newest");
    respond(0, 1, "Stale");
    await Promise.all([first, second]);
    expect(combo.options.value.map((option) => option.label)).toEqual(["Newest"]);
    combo.cancel();
  });

  it("sends quick-create CSRF and canonical search paging parameters", async () => {
    const meta = document.createElement("meta");
    meta.name = "csrf-token";
    meta.content = "csrf-value";
    document.head.append(meta);
    const fetchMock = vi.fn(
      async (
        _input: RequestInfo | URL,
        _init?: RequestInit,
      ): Promise<Response> =>
        new Response(JSON.stringify({ item: { id: 1, name: "New" } }), {
          status: 200,
          headers: { "Content-Type": "application/json" },
        }),
    );
    vi.stubGlobal("fetch", fetchMock);
    const field = makeField("user_id", "Combobox");
    await defaultComboboxTransport({
      field,
      source: {
        url: "/users",
        createUrl: "/users",
        createMethod: "POST",
        primaryKey: "name",
      },
      search: "New",
      page: 1,
      selected: [],
      create: true,
      signal: new AbortController().signal,
    });
    const createInit = fetchMock.mock.calls[0]?.[1] as RequestInit;
    expect(createInit.credentials).toBe("same-origin");
    expect(createInit.headers).toMatchObject({
      "X-CSRF-TOKEN": "csrf-value",
    });
    expect(JSON.parse(String(createInit.body))).toMatchObject({
      name: "New",
      q: "New",
    });

    fetchMock.mockClear();
    await defaultComboboxTransport({
      field,
      source: { url: "/users", preload: 5 },
      search: "",
      page: 1,
      selected: [],
      create: false,
      signal: new AbortController().signal,
    });
    expect(String(fetchMock.mock.calls[0]?.[0])).toContain("q=");
    expect(String(fetchMock.mock.calls[0]?.[0])).toContain("search=");
    expect(String(fetchMock.mock.calls[0]?.[0])).toContain("per_page=5");
    meta.remove();
    vi.unstubAllGlobals();
  });
});
