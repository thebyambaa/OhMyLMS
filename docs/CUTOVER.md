# Cutover from the recovered admin to the new app

The recovered admin (`admin.php?page=ohmylms`, the webpack runtime rebuilt from shipped bundles) is being replaced by
the typed admin app (`admin.php?page=ohmylms-app`, see [ARCHITECTURE](ARCHITECTURE.md)). This document is the plan
for switching over and then deleting the recovered runtime. Nothing has been deleted yet: the cutover switch exists
and is **off** by default.

Inputs: [LEGACY-ROUTES](LEGACY-ROUTES.md) (which legacy screens the new app replaces) and
[RUNTIME-DEPENDENCY-INVENTORY](RUNTIME-DEPENDENCY-INVENTORY.md) (what authored code still reads from the runtime).

## The cutover switch

| | Legacy admin enabled (default) | Legacy admin disabled (cutover) |
| --- | --- | --- |
| How | nothing to do | `define( 'OHMYLMS_LEGACY_ADMIN', false );` in `wp-config.php`, or `add_filter( 'ohmylms_legacy_admin_enabled', '__return_false' );` |
| OhMyLMS menu | as today: mapped entries open the new app, Content Hub opens the legacy admin | the top-level entry opens the new dashboard; every submenu entry opens a new-app route |
| Content Hub entry | `page=ohmylms#/content-hub` | `page=ohmylms-app#/courses` |
| Sessions entry (when shown) | `page=ohmylms-app#/sessions` | `page=ohmylms-app#/sessions` |
| "OhMyLMS (new)" entry | shown | removed; `page=ohmylms-app` still loads |
| Add-on entries that open `page=ohmylms#/…` | unchanged | mapped through `LegacyRoutes::resolve()`; unmapped ones are dropped. Entries for other pages and external links are kept |
| `admin.php?page=ohmylms#/route` | served; the browser redirect (`legacy-redirects.js`) moves mapped routes to the new app | redirected on the server (302) to `page=ohmylms-app&ohmylms_from_legacy=1`. The browser keeps the `#/route` fragment; the same script, loaded in the app's head, replaces it with the mapped route, or with the app home (`#/`) plus a notice naming the route, listing what is not available yet and linking to the legacy screen |
| `&ohmylms_legacy=1` (administrators) | keeps the legacy screen | still keeps the legacy screen; nothing is unreachable |
| `LegacyRoutes::url()` | unchanged | mapped routes open the new app (even if `ohmylms_legacy_routes_enabled` is false); unmapped ones still name the legacy route, which the server redirect sends home with the notice |

The filter wins over the constant. When the new app is not built (`AdminAppAssets::locate()` is null), the legacy
admin stays on whatever the switch says, because there would be nothing to open instead.

Implementation: `LegacyRoutes::legacy_admin_enabled()`, `legacy_redirect_url()`, `redirect_legacy_page()` (hooked on
`load-toplevel_page_ohmylms`), `enqueue_landing()` and `unavailable()` (filter `ohmylms_legacy_unavailable_screens`);
`Menu::cutover_submenu()` (on `admin_menu`, priority 9999); `AppPage::hide_menu_entry()` (on `admin_head`, because
removing the entry on `admin_menu` would make the page unloadable) and the hidden notice in `AppPage::render()`;
landing mode in `assets/js/legacy-redirects.js`. Both states are covered by `tests/php/legacy-routes-integration.php`
and `tests/js/legacy-redirects.test.mjs`.

## Inventory

`node tools/cutover-inventory.mjs` prints what still depends on the recovered runtime; `--details` lists every file,
`--json` prints the report, `--check` exits 1 on a blocker (a browser spec opening `page=ohmylms` without
`&ohmylms_legacy=1`, or code a kept entry loads reaching recovered or runtime-bound code). It is read-only; re-run it
after every deletion step. How it classifies is described in its header: the manifest output for
`assets/src/{runtimes,static}`, and import reachability from the webpack entries for authored source, where the legacy
entries are `extensions` (the legacy SDK) and the bridges `email-packages`, `vendor-charts`, `vendor-emotion`,
`editor-tiptap`, `editor-lowlight` and `course-structure`.

Summary on 2026-10-31 (sizes include source maps):

