<script setup lang="ts">
const appConfig = useAppConfig()
const site = useSiteConfig()
const { localePath } = useDocusI18n()

const ariaLabel = appConfig.header?.title || site.name
</script>

<template>
  <div class="inline-flex items-center gap-2 font-bold tracking-tight font-heading italic">
    <!-- These docs are served under /form by a rewrite, so the mark and the
         Inertify wordmark point at the parent site. A plain anchor keeps
         app.baseURL out of the href, which NuxtLink would prepend. -->
    <a
      href="/"
      aria-label="Inertify"
      class="inline-flex items-center gap-2"
    >
      <AppHeaderLogo class="h-6 w-auto shrink-0" />
      <span class="text-3xl text-primary">inertify</span>
    </a>
    <span class="text-lg text-muted">|</span>
    <NuxtLink
      :to="localePath('/')"
      :aria-label="ariaLabel"
      class="text-lg"
    >form</NuxtLink>
  </div>
</template>
