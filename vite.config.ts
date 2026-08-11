import tailwindcss from "@tailwindcss/vite";
import vue from "@vitejs/plugin-vue";
import laravel from "laravel-vite-plugin";
import { fileURLToPath, URL } from "node:url";
import { defineConfig } from "vite";

export default defineConfig({
  plugins: [
    laravel({
      input: [
        "workbench/resources/css/app.css",
        "workbench/resources/js/app.ts",
      ],
      publicDirectory: "workbench/public",
      buildDirectory: "build",
      hotFile: "workbench/public/hot",
      refresh: ["workbench/app/**", "workbench/resources/**", "workbench/routes/**"],
    }),
    vue(),
    tailwindcss(),
  ],
  resolve: {
    alias: {
      "@": fileURLToPath(new URL("./workbench/resources/js", import.meta.url)),
      "@inertify/form-vue": fileURLToPath(
        new URL("./resources/js/index.ts", import.meta.url),
      ),
    },
  },
});
