# Source development

Student frontend modules and add-on contracts are documented in [INTERACTIVITY.md](INTERACTIVITY.md).
They load on demand independently of the opt-in admin source build. The maintained frontend
`assets/src/frontend/js/ohmylms.js` handles remaining legacy integrations; its URL is selected by
`Extensions/Interactivity.php`. Original compiled frontend assets remain provenance, so turning off
`OHMYLMS_SOURCE_ASSETS` restores the admin asset selection but does not disable the migrated frontend.

The source build is opt-in. Read ACCEPTANCE.md before enabling it on a working site.

For component conventions, factory contracts and the feature development workflow, read [REACT-DEVELOPMENT.md](REACT-DEVELOPMENT.md). Run `npm run check` before delivering source changes.

## Source layout

- `assets/src/manifest.json`: original SHA-256/size, asset-to-source and numeric module mappings.
- `assets/src/modules/`: 831 extracted Webpack factories. Vendor-bundle modules are under `dist/vendors/`.
- `assets/src/application/`: ordered fragments of factory 1841. `routes.js` is the admin route table, `screens/<feature>/` holds each screen (`*Route.js` is the memoized route wrapper, `*Screen.js` the implementation), and `shared/<topic>.js` are byte-sized slices of shared code, named after the feature they mostly contain (a slice can include helpers for neighbouring features because the original scope was cut by size, not by feature). Fragment order in `manifest.json` is significant; rebuilding must produce an identical `ohmylms.js`.
- `assets/src/extensions/`: authored React components, registry, API client, error boundaries and editor integrations.
- `assets/src/features/`: membership validation and 17 quiz/question React components, including an authored quiz hook/API client and choice-options editor. See QUIZ-SOURCE.md for the compatibility boundaries and tested coverage.
- `tools/quiz-adapters.mjs`: checked integration of native quiz components into the recovered runtime.
- `assets/src/features/quiz-reports/`: ten editable report, grading and question-result components, integrated by `tools/quiz-report-adapters.mjs`. See the feature README for compatibility boundaries and validation.
- `assets/src/features/courses/`: 25 course components, authored editor/create/settings views, load/save and integration hooks, API client and model helpers. See COURSE-SOURCE.md for tested scope and compatibility boundaries.
- `tools/course-adapters.mjs`: checked integration of native course components into the recovered runtime.
- `tools/application-adapters.mjs`: checked AST integration points. Missing/changed patterns fail the build.
- Adapted application builds strip the retired `/license` and `/free-vs-pro` routes and their component implementations. Their recovered fragments remain untouched solely for parity/provenance checks.
- `assets/src/features/certificates/`: 13 editable certificate list, template, editor, design-control and preview React components integrated by `tools/certificate-adapters.mjs`. With the Gamification add-on on, the certificate list is shown as the Certificates tab of the Gamification screen; the recovered components are untouched. See [GAMIFICATION-CERTIFICATES.md](GAMIFICATION-CERTIFICATES.md).
- `assets/src/features/emails/`: 12 editable notification-template settings, personalization, editor, field and responsive-preview React components integrated by `tools/email-adapters.mjs`.
- `assets/src/features/settings/`: 19 editable General Settings route, design, account/privacy, permalink, advanced, payment and migration React components integrated by `tools/settings-adapters.mjs`. Email Settings remains in its dedicated feature.
- `assets/src/features/integrations/`: six editable Add-ons route, card, configuration and provider settings React components integrated by `tools/integration-adapters.mjs`.
- `assets/src/features/webhooks/`: four editable webhook list, details, field-mapping and editor-modal React components integrated by `tools/webhook-adapters.mjs`.
- `assets/src/features/taxonomies/`: the former Categories and Tags routes plus their shared taxonomy modal, integrated by `tools/taxonomy-adapters.mjs`. Categories and tags were replaced by the curriculum and Course Bundles, so `assets/src/extensions/index.jsx` sends `#/categories` and `#/tags` to those pages, shown as the Curriculum and Course Bundles tabs of the Content Hub; the converted source is kept because the parity build still expects it.
- `assets/src/features/setup/`: eleven editable Setup Wizard step, migration/import, controller and route components integrated by `tools/setup-adapters.mjs`.
- `tools/vendor-adapters.mjs`: makes the two installed ProseMirror state export formats share the editor's existing CommonJS instance. The original duplicated generators caused `Adding different instances of a keyed plugin (plugin$)` in both original and recovered lesson screens. The parity build retains the untouched factories; the adapted build repairs the duplication rather than suppressing its error.
- `assets/src/features/content-hub/`: the Content Hub frame and routes, the lesson library, the attach and publish dialogs the syllabus editor reuses, and their pure model modules, wired through `assets/src/extensions/index.jsx` and loaded as the lazy `content-hub` chunk (no recovered component is replaced). See [CONTENT-HUB.md](CONTENT-HUB.md).
- `assets/src/features/curriculum/` and `assets/src/features/bundles/`: authored accordion curriculum editor and Course Bundles manager, registered as SDK admin pages (no recovered component is replaced, so there is no `components.json`). See [CURRICULUM-BUNDLES.md](CURRICULUM-BUNDLES.md). The curriculum editor also hosts syllabuses (skill groups, skills, CSV import), described in [SYLLABUS.md](SYLLABUS.md).
- `build/`: generated output; never edit it.