| Group | Files | Size |
| --- | --- | --- |
| Recovered source used only by the legacy admin (`application` 230, `modules` 820, `runtimes` 8, `static` 3) | 1,061 | 18.7 MB |
| Recovered source still shipped to the front end, blocks and other screens (keep) | 80 | 736 KB |
| Authored source reachable only from the legacy SDK and bridges | 543 | 3.6 MB |
| … of which `createComponent(readRuntime)` components (plus 19 `components.json`) | 239 | 2.2 MB |
| Authored source shared with kept entries (keep) | 83 | 510 KB |
| Recovered bundles in `assets/dist` (`admin/ohmylms.js`, `vendors/*`, `chunks/355`, `chunks/655`) | 14 | 42.1 MB |
| Bridge entries (`assets/dist/admin/{email-packages,vendor-charts,vendor-emotion,editor-tiptap,editor-lowlight,course-structure}.*`) | 26 | 10.6 MB |
| Legacy SDK `extensions.js` and its named chunks | 55 | 8.6 MB |
| Numbered chunks to re-check after a build without the legacy entries | 28 | 11.2 MB |
| Legacy admin CSS (`assets/dist/css/admin`: 284 modules in 16 groups; `css/chunks/355.css`) plus `skill-studio.css`, `syllabus-comparison.css`, `tailwind.css` | 149 + 3 | 3.1 MB |
| PHP lines: `toplevel_page_ohmylms` checks 24, recovered bundle enqueues 20, bridge enqueues 20, legacy SDK 6, hard-coded legacy links 10 | | |
| Recovery-only tools (39), mixed tools to split (4) | 43 | |
| `tests/js`: recovery, parity and adapter tests 37; tests of features only the SDK loads 41 | 78 | |
| Legacy-only browser specs (27 to delete, 4 to retarget) | 31 | |
| npm packages imported only by legacy files | 50 | |

## Replace first

These must move before anything is deleted, in this order.

1. **Front-end slots, checkout fields and registration fields.** `Slots::render()` (`student.course.*`,
   `checkout.fields.after`, `checkout.summary.after`, `checkout-field` kind) and the signup form
   (`ohmylms_signup_form_start`, `[data-ohmylms-registration-fields]`) load the legacy SDK through
   `SourceAssets::enqueue()`. Move the DOM mounting (`mountSlots()` in `assets/src/extensions/index.jsx`) and the lazy
   `registration` chunk into the new app's 4 KB extension host (`assets/src/app/extensions/host-entry.ts`; `host.ts`
   already has `mount()`), make `SourceAssets::enqueue()` load the `ohmylms-app-extensions` handle, and keep the
   `ohmylms_enqueue_extension_scripts` action so add-ons keep registering. Measure the host stays small.
2. **MathLive configuration.** `MathLive::configure()` localizes `ohmylmsMath` on `ohmylms-extension-sdk`; move it to
   the host handle (`AdminAppAssets` already does this on the app page).
3. **Native lesson and quiz editors.** They do not need the SDK (`nativeAuthoringBootstrap.js` creates a registry when
   `window.ohmylms.extensions` is missing), but they import `assets/src/extensions/registry.mjs`, which the new host also
   imports. Move `registry.mjs` and `registry.d.mts` into `assets/src/app/extensions/` before deleting
   `assets/src/extensions/`.
4. **Hard-coded legacy links** (`tools/cutover-inventory.mjs --details`, section 4): `PreviewPlayer.php`,
   `NativeQuizEditor.php`, `NativeLessonEditor.php` (`#/question-bank` is not even a legacy route), the Google Meet OAuth
   redirects, `AdminAssets.php` (`ohmylms_home_page`) and `Rest/V1/CourseController.php` (`builder_url`). Use
   `LegacyRoutes::url()` or `LegacyRoutes::app_url()`. With the switch off they still land through the redirect, but
   unmapped ones end on the home screen with the notice.
5. **Screens the new app does not have** (`LegacyRoutes::unavailable()`): the Content Hub overview and the quiz reports
   overview (rewards and the All accounts tab landed too; exams, comparison, sessions, the student report, course students, design
   settings, migration and the `?action=`/`?add=` dialogs landed on 31 October 2026). Build or consciously drop each one; add a rule to `LegacyRoutes::routes()` (and regenerate the fixture) when a screen lands.
6. **Features only the legacy SDK loads** (543 files, 41 `tests/js` files): port what the new app needs, then delete
   the rest with its tests.
7. **Mixed tools** (`build-assets.mjs`, `sync-admin-assets.mjs`, `dev.mjs`, `check-reproducible.mjs`) and the npm
   scripts that call recovery tools (`recover`, `build:assets`, `build:application`, `build`, `dev`, `test` with
   `--parity`, `test:reproducible`, `format`/`format:check` globs, `analyze:bundles`, `analyze:editor`): keep the style
   builds and the new-app sync, drop the recovered parts.
