# Legacy admin routes

The recovered admin (`admin.php?page=ohmylms#/...`) is being retired screen by screen. Wherever the typed admin app
(`admin.php?page=ohmylms-app#/...`, see [ARCHITECTURE](ARCHITECTURE.md)) is at parity, links and bookmarks open the new
screen. Screens that are not at parity keep opening the legacy admin.

## One map

`includes/Admin/LegacyRoutes.php` holds the only list of replaced screens (`LegacyRoutes::routes()`). It is used by:

| Consumer | How |
| --- | --- |
| PHP links | `LegacyRoutes::url( '/legacy/route' )` returns the new-app URL when mapped, otherwise the legacy URL. Menu and submenus, Add-ons, admin bar course links, the plugins screen Settings link, xAPI and AI tutor redirects, Account Hub links (`Schools\Views`, `Student\Hub`) and instructor emails all use it. |
| Command palette | `Commands::all()` adds `page` and the route inside that page from `LegacyRoutes::target()`; `assets/js/commands.js` opens that page. See [COMMAND-PALETTE](COMMAND-PALETTE.md). |
| Old bookmarks | `assets/js/legacy-redirects.js` (plain ES2017, no build) loads in the head of `toplevel_page_ohmylms` before the legacy app, with the map as `window.ohmylmsLegacyRoutes`. On load, `hashchange`, `popstate` and History API changes (the legacy router uses `pushState`) it replaces the location with the new URL. Query parameters other than `page` are kept. |
| Grading emails | `LegacyRoutes::assignment_grading_url( $assignment, $student )` looks up the learner's latest submission, because the new grading route names the submission and the legacy one the student. |

`LegacyRoutes::app_url( '/route' )` builds a new-app URL directly. When the new app is not built
(`AdminAppAssets::locate()` is null) or the `ohmylms_legacy_routes_enabled` filter returns false, every link stays
legacy and the redirect script is not loaded. The `ohmylms_legacy_routes` filter can add rules.

**Opt-out.** An administrator can keep any legacy screen by adding `&ohmylms_legacy=1`, for example
`admin.php?page=ohmylms&ohmylms_legacy=1#/orders`. Nothing is unreachable during the migration.

**Cutover switch.** `OHMYLMS_LEGACY_ADMIN` (constant) and `ohmylms_legacy_admin_enabled` (filter), true by default,
turn the legacy admin off: the OhMyLMS menu then opens only new-app routes, "OhMyLMS (new)" disappears, and
`admin.php?page=ohmylms` redirects on the server to the new app, which maps the carried-over hash with the same rules
(unmapped routes open the app home with a notice; `LegacyRoutes::unavailable()` lists them). The opt-out still works.
See [CUTOVER](CUTOVER.md).

## Rule format

Each rule has `from` (path pattern), `to` (new route) and optionally `query` and `skip`:

- Pattern segments are literals, `:name` (required), `:name?` (optional, at the end) or a final `*` (any remaining
  segments). `:name` in `to` is replaced by the URL-encoded value.
- `query` lists allowed values per query parameter (`''` = absent); the value is usable in `to` and not copied.
- `skip` lists query parameters whose presence keeps the legacy screen. No built-in rule uses it any more; list dialogs
  are mapped with `query` allow-lists, so unknown `?action=` values keep the legacy screen.
- The first matching rule wins. PHP (`LegacyRoutes::resolve()`) and JavaScript (`resolve()` in the script) implement
  the same matcher.

## Mapped screens

