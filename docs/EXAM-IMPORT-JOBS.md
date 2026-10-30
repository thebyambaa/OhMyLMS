# Private PDF import jobs

The Exams tab accepts a question PDF and optional mark scheme, validates private storage,
then offers explicit AI extraction and side-by-side teacher review. Committing drafts,
approving versions, creating an exam and publishing it are separate owner actions.

## Storage configuration

Create a directory outside every web-server document root and public alias, writable only by
the PHP service account. Set `OHMYLMS_IMPORT_PRIVATE_DIR` in `wp-config.php` to its absolute path.
The directory must already exist; the plugin fails closed if it is missing, unwritable, or
resolves inside WordPress, `wp-content` or the current server document root. The administrator
must also keep it outside other served aliases, which PHP cannot discover. Back up private
sources together with WordPress job records. No public uploads or Media Library copies are made.

Files use generated UUID names and restrictive permissions. Client paths and document IDs are
never accepted for upload. A source is limited to the smaller of 10 MiB and WordPress's upload
limit. Extension, MIME, PHP upload status, size and PDF header are checked. This is not a PDF
parser or malware scan: deeper PDF validation belongs to the bounded extraction stage.

## Authoring REST API

All endpoints require `edit_posts` and `upload_files`; existing jobs are visible only to their
owner, including when another administrator requests them. WordPress REST authentication and
cookie nonce verification remain in effect. Request IDs are job IDs, not source-document IDs.

| Request | Behavior |
|---|---|
| `POST /wp-json/ohmylms/v1/exam-imports` | Multipart `file` and optional `mark_scheme`; store private sources and queue validation. Returns 201 with job ID. |
| `GET /wp-json/ohmylms/v1/exam-imports?page=1` | Current owner's jobs, newest first, 20 per page. |
| `GET /wp-json/ohmylms/v1/exam-imports/{id}` | Safe status, attempts, error code, update timestamp and source name/size. |
| `POST /wp-json/ohmylms/v1/exam-imports/{id}` | Retry failed validation or recover an interrupted queued job, within three attempts. |
| `DELETE /wp-json/ohmylms/v1/exam-imports/{id}` | Delete job, private source and pending scheduler actions. |
| `POST /wp-json/ohmylms/v1/exam-imports/{id}/extract` | Explicitly start/resume extraction through the configured AI provider. |
| `POST /wp-json/ohmylms/v1/exam-imports/{id}/review` | Save teacher edits with optimistic draft revision checking. |
| `POST /wp-json/ohmylms/v1/exam-imports/{id}/commit` | Validate and commit reviewed rows through the existing bank writer. |
| `GET /wp-json/ohmylms/v1/exam-import-skills?search=algebra` | Search the existing skill catalogue during review. |

The new admin uses the typed `exam-imports/jobs` routes instead (row-by-row edit, accept, reject and an
idempotent apply); see [api/exams.md](api/exams.md). Rows rejected there are left out of every commit,
including this legacy commit route, and are listed in the status as `rejected`.

Job records use a nonpublic `ohmylms-import` persistence type. It is separate from the
Exam content type and is absent from WordPress's generic REST API, search, rewrite and admin UI.
Owner status includes review candidates and private answer/rubric fields. It never includes
filesystem paths, document handles, content hashes or raw provider responses.

Action Scheduler must be initialized before an upload is accepted. Hook
`ohmylms_validate_exam_import` in group `ohmylms-exam-import` runs one bounded validation:
check the owner's current capabilities, private source availability, byte count and SHA-256.
The state changes from `queued` to `ready_for_extraction` or `failed`. Duplicate deliveries of
completed stages do nothing. Database advisory locks serialize validation, retry and deletion.
If a runner is interrupted or cannot obtain the lock, an owner can requeue the persistent job;
there is no automatic unbounded retry loop.

Extraction uses a separate `ohmylms_extract_exam_import` hook with generation/cursor tokens.
It inventories up to 200 questions/pages and extracts five questions per request, with three
attempts per stage and at most 125 provider requests per job. Each source is capped at 10 MiB.
Truncated, malformed or incomplete requested batches fail validation. AI review flags cannot
self-approve questions; saved teacher edits survive extraction retries. Source identity is
stable per owner, file hash and printed question number for repeat imports.

