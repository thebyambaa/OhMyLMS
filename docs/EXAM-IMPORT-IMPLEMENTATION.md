# Exam import implementation status

Status (October 19, 2026): merged into `main`. The code implements structured imports, private PDF extraction jobs, teacher review,
an Exams tab and content type, and an exam shell using the existing quiz player.
The workflow has synthetic WordPress integration and desktop browser coverage. An initial
local GPT-6 Luna run on the owner's 0580/43 October/November 2025 paper and mark scheme
retained all 26 questions and 100 marks. The owner requested saving that paper: its question
drafts and a private 120-minute exam draft are now saved. Exact-version approval and
publication were initially pending; the owner subsequently approved the questions and
published the local exam. **Review paper** now opens a dedicated dialog in Exams. It
shows the exact pinned question version, per-part marks, skills, answer/rubric notes and
approval controls. **Try exam** embeds the existing non-recording quiz preview in that
dialog. Imported papers awaiting commitment also use a dialog, with save/discard protection.
The optional side-by-side source PDF depends on browser PDF support; its protected link
remains available when embedding is unavailable. Timed learner exams keep their own player.

MathLive now renders explicit markers plus `$...$`, `$$...$$`, `\(...\)` and `\[...\]`
in question regions. Ordinary currency outside those regions and editable source fields
remain untouched. The installed renderer is enabled by default; an explicit
`OHMYLMS_MATHLIVE_ENABLED=false` or the existing filter can still disable it. Presentation
does not change answers or grading. The question metadata writer now recursively slashes
nested settings before WordPress unslashes them, preserving LaTeX in part prompts/rubrics.
For the local 0580 paper, eight questions had lost backslashes: only fields exactly matching
the unslashed original reviewed import were restored, through new approved versions and a
new quiz revision. Original versions and existing attempt snapshots remain intact; future
attempts and new previews use the repaired versions. Diagram delivery and broader real-paper
accuracy/provider compatibility still need verification before release.

## Implemented in this change

- An explicit `parts` field for JSON/CSV structured-question import.
- Compatibility with the existing `settings.parts` input.
- Stable part IDs, labels, prompts, numerical/text/written kinds, marks and private rubric.
- Part skill overrides and row-skill inheritance, including batched skill-code resolution.
- Existing draft writer, versioning, review and approval paths remain the persistence authority.
- Derived total marks in the assessment engine's enabled/value score format.
- Validation of conflicting totals, conflicting part definitions, invalid part settings and the
  50-part delivery limit. Unsupported question interactions are not installed automatically.
- Synthetic unit fixtures covering the import contracts and learner-safe projection.
- Runtime rehearsals on the owner's Local WordPress LMS and the disposable WordPress installation:
  repeat imports, review approval, frozen question versions, marking and per-part skill evidence.
- Private PDF upload API, owner-scoped persistent import records, status/list/retry/delete routes,
  source integrity validation and bounded Action Scheduler processing. See [EXAM-IMPORT-JOBS.md](EXAM-IMPORT-JOBS.md).

See [QUESTION-BULK-IMPORT.md](QUESTION-BULK-IMPORT.md) for the format and example.

## Remaining sequence

1. **Complete:** exercise structured imports on both Local and disposable WordPress, including repeat imports,
   frozen versions, review approval and per-part skill evidence after teacher marking.
2. **Implemented:** private storage and explicit extraction through the configured AI provider,
   optional mark scheme, inventory followed by five-question batches, schema validation,
   usage accounting, bounded retries and checkpoints. Native PDF requests support OpenAI,
   Anthropic and Gemini; actual provider extraction accuracy remains unverified.
3. **Implemented:** side-by-side paper/review form, editable parts, marks, answers, source
   pages and skill search. Teacher review, commit, exact-version approval and publication
   are separate actions. Re-extraction preserves the saved teacher draft.
4. **Implemented:** Exam metadata and all imported questions in source order, pinned approved
   versions, and a private backing course/quiz using the existing assessment engine.
5. **Implemented:** standalone exam shell, existing typed answers/working, server timing,
   autosave, teacher marking and release-controlled results/mark scheme. This extends the
   quiz player; it does not add a new handwriting or automatic method-marking engine.
6. **Implemented:** signed-in standalone access and `[ohmylms_exam exam="ID"]` embedding.
   Original paper figures remain available through protected PDF references; rebuilding
   figures as interactive question assets is deferred.
7. **Implemented:** Question Bank views by curriculum descendants, roadmap, course and
   skills using existing links and objectives, without copying questions. See the bulk
   import document for extension hooks and limits.
8. **Next:** test the supplied real papers and mockups, then complete mobile, accessibility,
   provider failure and load verification on staging before release.

Question-bank items are shared by quizzes, exams and future interactive books. Published uses
must pin approved versions; edits must not change an existing attempt's content or score.

## Deferred extensions

Overleaf ZIP ingestion, declarative/interactive figures, independent section timers, handwriting
uploads, adaptive testing and offline book/practice support have separate delivery gates.
Rubric import does not implement automatic Cambridge method or follow-through marks.
Papers without a mark scheme require reviewed AI solution drafts; practice raw points must
not be presented as an official Digital SAT scaled score.

## Development and release policy

Follow `AGENTS.md` and `docs/DEVELOPMENT.md`. Edit authored code and rebuild assets through the
repository tools. Run syntax, standards, focused PHP tests, `npm run check`, build and
reproducibility checks. A source/unit result does not establish WordPress runtime correctness.
Report unavailable tests and unrelated existing failures separately from regressions.

Use a reviewable pull request and merge into `main` after the owner's go-ahead; production deployment is
separate from merging code.
