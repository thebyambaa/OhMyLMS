# Enrolments: storage, sources and the `ohmylms_enrolments` table

Phase C, part 1 of the [admin redesign](../ADMIN-REDESIGN.md): the enrolment data model the gradebook will
build on. There is no new REST route. Server code is in `includes/Student/`: `Enrollments` (the service that
owns every write and the shared reads), `EnrolmentMirror` (legacy row → new row), `EnrolmentMigration`
(batched fill, revert, watchdog) and `EnrolmentSchema` (the table). Tests:
`tests/php/enrolments-migration-integration.php`.

## Audit (31 October 2026)

### Where enrolments live today

| Storage | What it holds |
| --- | --- |
| `ohmylms_user_enrollment` | One row per grant: `user_id`, `course_id`, `membership_id` (NULL or plan ID), `order_id` (0 = no order), `status`, `progress`, `start_date`, `end_date` (site time). No source, no author, no class. `ohmylms_user_progress.enrollment_id`, `ohmylms_learning_enrollments.enrollment_id` and `ohmylms_learning_awards.enrollment_id` point at its `id`. |
| `status` values | `enrolled`, `banned` (blocked student), `pending` (unpaid order), `cancelled` (refund, cancelled membership, deleted user, expired free grant), `expired`, `dropped` (learner left, with `progress = stop`). |
| `progress` values | `running`, `completed` (with `end_date` = completion), `stop`, `0` (MasterStudy imports). |
| `ohmylms_user_membership` | Membership grants: `membership_id` (plan post), `order_id`, `subscription_id` (0 = none), `status`, `start_date`, `end_date` (free-sale expiry). Course access through a plan is still an enrolment row with `membership_id` set, kept in step by `Membership\CourseSelection::sync()`. |
| Subscriptions | `ohmylms-subscription` posts with `_membership_id`; orders carry `_subscription_id` / `_subscription_renewal_id` meta. Subscription status changes do not touch enrolment rows. |
| Orders | `ohmylms-order` posts; `_order_total` (0 for free checkout, which is how free courses are self-enrolled). |
| Classes (rosters) | Account Hub tables `ohmylms_classes`, `class_memberships` (role `student`/`teacher`), plus the gradebook link tables `course_classes` (course × class, `created_at` GMT) and `course_class_students` (who a class sync included). `gradebook_overrides` holds per-cell manual scores. |
| Meta and options | User meta `_is_ohmylms_student`, `_is_ohmylms_user`, `_ohmylms_banned_student` (blocked flag), `_ohmylms_membership_free_sales` (free grant expiry); course meta `_ohmylms_exam_wrapper` (standalone exam course), `_ohmylms_migration_source` (MasterStudy import). Option `ohmylms_allow_admin_to_enroll`. |
| Seats | No storage. The only "seat" in the code is a course capacity setting; nothing sells or assigns seats yet. |
| Grades | `ohmylms_quiz_attempts` (+ answers), `ohmylms_assignment_attempts`, Assessment `grade_events`, `gradebook_overrides`. Not changed here. |

### Sources and their writers

| Source | Writer (before this change it wrote `$wpdb` directly) |
| --- | --- |
| Purchase (course) | `Commerce\Checkout::process_enrollment()` (insert or update by order or cohort), `Funnel\FunnelEndpoint` upsell, pending → enrolled in `Hooks\OrderHooks::after_payment_completed()` |
| Purchase (membership, subscription) | `Checkout` (row with `membership_id`), `Membership\CourseSelection::sync()` (insert/update per plan course, adopts old checkout rows) |
| Self (free) | Free checkout orders (`_order_total` 0), `Membership\FreeSale` grants, standalone exam start (`Exams\Player::start()`) |
| Manual | `Rest\V1\CourseController` (enrol, enrol by e-mail, unenrol), `Student\Enrollments::enroll_manually()` (students API) |
| Roster | `Schools\Gradebook::attach()` with `enroll` (class sync) |
| Import | `Migrations\TutorLMS`, `LearnDash`, `MasterStudy` |
| Status changes | `Student\Directory::set_blocked()`, refunds (`OrderRefundStore`), `MembershipStore::cancel_enrollment()`, `StudentStore` (completion, order status, delete by order), `Ajax` (completion date, drop course), `Learning\CompletionPolicy::award()`, `Quiz\Submission` (preview reset reopens a course), `FreeSale` expiry, `Hooks\CommonHook` (course, order, membership and user deletion) |

