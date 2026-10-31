# Enforcement checks

Roadmap step 10 (see [the modularization roadmap](MODULARIZATION-ROADMAP.md)). These checks keep
the typed admin app (`assets/src/app`) independent of the recovered runtime, and keep its published
bundles (`assets/dist/admin`) small and free of duplicate React or WordPress code. CI runs them on
every push and pull request to `main`.

## Run locally

| Command | What it runs |
| --- | --- |
| `npm run check:ci` | Everything CI runs in the Node job: `typecheck`, `test:app`, `check:architecture`, then `node --test tests/js/check-architecture.test.mjs` |
| `npm run check:architecture` | `node tools/check-architecture.mjs`: the architecture rules below and the budget table |
| `node --test tests/js/check-architecture.test.mjs` | Tests for the checker, using fixture trees under `tests/fixtures/architecture/` |

The architecture check reads source files and the bundles already published in `assets/dist/admin`.
It never builds anything. After you rebuild the admin app, run it again to check the new bundles.

CI (`.github/workflows/ci.yml`) has two jobs:

- **checks:** uses the Node version in `.nvmrc`, runs `npm ci`, then `npm run check:ci`.
- **php-lint:** runs `php -l` with PHP 8.2 on every file in `includes/` and `tests/php`. Neither job needs WordPress or a database.

## What `tools/check-architecture.mjs` enforces

Imports are found with `@babel/parser`, which ignores comments. They are resolved the way the bundler
resolves them: relative paths, extensions, `index.*` files, and `.js` specifiers that point to `.ts`
files. The checker reads static imports, `export … from`, dynamic `import()` and `require()`.
Package imports are not followed. Tests (`*.test.*`, `*.spec.*`, `test/` folders) and `.d.ts` files
are not shipped, so they are skipped.

### a. The app does not reach recovered runtime code

The checker starts at every app file and follows runtime imports through `assets/src`. Type-only
imports are skipped because the build erases them. It fails when one of these files can be reached:

- any file under `assets/src/application/`, `modules/`, `runtimes/` or `static/`;
- the recovered SDK: `assets/src/extensions/index.jsx`, `lazyFeatures.js` or `LazyFeature.jsx`;
- any file listed in a `components.json`;
- any module that reads `readRuntime` (including `createComponent(readRuntime)`), refers to
  `webpackChunkohmylms`, or loads numeric webpack module IDs (`__webpack_require__(N)`, or `n(N)`
  next to the `n.d`/`n.r` helpers).

Most of these files were deleted at the cutover ([CUTOVER.md](CUTOVER.md)); the rule keeps them, or code like them,
from coming back. `assets/src/ui/**` is never flagged for its own
content, but the checker still follows its imports. The extension registry lives in the app
(`assets/src/app/extensions/registry.mjs`). Authored modules elsewhere in `assets/src`
(for example `features/question-editor/coreEditorComponents.js`) are allowed as long as nothing
they import reaches the list above. Nothing is allowed by name.

Each failure names the app file, the import that leaves the app, and the shortest chain to the
recovered module:

```text
assets/src/app/x.ts imports assets/src/features/legacy/index.js, which reaches recovered runtime code:
assets/src/features/legacy/index.js -> assets/src/features/legacy/Widget.jsx (listed in assets/src/features/legacy/components.json)
```

### b. No import cycles in `assets/src/app`

The checker groups files into strongly connected components using static and dynamic runtime
imports. It reports each cycle once, with an example path. Cycles made only of type imports are
allowed.

### c. Features use each other only through public entry points

A file in `assets/src/app/features/<a>/` may import from `features/<b>/` only through
`features/<b>/index.ts` (or `index.tsx`) or a file named `public.ts` (or `public.tsx`). Type
imports are included, because they couple features too.

### d. No duplicate runtimes in the admin bundles

