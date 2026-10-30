# Cutover from the recovered admin to the new app

The recovered admin (`admin.php?page=ohmylms`, the webpack runtime rebuilt from shipped bundles) was replaced by the
typed admin app (`admin.php?page=ohmylms-app`, see [ARCHITECTURE](ARCHITECTURE.md)) and deleted on 31 October 2026.
This document records how the switch works now, what was removed and how it was verified.

Inputs: [LEGACY-ROUTES](LEGACY-ROUTES.md) (which legacy addresses open which new screens).

## After the cutover

There is no switch any more: `OHMYLMS_LEGACY_ADMIN`, the `ohmylms_legacy_admin_enabled` and
`ohmylms_legacy_routes_enabled` filters and the `&ohmylms_legacy=1` opt-out are gone and have no effect.

| | Behaviour |
| --- | --- |
| OhMyLMS menu | the top-level entry opens the new dashboard (its first submenu entry); every submenu entry opens a new-app route (`LegacyRoutes::app_url()`) |
| Add-on entries that open `page=ohmylms#/…` | mapped through `LegacyRoutes::resolve()`; unmapped ones are dropped. Entries for other pages and external links are kept |
| `page=ohmylms-app` | has no menu entry of its own ("OhMyLMS (new)" is gone) but stays registered below the OhMyLMS menu |
| `admin.php?page=ohmylms#/route` | still a registered admin page (the OhMyLMS top-level slug), redirected on the server (302) to `page=ohmylms-app&ohmylms_from_legacy=1`; other query parameters are kept, a leftover `ohmylms_legacy` is dropped. The browser keeps the `#/route` fragment; `legacy-redirects.js`, loaded in the app's head, replaces it with the mapped route, or with the app home (`#/`) plus a notice naming the route (and any screens add-ons list as unavailable). The marker is removed from the address, also from WordPress' canonical admin URL (`removable_query_args`) |
| `LegacyRoutes::url()` | always the new app: the mapped route, or for unmapped routes `page=ohmylms-app&ohmylms_from_legacy=1#/legacy/route`, which lands on the app home with the notice |
| `LegacyRoutes::target()` (command palette) | always `ohmylms-app`; unmapped routes open the app home |

Implementation: `LegacyRoutes::legacy_redirect_url()`, `redirect_legacy_page()` (hooked on
`load-toplevel_page_ohmylms`), `render_legacy_page()` (the page body WordPress needs to fire that hook; never reached),
`enqueue_landing()`, `removable_query_args()` and `unavailable()` (filter `ohmylms_legacy_unavailable_screens`);
`Menu::map_legacy_submenu()` (on `admin_menu`, priority 9999); `AppPage::hide_menu_entry()` (on `admin_head`, because
removing the entry on `admin_menu` would make the page unloadable) and the hidden notice in `AppPage::render()`;
`assets/js/legacy-redirects.js` (landing only; the legacy-page redirect mode is gone). Covered by
`tests/php/legacy-routes-integration.php` and `tests/js/legacy-redirects.test.mjs`.


## What was removed

Before deleting, every legacy screen got a new-app screen and a rule in `LegacyRoutes` (exams, comparison, sessions,
the student report, course students, design settings, migration, rewards, All accounts, the Content Hub overview, quiz
reports and the `?action=`/`?add=` dialogs), and every route in the recovered router resolves. The front-end slots,
checkout and registration fields and MathLive moved to the 2 KB extension host; native editors no longer import the
legacy SDK. The administrator chat assistant (LMS chat, attachments, draft creation, development worker) existed only
in the legacy admin and was dropped rather than rebuilt: its classes, REST routes, worker tool, tests and docs are
gone, the MCP server no longer trusts its in-process requests, and the 1.2.21 database update deletes its stored
options (`ohmylms_admin_assistant`, `ohmylms_coding_worker`, `ohmylms_coding_jobs`).

