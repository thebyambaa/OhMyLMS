# Acceptance status — 2026-09-24

Status (checked October 19, 2026): this file is the acceptance log of the source-recovery and extension work,
written increment by increment between September 24 and October 6, 2026; counts and "not verified" lists are
the figures of those dates. It does not cover the learner hub, schools and families, the Khan-style practice and
assessments, xAPI or the MCP tools; their verification records are in
[../CONTINUE-LEARNER-HUB.md](../CONTINUE-LEARNER-HUB.md) and
[learner-experience/RELEASE-READINESS.md](learner-experience/RELEASE-READINESS.md). Treat the "Remaining release
gates" at the end as the open list for the source build itself.

## Frontend Interactivity migration — 2026-10-02

Default quiz navigation/validation/timers, matching/reorder, SmartScore practice, reward notifications,
course tabs, layout-2 chapter expansion and common disclosures now use frontend stores. Public module,
validator, mount, event and slot contracts are documented in INTERACTIVITY.md. The custom-question
add-on uses the question registry and no longer replaces the quiz template or regrades after submission.

Eleven isolated browser scenarios pass using the actual PHP templates and installed WordPress runtime
with mocked data/REST. This is not full authenticated database/theme acceptance. Grading and payment
schemas are unchanged. The repository's aggregate check still has existing formatting failures in
LessonEditor.jsx and schools/AddModal.jsx, and a recovered admin AST parity mismatch; these files
were not changed by the frontend migration.

**Not released to the math site.** This is a tested source-recovery/extension foundation, not completion of every approved release gate.

## Evidence

- Code/add-on snapshots and database backup outside Git; 281 baseline asset hashes.
- Separate local WordPress DB, disposable fixtures, blocked HTTP/email.
- 831 recovered factories, 238 application fragments, 99 rebuilt assets with source maps.
- Separate Git review repository with baseline commit `1a6386ae223b47b47584318a3330083cdadf2c89`; implementation staged for comparison without touching the shared workspace index.
- Fresh source-only workspace installed locked dependencies offline and successfully built without old compiled assets.
- All 142 current generated artifacts match that separate source-only build byte for byte.
- Initial recovered dashboard screenshot matched original pixels.
- Eight browser scenarios pass together: asset inventory/errors, custom page, rendering isolation, membership create/reopen/settings, custom editor store updates, 11 admin routes, mobile/RTL screenshots, custom and built-in lesson saving/reopening, and the student course slot.
- 60 isolated PHP checks: content create/reopen, settings permissions/schema, custom question rendering/grading, enrollment denial, timers, duplicate submissions, progress, totals/coupons, checkout metadata and mocked QPay success/failure/partial/repeated callbacks.
- Registry/form/AST/source-map tests, built-in grading tests and two-build hash comparison. Use DEVELOPMENT.md commands to verify current checkout.

Checkout fixes: extension metadata now persists after the order has an ID; both QPay confirmation paths require the verified payment to cover the order total. No real payment requests were sent.

The original lesson editor also reproduced a ProseMirror duplicate-plugin exception. The source build now adapts the duplicate state-module exports to share one instance. The original assets remain unchanged for baseline comparison.

The student template rendered block-theme headers after `wp_head`, too late for WordPress's module import map. Header/footer blocks are now prepared before `wp_head`; the student page passes without the unresolved interactivity-module error. The closing body tag is also corrected.

## Quiz-first increment

Seventeen named React quiz/question components now build from `assets/src/features/quizzes/`. The quiz editor, loading/saving hook, API client and shared choice-options editor have authored source. Other extracted JSX components retain compatibility dependencies. Custom question changes now persist in the parent quiz, fixing a browser-detected store synchronization bug. See QUIZ-SOURCE.md for exact scope and remaining work.

The complete nine-scenario browser suite passed after integration. The expanded quiz scenario then passed after the choice-options rewrite: all nine built-in editors render, choice answers and custom numeric settings survive saving/reopening, and option add/remove/minimum behavior works. Ten JavaScript tests in total have passed (the original nine plus the new immutable reorder test); the 60 PHP integration checks remain passing. This is not exhaustive interaction coverage of every question type.

## Course increment

Twenty-five named course React components now build from `assets/src/features/courses/`, including the course list, creation dialog, editor/curriculum, settings/pricing tabs, community/funnel views and publishing preview. Authored hooks and API/model modules now handle course loading and saving. Chapter writes are awaited, partial saves are reported, creation failures permit retry, and late save responses preserve newer edits. See COURSE-SOURCE.md for the distinction between authored modules and reconstructed components that retain shared runtime dependencies.

The new browser scenarios cover course creation/failure/retry, adding a second chapter, editing title/description/chapter name, saving/reopening with lesson relationships, pricing/settings navigation, publishing, and simulated chapter-write failure/retry. Four new JavaScript tests pass, bringing the full JavaScript suite to 14. The 60 isolated PHP checks remain passing. This is not full course-system acceptance or a math-site release.

## Certificate increment

