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

### Audit before: questions of a quiz

How questions are stored today: a row per placement in `wp_ohmylms_quiz_questions_relationship`
(`quiz_id`, `question_id`, `order_number`; the learner order is `ORDER BY order_number`), and the pinned
version per question in the quiz meta `_ohmylms_version_pins` (`question_id => version_id`).
`RevisionPublisher::slot_version()` freezes, per slot, the pin, else the current version when the quiz
**owner** may edit the question, else the bank's approved version (409 `quiz_question_unapproved` if none).
Slot marks come from that version's `score`, overridden by section marks / `slot_marks`. Random pools live in
`_ohmylms_pools` (`Assessment\Pools`) and are appended after the fixed slots at publish time. Writers:
`DraftWriter::prepare_many/save_many` (legacy `PUT /quiz/{id}`, `PUT /quiz/{id}/content`: validate the whole
batch, then create/update and link every question in one transaction), `DraftWriter::link()` (append at
`MAX(order_number)+1`), `QuestionBankController::add_to_quiz` / `pin` (`/quiz/{id}/questions`,
`/quiz/{id}/questions/{qid}/pin`) and `Usage::remove_from_quiz()` (`DELETE /quiz/{id}/questions/{qid}`).

| Severity | Finding |
| --- | --- |
| High | **No stale-write detection on any placement write.** Two editors adding, reordering (by resending the whole `content` list) or removing questions silently overwrite each other; `base_modified` on `PUT /quiz/{id}` compares the post time, which placement writes never change. New routes: a `revision` token over the ordered ids and pins, checked under a row lock (`SELECT … FOR UPDATE` on the quiz) inside the write transaction. Legacy writers also change it, so they are detected too. |
| High | **Pin route trusts any version of a use-only question.** `PUT /quiz/{id}/questions/{qid}/pin` pins any `version_id` of the question, including unapproved drafts of a shared question the user may only use, so unreviewed content can reach learners. New route: a user who cannot edit the question can pin only its approved version (403 `ohmylms_quiz_pin_forbidden`). |
| Medium | **`add_to_quiz` accepts archived, trashed and duplicate questions** (`DraftWriter::link` silently no-ops a duplicate and still reports it as added), has no list limit and no argument schema. New route: 404 for trashed/unknown, 409 `ohmylms_question_archived`, 409 `ohmylms_quiz_question_exists`, 1–50 unique ids, 500 per quiz. |
| Medium | **Remove leaves a stale pin** in `_ohmylms_version_pins`; re-adding the question later silently reuses the old pinned version. New route drops the pin in the same transaction. |
| Medium | **Reads expose answer keys to editors of the quiz.** `GET /quiz/{id}/content` returns `settings` and options with `is_correct` for every question, including shared questions the user may only use. New list: summaries only, never settings or options; restricted questions show no name or type. |
| Medium | **No create-and-place.** Creating a question for a quiz meant `POST /question` then a second request to link it; a failed second step leaves an orphan question. New `POST …/questions/new` does both in one transaction, with the exact validation of `POST /questions`. |
| Low | `/quiz/{id}/questions*` permission callbacks answer 404 `ohmylms_rest_invalid_quiz_id` / 403 `ohmylms_forbidden` without route-specific codes; no `Cache-Control`; reorder relied on clients sending `order_number` per question. |
| OK | Writes go through `DraftWriter`/`Usage` with object-level `AccessPolicy` checks (`can_edit_quiz`, `can_use_question`, `can_edit_question`); use-only questions without an approved version are refused with 409 `quiz_question_unapproved`; pool rules are validated and checked for feasibility on save (`Pools::save`). |

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

Questions of a quiz (`includes/Rest/V1/QuizQuestionsController.php`; every route needs the question bank
tables, else 503 `ohmylms_question_bank_unavailable`, and `edit_post` on the quiz, else 401/403
`ohmylms_quiz_cannot_edit`; an unknown id answers 404 `ohmylms_quiz_not_found` first):

