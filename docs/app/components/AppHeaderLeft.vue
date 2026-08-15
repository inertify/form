<script setup lang="ts">
const appConfig = useAppConfig()
const site = useSiteConfig()
const { localePath } = useDocusI18n()

const ariaLabel = appConfig.header?.title || site.name
</script>

<template>
  <NuxtLink
    :to="localePath('/')"
    :aria-label="ariaLabel"
    class="inline-flex items-center gap-2"
  >
    <AppHeaderLogo class="h-6 w-auto shrink-0" />
    <span class="text-3xl font-bold tracking-tight font-heading italic"><span class="text-primary">inertify</span><span class="text-lg text-muted"> | </span><span class="text-lg">form</span></span>
  </NuxtLink>
</template>
