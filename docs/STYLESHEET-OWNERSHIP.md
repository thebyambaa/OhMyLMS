# Stylesheet ownership (roadmap step 7)

Measured on `main` on 29 October 2026. Sizes are uncompressed bytes of the files in the plugin directory. Nothing here claims a speed-up; page-load timing was not measured.

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
| Public compatibility URLs | `assets/css/style.css`, `style-rtl.css` | 476,556 / 1,215 | Generated from the same SCSS by `tools/build-frontend-styles.mjs` | Not enqueued by the plugin. Kept for integrations that request the old URLs |
| Admin application, shell | `admin-design-system.css` 17,002 (plus inline variables from `DesignSystem.php`), `tailwind.css` 22,069, `global.css` 596 (all wp-admin, LMS menu) | | `Design/DesignSystem.php`, `Assets/AdminAssets.php` | |
| Admin application, main layer | `assets/dist/css/admin/oml-main-*.css` (and the `ohmylms.css` fallback) | 1,430,902 across 144 modules | `assets/src/design-system/admin`, plus three vendor modules in `assets/src/admin-styles` | Of this, email editor 654,046, automations 394,457 and email shell 69,440 are vendor (Arco, email builder) and load on demand |
| Admin application, UI layer | `assets/css/oml-ui-*.css` (and the `admin-ui.css` fallback) | 77,274 across 140 modules | `assets/src/design-system/admin` | |
| Admin application, RTL | `oml-rtl-*.css` | 4,968 | same | Only when `is_rtl()` |
| Admin application, always requested on the app screen | `question-editor.css` 17,672, plus nine feature files (`question-stage`, `quiz-design`, `quiz-overview`, `course-objectives`, `skill-matrix`, `course-editor-ux`, `course-outline-editor`, `subject-profiles`, `library-curriculum`) totalling 55,727 | | `Design/Tokens.php::enqueue_admin()` | Printed as deferred slots and filled by `admin-styles.js` |
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

- Frontend: no exact duplicate rules across the eight page modules. Page rules that target `.ohmylms-button` set layout only (`width`, `display`, `gap`, `max-width`); none sets colour, background, border or padding. The layout 3 sidebar progress bar in `course.css` still sets its own size and colour.
- Admin (non-vendor): 3,148 rules scanned; 46 exact duplicate rules (about 5.9 KB), mostly the quiz option list (`main-066` and `main-074`) and RTL pagination rules. They are not removed because deleting the later copy changes cascade order.
- Admin button rules outside the shared layer that set skin properties: 6 (icon picker, resource actions, streak milestone fields); all are component-specific sizing or selected-state colours.

## Public selectors kept

All `.ohmylms-*` frontend classes used by `templates/`, Elementor, Bricks and add-ons, including `.ohmylms-button`, `.ohmylms-form-group`, `.ohmylms-progressbar*` and the `.ohmylms-page` scope, stay in the shared component styles and page modules. The old `style.css` and `style-rtl.css` URLs still work. No class was deleted in this step.

## Still open

- 177 plugin stylesheet links (284 modules, 95 inlined) load on the application screen. Adjacent modules cannot be merged because groups alternate in cascade order (284 runs for 284 modules); merging needs a cascade review.
- The Extensions page (`ohmylms-extensions`) requests many `courses` and `commerce` modules; check the on-demand loader against what that screen renders.
- Nine feature files totalling 55,727 bytes load on every application screen; they can move to the lazy groups already assigned in `AdminAssets::defer_feature_style()`.
- `assets/src/static/dist/css/admin/ohmylms.css` (1,648,931 bytes) remains as the parity fixture for `tests/js/admin-styles.test.mjs`; replace it with a generated selector list to retire it. `assets/dist/css/chunks/355.css` (167,412 bytes) is still requested by the email-editor chunk.
- Playwright style specs were not run: the Chromium binary is not installed on this machine.
