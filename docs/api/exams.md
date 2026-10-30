# Exams REST API (`ohmylms/v1/exams/library`, `ohmylms/v1/exam-imports/jobs`)

Contract version 1. This is the typed API of the new admin's Exams screens (`#/exams`,
`#/exams/:id`, `#/exams/imports/:jobId`; source in `assets/src/app/features/exams`). It sits next to the
legacy `/exams`, `/exams/compose`, `/exams/{id}/settings|questions|publish|release` and `/exam-imports…`
routes, which the old Content Hub Exams tab and the quiz editor's exam panel still call and which keep
their routes and response shapes.

Server code: `includes/Rest/V1/ExamsController.php` and `includes/Rest/V1/ExamImportsController.php`
(routes, arguments, permissions, responses); `includes/Exams/Editor.php` (settings, composition,
revision token) and `includes/ExamImport/Review.php` (row edit/accept/reject, apply). Creation, commit,
exam/quiz creation, publication, extraction and crops still go through the existing services:
`Exams\Composer`, `Exams\Library`, `ExamImport\Jobs`, `ExamImport\Workflow`, `ExamImport\Figures`,
`Learning\NativeQuizEditor::sync()` (placements) and `Assessment\AssessmentSettings` (sections, timing).
Design background: [EXAM-IMPORT-JOBS.md](../EXAM-IMPORT-JOBS.md),
[PRIVATE-FIGURE-ASSETS.md](../PRIVATE-FIGURE-ASSETS.md), [PRINTABLE-EXAMS.md](../PRINTABLE-EXAMS.md).

## Audit before

Findings in the existing exam model and routes (October 31, 2026). The legacy routes are unchanged
except where noted.

| Severity | Finding |
| --- | --- |
| High | **Exam creation was broken.** `Library::scaffold()` created the wrapper course through `POST /courses` with `name`; the hardened courses API now requires `title`, so every new exam (blank or imported) failed with 500 `exam_storage`. Fixed in `Library::scaffold()`; the two exam rehearsals that create courses (`exam-import-integration.php`, `figure-assets-integration.php`) had the same fixture bug and were fixed too. |
| High | **No stale-write protection on exams.** `POST /exams/{id}/settings` and `/questions` overwrite silently, outside any lock, and edits made meanwhile in the Gutenberg quiz editor are lost. Here every write carries a `revision` token covering the exam record, the backing quiz's title, settings, assessment settings and placements, checked under the exam lock inside one transaction. |
| Medium | **Unapproved shared questions could be added.** `POST /exams/{id}/questions` checks `can_use_question`, but not the rule the quiz editor enforces (a question the user cannot edit must have an approved version). Here composition enforces it (409 `ohmylms_exam_question_unapproved`). |
| Medium | **Review was all-or-nothing.** `POST /exam-imports/{id}/review` replaces the whole draft and commit requires every row reviewed; a wrong or duplicate question could not be left out. Rows can now be accepted or rejected one by one; rejected rows are skipped by `Workflow::commit()` (also for the legacy commit route) and listed in the legacy status as `rejected`. |
| Medium | **Loose validation.** Legacy create accepts any numeric duration string (`"1.5"`), unbounded titles and 4000-character header fields; settings save any `instructions` type silently as empty; `release` runs on drafts. Here arguments are typed and bounded (see below) and unknown body fields answer 400. |
| Low | **Figure delivery for owners** uses `?ohmylms_figure=<uuid>` on the front end (owner checks in `FigureContent::path()`, `no-store`, `same-origin` CORP, no nonce). The new review screen reads crops only through an authenticated REST route that also checks that the crop belongs to the job in the URL. The front-end route stays for learners and previews. |
| Low | Listings answer `{items, total}` without page headers or bounds on `page`; responses carrying rubrics and answers have no `Cache-Control`; `Workflow::extract()` reads `$job['state']` without checking the record. |
| OK | Ownership is enforced everywhere: exams and import jobs are visible to their author only (administrators included); import routes need `edit_posts` and `upload_files`; publication and release need `publish_posts`. Private sources live outside the web root with checksums; crops are verified on every read; Action Scheduler stages are bounded and lock-serialised; commit and exam creation run in transactions and are idempotent. |

Capabilities: exams `edit_posts` plus authorship (`publish_posts` to publish); imports `edit_posts`,
`upload_files` plus job authorship. Private figures: job owner (this API and the legacy owner preview),
authorised question users for reviewed content, learners only for their own attempt's exact version
(unchanged). Job ownership is `post_author` of the `ohmylms-import` record.

## Final routes

