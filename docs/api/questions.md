# Questions REST API (`ohmylms/v1/questions`)

Contract version 1. This is the typed API of the admin app's Question Bank list and single-question
editor, and of the native (Gutenberg) question, quiz and lesson editors. It replaced the legacy
`ohmylms/v1/question` and `ohmylms/v1/question-bank` routes, which were removed on 31 October 2026 (see
[Removed legacy routes](#removed-legacy-routes)). Server code: `includes/Rest/V1/QuestionsController.php`
(routes, arguments, validation, responses) and `includes/QuestionBank/Search.php` (the visibility-scoped
list query). Writes go through `includes/QuestionBank/DraftWriter.php`; archive, restore and delete through
`includes/QuestionBank/Usage.php`; approval through `includes/QuestionBank/Approval.php` and duplication
through `includes/QuestionBank/Duplicator.php`; object permissions come from
`includes/QuestionBank/AccessPolicy.php`.

## Audit before

Findings in the legacy routes this API replaced (now removed).

| Severity | Finding |
| --- | --- |
| High | **Answer keys reach users who may only use a question.** `GET /question` lists questions the user can edit *or use* (shared-bank `use` grant) and returns `settings` and `answer` (options with `is_correct`) for each. `GET /question-bank/{id}` returns `settings` and `options` to anyone with `use`. Here every response with settings or options requires `edit`, and list rows never carry them. |
| High | **`GET /question` forwards raw query vars**: a `filter` array is merged into `WP_Query`, plus `post_status`, `offset`, `parent*`, dates and `slug`. Here only declared, validated arguments reach a fixed, prepared query. |
| Medium | **Totals counted hidden questions**: `GET /question` filters rows after the query, so `X-WP-Total` includes questions the user cannot see. `/question-bank` already counted in SQL; that query is now `Search::query()` and is used by both. |
| Medium | **Reads wrote**: `GET /question-bank/{id}` creates the identity row (`VersionPublisher::identity()`) on read. Here reads never create rows. |
| Medium | **No argument schema** on `/question` writes (they reuse the collection params), unknown fields were ignored, `status` was writable, settings strings were stored without filtering, and option rules (number of options, correct answers) were not checked. |
| Medium | **No stale-save protection by default**: `base_modified` is optional on `/question`. Here it is required on update. |
| Low | Wrong statuses (`400` for unknown ids on update/delete), no `Location` on 201, mixed time formats (`modified` is a MySQL GMT string), no `Cache-Control` on responses that contain answer keys. |
| OK | Writes already go through `DraftWriter` (validated, authorised, one transaction, versioned), and item routes check `AccessPolicy`. Prepared SQL with `%i` for tables. |

## Final routes

Anonymous callers get `401`, logged-in users without the capability `403`. For item routes an unknown id,
another post type, a trashed question or an auto-draft answers `404 ohmylms_question_not_found` before the
capability check. One exception: the item route itself (`GET`, `PATCH|PUT|POST` and `DELETE /questions/{id}`)
accepts the caller's **own** auto-draft, the blank document WordPress allocates on
`post-new.php?post_type=ohmylms-question`; its first write turns it into a draft question (the native
editor's "new question"). Another user's auto-draft, and every sub-route of an auto-draft, still answer 404.
Core validates declared arguments before the permission callback, so a malformed request
can answer 400 before 401/403; a body above 256 KiB answers 413 before anything else. Every successful
response carries `Cache-Control: private, no-store`.

| Method | Path | Permission | Success | Errors |
| --- | --- | --- | --- | --- |
| GET | `/questions` | `edit_posts` (author); a named `bank` must be visible to the user | 200 summaries + `X-WP-Total`, `X-WP-TotalPages`, `Link` | 400, 401, 403, 503 |
| POST | `/questions` | `edit_posts` | 201 editor view + `Location` | 400, 401, 403, 409, 413, 422, 500, 503 |
| GET | `/questions/types` | `edit_posts` | 200 type list | 401, 403 |
| GET | `/questions/{id}` | `AccessPolicy::can_edit_question` | 200 editor view | 401, 403, 404, 503 |
| PATCH / PUT / POST | `/questions/{id}` | `can_edit_question` | 200 editor view | 400, 401, 403, 404, 409, 413, 422, 500, 503 |
| DELETE | `/questions/{id}` | `AccessPolicy::can_delete_question` (`delete_post`; bank grants never delete) | 200 `{deleted, archived, previous}` | 400, 401, 403, 404, 409, 500, 503 |
| POST | `/questions/delete` | `edit_posts`, then `can_delete_question` per question | 200 `{results, skipped}` | 400, 401, 403, 409, 500, 503 |
| POST | `/questions/{id}/archive` | `can_edit_question` | 200 editor view | 400, 401, 403, 404, 500, 503 |
| POST | `/questions/{id}/restore` | `can_edit_question` | 200 editor view | 400, 401, 403, 404, 500, 503 |
| POST | `/questions/{id}/approve` | `AccessPolicy::can_approve_question` | 200 editor view | 400, 401, 403, 404, 409, 503 |
| POST | `/questions/{id}/duplicate` | `can_author` and `AccessPolicy::can_use_question` | 201 editor view of the copy + `Location` | 400, 401, 403, 404, 500, 503 |
| GET | `/questions/{id}/versions` | `can_edit_question` | 200 versions + totals | 400, 401, 403, 404, 503 |
| GET | `/questions/{id}/versions/{version_id}` | `can_edit_question` | 200 the version's content | 401, 403, 404, 503 |

`503 ohmylms_question_bank_unavailable` means the question bank tables (`Assessment\Schema::ready()`) are
not installed.

**Delete** (`Usage::delete_or_archive()`, the logic of the removed `DELETE /question/{id}`): no body fields
(400 `ohmylms_question_unknown_field`). A question placed in more than one quiz answers 409
`ohmylms_question_shared` (`data.quizzes`; remove it from a quiz with
`DELETE /quizzes/{id}/questions/{question_id}` first). A question with learner history (attempt answers,
issued attempt or practice items, the `ohmylms_question_response_count` / `ohmylms_question_has_history`
filters) **or a version in a published quiz revision** (`quiz_revision_slots`, what attempts are delivered
from) is archived instead (`archived: true`), so no attempt or revision loses the version it references;
otherwise the question, its options, versions and identity row are deleted (`deleted: true`). Either way its
single quiz placement is removed under the quiz's row lock (`SELECT … FOR UPDATE`, as the quiz question routes
take it) together with what the quiz kept about it — its pin, its marks override and its place in a section
(`Usage::forget_in_quiz()`) — so the quiz's question-list `revision` changes and an editor holding the old one
gets 409 and reloads. `previous` is the editor view read just before. Fires `ohmylms_rest_delete_question`
($request, $result `{id, action}`).

**Bulk delete** (`POST /questions/delete`): `ids` (1–100 unique question IDs) and `skip_blocked` (default
false); no other fields. Every question is checked first: unknown or trashed (`ohmylms_question_not_found`),
not deletable by the user (`ohmylms_question_cannot_delete`) or placed in several quizzes
(`ohmylms_question_shared`). Without `skip_blocked`, any blocked question refuses the whole request with 409
`ohmylms_question_delete_blocked` and `data.blocked: [{id, code, message, quizzes}]`, and nothing changes. With
it, the others are deleted or archived as above and the blocked ones are returned in `skipped`. All deletes run
in one transaction (a storage failure changes nothing). Answers
`{ results: [{id, action: deleted|archived}], skipped: [{id, code, message, quizzes}] }` and fires
`ohmylms_rest_delete_question` per question. List rows carry `permissions.can_delete`.

**Version content** (`GET /questions/{id}/versions/{version_id}`): the frozen version row (`id`, `question_id`,
`version_no`, `title`, `body`, `type`, `settings`, `options` with `is_correct`, …) plus `skills` (the version's
skill map), without `content_hash`. A version of another question answers 404 `ohmylms_version_missing`. It
replaced `GET /question-bank/versions/{version_id}`, which returned the same content to users who could only
*use* the question; this route needs `edit`.

### Collection arguments (`GET /questions`)

| Argument | Rules |
| --- | --- |
| `page` | integer >= 1, default 1. A page past the end answers 400 `ohmylms_question_invalid_page`. |
| `per_page` | 1-100, default 20. |
| `search` | string, at most 100 characters, `sanitize_text_field`; matches the name or body. |
| `type` | a registered question type ID, otherwise 400. |
| `difficulty` | `easy`, `standard` or `challenge`. |
| `status` | `active` (default: not archived), `draft` (no approved version), `approved`, `archived`. |
| `bank` | integer >= 0; `0` is personal questions. A named bank must be in `Banks::for_user()` unless the user has `edit_others_posts`, otherwise 403 `ohmylms_question_bank_forbidden`. |
| `skill` | skill term ID >= 1. |
| `secure` | boolean: only secure (exam-only) questions, or only the others. |
| `exclude_quiz` | quiz ID >= 1: leave out the questions already placed in that quiz (the bank pickers). The quiz must be editable by the user, otherwise 403 `ohmylms_question_quiz_forbidden`. |
| `organisation`, `scope` | an organisation view ID (`^[a-z0-9_-]{1,60}$`, from `GET /question-bank-organisation`) and a node ID >= 1 of that view: only questions the view resolves to ([QUESTION-BULK-IMPORT](../QUESTION-BULK-IMPORT.md#question-bank-organisation)). An unknown or unavailable view answers 400/403/404 `bank_scope`; a scope never widens visibility. |
| `orderby` | `modified` (default), `title`, `id`. |
| `order` | `asc`, `desc` (default). |

Visibility: users with `edit_others_posts` see every question; others see their own questions and questions in
banks they can use. `X-WP-Total` counts only those.

### Summary (list row)

```json
{ "id": 812, "uuid": "1b0c…", "name": "Solve 2x = 6", "type": "single-choice", "type_label": "Single Choice",
  "marks": 2, "status": "draft", "post_status": "publish", "version": 3, "current_version_id": 95,
  "approved_version_id": 0, "approved_is_current": false, "bank_id": 0, "difficulty": "easy",
  "source": "Textbook 4.2", "family_id": "", "secure": false, "skills": [ 41 ],
  "usage": { "quizzes": 1, "responses": 0 }, "author": { "id": 7, "name": "Alex" },
  "modified": "2026-10-30T13:05:12Z", "permissions": { "can_edit": true, "can_approve": true } }
```

No settings, body, options or answer keys. `status` is `archived` for archived questions (even when a version
was approved), otherwise `approved` when a version is approved, otherwise `draft`. Related data is loaded in
batches per page (posts and meta, identity rows, usage counts, skill terms, authors).

### Editor view (`GET /questions/{id}` and every write)

```json
{ "id": 812, "uuid": "1b0c…", "name": "Solve 2x = 6", "description": "<p>…</p>", "type": "single-choice",
  "settings": { "type": "single-choice", "score": { "enabled": true, "value": 2 } },
  "options": [ { "id": 3301, "answer": "3", "is_correct": true, "order_number": 1, "image_id": 0,
                 "image_url": "", "matching_data": null } ],
  "skills": { "p1": { "primary": 41, "supporting": [] } },
  "bank": { "bank_id": 0, "difficulty": "easy", "source": "Textbook 4.2", "family_id": "", "secure": false },
  "thumbnail_id": 0, "video_id": 0, "extension_settings": {},
  "status": "draft", "post_status": "publish", "version": 3, "current_version_id": 95,
  "approved_version_id": 0, "approved_is_current": false,
  "usage": { "quizzes": 1, "responses": 0 }, "quizzes": [ { "id": 77, "name": "Unit 4", "can_edit": true } ],
  "permissions": { "can_edit": true, "can_approve": true, "can_delete": true },
  "modified": "2026-10-30T13:05:12Z" }
```

`name` and `description` are returned as stored (raw, filtered on write); render them as HTML only through
the same sanitisation. `uuid` is empty and `version` 0 for a question that has never been saved through the
bank; reading it does not create the identity row. `image_url` of an option is derived from `image_id`;
`thumbnail_url` and `video_url` (empty for none) are the URLs of `thumbnail_id` and `video_id`.

### Versions (`GET /questions/{id}/versions`)

Newest first, paged with `page` / `per_page` (as above) and `X-WP-Total`:

```json
[ { "id": 95, "version_no": 3, "created_at": "2026-10-30T13:05:12Z", "created_by": { "id": 7, "name": "Alex" },
    "is_current": true, "is_approved": false } ]
```

Rows carry no content or content hashes; `GET /questions/{id}/versions/{version_id}` returns one version's
content to the question's editors.

### Types (`GET /questions/types`)

All registered question types, built-in and from add-ons (for example `graded-reader` from
`ohmylms-graded-reader`), sorted by label:

```json
[ { "id": "single-choice", "label": "Single Choice", "format": "ohmylms-options", "answers": "options",
    "manual": false, "snapshot": true, "builtin": true } ]
```

`format` is the type's `editor.format` (`ohmylms-options`, `ohmylms-settings`, another value, or `null`).
`answers` is `options`, `text` or `null`. `builtin` is true for types OhMyLMS registers before
`ohmylms_register_extensions` fires (`Registry::mark_core()`).

## Type and field coverage of the editor

The new admin editor (`#/content-hub/question-bank/<id>`, and the same form hosted by the quiz editor) was
compared with what the server stores and plays (`Extensions\QuestionTypes`, `Assessment\Interactive`,
`Assessment\ExtendedQuestions`, `QuestionBank\DraftWriter`, the `single-lesson/quiz-loop/*` players).
53 types are registered on a site with the graded-reader add-on and the examples module: 52 built-in, plus
`graded-reader` and `example-number`, which register their own editors through `registerQuestionEditor`.

Answers, by type (before: 30 October 2026; after: this change):

| Types | Stored answer data | Before | After |
| --- | --- | --- | --- |
| `single-choice`, `multiple-choice`, `true-false` | options with `is_correct` | text, correct, order | + option images, shuffle; partial credit for `multiple-choice` |
| `reorder` | option order | text, order | + option images |
| `matching` | options with `matching_data.label`/`image_url`; `match_layout` | text, label, order | + option and match images, layout (lines or grid) |
| `statement` | expected answers (correct options, compared exactly, in order) | link to the deleted full editor | expected answers editor |
| `fill-in-the-blank` | `{answers}` in the name, or correct options; `blank_mode`, `case_sensitive`, `partial_credit` | link to the deleted full editor | detected blanks, accepted answers, drag or type, case, partial credit |
| `short-text`, `long-text` | none (marked by a teacher; length limit is a quiz setting) | link to the deleted full editor | explained, no answer key |
| `numerical`, `structured`, `dropdown-blanks`, `categorize`, `multi-blank`, `build-expression`, `expression`, `number-line`, `shade-model`, `count-blocks`, `set-clock`, `make-amount`, `fill-level`, `build-chart`, `grid-build` | type settings | registered core editor | unchanged |
| `text-entry`, `crossword`, `wordsearch` | type settings | "no editor yet" | registered core editor |
| `passage`, `graphing`, `hot-text`, `match-table-grid`, `labeling`, `hotspot`, `draw`, `audio-response`, `video-response`, `poll`, `word-cloud`, `discussion-board`, `slide`, `interactive-video`, `spin-wheel`, `gameshow`, `hangman`, `open-box`, `speaking-cards`, `flip-tiles`, `flashcards`, `timed-true-false`, `whack-a-mole`, `flying-fruit` | type settings (frozen keys, public views in `ExtendedQuestions`) | "no editor yet" | registered core editor (`ExtendedEditor`) |
| `graded-reader`, `example-number` (add-ons) | their settings | their own editor | unchanged |

Every built-in settings editor is registered by `registerCoreQuestionEditors()`
(`assets/src/features/question-editor/registerCoreEditors.js`) exactly as an add-on registers one, after
extension scripts ran, so an add-on can still replace any of them. Fields every type shares:

| Field | Stored in | Before | After |
| --- | --- | --- | --- |
| Name, question text | post title and content | yes | yes |
| Points | `settings.score` | yes (also overwrote multi-part totals and unscored types) | points types; the parts' total for `structured`/`passage`; always 0 for unscored types |
| Required | `settings.required` | no | yes (not for `slide`) |
| Shuffle options | `settings.randomize` | no | choice and true/false (`reorder`/`matching` always shuffle) |
| Hint, explanation | `settings.hint`, `settings.explanation` | no | yes |
| Question image and video | `thumbnail_id`, `video_id` | no | media library |
| Bank, difficulty, source, secure | `bank` | difficulty only | all; `family_id` shown read-only |
| Skills | `skills` (per part: primary and supporting) | no | yes, per part of `structured`/`passage` |
| Teacher marking notes | `parts[].rubric` of `structured`/`passage` | yes (structured editor) | yes; the server has no rubric for `short-text`/`long-text` |
| Extension settings | `extension_settings` | no | no (no question extension settings are registered; see Not covered) |

Tags, per-option feedback and per-question numeric tolerance outside `numerical`/`multi-blank`/`structured`
are not stored by the plugin. The editor's **Learner preview** posts the unsaved edits to
`POST /question-preview` (author-only; the saved question, or for a draft created in a quiz the quiz, is the
preview's document) and shows the real quiz player in a frame; nothing is saved or graded.

## Writes

### Body fields

| Field | Create | Update | Rules |
| --- | --- | --- | --- |
| `name` | required | optional | string, 1-500 characters after trimming (blank: 422 `ohmylms_question_invalid_name`). |
| `description` | optional | optional | string, at most 65535 characters; `wp_kses_post` unless `unfiltered_html` (DraftWriter). |
| `settings` | required | optional, replaces the whole object | object; `settings.type` must be a registered type (400 `ohmylms_question_invalid_type`); the type's own `validate_settings`/editor schema runs in DraftWriter (400 `ohmylms_invalid_question_settings`). Every string leaf is `wp_kses_post`-filtered unless the user has `unfiltered_html`; numbers, booleans and keys are kept. |
| `options` | optional | optional, replaces the option set | at most 100 items of `{id?, answer (required, <= 10000), is_correct?, order_number? >= 0, image_id? >= 1, image_url? (uri), matching_data? {label?, image_url?}}`; no other keys. `id` keeps an existing option row of this question (another question's option: 403 `ohmylms_option_foreign`). |
| `skills` | optional | optional, replaces the map | `{part: {primary: int >= 0, supporting: int[] (<= 50)}}`, at most 20 parts; term IDs must be skills. |
| `bank` | optional | optional, only sent keys change | `{bank_id >= 0, difficulty (enum), source <= 100, family_id <= 64, secure: bool}`; a `bank_id` the user cannot edit answers 403. |
| `thumbnail_id`, `video_id` | optional | optional | integer >= 0; a non-zero value must be an attachment (422 `ohmylms_question_invalid_media`). |
| `extension_settings` | optional | optional, merged by settings ID | object keyed by registered question extension settings; each is validated by its schema (400 `ohmylms_question_invalid_extension_settings`). Stored in the same transaction and part of the version. |
| `base_modified` | - | **required** | RFC 3339; the `modified` of the last read. A newer stored value answers 409 `ohmylms_question_conflict` with `data.modified`. |

Any other top-level body field, including `status` and `id`, answers 400 `ohmylms_question_unknown_field`
with `data.fields`. Archive and restore accept no body fields. The request body is capped at 256 KiB
(413 `ohmylms_question_too_large`).

### Shared settings rules

Checked whenever `settings` is sent, before anything is written. Failures answer 422
`ohmylms_question_invalid_settings` with `data.field` (`settings.<key>`).

| Setting | Rule |
| --- | --- |
| `required`, `randomize`, `partial_credit`, `case_sensitive` | `true`, `false`, `0`, `1`, `'0'`, `'1'` or `''` (legacy values stay accepted). |
| `hint`, `explanation` | A string of at most 65535 bytes. |
| `score` | An object; `enabled` on/off as above; `value` a number from 0 to 10000. |
| `blank_mode` | `''`, `drag` or `type`. |
| `match_layout` | `wire` or `grid`. |

### Option rules

Checked on create, and on update when `settings` or `options` is sent (or, for fill-in-the-blank, the
name), against the merged result (stored type, options or name for whatever was not sent). Failures answer
422 `ohmylms_question_invalid_options` with `data.field` (`options`, `options[i].answer` or
`options[i].matching_data.label`).

| Type | Rule |
| --- | --- |
| `single-choice`, `multiple-choice`, `true-false`, `reorder`, `matching` | At least two non-empty options (text after stripping tags, or an image). |
| `single-choice`, `true-false` | Exactly one correct option. |
| `true-false` | Exactly two options. |
| `multiple-choice` | At least one correct option. |
| `matching` | Every option has a non-empty `matching_data.label`. |
| `statement` | At least one option; every option has text and is correct (each is an expected answer). |
| `fill-in-the-blank` | A non-empty `{answer}` in the name, or at least one correct option with text. |

Other types are validated by their own `validate_settings` / editor schema in DraftWriter (400
`ohmylms_invalid_question_settings`): for example unscored types (`poll`, `word-cloud`, `slide` and the card
decks) must have 0 points, `labeling`/`hotspot` need an http(s) image, `hot-text` keys must name its tokens.

### Update semantics

PATCH, PUT and POST on `/questions/{id}` are the same partial update: omitted fields keep their stored
values. A request whose only field is `base_modified` changes nothing and returns the editor view. Every
content change is captured as a new immutable version; a save whose content equals the latest version reuses
it, so `version` only increases when something changed.

### Approve (`POST /questions/{id}/approve`)

Approves a version for shared use and practice. The only body field is optional `version_id` (integer >= 1);
any other field answers 400 `ohmylms_question_unknown_field`. Without it the current content is approved:
it is captured as a new version first if it changed since the last version, otherwise the current version
is reused (`version` does not change). The logic is the legacy route's, now in `QuestionBank\Approval`:

- the version must belong to this question, otherwise 404 `ohmylms_version_missing`;
- figure content must be publishable (`ExamImport\FigureContent::publishable()`), otherwise 409
  `exam_figure_review`;
- the type must support versioned snapshots, otherwise 409 `quiz_type_unversioned`;
- the identity row gets `approved_version_id` and `status = approved`, then
  `ohmylms_question_version_approved` ($question_id, $version_id, $user_id) fires.

The response is the editor view: `status` is `approved` (unless archived), `approved_version_id` is the
approved version and `approved_is_current` tells whether it is the current content. Permission is
`can_approve_question`: the bank's `approve` grant for questions in a bank, otherwise `edit_post` on the
question. A bank `edit` grant can edit but not approve (403 `ohmylms_question_cannot_approve`).

### Duplicate (`POST /questions/{id}/duplicate`)

Copies a question the user may use (shared-bank `use` grants included) as a new question owned by the user;
no body fields are accepted. The logic is the legacy route's, now in `QuestionBank\Duplicator`: the copy is
named "<name> (copy)" and keeps the body, settings, options (as new option rows), media, skill map,
difficulty, source and `secure`; it gets a new UUID, is version 1 and unapproved, and shares the source's
`family_id` (a new family is written to the source when it had none). It stays in the source's bank only if
the user can edit that bank. Extension settings are not copied (as in the legacy route). The legacy
`quiz_id` option (replace the source in a quiz) moved to `POST /quizzes/{id}/questions/copy` with `replace`
(below). Answers 201 with the copy's editor view and `Location`; `ohmylms_rest_save_question_item` fires with
`$creating = true`.

### Add to a quiz, or copy into a quiz

Under the quiz revision lock of [quizzes](quizzes.md): every write carries the `revision` of the quiz's question
list and is refused with 409 `ohmylms_quiz_questions_conflict` when the stored list differs.

- **The question itself** (shared by reference): the existing `POST /quizzes/{id}/questions`
  (`question_ids`, `pin`, `revision`).
- **A copy**: `POST /quizzes/{id}/questions/copy` with `question_ids` (1–50 unique), `replace` (default false)
  and `revision`; no other fields (400 `ohmylms_quiz_questions_unknown_field`). Needs `edit_post` on the quiz
  and, per question, `can_author` and `can_use_question` (403 `ohmylms_quiz_question_forbidden`); unknown or
  trashed 404 `ohmylms_question_not_found`; archived 409 `ohmylms_question_archived`. Unlike adding by reference,
  a shared question without an approved version can be copied, and so can a question already in the quiz. Each
  copy is made by `Duplicator` (as `POST /questions/{id}/duplicate`) and placed at the end (the 500-question
  limit applies). With `replace: true`, exactly one question that is in the quiz (else 422
  `ohmylms_quiz_question_replace_invalid`) is replaced by its copy in the same placement row: same position, its
  marks override and section move to the copy, its pin is dropped; the source stays in the bank. Copies and
  placements are one transaction. Answers 201 with the quiz question list plus
  `copied: [{source_id, id}]`; `ohmylms_rest_save_question_item` fires for each copy and
  `ohmylms_rest_quiz_questions_changed` with operation `copy`.

UI: the question bank list has row actions **Add to quiz** (not on archived questions) and **Delete** (on
questions the user may delete), and a **Delete** bulk action on the selected rows; the editor has **Add to a
quiz** next to Duplicate and a **Delete** section that says beforehand whether the question will be deleted,
archived (answered) or cannot be deleted (several quizzes). **Add to quiz** searches quizzes the user may edit
and offers "This question" (disabled with the reason for archived questions, shared questions without an
approved version, or a quiz that already holds it) or "A copy"; it reads the quiz's revision just before the
write and retries once if another editor changed the quiz in between. The delete dialog lists the outcome per
question and, when the server refuses some, shows why and offers to delete the others. The quiz editor's
**Add from question bank** picker has **Add as: the questions themselves / copies**.

## Examples

```http
POST /wp-json/ohmylms/v1/questions
X-WP-Nonce: <wp_rest nonce>
Content-Type: application/json

{ "name": "Solve 2x = 6", "settings": { "type": "single-choice", "score": { "enabled": true, "value": 2 } },
  "options": [ { "answer": "3", "is_correct": true }, { "answer": "12", "is_correct": false } ],
  "bank": { "difficulty": "easy", "source": "Textbook 4.2" } }
```

```http
HTTP/1.1 201 Created
Location: https://example.test/wp-json/ohmylms/v1/questions/812
Cache-Control: private, no-store

{ "id": 812, "uuid": "1b0c…", "name": "Solve 2x = 6", "version": 1, "status": "draft", … }
```

```http
PATCH /wp-json/ohmylms/v1/questions/812
Content-Type: application/json

{ "base_modified": "2026-10-30T13:05:12Z", "name": "Solve 2x = 8" }
```

Stale save:

```json
{ "code": "ohmylms_question_conflict",
  "message": "This question was changed by someone else. Reload it before saving.",
  "data": { "status": 409, "modified": "2026-10-30T13:07:40Z" } }
```

## Error codes

| HTTP | Code | When |
| --- | --- | --- |
| 400 | `rest_invalid_param`, `rest_missing_callback_param` | Argument schema (types, enums, ranges, lengths, unknown option keys, missing `name`/`settings`/`base_modified`). |
| 400 | `ohmylms_question_invalid_type` | `settings.type` missing, empty or not registered (`data.field`). |
| 400 | `ohmylms_question_unknown_field` | Body fields the route does not accept (`data.fields`). |
| 400 | `ohmylms_question_invalid_page` | `page` past the last page (list, versions). |
| 400 | `ohmylms_question_invalid_extension_settings` | Unregistered or invalid extension settings (`data.reason` is the underlying code). |
| 400 | `ohmylms_invalid_question_settings`, `ohmylms_question_invalid`, `ohmylms_skill_map_invalid`, `ohmylms_skill_missing`, `ohmylms_bank_invalid` | DraftWriter, SkillMap and BankAttributes validation. |
| 401 / 403 | `ohmylms_question_cannot_view`, `_cannot_create`, `_cannot_edit`, `_cannot_approve`, `_cannot_duplicate` | Not logged in (401) or missing the permission (403). |
| 403 | `ohmylms_question_bank_forbidden` | `bank` filter for a bank the user cannot see. |
| 403 | `ohmylms_forbidden`, `ohmylms_option_foreign` | A `bank_id` the user cannot edit; an option id of another question. |
| 403 | `rest_cookie_invalid_nonce` | Cookie auth without a valid `X-WP-Nonce` (core). |
| 404 | `ohmylms_question_not_found` | Unknown id, another post type, trash or auto-draft. |
| 404 | `ohmylms_version_missing` | Approve: `version_id` unknown or of another question. |
| 409 | `ohmylms_question_conflict` | Stale `base_modified` (`data.modified`). |
| 409 | `quiz_type_unversioned`, `exam_figure_review` | Approve: the type does not support versioned snapshots; figures are not reviewed. |
| 413 | `ohmylms_question_too_large` | Body above 256 KiB (`data.max_bytes`). |
| 422 | `ohmylms_question_invalid_name`, `ohmylms_question_invalid_options`, `ohmylms_question_invalid_media`, `ohmylms_question_invalid_settings` | Blank name, option rules, media IDs that are not attachments, shared settings rules (`data.field`). |
| 500 | `ohmylms_question_storage` | Storage failure; DraftWriter rolled the save back. |
| 503 | `ohmylms_question_bank_unavailable` | Question bank tables not installed. |

## Import and export

Server code: `includes/Rest/V1/QuestionImportController.php`, over `QuestionBank\BulkImport` (parse, check,
write through `DraftWriter`) and `QuestionBank\RowExporter`. Both routes need `edit_posts` (401 anonymous,
403 `ohmylms_question_cannot_import` / `_cannot_export`) and answer `Cache-Control: private, no-store`; the
row format is described in [QUESTION-BULK-IMPORT](../QUESTION-BULK-IMPORT.md).

**`POST /question-bank/import`** — `text` (≤ 2 000 000 bytes) or `rows` (≤ 200 objects), `format`
(`auto`, `csv`, `json`), `dry_run` (default true), `approve`, `update_existing` (default true), `skip_invalid`,
`bank_id` (≥ 0; must be a bank the user can edit), `default_skills` (≤ 2000), `review` (64 hex). Rows are
matched to earlier imports by exam, paper, year, section, number and language: `create`, `update`,
`unchanged` or `error` per row, so the same file imported twice changes nothing. A dry run writes nothing
and adds `review`, an HMAC of the caller, the rows, `bank_id`, `update_existing`, `default_skills` and every
row's planned status, target and errors. An import that sends `review` re-checks first and answers 409
`ohmylms_import_review_stale` without writing when anything differs (another file or option, a question
edited or imported meanwhile, another user); a used review is stale by construction. Without `skip_invalid`
any row error stops the whole import (`stopped`). The legacy Content Hub dialog still imports without
`review`; the new screen always sends it.

**`POST /questions/export`** — required `format` (`csv`, `json`) and exactly one of `ids` (1–200 unique
IDs, file order), `filters` (`search` ≤ 100, `type` (registered), `difficulty`, `status`
`active|draft|approved|archived`, `bank` ≥ 0 visible to the user, `skill`; no other keys) or `quiz_id` (the
questions of a quiz the user may edit, in quiz order, with their section titles; an unknown or trashed quiz
answers 404 `ohmylms_quiz_not_found`, a quiz the user cannot edit 403 `ohmylms_question_cannot_export`;
the native quiz editor's **Export questions** buttons use it); otherwise 400 `ohmylms_question_export_scope`.
Optional `label` ≤ 190 (the exam name written into every row; a quiz export defaults to the quiz title).
Answers `{ filename, mime, content, count, warnings, skipped }`.
The file holds answer keys, so only questions the user may edit are written: selected IDs answer 404
`ohmylms_question_not_found` for an unknown or trashed ID and 403 `ohmylms_question_cannot_export` if any
cannot be edited; filters and quizzes skip such questions (shared questions placed by reference) and report
them in `skipped`. Nothing to export answers 422 `ohmylms_question_export_empty`, more than 200 matches 422
`_export_too_many`. Re-importing an export on the same site plans copies (the exporter writes the label as the
exam, without paper or year).

UI: `#/content-hub/question-bank` has **Import questions** (paste or choose a file, **Check file**, review
every row, then **Import checked rows**; changing the text requires a new check; a stale refusal asks for a new
check), a selection checkbox on rows the user may edit, **Export N selected (CSV/JSON)** and **Export all
matching (CSV/JSON)** with the list's current filters. Deep links: `?action=new` opens the New question dialog
(which continues into the draft editor), `?action=import` the import dialog; the parameter is removed once
the dialog is open. Tests: `tests/php/question-exchange-rest-integration.php` (`OHMYLMS_QUESTIONS_TEST=1`) and
`features/questions/QuestionExchange.test.tsx`.

## Hooks

- Filter `ohmylms_rest_questions_query` ($filters, $request): list filters before `Search::query()`;
  visibility is applied afterwards and cannot be widened.
- Filter `ohmylms_rest_prepare_question_summary` ($data, $post, $request): one list row.
- Filter `ohmylms_rest_prepare_question_item` ($response, $post, $request): the editor view.
- Action `ohmylms_rest_save_question_item` ($post, $request, $creating): after create, update or
  duplicate (`$post` is the copy).
- Action `ohmylms_rest_delete_question` ($request, $result): after `DELETE /questions/{id}` (same name and
  arguments as on the removed `DELETE /question/{id}`).
- DraftWriter, Usage, VersionPublisher and Approval actions still fire (`ohmylms_question_draft_saved`,
  `ohmylms_question_archived`, `ohmylms_question_version_created`, `ohmylms_question_version_approved`, …).
  `ohmylms_rest_insert_question`, `ohmylms_rest_prepare_question` and `ohmylms_rest_ohmylms_question_query`
  belonged to the removed `/question` route and no longer fire; nothing in OhMyLMS or its add-ons hooked them.

## Security notes

- Cookie requests need the core REST nonce; application passwords also work. Every route has an object- or
  capability-level permission callback; item routes 404 before checking capabilities.
- Settings, options and answer keys are returned only in the editor view, which requires `edit` on the
  question. List rows, version rows and type rows contain no answer keys.
- Strings in settings, option text and matching labels are `wp_kses_post`-filtered for users without
  `unfiltered_html`; names and bodies are filtered by DraftWriter. Responses are JSON with
  `Cache-Control: private, no-store`.
- Only declared arguments reach the list query, which is prepared SQL with allow-listed sort columns.
- The legacy routes that exposed answer keys to `use` grantees, forwarded raw query vars and wrote on read
  are removed (below). A shared question an author may only *use* now shows in the native lesson and quiz
  editors as a read-only card without its content or answers (its 403 read is expected); the author can
  duplicate it to edit a copy.

## Removed legacy routes

Removed on 31 October 2026 after their last callers moved (the native question, quiz and lesson editors,
papers, the exam paper review, the Content Hub export buttons, the review queue and the MCP `list_questions`
/ `get_question` tools). Nothing in `docs/EXTENSIONS.md` or the `ohmylms-graded-reader` add-on used them.
They now answer 404 `rest_no_route`.

| Removed | Use instead |
| --- | --- |
| `GET /question` | `GET /questions` (no settings or options in rows) |
| `POST /question` | `POST /questions` (`options` instead of `questions`; `status` and `quiz_id` are refused); to create and place in a quiz, `POST /quizzes/{id}/questions/new` |
| `GET /question/{id}` | `GET /questions/{id}` (editors only) |
| `PUT /question/{id}` | `PATCH /questions/{id}` (`base_modified` required) |
| `DELETE /question/{id}` | `DELETE /questions/{id}`; with `quiz_id`: `DELETE /quizzes/{id}/questions/{question_id}` |
| `GET /question-bank` | `GET /questions` (array body and `X-WP-Total` instead of `{items, total, page, per_page}`; `organisation`, `scope`, `secure`, `exclude_quiz` kept) |
| `GET /question-bank/{id}` | `GET /questions/{id}` + `GET /questions/{id}/versions` (editors only; never writes) |
| `GET /question-bank/versions/{version_id}` | `GET /questions/{id}/versions/{version_id}` (editors only) |
| `POST /question-bank/{id}/approve`, `/archive`, `/restore`, `/duplicate` | `POST /questions/{id}/approve`, `/archive`, `/restore`, `/duplicate` (editor view responses; no `quiz_id` on duplicate) |
| `GET /question-bank/export` | `POST /questions/export` (`ids`, `filters` or `quiz_id`) |

`POST /question-bank/import` (typed, `QuestionImportController`) and the bank routes `/question-banks*` stay.
The quiz-placement routes `/quiz/{id}/content|questions|pools` are covered in [quizzes](quizzes.md#removed-legacy-routes).

## Tests

`tests/php/questions-rest-integration.php` runs against the Local site (`800.local`) and removes everything it
creates. On this machine:

```powershell
$env:OHMYLMS_QUESTIONS_TEST='1'; php -n -d extension_dir=C:\php8.5.4\ext -d extension=mysqli -d extension=mbstring -d extension=openssl -d extension=curl -d mysqli.default_port=10006 tests/php/questions-rest-integration.php
```

It covers route registration and permission callbacks, 401/403 on every route, object-level access between two
authors, every validation rule above, create/list/read/update/archive/restore/versions/types, stale saves,
extension settings versioning, that reads create no identity row, that a user without access never receives
answer text, and that the legacy routes are gone. Filters: `secure`, `exclude_quiz` (403 for a quiz the user
cannot edit or a post that is not a quiz), `organisation`/`scope`. Version content: editor read, unknown and
foreign versions, 401/403. Blank document: its author reads and fills it (it becomes a draft), others and its
sub-routes get 404. Delete: unknown body field, edit grant refused, 409 for a question in two quizzes,
deleted question and placement gone, then 404. Approve:
404 before capabilities, 403 for an author without rights and for a bank `edit` grant, unknown and foreign
`version_id`, approving the current and an older version, `approved_is_current` after a later edit, the
identity row status, the review queue approving through this route. Duplicate: new id and UUID with the same
content and family, bank placement for a bank editor, 403 for a user who cannot use the source.

`tests/php/question-types-rest-integration.php` (same flag) creates, reads, edits (version 2) and stale-saves
one question of every built-in type from `tests/php/fixtures/question-types.json` (and fails when a built-in
type has no fixture), and checks the shared-settings and per-type rules, media, option images, bank fields,
skills and that a draft's learner preview drops the answer key. App tests: `model.test.ts`,
`QuestionEditorPage.test.tsx` (every answer area, shared settings, bank and skills, preview),
`extensions/coreEditors.test.tsx` and `tests/js/core-question-editors.test.mjs` (every built-in settings type
registers an editor). `tests/browser/question-editor-parity.spec.cjs` (with
`tests/php/question-editor-browser-fixture.php`) drives every type through the editor on `800.local`, places
them in a quiz and has a temporary learner take it.

Delete and copy: `tests/php/question-delete-copy-integration.php` (same flag, 80 checks): routes, 401/403,
validation, the all-or-nothing preflight (shared, not allowed, unknown), `skip_blocked`, delete vs archive
(answered, in a published revision), the quiz left clean (pin, marks, section) with a new revision and a stale
editor refused, and copies (permissions, archived, unknown, stale revision, placement at the end, ownership,
`replace` moving position, marks and section and dropping the pin, a `use` grant copying an unapproved shared
question that it cannot add by reference). App tests: `QuestionDelete.test.tsx`, `deleteApi.test.ts`,
`quizzes/QuizQuestions.test.tsx` (copies from the picker).

## Not covered

- **Replace with a copy in the quiz editor**: `replace: true` has no button yet (the quiz question list would
  offer it on shared, read-only questions).
- **`short-text` and `long-text`** have no answer key and no rubric field on the server; graders see the
  response only. Marking notes exist for `structured`/`passage` parts.
- **Question extension settings** (`extension_settings`) have no editor in the new admin; none are registered
  on this site. The REST field is unchanged.
- **Unhelpful messages**: types whose `validate_settings` returns a `WP_Error` (labeling, hotspot,
  interactive video) are reported by `Extensions\Authoring` as "Invalid question settings."; the editor's
  type editors explain what is missing before saving.
- **Clearing all options**: DraftWriter treats an empty `options` list as "leave the options unchanged"
  (legacy semantics), so `options: []` cannot remove every option of a settings-based question.
- **Atomic stale check**: `base_modified` is compared before DraftWriter's transaction and again inside
  DraftWriter, without a row lock, so two saves in the same second can both pass.
- **Option `image_url`**: stored as option meta, but reads derive `image_url` from `image_id`
  (`QuestionStore::get_questions()`), so a URL without an attachment is not returned.
- **Settings filtering in other writers**: string leaves of settings are filtered here only; DraftWriter's
  other callers (such as the bulk import) still store settings strings unfiltered.
- **Per-row permissions** in the list call `AccessPolicy` per row; for questions in shared banks this costs a
  bank lookup each (all other related data is batched).
- **Suites on other databases** were moved to the typed routes but not run here: the fixtures and checks of
  `native-question-editor-integration.php`, `native-quiz-editor-integration.php`, `question-rows-integration.php`,
  `pdf-exam-http.php`, `assessment-browser-fixture.php`, `learning-browser-fixture.php` and the browser specs.
  The `ohmylms_source_test` assessment suite (`tests/php/assessment-integration.php` and `tests/php/assessment/*`)
  only had its approvals and archives moved; its question creation (`POST question` with `quiz_id` and
  `questions`), legacy reads, pins, adds, duplicates and `quiz/{id}/pools` calls still target removed routes and
  need porting to `POST /quizzes/{id}/questions/new`, `/quizzes/{id}/questions/*` and `/questions/*` before it
  can pass again.
