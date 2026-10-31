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
4. **Consolidate shared app infrastructure.** Done on 31 October 2026. `app/hooks` holds the one
   `useUnsavedChangesGuard`, `usePagedList`, `useRequest` (options `keepData`, `key`, `errorShape`,
   `replace`) and `useOperation` (`errorShape`, success callback); `app/lib/validation.ts` the `record`,
   `text` and `positiveId` validators with each feature's message; `app/lib/media.ts` and
   `app/components/MediaPicker` the one image parser and WordPress media-library field (course thumbnail,
   certificate images, badges, hub icons). Rules g and `shared-helpers` fail new copies
   ([ENFORCEMENT](ENFORCEMENT.md)). Kept as feature hooks: `useIntegrations` and `useIntegrationSettings`.
5. **PHP boundaries.** Done on 31 October 2026. Every REST route is registered by a `WP_REST_Controller`
   subclass: 22 new controllers (including QPay and the PayPal webhook) and 10 existing classes that now
   extend it (rule `rest-controllers`). No controller in `includes/Rest/V1` uses `$wpdb`; SQL, locks and revision checks
   live in feature services (`Schools\Directory`, `Schools\Versions`, `Schools\RosterImport`,
   `Quiz\Questions`, `Membership\Members`, `Membership\Subscriptions`, `Skills\SkillEditor` and others), and
   the `controller-persistence` ratchet budgets the four feature controllers that still do (gradebook,
   WP Fusion triggers, Zoom webhook, e-commerce orders). `LegacyRoutes::url()` is limited to
   `includes/Admin` and `NativeLessonEditor` (rule k); the inert select2 classes are gone. Remaining: those
   four controllers and one shared advisory-lock helper ([BACKEND-BOUNDARIES](BACKEND-BOUNDARIES.md)).
6. **Learner front end and native editors.** Done on 31 October 2026. The learner front end runs on
   Interactivity modules (L1–L7; behaviour map in [LEARNER-FRONTEND](LEARNER-FRONTEND.md)); `ohmylms.js`,
   the jQuery video scripts, Slick and html2pdf are deleted and learner pages load no jQuery.
   `assets/src/features` (native editors and blocks) and `assets/interactivity` are type-checked through
   JSDoc (`npm run typecheck:js`, 0 errors, in `check:ci`); front-end rules l–o forbid cycles, jQuery,
   relative SDK imports and new cross-folder imports. Remaining: `strict` mode (about 2,469 and 1,331
   errors) and per-folder entry points for the 21 allow-listed folder pairs.
