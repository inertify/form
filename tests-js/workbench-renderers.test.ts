import { h } from "vue";
import { mount } from "@vue/test-utils";
import { describe, expect, it } from "vitest";
import Form from "../resources/js/components/Form";
import type { UseFormApi } from "../resources/js/types";
import {
  FormField,
  FormFields,
  formFieldRenderers,
} from "../workbench/resources/js/components/form";
import { makeField, makeResource } from "./fixtures";

describe("workbench field renderers", () => {
  it("registers every serialized package field discriminator", () => {
    expect(Object.keys(formFieldRenderers).sort()).toEqual([
      "Blocks",
      "Checkbox",
      "CheckboxGroup",
      "ColorPicker",
      "Combobox",
      "Composer",
      "DatePicker",
      "File",
      "Hidden",
      "KeyValue",
      "Link",
      "Otp",
      "Radio",
      "Repeater",
      "RichText",
      "Slider",
      "Slug",
      "Submit",
      "Text",
      "Textarea",
      "TimePicker",
      "Toggle",
    ]);
  });

  it("renders registered field components without page-level type slots", async () => {
    let formApi: UseFormApi | undefined;
    const wrapper = mount(Form, {
      props: {
        form: makeResource(
          {
            name: "Ada",
            bio: "Notes",
            employed: true,
            save: null,
          },
          [
            makeField("name", "Text"),
            makeField("bio", "Textarea"),
            makeField("employed", "Checkbox", {
              trueValue: true,
              falseValue: false,
            }),
            makeField("save", "Submit"),
          ],
        ),
      },
      slots: {
        default: ({ form }) => {
          formApi = form;

          return h(FormFields, { form });
        },
      },
    });

    expect(wrapper.get('input[name="name"]').element).toBeInstanceOf(
      HTMLInputElement,
    );
    expect(wrapper.get('textarea[name="bio"]').element).toBeInstanceOf(
      HTMLTextAreaElement,
    );
    expect(wrapper.get('input[name="employed"]').attributes("type")).toBe(
      "checkbox",
    );
    expect(wrapper.find("[controller]").exists()).toBe(false);
    expect(wrapper.find("[context]").exists()).toBe(false);
    expect(wrapper.find('[name="save"]').exists()).toBe(false);
    expect(wrapper.text()).not.toContain("Unsupported field");

    await wrapper.get('input[name="name"]').setValue("Grace");

    expect(formApi?.data.value.name).toBe("Grace");
  });

  it("places registered fields explicitly in consumer-defined order", () => {
    const wrapper = mount(Form, {
      props: {
        form: makeResource(
          { name: "Ada", email: "ada@example.com" },
          [makeField("name"), makeField("email")],
        ),
      },
      slots: {
        default: () =>
          h("section", [
            h(FormField, { name: "email" }),
            h(FormField, { name: "name" }),
          ]),
      },
    });

    expect(wrapper.findAll("input").map((input) => input.attributes("name")))
      .toEqual(["email", "name"]);
  });

  it("allows page-level field overrides and an unsupported fallback", () => {
    const wrapper = mount(Form, {
      props: {
        form: makeResource(
          { name: "Ada", custom: "value" },
          [makeField("name"), makeField("custom", "CustomWidget")],
        ),
      },
      slots: {
        default: ({ form }) =>
          h(
            FormFields,
            { form },
            {
              "field-name": ({ value }: { value: unknown }) =>
                h("output", { "data-field": "name" }, String(value)),
              unsupported: ({ field }: { field: { component: string } }) =>
                h("p", { "data-unsupported": "" }, field.component),
            },
          ),
      },
    });

    expect(wrapper.get('[data-field="name"]').text()).toBe("Ada");
    expect(wrapper.get("[data-unsupported]").text()).toBe("CustomWidget");
    expect(wrapper.find('input[name="name"]').exists()).toBe(false);
    expect(wrapper.find("[controller]").exists()).toBe(false);
    expect(wrapper.find("[context]").exists()).toBe(false);
  });
});
