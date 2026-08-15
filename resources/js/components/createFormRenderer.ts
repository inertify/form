import {
  defineComponent,
  h,
  type Component,
  type PropType,
  type Slots,
  type VNodeChild,
} from "vue";
import { tryUseFormContext } from "../context";
import type {
  CreateFormRendererOptions,
  FormField,
  FormFieldRenderer,
  FormFieldRendererDefinition,
  FormFieldset,
  FormFieldSlotProps,
  UseFormApi,
} from "../types";
import FormFieldIterator from "./FormFieldIterator";
import FormFieldsIterator from "./FormFieldsIterator";

interface ResolvedFormFieldRendererDefinition {
  component: Component;
  props?: Readonly<Record<string, unknown>>;
}

export function createFormRenderer(options: CreateFormRendererOptions = {}) {
  const renderers = new Map(
    Object.entries(options.renderers ?? {}).map(([component, renderer]) => [
      component,
      snapshotRenderer(renderer),
    ]),
  );
  const fallback = snapshotRenderer(options.fallback);
  const name = options.name?.trim() || "RegisteredForm";

  function renderField(
    payload: FormFieldSlotProps,
    slots: Slots,
  ): VNodeChild {
    const renderer = renderers.get(payload.field.component);

    if (renderer === null) {
      return null;
    }

    if (renderer === undefined) {
      if (slots.unsupported) {
        return slots.unsupported(payload);
      }

      return renderComponent(fallback, payload);
    }

    return renderComponent(renderer, payload);
  }

  function rendererSlots(slots: Slots) {
    const forwarded = Object.fromEntries(
      Object.entries(slots).filter(
        ([slot]) => slot !== "default" && slot !== "unsupported",
      ),
    );

    return {
      ...forwarded,
      default: (payload: FormFieldSlotProps) =>
        slots.default?.(payload) ??
        slots[`${payload.name}-field`]?.(payload) ??
        renderField(payload, slots),
    };
  }

  const FormFields = defineComponent({
    name: `${name}Fields`,
    inheritAttrs: false,
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
      const context = props.form ?? tryUseFormContext();

      if (!context) {
        throw new Error(
          "Registered FormFields requires a `form` prop or form provider context.",
        );
      }

      const form: UseFormApi = context;

      function fields(): FormField[] {
        if (props.fields !== undefined) {
          return props.fields;
        }

        const fieldsets =
          props.fieldset === undefined
            ? form.fieldsets.value
            : [
                typeof props.fieldset === "string"
                  ? form.resolveFieldset(props.fieldset)
                  : props.fieldset,
              ].filter((fieldset): fieldset is FormFieldset => fieldset !== null);

        return fieldsets.flatMap((fieldset) =>
          declaredRendererFields(fieldset.fields),
        );
      }

      return () =>
        h(
          FormFieldsIterator,
          {
            form,
            fields: fields(),
            includeHidden: props.includeHidden,
          },
          rendererSlots(slots),
        );
    },
  });

  const FormField = defineComponent({
    name: `${name}Field`,
    inheritAttrs: false,
    props: {
      name: {
        type: [Object, String] as PropType<FormField | string>,
        required: true,
      },
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
      const context = props.form ?? tryUseFormContext();

      if (!context) {
        throw new Error(
          "Registered FormField requires a `form` prop or form provider context.",
        );
      }

      const form: UseFormApi = context;

      return () =>
        h(
          FormFieldIterator,
          {
            field: props.name,
            form,
            includeHidden: props.includeHidden,
          },
          rendererSlots(slots),
        );
    },
  });

  return { FormField, FormFields };
}

function renderComponent(
  definition: ResolvedFormFieldRendererDefinition | null | undefined,
  payload: FormFieldSlotProps,
): VNodeChild {
  if (!definition) {
    return null;
  }

  return h(definition.component, {
    ...(definition.props ?? {}),
    ...payload,
    key: payload.name,
  });
}

function snapshotRenderer(
  renderer: FormFieldRenderer | undefined,
): ResolvedFormFieldRendererDefinition | null {
  if (renderer === undefined || renderer === null) {
    return null;
  }

  if (
    typeof renderer === "object" &&
    Object.prototype.hasOwnProperty.call(renderer, "component")
  ) {
    const definition = renderer as FormFieldRendererDefinition;

    if (definition.component === null) {
      return null;
    }

    return {
      component: definition.component,
      ...(definition.props === undefined
        ? {}
        : { props: { ...definition.props } }),
    };
  }

  return { component: renderer };
}

function declaredRendererFields(
  candidates: Array<FormField | FormFieldset>,
): FormField[] {
  return candidates.flatMap((candidate) =>
    isRendererField(candidate)
      ? [candidate]
      : declaredRendererFields(candidate.fields),
  );
}

function isRendererField(
  candidate: FormField | FormFieldset,
): candidate is FormField {
  return (
    typeof candidate.component === "string" &&
    (typeof candidate.name === "string" ||
      typeof candidate.attribute === "string")
  );
}
