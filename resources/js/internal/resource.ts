import { getPath } from "./path";
import type {
  FormBlockSet,
  FormDataValues,
  FormField,
  FormFieldInstance,
  FormFieldset,
  FormVisibilityCondition,
} from "../types";

type SchemaItem = FormField | FormFieldset;

interface ResolveContext {
  data: FormDataValues;
  prefix: string;
  rowPath: string | null;
  fieldsetIndex: number;
  ancestorVisibility: FormVisibilityCondition[];
  ancestorClearWhenHidden: boolean;
}

/**
 * Flattens the declared schema once. For concrete collection row paths use
 * resolveFormFields(), which expands repeater and block schemas against data.
 */
export function flattenFields(fieldsets: FormFieldset[]): FormField[] {
  const fields: FormField[] = [];

  for (const fieldset of fieldsets) {
    appendDeclaredFields(fields, fieldset.fields);
  }

  return fields;
}

/** Resolve every declared field to an unambiguous path in the current data. */
export function resolveFormFields(
  fieldsets: FormFieldset[],
  data: FormDataValues,
): FormFieldInstance[] {
  const output: FormFieldInstance[] = [];

  fieldsets.forEach((fieldset, fieldsetIndex) => {
    appendResolvedItems(output, fieldset.fields, {
      data,
      prefix: "",
      rowPath: null,
      fieldsetIndex,
      ancestorVisibility: [],
      ancestorClearWhenHidden: false,
    });
  });

  return output;
}

export function fieldPath(field: FormField): string {
  if ("path" in field && typeof field.path === "string") {
    return field.path;
  }

  return normalizeRootPath(field.name || field.attribute || "");
}

export function fieldsetId(fieldset: FormFieldset, index: number): string {
  return fieldset.id ?? `fieldset-${index + 1}`;
}

export function fieldsForFieldset(
  fields: FormFieldInstance[],
  fieldsetIndex: number,
): FormFieldInstance[] {
  return fields.filter((field) => field.fieldsetIndex === fieldsetIndex);
}

function appendDeclaredFields(
  output: FormField[],
  candidates: SchemaItem[],
): void {
  for (const candidate of candidates) {
    if (isFormFieldset(candidate)) {
      appendDeclaredFields(output, candidate.fields);
      continue;
    }

    output.push(candidate);

    appendDeclaredFields(output, nestedSchema(candidate));

    for (const block of blockSets(candidate)) {
      appendDeclaredFields(output, blockSchema(block));
    }
  }
}

function appendResolvedItems(
  output: FormFieldInstance[],
  candidates: SchemaItem[],
  context: ResolveContext,
): void {
  for (const candidate of candidates) {
    if (isFormFieldset(candidate)) {
      const visibility = candidate.visibility ?? candidate.conditions;
      appendResolvedItems(output, candidate.fields, {
        ...context,
        ancestorVisibility: [
          ...context.ancestorVisibility,
          ...(candidate.visible === false ? [false] : []),
          ...(visibility === undefined ? [] : [visibility]),
        ],
        ancestorClearWhenHidden:
          context.ancestorClearWhenHidden ||
          candidate.clearWhenHidden === true,
      });
      continue;
    }

    const schemaName = candidate.name || candidate.attribute || "";
    const path = qualifyPath(context.prefix, schemaName);

    if (path === "") {
      continue;
    }

    const instance: FormFieldInstance = {
      ...candidate,
      name: path,
      ...(candidate.attribute
        ? { attribute: qualifyPath(context.prefix, candidate.attribute) }
        : {}),
      path,
      schemaName: normalizeRootPath(schemaName),
      schemaField: candidate,
      rowPath: context.rowPath,
      fieldsetIndex: context.fieldsetIndex,
      ancestorVisibility: [...context.ancestorVisibility],
      ancestorClearWhenHidden: context.ancestorClearWhenHidden,
    };
    output.push(instance);

    const component = componentToken(candidate.component);
    const childVisibility: FormVisibilityCondition[] = [
      ...context.ancestorVisibility,
      ...(candidate.visible === false ? [false] : []),
      ...visibilityConditions(candidate),
    ];
    const childClearWhenHidden =
      context.ancestorClearWhenHidden || candidate.clearWhenHidden === true;

    if (component === "repeater") {
      const rows = getPath(context.data, path);
      const schema = nestedSchema(candidate);

      if (Array.isArray(rows)) {
        rows.forEach((_row, index) => {
          const rowPath = `${path}.${index}`;
          appendResolvedItems(output, schema, {
            data: context.data,
            prefix: rowPath,
            rowPath,
            fieldsetIndex: context.fieldsetIndex,
            ancestorVisibility: childVisibility,
            ancestorClearWhenHidden: childClearWhenHidden,
          });
        });
      }

      continue;
    }

    if (component === "blocks") {
      const rows = getPath(context.data, path);
      const blocks = blockSets(candidate);

      if (Array.isArray(rows)) {
        rows.forEach((row, index) => {
          if (!isRecord(row)) {
            return;
          }

          const type = row.type ?? row.name;
          const block = blocks.find(
            (item) => (item.type ?? item.name) === type,
          );

          if (!block) {
            return;
          }

          const rowPath = `${path}.${index}.data`;
          appendResolvedItems(output, blockSchema(block), {
            data: context.data,
            prefix: rowPath,
            rowPath,
            fieldsetIndex: context.fieldsetIndex,
            ancestorVisibility: childVisibility,
            ancestorClearWhenHidden: childClearWhenHidden,
          });
        });
      }

      continue;
    }

    const schema = nestedSchema(candidate);

    if (schema.length > 0) {
      appendResolvedItems(output, schema, {
        ...context,
        prefix: path,
        ancestorVisibility: childVisibility,
        ancestorClearWhenHidden: childClearWhenHidden,
      });
    }
  }
}

