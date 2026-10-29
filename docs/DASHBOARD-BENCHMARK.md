# Dashboard measurements and deferred email runtime — 29 October 2026

Follow-up: [the latency investigation](DASHBOARD-LATENCY-INVESTIGATION.md) identified
slow local DNS resolution. The original first-byte figures below include DNS time; they
are not isolated server wait. The opt-in diagnostic now reports those measurements separately.

The shared vendor bundle decreases from **6,054,793 to 3,313,084 bytes (45.3%)**.
Gzip decreases from **1,782,466 to 1,024,735 bytes (42.5%)**. MJML rendering,
the email editor UI/core, its export facade and source editor now travel in the existing
email chunk, alongside the previously deferred Arco and html2canvas modules.
That chunk is 4,159,929 bytes (1,101,849 gzipped), downloaded when a template preview,
template gallery or designer needs it. This is deferred work, not deletion of functionality.

The small email block model stays in the initial bundle because the recovered application's
default template definitions use it. Its MJML parser import is deferred until conversion.
Two unused side-effect imports in the classic certificate code are removed by the adapted
build; certificate components do not use their exports. Template preview/gallery components
now use the existing SDK loading/error boundary, ensuring their synchronous conversion code
runs after the matching libraries load. Original recovered sources and parity output are intact.

## Actual page measurements

Three samples per version were taken in the logged-in in-app browser at the dashboard.
Normal browser caching was used. The first after sample downloaded changed code; later
samples reused it. There was no network/CPU throttling, and background development work
was not controlled. These are diagnostic samples, not a controlled performance experiment.

| Version/sample | Navigation to first byte | First contentful paint | Dashboard heading | Page load |
| --- | ---: | ---: | ---: | ---: |
| Before 1 | 626 ms | 852 ms | 1,165 ms | 1,147 ms |
| Before 2 | 6,767 ms | 6,952 ms | 14,849 ms | 14,885 ms |
| Before 3 | 663 ms | 848 ms | 1,185 ms | 1,141 ms |
| After 1 | 2,738 ms | 2,948 ms | 6,803 ms | 6,843 ms |
| After 2 | 655 ms | 868 ms | 1,390 ms | 1,255 ms |
| After 3 | 6,611 ms | 6,784 ms | 10,685 ms | 10,724 ms |

**These timings do not demonstrate a dashboard latency improvement.** The before median
heading time was 1,185 ms and the after median was 6,803 ms, with different cache conditions
and substantial server-response variability. The smaller startup payload is established;
its effect on page latency needs controlled cold/warm measurement. Investigate the intermittent
6–7 second navigation stalls before making a speed claim. The follow-up attributes the
largest first-byte stalls to local DNS resolution.

The heading metric means the Overview h1 has rendered, not that every chart/API request has
completed. The dashboard rendered correctly and reported no browser errors. Its resource
inventory did not request email chunk 355. Source asset overrides were enabled on this site,
so the browser used `build/` assets. The matching generated files were also published to `assets/`.
Machine-readable samples are in `state/admin-benchmark/2026-10-29.json`.

## Repeat the measurement

An administrator can add `&ohmylms_benchmark=1` before the dashboard URL hash. A read-only
measurement panel captures navigation/paint timings, script sizes and long tasks until five
seconds after load. Normal visits do not load this diagnostic script. URLs in its report omit
query parameters. It does not change settings, learning records or template data.

## Validation

- Production build and reproducibility check pass: 228 artifacts match across two builds.
- JavaScript suite: 663 passing tests, one skipped.
- Three Chromium cases cover library deferral/interaction, real saved-template JSON rendering,
  identical HTML against the original renderer/model, unchanged JSON, conversion back to JSON,
  and chunk failure/retry.
- SDK changes and diagnostic script pass targeted ESLint; formatting and source contracts pass.
- PHP syntax passes. The PHP standards check retains three pre-existing `$offsetSeconds`
  naming errors in AdminAssets.php. A narrowly documented nonce-check exception covers the
  read-only diagnostic query parameter; it does not authorize state changes.

An authenticated campaign save/send workflow was not performed. No emails were sent.