Native PDF wire formats follow the providers' documentation:
[OpenAI](https://developers.openai.com/api/docs/guides/file-inputs),
[Anthropic](https://platform.claude.com/docs/en/build-with-claude/pdf-support) and
[Gemini](https://ai.google.dev/gemini-api/docs/document-processing).
The configured provider/model must support PDFs. Tests mock transport; actual extraction
quality and provider compatibility must be checked with the supplied papers.

`POST /ohmylms/v1/exams` creates a private Exam from the committed job. Separate
`/exams/{id}/publish` and `/exams/{id}/release` operations publish approved frozen versions
and release feedback. The player uses a managed private course and the existing quiz engine.
Source routes `?ohmylms_import_source=JOB` and `?ohmylms_exam_source=EXAM` enforce ownership
or actual attempt access. Learners cannot access a scheme until submission and release;
add `&mark_scheme=1` to select it. Jobs referenced by an Exam cannot be deleted.

## Runtime verification (October 18, 2026)

- `tests/php/exam-import-integration.php`: 22 checks on both the owner's installed Local LMS and
  disposable WordPress. Dry run, repeated import, review-only AI imports, explicit approval,
  changed draft versions, frozen delivery/grading, private rubric, teacher marking and skill evidence.
- Existing assessment phases `math versioning bank evidence`: 190 checks on disposable WordPress.
- `tests/php/exam-import-http.php`: 17 checks of multipart upload and real job persistence/scheduler dispatch on
  disposable WordPress. Tests ownership, listing, path rejection, idempotence, integrity failures,
  bounded retry and source cleanup. Its synthetic header fixture verifies storage, not PDF extraction.

Both new rehearsals roll back fixture database writes. The Local runner refuses nontransactional
tables and persistent object caches, blocks mail/network and removes fixture shutdown writers.
It permits only the explicit `--local` checkout in a Local WordPress environment; other runs
require `OHMYLMS_TEST_CREDENTIALS` and the disposable `ohmylms_source_test` database.

Example CLI for this Local installation (check Local's current MySQL port):

```powershell
php -d extension_dir=C:/php8.5.4/ext -d extension=mysqli -d extension=mbstring -d mysqli.default_port=10006 tests/php/exam-import-integration.php --local
```

For HTTP lifecycle checks, set `OHMYLMS_TEST_CREDENTIALS`, start PHP with mysqli/mbstring using
`php -S 127.0.0.1:8791 tests/php/exam-import-http.php`, then send a synthetic PDF with
`curl.exe -F file=@fixture.pdf http://127.0.0.1:8791/`. Stop the test server afterwards.
The router refuses ordinary PHP web-server execution and any database except the disposable one.

Other checks: bulk-import unit checks, 16 registry/grading checks and 15 membership permission
checks pass. JavaScript has 451 passing tests and one skipped test. Formatting and source contracts
pass. Production build succeeds with the existing two math-runtime size warnings; 215 generated
artifacts match across two builds. Unrelated generated output is excluded from this PHP milestone.
Changed PHP services and both new rehearsals pass syntax and focused WordPress standards.

`npm run check` fails at repository-wide PHP standards: 7,575 errors and 2,722 warnings in existing
code. Separate full JavaScript standards report 295 errors and 35 warnings; CSS reports 75 errors.
These findings are not suppressed. Browser, accessibility and load verification remain release gates;
neither these service rehearsals nor a successful build establishes those results.

## Local browser smoke test (October 18, 2026)

Tested through the authenticated WordPress admin at `800.local` using the existing browser
session. A synthetic structured question used existing DSAT skills, a two-mark numerical
part and a one-mark written part with private rubric.

- A conflicting total was rejected; the valid dry run reported one new question without writing it.
- Import created one question; repeat import reported unchanged with the same ID.
- Changed wording and answer updated that ID to version 2; approval remained draft.
- The bank showed both mapped skills. The editor showed three total marks, both parts,
  the updated numerical answer and teacher-only marking notes.
- Learner preview showed the stem, part prompts and marks while hiding the answer and rubric.
- No browser console warnings or errors were observed during these flows.
- Browser inspection found a dark preview heading on the configured purple background.
  The heading now inherits the preview text color within the form workspace. Reloaded
  browser verification confirmed white text, including on an existing structured question.

The synthetic question and its versions/mappings were removed after verifying it had no
quiz uses or learner responses. Existing content was not edited. Screenshots are retained
locally under ignored `test-results/exam-import-browser-*.jpg`. Full CSS standards still
report the same 75 pre-existing findings; the scoped rule adds no finding. This is a desktop
smoke test, not full accessibility, mobile, marking-player or load acceptance. Private PDF
upload/review was subsequently tested as described below.

## PDF workflow browser and integration rehearsal (October 18, 2026)

- 23 extraction checks cover inventory/part validation and all three native PDF request formats.
- `tests/php/pdf-exam-http.php` passes 40 checks on disposable WordPress: two private source
  uploads, batched extraction, stale actions, review conflicts, preserved teacher edits,
  idempotent commit, approval/publication, frozen questions, timing, marking, per-part evidence,
  protected sources/release and curriculum/roadmap/course/extension scopes without expanded access.
- Local browser rehearsal used an actual synthetic PDF upload and seeded extraction candidates.
  Review, save, commit, exact-version approval, exam creation/publication and the standalone
  landing page worked. Curriculum scope found the questions. No external provider was called.
  The synthetic exam/questions/source were removed after confirming no learner attempts existed.
- New PHP services/tests, exam JavaScript and scoped CSS pass focused standards. The bank
  page retains one existing hook dependency warning. The full repository still has existing
  standards failures; these are reported rather than suppressed.

The browser rehearsal does not establish real-paper extraction accuracy, full accessibility,
mobile behaviour or load acceptance. Interactive figures and handwriting are deferred.

## First real 0580 paper rehearsal

The owner's October/November 2025 0580/43 question paper and mark scheme exposed a queue
integration defect: Action Scheduler's unique flag applies to the hook/group, so a running
stage blocked the next cursor. Scheduling now checks the exact job/generation/cursor token
under the existing job lock and inserts without hook-wide uniqueness. Repeated resume reuses
an existing exact token. A running-stage fixture and repeated-resume regression bring the
mocked WordPress rehearsal to 44 checks. No scheduler dependency is edited.

The provider prompt now names the exact answer fields and ID format, omits unused nulls,
and requests teacher-marked written parts where the scheme permits method, follow-through
or partial credit. Safe schema diagnostics are retained privately in the import record;
raw provider responses are not stored. Extracted candidates still require teacher review.

After the fixes, local GPT-6 Luna extraction completed all 26 questions, with 100 part marks
matching the 120-minute paper inventory. The owner review draft was saved with existing
Extended syllabus skill references and part-level overrides. Early numerical/text candidates
with method credit or algebraic-equivalence requirements were changed to teacher marking;
two ambiguous partial-credit rubrics were clarified against the source scheme. All teacher
review flags remain false. No question versions or Exam were approved or published. Original
PDF figures remain references, and drawing-response support still needs a separate solution.
