import { defineComponent, type PropType } from "vue";
import { tryUseFormContext } from "../context";
import { fieldsForFieldset, fieldsetId } from "../internal/resource";
import type { FormFieldsetIdSelector, UseFormApi } from "../types";

function selectorIds(
  selector: FormFieldsetIdSelector | undefined,
): ReadonlySet<string> | null {
  if (selector === undefined) {
    return null;
  }

  return new Set(typeof selector === "string" ? [selector] : selector);
}

export default defineComponent({
  name: "FormFieldsets",
  props: {
    form: {
      type: Object as PropType<UseFormApi>,
      default: undefined,
    },
    includeHidden: {
      type: Boolean,
      default: false,
    },
    only: {
      type: [String, Array] as PropType<FormFieldsetIdSelector>,
      default: undefined,
    },
    except: {
      type: [String, Array] as PropType<FormFieldsetIdSelector>,
      default: undefined,
    },
  },
  setup(props, { slots }) {
    const form = props.form ?? tryUseFormContext();

    if (!form) {
      throw new Error(
        "FormFieldsets requires a `form` prop or form provider context.",
      );
    }

    return () => {
      const only = selectorIds(props.only);
      const except = selectorIds(props.except);

      return form.fieldsets.value
        .map((fieldset, originalIndex) => ({
          fieldset,
          originalIndex,
          id: fieldsetId(fieldset, originalIndex),
        }))
        .filter(
          ({ id }) =>
            (only === null || only.has(id)) &&
            (except === null || !except.has(id)),
        )
        .map((candidate) => ({
          ...candidate,
          visible: form.isFieldsetVisible(candidate.fieldset),
        }))
        .filter(
          ({ visible }) => props.includeHidden || visible,
        )
        .flatMap(({ fieldset, originalIndex, id, visible }, index) => {
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
            visible,
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
    };
  },
});
