# Adaptive placement test (phase 5 of the skill graph)

A short test that finds where a learner stands in a subject without asking about every skill. Each question
depends on the answers so far, and the prerequisite links between shared skills
([AI skill analysis](AI-SKILL-ANALYSIS.md)) carry every answer across the subject.

## Taking it

My skills lists **Placement tests** for every subject with shared skills: **Take the placement test**, or, once
taken, the working grade per area, the skills to start with and **Take it again**. The test opens at
`?ohmylms_placement=SUBJECT`, or anywhere with the shortcode `[ohmylms_placement subject="Mathematics" items="15"]`.
Guests can take it too, with the same guest credential as practice (questions from public practice skills only).
When they log in on the same device, the test and its answers become theirs, as guest practice does.

The learner says which grade they are in (or "I am not sure"), then answers up to 15 questions (5 to 30 with
`items`). There are no hints, no tutor, no retries and no feedback until the end: the test measures what the learner
can do alone. **I have not learned this yet** replaces Skip and counts as a strong "not known".

At the end the runner shows the placement: how many of the subject's skills the learner already has, a working
grade per area, and where to start, each with a Practice link.

## How it decides

`Placement\PlacementModel` (no WordPress calls) keeps, for every shared skill of the subject, the probability that
the learner already has it.

- **Start.** From the stated grade: skills of earlier grades start likely known (0.85, or 0.75 for the grade just
  before), the learner's grade at even odds, later grades unlikely (0.25, then 0.15). With no grade, everything is
  at even odds.
- **A right answer** makes the skill known (0.95) and everything it builds on, however far back, likely known
  (0.9, 0.85, then 0.8). Skills of the same domain wholly before its grades rise to at least 0.75.
- **A wrong answer** makes the skill not known (0.1) and everything built on it likely not known (0.15, 0.2, then
  0.25). Skills of the same domain wholly after its grades fall to at most 0.3. **Not learned yet** is stronger
  (0.05, and 0.25 for later grades). Direct answers always stand, even when they disagree.
- **Next question.** Among skills with questions the learner may see and not asked yet, the most uncertain one
  that settles the most: like a binary search, a skill that splits its open prerequisite chain evenly scores
  highest, and a domain not asked about yet gets a bonus, so every area is reached early. Skills already below 0.2
  or above 0.8 are not asked. Ties go to the stated grade.
- **End.** After the question limit, or earlier when nothing is uncertain enough to ask.
- **Placement.** Known at 0.7 or more, not known at 0.3 or less, unsure between. An area's working grade is the
  highest grade at which at least 70% of its skills up to that grade are known. **Where to start**: skills not
  known whose prerequisites are all known, earliest grade first (at most six).

## What it changes

Questions come from the shared skill or from syllabus objectives mapped to it as `equivalent`, through the same
access rules as practice (public practice, question bank visibility, enrolment). The test runs as a practice
session (`mode` placement) through the practice routes, and its answers are graded with source `placement`. So the
questions actually answered are ordinary skill evidence, and *Settings → Gamification → XP and mastery → Answers
that count toward skills* lists **Placement** to switch that off. What the test only estimated is stored with the
test as the placement and never becomes a skill level. Placement tests earn no XP and do not count toward streaks.

## API and MCP

| Route | Access | Purpose |
| --- | --- | --- |
| `POST /placement/sessions` | signed-in learners, guests (guest credential header) | `subject`, `grade` (0–13), `item_limit` (5–30): starts a test; returns the practice session state |
| `GET /placement/subjects` | signed-in learners, guests | Subjects with a test, and where to take it |
| `GET /practice/sessions/{uuid}`, `POST …/answer`, `POST …/skip` | the learner or guest who started it | Run the test; `hint` and `ai` are refused |
| `GET /reports/skills/students/{id}/placement` | as the student report | Latest result per subject |

MCP: `get_student_placement`.

## Tests

`php tests/php/placement-unit.php` and `OHMYLMS_TEST_CREDENTIALS=... php tests/php/placement-test-integration.php`
on the isolated test site.
