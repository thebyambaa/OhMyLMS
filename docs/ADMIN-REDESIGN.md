# Admin redesign plan

Plan for the admin's menu, look, dashboard and gradebook after the cutover ([CUTOVER](CUTOVER.md)). Work starts
once the recovered admin is deleted, so it never has to keep two admins in step.

Reference pack (fake data; ideas, not a blueprint): [design-reference/](design-reference/). It contains the clickable prototype
(`ohmylms-prototype/index.html`; serve the folder over HTTP, for example `npx http-server docs/design-reference`), the
agreed menu organisation (`ohmylms-redesign/PROPOSED-NAV.md`, `wp-native/WP-MENU-PROPOSAL.md`), today's map
(`CURRENT-NAV-MAP.md`) and the enrolment/gradebook research (`GRADEBOOK-RESEARCH.md`, `KHAN-COMPARISON.md`).

Decisions (31 October 2026): start after the cutover; six real WordPress pages with tabs addressed by query string;
order: menu and WordPress look, then the dashboard, then the gradebook. Also decided:

| Topic | Decision |
| --- | --- |
| Audience | Site administrators see everything; school administrators and teachers see only their schools and classes. Every dashboard and gradebook endpoint is scoped server-side. |
| Scope picker | All schools, one school, one class. No district entity. |
| Tabs | Real links with a full page load; moving inside a tab uses `pushState`. |
| Old app addresses | `page=ohmylms-app#/…` redirect forever, like the legacy addresses. |
| Rollout | Replaced in place on `main`, screen by screen, each verified; no feature switch. |
| Lists | React renders WordPress list-table markup over the existing REST APIs (no `WP_List_Table`). |
| Dashboard data | Computed on request, cached about 10 minutes per scope and period. |
| At-risk rules | Defaults (for example inactive 7+ days, mastery down 10+ points in 2 weeks, overdue work), adjustable in Settings. |
| Nudge | A fixed email template, rate limited and logged. |
| Gradebook edits | Per-cell manual override with reason and history; the computed score stays visible. |
| Weighting | Per course, with an optional per-class override. |
| Letter grades | One site-wide scale in Settings; percentages always shown. |
| Enrolment source | A new enrolments table, filled once from existing data by a reversible migration. |

## Principles

- WordPress's own admin design: `.wrap`, `h1.wp-heading-inline` + `.page-title-action`, `nav-tab-wrapper`, `.subsubsub`,
  `wp-list-table widefat striped` with `.tablenav`, bulk actions, `.row-actions` and pagination, WordPress notices and
  postboxes; `@wordpress/components` for forms, dialogs and controls. No custom chrome.
- Fit the existing code: the hardened feature template, the extension API v1 contract, design tokens and the
  `.ohmylms-button` learner class stay. Every old address keeps working through `LegacyRoutes`.
- Adapt where the real data says otherwise; the prototype's numbers and widgets are illustrations.

## Phase A: menu and WordPress look

### Pages and tabs

