# Dashboard latency investigation — 29 October 2026

The main intermittent delay is **resolving the local hostname `800.local`**, rather than
several seconds of LMS rendering. DNS also holds PHP workers during background loopback
requests. This corrects the earlier interpretation of the dashboard benchmark's first-byte column.

## Evidence

| Measurement | Slow request | Subsequent cached-name request |
| --- | ---: | ---: |
| Browser DNS lookup | 6,037 ms | 0 ms |
| Actual server wait (`responseStart - requestStart`) | 772 ms | 697 ms |
| Navigation to first byte | 6,813 ms | 700 ms |
| Dashboard heading | 14,913 ms | 1,396 ms |

An independent static-file HEAD request reproduced the delay without running PHP:

| cURL request to the same existing static file | DNS | Total |
| --- | ---: | ---: |
| Normal hostname | 4,023 ms | 4,024 ms |
| IPv4-only hostname | 2,015 ms | 2,031 ms |
| Same hostname pinned to its existing `127.0.0.1` mapping | <1 ms | 1.3 ms |
| Same hostname pinned to its existing `::1` mapping | <1 ms | 1.3 ms |

Windows' own hostname lookup took 4,032 ms; `localhost` took 12 ms. The hosts file and DNS
cache already contain both loopback mappings for `800.local`, and both addresses respond
quickly when name lookup is bypassed. This isolates a Windows/local-hostname resolution
problem; it does not identify the exact resolver/network-service implementation causing it.

## Background work magnifies the delay

A temporary, query-gated MU-plugin measured WordPress phases and outbound HTTP requests
on authenticated dashboard visits. It recorded no cookies, SQL text or URL query parameters.

- One page reached its footer in 666 ms, but its PHP process finished at 8,787 ms.
- The Action Scheduler background POST to the site's `admin-ajax.php` took 4,013 ms.
- The WP-Cron background POST to the site's `wp-cron.php` took 4,016 ms.
- Both were marked non-blocking. Their DNS/connect preparation still occupied the caller.
- A request with neither dispatch reached the footer in 619 ms and shutdown in 696 ms.

The Local nginx configuration has two PHP upstream workers. Holding a worker for background
request preparation can therefore delay subsequent requests. On the slow browser visit,
the app-data and dashboard REST requests took 4.7 and 7.8 seconds respectively. Worker
contention is a plausible contributor to those API delays; individual queued API requests
were not separately profiled, so that attribution is not established request by request.

WordPress's installed HTTP transport documents timeout limitations while DNS is resolving,
and its cron implementation already dispatches at shutdown. Disabling cron or scheduled
actions would hide the symptom while interrupting work; no such change was made.

## Recommended remediation

Change this site's domain in Local from `800.local` to a non-`.local` name, such as
`800.test`, then repeat both DNS and authenticated dashboard measurements. Use Local's
site-domain workflow so its router, hosts entries and WordPress URLs remain consistent.
Local's [official Windows troubleshooting guide](https://localwp.com/help-docs/troubleshooting/troubleshoot-windows-local/)
specifically lists changing `.local` as a possible performance remedy. This has not been
tested for this particular site yet, so the improvement from that change is not guaranteed.

The IP-pinned requests above were diagnostic comparisons, not a permanent DNS override.
The site's domain and Windows network settings were not changed during this investigation.

## Diagnostic correction and cleanup

`assets/js/admin-benchmark.js` (deleted with the recovered admin at the cutover) reported actual server wait, DNS, connection time and
navigation-to-first-byte separately, along with slow resource requests. The old `ttfbMs`
values in `2026-10-29.json` were navigation-to-first-byte values; they must not be interpreted
as isolated PHP/server time. New results use the corrected definition.

The temporary MU-plugin and profiler were removed after recording the evidence. Sanitized
measurements are saved in `state/admin-benchmark/2026-10-29-investigation.json`.
A browser regression check verifies the timing definitions and omission of query parameters.
