# Course and curriculum architecture audit

Date: 23 October 2026. Scope: repository inspection and read-only local data inventory, after reverting the visual builder. This describes the agreed Khan Academy-inspired target; it does not claim to describe Khan Academy's internal database.

## Agreed structure

```text
Subject
  Course
    Unit
      Lesson group
        Video / reading lesson / skill practice / quiz
      Unit test
    Course challenge

Syllabus ── reference for course planning and skill objectives
Shared skill library ── referenced by practice, questions and course outcomes
```

Lesson groups should be the normal authoring/presentation level, while existing ungrouped activities remain compatible. Unit tests can sit outside a lesson group, and a course challenge belongs at course level. A shared activity is a reusable record placed in an outline; the hierarchy describes placements, not exclusive ownership of that record.

## Mapping to existing records

| Target concept | Existing representation | Decision |
| --- | --- | --- |
| Subject | `Curriculum/Items` row with `item_type=subject`; course links through `Curriculum/Links` and `Placement` | Reuse. A course may retain multiple curriculum placements; do not invent a subject post type or silently choose a single owner. |
| Course | Existing `ohmylms-course` post plus learning program | Reuse course ID, enrollment and access rules. Separate the learner course from its syllabus reference in ordinary authoring. |
| Unit | Existing chapter post, related by `ohmylms_chapter_relationship` | Keep the public chapter identifiers and PHP class mappings. Present as “Unit.” |
| Lesson group | Ordered `program.groups[]` entries with UUID, `chapter_id` and title | Already implemented. Use this instead of introducing another post type/table or treating a reading lesson as a container. |
| Video / reading | Reusable lesson posts; lesson subtype/settings select text, video, audio and other supported delivery | Keep the native lesson editor and subtype contracts. |
| Skill practice | Program item of type `practice` pointing to a shared skill term; outcomes set the target and requirement | Keep shared skill identity and existing mastery/evidence services. |
| Quiz / unit test / course challenge | Existing quiz posts, with assessment styles `quiz`, `unit-test`, `course-challenge` | Keep the quiz/question editors and assessment contracts. Validate placement separately from style. |
| Syllabus | Curriculum item with `is_syllabus`, topics and skill groups | Keep the reference structure; do not make it the default owner of every learner course's structure. |
| Course bundle / roadmap | Existing curated grouping / shared-skill source infrastructure | Keep separate from the course outline and subject hierarchy. |

Sources: [Items](../includes/Curriculum/Items.php), [Placement](../includes/Curriculum/Placement.php), [CourseProgram](../includes/Learning/CourseProgram.php), [ProgramGroups](../includes/Learning/ProgramGroups.php), [assessment settings](../includes/Assessment/AssessmentSettings.php).

## What is already usable

- `ProgramGroups` validates group identity, course-unit membership and item membership. It keeps a flat activity list alongside grouped/ungrouped views, supporting a compatible transition.
- The ordinary course Learning screen already includes [LessonGroups](../assets/src/features/courses/LessonGroups.jsx), with create, rename, ordering, assignment and removal controls. Its [helpers](../assets/src/features/courses/groups.mjs) preserve item data and remove stale group membership when an item changes units.
- [CourseOutline](../includes/Student/CourseOutline.php) and [the learner course template](../templates/student-hub/course.php) already present units, lesson groups, practice, unit tests and a course challenge.
- [LearnerCatalog](../includes/Student/LearnerCatalog.php) builds the subject browser from existing curriculum placements and excludes roadmap infrastructure. The library can select which curriculum item types count as browsing roots.
- Learning programs already have private drafts, published versions, readiness checks and enrollment bindings. Those services should remain authoritative.
- [CourseFromSyllabus](../includes/Curriculum/CourseFromSyllabus.php) provides a separate creation helper that seeds an ordinary draft course from syllabus domains and shared skills. This differs from the continuously mirrored syllabus-owned course described below.

No new content types or parallel course database are justified by this audit.

