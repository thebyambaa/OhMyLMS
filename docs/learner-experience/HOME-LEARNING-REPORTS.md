# Home-learning provenance and activity reports

Implemented October 16, 2026. The report uses the current hub components. Its composition is a
proposed design; it does not claim parity with an unobserved reference parent dashboard.

## Origin contract

Family schema version 2 adds `ohmylms_family_activity`. Existing links, invitations and audit
records are preserved. Install is additive and repeatable. There is no historical backfill.
`OHMYLMS_FAMILY_ENABLED=false` disables recording and report access while retaining all data.

Each record binds a source kind/row ID, learner, course/content or skill ID, origin, optional
school/class/distribution IDs, creation time and optional completion time. The unique source
key prevents resumes, duplicate lifecycle hooks or reviews from replacing the original context.
Passwords, raw context nonces, invitation tokens and answer bodies are never stored here.

The learner chooses a context on a lesson, quiz, assignment or skill-practice page before
starting. A school choice is offered only for an actual active distribution received by the
current learner, with active school/class/student relationships. Whole-course distributions
can scope skill practice; a numeric skill ID is not treated as a content-post ID. Home learning
is an explicit launch context, not a claim about physical location or a deduction from enrollment.
The choice grants no course, content, school or family permission.

Choices carry a normal WordPress nonce bound to the current user/session and exact kind,
course, content and distribution. Capture verifies that nonce and the persisted source owner.
Direct form requests read the page query; same-origin AJAX/REST requests can carry it through
the page referrer. Referrers are only transport, never authorization. Missing, expired, forged,
cross-user or mismatched context records `unknown`. No cookie or cross-tab mutable context is used.
Private learner/context pages send no-cache and same-origin referrer headers. View-as previews
cannot assert home or school learning provenance.

| Source | Origin bound | Activity counted |
| --- | --- | --- |
| Quiz | Existing `ohmylms_attempt_started` after a new attempt is persisted | `ohmylms_attempt_submitted`; reviews cannot change origin |
| Lesson | Verified new progress row at completion; only lesson posts | Same verified completion, once |
| Assignment | New `ohmylms_assignment_submitted` after a successful new submission insert | Submitted, with no claim that it is graded/passed |
| Skill practice | New `ohmylms_practice_started` after a new signed-in session insert | Existing `ohmylms_practice_completed` for a completed session |

Existing assessment grading, course completion, enrollment, purchase and manual-override rules
are unchanged. An older attempt that finishes now is not retroactively attributed. Guests,
guest claims, inline checks, historical rows and integration writes without the supported
creation hooks stay unavailable in home reports. Global skill mastery is not scoped evidence.

## Report authorization and presentation

Parents open **View home-learning report** from the Home learning child list. The exact scope
is `hub_scope=home_learning`; school child navigation remains separate. A child ID is a candidate,
not a permission. `Family\Service::child()` requires the signed-in eligible parent and an active,
approved independent-family link. School guardian links alone never authorize this report.

Summary and row queries also join the exact current guardian/link/scope in SQL without caching.
Only completed home-context records created during the current link approval period are included.
Reapproval uses the link's current `updated_at` boundary and does not reopen an older link period.
Revocation takes effect on the next read. Home reports work when the school schema is unavailable.

The report offers 7, 30 and 90 day windows, activity counts, active UTC dates, dated activity titles
and 20-row pagination. Submission labels distinguish submitted work from lesson/practice completion.
It also groups authorized records by activity type and UTC date, and shows totals for the ten most
active courses. These aggregates use the same exact-link scope as the activity list and never infer
study time or accuracy from submission counts. Daily/course breakdowns use native disclosures.
It exposes no grades, responses, credentials, school work or global mastery. Site-time formatting
is used for displayed timestamps; the active-date aggregate explicitly says UTC.

## Verification and remaining design work

- Protected disposable database: 56 family checks (33 provenance/report and 23 management); source ownership, signed transport,
  invalid contexts, school recipient revocation, immutable replay/resume, unknown-history exclusion,
  all four source types, cross-child/guardian denial, period/pagination, reapproval, email-verification
  policy, additive migration, no-school operation and feature rollback with retained data.
- Regressions: 88 Student Home and 82 school report/lifecycle/matrix checks; JS asset parity plus
  414 passing tests and one skipped test. New runtime PHP passes PHPCS; the guarded fixture runner
  retains direct-database/local-file warnings. Existing standards debt in older source is unchanged.
- Built-in browser: a disposable parent's populated report, dark theme, 390px mobile layout
  (375px document within the 390px viewport), and explicit lesson context selection. The parent's
  session used the existing View-as feature and returned to admin before cleanup. No password
  entry or real-account role change was needed. All fixture users, posts, links, enrollments,
  activity, submissions and preview audit records were removed; screenshots are ignored artifacts.

Managed-child recovery now requires explicit replacement confirmation, explains link expiry and
password behavior, and rejects accounts promoted to staff or parent roles or staff capabilities.
Issuance and consumption serialize on the learner account and recheck management authorization.
Learning choices uses grade then collections with Next/Back/Save/Cancel; closing or Escape discards
unsaved changes. Both sections remain usable without JavaScript. Cards preview public courses and
following never grants enrollment/access. These refinements passed built-in browser persistence,
cancellation, recovery confirmation/link creation and dark 390px layout checks using disposable users.
The existing hub style is used; unobserved reference layouts are not claimed as replicated designs.
Rich home-only skill/accuracy reports would need source-scoped evidence
queries; they must not substitute global mastery or unknown historical results.

The later release pass added the activity breakdowns above and verified report revocation through
the real parent form in the browser. See `RELEASE-READINESS.md` for current tests and limits.
