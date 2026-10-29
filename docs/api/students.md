# Students REST API (`ohmylms/v1/students`)

Contract version 2. The plugin is pre-release, so this replaces the old API outright; there is no
compatibility layer. The machine-readable form is `assets/src/app/features/students/contract.json`.
Server code: `includes/Rest/V1/StudentController.php` (directory, single student, block, course
choices) and `includes/Rest/V1/StudentProController.php` (enrolments). Test:
`tests/php/students-rest-integration.php`.

## Audit before

State of the code before this rewrite (both controllers extended the plugin base class but used
none of `WP_REST_Controller`: no schema, no `get_item`/`get_collection_params` contract).

| Severity | Finding |
| --- | --- |
| High | **Block endpoint was a collection `PATCH`.** `PUT/PATCH/POST /students` ran `ban_student()` on `ids[]`: any user ID was accepted, including administrators and the caller, and no user was checked for existence. `update_user_meta()` was written for ids that were not users. `/students/unban` had the same shape. |
| High | **Personal data over-exposure.** The list returned `billing_phone`, `whatsapp`, `timezone`, the avatar and the email of every student; the profile repeated them. The screens only needed name, email and counts. |
| High | **Two capabilities, undocumented.** Both controllers allowed `manage_options` **or** `manage_ohmylms`, a custom capability that is granted to non-administrators; student emails, enrolments and blocking were open to any holder. Enrolment (`CourseController::enroll_manually`) was open to every `edit_posts` user (contributors) and trusted a `student_id` from the body without checking the user. |
| Medium | **Validation.** `per_page` had no maximum (`per_page=100000`) or minimum (`0` gave a division by zero in `X-WP-TotalPages`); `order_by` and `order` silently fell back to defaults on bad input; `date_filter=custom` returned a `WP_Error` object that was then concatenated into SQL; `get_filter_query()` returned an error where a query string was expected. |
| Medium | **Wrong counts.** `courses_enrolled` used `COUNT(DISTINCT course_id)` over the same join that filtered by course, so filtering by course made every student show one course. The count query ignored the membership join differences. |
| Medium | **N+1 and unbounded work in the profile.** `get_item` ran an enrolment query, a progress query and four `Student` data-store calls (each a query) **per course**, with no pagination, and called `get_course_completed_points`/`get_course_total_points` twice more for `is_completed`. Per-student `get_user_meta` ran once per list row. |
| Medium | **Errors.** Plain codes (`invalid_user_id`, `user_not_found`, `invalid_filter`) with English strings that were not translatable; the profile answered a non-student with the details of any user; 404 vs 400 depended on the code path. |
| Medium | **Unenrolling left orphans.** `DELETE /courses/{id}/enroll/{student}` deleted the enrolment row but left `ohmylms_user_progress` rows, fired no hook, and reported success even when `$wpdb->delete()` returned `0`. |
| Low | `enroll_manually()` ignored the result of `$wpdb->insert()` and answered success; a double click created two enrolment rows (no unique key). |
| Low | The list carried an `X-WP-Course-Name` header; no schema, no `Link` headers, no `OPTIONS` support. |
| OK | Search used `$wpdb->esc_like()` and `prepare()`; `X-WP-Total` and `X-WP-TotalPages` existed. Cookie authentication: WordPress core verifies `X-WP-Nonce` in `rest_cookie_check_errors()` before any permission callback; no custom nonce code is needed. |

## Final routes

All routes require `manage_options` (`StudentController::CAPABILITY`, also used by
`StudentProController`). Anonymous callers get `401`, logged-in users without the capability get
`403` (`rest_authorization_required_code()`). Argument validation runs before the permission
callback (core behaviour), so a malformed request can answer 400 before 401/403.

