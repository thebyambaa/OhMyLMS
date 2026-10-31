# Modularization roadmap — updated 31 October 2026

The admin is the typed application in `assets/src/app` (`admin.php?page=ohmylms-app`); the recovered admin
was deleted on 31 October 2026 ([CUTOVER](CUTOVER.md)). The LMS counts as fully modular when each feature
owns its UI, state, API access, styles and tests, with explicit imports and documented interfaces, on the
admin **and** the learner side. See [architecture](ARCHITECTURE.md) and [extensions](EXTENSIONS.md).

Statuses below were checked against the code on 31 October 2026. Counts are files unless stated otherwise.

## What remains for full modularization (in order)

1. **Retire or harden the legacy REST routes that outlived their screens.** Done on 31 October 2026 for
   `ohmylms/v1/question`, `/question-bank` (except the typed import), `/quiz/{id}/content|questions|pools`,
   `ohmylms/v1/assignment` and `ohmylms/v1/session`: their callers (native question, quiz and lesson editors,
   papers, exam paper review, export buttons, review queue, MCP tools) moved to the typed routes and the
   routes were removed (deprecation tables in [questions](api/questions.md#removed-legacy-routes),
   [quizzes](api/quizzes.md#removed-legacy-routes), [assignments](api/assignments.md#removed-legacy-routes),
   [sessions](api/sessions.md)). `ohmylms/v1/school/*` followed the same day: the platform school editor and
   `Schools\Controller` were deleted, the hub invitation form uses `account-hub/invitations/accept`, and every route
   except View as was removed ([replacements](api/account-hub.md#retired-legacy-routes)).
   `ohmylms-commerce/v1/subscriptions` (and `cx-ecommerce/v1/subscriptions`) was removed too
   ([replacements](api/commerce.md#retired-legacy-route)). Remaining: the `ohmylms_source_test`
   assessment suite still needs its question fixtures ported to the typed routes.
2. **Rebuild the workflows that only the deleted legacy admin had.** Done on 31 October 2026 (decided the
   same day: rebuild all twelve, none dropped): certificate issuing and learner download
   ([certificates](api/certificates.md)); question delete and duplicate-into-quiz; quiz pool and section
   editing; assignment resources, prerequisites, drip and comments; Account Hub school removal, guardians,
   reports, rollover and sample preview; the gradebook ([gradebook](api/gradebook.md), Content Hub ›
   Gradebook, open to school staff for their own scopes); the [skill workspace](api/skill-workspace.md)
   (Content Hub › Skills sections); syllabus mind map and spreadsheet; lesson inline questions, video
   settings and objectives; course point prices, [cohorts](api/cohorts.md) and
   [automations](api/automations.md) (own Commerce tabs); checkout extras; exam results release and JSXGraph
   proposals ([exams](api/exams.md)). Every old `?tab=`, `?panel=` and `?list=` address redirects.
3. **Extension parity:** render `registerMembershipSettings` in the membership editor. Done on 31 October
   2026: the plan editor renders the panels whose IDs PHP registered for the `membership` context and saves
   their values as `extension_settings` with the plan ([memberships](api/memberships.md#extension-settings)).
4. **Consolidate shared app infrastructure:** done on 31 October 2026 for the guard and list hooks. The
   six feature `useUnsavedChangesGuard.ts` files (certificates, courses, gamification, memberships, quizzes,
   settings) and the copy inside `emails/hooks.ts` were replaced by `app/hooks/useUnsavedChangesGuard`. It
   gained `ignoreSamePath`, for the gamification and settings section links, and `requestLeave`, for the
   email editor's back button. The three `usePagedList.ts` (account-hub, memberships, quizzes) became
   `app/hooks/usePagedList`, which adds the `inAddress` and `keepPrevious` options. `useRequest` gained a
   `keepData` option, used by the quiz course filters. The architecture check fails if a feature defines
   either hook again ([rule g](ENFORCEMENT.md#g-features-use-the-shared-hooks-instead-of-their-own-copies)).
   Remaining, because behaviour differs (see [STATE-API-MODULARIZATION](STATE-API-MODULARIZATION.md#remaining-with-reasons)):
   - the keyed `useRequest` in commerce, students, dashboard and taxonomies, which keeps data and
     `ApiError` objects and offers `replace`;
   - `useOperation` in commerce, students and integrations, whose errors are `null` or an `ApiError`
     and which have no success callback;
   - the `record`, `text` and `positiveId` validators, which throw feature-specific error messages;
   - the media library parsers.
5. **PHP boundaries:** 35 PHP files still register REST routes outside `includes/Rest/V1`; outside
   `includes/Admin`, `Schools\Views` (two Account Hub links) still builds admin links from legacy route
   names through `LegacyRoutes::url()` instead of `app_url()` (`NativeLessonEditor::return_url()` keeps it
   because its input, `ohmylms_return` and assessment profiles' `return_route`, is a legacy route); the two
   inert `ohmylms-select2` field classes in `Admin\Pages\GeneralSettings`.
6. **Learner front end to Interactivity modules** (plan and behaviour map: [LEARNER-FRONTEND](LEARNER-FRONTEND.md)).
   Done on 31 October 2026: one source per learner script (the `runtimes/dist` reprints and stale
   `assets/dist/frontend` bundles deleted); Slick replaced by `ohmylms/carousel`; add to cart, checkout, tax
   and Stripe moved to `ohmylms/cart`, `ohmylms/checkout` and `ohmylms/stripe`; the React learner paper and
   print views replaced by server-rendered markup with `ohmylms/paper` and `ohmylms/paper-print`; html2pdf
   and the certificate template deleted; `ohmylms.js` trimmed from 112 KB to 73 KB of live code. Remaining
   (batches in the plan): shared request/toast helpers, catalog search and load more, learner dashboard and
   profile, login/signup and reviews, the lesson and assignment player, and the two video scripts; the
   124-file JSX/MJS tree in `assets/src/features` used by blocks and native editors.
7. **Extend enforcement:** Partly done on 31 October 2026 ([ENFORCEMENT](ENFORCEMENT.md#ci-jobs)): CI also
   runs `npm test`, `npm run lint:js` (LF via `.gitattributes`, Prettier `endOfLine: 'auto'` for older
   Windows checkouts), the 58 PHP unit suites, 75 PHP integration suites on a fresh WordPress + MySQL 8
   install (`tools/install-test-wordpress.sh`, `tools/run-php-tests.mjs`) and phpcs on changed lines
   (`tools/phpcs-changed.mjs`; the full run still reports about 2,200 legacy errors). Remaining
   (`tests/php-suites.json`): fix the seven integration suites expected to fail at HEAD; give CI the 52
   suites that need the disposable `ohmylms_source_test`, migration or streak databases or `pdftoppm`, and
   the six that need content of the Local site; the `admin-app-smoke` browser spec; a clean full phpcs run;
   and extending the architecture check to the front end and to legacy-route use in PHP.

## Steps

| # | Work | Status | Evidence / remaining |
| --- | --- | --- | --- |
| 1 | Dependency inventory | **Done, retired** | The recovered runtime it inventoried is deleted; report, generator and drift test removed |
| 2 | Third-party libraries | **Partial** | Easy Email, Arco, MJML, CodeMirror, html2canvas (admin copy), Chart.js, Emotion, Tiptap, Lowlight and Lodash left with the legacy admin (50 npm packages, [CUTOVER](CUTOVER.md)). The email editor (`app/features/emails`) uses only WordPress components and the server preview. Dead Select2, Mail Mint image, Slick 1.8.1 (replaced by `ohmylms/carousel`) and the html2pdf 0.10.1 bundle were removed on 31 October 2026. Remaining: the runtime libraries listed there (MathLive, JSXGraph). See [libraries](VENDOR-PACKAGE-MIGRATION.md) |
| 3 | Shared application infrastructure | **Partial** | `app/lib/api` (client, collection, errors, query), `app/hooks` (`useOperation`, `useRequest`, `usePagedList`, `useUnsavedChangesGuard`), `app/routing`, `app/layout`, `assets/src/ui` (13 components). Feature copies of the guard and list hooks were removed, and `checkSharedHookCopies` keeps them out. Remaining: the keyed `useRequest` and `useOperation` variants listed in item 4 above |
| 4 | State and API access | **Done (admin app)** | 24 features own an `api.ts` over `app/lib/api`; cross-feature access only through `public.ts` (quizzes, assignments, settings, students, questions); only `app/extensions/api.ts` calls `@wordpress/api-fetch` directly. Native editor code uses `assets/src/features/rest.mjs`; 56 dead legacy helpers removed. Remaining (see [STATE-API-MODULARIZATION](STATE-API-MODULARIZATION.md)): media parsers, syllabus/curriculum route ownership, duplicated validators and hooks, two `question-editor` files on `window.wp.apiFetch` |
| 5 | Feature independence | **Done (admin app)** | 25 feature folders, 55 route entries in `features/registry.ts`; no `createComponent(readRuntime)`; the only cross-feature imports are four from `quizzes` into `questions/public.ts` (`checkFeatureBoundaries`). Remaining: the learner tree in item 6 |
| 6 | Authored entry point | **Done** | `app/bootstrap` is the only admin entry (`admin-app.js`, 67 KB); recovered factories, adapters and SDK deleted |
| 7 | Stylesheet ownership | **Done (admin)** | Legacy admin CSS (285 sources) and `tailwind.css` deleted; feature CSS ships with its chunk ([ownership](STYLESHEET-OWNERSHIP.md)). Learner CSS cleanup (`assets/css/style.css`, 476 KB) is deliberately out of scope |
| 8 | Backend boundaries | **Partial** | Audit and plan in [BACKEND-BOUNDARIES](BACKEND-BOUNDARIES.md). Student, StudentPro, StudentReport, QuizReports and ContentHubOverview controllers hold no persistence (new `Quiz\AttemptReports`, `Student\Directory`, `Student\Enrollments`, `Assignment\Submissions`, `ContentHub\ContentCounts`); controller `\` calls 152 to 109; 42 of 81 controllers touch no `\`, options or meta. Remaining: course enrolment writes, Account Hub (38 calls), assignment grading, quiz attempts, commerce, a CI rule against new `\` in controllers |
| 9 | Asset and dependency isolation | **Partial** | Admin: one entry plus 38 shipped hashed lazy chunks with per-screen budgets; React and WordPress packages are externals (`checkDuplicateRuntimes`). Math (836 KB) and JSXGraph (995 KB) load only on pages that use them. Learner: commerce, carousel and paper are script modules with no jQuery. Remaining: `ohmylms.js` and the two video scripts still depend on jQuery |
| 10 | Enforcement and release checks | **Partial** | Five parallel jobs in `.github/workflows/ci.yml` ([ENFORCEMENT](ENFORCEMENT.md#ci-jobs)): `check:ci` (typecheck, app tests, recovered-runtime imports, cycles, feature boundaries, raw colours, duplicate runtimes, budgets) and `npm test`; `lint:js`; `php -l` and the PHP unit suites; phpcs on changed lines; the PHP integration suites against WordPress + MySQL. Skips and expected failures are listed with reasons in `tests/php-suites.json`. Remaining: item 7 above |

## Feature parity

All stored data is test data; REST shapes and storage may change with PHP and TypeScript together. Every
rebuilt feature has a `WP_REST_Controller` API, `docs/api/<feature>.md`, a PHP integration test and
component tests. Items named "not rebuilt" had a legacy screen that no longer exists.

| Feature | Status | Remaining / notes |
| --- | --- | --- |
| Webhooks, Integrations (Zoom, Google Meet, sign-in), Emails, Sessions | Done | Webhook bulk UI not built (bulk API exists) |
| Certificates | Done | Templates, issued records (Engagement › Gamification › Issued certificates), learner download and public verification |
| Students, course roster, student report | Done | Open-relay reminder route answers 410; rate-limited reminders live in the course roster |
| Categories and tags | Done | Read-only by design (writes answer 410) |
| Orders, coupons, subscriptions | Done | No refund button by design (server route is guarded); cancel at end of term impossible in the schema |
| Memberships | Done | Plans, rules and `registerMembershipSettings` panels |
| Settings (all tabs incl. design, course archive, quiz design, xAPI, AI tutor, MCP, migration) | Done | Checkout extras (gateway order, guest checkout, login form, content protection, read-only Stripe status) on the Payment gateways tab |
| Dashboard, earnings, course report | Done | Scoped widget dashboard for admins, school administrators and teachers (each widget its own chunk); teachers may create review-set draft quizzes for their own classes, which an administrator publishes. Mixed-currency totals; refunds dated by order |
| Gradebook | Done | Course × students, roster × assignments, student transcript, skill mastery and roll-up on the `ohmylms_enrolments` table; overrides keep a reason and history; per-course weighting with class override; site-wide letter scale (Settings › Letter grades) |
| Setup wizard, Gamification (incl. Rewards) | Done | |
| Content Hub (overview, skills, curriculum, compare, skill map, subject profiles, bundles) | Partial | Combined mappings, AI runs, shared content links, domain placement, skill-owned quizzes and provenance review rebuilt as [skill workspace](api/skill-workspace.md) sections of Content Hub › Skills (`skill-workspace-rest-integration.php` 122 checks; site administrators only). Not rebuilt: learner overlays on the skill map |
| Syllabus editor | Partial | Mind map and spreadsheet layouts done (`?layout=`; atomic, stale-checked `sheet_edit`; reviewed bulk paste). Not rebuilt: skill-group deletion, source configuration, course requirement controls |
| Courses and course structure | Partial | Point price on the Settings tab; cohorts and automations are Commerce tabs. Not rebuilt: drag-and-drop outline |
| Lessons | Partial | Blocks, source, resources, drip/prerequisites/media, extension lesson types, inline questions (list/insert/reorder/remove, question editor links; native panel lists and swaps checks), video settings with a server-enforced watched-% rule, and objectives (skills + syllabus objective mappings, course objectives as choices) done (`lesson-settings-integration.php` 74 checks). Not rebuilt: learner preview |
| Assignments | Partial | List, editor, submissions and grading done; resources, prerequisites, drip release (enforced on submit) and submission comment threads with emails done (`assignment-delivery-integration.php` 102 checks). Placement read-only |
| Quizzes, attempts, quiz reports | Partial | Editor, questions, per-question marks, sections and random pools (under the quiz revision lock; `quiz-questions-authoring-integration.php` 61 checks), attempts, grading, import/export and reports done; a live learner attempt grades as authored and autosave is accepted. Remaining: older-version pin UI, the legacy pools route is not hardened |
| Question bank and editor | Partial | List, versions, approve, duplicate, import/export and an editor for all 52 built-in types and every stored field (built-in editors register like add-ons), with per-type REST validation and a real-player preview (`question-types-rest-integration.php` 596 checks; live parity spec). Delete and copy-into-quiz on typed routes. Remaining: no rubric for short/long text; no editor for extension settings |
| Exams and exam import | Partial | Results release (policy, single/bulk release with email, undo before view, log; enforced in every result surface) and JSXGraph proposals (generate/author, accept instead of or beside the crop, reject) rebuilt ([api](api/exams.md)). The release email is on the Emails screen; held scores are hidden from every gradebook until released. Not rebuilt: renderer probe, side-by-side PDF, drag crop |
| Account Hub | Partial | Schools, years, classes, teachers, parents, All accounts, roster intake, school archive/restore/delete, guardians per school and class, school report with CSV, year rollover with roster promotion, class work and grading, sample school done (`account-hub-schools-lifecycle-rest-integration.php` 212 checks). Rollover skips classes with no active students unless `include_empty` is set. Panels load as their own chunks. Remaining: the older `Lifecycle::rollover` and `Service::rollover` paths still copy empty classes |
| Extension host | Partial | Pages, screen slots, editor panels by route, integration panels, question and lesson editors, checkout/frontend slots, per-extension error isolation. Membership settings missing |
| Communities | Retired | No backend; add-ons can register a page |
| Cutover | Done | 31 October 2026; old addresses redirect through `LegacyRoutes` |
| Learner front end on Interactivity | Partial | Carousel, commerce and exam paper done; `ohmylms.js` and the video scripts remain (item 6 above) |

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
