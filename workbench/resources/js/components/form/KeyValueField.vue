<script setup lang="ts">
import { computed } from "vue";
import { type FormField, type UseFormApi } from "@inertify/form-vue";
import Button from "@/components/ui/Button.vue";
import Input from "@/components/ui/Input.vue";
import FieldShell from "./FieldShell.vue";

defineOptions({ inheritAttrs: false });

const props = defineProps<{
    field: FormField;
    form: UseFormApi;
    name: string;
    value: unknown;
    error: string | null;
    required: boolean;
    disabled: boolean;
    readonly: boolean;
    setValue: (value: unknown) => void;
    blur: () => Promise<boolean>;
}>();

interface KeyValueRow {
    key: string;
    value: unknown;
}

const isKeyed = computed(
    () => String(props.field.mode ?? "keyed") !== "single",
);
const keyedRows = computed<KeyValueRow[]>(() =>
    Object.entries(recordValue(props.value)).map(([key, value]) => ({
        key,
        value,
    })),
);
const singleRows = computed<unknown[]>(() =>
    Array.isArray(props.value) ? props.value : [],
);
const rowCount = computed(() =>
    isKeyed.value ? keyedRows.value.length : singleRows.value.length,
);

const minimumItems = computed(() =>
    limit(props.field.minItems ?? props.field.minRows, 0),
);
const maximumItems = computed(() =>
    limit(props.field.maxItems ?? props.field.maxRows, Infinity),
);
const keyLabel = computed(() =>
    String(props.field.keyLabel ?? props.field.keyHeader ?? "Key"),
);
const valueLabel = computed(() =>
    String(props.field.valueLabel ?? props.field.valueHeader ?? "Value"),
);
const addLabel = computed(() =>
    String(props.field.addLabel ?? props.field.addButtonLabel ?? "Add row"),
);
const locked = computed(() => props.disabled || props.readonly);

function recordValue(value: unknown): Record<string, unknown> {
    return typeof value === "object" && value !== null && !Array.isArray(value)
        ? (value as Record<string, unknown>)
        : {};
}

function limit(value: unknown, fallback: number): number {
    if (value === null || value === undefined || value === "") {
        return fallback;
    }

    const numeric = Number(value);

    return Number.isFinite(numeric) && numeric >= 0
        ? Math.floor(numeric)
        : fallback;
}

function updateKey(index: number, key: unknown): void {
    const rows = keyedRows.value.map((row) => ({ ...row }));
    const current = rows[index];

    if (!current) {
        return;
    }

    current.key = String(key ?? "");
    props.setValue(Object.fromEntries(rows.map((row) => [row.key, row.value])));
}

function updateKeyedValue(index: number, value: unknown): void {
    const rows = keyedRows.value.map((row) => ({ ...row }));
    const current = rows[index];

    if (!current) {
        return;
    }

    current.value = value;
    props.setValue(Object.fromEntries(rows.map((row) => [row.key, row.value])));
}

function updateSingleValue(index: number, value: unknown): void {
    const rows = [...singleRows.value];

    if (index < 0 || index >= rows.length) {
        return;
    }

    rows[index] = value;
    props.setValue(rows);
}

function addRow(): void {
    if (locked.value || rowCount.value >= maximumItems.value) {
        return;
    }

    if (!isKeyed.value) {
        props.setValue([...singleRows.value, ""]);

        return;
    }

    const value = recordValue(props.value);
    let key = "";
    let suffix = 1;

    while (Object.prototype.hasOwnProperty.call(value, key)) {
        key = `key_${suffix}`;
        suffix += 1;
    }

    props.setValue({ ...value, [key]: "" });
}

function removeRow(index: number): void {
    if (locked.value || rowCount.value <= minimumItems.value) {
        return;
    }

    if (!isKeyed.value) {
        const rows = [...singleRows.value];
        rows.splice(index, 1);
        props.setValue(rows);

        return;
    }

    const rows = keyedRows.value.filter((_, rowIndex) => rowIndex !== index);
    props.setValue(Object.fromEntries(rows.map((row) => [row.key, row.value])));
}

function nestedError(index: number, key?: string): string | null {
    const path =
        isKeyed.value && key !== undefined
            ? `${props.name}.${key}`
            : `${props.name}.${index}`;

    return props.form.errors.value[path] ?? null;
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
        <div class="space-y-3">
            <div
                v-if="isKeyed && keyedRows.length > 0"
                class="grid grid-cols-[minmax(0,1fr)_minmax(0,1fr)_auto] gap-2 px-1 text-xs font-medium uppercase tracking-wide text-muted-foreground"
                aria-hidden="true"
            >
                <span>{{ keyLabel }}</span>
                <span>{{ valueLabel }}</span>
                <span class="w-20" />
            </div>

            <template v-if="isKeyed">
                <div
                    v-for="(row, index) in keyedRows"
                    :key="index"
                    class="grid gap-2 sm:grid-cols-[minmax(0,1fr)_minmax(0,1fr)_auto]"
                >
                    <Input
                        :model-value="row.key"
                        :name="`${name}.${index}.key`"
                        :placeholder="keyLabel"
                        :disabled="locked"
                        :aria-label="`${keyLabel} ${index + 1}`"
                        @update:model-value="updateKey(index, $event)"
                        @blur="blur"
                    />
                    <div class="space-y-1">
                        <Input
                            :model-value="row.value"
                            :name="`${name}.${index}.value`"
                            :placeholder="field.placeholder ?? valueLabel"
                            :disabled="locked"
                            :aria-label="`${valueLabel} ${index + 1}`"
                            :aria-invalid="Boolean(nestedError(index, row.key))"
                            @update:model-value="
                                updateKeyedValue(index, $event)
                            "
                            @blur="blur"
                        />
                        <p
                            v-if="nestedError(index, row.key)"
                            class="text-sm text-destructive"
                            role="alert"
                        >
                            {{ nestedError(index, row.key) }}
                        </p>
                    </div>
                    <Button
                        variant="destructive"
                        :disabled="locked || rowCount <= minimumItems"
                        :aria-label="`Remove ${keyLabel.toLowerCase()} ${index + 1}`"
                        @click="removeRow(index)"
                    >
                        Remove
                    </Button>
                </div>
            </template>

            <template v-else>
                <div
                    v-for="(row, index) in singleRows"
                    :key="index"
                    class="grid gap-2 sm:grid-cols-[minmax(0,1fr)_auto]"
                >
                    <div class="space-y-1">
                        <Input
                            :model-value="row"
                            :name="`${name}.${index}`"
                            :placeholder="field.placeholder ?? valueLabel"
                            :disabled="locked"
                            :aria-label="`${valueLabel} ${index + 1}`"
                            :aria-invalid="Boolean(nestedError(index))"
                            @update:model-value="
                                updateSingleValue(index, $event)
                            "
                            @blur="blur"
                        />
                        <p
                            v-if="nestedError(index)"
                            class="text-sm text-destructive"
                            role="alert"
                        >
                            {{ nestedError(index) }}
                        </p>
                    </div>
                    <Button
                        variant="destructive"
                        :disabled="locked || rowCount <= minimumItems"
                        :aria-label="`Remove ${valueLabel.toLowerCase()} ${index + 1}`"
                        @click="removeRow(index)"
                    >
                        Remove
                    </Button>
                </div>
            </template>

            <p v-if="rowCount === 0" class="text-sm text-muted-foreground">
                No entries yet.
            </p>

            <Button
                variant="outline"
                :disabled="locked || rowCount >= maximumItems"
                @click="addRow"
            >
                {{ addLabel }}
            </Button>
        </div>
    </FieldShell>
</template>
