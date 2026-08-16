import { createSSRApp, defineComponent, h, type PropType } from "vue";
import { renderToString } from "vue/server-renderer";
import { mount } from "@vue/test-utils";
import { describe, expect, it, vi } from "vitest";
import Form from "../resources/js/components/Form";
import { createFormRenderer, FormProvider } from "../resources/js/components";
import type { FormFieldRendererDefinition } from "../resources/js/types";
import { makeField, makeResource } from "./fixtures";

const ValueRenderer = defineComponent({
  name: "ValueRenderer",
  inheritAttrs: false,
  props: {
    name: { type: String, required: true },
    value: { type: null as unknown as PropType<unknown>, default: null },
    setValue: {
      type: Function as PropType<(value: unknown) => void>,
      required: true,
    },
    multiline: { type: Boolean, default: false },
    marker: { type: String, default: "registered" },
  },
  setup(props) {
    return () =>
      h(props.multiline ? "textarea" : "input", {
        name: props.name,
        value: String(props.value ?? ""),
        "data-marker": props.marker,
        onInput: (event: Event) =>
          props.setValue((event.target as HTMLInputElement).value),
      });
  },
});

const FallbackRenderer = defineComponent({
  inheritAttrs: false,
  props: {
    field: {
      type: Object as PropType<{ component: string }>,
      required: true,
    },
  },
  setup(props) {
    return () =>
      h("output", { "data-fallback": "" }, props.field.component);
  },
});

