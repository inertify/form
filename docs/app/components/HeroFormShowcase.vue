<script setup lang="ts">
import { ref } from 'vue'

const tabs = [
  {
    label: 'Preview',
    value: 'preview',
    slot: 'preview',
    icon: 'i-lucide-eye',
  },
  {
    label: 'PHP',
    value: 'php',
    slot: 'php',
    icon: 'i-vscode-icons-file-type-php2',
  },
  {
    label: 'Vue',
    value: 'vue',
    slot: 'vue',
    icon: 'i-simple-icons-vuedotjs',
  },
]

const skills = [
  'Laravel',
  'Vue',
  'TypeScript',
  'Accessibility',
]

const workModes = [
  { label: 'Remote', value: 'remote', icon: 'i-lucide-house' },
  { label: 'Hybrid', value: 'hybrid', icon: 'i-lucide-panels-top-left' },
  { label: 'Office', value: 'office', icon: 'i-lucide-building-2' },
]

const name = ref('Ada Lovelace')
const email = ref('ada@example.com')
const skill = ref('Laravel')
const workMode = ref('remote')
const employed = ref(true)
const company = ref('Analytical Engines')
const notifications = ref(true)
const avatarUpdated = ref(false)
const submitted = ref(false)

function markChanged() {
  submitted.value = false
}

function setEmployment(value: boolean | 'indeterminate') {
  employed.value = value === true

  if (!employed.value) {
    company.value = ''
  }

  markChanged()
}

function updateAvatar() {
  avatarUpdated.value = true
  markChanged()
}

function submitExample() {
  submitted.value = true
}

const spotlightX = ref(0)
const spotlightY = ref(0)
const spotlightVisible = ref(false)

function trackSpotlight(event: MouseEvent) {
  const bounds = (event.currentTarget as HTMLElement).getBoundingClientRect()

  spotlightX.value = event.clientX - bounds.left
  spotlightY.value = event.clientY - bounds.top
  spotlightVisible.value = true
}

function hideSpotlight() {
  spotlightVisible.value = false
}
</script>

