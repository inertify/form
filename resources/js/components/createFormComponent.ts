import { defineComponent, toRef, type PropType } from "vue";
import { provideFormContext } from "../context";
import { useForm } from "../useForm";
import type { FormResource, UseFormOptions } from "../types";

export function createFormComponent(name: string) {
  return defineComponent({
    name,
    emits: [
      "before",
      "start",
      "progress",
      "success",
      "error",
      "cancel",
      "finish",
    ],
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
    setup(props, { slots, expose, emit }) {
      const configuredSubmit = props.options.submit ?? {};
      const options: UseFormOptions = {
        ...props.options,
        submit: {
          ...configuredSubmit,
          onBefore: (visit) => {
            emit("before", visit);

            return configuredSubmit.onBefore?.(visit);
          },
          onStart: (visit) => {
            emit("start", visit);
            configuredSubmit.onStart?.(visit);
          },
          onProgress: (progress) => {
            emit("progress", progress);
            configuredSubmit.onProgress?.(progress);
          },
          onSuccess: (page) => {
            emit("success", page);
            configuredSubmit.onSuccess?.(page);
          },
          onError: (errors) => {
            emit("error", errors);
            configuredSubmit.onError?.(errors);
          },
          onCancel: () => {
            emit("cancel");
            configuredSubmit.onCancel?.();
          },
          onFinish: (visit) => {
            emit("finish", visit);
            configuredSubmit.onFinish?.(visit);
          },
        },
      };
      const api = useForm(toRef(props, "form"), options);
      provideFormContext(api);
      expose(api);

      return () =>
        slots.default?.({
          form: api,
          formId: api.formId.value,
          data: api.data.value,
          rootData: api.rootData.value,
          errors: api.errors.value,
          rootErrors: api.rootErrors.value,
          processing: api.processing.value,
          isDirty: api.isDirty.value,
          setData: api.setData,
          validate: api.validate,
          submit: api.submit,
          cancel: api.cancel,
          reset: api.reset,
          defaults: api.defaults,
          transform: api.transform,
          clearErrors: api.clearErrors,
        }) ?? null;
    },
  });
}
