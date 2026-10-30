# Digital SAT Math course: build log, UX notes and skill tracking

Course 509, "Digital SAT Math: Complete Prep", built on 2026-10-21 from the Digital SAT Math syllabus (curriculum item 20, 4 domains, 19 skills).

## What was built, in order

1. **Courses > Add Course > Start from Scratch.** A draft course (509) exists as soon as you click.
2. **Title and description** typed on the Content step.
3. **Syllabus Objectives > Add objectives.** Switched the edition to *Digital SAT Math* and ticked all 19 objectives. These save immediately ("Syllabus objectives saved").
4. **Four units**: Algebra, Advanced Math, Problem-Solving and Data Analysis, Geometry and Trigonometry.
5. **Saved** from the last step (Preview > ⋮ > Save as Draft). Nothing on the Content step saves the title or units.
6. **Four checkpoint quizzes**, imported from quiz files (the first through the new Import dialog), each question mapped to its skill by UUID. All imported with zero warnings.
7. **Question pool**: 57 single-choice questions (3 per skill, 2 families per skill), imported and approved in one batch through the same route as the Import dialog.
8. **Attached** the 19 skills to their units and each quiz to its unit and skills (Content Hub catalog: mode *blended*, pass mark 70%, required).
9. **Published the quizzes, then the catalog.** The first attempt was refused with 23 reasons; see below.

The course itself is still a **draft**. Publish it from Preview > Publish when you want learners to see it.

## UX and UI notes

Problems seen while doing the steps above, most important first.

1. **Unsaved work is lost silently.** Adding a quiz from the course editor opens the quiz editor in the same tab. Coming back, the title, description and unit names I had typed were gone; only the objectives (which save on their own) had survived. Autosave, or at least a "leave without saving?" warning, is needed. The only save is on the *last* step, hidden in a ⋮ menu as "Save as Draft".
2. **A course cannot use existing content.** The unit's Add Content dialog only creates new lessons, quizzes and assignments, and a content row's menu has only Edit and Delete. The dialog that attaches existing content, imports a quiz, and links skills (Attach content) only exists for syllabus items, so for an ordinary course I had to use the catalog API. Put one "Add content" dialog everywhere: *new*, *existing* and *import*, plus a skill picker.
3. **The publish gate is invisible until the end.** Publishing refused with 23 separate lines: the quizzes must be published, and every skill needs at least 3 approved questions from 2 families. Show a live readiness panel on the course (per skill: approved questions, families, a "write or import questions" button), and group the errors instead of listing 19 near-identical sentences.
4. **"How would you like to build your course?" has one choice.** Add "From a syllabus" (pick the syllabus and pre-create units from its domains and objectives) and "Import from PDF or file". Building the Digital SAT course meant re-picking 19 objectives and re-typing 4 unit names that already exist in the syllabus.
5. **Course and syllabus are mixed up.** The syllabus "Digital SAT Math" is itself listed as a course in All Courses (draft, free, 0 enrolled). Mark syllabus items with a badge or hide them from the course list.
6. **Objective picker.** It opens on the first edition (an IGCSE roadmap), not the most likely one, and has no "select all" or "select this topic". The 19 objectives landed as a long table that pushes the units 2,000 pixels down the page; collapse it after saving and show a count ("19 objectives, 0 with content").
7. **Draft records are created before you commit.** "Start from Scratch" and "Add Content" each create a database record immediately, so abandoned attempts leave "Untitled" courses and quizzes. Create on first save, or label them clearly and offer cleanup.
8. **"Untitled" is real text, not a placeholder**, in the quiz editor title and in unit rows. Clicking into it puts the cursor inside the word ("UntiAlgebra Checkpointtled"). Use a placeholder.
9. **Floating "Ask OhMyLMS" button covers the right edge** of content rows and has opened the assistant instead of the row's ⋮ button. Give it a safe margin or hide it in editors.
10. **The quiz editor's block inserter lists unrelated blocks** (Student Registration, Checkout, Sign In, Membership List) at the top, and the question block is named "Ohmylms Question" (casing) next to "OhMyLMS Question Player". Limit the inserter to quiz blocks and fix the name.
11. **Skill search returns 10 results with no paging**, and shared skills without a code appear next to coded ones ("Averages and measures of spread" beside DSAT.M.PSDA.3). Page the results and show which items are the same skill.
12. **Slug stays "untitled" until the final save**, so the Settings step shows a wrong slug.

