# Questions REST API (`ohmylms/v1/questions`)

Contract version 1. This is the typed API of the new admin app's Question Bank list and single-question
editor. It sits next to the legacy `ohmylms/v1/question` and `ohmylms/v1/question-bank` routes, which the
old admin still uses and which keep their routes and response shapes. Server code:
`includes/Rest/V1/QuestionsController.php` (routes, arguments, validation, responses) and
`includes/QuestionBank/Search.php` (the visibility-scoped list query, shared with `/question-bank`).
Writes go through `includes/QuestionBank/DraftWriter.php`; archive and restore through
`includes/QuestionBank/Usage.php`; approval through `includes/QuestionBank/Approval.php` and duplication
through `includes/QuestionBank/Duplicator.php` (both shared with the legacy `/question-bank` routes);
object permissions come from `includes/QuestionBank/AccessPolicy.php`.

## Audit before

Findings in the legacy routes this API replaces for the new admin. The legacy routes are unchanged.

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
capability check. Core validates declared arguments before the permission callback, so a malformed request
can answer 400 before 401/403; a body above 256 KiB answers 413 before anything else. Every successful
response carries `Cache-Control: private, no-store`.

| Method | Path | Permission | Success | Errors |
| --- | --- | --- | --- | --- |
| GET | `/questions` | `edit_posts` (author); a named `bank` must be visible to the user | 200 summaries + `X-WP-Total`, `X-WP-TotalPages`, `Link` | 400, 401, 403, 503 |
| POST | `/questions` | `edit_posts` | 201 editor view + `Location` | 400, 401, 403, 409, 413, 422, 500, 503 |
| GET | `/questions/types` | `edit_posts` | 200 type list | 401, 403 |
| GET | `/questions/{id}` | `AccessPolicy::can_edit_question` | 200 editor view | 401, 403, 404, 503 |
| PATCH / PUT / POST | `/questions/{id}` | `can_edit_question` | 200 editor view | 400, 401, 403, 404, 409, 413, 422, 500, 503 |
| POST | `/questions/{id}/archive` | `can_edit_question` | 200 editor view | 400, 401, 403, 404, 500, 503 |
| POST | `/questions/{id}/restore` | `can_edit_question` | 200 editor view | 400, 401, 403, 404, 500, 503 |
| POST | `/questions/{id}/approve` | `AccessPolicy::can_approve_question` | 200 editor view | 400, 401, 403, 404, 409, 503 |
| POST | `/questions/{id}/duplicate` | `can_author` and `AccessPolicy::can_use_question` | 201 editor view of the copy + `Location` | 400, 401, 403, 404, 500, 503 |
| GET | `/questions/{id}/versions` | `can_edit_question` | 200 versions + totals | 400, 401, 403, 404, 503 |

`503 ohmylms_question_bank_unavailable` means the question bank tables (`Assessment\Schema::ready()`) are
not installed. Delete is not part of this API; the legacy `DELETE /question/{id}` (delete, or archive when
learners have answered it) remains the only delete route.

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
bank; reading it does not create the identity row. `image_url` of an option is derived from `image_id`.

### Versions (`GET /questions/{id}/versions`)

Newest first, paged with `page` / `per_page` (as above) and `X-WP-Total`:

```json
[ { "id": 95, "version_no": 3, "created_at": "2026-10-30T13:05:12Z", "created_by": { "id": 7, "name": "Alex" },
    "is_current": true, "is_approved": false } ]
```

Content and content hashes are not exposed; the legacy `GET /question-bank/versions/{version_id}` returns a
version's content to users who may use the question.

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

### Option rules

Checked on create, and on update when `settings` or `options` is sent, against the merged result (stored
type or options for whichever was not sent). Failures answer 422 `ohmylms_question_invalid_options` with
`data.field` (`options`, or `options[i].matching_data.label`).

| Type | Rule |
| --- | --- |
| `single-choice`, `multiple-choice`, `true-false`, `reorder`, `matching` | At least two non-empty options (text after stripping tags, or an image). |
| `single-choice`, `true-false` | Exactly one correct option. |
| `true-false` | Exactly two options. |
| `multiple-choice` | At least one correct option. |
| `matching` | Every option has a non-empty `matching_data.label`. |

Other types are validated by their own `validate_settings` / editor schema in DraftWriter.

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
`quiz_id` option (replace the source in a quiz) is not offered here. Answers 201 with the copy's editor view
and `Location`; `ohmylms_rest_save_question_item` fires with `$creating = true`.

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
| 422 | `ohmylms_question_invalid_name`, `ohmylms_question_invalid_options`, `ohmylms_question_invalid_media` | Blank name, option rules, media IDs that are not attachments (`data.field`). |
| 500 | `ohmylms_question_storage` | Storage failure; DraftWriter rolled the save back. |
| 503 | `ohmylms_question_bank_unavailable` | Question bank tables not installed. |

