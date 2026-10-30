# Legacy admin routes

The recovered admin (`admin.php?page=ohmylms#/...`) is retired: the typed admin app (`admin.php?page=ohmylms-app#/...`,
see [ARCHITECTURE](ARCHITECTURE.md)) is the only admin. The route map below keeps old links, bookmarks and emails
working: each legacy route opens its new screen, and a route with no new screen opens the app home with a notice.

## One map

`includes/Admin/LegacyRoutes.php` holds the only list of replaced screens (`LegacyRoutes::routes()`). It is used by:

| Consumer | How |
| --- | --- |
| PHP links | `LegacyRoutes::url( '/legacy/route' )` always returns a new-app URL: the mapped route, or for an unmapped route `page=ohmylms-app&ohmylms_from_legacy=1#/legacy/route`, which lands on the app home with the notice. Add-ons, admin bar course links, the plugins screen Settings link, xAPI and AI tutor redirects, Account Hub links (`Schools\Views`, `Student\Hub`) and instructor emails use it; the OhMyLMS menu, View as and the Google Meet OAuth redirects use `LegacyRoutes::app_url()` directly. |
| Command palette | `Commands::all()` adds `page` and the route inside that page from `LegacyRoutes::target()` (always `ohmylms-app`; unmapped routes open the app home); `assets/js/commands.js` opens that page. See [COMMAND-PALETTE](COMMAND-PALETTE.md). |
| Old bookmarks | `admin.php?page=ohmylms` is still registered (it is the OhMyLMS top-level menu slug) and redirects on the server (302, `LegacyRoutes::redirect_legacy_page()` on `load-toplevel_page_ohmylms`) to `page=ohmylms-app&ohmylms_from_legacy=1`, keeping other query parameters. The browser keeps the `#/route` fragment. `assets/js/legacy-redirects.js` (plain ES2017, no build) loads in the app's head with the map as `window.ohmylmsLegacyRoutes` (`LegacyRoutes::script_data()`), replaces the hash with the mapped route, or with the app home plus the notice naming the route, and removes the marker from the address. |
| Grading emails | `LegacyRoutes::assignment_grading_url( $assignment, $student )` looks up the learner's latest submission, because the new grading route names the submission and the legacy one the student. |

`LegacyRoutes::app_url( '/route' )` builds a new-app URL directly. The `ohmylms_legacy_routes` filter can add rules;
`ohmylms_legacy_unavailable_screens` lists add-on screens in the notice. The former switch (`OHMYLMS_LEGACY_ADMIN`,
`ohmylms_legacy_admin_enabled`, `ohmylms_legacy_routes_enabled`) and the `&ohmylms_legacy=1` opt-out are gone; the
redirect drops a leftover `ohmylms_legacy`. See [CUTOVER](CUTOVER.md).

## Rule format

Each rule has `from` (path pattern), `to` (new route) and optionally `query` and `skip`:

- Pattern segments are literals, `:name` (required), `:name?` (optional, at the end) or a final `*` (any remaining
  segments). `:name` in `to` is replaced by the URL-encoded value.
- `query` lists allowed values per query parameter (`''` = absent); the value is usable in `to` and not copied.
- `skip` lists query parameters whose presence makes the rule not match. No built-in rule uses it any more; list
  dialogs are mapped with `query` allow-lists, so unknown `?action=` values are unmapped (app home with the notice).
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
| `/accounthub?tab=classes\|teachers\|parents\|users` | `/account-hub?tab=<same>` |
| `/accounthub?tab=students` | `/students` |
| `/quiz-report` | `/reports/quizzes` (filters copied) |
| `/quiz-report/:id/...` (including `/grade-quiz/:attempt`) | `/quizzes/:id/attempts` |
| `/content-hub` | `/content-hub` (overview) |
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
`leaderboard-settings` → `leaderboard`, `streak-settings` → `streaks`, `xp-settings` → `xp`, `reward-settings` → `rewards`.

## Unmapped routes

Every built-in legacy screen has a new one (31 October 2026). Still unmapped: `?action=`/`?add=` values the new
screens do not open, and add-on `/extensions/<id>` pages, whose add-ons register them with the new app. They open the
app home with the notice. `LegacyRoutes::unavailable()` is empty; add-ons can list their own screens with
`ohmylms_legacy_unavailable_screens`. Add-on submenu entries that open an unmapped legacy route are dropped
(`Menu::map_legacy_submenu()`).

## Known gaps

- The browser cannot turn a legacy student-keyed grading address into a submission, so old grading bookmarks open the
  submission list. Quiz grading links open the attempts list; the new screen has no per-attempt address.
- On the new app the WordPress menu highlights the OhMyLMS menu but not the matching submenu entry.
- The legacy-only browser specs were deleted with the legacy admin (31 October 2026); no spec in `tests/browser/`
  opens `page=ohmylms`. Specs that only need an authenticated page for REST fixtures open the new app.

## Tests

- `tests/fixtures/legacy-routes.json`: the rules (generated from PHP) and the shared case table.
- `tests/php/legacy-routes-integration.php` (`OHMYLMS_LEGACY_ROUTES_TEST=1`): fixture equals `LegacyRoutes::routes()`,
  every case, every target and tab exists in `assets/src/app/features`, `url()`/`target()`, commands, menu URLs and
  add-on entries, the plugins screen link, that the switch and the legacy-page redirect script are gone, the server
  redirect, the landing script and the marker's removal, the notice, the fixed hard-coded links and rendered
  instructor emails. After changing the map, run it once with `OHMYLMS_LEGACY_ROUTES_WRITE=1` to refresh the fixture.
- `tests/js/legacy-redirects.test.mjs`: the script's matcher against the same cases, that only the landing mode is
  left, and landing after the redirect (mapped, unmapped with the notice, and normal app visits).