Anonymous callers get 401, logged-in users without the capability 403. Item routes answer 404 for an
unknown ID or another post type before the capability check; another author's exam or job answers 403.
Core validates declared arguments before the permission callback, so a malformed request can answer 400
first. Bodies above 256 KiB (exams) or 1 MiB (imports, uploads excepted) answer 413. Every response
carries `Cache-Control: private, no-store`. Collections send `X-WP-Total` and `X-WP-TotalPages`; a page
past the end answers 400 `ohmylms_exam_invalid_page` / `ohmylms_exam_import_invalid_page`.

| Method | Path | Purpose | Success |
| --- | --- | --- | --- |
| GET | `/exams/library` | The author's exams: `page`, `per_page` (1-100), `search` (<= 100), `status` (`all`, `draft`, `publish`) | 200 summaries |
| POST | `/exams/library` | New blank draft: `title` (1-190), `duration_minutes` (1-1440), through `Composer::create()` | 201 editor view + `Location` |
| GET | `/exams/library/{id}` | Editor view | 200 |
| PATCH/PUT/POST | `/exams/library/{id}` | Settings, partial: `revision` (required), `title`, `duration_minutes`, `instructions` (<= 4000), `header`, `timing`, `paper` | 200 editor view |
| PUT/PATCH/POST | `/exams/library/{id}/composition` | Replace sections and questions: `revision`, `items` | 200 editor view |
| GET | `/exams/library/{id}/bank` | Questions the author may add (`Search::query()` visibility, excluding those already placed): `search`, `page`, `per_page` (1-50) | 200 rows |
| POST | `/exams/library/{id}/publish` | `revision`, `confirm_overlap`; the legacy gate (`Library::publish()`) | 200 editor view |
| GET | `/exam-imports/jobs` | The owner's jobs: `page`, `per_page` (1-50) | 200 summaries |
| POST | `/exam-imports/jobs` | Start: multipart `file` (PDF, with optional `mark_scheme`) or CSV/JSON file, or `text`; `target` (`exam`, `quiz`, `questions`), `format`, `name`, `default_skills`, `skip_invalid`, `dry_run` | 201 job detail + `Location`; 200 report for `dry_run` |
| GET | `/exam-imports/jobs/{id}` | Job detail with rows (also the poll) | 200 |
| DELETE | `/exam-imports/jobs/{id}` | Delete job and private sources (`Jobs::delete()`; refused while an exam uses it) | 200 `{deleted, id}` |
| POST | `/exam-imports/jobs/{id}/extract` | Start/resume AI extraction (`Workflow::extract()`) | 200 job detail |
| POST | `/exam-imports/jobs/{id}/retry` | Requeue failed validation (`Jobs::retry()`) | 200 job detail |
| PATCH/PUT/POST | `/exam-imports/jobs/{id}/rows/{index}` | Edit one row: `revision`, `question`, `page`, `requires_source`, `notes`, `skills`, `parts`, `figures` | 200 job detail |
| POST | `/exam-imports/jobs/{id}/rows/{index}/accept` and `/reject` | `revision` | 200 job detail |
| POST | `/exam-imports/jobs/{id}/apply` | Commit accepted rows once, then create the exam or quiz once: `revision`, `title`, `duration_minutes`, header fields, `instructions` | 200 job detail |
| GET / POST | `/exam-imports/jobs/{id}/figures` | List crops / queue a crop (`page` 1-500, `bounds` four numbers 0-1) | 200 / 201 |
| GET | `/exam-imports/jobs/{id}/figures/{asset}` | The crop PNG for the owner (`nosniff`, `same-origin`) | 200 image |
| GET | `/exam-imports/skills` | Skill catalogue search for tagging: `search` (1-200), `page` | 200 `{id, name, code, path}` |

`{index}` is the zero-based position of a row in the paper. Figure URLs carry a `wp_rest` nonce so an
`<img>` can load them with cookie authentication.

## Exams

Summary: `id`, `title`, `status` (`draft`, `publish`), `origin` (`composed` written in OhMyLMS,
`imported` from a job), `job_id`, `quiz_id`, `duration_minutes`, `total_marks`, `question_count`,
`released`, `updated_at` (RFC 3339 UTC), `revision` (40 hex characters).

The editor view adds `header` (`board`, `qualification`, `subject_code`, `paper`, `variant`, `session`,
`calculator`), `instructions`, `timing` (`duration_minutes`, `available_from`, `available_until`,
`grace_seconds`, `feedback_release`), `paper` (`PaperSettings`: `size`, `margin`, `font_size`, `font`,
`numbering`, `show_marks`, `lines_per_mark`, `page_numbers`, `default_view`, `versions`),
`composition`, `problems` and `warnings` (what blocks or affects publication), `links` (`player`,
`preview`, `editor`, `paper`, `key`) and `permissions` (`can_edit`, `can_compose`, `can_publish`).