| Method | Path | Body | Success | Errors |
| --- | --- | --- | --- | --- |
| GET | `/quizzes/{id}/questions` | – | 200 `QuizQuestions` | 401, 403, 404, 503 |
| POST | `/quizzes/{id}/questions` | `question_ids` (1–50 unique ids), `pin` (bool), `revision` | 200 `QuizQuestions` | 400, 401, 403, 404, 409, 413, 422, 500 |
| POST | `/quizzes/{id}/questions/new` | `question` (the `POST /questions` body), `revision` | 201 `QuizQuestions` + `created_id`, `Location` of the question | 400, 401, 403, 409, 413, 422, 500 |
| PUT / PATCH / POST | `/quizzes/{id}/questions/order` | `question_ids` (every id of the quiz once), `revision` | 200 `QuizQuestions` | 400, 401, 403, 404, 409, 500 |
| DELETE | `/quizzes/{id}/questions/{question_id}` | `revision` (query or body) | 200 `QuizQuestions` | 400, 401, 403, 404, 409, 500 |
| PUT / PATCH / POST | `/quizzes/{id}/questions/{question_id}/pin` | `version_id` (id, or `null` to unpin) **or** `current: true`; `revision` | 200 `QuizQuestions` | 400, 401, 403, 404, 409, 500 |

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

## Questions of a quiz

```json
{ "quiz_id": 11, "revision": "643d5437104296e2",
  "questions": [ { "question_id": 101, "position": 1, "name": "Pick the larger fraction",
    "type": "single-choice", "type_label": "Single Choice", "marks": 2, "section": "", "status": "draft",
    "version": 3, "current_version_id": 31, "approved_version_id": 0, "pinned_version_id": 0,
    "pinned_version_no": 0, "delivery": { "source": "current", "version_id": 31, "version_no": 3 },
    "usage": { "quizzes": 1, "responses": 0 }, "permissions": { "can_edit": true, "can_use": true },
    "modified": "2026-10-30T09:00:00Z" } ],
  "pools": [ { "id": "pool-1", "title": "Warm-up", "skill": { "id": 7, "name": "Fractions" }, "count": 3,
    "marks": 2, "difficulty": "", "bank_id": 0, "include_secure": true } ],
  "totals": { "questions": 1, "fixed_marks": 2, "pool_questions": 3, "pool_marks": 6, "total_marks": 8 } }
```

- **No answer keys**: slots never carry settings, options or answer text, for any user. `name` is plain
  text; for a question the user may neither edit nor use, `name` and `type` are empty
  (`permissions.can_use: false`). Responses carry `Cache-Control: private, no-store`.
- **Status** is `archived`, `approved` (a version is approved), `draft`, or `unavailable` (trashed/deleted).
- **Delivery** says which version learners get, read-only (reads never capture versions): `pinned`; else
  `current` when the quiz owner may edit the question; else `approved`; else `unavailable` (publishing will
  answer 409 `quiz_question_unapproved`). This mirrors `RevisionPublisher::slot_version()`.
- **Marks** are those of the delivered version's `score`, overridden by section marks / `slot_marks`, as
  publishing computes them. `totals.total_marks` adds the pools (`count × marks`).
- **Revision**: the first 16 hex characters of SHA-256 over the ordered question ids and the pins. Every write
  sends the revision it loaded; each write locks the quiz row, recomputes the revision from the database and
  answers 409 `ohmylms_quiz_questions_conflict` (with `data.revision`) when it differs, so edits by another
  editor, another tab or a legacy route are never overwritten. Pools and question content do not change it
  (question content has its own `base_modified`). The quiz's `updated_at` is not touched by these writes, so
  the settings form and the question list are separate conflict domains on the same screen.
- **Add**: each id must be an existing, non-trashed question (404 `ohmylms_question_not_found` with
  `data.question_id`), not archived (409 `ohmylms_question_archived`), not already placed (409
  `ohmylms_quiz_question_exists`), usable (`can_use_question`, else 403 `ohmylms_quiz_question_forbidden`), and,
  when the user cannot edit it, approved (409 `quiz_question_unapproved`, as today). Questions are appended in
  the given order. `pin: true` pins each to the version the quiz would use now (captured for editable questions,
  the approved version otherwise). A quiz holds at most 500 questions (422 `ohmylms_quiz_questions_limit`).
- **Create**: `question` is dispatched internally to `POST /questions`, so its argument schema, unknown-field
  rule, option rules, settings filtering, extension settings and permission apply unchanged (its 400/403/422
  errors are returned as they are). The new question is appended. Creation and placement run in one
  transaction: if placement fails, the question is rolled back too (`ohmylms_rest_save_question_item` will
  already have fired inside the transaction).
