# Shared email package migration — 29 October 2026

> **Historical.** The recovered email designer, its bridges and these npm packages were removed on
> 31 October 2026 ([CUTOVER.md](CUTOVER.md)); paths and commands below no longer exist. The typed email
> editor (`assets/src/app/features/emails`) uses WordPress components and the server preview only; no Arco,
> MJML or Easy Email code ships. Current inventory: [third-party libraries](VENDOR-PACKAGE-MIGRATION.md).

The shared Arco UI namespace and MJML renderer now come from pinned npm packages:
`@arco-design/web-react` **2.66.5** and `mjml-browser` **4.16.1**. Existing saved email
JSON and synchronous conversion APIs are preserved.

## Compatibility choices

Arco's version matches the recovered namespace's explicit version. The recovered MJML
bundle does not expose a reliable package version. Comparing published releases showed
that 4.17/4.18 change social icon HTML and carousel CSS; 4.16.1 matches the tested output.
This is a compatibility selection, not proof of the original package version. Updating
MJML to a newer rendering format should be reviewed as a separate behavior change.

The seven MJML fixtures cover Unicode, text, links, buttons, responsive columns, images,
backgrounds, raw HTML, tables, merge tags, custom styles, navigation, social icons,
accordions, carousels, hero blocks, groups and validation errors. Twenty-one comparisons
check the complete HTML/JSON/error results in formatted, minified and default modes.
Random IDs are seeded consistently for comparison. The actual saved default email template
also produces byte-identical HTML in Chromium and retains its input JSON.

## Runtime boundary

- `assets/src/features/vendor-packages/email.js` defines the npm imports and registry.
- `email-loader.js` is a small independent admin entry that imports those packages only
  when the email designer is requested. Concurrent callers share the download promise;
  a failure resets it so a retry can succeed.
- `tools/email-package-adapters.mjs` gates chunk 355's existing loader on both the recovered
  editor chunk and the npm packages. This covers all existing callers without making the
  renderer asynchronous after loading. Factory 3270 becomes an Arco namespace bridge;
  factory 44098 remains a callable CommonJS MJML renderer backed by npm.
- The existing email relocation guard still rejects new eager consumers outside the lazy
  boundary. Adapter counts fail the build if the expected loader or factory layout changes.
- The email chunk URL includes the package adapter's content in its version hash. PHP
  registers the bootstrap with generated WordPress React/ReactDOM dependencies before
  application startup. Both development and shipped builds use this configuration.
- Source maps, nested npm package notices and hashed async chunks are synchronized into
  shipped assets. The unchanged editor stylesheet modules remain responsible for Arco's
  visual styles and LMS overrides.

`npm ci` and `npm run build` recreate the output. Run `npm run analyze:bundles` for the
bootstrap and async package inventory, including their separately gzipped sizes. A snapshot
is saved in `state/admin-benchmark/2026-10-29-email-packages.json`.

## Verification

Unit tests check the bridges, missing-package errors, host React externalization, cold
bootstrap boundary, license notices and adapter layout guards. All unrelated lazy vendor
factories remain compared with the recovery baseline.

Browser tests verify no npm email download at startup, single shared downloads, recovery
after either recovered-chunk or npm-chunk network failure, all Arco export names, input
changes, select menus, switches and modal open/close behavior. The saved template and
existing editor, chart, Emotion and email rendering regressions also pass.

Validation snapshot: **707 unit tests pass, three are skipped; all ten Chromium cases pass.**
Targeted source lint/formatting, source contracts, PHP syntax and AdminAssets standards pass.
Two complete builds are byte-identical. The live WordPress dashboard renders without
console errors and includes only the small email bootstrap; the public homepage loads no
email package entry. The browser fixture tests exercise the designer APIs independently
of authentication and database writes.
Webpack's existing size/performance warnings remain visible.

## Scope and remaining code

