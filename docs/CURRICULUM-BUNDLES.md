# Curriculum and Course Bundles

Administrators describe the structures their content belongs to (exam boards, national curricula, tests, grades, subjects, syllabuses), place courses, skills, question banks and exams in them, and group selected courses and syllabuses into **Course Bundles** that learners add to their own dashboard. Examples in the UI and tests are illustrative names, not verified syllabus definitions; nothing is pre-seeded.

Admin pages: the **Curriculum** and **Course Bundles** tabs of **OhMyLMS → Content Hub** (`#/content-hub/curriculum` and `#/content-hub/bundles`; see [CONTENT-HUB.md](CONTENT-HUB.md)). They are SDK pages, so `#/extensions/curriculum` and `#/extensions/bundles` still open the same tabs. Learner section: the student dashboard (`[ohmylms_dashboard]`) or `[ohmylms_bundles]` on any page.

## Concepts and rules

- **Curriculum item**: stable numeric ID and UUID, name, parent (0 = top level), item type, sibling order, optional description, syllabus code and version. Any depth up to 10 levels and 5,000 items. The type is a free lowercase slug (suggestions: framework, qualification, level, grade, section, subject, syllabus, strand, topic, unit; extend with the `ohmylms_curriculum_item_types` filter or just type your own such as `ib-programme`). Items with the same name stay separate records.
- **Syllabus**: any curriculum item can be switched on as a syllabus (whatever its type). A syllabus has **skill groups** and **skills** of its own, its topics and chapters are the items beneath it, and it can be loaded from a CSV file. See [SYLLABUS.md](SYLLABUS.md).
- **Learning mode** (traditional, skill-based, blended) stays a course setting in its learning program. Curriculum membership is only a link and never stores, implies or changes a mode.
- **Links** place existing content under an item: `course`, `skill`, `bank` (question bank) and `quiz` (a quiz or an exam). Many-to-many; a link copies nothing, grants nothing and is removed when the content is deleted.
- **Structure safety** (all enforced on the server, under one named lock and a transaction): a parent must exist; an item can never move under itself or a descendant; depth is capped; deleting an item with children needs a choice (`promote` them one level up, or delete the branch); anything with links, children or bundle memberships needs explicit confirmation. Deleting only ever removes associations, never linked content.
- **Course Bundle**: name, description, draft or published, and an ordered list of members, each a course or a curriculum item (typically a syllabus). The same course or item can be in any number of bundles. Nothing is added automatically. A bundle needs at least one member to be published. Unpublishing hides it from learners without removing their follows.
- **Following** a bundle adds a row linking learner and bundle. It never creates or changes an enrollment, never grants access, and never affects course completion or skill state.
- **Skill mapping**: an explicit, directional link from a curriculum-specific skill to a shared skill, with a relation and a note describing how requirements, difficulty or syllabus versions differ. Skills are never merged by name. A skill cannot be both a mapped skill and a shared target (no chains). Only `equivalent` mappings pool evidence; `related` mappings are shown for reference only.

## Curriculum and bundles replace course categories and tags

Course categories no longer exist as a way to organize courses: **the curriculum is the hierarchy**, and a course is placed under one or more items. Course tags are replaced by **Course Bundles**, which are curated by administrators instead of being free-form labels. Nothing is converted automatically and nothing is deleted.