8. **Browser specs that reach native editors through a legacy redirect** (`assessment`, `lesson-block-editor`,
   `native-authoring`, `quizzes`): open `post.php?post=<id>&action=edit` directly, so the native editor coverage stays.

## Ordered deletion plan

1. Staging: turn the switch off (`OHMYLMS_LEGACY_ADMIN` false), run the verification checklist, soak for a release.
2. Change the default in `LegacyRoutes::legacy_admin_enabled()` to false for one release; `&ohmylms_legacy=1` and the
   constant/filter (set to true) remain the escape hatch.
3. PHP: remove the legacy handles from `AdminAssets::get_scripts()`/`get_styles()` and the CSS modules, the editor and
   TinyMCE block for `toplevel_page_ohmylms`, `enqueue_benchmark()`, `SourceAssets::admin()`; re-target the screen
   checks in `AssistantController.php`, `Design/Tokens.php`, `Exams/Library.php` and `Hooks/CommonHook.php` to the app
   screen; drop `Menu::plugin_page()` and `includes/Admin/views/app.php`. Keep `LegacyRoutes` (map, server redirect,
   landing) so old bookmarks and emails keep working; the legacy-page mode of `legacy-redirects.js` and
   `LegacyRoutes::enqueue_redirect()` go. `admin.php?page=ohmylms` must stay a registered page that redirects.
4. Webpack: remove the legacy entries (`extensions` and the six bridges) from `webpack.config.cjs` and rebuild; then
   delete the numbered chunks no kept entry produces.
5. Shipped assets: `assets/dist/admin/ohmylms*.js*`, `assets/dist/vendors/`, `assets/dist/chunks/`, the bridge entries,
   `assets/dist/admin/extensions.*` and its named chunks, `assets/dist/css/admin/`, `assets/dist/css/chunks/`,
   `assets/css/{skill-studio,syllabus-comparison,tailwind}.css`, and their `build/` mirrors.
6. Source: `assets/src/application/`, `assets/src/modules/`, the 11 legacy-admin files in `assets/src/runtimes/` and
   `assets/src/static/`; move the 80 kept files (front-end runtimes, block scripts, theme-support CSS, images) to an
   authored location before deleting the rest of those folders and `assets/src/manifest.json`; the legacy-only
   authored files, the 19 `components.json`, `assets/src/extensions/` (after step 3 of "Replace first") and the
   unwired `features/commerce-reference/`.
7. Tools: the 39 recovery-only tools; split the 4 mixed ones; update `KEEP_TOOLS` (`check-architecture.mjs` drops its
   `readRuntime` rule).
8. Tests: the 37 recovery tests and the SDK-feature tests not ported; the 27 legacy-only browser specs below.
9. npm: re-run the inventory and remove the packages it then lists as imported only by legacy files (today 50:
   `@arco-design/web-react`, `@emotion/cache`, `@emotion/react`, the `@tiptap/*` family, `chart.js`,
   `react-chartjs-2`, `easy-email-core`, `mjml-browser`, `highlight.js`, `lowlight`, `js-beautify`, `lodash`,
   `lucide-react`, `react-select`, `react-dom`). Check the 9 packages it lists as not imported (CLI and config).
10. Docs: retire RUNTIME-DEPENDENCY-INVENTORY.md and its generator, update LEGACY-ROUTES.md and ask the roadmap owner to
    close the related steps in MODULARIZATION-ROADMAP.md.

## Browser specs

Specs now open the legacy admin only on purpose. `--check` fails if one opens `page=ohmylms` without the opt-out.

- **Moved to the new app** (the page was only an authenticated page with `wp.apiFetch` for REST fixtures):
  `all-activities`, `diagram-labels`, `image-quiz`, `recall-activity`, `word-games`, `word-puzzles`,
  `wordwall-activities`, and the fixture setup of `comparison`, `single-workspace`, `quizzes`, `lesson-block-editor`
  (`#/content-hub/lessons`) and `native-authoring` (`#/quizzes`).
