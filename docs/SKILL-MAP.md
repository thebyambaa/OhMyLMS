# Skill map (phase 4 of the skill graph)

Three views read from the shared skill library: its domains and grade bands, prerequisite and "includes" links
([AI skill analysis](AI-SKILL-ANALYSIS.md)), the syllabus mappings, and learners' pooled levels
([performance](PERFORMANCE.md#guidance)). They only read; nothing is changed from them.

`Curriculum\SkillMap` reads the data and `Curriculum\SkillMapModel` holds the rules, with no WordPress calls.

## Coherence map

**Curriculum → Skill map → Coherence map** (`#/content-hub/curriculum/skill-map`). Pick a subject: its shared
skills are drawn as a grid of domains (rows) by grades (columns), each skill in the cell of its first grade, with
"to N" when its band runs further. Selecting a skill lights up everything it builds on (however far back) and
everything that builds on it, dims the rest, and opens its details: grades, how many objectives map to it, what it
needs first, what needs it, what it includes and what includes it, with weights.

**Links to check** lists what looks wrong in the links:

| Check | Meaning |
| --- | --- |
| `later_prerequisite` | A prerequisite is first taught in a later grade than the skill that needs it. |
| `long_gap` | A prerequisite ends four or more grades before the skill starts; a review or a bridging skill may help. |
| `no_prerequisites` | A skill above the first grade of its domain needs nothing; a link is probably missing. |

Skills without a domain or a grade are listed under **Not on the map yet**.

**Show what a syllabus teaches** marks each skill taught by one of the subject's syllabuses (an `equivalent`,
`broader`, `narrower` or `combined` mapping; `related` mappings are reference only), and counts the syllabus's
objectives with no such mapping. **Show a learner** finds a learner by name or email and colours each skill by
their level, with their strength (0-100) per domain beside it. This is the teacher's view of the student skill map.

## Syllabus comparison

**Skill map → Compare syllabuses**: pick syllabus A and B. Both are read through the shared skills their
objectives map onto, so syllabuses in different languages or with different wording compare directly. The page
shows the share of skills taught by both, counts per domain, and three lists (taught in both, only in A, only in
B) with each side's objectives and how they map. Objectives with no covering mapping are counted and left out;
map them, or analyse the syllabus with AI, for a full comparison.

## Student skill map

My skills shows **My skill map** under the guidance: the shared skills of the subject the learner has started most,
by domain and grade, each marked with their level (an objective with an `equivalent` mapping counts as its shared
skill), with their strength per domain. A learner with several subjects can switch between them
(`?skill_map_subject=`). On a phone the grid scrolls sideways inside its frame, with the domain column kept in view.

## API and MCP

| Route | Access | Purpose |
| --- | --- | --- |
| `GET /syllabus-comparison/skill-map/subjects` | administrators | Subjects with their shared skill count and syllabuses |
| `GET /syllabus-comparison/skill-map` | administrators | `subject`; optional `syllabus_id`, `student_id`. Domains, grades, skills with links, unplaced skills, issues, and the overlays |
| `GET /syllabus-comparison/compare` | administrators | `a`, `b`: rows, `both`, `only_a`, `only_b`, `unmapped_a`, `unmapped_b`, `overlap`, `domains` |
| `GET /reports/skills/students/{id}/map` | as the student report (the learner, staff in scope, guardians) | `subject` (optional): the learner's subjects and one map with their levels |

MCP tools: `get_skill_map`, `compare_syllabuses`, `get_student_skill_map`.

## Tests

`php tests/php/skill-map-unit.php`, `npm run test:app` (the typed skill map, `features/curriculum/`), and
`OHMYLMS_TEST_CREDENTIALS=... php tests/php/skill-map-integration.php` on the isolated test site.