Recovered fragments are reconstructed compiled syntax, not original JSX. They retain shared lexical scope, minified names, transpiler helpers and some third-party code. Moving one into an ES module requires identifying its imports/exports. Original comments, names and package versions cannot be recovered reliably. The original readable frontend JS and SCSS remain; this first equivalent build uses recovered runtime/CSS snapshots, not an unverified SCSS recompilation.

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

`build` reads recovered/authored source, not the previous compiled app. `dev` watches recovered source/build adapters and the React SDK. `test` compares the unadapted rebuilt AST with the shipped baseline and tests registry/form behavior. Syntax checks use Babel. Source maps embed source contents. The SDK uses WordPress-provided React and generates `extensions.asset.php` through [WordPress dependency extraction](https://developer.wordpress.org/block-editor/reference-guides/packages/packages-dependency-extraction-webpack-plugin/).

`recover`, `split-admin.mjs` and `import-static.mjs` are one-time migration tools, not build steps. Do not regenerate over edits. Installed PHP packages are included; do not clone another e-commerce package over this modified implementation.

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

## Dashboard and analytics source

Eighteen components now build from assets/src/features/analytics through tools/analytics-adapters.mjs. They cover the dashboard, course analytics, earnings reports and supporting controls; the existing course-row source is reused. See the feature README for compatibility boundaries and verification.

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

SDK admin pages wrap their content in `AdminPage` and `AdminCard` from
`assets/src/extensions/AdminPage.jsx`, styled by `assets/css/admin-ui.css`: the native 40px gutter,
20px title row with actions, white 7px cards and the grey-header table look for `table.widefat`.
Every extension route gets the gutter even without the component.

## Known issues

None currently recorded. The source-built vendor bundles used to load duplicate ProseMirror packages, which broke the rich-text editor. `tools/vendor-adapters.mjs` now maps the ESM copies of prosemirror-state, prosemirror-model and prosemirror-transform onto their CJS twins (module ids 42845/37820, 58903/77712, 38262/36553), so each package has a single instance, and the bundled `core/preferences` and `core/keyboard-shortcuts` stores are replaced by the WordPress ones. `SourceAssets::url()` serves `assets/dist/vendors/*` from `build/` like every other asset. If you rebuild after upgrading the shipped vendor bundle, re-check those module ids and export names.

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

## Interactive items in the lesson editor's "/" menu

The legacy rich-text editor's slash menu is a fixed list in a recovered module (`0-66427.js`). `tools/slash-adapters.mjs` patches the slash extension (`0-70181.js`) so the menu also reads `window.ohmylms.extensions.slashGroups()`: commands registered with `registerSlashCommand(id, {label, description, aliases, iconName, action(editor)})` appear in an "Interactive" group (`iconName` is any Lucide icon name; unknown names show no icon).

Interactive content is stored as a shortcode, e.g. `[ohmylms_activity type="reveal" prompt="..." answer="..."]`, and rendered by PHP (`ohmylms_register_activity`, see `includes/Extensions/Activities.php`). Behaviour uses the WordPress Interactivity API (`assets/extensions/reveal.js`, a script module). Only the shortcode is saved, so content filtering never strips markup. Plain shortcode attributes cannot contain double quotes; use a `data='{...}'` JSON attribute for richer data.

To add an activity: register the PHP activity, add a script module if it needs behaviour, and register a slash command whose action inserts its shortcode. Lesson pages render content after `wp_head`; on block themes `Activities::ensure_import_map()` prints the module import map in the footer so late-enqueued modules resolve `@wordpress/interactivity`.
