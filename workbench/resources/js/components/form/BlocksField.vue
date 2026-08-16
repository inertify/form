<script setup lang="ts">
import {
    FormCollection,
    type FormBlockSet,
    type FormField,
    type FormFieldset,
    type UseFormApi,
} from "@inertify/form-vue";
import Button from "@/components/ui/Button.vue";
import Input from "@/components/ui/Input.vue";
import Textarea from "@/components/ui/Textarea.vue";
import FieldShell from "./FieldShell.vue";

defineOptions({ inheritAttrs: false });

const props = defineProps<{
    field: FormField;
    form: UseFormApi;
    name: string;
    error: string | null;
    required: boolean;
    disabled: boolean;
    readonly: boolean;
}>();

const supportedComponents = new Set(["Text", "Textarea", "Checkbox"]);

function blockSets(): FormBlockSet[] {
    return Array.isArray(props.field.sets)
        ? props.field.sets
        : Array.isArray(props.field.blocks)
          ? props.field.blocks
          : [];
}

function record(value: unknown): Record<string, unknown> {
    return typeof value === "object" && value !== null && !Array.isArray(value)
        ? (value as Record<string, unknown>)
        : {};
}

function blockType(item: unknown): string {
    const value = record(item);

    return String(value.type ?? value.name ?? "");
}

function findSet(item: unknown): FormBlockSet | null {
    const type = blockType(item);

    return blockSets().find((set) => setType(set) === type) ?? null;
}

function setType(set: FormBlockSet): string {
    return String(set.type ?? set.name ?? "");
}

function setLabel(set: FormBlockSet | null, fallback = "Block"): string {
    return String(set?.label ?? set?.type ?? set?.name ?? fallback);
}

function schemaFields(set: FormBlockSet | null): FormField[] {
    if (!set) {
        return [];
    }

    const schema = Array.isArray(set.schema)
        ? set.schema
        : Array.isArray(set.fields)
          ? set.fields
          : [];

    return flattenFields(schema).filter((field) => field.visible !== false);
}

function flattenFields(items: Array<FormField | FormFieldset>): FormField[] {
    return items.flatMap((item) =>
        isField(item) ? [item] : flattenFields(item.fields),
    );
}

function isField(item: FormField | FormFieldset): item is FormField {
    return typeof (item as FormField).component === "string";
}

function fieldName(field: FormField): string {
    return String(field.name || field.attribute || "");
}

function blockData(item: unknown): Record<string, unknown> {
    return record(record(item).data);
}

