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

  // Nuxt UI's ColorModeImage resolves an absolute src against app.baseURL, and
  // the IPX provider prefixes it a second time for its endpoint, producing
  // /form/_ipx/_/form/… which IPX cannot resolve. Nothing here is worth
  // optimizing — the only assets are logos and favicons — so skip IPX and let
  // sources through untouched.
  image: {
    provider: 'none',
  },

  // Every face is subset, and @nuxt/fonts never preloads a face that carries a
  // unicode-range, so the fonts were fetched only after layout and every page
  // painted in the fallback first. Latin-only keeps the preloaded set small.
  fonts: {
    defaults: {
      subsets: ['latin'],
      preload: true,
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