Storage: title on the exam, the backing quiz and its wrapper course; duration as the quiz time limit
(`_quiz_settings.time_limit`, minutes) and in the exam record; header and instructions in `_ohmylms_exam`;
timing and sections in `_ohmylms_assessment_settings` (validated by `AssessmentSettings`, 422
`ohmylms_exam_invalid_timing`); paper settings in `_quiz_settings.paper`, merged with the stored values.

Composition `items`, in order: `{kind: "section", title, new_page}` or `{kind: "question", question_id}`.
Questions before the first section are not in a section. Read items add `name`, `type`, `marks`,
`approved` (the pinned or current version is approved) and `can_edit`. Saving rewrites placements with
`NativeQuizEditor::sync()` (removal through `Usage::remove_from_quiz()`), then sections; slot marks of
questions that stay are kept. The Gutenberg document is reconciled to the placements when it is next
opened. Rules: at most 200 questions and 50 sections; a question once; section titles 1-190; new
questions must be allowed in quizzes and usable (403 `ohmylms_exam_question_unavailable`), and approved
when the author cannot edit them (409 `ohmylms_exam_question_unapproved`). An imported exam keeps the
questions and order of its reviewed paper (409 `ohmylms_exam_questions_locked`); only sections change.

Every settings, composition and publish request needs the current `revision`; otherwise 409
`ohmylms_exam_conflict` with `data.revision`. Published exams answer 409 `ohmylms_exam_published` to
every write. Publishing keeps the legacy behaviour: completeness problems (409 `exam_incomplete`), the
practice-overlap warning (409 `exam_overlap` until `confirm_overlap`), approval of the pinned versions
for composed exams, approved versions required for imported ones (409 `exam_approval`).

## Imports

Job summary: `id`, `title`, `kind` (`pdf`, `rows`), `state` (`queued`, `failed`,
`ready_for_extraction`, `extracting`, `extraction_failed`, `ready_for_review`, `committed`), `target`,
`attempts`, `error`, `validation_error`, `source` (`name`, `bytes`), `has_mark_scheme`, `revision` (the
draft revision), `row_count`, `accepted_count`, `rejected_count`, `question_ids`, `exam_id`, `quiz_id`,
`updated_at`, `can_extract`, `can_apply`. Detail adds `inventory` (`title`, `duration_minutes`,
`total_marks`, `page_count`), `usage.requests` and `rows`: `index`, `number`, `status` (`pending`,
`accepted`, `rejected`), `question`, `page`, `requires_source`, `notes`, `skills`, `parts` (with
answers and teacher rubrics), `figures`, `figures_complete`, `marks`. No source handles, hashes, paths or
provider responses are returned.

- **Start.** A `.pdf` file goes to `Jobs::create()` (private storage, validation queued); poll the job.
  A `.csv`, `.json` or `.txt` file (<= 1 MiB) or `text` goes to `Workflow::rows()` and returns a
  committed `rows` job; `dry_run` returns `{dry_run, counts, rows: [{row, status, errors, warnings}]}`
  from `BulkImport` without writing.
- **Edit.** Changes are merged into the row and the whole draft is validated by
  `Extraction::questions()` (422 `ohmylms_exam_import_invalid_row`, `data.reason`); figure crops must
  belong to this job and owner (403 `ohmylms_exam_import_figure_forbidden`). An edit returns the row to
  `pending`. Only a job in `ready_for_review` is editable (409 `ohmylms_exam_import_not_reviewable`).
- **Accept** attests to the row and its figures: rows needing the source figure must have one (409
  `ohmylms_exam_import_figures_required`); every figure must be approved, described and ready (409
  `ohmylms_exam_import_figures_unreviewed`). **Reject** leaves the row out of the commit.
- **Apply** needs every row decided and at least one accepted (409 `ohmylms_exam_import_unreviewed`),
  commits through `Workflow::commit()`, then creates the exam (`Library::create()`, title and duration
  default to the inventory) or quiz (`Workflow::quiz()`). Repeating apply returns the same questions,
  exam or quiz.

Every row and apply request carries `revision`; a different stored revision answers 409
`ohmylms_exam_import_conflict` with `data.revision`. Writes run under the job lock.

## Error codes