- **Legacy-only, opened with `&ohmylms_legacy=1`, delete at cutover** (26; `communities` was deleted with the
  retired Communities screen): `analytics`, `baseline`, `commerce`, `comparison`, `course-objectives`, `course-structure`, `courses`, `curriculum`, `design`,
  `extensions`, `gamification`, `learning-program`, `learning`, `memberships`, `placement`, `schools`,
  `single-workspace`, `students`, `subjects`, `syllabus-course`, `syllabus`, `xapi-settings`, and the harness specs on
  the recovered bundles `editor-packages`, `editor-runtime`, `email-vendor-chunks`, `vendor-packages`. Write new-app
  specs for the screens that survive before deleting them; none exists yet for `page=ohmylms-app`.
- **Legacy, retarget at cutover** (4): `assessment`, `lesson-block-editor`, `native-authoring`, `quizzes` (see
  "Replace first", item 8).

The specs were checked with `node --check`; they were not run against 800.local (no credentials in this environment).

## Risks

- `assets/src/runtimes` and `assets/src/static` are not admin-only: 80 files feed the front end (checkout, Stripe,
  video player, blocks, Elementor/WPBakery, theme support, images). Delete by manifest output, never by folder.
- The front-end slots and registration fields load the legacy SDK only in source mode today (`OHMYLMS_SOURCE_ASSETS`);
  test both modes after moving them.
- Add-ons may register submenu entries or link to `page=ohmylms#/…`. Entries are mapped or dropped, and links land
  through the redirect, but add-on admin pages built on the legacy SDK (`/extensions/<id>` with
  `registerAdminPage`) need the new app's `/extensions/:id` route to be at parity.
- The server cannot see the `#` fragment, so the notice is filled in by the browser; without JavaScript the app does not
  run anyway.
- Some legacy screens link to other legacy screens without the opt-out; with the switch off such a click leaves the
  legacy screen.
- The WordPress menu does not highlight the matching submenu entry on the new app (known gap in LEGACY-ROUTES.md).
- Deleting numbered chunks by name is unsafe; rebuild without the legacy entries first and delete only chunks no
  build produces.
- Other engineers add files while this runs; the inventory numbers move. Re-run it rather than trusting this snapshot.

## Rollback

- Before any deletion: set `OHMYLMS_LEGACY_ADMIN` to true (or remove it), or return true from
  `ohmylms_legacy_admin_enabled`. Menu, redirect and links return to today's behaviour on the next request; nothing is
  stored.
- After step 2 (default flipped): the same constant or filter, set to true.
- After deletion (steps 3–9): revert the deletion commits; the recovered bundles cannot be regenerated from npm, so keep
  the last release that shipped them available until the new app has run in production for a full release.
- Per user: `&ohmylms_legacy=1` opens any legacy screen while it still exists.

## Verification checklist

- [ ] `OHMYLMS_LEGACY_ROUTES_TEST=1 php … tests/php/legacy-routes-integration.php` passes (both switch states).
- [ ] `node --test tests/js/legacy-redirects.test.mjs` passes.
- [ ] `node tools/cutover-inventory.mjs --check` exits 0.
- [ ] With the switch off, as an administrator: the OhMyLMS menu item opens `page=ohmylms-app#/dashboard`; every
      submenu link opens `page=ohmylms-app` (or another page/external link); "OhMyLMS (new)" is gone;
      `page=ohmylms-app` still loads.
- [ ] `page=ohmylms#/order-edit/<id>`, `#/settings/xapi-settings` (with `&xapi_notice=saved`) and a bare `page=ohmylms`
      end on `#/orders/<id>`, `#/settings?tab=xapi&xapi_notice=saved` and `#/dashboard`, with
      `ohmylms_from_legacy` removed from the address.
- [ ] `page=ohmylms#/content-hub` ends on `#/` with the notice naming `/content-hub` (while it is unmapped), and its
      link opens the legacy screen with `&ohmylms_legacy=1`.
- [ ] `page=ohmylms#/communities` (retired) opens `#/integrations`.
- [ ] `page=ohmylms&ohmylms_legacy=1#/orders` stays on the legacy Orders screen.
- [ ] Instructor emails, the plugins-screen Settings link, the command palette and the admin bar open new-app routes.
- [ ] Front end: course page slots, checkout fields and registration fields render (source mode and shipped mode)
      after "Replace first" items 1–2; MathLive fields load.
- [ ] Native lesson and quiz editors (`post.php`) open and save.
- [ ] The browser specs that remain pass against a test site; the new-app specs that replace the deleted ones exist.
- [ ] After each deletion step: `npm run build:sdk`, `npm run typecheck`, `npm run test:app`, `npm test`,
      `npm run check:architecture`, the PHP standards check and the inventory pass.
