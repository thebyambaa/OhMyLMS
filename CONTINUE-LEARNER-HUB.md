# Continue the OhMyLMS learner experience

Last updated: October 19, 2026, Asia/Ulaanbaatar (documentation cleanup; see the last section). The checkpoints dated October 16 are unchanged.

## Current state: start here (October 16, 2026)

This section is the authoritative summary. Everything below it is a chronological
ledger; where an older note says "next step is P1/P2/P3" or "no parent/child
implementation exists", this section wins.

Latest implementation and verification: [release readiness checkpoint](docs/learner-experience/RELEASE-READINESS.md).
The user has now requested repository-wide lint cleanup. This is **in progress**, not complete.
Work is in the live checkout; see `docs/CODING-STANDARDS.md` for the reviewed class-path,
public-selector and generated-stylesheet boundaries. The first cleanup test runs passed all
425 JS tests (one existing skip), all 236 component contracts and all 1,049 assessment checks.
438 changed PHP files passed syntax checks before the subsequent documentation-only pass.
The build passed with its two existing size warnings. Final lint totals and acceptance checks
must be rerun after the remaining fixes; the older debt totals below are historical snapshots.
The requested five-part pass added scoped teacher/parent reporting, repaired the targeted checks,
verified the disposable site, refreshed/compiled Mongolian catalogs and built reproducible assets.
Full repository lint, translation completion and manual acceptance still have the explicit gates
listed there. Earlier "PHPCS unavailable" and credential-blocked notes are historical.

### Product decisions (from the user)

- Closely adapt Khan Academy's UI with OhMyLMS branding and content.
- A **class is a student roster**, separate from courses.
- No Tutor Me, Teacher Tools, Exam content type or learner-facing roadmaps.
- **No front-end LMS portal.** Invitations and acceptance go through the hub
  workspaces; school administration is the hub **School workspace**, not wp-admin.
  Platform administrators may still use the wp-admin Account Hub.
- Self-registration is kept for learner, teacher and parent (hub sign-in chooser).
- A teacher can create **one school** (Khan Academy "add your school" adapted: no public
  school database, so the teacher creates it and becomes its administrator).

### What exists (all behind `[ohmylms_student_hub]`, `hub_role` selects the workspace)

| Workspace / feature | Entry | Main code |
| --- | --- | --- |
| Learner (library, My learning, classes, achievements, history, settings, membership) | default | `Student/Hub.php`, `templates/student-hub/*` |
| Teacher: independent classes, roster, invitations, archive/restore and period-specific class skill reports | `hub_role=teacher` | `Schools/ClassManagement.php`, `Student/TeacherActions.php`, `TeacherWorkspace.php`, `TeacherReports.php` |
| Parent: school-linked children (read-only work overview) and home-learning children (managed accounts, setup links, invitations, grade and followed collections, revocation) | `hub_role=parent` | `Student/ParentWorkspace.php`, `ParentActions.php`, `FamilyActions.php`, `includes/Family/*` (own schema `ohmylms_family_schema`) |
| Home-learning activity reports: explicit launch provenance, current-link scope, 7/30/90 days, activity/day/course totals and pagination | `hub_role=parent&hub_section=family&hub_scope=home_learning&hub_child=ID` | `Family/Provenance.php`, `Family/Reports.php`, `templates/student-hub/family-report.php`; contract in `docs/learner-experience/HOME-LEARNING-REPORTS.md` |
| Unit/practice presentation: Learn/Practice/Assessments, mastery legend, focused program lessons and course practice, collapsible outline and unit return | course overview, `?ohmylms_learning=COURSE&learning_item=UUID`, `?ohmylms_practice=SKILL&learning_course=COURSE` | `Student/PlayerContext.php`, `Practice/Presentation.php`, `templates/student-hub/activity-page.php`; `docs/learner-experience/P5-UNIT-PRACTICE.md` |
| School: overview, classes and years, people, parent links, invitations, roster CSV import, settings, member removal, managed-student recovery and previewed rollover | `hub_role=school` | `Student/SchoolWorkspace.php`, `SchoolActions.php`, `Schools/Lifecycle.php`, `templates/student-hub/school.php` |
| School reports and gradebook: completion/overdue counts, assigned-work drill-down, stored skill summaries, submission review/feedback, quiz/assignment matrix, weighted totals and CSV | `hub_role=school&hub_section=school-reports` or `school-gradebook` | `Student/SchoolReports.php`, `SchoolGradebook.php`, `SchoolActions.php`, `templates/student-hub/school-reports.php` |
| Sign-in chooser and sign-up (learner, teacher, parent) | signed out | `templates/student-hub/signin.php`, `ParentActions.php` |
| Invitation pages (school activation, family invitation, child setup) | `?ohmylms_invite`, `?ohmylms_family_invite`, `?ohmylms_child_setup` | `SchoolActivation.php`, `FamilyActions.php` |
| Legacy retirement and portal forwarding (no standalone frontend React app) | portal URLs and old blocks/shortcodes | `Student/LegacyPages.php`, `Schools/Views.php`, `ViewAs.php`; wp-admin Account Hub and school/class editors remain |

Security conventions used everywhere: nonce per operation, POST-only handlers, services
re-authorize on every call, request ids only select a candidate, tokens are 64-hex, stored as
SHA-256, single use, 48 hours, never audited or logged, invitation pages send no-store and
no-referrer headers, one-time results travel through short per-user transients.

### Verification record

- `npm test` (asset parity plus 418 JS tests): 417 pass, 0 fail, 1 skipped.
- PHPCS: 0 errors on all new files; remaining warnings are database/cache calls in the school and
  class services. Repository-wide PHP/JS/CSS standards debt is large and unchanged.
- Ad-hoc probes live in `test-results/` which is **git-ignored**: they are not in the repository.
  They covered P1 (17 checks), P2 (12), family P3a/P3c/P3d (36/19/14), legacy redirects (12),
  teacher management (47), school workspace (43), school activation over HTTP, and a four-process
  race on invitation acceptance. Promote the ones you want to keep into `tests/php/`.
- Live browser checks on http://800.local: teacher, parent, school and learner flows, invitations
  for guardian, teacher and school-admin roles, a non-admin teacher creating a school.
- Protected disposable-database suites passed without bypassing their guards: school reports
  (82 checks: 32 report, 31 lifecycle, 19 matrix), schools, teacher classes (47), student hub (88),
  legacy account blocks (44), and gradebook. The isolated site blocks outbound HTTP/email and uses the current plugin source.
- Family provenance/report/management suite: 56 protected checks, including migration, rollback,
  no-school operation, context replay, cross-child/guardian scope and revocation. The hub (88)
  and school reports (82) were rerun after the provenance hooks were added.
- Live browser verification passed for roster import, non-admin teacher invitation acceptance and
  departure, matrix/CSV download, recovery generation, rollover confirmation, mobile and dark theme.

### Working rules for the local site (800.local)

The assistant cannot type passwords on this host. For a real session it creates a disposable account,
writes credentials to a git-ignored file, and the user signs in in the browser pane. Fixtures and
accounts are removed afterwards and verified by count queries. The existing View-as feature can preview
disposable users without entering passwords; always return to admin before cleanup. Earlier checkpoints
added a parent role to the real admin; do not assume it is still present or change real roles for tests. A class named "0-0"
created by the user is real data and must be left alone.

### Git state

The checkpoints are local to `main`; use the latest Git log for the current commit ID. Nothing has been pushed.
Latest earlier commits: roster import, school settings
and member removal, school workspace and teacher-created schools, portal retirement, family and parent
work, teacher classes and legacy retirement, plus documentation commits.

### Open work, in suggested order

1. School reports, the submission gradebook, the scoped quiz/assignment matrix, totals and full
   class/course CSV export are implemented. Manual grade overrides remain in the platform Account
   Hub course gradebook and are deliberately separate from school assessment grades.
2. Managed-student recovery, leaving a school and previewed academic-year rollover are implemented.
3. Roster import and a non-admin teacher accepting an invitation and leaving the school passed
   live browser checks. See the latest checkpoint for the remaining verification details.
4. Dormant frontend portal screens and standalone renderers are removed. The wp-admin Account Hub
   and platform school/class editors are preserved; old links, blocks and shortcodes lead to the hub.
5. Home-learning activity reports and explicit origin recording are implemented. Global mastery,
   unknown historical activity and inline/guest evidence remain excluded. Managed-child recovery
   now requires replacement confirmation and rejects promoted staff accounts. Learning choices
   uses grade then collections, with Back/Cancel and a usable no-JavaScript form. These refinements
   use the existing hub style; the original reference layouts remain unobserved.
6. Protected integration verification is complete; retain the disposable-database guards for future runs.
7. P5, the Khan practice/level/assessment slices and Level up placement are implemented. Parent
   activity breakdowns and class-only teacher levels are now implemented too. Teacher reference
   comparison and the current release checks are recorded in `docs/learner-experience/RELEASE-READINESS.md`;
   its remaining manual, localization, lint and parity gates are the next work. A parent reference
   dashboard remains unobserved; do not claim exact parity for its proposed layouts.


## Parent and child UI extension (October 16, 2026)

The user requested parent/child features and explicitly requested UI replication.
The new specification is
[`docs/learner-experience/learner-hub-development.md`](docs/learner-experience/learner-hub-development.md).
It covers reference signup/profile layouts, parent and child workspaces, course
personalization, progress/connections, unit/practice presentation, authorization,
delivery slices, and visual acceptance. The parent dashboard itself was not
observed; its design is proposed. Existing school guardian links are implemented;
independent-family links and these new UI slices are planned, not completed.
This request updates documentation only. Preserve ongoing learner/teacher work.

Next step for this extension: P0 screen inventory and independent-family scope
contract, following the new specification. Earlier learner/teacher checkpoints
remain relevant to their existing slices.

Update: the P0 source/screen audit and proposed contract are recorded in
[`PARENT-CHILD-P0.md`](docs/learner-experience/PARENT-CHILD-P0.md). The latest
checkpoint below records runtime verification and actual legacy page retirement.
Next parent implementation slice is P1; unobserved reference screens remain open.

### Integrated delivery plan (October 16, 2026)

The parent/child specification is part of the active roadmap. Preserve the current
player, Membership, and teacher workspace changes and complete their verification
before extending the shared shell. In this product, **class means a student roster**;
courses, assignments, and reports remain separate destinations associated with it.

1. Finish the current learner/player and teacher workspace verification, including
   the unresolved legacy enrollment return flow. Keep an honest record of checks
   blocked by credentials or browser viewport support.
2. **P0:** inventory parent/child screens and supported services; document the
   independent-family relationship, approval, revocation, cache, migration, and API
   contracts before adding schema. Separate observed reference UI from proposed UI.
3. **P1:** add eligible Learner / Parent / Teacher context selection, parent
   signup/profile presentation, and reliable role-aware landing. Reuse authentication;
   selecting a context never grants permissions.
4. **P2:** build the parent workspace and child selector using existing approved
   school guardian links, with current relationship checks and read-only access.
5. **P3:** add independent-family relationships and private child setup/recovery.
   Support families without a school; never widen existing school guardian checks.
6. **P4:** add child grade/course personalization, course previews, profile, and
   Connections using actual data. Following courses does not enroll or purchase.
7. **P5:** refine unit/practice presentation on the existing player, preserving
   question extensions, assessment contracts, attempts, and scoring behavior.
8. **P6:** finish scoped parent reports and release verification: cross-child,
   cross-school/home, revoked-link, localization, mobile, migration, and rollback
   cases. Parent viewing must not mutate child learning records.

Parent assignments, messaging, and purchases for children remain deferred as stated
in the specification. Membership initially belongs to the signed-in adult. No
parent/child implementation or schema migration is claimed by this roadmap update.

### Legacy retirement is part of delivery (October 16, 2026)

The user authorized deleting old pages and cleaning up obsolete shortcodes along
the way. Apply this rule (formerly PLAN.md Slice K; PLAN.md was removed on October 19, 2026, and the outcome is in
`docs/learner-experience/LEGACY-RETIREMENT-PLAN.md`) to every completed replacement, rather than leaving
duplicate interfaces indefinitely. Inventory actual use, migrate content/menu/page
settings, establish redirects that survive page retirement, then trash redundant
generated pages and remove unused shortcode implementations/templates/assets.
Stop provisioning retired pages. Keep a small documented compatibility adapter only
where an identified consumer still needs it, and record the remaining dependency.
Do not delete custom mixed-content pages or learning records as interface cleanup.
The old enrollment page is still required until its replacement flow is verified.
No live pages or shortcode registrations were deleted in this planning update.

## Decisions agreed October 15, 2026 (implementation authorized)

The user approved building in this live plugin folder, using the PLAN section 12 defaults,
and including a Khan-style **teacher shell after the learner slices**. Defaults taken:

1. Landing: last visited destination; first visit goes to Classes if active class, else My learning.
2. Navigation: My learning, Classes, Browse subjects, Achievements, History, Membership;
   Settings lower. Skills live in course/unit context.
3. Following only pins; it never enrolls or purchases.
4. Labels: Collections (bundles). **Roadmaps are not learner-facing** (user decision, October 15, 2026): they are the shared-syllabus skill-mapping layer, hidden from Browse subjects and every learner list; Slice E is dropped.
5. Appearance: system/light/dark preference stored on the device. Legacy embedded views
   stay on a light surface until replaced, so the default is light.
6. Membership first slice: overview, plans, purchases, invoices, links to existing flows.
7. Profile first slice: display name, avatar, appearance, notifications, connections.

**Deviation from the earlier recommendation:** the learner app stays **server-rendered with
progressive enhancement**, not a new React entry. Reasons: the existing hub is server-rendered,
works without JavaScript, supports bookmarks, and a new React entry needs build manifests,
contracts and routing work that does not help the visual goal. Revisit only if a screen
needs client-side state. **Explicitly skipped by the user:** the learner app's **Tutor Me** and the teacher app's **Teacher Tools** menu. Do not build or stub them. Teacher scope is learner-first; MAPPING.md D1-D6 were open at the time and are all resolved now (see MAPPING.md section 5).

## Previous instruction: planning first (superseded by the decisions above)

The user wants a comprehensive, resumable plan before further application changes.
The next Khan Academy-style rebuild is **not implemented**. Continue the planning
discussion first. Earlier authorization to build the initial hub is not evidence
that a newly proposed data migration has been approved.

This file is a shared checkpoint for the user, Codex, Claude Code, or another
coding assistant. It records local work; it does not dispatch work to another
assistant or schedule an automatic continuation.

## Read these files in order

1. `AGENTS.md`: repository development and validation requirements.
2. "Current state: start here" at the top of this file, then the latest checkpoints at the end.
3. `docs/learner-experience/RESEARCH.md`: what was actually observed in the user's
   Khan Academy browser account, including the library-to-course-to-lesson flow.
4. `docs/learner-experience/MAPPING.md`: Khan vs OhMyLMS data model, gaps, rename rules.
5. `docs/STUDENT-HOME.md`: the hub shell, its settings, compatibility behavior, and validation.
6. `docs/learner-experience/KHAN-ACTIVITIES.md`, `learner-hub-development.md`, `PARENT-CHILD-P0.md`,
   `HOME-LEARNING-REPORTS.md`, `P5-UNIT-PRACTICE.md`, `LEGACY-RETIREMENT-PLAN.md` and
   `RELEASE-READINESS.md`: the current feature documents.

(The original item 2, `docs/learner-experience/PLAN.md`, was removed on October 19, 2026; see the
documentation cleanup section at the end.)

## Latest user direction

- Use Khan Academy's actual learner interface as the main reference.
- Replace the old dashboard/profile/course-list presentation; putting those old
  screens inside a new frame does not meet the intended design.
- Cover the learner hub, class interface, independent learning, library, skills,
  profile/settings, and a Membership menu entry.
- Reconsider how courses, roadmaps, and course bundles appear together.
- Preserve self-paced learning without requiring a class or teacher.
- Keep parent connections in the design. Teacher tools are explicitly excluded
  for now. A counselor authorization model is not defined or implemented.
- Produce the plan before writing the next application implementation.
- Keep small, explicit checkpoints so another assistant can resume safely.

## What is already implemented

The initial server-rendered hub is live on the local site:

- Learner page: `http://800.local/student-home/`.
- Layout editor: `http://800.local/wp-admin/admin.php?page=ohmylms-student-home`.
- Shortcode: `[ohmylms_student_hub]`.
- Main implementation: `includes/Student/Hub.php`.
- Views: `templates/student-hub/`.
- Authored assets: `assets/css/student-hub.css`, `assets/js/student-hub.js`.
- Integration checks: `tests/php/student-hub-integration.php`.

Its current sections are Home, My classes, My learning, My work, Progress, and
Account. It provisions one published page, redirects configured legacy account
entry points, saves administrator layout settings, and supports invitation
acceptance through the existing school service.

My classes displays the current learner's active school/class memberships,
assigned work, and existing teacher/guardian connections. My learning provides
independent course discovery. The user does not need to join a class to study.

**This is a functional first version, not the requested final visual design.**
It still renders legacy course, profile, catalog, and some achievement views.
Membership is currently an account subview, not its own primary menu item.

## Current repository state

All work is local and uncommitted. No branch, commit, push, or PR was created for
this learner-hub work. Read `git status` and the diff before editing; another
assistant may have added changes after this checkpoint.

Tracked files modified by the initial hub:

- `eslint.config.cjs`
- `includes/Assets/FrontendAssets.php`
- `includes/Shortcodes/Shortcodes.php`
- `includes/Utility/profile-functions.php`
- `package.json`
- `phpcs.xml.dist`

New application/documentation/test paths created by that work:

- `includes/Student/Hub.php`
- `templates/student-hub/`
- `assets/css/student-hub.css`
- `assets/js/student-hub.js`
- `docs/STUDENT-HOME.md`
- `tests/php/student-hub-integration.php`

This transition plan adds documentation only. No second-generation learner UI or
entity migration has been implemented during this planning phase.

## Last verified checks

- 50 disposable WordPress integration checks passed.
- Existing JavaScript suite: 414 passed, one skipped, zero failed.
- Production build succeeded, with two existing math-runtime size warnings.
- Source check passed: 1,603 JavaScript/JSX files; 236 contracts; 19 manifests.
- Targeted JavaScript ESLint, Prettier, and new CSS Stylelint passed.
- New production PHP: zero PHPCS errors, six direct-query/caching warnings.
- Test fixture PHP: zero PHPCS errors, 30 local-file/direct-query warnings.
- Full repository standards command still fails on existing unrelated PHP debt.
  Do not report the whole repository as standards-clean.
