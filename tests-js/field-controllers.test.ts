import { nextTick } from "vue";
import { describe, expect, it } from "vitest";
import {
  useChoices,
  useComposer,
  useDate,
  useFormFieldController,
  useLink,
  useOtp,
  useRichText,
  useSlug,
  useTextInput,
} from "../resources/js/fieldControllers";
import { useForm } from "../resources/js/useForm";
import { makeField, makeResource } from "./fixtures";

describe("field-specific controllers", () => {
  it("applies text masks without rendering an input", () => {
    const form = useForm(
      makeResource({ phone: "" }, [
        makeField("phone", "Text", { mask: "(999) 999-9999" }),
      ]),
    );
    const controller = useTextInput("phone", form);
    expect(controller.input("555abc1234567")).toBe("(555) 123-4567");
    expect(controller.display.value).toBe("(555) 123-4567");
    expect(form.getValue("phone")).toBe("5551234567");
  });

  it("generates slugs reactively and respects manual locking", async () => {
    const form = useForm(
      makeResource({ title: "Hello World", slug: "" }, [
        makeField("title"),
        makeField("slug", "Slug", {
          from: "title",
          lockOnManualEdit: true,
          updateOnEdit: true,
        }),
      ]),
    );
    const slug = useSlug("slug", form);
    expect(form.getValue("slug")).toBe("hello-world");
    form.setValue("title", "Changed");
    await nextTick();
    expect(form.getValue("slug")).toBe("changed");
    slug.manualInput("My Custom Slug");
    expect(form.getValue("slug")).toBe("My Custom Slug");
    form.setValue("title", "Changed Again");
    await nextTick();
    expect(form.getValue("slug")).toBe("My Custom Slug");
    expect(slug.locked.value).toBe(true);
  });

  it("formats and parses number and currency values", () => {
    const form = useForm(
      makeResource({ amount: 1234.5 }, [
        makeField("amount", "Text", {
          currency: { locale: "en-US", currency: "USD", decimals: 2 },
          parseNumbers: true,
        }),
      ]),
    );
    const amount = useTextInput("amount", form);
    expect(amount.display.value).toBe("$1,234.50");
    expect(amount.input("$2,500.75")).toBe("$2,500.75");
    expect(form.getValue("amount")).toBe(2500.75);
  });

  it("honors slug empty-only, edit, separator, and case options", async () => {
    const form = useForm(
      makeResource(
        {
          title: "Initial Title",
          fixed: "manual",
          once: "",
          cased: "",
        },
        [
          makeField("title"),
          makeField("fixed", "Slug", {
            from: "title",
            onlyWhenEmpty: true,
          }),
          makeField("once", "Slug", {
            from: "title",
            updateOnEdit: false,
          }),
          makeField("cased", "Slug", {
            from: "title",
            separator: "_",
            lowercase: false,
          }),
        ],
      ),
    );
    useSlug("fixed", form);
    useSlug("once", form);
    useSlug("cased", form);
    expect(form.getValue("fixed")).toBe("manual");
    expect(form.getValue("once")).toBe("initial-title");
    expect(form.getValue("cased")).toBe("Initial_Title");
    form.setValue("title", "Second Title");
    await nextTick();
    expect(form.getValue("fixed")).toBe("manual");
    expect(form.getValue("once")).toBe("initial-title");
    expect(form.getValue("cased")).toBe("Second_Title");
  });

  it("manages single and multiple choices", () => {
    const form = useForm(
      makeResource({ roles: ["admin"], status: "draft" }, [
        makeField("roles", "CheckboxGroup", { multiple: true }),
        makeField("status", "Radio"),
      ]),
    );
    const roles = useChoices("roles", form);
    roles.toggle("editor");
    roles.toggle("admin");
    expect(form.getValue("roles")).toEqual(["editor"]);
    const status = useChoices("status", form);
    status.select("published");
    expect(form.getValue("status")).toBe("published");
  });

  it("normalizes dates, rich text, OTP digits, and link metadata", () => {
    const form = useForm(
      makeResource(
        {
          date: null,
          body: "<p>A</p>",
          code: "12",
          docs: { url: "example.com", label: "Docs", target: "_blank" },
        },
        [
          makeField("date", "DatePicker"),
          makeField("body", "RichText"),
          makeField("code", "Otp", { length: 4 }),
          makeField("docs", "Link", {
            mode: "structured",
            withLabel: true,
            withTarget: true,
            requireScheme: true,
            allowedSchemes: ["https"],
          }),
        ],
      ),
    );
    const date = useDate("date", form);
    date.setDate(new Date("2026-08-11T12:00:00Z"));
    expect(date.date.value).toBe("2026-08-11");
    const rich = useRichText("body", form);
    rich.setHtml("<p>B</p>");
    expect(rich.html.value).toBe("<p>B</p>");
    const otp = useOtp("code", form);
    otp.setDigit(2, "3");
    otp.setDigit(3, "4");
    expect(otp.complete.value).toBe(true);
    expect(form.getValue("code")).toBe("1234");
    const link = useLink("docs", form);
    expect(link.href.value).toBe("https://example.com");
    expect(link.target.value).toBe("_blank");
    link.setUrl("docs.example.com");
    link.setLabel("Reference");
    expect(form.getValue("docs")).toEqual({
      url: "https://docs.example.com",
      label: "Reference",
      target: "_blank",
    });
    link.setUrl("javascript:alert(1)");
    expect(link.validScheme.value).toBe(false);
  });

  it("dispatches canonical PascalCase components", () => {
    const form = useForm(
      makeResource({ text: "", hidden: "", code: "", slug: "", tags: [] }, [
        makeField("text", "Text"),
        makeField("hidden", "Hidden"),
        makeField("code", "Otp", { length: 4 }),
        makeField("slug", "Slug"),
        makeField("tags", "CheckboxGroup", { multiple: true }),
      ]),
    );
    expect(useFormFieldController("text", form)).toHaveProperty("input");
    expect(useFormFieldController("hidden", form)).toHaveProperty("input");
    expect(useFormFieldController("code", form)).toHaveProperty("setDigit");
    expect(useFormFieldController("slug", form)).toHaveProperty("generate");
    expect(useFormFieldController("tags", form)).toHaveProperty("toggle");
  });

  it("normalizes Composer values and reuses headless attachment uploads", async () => {
    const upload = async ({ files }: { files: File[] }) =>
      files.map((file) => ({
        key: `token-${file.name}`,
        name: file.name,
        mimeType: file.type,
        size: file.size,
      }));
    const form = useForm(
      makeResource(
        {
          message: { text: "Hello", attachments: [] as string[] },
          plain: "Draft" as string | null,
        },
        [
          makeField("message", "Composer", {
            allowAttachments: true,
            temporaryUploadUrl: "/attachments",
          }),
          makeField("plain", "Composer"),
        ],
      ),
      { uploadTransport: { upload } },
    );
    const composer = useComposer("message", form);
    composer.setText("Updated");
    await composer.uploadAttachments(
      new File(["a"], "a.txt", { type: "text/plain" }),
    );
    expect(form.getValue("message")).toEqual({
      text: "Updated",
      attachments: ["token-a.txt"],
    });
    composer.clear();
    expect(form.getValue("message")).toEqual({ text: "", attachments: [] });

    const plain = useComposer("plain", form);
    plain.setText("");
    expect(form.getValue("plain")).toBeNull();
  });

  it("keeps RichText HTML separate from companion image tokens", async () => {
    const form = useForm(
      makeResource(
        { body: "<p>Hello</p>", body_images: [] as string[] },
        [
          makeField("body", "RichText", {
            imageUploads: {
              temporaryUploadUrl: "/images",
              multiple: true,
            },
          }),
        ],
      ),
      {
        uploadTransport: {
          upload: async ({ files }) =>
            files.map((file) => ({
              key: `image-${file.name}`,
              name: file.name,
              mimeType: file.type,
              size: file.size,
            })),
        },
      },
    );
    const rich = useRichText("body", form);
    await rich.uploadImages(
      new File(["image"], "hero.png", { type: "image/png" }),
    );
    expect(rich.html.value).toBe("<p>Hello</p>");
    expect(rich.images.value).toEqual(["image-hero.png"]);
    expect(form.getValue("body_images")).toEqual(["image-hero.png"]);
  });
});