describe("createFormRenderer", () => {
  it("supports slot-only rendering without a renderer registry", () => {
    const { FormField, FormFields } = createFormRenderer();
    const resource = makeResource(
      { name: "Ada", email: "ada@example.com" },
      [makeField("name"), makeField("email")],
    );
    const wrapper = mount(Form, {
      props: { form: resource },
      slots: {
        default: ({ form }) =>
          h("div", [
            h(
              FormFields,
              { form },
              {
                "field-name": ({ value }: { value: unknown }) =>
                  h("output", { "data-name": "" }, String(value)),
              },
            ),
            h(
              FormField,
              { form, name: "email" },
              {
                default: ({ value }: { value: unknown }) =>
                  h("output", { "data-email": "" }, String(value)),
              },
            ),
          ]),
      },
    });

    expect(wrapper.get("[data-name]").text()).toBe("Ada");
    expect(wrapper.get("[data-email]").text()).toBe("ada@example.com");
  });

  it("reports public component names when form context is missing", () => {
    const { FormField, FormFields } = createFormRenderer();
    const warning = vi.spyOn(console, "warn").mockImplementation(() => {});

    try {
      expect(() => mount(FormFields)).toThrow(
        "Registered FormFields requires a `form` prop or form provider context.",
      );
      expect(() => mount(FormField, { props: { name: "email" } })).toThrow(
        "Registered FormField requires a `form` prop or form provider context.",
      );
    } finally {
      warning.mockRestore();
    }
  });

  it("renders registered components, preset props, and null entries", async () => {
    const { FormFields } = createFormRenderer({
      name: "WorkbenchForm",
      renderers: {
        Text: { component: ValueRenderer },
        Textarea: {
          component: ValueRenderer,
          props: {
            multiline: true,
            marker: "preset",
            name: "cannot-replace-payload",
          },
        },
        Submit: null,
      },
    });
    let currentName: unknown;
    const wrapper = mount(Form, {
      props: {
        form: makeResource(
          { name: "Ada", bio: "Notes", save: null },
          [
            makeField("name", "Text"),
            makeField("bio", "Textarea"),
            makeField("save", "Submit"),
          ],
        ),
      },
      slots: {
        default: ({ form }) => {
          currentName = form.data.value.name;

          return h(FormFields, { form, fieldset: "main" });
        },
      },
    });

    expect(FormFields.name).toBe("WorkbenchFormFields");
    expect(wrapper.get('input[name="name"]').attributes("data-marker")).toBe(
      "registered",
    );
    expect(wrapper.get('textarea[name="bio"]').attributes("data-marker")).toBe(
      "preset",
    );
    expect(wrapper.find('[name="save"]').exists()).toBe(false);

    await wrapper.get('input[name="name"]').setValue("Grace");

    expect(currentName).toBe("Grace");
  });

  it("renders one registered field by qualified name", () => {
    const { FormField } = createFormRenderer({
      renderers: { Text: ValueRenderer },
    });
    expect(FormField.name).toBe("RegisteredFormField");
    const wrapper = mount(Form, {
      props: {
        form: makeResource(
          { name: "Ada", email: "ada@example.com" },
          [makeField("name"), makeField("email")],
        ),
      },
      slots: {
        default: () => h(FormField, { name: "email" }),
      },
    });

    expect(wrapper.findAll("input").map((field) => field.attributes("name")))
      .toEqual(["email"]);

    const bySchema = mount(Form, {
      props: {
        form: makeResource(
          { name: "Ada", email: "ada@example.com" },
          [makeField("name"), makeField("email")],
        ),
      },
      slots: {
        default: ({ form }) =>
          h(FormField, {
            form,
            name: form.resource.value.fieldsets[0]!.fields[0]!,
          }),
      },
    });

    expect(bySchema.findAll("input").map((field) => field.attributes("name")))
      .toEqual(["name"]);
  });

  it("lets collection renderers own descendants without duplicate fields", () => {
    const RepeaterRenderer = defineComponent({
      inheritAttrs: false,
      setup() {
        return () =>
          h("section", { "data-repeater": "" }, [
            h("input", { "data-owned-child": "" }),
          ]);
      },
    });
    const { FormField, FormFields } = createFormRenderer({
      renderers: {
        Repeater: RepeaterRenderer,
        Text: ValueRenderer,
      },
    });
    const resource = makeResource(
      { projects: [{ title: "Analytical Engine" }] },
      [
        makeField("projects", "Repeater", {
          schema: [makeField("title", "Text")],
        }),
      ],
    );
    const wrapper = mount(Form, {
      props: { form: resource },
      slots: {
        default: ({ form }) => h(FormFields, { form }),
      },
    });

    expect(wrapper.findAll("[data-repeater]")).toHaveLength(1);
    expect(wrapper.findAll("input")).toHaveLength(1);
    expect(wrapper.find('[name="projects.0.title"]').exists()).toBe(false);

    const nested = mount(Form, {
      props: { form: resource },
      slots: {
        default: ({ form }) =>
          h(FormField, { form, name: "projects.0.title" }),
      },
    });

    expect(nested.get('input[name="projects.0.title"]').element).toBeTruthy();
  });

  it("preserves field, type, insertion, and default slot overrides", () => {
    const { FormFields } = createFormRenderer({
      renderers: {
        Text: ValueRenderer,
        Textarea: ValueRenderer,
      },
    });
    const resource = makeResource(
      { name: "Ada", bio: "Notes" },
      [makeField("name", "Text"), makeField("bio", "Textarea")],
    );
    const named = mount(Form, {
      props: { form: resource },
      slots: {
        default: ({ form }) =>
          h(
            FormFields,
            { form },
            {
              "before-name-field": () => h("i", "before"),
              "field-name": () => h("b", "field"),
              "after-name-field": () => h("i", "after"),
              "type-textarea": () => h("b", "type"),
            },
          ),
      },
    });

    expect(named.text()).toBe("beforefieldaftertype");

    const fallback = mount(Form, {
      props: { form: resource },
      slots: {
        default: ({ form }) =>
          h(
            FormFields,
            { form },
            {
              default: ({ name }: { name: string }) =>
                h("span", `default:${name}`),
            },
          ),
      },
    });

    expect(fallback.text()).toBe("default:namedefault:bio");
    expect(fallback.find("input").exists()).toBe(false);

    const legacy = mount(Form, {
      props: {
        form: makeResource({ name: "Ada" }, [makeField("name")]),
      },
      slots: {
        default: ({ form }) =>
          h(
            FormFields,
            { form },
            {
              "name-field": () => h("span", "legacy"),
            },
          ),
      },
    });

    expect(legacy.text()).toBe("legacy");
    expect(legacy.find("input").exists()).toBe(false);
  });

  it("distinguishes unknown fields from intentionally renderless fields", () => {
    const { FormFields } = createFormRenderer({
      renderers: {
        Submit: null,
        Silent: { component: null },
      },
      fallback: FallbackRenderer,
    });
    const resource = makeResource(
      { custom: null, silent: null, save: null },
      [
        makeField("custom", "CustomWidget"),
        makeField("silent", "Silent"),
        makeField("save", "Submit"),
      ],
    );
    const fallback = mount(Form, {
      props: { form: resource },
      slots: { default: ({ form }) => h(FormFields, { form }) },
    });

    expect(fallback.findAll("[data-fallback]")).toHaveLength(1);
    expect(fallback.get("[data-fallback]").text()).toBe("CustomWidget");

    const overridden = mount(Form, {
      props: { form: resource },
      slots: {
        default: ({ form }) =>
          h(
            FormFields,
            { form },
            {
              unsupported: ({ name }: { name: string }) =>
                h("output", { "data-unsupported": "" }, name),
            },
          ),
      },
    });

    expect(overridden.get("[data-unsupported]").text()).toBe("custom");
    expect(overridden.find("[data-fallback]").exists()).toBe(false);
  });

  it("keeps renderer registries isolated per factory instance", () => {
    const First = defineComponent(() => () => h("span", "first"));
    const Second = defineComponent(() => () => h("span", "second"));
    const first = createFormRenderer({ renderers: { Text: First } });
    const second = createFormRenderer({ renderers: { Text: Second } });
    const resource = makeResource({ name: "Ada" }, [makeField("name")]);

    const wrapper = mount(Form, {
      props: { form: resource },
      slots: {
        default: ({ form }) =>
          h("div", [
            h(first.FormFields, { form }),
            h(second.FormFields, { form }),
          ]),
      },
    });

    expect(wrapper.text()).toBe("firstsecond");
  });

  it("snapshots renderer definitions and preset props", () => {
    const preset = { marker: "original" };
    const definition: FormFieldRendererDefinition = {
      component: ValueRenderer,
      props: preset,
    };
    const renderers = { Text: definition };
    const { FormFields } = createFormRenderer({ renderers });

    definition.component = defineComponent(() => () => h("span", "mutated"));
    preset.marker = "mutated";
    renderers.Text = definition;

    const wrapper = mount(Form, {
      props: {
        form: makeResource({ name: "Ada" }, [makeField("name")]),
      },
      slots: {
        default: ({ form }) => h(FormFields, { form }),
      },
    });

    expect(wrapper.get("input").attributes("data-marker")).toBe("original");
    expect(wrapper.text()).not.toContain("mutated");
  });

  it("renders nothing for an unknown field without an application fallback", () => {
    const { FormFields } = createFormRenderer();
    const wrapper = mount(FormProvider, {
      props: {
        form: makeResource(
          { custom: "value" },
          [makeField("custom", "CustomWidget")],
        ),
      },
      slots: {
        default: ({ form }) => h(FormFields, { form }),
      },
    });

    expect(wrapper.find("*").exists()).toBe(false);
  });

  it("renders with an instance-local registry during SSR", async () => {
    const { FormFields } = createFormRenderer({
      renderers: { Text: ValueRenderer },
    });
    const resource = makeResource({ name: "Ada" }, [makeField("name")]);
    const app = createSSRApp({
      render: () =>
        h(
          Form,
          { form: resource },
          { default: () => h(FormFields) },
        ),
    });

    await expect(renderToString(app)).resolves.toContain('name="name"');
  });
});
