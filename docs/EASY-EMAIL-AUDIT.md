# Easy Email audit — 29 October 2026

> **Historical.** The recovered email runtime, its tools and tests were deleted on 31 October 2026
> ([CUTOVER.md](CUTOVER.md)); paths and commands below may no longer exist. Current admin code:
> [ARCHITECTURE.md](ARCHITECTURE.md), [REACT-DEVELOPMENT.md](REACT-DEVELOPMENT.md).

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

## Golden corpus and a first behavioural comparison

`tests/fixtures/easy-email-corpus.json` holds 37 templates as plain JSON: an empty page, one page per
block type (all 17 basic and 19 advanced types, each in its valid parent chain), and a composition
with merge tags, Mongolian text, links with ampersands, right-to-left text, page fonts and head
styles. Beside each template is the output the **recovered** core's `JsonToMjml` produces today, in
`production` and `testing` mode. Recording is deterministic (two runs give identical files).

- `node tools/easy-email-corpus.mjs --write` re-records from the recovered core.
- `node tools/easy-email-corpus.mjs --compare [version]` installs a published `easy-email-core`
  in a scratch directory and diffs its output against the recording.
- `tests/js/easy-email-corpus.test.mjs` (in `npm test`) checks that the corpus covers every block
  type and that the recovered core still reproduces the recording byte for byte.
- `tools/recovered-modules.mjs` loads recovered factories in Node. It stubs the browser
  globals the code reads at load time (`window`, `document`, `DOMParser`).

**Result for `easy-email-core@4.17.1`: 68 of 74 renders are identical, 6 differ.** The differences
come from two causes. Each appears for both the basic and the `advanced_` block type:

1. **Footer blocks vanish** (4 renders: both modes, both block types). In `footer` and `advanced_footer` templates the recovered core renders
   an `<mj-hero>`, while 4.17.1 renders an empty wrapper. Existing emails that contain a footer
   block would lose it. **Resolved below:** the footer is a local block that no release has, not a
   registration difference.
2. **Image placeholder removed** (2 renders: `testing` mode, both block types). 4.17.1 fills an empty `src` with a hosted demo
   image at `easy-email-m-ryan.vercel.app` (11 references). The recovered core has none, so an empty
   image stays empty. That is a local change, and the swap must keep it.

The same comparison exposes more local patches that the corpus does not exercise but that the
code shows. The recovered core reads `window.MRM_Vars.images_url` (24 references, none in 4.17.1)
for built-in social icons. The plugin defines that global in `includes/Abstracts/Assets.php`. It also
reads `document.querySelector("html")` at load time to detect right-to-left pages. A published core
does neither, so the swap needs an authored module that supplies both.

## Where saved templates live (checked on 800.local)

Checked through the REST API on the local site, logged in as the administrator:

- **The ten system emails are not Easy Email documents.** `/ohmylms/v1/all-emails` returns flat
  fields per email (`heading`, `subject`, `additional_content` as HTML, `button_text`,
  `footer_text`, `recipient_email`). None contains MJML or block JSON. Their preview is built from
  those fields by `email-template-markup` (factory 1841), not by `JsonToMjml`.
- **Easy Email JSON appears only in the automation email builder.** The gallery and picker
  fragments read `json_content.content` (with `json_content.editor` set to `classic-editor` or the
  advanced builder) and call `JsonToMjml` on it.
- **That storage is not in this plugin.** The builder reads and writes `mrm/v1/...` routes
  (`email/templates`, `automation`). Those belong to a separate plugin; on 800.local
  `/wp-json/mrm/v1/email/templates` returns 404. There are no saved builder templates here to add
  to the corpus, and this plugin's PHP has no table or option for them.

What this changes: the compatibility surface of a swap is the automation builder only, and the
saved JSON is owned by whichever plugin serves `mrm/v1`. Before swapping, find out whether any live
site has builder templates (they would use footer blocks and demo images the corpus cannot predict),
and take a sample of their `json_content` for the corpus. The system emails are unaffected.

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

1. **Golden corpus.** Built (above) for block types and one composition. Still to add: real
   saved templates from a site that runs the `mrm/v1` backend and the built-in starter templates, and the recorded MJML
   renderer's HTML. Saved-template conversion from MJML needs a DOM, so it belongs in the browser
   test suite.
2. **Core.** Done: see "Core swap" below. A pinned `easy-email-core` sits behind an authored
   wrapper that restores every difference found, and all 74 recorded renders are identical.
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

