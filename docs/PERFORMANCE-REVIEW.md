# Performance and unused-code review — 15 October 2026

## npm-backed editor syntax — 29 October 2026

[The editor audit](EDITOR-DEPENDENCY-AUDIT.md) replaces recovered Lowlight and its standalone
Highlight.js core with pinned npm packages in a separate admin-only entry. All 192 languages
and sample highlighting trees are preserved. Combined syntax/vendor payload remains about
3.31 MB; this is a package/provenance boundary rather than a measured loading-speed gain.

## Local DNS latency — 29 October 2026

[The follow-up investigation](DASHBOARD-LATENCY-INVESTIGATION.md) separates DNS from
server wait: a slow browser navigation spent 6,037 ms resolving `800.local` and 772 ms
waiting for the server. Static-file comparisons reproduce 4-second DNS delays and complete
in about 1 ms with the existing loopback address pinned. Slow loopback resolution also
keeps PHP workers busy during cron and scheduled-action dispatch. Earlier first-byte
figures included DNS and should not be treated as isolated server execution time.

## Deferred MJML/email runtime and measured dashboard — 29 October 2026

The next email-runtime split reduces the shared vendor bundle from 6,054,793 to 3,313,084
bytes (gzip: 1,782,466 to 1,024,735 bytes). The actual logged-in dashboard measurements
were variable and did not demonstrate a latency improvement. See
[the full benchmark](DASHBOARD-BENCHMARK.md) for all six samples, cache conditions,
the synchronous model boundary, regression checks and server-response investigation target.

## Deferred email designer vendors — 29 October 2026

The shared vendor bundle now defers Arco Design React and html2canvas into the existing email
designer chunk. Its shipped size decreases from 7,242,969 to 6,054,793 bytes; gzip decreases
from 2,101,267 to 1,782,466 bytes. See [the dependency audit](ADMIN-VENDOR-AUDIT.md) for the
build guards, cache handling, tests and remaining dependency boundaries.

## Compact admin output — 29 October 2026

The authored SDK already splits feature screens into on-demand chunks. The recovered main
admin application was still shipped with formatted whitespace. `tools/build-assets.mjs`
now prints adapted (`--extensions`) JavaScript compactly, retaining comments, embedded
readable sources and source maps. The parity build retains its readable output. No bindings
are renamed and no expressions are optimized by this change.

| Main admin application | Before | After | Reduction |
| --- | ---: | ---: | ---: |
| JavaScript bytes | 5,502,504 | 3,206,926 | 41.7% |
| Gzip bytes | 684,160 | 532,299 | 22.2% |

These are file sizes, not measured page-load times. Gzip transfer savings depend on server
compression. The shipped vendor bundles remain unchanged. Run `npm run analyze:bundles`
to inspect current shipped admin and vendor sizes; it does not include WordPress dependencies
or lazy chunks and is not a complete page-transfer estimate.

Validation compared the entire pre-change and compact application after parsing and canonical
printing: executable syntax was identical. All 1,098,736 mapped positions with source locations
resolved to embedded source content. The production build succeeded. The existing SDK browser
suite passed its failed-chunk recovery scenario; two other scenarios stopped at the existing
assertion that the registered `performance` page must be absent, before exercising feature
navigation. This does not establish authenticated site or editor save/reopen acceptance.

The full JavaScript suite passes with 660 tests and one skipped test. The label-contract test
now reads translation calls from the syntax tree rather than depending on spaces in the
generated bundle. Formatting and source-contract checks pass. An HTTP request to the test
site returned the compact shipped application with matching bytes.

## Database evidence

An isolated WordPress database fixture uses two syllabus sources, 96 source objectives, 12 shared skills and 96 mappings. Each case starts with the process-local object cache flushed; no live-site records were changed. All three before/after JSON response SHA-256 hashes match.

| Operation | Queries before | Queries after | Post reads before → after | Example elapsed time before → after |
| --- | ---: | ---: | ---: | ---: |
| Mapping summary | 48 | 6 | 18 → 0 | 25.72 ms → 5.21 ms |
| Roadmap library | 665 | 103 | 216 → 24 | 951.75 ms → 57.03 ms |
| Focused objectives | 36 | 36 | 0 → 0 | 17.00 ms → 18.69 ms |

Timings are local diagnostic samples, not production benchmarks. The unchanged objective result is included to avoid attributing improvements to unaffected paths.

`SkillMappings::describe()` previously expanded complete skill descriptions, including resource relationships, before returning only `id`, `name` and `code`. It now reads those same identity fields directly. `for_skill()` primes endpoint terms and metadata in one batch while retaining the original two ordered mapping queries. WordPress cache invalidation remains responsible for freshness; no custom persistent cache was added.

Roadmap preview previously expanded an entire shared skill each time it encountered a target. It now checks the shared marker/term identity once per unique target and returns the same public preview fields. Existing mapping semantics, revision checks, evidence rules and public signatures remain intact. Regression checks verify the summary contract, absence of post lookups and immediate visibility of a renamed target.

## On-demand mathematics payload

Teacher syntax inspection now uses the package's supported `LatexSyntax` entry rather than loading the complete symbolic Compute Engine. This affects the optional syntax-check action, not initial-page loading or grading. A comparison of 35 valid/malformed samples retained validity decisions, and nested parser errors have a regression check. Authored LaTeX and server-side assessment logic remain unchanged.

The production advisory chunk decreased from 4,125,127 to 469,339 bytes (88.6% smaller); gzip decreased from 1,173,273 to 126,379 bytes (89.2% smaller). The administrator launcher is a separate 1,028-byte lazy chunk; its 9,976-byte panel loads only when opened.

The separate MathLive editor runtime remains about 808 KiB and is loaded on demand. Its webpack size warning remains visible. Splitting or raising thresholds would not reduce the required editor code, so no budget suppression or dependency deletion was used. Large recovered application assets and the math DOM observer remain further investigation opportunities; they were not rewritten without evidence or contract coverage.

## Proven unused authored components

Removed `ComparisonColumn.jsx`, `MappingConnections.jsx`, `RoadmapBuilder.jsx`, `SharedSkillDetails.jsx`, `SharedSkillList.jsx` and `StudyPlanAudit.jsx` from the curriculum feature. Repository searches found no consumers; the explicit public feature registries export only `CurriculumPage`, `SyllabusPage` and `ComparisonWorkspace`; all six were absent from the shipped curriculum source map. They belong to the replaced three-column interface. Backend auditing, authoring endpoints, MCP tools and extension hooks are retained.

No dependency, recovered third-party runtime or public PHP API was removed merely because static searches lacked references. Generated assets were rebuilt from authored source. Existing unrelated edits and the subsequent canvas wire-label change were preserved.

## Standards and limitations

The repository already has substantial WordPress standards debt. The earlier full baseline reported 7,019 JavaScript errors plus 30 warnings, 1,630 CSS errors, six existing formatting failures, and existing PHP violations. Targeted checks on the new assistant and worker changes are reported separately. The chat and queue deliberately use prepared MySQL advisory locks to serialize state across PHP processes; PHPCS warns about direct uncached database calls for those locks. Caching a lock query would invalidate the concurrency guarantee. These warnings remain visible rather than being hidden by a baseline or rule suppression.
