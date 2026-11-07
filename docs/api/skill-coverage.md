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
  "revision": "9f2c…",
  "units": [ { "id": 3, "title": "Fractions" } ],
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

`revision` identifies the saved outline the report was read from; send it back when fixing a gap. `units` lists the
course's units, which are where a fix can place a lesson.

Errors: `404` for an ID that is not a course, `401` or `403` without the right to edit it, `503` when learning storage
is not installed.

## Fixing a gap: add a lesson

`POST /ohmylms/v1/courses/{id}/skill-coverage/lessons` creates an empty **draft** lesson, connects it to the skill and
places it at the end of a unit, in one step. Nothing is published; the author writes the lesson next.

| Field | Required | Meaning |
| --- | --- | --- |
| `skill_id` | yes | The skill term the lesson teaches. |
| `unit_id` | yes | A unit of this course. |
| `revision` | yes | The `revision` from the report the author is looking at. |
| `title` | no | Up to 200 characters; defaults to the skill's name. |

It answers `201` with `lesson` (`id`, `title`, `unit_id`, `edit_url`), the new `revision` and the updated `report`, so
the view needs no second request. Needs the right to edit the course.

Errors: `400` for an unknown skill, a unit that is not in this course, or a title that is too long; `409`
(`ohmylms_program_stale`) when the outline changed since `revision` was read, in which case nothing is created; `403`,
`404` and `503` as for the report. If placing the lesson fails the lesson is deleted again.

In the course builder the buttons are disabled while any step has unsaved changes (a fix changes the saved outline) and
in a course with no units. After a fix the Curriculum step reloads the changed outline.

**Questions** are not created here: the **Add a question** link opens the question bank's new-question dialog with
`?action=new&skill={id}`, and the new question starts linked to that skill as its primary skill.