This replaces the **shared Arco factory and MJML renderer**, not every implementation
embedded inside third-party email editor packages. Easy Email's UI/editor/export modules,
its source editor and html2canvas remain recovered in chunk 355. Easy Email's prebuilt UI
also contains private vendor code, including Arco components. Migrating those complete
packages is the next boundary; removing their internals individually would risk the editor's
form and selection contracts. No claim is made that every Arco copy has been removed.

The public APIs remain synchronous after loading. No database migrations or saved email
rewrites were performed. The package move alone does not establish faster rendering;
the bootstrap is about 3.4 KB and the larger npm implementation stays deferred.

`npm audit` still reports the existing 46 findings (45 moderate, one high); no finding was
added or suppressed by these packages. MJML ships a prebuilt browser distribution, so the
top-level npm audit does not enumerate every dependency embedded in that distribution.
Package notices include MJML's upstream MIT license and the installed dependency licenses.
Upstream number-precision provides only an MIT declaration in README/package.json; its
author and source declaration are recorded explicitly rather than inventing a copyright.

References: [MJML browser package](https://github.com/mjmlio/mjml/blob/master/packages/mjml-browser/README.md),
[MJML 4.16.1 license](https://github.com/mjmlio/mjml/blob/v4.16.1/LICENSE.md),
[Arco React](https://github.com/arco-design/arco-design).

## Easy Email core (roadmap step 2) — 29 October 2026

`easy-email-core` **4.17.1** replaces the recovered core (webpack factory 49050) behind an authored
wrapper in `assets/src/shared/easy-email/`. Exact pins: `easy-email-core` 4.17.1, `js-beautify`
1.15.4 (the recovered formatter matches 1.14.11 and newer, the package's inlined copy is older) and
`lodash` 4.18.1 (the version WordPress 7.1 ships; webpack maps it to the `lodash` script, so it is
not bundled). Peer dependencies are `react` and `react-dom` 18.3.1, the version WordPress ships; the
lock file moves them from 19.3.0 to 18.3.1. `docs/EASY-EMAIL-AUDIT.md` lists what the wrapper
restores and why.

### Runtime boundary

- `email-loader.js` now also defines `window.ohmylmsEmailPackages.createEasyEmailCore`. Arco and
  MJML stay in the lazy chunk, which merges into the same object instead of replacing it.
- `bridgeEasyEmailCore()` in `tools/email-package-adapters.mjs` replaces factory 49050 in
  `vendors.js`. The factory calls `createEasyEmailCore()` when the first consumer requires it, so
  the page globals are read at the same moment as before. It refuses to build unless the recovered
  factory exports exactly the names in `export-names.mjs`.
- Core stays **eager**, unlike Arco and MJML. Admin startup requires factory 49050 synchronously
  (the template previews, and the default templates in factory 87381, which call
  `BlockManager` while loading), and the "Start from scratch" button reads those templates before
  any email chunk is requested. Making the core lazy needs a loading step before that click and an
  adapter that defers factory 87381; neither can be checked without a browser.
- Cost: `email-packages.js` grows from about 3.4 KB to about 457 KB minified (150 KB gzip), made of
  the package, the matching js-beautify, the React 18 server renderer and uuid. The shared
  `vendors.js` shrinks by about 173 KB. Webpack cannot drop the package's own inlined copies of `he`
  and js-beautify.
- `MjmlToJson` obtains the MJML renderer from the lazy chunk when it is called with markup.

### Licences

`tools/email-licenses.mjs` builds the notice text from the source maps of the bootstrap and the lazy
chunks, including `he`, which the package inlines. `tools/sync-admin-assets.mjs` still scans only
the lazy chunk maps, so it must call `emailNotices( root, maps )` with both before the next ship.

### Verification

`tests/js/easy-email-core.test.mjs` compares the wrapper with the still-present recovered factory
(exports, registry, defaults with payloads, helpers, 74 corpus renders, 888 variant renders, three
page-global scenarios). `tests/js/email-core-bridge.test.mjs` covers the adapter and its
fail-closed checks. The designer UI, `MjmlToJson` from markup and a save, reopen and send round trip
were not exercised in a browser.
