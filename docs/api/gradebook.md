# Gradebook (`ohmylms/v1/gradebook/*`, `ohmylms/v1/settings/grade-scale`)

Phase C of the [admin redesign](../ADMIN-REDESIGN.md): Content Hub › Gradebook with five views (Course × Students,
Roster × Assignments, Student transcript, Skill mastery, Org roll-up), per-cell manual overrides with a reason and
history, category weighting per course with an optional per-class override, and one site-wide letter scale.

Server code: `includes/Gradebook/` (`Schema`, `Repository`, `Items`, `Weights`, `GradeMath`, `CourseGrid`,
`Overrides`, `Views`, `SavedViews`, `ScaleSettings`, `Access`, `Version`, `Bootstrap`, `Rest/GradebookController`),
started from `Dashboard\Bootstrap::init()`. The older class gradebook (`Schools\Gradebook`,
`/courses/<id>/gradebook*`) keeps its routes; its grade writes now go through `Gradebook\Overrides` (history,
mirror). Client: `assets/src/app/features/gradebook/` and the settings tab `features/settings/GradeScaleForm.tsx`.

## Data model

| Source | Used for |
| --- | --- |
| `ohmylms_enrolments` (legacy table until the enrolment migration is complete) | rows: one per learner, the preferred enrolment (active, then completed, then suspended; newest), every `source` of the learner in the course, `class_id` (the roster that enrolled them) |
| `course_class_students` (+ enrolment `class_id`) | the learner's rosters in the course; "individuals" = none |
| `chapter_relationship` / `content_relationship` | graded items: published quizzes, exams (quizzes with `_ohmylms_exam_id`) and assignments placed in the course, in course order, with their module (chapter) |
| `quiz_attempts` (+ answers for the maximum), `assignment_attempts` | latest graded attempt; pending grading (quiz `in-review`, assignment `submitted`); quiz `in-progress` is not a submission |
| `learning_assignments` + `assignment_recipients` | per-learner due dates (Account Hub class assignments, UTC) when the item has none |
| `student_skill_state` | Skill mastery and the transcript's skills (levels as the dashboard heatmap) |
| new `gradebook_items` | per item: category (default assignment → `homework`, quiz → `quiz`, exam → `test`), optional maximum, audience (`all`, `classes` + `class_ids`, `individuals`), optional due date (UTC) |
| new `gradebook_weights` | course weights (`class_id` 0) and class overrides, each with a version |
| new `gradebook_cells` | current manual state per cell (`score`, `excused`, `none` after a revert), reason, version |
| new `gradebook_cell_history` | every change: action, old and new kind and score, maximum, computed score at the time, reason, actor, UTC time |

The schema (option `ohmylms_gradebook_schema` = 1) installs on first use and copies existing `gradebook_overrides`
rows into cells (history action `import`); `Schema::uninstall()` drops the four tables (legacy table untouched).
Score overrides are mirrored into `gradebook_overrides` so the class gradebook and its CSV agree.

## Cell states

| State | When |
| --- | --- |
| `not_assigned` | the learner is outside the item's audience (wins over everything; cannot be overridden) |
| `score` | a graded attempt, or a manual score |
| `pending` | a submission waits for grading (a newer pending retake never hides a graded attempt) |
| `missing` | assigned, due date passed, nothing submitted |
| `none` | assigned, nothing submitted, not due yet or no due date |
| `excused` | manual |

Every cell carries `computed` (what the attempts say) next to the effective value, `overridden` and `version`.

## Math

- Item percentage = score ÷ maximum (the attempt's frozen maximum for quizzes, else the item's).
- Counted cells: `score`; `missing` as 0 out of the maximum when **Count missing work as zero** is on (default);
  never `pending`, `none`, `not_assigned`, `excused`.
- **Points mode** (no weights): Σ counted scores ÷ Σ their maximums.
- **Weighted**: per category, Σ score ÷ Σ maximum; total = Σ(weight × category %) ÷ Σ(weights of categories with
  counted work). Empty categories drop out (the rest scale up); items whose category has no weight do not count.
- Weights: ≤ 12 slug categories, 0–100 with two decimals, sum 100 (or empty = clear).
- Which weights apply: the class override of the class that enrolled the learner (`via`), else the
  lowest-numbered class of theirs with an override, else the course's. `total.weights_class` says which.
