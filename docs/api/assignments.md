# Assignments REST API (`ohmylms/v1/assignments`)

Contract version 1. Server code: `includes/Rest/V1/AssignmentsController.php` (routes, schema,
validation). Storage is unchanged: the `ohmylms-assignment` post type (`OHMYLMS_ASSIGNMENT_CPT`), its
legacy meta keys (read by `includes/Data/Assignment.php` / `includes/DataStores/AssignmentStore.php`) and
the `ohmylms_assignment_attempts` table for learner submissions. The admin UI is
`assets/src/app/features/assignments`.

The legacy `ohmylms/v1/assignment` routes were removed on 31 October 2026 (see
[Removed legacy routes](#removed-legacy-routes)). `AssignmentController.php` remains only as the grading
pipeline (`update_attempt_report()`, no routes) that this API and `Schools\Service::grade()` call.

## Audit before

What existed before this API, and what it did wrong.

| Severity | Finding |
| --- | --- |
| High | **No author scoping.** `GET /assignment` needed only `edit_posts`, and `GET /assignment/{id}` the post-type `read_post`, so any author or contributor could list every assignment and its submission counts. Now authors without `edit_others_posts` list only their own, and every item route checks `edit_post` on the assignment. |
| High | **Reports exposed learner emails.** `GET /assignment/{id}/report` and `/report/{user_id}` returned `user_email`, the raw `files` array (absolute server path and public upload URL) and every attempt row. Now the submission list returns only the student's ID and display name. The detail returns files only through an authorised download route, after checking that the stored path is a real file inside the uploads directory. |
| High | **Grading could fatal.** `update_attempt_report()` called `$course->get_id()` without checking the course, so grading an assignment that was not in a course caused a fatal error. A passing grade also fataled in `StudentStore::complete_lesson()` when the chapter link had no `content_type = 'assignment'` (links written by `AssignmentHookHandler` have none). Now both cases return 409 before anything is written. |
| High | **Grading input unvalidated.** Score was neither bounded nor checked as a whole number, the note was stored without sanitising, and the body was an array of rows keyed by attempt ID. `PUT /assignment/{id}/report/{user_id}` used the edit capability but accepted any `status`. |
| High | **Deletion lost learner work.** `DELETE /assignment/{id}` force-deleted the post. `trash-bulk` trashed it. Both fired `ohmylms_rest_delete_assignment`, which also unlinks chapters and deletes progress, with submissions present. Now any delete answers 409 `ohmylms_assignment_has_submissions` while submissions exist. |
| Medium | **Broken or wrong writes.** `set_assignment_meta()` stored `content` as the post *status*. `POST /assignment/content/save/{id}` called `LessonHelper::save_assignment()`, which does not exist. `AssignmentStore::update()` rewrote the slug from the title on every save. Update arguments reused `get_collection_params()`, so types were never checked (`total_points: "abc"` was stored). |
| Medium | **Raw query vars.** `get_items` copied `filter[]`, `meta_*`, `post_status` and parent arguments into `WP_Query` through a WooCommerce-derived allow-list (`woocommerce_rest_query_var-*` hooks). `per_page` had no maximum. The course filter ran *after* paging, so `X-WP-Total` described the unfiltered set. |
| Medium | **N+1 queries.** Each listed assignment loaded the whole attempts report to count submissions, and resolved its course one query at a time. |
| Medium | **No concurrency check.** Two editors overwrote each other silently. Now `base_modified` gives 409 `ohmylms_assignment_conflict`, and writes run in one `Transaction`. |
| Low | Errors used numeric codes (`new WP_Error( 400, … )`) or misspelt codes (`ohmylms_rest_invalid_assignemnt_id`). There was no item schema, and new assignments were created published (the data store hard-codes `publish`) regardless of `publish_posts`. |
| OK | Cookie authentication (core verifies `X-WP-Nonce`). Prepared SQL with `%i`. The learner upload path (`Ajax::save_assignment_submission_file`) checks a nonce, the MIME allow-list, the file count and the size. |

The legacy UI (`assets/src/features/learning/Assignment*`) was deleted at the cutover; its hash routes
(`#/assignment-edit/:id`, `#/assignment-report/:id`, `#/assignment-report/:id/grade-assignment/:studentId`)
now redirect to the screens of this API (`includes/Admin/LegacyRoutes.php`).

## Final routes

| Method | Path | Purpose | Success | Errors |
| --- | --- | --- | --- | --- |
| GET | `/assignments` | List, paged | 200 + `X-WP-Total`, `X-WP-TotalPages`, `Link` | 400, 401, 403 |
| POST | `/assignments` | Create. Only `title` is required; starts as a draft with the legacy defaults | 201 + `Location` | 400, 401, 403, 422, 500 |
| GET | `/assignments/courses` | Courses for the list filter (the user's own unless they edit others' posts) | 200 + totals | 400, 401, 403 |
| GET | `/assignments/{id}` | Read. `context=edit` adds `instructions` and `placements` | 200 | 401, 403, 404 |
| PUT / PATCH / POST | `/assignments/{id}` | Partial update; optional `base_modified` | 200 (edit context) | 400, 401, 403, 404, 409, 422, 500 |
| DELETE | `/assignments/{id}` | Move to the trash; `force=true` (or an already trashed item) deletes permanently | 200 `{deleted, trashed, previous}` | 401, 403, 404, 409, 500 |
| GET | `/assignments/{id}/submissions` | Submissions, newest first, paged | 200 + totals | 400, 401, 403, 404 |
| GET | `/assignments/{id}/submissions/{submission_id}` | One submission with text, feedback and attachments | 200 | 401, 403, 404 |
| PUT / PATCH / POST | `/assignments/{id}/submissions/{submission_id}` | Grade: `score` (required) and `feedback` | 200 (the graded submission) | 400, 401, 403, 404, 409, 422, 500 |
| GET | `/assignments/{id}/submissions/{submission_id}/file` | Download the upload (`Content-Disposition: attachment`, `nosniff`, `no-store`) | 200 file | 401, 403, 404 |
| GET | `/assignments/{id}/prerequisite-options` | Lessons, quizzes and assignments it may require: `search`, `page` (20 per page) | 200 `{items, total, pages, scope}` | 400, 401, 403, 404 |
| GET | `/assignments/{id}/learner-state` | What the current user may do now (release, prerequisites, submit) | 200 | 401, 404 |
| GET | `/assignments/{id}/submissions/{submission_id}/comments` | The submission's comment thread, oldest first (`page`, `per_page` ≤ 100) | 200 `{items, total, role, comments_enabled, can_comment}` | 401, 403, 404 |
| POST | `/assignments/{id}/submissions/{submission_id}/comments` | Add a comment `{body}` (plain text, 1–5000) | 201 | 400, 401, 403, 404, 409, 422, 429, 500 |
| DELETE | `/assignments/{id}/submissions/{submission_id}/comments/{comment_id}` | Delete a comment | 200 `{deleted, previous}` | 401, 403, 404, 500 |

The last five routes live in `includes/Rest/V1/AssignmentDeliveryController.php`; the services are
`includes/Assignment/Delivery.php` (settings and the learner gate) and `includes/Assignment/Comments.php`.

Capabilities mirror the legacy routes:

- Listing, the course picker and creating need the post type's `edit_posts`. Creating or setting
  `status: publish` also needs `publish_posts` (403 `ohmylms_assignment_cannot_publish`).
- Reading and updating need `edit_post` on the assignment. Deleting needs `delete_post`.
- Submissions and grading need `edit_post` on the assignment, the same rule as the legacy report and
  grading routes.

Anonymous callers get 401, and logged-in users without the capability get 403. Item routes check the ID
first, so an unknown ID or another post type answers 404 before the capability check.

Collection arguments: `page`, `per_page` (1–100, default 20), `search`, `status`
(`all|publish|draft|trash`; `all` excludes the trash), `course`, `orderby`
(`id|title|status|created_at|updated_at`), `order`, `context`. The `course` filter matches chapter
placements and published learning programs. Submission arguments: `page`, `per_page` (1–100), `status`
(`all|submitted|graded`), `course`. A page past the end answers 400 `ohmylms_assignment_invalid_page`.

## Fields and storage

| Field | Meta / column | Rules |
| --- | --- | --- |
| `title` | `post_title` | Plain text, 1–200 characters. |
| `instructions` (edit) | `post_content` | `wp_kses_post`. This is what the learner page renders. |
| `status` | `post_status` | `draft`, `publish`. `trash` is read only; sending a status restores a trashed item. `future`/`private` read as `publish`, `pending` as `draft`. |
| `max_score` | `_total_points` | Integer 1–1000 (scores live in an INT column). |
| `pass_score` | `_maximum_pass_points` | Integer 0–1000 and at most `max_score`. This is checked against the stored values merged with the request. |
| `time_limit_enabled`, `time_limit`, `time_limit_unit` | `_enable_time_limit`, `_time_limit`, `_time_limit_type` | Flag; 1–365; `day`, `week` or `month` (stored lower case; legacy `Month`/`days` are read back normalised). |
| `allow_file_uploads`, `max_files` | `_allow_upload_files`, `_number_of_files` | Flag (a missing value means allowed, as the learner page treats it); 1–20. |
| `file_size_limit_enabled`, `max_file_size_mb` | `_enable_file_size_limit`, `_max_file_size_limit` | Flag; 1–1024 MB. |
| `course`, `placements` (edit) | `ohmylms_content_relationship` and programs | Read only. Placement is edited in the course editor. |
| `resources` (edit) | `_download_resource` `{file: [{type, id, name, url, size}]}` | Up to 20 rows `{type: file\|link, attachment_id, name, url, size}`. Files are media library attachments; URL and size come from the library (the request's are ignored); a newly added file must be editable by the author. Links: absolute HTTP(S) URL ≤ 2048, size label ≤ 50. Names 1–200. |
| `prerequisites` (edit) | `_prerequisites` `{enable, data: [{value, label}]}` | Read `{enable, items: [{id, title, type, status}]}` (`status` `missing` when removed); write `{enable, ids}`: up to 50 distinct lessons, quizzes or assignments other than itself; newly added ones must be editable by the author. |
| `drip` (edit) | `_drip_settings` `{enable, type, days, date, time}` | Exactly those keys; `type` `enrollment-from-x-days`, `specific-date`, `cohort-start`, `cohort-from-x-days`; `days` 0–3650; `date` empty or a real `YYYY-MM-DD`; `time` empty or `HH:MM` (stored `HH:MM:SS`); a specific date needs the date. Site time. |
| `comments_enabled` (edit) | `_enable_comments` (`'1'`/`'0'`) | Boolean: submission comment threads accept new comments. |
| `submission_count`, `pending_count` | `ohmylms_assignment_attempts` | Read only, counted for the whole page in one grouped query. |

Numbers are stored as strings and flags as `'1'`/`'0'`, exactly as the legacy data class writes them.
Missing legacy meta is reported the way learners see it, for example `0` for an unset maximum score.

Submissions: each learner upload is one row (`ohmylms_assignment_attempts`, written by
`Assignment::submit_file_submission()`). `status` is `submitted` until graded and then `graded`. `result`
is the stored `passed`/`failed`; `score` is `null` until graded. `submitted_at` and `graded_at` are UTC.

## Delivery settings and the learner gate

The editor's Resources, Prerequisites, Release and Comments sections save `resources`,
`prerequisites`, `drip` and `comments_enabled` with the other fields (same `base_modified` check and
transaction; an invalid value answers 422 `ohmylms_assignment_invalid_resources` /
`_invalid_resource_file` / `_invalid_prerequisites` / `_invalid_drip` with `data.field`, and nothing is
written). The prerequisite picker searches `GET /assignments/{id}/prerequisite-options`: the content of the
assignment's courses (`scope: course`), or the author's library when it is in no course yet (`scope:
library`).

`Assignment\Delivery::state()` (also `GET /assignments/{id}/learner-state` for the current user) gives
`{course_id, can_submit, reason, message, opens_at, opens_label, prerequisites: [{id, title, type, status,
url, completed}], comments_enabled, preview}`. `reason` is `not_published`, `not_in_course`,
`not_enrolled`, `not_open` (the drip release has not come; `opens_at` UTC) or `prerequisites` (an item is
not completed). People who can edit the assignment preview without these gates.

- **Server-side gate.** The learner upload handler (`Ajax::save_assignment_submission_file`) calls
  `Delivery::check_submission()` before storing any file and answers `{success: false, data: {code:
  "ohmylms_assignment_<reason>", message}}`. The course of a submission is now resolved on the server
  (the posted `course_id` is only a fallback for previews).
- **Drip.** Release dates are worked out by `Learning\DripSchedule` (shared with lessons and quizzes;
  `DripContent` uses it): enrolment start + N days, a date and time, or the course's first cohort. Until it
  opens the assignment page shows "This assignment will be available on …" instead of the content.
  Legacy `YYYY-MM-DDTHH:mm:ss` date/time values are read; a date without a time opens at midnight.
- **Prerequisites** block submitting, not reading (they used to hide the page): the page lists them with
  links and completion state, the Start button is disabled with the reason, and the server refuses the
  submission. `CommonHook::restrict_access()` and `Ajax::lesson_access_nonce()` no longer gate assignment
  pages on prerequisites (lesson prerequisites are unchanged).
- **Resources** are listed on the assignment page: files with a download link to the library file, links
  opening in a new tab. Library files are public URLs, as for lesson resources.

## Comments

One thread per submission in `{prefix}ohmylms_assignment_comments` (`id, submission_id, assignment_id,
user_id, role, body, created_at` UTC; created on first use, option `ohmylms_assignment_comments_schema`).
Only the submission's learner (`role: learner`) and people with `edit_post` on the assignment
(`role: instructor`) read or write it; anyone else gets 403 (401 for guests), an unknown or mismatched
submission 404. Comments are plain text (`sanitize_textarea_field`, 1–5000 characters). Posting needs
`comments_enabled` (409 `ohmylms_assignment_comments_disabled`; existing threads stay readable) and one
comment per author and submission every 5 seconds (429 `ohmylms_assignment_comment_rate_limited`).
Instructors may delete any comment, others only their own.

Each new comment fires `ohmylms_assignment_comment_added` ($comment, $submission, $assignment_id). The
two configurable emails react to it: `student_assignment_comment` (instructor comment → the learner, with
a link to the submission on the assignment page) and `instructor_assignment_comment` (learner comment → the
configured staff addresses, else the assignment and course authors, with a link to the grading screen);
the commenter is never emailed. Learners comment from the assignment page (`Your submissions`), a plain
form posting to `admin-post.php?action=ohmylms_assignment_comment` with a per-submission nonce; it returns
to the submission with `ohmylms_comment=added` or the error code. Instructors comment on the grading screen.

## Grading

`PUT /assignments/{id}/submissions/{submission_id}` with `{ "score": 40, "feedback": "<p>Good.</p>" }`:

1. The submission must belong to this assignment (otherwise 404). `score` must be a whole number from 0 to
   `max_score` (400 for type or range, 422 `ohmylms_assignment_invalid_score` above the stored maximum).
   `feedback` goes through `wp_kses_post`; if it is omitted, the stored feedback is kept.
2. The assignment must be in a course with a valid chapter link (409 `ohmylms_assignment_not_in_course` /
   `ohmylms_assignment_placement_invalid`), because the legacy pipeline needs both.
3. The request goes through `AssignmentController::update_attempt_report()`, the same pipeline the legacy
   grading screen and `Schools\Service::grade()` use. The result is `passed` when `score >= pass_score`,
   and a pass completes the learner's progress. It fires `ohmylms_after_assignment_review( $assignment_id,
   $course_id, $student_id, $status, $attempt_id )` once (the "assignment graded" email, the `assignment_reviewed`
   automation event, WP Fusion, completion awards), `ohmylms_pro_after_assignment_review`
   (leaderboard), and course-completion hooks when applicable.
4. The row is read back. If it did not persist, the route answers 500 `ohmylms_assignment_grade_failed`.

Regrading is allowed and runs the pipeline again (including the email), as in the legacy screen.

## Examples

```http
PATCH /wp-json/ohmylms/v1/assignments/31
X-WP-Nonce: <wp_rest nonce>
Content-Type: application/json

{ "max_score": 80, "time_limit_enabled": true, "time_limit": 2, "time_limit_unit": "week",
  "base_modified": "2026-10-30T09:20:00Z" }
```

```json
{ "id": 31, "title": "Essay on rivers", "slug": "essay-on-rivers", "status": "publish",
  "permalink": "https://example.test/assignment/essay-on-rivers/", "author_id": 3, "author_name": "Sam",
  "course": { "id": 12, "title": "Geography" }, "instructions": "<p>Write 500 words.</p>",
  "placements": [ { "course_id": 12, "course_title": "Geography", "chapter_id": 40, "chapter_title": "Rivers", "source": "chapter" } ],
  "max_score": 80, "pass_score": 25, "time_limit_enabled": true, "time_limit": 2, "time_limit_unit": "week",
  "allow_file_uploads": true, "max_files": 5, "file_size_limit_enabled": true, "max_file_size_mb": 5,
  "submission_count": 4, "pending_count": 1, "created_at": "2026-10-30T09:15:00Z",
  "updated_at": "2026-10-30T09:31:02Z", "can_edit": true, "can_delete": true }
```

Submission row (`GET /assignments/31/submissions`):

```json
{ "id": 501, "assignment_id": 31, "student": { "id": 9, "display_name": "Alex" },
  "course": { "id": 12, "title": "Geography" }, "status": "submitted", "result": null, "score": null,
  "max_score": 80, "pass_score": 25, "submitted_at": "2026-10-30T10:00:00Z", "graded_at": null,
  "attachment_count": 1 }
```

The detail adds `content` (plain text as the learner typed it), `feedback`, and
`attachments: [ { "name": "essay.pdf", "type": "application/pdf", "size": 20480, "url": ".../assignments/31/submissions/501/file?_wpnonce=…" } ]`.

## Error codes

| HTTP | Code | When |
| --- | --- | --- |
| 400 | `rest_invalid_param`, `rest_missing_callback_param` | Schema validation (types, enums, ranges, required `title` / `score`). |
| 400 | `ohmylms_assignment_invalid_page` | `page` past the last page. |
| 401 / 403 | `ohmylms_assignment_cannot_view` / `_create` / `_publish` / `_edit` / `_delete` / `_grade` | Not logged in (401) or missing capability (403). |
| 404 | `ohmylms_assignment_not_found`, `_submission_not_found`, `_file_not_found` | Unknown ID or another post type; a submission of another assignment; no servable upload. |
| 409 | `ohmylms_assignment_conflict` | `base_modified` is older than the stored copy (`data.modified`). |
| 409 | `ohmylms_assignment_has_submissions` | Delete while submissions exist (`data.submissions`). |
| 409 | `ohmylms_assignment_not_in_course`, `_placement_invalid` | Grading an assignment the legacy pipeline cannot complete. |
| 422 | `ohmylms_assignment_invalid_title`, `_invalid_pass_score`, `_invalid_score` | Blank title, pass above max, score above max (`data.field`). |
| 422 | `ohmylms_assignment_invalid_resources`, `_invalid_resource_file`, `_invalid_prerequisites`, `_invalid_drip`, `_invalid_comment` | Delivery settings or a comment that break the rules above (`data.field`). |
| 403 / 401 | `ohmylms_assignment_cannot_comment`, `_cannot_delete_comment` | Not the submission's learner nor an instructor; not the comment's author. |
| 404 | `ohmylms_assignment_comment_not_found` | Unknown comment, or one of another submission. |
| 409 | `ohmylms_assignment_comments_disabled` | Posting while comments are switched off. |
| 429 | `ohmylms_assignment_comment_rate_limited` | A second comment by the same person on one submission within 5 seconds. |
| 500 | `ohmylms_assignment_create_failed`, `_update_failed`, `_delete_failed`, `_grade_failed` | Database failure; a failed create or update is rolled back. |

## Hooks kept

Actions `ohmylms_after_creating_new_assignment` ($id, $assignment), `ohmylms_after_updating_assignment`
($id, $assignment), `ohmylms_rest_insert_assignment` ($post, $request, $creating; `chapter_id` is removed
from the request so this API cannot link chapters), and `ohmylms_rest_delete_assignment` ($id; unlinks
chapters and progress, as before). Data-store `ohmylms_after_deleting_a_assignment` still fires on
permanent delete. Filters `ohmylms_rest_prepare_assignment` ($response, $post, $request) and
`ohmylms_rest_ohmylms_assignment_query` ($args, $request; now a fixed, validated query). Grading fires the
legacy grading hooks listed above. Learner submission (`ohmylms_assignment_submitted`) is unchanged.

## Known gaps

- Placement is read only. Assignments are added to chapters in the course editor.
- The learner's submit and comment forms are server-rendered; the upload widget is still the jQuery
  `ohmylms.js` script (roadmap item 6). Comments are plain text without attachments or editing.
- Cohort releases use the course's first cohort, as before; per-cohort dates are not modelled.
- The grading pipeline still lives in `AssignmentController::update_attempt_report()` (a class without routes)
  and takes a constructed `WP_REST_Request`; extracting it into a service is in
  [BACKEND-BOUNDARIES](../BACKEND-BOUNDARIES.md).

## Removed legacy routes

Removed on 31 October 2026 with `AssignmentController`'s registration in `Hooks\ApiHook` (they answer 404
`rest_no_route`). No code called them (the legacy screens were deleted at the cutover; `Schools\Service` and
this API call the grading method directly), and neither `docs/EXTENSIONS.md` nor the add-ons documented them.

| Removed | Use instead |
| --- | --- |
| `GET|POST /assignment` | `GET|POST /assignments` |
| `DELETE /assignment/trash-bulk` | `DELETE /assignments/{id}` per assignment |
| `GET|PUT|POST|DELETE /assignment/{id}` | `/assignments/{id}` |
| `GET /assignment/{id}/report`, `/report/{user_id}` | `GET /assignments/{id}/submissions[/{submission_id}]` |
| `PUT /assignment/{id}/report/{user_id}` | `PUT /assignments/{id}/submissions/{submission_id}` |
| `POST /assignment/content/save/{id}` (called a missing method) | `PATCH /assignments/{id}` (`instructions`) |

## Tests

`tests/php/assignments-rest-integration.php` (set `OHMYLMS_ASSIGNMENTS_TEST=1`; see the file header; it also
checks that the legacy routes are gone and the grading pipeline stays),
`tests/php/assignment-delivery-integration.php` (`OHMYLMS_ASSIGNMENT_DELIVERY_TEST=1`: delivery settings
save/reopen and storage, validation without writes, permissions, prerequisite choices, the learner state
and the real upload handler refusing submissions before the release and with an incomplete prerequisite,
comment threads, permissions, pace, deletion, the switch and both emails) and
`npx vitest run --configLoader runner features/assignments`.
