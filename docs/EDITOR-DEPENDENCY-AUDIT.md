# Editor dependency audit and npm Lowlight migration — 29 October 2026

The production editor now uses **lowlight 3.3.0** and **highlight.js 11.11.1** from npm,
with exact direct versions and a committed lockfile. The Highlight.js version matches the
version string in the recovered core. The original Lowlight package version is not inferred.

## Delivered boundary

- `assets/src/features/editor-packages/lowlight.js` imports the package's `all`, `common`
  and `createLowlight` exports and publishes the explicit editor-package namespace.
- Webpack emits a separate `editor-lowlight.js` entry with source maps. Its dependency
  metadata contains no WordPress script dependencies, and its sources contain no React or
  ProseMirror package copy. WordPress loads it before the recovered admin vendor bundle.
- Factory 7871 becomes a small named-export bridge to that instance. Recovered Highlight.js
  factory 38416 is removed from adapted output because its only consumer was factory 7871.
  A build guard rejects new consumers of the retired core.
- Original recovered sources and the parity build remain available for compatibility checks.
  The code-block extension, its node schema, language attributes and default language are retained.
- Package licenses for Lowlight, Highlight.js and devlop ship next to the generated entry in
  `assets/dist/admin/editor-lowlight.LICENSES.txt`.

The package entry is registered only for the LMS admin screen. Verification on the actual
WordPress site confirmed it precedes the vendor/application scripts, the dashboard renders
without browser errors, and the public homepage loads no `editor-lowlight` script.

## Payload accounting

| JavaScript | Before | After |
| --- | ---: | ---: |
| Recovered shared vendor bundle | 3,313,084 bytes | 2,361,829 bytes |
| Separate npm syntax bundle | — | 951,340 bytes |
| Combined | 3,313,084 bytes | 3,313,169 bytes |
| Combined gzip | 1,024,735 bytes | 1,024,067 bytes |

**This is a maintainability/package migration, not an initial-download speedup.** The combined
payload is effectively unchanged. Keeping syntax in its own entry gives it an independent
cache/version and avoids putting 192 grammars in the SDK used by public/student pages.
The complete registry remains available; no supported language was dropped to shrink a chart.
Webpack's size warnings remain visible. Page latency was not benchmarked again in this increment.

## Compatibility evidence

The recovered and npm implementations expose the same **192 languages** and common-language
set. Tests compare normalized highlighting trees for three samples in every language, including
empty code, Unicode/Mongolian text and HTML-like code. Alias handling, automatic detection,
unknown-language errors and the bridge's shared instance are also checked.

A Chromium test creates the actual recovered Tiptap code-block extension with the npm
highlighter, edits a highlighted code block, serializes its JSON/HTML, destroys the editor,
and reopens the serialized JSON. The document and HTML match. Python, XML and an unknown
language retain their language attributes and exact text. Script-looking code remains escaped
and does not execute. This is a client-side save/reopen contract check, not an authenticated
WordPress lesson save. Built-in Gutenberg authoring remains a separate editor path.

Validation snapshot: 666 JavaScript tests pass with one skipped test; four Chromium cases
pass, including the previous email deferral/rendering/retry checks. Two full builds produce
377 identical artifacts (including the current stylesheet build). Targeted source formatting,
JavaScript lint and PHP syntax pass. The current PHP standards scan still reports three
naming errors and five warnings outside the Lowlight registration changes.

`npm audit` also reports unrelated transitive fast-uri and zod findings outside the added
Lowlight/Highlight.js/devlop runtime dependency tree. No blanket dependency update was applied.

## Icon audit and remaining work

The recovered icon wrapper (94608) selects `customIcons[name]` or `icons[name]` from the Lucide
collection (6165). Eleven recovered module files reference that wrapper. Call-site analysis
finds 27 literal icon names and three runtime-name expressions:

- `e.iconName` in module 82080;
- an option-provided icon or `Pilcrow` in module 93552;
- `e.icon` in module 93552.

The literal list therefore cannot serve as a complete allowlist. Shrinking the collection to
those 27 names would remove valid runtime-provided icons. The current collection and custom
icon precedence are retained. The next icon change needs an explicit registry contract for
those runtime options, followed by selective npm imports and a compatibility policy for
additional icon names. Its original package version has not been established.

The recovered CodeBlockLowlight extension (52183) also embeds a Highlight.js language-lookup
core. Migrating that extension itself is a separate Tiptap/ProseMirror compatibility task;
this increment removes the standalone recovered core used by Lowlight, not every embedded copy.

Run `npm run analyze:editor` to regenerate the exact icon consumers, literal names, language
counts and combined payload sizes. The saved audit is
`state/admin-benchmark/2026-10-29-editor-dependencies.json`.

Package API reference: [Lowlight's official documentation](https://github.com/wooorm/lowlight).