| Where | Before | Now |
| --- | --- | --- |
| Course editor, Organize tab | Category and tag pickers | Curriculum item list (searchable, indented by depth) and Course Bundle list. Each change is saved immediately through `courses/{id}/organization`; the editor's Save button covers only the course itself. |
| Who may change it | Anyone who could edit the course | Anyone who may edit the course can change its curriculum placement. Course Bundles are what learners see and follow, so only administrators can change which bundles a course is in; everyone else sees them read-only. |
| Course list filter (admin) | Category dropdown | Curriculum dropdown. A parent item includes everything below it. |
| Admin menu | Categories, Tags | Curriculum and Course Bundles tabs of Content Hub (the old `#/categories` and `#/tags` screens open these) |
| Public filters and tabs | Category and tag checkboxes, category tabs | Curriculum items (indented, only those with published courses) and published Course Bundles. Filter values are `c<id>` for an item and `t<id>` for a bundle. |
| Shortcode `[ohmylms_course_list]`, block, Elementor, Bricks, WPBakery | `category` | `curriculum` and `bundle` (IDs, or `c12` / `t3` style slugs, comma-separated). A value that names nothing valid shows no courses rather than all of them. The old `category` attribute is ignored. |
| Single course sidebar | Category and tag lists | Where the course sits (for example *Science › Physics*) and its **published** bundles. The existing page-feature toggles (`category`, `tag`, and their `_with_enroll` variants) keep their keys. |
| Membership plans | Course category and tag rules | `course_curriculum` (includes everything below each item) and `course_bundles` (a bundle's courses plus the courses under its curriculum members). See below for the old rules. |
| Duplicate course | Copied all terms | Copies the curriculum placement. Bundle membership is not copied, because bundles are curated. |
| Export / import | Terms | Exports `curriculum` (paths such as `["Science", "Physics"]`) and `learning_bundles` (titles). Files exported earlier import categories as curriculum items and tags as **draft** bundles, reusing items and bundles with the same name. |
| LearnDash, LearnPress, Tutor LMS, MasterStudy imports | Created category and tag terms | Categories become curriculum items (keeping their parents), tags become draft Course Bundles. Existing items and bundles with the same name are reused. |
| Setup wizard | Created category terms | Creates top-level curriculum items. |

### Existing data

- Old category and tag **terms and their assignments are left exactly as they are**. The `course_category` and `course_tag` taxonomies stay registered, so old links such as `/?post_type=ohmylms-course&course_category=<slug>` still list the same courses. The terms are not shown or editable anywhere in the plugin any more, and they are never created from imports.
- To move old structure over, create the matching curriculum items and bundles and place the courses (the importers above do the same for other LMS plugins). You can then delete the old terms with core tools if you want to.
- **Membership plans keep working.** A plan that still has a category or tag rule keeps granting access through it, because removing it silently would also remove members' enrollments. Such rules are listed in a warning in the plan editor, count in the course preview, and can be **removed but not added to or replaced** (the API answers `membership_legacy_rules`). Remove one once its courses are covered by curriculum items or bundles.
- Course payloads keep the keys the admin app reads. `categories` and `tags` now carry the course's curriculum items and bundles as `{id, name, slug}`; new `curriculum` and `learning_bundles` carry more detail.

### Compatibility endpoints

`GET categories` and `GET tags` list curriculum items and bundles in the shape of the old terms (`term_id`, `id`, `name`, `slug`, `parent`, `count`, `courses`; tags can be searched with `search`). Every write to them, and `POST courses/{id}/terms` for the two taxonomies, answers **410** (`ohmylms_categories_replaced`, `ohmylms_tags_replaced`, `ohmylms_taxonomy_replaced`). The course list accepts `curriculum_id` and `bundle_id`, and the old `category_id` and `tag_id` mean the same. Saving a course with `categories` or `tags` in the body writes nothing.

### New routes

| Route | Purpose |
| --- | --- |
| `GET/PUT courses/{id}/organization` | A course's curriculum items and bundles. `PUT` takes `curriculum_ids` and `bundle_ids`; send only what changes. Unknown IDs are 400, a published bundle cannot lose its only member (409 `ohmylms_bundle_empty`), and non-administrators changing bundles get 403. |
| `GET curriculum/outline` | Names only, for pickers. `?courses=1` adds each item's published courses. |
| `GET bundles/outline` | Bundle names and status for pickers |

Placement changes fire `ohmylms_curriculum_changed`, which re-resolves membership plans.

### Labels in the admin app

The exact-string relabeling of the recovered admin (`labels.mjs` and its `i18n.gettext_ohmylms` filter) was deleted with that admin at the cutover ([CUTOVER.md](CUTOVER.md)). The typed app writes "Curriculum" and "Course bundle(s)" in its own strings; only the read-only `#/taxonomies` screen ("Categories and tags") still uses the old names.

## What learners see (per followed bundle)

- Each course: enrolled or not, learning mode, and counts from the course's own rules (activities, skill targets, checkpoints for a learning program; completed activities for an original-curriculum course). Course completion is shown as its own state. No percentage is computed.
- Each curriculum item: its path, code and version, the published courses linked under it (and under its descendants) and its skills.
- Skills across the bundle: level (Not assessed, Developing, Proficient, Mastered), classified as strength, needs practice (developing with repeated recent errors), in progress or not assessed (never counted as a gap), with a practice link only when the learner may practise the skill and approved questions exist, and a short next-practice list.
- Combined skills where mappings exist: a shared level pooled from the shared skill's own evidence and its `equivalent` mapped skills, with each source's own level, curriculum path, code, version and note listed beside it.

Everything is read-only. Pooled evidence is computed on request from existing skill-evidence rows: each graded part counts once however many mapped skills reference it, and a question answered again is never an independent first try. Nothing writes `student_skill_state` or the completion tables, so shared evidence cannot complete another course. Viewing uses `CompletionPolicy::status`, never `award`. Students only see published courses; a draft or private course in a bundle stays hidden from them.

## Storage

Course Bundles were stored as "tracks" until schema version 6. Installing version 6 renames `ohmylms_tracks`, `ohmylms_track_items` and `ohmylms_track_follows` to the bundle names, renames the `track_id` columns to `bundle_id`, swaps the `track_position` index for `bundle_position`, and moves the membership plan meta key `_course_tracks` to `_course_bundles`. Every row is kept (the tables are renamed, not copied), and repeating the migration changes nothing. If both an old table and a populated new table exist, the old data is left untouched and the schema stays not ready until the leftover is resolved by hand.

Six additive InnoDB tables created by `OhMyLMS\Curriculum\Schema` (option `ohmylms_curriculum_schema`, installed on `init`, safe to repeat): `ohmylms_curriculum_items`, `ohmylms_curriculum_links`, `ohmylms_skill_mappings`, `ohmylms_bundles`, `ohmylms_bundle_items`, `ohmylms_bundle_follows`. No existing table is altered; courses, enrollments, learning programs and progress are only referenced by ID. Deleting a course, quiz, skill or user removes the matching links, mappings, bundle slots or follows. Schema version 2 adds the syllabus flag (two columns on `ohmylms_curriculum_items`) and two more tables, `ohmylms_syllabus_groups` and `ohmylms_syllabus_group_skills`; see [SYLLABUS.md](SYLLABUS.md).

## Authorization

| Who | Access |
| --- | --- |
| Administrators (`manage_options`, filter `ohmylms_can_manage_curriculum`) | Every `curriculum/*`, `skill-mappings` and `bundles*` route. Skill mapping is an administrator-only action; Skills are a core feature, so no add-on switch is involved. |
| Signed-in learners | `me/bundles`, `me/bundles/{id}` and `bundles/{id}/follow`, always for the current user, never a user parameter. Published bundles only. |
| Guests | Nothing (401). Authors and teachers without `manage_options` get 403 on administration routes. |

## REST API (`ohmylms/v1`)

| Route | Purpose |
| --- | --- |
| `GET curriculum/tree` | Flat ordered items with child, link and bundle counts, types and limits |
| `POST curriculum/items`, `GET/PUT/DELETE curriculum/items/{id}` | Create, read (with links, mappings and dependents), edit (optional `expected_updated_at` for 409 on stale saves), delete (`children=promote|delete`, `confirm`) |
| `POST curriculum/items/{id}/move` | `parent_id`, optional zero-based `position` |
| `POST/DELETE curriculum/items/{id}/links`, `GET curriculum/link-targets` | Link or unlink content; search content |
| `GET/PUT/DELETE skill-mappings` | Read, save or remove a mapping |
| `GET/POST bundles`, `GET/PUT/DELETE bundles/{id}`, `PUT bundles/{id}/items`, `POST bundles/{id}/publish`, `GET bundles/targets` | Bundle administration (deleting a followed bundle needs `force`) |
| `GET me/bundles`, `GET me/bundles/{id}`, `POST/DELETE bundles/{id}/follow` | Learner discovery, progress and following |
| `GET/PUT courses/{id}/organization`, `GET curriculum/outline`, `GET bundles/outline` | Where a course sits (see above) |
| `GET categories`, `GET tags`, and the 410 write routes | Compatibility with the old category and tag endpoints (see above) |

Hook: `do_action('ohmylms_lms_student_dashboard_sections')` fires at the end of `[ohmylms_dashboard]`; the bundle section also prints on the existing `ohmylms_lms_student_profile_after_dashboard_content` hook, and only once per page.

## Source

- PHP: `includes/Curriculum/` (Schema, Tree, Items, Links, SkillMappings, Access, Bootstrap, and `Placement`, the one place that knows how courses sit in the curriculum and bundles), `includes/Bundles/` (Bundles, Follows, Progress, Combined, Frontend), controllers `CurriculumController`, `CourseOrganizationController`, `SkillMappingController`, `BundleController`, `MyBundlesController` in `includes/Rest/V1/`. `CategoryController` and `TagController` are now read-only compatibility views. Public lists use `ohmylms_course_ids_for_group()` in `includes/Utility/course-functions.php`.
- Admin UI: the typed app's `assets/src/app/features/curriculum/` (`#/content-hub/curriculum`, skill map, subjects, compare and `#/content-hub/bundles`), with shared helpers in `assets/src/features/curriculum/`.
- Course placement: the course editors in `assets/src/app/features/course-structure/` and `courses/`. Memberships: `assets/src/app/features/memberships/`. The old category and tag screens were deleted; `#/taxonomies` is a read-only view.
- Learner UI: server-rendered by `Bundles\Frontend`, with the `ohmylms/bundles` Interactivity API module (`assets/interactivity/bundles.js`) and `assets/css/learning-bundles.css`.

## Validation

Syllabuses (skill groups, skills and CSV import) have their own checks, listed in [SYLLABUS.md](SYLLABUS.md).

```sh
node --test tests/js/curriculum.test.mjs tests/js/curriculum-api.test.mjs
npm run test:app
php tests/php/curriculum-unit.php
OHMYLMS_TEST_CREDENTIALS=/path/test-credentials.json php tests/php/curriculum-integration.php
OHMYLMS_TEST_CREDENTIALS=/path/test-credentials.json php tests/php/placement-integration.php
```

The PHP integration suite refuses any database other than `ohmylms_source_test`. It covers hierarchy creation at different depths, ordering, moves, circular and over-deep moves, stale saves, lock contention, deletion safety, links and cleanup hooks, a permission matrix for guests, learners and authors, bundle membership across bundles, follow versus enrollment and access, read-only progress, strengths, gaps and "Not assessed", practice availability, mapping rules, de-duplicated pooling, no leakage into other courses' completion, escaping, and repeatable migration with existing records unchanged. The curriculum and placement browser specs were deleted with the recovered admin editors they drove.

`placement-integration.php` covers the replacement: placing and removing a course, validation, the administrator-only rule for bundles, the published-bundle guard, the course list filter and its old parameters, the compatibility and 410 endpoints, slug parsing, `narrow_query`, public lists, carousel and filter templates, the single course sidebar, shortcodes, membership resolution and the old-rule safeguard, duplicate, export and import (including old export files), imports from other plugins, the setup wizard, and that old terms and archive queries are untouched. `placement.spec.cjs` drives the admin menu and old routes, the Organize tab, the course list filter, the membership editor (including removing an old rule), the public filters with AJAX, and old archive addresses.

## Known limitations

- No drag and drop (use the up, down and "Move to" controls) and no bulk import or export of structures. Curriculum filters exist for courses (admin list and public lists) but not for skills and question banks. Memberships are available to code through `OhMyLMS\Curriculum\Links::memberships()`.
- Old category and tag terms are never converted. Moving old structure over is manual (or happens through the importers); until then a plan's old rules keep granting access and can only be removed.
- Only administrators can put a course in a Course Bundle; a teacher who owns a course can place it in the curriculum but not in bundles.
- A course in a bundle by way of a curriculum member shows in that bundle's lists but is not marked as a direct member in the Organize tab.
- Bundle membership is not copied when a course is duplicated, and the admin course list has no Course Bundle filter control (the `bundle_id` parameter works).
- The old `category` shortcode, block and builder attribute is ignored, and the `Category Base` / `Tag Base` permalink settings still describe only the old term URLs.
- Only administrators manage structure, bundles and mappings. Teachers cannot yet.
- Bundle order is creation order; there is no reorder control for bundles themselves (members can be reordered).
- Suggested bundles rank only by how many of the learner's enrolled courses a bundle includes.
- Viewing progress for a learning-program course can create that enrollment's program binding, as any learner page view already does.
- Strings added after the last catalog refresh are not yet in `languages/ohmylms.pot` or the Mongolian translation; see "Translations" in [DEVELOPMENT.md](DEVELOPMENT.md).
