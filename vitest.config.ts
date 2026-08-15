import vue from "@vitejs/plugin-vue";
import { fileURLToPath, URL } from "node:url";
import type { UserConfig as VitestUserConfig } from "vitest/config";
import { defineConfig } from "vite";

const viteConfig = defineConfig({
  plugins: [vue()],
  resolve: {
    alias: {
      "@": fileURLToPath(
        new URL("./workbench/resources/js", import.meta.url),
      ),
      "@inertify/form-vue": fileURLToPath(
        new URL("./resources/js/index.ts", import.meta.url),
      ),
    },
  },
});

const test = {
  environment: "happy-dom",
  setupFiles: ["tests-js/setup.ts"],
  include: ["tests-js/**/*.test.ts"],
  coverage: {
    provider: "v8",
    reporter: ["text", "json-summary"],
    include: ["resources/js/**/*.ts"],
    exclude: [
      "resources/js/index.ts",
      "resources/js/composables.ts",
      "resources/js/components/index.ts",
    ],
  },
} satisfies VitestUserConfig["test"];

export default {
  ...viteConfig,
  test,
};
