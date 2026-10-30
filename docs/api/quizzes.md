# Quizzes REST API (`ohmylms/v1/quizzes`)

Contract version 1. The plugin is pre-release, so this replaces the old list, report and bulk-trash routes of
`ohmylms/v1/quiz` outright. The machine-readable form is `assets/src/app/features/quizzes/contract.json`.
Server code: `includes/Rest/V1/QuizController.php` (routes, schema), `includes/Quiz/QuizSettings.php`
(settings mapping and validation), `includes/Quiz/GradingPolicy.php` (who may grade),
`includes/Quiz/Review.php` (the transactional grade writer) and `includes/Rest/V1/AttemptController.php`
(the learner's autosave and resume routes).

## Audit before

| Severity | Finding |
| --- | --- |
| High | **Report and grading routes were authorised by `edit_post` on the quiz**, so any editor of the quiz saw every student's name, email and answers and could change marks. Grading is now limited to administrators, the quiz author and the author of the course containing the quiz (`GradingPolicy`), and `Review::save()` repeats the check, so no other caller can bypass it. |
| High | **`GET /quiz/{id}/report` returned every attempt unpaginated**, with `GROUP_CONCAT` of all answers per attempt (an N-row, unbounded response with answer text and student emails). Replaced by a paged attempts list that carries only id, name and email of the student plus scores. |
| High | **Unvalidated `settings` blob**: `PUT /quiz/{id}` stored any array. Negative or huge time limits, passing grades, attempt counts and unordered availability dates were accepted. The new route validates the whole request before the first write. The old route now requires an object but is only the question editor's raw channel. |
| High | **Legacy `POST /quiz` could link a quiz to any chapter**: the `chapter_id` parameter reached the `ohmylms_rest_insert_quiz` hook without a permission check. The authoring route now requires an existing chapter the user can edit; the new routes strip `chapter_id` before the hook. |
| High | **Permanent delete of quizzes with attempts**: `DELETE /quiz/{id}` called `wp_delete_post( id, true )` and orphaned attempt rows. Delete now trashes, and a permanent delete is refused with 409 while attempts exist. |
| Medium | **Wrong status codes and codes**: unknown ids answered 400 or 404 inconsistently; `create_item` returned `new WP_Error( 400, ... )` (a numeric code) for a `DataException`; the permission callbacks returned `ohmylms_forbidden` without telling guests (401) from users (403) for grading. |
| Medium | **List query**: raw `filter`, `meta_query`, `tax_query`, `post_parent*` and `offset` were merged into `WP_Query` through a copied "valid vars" list; `per_page` had no maximum; the course filter, `course_name` and `number_of_submissions` sorting ran in PHP over the current page after pagination, so totals and pages were wrong; capability was filtered after the query, so totals counted quizzes the user could not see; each row ran `get_report()` (all attempts, all answers) and course lookups (N+1). Now: whitelisted arguments, SQL-level scoping, one grouped query each for attempts, questions and courses. |
| Medium | **Slug rewritten on every update**: `QuizStore::update()` sets `post_name` from the title. The new update path does not use that method. The legacy `PUT /quiz/{id}` still does (`QuizStore.php` is outside this change; see decisions). |
| Medium | **Grading accepted client marks without a clear contract**: bounds were checked inside the transaction with a generic 400. The request shape is now typed, questions must belong to the attempt, duplicates are rejected, marks above the frozen maximum answer 422 with `question_id` and `max`, and any client-supplied total is refused. Totals are recomputed from the answer rows by `Review::save()`. |
| Medium | **No audit of who graded**: legacy attempts had no grade events. Grading is now only allowed on versioned attempts (409 `ohmylms_attempt_not_versioned` otherwise), whose grade events store reviewer, reason and time. |
| Medium | **AttemptController (learner autosave)**: `question_id`, `sequence` and the id had no ranges, `response` had no declared type and its 64 KB limit was only checked after sanitising; a deleted attempt between permission check and handler caused a fatal on `null['status']`. Now: ranges, a declared `response` type with a size validator, and 404 for a vanished attempt. |
| Low | No `Location` header on 201; deletes returned `{status, message}`; no item schema; `get_item` had an unreachable second `return`. |
| OK | Cookie authentication: core verifies `X-WP-Nonce` before any permission callback. Prepared SQL with `%i` for tables everywhere. AttemptController returns 404 (not 403) for another learner's attempt and never reveals correctness. |

## Final routes

Argument validation runs before the permission callback (core behaviour), so a malformed request can answer
400 before 401/403. Guests get `401`, logged-in users without rights `403`.

| Method | Path | Who | Success | Errors |
| --- | --- | --- | --- | --- |
| GET | `/quizzes` | `edit_posts`; rows the user wrote or that are in a course they wrote (all quizzes with `edit_others_posts`) | 200 + `X-WP-Total`, `X-WP-TotalPages`, `Link` | 400, 401, 403 |
| POST | `/quizzes` | `edit_posts` (`publish_posts` to publish) | 201 + `Location` (edit context, draft) | 400, 401, 403, 422, 500 |
| GET | `/quizzes/courses` | as list | 200 + totals (max 50 per page) | 400, 401, 403 |
| GET | `/quizzes/{id}` | `edit_post` or may grade (`context=edit` adds description, settings, total marks) | 200 | 401, 403, 404 |
| PUT / PATCH / POST | `/quizzes/{id}` | `edit_post` | 200 (edit context) | 400, 401, 403, 404, 409, 422, 500 |
| DELETE | `/quizzes/{id}` | `delete_post`; `?force=true` or an already trashed quiz is deleted for good | 200 `{deleted, trashed, previous}` | 401, 403, 404, 409, 500 |
| GET | `/quizzes/{id}/attempts` | may grade | 200 + totals | 400, 401, 403, 404 |
| GET | `/quizzes/{id}/attempts/{attempt_id}` | may grade | 200 | 401, 403, 404 |
| POST | `/quizzes/{id}/attempts/{attempt_id}/grades` | may grade | 200 attempt + `changed` | 400, 401, 403, 404, 409, 422, 500 |

Question-authoring routes kept under `ohmylms/v1/quiz` (used by the question editor, exam import and
activity drafts; unchanged response shapes, `edit_post` checks): `POST /quiz`, `GET|PUT|POST /quiz/{id}`,
`GET|PUT /quiz/{id}/content`, `DELETE /quiz/{id}/questions/{question_id}`, `GET /quiz/{id}/export`,
`POST /quiz/import`, `PUT /quiz/{id}/skills`. Removed: `GET /quiz`, `GET|POST /quiz/contents`,
`DELETE /quiz/trash-bulk`, `DELETE /quiz/{id}`, `GET /quiz/{id}/report`,
`GET|PUT /quiz/{id}/report/{attempt_id}`, `POST /quiz/{id}/report/{attempt_id}/{answer_id}`.

Who may grade (`GradingPolicy::can_grade`): `manage_options`; or the quiz's `post_author` with `edit_post`;
or the `post_author` of the course that contains the quiz through a chapter, with `edit_post` on the course.

## Quiz

```json
{ "id": 11, "title": "Fractions check", "slug": "fractions-check", "status": "publish",
  "permalink": "https://example.test/quiz/fractions-check/", "author_id": 2, "author_name": "Ada Author",
  "course": { "id": 5, "title": "Maths 101" }, "question_count": 4, "attempt_count": 3,
  "pending_review_count": 1, "created_at": "2026-10-30T09:00:00Z", "updated_at": "2026-10-30T09:30:00Z",
  "can_edit": true, "can_delete": true, "can_grade": true,
  "native_editor_url": "https://example.test/wp-admin/post.php?post=11&action=edit" }
```

`context=edit` adds `description`, `total_marks` and `settings`:

```json
{ "time_limit_minutes": 30, "passing_score": 60, "attempts_allowed": 2, "randomize_questions": false,
  "show_correct_answers": true, "layout": "one_question_per_page", "questions_per_page": 1,
  "feedback_mode": "immediate", "available_from": null, "available_until": "2026-12-31T09:00:00Z" }
```

Settings are stored without renaming any key, so learner pages, the quiz player, attempts, grading and
frozen revisions keep reading them: `time_limit.value/type`, `passing_grade.enabled/value`,
`allow_attempts`, `randomize_questions`, `show_correct_answers` (and `hide_answers`, kept in agreement),
`layout`, `question_in_one_page` in `_quiz_settings`, and `feedback_release`, `available_from`,
`available_until` in `_ohmylms_assessment_settings`. Unknown keys in the blob are preserved.

- `passing_score` (0-100; 0 disables) is compared with the learner's **marks**, as the learner flow
  (`Quiz::get_passing_grade()`) has always done, not with a percentage.
- `attempts_allowed` 0 means "unset": the learner flow then allows one attempt.
- Dates are UTC ISO 8601; `available_until` must be after `available_from`, checked against the merged
  stored and submitted values.
- Updates validate the whole request, then write inside one transaction, so a rejected request changes
  nothing. `base_modified` (the `updated_at` the editor loaded) answers `409 ohmylms_quiz_conflict` if the
  quiz changed since.

## Attempts and grading

Attempt row: `{ id, quiz_id, course_id, student: { id, name, email }, status, score, max_marks,
percentage, started_at, submitted_at, needs_grading }`. Status is `in-progress`, `in-review` (waiting for
manual marks) or `completed`. Collection arguments: `page`, `per_page` (1-100), `search` (name or email),
`status`, `student_id`, `orderby` (`started_at|score|student`), `order`.

Attempt detail adds `passing_mark`, `passed`, `versioned` and `questions`: `{ question_id, position, title,
type, max_marks, awarded, status, correct, answer[], parts[]|null, history[] }`. `answer` is plain text lines
(option ids are replaced by their text); no answer key or option correctness is sent. `history` lists the
last ten grade events with reviewer, reason and time.

```http
POST /wp-json/ohmylms/v1/quizzes/11/attempts/21/grades
X-WP-Nonce: <wp_rest nonce>
Content-Type: application/json

{ "grades": [ { "question_id": 102, "marks": 3 } ], "reason": "Good reasoning" }
```

Rules: 1-200 grades; each `question_id` must belong to the attempt and appear once; `marks` is between 0 and
the question's frozen maximum (a number, or an object of marks by part for structured questions); only
`grades` and `reason` are accepted, a `total` or `score` is refused. `Review::save()` locks the attempt,
rewrites the answer rows, recomputes the attempt total as the sum of the answer rows, sets the status
(`completed` once nothing waits for manual marks), and writes one grade event per changed question or part
(reviewer, reason, UTC time; the previous event is superseded). Sending the same marks again is a no-op:
`changed` is 0 and no event is written. Hooks `ohmylms_answer_regraded`, `ohmylms_attempt_graded`,
`ohmylms_rest_review_quiz_attempt` and the completion hooks still run.

## Error codes

| HTTP | Code | When |
| --- | --- | --- |
| 400 | `rest_invalid_param`, `rest_missing_callback_param` | Schema validation: types, enums, ranges, unknown settings, unknown fields in a grade, empty or oversized lists. |
| 400 | `ohmylms_quiz_invalid_page`, `ohmylms_attempt_invalid_page` | `page` past the last page. |
| 400 | `ohmylms_grade_unexpected_field` | A field other than `grades` and `reason` (for example `total`). |
| 401 / 403 | `ohmylms_quiz_cannot_view`, `_create`, `_publish`, `_edit`, `_delete`, `_grade` | Not logged in (401) or not allowed (403). |
| 403 | `rest_cookie_invalid_nonce` | Cookie authentication without a valid `X-WP-Nonce` (core). |
| 404 | `ohmylms_quiz_not_found`, `ohmylms_attempt_not_found` | Unknown id, other post type, or attempt of another quiz. |
| 409 | `ohmylms_quiz_conflict` | `base_modified` is older than the stored quiz (`data.modified`). |
| 409 | `ohmylms_quiz_has_attempts` | Permanent delete while attempts exist (`data.attempts`). |
| 409 | `ohmylms_attempt_not_submitted`, `ohmylms_attempt_not_versioned`, `quiz_attempt` | Grading an in-progress, pre-versioning or otherwise unavailable attempt. |
| 422 | `ohmylms_quiz_invalid_title`, `_time_limit`, `_passing_score`, `_attempts`, `_layout`, `_questions_per_page`, `_feedback_mode`, `_availability`, `_status` | Semantic validation of the merged request. |
| 422 | `ohmylms_grade_unknown_question`, `_duplicate_question`, `_exceeds_max`, `quiz_marks` | Grades outside the attempt, repeated, or above the maximum (`data.question_id`, `data.max`). |
| 500 | `ohmylms_quiz_create_failed`, `_update_failed`, `_delete_failed`, `quiz_storage` | Database failure; nothing is changed. |

## Hooks kept

Actions `ohmylms_rest_insert_quiz` ($post, $request, $creating), `ohmylms_rest_delete_quiz` ($id),
`ohmylms_after_updating_quiz` ($id, $quiz) and the grading hooks above. Filters `ohmylms_rest_prepare_quiz`
(now also on `/quizzes` responses; `native_editor_url` is added by the native editor) and
`ohmylms_rest_ohmylms_quiz_query` ($args, $request; now applied to a fixed, validated query). Removed:
`ohmylms_rest_query_var-*`, `ohmylms_rest_query_vars` (the old raw query-var passthrough) and
`ohmylms_rest_quiz_meta_updated` on the new routes.

## Security notes

- Every route checks the object (`edit_post`, `delete_post`, `GradingPolicy`), never a flat `edit_posts` for
  someone else's quiz or student attempts. Student PII is limited to name and email, only to graders.
- Search uses `$wpdb->esc_like()` and prepared statements; `orderby` is a whitelist.
- Attempts are never writable by the client except through the grading route; the learner routes only
  write the learner's own drafts (`AttemptController`).
- Descriptions are sanitised with `wp_kses_post`; titles are plain text; JSON responses only.

## Old callers that break

`assets/src/features/quiz-reports/*` and `assets/src/application/screens/quiz-report*` (report and grading
routes), the old quiz list (`GET /quiz`, `trash-bulk`), `tests/php/assessment/versioning.php` (calls
`GET quiz/{id}/report/{attempt}`; `Review::save()` is unchanged and still tested directly). The question editor
and internal callers (`SkillAssessment`, `ExamImport\Workflow`, `Learning\ActivityDrafts`) keep working on the
authoring routes.