Thirteen named React components now build from `assets/src/features/certificates/`. They cover the certificate list, row actions, filtering, bulk deletion, template selection, editor route, header and persistence controls, design fields, preview, image fields and course assignment. The checked certificate adapter requires all 13 factory bindings during production builds. The existing WordPress store, REST endpoints, shared controls, HTML-to-image/PDF helpers, templates, permissions and Pro gating remain in place.

Focused source and adapter tests pass, all source parses, the production build integrates all 13 replacements, and 142 generated artifacts are byte-identical across two builds. Existing route smoke coverage includes `/certificates`; complete certificate creation/editing/publishing/PDF browser acceptance remains a release gate.

## Email settings and templates increment

Twelve named React components now build from `assets/src/features/emails/`. They cover administrator/student notification lists, enable toggles, branding and sender personalization, the individual template editor, editable subject/body/footer fields, responsive controls and template-specific HTML previews. The checked email adapter requires all 12 factory bindings during production builds. Existing WordPress store and REST behavior remains unchanged.

Focused source and adapter tests cover the conversion boundary. Existing smoke coverage reaches email settings through the Settings route; complete save/reload, delivery, responsive email-client rendering and permission acceptance remain release gates.

## General Settings increment

Nineteen named React components now build from `assets/src/features/settings/`. They cover the Settings route and its general, branding, account/privacy, permalink, advanced, design, payment, currency, tax and migration panels. Email Settings is deliberately excluded and remains in `assets/src/features/emails/`; the existing Gamification and Webhooks modules remain separate. The checked settings adapter requires all 19 factory bindings during production builds while retaining existing stores, REST behavior, shared controls, permissions and Pro gating.

Focused source and adapter tests cover the conversion boundary. Existing route smoke coverage reaches `/settings`; complete save/reload, gateway configuration, migration, responsive and permissions acceptance remain release gates.

## Integrations / Add-ons increment

Seven named React components now build from `assets/src/features/integrations/`. They cover the Add-ons route, integration cards, category filtering and search, enable toggles, the configuration shell, and the Zoom, AI Model, Google Meet and Google Sign-In settings panels. The checked integration adapter requires all seven factory bindings during production builds while retaining the existing WordPress store, REST endpoints, shared controls, entitlement checks and server-provided integration manifest.

Focused source and adapter tests cover the conversion boundary. Existing route smoke coverage reaches `/integrations`; complete provider credential save/reload, OAuth redirects, dependency handling and permissions acceptance remain release gates.

## Webhooks increment

Four named React components now build from `assets/src/features/webhooks/`. They cover the webhook list, search and status filtering, selection and bulk actions, pagination, deletion, the details form, event-aware payload field mapping, and the two-step create/edit modal. The checked webhook adapter requires all four factory bindings during production builds while retaining the existing WordPress store, REST endpoints, shared controls, entitlement checks and Webhooks integration toggle.

Focused source and adapter tests cover the conversion boundary. Existing route smoke coverage reaches `/webhooks`; complete create/edit/delete persistence, outgoing delivery, retry/error handling, responsive and permissions acceptance remain release gates.

## Categories and Tags increment

(Later replaced: categories and tags were superseded by the curriculum and Course Bundles, and the routes now open those pages; the converted components stay in the tree only for the parity build. See `assets/src/features/taxonomies/README.md`.)

Three named React components now build from `assets/src/features/taxonomies/`: the Categories route, Tags route and their shared create/edit modal. They cover loading, search, selection, course counts, category hierarchy, single and bulk deletion, and empty states. The checked taxonomy adapter replaces both anonymous route functions and the shared modal while retaining the existing REST endpoints, shared controls and category store synchronization.

Focused source and adapter tests cover the conversion boundary. Complete create/edit/delete persistence, hierarchy edge cases, responsive and permissions acceptance remain release gates.

## Setup Wizard increment

Eleven named React components now build from `assets/src/features/setup/`. They cover welcome, experience selection, preferences, niche, LMS migration, SCORM import, course-import routing, completion, the wizard controller, wrapper and route shell. The checked setup adapter requires every factory binding while retaining the existing stores, REST requests, shared controls, migration endpoints and router behavior.

Focused source and adapter tests cover the conversion boundary. Complete fresh-install onboarding, each migration provider, SCORM upload, skip/resume behavior, responsive and failure-recovery browser acceptance remain release gates.

## AI Course Outline Generator increment

**Removed.** This increment converted eighteen components in a former `ai-course-outline` feature folder. The AI Course Outline Generator was later removed from the plugin (commit `876b6cf`), so the directory, its adapter and its route no longer exist. AI assistance now comes from the [AI tutor](AI-TUTOR.md) and the [MCP tools](MCP.md); the administrator chat assistant was dropped with the legacy admin (31 October 2026).

## Question bank, versioned assessment and skills increment (2026-10-02)

Implemented the repository-based implementation plan v2.0 (tickets A01–A12) on branch `assessment-engine` (since merged into `main`); see [ASSESSMENT-ENGINE.md](ASSESSMENT-ENGINE.md). Object-level authorization and atomic saves for questions/quizzes; immutable question versions, quiz revisions and frozen attempt items with opaque option tokens; shared question banks with approval and pins; skill taxonomy with part-level mapping; autosave/resume, UTC deadlines with finalization, decimal scoring and an exam preset; a durable grade-event → evidence pipeline with transparent mastery rules; skill practice, inline lesson checks and guest claims; numerical and structured question types, random pools, a non-recording preview and a migration rehearsal tool.