- Totals are rounded to two decimals; the letter uses that value with inclusive lower bounds. `computed_percent`
  is the same total without manual cells.

## Access

Scoped exactly like the dashboard (`Dashboard\ScopeResolver`, see [dashboard.md](dashboard.md)): every request
takes `scope` (`all`, `school:N`, `class:N`; default the user's first). Meta capability `ohmylms_view_gradebook`
(site administrators via `manage_options`; school staff with a scope via `read`), exposed to the app.

| Action | Who |
| --- | --- |
| read, override/excuse/revert cells | anyone whose scope contains the course and the learner |
| course weights, item settings | site administrators and users who may `edit_post` the course |
| class weight override | users with a scope containing the class (administrators, its school administrators, its teachers) |
| letter scale settings | settings permissions (`manage_options`) |

Outside the scope: 404 `ohmylms_gradebook_course_not_found` / `_learner_not_found` / `_class_not_found`; scope
errors as the dashboard (`403 ohmylms_dashboard_forbidden_scope`); no access 401/403 `ohmylms_gradebook_cannot_view`;
not allowed to change 403 `ohmylms_gradebook_forbidden`. Class names outside the scope are never sent.

## Routes

All below `/wp-json/ohmylms/v1`, `Cache-Control: private, no-store`; reads say `X-OhMyLMS-Cache: hit|miss`.

| Method | Path | Arguments | Success |
| --- | --- | --- | --- |
| GET | `/gradebook/context` | `scope` | `{ scope, scopes[], courses[{id,title}], classes[{id,name,school_name}], scale[], site_admin }` |
| GET | `/gradebook/courses/<course>` | `scope`, `audience` (`all`/`individuals`/`class:N`), `status` (`missing`, `pending`, `overridden`, `excused`, `active`, `completed`, `suspended`), `type` (category), `module`, `search`, `orderby` (`name`/`total`/`source`), `order`, `page`, `per_page` (≤ 200, default 50), `export` (all rows, ≤ 5000) | `{ course, items[], all_items, modules[], categories[], classes[], weights{course, overrides[]}, scale[], missing_as_zero, rows[], summary{learners, average, items{id:{average, states}}}, total, total_pages, page, per_page, generated_at, scope, can{grade, manage_course, weight_classes[]} }` |
| GET | `/gradebook/classes/<class>` | `scope` | Roster × Assignments: `{ class, courses[{id,title,items,average}], learners[{id,name,totals{course:…|null}, cells{"course:item":cell}}], truncated, scale }` (≤ 12 courses, 120 columns, 500 learners) |
| GET | `/gradebook/learners` | `scope`, `search` | `{ learners[{id,name}] }` (≤ 20, the scope's learners) |
| GET | `/gradebook/learners/<learner>` | `scope` | transcript: `{ learner, enrolments[{course, items, weights, row}], truncated, mastery{skills[], levels[]}, scale }` (≤ 20 courses) |
| GET | `/gradebook/mastery` | `scope`, `course` (required in `all`), `audience`, `search`, `page` | `{ skills[], learners[{id,name,source,rosters,cells{skill:{percent,level}}}], levels[], total, total_pages, page }` (20 skills, 50 learners per page) |
| GET | `/gradebook/rollup` | `scope` | `{ nodes[{type: school|class|independent|individuals, id, name, learners, enrolments, average, missing_learners, pending, children[]}], truncated, scope }` (≤ 60 classes) |
| GET | `/gradebook/courses/<course>/cells/<item>/<learner>` | `scope` | `{ course, item, learner, cell{…, reason, updated_by, updated_at}, total, history[] }` |
| PUT | same | `scope`, `action` (`override`/`excuse`/`revert`), `score` (override, 0…max), `reason` (required for override and excuse, ≤ 500), `expected_version` (required; 0 = no manual change yet) | the cell detail; 409 `ohmylms_gradebook_stale` with `data.current`; 409 `ohmylms_gradebook_nothing_to_revert`; 422 `ohmylms_gradebook_invalid_cell` (`data.params`), `ohmylms_gradebook_not_assigned` |
| GET | `/gradebook/courses/<course>/weights` | `scope` | `{ course, weights, classes }` |
| PUT | same | `scope`, `class_id` (0 = course), `weights` (object), `expected_version` | `{ class_id, weights, version }`; 409 stale; 422 `ohmylms_gradebook_invalid_weights`, `ohmylms_gradebook_class_not_linked` |
| PUT | `/gradebook/courses/<course>/items/<item>` | `scope`, `category`, `max_marks` (null or > 0), `audience`, `class_ids` (linked classes), `due_at` (ISO 8601, site time without offset; null clears) | the item; 422 `ohmylms_gradebook_invalid_item` (`data.params`) |
| GET, POST | `/gradebook/views` | POST: `name` (1–80), `section` (`course`, `roster`, `student`, `mastery`, `rollup`), `query` | `{ views[] }` / 201 view (user meta `ohmylms_gradebook_views`, ≤ 50; unknown query keys dropped) |
| DELETE | `/gradebook/views/<id>` | — | `{ deleted, id }`; 404 for another user's view |
| GET, PATCH | `/settings/grade-scale` | `a_min`, `b_min`, `c_min`, `d_min` (1–100, descending), `missing_as_zero` | settings-group contract (`{ group, values, changed }`; 400 types/unknown, 422 per field) |

Errors: 422 `ohmylms_gradebook_too_large` (more than 3000 learners or 150 000 cells: pick a school or class),
500 `ohmylms_gradebook_query_failed` / `ohmylms_gradebook_storage`.

## Caching and performance

`CourseGrid::compute()` reads a course in a fixed number of queries (items, enrolments, rosters, names, quiz and
assignment attempts, due dates, cells, weights; id lists in chunks of 500) and returns a compact grid that does
not depend on the user. It is cached with `Dashboard\Cache` per course and scope (id `gradebook-course`, TTL filter
`ohmylms_dashboard_cache_ttl`, default 600 s) with the **gradebook version** (`Gradebook\Version`) in the key.
The version is a unique token replaced on every override, weight, item-setting and scale write, and once per
request on `ohmylms_attempt_submitted`, `ohmylms_attempt_graded`, assignment submit/review, manual enrolment,
unenrolment and quiz revision publication, so an override is visible in the next read. Transcript
(`gradebook-transcript`, per learner) and roll-up (`gradebook-rollup`) are cached the same way. Filters, sorting,
paging and per-user permissions are applied after the cache.

Measured on 800.local (30 learners × 20 items, in-process REST dispatch): cold 56–141 ms, warm 5–8 ms (median of 5).

## Client

Features `gradebook` (`/content-hub/gradebook`), `gradebook-roster`, `gradebook-student`, `gradebook-mastery`,
`gradebook-rollup` (`&section=roster|student|mastery|rollup`), capability `ohmylms_view_gradebook`. Filters live
in the address (`scope`, `course`, `audience`, `status`, `type`, `module`, `s`, `orderby`, `order`, `class`,
`learner`, page), so Share copies the address and saved views store that query. The grid (`GradeGrid.tsx`) is a
WAI-ARIA grid in `widefat` markup: one tab stop, arrows, Home/End, Ctrl+Home/End, Page Up/Down; Enter, Space or
F2 opens a cell; every state is written out (not colour only) and cells have accessible names with the computed
value. CSV export (all matching rows) neutralises spreadsheet formulas.

## Tests

- `tests/php/gradebook-rest-integration.php` (`OHMYLMS_GRADEBOOK_TEST=1`): 222 checks — states (missing vs not
  assigned vs none vs pending), weighted/points math and re-normalisation, letters, permissions per role and scope,
  overrides (reason, bounds, 409, history, legacy mirror, revert), weights (409, class override), item settings,
  scale settings, the five views, saved views, caching, timings. Fixtures: `tests/php/support/gradebook-fixture.php`;
  live: `tests/php/gradebook-browser-fixture.php create|cleanup gbklive<digits>`.
- `npx vitest run --configLoader runner features/gradebook` (model, grid keyboard and roles, Course × Students,
  the other views) and `features/settings/gradeScale`.

## Known gaps

- The Content Hub › Gradebook tab and its sections are registered by the coordinator in `AdminNavigation`; the
  Content Hub page itself still needs `manage_options`, so school staff use the endpoints but cannot open the page
  until it admits `ohmylms_view_gradebook` (as for the dashboard).
- "Seat" sources appear when the enrolment service records them; no seat writer exists yet.
- Due dates come from item settings or Account Hub class assignments; per-learner extensions are not modelled.
