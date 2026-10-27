# Subjects and course browsing

Subjects are curriculum items whose saved type is `subject`. They organize course discovery; syllabuses, bundles and roadmaps retain their existing meanings. No existing item is converted automatically.

## Author workflow

1. Open Content Hub → Curriculum and create or edit an item with type `subject`.
2. Use its **Linked content** controls to assign courses, or use the course editor's existing curriculum placement controls. Courses can also sit in subject subgroups.
3. In the subject's **Subject course order** panel, use Up/Down and **Save course order**. The list includes unpublished courses so their future positions can be authored. **View subject page** opens the public result.
4. Publish the course and its outline using their existing controls. Published course visibility governs browsing; unit metadata comes from the published/assigned outline rather than the draft.

## Learner workflow

- Public index: `/?ohmylms_subject=all`; a subject page uses `/?ohmylms_subject=ID`.
- Shortcodes: `[ohmylms_subjects]` for the index, `[ohmylms_subjects id="123"]` for one subject.
- The learner hub's Browse subjects tab opens subject detail pages with `hub_subject=ID`. With no custom library type selection, the tab now defaults to actual subjects.
- Course cards follow the authored order, deduplicate courses placed in multiple subject subgroups, and omit draft/private/roadmap courses. Empty subjects are not listed.
- Each card links to the course overview and offers **Start course**, or the learner's existing Start/Continue/Review learning action. Starting an unenrolled course opens its enrollment overview; browsing grants no access and creates no enrollment.
- **View published outline** shows the learner's assigned unit titles. A course opened from a subject has a validated breadcrumb back to that subject. Password-protected courses keep their existing locked display/access policy.

## Storage and API

Curriculum links remain the source of membership. Each subject's optional ordered ID list is stored in the non-autoloaded `ohmylms_subject_order_ID` option. New placements append after ranked members in title/ID order; removed memberships are filtered out. Deleting a curriculum item removes its ordering option. Reordering never deletes courses, changes their bodies, overwrites unrelated curriculum links or changes enrollment/progress records.

`GET /ohmylms/v1/subjects/{id}/courses` returns all existing authoring members, including descendant placements, and a revision. `PUT` accepts the complete integer `course_ids` array plus that `revision`. Both require the existing curriculum administration capability. Missing/stale revisions return 409; duplicate, foreign, malformed or incomplete lists return 400. Ordering writes use the existing curriculum lock and transaction. Reload the ordering panel after a conflict before retrying. A placement created concurrently is retained and appended; an order save never replaces membership.

Existing course/curriculum route names, fields, hooks and assessment contracts are unchanged. Public templates escape output by context. Personalized subject pages are served without public caching.

## Verification

- 33 local subject integration checks cover ordering, subgroup placements, independent subjects, privacy, publication isolation, Start/Continue actions, stale/invalid writes, permissions, new/removable placements and option cleanup.
- Two browser tests cover anonymous discovery, published outline preview, course breadcrumb navigation, 390px layout, unavailable subjects, author ordering and enrolled learner continuation.
- JavaScript suite: 584 passed, one skipped. Source-contract checks and build pass; build retains two existing webpack size warnings. New PHP/React/CSS pass targeted WordPress standards checks.
- Repository-wide checks still report pre-existing PHP findings, 316 JavaScript errors/37 warnings, 125 CSS errors, and formatting issues in five unrelated files.
- The existing global SDK makes an unauthenticated `/wp/v2/users/me` preferences request on guest pages, including the ordinary homepage. The visitor browser test tolerates only its verified `rest_not_logged_in`/401 rejection; other browser errors still fail. The subject browser itself issues no client REST requests.

For repeatable local checks, explicitly set `OHMYLMS_SUBJECTS_INTEGRATION=1` and run `tests/php/subjects-integration.php`. Its `browser` mode creates temporary content and an expiring session in an ignored JSON file for `tests/subjects.config.cjs`; run its `cleanup` mode afterwards. No schema or data migration is required.