| Area | Removed |
| --- | --- |
| PHP | every legacy handle and enqueue in `AdminAssets` (only `ohmylms-global` remains), the CSS modules and their loader, `get_script_data()` (`ohmylms_params`, `MRM_Vars`), the editor/TinyMCE block for the legacy screen, `SourceAssets::admin()`, `Tokens::enqueue_admin()`, `Menu::plugin_page()`, `includes/Admin/views/app.php`, the switch (`OHMYLMS_LEGACY_ADMIN`, its filters, the `&ohmylms_legacy=1` opt-out) |
| Webpack | the legacy SDK entry `extensions` and the six bridges (`email-packages`, `vendor-charts`, `vendor-emotion`, `editor-tiptap`, `editor-lowlight`, `course-structure`) |
| Shipped assets | `assets/dist` 91.5 MB to 17.6 MB: recovered bundles, `vendors/`, recovered chunks, bridge entries, the legacy SDK and its chunks, `css/admin/`, `css/chunks/`, and the stylesheets only the legacy screen loaded (`admin-ui`, `tailwind`, `skill-studio`, `syllabus-comparison`, `question-stage`, `quiz-design`, `quiz-overview`, `course-objectives`, `course-editor-ux`, `course-outline-editor`, `subject-profiles`, `library-curriculum`, `admin-assistant`, `exam-import`) |
| Source | 2,179 files, 26.9 MB: `assets/src/application` (230), `modules` (820), the legacy files of `runtimes`/`static`, `assets/src/extensions`, 612 legacy-only authored files with their `components.json`, the admin CSS sources; `manifest.json` trimmed to the 79 kept front-end, block, theme and image assets |
| Tools and tests | 43 recovery tools (including the cutover inventory), 118 `tests/js` files, 38 legacy browser specs, helpers and fixtures |
| npm | 50 packages imported only by legacy code (`@arco-design/web-react`, `@emotion/*`, `@tiptap/*`, `chart.js`, `easy-email-core`, `mjml-browser`, `lodash`, `react-select` and others) plus `sass-loader` |

Kept: `LegacyRoutes` (map, server redirect, landing notice) so old bookmarks and emails keep working, and the 79
recovered front-end assets the manifest still lists.

## Risks

- Add-on admin pages built on the legacy SDK (`/extensions/<id>` with `registerAdminPage`) must register with the new
  app's extension host; unmapped add-on addresses land on the app home with the notice.
- The WordPress menu does not highlight the matching submenu entry on the new app (known gap in LEGACY-ROUTES.md); the
  redesign in [ADMIN-REDESIGN](ADMIN-REDESIGN.md) fixes it with real group pages.
- Translation files (`languages/*.pot`/`.po`) still reference deleted source files until they are regenerated.
- No browser specs exist yet for `page=ohmylms-app`.
## Rollback

- The switch is gone, so there is no configuration rollback. Revert the cutover commit; the
  recovered bundles cannot be regenerated from npm, so keep the last release that shipped them available until the new
  app has run in production for a full release.

## Verification (31 October 2026)

- `npm run build` and `npm run check:ci`: typecheck, 1,524 app tests (148 files), the architecture check and its
  budgets pass; every chunk `admin-app.js` references exists.
- `npm test`: 399 of 400 pass, one skipped.
- 41 PHP suites pass, including every `*-rest-integration.php`, `legacy-routes-integration.php` (367 checks),
  `extension-host-assets-integration.php` (source and shipped modes), `native-lesson-editor-integration.php` and
  `settings-secrets-integration.php`. `view-as-unit.php` fails the same way on the commit before the cutover.
- On 800.local: the OhMyLMS menu opens only `page=ohmylms-app`; bare `page=ohmylms` lands on `#/dashboard`,
  `#/order-edit/<id>` on `#/orders/<id>`, an unmapped add-on address on `#/` with the notice, and the
  `ohmylms_from_legacy` marker is removed from the address. 24 app screens load without error notices, missing assets
  or script errors. The course, checkout, a lesson with blocks and the native editor load without plugin 404s and
  without the legacy SDK.