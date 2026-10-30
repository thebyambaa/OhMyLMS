# Assignments REST API (`ohmylms/v1/assignments`)

Contract version 1. Server code: `includes/Rest/V1/AssignmentsController.php` (routes, schema,
validation). Storage is unchanged: the `ohmylms-assignment` post type (`OHMYLMS_ASSIGNMENT_CPT`), its
legacy meta keys (read by `includes/Data/Assignment.php` / `includes/DataStores/AssignmentStore.php`) and
the `ohmylms_assignment_attempts` table for learner submissions. The admin UI is
`assets/src/app/features/assignments`.

The legacy `ohmylms/v1/assignment` controller (`AssignmentController.php`) stays registered because the
legacy admin screens and `Schools\Service::grade()` still call it. New code uses this API.

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

Legacy UI (still shipped): `assets/src/features/learning/Assignment*` (list, editor, report and grading),
reached at `#/assignments`, `#/assignment-edit/:id`, `#/assignment-report/:id`,
`#/assignment-report/:id/grade-assignment/:studentId` and the Content Hub Assignments tab. Instructor emails
(`Emails/CreatorsEmail/AssignmentSubmitted.php`) still link to the legacy grading route.

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
| `submission_count`, `pending_count` | `ohmylms_assignment_attempts` | Read only, counted for the whole page in one grouped query. |

Numbers are stored as strings and flags as `'1'`/`'0'`, exactly as the legacy data class writes them.
Missing legacy meta is reported the way learners see it, for example `0` for an unset maximum score.

Submissions: each learner upload is one row (`ohmylms_assignment_attempts`, written by
`Assignment::submit_file_submission()`). `status` is `submitted` until graded and then `graded`. `result`
is the stored `passed`/`failed`; `score` is `null` until graded. `submitted_at` and `graded_at` are UTC.

## Grading

`PUT /assignments/{id}/submissions/{submission_id}` with `{ "score": 40, "feedback": "<p>Good.</p>" }`:

1. The submission must belong to this assignment (otherwise 404). `score` must be a whole number from 0 to
   `max_score` (400 for type or range, 422 `ohmylms_assignment_invalid_score` above the stored maximum).
   `feedback` goes through `wp_kses_post`; if it is omitted, the stored feedback is kept.
2. The assignment must be in a course with a valid chapter link (409 `ohmylms_assignment_not_in_course` /
   `ohmylms_assignment_placement_invalid`), because the legacy pipeline needs both.
3. The request goes through `AssignmentController::update_attempt_report()`, the same pipeline the legacy
   grading screen and `Schools\Service::grade()` use. The result is `passed` when `score >= pass_score`,
   and a pass completes the learner's progress. It fires `ohmylms_after_assignment_review` (the "assignment
   graded" email, automations, WP Fusion, completion awards), `ohmylms_pro_after_assignment_review`
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
- Download resources, prerequisites, drip settings and comments are not part of this API. The legacy
  editor still edits them, and this API leaves them untouched.
- Emails and the legacy grading route still identify submissions by student; their links still point to
  the legacy screens.
- The legacy `ohmylms/v1/assignment` routes keep their behaviour (see the audit) until the legacy screens
  are retired.

## Tests

`tests/php/assignments-rest-integration.php` (set `OHMYLMS_ASSIGNMENTS_TEST=1`; see the file header) and
`npx vitest run --configLoader runner features/assignments`.