`User\UserRepository::insert_new_enrollment()` writes a table that does not exist
(`ohmylms_users_enrolled_in_courses`); it is left as it was.

### Readers

About 55 files read `ohmylms_user_enrollment`, almost all with `status = 'enrolled'` or `IN ('enrolled','banned')`
and many joining `ohmylms_user_progress` on its `id`. Switched to `Enrollments` in this change: the students API
(list, lookup, counts), the student report, `CourseController` enrolled counts, `LearningController` report,
`SessionsController` attendees, `SkillReportController` course scope and `Schools\Gradebook` (students and the
grade check). Still direct (they keep working because the legacy table stays authoritative): `Student\Directory`
list SQL, `Analytics\Repository` and `Analytics\Reminders` (dashboard workstream), `DataStores\StudentStore` /
`CourseStore`, `Data\Student`, emails, webhooks, xAPI, WP Fusion, automations, drip content, cohorts and Zoom
reminders, `Learning\*` (program, placements, bootstrap), `Quiz\*`, engagement, family provenance, history feed,
my learning, bundles, skills report data, `Schools\ViewAs` and `SamplePreview`, migrations' duplicate checks.

### Findings

- No row says how an enrolment came about; manual, roster, import and exam rows are indistinguishable.
- Class sync creates enrolments but detaching a class does not revoke them; class membership changes never
  touch enrolments.
- Subscription cancellation and expiry do not revoke course access rows.
- `Checkout` wrote `cohort_id`, a column the table does not have (that insert failed when a cohort was set).
  Fixed 31 October 2026: the write is gone; cohort membership lives in `ohmylms_cohort_members` ([cohorts](cohorts.md)).
- A student can have several rows per course (one per order, membership, manual grant); readers pick the first
  or newest row inconsistently.

## Target model

```sql
CREATE TABLE {prefix}ohmylms_enrolments (
  id bigint unsigned NOT NULL auto_increment,
  user_id bigint unsigned NOT NULL,
  course_id bigint unsigned NOT NULL,
  status varchar(20) NOT NULL DEFAULT 'active',     -- active, completed, expired, cancelled, suspended, pending
  source varchar(20) NOT NULL DEFAULT 'manual',     -- purchase, self, manual, roster, seat, import
  source_type varchar(20) NOT NULL DEFAULT '',      -- what source_ref is: order, membership, subscription, class, seat, exam, automation
  source_ref bigint unsigned NOT NULL DEFAULT 0,
  class_id bigint unsigned DEFAULT NULL,            -- the roster (Account Hub class) that enrolled the student
  legacy_id bigint unsigned DEFAULT NULL,           -- ohmylms_user_enrollment.id (progress rows refer to it)
  active_key tinyint unsigned DEFAULT NULL,         -- 1 while active and holding the unique slot, else NULL
  migrated tinyint unsigned NOT NULL DEFAULT 0,     -- 1 = created by the migration (the down step removes only these)
  enrolled_at datetime DEFAULT NULL,
  expires_at datetime DEFAULT NULL,
  completed_at datetime DEFAULT NULL,
  updated_at datetime NOT NULL,
  created_by bigint unsigned NOT NULL DEFAULT 0,    -- acting user; 0 = system or migration
  PRIMARY KEY (id),
  UNIQUE KEY legacy_id (legacy_id),
  UNIQUE KEY active_enrolment (user_id, course_id, source, source_type, source_ref, active_key),
  KEY course_status (course_id, status), KEY user_status (user_id, status), KEY class_id (class_id), KEY migrated (migrated)
) ENGINE=InnoDB;
```

- **One active row per user, course and source reference.** MySQL has no partial unique index, so `active_key`
  is 1 only for an active row and NULL otherwise (NULLs never collide). `source` and `source_type` are part of
  the key because order 5 and class 5 are different references. When a second active row with the same
  reference exists (legacy duplicates, a repeated grant), it is stored with `active_key` NULL and counted in the
  migration state (`duplicates`).
