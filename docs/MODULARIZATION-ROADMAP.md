# Modularization roadmap — updated 30 October 2026

## Current rebuild direction

The new admin is a typed application in `assets/src/app`, developed at
`admin.php?page=ohmylms-app`. Its foundation, feature registry, REST client and tests are present.
The preview includes Skills, Webhooks, Students, Taxonomies, Certificates, Emails, Integrations,
Commerce, Memberships, Settings, Dashboard/analytics, Curriculum hierarchy, Course structure
and Syllabus authoring screens. These are bounded feature batches, not full product parity. The current
batch/status sections below are authoritative; the recovered-runtime sections are reference.
See [architecture](ARCHITECTURE.md) and [course authoring](api/course-authoring.md).

The stored-data compatibility requirement below has been superseded by the user's rebuild policy:
stored data, REST shapes, option names and tables may be redesigned with PHP and TypeScript changed
together. Do not add migrations or compatibility wrappers solely to preserve old stored data.
Preserve the public hooks and extension points in [EXTENSIONS.md](EXTENSIONS.md). Fix recovered
quirks rather than reproduce them. Ask before destructive actions outside the repository, including
dropping or resetting the Local database.

Every rebuilt feature needs server permission checks, validation, typed API boundaries, tests and
accessible controls. The old admin must not be replaced or its runtime deleted while features and
documented extension surfaces are still missing. Stylesheet cleanup is outside this rebuild;
reuse the existing tokens and shared components.

The LMS counts as fully modular when each feature owns its UI, state, API access, styles and
tests, with explicit imports and documented interfaces. The largest remaining job is removing
dependence on the recovered application runtime. Feature folders exist, but their components
are still created with `createComponent(readRuntime)` and receive stores, controls, helpers
and other components from recovered factory 1841.

Current numbers live in the generated [runtime dependency inventory](RUNTIME-DEPENDENCY-INVENTORY.md).
To regenerate it:

```bash
node tools/runtime-inventory-report.mjs --write
```

`tests/js/runtime-inventory.test.mjs` runs as part of `npm test`. It fails when a binding has no
owner, when a component reads a binding its `components.json` does not declare, or when the
report is stale.

## Invariants for every step

- Stored-data preservation is not a release requirement. Coordinate changes across PHP, TypeScript,
  validation and tests; do not silently drop or reset the Local database.
- Before a feature's old implementation is retired, it must pass a real WordPress save →
  reopen check and a permission check (allowed and denied user).
- Preserve the documented hooks and extension points. REST shapes and internal persistence may
  change as part of the coordinated rebuild. Retain assessment security and correct server grading.
- Modularization and performance need separate evidence. A migration claims no speed-up
  without a benchmark.

## Steps

| # | Work | Done when | Status |
| --- | --- | --- | --- |
| 1 | Dependency inventory | Every recovered library and LMS-owned module has an owner and plan | **Done**: generated report, zero unassigned bindings, drift test |
| 2 | Easy Email and remaining libraries | Email UI, editor core, source editor, html2canvas and embedded vendor copies use maintained packages or documented replacements; saved templates still open | Partly done (MJML, Arco shared factory, Chart.js, Emotion, Tiptap, Lowlight) |
| 3 | Shared application infrastructure | Routing, controls, notifications, permissions, configuration and utilities have named modules and public exports | Typed app bootstrap/router/API/layout are present. Shared controls include npm-backed advanced/search selects. Cancellable request and unsaved-change hooks are shared by the new authoring batch. Legacy consumers still need extraction |
| 4 | State and API access | Features import their stores, hooks and API clients directly | Preview feature batches own typed APIs and page state; the new authoring batch reuses existing transactional services. Legacy store/client dependencies remain |
| 5 | Feature independence | Features use normal components and explicit imports, not `createComponent(readRuntime)` | 15 preview feature folders, including Home, with 22 registered route entries. Curriculum/course and syllabus authoring batches added. Full workflow and extension parity remain |
| 6 | Authored entry point | A small bootstrap registers routes, initializes services and loads features. Production startup needs no numeric Webpack IDs or adapters | New preview bootstrap exists beside the old admin; production cutover and runtime deletion remain pending |
| 7 | Stylesheet ownership | Tokens and controls are centralized; feature styles load with their features; recovered CSS snapshots are retired | Separate CSS work completed; excluded from this app rebuild. See [stylesheet ownership](STYLESHEET-OWNERSHIP.md) |
| 8 | Backend boundaries | PHP modules separate REST controllers, business rules and persistence; cross-feature access goes through services | Partial: Analytics repository/date/cache, Settings registry/service, Setup services, Gamification registry/store, quiz policy/settings, and course catalogue/structure services exist. Repository-wide separation remains |
| 9 | Asset and dependency isolation | Each page type loads only its modules; one deliberate React; no accidental duplicate runtimes | Partly done (lazy email/editor chunks) |
| 10 | Enforcement and release checks | Automated checks catch circular imports, forbidden cross-feature imports, compatibility regressions, security findings and build drift | Inventory drift check added |

