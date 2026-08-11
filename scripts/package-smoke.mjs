import assert from "node:assert/strict";
import { execFileSync } from "node:child_process";
import { createRequire } from "node:module";
import { mkdtempSync, readFileSync, rmSync } from "node:fs";
import { tmpdir } from "node:os";
import { join } from "node:path";

const manifest = JSON.parse(readFileSync(new URL("../package.json", import.meta.url), "utf8"));
const npmCache = mkdtempSync(join(tmpdir(), "inertify-form-npm-cache-"));
let pack;

try {
  pack = JSON.parse(
    execFileSync("npm", ["pack", "--json", "--dry-run"], {
      cwd: new URL("..", import.meta.url),
      encoding: "utf8",
      env: { ...process.env, npm_config_cache: npmCache },
    }),
  )[0];
} finally {
  rmSync(npmCache, { recursive: true, force: true });
}

const publishedFiles = pack.files.map(({ path }) => path);

assert.deepEqual(manifest.files, ["dist"]);
assert.equal(manifest.sideEffects, false);
assert.equal(manifest.dependencies, undefined);
assert(publishedFiles.includes("dist/index.js"));
assert(publishedFiles.includes("dist/index.cjs"));
assert(publishedFiles.includes("dist/index.d.ts"));
const packageMetadata = new Set(["package.json", "README.md", "LICENSE.md"]);
assert(publishedFiles.every((path) => packageMetadata.has(path) || path.startsWith("dist/")));
assert(publishedFiles.every((path) => !path.endsWith(".css")));
assert(publishedFiles.every((path) => !path.endsWith(".map")));

const esm = await import(new URL("../dist/index.js", import.meta.url));
const cjs = createRequire(import.meta.url)("../dist/index.cjs");

assert.equal(typeof esm.useForm, "function");
assert.equal(typeof esm.HeadlessForm, "object");
assert.equal(typeof cjs.useForm, "function");
assert.equal(typeof cjs.HeadlessForm, "object");

await assert.rejects(
  import("@inertify/form-vue/resources/js/index.ts"),
  (error) => error?.code === "ERR_PACKAGE_PATH_NOT_EXPORTED",
);