- **Dates** are copied verbatim from the legacy row (site time). Zero dates become NULL. A completed legacy row
  without an end date gets `completed_at = enrolled_at`.
- **Status mapping**: `enrolled` → `active`, or `completed` when `progress = completed`; `banned` → `suspended`;
  `pending`, `expired` stay; `cancelled`, `dropped` and anything else → `cancelled`. Readers that need the
  legacy strings derive them (`active`/`completed` → `enrolled`, `suspended` → `banned`; progress from
  `completed_at`).
- **Source rules** for rows that predate the table (`EnrolmentMirror::detect()`), in order: a membership row
  with a subscription → `purchase/subscription`; a membership bought with a paid order → `purchase/membership`;
  a free membership grant → `self/membership` (with `expires_at` from the grant); an order → `purchase/order`, or
  `self/order` when `_order_total` is 0; a standalone exam course → `self/exam`; a `course_class_students` row
  created within 300 seconds of the enrolment → `roster/class` with `class_id`; a course with
  `_ohmylms_migration_source` → `import`; otherwise `manual`. Tutor LMS and LearnDash imports did not mark their
  courses, so their old rows become `manual`; new imports record `import`.

## Writes (dual write)

Every writer listed above now calls `Student\Enrollments`:

| Method | Does |
| --- | --- |
| `create( $row, $origin, $format )` | `$wpdb->insert()` on the legacy table, then the projection with `$origin` (`source`, `source_type`, `source_ref`, `class_id`, `expires_at`, `created_by`; or `'detect'`). Returns what `$wpdb->insert()` returned and leaves `$wpdb->insert_id` = legacy ID. |
| `update( $data, $where, $origin, $format, $where_format )` | Same `$wpdb->update()`, then re-projects the matched rows (source kept unless `$origin` is given). Returns the `$wpdb` result, so `!== 1` checks still work. |
| `delete( $where, $where_format )` | Same `$wpdb->delete()`, then removes the projections. |
| `resync( $where, $origin )` | Re-projects after a set-based SQL write (`FreeSale` expiry, `CourseSelection` adoption). |

Sources recorded: `CourseController` and the students API → `manual`; checkout, upsell and order completion →
`EnrolmentMirror::order_origin()` (purchase or self, order/membership/subscription); `CourseSelection` →
`detect`; class sync → `roster/class` with `class_id`; exam start → `self/exam` with the exam ID; LMS imports →
`import`. No seat writer exists yet; `create()` accepts `seat/seat`.

Writes run in the caller's transaction (the class sync and course completion keep theirs). Projection writes
suppress and restore `$wpdb` errors, `insert_id` and `rows_affected`; a failed projection never fails the legacy
write. Write-side checks (duplicates, "already enrolled") keep reading the legacy table. Until the migration is
complete, updates only touch projections that exist; afterwards they also create missing ones.

## Migration

- **DB update key `1.2.21`** (after the admin assistant clean-up): `ohmylms_update_1221_create_enrolments_table`
  (`EnrolmentSchema::install()`, `dbDelta`, option `ohmylms_enrolments_schema` = `1`) and
  `ohmylms_update_1221_schedule_enrolment_migration` (`EnrolmentMigration::start()`). The table is also in
  `Install::get_schema()` for activation. `OhMyLMS::VERSION` is still `1.2.20`, so these run when the release
  bumps the version to 1.2.21.
- **Up** (`EnrolmentMigration`, WP-Cron hook `ohmylms_migrate_enrolments_batch`): each batch takes the next 200
  legacy rows by ID `FOR UPDATE` inside a transaction (a concurrent legacy write waits until its projection
  exists), projects them (`migrated = 1`), stores the cursor in option `ohmylms_enrolment_migration` and
  schedules the next batch; `GET_LOCK` keeps runners from overlapping. Then a sweep projects legacy rows still
  without a projection, deletes projections whose legacy row is gone, and sets `status = complete`.
  Idempotent (keyed by `legacy_id`; existing rows are updated in place, keeping their source) and resumable (a
  failed batch keeps the cursor, records `error` and retries in 60 seconds).
