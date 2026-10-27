# Learner hub development: parent and child experience

Updated: October 16, 2026, Asia/Ulaanbaatar.

Status (checked October 19, 2026): **P0 to P5 are delivered; P6 (parent read-only reports and release
verification) is delivered in part.** This document keeps the screen specification and the rules the
parent and child experience must obey; the delivery table in section 5 records what was built and
where. See "Current state: start here" in [CONTINUE-LEARNER-HUB.md](../../CONTINUE-LEARNER-HUB.md)
for the current summary and chronological ledger.

Built and verified: shared role contexts and sign-in chooser, parent sign-up, the parent workspace
with school-linked children (P2), independent family links with managed children, invitations and
learning choices (P3a to P3d), the teacher and school workspaces, and home-learning activity reports
with explicit, immutable launch context ([the reporting contract](HOME-LEARNING-REPORTS.md); older
unknown activity and global mastery are excluded). Managed-child recovery and grade-then-collections
refinements are implemented. P5 added grouped unit activities, a mastery legend, focused program
lessons and course practice, collapsible course navigation, unit return links and keyboard-friendly
practice controls ([P5 checkpoint](P5-UNIT-PRACTICE.md)). The Khan-style practice runner, level model
and mixed-skill assessments came after that ([KHAN-ACTIVITIES.md](KHAN-ACTIVITIES.md)).
Remaining visual acceptance: the parent dashboard reference screens and a same-viewport reference
comparison were never observed, so exact parity is not claimed (see
[RELEASE-READINESS.md](RELEASE-READINESS.md)).

The user explicitly requested UI replication of the observed Khan Academy screens, with OhMyLMS
branding. Roadmaps stay hidden shared-syllabus infrastructure, a class is a student roster, and there
is no front-end portal and no learner tutor ("Tutor Me") or Teacher Tools.

## 1. Product direction: replicate the observed UI

Build the parent and child experience into OhMyLMS. Closely reproduce the observed
Khan Academy page composition, spacing, content hierarchy, navigation, selected
states, onboarding steps, and learning interactions. A generic dashboard with
similar features, or an old dashboard embedded in a new frame, does not meet this
requirement. Use OhMyLMS branding, original illustrations/icons, and existing LMS
content; do not copy Khan Academy branding, photographs, or educational assets.

Keep the existing server-rendered hub with progressive enhancement. Extend
`includes/Student/Hub.php`, `templates/student-hub/`, and scoped authored hub
assets. Reuse the existing authentication, school/family, learning, assessment,
commerce, and mastery services rather than creating parallel account systems.

Membership remains an OhMyLMS addition. Roadmaps remain hidden shared-syllabus
infrastructure. Tutor Me and Teacher Tools remain excluded. Existing teacher
workspace work and other uncommitted changes must be preserved.

## 2. Reference evidence and limits

Observed live on October 16, 2026:

| Screen | Verified behavior | Implementation implication |
| --- | --- | --- |
| Role selection | Learner, Family, Teacher cards with short purpose text | Provide role-specific entry routes through shared authentication |
| Mongolian parent signup | Two columns: circular parent/child image and fact panel; signup form on right | Match hierarchy, proportions, whitespace, and responsive stacking with original imagery |
| Parent authentication | Terms/privacy checkbox; Google primary button; Facebook, Apple, Microsoft secondary buttons; email/password alternative | Show only providers actually configured in OhMyLMS; preserve its stronger password and verification rules |
| Parent profile completion | First/last name, birth month/year, homeschooling choice, primary continue button | Separate account creation from profile completion; justify collected fields before adding them |
| Parent submission | Returned to an existing teacher dashboard | Treat role routing as a requirement to fix in OhMyLMS; do not reproduce this redirect |
| Child profile | Header, streak/level strip, avatar/name banner, left navigation, profile statistics, badges, projects | Reproduce visual grouping; display only supported metrics and features |
| Child personalization | Two-step dialog; grade selection visible in step 1 | Grade selection must lead to course selection; exact second-step reference UI was not captured |
| Child courses | Course headings, short unit lists, Start actions, Edit Courses, Add another course | Reuse course following; selection must not purchase or grant enrollment/access |
| Child progress | Date/course/activity filters, exercise minutes, total learning minutes, empty state; update-delay notice | Use measured activity data and document actual update latency |
| Child connections | Connected teacher, student identifier, Enter a class code; notice about teacher data access | Show real relationship scope and supported join mechanism |
| Unit overview | Course/unit sidebar, breadcrumbs, skill mastery legend, Learn/Practice groups, quizzes, unit test | Build from existing program placements and shared skills |
| Practice | Four-question entry card; lesson sidebar; image question with two blanks; related video; drawing, restart, skip/check, progress dots | Adapt the existing assessment player without changing assessment contracts |

The parent dashboard, child creation/linking screens, parent reports, and account
recovery were not inspected in the reference. Their requirements below are proposed
OhMyLMS designs, not verified Khan Academy behavior. The child account used the
English website; parent onboarding used Mongolian. Do not infer age restrictions
or parent permissions from the visible learner UI alone. No answers were submitted.
Do not save reference account names, birth dates, emails, IDs, tokens, or class codes
in fixtures or documentation.

