# Stylesheet ownership (roadmap step 7)

The table below records the 29 October 2026 snapshot on `main`; see the 30 October cleanup and the 31 October legacy admin deletion below for subsequent changes. Sizes are uncompressed bytes of the files in the plugin directory. Nothing here claims a speed-up; page-load timing was not measured.

## Who owns what

| Surface | Stylesheet | Bytes | Owner (authored source) | Notes |
| --- | --- | ---: | --- | --- |
| Learner, every LMS page | `assets/css/oml-ui.css` | 17,000 | `Design/Ui.php` (shared tokens and components) | Dependency of every frontend module |
| Learner, every LMS page | `assets/css/quiz-a11y.css` | 1,416 | `Assets/FrontendAssets.php` | |
| Learner `core` | `assets/css/frontend/core.css` | 51,338 | `assets/src/frontend/scss/base`, `header`, `login` | Loaded on every LMS page |
| Learner `catalog` / `course` / `lesson` | `frontend/catalog.css`, `course.css`, `lesson.css` | 56,013 / 121,829 / 105,241 | `assets/src/frontend/scss/{archive,single,lesson}` | Chosen by `Design/FrontendStyles::groups()` |
| Learner `checkout` / `thankyou` / `account` / `membership` | `frontend/*.css` | 36,512 / 12,995 / 84,565 / 8,352 | `assets/src/frontend/scss/{checkout,thankyou,dashboard,membership}` | |
| Learner RTL | `frontend/rtl.css` | 1,209 | `assets/src/frontend/scss/rtl` | Only when `is_rtl()` |
| Learner, other features | `student-hub.css` 49,070, `quiz-player-templates.css` 43,298, `practice.css` 23,752, `paper.css` 21,618, `student-dashboard.css` 17,373, and about 30 smaller files | | Each feature's PHP class enqueues its own | Referenced from `includes/` or `templates/` |
| Public compatibility URLs | `assets/css/style.css`, `style-rtl.css` | 476,556 / 1,215 | Generated from the same SCSS by `tools/build-frontend-styles.mjs` | Not a second colour source and not enqueued by the plugin. Kept so old URLs still work. Recolour the authored SCSS and regenerate; do not hand-edit these files |
| Admin shell | `admin-design-system.css` 17,002 (plus inline variables from `DesignSystem.php`), `global.css` 596 (all wp-admin, LMS menu) | | `Design/DesignSystem.php`, `Assets/AdminAssets.php` | |
| Admin app (`page=ohmylms-app`) | `assets/dist/admin/admin-app*.css` and its feature chunks | | `assets/src/app/styles`, feature CSS in `assets/src/app` | Built by Webpack; see [ARCHITECTURE.md](ARCHITECTURE.md) |
| Retired legacy admin (`page=ohmylms`) | `assets/dist/css/admin/oml-{main,rtl}-*.css`, `assets/css/oml-ui-*.css`, `admin-ui.css`, `tailwind.css`, the `admin-styles.js` loader | was 1,430,902 + 77,274 + 4,968 + 22,069 | deleted 31 October 2026 (see below) | |
| Question editing styles | `question-editor.css` 17,672, plus nine feature files (`question-stage`, `quiz-design`, `quiz-overview`, `course-objectives`, `skill-matrix`, `course-editor-ux`, `course-outline-editor`, `subject-profiles`, `library-curriculum`) totalling 55,727 | | `Design/Tokens.php::enqueue_admin()` | Were requested on the legacy screen; re-targeted with that screen check |
| Gutenberg editor | `assets/blocks/css/*`, `question-editor.css` and `paper.css` (native lesson editor) | | `Blocks/BlocksManager.php`, `Learning/NativeLessonEditor.php` | WordPress's own editor styles are not copied |

## WordPress core styles