| Method | Path | Purpose | Success | Errors |
| --- | --- | --- | --- | --- |
| GET | `/students` | Student directory (paginated) | 200 + `X-WP-Total`, `X-WP-TotalPages`, `Link` | 400, 401, 403 |
| GET | `/students/courses` | Course choices (filter, enrol form) | 200 + totals | 400, 401, 403 |
| GET | `/students/{id}` | One student | 200 | 401, 403, 404 |
| PUT / PATCH / POST | `/students/{id}` | Block or unblock (`blocked`) | 200 `Student` | 400, 401, 403, 404, 422, 500 |
| GET | `/students/{id}/enrollments` | Enrolments with progress | 200 + totals | 400, 401, 403, 404 |
| POST | `/students/{id}/enrollments` | Enrol (`course_id`) | 201 + `Location` | 400, 401, 403, 404, 409, 500 |
| DELETE | `/students/{id}/enrollments/{course_id}` | Unenrol and delete progress | 200 `{deleted, previous}` | 401, 403, 404, 500 |

Removed: `PATCH /students` (bulk ban by `ids`), `POST /students/unban`, and the old combined
profile on `GET /students/{id}` (now split into the student and its `enrollments`).

A **student** is a user who has an enrolment (`enrolled` or `banned`) or the role `subscriber`,
`ohmylms_student` or the role returned by `ohmylms_get_student_role()`. Any other user answers 404 on
the student routes. (`POST .../enrollments` accepts any existing user, because that is how a
new student becomes one.)

Collection arguments (`/students`): `page` (>= 1), `per_page` (1-100, default 20), `search` (name,
username or email, max 100 characters), `course` (course ID), `orderby`
(`name|email|registered|courses_enrolled`, default `name`), `order` (`asc|desc`, default `asc`).
`/students/{id}/enrollments`: `page`, `per_page`, `search` (course title), `progress`
(`all|running|completed`). `/students/courses`: `page`, `per_page`, `search`. A page beyond the last
page answers 400 `ohmylms_student_invalid_page` (`ohmylms_enrollment_invalid_page` for
enrolments).

## Examples

```http
GET /wp-json/ohmylms/v1/students?search=ada&per_page=20&orderby=registered&order=desc
X-WP-Nonce: <wp_rest nonce>

HTTP/1.1 200 OK
X-WP-Total: 1
X-WP-TotalPages: 1

[
  {
    "id": 12,
    "name": "Ada Lovelace",
    "email": "ada@example.com",
    "avatar_url": "https://secure.gravatar.com/avatar/...?s=96",
    "registered": "2026-01-05T10:00:00",
    "last_login": "2026-10-29T08:30:00",
    "blocked": false,
    "enrolled_courses": 2,
    "completed_courses": 1,
    "_links": { "self": [ { "href": ".../students/12" } ], "enrollments": [ { "href": ".../students/12/enrollments" } ] }
  }
]
```

Enrol:

```http
POST /wp-json/ohmylms/v1/students/12/enrollments
{ "course_id": 40 }

HTTP/1.1 201 Created
Location: https://example.test/wp-json/ohmylms/v1/students/12/enrollments

{
  "course_id": 40,
  "title": "Intro to Algebra",
  "status": "enrolled",
  "progress": "running",
  "enrolled_at": "2026-10-30T09:15:00",
  "completed_at": null,
  "lessons": { "completed": 0, "total": 4 },
  "quizzes": { "completed": 0, "total": 1 },
  "assignments": { "completed": 0, "total": 0 },
  "percent_complete": 0
}
```

Block:

```json
PATCH /students/12
{ "blocked": true }
```

Unenrol:

```json
DELETE /students/12/enrollments/40

200
{ "deleted": true, "previous": { "course_id": 40, "title": "Intro to Algebra", "...": "..." } }
```

Error body (WordPress shape):

```json
{ "code": "ohmylms_student_already_enrolled",
  "message": "The student is already enrolled in this course.",
  "data": { "status": 409 } }
```

## Error codes

