import { defineComponent, type PropType } from "vue";
import { tryUseFormContext } from "../context";
import { useFormWizard } from "../useFormWizard";
import type { UseFormApi } from "../types";

export default defineComponent({
  name: "HeadlessFormWizard",
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
        "HeadlessFormWizard requires a `form` prop or form provider context.",
      );
    }

    const wizard = useFormWizard(form);

    return () =>
      slots.default?.({
        form,
        wizard,
        enabled: wizard.enabled.value,
        steps: wizard.steps.value,
        current: wizard.current.value,
        currentIndex: wizard.currentIndex.value,
        completed: wizard.completed,
        isFirst: wizard.isFirst.value,
        isLast: wizard.isLast.value,
        canPrevious: wizard.canPrevious.value,
        canNext: wizard.canNext.value,
        labels: wizard.labels.value,
        goTo: wizard.goTo,
        next: wizard.next,
        previous: wizard.previous,
        validateCurrent: wizard.validateCurrent,
        reset: wizard.reset,
      }) ?? null;
  },
});