Sequence: remaining email dependencies → shared infrastructure → state/API modules →
independent features → authored bootstrap → retire recovery adapters. Backend, styles,
security and testing progress alongside.

## Recovered-runtime inventory (reference)

The sections below describe the earlier extraction approach. Use them to locate dependencies;
they are not instructions to preserve recovered quirks or introduce stored-data compatibility
wrappers. The active sequence is: verify the typed foundation and Webhooks API, rebuild feature
batches, verify the documented extension surfaces, switch the admin, then retire the recovered
runtime and finish backend boundaries, page-specific loading and CI enforcement.

The core Skills catalogue batch is implemented in the preview, with bounded search, editing,
hierarchy/prerequisites, archive/restore and lesson/course links. Next, rebuild the advanced Skills
and Curriculum workflows, then the remaining Content Hub editors. Skills stays core without an
Add-ons switch. Do not remove existing routes until replacements and extension contexts are verified.

### Step 2: libraries (owner: `features/vendor-packages`)

- **Easy Email** (see [the audit](EASY-EMAIL-AUDIT.md): a locally patched copy of an unidentified release; swap core first, behind a golden-template corpus)
  (about 2.2 MB across chunk 355 and the shared bundle) is the largest remaining
  family. It also embeds private copies of MJML, Arco, CodeMirror, html2canvas and
  `@wordpress/icons`. Move the whole package rather than individual internals. Removing pieces
  one at a time risks the editor's form and selection contracts. Before switching, capture
  every saved template's JSON and MJML, then compare it after reopen and re-save.
- **Moment** (138 factories) and **Lodash** (33): features read them directly as `aN` and `M`.
  Replace each with `@wordpress/date` or Day.js, or with native code, one feature at a time.
  Check every date format string, including the localized strings.
- **React Router** (`f`, `v`, read by 12 features): becomes a pinned npm dependency owned by
  the step 6 bootstrap. Features receive navigation through a shared routing module, not
  through the router directly.
- **Bundled copies of `@wordpress/icons` and the JSX runtime** go back to WordPress externals.
- **231 unidentified vendor factories** (about 530 KB) must be assigned to packages before
  step 9 can claim no duplicate runtimes.

### Step 3: shared infrastructure (owner: `assets/src/application/shared`, public exports)

The most-read shared bindings, in suggested extraction order:

| Module to create | Recovered source | Read by |
| --- | --- | --- |
| `controls` | factory 38093 (`I`, `*WP` wrappers) | 18 features |
| `notices` | factory 71046 (`z`) plus the toast library | 13 |
| `admin-menu` / routing helpers | `HG` (admin menu highlight), router bindings | 14 |
| `html` utilities | `Ge` (safe text from HTML) | 12 |
| `table`, `skeleton`, `tooltip`, `switcher`, `avatar` | factories 65, 68119, 6425, 25946, 53725 | 2–10 |
| `media` controls | `df`, `uf`, `nf` (course-thumbnail-controls fragment) | 4–13 |
| `config` | `ohmylms_params` readers | several |

`assets/src/ui` already holds authored controls. Prefer extending it over adding a second kit.

### Step 4: state and API (owner: each feature, with a thin shared REST client)

Factory 86169 (`T`) is one store-plus-client read by 123 components in 17 of the 18 wired features. Split it by REST
namespace into per-feature `api.mjs` modules and stores. `courses/api.mjs` and
`courses/structureClient.js` already show the pattern. The plugin-installer client (`He`,
18 components) belongs in shared infrastructure. Each split needs the permission check from
the invariants: the client must not widen which capability can reach a route.

### Step 5: feature independence (owner: the named feature)

The report lists, for each feature, the recovered locals only that feature uses. They move
into the feature as authored modules. The 480 inlined Babel helper bindings need no work of
their own; they disappear as each component is rewritten as normal JSX.

