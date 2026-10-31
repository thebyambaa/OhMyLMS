# Legacy admin routes

The recovered admin (`admin.php?page=ohmylms#/...`) and the first app page (`admin.php?page=ohmylms-app#/...`) are
retired. The admin is six WordPress pages with query-string addresses (`admin.php?page=ohmylms-content&tab=courses&id=12`,
see [ARCHITECTURE](ARCHITECTURE.md), "Routing"). Both old address forms redirect forever: each legacy route opens its
new screen's address, every app route opens its own address, and a legacy route with no new screen opens the Dashboard
with a notice.

## Two hops

1. **Legacy route → app route.** `includes/Admin/LegacyRoutes.php` holds the only list of replaced screens
   (`LegacyRoutes::routes()`, rules below), for example `/order-edit/12` → `/orders/12`.
2. **App route → address.** `AdminNavigation::address()` in PHP and `toAddress()` in `assets/src/app/routing/address.ts`
   give every app route its address, for example `/orders/12` → `page=ohmylms-commerce&tab=orders&id=12`. The app
   routes are the routes of `assets/src/app/features` (today's `page=ohmylms-app#/…` addresses).

| Consumer | How |
| --- | --- |
| PHP links | `LegacyRoutes::url( '/legacy/route' )` returns the address of the mapped route, or for an unmapped route `admin.php?page=ohmylms#/legacy/route`, which shows the notice on the Dashboard. `LegacyRoutes::app_url( '/route' )` returns the address of an app route (one no screen serves opens `page=ohmylms&ohmylms_from_app=1#/route`, the app's "not found"). Plugin links name app routes with `app_url()`: admin bar course links, the plugins screen Settings link, xAPI and AI tutor redirects, `Student\Hub`, View as, the Google Meet OAuth redirects, the quiz form and preview return links, the question bank link and instructor emails. `url()` is for legacy input: add-ons, `NativeLessonEditor::return_url()` (`ohmylms_return`, assessment profiles' `return_route`) and, until it moves, `Schools\Views`. |
| Command palette | `Commands::all()` adds `page`, `tab`, the app `route` and the address `url` from `LegacyRoutes::target()` (unmapped routes open the Dashboard); `assets/js/commands.js` opens the address, or sets the hash when the command is on the current tab. See [COMMAND-PALETTE](COMMAND-PALETTE.md). |
| Menu entries of add-ons | `Menu::map_legacy_submenu()` points entries that open `page=ohmylms#/…` at the mapped address (unmapped ones are dropped) and entries that open `page=ohmylms-app#/…` at the route's address. |
| Legacy bookmarks | `admin.php?page=ohmylms` is the Dashboard page. `assets/js/legacy-redirects.js` (plain ES2017, no build) loads in its head with the map as `window.ohmylmsLegacyRoutes` (`LegacyRoutes::script_data()`) and reads a `#/route` hash there as a legacy route: it replaces the hash with the mapped app route, or removes it and shows the notice naming the route. It does this on load and on `hashchange` (a legacy link clicked on the Dashboard). The app's router then opens the app route at its address (`location.replace`). |
| App bookmarks | `admin.php?page=ohmylms-app` stays registered without a menu entry and redirects on the server (302, `LegacyRoutes::redirect_app_page()`) to `page=ohmylms&ohmylms_from_app=1`, keeping other query parameters (a leftover `ohmylms_legacy` is dropped; an older `ohmylms_from_legacy=1` is kept). The browser keeps the `#/route`; with the marker the landing script keeps it as an app route and removes the marker, and the router opens its address. Without a hash the redirect is the Dashboard. |
| Former PHP pages | `page=ohmylms-skill-assessments` and `page=ohmylms-import-questions` redirect to Content Hub › Skills › Assessments and Question bank › Import review (`load-` hooks; filters are kept). |
| Grading emails | `LegacyRoutes::assignment_grading_url( $assignment, $student )` looks up the learner's latest submission, because the new grading route names the submission and the legacy one the student. |

The markers are removed from WordPress' canonical admin URL (`removable_query_args`). The `ohmylms_legacy_routes`
filter can add rules; `ohmylms_legacy_unavailable_screens` lists add-on screens in the notice. The former switch
(`OHMYLMS_LEGACY_ADMIN`, `ohmylms_legacy_admin_enabled`, `ohmylms_legacy_routes_enabled`) and the `&ohmylms_legacy=1`
opt-out are gone. See [CUTOVER](CUTOVER.md).

Examples (all tested): `page=ohmylms#/order-edit/12` → `page=ohmylms-commerce&tab=orders&id=12`;
`page=ohmylms#/settings/payments-settings/tax` → `page=ohmylms-settings&tab=settings&section=tax`;
`page=ohmylms#/accounthub?tab=students` → `page=ohmylms-accounts&tab=students`; `page=ohmylms-app#/courses/12/settings`
→ `page=ohmylms-content&tab=courses&id=12&view=settings`; `page=ohmylms-app#/gamification?tab=badges` →
`page=ohmylms-engagement&tab=gamification&section=badges`; `page=ohmylms-app#/certificates/5` →
`page=ohmylms-engagement&tab=gamification&section=certificates&id=5`.

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
screens do not open, and add-on `/extensions/<id>` pages, whose add-ons register them with the new app (the app's own
`/extensions/<id>` route opens them as tab `ext-<id>`). Unmapped legacy routes open the Dashboard with the notice.
`LegacyRoutes::unavailable()` is empty; add-ons can list their own screens with `ohmylms_legacy_unavailable_screens`.

## Known gaps

- The browser cannot turn a legacy student-keyed grading address into a submission, so old grading bookmarks open the
  submission list. Quiz grading links open the attempts list; the new screen has no per-attempt address.
- On the Dashboard a `#/route` hash is always read as a legacy route. Old app routes reach it with the
  `ohmylms_from_app` marker (the server redirect, `LegacyRoutes::app_url()`); code that sets an app route as the hash
  on the Dashboard itself gets the legacy mapping (for example `#/orders/12` opens the order list, as legacy
  `/orders/12` was page 12 of it).
- A legacy or app hash address costs one more page load than a new address (Dashboard, then the target page).
- Browser specs in `tests/browser/` that open `page=ohmylms-app#/…` reach their screen through the redirect.

## Tests

- `tests/fixtures/legacy-routes.json`: the rules (generated from PHP), the shared case table (legacy route → app
  route) and `addresses` (legacy route → final address; `null` for the Dashboard with the notice).
- `tests/fixtures/admin-navigation.json`: the pages, tabs, sections and route table (generated from PHP) and `cases`
  (app route → address, and the route read back when it differs).
- `tests/php/legacy-routes-integration.php` (`OHMYLMS_LEGACY_ROUTES_TEST=1`): both fixtures equal the PHP tables,
  every case, that every app route and every legacy target has an address on its group page, `url()`/`app_url()`/
  `target()`, commands, the six submenus (order, badge, hidden tabs, flags, capability, fallback, a page with no
  visible tab gets no submenu), the page markup (heading, tabs, sections, mount point, server-rendered sections,
  Help & Feedback), add-on menu entries, the plugins screen link, the app page and former page redirects, the landing
  script and the markers' removal, the notice, the fixed hard-coded links and rendered instructor emails. After
  changing a table, run it once with `OHMYLMS_LEGACY_ROUTES_WRITE=1` to refresh the generated parts.
- `tests/js/legacy-redirects.test.mjs`: the script's matcher against the same cases, landing on the Dashboard (legacy
  hash, app hash with the marker, unmapped with the notice, plain visits, a hash change).
- `assets/src/app/routing/navigation-contract.test.ts`: the registry equals the PHP route table, every PHP tab and
  section is served (or rendered by PHP), every feature route has an address that opens the same feature, every
  navigation case both ways, and every `addresses` case through the legacy matcher and the app's address format.
- `assets/src/app/routing/QueryRouter.test.tsx`: pushState inside a tab, popstate, full loads across tabs, the
  server's fallback place, PHP section links, `#/route` hashes and same-tab link resolution.
