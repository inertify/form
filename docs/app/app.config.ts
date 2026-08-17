export default defineAppConfig({
  docus: {
    locale: 'en',
  },

  search: {
    fts: true,
  },

  seo: {
    titleTemplate: '%s · Inertify Form',
    title: 'Inertify Form',
    description: 'Headless, schema-driven forms for Laravel, Inertia, and Vue.',
  },

  header: {
    title: 'Inertify Form',
    logo: {
      // Relative, not root-absolute: Nuxt UI's ColorModeImage prefixes an
      // absolute src with app.baseURL, and @nuxt/image prefixes it again for
      // the /_ipx endpoint, producing /form/_ipx/_/form/… which IPX can't
      // resolve. A relative src is passed through untouched.
      light: 'inertify-logo.svg',
      dark: 'inertify-logo.svg',
      alt: 'Inertify Form',
      favicon: '/favicon.svg',
    },
  },

  github: {
    url: 'https://github.com/inertify/form',
    branch: 'main',
    rootDir: 'docs',
  },

  ui: {
    colors: {
      primary: 'inertify',
      neutral: 'zinc',
    },
    prose: {
      codeIcon: {
        php: 'i-vscode-icons-file-type-php2',
      },
      pre: {
        slots: {
          // Keep code blocks dark in both color modes. `dark` scopes the UI
          // color tokens and Shiki's dark theme to this subtree only.
          root: 'dark',
          header: 'px-5 py-3.5',
          copy: 'top-[13px] end-[13px]',
          // Scroll long lines instead of wrapping them. Line numbers come from
          // the `line` attribute Shiki sets on each line (see app.css).
          base: 'px-5 py-4 whitespace-pre wrap-normal **:[.line.highlight]:-mx-5 **:[.line.highlight]:px-5',
        },
      },
    },
    pageHero: {
      slots: {
        title: 'font-semibold sm:text-6xl',
        // Halve the theme's top padding; the hero card carries the height.
        container: '!pb-0 pt-12 sm:pt-16 lg:pt-20',
      },
    },
    pageCard: {
      slots: {
        container: 'lg:flex min-w-0',
        wrapper: 'flex-none',
      },
    },
  },

  toc: {
    bottom: {
      title: 'Package links',
      links: [
        {
          icon: 'i-simple-icons-github',
          label: 'GitHub repository',
          to: 'https://github.com/inertify/form',
          target: '_blank',
        },
        {
          icon: 'i-simple-icons-packagist',
          label: 'Composer package',
          to: 'https://packagist.org/packages/inertify/form',
          target: '_blank',
        },
        {
          icon: 'i-simple-icons-npm',
          label: 'Vue package',
          to: 'https://www.npmjs.com/package/@inertify/form-vue',
          target: '_blank',
        },
      ],
    },
  },
})
