import { defineConfig } from "vitest/config";

export default defineConfig({
  test: {
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
  },
});
