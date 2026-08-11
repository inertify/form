import { getPath, hasPath, isBlank } from "./path";
import type {
  FormDataValues,
  FormVisibilityClause,
  FormVisibilityCondition,
  FormVisibilityGroup,
} from "../types";

export function evaluateVisibility(
  condition: FormVisibilityCondition | undefined,
  rootData: FormDataValues,
  rowData?: FormDataValues,
): boolean {
  if (condition === undefined || condition === null) {
    return true;
  }

  if (typeof condition === "boolean") {
    return condition;
  }

  if (Array.isArray(condition)) {
    return condition.every((item) =>
      evaluateVisibility(item, rootData, rowData),
    );
  }

  if (typeof condition !== "object") {
    return Boolean(condition);
  }

  if ("conditions" in condition && Array.isArray(condition.conditions)) {
    return evaluateGroup(condition as FormVisibilityGroup, rootData, rowData);
  }

  if ("field" in condition || "attribute" in condition) {
    return evaluateClause(condition as FormVisibilityClause, rootData, rowData);
  }

  return Object.entries(condition).every(([path, expected]) =>
    equalValues(resolveConditionValue(path, rootData, rowData), expected),
  );
}

function evaluateGroup(
  group: FormVisibilityGroup,
  rootData: FormDataValues,
  rowData?: FormDataValues,
): boolean {
  const conditions = group.conditions ?? [];
  const mode = group.mode ?? "all";

  if (mode === "any" || mode === "or") {
    return conditions.some((condition) =>
      evaluateVisibility(condition, rootData, rowData),
    );
  }

  if (mode === "not") {
    return !conditions.every((condition) =>
      evaluateVisibility(condition, rootData, rowData),
    );
  }

  return conditions.every((condition) =>
    evaluateVisibility(condition, rootData, rowData),
  );
}

function evaluateClause(
  clause: FormVisibilityClause,
  rootData: FormDataValues,
  rowData?: FormDataValues,
): boolean {
  const path = clause.field ?? clause.attribute;

  if (!path) {
    return true;
  }

  const actual = resolveConditionValue(path, rootData, rowData);
  const expected = clause.value;

  switch (clause.operator ?? "is") {
    case "=":
    case "is":
    case "equals":
      return equalValues(actual, expected);
    case "!=":
    case "is_not":
    case "not_equals":
      return !equalValues(actual, expected);
    case "contains":
      return containsValue(actual, expected);
    case "not_contains":
      return !containsValue(actual, expected);
    case "in":
      return Array.isArray(expected)
        ? expected.some((item) => equalValues(actual, item))
        : containsValue(expected, actual);
    case "not_in":
      return Array.isArray(expected)
        ? !expected.some((item) => equalValues(actual, item))
        : !containsValue(expected, actual);
    case ">":
    case "greater_than":
      return compareValues(actual, expected) > 0;
    case ">=":
    case "greater_than_or_equal":
      return compareValues(actual, expected) >= 0;
    case "<":
    case "less_than":
      return compareValues(actual, expected) < 0;
    case "<=":
    case "less_than_or_equal":
      return compareValues(actual, expected) <= 0;
    case "filled":
    case "not_empty":
      return !isBlank(actual);
    case "blank":
    case "empty":
      return isBlank(actual);
    case "truthy":
      return Boolean(actual);
    case "falsy":
      return !actual;
    case "starts_with":
      return String(actual ?? "").startsWith(String(expected ?? ""));
    case "ends_with":
      return String(actual ?? "").endsWith(String(expected ?? ""));
    default:
      return equalValues(actual, expected);
  }
}

function resolveConditionValue(
  path: string,
  rootData: FormDataValues,
  rowData?: FormDataValues,
): unknown {
  if (path.startsWith("$.")) {
    return getPath(rootData, path.slice(2));
  }

  if (rowData && hasPath(rowData, path)) {
    return getPath(rowData, path);
  }

  return getPath(rootData, path);
}

function equalValues(left: unknown, right: unknown): boolean {
  if (Object.is(left, right)) {
    return true;
  }

  if (typeof left === "boolean") {
    return left === normalizeBoolean(right);
  }

  if (typeof right === "boolean") {
    return normalizeBoolean(left) === right;
  }

  if (typeof left === "number" || typeof right === "number") {
    const leftNumber = Number(left);
    const rightNumber = Number(right);

    return (
      Number.isFinite(leftNumber) &&
      Number.isFinite(rightNumber) &&
      leftNumber === rightNumber
    );
  }

  return String(left ?? "") === String(right ?? "");
}

function normalizeBoolean(value: unknown): boolean | unknown {
  if (value === "true" || value === 1 || value === "1") {
    return true;
  }

  if (value === "false" || value === 0 || value === "0") {
    return false;
  }

  return value;
}

function containsValue(haystack: unknown, needle: unknown): boolean {
  if (Array.isArray(haystack)) {
    return haystack.some((item) => equalValues(item, needle));
  }

  if (typeof haystack === "string") {
    return haystack.toLowerCase().includes(String(needle ?? "").toLowerCase());
  }

  return false;
}

function compareValues(left: unknown, right: unknown): number {
  const leftNumber = Number(left);
  const rightNumber = Number(right);

  if (Number.isFinite(leftNumber) && Number.isFinite(rightNumber)) {
    return leftNumber - rightNumber;
  }

  return Number.NaN;
}
