import { defineComponent, type PropType } from "vue";
import { tryUseFormContext } from "../context";
import type { UseFormApi } from "../types";

export default defineComponent({
  name: "FormErrors",
  props: {
    form: {
      type: Object as PropType<UseFormApi>,
      default: undefined,
    },
  },
  setup(props, { slots }) {
    const form = props.form ?? tryUseFormContext();

    if (!form) {
      throw new Error(
        "FormErrors requires a `form` prop or form provider context.",
      );
    }

    return () => {
      const entries = Object.entries(form.errors.value).map(([name, message]) => ({
        name,
        message,
      }));

      return (
        slots.default?.({
          form,
          errors: form.errors.value,
          entries,
          hasErrors: entries.length > 0,
          firstErrorPath: form.firstErrorPath.value,
          clearErrors: form.clearErrors,
        }) ?? null
      );
    };
  },
});
