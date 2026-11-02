> The Khan level rules described here are now the `steps` policy of the unified skill tracking system; see [PERFORMANCE.md](../PERFORMANCE.md). The separate level log and the display class below no longer exist.

# Khan Academy exercises, quizzes and tests: observed behaviour and OhMyLMS gaps

Status (October 19, 2026): the observation (sections 1 to 4) is a reference; sections 5 to 11 record what was
built from it in OhMyLMS, all merged. Section 12 lists where each piece lives in the code.

Observed October 16, 2026 on khanacademy.org in the built-in browser, **signed out**, on 5th grade math
(Unit 1, Decimal place value). Mastery rules come from the Khan Academy Help Center articles
"How do Khan Academy's Mastery levels work?" and "What are Course and Unit Mastery?". No account,
learner data or progress was created. Signed-in only behaviour (results screens, saved progress, Level up
rewards) was **not observed** and is marked as such.

## 1. Course and unit structure

- A course has units. A unit lists, in order: skills (each one exercise), a **Quiz** after every three or four
  skills (Unit 1 has Quiz 1 to 4), then one **Unit test**. The course ends with a **Course challenge**.
  Course overview shows "130 skills" with a legend: Mastered, Proficient, Familiar, Attempted, Not started,
  plus Quiz and Unit test icons.
- Unit page: header "13 skills", one row per skill with its level in words (for example "unfamiliar" when not
  started), a **Level up** button that goes to the unit test, an "About this unit" blurb, a link to a prerequisite
  course ("Not feeling ready? Check out Get ready for 5th grade math").
- Lesson sidebar (left): course name, "Unit 1 > Lesson 1", the lesson's items (video, exercise, review article), a
  collapse arrow, breadcrumb at the bottom. An exercise page keeps the sidebar visible.

## 2. Exercise (practice) screen

- Header: skill title, standards link (for example CCSS.Math: 5.NBT.A.3), "Microsoft Teams" and "Share link".
- Question card, "Choose 1 answer", lettered radio rows A-D. Maths rendered inline.
- Bottom bar, always visible: restart icon, **"Do 7 problems"** with seven dots (becomes "2 of 7"), **Skip**, **Check**.
  Check is disabled until an answer exists. Enter also submits (checked on a numeric field).
- A per-question **Related content** card (video with duration) and **Report a problem**.
- Wrong answer: toast "Not quite! Give it another try!" with a **Show a step** button; the bar changes to
  **Hints** and **Try again**. The answer stays selected and can be changed. The dot for that question is half
  filled while the learner is still on it.
- **Hints are progressive**: "Hint #1, 1/4", **Get another hint (1/4)**. Hint text can use the question's numbers.
- Correct answer: chosen row turns green with a check, the other rows dim, **Next question** appears.
  Two feedback tiers: first try "Great work! You got it. Onward!"; after hints or retries "Nice work! Great that you
  stuck with it. Onward!". Both offer **See a step-by-step solution**.
- Dots: a green check dot means correct on the first try; a grey dot means the question needed help.
- Every problem is a new variation of the same template (the digits change: 12.47, then 4.09).
- First interaction shows a four-step tour: reach new Levels, build weekly streaks, jump to your next suggested
  skill, review recent courses. A header strip shows weekly streak and "Level 1, 0/1 skill".

## 3. Quiz and Unit test screens

- Quiz intro: "Time for a quiz? Get ready for questions on the unit so far. **5 questions, 5-10 minutes**", Let's go.
- Unit test intro: "All set for the unit test? ... **11 questions, 11-22 minutes**" (one to two minutes each).
- Both use the same runner as exercises ("Do 5 problems" / "Do 11 problems", Skip, Check) and draw questions from
  several skills in the unit (the Quiz 1 question was a different skill from the exercise). Free-text numeric
  answers appear in tests.
- Signed out, hints, retries and per-question feedback were still available. Whether signed-in quizzes and tests
  withhold hints or feedback was **not verified**.