- **Reorder**: `question_ids` must list every question of the quiz exactly once (duplicates: 400; a missing or
  foreign id: 409 `ohmylms_quiz_questions_mismatch` with `data.expected`). `order_number` becomes 1..n.
- **Remove** is `Usage::remove_from_quiz()` (fires `ohmylms_quiz_question_removed`; the question stays in the
  bank and in other quizzes; attempts keep their frozen items) and drops the question's pin in this quiz.
  A question not in the quiz answers 404 `ohmylms_question_not_in_quiz`.
- **Pin**: exactly one of `version_id` or `current: true` (else 400 `ohmylms_quiz_pin_invalid`); `version_id:
  null` unpins. The version must belong to the question (404 `ohmylms_version_missing`). Pinning needs
  `use` on the question; a user who cannot edit it can pin only the approved version (403
  `ohmylms_quiz_pin_forbidden`). Unpinning is always allowed to quiz editors.
- Writes on a trashed quiz answer 409 `ohmylms_quiz_trashed`; any other body field answers 400
  `ohmylms_quiz_questions_unknown_field` (`data.fields`); bodies over 256 KiB answer 413
  `ohmylms_quiz_questions_too_large`; storage failures roll back and answer 500 `ohmylms_quiz_questions_storage`.
- Hooks: action `ohmylms_rest_quiz_questions_changed` ($quiz_id, $operation `add|create|reorder|remove|pin`,
  $request) after every successful write; filter `ohmylms_rest_prepare_quiz_questions` ($data, $quiz_id,
  $request) on every list response. `ohmylms_quiz_question_relationship_created` still fires from
  `DraftWriter::link()`.

```http
PUT /wp-json/ohmylms/v1/quizzes/11/questions/order
X-WP-Nonce: <wp_rest nonce>
Content-Type: application/json

{ "question_ids": [ 102, 101 ], "revision": "643d5437104296e2" }
```

### Admin UI

The quiz editor (`#/quizzes/<id>`, extension route `/quiz-edit/:id`) has a Questions section
(`assets/src/app/features/quizzes/QuizQuestions.tsx`): slots with position, type, marks and the delivered
version; the total marks (also used in the passing-score help); add from the question bank (a searchable,
paged picker over `GET /questions` with type and "approved only" filters; questions already placed, archived
questions and shared questions without an approved version cannot be chosen; optional pin); create (name and
type, then the question bank's own draft editor, loaded lazily from `features/questions/draft/public.ts`,
saving through `POST …/questions/new`); edit (link to `#/content-hub/question-bank/<id>` for questions the user
may edit); move up/down buttons that keep keyboard focus on the moved question; pin/unpin; remove with a
confirmation dialog; read-only random pools. Every change is saved immediately with the loaded revision; a 409
reloads the list and says nothing was changed. An open question draft counts as unsaved work for the page's
leave guard. The legacy "Edit questions" link to `#/quiz-edit/<id>` was removed from this screen.

### Not covered (questions)

- **Pool editing**: pools are shown read-only. `GET|PUT /quiz/{id}/pools` (`AssessmentController`) still edits
  them; it has no stale-write check and does not check that a pool's `bank_id` is a bank the user may use (a
  pool can draw approved questions from another author's private bank). Harden before exposing it here.
- **Pin to a specific older version** is supported by the route but the UI offers only "pin the version used
  now" and "unpin".
- **Sections**: section titles and marks are shown but not edited; removing a question does not remove it from
  `sections` in the assessment settings (as before).
- The legacy `/quiz/{id}/content`, `/quiz/{id}/questions`, `/quiz/{id}/questions/{qid}/pin` and
  `DELETE /quiz/{id}/questions/{qid}` routes are unchanged (still used by the old editor, import and internal
  callers) and keep the findings above until they retire.

## Import and export

The file format and importer are described in [QUIZ-IMPORT-EXPORT](../QUIZ-IMPORT-EXPORT.md); the routes stay
under `ohmylms/v1/quiz` and are hardened here:

- `POST /quiz/import`: `content` / `csv` at most 10 MB (`Document::MAX_BYTES`), `name` at most 200 characters
  (schema-validated before sanitizing), `format` `auto|ohmylms|csv`, `dry_run`, `copy_media`, and `review`
  (64 hex). A dry run answers `review`, an HMAC of the caller, the file text, the name and the format; an import
  that sends it must be exactly the checked file and name (409 `ohmylms_quiz_import_review_stale`, nothing
  created). Import always creates a new draft quiz, so it is never idempotent by design; the review only
  guarantees that what is created is what was checked. `edit_posts` (`AccessPolicy::can_author`).
- `GET /quiz/{id}/export`: edit rights on the quiz (`AccessPolicy::can_edit_quiz`); the file holds every
  answer key.
- Both answer `Cache-Control: private, no-store`.

UI on `#/quizzes`: **Import quiz** (choose or paste a file, optional name, **Check file** lists the summary
and warnings or every problem with its question number, **Import as a new draft quiz** opens the new quiz;
changing the file or name requires a new check) and an **Export** row action for quizzes the user can edit.
Deep links: `?action=new` opens **Add quiz**, `?action=import` opens **Import quiz**; the parameter is removed
once the dialog is open. Tests: `tests/php/quiz-import-export-rest-integration.php` (`OHMYLMS_QUIZZES_TEST=1`)
and `features/quizzes/QuizExchange.test.tsx`.

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
| 400 | `ohmylms_quiz_questions_unknown_field`, `ohmylms_quiz_pin_invalid` | Questions routes: unknown body field; pin without exactly one of `version_id` / `current`. |
| 403 | `ohmylms_quiz_question_forbidden`, `ohmylms_quiz_pin_forbidden` | A question the user may not use; pinning a non-approved version of a use-only question. |
| 404 | `ohmylms_question_not_found`, `ohmylms_question_not_in_quiz`, `ohmylms_version_missing` | Unknown/trashed question; question not placed in the quiz; version of another question. |
| 409 | `ohmylms_quiz_questions_conflict`, `ohmylms_quiz_questions_mismatch`, `ohmylms_quiz_question_exists`, `ohmylms_question_archived`, `quiz_question_unapproved`, `ohmylms_quiz_trashed` | Stale revision (`data.revision`); reorder list differs from the quiz; duplicate add; archived question; use-only question without an approved version; trashed quiz. |
| 413 / 422 / 500 / 503 | `ohmylms_quiz_questions_too_large`, `ohmylms_quiz_questions_limit`, `ohmylms_quiz_questions_storage`, `ohmylms_question_bank_unavailable` | Body over 256 KiB; more than 500 questions; storage failure (rolled back); bank tables missing. |

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

## Tests

`tests/php/quizzes-rest-integration.php` (against `800.local`, removes everything it creates):

```powershell
$env:OHMYLMS_QUIZZES_TEST='1'; php -n -d extension_dir=C:\php8.5.4\ext -d extension=mysqli -d extension=mbstring -d extension=openssl -d extension=curl -d mysqli.default_port=10006 tests/php/quizzes-rest-integration.php
```

For the questions routes it covers registration; 401/403 for guests, students, an unrelated author and a
cross-author write; 404s; argument validation; unknown fields; stale revisions on every write; unknown,
unshared and use-only-unapproved questions (nothing written); add in order with totals and no answer keys;
duplicate add; adding an approved shared question with a pin to its approved version; pinning an unapproved
version as a use-only user (403), a foreign version, invalid pin bodies, pin current/unpin and the stored meta;
reorder mismatch/duplicate/conflict and the stored and learner order; remove (question kept, pin dropped);
create in quiz with `POST /questions` validation, 201/`Location`/totals/ownership; create atomicity (a failing
placement hook rolls the question back); detection of a legacy-route change; read-only pools in the totals;
trashed quiz. App tests: `QuizQuestions.test.tsx`, `questionsApi.test.ts`, `questionsModel.test.ts`,
`QuizEditorPage.test.tsx`.

## Old callers that break

`assets/src/features/quiz-reports/*` and `assets/src/application/screens/quiz-report*` (report and grading
routes; both deleted at the cutover), the old quiz list (`GET /quiz`, `trash-bulk`), `tests/php/assessment/versioning.php` (calls
`GET quiz/{id}/report/{attempt}`; `Review::save()` is unchanged and still tested directly). The question editor
and internal callers (`SkillAssessment`, `ExamImport\Workflow`, `Learning\ActivityDrafts`) keep working on the
authoring routes.