## 3. Screen specification and visual acceptance

### Shared shell and role routing

- Keep stable hub URLs and public identifiers. Display role labels independently
  from stored WordPress role names. An account can be both parent and teacher.
- Add explicit Learner / Parent / Teacher context selection for eligible accounts.
  Selecting a role changes presentation only; authorization comes from current
  capabilities and relationships. A query parameter cannot grant access.
- Parent onboarding lands in the parent workspace, child login in the learner
  workspace. Restore the last valid context without overriding an explicit entry
  route. Handle empty and revoked contexts with a clear destination.
- Reproduce the reference child header, engagement strip, profile banner, and
  two-column navigation/content layout in the child profile/course views. Preserve
  existing hub destinations: My learning, Classes, Browse subjects, Achievements,
  History, Membership, and lower Settings. Map Courses to My learning, Progress
  to History, and Teachers to Connections; avoid duplicate competing menus.
- Use shared typography, borders, spacing, blue primary actions, focus rings,
  and subtle selected backgrounds. Scope CSS; support light/dark/system and
  Mongolian text expansion. On mobile, stack signup panels and collapse navigation.

### Parent entry and onboarding

- Role cards lead to dedicated parent signup/sign-in with safe return URLs.
- Signup mirrors the observed two-column layout, provider hierarchy, divider,
  labeled required fields, inline errors, password visibility, and login link.
- Use current OhMyLMS password policy (documented as 12 characters) rather than
  copying Khan's displayed eight-character rule. Preserve nonce, rate limiting,
  duplicate identity handling, spam protection, and verified account linking.
- Terms acceptance is explicit and recorded under the site's configured policy.
  Do not preselect acceptance to match a reference screenshot.
- Parent profile completion has an independent resumable state. Name fields and
  optional homeschooling context support the intended experience. Birth month/year
  is not automatically required just because the reference collected it; establish
  a concrete age/consent need and retention rule before introducing that field.
- Show a useful empty family screen immediately after onboarding: add/create a
  child when enabled, accept an invitation, or return later. No school is required
  for the proposed independent family workflow.

### Parent workspace (proposed)

- Persistent child selector with avatar/name, link status, and clear selected child.
- Child overview: continue-learning context, permitted courses, assigned work,
  supported skill mastery, and recent activity. Keep completion, mastery, and
  engagement distinct. Every metric states its scope and period.
- Progress screen reuses learner report components in read-only parent mode.
  School-scoped links show only that school's distributed work; independently
  approved home-learning scope must be explicit.
- Connections/settings show invitations, pending/active/revoked links and supported
  recovery actions. Multiple children and multiple approved guardians are supported.
- Parent pages say whose data is being viewed. Viewing a child does not impersonate
  the child, submit answers, mark lessons complete, alter grades, or change purchases.
- Parent assignment creation, messaging, and purchases for children are deferred;
  Membership initially remains the signed-in adult's own commerce context.

### Child onboarding and learner home

- A child is an existing learner identity with approved guardian relationships,
  not a second copy of a student record. Personal email can be optional for the
  managed-child flow; do not fabricate deliverable email addresses.
- Proposed independent-family creation collects display name, unique username,
  and only necessary age/consent information. Credential setup uses a private,
  expiring setup flow; credentials are never displayed in parent reports or logs.
- Existing child linking uses verified approval/invitation, never username/name
  matching alone. A parent role alone grants no child access.
- Replicate the personalization dialog: grade first, supported course choices
  second, explicit save, back/continue actions, accessible validation. Allow
  later editing; selected grade is a discovery preference, not an access rule.
- My learning shows followed/enrolled courses with unit previews and a prominent
  continue/start action. New children get an honest empty state and course discovery.
- Profile/achievements use existing avatars, XP, badges, streaks, and certificates
  where supported. Do not invent energy points, levels, projects, or progress to
  fill the reference layout. Document omitted panels and metric substitutions.
- Connections shows approved parents/teachers and what each can access. Reuse
  current invitation joining; a public reusable class-code system needs a separate
  implementation contract rather than a decorative input that cannot work.

### Unit and practice UI

- Replicate the course/unit navigator, breadcrumbs, mastery strip/legend, Learn
  and Practice blocks, next activity, quizzes, and unit test placement.
- Use existing unit terminology while retaining chapter identifiers and mappings.
  Shared skill evidence continues to update linked courses through existing services.
- Practice entry shows a real question count and primary start button. The player
  has a collapsible lesson list, focused question canvas, related learning content,
  and sticky footer with progress and applicable Check/Skip/restart actions.
- Preserve custom question extensions, attempt lifecycle, scoring, hints, skip
  semantics, published program versions, and assessment contracts. Enable drawing
  only through supported player behavior; identify it as a gap if unavailable.
- Resume/exit retains course and unit context. Visual replication must not cause
  auto-completion or create scores merely from visiting a screen.

## 4. Existing implementation and required additions

