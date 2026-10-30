# Question bank, versioned assessments and skill evidence

Status: implemented (2026-10-02) against the "OhMyLMS implementation plan — repository-based
revision" (v2.0) and merged into `main`. Not yet released to a live site; the classroom pilot
is still to be run. This document describes what exists, how it fits together, how to roll it
out and back, and what is deliberately out of scope. Later additions that build on the engine
are documented elsewhere: the optional Khan level model, mixed-skill unit tests and course
challenges in [learner-experience/KHAN-ACTIVITIES.md](learner-experience/KHAN-ACTIVITIES.md),
quiz and question editors in [question-block-editor.md](question-block-editor.md), quiz files in
[QUIZ-IMPORT-EXPORT.md](QUIZ-IMPORT-EXPORT.md), bulk import in
[QUESTION-BULK-IMPORT.md](QUESTION-BULK-IMPORT.md) and exam PDFs in
[EXAM-IMPORT-IMPLEMENTATION.md](EXAM-IMPORT-IMPLEMENTATION.md).

The engine extends the existing quiz/question post types, React editors, question
type registry, submission/review entry points and gradebook. There is no second
quiz engine. Every response-producing surface — quizzes, exams, skill practice and
inline lesson checks — shares one chain:

**question version → grading contract → grade event → evidence outbox → skill evidence → skill state → recommendations**

Grades and skill levels stay separate: gradebook overrides are never skill evidence,
and practice or inline checks never touch course completion.

## Ticket map

| Ticket | Delivered | Main code |
|---|---|---|
| A01 | Isolated test site restored (`tools/create-test-site.py` renamed to ohmylms identifiers); pre-existing rename bug fixed (two completion hooks shared a name and crashed quiz completion) | `tools/`, `includes/Extensions/Bootstrap.php` |
| A02 | Object-level authorization for every question/quiz route and nested write; foreign option IDs rejected; reports scoped to their own quiz | `QuestionBank/AccessPolicy.php`, `QuestionBank/DraftWriter.php`, `Rest/V1/QuestionController.php`, `Rest/V1/QuizController.php` |
| A03 | Atomic nested saves with per-child errors; stale-save conflict (409); editor save-generation guard keeps edits made during a save; delete split into remove-from-quiz / archive / delete-unused | `DraftWriter.php`, `QuestionBank/Usage.php`, `features/quiz-editor/useQuizEditor.js`, `model.mjs` |
| A04 | Question UUIDs, immutable content-hashed versions, resumable migration with inventory | `QuestionBank/VersionPublisher.php`, `Assessment/Migration.php`, `Assessment/Schema.php` |
| A05 | Quiz revisions and slots; per-attempt frozen items (seeded order, opaque option tokens); snapshot-aware graders; versioned reports | `Assessment/RevisionPublisher.php`, `AttemptItems.php`, `Grader.php`, `AttemptReport.php`, `Quiz/Submission.php`, `Quiz/Review.php`, quiz templates |
| A06 | Bank search/filter, approval, archive/restore, duplicate (to edit shared questions), banks and grants, quiz references, version pins; React bank page (with a New question form for choice, true/false, short/long answer, numerical and structured questions, saved without a quiz) and quiz-editor picker | `Rest/V1/QuestionBankController.php`, `QuestionBank/Banks.php`, `features/question-bank/` |
| A07 | Skill taxonomy (UUIDs, codes, acyclic prerequisites, lesson/course links); part-level skill maps frozen per version | `Skills/Taxonomy.php`, `QuestionBank/SkillMap.php`, `Rest/V1/SkillController.php`, `SkillsPage.jsx` |
| A08 | Autosave/resume, UTC deadlines with grace and a finalizer for closed browsers, decimal scoring migration, grade-event history, exam preset (sections, slot marks, availability, feedback release, extra time) | `Responses.php`, `Deadlines.php`, `Scoring.php`, `AssessmentSettings.php`, `Rest/V1/AttemptController.php`, `assets/js/quiz-autosave.js`, `features/assessment/` |
| A09 | Evidence outbox processor, transparent mastery rules, rebuild, learner dashboard and teacher skill report | `Skills/Evidence.php`, `Skills/Mastery.php`, `Skills/Recommendations.php`, `Rest/V1/SkillReportController.php`, `PerformancePage.jsx` |
| A10 | Adaptive skill practice and inline lesson checks on shared versions/grading | `Practice/Selector.php`, `Practice/Sessions.php`, `Practice/Inline.php`, `assets/js/practice.js`, `assets/js/inline-check.js` |
| A11 | Guest credentials, local history and a one-time, replay-safe claim | `Practice/Guests.php`, `Rest/V1/PracticeController.php` |
| A12 | Numerical type (tolerance, fractions, invalid input), structured multi-part questions with per-part evidence, limited random pools, migration rehearsal tool, non-recording author preview | `NumericAnswer.php`, `Structured.php`, `Pools.php`, `tools/assessment-rehearsal.php`, `Rest/V1/PreviewController.php` |

