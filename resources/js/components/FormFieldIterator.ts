import { defineComponent, h, type PropType } from "vue";
import { tryUseFormContext } from "../context";
import FormFieldsIterator from "./FormFieldsIterator";
import type { FormField, UseFormApi } from "../types";

export default defineComponent({
  name: "FormFieldIterator",
  props: {
    form: {
      type: Object as PropType<UseFormApi>,
      default: undefined,
    },
    field: {
      type: [Object, String] as PropType<FormField | string>,
      required: true,
    },
    includeHidden: {
      type: Boolean,
      default: false,
    },
  },
  setup(props, { slots }) {
    const form = props.form ?? tryUseFormContext();

    if (!form) {
      throw new Error(
        "FormFieldIterator requires a `form` prop or form provider context.",
      );
    }

    return () => {
      const field =
        typeof props.field === "string"
          ? form.resolveField(props.field)
          : props.field;

      if (!field) {
        return null;
      }

      return h(
        FormFieldsIterator,
        {
          form,
          fields: [field],
          includeHidden: props.includeHidden,
        },
        slots,
      );
    };
  },
});
