import { defineComponent, type PropType } from "vue";
import { tryUseFormContext } from "../context";
import { useFormUploads } from "../useFormUploads";
import type { UseFormApi } from "../types";

export default defineComponent({
  name: "HeadlessFormUploads",
  props: {
    form: {
      type: Object as PropType<UseFormApi>,
      default: undefined,
    },
    name: {
      type: String,
      required: true,
    },
  },
  setup(props, { slots }) {
    const form = props.form ?? tryUseFormContext();

    if (!form) {
      throw new Error(
        "HeadlessFormUploads requires a `form` prop or form provider context.",
      );
    }

    const uploads = useFormUploads(form);

    return () =>
      slots.default?.({
        form,
        uploads,
        name: props.name,
        state: uploads.state(props.name),
        value: form.getValue(props.name),
        upload: (files: File | File[] | FileList) =>
          uploads.upload(props.name, files),
        remove: (indexOrToken?: number | string) =>
          uploads.remove(props.name, indexOrToken),
        reorder: (from: number, to: number) =>
          uploads.reorder(props.name, from, to),
        cancel: () => uploads.cancel(props.name),
        retry: () => uploads.retry(props.name),
        pause: () => uploads.pause(props.name),
        resume: () => uploads.resume(props.name),
        clear: () => uploads.clear(props.name),
      }) ?? null;
  },
});
