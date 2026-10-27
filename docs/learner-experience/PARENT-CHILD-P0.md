# Parent and child: P0 implementation contract

Source audit: October 16, 2026. This records implementation decisions and remaining
reference gaps.

**Status (checked October 19, 2026):** the contract below is implemented through P1, P2 and
P3a-P3d: role chooser and parent sign-up, school-linked read-only child overview, and the
independent family schema (`ohmylms_family_schema`, tables `ohmylms_family_links`,
`_invitations`, `_audit`, and version 2 `_activity`) with managed children, setup links,
existing-learner invitations, learning choices and revocation. Differences from this contract:
family operations use `admin-post` forms rather than a `/ohmylms/v1/family` REST namespace
(no such routes exist), and the hub password minimum was reconciled to 12-128. Home-learning
activity reports and launch provenance are implemented; see
[HOME-LEARNING-REPORTS.md](HOME-LEARNING-REPORTS.md). The unobserved reference screens
(parent dashboard, family linking, recovery, personalization step two) remain proposed designs.
The current summary is "Current state: start here" in the continuation ledger.
Read with [the screen specification](learner-hub-development.md) and the
[continuation ledger](../../CONTINUE-LEARNER-HUB.md).

## Existing services and boundaries

| Area | Source finding | Implementation decision |
| --- | --- | --- |
| Guardian identity | Schools schema already registers `ohmylms_parent` with `read` | Reuse WordPress identities; a role label alone grants no child access |
| School links | `Schools/Schema.php` has `guardian_links`, unique by school/guardian/student, approval actor/date and active status | Keep this table and its public contract unchanged |
| Child selector | `Schools/Service::children()` joins active schools, approved guardian links and active student membership; bounded to 100 rows | Use this for the initial school-linked workspace; show a bounded-list notice if the limit is reached, then add a paginated read model without changing the existing API |
| Access | `Schools/Access::guardian()` checks active student membership and an active link; `children()` additionally checks school status | Every new school-parent request must check the school is active as well as the guardian link; do not rely on an earlier selector response |
| Parent reports | `Schools/Service::assignments()` authorizes the guardian and restricts rows to distributed work/current memberships in the selected school | Reuse that scope; do not call global history/mastery readers with an arbitrary child ID |
| HTTP caching | `Schools/Controller::dispatch()` sets `Cache-Control: private, no-store` | Parent responses and HTML follow the same private/no-store policy |
| Invitations | School invitations carry a school, hashed token, issuer, expiry, consumed/revoked state; acceptance rechecks issuer access | Keep school invitations school-scoped; create independent family invitations separately |
| Role routing | The current hub supports learner/teacher context; teacher Settings now preserves eligible teacher context | Add parent context using current relationships; preserve explicit routes over device defaults |
| Passwords | School signup requires 12–128 characters; hub password changes allowed eight when this audit was written | Parent signup uses the 12–128 rule. The hub password-change policy was reconciled to 12–128 during P1 |
| Preview | `Schools/ViewAs.php` is administrative preview with its own session | Never use it for parent viewing or child switching |

The current class definition is **a student roster**. Courses are followed/enrolled
learning objects; assignments and reports are separate class destinations. A parent
child-selector is a family relationship selector, not a class or course selector.

## Screen inventory

| Screen | Reference evidence | Initial delivery and empty state |
| --- | --- | --- |
| Role chooser | Observed learner/family/teacher cards | Shared sign-in with explicit destination; authenticated switch shows eligible contexts |
| Parent signup/profile | Observed composition and fields | Original OhMyLMS assets, configured providers only, inline validation; no unsupported provider buttons |
| Parent home/child selector | Proposed; parent dashboard not observed | Approved school children, selected school scope; no-child invitation/return actions |
| Child overview | Proposed parent view | Child name and scope, distributed assignments and actual completion; no global learner dashboard embed |
| Parent progress | Proposed | School-distributed work first; absent measured data gets an unavailable/empty state |
| Family connections | Proposed | Distinct pending/active/revoked links and school/home scope, with allowed actions only |
| Child personalization | Grade step observed; second-step layout unobserved | Grade then real course choices; save/back/edit; follows never grant access |
| Child profile/courses | Composition observed | Existing avatar/course/unit data; omit unsupported metrics and project panels |
| Child connections/history | Observed | Actual teachers/approved guardians, supported joining and measured activity |
| Unit/practice | Composition and entry observed; no answers submitted | Preserve existing assessment lifecycle; drawing/related-content interactions require capability inventory before controls appear |

Before visual acceptance, obtain same-viewport comparisons for unobserved screens
or explicitly label them as proposed OhMyLMS designs. Do not describe a proposed
parent dashboard as a verified Khan Academy replica.

## Independent family model for P3

