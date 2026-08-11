import { defineComponent, type PropType } from "vue";
import { tryUseFormContext } from "../context";
import { useFormSubmission } from "../useFormSubmission";
import type { UseFormApi } from "../types";

export default defineComponent({
  name: "HeadlessFormSubmit",
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
        "HeadlessFormSubmit requires a `form` prop or form provider context.",
      );
    }

    const submission = useFormSubmission(form);

    return () =>
      slots.default?.({
        form,
        processing: submission.processing.value,
        progress: submission.progress.value,
        wasSuccessful: submission.wasSuccessful.value,
        recentlySuccessful: submission.recentlySuccessful.value,
        hasErrors: submission.hasErrors.value,
        firstErrorPath: submission.firstErrorPath.value,
        canSubmit: submission.canSubmit.value,
        submit: submission.submit,
        cancel: submission.cancel,
      }) ?? null;
  },
});