| HTTP | Code | When |
| --- | --- | --- |
| 400 | `rest_invalid_param`, `rest_missing_callback_param` | Schema validation (types, enums, `per_page` > 100, `search` > 100 chars, missing `blocked` or `course_id`). `data.params` names the fields. |
| 400 | `ohmylms_student_invalid_page`, `ohmylms_enrollment_invalid_page` | `page` is past the last page. |
| 401 / 403 | `ohmylms_student_cannot_view`, `ohmylms_student_cannot_edit`, `ohmylms_enrollment_cannot_view` / `_create` / `_delete` | Not logged in (401) or missing `manage_options` (403). |
| 403 | `rest_cookie_invalid_nonce` | Cookie auth without a valid `X-WP-Nonce` (core). |
| 404 | `ohmylms_student_not_found` | Unknown user, or a user who is not a student. |
| 404 | `ohmylms_course_not_found` | Enrol: unknown course, not an `ohmylms-course`, trashed. |
| 404 | `ohmylms_enrollment_not_found` | Unenrol: no such enrolment. |
| 409 | `ohmylms_student_already_enrolled` | Enrol: a row exists (any status), including the loser of two simultaneous requests. |
| 409 | `ohmylms_student_blocked` | Enrol: the student is blocked. |
| 422 | `ohmylms_student_protected` | Block: the target is an administrator or the current user. |
| 500 | `ohmylms_student_update_failed`, `ohmylms_enrollment_create_failed`, `ohmylms_enrollment_delete_failed` | Database failure. |

## Schema

Served by `OPTIONS /students` (`title: student`) and `OPTIONS /students/{id}/enrollments`
(`title: student-enrollment`), JSON Schema draft 4.

`student`: `id`, `name`, `email`, `avatar_url`, `registered` and `last_login` (site time, string or
null), `blocked` (boolean, the only writable field), `enrolled_courses`, `completed_courses`.
`student-enrollment`: `course_id`, `title`, `status` (`enrolled|banned`), `progress`
(`running|completed`), `enrolled_at`, `completed_at`, `lessons`/`quizzes`/`assignments`
(`{completed, total}`), `percent_complete` (0-100).

Item progress: completed items are counted from `ohmylms_user_progress` (`text`, `video`, `audio` and
`session` content as lessons) and capped at the course total; a completed enrolment reports 100.

## Security notes

- **Authorisation.** `manage_options` on every route through one constant. The legacy `manage_ohmylms`
  capability no longer opens student data. CSRF: core's `rest_cookie_check_errors()` rejects
  cookie-authenticated requests without a valid `X-WP-Nonce`.
- **Personal data.** Responses carry only the fields in the schema above. No password hash,
  activation key, session token, phone, WhatsApp or timezone. The list is capped at 100 rows per
  request. `email` is visible to anyone allowed to read students (administrators).
- **SQL.** One prepared query with `%i` identifiers for the page and one for the count; `orderby` is
  an allow-list map, `order` is forced to `ASC`/`DESC`, search is `esc_like()`-escaped. Enrolment
  counts come from one aggregate join, not a query per row; user meta for the page is primed with
  one `update_meta_cache()` call.
- **Enrolments.** Enrolling validates the user, the course post type and status, the blocked flag
  and duplicates, and rolls back its own row if a concurrent request created another. Unenrolling
  deletes the enrolment and its progress rows (a deliberate hard delete; the UI says so before it
  proceeds). There is no unique key on `(user_id, course_id)`; the duplicate check is best effort
  under concurrency.
- **Progress cost.** The enrolment list computes course totals with `Course::get_lessons_count()`,
  `get_quiz_count()` and `get_assignment_count()` (statically cached, three queries per distinct
  course) plus one progress query per page, so a page costs at most `per_page * 3 + 2` queries.
- **Reminder e-mails.** `POST /notification/course/{c}/student/{s}` (NotificationController) accepts
  any recipient `email`, `subject` and HTML `message` from the caller and sends them. That is an open
  mail relay for its callers; the Students screen deliberately does not use it. Rebuild it to derive
  the recipient from the student and course before adding a reminder action.

## Hooks

Kept: `ohmylms_manual_student_enrollment( $user_id, $course_id )` fires after a successful enrol
(also fired by `CourseController` and the gradebook). `ohmylms_rest_v1_controllers` still registers
both controllers.

New: `ohmylms_student_unenrolled( $user_id, $course_id )` (it was listened to by the WP Fusion
integration but never fired), `ohmylms_student_blocked( $user_id )` and
`ohmylms_student_unblocked( $user_id )`.

## Authored admin preview

`#/students` (list: search, course filter, sort, paging) and `#/students?student=<id>` (profile:
enrolments with progress, enrol, unenrol, block/unblock) in
`assets/src/app/features/students/`. The profile uses a query parameter because the app shell
registers one exact path per feature.
