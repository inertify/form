<script setup lang="ts">
import { computed, nextTick, ref, useId } from "vue";

defineOptions({ inheritAttrs: false });

const props = withDefaults(
  defineProps<{
    modelValue?: string | null;
    id?: string;
    name?: string;
    length?: number;
    numeric?: boolean;
    masked?: boolean;
    disabled?: boolean;
    readonly?: boolean;
    required?: boolean;
    autocomplete?: string;
    label?: string;
  }>(),
  {
    modelValue: "",
    length: 6,
    numeric: true,
    masked: false,
    disabled: false,
    readonly: false,
    required: false,
    autocomplete: "one-time-code",
    label: "One-time code",
  },
);

const emit = defineEmits<{
  "update:modelValue": [value: string];
  complete: [value: string];
  blur: [event: FocusEvent];
}>();

const generatedId = useId();
const root = ref<HTMLElement | null>(null);
const inputs = ref<Array<HTMLInputElement | null>>([]);
const value = computed(() => sanitize(props.modelValue ?? ""));
const characters = computed(() =>
  Array.from({ length: props.length }, (_, index) => value.value[index] ?? ""),
);

function sanitize(candidate: string): string {
  const pattern = props.numeric ? /[0-9]/g : /[a-z0-9]/gi;

  return (candidate.match(pattern) ?? []).join("").slice(0, props.length);
}

function setInput(index: number, element: unknown): void {
  inputs.value[index] = element instanceof HTMLInputElement ? element : null;
}

function focus(index: number): void {
  const target = Math.max(0, Math.min(props.length - 1, index));
  void nextTick(() => inputs.value[target]?.focus());
}

function publish(candidate: string): void {
  const normalized = sanitize(candidate);
  emit("update:modelValue", normalized);

  if (normalized.length === props.length) {
    emit("complete", normalized);
  }
}

function replaceAt(index: number, replacement: string): void {
  const next = [...characters.value];
  next[index] = replacement;
  publish(next.join(""));
}

function input(index: number, event: Event): void {
  const target = event.target as HTMLInputElement;
  const entered = sanitize(target.value);

  if (entered.length > 1) {
    const next = [...characters.value];

    for (const [offset, character] of [...entered].entries()) {
      if (index + offset < props.length) {
        next[index + offset] = character;
      }
    }

    publish(next.join(""));
    focus(Math.min(index + entered.length, props.length - 1));

    return;
  }

  replaceAt(index, entered.slice(-1));

  if (entered) {
    focus(index + 1);
  }
}

function keydown(index: number, event: KeyboardEvent): void {
  if (event.key === "Backspace") {
    event.preventDefault();

    if (characters.value[index]) {
      replaceAt(index, "");
    } else if (index > 0) {
      replaceAt(index - 1, "");
      focus(index - 1);
    }

    return;
  }

  if (event.key === "Delete") {
    event.preventDefault();
    replaceAt(index, "");

    return;
  }

  if (event.key === "ArrowLeft") {
    event.preventDefault();
    focus(index - 1);
  } else if (event.key === "ArrowRight") {
    event.preventDefault();
    focus(index + 1);
  }
}

function paste(index: number, event: ClipboardEvent): void {
  event.preventDefault();
  const pasted = sanitize(event.clipboardData?.getData("text") ?? "");

  if (!pasted) {
    return;
  }

  const next = [...characters.value];

  for (const [offset, character] of [...pasted].entries()) {
    if (index + offset < props.length) {
      next[index + offset] = character;
    }
  }

  publish(next.join(""));
  focus(Math.min(index + pasted.length, props.length - 1));
}

function leaveGroup(event: FocusEvent): void {
  const next = event.relatedTarget;

  if (!(next instanceof Node) || !root.value?.contains(next)) {
    emit("blur", event);
  }
}

defineExpose({
  root,
  focus: () => focus(0),
});
</script>

<template>
  <div
    :id="id ?? `otp-${generatedId}`"
    ref="root"
    v-bind="$attrs"
    role="group"
    :aria-label="label"
    class="flex items-center gap-2"
    @focusout="leaveGroup"
  >
    <input v-if="name" type="hidden" :name="name" :value="value" />
    <input
      v-for="(_, index) in characters"
      :id="`${id ?? `otp-${generatedId}`}-${index}`"
      :key="index"
      :ref="(element) => setInput(index, element)"
      :value="characters[index]"
      :type="masked ? 'password' : 'text'"
      :inputmode="numeric ? 'numeric' : 'text'"
      :autocomplete="index === 0 ? autocomplete : 'off'"
      :disabled="disabled"
      :readonly="readonly"
      :required="required"
      maxlength="1"
      :aria-label="`${label}, character ${index + 1} of ${length}`"
      class="size-10 rounded-md border bg-card text-center text-base font-medium shadow-sm outline-none transition focus:ring-2 focus:ring-primary/30 disabled:cursor-not-allowed disabled:opacity-50"
      @input="input(index, $event)"
      @keydown="keydown(index, $event)"
      @paste="paste(index, $event)"
      @focus="($event.target as HTMLInputElement).select()"
    />
  </div>
</template>
