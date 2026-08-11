export function pathSegments(path: string): string[] {
  return path
    .replace(/\[([^[\]]+)\]/g, ".$1")
    .split(".")
    .map((segment) => segment.replace(/^['"]|['"]$/g, "").trim())
    .filter((segment) => segment.length > 0);
}

export function getPath(target: unknown, path: string): unknown {
  let current = target;

  for (const segment of pathSegments(path)) {
    if (typeof current !== "object" || current === null) {
      return undefined;
    }

    current = (current as Record<string, unknown>)[segment];
  }

  return current;
}

export function hasPath(target: unknown, path: string): boolean {
  const segments = pathSegments(path);
  let current = target;

  for (const segment of segments) {
    if (
      typeof current !== "object" ||
      current === null ||
      !Object.prototype.hasOwnProperty.call(current, segment)
    ) {
      return false;
    }

    current = (current as Record<string, unknown>)[segment];
  }

  return segments.length > 0;
}

export function setPath(target: object, path: string, value: unknown): void {
  const segments = pathSegments(path);

  if (segments.length === 0) {
    return;
  }

  let current = target as Record<string, unknown>;

  segments.forEach((segment, index) => {
    if (index === segments.length - 1) {
      current[segment] = value;

      return;
    }

    const nextSegment = segments[index + 1] ?? "";
    const existing = current[segment];

    if (typeof existing !== "object" || existing === null) {
      current[segment] = /^\d+$/.test(nextSegment) ? [] : {};
    }

    current = current[segment] as Record<string, unknown>;
  });
}

export function deepClone<T>(value: T): T {
  if (Array.isArray(value)) {
    return value.map((item) => deepClone(item)) as T;
  }

  if (value instanceof Date) {
    return new Date(value.getTime()) as T;
  }

  if (
    (typeof File !== "undefined" && value instanceof File) ||
    (typeof Blob !== "undefined" && value instanceof Blob)
  ) {
    return value;
  }

  if (typeof value === "object" && value !== null) {
    return Object.fromEntries(
      Object.entries(value).map(([key, item]) => [key, deepClone(item)]),
    ) as T;
  }

  return value;
}

export function deepEqual(left: unknown, right: unknown): boolean {
  if (Object.is(left, right)) {
    return true;
  }

  if (Array.isArray(left) && Array.isArray(right)) {
    return (
      left.length === right.length &&
      left.every((item, index) => deepEqual(item, right[index]))
    );
  }

  if (
    typeof left === "object" &&
    left !== null &&
    typeof right === "object" &&
    right !== null
  ) {
    const leftKeys = Object.keys(left);
    const rightKeys = Object.keys(right);

    return (
      leftKeys.length === rightKeys.length &&
      leftKeys.every(
        (key) =>
          Object.prototype.hasOwnProperty.call(right, key) &&
          deepEqual(
            (left as Record<string, unknown>)[key],
            (right as Record<string, unknown>)[key],
          ),
      )
    );
  }

  return false;
}

export function isBlank(value: unknown): boolean {
  if (value === null || value === undefined || value === "") {
    return true;
  }

  if (Array.isArray(value)) {
    return value.length === 0;
  }

  if (typeof value === "object") {
    return Object.keys(value).length === 0;
  }

  return false;
}