| Legacy route | New route |
| --- | --- |
| `/`, `/dashboard` | `/dashboard` |
| `/earnings-report` | `/earnings` |
| `/course/:id/report` | `/reports/courses/:id` |
| `/orders(/:page)`, `/memberships/orders(/:page)` | `/orders` |
| `/order-edit/:id` | `/orders/:id` |
| `/coupons`, `/memberships/coupons` | `/coupons` |
| `/subscriptions(/:page)`, `/memberships/subscriptions(/:page)` | `/subscriptions` |
| `/subscription-edit/:id` | `/subscriptions/:id` |
| `/memberships` | `/memberships` |
| `/certificates`, `/gamification/certificates` | `/certificates` |
| `/certificate-edit/:id` | `/certificates/:id` |
| `/emails`, `/settings/emails-settings` | `/emails` |
| `/emails/:id` | `/emails?email=:id` |
| `/webhooks`, `/settings/webhooks-settings` | `/webhooks` |
| `/integrations` | `/integrations` |
| `/settings/<tab>/...` | `/settings?tab=<new tab>` (below) |
| `/gamification/<tab>`, `/settings/gamification-settings(/<tab>)` | `/gamification(?tab=<new tab>)` (below) |
| `/setup-wizard(/:tab)` | `/setup` |
| `/assignments`, `/content-hub/assignments`, `/assessments/assignments`, `/assignment-report` | `/assignments` |
| `/assignment-edit/:id` | `/assignments/:id` |
| `/assignment-report/:id` | `/assignments/:id/submissions` |
| `/assignment-report/:id/grade-assignment/:student` | `/assignments/:id/submissions?student=:student` in the browser; the exact `/assignments/:id/submissions/:submission` from PHP |
| `/content-hub/question-bank`, `/extensions/question-bank`, `/assessments/question-bank` (no `?action=`, or `new`/`import`) | `/content-hub/question-bank(?action=<same>)` |
| `/accounthub` (no tab, `home`, `schools`) | `/account-hub` |
| `/accounthub?tab=classes\|teachers\|parents` | `/account-hub?tab=<same>` |
| `/accounthub?tab=students` | `/students` |
| `/quiz-report/:id/...` (including `/grade-quiz/:attempt`) | `/quizzes/:id/attempts` |
| `/quiz-edit/:id` | `/quizzes/:id` (settings and questions) |
| `/quizzes`, `/content-hub/quizzes`, `/assessments` (no `?action=`, or `new`/`import`) | `/quizzes(?action=<same>)` |
| `/course-edit/:id/...` | `/courses/:id` |
| `/courses`, `/content-hub/courses` | `/courses` |
| `/lesson-edit/:id` | `/content-hub/lessons/:id` |
| `/content-hub/lessons` | `/content-hub/lessons` |
| `/content-hub/skills`, `/extensions/skills` (no `?add=`, or `add=1`) | `/content-hub/skills(?add=1)` |
| `/content-hub/curriculum`, `/extensions/curriculum`, `/categories`, `/tags` | `/content-hub/curriculum` |
| `/content-hub/curriculum/skill-map`, `/subjects`, `/syllabus/:id` | the same paths |
| `/content-hub/bundles`, `/extensions/bundles` | `/content-hub/bundles` |
| `/content-hub/curriculum/compare` (`?roadmap=:id` opens the syllabus editor) | the same path |
| `/content-hub/exams` (no `?action=`, or `new`/`import`) | `/exams(?action=<same>)` |
| `/communities` (retired) | `/integrations` |
| `/students/:id/report` | `/students/:id/report` |
| `/courses/:id/students` | `/reports/courses/:id/students` |
| `/sessions` | `/sessions` |
| `/settings/design-settings/quiz-editor` | `/settings?tab=quiz-design` |
| `/settings/design-settings/colors-fonts` | `/settings?tab=design-system` |
| `/settings/design-settings/checkout-page` | `/settings?tab=payment-gateways` |
| `/settings/design-settings(/course-list\|course-details)` | `/settings?tab=course-archive` (course details stored options nothing reads) |

Settings tabs: `general-settings` → `general`, `branding-settings` → `branding`, `account-privacy-settings` →
`account-and-privacy`, `parmalink-settings` → `permalink`, `advanced-settings` → `advanced`, `ai-tutor-settings` →
`ai-tutor`, `design-system-settings` → `design-system`, `xapi-settings` → `xapi`, `migration-settings` → `migration`, `payments-settings` and
`payments-settings/payments` → `payment-gateways`, `payments-settings/currency` → `currency`, `payments-settings/tax` →
`tax`.

Gamification tabs: `point-settings` → `points`, `badge-settings` → `badges`, `level-settings` → `levels`,
`leaderboard-settings` → `leaderboard`, `streak-settings` → `streaks`, `xp-settings` → `xp`.

## Kept on the legacy admin

Not at parity, so not mapped: the `/content-hub` frame, other `?action=`/`?add=` values, other `/extensions/<id>`
pages, `/gamification/reward-settings`, `/accounthub?tab=users` and `/quiz-report` without an id. The Content Hub
menu entry therefore still opens the legacy admin; the "OhMyLMS (new)" entry is kept. After the cutover the Content Hub
entry opens `/courses` and "OhMyLMS (new)" is removed. The Sessions entry (when shown) opens `/sessions` in both
states.

## Known gaps

- The browser cannot turn a legacy student-keyed grading address into a submission, so old grading bookmarks open the
  submission list. Quiz grading links open the attempts list; the new screen has no per-attempt address.
- On the new app the WordPress menu highlights "OhMyLMS (new)" rather than the matching entry.
- Browser specs in `tests/browser/` that test a legacy screen open it with `&ohmylms_legacy=1`; specs that only need an
  authenticated page for REST fixtures open the new app. `node tools/cutover-inventory.mjs --check` fails when a spec
  opens `page=ohmylms` without the opt-out. CUTOVER.md lists the legacy-only specs.

## Tests

- `tests/fixtures/legacy-routes.json`: the rules (generated from PHP) and the shared case table.
- `tests/php/legacy-routes-integration.php` (`OHMYLMS_LEGACY_ROUTES_TEST=1`): fixture equals `LegacyRoutes::routes()`,
  every case, every target and tab exists in `assets/src/app/features`, `url()`/`target()`, commands, menu URLs, the
  plugins screen link, the redirect enqueue and opt-out, both states of the cutover switch (menu, add-on entries,
  server redirect, landing script, notice, constant and filter) and rendered instructor emails. After changing the
  map, run it once with `OHMYLMS_LEGACY_ROUTES_WRITE=1` to refresh the fixture.
- `tests/js/legacy-redirects.test.mjs`: the script's matcher against the same cases, URL building, opt-out and
  redirects on load, `hashchange` and `pushState`, and the landing mode after the cutover redirect.