## How skill tracking works

One chain handles every quiz, exam, practice session and lesson check, so there is no second engine:

**question version, grading, grade event, evidence outbox, skill evidence, skill state, recommendations**

- **Questions carry skills.** Each question version freezes its skill map: for every part, one *primary* skill and optional *supporting* skills. Editing a question later creates a new version; older attempts keep the mapping they were graded with.
- **Grading writes events.** Each graded item or part writes a *grade event*. A row in `evidence_outbox` is written in the same database transaction, so evidence can never be lost or counted twice.
- **Evidence is derived.** A background job turns events into `skill_evidence` rows: marks awarded and available, whether the answer was *independent* (no hint) and a *first try*, the question *family*, difficulty and time. Only the **primary** skill earns attainment; supporting skills only inform recommendations. A regrade supersedes the earlier evidence exactly once. Gradebook overrides never become evidence.
- **Levels follow transparent rules** (Settings, option `ohmylms_mastery_rules`), looking at the learner's last 8 independent first tries on a skill:
  - *Not assessed*: no evidence. *Developing*: some evidence.
  - *Proficient*: at least 3 correct, from at least 2 families, with 80% or better accuracy.
  - *Mastered*: at least 5 correct from at least 3 families at 85% or better, plus a correct answer given at least 24 hours after first reaching proficient.
  - *Review due* is a separate flag after 14 days with no new evidence.
  - Hinted answers and repeats are kept but do not count. There is no probability score. An optional 0 to 100 score and a Khan-style model can be switched on and sit beside these levels.
- **Where it shows.** `student_skill_state` caches each learner's level per skill. Learners see it on their dashboard and subject skills view, teachers in the skill report and Performance page, and Recommendations picks what to practise next.
- **Why the 3 questions and 2 families per skill requirement.** It is the smallest pool that can reach *proficient* without repeating a question or a family, so the course cannot be published with a skill no learner could ever master. Mastered needs at least 5 questions from 3 families, so for real use each skill needs a bigger pool than this course now has.

## What was changed in response (2026-10-21)

1. **Unsaved work.** The course builder now saves a draft by itself 0.8 seconds after an edit, shows "Saving…", "You have unsaved changes" or "All changes saved at …" under the toolbar with a Save draft button (a live course shows Update course and is never changed silently), and warns before a tab close or full-page jump while edits are unsaved. The slug follows the title because the title now saves early.
2. **Existing content.** A panel above the units, "Add existing content or import a quiz", adds existing lessons, quizzes and assignments to a chosen unit and imports a quiz file straight into it. The Attach content window used from syllabus screens also gained quiz import.
3. **Readiness.** A panel in the builder shows whether the course is ready for learners: unpublished resources, completion rules, and for each required skill its approved questions and families against what it needs, with an Add questions button that opens the import dialog with that skill chosen. The server report is `readiness_report()` and is returned with the catalog and with a failed publish.
4. **Starting a course.** The dialog offers Start from scratch, From a syllabus (a unit per domain, every skill linked and placed, no content created; `POST /courses/from-syllabus`) and From a quiz file.
5. **Course list.** Syllabus items carry a Syllabus badge and untouched drafts an Unnamed draft badge.
6. **Objective picker.** It opens on the edition the course already uses, then the last one you chose; has Select all shown / in this topic and Clear selection; the objectives table collapses to a one-line summary once there are more than five.
7. **Empty drafts.** Clicking Exit in the builder removes the course if it is still completely untouched (default title, no description, image, objectives, skills, content, learners); the server decides (`POST /courses/{id}/discard-if-blank`).
8. **"Untitled".** Quiz and lesson editors blank the stored "Untitled" so the title placeholder shows; a name still holding "Untitled" is selected when you click into it in the course builder.
9. **Assistant button.** The builder leaves 96px of room below its content.
10. **Quiz editor.** The block inserter no longer lists the plugin's site blocks (registration, sign in, dashboards, checkout, buy buttons, membership list); the question block is named "OhMyLMS Question".
11. **Skill search.** Skill choices page ("Show more skills", 40 at a time) and the question-paper skill search takes a page. Not done: flagging which uncoded skills are the same skill as a coded one (the library has no reliable link to show).
12. **Course 509.** Its four quizzes are now also in its units in the builder (they had been attached only through the catalog).

The recovered course builder and its Add Content dialog were deleted at the cutover; the new panel is the way to add existing content. Publishing a course for learners still needs its Preview step.
