import { defineConfig, type Options } from "tsup";

const shared: Options = {
  entry: {
    index: "resources/js/index.ts",
    components: "resources/js/components/index.ts",
    composables: "resources/js/composables.ts",
  },
  sourcemap: false,
  splitting: false,
  treeshake: true,
};

export default defineConfig([
  {
    ...shared,
    format: ["esm"],
    dts: true,
    clean: true,
    external: ["vue", "@inertiajs/vue3"],
  },
  {
    ...shared,
    format: ["cjs"],
    dts: false,
    clean: false,
    external: ["vue"],
    // Inertia 3 is ESM-only. Bundling it is the only way to provide a
    // synchronously require-able CommonJS entry while Vue remains a peer.
    noExternal: ["@inertiajs/vue3", "@inertiajs/core"],
  },
]);
