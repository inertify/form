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
      light: '/inertify-logo.svg',
      dark: '/inertify-logo.svg',
      alt: 'Inertify Form',
      favicon: '/favicon.svg',
    },
  },

  github: {
    url: 'https://github.com/enkot/inertify-form',
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
    },
    pageHero: {
      slots: {
        title: 'font-semibold sm:text-6xl',
        container: '!pb-0',
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
          to: 'https://github.com/enkot/inertify-form',
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
