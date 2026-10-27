# Course structure contract

This implements steps 1 and 2 of the course/curriculum architecture audit. The dashboard now also provides the [unified independent-course outline editor](UNIFIED-COURSE-OUTLINE.md). There is no bulk data migration or change to assessment/question contracts.

## Read and save

Authenticated authors with `edit_post` for the course can read `GET /ohmylms/v1/courses/{id}/structure`. The response contains `course_id`, opaque `revision`, ordered `units`, `outcomes`, and `native_items`. Units contain existing chapter IDs, titles, numbers, program items, lesson groups and ungrouped items. Empty native units and legacy unplaced program items are retained. Existing saved group and placement UUIDs are preserved; unsaved native fallback placements now have deterministic UUIDs.

Native relationship order determines unit order. Program groups/items keep their existing ordering rules. The learner outline uses the same unit ordering helper, while continuing to omit empty/inaccessible units and use the existing enrollment/program selection policy.

Authoring responses include `X-OhMyLMS-Structure-Revision` and `X-OhMyLMS-Structure-Course`. Send the revision back as `X-OhMyLMS-Structure-Revision` on a mutation, or as the existing JSON `revision` field. The token remains 32 hexadecimal characters and now covers native unit metadata/order, native activity placements, and program state. Missing or stale tokens return HTTP 409; callers must reread and reapply their edit. There is no automatic stale-write retry.

The dashboard middleware records tokens from existing reads and successful writes. It loads before the existing application, retains apiFetch JSON/raw-response behavior, and keeps separate state in each tab. It adds no per-row fetches. Public course reads retain their existing access policy and expose no private structure headers.

## Covered boundaries

The REST dispatch adapter runs after each existing route permission callback, additionally checks the specific course, and applies a shared named lock and transaction to:

- Legacy course chapter-list and chapter/content placement operations.
- Content Hub catalog operations.
- Course learning-program save/publish operations and MCP structure operations.

Authoring reads take the same lock so their response and revision describe one coherent snapshot. Syllabus-to-course synchronization also uses the common lock/transaction. Existing route names, custom lesson registrations, successful response formats and native chapter create/update/delete hooks remain available.

Unit ownership must be exclusive to the target course. Complete-list saves reject foreign/duplicate unit IDs and missing existing units. Explicit ordering positions must be distinct nonnegative integers; otherwise array order is used. Validation or database ordering failures roll back the transaction and invalidate request-local caches.

## Narrow compatibility changes

- External structure clients, including MCP unit writers, must first read a revision and send it with mutations. This intentionally closes the former unguarded write paths while preserving route and field names.
- Complete-list saves cannot silently drop existing units/activities. Use explicit removal. Populated or published units cannot be cascade-deleted through the legacy endpoint.
- Activity-list saves place existing, editable lesson/quiz/assignment/session records. They preserve reusable titles/bodies and student history. Create/edit resources through their existing editors before placing them; temporary or missing IDs are rejected. Registered custom lesson types remain supported.
- Removing a native activity placement preserves the content post, learner progress and any independently published program placement. Published programs continue to require their own explicit authoring change/publication.

## Remaining steps

Unit titles, descriptions, numbers and ordering now have draft/live separation. Native records remain the dashboard's authoring draft; learner views use immutable unit snapshots stored with program versions. See [Course outline publishing](COURSE-OUTLINE-PUBLISHING.md). Shared resource bodies remain live. [Subject browsing and manual course ordering](SUBJECT-BROWSING.md) are implemented. An explicit syllabus transition remains separate work. Existing orphan/test records are not cleaned up automatically.

## Verification

`tests/php/course-structure-integration.php` requires an explicit `OHMYLMS_STRUCTURE_INTEGRATION=1` and a running local WordPress database. It creates and cleans up temporary records. It covers revision/authorization checks, native and program saves, rollback, ordering, stable identities, deletion safety and preserved learner history. `tests/js/course-structure-client.test.mjs` covers apiFetch transport and tab isolation.

The browser fixture uses the same explicit environment switch. Run `tests/php/course-structure-browser-fixture.php` to create temporary records and an expiring session, run Playwright with `tests/course-structure.config.cjs`, then run the fixture with `cleanup`. Its ignored session file must never be committed. The browser test exercises the existing dashboard Save button and a stale edit across two tabs.

Local verification on October 23, 2026:

- Shared structure integration: 38 checks passed, including SQL rollback, preserved progress and syllabus mirror compatibility.
- Existing structure tools: 295 checks passed; existing syllabus unit tests: 56 passed.
- JavaScript suite: 584 passed, one skipped; includes six structure-client tests.
- Existing dashboard browser flow: passed. Temporary content and authentication session were cleaned up.
- Build succeeded with two existing webpack size warnings. Changed/new PHP files passed syntax checks; new service/controller/helper/tests passed WordPressCS, and the authored client passed ESLint/Prettier. Source-contract validation passed.
- Repository-wide standards remain failing: PHP findings in existing code, 316 JavaScript errors/37 warnings, 125 CSS errors, and formatting findings in five unrelated files. No blanket baseline or disabled standards rule was introduced.