- **Watchdog**: on `admin_init`, once the DB version is at least 1.2.21, starts a migration that never started
  and re-arms a lost event for a running, stale or reverting one. A complete or reverted migration is left alone.
- **Reads switch** (`EnrolmentMigration::complete()`, filter `ohmylms_enrolments_read_new`) only when the state is
  `complete`, the table exists for the current prefix, and the newest legacy row has a projection. A legacy row
  inserted outside the service makes readers fall back at once; if it is still unprojected after 60 seconds the
  state becomes `stale` and a sweep runs. A failed projection write also marks the state `stale` (full re-walk).
- **Down**: `EnrolmentMigration::revert()` sets `reverting` (reads fall back at once) and hook
  `ohmylms_revert_enrolments_batch` deletes `migrated = 1` rows 500 at a time, ending in `reverted`. Rows written
  by the services stay; legacy storage is never touched. `EnrolmentSchema::uninstall()` drops the table and its
  option (schema down). `EnrolmentMigration::compare()` reports legacy and new counts, missing rows, orphans and
  status mismatches. To run by hand: `wp eval '\OhMyLMS\Student\EnrolmentMigration::start(); print_r(\OhMyLMS\Student\EnrolmentMigration::run());'`.

## Readers (`Enrollments`)

Each returns the same shape from either table; `id` is always the legacy row ID.

| Method | Used by |
| --- | --- |
| `count()`, `page()`, `find()`, `course_counts()`, `completed_items()` | students API, student report |
| `count_by_course()`, `count_in_course()` | `CourseController` (course lists, single course), `LearningController` |
| `course_user_ids()` | `LearningController` report (paged), `SkillReportController` (distinct) |
| `course_roster()` | `SessionsController` attendees |
| `listed_students()`, `is_listed()` | `Schools\Gradebook` |
| `rows_in_course()`, `legacy_status()` | write-side checks (always legacy) |

## Verification (31 October 2026, 800.local)

- `enrolments-migration-integration.php` (`OHMYLMS_ENROLMENTS_TEST=1`), 309 checks: schema and indexes; fixtures
  for every source and status; up interrupted after one batch and resumed from the cursor; a service write
  during the run; a raw row added during the sweep; idempotent re-run (same IDs and sources); identical answers
  from both tables for every reader and the students REST route; the lag fallback and sweep; each service write
  path (block, completion, cancel by order, course enrol/unenrol/by e-mail, students enrol/unenrol, class sync,
  seats, course and user deletion); down in batches (only `migrated = 1` rows removed, legacy untouched); up
  again; watchdog; schema down and up with legacy writes still working.
- Existing suites, before and after, same check counts: courses 284, students 135, student report 67, commerce
  210, memberships 174, subscriptions 189, Account Hub 227, roster 89, users 88, exams 137, sessions 49,
  gradebook, free-sale and class-gradebook units. (Later in the day the concurrent removal of the legacy
  `SessionController` broke the sessions suite's two legacy-route checks; the other 47, attendees included,
  pass. `membership-selection-unit` fails before and after on a missing `MembershipController` method; its
  enrolment part passes.) The students, student report, courses, roster and gradebook
  suites also pass with reads on the new table (`students-rest-integration.php` now writes its fixtures through
  the service).
- Real data: 3 legacy rows → 3 projections (2 `self/order` free checkouts, 1 `self/exam`), 0 missing, 0 orphans,
  0 mismatches, identical per-course and per-student counts; the down step removed the same 3.

## What the gradebook can rely on

- Every enrolment written by the plugin has a row in `ohmylms_enrolments` with its source, reference and author;
  after the migration every legacy row has one (`legacy_id`), so progress, attempts and awards join through
  `legacy_id`.
- `class_id` is set for roster enrolments; `course_class_students` remains the course × class membership used
  for grouping (it also lists students who were enrolled before the class was attached).
- "Individuals only" = enrolments whose student has no `course_class_students` row for the course; "one roster"
  = that table for one class. "Not assigned" (no enrolment or not targeted) and "missing" (enrolled, no
  attempt) are distinct because the enrolment row exists independently of attempts.
- Not yet: seat storage, class detach and subscription end do not revoke enrolments, and the readers listed as
  "still direct" above have not moved. Until they do, legacy storage stays the source of truth.
