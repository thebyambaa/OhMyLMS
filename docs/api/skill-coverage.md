# Course skill coverage

`GET /ohmylms/v1/courses/{id}/skill-coverage` answers one question for an author: for each skill the course touches,
is it taught by a lesson in the course, and are there enough approved questions to assess it?

It is read-only, needs the right to edit the course, and reports on the saved **draft** program. The course builder
no longer has a step of its own for it: the Curriculum step reads it through the skill plan below, and the old
`#/courses/{id}/coverage` address opens Curriculum. Implementation: `OhMyLMS\Learning\SkillCoverage`.

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

The course builder no longer calls this route (it connects skills to the lessons already in the outline, see the skill
plan below); it stays for API and MCP clients.

**Questions** are not created here. The question bank's new-question dialog opens with `?action=new&skill={id}`, and
the new question starts linked to that skill as its primary skill.

## Skill plan: the Curriculum step

The Curriculum step shows the outline on the left and a skill panel on the right. The panel follows what is selected
on the left. All three routes need the right to edit the course (`Catalog::can_edit`) and answer `404` for an ID that is
not a course. Implementation: `OhMyLMS\Learning\SkillPlan`.

`GET /ohmylms/v1/courses/{id}/skill-plan?curriculum={item}` returns one curriculum (a syllabus) with its topics,
skill groups and skills, and how the course teaches them:

```json
{
  "course_id": 7, "revision": "9f2c…",
  "curricula": [ { "id": 1, "name": "Digital SAT Mathematics" } ],
  "curriculum": 1,
  "topics": [ { "id": 2, "name": "Algebra", "depth": 1,
                "groups": [ { "id": 5, "name": "Linear equations", "skills": [ 12, 13 ] } ] } ],
  "names": { "12": { "name": "Solve one-step equations", "code": "A.1" } },
  "skills": { "12": { "name": "…", "code": "A.1", "status": "covered", "teaching": [ { "id": 9, "type": "lesson", "title": "Halves", "unit_id": 3, "unit": "Fractions" } ],
                      "questions": 5, "questions_need": 3, "teaching_ok": true, "questions_ok": true } },
  "units": [ { "id": 3, "title": "Fractions" } ],
  "unit_topics": { "3": [ 2 ] }
}
```

`curriculum` defaults to the syllabus the course is linked to, else the first one; an unknown ID answers `400`. `names`
holds every skill of the curriculum; `skills` holds only the skills the course touches (the report's rows, without the
fields the panel does not use). The syllabus routes under `/curriculum/items/{id}/syllabus` need administrator rights;
this one is for course authors.

`PUT /ohmylms/v1/courses/{id}/skill-plan/units/{unit}` with `{ "topic_ids": [2, 3] }` chooses the topics a unit covers
(an empty list clears them) and answers `{ "unit_topics": { … } }` for the whole course. It only scopes the panel, so
selecting a lesson of that unit lists the skills of those topics; it connects nothing. The unit must be saved in the
course (`400` otherwise) and every topic must exist. The choice is course meta `_ohmylms_unit_topics`, kept apart from the
outline draft and from publication.

`PUT /ohmylms/v1/courses/{id}/skill-plan/lessons/{lesson}` with `{ "add": [12], "remove": [13] }` connects or
disconnects skills of a lesson placed in the saved draft (`400` for a lesson that is not placed, or a skill that does
not exist). The skills are the lesson's ordinary skill terms, so questions, practice and evidence see them as they see any
other. It answers `{ "lesson_skills": […], "skills": { … } }` with the lesson's skills and the updated teaching map.