<template>
  <section
    aria-label="Interactive profile form example"
    class="relative mx-auto w-full min-w-0 max-w-xl"
  >
    <!-- <div
      aria-hidden="true"
      class="absolute -inset-6 -z-10 rounded-[2.5rem] bg-primary/10 blur-3xl dark:bg-primary/5"
    /> -->

    <div
      class="dark relative min-w-0 rounded-2xl border border-default bg-default shadow-2xl shadow-black/10 ring-1 ring-green-950/5 dark:shadow-black/30 dark:ring-white/5 [--spotlight-size:520px] before:pointer-events-none before:absolute before:-inset-px before:rounded-[inherit] before:bg-[radial-gradient(var(--spotlight-size)_var(--spotlight-size)_at_calc(var(--spotlight-x,0px))_calc(var(--spotlight-y,0px)),var(--ui-primary),transparent_70%)] before:opacity-0 before:transition-opacity before:duration-300"
      :class="spotlightVisible ? 'before:opacity-100' : ''"
      :style="{
        '--spotlight-x': `${spotlightX}px`,
        '--spotlight-y': `${spotlightY}px`,
      }"
      @mousemove="trackSpotlight"
      @mouseleave="hideSpotlight"
    >
      <div
        aria-hidden="true"
        class="pointer-events-none absolute inset-0 rounded-[inherit] bg-default/90"
      />

      <div class="relative min-w-0 overflow-hidden rounded-[inherit]">
        <UTabs
          :items="tabs"
          default-value="preview"
          :unmount-on-hide="false"
          :ui="{
            root: 'relative gap-0',
            list: 'items-center gap-1 overflow-x-auto rounded-none border-b border-muted bg-default p-2 pe-12',
            indicator: 'inset-y-2 rounded-md bg-muted shadow-xs',
            trigger: 'grow-0 px-2 py-1.5 text-sm text-default data-[state=inactive]:text-default data-[state=active]:text-highlighted hover:bg-muted/50',
            leadingIcon: 'size-4',
            content: 'min-w-0 rounded-none focus-visible:outline-none',
          }"
        >
          <template #preview>
            <div class="h-[32rem] overflow-y-auto bg-muted p-4 text-left sm:p-5">
              <div class="mb-4 flex items-center gap-3 rounded-xl border border-default bg-elevated/45 p-3 shadow-xs">
                <div
                  role="img"
                  aria-label="Ada Lovelace avatar"
                  class="relative grid size-11 shrink-0 place-items-center rounded-full bg-zinc-900 text-xs font-semibold text-white ring-2 ring-default dark:bg-zinc-100 dark:text-zinc-900"
                  :class="avatarUpdated ? 'ring-primary/50' : ''"
                >
                  AL
                  <span class="absolute bottom-0 right-0 size-3 rounded-full border-2 border-default bg-primary" />
                </div>

                <div class="min-w-0 flex-1">
                  <p class="truncate text-sm font-semibold text-highlighted">
                    Avatar
                  </p>
                  <p class="truncate text-xs text-muted">
                    Placed explicitly with FormField
                  </p>
                </div>

                <UButton
                  type="button"
                  color="neutral"
                  variant="outline"
                  size="sm"
                  :icon="avatarUpdated ? 'i-lucide-check' : 'i-lucide-upload'"
                  :label="avatarUpdated ? 'Updated' : 'Avatar'"
                  class="shrink-0"
                  @click="updateAvatar"
                />
              </div>

              <form
                class="space-y-5"
                @input="markChanged"
                @submit.prevent="submitExample"
              >
                <section class="space-y-3">
                  <div class="grid gap-3 sm:grid-cols-2">
                    <UFormField
                      label="Name"
                      name="name"
                      size="xl"
                      required
                    >
                      <UInput
                        v-model="name"
                        autocomplete="name"
                        class="w-full"
                      />
                    </UFormField>

                    <UFormField
                      label="Email"
                      name="email"
                      size="xl"
                      required
                    >
                      <UInput
                        v-model="email"
                        type="email"
                        autocomplete="email"
                        class="w-full"
                      />
                    </UFormField>
                  </div>

                  <div class="grid gap-3 sm:grid-cols-2">
                    <UFormField
                      label="Primary skill"
                      name="skill"
                      size="xl"
                    >
                      <USelect
                        v-model="skill"
                        :items="skills"
                        class="w-full"
                        @update:model-value="markChanged"
                      />
                    </UFormField>

                    <UFormField
                      v-if="employed"
                      label="Company"
                      name="company"
                      size="xl"
                    >
                      <UInput
                        v-model="company"
                        autocomplete="organization"
                        class="w-full"
                      />
                    </UFormField>

                    <div
                      v-else
                      class="flex min-h-17 items-end"
                    >
                      <div class="flex h-10 w-full items-center gap-2 rounded-md border border-dashed border-default bg-elevated/50 px-3 text-sm text-muted">
                        <UIcon
                          name="i-lucide-eye-off"
                          class="size-4 shrink-0"
                        />
                        Company field hidden
                      </div>
                    </div>
                  </div>
                </section>

                <section class="space-y-3">
                  <fieldset>
                    <legend class="mb-1 text-base font-medium text-default">
                      Preferred work mode
                    </legend>

                    <div class="grid grid-cols-3 gap-2">
                      <label
                        v-for="mode in workModes"
                        :key="mode.value"
                        class="cursor-pointer"
                      >
                        <input
                          v-model="workMode"
                          type="radio"
                          name="work_mode"
                          :value="mode.value"
                          class="sr-only"
                          @change="markChanged"
                        >
                        <span
                          class="flex h-10 items-center justify-center gap-2 rounded-md border px-3 text-sm font-medium transition"
                          :class="workMode === mode.value
                            ? 'border-primary bg-primary/10 text-highlighted ring-1 ring-primary/20'
                            : 'border-default bg-default text-muted hover:bg-elevated'"
                        >
                          <UIcon
                            :name="mode.icon"
                            class="size-4 shrink-0"
                          />
                          {{ mode.label }}
                        </span>
                      </label>
                    </div>
                  </fieldset>

                  <div class="grid gap-4 rounded-lg border border-default bg-elevated/35 px-3 py-2.5 sm:grid-cols-2">
                    <UCheckbox
                      :model-value="employed"
                      name="is_employed"
                      label="Currently employed"
                      size="xl"
                      @update:model-value="setEmployment"
                    />

                    <USwitch
                      v-model="notifications"
                      name="notifications"
                      label="Project notifications"
                      size="xl"
                      @update:model-value="markChanged"
                    />
                  </div>
                </section>

                <div class="flex items-center gap-3 pt-0.5">
                  <p class="min-w-0 flex-1 text-sm text-muted">
                    <span class="font-medium text-highlighted">Laravel</span>
                    owns validation and visibility.
                  </p>

                  <!-- <UButton
                    type="submit"
                    size="xl"
                    :icon="submitted ? 'i-lucide-check' : undefined"
                    label="Save profile"
                    class="shrink-0"
                  /> -->
                </div>

                <p
                  class="sr-only"
                  role="status"
                  aria-live="polite"
                >
                  {{ submitted ? 'Example profile saved.' : avatarUpdated ? 'Example avatar updated.' : '' }}
                </p>
              </form>
            </div>
          </template>

          <template #php>
            <div class="source-panel h-[32rem] min-w-0 bg-muted text-left [&>div]:!static [&>div]:!my-0 [&>div]:h-full [&>div>button]:z-10 [&>div>pre]:h-full [&>div>pre]:!rounded-none [&>div>pre]:!border-0 [&>div>pre]:overflow-auto">
              <slot name="php" />
            </div>
          </template>

          <template #vue>
            <div class="source-panel h-[32rem] min-w-0 bg-muted text-left [&>div]:!static [&>div]:!my-0 [&>div]:h-full [&>div>button]:z-10 [&>div>pre]:h-full [&>div>pre]:!rounded-none [&>div>pre]:!border-0 [&>div>pre]:overflow-auto">
              <slot name="vue" />
            </div>
          </template>
        </UTabs>
      </div>
    </div>

    <!-- <div
      aria-hidden="true"
      class="mx-auto h-1.5 w-[88%] rounded-b-full bg-elevated shadow-sm"
    /> -->
  </section>
</template>

<style scoped>
.source-panel {
  min-width: 0;
}
</style>