Cross-feature reads (for example, settings reading `GamificationPage`, `EmailSettingsPage` and
`WebhooksPage`) must become documented public exports before step 10 can forbid the rest.

`assets/src/features/commerce-reference` is a 22-component copy of `commerce` that no loader
imports. Retire it once nothing else refers to it.

### Step 6: authored entry point (owner: bootstrap)

The recovered router mounts 31 routes. Twenty-seven already render authored feature components.
`/quiz-edit/:id` and `/students` still render recovered screens. `/license` and
`/free-vs-pro` point at bindings that are no longer in factory 1841 and need a decision:
retire them or reimplement them.

## Separate follow-ups

- Benchmark startup, navigation, editors, API requests and database queries before and after
  each step.
- Fix the local DNS delays found earlier on their own; they are unrelated to modularization.

## Rebuild status (clean rebuild, updated 30 October 2026)

All stored data is test data, so the rebuild changes REST shapes, options and storage freely and fixes
recovered defects instead of reproducing them. The new TypeScript app lives in `assets/src/app` (see
[ARCHITECTURE](ARCHITECTURE.md)) and opens at `admin.php?page=ohmylms-app` beside the recovered admin.
Every rebuilt feature ships a hardened `WP_REST_Controller` API, `docs/api/<feature>.md`, a PHP integration
test in `tests/php/` and component tests.

| Feature | State | Notes |
| --- | --- | --- |
| Webhooks, Integrations, Emails, Certificates (templates) | Done | Issued certificates and learner download not built; Google Meet, xAPI and MCP settings not built |
| Students, Categories and tags (read-only) | Done | Student reminder route (open mail relay) must be rebuilt before any UI |
| Orders, Coupons | Done | Refund UI deliberately absent; gateway list route missing |
| Memberships | Done | Old membership routes removed |
| Settings (General, Branding, Account, Permalinks, Advanced, Currency, Tax) | Done | Payment gateway secrets still returned by the old route |
| Dashboard, Earnings, Course report | Done | Mixed-currency totals; amounts still in postmeta |
| Content Hub (skills and curriculum) | Partial | Skills catalogue, curriculum hierarchy/placement, syllabus skill groups/order/library assignment, CSV review/apply, draft-course sync and individual mapping/comparison. Rich objectives/translations, resources, combined mappings and full workspace parity remain |
| Courses | Partial | The `/courses` screens provide hardened metadata/media editing; `/content-hub/courses` retains units, lesson placements, settings, mastery and reviewed program publication. Consolidating overlapping controls and extension parity remain |
| Learner frontend TypeScript | Planned | Migrate learner-facing JavaScript after the authoring workflows; admin TypeScript completion does not complete the frontend migration |
| Lessons | Partial | Typed library/draft creation, WordPress visual blocks/source, resources, explicit publication and course-unit editing links. Advanced media, inline question and extension controls remain native |
| Setup wizard and Gamification | Typed admin screens available | Upstream registry-backed screens/APIs are merged; see their feature contracts |
| Quizzes, attempts and grading | Partial | Upstream hardened APIs and typed screens are merged; full question/content editor parity remains |
| Communities, Assignments and question editors | Preview UI pending | Backend groundwork exists; no full-screen parity claim |
| Cutover (menu points at the new app, recovered runtime deleted) | Not started | Old PHP tests for removed routes go with it |
| PHP boundaries, page-by-page asset loading, CI enforcement checks | Partial | Named backend services and lazy preview chunks exist; complete boundaries, isolation and enforcement remain |

Stylesheet cleanup is intentionally skipped.

## Completed batch: Curriculum hierarchy and course outlines (30 October 2026)

- Added `#/content-hub/curriculum`, `#/content-hub/courses` and
  `#/content-hub/courses/:id` to the typed preview. Existing routes are retained.
- Curriculum fields and parent moves use the established validated services. The course editor
  exposes its curriculum placement through Curriculum's public component, with immediate saves.
- Course discovery is bounded to 20 summaries per page; creation accepts only a title and always
  creates a draft owned by the caller. UI, API transport and model concerns are separate modules.
- Outline editing adds/renames/reorders units and attaches/reorders/detaches reusable block lessons.
  It carries the full program through the existing revision lock and transaction. Stale writes
  retain local edits; draft saves do not publish. Retired lesson folders are not reintroduced.
