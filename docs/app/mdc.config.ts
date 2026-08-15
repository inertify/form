import { defineConfig } from '@nuxtjs/mdc/config'

function isVueTemplateFragment(code: string): boolean {
  const source = code
    .trimStart()
    .replace(/^(?:<!--[\s\S]*?-->\s*)+/, '')

  return source.startsWith('<')
    && !/^<(?:script|template|style)(?:\s|>)/i.test(source)
}

export default defineConfig({
  shiki: {
    transformers(code, lang) {
      if (lang !== 'vue' || !isVueTemplateFragment(code)) {
        return []
      }

      return [{
        name: 'inertify:vue-template-fragment',
        preprocess(source, options) {
          options.grammarContextCode = '<template>'

          return source
        },
      }]
    },
  },
})