7. **Extend enforcement.** Done on 31 October 2026 ([ENFORCEMENT](ENFORCEMENT.md#ci-jobs)): six CI jobs
   (build and `check:ci`, `lint:js`, PHP lint and 58 unit suites, phpcs on changed lines, PHP integration on
   WordPress + MySQL with the disposable source, migration and streak sites and `pdftoppm`, and the
   Playwright browser specs). Remaining: 19 suites run as expected failures on the disposable sites (11
   written against older APIs, 8 undiagnosed; `tests/php-suites.json`), the six Local-content suites, and a
   clean full phpcs run (about 2,200 legacy errors).
8. **Interface languages** (added 31 October 2026). English and Mongolian: learners switch in the learner
   chrome and Student Hub, visitors follow their browser, staff use their profile language, and emails are
   composed in each recipient's language with saved templates storing only changed fields
   ([DEVELOPMENT](DEVELOPMENT.md#interface-languages)). Remaining: native review of 10,991 AI-drafted
   Mongolian strings (fuzzy) and the flagged terms in [DEVELOPMENT](DEVELOPMENT.md#translations).
## Steps

| # | Work | Status | Evidence / remaining |
| --- | --- | --- | --- |
| 1 | Dependency inventory | **Done, retired** | The recovered runtime it inventoried is deleted; report, generator and drift test removed |
| 2 | Third-party libraries | **Partial** | Easy Email, Arco, MJML, CodeMirror, html2canvas (admin copy), Chart.js, Emotion, Tiptap, Lowlight and Lodash left with the legacy admin (50 npm packages, [CUTOVER](CUTOVER.md)). The email editor (`app/features/emails`) uses only WordPress components and the server preview. Dead Select2, Mail Mint image, Slick 1.8.1 (replaced by `ohmylms/carousel`) and the html2pdf 0.10.1 bundle were removed on 31 October 2026. Remaining: the runtime libraries listed there (MathLive, JSXGraph). See [libraries](VENDOR-PACKAGE-MIGRATION.md) |
| 3 | Shared application infrastructure | **Done** | `app/lib/api`, `app/lib/validation.ts`, `app/lib/media.ts`, `app/hooks` (`useOperation`, `useRequest`, `usePagedList`, `useUnsavedChangesGuard`), `app/components/MediaPicker`, `app/routing`, `app/layout`, `assets/src/ui`. Rules g and `shared-helpers` fail feature copies |
| 4 | State and API access | **Done** | 24 features own an `api.ts` over `app/lib/api`; cross-feature access only through `public.ts`; only `app/extensions/api.ts` calls `@wordpress/api-fetch` directly. Validators and media parsing are shared. Native editor code uses `assets/src/features/rest.mjs` and is type-checked. Remaining (see [STATE-API-MODULARIZATION](STATE-API-MODULARIZATION.md)): syllabus/curriculum route ownership, two `question-editor` files on `window.wp.apiFetch` |
| 5 | Feature independence | **Done (admin app)** | 25 feature folders, 55 route entries in `features/registry.ts`; no `createComponent(readRuntime)`; the only cross-feature imports are four from `quizzes` into `questions/public.ts` (`checkFeatureBoundaries`). Remaining: the learner tree in item 6 |
| 6 | Authored entry point | **Done** | `app/bootstrap` is the only admin entry (`admin-app.js`, 67 KB); recovered factories, adapters and SDK deleted |
| 7 | Stylesheet ownership | **Done (admin); learner colours on tokens** | Legacy admin CSS (285 sources) and `tailwind.css` deleted; feature CSS ships with its chunk ([ownership](STYLESHEET-OWNERSHIP.md)). Admin spacing and radius use `--oml-space-*`/`--oml-radius-*` (rule `checkRawSpacing`); learner SCSS colours read `--oml-*` with fallbacks (stylelint `ohmylms/no-raw-svg-colour`); PHP inline colours became `oml-ui.css` classes ([design system](DESIGN-SYSTEM.md#known-gaps)). Remaining: the generated compatibility `assets/css/style.css` (483 KB) stays for old URLs |
| 8 | Backend boundaries | **Done (REST controllers)** | Every route in a `WP_REST_Controller` subclass; no `$wpdb` in `includes/Rest/V1` (253 lines in 28 controllers before); persistence in feature services; `controller-persistence` ratchet over all 141 controller files. Remaining: four budgeted feature controllers (gradebook 7, WP Fusion triggers 13, Zoom webhook 4, e-commerce orders 10) and a shared advisory-lock helper ([BACKEND-BOUNDARIES](BACKEND-BOUNDARIES.md)) |
| 9 | Asset and dependency isolation | **Done** | Admin: one entry plus hashed lazy chunks with per-screen budgets; React and WordPress packages are externals (`checkDuplicateRuntimes`). Math (836 KB) and JSXGraph (995 KB) load only where used. Learner: script modules only; video provider APIs load lazily per provider; no jQuery on learner pages (`learner-no-jquery` browser spec) |
| 10 | Enforcement and release checks | **Done** | Six CI jobs ([ENFORCEMENT](ENFORCEMENT.md#ci-jobs)): `check:ci` (typecheck, `typecheck:js`, app tests, architecture rules a–o incl. REST controllers, controller persistence, shared helpers, legacy routes, front-end rules, raw colours and spacing, budgets) and `npm test`; `lint:js`; PHP lint and unit suites; phpcs on changed lines; PHP integration with disposable sites; Playwright. Remaining: item 7 above |

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
| Extension host | Partial | Pages, screen slots, editor panels by route, integration panels, question and lesson editors, checkout/frontend slots, per-extension error isolation. Membership settings panels render in the plan editor |
| Communities | Retired | No backend; add-ons can register a page |
| Cutover | Done | 31 October 2026; old addresses redirect through `LegacyRoutes` |
| Learner front end on Interactivity | Done | L1–L7, 31 October 2026. `ohmylms.js` is deleted; `ohmylms-frontend` stays as a script-less handle (item 6 above) |

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