- The build no longer ships a copy of `wp-block-editor` (the former `main-085-block-editor.css`, 244,252 bytes). The admin application declares `wp-block-editor` as a dependency instead.
- `AdminAssets::load_scripts()` used to enqueue `wp-interface` and `wp-block-editor` on every wp-admin page. It now does so only on `toplevel_page_ohmylms`. On the WordPress Dashboard of the test site, `wp-block-editor` (124,331 bytes) and `wp-preferences` (1,047 bytes) are no longer requested; the application screen still requests them. `wp-components` is still requested on the Dashboard by other enqueue paths and was not changed.

## What changed in this step

| Change | Bytes |
| --- | ---: |
| Orphaned generated admin modules deleted (96 in `assets/css`, 87 in `assets/dist/css/admin`, none referenced by `modules.json`; rebuilding from the manifest reproduces `modules.json` and `admin-ui.css` byte for byte) | 592,543 (183 files) |
| `assets/dist/css/vendors.css` retired and listed in `retiredAssets`; nothing in `includes/` or the shipped scripts loads it, and 5,522 of its 5,559 selectors are already in the admin modules | 578,256 (also its copy in `assets/src/static`) |
| Recovered `assets/src/static/css/style.css` and `style-rtl.css` removed with their manifest entries; the shipped files come only from `build-frontend-styles` | 1,024,758 |
| `main-085-block-editor.css` moved from `assets/src/admin-styles` to `tests/fixtures/admin-styles/wordpress-block-editor.css`; only the parity test uses it | 244,252 moved |
| 15 stylelint errors in authored SCSS fixed (empty blocks, `currentcolor`, comment spacing); the compiled output is identical apart from the keyword case | 0 |
| `stylelint.config.mjs` now ignores the generated `style.css`, `style-rtl.css` and `frontend/**` explicitly; they were previously ignored only through the retired manifest entries | |

Earlier in step 7 (commit `5d4cbc26` and predecessors), measured from git: frontend `style.css` 537,751 plus `learning-pages.css` 269,566 plus `oml-legacy-buttons.css` 2,929 became the page modules above; admin `ohmylms.css` 1,648,842 became 1,430,902; `admin-ui.css` 89,726 became 77,274.

## Competing declarations, measured

- Frontend: no exact duplicate rules across the eight page modules. Page rules that target `.ohmylms-button` set layout only (`width`, `display`, `gap`, `max-width`); none sets colour, background, border or padding. After the 30 October cleanup, the layout-3 progress bar retains its allocated width and uses the shared component appearance.
- The cleanup rechecked exact authored rules using layer, direction, loading group and conditional scope. It removed eight earlier copies while retaining their later copies. A regression check now rejects exact duplicates within the same loading scope. Vendor rules and distinct states/conditions are not collapsed.
- Admin button rules outside the shared layer that set skin properties: 6 (icon picker, resource actions, streak milestone fields); all are component-specific sizing or selected-state colours.

## Public selectors kept

All `.ohmylms-*` frontend classes used by `templates/`, Elementor, Bricks and add-ons, including `.ohmylms-button`, `.ohmylms-form-group`, `.ohmylms-progressbar*` and the `.ohmylms-page` scope, stay in the shared component styles and page modules. The old `style.css` and `style-rtl.css` URLs still work. No class was deleted in this step.

## Cleanup verified 30 October 2026

- WordPress toolbar entries `visit-ohmylms-courses-class` and `visit-ohmylms-memberships-class`
  were incorrectly triggering course and commerce CSS on Curriculum. Automatic detection now scans
  `#wpbody-content` (or the standalone main element) and body-level portal containers. WordPress
  menus and the toolbar are excluded; explicit feature loading is unchanged.
- Eight earlier exact rules were removed from `main-005-courses.css` and `main-066-quizzes.css`.
  Later identical copies remain in the same loading scope, preserving final cascade priority.
- Layout-3 course progress no longer overrides shared height, fill, track, radius or animation.
- The design language, token guide and legacy audit describe the actual modular build and aliases.
- Browser checks cover menu false positives, dynamic content, portals, explicit loading, retry,
  LTR/RTL loading order, shared progress across themes and the component gallery.