function nestedSchema(field: FormField): SchemaItem[] {
  for (const key of ["schema", "fields", "children"] as const) {
    const candidate = field[key];

    if (Array.isArray(candidate)) {
      return candidate.filter(isSchemaItem);
    }
  }

  return [];
}

function blockSets(field: FormField): FormBlockSet[] {
  const candidates = Array.isArray(field.sets)
    ? field.sets
    : Array.isArray(field.blocks)
      ? field.blocks
      : [];

  return candidates.length > 0
    ? candidates.filter(isFormBlockSet)
    : [];
}

function blockSchema(block: FormBlockSet): SchemaItem[] {
  const candidates = Array.isArray(block.schema)
    ? block.schema
    : Array.isArray(block.fields)
      ? block.fields
      : [];

  return candidates.filter(isSchemaItem);
}

function visibilityConditions(field: FormField): FormVisibilityCondition[] {
  const visibility = field.visibility ?? field.conditions;

  return visibility === undefined ? [] : [visibility];
}

function qualifyPath(prefix: string, name: string): string {
  if (name.startsWith("$.")) {
    return normalizeRootPath(name);
  }

  const normalizedName = normalizeRootPath(name);

  return prefix === "" ? normalizedName : `${prefix}.${normalizedName}`;
}

function normalizeRootPath(path: string): string {
  return path.startsWith("$.") ? path.slice(2) : path;
}

function componentToken(component: unknown): string {
  return normalizedSlotToken(component)?.replace(/-/g, "") ?? "";
}

function isSchemaItem(value: unknown): value is SchemaItem {
  return isFormField(value) || isFormFieldset(value);
}

function isFormField(value: unknown): value is FormField {
  return (
    isRecord(value) &&
    (typeof value.name === "string" || typeof value.attribute === "string") &&
    typeof value.component === "string"
  );
}

function isFormFieldset(value: unknown): value is FormFieldset {
  return isRecord(value) && !isFormField(value) && Array.isArray(value.fields);
}

function isFormBlockSet(value: unknown): value is FormBlockSet {
  return (
    isRecord(value) &&
    (typeof value.type === "string" || typeof value.name === "string") &&
    (Array.isArray(value.schema) || Array.isArray(value.fields))
  );
}

function isRecord(value: unknown): value is Record<string, unknown> {
  return typeof value === "object" && value !== null;
}

export function fieldEmptyValue(field: FormField | null): unknown {
  if (!field) {
    return null;
  }

  const component = componentToken(field.component);

  if (
    ["text", "slug", "textarea", "hidden", "otp"].includes(
      component,
    )
  ) {
    return "";
  }

  if (component === "composer") {
    return field.allowAttachments === true
      ? { text: "", attachments: [] }
      : "";
  }

  if (["checkboxgroup", "repeater", "blocks"].includes(component)) {
    return [];
  }

  if (component === "keyvalue") {
    return field.mode === "single" ? [] : {};
  }

  if (component === "checkbox") {
    return Object.prototype.hasOwnProperty.call(field, "falseValue")
      ? field.falseValue
      : false;
  }

  if (component === "toggle") {
    if (Object.prototype.hasOwnProperty.call(field, "offValue")) {
      return field.offValue;
    }

    return Object.prototype.hasOwnProperty.call(field, "falseValue")
      ? field.falseValue
      : false;
  }

  if (component === "combobox") {
    return field.multiple === true || field.tokens === true ? [] : null;
  }

  if (component === "file") {
    return field.multiple === true ? [] : null;
  }

  if (component === "link") {
    if (field.mode !== "structured") {
      return "";
    }

    return {
      url: "",
      ...(field.withLabel === true ? { label: "" } : {}),
      ...(field.withTarget === true ? { target: "" } : {}),
    };
  }

  if (field.multiple === true) {
    return [];
  }

  return null;
}

export function normalizedSlotToken(value: unknown): string | null {
  if (typeof value !== "string") {
    return null;
  }

  const normalized = value
    .trim()
    .replace(/([a-z0-9])([A-Z])/g, "$1-$2")
    .toLowerCase()
    .replace(/\s+/g, "-")
    .replace(/[^a-z0-9_-]/g, "");

  return normalized.length > 0 ? normalized : null;
}