No runtime code changed, and nothing was measured in a browser. The corpus covers rendering from
JSON only. It does not cover `MjmlToJson` (needs a DOM), the editor UI, or real saved templates.

## Core swap (roadmap step 2): findings and result

### Why footer blocks vanished

Footer blocks are not a registration difference. No published release has them: `easy-email-core`
3.2.3, 4.0.0, 4.4.5, 4.9.0, 4.10.0, 4.13.0, 4.15.0, 4.16.5 and 4.17.1 define no footer block type
and no `BasicType.FOOTER` or `AdvancedType.FOOTER` (the word only appears in a list of HTML tag
names). Registering blocks cannot help, because there is nothing to register. The footer is a Mail
Mint addition: it renders an `mj-hero`, its default content reads `MRM_Vars.business_basic_settings`
and `MRM_Vars.address`, and the recovered core lists it as a valid parent of the other content
blocks. The wrapper carries that definition, built on the package's own hero block.

Correction to the export table above: the recovered core exports 39 names, 38 of them shared with
4.17.1. The only recovered-only name is `MjmlToJson`; `getNodeIdxClassName` is exported by both (the
audit script mis-read it). The package also exports `I18nManager` and a minified alias `t`.

### Everything else the recovered core changed

The corpus only exercises 2 of these. A differential run of 888 further renders (hidden blocks,
iteration, conditions, class names, boolean attributes, page head settings, beautified output, both
modes) found the rest, and `tests/js/easy-email-core.test.mjs` now checks each against the recovered
core:

| Area | Recovered core | 4.17.1 | Restored by |
| --- | --- | --- | --- |
| Footer blocks | `footer`, `advanced_footer`, drawn as `mj-hero`; listed as valid parent of content blocks | none | `footer.mjs`, `block-types.mjs` |
| Empty image in `testing` mode | `src=""` | hosted demo image | `ImageManager` values |
| Default images | all empty except social icons | demo images for accordion, hero, carousel, social, carousel arrows | `ImageManager`, `recovered-defaults.mjs` |
| Social icons | `MRM_Vars.images_url` + file name, read when the core loads; `MRM_Vars.business_social_settings` replaces the list | demo images | `host-settings.mjs`, `recovered-defaults.mjs` |
| Right-to-left | new text blocks align right when `<html dir="rtl">` at load | always left | `recovered-defaults.mjs` |
| Block labels and texts | `MRM_Vars.mint_trans` (names at load; text, button and the four empty-container hints at use) | English via `I18nManager` | `recovered-defaults.mjs`, `render.mjs` |
| Default values of new blocks | Arial page font, white section, column, wrapper backgrounds, different button, hero and social defaults | published defaults | `recovered-defaults.mjs` |
| Hidden blocks | `data.hidden` ignored | skipped | `render.mjs` |
| Boolean attributes | not printed | printed as `name="true"` | `render.mjs` |
| Condition tags | falsy is `x == nil`; variable suffix is 5 characters | `x == nil or x == false`; UUID | `render.mjs` |
| `beautify: true` | js-beautify 1.14.11 or newer output | older inlined js-beautify, different line breaks | `render.mjs` with pinned js-beautify 1.15.4 |
| Block set and order | no `advanced_table`; footers after social | has `advanced_table` | `block-types.mjs`, `core.mjs` |
| `MjmlToJson` | exported from core | exported by easy-email-extensions | `mjml-to-json.mjs` |

`beautify: true` matters: the template gallery, picker and designer all call it. js-beautify 1.13.5,
1.14.0 and 1.14.4 do not match the recovered output; 1.14.11, 1.15.1 and 1.15.4 match all 70 non-footer
corpus renders (the test also covers the footers).

One deliberate difference remains. The recovered core handed every new social block the same
element array, so a payload merged into an advanced social block changed the defaults of every
later social block. The wrapper creates a fresh list each time. Saved emails are not affected; only
the icons of a social block added after such a call could differ from the old behaviour, and the old
behaviour was an accident.

### Result

`node tools/easy-email-corpus.mjs --wrapper` renders all 74 recorded outputs identically. The wrapper
lives in `assets/src/shared/easy-email/`. `createEasyEmailCore()` builds it when the first consumer
requires factory 49050, because the recovered core read `MRM_Vars` and the `dir` attribute at that
moment and WordPress defines `MRM_Vars` after the scripts that precede the application.

Not verified here: the designer UI in a browser, `MjmlToJson` from markup (it needs `DOMParser`; only
the parsed-tree path is compared), and emails saved by a live `mrm/v1` backend.
