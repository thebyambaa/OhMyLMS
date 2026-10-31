# Cohorts (`ohmylms/v1/cohorts`)

A cohort groups the learners of one **course** or one **membership plan** who enrolled or bought in the
same window. Server code: `includes/Cohorts/Cohorts.php` (storage, validation, membership, assignment),
`CohortHooks.php` (enrolment, checkout and access hooks), `CohortSchema.php` (tables),
`includes/Rest/V1/CohortsController.php`. Screen: Commerce › Memberships › **Cohorts**
(`admin.php?page=ohmylms-commerce&tab=memberships&panel=cohorts`, `features/commerce/cohorts`). Test:
`tests/php/cohorts-integration.php` (`OHMYLMS_COHORTS_TEST=1`, 65 checks).

## What the legacy admin had

The recovered course settings had one cohort per cohort-based course (`CourseCohortSettings.jsx`: start, end,
enrolment deadline, capacity) stored in `ohmylms_cohorts`; nothing recorded who belonged to a cohort. The
legacy behaviour kept here: a cohort-based course can be bought only while a cohort is open (deadline and end
not passed) and has a seat (`templates/single-course/*`, `template-functions.php`), and lessons can drip from
the cohort start (`DripContent`). `Checkout::process_enrollment()` wrote a `cohort_id` column the enrolment
table does not have; that write is removed and membership now lives in its own table.

## Storage

- `ohmylms_cohorts` (installer table; `CohortSchema` adds `membership_id` and `enrollment_start` with dbDelta,
  option `ohmylms_cohorts_schema`): `course_id` (0 for a plan cohort), `membership_id` (0 for a course cohort),
  `title`, `start_date`, `end_date`, `enrollment_start`, `enrollment_end`, `has_capacity`/`capacity`, `status`
  (`active`/`archived`; older rows without a status count as active), `meta` (JSON: `close_access_at_end`).
- `ohmylms_cohort_members`: `cohort_id`, `user_id` (unique pair), `source` (`purchase`, `enrolment`,
  `membership`, `manual`, `automation`, `sync`), `source_ref`, `joined_at`, `added_by`.
- Dates are site time. Creating a course cohort sets the course's `_type` to `cohort-based`.

## Rules

- **Window open** at a time: not archived, not before `enrollment_start`, not after `enrollment_end`, not after
  `end_date`. **Seat**: no capacity, or fewer members than the capacity (checked under a per-cohort `GET_LOCK`).
- **Automatic joining**: after `ohmylms_after_enrolled_student` (checkout, points purchase, order completion) the
  learner joins the earliest-starting open cohort with a seat of each enrolled course and, for a plan order
  (`_membership_id`), of the plan; after `ohmylms_manual_student_enrollment` the course cohort. A learner who
  already belongs to a cohort of that course or plan that has not ended is left alone.
- **Checkout and points**: a course that has cohorts but none open with a seat is refused at checkout
  (`ohmylms_after_checkout_validation`, error `ohmylms_cohort_enrolment_closed`) and for points
  (`ohmylms_point_purchase_course_error`). Manual enrolment is still allowed.
- **Access** (`ohmylms_is_lesson_locked`, priorities 9 and 11): the governing cohort of a learner and course is
  their course cohort, else the cohort of the plan that granted the course. Members cannot open lessons, quizzes
  or assignments before its start; with `close_access_at_end`, not after its end. Lessons that drip from the
  cohort start (`cohort-start`, `cohort-from-x-days`) use the member's own cohort instead of the course's first
  one; a lock set before the drip filter (unverified email) is kept. Non-members are unaffected; archived
  cohorts no longer govern access.

## Routes

All require `manage_options` (401 anonymous, 403 others, `ohmylms_cohort_forbidden`).

| Method | Path | Purpose |
| --- | --- | --- |
| GET | `/cohorts` | Paged list (`page`, `per_page` 1-100, `search`, `course_id`, `membership_id`, `status` any/active/archived) with `X-WP-Total`/`X-WP-TotalPages` |
| POST | `/cohorts` | Create (201 + `Location`) |
| GET / PATCH / DELETE | `/cohorts/{id}` | Read, partial update, delete (only when empty: 409 `ohmylms_cohort_has_members`) |
| GET / POST | `/cohorts/{id}/members` | Paged members (name and login, no email); add `{user_id}` (201) |
| DELETE | `/cohorts/{id}/members/{user_id}` | Remove (the enrolment is not touched) |
| POST | `/cohorts/{id}/sync` | `{confirm: true}`: add every eligible learner whose enrolment started inside the window and who is in no other cohort of that course or plan, until full; answers `{added, full, cohort}` |
| GET | `/cohorts/targets` | Course or plan picker (`scope`, `search`, 20 by title) |
| GET | `/cohorts/{id}/candidates` | Enrolled learners not yet members (20) |

**Fields** (create needs `scope`, `course_id` or `membership_id`, `title`, `start_date`): `title` 1-190 plain
text; dates `YYYY-MM-DD[THH:MM[:SS]]` or `null`; `end_date` after `start_date`; `enrollment_end` not after
`end_date`; `enrollment_start` before `enrollment_end`; `capacity` 1-100000 or `null` (not below the member
count); `status`; `close_access_at_end` boolean. `scope` and the target cannot change. Unknown fields: 400
`ohmylms_cohort_unknown_field`; rule failures: 422 `ohmylms_cohort_invalid` with `data.params`. Members: 404
`ohmylms_cohort_user_not_found`, 409 `ohmylms_cohort_not_enrolled` (not enrolled in the course / not holding
the plan), `_member_exists`, `_full`, `_archived`, `_busy`.

**Response**: `id, title, scope, course_id, membership_id, target_title, start_date, end_date, enrollment_start,
enrollment_end, capacity, member_count, seats_left, status, state (upcoming|running|ended), enrollment_open,
close_access_at_end, created_at`.

Hooks: `ohmylms_cohort_created`, `ohmylms_cohort_updated( $id, $fields )`, `ohmylms_cohort_deleted`,
`ohmylms_cohort_member_added( $id, $user_id, $source )`, `ohmylms_cohort_member_removed`.

## Not covered

The course page templates still read the cohort list through `CourseStore::get_cohort()` (first cohort shown);
seats shown there count course enrolments, not cohort members. Leaving a cohort or archiving it never changes
enrolments.
