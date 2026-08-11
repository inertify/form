import { defineComponent, type PropType } from "vue";
import { tryUseFormContext } from "../context";
import { useFormCollections } from "../useFormCollections";
import type { UseFormApi } from "../types";

export default defineComponent({
  name: "HeadlessFormCollection",
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
        "HeadlessFormCollection requires a `form` prop or form provider context.",
      );
    }

    const collection = useFormCollections(form).forField(props.name);

    return () =>
      slots.default?.({
        form,
        collection,
        name: props.name,
        items: collection.items.value,
        keys: collection.keys.value,
        field: collection.field.value,
        canAppend: collection.canAppend.value,
        append: collection.append,
        prepend: collection.prepend,
        insert: collection.insert,
        update: collection.update,
        remove: collection.remove,
        move: collection.move,
        swap: collection.swap,
        duplicate: collection.duplicate,
        appendBlock: collection.appendBlock,
        clear: collection.clear,
      }) ?? null;
  },
});
