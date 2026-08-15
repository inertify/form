import { nextTick, shallowRef } from "vue";
import { describe, expect, it, vi } from "vitest";
import { useForm } from "../resources/js/useForm";
import { fieldEmptyValue } from "../resources/js/internal/resource";
import { normalizeUploadData } from "../resources/js/internal/uploadValues";
import { makeField, makeFieldset, makeResource } from "./fixtures";

describe("useForm", () => {
  it.each([
    [makeField("text", "Text"), ""],
    [makeField("slug", "Slug"), ""],
    [makeField("otp", "Otp"), ""],
    [makeField("check", "Checkbox", { falseValue: "no" }), "no"],
    [makeField("toggle", "Toggle", { offValue: 0 }), 0],
    [makeField("keyed", "KeyValue"), {}],
    [makeField("single", "KeyValue", { mode: "single" }), []],
    [makeField("choice", "Combobox"), null],
    [makeField("choices", "Combobox", { multiple: true }), []],
    [makeField("tokens", "Combobox", { tokens: true }), []],
    [
      makeField("link", "Link", {
        mode: "structured",
        withLabel: true,
        withTarget: true,
      }),
      { url: "", label: "", target: "" },
    ],
    [makeField("plain_link", "Link"), ""],
    [makeField("rows", "Repeater"), []],
    [makeField("blocks", "Blocks"), []],
    [makeField("files", "File", { multiple: true }), []],
    [makeField("file", "File"), null],
    [
      makeField("composer", "Composer", { allowAttachments: true }),
      { text: "", attachments: [] },
    ],
  ])("uses the documented hidden clear shape for %s", (field, expected) => {
    expect(fieldEmptyValue(field)).toEqual(expected);
  });

  it("manages nested data, custom defaults, and compatibility accessors", () => {
    const form = useForm(
      makeResource(
        { profile: { name: "Ada" }, role: "user" },
        [makeField("profile.name"), makeField("role")],
      ),
    );

    expect(form.getField("profile.name")?.name).toBe("profile.name");
    expect(form.getValue("profile.name")).toBe("Ada");
    form.setData("profile.name", "Grace");
    expect(form.data.value.profile).toEqual({ name: "Grace" });
    expect(form.isDirty.value).toBe(true);
    form.defaults();
    form.setValue("profile.name", "Linus");
    form.reset();
    expect(form.getValue("profile.name")).toBe("Grace");
    expect(form.isDirty.value).toBe(false);
    expect(form.touched.size).toBe(0);
  });

  it("traverses recursively nested fieldsets", () => {
    const nested = makeFieldset([makeField("profile.email")], {
      id: "contact",
    });
    const form = useForm(
      makeResource({ profile: { email: "ada@example.test" } }, [nested]),
    );

    expect(form.fields.value.map((field) => field.path)).toEqual([
      "profile.email",
    ]);
    expect(form.getField("profile.email")?.schemaField).toBe(
      nested.fields[0],
    );
  });

  it("supports literal conditions and clears only after visible-to-hidden", async () => {
    const resource = makeResource(
      { kind: "person", company: "Keep initially" },
      [
        makeField("kind"),
        makeField("company", "Text", {
          visibility: { field: "kind", operator: "=", value: "business" },
          clearWhenHidden: true,
        }),
      ],
    );
    const form = useForm(resource);
    await nextTick();
    expect(form.getValue("company")).toBe("Keep initially");
    form.setValue("kind", "business");
    await nextTick();
    expect(form.isVisible("company")).toBe(true);
    form.setValue("kind", "person");
    await nextTick();
    expect(form.getValue("company")).toBe("");
  });

  it("resolves row-local repeater conditions to qualified paths", async () => {
    const repeater = makeField("items", "Repeater", {
      schema: [
        makeField("kind"),
        makeField("company", "Text", {
          visibility: { field: "kind", operator: "=", value: "business" },
          clearWhenHidden: true,
        }),
        makeField("root_note", "Text", {
          visibility: {
            field: "$.account_type",
            operator: "=",
            value: "team",
          },
        }),
        makeField("fallback_note", "Text", {
          visibility: {
            field: "account_type",
            operator: "=",
            value: "team",
          },
        }),
      ],
    });
    const form = useForm(
      makeResource(
        {
          account_type: "team",
          items: [
            {
              kind: "business",
              company: "Inertify",
              root_note: "A",
              fallback_note: "A",
            },
            {
              kind: "person",
              company: "Do not clear initially",
              root_note: "B",
              fallback_note: "B",
            },
          ],
        },
        [makeField("account_type"), repeater],
      ),
    );

    await nextTick();
    expect(form.resolveField("company")).toBeNull();
    expect(form.resolveField("items.0.company")?.schemaName).toBe("company");
    expect(form.isVisible("items.0.company")).toBe(true);
    expect(form.isVisible("items.1.company")).toBe(false);
    expect(form.isVisible("items.0.root_note")).toBe(true);
    expect(form.isVisible("items.1.root_note")).toBe(true);
    expect(form.isVisible("items.0.fallback_note")).toBe(true);
    expect(form.isVisible("items.1.fallback_note")).toBe(true);
    expect(form.getValue("items.1.company")).toBe("Do not clear initially");

    form.setValue("items.0.kind", "person");
    await nextTick();
    expect(form.getValue("items.0.company")).toBe("");
    expect(form.getValue("items.1.company")).toBe("Do not clear initially");
  });

  it("resolves active block-set fields below the row data path", () => {
    const form = useForm(
      makeResource(
        {
          content: [
            { type: "hero", data: { title: "Welcome" } },
            { type: "quote", data: { citation: "Ada" } },
          ],
        },
        [
          makeField("content", "Blocks", {
            sets: [
              { type: "hero", schema: [makeField("title")] },
              { type: "quote", schema: [makeField("citation")] },
            ],
          }),
        ],
      ),
    );

    expect(form.resolveField("content.0.data.title")?.schemaName).toBe("title");
    expect(form.resolveField("content.1.data.citation")?.schemaName).toBe(
      "citation",
    );
    expect(form.resolveField("content.0.data.citation")).toBeNull();
  });

  it("clears a hidden collection parent without recreating stale rows", async () => {
    const form = useForm(
      makeResource(
        { enabled: true, items: [{ title: "Keep until hidden" }] },
        [
          makeField("enabled"),
          makeField("items", "Repeater", {
            visibility: { field: "enabled", operator: "truthy" },
            clearWhenHidden: true,
            schema: [makeField("title")],
          }),
        ],
      ),
    );
    await nextTick();
    form.setValue("enabled", false);
    await nextTick();
    expect(form.getValue("items")).toEqual([]);
    expect(form.resolveField("items.0.title")).toBeNull();
  });

  it("supports canonical and/or condition group modes", () => {
    const form = useForm(
      makeResource(
        { first: false, second: true, result: "visible" },
        [
          makeField("first"),
          makeField("second"),
          makeField("result", "Text", {
            visibility: {
              mode: "or",
              conditions: [
                { field: "first", operator: "truthy" },
                { field: "second", operator: "truthy" },
              ],
            },
          }),
        ],
      ),
    );

    expect(form.isVisible("result")).toBe(true);
  });

  it("matches Laravel blank and numeric comparison semantics", () => {
    const form = useForm(
      makeResource(
        { value: "abc", list: [""], numeric_result: null, list_result: null },
        [
          makeField("value"),
          makeField("list"),
          makeField("numeric_result", "Text", {
            visibility: { field: "value", operator: ">", value: 1 },
          }),
          makeField("list_result", "Text", {
            visibility: { field: "list", operator: "not_empty" },
          }),
        ],
      ),
    );
    expect(form.isVisible("numeric_result")).toBe(false);
    expect(form.isVisible("list_result")).toBe(true);
  });

  it("omits hidden fieldsets and handles nullable generated IDs", () => {
    const resource = makeResource(
      { first: "a", second: "b" },
      [makeField("first")],
      {
        fieldsets: [
          makeFieldset([makeField("first")], { id: null }),
          makeFieldset([makeField("second")], {
            id: "hidden",
            visibility: false,
          }),
        ],
      },
    );
    const form = useForm(resource);
    expect(form.resolveFieldset("fieldset-1")?.id).toBeNull();
    expect(form.visibleFieldsets.value).toHaveLength(1);
  });

  it("validates through an injected transport and maps Laravel errors", async () => {
    const transport = vi.fn(async () => ({
      valid: false,
      errors: { email: ["Email is invalid."] },
    }));
    const form = useForm(
      makeResource({ email: "bad" }, [
        makeField("email", "Text", { precognitive: true }),
      ]),
      { validationTransport: transport },
    );

    await expect(form.validate("email")).resolves.toBe(false);
    expect(transport).toHaveBeenCalledWith(
      expect.objectContaining({ path: "email", method: "POST" }),
    );
    expect(form.errors.value.email).toBe("Email is invalid.");
  });

  it("submits the configured method with only the enabled submitter and resets dirty state", () => {
    const form = useForm(
      makeResource({ title: "Draft" }, [makeField("title")]),
    );
    form.setValue("title", "Published");
    const submitter = document.createElement("button");
    submitter.name = "intent";
    submitter.value = "publish";

    expect(form.submit({ submitter })).toBe(true);
    const submissions = (
      globalThis as typeof globalThis & {
        __inertifySubmissions: Array<Record<string, unknown>>;
      }
    ).__inertifySubmissions;
    const submission = submissions[0] as {
      method: string;
      url: string;
      data: Record<string, unknown>;
    };
    expect(submission.method).toBe("post");
    expect(submission.url).toBe("/submit");
    expect(submission.data).toEqual({
      title: "Published",
      intent: "publish",
    });
    expect(form.isDirty.value).toBe(false);
    expect(form.touched.size).toBe(0);
  });

  it("spoofs non-POST multipart methods and forces FormData", () => {
    const file = new File(["avatar"], "avatar.txt", { type: "text/plain" });
    const form = useForm(
      makeResource(
        { profile: { avatar: file } },
        [makeField("profile.avatar", "File", { storeWithForm: true })],
        { method: "PATCH" },
      ),
    );
    form.submit();
    const submission = (
      globalThis as typeof globalThis & {
        __inertifySubmissions: Array<Record<string, unknown>>;
      }
    ).__inertifySubmissions[0] as {
      method: string;
      data: { profile: { avatar: File }; _method: string };
      options: { forceFormData: boolean };
    };
    expect(submission.method).toBe("post");
    expect(submission.data._method).toBe("PATCH");
    expect(submission.data.profile.avatar).toBeInstanceOf(File);
    expect(submission.options.forceFormData).toBe(true);
  });

  it("submits existing file keys without exposing their display resources as values", () => {
    const existing = {
      key: "encrypted-existing-key",
      name: "contract.pdf",
      mimeType: "application/pdf",
      size: 2048,
      url: "/files/contract.pdf",
    };
    const form = useForm(
      makeResource({ contract: existing }, [
        makeField("contract", "File", {
          temporaryUploadUrl: "/uploads",
        }),
      ]),
    );

    expect(form.getValue("contract")).toBe("encrypted-existing-key");
    expect(form.getDefaultValue("contract")).toBe("encrypted-existing-key");
    expect(form.isDirty.value).toBe(false);
    form.submit();

    const submission = (
      globalThis as typeof globalThis & {
        __inertifySubmissions: Array<Record<string, unknown>>;
      }
    ).__inertifySubmissions[0] as { data: Record<string, unknown> };
    expect(submission.data.contract).toBe("encrypted-existing-key");
  });

  it("normalizes existing file resources without browser globals", () => {
    vi.stubGlobal("File", undefined);
    vi.stubGlobal("Blob", undefined);

    try {
      const normalized = normalizeUploadData(
        makeResource(
          {
            attachment: {
              key: "ssr-safe-key",
              name: "server.txt",
              mimeType: "text/plain",
              size: 10,
            },
          },
          [makeField("attachment", "File")],
        ),
      );

      expect(normalized.attachment).toBe("ssr-safe-key");
    } finally {
      vi.unstubAllGlobals();
    }
  });

  it("synchronizes a replacement server resource", async () => {
    const resource = shallowRef(
      makeResource({ name: "Before" }, [makeField("name")]),
    );
    const form = useForm(resource);
    resource.value = makeResource({ name: "After" }, [makeField("name")]);
    await nextTick();
    expect(form.getValue("name")).toBe("After");
    expect(form.isDirty.value).toBe(false);
  });

  it("preserves live edits across options-only resource replacements", async () => {
    const resource = shallowRef(
      makeResource({ name: "Before", owner_id: null }, [
        makeField("name"),
        makeField("owner_id", "Combobox", {
          optionsMode: "inertia",
          optionsKey: "owner_id",
        }),
      ]),
    );
    const form = useForm(resource);
    form.setValue("name", "Live edit");
    resource.value = makeResource(
      { name: "Before", owner_id: null },
      resource.value.fieldsets[0]?.fields ?? [],
      { meta: { options: { owner_id: { data: [] } } } },
    );
    await nextTick();
    expect(form.getValue("name")).toBe("Live edit");
    expect(form.isDirty.value).toBe(true);
    expect(form.isTouched("name")).toBe(true);
  });

  it("installs unsaved guards only while configured data is dirty", async () => {
    const form = useForm(
      makeResource({ name: "Ada" }, [makeField("name")], {
        unsavedWarning: true,
      }),
    );
    const globals = globalThis as typeof globalThis & {
      __inertifyBeforeListeners: Set<
        (event: { preventDefault: () => void }) => void
      >;
    };
    expect(globals.__inertifyBeforeListeners.size).toBe(0);
    form.setValue("name", "Grace");
    await nextTick();
    expect(globals.__inertifyBeforeListeners.size).toBe(1);

    const beforeUnload = new Event("beforeunload", { cancelable: true });
    window.dispatchEvent(beforeUnload);
    expect(beforeUnload.defaultPrevented).toBe(true);

    vi.spyOn(window, "confirm").mockReturnValue(false);
    const navigation = { preventDefault: vi.fn() };
    globals.__inertifyBeforeListeners.forEach((listener) => listener(navigation));
    expect(navigation.preventDefault).toHaveBeenCalledOnce();

    form.defaults();
    await nextTick();
    expect(globals.__inertifyBeforeListeners.size).toBe(0);
  });

  it("scrolls an offscreen registered first error without focusing it", () => {
    vi.spyOn(window, "requestAnimationFrame").mockImplementation((callback) => {
      callback(0);

      return 1;
    });
    const element = document.createElement("input");
    const scrollIntoView = vi.fn();
    const focus = vi.spyOn(element, "focus");
    element.scrollIntoView = scrollIntoView;
    vi.spyOn(element, "getBoundingClientRect").mockReturnValue({
      top: window.innerHeight + 10,
      bottom: window.innerHeight + 40,
      left: 0,
      right: 100,
      width: 100,
      height: 30,
      x: 0,
      y: window.innerHeight + 10,
      toJSON: () => ({}),
    });
    const form = useForm(
      makeResource({ email: "bad" }, [makeField("email")], {
        scrollToFirstError: true,
      }),
    );
    form.registerFieldElement("email", element);
    (
      globalThis as typeof globalThis & {
        __inertifyNextErrors: Record<string, string> | null;
      }
    ).__inertifyNextErrors = { email: "Invalid." };
    form.submit();
    expect(scrollIntoView).toHaveBeenCalledWith({
      behavior: "smooth",
      block: "center",
    });
    expect(focus).not.toHaveBeenCalled();
  });
});