- Browser checked saved settings, card move controls, course tabs/discovery,
  invitation entry, full-width layout, and a 390px preview without overflow.

Tests used the disposable database `ohmylms_source_test`, never the user's live
learning records. Fixtures were cleaned. Provider credentials were not required.
The proof image is an ignored local artifact at `test-results/student-home.png`.

## Exact next step

Review PLAN sections 3–7 with the user: navigation, course/roadmap/bundle mapping,
the class/independent-learning distinction, and Membership. Resolve the small set
of open product decisions in section 12. Then update this checkpoint with the
agreed decisions. Do not start the shell rewrite while the user is asking to plan.

## Delivery ledger

| Slice | Status | Evidence / next action |
|---|---|---|
| Initial functional hub | Implemented; superseded as visual direction | Existing files and checks above |
| Live reference inspection | Completed for class, library, course overview, and article flow | RESEARCH.md |
| New experience plan | Superseded; carried out in the slices below (PLAN.md removed October 19, 2026) | This ledger |
| Navigation/entity decisions | Decided; see MAPPING.md section 5 | MAPPING.md |
| New visual shell | Implemented; browser-checked light, dark, 390px | Slice A notes below |
| Class screen redesign | Implemented; browser-checked with a removable fixture | Slice B notes below |
| Library redesign | Implemented (first version); browser-checked | Slice C notes below |
| Course/unit navigator | Implemented (first version); browser-checked, see notes | Slice D notes below |
| Roadmap presentation | **Dropped by user** | Roadmaps are hidden infrastructure |
| Bundle presentation | Not started | Slice F |
| My learning / following | Implemented; browser-checked with a removable fixture | Slice G notes below |
| History / achievements / skills | History and Achievements implemented; skills stay inside course view | Slice H notes below |
| Profile/settings replacement | Not started | Slice I |
| Membership primary section | Not started | Slice J |
| Compatibility and release checks | Initial hub only | Slice K |

## Checkpoint discipline

After each accepted decision or completed slice, update this file before another
large step. Record the changed files, checks actually run, outcomes, known failures,
and one precise next action. Never mark a proposed feature as implemented.

If work stops mid-slice, record incomplete code and any temporary test fixtures
explicitly. On resuming, inspect the current diff and rerun the smallest relevant
check instead of assuming that another assistant's edits match this document.

Do not record passwords, API keys, cookies, invitation tokens, class share codes,
student roster details, or private account identifiers in these handoff files.

## Slice A progress (October 15, 2026)

Changed (all uncommitted, live plugin folder): `templates/student-hub/workspace.php`
(left rail with icons, lower Settings/Customize/appearance/account row, top bar, mobile
drawer, Membership rail entry when commerce is on, legacy views wrapped in `oml-hub-legacy`),
`assets/css/student-hub.css` (Khan-style tokens, light/dark/system, hard-shadow button,
signed-out card), `assets/js/student-hub.js` (device-only appearance preference, rail
open/close, Escape), `includes/Student/Hub.php` (signed-out markup gets `oml-hub-signin`).
Section ids and `Hub::url()` are unchanged; no stored data touched.

Checks run: PHP syntax on workspace.php passes; ESLint and Prettier on student-hub.js pass;
Stylelint on student-hub.css passes. Signed-out page checked in the browser.

**Not done:** the 50-check integration script was NOT run (it needs the private
`OHMYLMS_TEST_CREDENTIALS`, unset in this shell). The signed-in shell, dark theme and a
390px view have not been looked at yet because 800.local needs the user to sign in.
Plus Jakarta Sans is named in the font stack but not loaded; it falls back to Noto Sans or
the system font. Skipped on purpose: Tutor Me and Teacher Tools.

**Exact next step:** user signs in on 800.local in the browser pane; then screenshot
`/student-home/` in light, dark and 390px, fix any layout issues, run the integration script
with the credentials, then start Slice C (library) on a `LearnerCatalog` read-model.

## Slice A verification and Slice C (October 15, 2026)

**Slice A verified** on 800.local while signed in: light, dark, and a 375px viewport
(drawer opens, no horizontal overflow). Fixed along the way: WordPress admin-bar offset on the
rail, dim greeting text in dark, light card tints in dark, and the signed-out card layout.

**Slice C built:** new section `library` ("Browse subjects", after My learning).
- `includes/Student/LearnerCatalog.php`: read-only adapter. Subjects are curriculum items
  that have published courses beneath them (`Placement::public_items`,
  `published_courses_by_item`); only published courses are returned; legacy category/tag
  taxonomies are not read. Search filters by course title and prunes empty groups.
- `templates/student-hub/library.php`: heading banner, search, two-column expandable subject
  tiles (native `<details>`, works without JavaScript), nested groups, empty states.
