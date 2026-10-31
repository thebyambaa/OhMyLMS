# Question bulk import

Adds past papers or any other questions to the question bank from CSV or JSON. Every row is
checked first (a dry run); nothing is written until you ask for it. Uploading the same rows
again updates the same questions instead of creating duplicates.

Where: the **Import** button on the Content Hub Question Bank tab (choose Questions, then a CSV or JSON file), or `POST /wp-json/ohmylms/v1/question-bank/import`.
Who: anyone who can edit posts. Each question is saved through the same writer the question
editor uses, so ownership, bank permissions, validation, versioning and skill mapping all apply.

## One import dialog

The **Import** button on the Quizzes, Question Bank, Exams, Curriculum and Skills tabs, and in a course when you attach a quiz, opens one dialog. Choose what the file becomes (the tab you are on is chosen for you), then the file:

| Import as | PDF | CSV or JSON |
|---|---|---|
| Questions | Read by the AI converter, reviewed, saved as bank drafts | Checked row by row, then added to the Question Bank |
| Quiz | Read and reviewed, then linked into a new draft quiz | A quiz file made with Export, or the simple quiz CSV |
| Exam | Read and reviewed, then a timed exam on the quiz player | Rows are saved as secure bank drafts, then the same exam details step |

**Import questions** in the quiz editor's *Quiz authoring* panel (for quizzes and exams alike) adds a file's questions to the end of the open assessment instead of making a new one. A CSV or JSON file is checked, saved to the Question Bank and inserted as question blocks at once; a PDF is read and reviewed first, then **Add N questions to this assessment** inserts them. In an exam the questions are saved as secure (exam-only) bank drafts. As with **Add from question bank**, save the quiz to keep them.

On the Curriculum and Skills tabs the dialog also offers **Choose skills**: the skills chosen are given to every question in the file (or PDF) that names none. In a course, only a quiz can be imported, and it is added to the attach list. PDF imports need the exam converter enabled under Settings > AI tools. Questions an AI assistant added wait for approval on Content Hub › Question bank › Import review (`admin.php?page=ohmylms-content&tab=question-bank&section=import-review`; the old `admin.php?page=ohmylms-import-questions` redirects there). REST: `POST /exam-imports` takes a `target` and optional `default_skills` of `exam` (default), `quiz` or `questions`; `POST /exam-imports/rows` turns CSV or JSON rows into a committed job; `POST /exam-imports/{id}/quiz` makes the quiz from a committed quiz job.

## Row format

One row is one question. CSV needs a header row; JSON is a list of objects (or `{"rows": [...]}`).
Column names are case-insensitive; spaces and dashes are read as underscores.

| Field (aliases) | Meaning |
|---|---|
| `exam`, `paper`, `year`, `section`, `number` (`question_number`, `no`, `q`), `language` (`lang`) | Identify the question. **Number is required, plus an exam or a paper name.** Language defaults to `en`. These six fields form the import key, so the same exam/paper/year/section/number/language always means the same question. Kept on the question for rebuilding papers later. |
| `type` | `single-choice` (default), `multiple-choice`, `true-false`, `numerical`, `fill-in-the-blank`, or any other registered question type (see below). |
| `question` (`body`, `stem`) | The text learners see. For `fill-in-the-blank`, put each answer in braces: `The capital is {Paris}.` |
| `title` | Optional admin title. Default: exam, paper and year plus Q number. |
| `answers` (`choices`) | Answer texts separated by `\|`. True/false needs none. |
| `correct` (`answer`, `key`) | A letter (`B`), a 1-based number (`2`), or the answer text. Several with `\|` for multiple choice. For `numerical` it is the number (a decimal comma is accepted). |
| `tolerance`, `tolerance_type` (`absolute` or `relative`), `unit` | Numerical questions only. |
| `skills` (`skill`, `skill_codes`) | Skill codes, UUIDs or `#termID`, separated by `;` or `,`. **The first is the primary skill**, the rest supporting. At least one is required: a question without a skill cannot count toward progress. A code shared by several skills is rejected; use the UUID. |
| `family` (`family_id`) | Groups variants of the same question. Practice needs at least two families per skill, so give unrelated questions different families (or leave blank). |
| `difficulty` | `easy`, `standard` (default) or `challenge`. |
| `marks` (`score`, `points`) | Score for the question. |
| `explanation` (`solution`) | Shown after answering in practice. |
| `image` | A media library image ID or URL (upload the screenshot first). Becomes the question image. |
| `confidence`, `notes` (`ai_confidence`, `ai_notes`, `rationale`) | For AI-classified rows: how sure the classifier is (0 to 1) and why it chose those skills. Shown to the reviewer; they are not part of the question, so changing only these does not update a question. |
| `parts` | Structured questions: a JSON list of 1–50 parts. Each part has a stable `id`, `kind` (`numerical`, `text`, `written`), optional label/prompt, marks (default 1), answer configuration, optional teacher-only rubric, and optional `skills`. Part skills override the row skills; omitted part skills inherit them. |
| `settings`, `options_raw` | JSON, for question types that are not listed above. `settings` is the type's settings object; `options_raw` is its list of `{answer, is_correct}` rows if it uses options. |

