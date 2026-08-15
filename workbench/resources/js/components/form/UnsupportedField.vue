<script setup lang="ts">
import type { FormField } from "@inertify/form-vue";

defineOptions({ inheritAttrs: false });

defineProps<{
  field: FormField;
}>();
</script>

<template>
  <p class="rounded-md border border-dashed p-3 text-sm text-muted-foreground">
    Add an application renderer for <code>{{ field.component }}</code>.
  </p>
</template>
