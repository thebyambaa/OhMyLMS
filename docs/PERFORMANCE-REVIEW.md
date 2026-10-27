# Performance and unused-code review — 15 October 2026

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