## Data model

All new tables are additive sidecars (`Assessment\Schema`, version option
`ohmylms_assessment_schema`). Existing attempt IDs and answer rows stay the
compatibility anchor; the gradebook keeps reading `ohmylms_quiz_attempts_answers`,
whose `question_marks` are frozen per attempt.

| Table | Purpose |
|---|---|
| `qb_banks`, `qb_grants` | Bank ownership and use < edit < approve grants |
| `qb_questions` | UUID and indexed bank attributes per question post (bank, family, difficulty, source, secure, current/approved version) |
| `qb_question_versions` | Immutable content: title, body, settings, options with correctness, media references, extension data, part weights, schema/grader version, content hash, migration-snapshot flag |
| `qb_version_skills` | Frozen part-level skill mapping (primary/supporting) |
| `quiz_revisions`, `quiz_revision_slots` | Frozen settings, slot marks, sections, pinned/approved versions, pool rules |
| `attempt_context`, `attempt_items` | Revision, scoring policy, UTC deadline, grace, extra time, seed; issued versions, option order, display, per-item result |
| `response_drafts`, `response_events` | Server-received autosaves (newest sequence wins) and their history |
| `grade_events` | Every grade and regrade per item/part, with reviewer, reason and supersession |
| `evidence_outbox` | One row per grade event, written in the grading transaction |
| `skill_evidence`, `student_skill_state` | Reconstructible contributions and cached levels |
| `practice_sessions`, `practice_items` | Skill practice and inline checks |
| `guest_sessions` | Hashed guest credentials and their claim |

Attempt score columns are `decimal(12,4)` after the decimal migration. Attempts keep the
scoring policy they started with: `legacy-int` (whole points, as before) or `decimal`.

## Key rules

- **Versions.** Every successful save captures a version; unchanged content reuses the
  latest one (content hash, including the skill map). Migration-time versions are labelled
  as snapshots, not reconstructions of the past.
- **Which version a quiz uses.** A pinned version; otherwise the current version when the
  quiz owner may edit the question; otherwise (shared, use-only) the approved version.
  Evaluated for the quiz owner, never for the learner.
- **Delivery.** Selection, question order and option order are fixed at attempt start from a
  stored seed. Option values are per-attempt HMAC tokens (matching definitions use a
  separate token kind), so markup never reveals correctness, pairings or order. Text-answer
  option text, `is_correct`, rubrics, tolerances and answer settings are never output.
- **Deadlines.** UTC deadline = start + time limit + extra time, capped at the assessment's
  closing time (`available_until`): the closing time is a hard deadline, so a learner who starts
  30 minutes before the close gets 30 minutes, and an untimed attempt ends at the close. Answers within the grace
  period count; after it, only autosaved, server-received answers are graded. A cron job
  (every five minutes) and the next start finalize abandoned attempts.
- **Question types** must declare `'snapshot' => true` (grade only the object passed in);
  publishing a quiz that contains a type without it fails with `quiz_type_unversioned`.
  Option-referencing types declare `'answers' => 'options'`; types may name learner-visible
  settings in `'public_settings'` and validate authoring with `'validate_settings'`.
- **Evidence.** Only the primary skill of each part receives attainment credit; supporting
  skills inform recommendations. Assisted (hinted) answers and repeats are recorded but do
  not count toward levels. A regrade supersedes the old contribution exactly once, even
  when the attempt total does not change.
