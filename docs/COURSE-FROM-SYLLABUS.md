# Independent course generation contract

See [Learning Architecture](LEARNING-ARCHITECTURE.md) for product intent and author workflow. The service is implemented and the typed app exposes it as the **Create a course from a syllabus** workflow described below.

## Screen

`assets/src/app/features/syllabus/CourseFromSyllabusDialog.tsx` opens from the syllabus editor (**New course from this syllabus…**, the syllabus preselected) and from the course list (**Create from syllabus**, which loads it lazily through `features/syllabus/public.ts` and first lists the syllabuses and roadmaps). It is available to content authors (`edit_posts`), like the routes it calls.

1. **Select.** An optional course title (at most 200 characters; empty uses the syllabus name) and the skills to include: a checkbox per topic, per skill group and per skill, with select-all and clear. The count is shown against the 200-skill limit and review is unavailable outside 1–200 skills.
2. **Review.** The units, draft lessons and per-lesson skill links the service will create, computed from the read source (`generation.ts`, `planCourse`), with the counts. Nothing has been written yet; **Back to selection** keeps the selection.
3. **Create.** **Create draft course** posts `syllabus_id`, `title`, `skill_ids` (syllabus order) and the read `revision`. On success the app opens `#/courses/{id}/outline`. A 409 (the syllabus changed) offers **Reload the syllabus and review again**, which rereads the source and keeps the still-present selected skills. Other errors stay in the dialog.

Generated outcomes are optional; choose required skills and targets in the course outline before publishing.

Each syllabus folder containing selected skills becomes a unit. Its skill groups become draft block lesson posts, with course-local skill links. Nested folders retain their syllabus traversal order as separate units. Groups directly under the syllabus use a unit named after that syllabus. Empty/unselected folders and groups are omitted. Existing skill IDs become optional program outcomes with generated unit IDs; lesson placements reference those outcomes through `skill_ids`. The course also stores references to the source syllabus objectives. Generated lessons have empty bodies for the author to fill; no questions or practice activities are invented.

The generated course has its own unit IDs, lesson posts and placement UUIDs. It does not carry the syllabus-owned course marker and is not redirected to the syllabus editor. Later syllabus structure edits do not rename or reorder its units/lessons. Skills remain shared records, so edits to a shared skill still apply to that skill. Existing syllabus-mirrored courses remain in their current mode; this action does not convert them.

## API and compatibility

- `GET /ohmylms/v1/courses/from-syllabus` retains its existing syllabus choices response.
- `GET /ohmylms/v1/courses/from-syllabus/{id}` returns `name`, `topics` with `lessons` and skill references, and an opaque source `revision`. Reading does not create or synchronize a mirrored course.
- `POST /ohmylms/v1/courses/from-syllabus` accepts `syllabus_id`, optional `title`, and optional `skill_ids`. A selection requires the reviewed `revision`. Invalid, duplicate, foreign or empty skill selections fail; stale sources return 409 before creation. The successful 201 response retains `id`, `name`, `units` and `objectives`, adding the lesson count as `lessons`.

Existing callers omitting `skill_ids` still generate from the entire syllabus, now including draft block lessons. Existing endpoints, public class names, field names and assessment contracts remain intact. The source curriculum lock covers validation and creation; a transaction rolls back the course, units, lesson posts and mappings if a later step fails. Normal REST authentication, cookie nonces and author capabilities apply. Publishing remains a separate action.

## Verification

`tests/php/syllabus-course-integration.php` runs only with `OHMYLMS_STRUCTURE_INTEGRATION=1` against local WordPress. It verifies source reads, subset generation, stable skill references, independent draft status, lesson linkage, backward compatibility, input validation, conflicts, authorization and rollback after partial creation.

App tests (`features/syllabus/CourseFromSyllabusDialog.test.tsx`, `generation.test.ts`, the syllabus page and course list tests) cover topic, group and individual skill selection, the review plan, the request payload, the 200-skill limit, syllabus choice, stale-revision reload and opening the course outline. On 2 November 2026 the PHP suite passed 32 checks with the typed screen in place.

The browser test written for the recovered dialog left with it; its Playwright config and the PHP suite's browser `fixture`/`cleanup` modes were removed on 2 November 2026. The component tests above are the coverage for the typed screen.

Local verification on October 24, 2026: 30 creation checks, 22 outline integration checks, 61 structure integration checks, 37 learning model checks, 295 structure tool checks and 56 syllabus unit checks passed. Both the creation browser workflow and existing outline save/preview/publish/conflict workflow passed. JavaScript tests: 588 passed, one skipped. Source contracts, changed PHP syntax/WordPressCS, changed authored JavaScript ESLint/Prettier and scoped CSS checks passed. Assets were rebuilt with the two existing webpack size warnings. Repository-wide standards continue to report existing unrelated violations.
