import { inject, provide, type InjectionKey } from "vue";
import type { FormDataValues, UseFormApi } from "./types";

export const FormContextKey: InjectionKey<UseFormApi> = Symbol(
  "InertifyFormContext",
);

export function provideFormContext<TData extends FormDataValues>(
  form: UseFormApi<TData>,
): void {
  provide(FormContextKey, form as UseFormApi);
}

export function tryUseFormContext<
  TData extends FormDataValues = FormDataValues,
>(): UseFormApi<TData> | null {
  return inject(FormContextKey, null) as UseFormApi<TData> | null;
}

export function useFormContext<
  TData extends FormDataValues = FormDataValues,
>(): UseFormApi<TData> {
  const context = tryUseFormContext<TData>();

  if (!context) {
    throw new Error(
      "useFormContext must be used inside <Form>, <Wizard>, or <FormProvider>.",
    );
  }

  return context;
}