## 4. Mastery rules (official)

Skill levels: Not started, Attempted, Familiar, Proficient, Mastered.

| Event | Result |
| --- | --- |
| Exercise finished below 70% | Attempted |
| Exercise finished 70-99% | Familiar |
| Exercise finished at 100% | Proficient |
| Mixed-skill assessment (quiz, unit test, challenge): question on the skill right | Not started or Attempted to Familiar; Familiar to Proficient; Proficient to Mastered |
| Mixed-skill assessment: question on the skill wrong | Not started or Familiar to Attempted; Proficient to Familiar; Mastered to Proficient |

- Only Proficient and Mastered count toward unit and course mastery percentage (5 of 20 skills = 25%).
- After any activity the learner sees which skills leveled up, leveled down, or stayed, with recommended lessons.
- Not verified here: whether an exercise can lower a Mastered skill (older community text said 70-99% drops Mastered
  to Proficient), and the exact unit test and course challenge question counts per skill.

## 5. Where OhMyLMS stands against the observation

This table was written before the slices in sections 7 to 11 and is updated to the code as of October 19, 2026.

| Area | OhMyLMS now | Khan |
| --- | --- | --- |
| Skill exercise runner | Practice sessions (3-30 questions), Check, Next, summary, resume, templates with new numbers; progress dots, Skip, stepped hints, tiered feedback and Start over in the standard runner (section 7) | Same, plus per-question related content, Report a problem, standards badge |
| Hints | Stepped hints: an authored hint split with `<hr>` shows one step at a time; the first request marks the answer "assisted" | Up to four progressive hints |
| Skip | Standard runner only; not graded, no evidence, does not use up a problem; refused in lesson style | Skip on every question |
| Mastery levels | Evidence rules (default) or, by setting, Khan Academy levels (five names, append-only log, one-step moves from assessments, retry after a wrong practice answer; section 8); display names mapped by `MasteryDisplay`; optional 0-100 SmartScore | Level set by exercise percentage and by mixed-skill assessments |
| Quiz | Quiz engine with attempts, sections, exam preset, answer review; a mixed-skill quiz built from several skills (section 9) | Short mixed-skill check that drives skill levels |
| Unit test, Course challenge | Built as the `unit-test` and `course-challenge` styles of a skill assessment; one unit test per unit and an optional course challenge can be created in one step (section 11) | Present in every unit and course |
| Post-activity summary | Practice summary with accuracy and XP, and "Leveled up / down / Stayed at" with the Khan model on; the same list on a quiz result | Leveled up/down/same per skill plus recommended lessons |
| Quiz intro | Intro screen with question count, time range and skills covered (section 9) | Question count and time estimate before starting |
| Level up button | Hub course overview shows Level up on a unit with a published unit test, and a Course challenge card (section 11) | Starts the unit test |
| Standards badge | None | CCSS code on each exercise |
| Weekly streak and level strip | Streak card when streaks are enabled; no first-use tour | Header strip on every page and a first-use tour |

## 6. The slices and what became of them

1. **Runner parity** - built (section 7): progress dots, Skip, Start over, tiered feedback, stepped hints.
   Not built: Enter-to-check beyond the existing behaviour, a standards label.
2. **Khan level rules as a selectable mastery model** - built as an option beside the evidence model
   (section 8). The default is unchanged.
3. **Mixed-skill assessments** - built (section 9), with an authoring screen and AI draft tools (section 10).
4. **Course challenge and the Level up button** - built (section 11).
5. **Header strip and first-use tour** - not built.

How the owner's open questions were settled: the Khan level model sits beside the evidence model (a
setting, off by default); whether signed-in quizzes hide hints and feedback was never observed, so they
behave as before; a test draws `per_skill` (1-10, default 1) questions per skill, at most 60 in all; Skip
exists only in the standard practice runner, not in formal assessments.

## 7. Slice 1, runner parity: what was built (October 16, 2026)