## Findings requiring changes

### 1. The local subject structure is missing

The local inventory has **zero subject items**. All six non-trash/non-auto-draft courses lack a curriculum ancestor typed `subject`. The saved library type filter is `syllabus, unit`, and the current learner catalog query returns no subject roots.

This is a local data/configuration finding, not evidence that the subject model is absent. The model already supports it. Introduce explicit subjects and review course placements before changing the learner browser to prefer them. Do not manufacture subject names from syllabus codes or automatically reparent the existing curriculum.

### 2. Syllabuses currently own some learner course structure

[SyllabusCourse](../includes/Curriculum/SyllabusCourse.php) intentionally makes a syllabus into a real course. Its sync maps each syllabus skill group to a chapter, shared skills to course outcomes, and the syllabus name to the course title. It also renames and reorders native chapter records.

The local data contains three syllabus-owned courses and 158 chapters carrying syllabus-group mapping metadata. A syllabus flag can exist on a curriculum item whose type is not literally `syllabus`; the inventory has three flagged items but only two items typed `syllabus`.

This is a different concept from the agreed default: a course independently organizes teaching units and lesson groups while referencing a syllabus. Preserve existing mirrored courses as an explicit compatibility mode. New ordinary courses should not silently enter that mode. Moving existing syllabus skill groups down to lesson groups needs a reviewed mapping that retains course/chapter IDs, resource placements and enrollment history.

### 3. Lesson groups exist but are not populated locally

The three stored learning-program drafts contain zero lesson groups and zero grouped activity placements. This is not a missing backend model.

Ordinary courses expose groups in the Learning screen. Syllabus-owned courses are redirected to their syllabus workspace by [courseEditPath](../assets/src/features/courses/model.mjs) and [CourseEditor](../assets/src/features/courses/CourseEditor.jsx), so the authoring route differs by course type. A consistent course outline screen should make units, lesson groups and activities visible together without requiring users to understand these different storage paths.

### 4. Structure has two representations and several ordering rules

| Level | Current ordering source |
| --- | --- |
| Curriculum siblings | Curriculum item `position` |
| Courses in the public placement query | Course title, then ID; not an authored per-subject course order |
| Native course units | Chapter relationship `order_number`, then ID |
| Native unit activities | Content relationship `order_number` |
| Program lesson groups | Position in `program.groups` |
| Program activities | Position in `program.items`, with `chapter_id` and optional `group_id` |
| Learner units | First encounter of each chapter while traversing visible program items |

The learner adapter constructs units from activities, so empty units disappear and native unit order alone does not guarantee learner unit order. A course without a published program can fall back to native relationships; a managed course can use a published/enrollment-bound program instead.

The next implementation needs one shared structure contract and explicit projection rules for these existing stores. It should retain all legacy IDs and placements, preserve ungrouped content, and produce the same order for dashboard and learner readers. An authored course order within a subject would need an explicit compatible storage decision; current alphabetical order should not be described as manual ordering.

### 5. Published program versions do not freeze the whole outline

`CourseProgram` versions items, outcomes and groups, but unit titles and numbers still come from live chapter records. `Catalog::rename_chapter()` and `reorder_chapters()` write native records immediately. `SyllabusCourse::sync()` also updates live course and chapter titles. Published activity records are reusable, and their bodies remain live records as well.

Consequently, a program draft is not a complete draft of the learner-visible course structure/content. Before a new authoring flow promises safe drafts, it must explicitly define which titles, descriptions, ordering and content revisions are frozen and which shared resources intentionally update everywhere.

### 6. Concurrency and permissions differ across write paths

[Catalog::revision()](../includes/Learning/Catalog.php) fingerprints learning-program fields. It does not cover native chapter titles, numbers or chapter relationship order. Some program/group tools use `with_revision()`, while Content Hub chapter handlers call native chapter operations directly. The legacy chapter controller's main permission callbacks check `edit_posts`; specific course/chapter authorization needs review across each handler before that controller is reused as a common structure API.

