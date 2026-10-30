# Source development

Student frontend modules and add-on contracts are documented in [INTERACTIVITY.md](INTERACTIVITY.md).
They load on demand independently of the opt-in admin source build. The maintained frontend
`assets/src/frontend/js/ohmylms.js` handles remaining legacy integrations; its URL is selected by
`Extensions/Interactivity.php`. Original compiled frontend assets remain provenance, so turning off
`OHMYLMS_SOURCE_ASSETS` restores the admin asset selection but does not disable the migrated frontend.

The source build is opt-in. Read ACCEPTANCE.md before enabling it on a working site.

The admin is the typed app in `assets/src/app` ([ARCHITECTURE.md](ARCHITECTURE.md)); for its conventions and the feature workflow, read [REACT-DEVELOPMENT.md](REACT-DEVELOPMENT.md). Run `npm run check` before delivering source changes.

## Source layout

The recovered admin (`admin.php?page=ohmylms`, its Webpack factories, fragments, adapters and SDK) was deleted
at the cutover ([CUTOVER.md](CUTOVER.md)); `admin.php?page=ohmylms` only redirects to the new app.

- `assets/src/app/`: the typed admin app, its extension host (`app/extensions/`: registry, `api.ts`,
  `ExtensionBoundary.tsx`, slots, DOM mounting) and the registration entry.
- `assets/src/features/`: authored JavaScript the kept Webpack entries and the app still use: native lesson and
  quiz editors (`learning/`), question editors and models (`question-editor/`, `question-bank/`), assessment
  settings, content-hub helpers, curriculum CSV/API helpers, math runtime, printable papers, Schools and the
  signup fields (`students/registration-api.mjs`). `features/curriculum/icons.json` is read by
  `Curriculum/Icons.php`.
- `assets/src/ui/`: the shared UI kit with the PHP twin in `Design/Ui.php`.
- `assets/src/blocks/`: block editor and view scripts (quiz player, question player, interactive activity, subject
  browsing).
- `assets/src/frontend/`: front-end SCSS (built by `tools/build-frontend-styles.mjs`) and readable front-end
  scripts; `frontend/js/ohmylms.js` is served by `Extensions/Interactivity.php`.
- `assets/src/runtimes/` and `assets/src/static/`: the 79 recovered front-end, block, Elementor/WPBakery,
  theme-support and image assets still shipped. `assets/src/manifest.json` maps each to its output;
  `tools/build-assets.mjs` copies or reprints them. Delete by manifest output, never by folder.
- `assets/src/design-system/admin/public-selectors.json`: the public class names `stylelint.config.mjs` allows.
- `build/`: generated output; never edit it.

## Commands

Use Node 26.0.0 and npm 11.12.1 as pinned in `.nvmrc` and `package.json`.

```sh
npm ci
npm run build
npm run dev
npm test
npm run lint
npm run test:reproducible
```

`build` runs `build:assets` (front-end styles and the manifest outputs), `build:sdk` (the Webpack entries) and `tools/sync-admin-assets.mjs`, which publishes `build/` to `assets/dist`. `dev` watches the manifest sources and runs Webpack in watch mode. `test` runs the `node:test` suites in `tests/js`; `test:app` runs the app's Vitest suites. `lint:source` (`tools/check-source.mjs`) parses every script and checks that authored relative imports resolve. Source maps embed source contents. Entries use WordPress-provided React and generate `*.asset.php` through [WordPress dependency extraction](https://developer.wordpress.org/block-editor/reference-guides/packages/packages-dependency-extraction-webpack-plugin/).

Installed PHP packages are included; do not clone another e-commerce package over this modified implementation.

For the disposable WordPress installation, set `OHMYLMS_TEST_CREDENTIALS` to its external JSON file and run `npm run test:browser` (Chromium; set `OHMYLMS_CHROMIUM_PATH` to use a specific binary), then `php tests/php/extensions-integration.php` with mysqli/mbstring. The PHP suite refuses databases other than `ohmylms_source_test`. Also run `php tests/unit.php` and `php tests/membership-permissions.php`.

## Opt-in and rollback

On a test site:

```php
define('OHMYLMS_SOURCE_ASSETS', true);
define('OHMYLMS_ENABLED_MODULES', ['examples']); // Optional, disabled by default.
```

Without these constants, shipped assets remain active and examples remain disabled. Missing generated files currently fall back to shipped assets; check browser inventories for fallback URLs before release. Turning off `OHMYLMS_SOURCE_ASSETS` restores the old JavaScript. Full rollback restores all three matching plugin folders from the source-baseline snapshot. Restore a database only with its matching code backup. This stage performs no additional prefix migration.

## Fixtures and provenance

`tools/create-test-site.py`, `setup-test-db.php`, `setup-test-user.php` and `tests/fixtures/isolation.php` document isolation. The site has a separate DB, generated administrator and local-only server. HTTP/email are blocked; tests mock known QPay endpoints in memory. The copied database contains site data and stays outside Git.

`tests/baseline-assets.json` is a versionable asset inventory. Ignored `test-results/` contains screenshots, traces and script/style/global/network observations. Never commit credentials, SQL dumps or authenticated traces. New dependencies have lockfile versions/integrity hashes; existing PHP licence files are retained. Recovered third-party JS has asset hashes, but its original package versions and missing extracted notices are not fully verified. Hash pinning is not package provenance; do not invent versions/licences.

## Communities