Example (CSV):

```
exam,paper,year,section,number,type,question,answers,correct,skills,family,difficulty
Digital SAT,2023-03 US,2023,Math M1,1,single-choice,"What is 2+2?",3|4|5|6,B,ALG.1,add-1,easy
```

## Structured past-paper questions

Use one row per printed question and keep its answerable parts together. Nested printed
labels such as `(a)(i)` use stable IDs such as `a_i`; labels do not replace IDs.

```json
{
  "exam": "Cambridge IGCSE Mathematics 0580",
  "paper": "Sample Paper 4",
  "year": "2026",
  "number": "1",
  "type": "structured",
  "question": "A rectangle has length 4 cm and width 3 cm.",
  "skills": "GEOMETRY.AREA",
  "parts": [
    {"id":"a_i","label":"(a)(i)","prompt":"Find the area.","kind":"numerical","marks":2,"answer":12,"unit":"cm²"},
    {"id":"b","label":"(b)","prompt":"Explain your method.","kind":"written","marks":1,"rubric":"Teacher reviews the student's method.","skills":"GEOMETRY.REASONING"}
  ]
}
```

Skill codes in this synthetic example must exist in the target site's catalogue. Each part
needs skills: use the row mapping as a fallback or supply every part's own mapping. An
explicit empty part mapping is an error. First skill is primary; later skills support it.
Unknown or ambiguous codes fail the dry run, including codes used only inside a part.

Question marks default to the sum of part marks. If `marks` is supplied on the row, it must
match that sum. Existing `settings.parts` imports remain accepted; do not provide conflicting
`parts` and `settings.parts`. In CSV, encode `parts` as a properly quoted JSON cell.

Part mappings go through the normal skill writer under their stable IDs, not `p1`.
Import-only skill tokens are removed from the saved answer settings. Answers and rubrics
remain private under the existing learner projection; written parts require teacher marking.
Rubric notes do not implement automatic method marks. These changes do not add PDF reading,
a new Exam content type, graph interactions or new structured part kinds.

## Checking and importing

1. **Check rows** reports for every row: will create, will update, no change, or error with the reason. Nothing is written.
2. **Import** writes. If any row has an error, **nothing is imported** unless you tick *Import the valid rows even if other rows have errors*.
3. *Approve imported questions* approves each new or changed question so it can be used in practice and exams. Without it, questions stay drafts of their approval and are not drawn into practice.
4. *Update questions that were imported before* controls whether changed rows overwrite the existing question. With it off, changed rows are reported as "no change".
5. Choose a **bank** to put questions in a shared question bank (you need edit permission on it).

The admin page accepts up to 1000 rows per submission and checks every batch before writing the first one.
The REST route accepts up to 200 rows per request; split larger files into batches and send each with `dry_run: true` first.

## REST

`POST /ohmylms/v1/question-bank/import` with `rows` (decoded list) or `text` plus `format` (`auto`, `json`, `csv`), and:
`dry_run` (**defaults to true**; send `false` to write), `bank_id`, `approve`, `update_existing` (default true), `skip_invalid`.
The response lists each row with `row`, `key`, `status` (`create`, `update`, `unchanged`, `error`), `id`, `errors`, `warnings`,
plus `counts`, `committed`, `approved`.

## What is stored

Each question gets `_ohmylms_import_key` (the identity above), `_ohmylms_import_hash` (a fingerprint of what was written, so identical re-uploads are skipped) and
`_ohmylms_import_paper` (exam, paper, year, section, number, language). The bank's `source` field holds a readable
"Exam · Paper · Year · Section · Qn" string, and `family_id` and `difficulty` use the bank's existing attributes. Nothing is added to the database schema.

