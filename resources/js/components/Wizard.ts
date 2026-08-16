import { defineComponent, ref, toRef, type PropType } from "vue";
import { provideFormContext } from "../context";
import { useForm } from "../useForm";
import { useFormWizard } from "../useFormWizard";
import { renderFormElement } from "./formElement";
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
    const formElement = ref<HTMLFormElement | null>(null);
    provideFormContext(form);
    expose({ form, wizard, element: formElement });

    return () =>
      renderFormElement(
        form,
        formElement,
        slots.default?.({ form, wizard }) ?? null,
      );
  },
});
