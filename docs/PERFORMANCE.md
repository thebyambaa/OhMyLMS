# Skill and student performance tracking

One system tells where a learner stands on each skill. Every answer is evidence in one ledger, one
engine turns the evidence into a stored state, and every screen, report, MCP tool and AI note reads that
state through one API.

```
answer → grade event → evidence outbox → skill_evidence ──► Engine ──► student_skill_state ──► Snapshot ──► screens, reports, MCP
                                  (only activities that count)    │         (level + mastery score)        └──► AI notes (explain only)
                                                                   └──► skill_level_events (what each activity did)
```

## The ledger

`skill_evidence` (see [the assessment engine](ASSESSMENT-ENGINE.md)) holds one row per graded answer per
skill: marks, whether it was independent (no hint), whether it was a first try, the question family and
difficulty, and the activity it came from. Rows are written once per grade event and a regrade supersedes
the old row. Everything below is derived from this table, so it can always be rebuilt.

**Which activities count.** *Settings → Gamification → XP and mastery → Answers that count toward skills*
switches each activity (quiz, practice, exam, inline check, question player, or any registered assessment
kind) on or off. A kind's default comes from its profile (`skills` => true; see
[assessment profiles](ASSESSMENT-PROFILES.md)). Changing it rebuilds the evidence in the background.

## One scale, three policies

Every level is one of `not-started`, `attempted`, `familiar`, `proficient`, `mastered`
(`Performance\Levels`). A **policy** decides which level some evidence earns; it is a pure function, so
switching policy is a rebuild and never a data migration. Choose one under *How skill levels are decided*.

| Policy | How it decides |
| --- | --- |
| `evidence` (default) | Familiar: two or more independent first tries at 60% or better. Proficient: three correct from two question families at 80% or better over the last eight. Mastered: five from three families at 85%, plus a correct answer a day after first reaching proficient. Thresholds: option `ohmylms_mastery_rules`. |
| `steps` | Replays the answers activity by activity. Practice sets the level (under 70% right first time is Attempted, 70-99% Familiar, all right Proficient) and never lowers it. A quiz, exam or question player moves it up one step when every question on the skill is right and down one when any is missed. A learner may retry a wrong practice answer. |
| `score` | The level is the band the 0-100 mastery score sits in. |

The 0-100 **mastery score** (`Skills\ScoreModel`) is always computed beside the level and shown when
*Show the mastery score* is on. The XP, streak and medal features read it as before.

A policy is a class implementing `Performance\Policy` (`id`, `label`, `description`, `level( $rows,
$context )`). Add one with the `ohmylms_performance_policies` filter.

## The engine and the state

`Performance\Engine::recompute( $student, $term )` loads the countable evidence, asks the active policy for
the level, computes the mastery score, accuracy, review timing and counts, and writes one row to
`student_skill_state`. It also logs what the newest activities did to the level in `skill_level_events`
(the "Leveled up: Not started → Familiar" lines on the result screen). Both tables are derived.

States are recomputed whenever evidence changes (`Skills\Evidence`, run after each request, with a
one-minute WP-Cron fallback). `Evidence::rebuild( $student )` rebuilds one learner;
`Engine::schedule_rebuild()` rebuilds everyone in slices after a settings change. `ohmylms_skill_state_updated`,
`ohmylms_skill_score_updated` and `ohmylms_skill_score_milestone` fire as before.

## Reading it

`Performance\Snapshot` is the only reader: `students()` and `student()` for the level of each skill,
`skills()` for everything a dashboard shows, `counts()`, `started_terms()` and `next_steps()`. The
learner dashboard, teacher and school reports, course outlines, bundles, completion rules and recommendations
all use it, or the state table it reads.

REST: `GET /reports/skills` (learners x skills for a course or class), `GET /reports/skills/students/{id}`,
`POST /reports/skills/students/{id}/rebuild`, `POST /reports/skills/students/{id}/insight`, and the settings
at `/engagement/settings/mastery`.

## Guidance

`Performance\Guidance` (read through `Snapshot::guidance( $student )`) turns the learner's states and the
shared skill library ([AI skill analysis](AI-SKILL-ANALYSIS.md): domains, grade bands, prerequisites and
"includes" links) into four kinds of advice. The arithmetic is in `Performance\GuidanceModel`, which has no
WordPress calls. Guidance only reads: levels and review dates stay what the engine stored.

Skills are pooled first. An objective with an `equivalent` mapping counts as its shared skill. When a learner has
answered on more than one member (a Grade 7 objective and an IGCSE objective of the same shared skill), all those
answers are pooled, each counted once, and judged by the site's level policy, so evidence split across syllabuses
adds up. A skill with one member keeps its stored level.

- **Strength by area.** For each domain the learner has started, the mean of the level points (attempted 0.2,
  familiar 0.5, proficient 0.8, mastered 1) as 0-100, named strong (75+), steady (45+) or building, with the
  highest grade reached at proficient or above.
- **Reviews covered by other work.** A proficient skill is due for review `review_after_days` after its last
  answer. Each skill worked since then, at familiar or above and not struggling, that includes it adds the
  link's weight as credit (weights multiply along a chain of up to three links; the total is capped at 1). Credit
  moves the review later by that share of the interval, so full credit counts as a whole review. A review that
  was due but is pushed past today is listed as covered, with the skills that covered it.
