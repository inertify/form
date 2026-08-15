import { h, nextTick, ref } from "vue";
import { mount } from "@vue/test-utils";
import { describe, expect, it, vi } from "vitest";
import Form from "../resources/js/components/Form";
import {
  createFormRenderer,
  FormCollection,
  FormErrors,
  FormFieldsets,
  FormProvider,
  FormSubmit,
  FormUploads,
  FormWizard,
  Wizard,
} from "../resources/js/components";
import { makeField, makeFieldset, makeResource } from "./fixtures";

const { FormFields } = createFormRenderer();

function makeFieldsetSelectorResource() {
  return makeResource(
    { first: "A", second: "B", third: "C", fourth: "D" },
    [],
    {
      fieldsets: [
        makeFieldset([makeField("first")], { id: "first" }),
        makeFieldset([makeField("second")], { id: null }),
        makeFieldset([makeField("third")], {
          id: "third",
          visibility: false,
        }),
        makeFieldset([makeField("fourth")], { id: "fourth" }),
      ],
    },
  );
}

function fieldsetOutput({
  id,
  index,
  originalIndex,
  fields,
  visible,
}: {
  id: string;
  index: number;
  originalIndex: number;
  fields: Array<{ path: string }>;
  visible: boolean;
}) {
  return h(
    "output",
    {
      "data-fieldset": id,
      "data-index": index,
      "data-original-index": originalIndex,
      "data-visible": String(visible),
    },
    fields.map((field) => field.path).join(","),
  );
}

function mountFieldsetSelector(props: Record<string, unknown>) {
  return mount(Form, {
    props: { form: makeFieldsetSelectorResource() },
    slots: {
      default: ({ form }) =>
        h(
          FormFieldsets,
          { ...props, form },
          { default: fieldsetOutput },
        ),
    },
  });
}

describe("renderless form components", () => {
  it("uses unprefixed public component names", () => {
    expect(
      [
        Form,
        Wizard,
        FormProvider,
        FormFieldsets,
        FormErrors,
        FormSubmit,
        FormWizard,
        FormUploads,
        FormCollection,
      ].map((component) => component.name),
    ).toEqual([
      "Form",
      "Wizard",
      "FormProvider",
      "FormFieldsets",
      "FormErrors",
      "FormSubmit",
      "FormWizard",
      "FormUploads",
      "FormCollection",
    ]);
  });

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
      [makeField("email", "Text")],
    );
    const wrapper = mount(Form, {
      props: { form: resource },
      slots: {
        default: ({ form }) =>
          h(
            FormFields,
            { form },
            {
              "before-email-field": () => h("i", "before"),
              "email-field": () => h("b", "deprecated fallback"),
              "field-email": ({ value }: { value: unknown }) =>
                h("b", String(value)),
              "type-text": () => h("b", "wrong type"),
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
      makeField("email", "Text"),
    ]);
    const wrapper = mount(Form, {
      props: { form: resource },
      slots: {
        default: ({ form }) =>
          h(
            FormFields,
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
            FormFields,
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
            FormFieldsets,
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

  it("selects fieldsets by ID without changing schema order or original indexes", () => {
    const selected = mountFieldsetSelector({
      only: ["fourth", "first", "fieldset-2", "missing"],
      except: "first",
    });

    expect(
      selected.findAll("[data-fieldset]").map((fieldset) => ({
        id: fieldset.attributes("data-fieldset"),
        index: fieldset.attributes("data-index"),
        originalIndex: fieldset.attributes("data-original-index"),
      })),
    ).toEqual([
      { id: "fieldset-2", index: "0", originalIndex: "1" },
      { id: "fourth", index: "1", originalIndex: "3" },
    ]);

    const excluded = mountFieldsetSelector({
      except: ["first", "fourth", "missing"],
    });
    expect(
      excluded
        .findAll("[data-fieldset]")
        .map((fieldset) => fieldset.attributes("data-fieldset")),
    ).toEqual(["fieldset-2"]);

    const generated = mountFieldsetSelector({
      only: "fieldset-2",
      except: ["missing"],
    });
    expect(generated.get("[data-fieldset]").attributes("data-fieldset")).toBe(
      "fieldset-2",
    );

    expect(mountFieldsetSelector({ only: "missing" }).find("output").exists())
      .toBe(false);
    expect(mountFieldsetSelector({ only: [] }).find("output").exists()).toBe(
      false,
    );
  });

  it("applies visibility after fieldset selection unless hidden sets are included", () => {
    const visible = mountFieldsetSelector({ only: ["first", "third"] });
    expect(
      visible
        .findAll("[data-fieldset]")
        .map((fieldset) => fieldset.attributes("data-fieldset")),
    ).toEqual(["first"]);

    const hidden = mountFieldsetSelector({
      only: "third",
      includeHidden: true,
    });
    const fieldset = hidden.get('[data-fieldset="third"]');

    expect(fieldset.attributes("data-index")).toBe("0");
    expect(fieldset.attributes("data-original-index")).toBe("2");
    expect(fieldset.attributes("data-visible")).toBe("false");
    expect(fieldset.text()).toBe("third");
  });

  it("reacts to in-place fieldset selector array changes", async () => {
    const only = ref(["first"]);
    const wrapper = mount(Form, {
      props: { form: makeFieldsetSelectorResource() },
      slots: {
        default: ({ form }) =>
          h(
            FormFieldsets,
            { form, only: only.value },
            { default: fieldsetOutput },
          ),
      },
    });

    expect(
      wrapper
        .findAll("[data-fieldset]")
        .map((fieldset) => fieldset.attributes("data-fieldset")),
    ).toEqual(["first"]);

    only.value.splice(0, 1, "fourth", "fieldset-2");
    await nextTick();

    expect(
      wrapper
        .findAll("[data-fieldset]")
        .map((fieldset) => fieldset.attributes("data-fieldset")),
    ).toEqual(["fieldset-2", "fourth"]);
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
