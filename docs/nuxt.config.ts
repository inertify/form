export default defineNuxtConfig({
  extends: ['docus'],

  app: {
    baseURL: '/form/'
  },

  site: {
    name: 'Inertify Form',
  },

  icon: {
    clientBundle: {
      icons: ['vscode-icons:file-type-php2'],
    },
  },

  content: {
    build: {
      markdown: {
        highlight: {
          langs: [
            'bash',
            'diff',
            'json',
            'js',
            'ts',
            'html',
            'css',
            'vue',
            'shell',
            'mdc',
            'md',
            'yaml',
            'php',
          ],
        },
      },
    },
  },

  mdc: {
    highlight: {
      theme: {
        light: 'github-light',
        default: 'github-light',
        dark: 'github-dark',
      },
    },
  },

  llms: {
    title: 'Inertify Form',
    description: 'Headless, schema-driven forms for Laravel, Inertia, and Vue.',
    full: {
      title: 'Inertify Form documentation',
      description: 'Complete documentation for the Inertify Form Laravel and Vue packages.',
    },
  },
})
