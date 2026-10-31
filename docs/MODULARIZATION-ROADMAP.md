# Modularization roadmap — updated 31 October 2026

The admin is the typed application in `assets/src/app` (`admin.php?page=ohmylms-app`); the recovered admin
was deleted on 31 October 2026 ([CUTOVER](CUTOVER.md)). The LMS counts as fully modular when each feature
owns its UI, state, API access, styles and tests, with explicit imports and documented interfaces, on the
admin **and** the learner side. See [architecture](ARCHITECTURE.md) and [extensions](EXTENSIONS.md).

Statuses below were checked against the code on 31 October 2026. Counts are files unless stated otherwise.

## What remains for full modularization (in order)

1. **Retire or harden the legacy REST routes that outlived their screens.** `ohmylms/v1/question` and
   `/question-bank` (answer keys to `use` grantees, reads that write), `/quiz/{id}/content|questions|pools`
   (no stale check, pool bank not checked), `ohmylms/v1/assignment`, `ohmylms/v1/school/*` (account
   directory PII) and `ohmylms-commerce/v1/subscriptions` keep the findings in their `docs/api/*` audits.
   First move their remaining callers, mainly the 19 files in `assets/src/features` that call `apiFetch`
   (native editors, question editor, papers), to the typed routes.
2. **Decide build-or-drop for workflows that only the deleted legacy admin had** (they now have no screen):
   certificate issuing and learner download; question delete and duplicate-into-quiz; quiz pool and section
   editing; assignment resources, prerequisites, drip and comments; Account Hub school removal, guardian
   lists, reports, gradebook, rollover and sample preview; Content Hub combined mappings, AI runs, shared
   content links, domain placement, skill-owned quizzes and provenance review; syllabus mind map and
   spreadsheet; lesson inline questions, video settings and objectives; exam results release and JSXGraph
   proposals; course purchase points, cohorts and automations; checkout gateway extras.
3. **Extension parity:** render `registerMembershipSettings` in the membership editor (documented in
   EXTENSIONS.md; no app code renders it).
4. **Consolidate shared app infrastructure:** six feature-local `useUnsavedChangesGuard.ts` (certificates,
   courses, gamification, memberships, quizzes, settings) and three `usePagedList.ts` (account-hub,
   memberships, quizzes) duplicate `app/hooks` and `app/lib`.
5. **PHP boundaries:** 35 PHP files still register REST routes outside `includes/Rest/V1`; 16 calls in 11
   files outside `includes/Admin` build admin links from legacy route names through `LegacyRoutes::url()`
   instead of `app_url()`; dead leftovers (`Abstracts\Assets::localize_script_MRM()`,
   `includes/Assets/your-logo.png`, the inert `ohmylms-select2` field classes).
6. **Learner front end to typed modules:** 33 jQuery scripts (`assets/src/frontend/js`, `assets/src/runtimes`)
   and the 124-file JSX/MJS tree in `assets/src/features` (11 folders used by blocks and native editors).
   Replace Slick and the embedded html2pdf bundle in the same batches (step 2).
7. **Extend enforcement:** CI runs `check:ci` and `php -l` only; add `npm test`, the PHP integration suites,
   the `admin-app-smoke` browser spec and the WordPress standards lint, and extend the architecture check to
   the front end and to legacy-route use in PHP.

## Steps