- No recovered runtime was removed and no main-menu cutover was made. CSS ownership is unchanged.
- Verification: the full app suite passed 627 tests; 22 focused Node checks, TypeScript, source
  contracts and targeted JavaScript/PHP standards checks passed. WordPress REST integration passed
  33 checks on `800.local`, including allowed/denied users, save/reopen and stale-write rejection.
  The SDK compiles with its existing three performance warnings.
- After the final refinements, all 37 focused authoring/shared-guard tests passed again. All 34
  published app files were checked against the final SDK output; obsolete generated app chunks
  were removed. Targeted standards checks are clean; this does not erase repository-wide legacy
  formatting/PHP debt or the previously recorded stale runtime-inventory report.
- Live browser verification created and reopened `Modularization verification 1030` and draft
  course `Modularization verification course 1030` (ID 938). Unit title/number/description,
  curriculum placement and an existing reusable lesson persisted across a full reload, with no
  console errors. These browser examples remain for review; scripted fixtures were cleaned up.
- Follow-up authoring batches: syllabus/skill workflows and course metadata, followed by
  lesson/assignment and assessment editors. See [the API/scope contract](api/course-authoring.md)
  for exact exclusions and verification instructions.

## Completed batch: Syllabus authoring (30 October 2026)

- Checkpointed the previous curriculum/course batch as `6e089892` on
  `codex/syllabus-authoring` before starting this work.
- Added the lazy typed syllabus editor at `#/content-hub/curriculum/syllabus/:id`, linked from
  Curriculum rows. Group fields/order/moves, new/library skill assignment, skill fields/order/
  moves and confirmed placement detachment use the existing domain services.
- Added an administrator-only validated command boundary. Unexpected fields, invalid types,
  oversized payloads and cross-syllabus moves are rejected before domain writes.
- CSV reuses the established pure parser with a TypeScript declaration. Column mapping, UTF-8,
  limits, server review, planned counts and explicit apply are implemented. Source/mapping
  changes invalidate the review; applying a changed syllabus/row set requires a fresh review.
- Explicit course creation/sync keeps the same associated course and updates only the draft.
  A synchronization warning does not hide a successful syllabus write.
- Individual mapping edits/removal use read revisions and explicit confirmation. Pairwise
  comparison shows shared coverage and separate unmapped counts; combined mappings are read-only.
- Full app suite: 653 tests passed; the final 28 focused syllabus tests passed after adding
  BOM/record-limit cases. Focused Node suites: 31 passed. WordPress REST integration:
  47 checks passed on `800.local`, with tagged fixtures removed. TypeScript, source contracts
  and targeted JavaScript/PHP standards checks pass. Existing repository-wide debt remains.
- Live verification: syllabus `Syllabus browser verification 1030` (ID 82), its draft course
  (ID 958) and shared verification skill (ID 335) remain for review. Group creation, new skill
  creation, CSV preview/apply, skill reordering, explicit course sync and a confirmed mapping
  persisted across a full reload. Pairwise comparison with Digital SAT Math rendered mapped
  coverage and separate unmapped counts. No existing syllabus was edited. The browser console
  also recorded generic message-channel listener errors without a source stack; their origin
  was not diagnosed in this batch, and the checked workflows completed successfully.
- The SDK builds with the existing three performance warnings. All 36 shipped app files
  match the final build; all 15 runtime chunk references resolve to shipped files. Obsolete
  generated app chunks/maps were removed.
- Remaining: course metadata/settings, rich skill objectives/translations and resources,
  mind-map/spreadsheet parity, combined mapping editing, lesson/assignment and assessment editors,
  publication and extension contexts, then production cutover/runtime retirement. CSS ownership
  remains unchanged. See [the syllabus API and scope](api/syllabus-authoring.md).

## Completed batch: Course settings and publication (30 October 2026)

- Started from local main `6d86825a` on `codex/course-settings`.
- Added a lazy typed settings screen for title/description, author/image IDs, status,
  free/paid pricing, public/password access, and enrollment capacity. Settings apply
  immediately when saved; structure remains a separate draft.
- Added an administrator-only settings boundary with per-course capabilities,
  strict field/type validation, shared locking, transactions, and settings/program
  revision checks. Invalid/stale saves preserve the existing saved course.
- Added learning mode, prior mastery, evidence age and shared skill target controls
  to the draft outline. Required flags, target levels and unit placement are editable.
- Added saved-outline/readiness preview and explicit publication confirmation. Course
  status and program version publish atomically; existing learner versions remain pinned.
  Shared draft resources must be published separately.