- **Next steps, one sitting at a time.** Help with struggling skills first (with the weaker prerequisites to check),
  then reviews still due (longest overdue first), then **keep going** with skills already started (not struggling,
  not yet proficient; most recently worked first), then skills ready to learn. Ready skills are those not started
  whose prerequisites are all proficient, plus, for a learner whose profile grade is known, the skills of that
  grade that need nothing first, so a new learner who skipped the placement test still has somewhere to start.
  Among ready skills of the same grade, one whose practice also covers a due review comes first ("Also reviews …").
  While three skills are in progress, no new skill is offered: finishing comes first. The steps are cut to one
  sitting of about fifteen minutes (help, keep going and new skills about 5 minutes each, reviews about 3; three to
  five steps), shown as "One sitting of about N minutes". Up to five ready skills are also returned as `ready` for
  the next sitting. Each step names the skill to practise (the skill itself, or a mapped objective that has practice
  questions) and up to two lessons.
- **Placement tests.** Skills the learner's latest [placement test](PLACEMENT-TEST.md) placed as known count as met
  prerequisites (they are never suggested, and never become levels), and the skills it said to start with come
  first among ready skills, marked "Your placement test suggests starting here".
- **Readiness.** For each syllabus the learner has started, the share of its objectives they have (proficient or
  above counts fully, familiar half, attempted a fifth), the counts, and the next objectives to learn.

My skills shows all four above the skill table. A learner with several subjects can narrow the whole page to one
(`?skills_subject=`; `subject` on the REST route); the teacher's student report (Skill performance, a learner's
details) shows the same guidance and their placement tests. The school hub's class report does not: it reports
recorded class learning only. REST: `GET /reports/skills/students/{id}/guidance` (`limit` of
next steps, 1-20), with the same access as the student report; guardians see it too, since it is a summary. MCP:
`get_student_guidance`.

### Exam mode

A learner can choose the exam or syllabus they are **Preparing for** and its date on My skills (user meta
`ohmylms_exam_target`; REST `GET/POST/DELETE /reports/skills/students/{id}/exam-target`, writable by the learner or
an administrator; a no-JavaScript form posts to `admin-post.php?action=ohmylms_exam_target`). The date may be today
up to three years ahead. `Performance\ExamPlan` (pure, unit tested) turns it into a plan:

- The target's skills not yet proficient are ordered by how much each lifts readiness (objectives mapped to one
  shared skill add up, and a skill not yet started lifts more than one already familiar), skills whose
  prerequisites are proficient first, then by grade.
- They are spread over the days until the last week, at most three a day. `on_track` is false when more remain than
  three a day can cover; the most useful still come first.
- The last seven days start nothing new: each day reviews the target skills already worked on, least recently
  practised first. With nothing left to learn every day is review.
- Guidance returns `exam` (syllabus, date, days left, phase, readiness, `on_track`, a week of `schedule`). While an
  exam is set, next steps are help first, then today's plan (`reason: exam`), then the usual steps, still cut to one
  sitting. Teachers see the exam and its readiness in the learner's guidance panel.

### Who needs help now (teachers)

The Skill performance page opens with **Who needs help now** for the chosen course
(`GET /reports/skills/help-now?course_id=` or `class_id=`, same access as the matrix: a course the viewer may edit
or a class they teach; up to 200 learners). `Performance\HelpNow` takes each learner's `stuck` list from guidance
(every skill where recent answers had errors) and groups learners by the weaker prerequisite to reteach, so one
mini-lesson helps several. A skill with several weak prerequisites goes to the one the most learners share (ties:
lowest ID); a stuck skill with no weak prerequisite is its own group ("Reteach this skill"). Biggest group first;
inside a group learners with the nearest exam come first, with the exam shown. `stuck` lists every such skill, not
only the ones that fit the learner's own fifteen-minute sitting.

### Credit back

An objective with an `equivalent` mapping stands for its shared skill, so work done under another syllabus on the
same shared skill counts toward it (`Guidance::credit()`, answers pooled as in Guidance). It shows in two places:

- A course program's skill targets (the Skills table of a learning course) show the pooled level marked "from other
  syllabuses" when it is above the objective's own level.
- It counts toward course **completion** only when the pooled level is proficient or higher, and only for programs
  that recognise prior learning without an evidence window (`recognize_prior` on, `evidence_days` 0). Familiar or
  attempted work elsewhere is shown ("counts once proficient") but never completes a target. A course-only program
  (`recognize_prior` off) or one with an evidence window keeps counting only its own evidence.

The learner's course outline counts credited targets that are proficient toward "N of M skills to Proficient" and
shows how many came from other syllabuses (`summary.credited`).

Readiness for a target syllabus already counted pooled levels.

## AI notes

With an AI provider connected under *AI settings* and *Write AI study notes* on, a learner can ask for a short
note about their own skills (My skills) and a teacher for a note about a learner (the student report).

- **AI explains, never decides.** Levels, scores and evidence come only from the engine. The model is told so,
  and its words are stored as text only.
- **Only skill names and numbers leave the site.** No name, e-mail, user or site identifier.
- **Cached.** A note is stored with a hash of the facts it came from and is reused until the learner's skills
  change; the daily limit set for AI applies per reader.
- **Optional.** When AI is off, unconfigured or failing, every screen works exactly as before.

A learner can only ask for their own note (audience `student`); staff with full access to a learner ask for
the `teacher` note. Guardians see summaries only.

## MCP

`get_student_skills`, `get_student_guidance`, `get_skill_report`, `get_performance_settings`,
`set_performance_settings` and `write_student_skill_note`. Levels are not writable. See [MCP](MCP.md).

## Tests

`php tests/php/performance-unit.php` (pure, including the guidance model),
`OHMYLMS_TEST_CREDENTIALS=... php tests/php/assessment-integration.php performance` and
`OHMYLMS_TEST_CREDENTIALS=... php tests/php/guidance-integration.php` on the isolated test site.
