import { h, type Ref, type VNode } from "vue";
import type { FormMethod, NormalizedFormMethod, UseFormApi } from "../types";

const SPOOFED_METHODS: NormalizedFormMethod[] = ["put", "patch", "delete"];

/**
 * Renders the native form element for the components that own one. Attributes
 * fall through from the consumer, so `id`, `method`, and `novalidate` can be
 * overridden per usage. Use FormProvider when no element should be emitted.
 */
export function renderFormElement(
  form: UseFormApi,
  element: Ref<HTMLFormElement | null>,
  children: VNode[] | null,
): VNode {
  const method = normalizeMethod(form.resource.value.method);
  const action = form.resource.value.action;

  return h(
    "form",
    {
      ref: element,
      id: form.formId.value,
      ...(action === null ? {} : { action }),
      method: method === "get" ? "get" : "post",
      novalidate: true,
      onSubmit: (event: SubmitEvent) => {
        event.preventDefault();

        form.submit({ submitter: submitterOf(event) });
      },
    },
    SPOOFED_METHODS.includes(method)
      ? [methodField(method), ...(children ?? [])]
      : (children ?? []),
  );
}

function methodField(method: NormalizedFormMethod): VNode {
  return h("input", {
    type: "hidden",
    name: "_method",
    value: method.toUpperCase(),
  });
}

function submitterOf(
  event: SubmitEvent,
): HTMLButtonElement | HTMLInputElement | null {
  const submitter = event.submitter;

  return submitter instanceof HTMLButtonElement ||
    submitter instanceof HTMLInputElement
    ? submitter
    : null;
}

function normalizeMethod(method: FormMethod): NormalizedFormMethod {
  return method.toLowerCase() as NormalizedFormMethod;
}