Centralize object authorization, complete ordering validation, revision checks and atomic persistence in the shared service. Make all dashboard structure writers use it; adding a second guarded UI while legacy writers bypass it would leave the underlying problem unresolved.

### 7. Relationship cleanup needs investigation before migration

The local inventory found four chapter relationships with a missing course or chapter, and seven native activity relationships with a missing content or chapter record. Three activity rows have an empty type and `content_id=0`; these overlap with the invalid activity inventory and are not three additional proven broken lessons.

These counts include all native relationship rows, not only the six current courses. Their provenance has not been established; they may include old/test data. No cleanup was performed. Produce a reviewable list with ownership and references before deleting or remapping anything. Never cascade-delete real lessons, quizzes or shared skills to repair an outline.

## Read-only local inventory

| Observation | Result |
| --- | --- |
| Non-trash/non-auto-draft courses | 6: 3 published, 2 draft, 1 private |
| Curriculum types | 1 roadmap, 2 syllabus, 29 topic, 0 subject |
| Items flagged as syllabuses | 3 |
| Courses with a valid syllabus owner | 3 |
| Native chapter relationships, including stale rows | 170 |
| Chapters related to more than one course | 0 |
| Chapters marked as syllabus skill-group mirrors | 158 |
| Courses with stored learning drafts / published-program pointers | 3 / 1 |
| Lesson groups in stored drafts | 0 |
| Shared skill terms | 476 |
| Current learner catalog subject roots | 0 |

These are counts from the local installation on the audit date. They are not a production migration report. No learner attempts, mastery records, enrollments, posts or curriculum links were changed.

## Recommended implementation order

1. **Unify the structure contract and writes.** Normalize the existing course → unit → group → activity structure; include native metadata/order in conflict detection; enforce per-object capabilities; route dashboard writers through the same service. Add regression coverage for ordinary, mirrored and legacy courses before changing data.
2. **Make draft/live behavior explicit.** Preserve the published unit metadata and order while authors edit. Keep existing learning-program versions/enrollment policy. Define safe treatment of shared published content rather than implying program versions also version resource bodies.
3. **Establish subjects and browsing.** Review subject names and course placements, preserve other curriculum links, then configure the subject browser around `subject` items. Keep syllabuses, bundles and roadmaps distinct.
4. **Align authoring and learner presentation.** Put the existing lesson-group controls into a coherent unit outline. Use the same normalized order and labels in both readers, preserving legacy ungrouped activities. Enforce valid locations for unit tests and course challenges.
5. **Offer an explicit syllabus transition.** Seed new independent courses from syllabus references. For existing mirrored courses, preview proposed topic → unit and skill-group → lesson-group mappings; retain compatibility until each course is deliberately converted.

Steps 1–4 are now implemented: shared structure writes, draft/live unit snapshots, subject browsing and the independent-course outline editor. See [the unified editor](UNIFIED-COURSE-OUTLINE.md), [structure contract](COURSE-STRUCTURE-CONTRACT.md) and [subject browsing](SUBJECT-BROWSING.md). Step 5 now supports [creating an independent course from selected syllabus topics and skills](COURSE-FROM-SYLLABUS.md). Conversion of existing mirrored courses remains separate work. The findings and inventory above describe the original audit state.

## Verification and scope

- Read the curriculum, course/program, group, assessment, learner adapter and dashboard route implementations referenced above.
- Ran a local read-only inventory; diagnostic output is in ignored `test-results/course-architecture-audit.json`.
- Existing course structure tests: 285 assertions passed.
- Existing syllabus tests: 56 assertions passed using PHP with `mbstring` enabled. An initial run without that extension failed the invalid-UTF-8 check; the configured run passed.
- Existing JavaScript group/link tests: four passed.
- No new production code, schema migration, content changes, browser interaction or visual builder was introduced by this audit. The previously requested submenu removal remains unchanged.