Live Curriculum verification: plugin stylesheet links with an active URL fell from **127 to 82**;
all 45 course/commerce slots remained deferred. The page rendered with no console errors. The rebuilt
admin compatibility CSS shrank by 947 bytes (1,430,902 to 1,429,955); the course module shrank by
395 bytes (121,829 to 121,434). These are byte/request counts, not a measured load-time improvement.

Validation: 35 relevant browser checks, nine focused Node checks, 364 PHP design-token checks,
PHP admin-style registration checks, CSS lint and the modified loader's JavaScript lint passed.

## Legacy admin CSS deleted (31 October 2026)

The recovered admin was deleted, so the build of its stylesheets went with it:

| Removed | Files | Bytes |
| --- | ---: | ---: |
| `assets/src/design-system/admin/*.css` (main, UI and RTL modules); `public-selectors.json` stays for `stylelint.config.mjs` | 281 | 439,092 |
| `assets/src/admin-styles/` (module manifest and the email shell, email editor and automations vendor modules) | 4 | 1,161,090 |
| `assets/src/static/css/tailwind.css` and its manifest entry (only `toplevel_page_ohmylms` loaded it) | 1 | 22,069 |
| `tools/build-admin-styles.mjs`, `tools/modularize-admin-css.mjs`, `tools/modularize-admin-overrides.mjs`, `assets/js/admin-styles.js`, `tests/js/admin-styles.test.mjs`, `tests/browser/admin-styles.spec.cjs`, `tests/fixtures/admin-styles/` | 7 | |

Front-end SCSS, `oml-ui.css`, the learner CSS, block styles, theme-support CSS and the app's own CSS are unchanged.
The shipped outputs (`assets/dist/css/admin`, `assets/dist/css/chunks`, `assets/css/oml-ui-*.css`, `admin-ui.css`,
`tailwind.css`) and their PHP handles are removed by the build and PHP steps of [CUTOVER.md](CUTOVER.md).

## WordPress list tables and admin colour scheme (31 October 2026)

- App lists render `WP_List_Table` markup through `assets/src/ui/ListTable.jsx`; wp-admin's own
  `list-tables.css` (part of the `wp-admin` style bundle on every admin page) styles them. The app adds no
  list-table CSS beyond one alignment rule in `assets/src/app/styles/app.css`.
- `admin-design-system.css` rules moved from `body.oml-ds` to `body.oml-ds-branded`, so they apply only for a
  branded Design system preset; under the default WordPress preset plugin screens use WordPress's styles
  and the user's admin colour scheme. The `.ohmylms-ds-*` rules for the Design system tab are unscoped as before.
- One-off inline values in app components (earnings chart colours, email and certificate preview frames,
  summary-card spacing, level chips, pre-wrapped notes, the design-system swatch) moved to token-based
  classes in `app.css` and `admin-design-system.css`; `check:architecture` now rejects raw colours in
  `assets/src/app` ([DESIGN-TOKENS](DESIGN-TOKENS.md)).

## Learner colours

Authored learner colours live in `assets/src/frontend/scss` and use `--oml-*` (previous hex only as a `var()` fallback). `assets/css/style.css` and `style-rtl.css` are the generated compatibility bundle from that SCSS, written by `tools/build-frontend-styles.mjs` into `assets/css` with the page modules. They are not a second source. The October snapshot's 476,556 bytes was the size of that generated file, not a separate palette; after the icon masks were regenerated it is 483,294 bytes. Exceptions that stay literal are listed in `tools/learner-colour-allowlist.json` (star artwork, select chevrons, one notice glyph, two-paint icons). The video player keeps its fixed media palette. Exam-print colours are in `paper.css`, not this SCSS.

## Remaining optimization boundaries

- Feature modules retain their ordered cascade slots. Further request-count reduction needs a
  cascade review; arbitrary regrouping can change precedence.
- The nine feature styles are already assigned lazy groups by `AdminAssets::defer_feature_style()`.
  They load when their corresponding content or explicit feature loader requires them, rather than
  being treated as unconditional application CSS.
- The Sass build still uses legacy `@import` because some partials share placeholder extensions.
  A future Sass module migration must preserve those dependencies and the existing cascade.
