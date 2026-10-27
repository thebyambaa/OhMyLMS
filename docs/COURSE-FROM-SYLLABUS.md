# Create an independent course from a syllabus

Use **Create course from syllabus** in a syllabus workspace, or choose **From a syllabus** when creating a course.

1. Enter the course title and choose topics or individual skills (1–200 skills).
2. Review the generated units, lessons and skill links.
3. Choose **Create draft course** to open the independent course editor.
4. Write and publish the generated block lessons, add practice or quizzes, save the outline draft, preview and publish when ready.

Each syllabus folder containing selected skills becomes a unit. Its skill groups become draft block lesson posts, with course-local skill links. Nested folders retain their syllabus traversal order as separate units. Groups directly under the syllabus use a unit named after that syllabus. Empty/unselected folders and groups are omitted. Existing skill IDs become optional program outcomes with generated unit IDs; lesson placements reference those outcomes through `skill_ids`. The course also stores references to the source syllabus objectives. Generated lessons have empty bodies for the author to fill; no questions or practice activities are invented.

The generated course has its own unit IDs, lesson posts and placement UUIDs. It does not carry the syllabus-owned course marker and is not redirected to the syllabus editor. Later syllabus structure edits do not rename or reorder its units/lessons. Skills remain shared records, so edits to a shared skill still apply to that skill. Existing syllabus-mirrored courses remain in their current mode; this action does not convert them.

## API and compatibility

- `GET /ohmylms/v1/courses/from-syllabus` retains its existing syllabus choices response.
- `GET /ohmylms/v1/courses/from-syllabus/{id}` returns `name`, `topics` with `lessons` and skill references, and an opaque source `revision`. Reading does not create or synchronize a mirrored course.
- `POST /ohmylms/v1/courses/from-syllabus` accepts `syllabus_id`, optional `title`, and optional `skill_ids`. A selection requires the reviewed `revision`. Invalid, duplicate, foreign or empty skill selections fail; stale sources return 409 before creation. The successful 201 response retains `id`, `name`, `units` and `objectives`, adding the lesson count as `lessons`.

Existing callers omitting `skill_ids` still generate from the entire syllabus, now including draft block lessons. Existing endpoints, public class names, field names and assessment contracts remain intact. The source curriculum lock covers validation and creation; a transaction rolls back the course, units, lesson posts and mappings if a later step fails. Normal REST authentication, cookie nonces and author capabilities apply. Publishing remains a separate action.

## Verification

`tests/php/syllabus-course-integration.php` runs only with `OHMYLMS_STRUCTURE_INTEGRATION=1` against local WordPress. It verifies source reads, subset generation, stable skill references, independent draft status, lesson linkage, backward compatibility, input validation, conflicts, authorization and rollback after partial creation.

Its `fixture` mode creates temporary browser data and an expiring session in ignored `test-results/syllabus-course-session.json`. Run Playwright with `tests/syllabus-course.config.cjs`, then run the PHP fixture with `cleanup`. The browser test selects a topic, reviews the plan, creates a draft and verifies its lesson skill links. All temporary records and the exact authentication session are removed during cleanup.

Local verification on October 24, 2026: 30 creation checks, 22 outline integration checks, 61 structure integration checks, 37 learning model checks, 295 structure tool checks and 56 syllabus unit checks passed. Both the creation browser workflow and existing outline save/preview/publish/conflict workflow passed. JavaScript tests: 588 passed, one skipped. Source contracts, changed PHP syntax/WordPressCS, changed authored JavaScript ESLint/Prettier and scoped CSS checks passed. Assets were rebuilt with the two existing webpack size warnings. Repository-wide standards continue to report existing unrelated violations.
