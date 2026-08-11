import { defineComponent, type PropType } from "vue";
import { tryUseFormContext } from "../context";
import {
  fieldPath,
  fieldsForFieldset,
  normalizedSlotToken,
} from "../internal/resource";
import { useFormFields } from "../useFormFields";
import type {
  FormField,
  FormFieldset,
  UseFormApi,
} from "../types";

export default defineComponent({
  name: "HeadlessFormFields",
  props: {
    form: {
      type: Object as PropType<UseFormApi>,
      default: undefined,
    },
    fields: {
      type: Array as PropType<FormField[]>,
      default: undefined,
    },
    fieldset: {
      type: [Object, String] as PropType<FormFieldset | string>,
      default: undefined,
    },
    includeHidden: {
      type: Boolean,
      default: false,
    },
  },
  setup(props, { slots }) {
    const resolvedForm = props.form ?? tryUseFormContext();

    if (!resolvedForm) {
      throw new Error(
        "HeadlessFormFields requires a `form` prop or form provider context.",
      );
    }

    const form = resolvedForm;
    const fieldsApi = useFormFields(form);

    function candidates(): FormField[] {
      if (props.fields) {
        return props.fields.flatMap((field) => {
          if ("path" in field && typeof field.path === "string") {
            return [field];
          }

          const instances = form.fields.value.filter(
            (candidate) => candidate.schemaField === field,
          );

          return instances.length > 0
            ? instances
            : [form.resolveField(fieldPath(field)) ?? field];
        });
      }

      if (props.fieldset) {
        const fieldset =
          typeof props.fieldset === "string"
            ? form.resolveFieldset(props.fieldset)
            : props.fieldset;

        const index = fieldset ? form.fieldsets.value.indexOf(fieldset) : -1;

        return index >= 0 ? fieldsForFieldset(form.fields.value, index) : [];
      }

      return form.fields.value;
    }

    return () =>
      candidates()
        .filter((field) => props.includeHidden || form.isVisible(field))
        .flatMap((field) => {
          const name = fieldPath(field);
          const controller = fieldsApi.controller(field);
          const type = normalizedSlotToken(field.component);
          const payload = {
            field,
            form,
            context: form,
            controller,
            name,
            value: controller.value.value,
            error: controller.error.value,
            errors: controller.errors.value,
            visible: controller.visible.value,
            touched: controller.touched.value,
            dirty: controller.dirty.value,
            disabled: controller.disabled.value,
            readonly: controller.readonly.value,
            required: controller.required.value,
            setValue: controller.setValue,
            blur: controller.blur,
            validate: controller.validate,
            registerElement: controller.registerElement,
          };
          const before = slots[`before-${name}-field`]?.(payload) ?? [];
          const replacement =
            slots[`field-${name}`]?.(payload) ??
            (type ? slots[`type-${type}`]?.(payload) : undefined) ??
            slots.default?.(payload) ??
            slots[`${name}-field`]?.(payload) ??
            [];
          const after = slots[`after-${name}-field`]?.(payload) ?? [];

          return [...before, ...replacement, ...after];
        });
  },
});
