# Modularization roadmap — updated 2 November 2026

The admin is the typed application in `assets/src/app` (`admin.php?page=ohmylms-app`); the recovered admin
was deleted on 31 October 2026 ([CUTOVER](CUTOVER.md)). The LMS counts as fully modular when each feature
owns its UI, state, API access, styles and tests, with explicit imports and documented interfaces, on the
admin **and** the learner side. See [architecture](ARCHITECTURE.md) and [extensions](EXTENSIONS.md).

Statuses below were checked against the code on 2 November 2026. Counts are files unless stated otherwise.

## Work done for full modularization (in order; all complete on 2 November 2026)

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
   ([replacements](api/commerce.md#retired-legacy-route)). The `ohmylms_source_test`
   assessment suite moved to the typed routes on 2 November 2026 (question create and edit, add, pin, copy,
   pools, attempt reports and grading); every phase except the manual `xapi-live` passes and runs in CI.
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
   `Quiz\Questions`, `Membership\Members`, `Membership\Subscriptions`, `Skills\SkillEditor`,
   `Gradebook\Repository`, the WP Fusion `Triggers` repository, the e-commerce `OrderStore` and others).
   On 1 November 2026 the last four feature controllers (gradebook, WP Fusion triggers, Zoom webhook,
   e-commerce orders) were emptied too: no REST controller anywhere uses `$wpdb`, and the
   `controller-persistence` ratchet has no budgets left. Every MySQL advisory lock goes through
   `OhMyLMS\Utility\Lock` (`with`, `acquire`/`release`, and option-row locks), about 40 callers with the same
   names, timeouts and busy errors; rule p fails `GET_LOCK` anywhere else. `LegacyRoutes::url()` is limited
   to `includes/Admin` and `NativeLessonEditor` (rule k); the inert select2 classes are gone
   ([BACKEND-BOUNDARIES](BACKEND-BOUNDARIES.md)).
6. **Learner front end and native editors.** Done on 31 October 2026. The learner front end runs on
   Interactivity modules (L1–L7; behaviour map in [LEARNER-FRONTEND](LEARNER-FRONTEND.md)); `ohmylms.js`,
   the jQuery video scripts, Slick and html2pdf are deleted and learner pages load no jQuery.
   `assets/src/features` (native editors and blocks) and `assets/interactivity` are type-checked through
   JSDoc with `strict` on since 1 November 2026 (`npm run typecheck:js`, 0 errors, in `check:ci`; about
   3,800 strict errors fixed). Each feature folder that others use has a `public.mjs`, and rule n allows a
   cross-folder import only through it (three allow-listed exceptions with reasons: an import cycle, a
   side-effect block registration, a JSX file Node tests cannot load). Front-end rules l–o forbid cycles,
   jQuery and relative SDK imports.
7. **Extend enforcement.** Done on 31 October 2026 ([ENFORCEMENT](ENFORCEMENT.md#ci-jobs)): six CI jobs
   (build and `check:ci`, `lint:js`, PHP lint and 58 unit suites, phpcs on changed lines, PHP integration on
   WordPress + MySQL with the disposable source, migration and streak sites and `pdftoppm`, and the
   Playwright browser specs). All six jobs passed on GitHub on 1 November 2026. The 18 suites that ran as
   expected failures were ported or fixed the same day (11 written against older APIs, 6 stale after
   deliberate changes, 1 real bug: syllabus courses could publish with a required skill that had no
   approved questions), so `tests/php-suites.json` lists no expected failures. On 2 November 2026 the six
   Local-content suites were made self-contained (they create their own course, syllabus and fixture subject
   profile, read enrolment sources from the migrated table, check add-on question types with a fixture type,
   and the extension host suite runs against `build/sdk`, which CI now builds), so `tests/php-suites.json` has
   no CI-only skips, and the lint job runs Stylelint too. The same day every PHP file reached 0 phpcs errors
   (2,124 cleared: 1,011 by phpcbf, the rest by hand, with narrow reasoned ignores for false positives) and the
   phpcs job fails on any error in any file; about 2,400 legacy warnings remain and are not enforced.
8. **Interface languages** (added 31 October 2026). English and Mongolian: learners switch in the learner
   chrome and Student Hub, visitors follow their browser, staff use their profile language, and emails are
   composed in each recipient's language with saved templates storing only changed fields
   ([DEVELOPMENT](DEVELOPMENT.md#interface-languages)). Remaining: native review of 10,991 AI-drafted
   Mongolian strings (fuzzy) and the flagged terms in [DEVELOPMENT](DEVELOPMENT.md#translations).
## Steps

| # | Work | Status | Evidence / remaining |
| --- | --- | --- | --- |
| 1 | Dependency inventory | **Done, retired** | The recovered runtime it inventoried is deleted; report, generator and drift test removed |
| 2 | Third-party libraries | **Done** | Easy Email, Arco, MJML, CodeMirror, html2canvas (admin copy), Chart.js, Emotion, Tiptap, Lowlight and Lodash left with the legacy admin (50 npm packages, [CUTOVER](CUTOVER.md)). The email editor (`app/features/emails`) uses only WordPress components and the server preview. Dead Select2, Mail Mint image, Slick 1.8.1 (replaced by `ohmylms/carousel`) and the html2pdf 0.10.1 bundle were removed on 31 October 2026. MathLive and JSXGraph stay on purpose as runtime libraries: each is pinned, loaded only on the pages that use it and exposed through one handle (`ohmylms-mathlive`, `ohmylms-jsxgraph`). See [libraries](VENDOR-PACKAGE-MIGRATION.md) |
| 3 | Shared application infrastructure | **Done** | `app/lib/api`, `app/lib/validation.ts`, `app/lib/media.ts`, `app/hooks` (`useOperation`, `useRequest`, `usePagedList`, `useUnsavedChangesGuard`), `app/components/MediaPicker`, `app/routing`, `app/layout`, `assets/src/ui`. Rules g and `shared-helpers` fail feature copies |
| 4 | State and API access | **Done** | 24 features own an `api.ts` over `app/lib/api`; cross-feature access only through `public.ts`; only `app/extensions/api.ts` calls `@wordpress/api-fetch` directly. Validators and media parsing are shared. Native editor code uses `assets/src/features/rest.mjs` (no direct `wp.apiFetch`) and is strict type-checked. `curriculum` owns `/syllabus-comparison/*`, which `syllabus` calls through `curriculum/public.ts` ([STATE-API-MODULARIZATION](STATE-API-MODULARIZATION.md)) |
| 5 | Feature independence | **Done** | 25 feature folders, 55 route entries in `features/registry.ts`; no `createComponent(readRuntime)`; the only cross-feature imports are four from `quizzes` into `questions/public.ts` (`checkFeatureBoundaries`). The learner tree follows the same rule through `public.mjs` (item 6, front-end rule n) |
| 6 | Authored entry point | **Done** | `app/bootstrap` is the only admin entry (`admin-app.js`, 67 KB); recovered factories, adapters and SDK deleted |
| 7 | Stylesheet ownership | **Done** | Legacy admin CSS (285 sources) and `tailwind.css` deleted; feature CSS ships with its chunk ([ownership](STYLESHEET-OWNERSHIP.md)). Admin spacing and radius use `--oml-space-*`/`--oml-radius-*` (rule `checkRawSpacing`); learner SCSS colours read `--oml-*` with fallbacks (stylelint `ohmylms/no-raw-svg-colour`); PHP inline colours became `oml-ui.css` classes ([design system](DESIGN-SYSTEM.md#known-gaps)). The generated compatibility `style.css`/`style-rtl.css` (about 1.09 MB with maps) were removed in 1.2.22; learner pages load only their page modules, and the old URLs are retired assets (404) |
| 8 | Backend boundaries | **Done** | Every route in a `WP_REST_Controller` subclass; no `$wpdb` in any of the 141 REST controller files (`Rest/V1` alone had 253 lines in 28 controllers on 31 October); persistence in feature services; `controller-persistence` ratchet with no budgets; one advisory-lock helper (`Utility\Lock`, rule p) ([BACKEND-BOUNDARIES](BACKEND-BOUNDARIES.md)) |
| 9 | Asset and dependency isolation | **Done** | Admin: one entry plus hashed lazy chunks with per-screen budgets; React and WordPress packages are externals (`checkDuplicateRuntimes`). Math (836 KB) and JSXGraph (995 KB) load only where used. Learner: script modules only; video provider APIs load lazily per provider; no jQuery on learner pages (`learner-no-jquery` browser spec) |
| 10 | Enforcement and release checks | **Done** | Six CI jobs ([ENFORCEMENT](ENFORCEMENT.md#ci-jobs)): `check:ci` (typecheck, `typecheck:js`, app tests, architecture rules a–o incl. REST controllers, controller persistence, shared helpers, legacy routes, front-end rules, raw colours and spacing, budgets) and `npm test`; `lint:js`; PHP lint and unit suites; phpcs errors in every file and on changed lines; Stylelint; PHP integration with disposable sites; Playwright |

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
| Content Hub (overview, skills, curriculum, compare, skill map, subject profiles, bundles) | Done | Combined mappings, AI runs, shared content links, domain placement, skill-owned quizzes and provenance review rebuilt as [skill workspace](api/skill-workspace.md) sections of Content Hub › Skills (`skill-workspace-rest-integration.php` 122 checks; site administrators only). The skill map page with learner overlays (a learner's, class's or school's mastery levels on each skill) is open to site administrators, school administrators and teachers; staff see only their schools' and classes' learners and editing stays with administrators (`skill-map-overlay-integration.php` 119 checks) |
| Syllabus editor | Done | One outline/detail workspace (mind map and spreadsheet layouts removed on 1 November 2026 per [Learning Architecture](LEARNING-ARCHITECTURE.md); `sheet_edit` stays for API callers). Skill-group deletion with confirmation, source and profile editing with category icon/colour styles, course requirement controls (required flag and target per skill, per-group bulk), reviewed publication of the mirrored course (the course editor's Publish panel), the `syllabus.skill.resources` extension slot, and the reviewed course-from-syllabus workflow from the syllabus page and the course list ([syllabus authoring](api/syllabus-authoring.md), [course from syllabus](COURSE-FROM-SYLLABUS.md#screen); `syllabus-editor-rest-integration.php` 97 checks, `syllabus-course-integration.php` 32 checks) |
| Courses and course structure | Done | Point price on the Settings tab; cohorts and automations are Commerce tabs; drag-and-drop outline (units, items within and between units; keyboard path and announcements; immediate save with rollback when the draft is clean). No auto-scroll while dragging |
| Lessons | Done | Blocks, source, resources, drip/prerequisites/media, extension lesson types, inline questions (list/insert/reorder/remove, question editor links; native panel lists and swaps checks), video settings with a server-enforced watched-% rule, and objectives (skills + syllabus objective mappings, course objectives as choices) done (`lesson-settings-integration.php` 74 checks). Learner preview: signed, one-hour links for editors open the real learner template as a new learner, record nothing and can bypass locks (`lesson-preview-integration.php` 77 checks) |
| Assignments | Done | List, editor, submissions and grading done; resources, prerequisites, drip release (enforced on submit) and submission comment threads with emails done (`assignment-delivery-integration.php` 102 checks). Placement stays owned by the course outline (decision in [api](api/assignments.md)): the editor lists placements and links each to the outline focused on it, where it is moved or detached and saved with the outline draft |
| Quizzes, attempts, quiz reports | Done | Editor, questions, per-question marks, sections and random pools (under the quiz revision lock), pinning any saved question version from a Versions dialog (`quiz-questions-authoring-integration.php` 72 checks), attempts, grading with rubric breakdowns validated against the delivered rubric and stored in the grade history (shown to learners only when feedback is released; `quizzes-rest-integration.php` 276 checks), import/export and reports done; a live learner attempt grades as authored and autosave is accepted. The legacy pools route is removed and has no callers. Quiz revisions themselves cannot be pinned (new attempts use the current content) |
| Question bank and editor | Done | List, versions, approve, duplicate, import/export and an editor for all 52 built-in types and every stored field (built-in editors register like add-ons), with per-type REST validation and a real-player preview (`question-types-rest-integration.php` 645 checks; live parity spec). Delete and copy-into-quiz on typed routes. Marking rubric for short and long text (versioned, never delivered, used in grading); add-on question settings edited through schema-generated fields or `registerQuestionSettings` panels |
| Exams and exam import | Done | Results release (policy, single/bulk release with email, undo before view, log; enforced in every result surface) and JSXGraph proposals (generate/author, accept instead of or beside the crop, reject) rebuilt ([api](api/exams.md)). The release email is on the Emails screen; held scores are hidden from every gradebook until released. Import review shows the renderer status, the source page beside each question and a drag/keyboard crop tool, with owner-only page images. The server checks every crop (size and page when queued, pixels inside the page raster, a figure's page and bounds against its crop on save and accept) and the review screen offers a redraw; Imagick (Ghostscript) renders the same rasters when Poppler is not usable, Poppler preferred (`exam-import-source-integration.php` 130 checks, 142 with Imagick) |
| Account Hub | Done | Schools, years, classes, teachers, parents, All accounts, roster intake, school archive/restore/delete, guardians per school and class, school report with CSV, year rollover with roster promotion, class work and grading, sample school done (`account-hub-schools-lifecycle-rest-integration.php` 226 checks). Every rollover, the School workspace's included, goes through `Schools\Rollover`: classes move with their active students, classes with none are skipped unless `include_empty` is set, and classes without a grade are kept; `Service::rollover` was removed. Panels load as their own chunks |
| Extension host | Done | Pages, screen slots (including `syllabus.skill.resources`), editor panels by route, integration panels, question and lesson editors, question settings (`registerQuestionSettings` or schema-generated fields), membership settings panels in the plan editor, checkout/frontend slots, per-extension error isolation. Every registration function in [EXTENSIONS](EXTENSIONS.md) is implemented |
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