| HTTP | Code | When |
| --- | --- | --- |
| 400 | `rest_invalid_param`, `rest_missing_callback_param` | Argument schema (types, enums, ranges, lengths, missing `title`, `duration_minutes`, `revision`, `items`, `search`). |
| 400 | `ohmylms_exam_unknown_field`, `ohmylms_exam_import_unknown_field` | Body fields the route does not accept (`data.fields`). |
| 400 | `ohmylms_exam_import_empty`, `_file_type`, `_upload`; `exam_upload`, `exam_pdf`, `exam_questions` | Nothing to import, unsupported file, bad upload; legacy upload and row validation. |
| 401 / 403 | `ohmylms_exam_cannot_view`, `_cannot_create`, `_forbidden`, `_cannot_publish`; `ohmylms_exam_import_cannot_view`, `_forbidden`, `_figure_forbidden` | Not logged in, missing capability, not the owner, a foreign crop. |
| 404 | `ohmylms_exam_not_found`, `ohmylms_exam_import_not_found`, `_row_not_found`, `_figure_not_found` | Unknown ID or type; no such row; crop not in this job. |
| 409 | `ohmylms_exam_conflict`, `ohmylms_exam_import_conflict` | Stale `revision` (`data.revision`). |
| 409 | `ohmylms_exam_published`, `_questions_locked`, `_question_unapproved`; `exam_incomplete`, `exam_overlap`, `exam_approval` | Edit after publication; imported questions; unapproved shared question; publication gate. |
| 409 | `ohmylms_exam_import_not_reviewable`, `_figures_required`, `_figures_unreviewed`, `_unreviewed`, `_not_ready`, `_figure_unavailable`; `exam_source_in_use`, `exam_state`, `exam_busy` | Review state and figure gates; job used by an exam; extraction state; lock busy. |
| 413 | `ohmylms_exam_too_large`, `ohmylms_exam_import_too_large` | Body too large. |
| 422 | `ohmylms_exam_invalid_field` (`data.field`), `_invalid_timing`, `_invalid_sections`; `ohmylms_exam_import_invalid_row` | Blank title, duration, sections, duplicate question; `AssessmentSettings` timing; row content. |
| 500 / 503 | `ohmylms_exam_storage`, `exam_storage`, `exam_commit`, `ohmylms_exam_import_storage`; `exam_scheduler`, `exam_provider`, `ohmylms_question_bank_unavailable` | Storage failure (rolled back); scheduler, AI provider or bank tables unavailable. |

## Known gaps

- The revision check and write share the exam lock and a transaction, but the Gutenberg editor and the
  legacy routes do not take that lock; their writes change the token (a stale client still gets 409), yet
  one landing between the check and the commit is not blocked. Publish checks the revision just before
  `Library::publish()` takes the lock.
- Apply is two steps (commit, then exam/quiz creation), each in its own transaction; a failure after the
  commit is resumed by repeating apply.
- Not in this API (legacy routes stay): results release, JSXGraph reconstruction proposals, the renderer
  probe and the side-by-side source PDF.
- Bank picker totals include questions marked `available: false` (shared and unapproved, or not allowed
  in quizzes) instead of filtering them, so page totals stay exact.
- The crop picker requests crops by percentage bounds; the legacy drag-selection on the rendered page is
  not rebuilt.

## Tests

`tests/php/exams-rest-integration.php` (137 checks) runs against the Local site inside one rolled-back
transaction, with mail and HTTP blocked (no AI provider is called):

```powershell
$env:OHMYLMS_EXAMS_TEST='1'; php -n -d extension_dir=C:\php8.5.4\ext -d extension=mysqli -d extension=mbstring -d extension=openssl -d extension=curl -d mysqli.default_port=10006 tests/php/exams-rest-integration.php
```

It covers route registration (and that the legacy routes remain); 401/403 for guests, subscribers,
contributors without `upload_files`, another author and an administrator who is not the owner; 404 before
capabilities; create/read/list validation; stale settings, composition, publish, row and apply writes;
timing, paper, header and section storage on the backing quiz; reorder, removal and the unavailable
question rule; imported exams' locked questions; CSV dry run and rows jobs; cross-owner job, row, apply,
delete, figure list and figure image access; foreign crops in row edits; figure and requires-source
gates; reject leaving a row out of the commit; apply idempotence (one exam, same questions); the
committed figure on the question version; the skill search. The existing rehearsals still pass:
`exam-import-integration.php --local` (22), `figure-assets-integration.php --local` (89),
`import-targets-integration.php --local` (32). The multipart PDF path needs a real HTTP upload and is
covered by `pdf-exam-http.php`, which needs the disposable database and was not run.

Front end: `npx vitest run --configLoader runner features/exams` (30 tests: contract parsers,
validation, composition helpers, multipart upload, list/editor/review screens, conflicts, polling).
