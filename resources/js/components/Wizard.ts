import { defineComponent, toRef, type PropType } from "vue";
import { provideFormContext } from "../context";
import { useForm } from "../useForm";
import { useFormWizard } from "../useFormWizard";
import type { FormResource, UseFormOptions } from "../types";

export default defineComponent({
  name: "Wizard",
  props: {
    form: {
      type: Object as PropType<FormResource>,
      required: true,
    },
    options: {
      type: Object as PropType<UseFormOptions>,
      default: () => ({}),
    },
  },
  setup(props, { slots, expose }) {
    const form = useForm(toRef(props, "form"), props.options);
    const wizard = useFormWizard(form);
    provideFormContext(form);
    expose({ form, wizard });

    return () => slots.default?.({ form, wizard }) ?? null;
  },
});