Standard practice runner only (`assets/js/practice.js`, `includes/Practice/Sessions.php`). The fast-feedback
"lesson" style keeps its own header and gets none of these controls. No scoring, evidence, mastery or schema change.

- **Progress dots**, one per problem, in the order answered: right first time (green with a check), right after
  a hint (grey), not right (red with a cross), the current question (ring), not reached (empty). Each dot carries
  a text label for screen readers. The server returns `results` (`right`, `help`, `wrong`) and `skipped`.
- **Skip** (`POST /practice/sessions/{uuid}/skip`). The question is not graded and creates no grade event or
  evidence; its row is stored with no response and `display.skipped`. It is replaced by another question and does not
  use up one of the session's problems. At most `item_limit` skips per session; refused in lesson style and in
  finished sessions. Skipped questions are not replayed and do not move the difficulty band. `can_skip` in the
  session state tells the runner when to show the button.
- **Stepped hints.** A hint written with horizontal rules (`<hr>`) is shown one step at a time: "Hint 1 of 3",
  then "Get another hint (1/3)". A hint without a rule behaves as before. The first request still marks the question
  assisted, so later steps need no server call. Authors write the rules in the existing hint field.
- **Feedback tiers.** Right first time says "Great work! You got it. Onward!"; right after a hint says "Nice work!
  Great that you stuck with it. Onward!". The answer response now includes `assisted`.
