# Course syllabus objectives

An ordinary course's Content editor includes **Syllabus Objectives** below the course information. Authors browse existing syllabuses by name, code, edition, grade and subject, filter objectives by topic or search their full wording, and add selected targets. Selections survive filtering and switching syllabuses. Already-added references are disabled. The table shows live objective wording, source context, existing course content connections and removal controls. These changes save independently of the course Save button.

The source records are existing curriculum syllabus items and skill terms. `_ohmylms_course_objectives` stores only `{syllabus_id, term_id}` pairs, up to 200 per course. The pair identifies a mapping because a skill may be placed in several syllabuses. Nothing is copied and no learning program, mastery rule, official objective or learner evidence is changed. Syllabus-owned courses retain their existing workspace and mirroring behavior; this panel is for ordinary courses referencing syllabus objectives.

Coverage includes native content in this course and non-practice items in its learning-program draft. Skill taxonomy links and explicit draft attachment skill IDs supply the connections. Unrelated library lessons and questions are excluded. The table counts lessons and assessment activities, not individual questions. It reports recorded links, not reviewed content suitability or objective achievement.

Deleted source objectives remain visible as unavailable mappings and can be removed. Existing unavailable references can be retained while other references change. New references must belong to their selected syllabus. Browsing uses the read-only syllabus outline and never creates or synchronizes a syllabus-owned course.

## API and MCP tools

All operations require authentication, `edit_posts`, and permission to edit the particular course. Referencing official objectives does not grant curriculum editing permission. Existing WordPress REST authentication and nonce handling apply; application-password authentication can be used by an external integration.

- `GET /ohmylms/v1/courses/{id}/syllabus-objectives` returns `mappings`, a `revision`, available `syllabuses`, and `limit`.
- `GET /ohmylms/v1/courses/{id}/syllabus-objectives?syllabus_id={id}` returns selectable `objectives` from that syllabus.
- `PUT /ohmylms/v1/courses/{id}/syllabus-objectives` takes `{revision, mappings: [{syllabus_id, term_id}]}` and returns the refreshed table. An empty list clears course targets. The revision from the latest GET is required. A stale revision returns HTTP 409; reload before reconciling the requested changes.

Writes run under the existing curriculum lock and validate all new references before saving. IDs must be positive JSON integers. Duplicate pairs are collapsed. The UI preserves the selection after a failed save.

The existing dedicated MCP server now exposes `get_course_objectives`, `list_course_objective_options`, and `set_course_objectives` through WordPress Abilities. MCP access retains the existing enabled-add-on and administrator checks as well as the course API permissions. See [MCP tools](MCP.md) for argument schemas and replacement semantics. The API also accepts optional `page` and `per_page` (up to 50) for bounded mapping/option responses; option browsing accepts `search`. Without pagination arguments, the existing course editor response is unchanged. MCP defaults to 10 rows and preserves the full-set revision on every page; successful MCP writes also return a bounded first page.

Lesson objective mappings and evidence-backed content-alignment reviews are available through the
administrator MCP authoring tools and corresponding REST routes. Course targets still describe
course scope; lesson mappings describe teaches/practices/assesses relationships. Saved lesson
reviews become stale when inspected content or official wording changes. They do not infer learner
achievement. See [authoring workflow and schemas](MCP.md#lesson-and-question-authoring).
`CourseObjectiveTools.php` follows the existing namespace-to-file autoloader and retains its two
visible WordPress filename findings, like the other MCP classes; no filename rule is suppressed.

## Standards and verification

`includes/Curriculum/CourseObjectives.php` intentionally follows the existing namespace-to-file autoloader. Its two WordPress filename findings remain visible; renaming it requires an autoloader migration. The course-scoped relationship query is prepared and intentionally uncached so editor refreshes show current native placements; PHPCS reports its two direct-query warnings. No rule is suppressed.

The objective panel and picker are separate components sharing source labels and identity helpers. Their styles live in `assets/css/course-objectives.css`, loaded by the admin design enqueuer. Coverage batches skill taxonomy reads and indexes draft attachment links rather than scanning every attachment for each content item. Objective components and helpers pass ESLint and their dedicated stylesheet passes Stylelint. Source contracts and the production build are also checked.

Run `node --test tests/js/course-objectives.test.mjs tests/js/courses.test.mjs tests/js/course-organization.test.mjs`, `tests/php/course-objectives-integration.php` and `tests/browser/course-objectives.spec.cjs`. Database/browser checks require the isolated `ohmylms_source_test` installation and external `OHMYLMS_TEST_CREDENTIALS`; fixtures clean up their own records. Browser checks cover search, retained selections, failed-save recovery, persistence, connection details, removal, and desktop/mobile layouts.

Verified on 2026-10-15: database/REST integration and browser acceptance checks pass. Production assets were rebuilt and synchronized; webpack retains its two math-runtime size warnings. All 1,608 source files parse and 236 component contracts validate. The integration fixture has no PHPCS errors and two CLI file-I/O warnings (local credentials input and STDOUT output).

The complete `npm test` run after cleanup reports 408 tests: 407 passed, one skipped, zero failures. Objective JavaScript passes ESLint and formatting; its dedicated CSS passes Stylelint. Browser regression acceptance passes after extracting the picker and stylesheet.

The JavaScript cleanup repaired missing real helper dependencies in the quiz-autosave harness and React lazy state initialization in the quick-prompt harness. Asset comparison now treats equivalent quoted/unquoted ordinary object keys alike, with explicit checks retaining sensitivity to changed values and computed keys. It does not change asset baselines or exclude assets. The isolated browser still encounters the existing duplicated ProseMirror keyed-plugin error described in `DEVELOPMENT.md`; the acceptance test annotates that exact error and rejects other page errors. The surrounding course editor retains its existing narrow-screen layout limitations; the objective picker is separately constrained to the mobile viewport and its bounds are asserted.

MCP verification: run `tests/php/course-objectives-integration.php mcp` in the isolated installation. It checks native ability schemas and permissions, paginated search and mapping reads, revision conflicts, replace/remove/clear behavior, unchanged learning requirements, and actual adapter initialization, tool discovery and read/write tool calls. Temporary MCP options, credentials and sessions are cleaned up. MCP lifecycle/schema coverage is also included in `tests/php/mcp-addon-unit.php`.

The MCP extension passed 38 combined MCP/REST integration assertions, the separate 22-assertion REST run and MCP add-on lifecycle/schema checks on 2026-10-15. PHP syntax checks pass. Cleanup reduced the Content Hub controller's PHPCS errors from 22 to its two documented PSR-4 filename findings, matching the tool-schema file. The integration fixture retains only its two CLI file-I/O warnings. Other legacy files have not received a repository-wide standards rewrite.
