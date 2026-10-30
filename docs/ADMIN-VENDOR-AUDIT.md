# Admin vendor audit — 29 October 2026

> **Historical.** The recovered admin and the vendor code audited here were deleted on 31 October 2026
> ([CUTOVER.md](CUTOVER.md)); paths and commands below no longer exist. The current inventory of bundled and
> vendored libraries (npm packages, Slick, html2pdf, Composer) is in
> [third-party libraries](VENDOR-PACKAGE-MIGRATION.md).

The shared MJML and Arco APIs now use lazy npm packages; see the
[email package migration](EMAIL-PACKAGE-MIGRATION.md) for scope and saved-template checks.

Chart.js and Emotion have since been replaced with npm packages; see
[the vendor package migration](VENDOR-PACKAGE-MIGRATION.md) for current ownership and checks.

For the current editor package boundaries and payloads, see [editor modularization](EDITOR-MODULARIZATION.md).
The figures below describe earlier stages.

The subsequent [editor dependency audit](EDITOR-DEPENDENCY-AUDIT.md) moves recovered Lowlight
to a separate npm entry. The shared vendor file is now 2,361,829 bytes, plus a 951,340-byte
syntax bundle; combined size is effectively unchanged by that package migration.

The follow-up [dashboard benchmark and email-runtime split](DASHBOARD-BENCHMARK.md)
reduces the current shared bundle further to 3,313,084 bytes. The first-increment figures
below describe the earlier Arco/html2canvas split; see the follow-up for current measurements.

The initial shared vendor bundle registered 520 Webpack factories and occupied 7,242,969
bytes (2,101,267 gzipped). Authored feature screens already use lazy SDK chunks; this audit
examines libraries left in the recovered shared bundle. Factory registration downloads and
parses code even when a library has not yet been imported and executed.

## Delivered: email designer libraries load with the designer

Arco Design React (factory 3270) and html2canvas (factory 50587) have consumers exclusively
in the existing email designer chunk 355. The recovered application already waits for that
chunk before importing its email designer. The adapted build now registers these two
factories in that chunk, leaving the original functions, exports and dependencies intact.
They remain recovered dependencies; this change does not migrate them to npm.

| Shared vendor payload | Before | After | Reduction |
| --- | ---: | ---: | ---: |
| JavaScript bytes | 7,242,969 | 6,054,793 | 16.4% |
| Gzip bytes | 2,101,267 | 1,782,466 | 15.2% |

After this first increment, the initial shared bundle registered 518 factories. Deferred code is downloaded when the
email designer opens, rather than removed from the application. Chunk 355 is now 1,418,195
bytes. The published shared bundle also uses the existing adapted production printer and
WordPress/ProseMirror deduplication adapters, so the size difference includes those effects.
The original recovered files and parity build retain their original layout.

`tools/email-vendor-chunks.mjs` checks all recovered factory fragments and runtime sources
for either ID outside the email chunk. New external references, even in a bound import or
context map, fail the adapted build. The check is deliberately conservative: an unrelated
numeric occurrence blocks relocation until reviewed. The shared vendor script uses a file
modification version, and the application's email chunk URL carries a source-content version
to avoid combining the new shared bundle with an old cached email chunk.

## Retained dependencies and next candidates

| Recovered factory | Identified code | Reason retained |
| --- | --- | --- |
| 44098 | MJML rendering runtime | Deferred in the follow-up increment |
| 47909, 58088 | Email editor UI and core | Deferred in the follow-up increment after separating certificate imports |
| 7871 | Lowlight and bundled syntax languages | Referenced by the recovered rich-text code block extension |
| 6165 | Lucide icon exports | Referenced by the recovered editor UI |
| 17190 | Email source/code editor | Now registered with the deferred email chunk |

The certificate/email coupling and MJML deferral described here were completed in the follow-up.
A complete npm replacement still needs explicit exports and
compatibility tests for the editor schema and saved templates. No library was deleted solely
because it appeared unused by the dashboard. Editor, Chart.js and Emotion script handles are
retained in this increment.

## Verification and limits

- Production build succeeds; 662 JavaScript tests pass with one skipped test.
- Tests compare every factory across the original and relocated bundles, accounting for the
  separately tested existing vendor adapters. Module IDs and chunk registrations are preserved.
- Two Chromium tests use the shipped Webpack loader with application startup replaced only
  in memory. They verify no initial email-library registration, on-demand loading, a working
  Arco button, the html2canvas export, a single versioned chunk request and retry after failure.
- HTTP requests to the test site return both updated files with matching local bytes.
- Formatting and source-contract checks pass. PHP standards checking of AdminAssets.php
  reports three existing snake_case errors for `$offsetSeconds` at lines 230, 234 and 235.
- Reproducibility checks confirm 228 generated artifacts are identical across two builds.

Sizes are measured file and gzip sizes, not dashboard latency. These isolated browser tests
do not exercise authenticated template saving or a full email campaign workflow. Run
`npm run analyze:bundles` to regenerate sizes and the exact email dependency references.