- **Mastery levels** are decided by one engine; see [PERFORMANCE.md](PERFORMANCE.md). The default evidence rules (option `ohmylms_mastery_rules`) read: Not started → Attempted → Familiar →
  Proficient (≥3 independent first-try correct from ≥2 families, ≥80% recent accuracy) →
  Mastered (≥5 from ≥3 families, ≥85%, plus a correct answer ≥24 h after first reaching
  proficiency). Review due is a separate flag after 14 days without evidence. No probability
  score is shown.
- **Practice** uses approved, non-secure, non-archived, automatically gradable versions; it
  adapts between easy/standard/challenge bands, avoids repeating questions and families,
  and says so when the pool is exhausted.
- **Inline checks** (`[ohmylms_question uuid="…"]` or the `question-check` activity) render
  the approved version with a signed, per-render token and never affect completion.
- **Guests** answer on public lesson pages with a pseudonymous credential (stored hashed);
  results stay server-side and are attached only by an authenticated, one-time claim.
- **Answer review.** The quiz setting `show_correct_answers` (on by default, including for
  quizzes that never set it; "Show Correct Answers On Results Page" on the quiz introduction
  tab, which teachers can turn off per quiz) adds a "Review your answers" section
  to the quiz page for the learner's most recent submitted attempt: verdict, marks, their
  answer, the correct answer and the explanation (`Quiz\AnswerReview`,
  `templates/single-lesson/quiz-review.php`, overridable in a theme). It never shows more than
  the quiz allows: the older `hide_answers` setting still wins (and disables the new switch in
  the editor), an attempt still in progress is never reviewed, and an exam whose results are
  not released yet (`feedback_release`) stays hidden; once released, an exam shows its answers
  too unless its teacher turned the switch off. Versioned attempts show the questions as
  frozen into the attempt; attempts from before versioning use the question as it is now.
  The setting is read from the current quiz, not the frozen revision, so changing it applies to
  past attempts too. Types without a readable key (long text, structured and the extended
  types) show the learner's answer and verdict but no correct answer.
  The review opens with a score summary (headline, marks, percentage and verdict counts). A
  learner with several attempts picks one with `?review=ATTEMPT_ID`; only their own submitted
  attempts count, and anything else shows the newest. Structured questions list each part with
  its own verdict and marks. "Retry the questions I missed" (`?retry=1`) offers the missed,
  automatically marked questions again as inline practice checks. It needs the practice engine
  and approved, non-exam-only questions, and never changes the quiz marks.
  One renderer, `AnswerReview::render()`, serves every placement: the lesson quiz page, the
  Quiz Player block and its embed document, `[ohmylms_quiz]`, `[ohmylms_quiz_review]` and the
  theme helpers `ohmylms_get_quiz_player()` and `ohmylms_get_quiz_review()`. A learner's
  attempts are found whichever course they were taken in when the review is placed on its own.
  The instructor preview (`PreviewPlayer`) shows the same review after Submit, with the answers
  always visible to the editor and a note when learners will not see them (switch off or hide
  answers on). Nothing is saved, and a question with a teacher-marked part credits its
  automatically marked parts, as a stored attempt does.

## REST API (ohmylms/v1)

| Route | Who | Purpose |
|---|---|---|
| `question-bank` (GET), `question-bank/{id}` | authors | Search/filter; detail with versions, skill map, usage |
| `question-bank/{id}/approve|archive|restore|duplicate` | per policy | Lifecycle; duplicate with `quiz_id` swaps the reference |
| `question-bank/versions/{version}` | users of the question | Frozen version |
| `question-banks`, `question-banks/{id}`, `…/grants` | owner/admin | Banks and sharing |
| `quiz/{id}/questions` (POST), `quiz/{id}/questions/{q}` (DELETE), `…/pin` | quiz editors | Place by reference, remove from quiz, pin a version |
| `quiz/{id}/revisions` (GET/POST) | quiz editors | History; publish |
| `quiz/{id}/assessment-settings`, `quiz/{id}/pools` | quiz editors | Exam settings; random pools |
| `quiz/{id}/preview`, `…/preview/grade` | quiz editors | Non-recording preview |
| `attempts/{id}`, `attempts/{id}/responses` | the attempt's learner | Resume; autosave |
| `skills`, `skills/{id}`, `skills/{id}/lessons`, `skills/{id}/courses`, `skills/link-targets`, `question/{id}/skills` | authors | Catalogue and lesson/course/question mappings |
| `practice/sessions…`, `practice/inline`, `practice/guest`, `practice/claim`, `me/skills` | learners/guests | Practice, inline checks, claims, own progress |
| `reports/skills?course_id|class_id`, `reports/skills/students/{id}` | course editors, class teachers, guardians (with `school_id`; summary and suggestions only, no answer log) | Skill reports; shown in the school portal (family view per child, class view per class) |
| `assessment/migration` | administrators | Inventory and migration |

