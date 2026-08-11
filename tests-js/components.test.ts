import { h } from "vue";
import { mount } from "@vue/test-utils";
import { describe, expect, it, vi } from "vitest";
import Form from "../resources/js/components/Form";
import HeadlessFormFields from "../resources/js/components/HeadlessFormFields";
import HeadlessFormFieldsets from "../resources/js/components/HeadlessFormFieldsets";
import { makeField, makeResource } from "./fixtures";

describe("renderless form components", () => {
  it("renders no package-owned element without a consumer slot", () => {
    const wrapper = mount(Form, {
      props: {
        form: makeResource({ name: "Ada" }, [makeField("name")]),
      },
    });

    expect(wrapper.find("*").exists()).toBe(false);
    expect(wrapper.html()).not.toContain("<form");
  });

  it("provides the upstream-compatible root slot payload", () => {
    const wrapper = mount(Form, {
      props: {
        form: makeResource({ name: "Ada" }, [makeField("name")]),
      },
      slots: {
        default: ({ form, data, setData }) => {
          setData("name", "Grace");
          return h("output", { "data-id": form.formId.value }, data.name);
        },
      },
    });

    expect(wrapper.get("output").text()).toBe("Ada");
    expect(wrapper.get("output").attributes("data-id")).toMatch(
      /^inertify-form-/,
    );
  });

  it("prioritizes qualified field, normalized type, and default slots", () => {
    const resource = makeResource(
      { email: "ada@example.com" },
      [makeField("email", "TextInput")],
    );
    const wrapper = mount(Form, {
      props: { form: resource },
      slots: {
        default: ({ form }) =>
          h(
            HeadlessFormFields,
            { form },
            {
              "before-email-field": () => h("i", "before"),
              "email-field": () => h("b", "deprecated fallback"),
              "field-email": ({ value }: { value: unknown }) =>
                h("b", String(value)),
              "type-text-input": () => h("b", "wrong type"),
              "after-email-field": () => h("i", "after"),
            },
          ),
      },
    });

    expect(wrapper.text()).toBe("beforeada@example.comafter");
    expect(wrapper.text()).not.toContain("wrong");
    expect(wrapper.text()).not.toContain("deprecated");
  });

  it("keeps the upstream name-field slot as a deprecated final fallback", () => {
    const resource = makeResource({ email: "ada@example.com" }, [
      makeField("email", "TextInput"),
    ]);
    const wrapper = mount(Form, {
      props: { form: resource },
      slots: {
        default: ({ form }) =>
          h(
            HeadlessFormFields,
            { form },
            {
              "email-field": ({ value }: { value: unknown }) =>
                h("span", String(value)),
            },
          ),
      },
    });

    expect(wrapper.text()).toBe("ada@example.com");
  });

  it("falls through field alias, normalized type, and default slots", () => {
    const resource = makeResource(
      { one: "1", two: "2", three: "3" },
      [
        makeField("one", "CustomField"),
        makeField("two", "DatePicker"),
        makeField("three", "Unknown"),
      ],
    );
    const wrapper = mount(Form, {
      props: { form: resource },
      slots: {
        default: ({ form }) =>
          h(
            HeadlessFormFields,
            { form },
            {
              "field-one": () => h("span", "field"),
              "type-date-picker": () => h("span", "type"),
              default: ({ name }: { name: string }) =>
                h("span", `default:${name}`),
            },
          ),
      },
    });

    expect(wrapper.text()).toBe("fieldtypedefault:three");
  });

  it("supports generated fieldset compatibility slots", () => {
    const resource = makeResource(
      { name: "Ada" },
      [makeField("name")],
      { fieldsets: [{ ...makeResource({ name: "" }, []).fieldsets[0]!, id: null, fields: [makeField("name")] }] },
    );
    const wrapper = mount(Form, {
      props: { form: resource },
      slots: {
        default: ({ form }) =>
          h(
            HeadlessFormFieldsets,
            { form },
            {
              "before-fieldset-1-fieldset": () => h("span", "before"),
              "fieldset-1-fieldset": ({ id }: { id: string }) =>
                h("span", id),
              "after-fieldset-1-fieldset": () => h("span", "after"),
            },
          ),
      },
    });

    expect(wrapper.text()).toBe("beforefieldset-1after");
  });

  it("emits the complete successful submission lifecycle and preserves callbacks", () => {
    const success = vi.fn();
    let submitted = false;
    const wrapper = mount(Form, {
      props: {
        form: makeResource({ name: "Ada" }, [makeField("name")]),
        options: { submit: { onSuccess: success } },
      },
      slots: {
        default: ({ submit }: { submit: () => boolean }) => {
          if (!submitted) {
            submitted = true;
            submit();
          }

          return null;
        },
      },
    });

    expect(wrapper.emitted("before")).toHaveLength(1);
    expect(wrapper.emitted("start")).toHaveLength(1);
    expect(wrapper.emitted("progress")?.[0]?.[0]).toEqual({ percentage: 50 });
    expect(wrapper.emitted("success")).toHaveLength(1);
    expect(wrapper.emitted("finish")).toHaveLength(1);
    expect(success).toHaveBeenCalledOnce();
  });

  it("emits error and cancellation lifecycle payloads", () => {
    const globals = globalThis as typeof globalThis & {
      __inertifyNextErrors: Record<string, string> | null;
      __inertifyHoldSubmission: boolean;
    };
    globals.__inertifyNextErrors = { name: "Required." };
    let submitted = false;
    const failed = mount(Form, {
      props: { form: makeResource({ name: "" }, [makeField("name")]) },
      slots: {
        default: ({ submit }: { submit: () => boolean }) => {
          if (!submitted) {
            submitted = true;
            submit();
          }

          return null;
        },
      },
    });
    expect(failed.emitted("error")?.[0]?.[0]).toEqual({ name: "Required." });
    expect(failed.emitted("success")).toBeUndefined();
    expect(failed.emitted("finish")).toHaveLength(1);

    globals.__inertifyHoldSubmission = true;
    submitted = false;
    const cancelled = mount(Form, {
      props: { form: makeResource({ name: "Ada" }, [makeField("name")]) },
      slots: {
        default: ({ submit }: { submit: () => boolean }) => {
          if (!submitted) {
            submitted = true;
            submit();
          }

          return null;
        },
      },
    });
    (cancelled.vm as unknown as { cancel: () => void }).cancel();
    expect(cancelled.emitted("cancel")).toHaveLength(1);
    expect(cancelled.emitted("finish")).toHaveLength(1);
  });
});