| Submenu | Page slug | Tabs (`&tab=`) |
| --- | --- | --- |
| Dashboard | `ohmylms` | none (Home and Dashboard merged; Help & Feedback becomes a link here) |
| Content Hub | `ohmylms-content` | courses, lessons, quizzes, exams, assignments, gradebook (phase C), question-bank, skills, curriculum |
| Account Hub | `ohmylms-accounts` | students, schools, classes, teachers, parents, users (All accounts) |
| Commerce | `ohmylms-commerce` | earnings, orders, memberships, subscriptions, coupons |
| Engagement | `ohmylms-engagement` | gamification (sections incl. rewards and certificates), sessions (only with `ohmylms_show_sessions_menu`), emails |
| Settings | `ohmylms-settings` | settings (sections = today's settings tabs incl. xAPI, AI tutor, migration), integrations, webhooks, setup |

Detail screens add `&id=` and `&view=` (`page=ohmylms-content&tab=courses&id=12&view=settings`); second-level
sections use `&section=`, rendered as `.subsubsub` under the tab (curriculum: bundles, skill-map, subjects, taxonomies;
skills: assessments; question bank: import-review). Feature query parameters (filters, `action=new|import`, `add=1`)
stay as they are.

- **One source for tabs.** `includes/Admin/AdminNavigation.php` declares groups, tabs, sections, labels, capability
  and flag per tab. PHP prints the tabs as real links (they work without JavaScript, keep the WordPress menu highlight
  and can be bookmarked) and registers a submenu only when the user can see one of its tabs; a hidden or unknown tab
  falls back to the group's default. `FeatureDefinition` gains `group`, `tab` and optional `section`/`view`, and a
  contract test fails when the TypeScript registry and the PHP table disagree.
- **Routing.** Replace `HashRouter` with a router keyed on the query string: an adapter turns
  `page/tab/id/view/section` into the internal route (`/courses/12/settings`) and back, so feature code keeps
  `useParams` and internal `Link` paths. Changing tab is a page load (the tab link); moving inside a tab uses
  `history.pushState`/`replaceState`. The adapter is the only place that knows the URL format.
- **Chrome.** Drop `AppLayout`'s header and navigation and the "OhMyLMS (new)" entry; each page prints `.wrap`, the
  group heading, the tabs and the mount point. Restore the pending-orders badge on the top-level menu and Commerce.
  `CommonHook.php` and `SkillAssessmentPage.php` stop adding their own submenus (they become tabs/sections).
- **Merges** from PROPOSED-NAV: Home + Dashboard; one course editor (structure and settings as views of
  `tab=courses&id=`); categories/tags as a curriculum section; Students under Account Hub; Certificates always a
  Gamification section.
- **Redirects.** `page=ohmylms#/…` (recovered admin) and `page=ohmylms-app#/…` (today's app) map to the new
  addresses: the hash only reaches the browser, so the landing script maps it; `page=ohmylms-app` with no hash redirects
  on the server. `LegacyRoutes::url()`/`app_url()` emit new addresses, so emails, the command palette, the admin bar
  and the plugins screen follow. The map follows WP-MENU-PROPOSAL §4; the fixture and both redirect tests grow with it.
- **Extensions.** `registerAdminPage` gains an optional `group` (default Settings) and appears as tab `ext-<id>`;
  `context.route` and editor-panel slots keep their legacy names (EXTENSIONS.md v1 stays valid).

**Progress (31 October 2026): navigation done.** The six submenus, tabs, sections, query-string router, PHP chrome
and the forever redirects are in place and verified on 800.local ([ARCHITECTURE](ARCHITECTURE.md), "Routing";
[LEGACY-ROUTES](LEGACY-ROUTES.md)). As built:

- Content Hub › Courses has the sections All courses and Overview (the former Content Hub overview); Quizzes has
  Reports (quiz reports). Course reports and rosters are views of a course (`&id=…&view=report|students`); the old
  course-structure routes are views `structure`/`structure-settings` that redirect into the course editor.
- Skills › Assessments and Question bank › Import review are sections PHP renders (the former pages redirect).
- Engagement › Gamification opens Points with the Gamification add-on on and Certificates otherwise.
- Settings sections are the settings screen's tabs; the in-page tab rows of Settings, Gamification and Account Hub
  are hidden on the admin pages, as PHP prints them.
- Every page requires `manage_options`, as before; tabs carry their own capability (some `edit_posts`) for when
  teachers get access (Phase B scoping).
- The Home demo route is gone (`/` opens the Dashboard); Help & Feedback is a `.page-title-action` on the Dashboard.

### Look

- Lists: `ui/Table` renders WordPress list-table markup (views, bulk actions, search box, sortable columns, row
  actions, pagination); every list screen moves to it. Forms and dialogs stay on `@wordpress/components`.
- Colours (decided 31 October 2026): admin screens follow the user's WordPress admin colour scheme
  (`--wp-admin-theme-color`); `--oml-*` remains for learner pages. The Design system setting (`ohmylms_design_system`,
  Khan Academy preset by default) no longer recolours admin screens by default: add a "WordPress" preset that reads the
  admin colour scheme and make it the admin default; the other presets stay for sites that want a branded admin.
- Remove one-off values: every new-app stylesheet uses WordPress classes or tokens only
  ([DESIGN-TOKENS](DESIGN-TOKENS.md), [STYLESHEET-OWNERSHIP](STYLESHEET-OWNERSHIP.md)); `check:architecture` gains a
  rule against raw colours in `assets/src/app`.

### Done when

Every screen is reachable from the six submenus with the right menu highlight; every address in the redirect fixture
(legacy hash, app hash, PHP links) lands on its new page; list screens use list-table markup; screenshots of each tab
are reviewed; axe has no serious issues; asset budgets hold.

## Phase B: dashboard

Widgets from the prototype, each backed by a hardened, cached, scope-aware endpoint (scope = all schools, one
school or one class from Account Hub; period 7/30/90 days):

| Widget | Source | Notes |
| --- | --- | --- |
| Needs attention (with snooze) | assignments awaiting grading, failed payments, manual-approval orders, at-risk count, stalling courses, items most learners miss | ranked by urgency; snooze per user (user meta, expires) |
| Pulse | earnings, weekly active learners, completions, renewals at risk | sparkline per metric vs the previous period |
| At-risk learners | inactivity, falling mastery, overdue work | the reasons are shown; nudge uses the existing email templates, rate limited |
| Mastery heatmap | class × skill from skill evidence | levels as in XP-MASTERY / Khan: mastered, proficient, familiar, attempted, not started |
| Recommended next assignments | groups below Familiar per skill | "assign review set" creates a draft assignment |
| Course health | completion × rating, drop-off lesson | links to the course report |
| Insights | deterministic rules over the same data | plain sentences with the numbers; no AI calls |
| Activity, site health, weekly goal | existing events and checks | optional cards |

Before building: audit what [DASHBOARD-BENCHMARK](DASHBOARD-BENCHMARK.md) and
[DASHBOARD-LATENCY-INVESTIGATION](DASHBOARD-LATENCY-INVESTIGATION.md) measured; each endpoint gets a latency budget
on the test data and a PHP integration test.

## Phase C: gradebook

Content Hub › Gradebook with five views: Course × Students (audience filter: all / individuals only / one roster),
Roster × Assignments, student transcript, skill mastery, org roll-up (district → school → class). Saved views,
share link and CSV export; per-course weighting with a roster override.

Start with an audit of how enrolments, classes and grades are stored today, then adopt the research's model:
an enrolment records its source (purchase, self, manual, roster, seat); rosters are Account Hub classes; graded items
are quizzes, assignments and exams; "missing" and "not assigned" are distinct states. Migrations must be reversible
and tested on the test site.

## Working method

Same as the modularisation: parallel subagents per area (navigation and routing; list-table and look; one per
dashboard widget group; gradebook model then views), each with tests, docs and a live check on 800.local;
the coordinator owns `LegacyRoutes`, `Menu.php`, the registry and the combined build, and commits to `main`.