The rule checks `assets/dist/admin/admin-app*.js` and `assets/dist/admin/chunks/admin-app-*.js`.
A bundle fails when it carries its own copy of React, ReactDOM, the scheduler, or an `@wordpress/*`
package that WordPress provides. WordPress-provided means any `@wordpress/*` package that
`@wordpress/dependency-extraction-webpack-plugin` does not list in `BUNDLED_PACKAGES`. Bundling
`@wordpress/icons` is therefore expected. The rule looks for these signs:

- React build markers (`react.production`, `scheduler.production`);
- definitions of `__SECRET_INTERNALS_DO_NOT_USE_OR_YOU_WILL_BE_FIRED` or of `ReactCurrentOwner`
  (reading them from the WordPress copy is fine);
- `node_modules/<package>/` module IDs in the bundle;
- the package in the source map's `sources`;
- React in the bundle's `.LICENSE.txt`.

The dependencies in `admin-app.asset.php` must also include `react`, `react-dom` and `wp-element`.

### e. Per-screen size budgets

The rule measures the raw size and the gzip size (level 9) of `admin-app.js`,
`admin-app-extensions.js`, `admin-app.css`, and every `chunks/admin-app-*` JS and CSS file. CRLF is
folded to LF first, so Windows and Linux checkouts give the same numbers. The content hash is
removed from chunk names (`chunks/admin-app-lessons.1567f15f9da9.js` becomes
`chunks/admin-app-lessons.js`). The sizes are compared with `tools/asset-budgets.json` and printed
as a table.

The check fails when:

- an asset is over its raw or gzip budget;
- an asset has no budget;
- a budget has no published asset;
- the same chunk is published twice under different hashes.

### g. Features use the shared hooks instead of their own copies

`useUnsavedChangesGuard` and `usePagedList` live once, in `assets/src/app/hooks`. A file under
`assets/src/app/features/` fails when it declares a function, class or variable with one of those
names at its top level, or is itself named after the hook (`useUnsavedChangesGuard.ts`, including
re-export shims). When a screen needs different behaviour, add a backwards-compatible option to the
shared hook (for example `ignoreSamePath`, `inAddress`, `keepPrevious`) instead of copying it.

## Changing the rules on purpose

### Allow-list: `tools/architecture-allowlist.json`

Use the allow-list only for violations that already exist and cannot be fixed in the same change.
Every entry needs a `reason`. Entries without one fail the check. An entry that no longer matches a
violation also fails, so remove entries once their violation is fixed. Allowed violations are
still printed (`~ allowed: …`) with their reason.

```json
{
	"recoveredRuntime": [ { "from": "assets/src/app/…", "to": "assets/src/…", "reason": "…" } ],
	"cycles": [ { "files": [ "assets/src/app/a.ts", "assets/src/app/b.ts" ], "reason": "…" } ],
	"crossFeature": [ { "from": "assets/src/app/features/a/…", "to": "assets/src/app/features/b/…", "reason": "…" } ],
	"sharedHooks": [ { "file": "assets/src/app/features/a/…", "hook": "usePagedList", "reason": "…" } ]
}
```

For `recoveredRuntime`, `from` is the app file and `to` is the first file it imports on the chain;
both appear in the failure message. For `cycles`, `files` lists every file in the cycle.

The current code has no violations, so all three lists are empty.

### Budgets: `tools/asset-budgets.json`

The initial budgets are the published sizes plus 10% headroom, rounded up to 100 bytes. When a
screen legitimately grows, or when you add or remove a chunk:

1. Rebuild the admin app and publish the new bundles.
2. Either edit the entry in `tools/asset-budgets.json` by hand, or run
   `node tools/check-architecture.mjs --update-budgets`. That rewrites every budget to the current
   size plus `headroom`, adds new chunks and removes chunks that are gone.
3. Review the diff and explain the growth in the pull request. Do not raise budgets just to make CI
   pass.

### Runtime dependency inventory

Retired on 31 October 2026 with the recovered runtime it inventoried; see
[MODULARIZATION-ROADMAP.md](MODULARIZATION-ROADMAP.md).