## Hooks

- Filter `ohmylms_rest_questions_query` ($filters, $request): list filters before `Search::query()`;
  visibility is applied afterwards and cannot be widened.
- Filter `ohmylms_rest_prepare_question_summary` ($data, $post, $request): one list row.
- Filter `ohmylms_rest_prepare_question_item` ($response, $post, $request): the editor view.
- Action `ohmylms_rest_save_question_item` ($post, $request, $creating): after create, update or
  duplicate (`$post` is the copy).
- DraftWriter, Usage, VersionPublisher and Approval actions still fire (`ohmylms_question_draft_saved`,
  `ohmylms_question_archived`, `ohmylms_question_version_created`, `ohmylms_question_version_approved`, …). The legacy
  `ohmylms_rest_insert_question` / `ohmylms_rest_prepare_question` hooks belong to `/question` and do not fire here.

## Security notes

- Cookie requests need the core REST nonce; application passwords also work. Every route has an object- or
  capability-level permission callback; item routes 404 before checking capabilities.
- Settings, options and answer keys are returned only in the editor view, which requires `edit` on the
  question. List rows, version rows and type rows contain no answer keys.
- Strings in settings, option text and matching labels are `wp_kses_post`-filtered for users without
  `unfiltered_html`; names and bodies are filtered by DraftWriter. Responses are JSON with
  `Cache-Control: private, no-store`.
- Only declared arguments reach the list query, which is prepared SQL with allow-listed sort columns.
- The legacy routes still expose, unchanged: `GET /question` (raw `filter` vars into `WP_Query`, settings and
  options with `is_correct` for questions the user can only use, totals that include hidden questions);
  `GET /question-bank/{id}` (settings and options to users with `use`; creates the identity row on read);
  `GET /question-bank/versions/{version_id}` (frozen content including options to users with `use`).
  Retire them once the old admin no longer needs them.

## Tests

`tests/php/questions-rest-integration.php` runs against the Local site (`800.local`) and removes everything it
creates. On this machine:

```powershell
$env:OHMYLMS_QUESTIONS_TEST='1'; php -n -d extension_dir=C:\php8.5.4\ext -d extension=mysqli -d extension=mbstring -d extension=openssl -d extension=curl -d mysqli.default_port=10006 tests/php/questions-rest-integration.php
```

It covers route registration and permission callbacks, 401/403 on every route, object-level access between two
authors, every validation rule above, create/list/read/update/archive/restore/versions/types, stale saves,
extension settings versioning, that reads create no identity row, that a user without access never receives
answer text, and that `GET /question-bank` returns the same ids and totals as `GET /questions`. Approve:
404 before capabilities, 403 for an author without rights and for a bank `edit` grant, unknown and foreign
`version_id`, approving the current and an older version, `approved_is_current` after a later edit, the
identity row status. Duplicate: new id and UUID with the same content and family, bank placement for a bank
editor, 403 for a user who cannot use the source. The legacy `/question-bank/{id}/approve` and `/duplicate`
routes still answer with their own summaries and permissions.

## Not covered

- **Delete**: left to the legacy `DELETE /question/{id}`.
- **Duplicate into a quiz**: the legacy `/question-bank/{id}/duplicate` with `quiz_id` (the copy replaces the
  source in that quiz) has no equivalent here.
- **`fill-in-the-blank` and `short-text` option rules**: not enforced. Fill-in-the-blank has two answer
  storages (`{braces}` in the name parsed by `InlineBlanks`, or correct options compared by text in
  `QuestionTypes::grade_builtin()`), and short-text is graded manually with no stored key.
- **Clearing all options**: DraftWriter treats an empty `options` list as "leave the options unchanged"
  (legacy semantics), so `options: []` cannot remove every option of a settings-based question.
- **Atomic stale check**: `base_modified` is compared before DraftWriter's transaction and again inside
  DraftWriter, without a row lock, so two saves in the same second can both pass.
- **Option `image_url`**: stored as option meta, but reads derive `image_url` from `image_id`
  (`QuestionStore::get_questions()`), so a URL without an attachment is not returned.
- **Settings filtering in other writers**: string leaves of settings are filtered here only; DraftWriter and
  the legacy `/question` route still store settings strings unfiltered.
- **Per-row permissions** in the list call `AccessPolicy` per row; for questions in shared banks this costs a
  bank lookup each (all other related data is batched).
- The `ohmylms_source_test` assessment suite (`tests/php/assessment-integration.php`), which also exercises
  `/question-bank`, needs its own database and was not run on `800.local`.