- **Start over** link beside the dots (asks first; the old session's answers stay recorded).

Not built, on purpose:
- **Try again after a wrong answer.** Built in slice 2 (section 8), only with the Khan level rules on.
- Disabling Check until an answer exists, restart that reuses the same session, per-question related video,
  Report a problem, standards badge. New strings need the next translation catalog regeneration.

Verification: `node --test tests/js/practice-presentation.test.mjs` (6 pass, 3 new), assessment phase `skip`
(37 checks) plus phases lesson, xp, evidence (320 checks), learning (76), player templates (81), Student Home (88).
Browser (disposable site): hint steps, "Nice work" tier with a grey dot, "Great work" tier with a green dot, Skip
replacing a question without using a slot. PHPCS is not installed in this environment, so only PHP syntax,
ESLint, Prettier and Stylelint were run on the changed files; PHPCS on `Sessions.php`, `Frontend.php` and
`PracticeController.php` is outstanding.

## 8. Slice 2, Khan level rules as an option: what was built (October 16, 2026)

A site setting, **OhMyLMS -> Gamification -> XP & mastery -> Mastery model**: *Evidence rules (default)* or *Khan Academy levels*
(`ohmylms_mastery_settings`, REST `engagement/settings/mastery`). Nothing changes until an administrator picks Khan. Switching back
keeps every record; skills then use the evidence rules again.

With the Khan model on:

- **Exercise** (a finished practice session): right first time without a hint, out of the problems that counted (skipped ones do not).
  Under 70% is Attempted, 70-99% Familiar, 100% Proficient. An exercise raises a skill to that level and never lowers it, except that an
  imperfect exercise takes Mastered back to Proficient. (The Help Center does not say whether a weak exercise can lower a skill; this is
  the conservative reading and the one rule worth confirming with Khan's wording.)
- **Quiz or any graded attempt**: for each skill a question maps to (primary mapping), all right moves the skill up one step (Not started
  or Attempted to Familiar, Familiar to Proficient, Proficient to Mastered); any miss moves it down one step (floor Attempted). Runs on
  `ohmylms_attempt_graded`, so manually marked attempts count once they are graded.
- **Retry after a wrong answer** (standard practice runner only): the first answer is the learner's first try and is recorded exactly as
  before (grade event, evidence). A wrong first answer leaves the question open: nothing is revealed, the button says "Try again", hints
  and Skip stay available. The first right answer closes the question as right with help (grey dot), with no further grade event, so the
  wrong first try still stands in the evidence. Lesson style keeps its own replay of misses.
- **Where the level lives**: an append-only log `ohmylms_khan_level_events` (learner, skill, source, level before and after). The current
  level is the newest row; a skill with no Khan history shows its stored evidence level (developing as Attempted), so switching on is
  seamless. The stored evidence level (`student_skill_state.level`) follows the Khan level, so completion targets, recommendations and the
  hub keep working from one decision. A source can move a skill only once (unique key), so replays are harmless.
- **Display**: `MasteryDisplay` shows the Khan level (five names) for skills with history; the achievements counts include them. The
  practice summary lists "Leveled up / Leveled down / Stayed at" with before and after for the skill.

Not built: a migration of old history into the log. (When this section was written the leveled up/down list on the quiz result, the unit
test and course challenge, and teacher-side reports of Khan levels were also missing; the first two arrived in sections 9 and 11 and the
class-only teacher levels in `RELEASE-READINESS.md`.) Retry is not offered to guests' lesson style or inside quizzes.

Verification: assessment phase `khan` (87 checks: all rule tables, setting validation and permissions, retry flow with event and
row checks, no leak of the solution, summary changes, assessment up/down, replay idempotency, evidence-model fallback), phases skip,
lesson, xp, evidence, templates, review (672 checks together), learning 76, player templates 81, Student Home 88, JS 423 (422 pass, 1
skipped). Browser: the setting saved from the admin tab (the first build missed the save logic and was fixed), a wrong then right
retry with the "Nice work" tier and grey dot, and "Leveled up: Attempted -> Familiar" on the summary. PHPCS is not installed in this
environment, so it is outstanding for the new `Skills/Khan/*`, `Sessions.php`, `EngagementController.php` and `MasteryDisplay.php`.

## 9. Slice 3, mixed-skill Quiz and Unit test: what was built (October 16, 2026)

This builds on the quiz engine's existing skill question pools (`Assessment\Pools`), so nothing about attempts, grading or revisions changed.
How the draw works: a quiz holds one rule per skill (skill, count, marks, optional difficulty and bank). A question is eligible when its
approved version has that skill as its primary skill (sub-skills count), it is not archived, and secure questions are allowed. Publishing
checks all rules together (different families, no double-counting). Each attempt gets its own seed; every slot takes the eligible,
unused-family question with the smallest hash of seed, rule and question, and the choice is frozen into the attempt.

- **Builder.** `POST /ohmylms/v1/skill-assessments` (needs `edit_posts`; runs the usual quiz permissions) or `Assessment\SkillAssessment::create()`.
  Body: `title`, `style` (`quiz`, `unit-test`, `course-challenge`), `skills` (1-40 skill IDs), `per_skill` (1-10, default 1), `time_limit`
  (minutes, optional), `publish` (publishes a frozen revision and the post), `chapter_id` (puts it at the end of that unit). At most 60
  questions in all. A shortage in any skill fails with the pool message and removes the half-made quiz.
- **Style.** New assessment setting `style` (quiz by default). It is only presentation: the intro wording and the unit test or
  challenge label. The kind (quiz, exam, practice) is unchanged.
- **Intro screen** (`Quiz\Intro`, shown on the Quiz Player block and the quiz page): "Time for a quiz?", "All set for the unit test?" or
  "Ready for the course challenge?", then "N questions · a-b minutes" (the quiz's own limit when it has one, otherwise one to two
  minutes a question) and "Covers N skills", plus a line about levels when the Khan model is on. It appears only for unit tests, course
  challenges and quizzes that draw from two or more skills; an ordinary quiz keeps its start screen.
- **Result screen.** With the Khan model on, the review lists each skill the attempt touched: "Leveled up: Not started -> Familiar",
  "Leveled down", or "Stayed at".

Not built: a time-per-question rule and any change to how a quiz is marked. (The unit page "Level up" button, placing a unit test at the end
of every unit and an authoring screen were missing at this point and were added in sections 10 and 11.)

Verification: assessment phase `skillcheck` (58 checks: input validation, shortage rollback, builder output, one question per skill per
attempt, placement, the intro summary and wording, timed quiz, Khan level moves over two attempts, the result block, off-state), all
assessment phases together 808, learning 76, player templates 81, Student Home 88, quiz exchange 578, quiz player block 37. Browser
(disposable site): the intro on the player, a real attempt submitted through the player (2 of 3 right), and "Leveled up: Not started
-> Familiar" for two skills and "-> Attempted" for the missed one; the quiz page also shows the intro and the levels. PHPCS is not
installed here, so it is outstanding for the new files.

## 10. Authoring screen and AI quiz building (October 16, 2026)

- **OhMyLMS -> Skill assessments** (`Assessment\SkillAssessmentPage`, needs `edit_posts`). Steps: choose a course and unit (optional), search
  for more skills (optional), tick the skills (a unit's skills start ticked, each with how many different approved questions exist and a
  warning at 0), then set title, style (Quiz, Unit test, Course challenge), questions per skill, time limit, publish now or keep a draft, and
  whether to add it at the end of the unit. It calls the same `SkillAssessment::create()` as the REST route; the unit placement needs edit
  rights on the course. A unit's skills are the skills on its lessons plus the skills its quizzes are connected to.
- **AI.** The builder is also an assistant and MCP tool pair (`list_unit_skills`, `create_skill_assessment`), draft only, approved in the chat
  first. The AI chooses skills and counts; the questions are always existing approved questions of any question type. AI-written questions go
  through the import tools, arrive unapproved and flagged, and cannot be drawn until a person approves them.
- **Question types.** Pools draw any approved version whose primary skill matches, so single and multiple choice, true/false, numerical,
  fill-in-the-blank, matching, structured and the interactive types all work. Checked in tests with a single-choice and a numerical question
  drawn together and marked in one attempt. Hand-marked types still wait for a teacher, and Khan levels move only after the marking.
- **Bug fixed on the way.** `SkillCatalogue::search` hid any skill that had no code, no objective or no UUID (a NULL score), so skills made
  in the editor without those could not be found by word. Scores are now COALESCEd. This also helps the existing MCP search tool.

Verification: assessment phase `skillcheck` (85 checks, including unit skills, tool calls, a learner refused, a tool "publish" ignored, mixed
question types, and the screen's listing, search, create and error paths), all assessment phases together 835, assistant 28 (tool count now 14),
comparison 98, learning 76, player templates 81, Student Home 88, JS 423 (422 pass, 1 skipped). Browser: the menu entry, the unit's three skills
with counts, creating a published unit test from the form. `mcp-integration.php` stops at "Official adapter server not registered" on this test
site (the MCP adapter is not installed there); it was not run to completion. PHPCS is not installed here, so it is outstanding for the new
files. The authoring screen is plain PHP with no script, so there was no build step.

## 11. Level up and unit tests for every unit (October 16, 2026)

- **Unit page.** `Student\CourseOutline` now marks each quiz with its assessment style, exposes a unit's unit test (`units[].test`) and the
  course's challenge (`challenge`), and names those rows "Unit test" and "Course challenge" instead of "Quiz". In the hub course overview a unit
  that has a published unit test shows a **Level up** button at the top of its body ("Take the unit test to level up your skills in this
  unit", or "again" once completed), and the course gets a **Course challenge** card with "Start course challenge". A learner who is not
  enrolled is sent to the course page, as for every other activity. Drafts never show.
- **Placement.** `SkillAssessment::create_for_course()` (REST `POST /ohmylms/v1/skill-assessments/course`, and a section on the Skill
  assessments screen once a course is chosen) creates one unit test for every unit that does not have one, from the skills its lessons and
  quizzes teach, and adds it at the end of the unit. Skills with fewer different questions than asked for are left out and listed; a unit with
  no skills, or none with enough questions, is skipped with the reason. Optionally one course challenge from all the skills, placed in the
  last unit. Running it again changes nothing (units that already have a unit test or the course a challenge are skipped). It needs edit
  rights on the course and creates drafts unless "publish" is ticked.
- **Limit.** A course with a published learning program (managed program) keeps its own item list, so new assessments appear for its
  learners only after they are added in the program editor; the screen says so. Courses without a program read their units directly and show
  them at once.

Verification: assessment phase `levelup` (42 checks: input and permission checks, two unit tests plus a challenge in unit order, left-out
and skipped reasons, rerun creates nothing, the outline's `test` and `challenge`, Level up on exactly the two units with a test, the course
challenge card, a visitor sent to the course page, a draft hidden, and the whole-course form), all assessment phases together 877,
learning 76, player templates 81, Student Home 88, assistant 28, JS 423 (422 pass, 1 skipped), Stylelint clean on the changed stylesheet.
Browser (disposable site): the course overview with Level up, the "Unit test" and "Course challenge" rows and the challenge card, and the
whole-course form on the authoring screen. PHPCS is still not installed here.

### Subsequent local standards pass (October 16, 2026)

The live Windows checkout has PHPCS installed, superseding the earlier environment limitation.
Checked the nine new assessment/intro/Khan/MCP/REST/template files: 16 errors and 2 warnings remain
after formatting fixes and removal of WordPress-global loop variables from the authoring template.
Twelve errors are the retained PSR-4 filenames (a compatibility exception, with findings visible);
the rest concern the Khan bulk-read SQL and the 200-course authoring query limit. No rules were
disabled. See the final live-folder checkpoint in `CONTINUE-LEARNER-HUB.md` for commands, verification
results and the remaining integration work. This pass did not rerun the disposable-site browser flows.

The subsequent five-part release pass resolved the bulk SQL findings with prepared identifiers and
documented query-local generated-placeholder annotations. The protected assessment run passed 1,049
checks, including empty/deduplicated bulk reads. Teacher Reports now replays only attributed class
quiz outcomes for a selected period, without reading overall skill history; its UI and privacy limits
are in `RELEASE-READINESS.md`. That checkpoint also supersedes the earlier credential/browser blockers
and records the remaining full-repository standards and exact-parity differences.

## 12. Where it lives in the code (checked October 19, 2026)

| Piece | Files and entry points |
| --- | --- |
| Practice runner (dots, Skip, stepped hints, tiers, Start over) | `assets/js/practice.js`, `includes/Practice/Sessions.php` (`can_skip`, `results`, `skipped`), REST `POST /practice/sessions/{uuid}/skip` in `Rest/V1/PracticeController.php` |
| Khan level model | `includes/Skills/Khan/Settings.php` (option `ohmylms_mastery_settings`, models `evidence` and `khan`), `Levels.php` (rules, `changes()`), `Schema.php` (table `{prefix}ohmylms_khan_level_events`, option `ohmylms_khan_schema`); the setting is the Mastery model switch under Gamification, XP & mastery (REST group `mastery` of `Rest/V1/EngagementController.php`) |
| Level display | `includes/Skills/MasteryDisplay.php` |
| Mixed-skill assessments | `includes/Assessment/SkillAssessment.php` (`create()`, `create_for_course()`), REST `POST /ohmylms/v1/skill-assessments` and `/skill-assessments/course` in `Rest/V1/AssessmentController.php`, setting `style` in `Assessment/AssessmentSettings.php`, pools in `Assessment/Pools.php` |
| Intro screen and result levels | `includes/Quiz/Intro.php`, `Quiz/AnswerReview.php` |
| Authoring screen | **OhMyLMS -> Skill assessments**: `Assessment/SkillAssessmentPage.php` and `templates/admin/skill-assessments.php` |
| AI and MCP tools | `list_unit_skills` and `create_skill_assessment` (draft only): `includes/MCP/SkillAssessmentTools.php`; see [../MCP.md](../MCP.md) |
| Level up and Course challenge on the hub | `includes/Student/CourseOutline.php` (`units[].test`, `challenge`), `templates/student-hub/course.php` |