- Full app suite: 661 passed before final response-validation refinements; the final
  focused authoring suite passed 31 checks. WordPress REST integration: 61 checks passed on
  `800.local`, including forced publication rollback; scripted fixtures were removed.
- TypeScript, source contracts and targeted JavaScript/PHP standards pass. SDK builds
  retain the existing three performance warnings. All 38 shipped app files match the
  final build; all 16 runtime chunk references resolve. Obsolete app hashes were removed.
- Live browser verification created course `Course settings browser verification 1030`
  (ID 983). Description with a mathematical backslash, paid/sale pricing, enrollment
  capacity, blended mode, evidence age and a required shared skill target survived
  full reloads. Preview correctly blocks publication for insufficient approved skill
  questions/families. This draft remains for review; no existing course was changed.
- Browser diagnostics again recorded generic asynchronous message-channel listener
  errors without a source stack; the checked workflows completed, and their origin
  remains undiagnosed. Existing repository-wide legacy lint/runtime-inventory debt remains.
- See [the settings API and scope](api/course-settings.md) for remaining advanced
  controls. Learner frontend TypeScript migration and old-menu cutover remain future work.

## Completed batch: Lesson content authoring (30 October 2026)

- Started `codex/lesson-authoring` from course-settings commit `9e0de26d`; this branch
  includes that completed batch. Both batches are included in the integration below.
- Added lazy typed lesson library/editor routes and course-unit links. Owned drafts
  are created with retry identifiers; existing lessons remain reusable shared posts.
- The installed WordPress block editor supplies visual blocks, the inspector,
  parsing/serialization and unsupported-block preservation. Typed controls add source
  editing, format, external media, preview/comments and download resource links.
- Added an administrator-only API over the native secured REST writer, with strict
  payload validation, shared native/typed locks, revision checks and transactional
  rollback. Saving does not change publication status; publishing requires a reviewed
  saved revision, explicit confirmation and the registered publishing capability.
- Existing drip/media/prerequisite settings and resource extension fields survive.
  Published lesson edits apply immediately everywhere the lesson is used.
- Verification: all 672 app tests passed; the final 39 focused lesson/course checks
  passed after compatibility refinements. All 49 Local WordPress REST checks passed.
  TypeScript and targeted JS/PHP/CSS standards pass. SDK builds retain the existing
  three performance warnings; repository-wide legacy lint/inventory debt remains.
- Live verification: lesson `Typed lesson browser verification 1030` (ID 1003) was
  created in the visual editor, saved/reopened with mathematical backslashes and a
  worksheet link, explicitly published and attached to `Typed lesson verification
  unit` in the earlier disposable course 983. Placement survived a full reload and
  its new edit link opened the same shared lesson. An opaque unsupported block also
  survived a visual paragraph edit; that temporary probe was removed before saving.
- Async feature stylesheets now use content hashes, like feature scripts, so updates
  invalidate browser caches. Lesson block packages remain WordPress script externals.
- All 42 shipped app assets match the final SDK build; the runtime resolves all 17
  script chunks and the hashed lesson stylesheet. Obsolete generated app hashes were removed.
- Browser diagnostics included Grammarly extension errors and one AbortError whose
  stack points to the existing shared request hook's navigation cleanup. Checked saves,
  publication and reopen flows completed; no console-error-free claim is made.
- Remaining: advanced lesson media/inline assessments/extensions, assignments, quizzes,
  advanced course settings, learner frontend TypeScript migration, then old-menu
  cutover/runtime retirement. See [the lesson contract and limitations](api/lesson-authoring.md).

## Integration checkpoint: main/origin (30 October 2026)

- Combined course-settings `9e0de26d` and lesson-authoring `41603df8` with upstream
  main `a4a2e6d5`, retaining the newer course, quiz, setup and gamification work.
- Resolved the feature registry by retaining both course route families, lesson and
  course-settings routes, and every upstream feature. Generated assets were rebuilt
  from the combined source, including the source map deleted upstream.
- The setup heading-focus test now waits for the existing focus effect; its focus
  requirement is unchanged. All 995 app tests and 574 PHP integration checks pass
  (course settings, lessons, course authoring, courses and quizzes).
- TypeScript, source contracts and targeted registry/setup-test lint pass. The SDK
  retains its three existing performance warnings. All 50 shipped app assets match
  the build; 21 script chunks and the hashed lesson stylesheet resolve correctly.