The Communities admin screen is retired: OhMyLMS has no communities backend, and the screen only worked with
the separate Community add-on's REST routes. Legacy `#/communities` links open Integrations, where the add-on
switch lives. The course `has_community` flag, the integration switch and the enrolment hook remain, so an
add-on can still provide communities and add its own admin page through the extension API
(`registerAdminPage`, see [EXTENSIONS.md](EXTENSIONS.md)). The recovered route and its fragments are deleted
with the rest of the recovered runtime (see [CUTOVER.md](CUTOVER.md)).

## Design tokens and admin page layout

Colors and fonts are chosen under Settings → Design → Colors & fonts and turned into CSS custom
properties by `includes/Design/Tokens.php`. Stylesheets read the variables instead of hard-coding
brand colors or fonts.

- Learner pages: `--ohmylms-primary-color` (`-rgb`, `-hover-color`, `-soft-color`),
  `--ohmylms-heading-color`, `--ohmylms-body-text-color`, `--ohmylms-progressbar-color`,
  `--ohmylms-outline-color` and `--ohmylms-font-family`. The font variable is left undefined for
  "Theme font", so write `var(--ohmylms-font-family, <fallback>)`.
- Admin app: the same brand variables plus `--wp-admin-theme-color*`, `--wp-components-color-*`, the
  Arco `--primary-1…9` scale and `--ohmylms-admin-*` (heading, text, muted, surface, subtle, border,
  radius, gutter, font). The Design screen previews admin colors live; saved values print on reload.
- Fonts: system, the theme font (learner pages) or one of the Cyrillic-capable Google Fonts in
  `Tokens::FONTS`. Values are validated; invalid colors and fonts fall back to the defaults.

Extension admin pages (`registerAdminPage`) render in the new app at `#/extensions/<id>`, inside the app layout.

## Known issues

None currently recorded.

## Block-based Text lessons and the question engine

Current editor: built-in Block lessons (stored as `text`), audio and video lessons open the native WordPress post editor. Course and syllabus links navigate directly to it; old lesson hash routes redirect for bookmark compatibility. The retired embedded Gutenberg workspace, its lesson-only styles and localized settings have been removed. Shared rich-text components remain for assignments and custom lesson types. Add future lesson blocks through native WordPress block registration. See `assets/src/features/learning/README.md` for integration and validation details.

- Instructors author. Students only view and interact.
- Lessons render through `the_content()`. Lesson navigation uses full page loads today.

### Requirements for the Question Bank and Exam Engine (implemented)

The original requirements for inline quiz performance data, skills, attempt history, immutable versions, server-side answer keys, guest history and untouched completion are all implemented in the question bank and exam engine, not in the Text lesson editor, and are merged into `main`. See [ASSESSMENT-ENGINE.md](ASSESSMENT-ENGINE.md) for the design, rollout and limitations. Status per requirement:

| # | Status |
|---|---|
| 1 | Inline checks (`[ohmylms_question uuid="…"]` / `question-check` activity) record practice evidence that feeds the skill dashboard and recommendations. They are inserted as shortcode text from the lesson-editor panel rather than as a native editor node. |
| 2 | Skills are `ohmylms_skill` terms with UUIDs and prerequisites; questions map skills per part. Skills are a core feature (the old creatorlms-skills plugin was removed and the model rebuilt in ohmylms). |
| 3 | Attempts record user, context, question UUID, version, response, correctness and time (attempt items, practice items, grade events). |
| 4 | Immutable question versions; attempts and reports read the version the learner saw. |
| 5 | Answer keys never reach the page; option values are per-delivery tokens graded by REST/form submission. |
| 6 | Guests keep history in localStorage with a server credential; results attach after login through a one-time claim. |
| 7 | Inline checks and practice never call completion hooks. |

## Translations

Mongolian (`mn`) is the only bundled translation. Files in `languages/`: `ohmylms.pot` (template), `ohmylms-mn.po` (source of truth, machine translation that needs a native review), `ohmylms-mn.mo` and `ohmylms-mn-ohmylms.json` (Jed catalog for the registered `ohmylms` admin script handle). Spanish, Dutch and Bengali were removed. After changing strings, refresh the POT with WP-CLI `wp i18n make-pot` (merge PHP and changed authored JavaScript into the existing catalog; do not drop unchanged entries), then:

```sh
python -m pip install polib==1.2.0
python tools/compile-translations.py --locale mn --merge-pot
```

`tools/compile-translations.py` appends missing entries to the `.po` without rewriting existing translations and compiles the `.mo` and the Jed JSON; recompiling an unchanged catalog is byte-identical. Do not edit generated `.mo` or `.json` files by hand.

## Interactive items

`registerSlashCommand(id, {label, description, aliases, iconName, action(editor)})` still registers commands in the extension registry, but the recovered rich-text editor that showed them in its "/" menu was deleted with the legacy admin; no current editor reads them.

Interactive content is stored as a shortcode, e.g. `[ohmylms_activity type="reveal" prompt="..." answer="..."]`, and rendered by PHP (`ohmylms_register_activity`, see `includes/Extensions/Activities.php`). Behaviour uses the WordPress Interactivity API (`assets/extensions/reveal.js`, a script module). Only the shortcode is saved, so content filtering never strips markup. Plain shortcode attributes cannot contain double quotes; use a `data='{...}'` JSON attribute for richer data.

To add an activity: register the PHP activity and add a script module if it needs behaviour. Lesson pages render content after `wp_head`; on block themes `Activities::ensure_import_map()` prints the module import map in the footer so late-enqueued modules resolve `@wordpress/interactivity`.
