# Easy Email audit — 29 October 2026

Roadmap step 2, first increment. It answers one question before any code is swapped: is the
recovered Easy Email code a published release, and what has been changed locally? Run
`node tools/easy-email-audit.mjs [version]` to reproduce the numbers. The script downloads the
three npm packages into a temporary directory and compares exported names only. It says nothing
about behaviour.

## What is recovered

| npm package | Recovered factory | Bundle | Size |
| --- | --- | --- | --- |
| `easy-email-core` | 49050 | shared vendors | 212 KB |
| `easy-email-editor` | 58088 | shared vendors | 262 KB |
| `easy-email-extensions` | 47909 | shared vendors | 1,650 KB |
| LMS email designer (authored, compiled) | 31736 | chunk 355 | 148 KB |

Factory 31736 is the LMS's own code, not Easy Email. It wires the editor together and contains
the `easy-email-rich-text-bar` UI. The extensions factory also holds private copies of other
libraries; the inventory lists them (`react-codemirror2` 17190, html2canvas 50587, bridged Arco
3270 and MJML 44098, lodash 4.17.21).

## Result: not a published release

Exported names compared with `4.17.1`, the latest release:

| Package | Recovered | Published | Shared | Only recovered | Only published |
| --- | --- | --- | --- | --- | --- |
| core | 38 | 39 | 37 | `MjmlToJson` | `I18nManager`, `getNodeIdxClassName` |
| editor | 55 | 52 | 45 | 10 rich-text helpers (below) | `AvailableTools`, `Stack`, `TabPane`, `Tabs`, `ToolsPanel`, `getEditorRoot`, `useDarkMode` |
| extensions | 69 | 76 | 67 | `DatePickerField` | `CollapseWrapper`, `Condition`, `DefaultPageConfigPanel`, `MjmlToJson`, `NumberField`, `PresetColorsProvider`, `SelectionRangeProvider`, `ShadowDom`, `setIconsMap` |

What this shows:

- **The public surface is almost the same.** About 90% of the exported names match, so the
  recovered code is Easy Email, not a rewrite.
- **It is not 4.17.1, and no release I checked matches it.** Core 4.10.0, 4.11.0, 4.12.2,
  4.13.0, 4.15.0, 4.16.1 and 4.17.1 all export `I18nManager`. The recovered core does not
  mention it at all. Version 4.14.0 could not be downloaded, so it is unchecked.
  Versions 4.10 and newer also differ in `ShadowDom`, `SelectionRangeProvider` and the dark-mode
  tools. Treat the recovered code as an older release carrying local changes, and do not assume
  any version number.
- **There are local additions in the editor.** `saveSelection`, `restoreSelection`,
  `wrapSelectedTextInSpan`, `findAnchorTag`, `isTextInAnchor`, `makeTextBlockFocus`,
  `getShowPopup`, `setShowPopup`, `getUniqueIdForA` and `useOutsideAlerter` do not exist in any
  release I downloaded. They support the rich-text bar, which also appears in factory 31736.
  `DatePickerField` is the only extra in extensions.
- Core's `MjmlToJson` is exported from core here but from extensions in the published packages.
  Consumers must keep resolving it.

## Risks for a swap

1. **Behavioural drift in rendering.** Core builds MJML from the saved JSON. A different release
   can change attribute defaults or block output, which changes emails that already exist.
2. **Rich-text editing.** Those helpers hold selection and link behaviour. Losing them breaks
   formatting in the designer.
3. **Selection and form contracts.** Extensions and editor share context objects. Upgrading one
   without the other fails at runtime. The earlier migration notes say the same.
4. **Newer releases add providers** (`ShadowDom`, `SelectionRangeProvider`) that the LMS code does
   not mount, so a plain upgrade changes the component tree.

## Plan

Do not swap everything at once. Core is pure (JSON to MJML, no DOM), so it can be verified in
Node, which makes it the first and safest step.

1. **Golden corpus.** Export every saved template shape from the test site (and the built-in
   starter templates) as JSON. Record, for each, the recovered core's `JsonToMjml` output and the
   MJML renderer's HTML. Check the corpus in as fixtures.
2. **Core.** Add pinned `easy-email-core` and compare its output with the fixtures. If it differs,
   find the cause: a missing local patch or a changed default. Then either keep a thin authored
   patch module, or stay on the recovered core for that block type. Swap only when every fixture
   is identical.
3. **Editor.** Move the ten rich-text helpers into an authored module (`features/emails`), then
   take `easy-email-editor` from npm. The save → reopen browser test runs on both builds.
4. **Extensions.** Last, because it is 1.6 MB and holds the private library copies. Take the npm
   package, mount the new providers, and delete the private copies one at a time, starting with
   html2canvas and CodeMirror, which have a single consumer.
5. **Every step:** keep the saved JSON and MJML of existing emails unchanged; verify an email
   saved before the swap reopens, re-saves and sends identically; run the permission checks for
   email routes; compare payload and startup separately, since a package move alone does not make
   anything faster.

Each swap lands as its own commit on `main`, so it can be reverted without touching the others.

## Not done here

No runtime code changed. Nothing was measured in a browser. The golden corpus does not exist
yet; it is the first piece of step 2 proper.