| Area | Existing source | Required work |
| --- | --- | --- |
| Hub shell | `includes/Student/Hub.php`, `templates/student-hub/`, authored hub CSS/JS | Parent context, child selector, reusable profile/report presentation |
| Signup/login | `includes/Schools/Controller.php`, existing registration blocks and auth services | Dedicated onboarding presentation and reliable role-aware landing |
| School guardians | `includes/Schools/Schema.php`, `Access::guardian()`, `Service::children()` | Preserve school-specific approvals and immediate revocation |
| Children API | `GET /ohmylms/v1/school/children` | Keep existing contract; add scoped family read models rather than widening it silently |
| Scope gap | Guardian links currently require school/student membership | Add an explicit independent-family relationship design and additive migration |
| Teacher connections | Current class/school memberships | Show actual connections without broadening teacher access |
| Reporting/mastery | Existing learner history, skill state, completion and assessment services | Reuse calculations with relationship-scoped queries |
| Account preview | `includes/Schools/ViewAs.php` | Never use administrative preview/impersonation as parent access |

Before schema work, inventory exact ownership and cache behavior. Independent
family links should have explicit guardian, learner, scope, approval provenance,
and pending/active/revoked states. Use a separate relationship or explicit scope
model; do not turn `school_id = 0` into a wildcard in existing guardian checks.
Document chosen tables, indexes, migration version, and API contracts before coding.
Migrations must be additive and safely repeatable; rollback disables new views
without deleting identities, enrollments, or historical learning records.

Every report, count, search, export, download, and child-selector request validates
current actor and relationship scope on the server. School approval does not imply
access to home learning or another school. Cache keys include actor, child, and
scope, with invalidation on revocation. Use capability/nonce checks, sanitization,
prepared queries, contextual escaping, and generic unauthorized responses.

## 5. Delivery order and acceptance gates

| Slice | Deliverable | Acceptance gate | Status |
| --- | --- | --- | --- |
| P0 | Screen inventory, annotated reference comparisons, relationship/data contract | Observed vs proposed screens and supported vs missing player features recorded | Delivered: [PARENT-CHILD-P0.md](PARENT-CHILD-P0.md) |
| P1 | Role chooser, parent auth/profile UI, role-aware landing | Parent/teacher dual-role account reaches requested workspace; no role escalation | Delivered |
| P2 | Parent workspace using existing school guardian links | Two children switch correctly; unrelated child IDs denied; revocation effective immediately | Delivered |
| P3 | Independent family links and child setup | No-school family works; duplicate identity, expired/replayed invitation, recovery and multi-guardian cases pass | Delivered (P3a-P3d) |
| P4 | Child personalization, My learning, profile and connections | Reference composition reproduced with real data, empty states, supported metrics and working navigation | Delivered in the learner hub; unobserved reference screens not claimed |
| P5 | Unit and practice presentation integration | Question extensions and genuine assessment behavior preserved; resume/exit and keyboard flow verified | Delivered: [P5-UNIT-PRACTICE.md](P5-UNIT-PRACTICE.md) |
| P6 | Parent read-only reports and release verification | Cross-school/home scope tests, visual comparison, localization, migration rehearsal and rollback checks pass | Reports delivered ([HOME-LEARNING-REPORTS.md](HOME-LEARNING-REPORTS.md)); the visual comparison, full localization and migration rehearsal remain open ([RELEASE-READINESS.md](RELEASE-READINESS.md)) |

Update the continuation ledger after each slice with changed files, checks actually
run, known failures, and one exact next step.

## 6. Verification

- Capture reference comparisons and OhMyLMS screenshots at the same desktop
  viewport, plus 390px mobile. Compare panel geometry, spacing, typography,
  selected states, primary actions, question canvas, and sticky player footer.
- Verify keyboard navigation, labels, visible focus, dialog focus return, error
  announcements, long Mongolian strings, dark mode, and no horizontal overflow.
- Test parent with no children, multiple children/guardians, revoked/pending links,
  teacher+parent role, independent child, school child, and mixed-scope child.
- Deny guessed child IDs and cross-scope reads/writes, including exports and cached
  results. Parent viewing must not mutate child attempts or completion.
- Use disposable integration fixtures; never submit exercises on a real child's
  behalf to validate scoring. Run relevant school/hub integration tests and player
  contract tests after implementation, then required syntax and standards checks.
- Repository commands include `npm run lint:source`, `npm run lint:standards`,
  `npm test`, `npm run build`, `php tests/php/schools-integration.php`, and
  `php tests/php/student-hub-integration.php`. Respect their disposable database
  and credential requirements. Report existing violations and unavailable checks.

This specification does not itself implement UI or migrate data. The source audit, screen
inventory and family contract are in [PARENT-CHILD-P0.md](PARENT-CHILD-P0.md). Unobserved reference
screens remain open visual acceptance items; follow the continuation ledger for current runtime
changes and checks.


## Hub address and administration (October 2026)

The hub page slug is `profile`; `/student-home/` redirects permanently with its query string, so invitation emails and bookmarks keep working. Retired-page redirects must never match the hub's own address (the old profile page also used `/profile/`). Hub administration, one-click role previews and the optional sample preview people live on the Account Hub home tab; see "Account Hub home, previews and the profile slug" in CONTINUE-LEARNER-HUB.md for the files, options and tests.
