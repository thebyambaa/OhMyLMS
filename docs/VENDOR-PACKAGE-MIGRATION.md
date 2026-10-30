# Chart.js and Emotion npm migration — 29 October 2026

> **Historical.** The recovered admin that used these packages, the `vendor-charts`/`vendor-emotion` bridges
> and the packages themselves were removed on 31 October 2026 ([CUTOVER.md](CUTOVER.md)); paths and commands
> below may no longer exist.

Chart.js, its React wrapper and Emotion now build from pinned npm packages instead of
recovered library implementations. The existing chart and select components remain intact.

| Package | Version | Reason |
| --- | --- | --- |
| chart.js | 4.5.0 | Matches the version in the recovered Chart constructor |
| react-chartjs-2 | 5.3.1 | Compatible React wrapper for Chart.js 4 |
| @emotion/react | 11.14.0 | Supplies css, jsx and keyframes |
| @emotion/cache | 11.14.0 | Supplies the existing cache API |

The lockfile fixes all transitive versions. React and the JSX runtime are supplied by
WordPress, with generated script dependency metadata; neither entry embeds another React.

## Boundaries

- `assets/src/features/vendor-packages/charts.js` exposes only the chart types, scales,
  elements and plugins needed by the earnings chart, plus the npm Line component.
- `assets/src/features/vendor-packages/emotion.js` exposes the style and cache APIs used
  by the recovered select controls. No application styling was rewritten.
- `tools/vendor-package-adapters.mjs` replaces all four factories in the old chart and
  Emotion chunks with named-export bridges. Chunk IDs and existing consumer export names
  remain compatible. Seven private helper factories are removed from the shared bundle:
  Chart.js's color helper and Emotion's old Stylis implementation.
- The adapter rejects new unmapped factories or consumers of removed helpers, including
  references in runtime templates. Original source and the parity build remain available
  for compatibility tests.
- `vendor-charts.js` and `vendor-emotion.js` are independent LMS-admin-only package entries.
  PHP dependencies load them before their compatibility chunks and consumers, with version
  metadata for the package entries and file versions for the changed bridge chunks.
- Build synchronization updates both development and shipped assets, source maps and full
  license notices for every included npm package. Run `npm ci` then `npm run build`.

## Verification

The Chromium comparison uses the actual lazy earnings chart component from chunk 655.
Its canvas pixels, datasets, tooltip plugins and currency labels match the recovered
runtime. Updating labels/data reuses the chart instance, active tooltips use the new
data, and unmounting releases the instance.

The Emotion comparison checks serialized styles, hashes, keyframe names/tokens, prefixed
CSS, nonce handling, cache insertion/flush, and the actual select control. Its generated
styles match the recovered runtime, and selecting a Mongolian option still works.

Unit tests check every bridge's exports and exact instance identity, all removed helpers,
the fail-closed audit, host React externalization and shipped licenses. Recovery tests
continue to compare all unrelated vendor factories. The editor and email browser
regressions remain part of validation.

Validation snapshot: **682 unit tests pass, three are skipped; all eight editor, email
and vendor Chromium cases pass.** Two full builds produce 534 byte-identical artifacts.
Source contracts, targeted WordPress JavaScript lint/formatting, PHP syntax and the
AdminAssets PHP standards check pass. The live WordPress earnings report renders a
590 × 330 chart without console errors and loads the host JSX runtime before the chart
entry. The public homepage loads neither new vendor entry. Webpack's three existing
size/performance warnings remain visible.

## Payload and remaining work

| Current production JavaScript | Bytes | Separately gzipped bytes |
| --- | ---: | ---: |
| Shared vendors | 1,327,572 | 440,687 |
| Chart compatibility chunk | 1,094 | 495 |
| Emotion compatibility chunk | 922 | 394 |
| npm chart entry | 147,573 | 50,910 |
| npm Emotion entry | 18,447 | 7,336 |

These are package ownership and maintenance improvements; the additional entries must be
included when comparing download size. Shrinking the old chunk alone does not establish
a startup speedup. No page-latency claim is made by this migration. The updated bundle
inventory is available with `npm run analyze:bundles`.

The existing dependency audit still reports 46 findings (45 moderate, one high), from the
previously documented Tiptap advisory range and fast-uri/zod dependencies. This migration
adds no audit finding and does not suppress or broadly update unrelated dependencies.
See [editor migration notes](EDITOR-MODULARIZATION.md) for the compiled Tiptap regression.

The shared MJML and Arco APIs have since moved to npm; see the
[email package migration](EMAIL-PACKAGE-MIGRATION.md). Easy Email's prebuilt UI, editor,
source editor and html2canvas remain the next package boundary.

References: [Chart.js integration](https://www.chartjs.org/docs/latest/getting-started/integration.html),
[React Chart.js integration](https://react-chartjs-2.js.org/),
[Emotion cache](https://emotion.sh/docs/@emotion/cache).
