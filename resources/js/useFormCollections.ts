import { computed, ref, watch } from "vue";
import { useFormContext } from "./context";
import { deepClone, setPath } from "./internal/path";
import type {
  FormBlockSet,
  FormField,
  FormFieldset,
  UseFormApi,
  UseFormCollectionApi,
  UseFormCollectionsApi,
} from "./types";

const cache = new WeakMap<object, UseFormCollectionsApi>();
let collectionKey = 0;

export function useFormCollection(
  path: string,
  formApi?: UseFormApi,
): UseFormCollectionApi {
  const form = formApi ?? useFormContext();
  const field = computed(() => form.resolveField(path));
  const items = computed<unknown[]>(() => {
    const value = form.getValue(path);

    return Array.isArray(value) ? value : [];
  });
  const keys = ref<string[]>(items.value.map(() => nextKey()));
  const canAppend = computed(() => {
    const configuredMaximum = field.value?.maxItems;

    if (configuredMaximum === null || configuredMaximum === undefined) {
      return true;
    }

    const maximum = Number(configuredMaximum);

    return !Number.isFinite(maximum) || maximum < 0 || items.value.length < maximum;
  });

  function commit(next: unknown[], nextKeys = keys.value): void {
    keys.value = [...nextKeys];
    form.setValue(path, next);
  }

  function insert(index: number, value?: unknown): void {
    if (!canAppend.value) {
      return;
    }

    const next = [...items.value];
    const nextKeys = [...keys.value];
    const target = Math.max(0, Math.min(Math.floor(index), next.length));
    const item = value === undefined ? newCollectionItem(field.value) : value;
    next.splice(target, 0, deepClone(item));
    nextKeys.splice(target, 0, nextKey());
    commit(next, nextKeys);
  }

  function update(index: number, value: unknown): void {
    if (index < 0 || index >= items.value.length) {
      return;
    }

    const next = [...items.value];
    next[index] = value;
    commit(next);
  }

  function remove(index: number): unknown {
    if (index < 0 || index >= items.value.length) {
      return undefined;
    }

    const next = [...items.value];
    const nextKeys = [...keys.value];
    const [removed] = next.splice(index, 1);
    nextKeys.splice(index, 1);
    commit(next, nextKeys);

    return removed;
  }

  function move(from: number, to: number): void {
    if (
      from < 0 ||
      from >= items.value.length ||
      to < 0 ||
      to >= items.value.length ||
      from === to
    ) {
      return;
    }

    const next = [...items.value];
    const nextKeys = [...keys.value];
    const [item] = next.splice(from, 1);
    const [key] = nextKeys.splice(from, 1);

    if (item !== undefined && key !== undefined) {
      next.splice(to, 0, item);
      nextKeys.splice(to, 0, key);
      commit(next, nextKeys);
    }
  }

  function swap(first: number, second: number): void {
    if (
      first < 0 ||
      second < 0 ||
      first >= items.value.length ||
      second >= items.value.length ||
      first === second
    ) {
      return;
    }

    const next = [...items.value];
    const nextKeys = [...keys.value];
    [next[first], next[second]] = [next[second], next[first]];
    [nextKeys[first], nextKeys[second]] = [
      nextKeys[second] as string,
      nextKeys[first] as string,
    ];
    commit(next, nextKeys);
  }

  watch(
    () => items.value.length,
    (length) => {
      if (keys.value.length > length) {
        keys.value = keys.value.slice(0, length);
      }

      while (keys.value.length < length) {
        keys.value.push(nextKey());
      }
    },
  );

  return {
    path,
    field,
    items,
    keys: computed(() => keys.value),
    canAppend,
    append: (value?: unknown) => insert(items.value.length, value),
    prepend: (value?: unknown) => insert(0, value),
    insert,
    update,
    remove,
    move,
    swap,
    duplicate: (index: number) => {
      if (index >= 0 && index < items.value.length) {
        insert(index + 1, deepClone(items.value[index]));
      }
    },
    appendBlock: (type: string) => {
      const currentField = field.value;
      const set = blockSets(currentField).find(
        (candidate) => (candidate.type ?? candidate.name) === type,
      );

      if (!set || !canAppend.value || !canAppendBlock(items.value, set, type)) {
        return false;
      }

      const data = isRecord(set.defaultData)
        ? deepClone(set.defaultData)
        : synthesizeSchemaRow(blockSchema(set));
      insert(items.value.length, { type, data });

      return true;
    },
    clear: () => commit([], []),
  };
}

export function useFormCollections(
  formApi?: UseFormApi,
): UseFormCollectionsApi {
  const form = formApi ?? useFormContext();
  const existing = cache.get(form);

  if (existing) {
    return existing;
  }

  const collections = new Map<string, UseFormCollectionApi>();
  const api: UseFormCollectionsApi = {
    forField(path: string): UseFormCollectionApi {
      const collection = collections.get(path) ?? useFormCollection(path, form);
      collections.set(path, collection);

      return collection;
    },
  };
  cache.set(form, api);

  return api;
}

function nextKey(): string {
  collectionKey += 1;

  return `form-item-${collectionKey}`;
}

function newCollectionItem(field: FormField | null): unknown {
  if (field && field.defaultItem !== undefined) {
    return deepClone(field.defaultItem);
  }

  return synthesizeSchemaRow(fieldSchema(field));
}

function synthesizeSchemaRow(
  schema: Array<FormField | FormFieldset>,
): Record<string, unknown> {
  const row: Record<string, unknown> = {};

  for (const item of schema) {
    if (isFieldset(item)) {
      Object.assign(row, synthesizeSchemaRow(item.fields));
      continue;
    }

    const name = item.name || item.attribute || "";

    if (name !== "" && !name.startsWith("$.")) {
      setPath(row, name, null);
    }
  }

  return row;
}

function fieldSchema(field: FormField | null): Array<FormField | FormFieldset> {
  if (!field) {
    return [];
  }

  for (const key of ["schema", "fields", "children"] as const) {
    if (Array.isArray(field[key])) {
      return field[key];
    }
  }

  return [];
}

function blockSets(field: FormField | null): FormBlockSet[] {
  if (!field) {
    return [];
  }

  return Array.isArray(field.sets)
    ? field.sets
    : Array.isArray(field.blocks)
      ? field.blocks
      : [];
}

function blockSchema(set: FormBlockSet): Array<FormField | FormFieldset> {
  return Array.isArray(set.schema)
    ? set.schema
    : Array.isArray(set.fields)
      ? set.fields
      : [];
}

function canAppendBlock(
  items: unknown[],
  set: FormBlockSet,
  type: string,
): boolean {
  if (set.maxItems === null || set.maxItems === undefined) {
    return true;
  }

  const maximum = Number(set.maxItems);

  if (!Number.isFinite(maximum) || maximum < 0) {
    return true;
  }

  return items.filter(
    (item) => isRecord(item) && (item.type ?? item.name) === type,
  ).length < maximum;
}

function isFieldset(
  value: FormField | FormFieldset,
): value is FormFieldset {
  return !("component" in value) && Array.isArray(value.fields);
}

function isRecord(value: unknown): value is Record<string, unknown> {
  return typeof value === "object" && value !== null && !Array.isArray(value);
}