Verification on the isolated site: 266 assessment integration checks, 49 assessment unit checks, all pre-existing PHP suites passing, JavaScript 91/92 (the remaining failure is the pre-existing shipped-bundle parity check), and browser checks of the authoring, learner, grading, practice, guest-claim and report flows. Pre-existing defects fixed along the way: the renamed completion hooks recursing into each other, `Review::save()` calling a missing method, question titles/descriptions losing LaTeX backslashes, matching/reorder markup revealing answers, and the question list endpoint querying quizzes.

Not done: classroom pilot, math-site enablement, translations of new strings, and the items listed under "Limitations" in ASSESSMENT-ENGINE.md.

## Content Hub increment (2026-10-06)

Courses and Skills are combined into the **Content Hub** (Catalog, Courses, Lessons, Skills tabs; see [CONTENT-HUB.md](CONTENT-HUB.md)), and Skills became a core feature. The Courses and Skills submenus are removed; their old addresses open the hub tabs. A grade, exam or subject is a course whose learning-program draft is arranged as chapters, skills and scoped attachments, published explicitly. Lessons form a reusable library, and quizzes and assignments can be placed in several courses with shared attempts.

Verified in a session with no WordPress or database: `npm run lint`, the JavaScript suite (including new hub routing, catalog model, autosave and component-render tests), `php tests/php/learning-model-unit.php`, `php tests/php/addons-unit.php` in all four modes, PHP syntax checks of every changed file, and a production build.

**Not verified:** the REST controllers and the SQL in `Catalog`/`Placements` against a database, the shared-attempt and course-resolution changes in real quiz delivery, upgrade behaviour on a site that had the Skills switch off, and every browser flow (the updated menu and design specs were edited but not run). The existing PHP integration and Playwright suites must be run on the isolated site before release.

## Syllabus increment (2026-10-06)

Any curriculum item can be a **syllabus** with skill groups and skills of its own, edited in the Curriculum tab or imported from CSV (column mapping, dry-run review, repeatable import that never deletes; see [SYLLABUS.md](SYLLABUS.md)). The design follows two reference documents, a Cambridge IGCSE syllabus (topic, coded section, objectives) and a Mongolian grade 11 skill list (chapter, skill group, atomic skill). Skills are real library skills under one root skill per syllabus; names may repeat, codes are unique per syllabus. Curriculum schema moves to version 2 (two columns, two tables) on the next page load. The skill API and the curriculum skill pickers now return skill names as plain text rather than as the HTML entities WordPress stores, so objectives containing `<` and `>` read correctly.

Verified on the isolated site with a production build: the JavaScript suite, `php tests/php/syllabus-unit.php`, `php tests/php/syllabus-integration.php` (flag and guard, group and skill editing, maths and Cyrillic text, long names that begin alike, dry run, apply, repeat, edit, refusal, rollback of a failed import, Bundles integration, deletion, permissions), the existing curriculum and placement integration suites, `tests/browser/syllabus.spec.cjs`, and a 259-skill, 83-group, 13-chapter import of a file made from the Mongolian reference through the real dialog (3.7 s on the server).

**Not verified:** the live site has not been opened (its curriculum tables upgrade on its next page load); PDF files are not read by the app (they are converted to CSV first); browsers other than Chromium; a screen reader pass over the import dialog (focus moves into the dialog on every step, and the keyboard flow is covered by the browser spec).

## Remaining release gates

- The standalone License and Free vs Pro admin modules are intentionally retired. Adapted builds remove both routes and their component implementations; the recovered snapshot retains them only as immutable provenance for parity verification.
- Convert remaining reconstructed shared scopes into independent named modules. Editable fragments are not the final maintainability goal.
- Verify original JavaScript dependency versions and restore missing licence notices from verified provenance. Original package manifest/source map were absent.
- Complete original-vs-rebuilt visual/end-to-end comparisons for every authoring, student, restriction and checkout path, including every built-in question editor. API/grading tests are not full browser coverage.
- Exercise QPay browser polling/concurrent callbacks and the existing custom-question add-on's full browser flow. Sequential duplicate callbacks are covered; loading add-ons is not full compatibility proof.
- Test actual WordPress RTL locale/assets and all responsive screens. Document direction screenshots alone are insufficient. Check every dynamic chunk/fallback URL.
- Final math-site smoke test with matched rollback before enabling rebuilt assets there.

The read-only `http://math.local` smoke request failed at the local connection layer, including an elevated retry. No HTTP response was obtained. The isolated site at `127.0.0.1:8099` remained available. Rebuilt assets have not been enabled on math.

The isolated PHP log records pre-existing Razorpay PHP 8.2 dynamic-property deprecations, distinct from browser console errors. Historical test failures remain in logs after fixes/reruns.