Use additive, independently versioned `Family` services and tables. Do not modify
the meaning of `school_id`, and do not use zero as a school-permission wildcard.
These schema names are now installed (version 1, with an added `kind` column on invitations):

- `ohmylms_family_links`: ID, guardian user ID, learner user ID, explicit scope
  `home_learning`, status `pending|active|revoked`, approval actor/time, approval
  method, created/updated/revoked timestamps and revision. Unique guardian/learner/
  scope; indexes on guardian/status and learner/status. Validate identities and
  prohibit self-links. Multiple approved guardians are supported.
- `ohmylms_family_invitations`: ID, unique token hash, issuer, intended recipient
  identity or verified destination, target learner/link, scope, expiry, created,
  consumed and revoked timestamps. Tokens are one-use, time-limited and never
  exposed in reports/logs; recheck the issuer at acceptance. Acceptance and link
  activation must be atomic so replay/concurrent acceptance cannot grant extra links.
- `ohmylms_family_audit`: actor, action, link/object ID and UTC timestamp. Record
  approval/revocation provenance; never record passwords or raw setup/invitation tokens.

Use existing `wp_users` learners. Managed children may have an empty personal email
where supported; do not fabricate an email address to satisfy a form. Use a private,
expiring credential-setup flow. Existing-child linking requires verified approval,
not a matching display name/username. Parent accounts cannot read child credentials.
Collect only age/consent information required by the configured site policy; the
reference's birth fields alone do not establish a collection requirement.

Do not silently import school guardian links into independent family links. A
guardian may have multiple school scopes plus a separately approved home scope.
Each selector option carries the scope; every read validates that exact scope anew.

Home learning reports require reliable activity/enrollment provenance. The current
global learner history is not proof that activity happened independently of a
school. P3 must inventory event provenance and add the necessary origin binding
before P6 enables home aggregates. Unknown-origin evidence stays unavailable in
scope-specific reports; do not invent a home/school split from course membership.

## HTTP and migration contract

- Keep `/ohmylms/v1/school/children` and school report contracts intact. The contract
  proposed a separate `/ohmylms/v1/family` namespace; it was not built, and independent-family
  operations use nonce-protected `admin-post` forms instead.
- Reads use the signed-in actor, never a client-supplied guardian ID. A child/scope
  identifier selects a candidate; it does not grant access. Writes require current
  authorization, nonce, input validation and bounded/rate-limited invitation handling.
- P2 exposes school-linked read-only pages. P3 adds create/invite/accept/revoke/setup
  operations only after their relationship contract and integration checks pass.
  Parent assignment creation, messaging and purchases for a child remain deferred.
- Start with fresh permission queries and no shared parent-report cache. If caching
  is introduced, keys include actor, learner, scope and relationship revision, with
  invalidation on school/member/link changes. Revocation must apply on the next request.
- Install using an independent schema version, repeatable additive migrations and
  explicit indexes. Do not rewrite learning history or enrollments. Rollback disables
  new routes/views while retaining identities, relationships and audit history.

## Delivery status and decisions adopted for P3

P1 (role selection, parent onboarding, the 12-128 character password rule), P2 (school-linked
read-only overview, which denies unrelated children, inactive schools, inactive student
memberships and revoked links on every request) and P3a-P3d are built. Reference inspection is
still incomplete for the parent dashboard, family linking, reports, recovery and personalization
step two, so visual parity for those screens is not claimed.

P3 was delivered in four slices:

- **P3a** schema plus `Family\Service` (`children()`, `child()`, `create_child()`,
  `invite_existing()`, `accept()`, `revoke()`) with no UI; **P3b** "Add a child" and the child
  setup link; **P3c** invite an existing learner, with accept/decline in the learner's
  Connections; **P3d** grade and collections step for a managed child. Accept is one transaction
  that rechecks the issuer and consumes the invitation while activating the link, so replay and
  concurrent accepts cannot double-link.
- Decisions adopted: collect no birth date (display name and grade only) unless a site policy
  requires more; a managed child is a `wp_users` row with a generated login and no personal
  email, signing in through a parent-issued expiring setup link; an existing learner is linked
  only through a private single-use invitation bound to their verified email; selector rows
  carry a scope (`school:<id>` or `home`) and `ParentWorkspace::children()` merges both,
  revalidating the exact scope on every request. `OHMYLMS_FAMILY_ENABLED=false` disables
  recording and views and keeps every table, link and audit row.
- Not part of P3: parent-created assignments, messaging, purchases for a child, importing school
  guardian links into family links, and the unobserved reference screens.

Legacy retirement was handled slice by slice; see
[LEGACY-RETIREMENT-PLAN.md](LEGACY-RETIREMENT-PLAN.md). Checkout is an active dependency and
remains separate from the hub.
