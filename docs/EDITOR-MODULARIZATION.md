# Editor runtime modularization — 29 October 2026

> **Historical.** The recovered editor runtime, its `editor-tiptap`/`editor-lowlight` bridges and the Tiptap
> packages were removed on 31 October 2026 ([CUTOVER.md](CUTOVER.md)); paths and commands below may no longer
> exist. Current admin code: [ARCHITECTURE.md](ARCHITECTURE.md), [REACT-DEVELOPMENT.md](REACT-DEVELOPMENT.md).

The recovered editor dependency runtime now comes from pinned npm packages: Tiptap
2.27.3, its React adapter and extensions, ProseMirror through `@tiptap/pm`, Lucide React
0.468.0, Lowlight 3.3.0 and Highlight.js 11.11.1. Built-in lesson and question authoring
continues to use Gutenberg. Certificate text fields remain their existing custom fields.

## Source and build boundaries

- `assets/src/features/editor-packages/tiptap.js` defines the named package registry.
  Webpack supplies React and ReactDOM from WordPress and resolves both CommonJS and ESM
  consumers to one ESM instance of each ProseMirror package.
- `compatibility.js` extends the npm text alignment and table extensions to preserve
  the existing default alignment and serialized column styles. Package files are untouched.
- `icons.js` imports icons from npm and retains all 1,534 canonical names and 5,196 root
  exports/aliases. Thirty-six SVG compatibility records preserve changed icon artwork.
  Dynamic names and the existing custom icon precedence remain supported.
- `tools/editor-runtime-manifest.mjs` maps the old module IDs to named exports. The build
  adapter replaces every implementation in `vendors/editor.js` and the shared editor/icon
  factories in `vendors/vendors.js` with small compatibility bridges. The numeric IDs remain
  only to support existing consumers; new source can use named imports.
- Unused Hocuspocus, its WebSocket enum and Yjs factories are removed from generated output.
  Their only application consumer needed connection status strings, already supplied by the
  existing status adapter. Build guards reject unmapped editor implementations or new
  consumers of the retired libraries.
- `editor-tiptap.js` and `editor-lowlight.js` are independent admin entries with source maps,
  dependency metadata and license notices. The Tiptap code-block extension shares Lowlight's
  Highlight.js core rather than embedding another copy. PHP dependencies enforce loading
  syntax, editor packages, vendor bridges, then the application.
- Both opt-in source assets and shipped assets use these entries. They are registered only
  for the LMS admin screen. Original recovered source and the parity build remain available
  as compatibility references.

Run `npm ci` and `npm run build` to build and sync the production assets. The one-time
`node tools/generate-editor-icons.mjs` migration generator can regenerate the compatible
icon registry from the preserved baseline; it is not needed during a normal build.

## Verification

Unit tests resolve every old bridge export to the exact package instance, check retired
factories, exercise build guards, compare every icon's name/SVG/aliases and verify that
the bundle contains one ProseMirror implementation, external React and no duplicate
Highlight.js core. Existing recovery tests still compare all unrelated factories.

Chromium tests edit and reopen code and rich text, including headings, marks, links,
colors, fonts, alignment, lists, tasks, images and the custom table-cell extension. Saved
JSON and HTML match the pre-migration runtime with its existing deduplication adapter.
React hook updates, toolbar formatting and dynamic/custom icons are exercised. Email
chunk loading, identical rendered templates and failed-load retries remain covered.
These are client serialization checks; they do not claim an authenticated database save
for every editor feature.

Validation: 679 JavaScript tests pass with three skipped; all six editor/email Chromium
cases pass. Two complete builds produce 526 byte-identical artifacts. Authored JavaScript
lint, source contracts, targeted formatting, PHP syntax and the AdminAssets PHP standards
check pass. The live WordPress dashboard renders with the correct script order and no
console errors; the public homepage loads neither admin editor entry. Webpack's three
bundle-size/performance warnings remain visible.

## Payload and scope

The current editor/shared group totals **3,370,560 bytes** of JavaScript, or **1,027,705
bytes** when its four files are separately gzipped:

| File | Bytes | Gzip bytes |
| --- | ---: | ---: |
| Shared vendors | 1,340,288 | 445,730 |
| npm Lowlight | 951,353 | 291,150 |
| npm Tiptap/ProseMirror/icons | 1,054,918 | 289,284 |
| Editor compatibility bridges | 24,001 | 1,541 |

This completes the editor dependency migration. It does not remove every recovered
dependency from the LMS: the email designer/MJML/Arco runtime remains in its lazy chunk,
and, at that snapshot, Chart.js and Emotion remained recovered. They have since moved to
[npm packages](VENDOR-PACKAGE-MIGRATION.md). Custom editor
nodes and UI are preserved application modules. Modularization supports maintenance and
caching; these figures alone do not establish faster user interactions or fix the separately
documented local DNS/loopback delays. Run `npm run analyze:editor` for current measurements;
the snapshot is `state/admin-benchmark/2026-10-29-editor-runtime.json`.

## Dependency audit

`npm audit` currently reports **46 findings: 45 moderate and one high**. The Tiptap core
advisory's broad version range still flags 2.27.3 and propagates to dependent extensions.
The installed 2.27.3 implementation already defines an own `__proto__` key as a data
property; a regression test exercises the compiled bundle and confirms it does not inherit
the attacker's DOM event attributes. This evidence does not make the audit report clean.
The existing fast-uri and zod findings remain outside this migration. No advisory is
suppressed and no blanket dependency update was applied.

References: [Tiptap upgrade guide](https://tiptap.dev/docs/guides/upgrade-tiptap-v2),
[mergeAttributes advisory](https://github.com/advisories/GHSA-cp6q-959q-f8rh),
[upstream Tiptap MIT license](https://github.com/ueberdosis/tiptap/blob/main/LICENSE.md).
