import type {
  FormDataValues,
  FormField,
  FormFieldset,
  FormResource,
} from "../resources/js/types";

export function makeField(
  name: string,
  component = "TextInput",
  overrides: Partial<FormField> = {},
): FormField {
  return {
    name,
    component,
    label: name,
    help: null,
    placeholder: null,
    default: null,
    rules: [],
    precognitive: false,
    disabled: false,
    readonly: false,
    autofocus: false,
    modelBinding: false,
    clearWhenHidden: false,
    dataAttributes: null,
    meta: null,
    ...overrides,
  };
}

export function makeFieldset(
  fields: Array<FormField | FormFieldset>,
  overrides: Partial<FormFieldset> = {},
): FormFieldset {
  return {
    id: "main",
    legend: "Main",
    description: null,
    fields,
    dataAttributes: null,
    meta: null,
    ...overrides,
  };
}

export function makeResource<TData extends FormDataValues>(
  data: TData,
  fields: Array<FormField | FormFieldset>,
  overrides: Partial<FormResource<TData>> = {},
): FormResource<TData> {
  return {
    action: "/submit",
    method: "POST",
    fieldsets: [makeFieldset(fields)],
    data,
    dataAttributes: null,
    meta: null,
    unsavedWarning: false,
    scrollToFirstError: false,
    wizard: null,
    ...overrides,
  };
}