function pathSegments(path: string): string[] {
    return path
        .replace(/\[([^\[\]]+)\]/g, ".$1")
        .split(".")
        .map((segment) => segment.replace(/^[\"']|[\"']$/g, "").trim())
        .filter(Boolean);
}

function nestedValue(item: unknown, field: FormField): unknown {
    let current: unknown = blockData(item);

    for (const segment of pathSegments(fieldName(field))) {
        if (typeof current !== "object" || current === null) {
            return undefined;
        }

        current = (current as Record<string, unknown>)[segment];
    }

    return current;
}

function setNestedValue(
    source: unknown,
    segments: string[],
    value: unknown,
): unknown {
    const [segment, ...remaining] = segments;

    if (segment === undefined) {
        return value;
    }

    if (Array.isArray(source)) {
        const next = [...source];
        const index = Number(segment);

        if (Number.isInteger(index) && index >= 0) {
            next[index] = setNestedValue(next[index], remaining, value);
        }

        return next;
    }

    const next = record(source);

    return {
        ...next,
        [segment]: setNestedValue(next[segment], remaining, value),
    };
}

function updateNested(
    update: (index: number, value: unknown) => void,
    item: unknown,
    index: number,
    field: FormField,
    value: unknown,
): void {
    const current = record(item);
    const data = setNestedValue(
        blockData(item),
        pathSegments(fieldName(field)),
        value,
    );

    update(index, { ...current, data });
}

function checkboxValue(item: unknown, field: FormField): boolean {
    return Object.is(nestedValue(item, field), field.trueValue ?? true);
}

function updateCheckbox(
    update: (index: number, value: unknown) => void,
    item: unknown,
    index: number,
    field: FormField,
    event: Event,
): void {
    const input = event.target as HTMLInputElement;
    updateNested(
        update,
        item,
        index,
        field,
        input.checked ? (field.trueValue ?? true) : (field.falseValue ?? false),
    );
}

function nestedPath(index: number, field: FormField): string {
    return `${props.name}.${index}.data.${fieldName(field)}`;
}

function nestedId(index: number, field: FormField): string {
    return `field-${nestedPath(index, field).replace(/[^a-z0-9_-]/gi, "-")}`;
}

function nestedError(index: number, field: FormField): string | null {
    return props.form.errors.value[nestedPath(index, field)] ?? null;
}

function nestedRequired(field: FormField): boolean {
    const rules = Array.isArray(field.rules)
        ? field.rules
        : typeof field.rules === "string"
          ? field.rules.split("|")
          : [];

    return rules.some(
        (rule) =>
            typeof rule === "string" && rule.split(":", 1)[0] === "required",
    );
}

function isLocked(field?: FormField): boolean {
    return (
        props.disabled ||
        props.readonly ||
        Boolean(field?.disabled) ||
        Boolean(field?.readonly)
    );
}

function minimumItems(): number {
    const value = Number(props.field.minItems ?? props.field.minBlocks ?? 0);

    return Number.isFinite(value) && value >= 0 ? Math.floor(value) : 0;
}

function canAddSet(
    items: unknown[],
    set: FormBlockSet,
    canAppend: boolean,
): boolean {
    if (isLocked() || !canAppend) {
        return false;
    }

    if (set.maxItems === null || set.maxItems === undefined) {
        return true;
    }

    const maximum = Number(set.maxItems);

    return (
        !Number.isFinite(maximum) ||
        maximum < 0 ||
        items.filter((item) => blockType(item) === setType(set)).length <
            maximum
    );
}
</script>

<template>
    <FieldShell
        :id="`field-${name}`"
        :label="field.label"
        :help="field.help"
        :error="error"
        :required="required"
    >
        <FormCollection
            :form="form"
            :name="name"
            v-slot="{
                items,
                keys,
                canAppend,
                appendBlock,
                update,
                remove,
                move,
            }"
        >
            <div class="space-y-4">
                <article
                    v-for="(item, index) in items"
                    :key="keys[index]"
                    class="space-y-4 rounded-lg border bg-muted/30 p-4"
                >
                    <header
                        class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between"
                    >
                        <div>
                            <strong class="text-sm">
                                {{
                                    setLabel(
                                        findSet(item),
                                        blockType(item) ||
                                            `Block ${index + 1}`,
                                    )
                                }}
                            </strong>
                            <p
                                v-if="findSet(item)?.description"
                                class="mt-1 text-sm text-muted-foreground"
                            >
                                {{ findSet(item)?.description }}
                            </p>
                        </div>
                        <div class="flex gap-1">
                            <Button
                                variant="ghost"
                                :disabled="isLocked() || index === 0"
                                :aria-label="`Move block ${index + 1} up`"
                                @click="move(index, index - 1)"
                            >
                                ↑
                            </Button>
                            <Button
                                variant="ghost"
                                :disabled="
                                    isLocked() || index === items.length - 1
                                "
                                :aria-label="`Move block ${index + 1} down`"
                                @click="move(index, index + 1)"
                            >
                                ↓
                            </Button>
                            <Button
                                variant="destructive"
                                :disabled="
                                    isLocked() ||
                                    items.length <= minimumItems()
                                "
                                :aria-label="`Remove block ${index + 1}`"
                                @click="remove(index)"
                            >
                                Remove
                            </Button>
                        </div>
                    </header>

                    <div v-if="findSet(item)" class="space-y-4">
                        <template
                            v-for="nestedField in schemaFields(
                                findSet(item),
                            )"
                            :key="fieldName(nestedField)"
                        >
                            <div
                                v-if="nestedField.component === 'Checkbox'"
                                class="space-y-1"
                            >
                                <label
                                    :for="nestedId(index, nestedField)"
                                    class="flex cursor-pointer items-start gap-3 rounded-lg border bg-card p-3 text-sm"
                                >
                                    <input
                                        :id="nestedId(index, nestedField)"
                                        type="checkbox"
                                        :name="
                                            nestedPath(index, nestedField)
                                        "
                                        :checked="
                                            checkboxValue(item, nestedField)
                                        "
                                        :disabled="isLocked(nestedField)"
                                        class="mt-0.5 size-4 accent-primary"
                                        v-bind="
                                            nestedField.dataAttributes ?? {}
                                        "
                                        @change="
                                            updateCheckbox(
                                                update,
                                                item,
                                                index,
                                                nestedField,
                                                $event,
                                            )
                                        "
                                    />
                                    <span>
                                        <span class="block font-medium">
                                            {{ nestedField.label }}
                                            <span
                                                v-if="
                                                    nestedRequired(
                                                        nestedField,
                                                    )
                                                "
                                                class="text-destructive"
                                                aria-hidden="true"
                                                >*</span
                                            >
                                        </span>
                                        <span
                                            v-if="nestedField.help"
                                            class="mt-1 block text-muted-foreground"
                                        >
                                            {{ nestedField.help }}
                                        </span>
                                    </span>
                                </label>
                                <p
                                    v-if="nestedError(index, nestedField)"
                                    class="text-sm text-destructive"
                                    role="alert"
                                >
                                    {{ nestedError(index, nestedField) }}
                                </p>
                            </div>

                            <div
                                v-else-if="
                                    nestedField.component === 'Textarea'
                                "
                                class="space-y-2"
                            >
                                <label
                                    :for="nestedId(index, nestedField)"
                                    class="text-sm font-medium"
                                >
                                    {{ nestedField.label }}
                                    <span
                                        v-if="nestedRequired(nestedField)"
                                        class="text-destructive"
                                        aria-hidden="true"
                                        >*</span
                                    >
                                </label>
                                <Textarea
                                    :id="nestedId(index, nestedField)"
                                    :model-value="
                                        nestedValue(item, nestedField)
                                    "
                                    :name="nestedPath(index, nestedField)"
                                    :placeholder="
                                        nestedField.placeholder ?? undefined
                                    "
                                    :disabled="isLocked(nestedField)"
                                    :readonly="
                                        readonly ||
                                        Boolean(nestedField.readonly)
                                    "
                                    :aria-invalid="
                                        Boolean(
                                            nestedError(index, nestedField),
                                        )
                                    "
                                    v-bind="
                                        nestedField.dataAttributes ?? {}
                                    "
                                    @update:model-value="
                                        updateNested(
                                            update,
                                            item,
                                            index,
                                            nestedField,
                                            $event,
                                        )
                                    "
                                />
                                <p
                                    v-if="nestedError(index, nestedField)"
                                    class="text-sm text-destructive"
                                    role="alert"
                                >
                                    {{ nestedError(index, nestedField) }}
                                </p>
                                <p
                                    v-else-if="nestedField.help"
                                    class="text-sm text-muted-foreground"
                                >
                                    {{ nestedField.help }}
                                </p>
                            </div>

                            <div
                                v-else-if="
                                    nestedField.component === 'Text'
                                "
                                class="space-y-2"
                            >
                                <label
                                    :for="nestedId(index, nestedField)"
                                    class="text-sm font-medium"
                                >
                                    {{ nestedField.label }}
                                    <span
                                        v-if="nestedRequired(nestedField)"
                                        class="text-destructive"
                                        aria-hidden="true"
                                        >*</span
                                    >
                                </label>
                                <Input
                                    :id="nestedId(index, nestedField)"
                                    :model-value="
                                        nestedValue(item, nestedField)
                                    "
                                    :name="nestedPath(index, nestedField)"
                                    :type="
                                        String(
                                            nestedField.inputType ?? 'text',
                                        )
                                    "
                                    :placeholder="
                                        nestedField.placeholder ?? undefined
                                    "
                                    :disabled="isLocked(nestedField)"
                                    :readonly="
                                        readonly ||
                                        Boolean(nestedField.readonly)
                                    "
                                    :aria-invalid="
                                        Boolean(
                                            nestedError(index, nestedField),
                                        )
                                    "
                                    v-bind="
                                        nestedField.dataAttributes ?? {}
                                    "
                                    @update:model-value="
                                        updateNested(
                                            update,
                                            item,
                                            index,
                                            nestedField,
                                            $event,
                                        )
                                    "
                                />
                                <p
                                    v-if="nestedError(index, nestedField)"
                                    class="text-sm text-destructive"
                                    role="alert"
                                >
                                    {{ nestedError(index, nestedField) }}
                                </p>
                                <p
                                    v-else-if="nestedField.help"
                                    class="text-sm text-muted-foreground"
                                >
                                    {{ nestedField.help }}
                                </p>
                            </div>

                            <p
                                v-else-if="
                                    !supportedComponents.has(
                                        nestedField.component,
                                    )
                                "
                                class="rounded-md border border-dashed p-3 text-sm text-muted-foreground"
                            >
                                {{ nestedField.label ?? nestedField.name }}
                                uses {{ nestedField.component }}, which this
                                simple block editor does not render.
                            </p>
                        </template>
                    </div>

                    <p v-else class="text-sm text-destructive" role="alert">
                        Unknown block type “{{ blockType(item) }}”.
                    </p>
                </article>

                <p
                    v-if="items.length === 0"
                    class="text-sm text-muted-foreground"
                >
                    No blocks yet.
                </p>

                <div
                    v-if="blockSets().length > 0"
                    class="flex flex-wrap gap-2"
                >
                    <Button
                        v-for="set in blockSets()"
                        :key="setType(set)"
                        variant="outline"
                        :disabled="!canAddSet(items, set, canAppend)"
                        @click="appendBlock(setType(set))"
                    >
                        Add {{ setLabel(set) }}
                    </Button>
                </div>
                <p v-else class="text-sm text-muted-foreground">
                    No block sets are configured.
                </p>
            </div>
        </FormCollection>
    </FieldShell>
</template>
