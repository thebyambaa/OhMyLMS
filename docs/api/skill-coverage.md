# Course skill coverage

`GET /ohmylms/v1/courses/{id}/skill-coverage` answers one question for an author: for each skill the course touches,
is it taught by a lesson in the course, and are there enough approved questions to assess it?

It is read-only, needs the right to edit the course, and reports on the saved **draft** program. The course builder
shows it as the **Skill coverage** step (`#/courses/{id}/coverage`). Implementation: `OhMyLMS\Learning\SkillCoverage`.

## Which skills are listed

A skill is listed when any of these is true:

- the draft program has it as a skill **outcome** (required or optional, with a target level);
- a placed lesson, quiz or assignment is **connected** to it, by the skill taxonomy on the post or by the placement's own
  skill list;
- a **practice** activity of the course is on it;
- the course targets it as a **syllabus objective** (`_ohmylms_course_objectives`).

## What counts

| Question | Counts | Does not count |
| --- | --- | --- |
| Is it **taught**? | A lesson placed in the course that is connected to the skill. | A quiz tag, a syllabus objective or a practice activity. Reading a lesson is not mastery, but a lesson is how a course teaches a skill. |
| Is it **assessable**? | Approved, auto-marked, non-secure questions whose **primary** skill is this one, in the pool the course may use (the same bank and author rules, and the same query, as publication readiness). The count needs enough questions *and* enough question families. | Supporting-skill mappings and quiz tags. |

The thresholds come from the mastery rules (`<target>_min` questions and `<target>_families` families). For a skill
that is an outcome they follow its target (`familiar`, `proficient` or `mastered`). A skill that is not an outcome is
judged against `proficient`, and `need_basis` says so (`target` or `default`).

## Response

```json
{
  "course_id": 7,
  "summary": {
    "skills": 4, "required": 2, "covered": 1,
    "needs_teaching": 0, "needs_questions": 1, "needs_both": 2,
    "objectives": 1, "objectives_taught": 0, "required_not_ready": 1
  },
  "skills": [
    {
      "term_id": 12, "name": "Add halves", "code": "F.1",
      "outcome": true, "required": true, "target": "mastered",
      "objective": false, "practice": false,
      "teaching":  [ { "id": 9, "type": "lesson", "title": "Halves", "unit_id": 3, "unit": "Fractions" } ],
      "assessing": [ { "id": 20, "type": "quiz", "title": "Quiz", "unit_id": 3, "unit": "Fractions" } ],
      "questions": 5, "questions_need": 5, "families": 3, "families_need": 3,
      "need_basis": "target", "teaching_ok": true, "questions_ok": true,
      "status": "covered"
    }
  ]
}
```

`status` is one of `covered`, `needs-teaching`, `needs-questions` and `needs-both`. Skills are ordered gaps first
(`needs-both`, then `needs-teaching`, then `needs-questions`), required before optional, then by name; covered skills
come last. `required_not_ready` counts required skills whose question pool is short, which is also what blocks
publishing. It agrees with `CourseProgram::readiness_report()` for those skills.

Errors: `404` for an ID that is not a course, `401` or `403` without the right to edit it, `503` when learning storage
is not installed.