Existing question/quiz routes are unchanged in shape; `quiz/{id}` updates now also return
`saved_ids`, and questions include `modified`, `uuid`, `version`, `readonly`.

### Previewing a quiz on the site

Opening a quiz from the editor's Preview button runs the real quiz page. Administrators and the
quiz's author are always previewing (`Submission::is_preview()`), enrolled or not; any other user
who can edit the quiz previews until they enrol. Students never preview. Attempts have no limit
and leave no record. The in-progress attempt exists only while the quiz is open (a reload resumes
it); on submit, exit or timeout it is graded, summarised for the quiz page (a one-hour transient,
shown once as "Last preview result"), and erased. No grade or evidence events are written, the
lesson is not completed, and none of the `ohmylms_*` attempt/quiz events fire, so instructors get
no email and webhooks, achievements and automations are not triggered.

When an author opens their own quiz, attempts and completion recorded by earlier versions (which
treated their testing as a learner's) are cleared, along with a course completion that depended on
them; an attempt in progress is kept. Other people's quizzes are previewed but never wiped. Skill
evidence written by those earlier versions is not removed. A site that wants enrolled staff recorded
as learners can return `false` from the `ohmylms_quiz_is_preview` filter
(`$preview, $quiz_id, $user_id`).
Checked by `php tests/php/quiz-preview-unit.php` and `php tests/php/restrict-access-unit.php`.

## Release switches

All default on; define in `wp-config.php` to turn off:

- `OHMYLMS_VERSIONED_ENGINE` — new launches use revisions and frozen items. Off = new
  attempts start on the legacy engine; already-started versioned attempts keep their
  reader/writer and reports.
- `OHMYLMS_PRACTICE_ENABLED` — skill practice, inline checks, guests.
- `OHMYLMS_QUESTION_BANK_UI` — bank, skills and performance admin pages.

The admin pages and editor panels are SDK extensions and need `OHMYLMS_SOURCE_ASSETS`.

## Rollout

1. Back up the database. On a staging copy run
   `php tools/assessment-rehearsal.php /path/to/wordpress` (inventory and checksums),
   then `--run`. It migrates and verifies that question links, option IDs and content,
   attempt totals/statuses, gradebook totals and overrides are unchanged; exit code 0 means
   unchanged. It refuses production unless `--i-have-a-backup` is given.
2. Deploy; the schema installs on `init` and the question backfill runs in WP-Cron batches
   (`GET/POST /assessment/migration` shows and drives it). Active legacy attempts finish on
   the legacy engine.
3. Approve bank questions that should be shared or practised; map skills; publish quizzes.

**Rollback:** set `OHMYLMS_VERSIONED_ENGINE` false to stop new versioned launches while
existing ones finish. Never restore an old database over new submissions; the new tables
can stay in place with the code switched off.

Roll back with the switches, not by reinstalling older plugin code. The rollback rehearsal
(`tools/assessment-rollback-rehearsal.php`, steps `prepare`, `engine-off`, `old-code`,
`forward`, on a migrated database copy) showed:

- Engine off: new attempts start on the legacy engine; an attempt already started on the
  versioned engine still resumes, submits, grades from its frozen items and reports; no other
  attempt or gradebook total changes.
- Pre-assessment code (`ebe2cc5`) on the migrated data keeps the decimal columns and reads
  identical gradebook totals and attempts, and opens versioned attempt reports. It grades a new
  attempt but then crashes on the completion event (`EngagementHook::lesson_completed` receives
  one argument), a bug in that code fixed on this branch, so lesson/quiz completion would break.
- Forward again: the new code reads everything written meanwhile and new attempts use the
  versioned engine.

## Verification

```sh
OHMYLMS_TEST_CREDENTIALS=/path/test-credentials.json php tests/php/assessment-integration.php authoring versioning bank deadlines evidence math completion
php tests/assessment-unit.php
node --test tests/js/assessment.test.mjs tests/js/question-bank.test.mjs tests/js/quizzes.test.mjs tests/js/quiz-reports.test.mjs
```

Latest run: 306 integration checks (phases above plus `completion`), 49 unit checks, `php tests/unit.php`,
all existing PHP suites passing (extensions 60, gradebook, schools, students, WordPress API 27,
account blocks 44, leaderboard, view-as, membership 15, QPay 59). `npm run check` passes
(JavaScript 94/94 including source parity) and `npm run test:reproducible` passes.

Browser suite (`npx playwright test`): `tests/browser/assessment.spec.cjs` covers exam page
breaks, autosave and resume, keyboard-only answering and phone layouts, lesson checks with
guest claim, skill practice, guardian and teacher skill views, and the skills/question
bank pages. Known failures unrelated to this work: course, lesson and learning editor specs
report ProseMirror's `Adding different instances of a keyed plugin` because the shipped vendor
bundles are kept (see DEVELOPMENT.md), and the student registration specs expect a published
registration page with ID 210 on the test site.

Browser checks on the isolated site: quiz editor (version bar, bank picker, numerical and
structured editors, assessment settings), question bank and skills pages, learner quiz
delivery (tokens, no keys, stable order on refresh), autosave and resume after reload,
submission, versioned grading view including structured parts, inline check as a guest,
claim after login, skills dashboard, practice runner to an exhausted pool, and the
performance report.

## Limitations and later work

- Classroom pilot, full RTL/mobile acceptance and enabling on the math site remain.
- Translations: `languages/ohmylms.pot` is the template and Mongolian is the only bundled translation
  (see "Translations" in [DEVELOPMENT.md](DEVELOPMENT.md)); strings added by later work arrive with
  the next catalog refresh. Mongolian (Cyrillic) content itself is verified: question
  text, skill names, family IDs and case-insensitive text answers.
- Math rendering uses the site's KaTeX auto-render (`wpmath`). Server-rendered quizzes and
  lesson checks are covered by its page-load pass; content added later by the practice runner
  and feedback is re-rendered through `window.ohmylmsTypeset`.
- Inline checks are authored as the **OhMyLMS Question** block (`ohmylms/shared-multiple-choice`), which
  references the bank question by ID and UUID and renders through the inline runtime (guests, retries and a
  lesson-scoped token; no effect on lesson completion). In a lesson the block edits single and multiple choice
  questions in place and shows every other type as a read-only learner preview with a link to the question bank.
  The lesson panel "Inline question checks" inserts this block for every question type. Older checks written as
  `[ohmylms_question uuid="..."]` keep working on the page; when a lesson opens in the editor, a shortcode that
  stands alone in a Shortcode block or paragraph is shown as the block (`Inline::blocks_from_shortcodes`, applied
  by `NativeLessonEditor::blocks_for_editor`), and is saved as a block on the next save. A shortcode inside a
  sentence is left as text.
- Media: each version copies the local uploads it references (question image/video, option and
  matching images, images in the question text) to `uploads/ohmylms-frozen/` by SHA-256 and serves
  those copies. Replacing a file in place produces a new version on the next save; files outside
  uploads or over 25 MB keep their live URL. Frozen copies are never deleted automatically.
- Exams: a section can start a new page; the paper is then split into pages even when the quiz
  shows all questions on one page. The gradebook maximum for unattempted quizzes is the
  published revision's total.
- Structured questions: teachers can mark each part in the grading screen (sent as
  `part_marks`) or enter a whole-question mark (automatic parts keep their scores, the rest goes
  to written parts). Choice-type parts are not supported.
- Pool feasibility is checked greedily across pools; unusual overlaps may be reported short.
- Not built: symbolic equivalence, follow-through marking, Moodle XML import/export,
  generators, PDF/mark-scheme export. (Quiz files, bulk import, exam PDF import and AI-drafted
  questions through the MCP import tools, which arrive unapproved, were added after this list was
  written; see the status paragraph at the top.)