| # | Work | Status | Evidence / remaining |
| --- | --- | --- | --- |
| 1 | Dependency inventory | **Done, retired** | The recovered runtime it inventoried is deleted; report, generator and drift test removed |
| 2 | Third-party libraries | **Partial** | Easy Email, Arco, MJML, CodeMirror, html2canvas (admin copy), Chart.js, Emotion, Tiptap, Lowlight and Lodash left with the legacy admin (50 npm packages, [CUTOVER](CUTOVER.md)). The email editor (`app/features/emails`) uses only WordPress components and the server preview. Dead Select2, Mail Mint image and duplicate Slick copies were removed today. Remaining: vendored Slick 1.8.1 and the embedded html2pdf 0.10.1 bundle. See [libraries](VENDOR-PACKAGE-MIGRATION.md) |
| 3 | Shared application infrastructure | **Partial** | `app/lib/api` (client, collection, errors, query), `app/hooks` (`useOperation`, `useRequest`, `useUnsavedChangesGuard`), `app/routing`, `app/layout`, `assets/src/ui` (13 components). Remaining: the duplicated local hooks in item 4 above |
| 4 | State and API access | **Done (admin app)** | 24 features own an `api.ts` over `app/lib/api`; cross-feature access only through `public.ts` (quizzes, assignments, settings, students, questions); only `app/extensions/api.ts` calls `@wordpress/api-fetch` directly. Native editor code uses `assets/src/features/rest.mjs`; 56 dead legacy helpers removed. Remaining (see [STATE-API-MODULARIZATION](STATE-API-MODULARIZATION.md)): media parsers, syllabus/curriculum route ownership, duplicated validators and hooks, two `question-editor` files on `window.wp.apiFetch`, the schools screen's raw `fetch` |
| 5 | Feature independence | **Done (admin app)** | 25 feature folders, 55 route entries in `features/registry.ts`; no `createComponent(readRuntime)`; the only cross-feature imports are four from `quizzes` into `questions/public.ts` (`checkFeatureBoundaries`). Remaining: the learner tree in item 6 |
| 6 | Authored entry point | **Done** | `app/bootstrap` is the only admin entry (`admin-app.js`, 67 KB); recovered factories, adapters and SDK deleted |
| 7 | Stylesheet ownership | **Done (admin)** | Legacy admin CSS (285 sources) and `tailwind.css` deleted; feature CSS ships with its chunk ([ownership](STYLESHEET-OWNERSHIP.md)). Learner CSS cleanup (`assets/css/style.css`, 476 KB) is deliberately out of scope |
| 8 | Backend boundaries | **Partial** | Audit and plan in [BACKEND-BOUNDARIES](BACKEND-BOUNDARIES.md). Student, StudentPro, StudentReport, QuizReports and ContentHubOverview controllers hold no persistence (new `Quiz\AttemptReports`, `Student\Directory`, `Student\Enrollments`, `Assignment\Submissions`, `ContentHub\ContentCounts`); controller `\` calls 152 to 109; 42 of 81 controllers touch no `\`, options or meta. Remaining: course enrolment writes, Account Hub (38 calls), assignment grading, quiz attempts, commerce, a CI rule against new `\` in controllers |
| 9 | Asset and dependency isolation | **Partial** | Admin: one entry plus 38 shipped hashed lazy chunks with per-screen budgets; React and WordPress packages are externals (`checkDuplicateRuntimes`). Math (836 KB) and JSXGraph (995 KB) load only on pages that use them. Remaining: front-end jQuery scripts, Slick on course/archive pages |
| 10 | Enforcement and release checks | **Partial** | `npm run check:ci` in `.github/workflows/ci.yml`: typecheck, app tests, recovered-runtime imports, cycles, feature boundaries, raw colours, duplicate runtimes, budgets; plus `php -l`. Remaining: item 7 above |

## Feature parity

All stored data is test data; REST shapes and storage may change with PHP and TypeScript together. Every
rebuilt feature has a `WP_REST_Controller` API, `docs/api/<feature>.md`, a PHP integration test and
component tests. Items named "not rebuilt" had a legacy screen that no longer exists.

| Feature | Status | Remaining / notes |
| --- | --- | --- |
| Webhooks, Integrations (Zoom, Google Meet, sign-in), Emails, Sessions | Done | Webhook bulk UI not built (bulk API exists) |
| Certificates | Partial | Templates done. Not rebuilt: issued-certificate records and learner download (still the `Ajax` path with html2pdf) |
| Students, course roster, student report | Done | Open-relay reminder route answers 410; rate-limited reminders live in the course roster |
| Categories and tags | Done | Read-only by design (writes answer 410) |
| Orders, coupons, subscriptions | Done | No refund button by design (server route is guarded); cancel at end of term impossible in the schema |
| Memberships | Partial | Plans and rules done; `registerMembershipSettings` not rendered |
| Settings (all tabs incl. design, course archive, quiz design, xAPI, AI tutor, MCP, migration) | Done | Gateway extras not exposed: checkout order, guest checkout, content protection, enable login, Stripe webhook data |
| Dashboard, earnings, course report | Done | Mixed-currency totals; refunds dated by order |
| Setup wizard, Gamification (incl. Rewards) | Done | |
| Content Hub (overview, skills, curriculum, compare, skill map, subject profiles, bundles) | Partial | Overview exists (`content-hub/OverviewPage.tsx`). Not rebuilt: combined mapping groups, AI runs, shared content links, domain/level placement, learner overlays, skill-owned quizzes, provenance review |
| Syllabus editor | Partial | Not rebuilt: mind map/spreadsheet layouts, skill-group deletion, bulk/combined mapping, AI proposals, source configuration, course requirement controls |
| Courses and course structure | Partial | Not rebuilt: purchase points, cohort editing, automations, drag-and-drop outline |
| Lessons | Partial | Blocks, source, resources, drip/prerequisites/media, extension lesson types done. Not rebuilt: inline question insertion, video settings, lesson objectives, learner preview |
| Assignments | Partial | List, editor, submissions and grading done; emails link `#/assignments/:id/submissions/:sid` (`LegacyRoutes::assignment_grading_url`). Placement read-only; not rebuilt: resources, prerequisites, drip, comments |
| Quizzes, attempts, quiz reports | Partial | Editor, questions, per-question marks, sections and random pools (under the quiz revision lock; `quiz-questions-authoring-integration.php` 61 checks), attempts, grading, import/export and reports done; a live learner attempt grades as authored and autosave is accepted. Remaining: older-version pin UI, the legacy pools route is not hardened |
| Question bank and editor | Partial | List, versions, approve, duplicate, import/export and an editor for all 52 built-in types and every stored field (built-in editors register like add-ons), with per-type REST validation and a real-player preview (`question-types-rest-integration.php` 596 checks; live parity spec). Remaining: delete and duplicate-into-quiz only on legacy routes; no rubric for short/long text; no editor for extension settings |
| Exams and exam import | Partial | Not rebuilt: results release, JSXGraph reconstruction proposals, renderer probe, side-by-side PDF, drag crop |
| Account Hub | Partial | Schools, years, classes, teachers, parents, All accounts and roster intake (invitations, managed students, CSV review/import at `#/account-hub/schools/:id/roster`) done. Not rebuilt: school removal, guardian lists per school, reports, gradebook, rollover, sample preview. Schools have no archive in the code base |
| Extension host | Partial | Pages, screen slots, editor panels by route, integration panels, question and lesson editors, checkout/frontend slots, per-extension error isolation. Membership settings missing |
| Communities | Retired | No backend; add-ons can register a page |
| Cutover | Done | 31 October 2026; old addresses redirect through `LegacyRoutes` |
| Learner front end in TypeScript | Not started | Item 6 above |

## Invariants

- Before retiring an implementation: a real WordPress save → reopen check and a permission check (allowed
  and denied user). Never silently drop or reset the Local database.
- Preserve the hooks and extension points in [EXTENSIONS.md](EXTENSIONS.md); keep server grading
  authoritative.
- Modularization and performance need separate evidence: no speed-up claim without a benchmark.

## History

The recovered-runtime inventory, the step-by-step extraction plan and the 30 October batch logs
(curriculum, syllabus, course settings, lesson authoring, integration checkpoint) described code deleted
at the cutover; read them in git history (`git log -- docs/MODULARIZATION-ROADMAP.md`). The deletion
itself (1,062 recovered source files, 19.6 MB; 612 legacy-only authored files; 285 CSS sources; 43 tools;
118 `tests/js` files; 38 browser specs) is recorded in [CUTOVER](CUTOVER.md).