- `Hub.php`: `library` section, `hub_q` query var, settings version 3. `upgrade()` now adds
  `library` after `learn` for stored version 2 settings, and runs on `init` as well as
  `admin_init` for any visitor (it only rewrites this plugin's option).
- Data on this site is tiny: one subject with one course, so large catalogs are untested.

Checks: PHP syntax passes on all changed PHP; PHPCS on `includes/Student` and
`templates/student-hub` reports zero errors and the same six intentional warnings in
`Hub.php`; Stylelint, ESLint and Prettier pass. Browser: library, search hit and search miss.

**Not done / not verified:** the 50-check integration script (needs the private
`OHMYLMS_TEST_CREDENTIALS`, unset here) and the JS/build suite were not run. Its
section-registry assertions may need updating for the new `library` section. Subject tiles use
a letter, not the curriculum `icon` field. Courses link to the normal course permalink; the
course overview page (Slice D) does not exist yet. D1-D6 decisions in MAPPING.md still open;
D3 (which item types are Subjects) is currently "top-level items with courses".

**Exact next step:** run the integration script with credentials and fix any assertion on the
new section; then Slice D: course overview (hero, Course progress strip, unit accordions) fed
by a course-outline read-model (chapters via `ohmylms_chapter_relationship`, program items,
`student_skill_state` through a MasteryAdapter).

## Roadmap exclusion (October 15, 2026)

User: roadmaps are only a shared-syllabus mapping; skills done in one syllabus or its linked
course update other courses; they are not simple courses. `LearnerCatalog::subjects()` now
skips any course with non-empty `_ohmylms_roadmap_sources`. Checked in the browser: the only
course on 800.local was a roadmap course, so Browse subjects now shows its empty state
(a real course is needed to see a tile again). PHPCS clean on the file; PHP syntax passes.
Still to handle: My learning and `Hub::catalog_url()`/"Explore courses" still use the legacy
course list and may show roadmap courses; fix when those views are replaced (Slice G).

## Browse subjects: bundles and admin setting (October 15, 2026)

User: Browse subjects lists only courses and course bundles, and which curriculum levels
count as "subjects" must be adjustable in the admin dashboard.

- Setting lives in **OhMyLMS -> Student Home** (the hub's own editor, `templates/student-hub/editor.php`),
  panel "Browse subjects". Chosen over the Curriculum admin because the library is a hub
  section and the page already stores hub options. Stored in the existing `ohmylms_student_hub`
  option as `library_types` (curriculum item types; empty means every top-level item) and
  `library_bundles` (default on). No schema or curriculum data change.
- `LearnerCatalog::subjects( $search, $types )`: with types set, the outermost item of a chosen
  type becomes a tile and deeper levels become groups inside it. Courses linked only above a
  chosen level are not listed (stated in the admin help text). The `roadmap` type is not offered.
- `LearnerCatalog::bundles()`: published bundles with their published, non-roadmap courses.
  Viewing a bundle grants no access. Shown under "Course bundles" on the library page.
- Checked in the browser: panel renders, saving `subject` persisted and reloaded checked, then
  reverted to none. No courses or bundles exist on 800.local to see tiles, so the tile and
  bundle rendering is verified only by PHP syntax, PHPCS (zero errors, same six warnings in
  Hub.php) and the empty state, not with real bundle data. The `subjects()` type filtering
  has not been exercised against a real tree.
- Not run: the 50-check integration script (private credentials); its assertions about section
  lists and settings keys may need updating for `library`, `library_types`, `library_bundles`.

## Library verified with real data (October 15, 2026)

Created temporary test data on 800.local (two curriculum items, four courses incl. one draft, one
published bundle) through the plugin's REST API, verified, then deleted it all. Confirmed afterwards:
31 original curriculum items remain, test courses return 404, no bundles, library shows its empty
state, and `library_types` is back to empty.

Verified in the browser:
- Subject tile with a direct course plus a nested group; draft course hidden; counts correct.
- Bundle tile listing its published courses, shown under "Course bundles".
- Search: finds the course inside both the subject group and the bundle; a draft course title finds nothing.
- Type filter: with `topic` chosen, the tile became the topic with only its own course; a course linked
  only at the parent level dropped out (documented in the admin help text). Bundles unaffected.
- Admin panel save and reload persisted `library_types`.
Not verified: very large catalogs; the 50-check integration script (private credentials); subject icons.
Data note: roadmap/syllabus items on this site (ids 1, 6, 27) carry draft or roadmap courses, so they
do not appear; Digital SAT Mathematics (course 132) and IGCSE 0580 (course 153) will appear once published.
API note for future tests: courses use `name` (not `title`); bundle members are `{type, id}`;
the front end has no REST nonce, so run REST calls from a wp-admin page.

## Scope widened by the user (October 15, 2026)

User: do not only restyle the front end; looking "forced" is not good. The backend, the database,
course names and course structures may be changed where the Khan-style experience needs it. Treat
this as permission to build real structure, but still keep changes additive and reversible, keep
stored identifiers and public contracts working, document each one here, and test on the disposable
database or with removable test data. Standing requirement from the user: **Khan says "Unit" where
this LMS says "Chapter"; change it and adapt the whole LMS.**

### Terminology layer ("Chapter" becomes "Unit")

- `includes/Utility/Terminology.php`, registered in `Shortcodes.php` next to `Hub::init()`. A `gettext`,
  `gettext_with_context`, `ngettext` and `ngettext_with_context` filter for the `ohmylms` and `ohmylms-pro`
  text domains, plus an inline script after `wp-i18n` registering the same rule on `wp.hooks`
  (`i18n.gettext` family) so the React course editor and admin screens follow.
- Only displayed words change. Post type `ohmylms-chapter`, tables, REST routes (`/chapters`), URLs, CSS classes
  and hooks are untouched; nothing is migrated, and unchecking restores "chapter".
- Strings that already have a translation (for example es_ES "Capítulo") keep it. Edit those `.po` files
  if a shipped language should say Unit. No Mongolian locale ships with the plugin yet.
- Admin: **OhMyLMS -> Student Home -> Wording** (enable, singular word, plural word). Stored in option
  `ohmylms_terminology`, saved by the same capability and nonce check as the hub settings. Default on, "Unit"/"Units".
- Verified in the browser on 800.local: JS `__('Chapter','ohmylms')` returns "Unit", `_n` returns "Units", another
  text domain is untouched; the learner course page shows "1 unit" and "Expand all units"; the admin course editor
  shows "Add Unit", "Unit" and "Add unit description" with no remaining "chapter" text.
- Not covered: hard-coded English that is not wrapped in a translation function, the sample data inside
  `video-player-settings.js`, URL slugs, and the wording inside post content already saved by authors.
  Search again for stragglers when screens are rebuilt.

### Slice D: course overview

- `includes/Student/CourseOutline.php`: read-only course outline for the current learner. Draft, private,
  password-protected and roadmap courses are never described (404 style error). Units come from the enrollment's
  program version when managed, otherwise the current program or the original chapter structure. Skills and levels
  come from `CompletionPolicy::status` and `Mastery`. Not-enrolled visitors see titles only and every link goes to the
  course page. One primary action: Start/Continue learning, View course or Review course.
- `templates/student-hub/course.php`: breadcrumb, hero (title, unit and skill counts, description, action), collapsible
  Course progress card with percent chip, "x of N skills to Proficient" (or activities complete), a progress bar and a
  strip of one coloured dot per activity per unit, then unit accordions with an Expand all control.
- Route: `Hub::course_url()` -> `hub_section=library&hub_course=<id>`; library tiles and bundles now link to it.
- Verified on 800.local with a removable test course (one unit, three lessons, one quiz): not-enrolled view
  (View course, "Enroll to track your progress", dots, accordion, Expand/Collapse all) and enrolled view (Start learning,
  "0 of 4 activities complete", unit count 0/4). All test data was then deleted (course, chapters, lessons, quiz,
  curriculum item, enrollment); 31 original curriculum items remain.
- NOT verified: completed or mastered states (dots turn green/amber/purple only when the evidence pipeline reports
  them), a managed skill-based program, multiple units (the REST API would not attach extra chapters to a course;
  the accordion code handles it), practice rows, mobile width of this page, very large courses (maybe one
  completion query per lesson for non-program enrollments).
- API notes: creating a chapter with `course_id` did not link it to the course; the course's auto-created default chapter
  did link. Use `PUT /courses/{id}` and the default chapter, or the admin editor, for multi-unit fixtures.

### Next backend steps worth doing (each additive, needs a decision from the user)

1. Lesson groups inside units (MAPPING D1): add an optional group label to program items.
2. Khan mastery names (D2): display mapping for Attempted/Familiar/Proficient/Mastered.
3. Rename Chapter strings that are hard-coded, and translate for the languages you ship.
4. Review `/chapters` REST naming only if an external consumer needs `/units`; add an alias, never remove the old route.

## Khan mastery names (October 15, 2026) - MAPPING D2 resolved

View-only mapping, as requested. `includes/Skills/MasteryDisplay.php`:
- SmartScore bands onto four names: below the second band (70) is **Attempted**, 70-79 **Familiar**, 80-99
  (Proficient and Challenge Zone) **Proficient**, 100 **Mastered**; no scored answers is **Not started**. Thresholds
  come from the administrator's tuned bands (`Score::config()`), not hard-coded numbers (70/80 are only the fallback).
- Without a SmartScore (the feature is off by default, or the skill has no score row), it falls back to the stored
  evidence level: not-assessed to Not started, developing to Attempted, proficient to Proficient, mastered to Mastered.
  The stored model has no "familiar", so that name only appears when SmartScore is on.
- `MasteryDisplay::for_student( $student_id, $term_ids )`: one batch read, uses `Score::summary()` when enabled.
  Caveat: `Score::summary()` may recompute and store a missing score the first time (existing behavior), so
  "view only" means nothing here changes mastery, completion or awards; the first read of an unscored skill can
  populate that score row.
- Wired into `CourseOutline`: practice rows now carry display `level`, `label` and `score`; dots use classes
  `is-attempted`, `is-familiar`, `is-proficient`, `is-mastered`, neutral for not started; the "x of N skills to
  Proficient" count and percent use the display level. Visitors and unenrolled learners see no levels.
- Completion, certificates, recommendations and `CompletionPolicy` still use the stored level and targets. So the
  overview can say "Proficient" while a program target is judged on the stored level; they usually agree, and can
  differ for skills whose SmartScore is high but whose evidence rules have not marked them proficient.

Verified:
- `php tests/php/mastery-display-unit.php` passes (69.99/70/79.99/80/99.99/100 boundaries, tuned bands, missing bands,
  fallback levels, ordering). `php tests/php/terminology-unit.php` passes (rename rules, custom words, limits, disabled,
  translated strings kept, other domains untouched); it found and I fixed a missing-`mbstring` crash risk.
- Real database, through WordPress from the command line (read-only except three temporary rows, deleted; 0 left):
  stored levels gave Attempted, Proficient and Mastered; an unknown skill gave Not started; SQL ran with no DB error.
- Browser: course overview still renders (units, rows, dots, no PHP notices). Test course, chapter, lessons and
  curriculum item were created and then deleted; 31 original curriculum items remain.

Not verified: practice rows and dots coloured by mastery in the browser (a skill practice program cannot be
published without at least three approved questions per skill, so a visual fixture needs a question bank), the
SmartScore branch against real score rows (SmartScore is switched off on 800.local; covered by unit tests only),
the 50-check integration script, and the full JS build/test suite.

Command-line probe tip for next time: PHP at `C:/php8.5.4/php.exe` needs
`-d extension_dir=C:/php8.5.4/ext -d extension=mysqli -d extension=mbstring`, and Local's MySQL is on
`127.0.0.1:10006` (define `DB_HOST` before `wp-load.php`). Keep such probes read-only or delete what they add.

**Exact next step:** pick one of: lesson groups inside units (MAPPING D1), a published-question fixture to see
mastery colours, or Slice G (My learning, hiding roadmap courses from the legacy course list).

## Mastery display verified in the browser (October 15, 2026)

Fixture on 800.local: bank, six approved questions (two real skills, three questions and three families each),
a course with one unit, one lesson and two skill-practice items, published program, admin enrolled, and temporary
`student_skill_state` rows (developing and proficient). Seen in the browser: hero "Continue learning" (goes to the
learning path), "2 skills", progress card "1 of 2 skills to Proficient" with a 50% chip and a filled blue bar, dots
grey (lesson), amber (Attempted), green (Proficient), rows labelled Attempted and Proficient with matching icon
colours (fixed during this check: row icons now follow the level, not only "complete"). Baseline before progress:
"0 of 2 skills to Proficient", both skills "Not started".
Whole fixture then removed: REST deletes (course, unit, lesson, curriculum item, six questions, unenroll), and
exact-id database cleanup of what the plugin leaves behind (program, 2 outcomes, enrollment binding, 6
question-skill links, the bank row, temporary skill-state rows). Re-audit shows 0 fixture rows; 31 curriculum
items remain; SmartScore stays off. One unrelated `learning_enrollments` row with program_id 0 pre-exists and was
not touched. Learned: deleting a course via REST leaves its learning program, outcomes and binding rows; deleting a
question leaves `question_skills` rows; there is no REST delete for question banks.

NOT verified: **Familiar** (and SmartScore-driven Mastered). SmartScore is off and its tables
(`wp_ohmylms_skill_scores`, `XpSchema`) are not installed on this site, so `Score::enabled()` is false. Showing it
means installing/enabling the XP and SmartScore feature, which I did not do as a side effect. The score branch is
covered by `tests/php/mastery-display-unit.php` only.

## New requirements from the user (October 15, 2026): question bank scale and reach

User plans to upload Digital SAT, IELTS and Cambridge Mathematics past papers into the question bank. Every
question will be connected to skills, measured, and must drive the learner progress bar. The bank must be flexible
for any exam, topic or subject, support more question types, and support translations.

What the code already gives (checked, not assumed):
- Skill link to progress is real: `question_skills` / `qb_version_skills` -> `skill_evidence` -> `student_skill_state` and
  SmartScore -> `CourseOutline` -> dots and "x of N skills to Proficient". Quiz-level evidence (`quiz_skills`) also feeds it.
- Question types are an extension registry (`Extensions\Registry`, `QuestionTypes`): built in are single-choice, multiple-choice,
  true-false, short-text, long-text, fill-in-the-blank, dropdown-blanks, matching, numerical and structured (plus passage).
  New types can be added without core changes; see `docs/EXTENSIONS.md`.
- Banks (`qb_banks`: owner, course, visibility, grants), versions (immutable snapshots, approval), `family_id`, `difficulty`,
  `source`, `secure` flag per question; `docs/QUIZ-IMPORT-EXPORT.md` describes the existing quiz import/export.

Gaps and risks to design before the big upload (each is a candidate backend task; none started):
1. **Pool rules:** practice only draws approved, non-`secure`, auto-gradable questions with a primary skill. Written, manual
   or teacher-marked parts (IELTS writing/speaking, Cambridge working) are excluded from practice and cannot move
   SmartScore. Decide the exam-mode vs practice split and mark real past papers `secure` only if they must stay out of practice.
2. **Families:** a skill needs >=3 approved questions and >=2 families to publish a target. Past-paper variants (same
   stem, new numbers) must share a `family_id`; unrelated questions must not, or mastery looks narrower than it is.
3. **Translations:** none for questions. Skill translations exist but only for `en` and `mn` (hard-coded in `Skills\Translations`).
   Needs a design: a question translation links to its source (uuid), shares skill map, family and difficulty, has its own
   language, and learners get their language with fallback; evidence must count toward the same skill either way.
4. **Bulk import:** only `CourseImporter`, `ScormImporter` and the quiz import exist. A past-paper import (CSV/JSON/QTI-like) with
   dry-run, per-row errors, source/paper/year/question-number metadata, skill codes, family and difficulty, idempotent
   re-runs (no duplicates), and approval in bulk is the main missing tool. `DraftWriter::save_many` is the transactional writer to build on.
5. **Taxonomy beyond skills:** exam, paper, year, section, topic and language are not first-class filters. Prefer additive
   attributes on `qb_questions` (or a small meta table) over new post types; keep skills as the only mastery link.
6. **Scale:** `Selector::pool` joins four tables per skill with no per-skill cache; thousands of questions and a
   few hundred skills need an index review and bounded queries before launch. Test with a generated load set.
7. **Rights:** past papers are copyrighted by their publishers; confirm the licence before upload (recorded as a note only).
8. **Progress semantics:** a past-paper quiz attempt feeds evidence for each question's skills, so one long paper can move many
   skills at once. Decide whether exam attempts count the same as practice (`independent`/`first_try` rules in `ScoreModel`).

Suggested order for this track: (a) bulk import with dry-run and metadata, (b) question translation model, (c) exam/paper/year
attributes and filters, (d) pool and index review with a load fixture, (e) new question types as the papers require
(IELTS: matching headings, gap-fill, map labelling, speaking and writing capture with manual marking).

## Question bulk import (October 15, 2026)

Built and verified. User guide: `docs/QUESTION-BULK-IMPORT.md`.

- `includes/QuestionBank/BulkImport.php`: parse JSON/CSV, normalise (aliases), identity key (exam, paper, year, section, number,
  language), per-type validation (single/multiple choice, true/false, numerical, fill-in-the-blank, plus any registered type through
  `settings` and `options_raw`), skill resolution by code, UUID or `#id` (ambiguous codes rejected), `DraftWriter::prepare_many` as the
  authority before any write, `DraftWriter::save_many` for writing, bulk approval through the normal approve route, idempotency by import
  key plus content fingerprint.
- `includes/Rest/V1/QuestionImportController.php`: `POST /ohmylms/v1/question-bank/import` (dry run is the default; 401 when not
  logged in; needs `edit_posts`; 200 rows per request).
- `includes/QuestionBank/ImportPage.php` and `templates/admin/import-questions.php`: **OhMyLMS -> Import questions**; check, then import;
  all batches are checked before the first write; up to 1000 rows or 2 MB per submission.
- Row metadata kept per question: `_ohmylms_import_key`, `_ohmylms_import_hash`, `_ohmylms_import_paper`; bank `source`, `family_id`,
  `difficulty` use existing attributes. No schema change.
- Images: `image` accepts a media library ID or URL and sets the question image. Question-level images only; PDFs are not attached yet.

Tests: `php tests/php/bulk-import-unit.php` passes (parsing JSON/CSV, BOM, multi-line cells, aliases, keys, all validation rules, payload
shape, fingerprint). PHPCS on the new files: zero errors and zero warnings.
Live on 800.local (removable fixtures, all deleted afterwards): dry run (4 create, 2 errors: unknown skill, duplicate row); a real import with
errors and no "skip invalid" wrote nothing (checked in the database); real import with approve created 4 approved questions with correct
family, difficulty, source and skill mapping (primary and supporting); identical re-run gave 4 unchanged and 0 writes; an edited row
updated the same question (no duplicate) with a new approved version; "updates off" reported a warning instead; CSV with a multi-line stem,
`B|D` multiple answers, marks and a bank worked; the admin page showed check results, blocked an import with errors, and imported with
"skip invalid" and approve; unauthenticated calls got 401.
Cleanup: REST deletes, then exact-id removal of leftovers the plugin does not remove itself (question-skill links, the bank row).

Found along the way (existing behaviour, not caused by the import): `QuestionStore::update` sets an edited question's post status to draft
(confirmed with a stack trace and a direct `DraftWriter::save`, even when the payload says publish). Practice only needs an approved version,
so it keeps working, but the question list shows such questions as drafts. The import restores publish. The normal editor probably does the
same; worth a separate look.

NOT verified: image column with a real attachment, `fill-in-the-blank` or numerical answers actually being graded in a learner attempt,
very large uploads and timing (nothing beyond a handful of rows was run), question types beyond the five tested, the import under a
non-administrator author account, and the JS build/test suite.

### Exam content type: design for the user's decision (not started)

User wants: upload an exam paper (PDF and/or screenshots), save every question into the bank, and save the PDF/images as an **Exam** for a
course or as a stand-alone exam; Quiz = checking what was learned, Exam = mock readiness. New content type next to Quiz.

What already exists and should be reused: the assessment engine's exam preset on a quiz (`AssessmentSettings`: kind `exam`, timed
deadline with grace, sections with slot marks and optional page breaks, availability window, feedback released after close, per-student
accommodations, exam-only questions via the bank `secure` flag, versioned revisions, autosave, evidence into skills). See
`docs/ASSESSMENT-ENGINE.md` (A08 and the exam notes).
Option A (recommended): an Exam is a quiz-engine assessment with kind `exam` that the product presents as its own content type: its own
label, icon, list, "Add exam" in the course builder, and a **paper documents** panel (attached PDFs/images shown as the source paper,
downloadable or viewable in the exam page). Questions come from the bank in paper order; import rows with the same exam/paper/year
(and section) can create or refresh the exam automatically. Reuses attempts, grading, evidence, reports.
Option B: a new post type `ohmylms-exam` with its own tables. Cleaner in naming but duplicates attempts, grading, evidence and reports.
Gaps to design for A: how it appears as a course content type (the course builder source is `assets/src/features/courses/`; compiled
admin bundles must not be edited by hand), program item type (`lesson|quiz|assignment|practice` today), learner navigation (units list,
"Exam" icon/label in the overview), progress semantics (exam attempts feed skill evidence for every question's skills), attachments
(media IDs on the exam, validated as PDF/image, and which learners may see the source paper), stand-alone exams (not in any course), and
whether the exam uses `secure` questions that never appear in practice.

## MCP tools for AI classification and review (October 15, 2026)

Built and verified. Guide: `docs/MCP.md`, section "Question bank: classify and import with AI".

- Tools (ability IDs `ohmylms/search-skill-catalogue`, `import-questions`, `list-questions-for-review`, `review-questions`):
  `search_skill_catalogue` (code, UUID or words; objective; parent path; child count; paged), `import_questions` (dry run default; max 100 rows),
  `list_questions_for_review`, `review_questions` (approve or dismiss, max 50).
- New code: `includes/MCP/QuestionBankTools.php` (definitions), `includes/Rest/V1/QuestionBankToolsController.php` (internal routes
  `/mcp-question-bank/...`, registered in `Rest/Api.php`; merged in `MCP/Server::tools()`), `includes/QuestionBank/SkillCatalogue.php`,
  `includes/QuestionBank/ReviewQueue.php` (flag meta `_ohmylms_review_status` and `_ohmylms_review_data`), a `review` option in `BulkImport`
  and `confidence` and `notes` row fields, and a **Waiting for review** list with Approve and Keep as draft on **OhMyLMS -> Import questions**.
- Rules enforced on the server: MCP imports are always flagged and never approved (no `approve` argument; the ability schema rejects it);
  a changed question returns to review while its earlier approved version stays live; approval goes through the normal approve route.
- Verified live on 800.local with removable fixtures (all deleted, leftovers removed by exact id): skill search by code, words, UUID, parent and
  empty result with correct paths and child counts (DSAT.D.1.5 sits under Digital SAT Mathematics, 109 children); dry run, default dry run,
  real import (flagged pending, `approved_version` 0, confidence and notes stored); an `approve` argument ignored on the route and rejected
  by the ability layer; list shows skills with codes; approve gives an approved version; dismiss leaves it unapproved; repeat and unknown ids
  are skipped; bad decision rejected; identical re-import unchanged; edit after approval re-flags and keeps the old approved version; the
  admin review list and its Approve button. Through the Abilities API as admin: all four tools registered, MCP add-on enabled, same results.
- Tests: `bulk-import-unit.php` extended (confidence range, aliases, review fields kept out of the payload and fingerprint); PHPCS clean on the
  new files. NOT run: a real MCP client over HTTP with a bearer token (no token was created; that is the user's step), the JS build/test suite,
  the 50-check integration script, large batches, non-administrator accounts (the tools need `manage_options`).

Open points:
- The skill catalogue returns the skill tree as stored; AI quality depends on the skill names and objectives. 45 of the 47 root skills have no
  children, and the parents have no code, so a model may need objectives or descriptions before a big upload.
- Flagging cannot tell a reviewer's edit in the question editor from an AI edit; editing a flagged question in the editor leaves the flag
  until someone approves or dismisses it.
- A script-based batch classifier (any model provider) is not written; it should call `search_skill_catalogue` and `import_questions` with
  `dry_run` first, in batches of at most 100 rows.

## Real paper study and skill-search improvement (October 15, 2026)

The owner supplied a real Cambridge IGCSE 0580 Paper 4 question paper and mark scheme (20 and 10 pages) as material "for improvement".
Full analysis and design: `docs/EXAM-PAPERS-AND-FIGURES.md` (the paper's text is not copied into the repo).

Built: `SkillCatalogue::search` is now ranked (any word, name/code weighted over objective/description, three-letter stemming, filler
words ignored, exact code or UUID first). Verified live: empty search lists all 476 skills, parent browsing (109 children), exact code first, no
match returns 0, junk or SQL-looking input is harmless, paging works; all 26 questions of the real paper found a sensible Cambridge skill among the top results.
PHPCS clean on the file. The earlier unit tests still pass.

Key findings from the paper:
- The plugin already has a `structured` multi-part question type with part marks, numeric/text/hand-marked parts, teacher-only rubric and **per-part skills**.
  The bulk import cannot create it yet: next task is a `parts` field on import rows (mark-scheme partial marks go to the part rubric).
- Exam needs: paper header fields, attached documents (question paper, mark scheme released after results, formula sheet), a paper mode with
  teacher or self marking for hand-marked parts, and (decision needed) a hidden single-exam wrapper course for stand-alone exams because all quiz access,
  attempts (`course_id`) and reports are course-based.
- Figures: store a declarative, validated figure spec (2D SVG, 3D Three.js with SVG fallback), never AI-written HTML or script; a person checks the rebuilt figure
  against the original. Three.js is not in the project; load it lazily like MathLive.

Not built this round: `parts` import, figure renderer, Exam entity (only discovery and design). Nothing was imported into the live site from the paper; the classification
test used only read-only skill searches. The earlier "Exam content type" slice is still not started in code.

## Khan navigation, History, Achievements, Classes (October 15, 2026)

The owner dropped the Exam work and asked to keep replicating Khan Academy. Done this round (Tutor Me and Teacher Tools remain skipped by decision):

- **Rail order** now: Home, My classes, My learning, Browse subjects, Achievements, History, Membership; Settings below. New sections `achievements` and `history`
  replace `work` and `progress` in navigation (settings version 4; the upgrade swaps them in saved navigation and runs for any visitor). `work` and `progress` stay valid
  section ids for old bookmarks and can be re-enabled in the admin editor; fresh installs exclude them (`Hub::LEGACY_SECTIONS`).
- **History** (`includes/Student/HistoryFeed.php`, `templates/student-hub/history.php`): one dated list merging completed lessons, quiz and exam attempts (with score percent
  and Exam or Quiz label) and finished skill practice (with answers right out of answers given), newest first, filters for period (week, month, all) and type, totals (activities,
  practice answers, active days), 20 per page. One UNION query paged in SQL, titles hydrated only for the page; practice times shifted from UTC to site time so sources sort together.
  Verified on 800.local with the admin's 6 real quiz attempts (type and 7-day filters, unknown type falls back to all, no learner returns nothing, no DB errors); mobile 375px has no overflow.
  Not shown because the data does not exist: minutes spent and skill-level change per activity (Khan has both).
- **Achievements** (`templates/student-hub/achievements.php`): Khan-style banner; **Mastery learning** tiles (Attempted, Familiar, Proficient, Mastered) from the new
  `MasteryDisplay::counts()`; XP tiles only when XP is enabled; the existing streak card only when streaks are enabled; badges when badges are enabled; completed courses with the
  existing certificate download button (its data attributes were verified against the download script). Verified empty and populated (temporary skill rows, removed).
- **Classes** (`templates/student-hub/classes.php`): Khan layout. Class selector on top, "You have no assignments to complete / Explore the library or check another class / Go to library" hero when empty,
  otherwise an assignment list (overdue badge, completed struck through, Open assignment) with a right column: Mastery learning legend (counts) and the class card with a link to
  Teachers and guardians. The old sub-navigation is gone for classes; Join a class and Connections are linked from the page, and Connections has a back link. Verified on 800.local with a
  throwaway school, class and two assignments (created and then deleted by exact id; all school tables are empty again), including the join form, connections view and mobile.
- Files: `includes/Student/HistoryFeed.php`, `includes/Student/Hub.php`, `includes/Skills/MasteryDisplay.php` (`counts`), templates `history.php`, `achievements.php`, `classes.php`,
  `workspace.php`, `assets/css/student-hub.css`. Stylelint and PHPCS clean on all touched files (Hub.php keeps its six intentional direct-query warnings). Unit tests still pass.

NOT verified or not done: the 50-check integration script (needs the private test credentials; its section-list assertions may need updating for the new default navigation); the
JS build and test suite; History with lessons or practice rows (the admin has only quiz attempts); SmartScore-driven Familiar tiles (SmartScore is off on this site); XP, streak and
badge tiles (those features are disabled on 800.local, so only their absence was seen); the "Mission" and "Gem challenge" cards (not built, no equivalent data); My learning (next).

Navigation upgrade checked live (`Hub::upgrade`, run against the real setting and then restored identically): saved version 3 navigation becomes
home, learn, library, achievements, history, account (My work and Progress removed, Achievements and History added); version 2 gains classes as before; fresh defaults
exclude the legacy sections. `tests/php/student-hub-integration.php` was updated to cover every section (including the legacy ones through the registry) and to assert this upgrade;
syntax checked only, the script itself still needs the private test credentials to run.

**Exact next steps (pick in this order unless told otherwise):** (1) My learning in the Khan style, using only real follow/enrol data and hiding roadmap courses
from the legacy course list (`Hub::catalog_url`, `ShortcodeCourseList`); (2) Settings (account, appearance, notifications, connections) replacing the legacy profile rendering,
with the Khan "disabled until changed" save buttons; (3) Membership as a primary section; (4) the teacher shell; (5) activity player context (exit control, unit navigation, return to the course overview).

## My learning and roadmap hiding (October 16, 2026)

- **My learning** (`includes/Student/MyLearning.php`, `templates/student-hub/my-learning.php`): one Khan-style page replacing My courses, Learning paths and Explore courses
  (those old links open it; `Hub::views('learn')` is now empty). A "Continue learning" card for the most recent course in progress, a grid of the learner's enrolled courses with
  a real progress bar and a Start or Continue button (completed courses show a full bar and Review course), and "Collections you follow" listing each followed bundle's courses.
  Progress comes from the same `CourseOutline` the course page uses, so both screens always agree. Following a collection still only lists it (no access, no enrolment).
  Courses in progress come first, then completed; 12 per page. Per-course outline work means a learner with very many enrolments pays one outline per card shown (12 per page).
- **Roadmap courses are hidden at the source.** `LearnerCatalog::hide_roadmaps` (a `pre_get_posts` guard registered from `Shortcodes.php`) removes roadmap courses from every
  front-end course list: the old `[ohmylms_course_list]` shortcode, the course archive and AJAX course filters. It leaves wp-admin, REST (the editors), single course pages and
  queries for one explicit course alone. The probe caught a recursion bug in my first version (the roadmap lookup was filtered by its own guard and found nothing, which would have
  shown roadmaps again); fixed with a flag, and proved: with the guard 0 courses, without it the roadmap course 280 is present, an explicit load of 280 still works, the
  shortcode output does not contain it. My learning and Browse subjects never list roadmap courses either.
- `Hub::catalog_url()` now prefers Browse subjects over the old catalog page when the library section is enabled.
- Verified on 800.local with a removable fixture: two published courses (one in progress, one completed), the admin enrolled in both and in the roadmap course, a published followed
  collection. The page showed only the two real courses (roadmap hidden even though enrolled), in-progress first, correct buttons and labels, the followed collection with its courses, and no
  overflow at 375px. Empty state checked before. Then everything was removed: collection and follow, both courses with their units and lessons, all three enrolments and the three
  learning-binding rows that viewing a course creates; the admin has no enrolments again; roadmap course 280 untouched.
- Lint: PHPCS and Stylelint clean on touched files. The integration script was updated (its "My Courses" assertion now expects the new page) but still needs the private credentials to run.

NOT verified: legacy `profile/my-courses.php` is no longer used by the hub (still used outside it); My learning with 13+ courses (paging) or a managed skill-based program card; the old
course archive page rendered in a browser (checked through queries and the shortcode, not visually); learners who are enrolled only in unpublished courses (they are simply not listed).

## Settings (October 16, 2026) - BUILT, NOT YET TESTED (hand-off point)

Replaces the legacy Profile / Edit profile / Preferences / Notifications screens inside the hub with one Khan-style Settings area. Every change made:

- **New `includes/Student/SettingsSave.php`** - safe per-card save handler. `admin_post_ohmylms_hub_settings` (registered by `SettingsSave::init()` in `includes/Shortcodes/Shortcodes.php`, next to `LearnerCatalog::init()`).
  Cards: `account` (first/last/display name), `about` (bio, interests, country, phone, whatsapp, address), `email` (needs current password), `password` (current + new + confirm, min 8,
  keeps the session signed in via `wp_set_auth_cookie`), `notifications` (four on/off prefs). Each card changes ONLY its own fields (the legacy `FormHandler::save_account_details` reset every
  unposted field, which is why it was not reused). Per-card nonce `ohmylms_hub_settings_<card>`, signed-in learner only, always acts on the current user. Pure logic is in
  `SettingsSave::apply($user_id,$card,$input)` (returns `saved` + `errors`) so it can be tested from CLI; `handle()` is only nonce/redirect glue. Result is shown once via a 60-second
  transient (`flash()`).
- **New `templates/student-hub/settings.php`** - views: Account (Basics, About you, Email, Password cards), Appearance (device-only Match device / Light / Dark using the existing
  `[data-hub-theme-set]` buttons; applies at once, stored in localStorage, no server save), Notifications (four checkboxes), Connections (read-only list of the learner's classes with teacher and
  guardian display names via `Hub::supporters`, plus Join a class). Each form carries `data-hub-dirty`; its Save button has `data-hub-save` and starts `disabled`.
- **`includes/Student/Hub.php`** - `views('account')` is now account, appearance, notifications, classes (labelled Connections) + the unchanged commerce views (orders/invoice/billing/membership).
  Legacy endpoint map: profile, profile-edit, edit-profile -> account; settings -> appearance; notification -> notifications. `Hub::account()` no longer maps profile/profile-edit/settings/notifications
  (only commerce views still use the legacy renderers). Unknown views fall back to the first view (account).
- **`templates/student-hub/workspace.php`** - Settings rail link now points at `account/account`; section `account` renders `settings.php` for the four new views and the legacy wrapper for commerce views.
- **`assets/js/student-hub.js`** - dirty-state: snapshots non-hidden field values on load and enables Save only when they differ (re-checked on input, change and `pageshow`).
- **`assets/css/student-hub.css`** - `.oml-hub-settings`, `-settings-card`, `-field`, `-check`, `-actions`, `-save` (+`:disabled`), `-theme-large`, `-notice` (inserted before the editor-grid rules).

Honesty notes:
- The notification preferences are STORED on the learner but a grep found nothing outside Student/FormHandler/templates that reads them, so they may not drive any email. The UI does not claim they do.
- "Save buttons disabled until you change something" applies to every server-saved card. Appearance has no Save button because it applies instantly and is saved on the device only.

Verified: `php -l` on all touched PHP; PHPCS has no errors on `SettingsSave.php`, `settings.php`, `workspace.php` (Hub.php shows only its existing direct-DB warnings; `Shortcodes.php` has many errors that predate this slice - my change there is one added line).

NOT verified / TO DO next (for whoever continues, e.g. Codex):
1. Node build tools were not found at `%LOCALAPPDATA%\OhMyLMS-build-tools\node-v26.0.0-win-x64` this session, so Stylelint, ESLint and Prettier have NOT been run on `student-hub.css` / `student-hub.js`. Run them (watch for no-descending-specificity and CRLF vs LF).
2. Test `SettingsSave::apply()` by CLI with a throwaway user (create, test, delete): partial save keeps untouched profile fields; wrong current password rejected for email and password cards; duplicate email rejected;
   display name that is an email rejected; notifications off/on round-trip. Never test on the admin's real account or change its password.
3. Look at the page in the browser (desktop + 375px, light + dark): all four views, Save disabled on load and enabled after an edit, flash message after saving, Appearance buttons, Join a class link.
4. Confirm the admin_post form works for a logged-in learner and `wp_safe_redirect` lands on `Hub::url('account', <tab>)`; confirm no leftover `ohmylms_hub_settings_flash_*` transients matter.
5. Update `tests/php/student-hub-integration.php` if it asserts the old Profile/Preferences labels (not checked).
6. Remaining roadmap: Membership as a primary section (slice J), activity player context (exit control, return to course overview), Khan-style teacher shell (Teacher Tools excluded), Mission/Gem cards (no data).

## Hand-off summary (October 16, 2026)

Work happens in the LIVE plugin folder `C:\Users\abaym\Local Sites\xyz\app\public\wp-content\plugins\OhMyLMS` (site http://800.local), not the git worktree. All work is uncommitted on `main`.
Decisions to keep: no Tutor Me, no Teacher Tools, no Exam content type, roadmaps never shown to learners, "Chapter" reads "Unit" via the Terminology layer, AI-imported questions stay unapproved until a person approves.
Slices done (details above in this file): A shell, C library, D course overview, mastery names, bulk import + MCP tools, History/Achievements/Classes, My learning + roadmap hiding, Settings (built, untested).

### Settings verification (October 16, 2026, later)

- CLI probe with a throwaway user (deleted afterwards): 16/16 checks passed. About saves; Basics saves and keeps bio/phone/interests; email-as-display-name rejected; email change rejected with wrong
  password and accepted with the right one; password change rejected for wrong current, too short, mismatch and accepted otherwise; notifications round-trip on/off and keep the bio; unknown card and no user rejected.
  (The "email already registered" branch was not exercised.)
- Browser on 800.local: all four views render; subnav shows Account, Appearance, Notifications, Connections plus commerce tabs; all Save buttons disabled on load, enabled after an edit and disabled again
  when the edit is reverted; no horizontal overflow at 375px. A real notifications submit saved, redirected back, showed "Your changes were saved." and persisted; the admin's setting was then restored to its original (all off).
- Still NOT done: Stylelint/ESLint/Prettier on `student-hub.css` / `student-hub.js` (Node tools not found); real browser submit of the email/password cards (deliberately not run on the admin account); Connections with a real class; light-theme visual check of Settings.

### Settings lint and light theme (October 16, 2026, final)

- Node tools are the plugin's local `node_modules` with system Node v25.2.1 (`npx --no-install eslint|stylelint|prettier`); the pinned v26 path from earlier notes does not exist on this machine.
- ESLint, Stylelint and Prettier now pass on `assets/js/student-hub.js` and `assets/css/student-hub.css`. Fixes: Prettier reformat of the dirty-state filter; the notice paragraphs got their own class
  `oml-hub-notice-line` (was `.oml-hub-notice p`, a no-descending-specificity error) in both CSS and `templates/student-hub/settings.php`. PHPCS still clean on `settings.php`.
- Light theme checked in the browser: Settings and Appearance render correctly. Remaining unverified: Connections with a real class, email/password cards submitted through the browser.

## Activity player context (October 16, 2026)

- **New `includes/Student/PlayerContext.php`** (registered in `Shortcodes::init()` after `SettingsSave::init()`): on `ohmylms_before_lesson_main_content` (priority 1) signed-in learners see a slim bar above lessons,
  quizzes and assignments: "<- Course overview" plus the course title. The link goes to the Khan-style overview (`Hub::course_url`) when Browse subjects is enabled, otherwise to the course page.
  It only links; it changes no progress, access or content. Nothing is shown to signed-out users or when no course is found.
- **New `assets/css/player-context.css`**, enqueued on `wp_enqueue_scripts` only for signed-in learners on lesson/quiz/assignment pages (light bar, wraps on small screens). Stylelint and PHPCS clean.
- Verified: render probe from CLI on the existing quiz 297 (course 119) returned the bar with the overview link `.../student-home/?hub_section=library&hub_course=119`; logged-out returned nothing; the hook is registered.
- NOT verified: the bar in a real browser page. Course 119 is a draft and visiting its quiz hits the known draft-course redirect/malformed-permalink quirk, so I did not see it rendered. The bar is light-only (does not follow the hub dark theme),
  and it is not shown on session (Zoom/Meet) pages. Next time a published course with a lesson exists, load a lesson and check the bar, its position under the theme header and the 375px layout.

## Course page now opens the Khan-style overview (October 16, 2026)

- The user opened `http://800.local/?post_type=ohmylms-course&p=119/course-quizzes/all-question-types-test/` and it showed the OLD single-course page. (That URL is not a supported or deprecated route: the site uses plain permalinks and the
  draft course's quiz link is built by appending a pretty path to `?p=119`; WordPress ignores the extra path and serves the course.)
- `Hub::redirect_course_page()` (template_redirect, priority 6): signed-in users who open a front-end single course page are redirected to `Hub::course_url()` (the Khan-style overview). Skipped for roadmap courses,
  when Browse subjects is off, with `?preview=...` or `?legacy=1`, and for signed-out visitors (public course page stays). `is_preview()` cannot be used: it is true for editors viewing drafts.
- The old course page is still where enrolment happens, so every outline link to it (`View course`, unenrolled activity rows, `Review course`) now goes through `CourseOutline::course_page()` which adds `?legacy=1`; otherwise those links would redirect back to the overview.
- Verified: probe redirected course 119 to the overview; browser followed it and showed the Khan-style overview; the "View course" link is `...&p=119&legacy=1` and returns 200 (no loop). PHPCS clean on both files.
- NOT verified: enrolling from the legacy page and returning; a lesson URL of a published course (lessons still use the classic lesson template with only the new exit bar); the lesson/quiz player itself is NOT yet Khan-styled.

## Final hand-off for Codex (October 16, 2026)

State: everything below is committed on `main` in the live plugin folder `C:\Users\abaym\Local Sites\xyz\app\public\wp-content\plugins\OhMyLMS` (site http://800.local). Work there, not in the `.codex\worktrees` checkout.

Built this session, newest first (details in the sections above):
1. Course pages -> Khan-style overview redirect (`Hub::redirect_course_page`, `?legacy=1` escape, `CourseOutline::course_page`).
2. Activity player exit bar (`Student/PlayerContext.php`, `assets/css/player-context.css`).
3. Settings: Account, Appearance, Notifications, Connections (`Student/SettingsSave.php`, `templates/student-hub/settings.php`); verified by CLI probe + browser + lint.
4. My learning + roadmap hiding; History, Achievements, Classes; question bulk import + MCP tools; mastery names; course overview; library; shell.

How to lint (this machine): `npx --no-install eslint|stylelint|prettier ...` from the plugin folder; PHPCS via `C:/php8.5.4/php.exe tools/coding-standards/vendor/squizlabs/php_codesniffer/bin/phpcs --standard=phpcs.xml.dist <files>`.
CLI probes need `-d extension_dir=C:/php8.5.4/ext -d extension=mysqli -d extension=mbstring -d extension=openssl`, `define('DB_HOST','127.0.0.1:10006')` and `$_SERVER['HTTP_HOST']='800.local'` before `wp-load.php`.
Rules: never log in as other users or change the admin password; fixtures must be removable and removed; AI-imported questions stay unapproved; no Tutor Me, Teacher Tools or Exam type.

Next candidates, in suggested order:
1. Khan-style lesson/quiz/assignment PLAYER (single-lesson templates still classic; only the exit bar was added; the bar is light-only and absent on session pages). Verify with a published fixture course (drafts hit the plain-permalink quirk).
2. Membership as a primary section (rail link exists; renders legacy purchase screens).
3. Khan-style teacher shell (role switch, no Teacher Tools).
4. Not verified anywhere: Connections with a real class, email/password cards via the browser, enrol-from-legacy-page round trip, the 50-check integration script (needs private credentials), the JS build/test suite.

## Current implementation checkpoint (October 16, 2026, later)

This checkpoint supersedes older descriptions of the player as an exit bar only.
The initial focused player was committed externally as `2f4a2a4`; additional work
below is uncommitted in the live plugin directory. No push was performed here.

- Player: focused course/unit navigation, current activity and course exit, shared
  appearance preference, scoped lesson/quiz/assignment presentation. Built-in quiz
  designs use the paper presentation inside this shell; custom designs remain
  supported and saved assessment settings/contracts are unchanged. An active quiz
  keeps native exit controls and hides extra navigation. Session context is included
  in code but has not been verified in the browser.
- Membership: native overview, plans, purchases and owned invoices; bounded queries,
  actual subscription status and dates. Legacy commerce routes map here. Placeholder
  billing information is removed from Settings; checkout remains the billing flow.
- Teacher: eligible role switch, My classes, Assignments, Roster, Reports and Class
  settings, using existing relationship checks. **A class is a student roster.**
  Teacher rows are excluded from the student list. Assigned work/report data stays
  class-scoped; existing management pages still handle editing. Full teacher form
  replacement and full Khan Academy feature parity are not claimed.
- Landing: first visit chooses Classes when applicable, otherwise My learning;
  progressive enhancement remembers a valid learner section/view on the device.
  Home remains a compatible route but is removed from the primary rail.
- Parent/child: the new specification has been read and integrated into the ordered
  roadmap near the top of this document. No parent/child schema or UI is implemented
  by this checkpoint. Preserve the new specification and start it at P0.

Validation actually run:

- `npm test`: 415 tests, 414 passed, one skipped, zero failed. `npm run build` passed
  with two existing bundle-size warnings. `npm run lint:source` passed.
- Learning model unit checks: 25 passed; mastery display and membership selection
  unit scripts passed. Extended hub integration tests were syntax-checked, but the
  credential/disposable-database integration suite was not run.
- Live disposable fixture probe: 21 passed, including customer ownership, all four
  Membership views, teacher views, Connections with an approved teacher, immediate
  revocation, and signed-out rejection. Synthetic fixture posts, class, school,
  memberships, teacher, enrollment and quiz attempt were removed afterward.
- Targeted ESLint/Prettier (hub/player JS) and Stylelint (hub/player CSS) passed.
  PHPCS on hub/player/new classes and hub templates: zero errors, ten warnings
  (six existing database warnings, two membership meta-query warnings, two fresh
  teacher permission-query warnings). Syntax checks passed on the modified quiz
  template and integration script.
- Repository-wide standards are still failing: existing PHP debt, 7,019 JavaScript
  errors/27 warnings, and 1,649 CSS errors. The modified legacy `quiz-form.php`
  retains the same 82 errors/nine warnings as its HEAD baseline. No blanket rule
  disabling or baseline was added to conceal these results.
- Desktop browser: lesson/quiz/assignment frames, light/dark presentation,
  Membership, teacher roster and existing quiz exit UI inspected. The empty fixture
  quiz correctly refused to start; a synthetic in-progress attempt checked the
  frame only. Answer submission/grading is not verified by that inspection.

Still open, in order:

1. Fix and verify legacy free enrollment: clicking Enroll now on the fixture's
   legacy course page navigated to the new overview without creating enrollment.
   Investigate the existing add-to-cart script/localization and AJAX flow; retain
   its nonce/access checks. Do not replace it with insecure GET enrollment.
2. Verify remembered-section landing, role-switch interaction and teacher Settings
   save return context. Finish actual mobile checks: the IAB viewport override did
   not change the observed desktop width, so the new player/mobile layout is unverified.
3. Run the credential-protected hub/school integration suites in their required
   disposable environment. Email/password browser submissions and Connections with
   a real class remain unverified; only synthetic class relationships were checked.
4. Continue parent/child P0 and subsequent gates in the integrated roadmap.

Screenshots under ignored `test-results/` include `teacher-roster-final.jpg` (light,
synthetic student roster) and `teacher-roster-dark.jpg`. The older file named
`player-mobile-dark.jpg` actually captured a desktop viewport; it is not mobile proof.

## Enrollment recovery, legacy retirement and parent P0 (October 16, 2026)

This section supersedes the unresolved enrollment diagnosis and the statement that
no legacy pages had yet been retired. Work remains uncommitted in the live plugin
folder; no push was performed.

### Runtime changes

- The enrollment failure was an unconfigured checkout page: the add-to-cart script
  loaded and posted correctly, but `ohmylms_get_checkout_url()` returned an empty
  string. Created/configured the canonical **Checkout page 395**, at
  `http://800.local/ohmylms-checkout/`, with `[ohmylms_checkout]` and the existing
  checkout template. This is a real required page, intentionally retained.
- `includes/Ajax.php` now rejects an absent checkout destination with HTTP 503 before
  changing the cart. It no longer reports success with a relative/empty redirect.
  The legacy JS still has limited error presentation; improving that authored
  interaction is separate from this routing fix. No payment bypass was introduced.
- `Hub::redirect_course_page()` now applies only to GET, preserving form handlers.
- Teacher Settings forms carry the requested presentation context; the save handler
  rechecks `TeacherWorkspace::available()` before returning there. A forged teacher
  context falls back to the learner's own Settings. No arbitrary return URL is accepted.
- History's filter label now reads Quizzes, removing the excluded Exam wording.

### Legacy retirement ledger

New `includes/Student/LegacyPages.php` registers redirects independently of page
publication. Explicit administrator-only `retire()` checks the configured page,
exact standalone shortcode, metadata, child pages and menu references before
moving it to Trash. It retains custom content/builder metadata and pages with
children or explicit menu dependencies. No automatic frontend deletion occurs.

| Retired ID | Former URL | Replacement |
| --- | --- | --- |
| 115 | `/my-dashboard-2/` | My learning |
| 117 | `/my-profile/` | Settings / Account |
| 118 | `/my-courses-2/` | My learning / Courses |

All three are **in Trash**, not permanently purged. Their configured student page
IDs now point to the canonical hub. Redirects also support plain page-ID URLs,
known account endpoints/custom endpoint slugs, invoice ID and pagination; actions
and old nonces are not forwarded. Logout uses WordPress's own nonce-protected URL.
The ledger is option `ohmylms_hub_retired_pages`. Default page provisioning omits
retired definitions. Disabling the hub through its settings restores recoverable
pages and their original configured IDs via `LegacyPages::restore()`.

Narrow compatibility exception: retain the public tags `ohmylms_dashboard`,
`ohmylms_profile`, `ohmylms_my_profile`, and `ohmylms_my_courses`. With the hub enabled,
they now render a canonical workspace link instead of the obsolete interface,
preserving surrounding mixed content and preventing raw shortcode text. With the
hub disabled, the existing renderers remain available for rollback. Filtered tag
names and public callbacks are unchanged. Embedded/editor/theme consumers and
disabled-hub fallback still depend on these tags/templates; do not remove their
registrations or legacy renderer files until those dependencies are migrated.
Checkout, course catalog, Membership and purchase tags remain active dependencies.
Other plugins' older `cr-*` pages/tags were not retired by this plugin migration.

### Verification and cleanup

- `php tests/php/checkout-routing-unit.php`: two checks passed, including invalid
  nonce rejection and missing checkout rejection before any cart/payment service.
- Disposable live CLI checkout: zero-price order created active enrollment and
  `CourseOutline::get()` reflected it. Outbound mail/HTTP were suppressed within
  that fixture process. The test learner, order and enrollment were removed.
- Settings handler probe: five checks passed for eligible teacher return, forged
  teacher-context rejection, correct card return and POST redirect handling. This
  was a CLI handler check; no real email/password form was submitted in the browser.
- Retirement probe: **19 checks passed**, including custom content, builder metadata,
  draft child-page preservation, plain/pretty route handling, invoice context,
  filtered provisioning, shortcode adapters, unauthorized actor rejection and rollback.
  Its fixture pages were removed and original options restored before applying the
  real three-page migration. Verified the retired pages had no builder/custom metadata.
- Browser: the old dashboard/profile/courses URLs redirected to their proper new
  views after trashing; remembered History landing and Learner -> Teacher switching
  worked; teacher Settings forms contained the teacher context. Checkout displayed
  the fixture at $0. **Final checkout submission was tested by CLI, not in the browser.**
- Synthetic course 390 and activities 391–394 were removed. Fixture persistent-cart
  references and any fixture enrollment xAPI references were cleaned. A final query
  found zero fixture posts. Checkout page 395 remains published. Screenshot:
  `test-results/legacy-pages-retired.jpg`.
- Targeted PHPCS: new retirement class and modified Settings/templates have no
  errors; the test stub retains three unused-parameter warnings, the extended hub
  integration script 30 existing fixture/database warnings, and Hub six database
  warnings. The changed legacy files retain their prior counts: Ajax 176 errors/25
  warnings; Shortcodes 19 errors. These were checked before and after the edits.
  Syntax checks and `git diff --check` passed. Repository `lint:standards` was rerun
  and still stops on existing PHP debt (`test-results/continuation-standards.log`).
  The credential-protected disposable integration suite was extended but not run.
  No JS/build-source changes in this continuation; earlier npm test/build results
  remain recorded above, not claimed as newly rerun.

### Parent/child P0 and exact next work

Read `docs/learner-experience/PARENT-CHILD-P0.md`: source audit, observed/proposed
screen inventory, separate independent-family table/API contract, scope/cache rules,
provenance gap and migration boundaries. Existing school guardian queries are scoped
to approved school work; they are not permission to read a child's global history.
School signup requires 12–128-character passwords, while Settings currently allows
eight; reconcile that policy explicitly in P1 rather than claiming it is unified.

Next: **P1 role selection and parent onboarding**, reusing authentication and the
existing parent role, with explicit role-aware landing and no role-based child-data
grant. Then P2 school-linked read-only parent workspace. Independent-family tables
and child setup remain P3. Parent dashboard, linking, recovery and personalization
step-two reference screens still need observation or a clearly proposed design.
Mobile verification, credential integration suites, real-class Connections, and
browser email/password submission remain open. Continue retiring legacy dependencies
as their replacements and redirects are verified; keep this ledger current.

## Native teacher class management (October 16, 2026)

A class is an independent **student roster**, separate from courses. Management is
native in the hub (`hub_role=teacher`); no Tutor Me, Teacher Tools, Exam type or
learner-facing roadmaps.

### What exists

- `includes/Schools/ClassManagement.php`: create (school contexts limited to
  administered schools/active academic years), edit name/subject/grade (never
  school/year), archive with explicit confirmation, restore, searchable/paginated
  roster, add existing approved school students (separate candidate pagination),
  remove roster membership (accounts, enrollments and history are untouched).
- Private standalone invitations: recipient email must match, single-use, 48-hour
  expiry, revocation, issuer-access revalidation, **no automatic email**. They reuse
  `school_invitations` with `school_id=0`; `Service::accept()` branches to
  `ClassManagement::accept()` inside its existing transaction/token validation.
  Acceptance only grants standalone student membership: no school membership, no
  global role change, no email-verified flag.
- `includes/Student/TeacherActions.php` (POST handlers, nonce/method checks; also
  redirects the old `?ohmylms_portal=1&view=teacher-dashboard` GET entry to the hub,
  excluding invitation and file flows), `TeacherWorkspace.php`, `Hub.php`,
  templates `teacher.php`, `teacher-class.php`, `teacher-create.php`, `classes.php`
  (learner class list/connections support standalone classes), `student-hub.css`.
  Invitation links preserve their destination through sign-in.

### Verification

- Browser (admin, real session): create + redirect to roster, edit saved, archive
  (confirmation), archived list, open archived class, **Restore class** ("Class
  restored."), **roster removal** (blocked without the confirm checkbox; with it,
  "Student removed from this class. Their account and learning history were kept."),
  **old portal redirect** `?ohmylms_portal=1&view=teacher-dashboard` ->
  `/student-home/?hub_section=classes&hub_role=teacher`. Mobile (375px) roster has
  no horizontal overflow. The disposable student's invitation acceptance was done
  by CLI, not browser.
- `test-results/teacher-management-probe.php`: 47 checks passed on synthetic
  fixtures (permissions, nonce/method, expiry/replay/revocation, issuer revocation,
  archive/restore, school-roster restrictions, enrollment preservation).
- PHPCS (targeted): 0 errors. Warnings: ClassManagement 15 and TeacherWorkspace 4
  (direct DB/cache), test support 15, integration runner 1. Syntax checks passed;
  stylelint passed (trailing blank line at CSS EOF removed).
- Repository-wide PHP/JS/CSS standards debt is substantial and unchanged.

### Not run / limitations

- The credential-protected `tests/php/teacher-class-integration.php` runner was
  **not run** (disposable-database guard not bypassed).
- Later refinements (verified with a temporary class, since removed): `aria-current`
  on the selected class tab, "All classes" returns to the archived list for archived
  classes, spacing under the back pill. Still not done: a wider class panel. Roster-row wrapping with a real student on
  narrow screens was not re-checked after the student was removed.
- Teachers need no automatic invitation email; teachers share links manually.

### Fixture cleanup

Live fixture class 5 and synthetic student 206 were removed, including class
memberships, the invitation row and its `class_invitation_*` audit entries (matched by
exact invitation IDs). Verified afterwards: zero classes, memberships, invitations
and invitation audit rows, user gone. Helper scripts remain under the git-ignored
`test-results/` for reference. The `Demo student` fixture no longer exists.

Next: **P1 parent onboarding** per `docs/learner-experience/PARENT-CHILD-P0.md`.

## P1: role selection and parent onboarding (October 16, 2026)

Built per `PARENT-CHILD-P0.md`; school-linked only. No new tables or migrations.

- `includes/Student/ParentWorkspace.php`: `available()` (parent role or an active
  guardian link, schools enabled), `parent_only()`, `url()`, `children()` (delegates
  to `Schools\Service::children()`, so each child is authorized fresh by school,
  guardian link and active membership; a parent role label grants nothing).
- `includes/Student/ParentActions.php`: signed-out `admin-post` handler with nonce
  that calls `Schools\Controller::signup()` (made `public`; behavior unchanged, same
  rate limit, honeypot, 12-128 password rule, parent role only, verification email).
  Errors other than 400/429 are replaced by a generic message.
- `Hub.php`: signed-out entry renders `templates/student-hub/signin.php` (learner /
  parent / teacher chooser with explicit destinations preserved through sign-in; the
  parent choice shows the sign-up form). Signed-in `hub_role=parent` shows a
  "Family workspace" (`parent.php`: approved children or an empty state, 100-row
  notice). A parent-only account with no explicit section lands there; explicit
  routes always win. Forged `hub_role=parent` is denied.
- `workspace.php`: role switch lists Learner/Teacher/Parent per eligibility.
  Settings keeps the parent context on save (`SettingsSave`).
- Password policy reconciled: hub password change now requires 12-128 characters
  (`SettingsSave::MIN_PASS`/`MAX_PASS`), matching school sign-up.

Verification: CLI probes (`test-results/parent-p1-probe.php` 17 checks:
chooser per role, parent form only for parent, no-link empty state, parent-only
landing, subscriber denied; `parent-signup-probe.php` 6 checks: short password,
honeypot, forced role, parent-only role, no elevated caps; mail/HTTP suppressed,
fixtures removed, one orphan `account_created` audit row removed). Learner page
still renders in the browser with the role switch. PHPCS 0 errors on changed files
(Hub 6, signin 5, workspace 1 warnings); stylelint passes.

Not done / limitations: no browser test of the signed-out chooser or real
sign-up/email (signing out would end the admin session; sign-up tested by CLI);
no child-detail view yet; parent dashboard layout is a proposed OhMyLMS design, not a
verified Khan Academy replica. Next: **P2** read-only school-linked child overview
and progress, denying unrelated children, inactive schools/memberships and revoked
links on every request.

## P2: read-only school-linked child overview (October 16, 2026)

- `ParentWorkspace::child()` accepts a requested `hub_child`/`hub_school` pair only
  if it is in the actor's fresh `Service::children()` result (active school, active
  guardian link, active student membership). Request values select a candidate and
  never grant access. `child_url()` builds the links; query vars `hub_child` and
  `hub_school` were registered in `Hub.php`.
- `ParentWorkspace::work()` reads distributed work through
  `Service::assignments( 0, child, school, page )`, which rechecks guardian access,
  school status and current class/school membership; it returns no global history.
- `templates/student-hub/parent.php`: child list with View links; overview with
  child, school, completed/not completed assigned work, pagination (50 per page),
  empty and "not available" states. Not included: measured skills/mastery, global
  history, messaging, assignment creation (deferred by the P0 contract).
- Probe `test-results/parent-p2-probe.php`: 12 checks passed on synthetic
  fixtures: linked child allowed; unlinked child in the same school, wrong school
  scope and another parent denied; only the linked child's work listed; forged
  child URL leaks nothing; revoked link, inactive membership and suspended school
  denied on the next request. Fixtures removed; a count query found no leftover
  schools, classes, links, assignments or test users. PHPCS: 0 errors/warnings on
  the two new/changed P2 files.
- Not tested: browser rendering of the child overview (needs a real parent login,
  which we do not impersonate), mobile layout, and the credential integration suites.
- Next: P3 independent-family model (tables, invitations, child setup) per
  `PARENT-CHILD-P0.md`, only after reconfirming the contract; it introduces schema,
  so start with a migration/rollback plan.

P3 plan (no code yet): `PARENT-CHILD-P3-PLAN.md` in `docs/learner-experience/`, with four open policy decisions before P3a. (That file was
removed on October 19, 2026; its adopted decisions are in `PARENT-CHILD-P0.md`.)

## P3a: independent family schema and service, no UI (October 16, 2026)

Defaults from the P3 plan (now summarised in `PARENT-CHILD-P0.md`) were adopted: no birth date (name and
optional grade only); managed children have no email and sign in through a
parent-issued setup link; existing learners link by private email-matched
invitation; no home aggregates.

- `includes/Family/Schema.php`: option `ohmylms_family_schema` (version 1), tables
  `ohmylms_family_links`, `_invitations`, `_audit` (additive, idempotent
  `dbDelta`, installed on `init` priority 7 by `Family\Bootstrap`, wired in
  `includes/extensions.php`). Rollback: define `OHMYLMS_FAMILY_ENABLED` as false;
  routes/services stop, data and audit rows stay. **The tables were installed on the
  live 800.local database by the probe** (empty afterwards).
- `includes/Family/Service.php`: `guardian_eligible()` (parent role + verified
  email), `children()`/`child()` (fresh read, bounded 100), `create_child()`
  (quota 20, no email, random password nobody sees, active link),
  `issue_setup()` (supersedes older setup tokens), `invite_existing()` (quota 20),
  `accept_link()` (signed-in, email match, verified, not self, issuer re-checked,
  row-locked single use, reactivates the unique pair with revision bump),
  `accept_setup()` (no session, 12-128 password, issuer/link recheck, returns login
  once), `revoke_link()` (guardian or learner), `revoke_invitation()` (issuer).
  Tokens are 64-hex, stored only as SHA-256, valid 48 hours, never audited.
- Probe `test-results/family-p3a-probe.php`: 36 checks passed (idempotent install,
  non-parent denied, other parent isolation, superseded/replayed/expired/malformed
  tokens, issuer role loss, wrong-email/signed-out/self accept, revocation applies on
  next request, reinstatement keeps one row, no raw tokens stored). Fixtures and test
  users removed; counts verified zero. Mail/HTTP suppressed. PHPCS: zero errors and
  warnings on `includes/Family`.
- Not done: concurrent-acceptance race test (relies on `FOR UPDATE` row locks;
  not exercised with parallel requests), REST routes, hub UI, child sign-in screen,
  rate limiting beyond the pending-invitation quota, merging home links into
  `ParentWorkspace::children()`, and a migration test on a fresh database.
- Next: **P3b** "Add a child" UI and setup-link page (native forms + nonces), then
  P3c invitations UI and P3d personalization.

## P3b: add a child and child setup page (October 16, 2026)

- `includes/Student/FamilyActions.php`: nonce-protected `admin-post` operations
  `create_child` (also issues the first setup link), `issue_setup` and `revoke_link`
  (confirmation required), results shown once through a 60-second per-user transient
  (it briefly holds the setup URL, like the teacher invitation flash). Public setup
  handler `ohmylms_family_setup` (nonce, 15 attempts per 15 minutes per client,
  matching passwords, 12-128 characters) shows the login name once in the response and
  never stores it.
- `Hub.php`: `?ohmylms_child_setup=<64 hex>` signed out renders
  `templates/student-hub/child-setup.php`; the token is only a form value and the page
  sends no-store, no-referrer and noindex headers. A signed-in visitor gets the normal
  hub.
- `templates/student-hub/parent.php`: a "Home learning" card (separate from
  school-linked children) with the child list, New setup link, Remove link
  (confirmation) and Add a child form (name, optional grade). No email is sent.
- Verified over real HTTP on http://800.local with a disposable guardian: setup page
  200 with private headers; bad token shows no form; no nonce 403; mismatched
  passwords, success and replay behave; login name shown, no token echoed back
  outside the form. CLI render probe: 10 checks passed (home section, one-time
  setup link, flash consumed, confirmation, unknown operation, other parent denied
  view/issue/revoke). PHPCS: 0 errors on new/changed files. Fixtures removed; family
  tables verified empty and no test users remain.
- Not browser-tested: the signed-in parent page (a real parent login is not
  impersonated) and its mobile layout; child sign-in after setup beyond the service
  checks; the child's own "Connections" view. Parent's school and home sections are
  separate lists, not merged.
- Next: **P3c** invite an existing learner (UI), learner-side accept/decline and
  revoke, then P3d personalization.

## P3c: invite an existing learner (October 16, 2026)

- `Family\Service` gained `invitations()` (guardian's pending list, no hashes),
  `guardians()` (learner's active, still-eligible guardians), `preview( token )`
  (non-consuming, only for the addressed signed-in account) and `decline( token )`.
- `FamilyActions`: parent operations `invite_existing` and `revoke_invitation`;
  learner handler `ohmylms_family_invite` (nonce; accept or decline, token revalidated
  against the signed-in account); `invite_url()`. Results use the existing one-time
  flash. Invitation pages send no-store/no-referrer headers.
- Hub: `?ohmylms_family_invite=<64 hex>`; signed out it shows sign-in and preserves the
  destination; signed in it renders `family-invite.php` (guardian name, Accept/Decline,
  or a non-leaking "not available" page for anyone else).
- UI: parent "Home learning" card gets Invite a child (email), the one-time link, and
  pending invitations with Revoke. Learner Connections (and the no-class state) get
  `family-connections.php` with guardians and a confirmed Remove connection; both
  sides can revoke and the effect applies on the next request.
- Verified: `test-results/family-p3c-probe.php` 19 checks passed (non-parent denied,
  wrong account/signed-out leak nothing, destination preserved, decline/revoke block
  acceptance, learner sees the guardian, unrelated learner sees nothing, removal
  needs confirmation and is immediate for both sides). Earlier probes rerun: P3a 36,
  P2 12, signup 6, teacher 47, all passing. Real HTTP on 800.local: invitation page
  signed-out returns 200, private headers, sign-in with redirect preserved. PHPCS 0
  errors/warnings on the family files. Fixtures removed; family and school tables
  empty. An orphan `account_created` audit row left by the signup probe was removed
  (that probe does not clean its own audit row).
- Mishap noted: `phpcbf` run over a file with a parse error truncated
  `family-connections.php`; it was rewritten and re-linted. Do not run phpcbf on files
  that fail `php -l`.
- Not done: browser test as a real parent/learner (no impersonation); mobile
  layout; the learner accept flow through the real `admin-post` handler (service and
  render are tested, the redirecting handler is not exercised by a test).
- Next: **P3d** managed-child personalization (grade, then real course choices),
  child sign-in verification, then the P3 integration runner and P6 provenance work.

### Parent UI polish (October 16, 2026)

Parent page wrapped in `.oml-hub-family` (max 880px, cards no longer capped at 640px),
flash as a notice card with a monospace one-time link field, child rows with a Home
learning chip and grouped actions (Remove link is red), bordered collapsible forms.
Verified visually from a static snapshot rendered from the real template and
stylesheet (`test-results/parent-workspace-polished.jpg`); stylelint passes, PHPCS clean,
P3c probe still 19/19. Not checked: narrow/mobile widths, dark mode, interactions in
a real parent session. The snapshot helper is `test-results/parent-ui-snapshot.php`
(`cleanup` removes its fixtures and HTML).

### Live parent session test on 800.local (October 16, 2026)

With a disposable parent account (ID 264, created through `Controller::signup`; the
user signed in themself, no other account was logged into) the real browser flows
worked: parent workspace in dark mode, Add a child (one-time setup link; flash gone
on reload), Invite existing learner (one-time invitation link and pending list),
Revoke invitation, Remove link blocked without confirmation and working with it,
Settings in parent context (hidden `hub_role=parent`, password 12-128), role switch
(Learner, Parent), 375px layout with no horizontal overflow. Not tested live: the
learner accepting an invitation in a browser (needs a second signed-in learner) and
school-linked children. Children and family rows created in the test were removed
with `test-results/family-clean-guardian.php`; the parent account was kept for
further tests and must be deleted afterwards (`... 264 delete-account`).
An attempt to grant the real admin account the parent role was reverted
(roles verified as administrator only).

## P3d and completion pass (October 16, 2026)

- **P3d (managed-child choices):** `Family\Service::is_managed()`, `choices()` and
  `set_choices()`; `FamilyActions` operation `update_choices`; "Learning choices" form
  per managed child on the parent page (grade; follow public collections). Following
  uses the existing `Bundles\Follows`, lists the collection on the child's My learning,
  grants no access and enrols nobody; only public catalog collections are accepted
  (drafts and unknown IDs ignored); invited learners are not managed, so a parent
  cannot set their choices; other parents are denied. Probe 14/14. Collections need the
  site to have published bundles: 800.local has none, so the live test used a
  disposable bundle and course that were removed.
- **Live test as the disposable parent (user signed in):** Add child, grade 4 and
  collection save ("Learning choices saved.", values persisted on reload).
- **Race test** (`test-results/family-race.php`): four parallel PHP processes accepted
  the same invitation and setup token; exactly one succeeded for each, one link row, one
  audit entry for each. Row locks hold.
- **Regression:** P3a 36, P3c 19, P3d 14, P2 12, P1 17, signup 6, teacher 47 all pass.
  PHPCS: 0 errors on the family/parent files (5 warnings in `signin.php`); stylelint and
  syntax checks pass. Tables empty; no fixture bundles, courses or children remain.
  The test parent account (ID 264) and its credentials file were deleted afterwards (verified: no test users, family tables empty, admin roles unchanged).
- **Remaining, deliberately not done:** learner accepting an invitation in a browser
  (needs a second signed-in learner account; service, render and race are tested);
  school-linked children live (probes only); family REST routes (UI uses admin-post
  forms only); fresh-database migration run; home-learning reports (P6, needs activity
  provenance); unobserved reference screens (parent dashboard, recovery, personalization
  step two) remain proposed designs; teacher integration runner and the wider class panel;
  repository-wide standards debt; retirement of remaining legacy pages.

### Follow-up polish (October 16, 2026)

- Selected teacher class panel widened to 880px (`.oml-hub-class-panel`), forms kept at
  640px. Verified live on 800.local (dark mode) after the user signed in: the class settings
  panel is wide, fields stay readable, the selected tab shows `aria-current`; the flash
  and create cards were widened to match. The disposable class was removed, and the test
  teacher account created for this check (ID 310) and its file were deleted.
- Signed-out chooser verified live on 800.local (parent tab shows the sign-up form).
  Found and fixed low-contrast "Sign in to continue" text in dark mode
  (`a.oml-hub-button` inside `.oml-hub-signin`).
- Correction: the test parent account note above is superseded; it was deleted.

### Final verification snapshot (October 16, 2026)

`npm test` (asset parity plus 415 JS tests): 414 pass, 0 fail, 1 skipped. PHP syntax
clean on every changed/new file. All fixtures and disposable accounts (parent, teacher,
learner) were deleted; family and school tables are empty. Still open: browser round-trip of a
learner accepting a parent invitation, the unrun credential-protected teacher
integration runner, legacy-page retirement, home-learning reports, and the unobserved
reference screens.

Legacy retirement inventory and proposal (the original text of `docs/learner-experience/LEGACY-RETIREMENT-PLAN.md`; that file now records the outcome instead) (no changes made; first proposed steps are parent/student portal redirects and deleting two trashed test pages).

### Legacy retire-now steps done (October 16, 2026)

Portal `parent-dashboard` and `student-assignments` now redirect eligible signed-in users to
the parent workspace and learner Classes (`LegacyPages::redirect_portal`); test pages 313
and 314 were permanently deleted (page 312, another test fixture, remains in Trash);
provisioning already respected the ledger (verified, no change). Details and limits in
`docs/learner-experience/LEGACY-RETIREMENT-PLAN.md`. Probe: 12 checks; earlier probes
rerun and passing.

### Admin parent role (October 16, 2026)

At the user's request the real `admin` account was given the `ohmylms_parent` role in
addition to `administrator` (so the parent workspace and family tools can be used live).
To undo: `get_user_by('login','admin')->remove_role('ohmylms_parent')`. Any children
or links created as admin live in the family tables and are removable with
`test-results/family-clean-guardian.php <admin id>` (without `delete-account`).

### Live parent test as admin (October 16, 2026)

With the admin account holding the parent role, in the signed-in browser on 800.local:
parent workspace and role switch (Learner/Teacher/Parent), Add a child, Learning choices
(grade and a disposable collection), Invite existing learner, Revoke invitation, Remove link
(blocked without the confirmation box, works with it) all passed. The portal
`view=parent-dashboard` now redirects to the family workspace, and with `ohmylms_invite` it
stays on the portal. The child, links, invitation rows, disposable bundle and course were
removed (family tables, bundles, follows empty). Admin keeps the `ohmylms_parent` role.

## School activation in the hub; invitation links repointed (October 16, 2026)

- `Schools\Service::invite_role( token )` reports the role of a still-valid token without
  consuming it. `Student\SchoolActivation` (admin-post `ohmylms_school_activate`, nonce, 15
  attempts per 15 minutes, matching 12-128 character passwords) calls the existing
  `Service::accept()` only for `activation` tokens and shows the sign-in name once.
  `templates/student-hub/school-activation.php` is the signed-out page; the hub renders it
  for `?ohmylms_invite=<token>` when the token is a valid activation invitation. Invitation
  pages send no-store, no-referrer and noindex headers.
- Signed-in users who open a non-activation invitation link directly now land on the Join a
  class view with the token filled (when the Classes tab is enabled); signed-out visitors
  still go to sign-in with the destination preserved.
- `Hub::invitation_url()`: new school invitation links point at the hub (activation always;
  other roles when the Classes tab is on), falling back to the portal otherwise. The portal
  route and the React portal app are untouched, so links already sent keep working.
- Verified on 800.local over real HTTP with a disposable school and managed student:
  activation page 200 with private headers and a password form; student-role and bogus
  tokens show no form; no nonce 403; mismatch, short password and wrong-role token
  rejected; success sets the password (confirmed) and consumes the invitation; replay
  rejected without changing the password; the old portal link still returns 200. Render
  probe 2 checks; earlier probes (P1, P2, P3c, redirects, teacher) still pass. PHPCS 0 errors;
  school fixtures removed.
- Not done: the credential-protected `tests/php/schools-integration.php` was not run; the
  invited non-activation roles (teacher, school admin, guardian) were not exercised end to
  end through the hub join view in a browser.

### Live school invitation round trip as admin (October 16, 2026)

Disposable school, class, student and two assignments; a guardian invitation addressed to
the admin's email was opened through a redirect file (token never typed in a command).
- **Bug found and fixed:** the remembered-location script in `assets/js/student-hub.js`
  rewrote the invitation URL to the saved section (My learning), so direct invitation
  links never reached the Join view. It now skips restoring when `ohmylms_invite` is present.
  ESLint passes; `npm test` (asset parity + 415 JS tests) rerun: 414 pass, 0 fail, 1 skipped.
- Verified live in the browser: the invitation opens the hub Join view with the token
  filled; Accept works ("Invitation accepted..."); the parent workspace lists the
  school-linked child; the child overview shows both assignments (1 completed, 1 not);
  a forged school scope shows "This learner is not available". The overview card now uses
  the wide panel width.
- Cleanup: fixture school, class, assignments, link, invitation, audit rows and the student
  were removed; the admin's `_ohmylms_email_verified` meta (which acceptance sets) was
  restored to its prior state; admin roles unchanged.

### Teacher and school-admin invitations through the hub (October 16, 2026)

Live on 800.local as admin with a disposable school: the hub Join view accepted a teacher
invitation (school and class memberships plus `ohmylms_teacher`) and a school-admin
invitation (membership plus `ohmylms_school_admin`). The teacher workspace then listed the
school's class, and the Create a class form offered the administered school and year. Both
invitations were marked consumed. Cleanup removed the school, class, memberships and
invitations, removed the two added WordPress roles, and restored the email-verified meta;
admin is back to administrator plus parent. Fixed: the success message "Open My
classes to see your class" is shown for every role; wording could be role-aware. The
credential-protected schools integration suite was still not run.

Join-view success text is now role-neutral ("Invitation accepted. Open My classes, or use Switch
role at the top to open your new workspace."); the class-specific API message is unchanged.

## No portal (October 16, 2026)

Per the user: no front-end LMS portal. Self-registration for learner, teacher and parent is in
the hub chooser; old portal and registration entry points forward to the hub or wp-admin; View-as
previews land in hub workspaces. Details and limits: `docs/learner-experience/LEGACY-RETIREMENT-PLAN.md`.
Verified over HTTP on 800.local (chooser role fields, student/teacher/parent signups created the
right roles and a forged admin role became parent, portal URLs 302 to the hub, invitation tokens
preserved) and in the browser (portal URL as admin opens the wp-admin Account Hub). The signups
and their audit rows were removed. Not committed. `tests/php/view-as-unit.php` passes; the
credential-protected suites were not run.

## School workspace and teacher-created schools (October 16, 2026)

**Khan Academy reference (public Help Center article "How do I add my school to my teacher
dashboard?"):** a teacher account adds one school from a button on the Teacher Dashboard
(country, ZIP, pick from a public database; "Other" fallback; optional; one school per
account; changeable). We do not have a public school database and a teacher must not join an
existing private school freely, so the adaptation is: a teacher **creates their school**
from the Teacher workspace ("Create your school": name and time zone), becomes its
administrator and teacher, and invites others. One school per teacher account. Not observed:
the signed-in Khan teacher dashboard itself (only the help article).

- `Schools\Service::create_school()` now allows teachers (previously platform admins only):
  creator gets active `school_admin` and `teacher` memberships and the `ohmylms_school_admin`
  role; one school per non-platform user; slugs are made unique. Platform behavior unchanged.
- `Student\SchoolWorkspace` (availability = administers an active school; platform admins see
  all active schools), `Student\SchoolActions` (`ohmylms_school_admin` admin-post: create
  school, academic year, class, archive class (confirm), add student, invite, revoke invite,
  remove parent link (confirm)), `templates/student-hub/school.php` (Overview, Classes,
  People, Invitations), `teacher-school.php` (Create your school / Open school workspace).
  Hub mode `hub_role=school`, role switch entry "School", Settings keeps the context.
  All reads/writes go through the existing school services, which re-authorize every call.
- Select fields got dark-mode styling (`.oml-hub-field select`).
- Verified: probe `test-results/school-workspace-probe.php` 28/28 (non-teacher denied, one
  school per teacher, creator roles, distinct slugs, other teacher denied, year/class/student/
  invite/archive flows, forged school id shows nothing, hub-pointing activation link); live as
  admin in the browser: create school, year, class, student, activation invitation (hub link),
  pending list. Earlier probes still pass. PHPCS 0 errors/warnings on the new files.
- Not done: school settings (name/time zone edit), teacher invitations accepted into the
  workspace UI beyond the existing flow, roster import, gradebook/reports, rollover, managed
  student password reset, co-admin removal, "leave school", and a browser test as a real
  non-admin teacher (platform admins were used). The wp-admin Account Hub remains for platform
  administration. Not committed.

### School settings and member removal (October 16, 2026)

`Service::update_school()` (name and time zone, school admin or platform, audited), plus
`SchoolActions` operations `update_school` and `remove_member` (confirmation required; the
existing service refuses self-removal, deactivates class memberships and parent links, keeps the
account). New "School settings" tab in the School workspace and a Remove control per person.
Probe 35/35; live as admin: rename plus time zone saved, a student was removed with the account
kept. Test school removed. Still not done: roster import, reports/gradebook, rollover,
student password reset, leaving a school, and a real non-admin teacher browser session. Not committed.

### Real non-admin teacher session (October 16, 2026)

With a disposable teacher account (role `ohmylms_teacher`, signed in by the user, no admin bar):
"Create your school" appeared; creating a school opened the School workspace with 1 teacher and
1 administrator; academic year and class creation worked; the teacher workspace then listed the
school class and the role switch offered Learner, Teacher and School.
**Two bugs found and fixed:** (1) portal and direct `wp-admin/admin.php?page=ohmylms-schools`
URLs dropped non-administrators into the WordPress dashboard; they now go to the School workspace
(`LegacyPages::admin_target`, `Views::redirect_management`); only platform administrators are
sent to the Account Hub. (2) The forwarded `school` id was replaced by a presence flag (`1`);
the real id is now kept. Probes still pass (school workspace 35, redirects 12, chooser 17).
The school fixture and the added `ohmylms_school_admin` role were removed; the test teacher
account (ID 404) and its credentials file were deleted afterwards (verified).
Not tested live: a second account accepting a school teacher invitation as a non-admin.

### School roster import (October 16, 2026)

People page in the School workspace: paste a CSV (`name`, `external_student_id`, up to 200 rows), see
a preview (new, existing, error per row; nothing imported), then "Import the new students". It uses
`Service::import_roster()` unchanged: a commit needs a preview of the exact same CSV (a changed CSV
is rejected), existing school IDs are skipped, and repeated IDs inside the file are errors.
`SchoolActions` operations `import_preview` and `import_commit` (the result passes through the
one-time flash, now kept for five minutes). Probe `test-results/school-workspace-probe.php`: 43
checks passed (commit without preview rejected, classification, nothing imported by preview, render,
tampered CSV rejected, two students created, other teacher denied). PHPCS clean. **Not tested in a
browser** (the live test was skipped at the user's request; the browser session was signed out).
The disposable test school was removed.

### School reports and submission gradebook (October 16, 2026)

- New School workspace destinations: Reports and Gradebook. Reports paginate classes in groups
  of 20 and show completion percentage, completed/assigned counts and overdue work for current
  school and class students. Class drill-down shows distributed work (50 rows per page), due
  dates in the school's time zone, and completed/not-completed/overdue states.
- Skills shows existing stored summaries for current class students (50 roster entries per
  page). It reads without processing/rebuilding evidence. The UI explicitly describes these
  as overall learning levels, not school-attributed activity or grades. Disabled skills and
  learners without evidence have empty states. Home-learning provenance/report work stays open.
- Gradebook reviews distributed assignment submissions, 100 candidates per page, with a
  lookahead so filtered pre-distribution attempts cannot hide later pages. Each attempt has
  its own score, answer, protected attachment link and feedback form. Saving uses the existing
  `Service::grade()` assessment/completion pipeline. This slice does not introduce course-wide
  quiz matrices, manual grade overrides or exports.
- `SchoolReports` checks the active school administrator relationship and exact class/school
  match for every entry. POST grading uses an operation-specific nonce, school/class checks,
  recipient and attempt checks, and whole-number score validation. Archived classes retain
  assigned-work reports but grading is closed. Removed students disappear from current reports.
  Sidebar navigation now preserves the selected school. No schema change.
- Verification: **32 fixture checks passed**, including non-platform school administrators,
  cross-school denial, student denial, removed membership, invalid/decimal scores, pre-assignment
  attempts, passing/failing assessment status, completion propagation, archived grading denial,
  21 classes and over 100 submissions with non-overlapping pagination. Reusable checks are in
  `tests/php/support/school-report-checks.php`; the new credential-protected entry point is
  `tests/php/school-reports-integration.php`. The checks ran against disposable fixtures on the
  local site; the protected disposable-database runner and older full suites were not run.
- Built-in browser on 800.local: real Reports/Gradebook navigation, assigned-work due date,
  85/100 grade and feedback persisted as passed, work changed to Completed, skills empty state,
  dark theme and 390px mobile layout (no horizontal overflow). Browser grading used the existing
  platform-admin session; non-platform authorization was verified through the PHP fixture checks.
  Two browser fixture schools, three accounts and their course/assignment records were removed;
  fixture school/account absence was verified. Existing school data was preserved.
- `npm test`: asset parity plus 415 tests, 414 passed / 0 failed / 1 skipped. PHP syntax passed.
  PHPCS clean on the new runtime class/template and modified school action/workspace/templates;
  student-hub CSS passes Stylelint. Existing Hub/Service standards debt remains; test fixtures
  have direct-database cleanup warnings and the protected runner has a local file-read warning.
  Changes are uncommitted.

### Managed recovery, departure, rollover and the course matrix (October 16, 2026)

Delivered sequentially after the submission-report slice:

1. **Managed-student recovery:** People offers a confirmed "Create password reset link" action
   for ordinary accounts owned by the selected school. It replaces unused activation links,
   shows the new private link once for 48 hours, and leaves the current password unchanged until
   the student chooses another. Creation and acceptance both reject cross-school, removed or
   promoted staff accounts. Recovery and activation acceptance lock the user before invitations
   in the same order. Acceptance verifies that the new password was actually saved.
2. **Leaving a school:** Settings → Connections lists the signed-in person's actual school
   memberships and a confirmed Leave school action. It deactivates school/class access and
   associated guardian links, revokes invitations issued by that member, and retains the account,
   enrollments and learning records. School-managed students contact their administrator.
   The last active administrator cannot leave or be removed. Voluntary departure and removal
   share a school lock and recheck permissions after locking. Global WordPress roles are retained.
3. **Academic-year rollover:** Classes offers a five-minute, per-user preview. Confirmation copies
   class names/subjects/grades into the destination with empty rosters and archives the source
   year/classes. No assignments, enrollments or students are copied. The source class list and
   both year records are fingerprinted and checked under locks; changed, expired, forged or
   repeated previews fail. Both years are locked in ID order. Class creation rechecks its year
   under lock so it cannot finish inserting into a year that rollover just archived.
4. **Browser verification:** CSV import preview correctly classified one new and one existing
   student, and commit created exactly one. A disposable teacher with no platform-administrator
   permissions accepted a school/class invitation, saw the class, then used Connections to leave.
   The browser showed the departure success message and "No classes to show" afterwards. Rollover
   preview listed the expected source class without mutation.
5. **Course matrix and CSV:** Gradebook defaults to a paginated, read-only course matrix (50 current
   class students per page). A separate Review submissions view retains scoring and feedback.
   Only distributed activities enter columns; each student's cell needs their own activity/course
   distribution and permitted attempt date (or explicitly allowed prior completion). Whole-course
   distributions include the course's assessments. Latest graded attempts survive pending retakes,
   zero is a real grade, and quiz maxima use stored attempt-answer marks. Student totals are weighted
   earned/possible points over graded work. Missing/pending/unassigned cells are excluded. Platform
   manual overrides remain separate. The POST/nonce-protected full-class CSV streams all roster
   pages, reauthorizes each, and neutralizes spreadsheet formula prefixes in untrusted text.

New services/templates: `Schools/Lifecycle.php`, `Student/SchoolGradebook.php`,
`templates/student-hub/school-rollover.php`, `templates/student-hub/school-gradebook.php`.
No schema migration or changes to purchase/enrollment policy.

Verification: **77 reusable PHP checks passed** (32 report, 28 lifecycle, 17 matrix), including
21-class/100-plus-submission and 51-student pagination, exact scope, archived years/classes,
replacement/replay/promotion recovery cases, stale rollover previews, departure, quiz maxima,
pending retakes, zeroes, weighted totals and CSV text protection. The guarded
`tests/php/school-reports-integration.php` entry point runs all three support check sets, but was
not itself run against the credential-protected disposable database. The same support checks ran
with removable local fixtures. `npm test`: 415 total, 414 pass, 0 fail, 1 skipped, asset parity passes.
New runtime files and modified templates/actions pass PHPCS; student-hub CSS passes Stylelint;
PHP syntax checks pass. Existing Service/Hub standards debt remains, and fixture files retain
direct-database/local-file diagnostic warnings. The three variable-length IN-list queries have
narrow documented PHPCS exceptions because the static sniff cannot count dynamic variadic values;
each placeholder and argument list is built from the same bounded student-ID array.

Real HTTP CSV checks passed for the expected 85/100 and 7/10 grades and 83.64% weighted total,
CSV download headers, invalid nonce rejection, and cross-school export denial.
Final built-in browser verification also passed: the quiz/assignment matrix displayed 85/100 and
7/10 with the correct 92/110 (83.64%) total; "Download full class CSV" produced a real downloaded
file with matching values. Rollover confirmation archived the source class and displayed its active
replacement. The managed-student password recovery action displayed the expected private-link
success message without changing a password through the browser. A 390px check found a grid
minimum-width issue; setting the report card's minimum width to zero fixed it (page width 375px
inside a 390px viewport; table scrolls inside its 293px region). Dark-theme verification passed
and the viewport override was reset. A third disposable school was created through the teacher UI
for this final test; the user returned to the real administrator session before the matrix,
download and rollover checks. No extra sign-in is needed. Changes remain uncommitted.

Cleanup completed and verified: all three browser fixture schools and their student/staff accounts,
class memberships, assignments/attempts, quiz answers/questions, course/chapter records and audit
rows were removed. The roster-imported student was included. The private credentials file,
invitation redirect file and temporary executable probes were removed. Screenshots and the sample
CSV remain as ignored verification artifacts. The real administrator session and existing school
data were preserved.

## Review and protected-test checkpoint (October 16, 2026)

Reviewed and committed the school reports, lifecycle and gradebook implementation locally.
Two review fixes are included: activation candidate lookup precedes the transaction so a
concurrent student-membership revocation cannot be missed through an old repeatable-read
snapshot; streamed CSV columns and totals remain aligned with the original header even when
assignments change between pages. Regression tests exercise both cases.

Protected disposable-database verification passed: school reports **82 checks** (32 report,
31 lifecycle, 19 matrix), schools integration, teacher classes **47**, student hub **72**, and
gradebook integration. The legacy schools test now checks the agreed one-school-per-teacher
contract and confirms school creation does not grant platform administration. The isolated
configuration explicitly declares its local environment; production configuration is unchanged.
All database guards remained enabled, and outbound HTTP/email are blocked on that test site.

Final asset parity and JS tests passed: **414 pass, 0 fail, 1 skipped**. Targeted runtime PHP
standards and student-hub CSS checks passed; fixture cleanup/local-file warnings remain.
The separate mysqli connection in the race regression has a narrow documented exception
because the test requires an independently committed transaction. Existing Service/Hub
standards debt remains. The local PHP installation emits an existing missing-Imagick startup
warning; it did not prevent the suites passing. No push was performed.

## Frontend portal retirement checkpoint (October 16, 2026)

Removed the dormant React signup, invitation, learner-work, parent/child and registration-block
screens and their frontend mounting paths. The remaining school React entry only mounts
`admin-management` in wp-admin for platform administrators. The native Account Hub and its
school/class editors remain available; directory Manage actions now link directly to that editor
and retain the selected class. New school links point directly to the hub.

Legacy portal URLs forward to the corresponding hub workspace, including the selected class,
for signed-out users and View-as sessions too. Legacy blocks/shortcodes now render hub links;
the editor preview describes that behavior. The old standalone HTML document, login renderer,
asset-pruning hooks and portal/account-only CSS are removed. The file-download endpoint retains
nonce, resource-scope and upload-path checks. The obsolete portal rejects non-GET screen requests
with 405, and reports an unavailable hub with 503 rather than resurrecting the old app. Public
registration REST endpoints remain because the hub uses them. No schema or site data changes.

Verification: protected Student Home integration **88 checks**, legacy account blocks **44**,
and standalone View-as scope/session checks passed. Final asset parity and JS checks: **414 pass,
0 fail, 1 skipped**. Full authored-source rebuild and final SDK rebuild passed; generated admin
chunks/maps were refreshed by the build. Existing math-runtime bundle-size warnings remain.
Changed Views/LegacyPages/TeacherActions PHP files pass PHPCS, changed PHP syntax checks pass,
and touched feature JSX passes Prettier. Existing ESLint debt remains: school entry improved
from 28 to 18 errors (one hook warning); PeopleDirectory stays at 17 errors/one warning;
ThemeSettings stays at one error/two warnings. No blanket lint suppression was added.

Built-in browser verification passed for the retired teacher URL, selected-class preservation,
legacy school URL to platform editor, and native Account Hub student directory. The retired parent
URL correctly reaches the parent hub, which rejects the current account because it has no parent
access. An existing JetPlugins frontend error appeared in browser logs; it did not block these
routes. The current signed-in administrator session and real data were preserved. Browser proof
is ignored at `test-results/portal-retirement-hub.png`. Next implementation slice: activity
provenance before independent home-learning reports.

## Home-learning provenance and report checkpoint (October 16, 2026)

Family schema version 2 adds an immutable origin record per new activity source. The learner
explicitly selects Home learning or a currently received school distribution on the existing
player/practice page. Choices are user/session/content-bound WordPress nonces; page queries or
same-origin referrers carry them to verified creation hooks. Source ownership is read from the
persisted source row. Missing/invalid context stays unknown. There is no backfill or enrollment-based
inference. Quiz/practice origins freeze at creation; new assignment submissions and verified lesson
completions record once. Resumes/reviews cannot change the original scope. View-as previews cannot
assert provenance. No passwords, raw context nonces or answer bodies enter the new activity table.

Parents can open View home-learning report for an approved home child. Each summary/page query
rechecks the exact active link, and only home-origin completions created within the current link
approval period count. The report provides 7/30/90-day windows, activity counts, active UTC dates,
dated titles and 20-row pagination. It does not expose school work, old unknown history, scores or
global mastery. The school workspace/report pipeline is unchanged. Parent availability now supports
family-only sites. See docs/learner-experience/HOME-LEARNING-REPORTS.md for the contract and exclusions.

Protected verification: **33 family provenance/report checks**, including all four source types,
forged/cross-user/cross-content contexts, actual assignment submission capture, same-origin nonce
transport, replay/resume immutability, unknown older attempts, recipient revocation, cross-child and
non-guardian denial, link revocation/reapproval, periods/pagination, email-verification policy,
additive/idempotent migration, no-school operation and rollback with retained data. Student Home
**88** and school report/lifecycle/matrix **82** checks passed. Asset parity and JS: **414 pass,
0 fail, 1 skipped**. New runtime files, modified templates and parent workspace/context PHP pass
PHPCS; both changed stylesheets pass Stylelint; PHP syntax passes. The new guarded fixture runner
retains direct-database/local-file warnings; older runtime standards debt is unchanged.

Built-in browser checks showed the populated report as a disposable parent through View-as,
verified dark mode and a 390px responsive viewport (375px document width), and selected Home learning
on a disposable lesson before starting work. The original administrator session was restored.
Fixture users, posts, relationships, enrollment, submissions, activity, links and preview audits
were removed; count checks returned zero. No password entry or real-account role changes occurred.
Screenshots remain ignored at test-results/home-learning-report.png, home-learning-report-mobile.png
and learning-context-choice.png. Temporary CLI helpers outside the web root were removed.

The next checkpoint implements recovery and grade-then-collection refinements using the hub design.
Neither the report nor these refinements are claimed as an observed reference replica.

## Managed-family flow refinement checkpoint (October 16, 2026)

Recovery now explains the private 48-hour link, replacement of old unused links, unchanged password
until consumption and no email delivery. A required replacement checkbox is checked server-side too.
Managed accounts promoted to staff/parent or granted staff capabilities cannot be recovered or have
choices changed through an old family link; old setup links also reject those accounts. Replacement
and consumption use the same account-first lock order, with authorization refreshed under the lock.

Learning choices is a progressively enhanced grade-then-collections form. Next/Back preserve edits;
Cancel, Escape and closing the editor discard them. Save persists grade and followed collections.
Collection cards show public course previews and explain that following grants no enrollment/access.
Without JavaScript both sections and Save remain usable. No schema or asset build change is required.

Verification: 56 protected family checks (33 provenance/report plus 23 management), 88 Student Home
checks, asset parity and JS tests (414 pass, 0 fail, 1 skipped). Modified runtime PHP passes PHPCS;
the new fixture helper has six database warnings and the existing guarded runner retains its warnings.
Changed PHP syntax, ESLint and Stylelint pass. Existing missing-Imagick startup warning remains.

Built-in browser checks passed for Next/Back, Cancel reset, saved grade/collection persistence,
required recovery confirmation and private link creation. Dark mobile layout fits a 390px viewport
(375px document width). No browser password change or real-account role change occurred. Returned
to admin and the original teacher page, reset the viewport, removed disposable users/course/collection,
links/invitations and preview audits; remaining fixture users/links count zero. The temporary helper
outside the web root was deleted. Proof stays ignored at test-results/family-learning-choices.png,
family-recovery.png and family-choices-mobile.png. This checkpoint is committed locally, not pushed.

Remaining product work: observed reference comparison for proposed parent dashboard layouts, and
any richer home-only accuracy/skill reports, which require source-scoped evidence rather than global
mastery or unknown historical results. Those are separate slices from the completed family flow.

## Unit and practice presentation checkpoint (October 16, 2026)

P5 groups real unit activities into Learn, Practice and Assessments, shows the existing five-band
skill mastery legend, and opens the selected unit when returning through its anchor. Program lesson
and authorized standalone course-practice routes now share the focused activity document and course
outline. The document retains WordPress head/body/footer integrations. Navigation can collapse, starts
collapsed on mobile, supports keyboard toggling and Escape, and distinguishes skill term IDs from
content post IDs when highlighting the current activity. Active formal quizzes keep their existing
exit/attempt behavior and extension renderers.

Practice entry counts the same authorized pool used by Sessions (finite unique questions, repeatable
templates, 3–30 configured limit). The entry says "up to"; the live progress label identifies the session
limit because availability can change and a finite pool may finish early. Sticky controls retain the
existing Check/hint/Next actions and server feedback; duplicate submits are ignored while a request is
in flight. Focus moves to the question progress, Next, and completion summary. Existing session storage
keeps the course/skill resume context. Unsubmitted inputs are not saved. No engine, scoring, completion,
published-version or assessment-contract changes, no new schema or generated-asset build. Existing
learning-path award evaluation remains unchanged. Program pages now receive private context headers too.

Verification: 76 protected learning integration checks (13 new presentation checks), six pool-count
unit checks, 88 Student Home, 81 player-template and 56 family checks. Asset parity and JS: 417 pass,
0 fail, 1 skipped; the three new runner tests cover passive entry, single-flight checking, error retry,
server-driven advancement/summary focus and GET-only resume. PHP syntax, changed authored JS ESLint,
and all three changed stylesheets pass. New/modified player, practice presentation and template PHP
pass PHPCS; Practice/Frontend has six existing read-only query nonce warnings. Legacy Learning/Frontend
retains 34 existing standards errors (no new errors); the older integration fixture has existing debt.
The local PHP installation still emits its missing-Imagick startup warning.

Built-in browser: real disposable course/program/learner and five approved questions. Verified grouped
unit navigation, lesson entry without marking completion, current practice highlighting, correct and
incorrect feedback, a real hint, Enter for Next, exit to an expanded unit, resume at question two after
visiting the lesson, mobile default collapse and keyboard Escape, and a final 4/5 summary after pool
exhaustion. Dark 390px layout has a 375px document width. View-as returned to admin; the viewport was
reset and original teacher page restored. Fixture learner, course, lesson, quiz, questions, skills,
enrollment, programs, practice/evidence/score/streak rows and preview audit were removed. Remaining
fixture user/session counts were zero; the outside-web-root helper was deleted. Browser proof is ignored
at test-results/p5-unit-overview.png, p5-practice-desktop.png, p5-practice-mobile.png and
p5-practice-complete.png. Committed locally, not pushed. These are hub-style refinements; fresh visual
reference parity and final release acceptance remain separate work.

## Release verification checkpoint (October 16, 2026)

Run on the disposable site (`ohmylms_source_test`, served at 127.0.0.1:8099 for the browser pane), never the real 800.local data.

**Passed.** Protected suites: Student Home 88, teacher classes 47, schools (all areas), school reports 32/lifecycle 31/matrix 19, family provenance/report/management 56, learning 76, player templates 81, account blocks 44, gradebook, student directory. `npm test`: 417 pass, 0 fail, 1 skipped. Two suites (gradebook, student directory) need the site path as an argument and `WP_ENVIRONMENT_TYPE=local`; without them they print usage or die silently (debug.log shows the guard).

**Cross-role browser pass (built-in browser, disposable teacher, school admin, two parents, learner, empty learner).** Rendered HTML of every reachable section per role: status 200, no PHP notices, one main landmark, no unlabelled controls, no unnamed links/buttons, no images without alt. Layout at 375px: no horizontal overflow on any learner, parent, teacher or school page checked (parent 5, learner 8, teacher/school 7 pages). Access: a second parent could not open another parent's home or school child (clear "not available" page); a parent could not open the teacher or school workspaces; empty states render on every learner section and on the parent and school-child views.

**Migration and rollback rehearsal.** Full mysqldump, simulated an older state (family schema marker 1, hub settings version 3), reran install and upgrade: schema back to 2, hub settings back to 4, no table lost, no row count changed, second run identical. `OHMYLMS_FAMILY_ENABLED=false`: family service reports no children, parent workspace still renders, link rows retained. Restored the dump: database checksums identical to the pre-migration snapshot. Not rehearsed: a v1 database that truly lacks the `activity` table (the table held rows, so it was not dropped), a production-size database, the school schema upgrade path.

**Remaining issues, in suggested order**
1. **Translations (release blocker if non-English is shipped).** `languages/ohmylms.pot` is dated Oct 3. 444 of 512 unique strings in `templates/student-hub`, 107 of 127 in `includes/Student`, all 23 in `includes/Family` and 10 of 21 in `includes/Schools` are missing from it, so es_ES, nl_NL and bn_BD show English for the hub. No Mongolian locale ships. No xgettext or wp-cli is installed here, so the catalog was not regenerated.
2. Every hub section has the same `<title>` ("Student Home – math"), so screen-reader users get no page context (WCAG 2.4.2).
3. Parent school-child view has no h1 (child name is an h2).
4. Duplicate `id="_wpnonce"` on pages with several forms (invalid HTML; no functional break seen).
5. School People: remove/reset checkboxes render at 13x13px and the search box is 21px high on mobile (WCAG 2.5.8 target size).
6. `<main>` wraps the whole shell including the left rail, so landmark navigation is imprecise.
7. Not covered: real screen-reader and keyboard-only passes, colour contrast measurement, dark theme on every page, large rosters/catalogs, a real cross-school/home parent with children in both on the same account, revoked-link flow in the browser (covered by the 56 protected checks only), outbound email.
8. Separate feature work, unchanged: parent-dashboard reference comparison and richer home-learning reports.

Cleanup: fixture users, school, class, family links and the temporary scripts were removed (count queries return zero); the family `activity`, `audit` and `invitations` tables on the disposable database were also emptied. The test server was stopped.

## Khan activity study and runner parity checkpoint (October 16, 2026)

Studied khanacademy.org (signed out) with the built-in browser: exercises, Quiz, Unit test, Course challenge link, unit page, and the
official mastery rules from the Help Center. Findings, the gap matrix and the five proposed slices are in
`docs/learner-experience/KHAN-ACTIVITIES.md`. Owner choices: build runner parity first, and add the Khan level rules later as an
option beside the current evidence and SmartScore model (not a replacement).

Slice 1 built (standard practice runner only; no scoring, schema or mastery change): progress dots, Skip, stepped hints (`<hr>` between
steps), tiered correct feedback, Start over. Details, limits and what was deliberately not built (retry after a wrong answer) are in
section 7 of that doc. Checks: JS practice tests 6 pass, assessment phases skip/lesson/xp/evidence 320 checks, learning 76, player
templates 81, Student Home 88, browser check on the disposable site. PHPCS was not available in this environment, so it is outstanding for
`Sessions.php`, `Frontend.php` and `PracticeController.php`. Uncommitted: the working tree also holds the separate translation/accessibility
session's edits, so commit by path.

Next: slice 2 (Khan level rules as an option, including retry after a wrong answer), then slice 3 (mixed-skill Quiz and Unit test).

## Release verification fixes (October 16, 2026)

Fixes for items 1-5 of the release verification checkpoint above; items 6-8 are unchanged. Nothing committed.

1. **Translation catalog.** `languages/ohmylms.pot` was regenerated with the WP-CLI 2.12 phar that ships with Local (`i18n make-pot`, run through Local's PHP with mbstring; about 7 minutes). Command: `sh C:/Users/Byambaa/ohmylms-test/php.sh -d memory_limit=3072M "<Local>/resources/extraResources/bin/wp-cli/wp-cli.phar" i18n make-pot . languages/ohmylms.pot --slug=ohmylms --domain=ohmylms --skip-audit`. It now has 9,699 msgids (was 7,081) and covers `templates/student-hub` (667 references), `includes/Student`, `includes/Family` and `includes/Schools`. Strings that no longer exist in code dropped out of the catalog. Wording is untouched, so "Course bundle" stays. The es_ES, nl_NL and bn_BD `.po` files were merged append-only: the existing files are kept as they were and the new msgids were added untranslated (29 more were added across two later runs for the Practice runner, Khan settings and mastery-model strings). Translated entries before and after: es_ES 4,910/4,910, nl_NL 4,781/4,781, bn_BD 7/7, none lost or changed. `wp i18n update-po` was not used because it deletes translations whose source string is gone (222 in es_ES, 154 in nl_NL). The `.mo` and `.json` files were not rebuilt: the added entries are untranslated, so their content would not change. All three `.po` files compile with `i18n make-mo` (checked in a scratch folder).
2. **Document titles.** `Hub::document_title_parts()` gives each screen its own title, most specific first, for example "Join a class – My classes – Student Home – site", "Roster – Teacher workspace – site", "Sign in – site". To keep the title and the page body on the same logic, the section resolution in `Hub::shortcode()` moved unchanged into a private `Hub::state()`. Titles never include a child's name.
3. **Parent school-child view.** The child's name is now the h1 in `templates/student-hub/parent.php`, and so is the "This learner is not available" heading. A `.oml-hub-class-panel > h1` rule keeps the h2 look.
4. **Unique nonce ids.** `Hub::nonce_field()` prints the same `_wpnonce` field with an id of `oml-nonce-N`. It is used by the family, school and teacher action forms, the settings cards, the gradebook export and the parent sign-in. The field name is unchanged, so every handler verifies as before. The single-form pre-sign-in pages and the admin editor still use `wp_nonce_field()`.
5. **Target sizes.** In `student-hub.css`, the remove/reset confirmation checkboxes are 24x24px and the People search box and button are 40px high (measured at 375px in the built-in browser on a static page using the real stylesheet). The People form now has the class `oml-hub-people-search`.

Checks: `student-hub-integration` 88; schools, school reports 32/31/19, family 56, learning 76, player templates 81, account blocks 44, teacher classes 47, gradebook and student directory pass; `npm test` 420 pass, 0 fail, 1 skipped (the earlier 417 plus tests another session added). A render probe on the disposable site showed a distinct title and one h1 for each learner section, account view and teacher page, and no duplicate nonce ids. Stylelint is clean on `student-hub.css`; PHPCS has no errors on the touched PHP (its remaining warnings were there before). Not run: the parent-home and school-people titles and the parent-child h1 were not rendered live (only the protected suites cover those screens), and `student-dashboard-integration` needs the separate `ohmylms_streak_test` database.

Catalog note: regenerate again after the Practice runner strings settle. The last run was after the Khan level settings and mastery-model strings were added (about 14:25 on October 16).

Slice 2 built (October 16, 2026): Khan level rules as an option (`ohmylms_mastery_settings`, default evidence), retry after a wrong
practice answer, level log table `ohmylms_khan_level_events`, quiz-driven up/down steps, Khan display and a level summary after practice.
Details, rules and limits are in section 8 of `docs/learner-experience/KHAN-ACTIVITIES.md`. The admin setting lives on Gamification, XP &
mastery; `npm run build` regenerated `assets/dist/admin/chunks/gamification.*` and `extensions.*`. PHPCS still outstanding for the new
files. Next: slice 3 (mixed-skill Quiz and Unit test with intro and a level-change result screen).

Slice 3 built (October 16, 2026): mixed-skill quiz, unit test and course challenge on the existing question pools. Builder
`POST /ohmylms/v1/skill-assessments`, assessment setting `style`, intro screen (`Quiz\Intro`, `assets/css/quiz-intro.css`) and a level
change list on the result screen. Section 9 of `docs/learner-experience/KHAN-ACTIVITIES.md` has the draw explained, the API and the limits.
Next candidates: an authoring screen for the builder, the unit page "Level up" button, then the Khan-style course challenge placement. PHPCS
outstanding for all new files.

Authoring screen and AI building (October 16, 2026): **OhMyLMS -> Skill assessments** (PHP page, no build), assistant and MCP tools
`list_unit_skills` and `create_skill_assessment` (draft only, approval in chat), `SkillAssessment::unit_skills()`, `Pools::available()`. Fixed
`SkillCatalogue::search` hiding skills without code, objective or UUID. See section 10 of `docs/learner-experience/KHAN-ACTIVITIES.md`,
`docs/ADMIN-ASSISTANT.md` and `docs/MCP.md`. PHPCS outstanding for all new files; `mcp-integration.php` cannot finish on the test site (adapter
server not registered), unrelated to this change.

Level up and unit-test placement (October 16, 2026): the hub course overview shows a **Level up** button on units that have a published unit
test and a **Course challenge** card; `SkillAssessment::create_for_course()` and a whole-course form on **OhMyLMS -> Skill assessments** make a
unit test for every unit (and optionally a course challenge). Courses with a managed learning program need the new assessments added in the
program editor. See section 11 of `docs/learner-experience/KHAN-ACTIVITIES.md`.

## Live-folder continuation and standards check (October 16, 2026)

Resumed in the live plugin folder at `8662c55` with a clean working tree. The teacher, family,
practice, mixed-skill assessment and Level up implementation checkpoints above were already committed;
the older teacher restore/cleanup hand-off is superseded by the subsequent verification checkpoints.

PHPCS is available here at `tools/coding-standards/vendor/bin/phpcs`. Checked the nine new assessment,
Khan-level, intro, MCP/REST and authoring-template PHP files previously marked outstanding. Fixed
mechanical formatting in `SkillAssessment.php`, `SkillAssessmentPage.php` and `SkillAssessmentTools.php`.
The authoring template now uses scoped course/unit loop variables instead of WordPress globals
`$id` and `$title`, computes its form URL before rendering the attribute, and uses an explicit edit-link
fallback. Public identifiers, hooks, assessment contracts and generated assets are unchanged.

Scoped PHPCS result: **16 errors, 2 warnings**, down from 31 errors and 17 warnings. Twelve errors are
filename findings on six PSR-4 class files: retaining the namespace-to-file mapping is a narrow
compatibility exception; renaming requires an autoloader migration. No filename suppression was added.
Four errors and one warning remain in `Skills/Khan/Levels.php` on the dynamic bulk-read queries
(interpolated table/placeholder lists and replacement-count analysis); these still need resolution and
integration verification. One warning remains for the authoring screen's 200-course query limit.
The authoring template, MCP tools and REST tool controller pass their scoped PHPCS checks.

Validation in this session: `npm test` **422 passed, 0 failed, 1 skipped**, including asset parity;
`npm run lint:source` passed (1,603 parsed files, 236 component contracts); syntax checks passed on
all four modified PHP files; `mastery-display-unit.php` passed; `git diff --check` passed.
`npm run check` stops at six existing Prettier failures: `learning/QuizList.jsx`,
`question-bank/registerPages.js`, `question-editor/QuestionCanvas.jsx`,
`question-editor/VisualEditors.jsx`, `quiz-editor/QuizEditor.jsx`, `quiz-editor/QuizSkills.jsx`.
The separate legacy `php tests/unit.php` fails because its anonymous question fixture has no
`get_settings()` method (called by `QuestionTypes.php:249`); it is not a passing test result.

Not rerun: protected database suites (`OHMYLMS_TEST_CREDENTIALS` is unset), browser flows and the build.
No live records or browser sessions were changed, and no old fixture cleanup helper was run.
Changes from this checkpoint are uncommitted and not pushed.

Next: resolve and verify the Khan bulk-query findings on a disposable test site, repair the stale
unit-test fixture, then rerun the protected assessment phases (especially `khan`, `skillcheck`,
`levelup`) and teacher/family regression suites. Continue with the release checkpoint's remaining
keyboard/screen-reader, contrast, dark-theme and large-roster checks. Existing browser results above
remain historical evidence, not a fresh acceptance pass for this session.

## Five-part implementation and release pass (October 16, 2026)

Completed the targeted Khan SQL cleanup, six-file Prettier cleanup and standalone grading-fixture
repair. Added parent activity/day/course totals and teacher class-only Khan levels, preserving
provenance and current roster/link authorization. Fixed content landmarks, unavailable-page h1s,
mobile keyboard focus/inert state and a dark-theme text-contrast finding. No assessment contract,
global role, default mastery model or database schema changed.

The existing private disposable installation was found and all requested protected regressions
ran without bypassing guards: assessment phases 1,049, new report checks 28 (202-learner roster),
Student Home 92, teacher classes 47, family 56, school reports/lifecycle/matrix 32/31/19, learning 76,
player templates 81 and account blocks 44, plus schools, gradebook and student directory. The player
runner now supports the private credential configuration. JavaScript: 425 pass, 0 fail, 1 skipped.
Build passed with two existing size warnings; 215 generated artifacts match across two builds.

Authenticated built-in browser verification used the existing View as interface, returned to admin
before cleanup and covered teacher levels/23-person pagination, parent report/link revocation,
learner Connections with an assigned teacher, keyboard navigation and mobile sections across the
learner/teacher/school workspaces. Viewport reset. Temporary fixture user/activity/Khan counts are
zero and preview files were removed. No real 800.local accounts, passwords or learning data changed.

Catalog refresh retained previous JavaScript/JSON entries and extracted PHP plus changed authored
JavaScript. `tools/compile-translations.py` appends missing entries without replacing existing values
and compiles the Mongolian MO/admin Jed JSON. All 9,603 prior entries retain their translation values;
38 new entries remain untranslated. Coverage: 3,300 translated of 9,641, with 2,071 JS messages.
WordPress loaded both artifacts; recompilation is byte-identical.

Full lint still fails on older repository debt (PHP 14,100 errors/2,986 warnings, JS 6,947 errors/
27 warnings, CSS 1,647 errors). The new assessment/report scope retains 14 documented PSR-4 filename
errors and visible warnings; no blanket baseline or filename suppression was added. Review the
readiness document for the query-local placeholder annotation, exact coverage, command requirements,
Khan comparison, translation limitations and remaining manual screen-reader/password/email checks.
This implementation, generated assets and documentation form the local checkpoint; nothing pushed
or deployed. Do not restart the obsolete teacher restore/cleanup hand-off.


## Account Hub home, previews and the profile slug

- **Home tab.** Student Home's administration moved into OhMyLMS → Account Hub as the first tab, "Home" (the default landing; the other tabs use `#/accounthub?tab=students|teachers|parents|users|classes|schools`). The hidden admin page `ohmylms-student-home-settings` (`Hub::EDITOR_PAGE`) holds the unchanged editor form and is framed on the tab; `admin.php?page=ohmylms-student-home` redirects to the tab (`Hub::redirect_admin_page`). The learner hub's "Customize" link uses `Hub::admin_home_url()`.
- **One-click previews.** `assets/src/features/schools/PreviewHome.jsx` shows Student, Teacher, Parent and School admin cards. A card starts the existing `POST school/view-as/start` for the default person from `GET school/view-as/default?role=` (the admin's remembered person in user meta `_ohmylms_view_as_last_<role>`, validated against the role's eligible people again; otherwise the most recent sign-in among the newest 50 accounts holding the role). "Change person" opens the existing ViewAsModal. A new `school` View as role (school administrators) lands on `SchoolWorkspace::url()`. The "Previewing as X / Return to admin" bar already exists: `ViewAs::banner()` on `wp_footer`/`admin_footer`.
- **Sample people.** `includes/Schools/SamplePreview.php`, REST `school/sample-preview` (GET status, POST create, POST remove with `confirm`), administrators only. Creates one "Sample Preview School" with year, class, school admin, teacher, parent and student (guardian-linked, teacher and student in the class) through the existing `Service` functions. Accounts have marker user meta `_ohmylms_sample_preview`, an `@example.invalid` address and a random password that is hashed and discarded, so only View as reaches them. A ledger option `ohmylms_sample_preview` records the IDs; a second create only fills gaps. Removal first lists what it would delete; it deletes nothing that is not marked, and refuses while the school has other members, classes, invitations, assignments or the sample student has enrolments, quiz attempts or assignments. Never run create/remove against a database you do not own. Tests: `php tests/php/sample-preview-unit.php` (in-memory fakes) and `tests/php/sample-preview-integration.php` (disposable site only).
- **Slug.** New sites create the hub page as `profile` (`Hub::SLUG`). Existing sites migrate once (`Hub::migrate_slug`, on `admin_init`, outcome in option `ohmylms_hub_slug_migration`): only the configured hub page, only while its slug is still `student-home`, only when no other non-trashed post owns `profile` (trashed posts carry `__trashed`); otherwise nothing changes and the reason is recorded. `LegacyPages::renamed_hub_target` 301-redirects the old path to the new hub URL with the whole query string (`?hub_section=..&hub_role=..`, `?ohmylms_invite=<token>`) when the old address 404s. `LegacyPages::target` ignores the retired ledger's old `/profile/` page for the exact hub address so the hub cannot redirect onto itself. Tests: `php tests/php/hub-slug-unit.php`.

## Documentation cleanup (October 19, 2026)

Every Markdown and text document outside `vendor/`, `node_modules/` and generated assets was audited against the code. Documents whose job is
done were deleted (all recoverable from git history; this session did not commit, but another session's commit `3f4e76c` "Randomized variable" swept these documentation changes in with its own code, so the history of the cleanup is mixed into that commit); the others were corrected. Documents of other sessions'
uncommitted work (`docs/question-player-block.md`, `docs/shared-choice-prototype.md`) and `languages/` were not touched.

**Deleted, with the reason:**

- `docs/learner-experience/PLAN.md`: historical planning draft, already marked superseded by this ledger; its slices all shipped, its entity
  distinctions live in `MAPPING.md` and its commands in `docs/DEVELOPMENT.md`.
- `docs/learner-experience/PARENT-CHILD-P3-PLAN.md`: P3a to P3d shipped; the adopted decisions moved into `PARENT-CHILD-P0.md`.
- `docs/SCHOOL-FAMILY-IMPLEMENTATION-PLAN.md`: the 2026-09-30 plan for the retired portal and React app; the design rules and table list moved
  into `docs/SCHOOL-FAMILY-SETUP.md`.
- `docs/UNIFIED-LEARNING-PLAN.md`: the planning proposal shipped as `docs/UNIFIED-LEARNING.md`; its still-valid design rules moved there.
- `docs/shared-skills-implementation-plan.md`: the original phased plan, shipped and documented in `docs/SHARED-SKILLS.md`.
- `docs/question-editor-continuation.md`: a session hand-off from another machine ("resume this task"); the work was completed and is described in
  `question-block-editor.md` and `question-editor-verification.md`.
- `docs/quiz-overview-verification.md` and `docs/choice-controls-verification.md`: dated browser verification notes whose behaviour is now described in
  `docs/question-block-editor.md`.
- `docs/CLAUDE-DESIGN-BRIEF.md`: a brief written to feed an external design tool, describing an IXL-style product and the portal and React app that were retired.
- `docs/QUIZ-REPORT-INTEGRATION.md`: a one-time live test log on another local site; its fixture helper only runs on a site folder named `xyz`. The fixes it found
  are recorded in `assets/src/features/quiz-reports/README.md`.
- `docs/LESSON-EDITOR-CLEANUP.md`: a cleanup report (what was removed is in git history); the "retained on purpose" list moved into
  `assets/src/features/learning/README.md`.
- `docs/CODING-STANDARDS-AUDIT.md`: an audit snapshot with superseded totals; the remaining-debt description and the latest figures moved into
  `docs/CODING-STANDARDS.md`.
- `changelog.txt`: a stale CreatorLMS-era 1.0.1 fragment; `readme.txt` holds the changelog.

**Rewritten:** `docs/learner-experience/LEGACY-RETIREMENT-PLAN.md` (now the record of what was retired and what still forwards, with no front-end portal) and
`docs/SCHOOL-FAMILY-SETUP.md` (hub workspaces instead of the portal and blocks). **Corrected or extended:** `README.md` (now an index), `README-UNIFIED.md`,
`readme.txt` (translation claim and Unreleased changelog), `docs/DEVELOPMENT.md` (assessment-engine status, Translations section), `docs/CODING-STANDARDS.md`,
`docs/ACCEPTANCE.md`, `docs/ASSESSMENT-ENGINE.md`, `docs/UNIFIED-LEARNING.md`, `docs/STUDENT-HOME.md`, `docs/STUDENT-DASHBOARD-REFERENCE.md`, `docs/XP-MASTERY.md`,
`docs/ADDONS.md`, `docs/BLOCK-CLEANUP.md`, `docs/CURRICULUM-BUNDLES.md`, `docs/SYLLABUS.md`, `docs/GAMIFICATION-STREAKS.md`, `docs/EXAM-IMPORT-IMPLEMENTATION.md`,
`docs/EXAM-PAPERS-AND-FIGURES.md`, `docs/QUESTION-TYPES-IMPLEMENTATION-PLAN.md`, `docs/EXTENSIBILITY-SCOPE.md`, `docs/CREATORLMS-COEXISTENCE.md`, the learner-experience
documents (`KHAN-ACTIVITIES.md`, `MAPPING.md`, `RESEARCH.md`, `PARENT-CHILD-P0.md`, `learner-hub-development.md`, `RELEASE-READINESS.md`), the Google Meet and WPBakery
READMEs, and the quiz-reports and learning feature READMEs. Links in this ledger to the removed documents were annotated rather than rewritten, because the entries are
chronological.

**Known gap found while documenting:** teachers have no create-assignment form in the hub; "Assign learning" is in the wp-admin class editor and the REST route
`POST ohmylms/v1/school/classes/{id}/assignments`, and hub users are kept out of wp-admin. Confirm this is intended.
