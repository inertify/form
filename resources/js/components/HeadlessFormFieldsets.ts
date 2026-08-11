import { defineComponent, type PropType } from "vue";
import { tryUseFormContext } from "../context";
import { fieldsForFieldset, fieldsetId } from "../internal/resource";
import type { UseFormApi } from "../types";

export default defineComponent({
  name: "HeadlessFormFieldsets",
  props: {
    form: {
      type: Object as PropType<UseFormApi>,
      default: undefined,
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
        "HeadlessFormFieldsets requires a `form` prop or form provider context.",
      );
    }

    return () =>
      form.fieldsets.value
        .map((fieldset, originalIndex) => ({ fieldset, originalIndex }))
        .filter(
          ({ fieldset }) =>
            props.includeHidden || form.isFieldsetVisible(fieldset),
        )
        .flatMap(({ fieldset, originalIndex }, index) => {
          const id = fieldsetId(fieldset, originalIndex);
          const payload = {
            fieldset,
            form,
            context: form,
            id,
            index,
            originalIndex,
            fields: fieldsForFieldset(form.fields.value, originalIndex).filter(
              (field) => props.includeHidden || form.isVisible(field),
            ),
            visible: form.isFieldsetVisible(fieldset),
          };
          const before = slots[`before-${id}-fieldset`]?.(payload) ?? [];
          const replacement =
            slots[`${id}-fieldset`]?.(payload) ??
            slots[`fieldset-${id}`]?.(payload) ??
            slots.default?.(payload) ??
            [];
          const after = slots[`after-${id}-fieldset`]?.(payload) ?? [];

          return [...before, ...replacement, ...after];
        });
  },
});