## Behaviour worth knowing

- **Manual types** (`short-text`, `long-text`) are accepted with a warning: they are marked by hand, so they are never used in practice and never move SmartScore.
- **Editing and status:** the existing question writer sets a question's post status to *draft* whenever its content is saved. The import puts imported questions back to *published* after writing.
- **Changed questions** get a new version. With *Approve* ticked, the new version is approved; otherwise the previously approved version stays in use.
- **Other question types:** any type registered through the extension registry can be imported by giving its `settings` (and `options_raw`). The writer validates them with the type's own rules.
- **AI classification and review:** import through the MCP tool (`import_questions`) always flags questions for review and never approves them. The admin page lists them under **Waiting for review** with the skills the AI chose, its confidence and its notes; *Approve selected* approves the current version, *Keep as draft* only removes the flag. See `docs/MCP.md`.
- **PDF exams:** the Exams tab provides private PDF upload, explicit extraction, teacher review,
  draft commit, version approval and exam publication. See [EXAM-IMPORT-IMPLEMENTATION.md](EXAM-IMPORT-IMPLEMENTATION.md).
- **Deferred:** translations, interactive reconstruction of PDF figures and handwriting input.

## Question Bank organisation

The bank's organisation selector follows existing curricula and their descendants, roadmap
branches and course objectives/quiz placements. The skill filter can narrow these views further.
Existing source/shared skill mappings are included for discovery. These are views over reusable
questions, not separate question copies or an additional permission system; bank visibility
checks remain authoritative. Newly imported PDF questions default to **Exam only**.

`GET /ohmylms/v1/question-bank-organisation` supplies the available views. `GET /ohmylms/v1/questions` accepts
`organisation` and `scope` (the removed `GET /question-bank` did before). Integrations can extend the view metadata through
`ohmylms_question_bank_organisation_views` and resolve references with
`ohmylms_question_bank_resolve_scope`. Resolved references are limited to skills, banks,
courses and quizzes; empty scopes return no questions. Discovery currently lists at most
100 editable courses and resolves at most 5,000 references in a scope.

## Export: every question type, CSV or JSON

Questions can be written back out as the same rows the importer reads, so a file can move between sites, be
edited in a spreadsheet and be imported again. Every registered question type is covered.

Where: **Export questions (CSV)** and **(JSON)** in the quiz editor's *Quiz authoring* panel (this is also where
an exam's questions are edited) and on the Question Bank tab (the questions on the current page); or
`POST /wp-json/ohmylms/v1/questions/export` with `format` (`csv|json`) and `ids` (up to 200), `filters` or
`quiz_id`, optional `label` ([questions API](api/questions.md#import-and-export); the former
`GET /question-bank/export` was removed). The MCP tool `export_questions` uses the same exporter. Only people
who can edit a question can export it, because the file holds every answer key; a quiz export skips shared
questions the user may only use and reports them in `skipped`.

How types are written:

- Single choice, multiple choice, true/false, numerical and fill-in-the-blank use the readable columns above
  (`answers`, `correct`, `tolerance`, `unit`, `marks`, `explanation`). Settings beyond those columns (hints,
  partial credit, randomization) go in `settings`. Answers that contain `|` or carry images are written as
  `options_raw` instead, and the importer accepts `options_raw` for these types.
- Structured questions write `parts`, with each part's skills in its own `skills` field.
- Every other type (graphing, hotspot, crossword, the activities and games, and so on) is written as its full
  `settings` and its `options_raw` rows. Their stem may be empty, because the content is in `settings`.
- In CSV, lists and objects are JSON cells. The file starts with a byte-order mark so spreadsheets read UTF-8.
- Rows are identified by the exam name (`label`, default the quiz title) and the question number, so importing
  the same file again updates the questions it created. Quiz sections are written to `section`.
- Skills are written by code. A code shared by more than one skill is written as its UUID instead, because the
  importer refuses an ambiguous code.
- A question with no skill still exports, with a warning naming it. The importer refuses questions without
  skills, so add one before importing the file.

`tests/php/question-rows-integration.php` creates one question of every type, exports it as JSON and as CSV,
imports the file and requires each copy to store the same type, title, settings, options, skills, difficulty and
family. What is not exported: the media of an answer option on another site's media library, the bank a
question belongs to, version history and approval.
