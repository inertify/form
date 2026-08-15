# Inertify Form documentation

The documentation site is a standalone [Docus](https://docus.dev) application.

## Local development

Use a Nuxt-supported Node.js release: 22.19+, 24.11+, or 26+.

```bash
npm install
npm run dev
```

The development server is available at `http://localhost:3000` by default.

Build and preview the production output with:

```bash
npm run build
npm run preview
```

## Deploy to Vercel

Import the repository into Vercel and set **Root Directory** to `docs`. Vercel detects Nuxt and uses `npm run build`; no custom output directory or `vercel.json` is required.

After attaching a custom domain, set `NUXT_SITE_URL` to its canonical URL so sitemap, Open Graph, and LLM metadata use that domain. Preview deployments are detected automatically.
