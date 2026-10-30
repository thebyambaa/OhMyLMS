# Curriculum workspace API

Typed screens in `admin.php?page=ohmylms-app` that replace legacy Content Hub curriculum screens.
Each screen's extension route is its legacy route. All routes are under `ohmylms/v1` and require
the curriculum administrator permission (`Curriculum\Access::admin`), enforced on the server.

| Screen | App route / extension route | Feature |
| --- | --- | --- |
| Curriculum (fields, icons, moves, deletion) | `/content-hub/curriculum` | `features/curriculum/CurriculumPage` |
| Skill map | `/content-hub/curriculum/skill-map` | `features/curriculum/SkillMapPage` |
| Subject profiles | `/content-hub/curriculum/subjects` | `features/curriculum/SubjectProfilesPage` |
| Course bundles | `/content-hub/bundles` | `features/curriculum/BundlesPage` |

## Curriculum items: icons and branch deletion

`GET /curriculum/tree` now also returns `icons` (`[{id, label}]`, the catalogue `Curriculum\Icons`
validates against). `PUT /curriculum/items/:id` accepts `icon` (an id from that list, or `''` for
the kind's default) with the existing `expected_updated_at` check.

`GET /curriculum/items/:id` returns `dependents` and `delete_revision`, a hash of the item's
`updated_at`, its descendant IDs and every count deletion affects. `DELETE /curriculum/items/:id`
takes `children` (`''`, `promote`, `delete`), boolean `confirm` and optional 64-character
`expected_revision`; other values return 400. The existing domain rules apply: an item with
children needs a strategy (409 `ohmylms_curriculum_has_children`), and children, links, bundle
memberships or skill groups need `confirm` (409 `ohmylms_curriculum_needs_confirmation`). When
`expected_revision` is sent, it is compared inside the curriculum lock; any change to the branch
returns 409 `ohmylms_curriculum_stale` and nothing is deleted. The dialog previews the effects,
offers promote or whole-branch deletion, and requires a fresh review after a stale refusal. Linked
courses, skills, banks, exams, a syllabus's course and the skills inside groups are never deleted.

## Skill map (read-only)

- `GET /syllabus-comparison/skill-map/subjects`: subjects with shared skills or syllabuses.
- `GET /syllabus-comparison/skill-map`: required `subject` (1–190 characters); optional
  `syllabus_id` and `student_id` (non-negative integers; `''` means none, as legacy clients send).

The screen shows a domain × starting-level table, syllabus coverage, unplaced skills, link checks
and a selected skill's prerequisites and dependents. It does not show learner overlays or the
pairwise comparison tab; those stay in the legacy map and the syllabus editor's comparison dialog.

## Subject profiles

`GET /syllabus-comparison/subject-profiles` returns profiles, default, subjects in use and a
16-character `revision`. Writes require that `expected_revision`:

- `POST …/subject-profiles/save`: required `label` (≤ 100), `levels` (1–30 strings ≤ 60); optional
  `key` (≤ 100), `language` (≤ 20), `domains` (≤ 60 objects with `id` ≤ 40 and `label` ≤ 100),
  `ai` (only `role`, `context`, `level_hints`, each ≤ 2000).
- `POST …/subject-profiles/remove` and `…/default`: required `key`.

The model validates again (domain id format, uniqueness, language code). Save already ran under
the curriculum lock; remove and default now do too, so the revision check and write are atomic.
A changed revision returns 409 `ohmylms_subject_profile_conflict`; the screen keeps the form and
offers "Reload profiles and keep my edits". Saving creates or renames the profile's curriculum
subject; removing never deletes that subject or changes skills.

## Course bundles

| Route | Contract |
| --- | --- |
| `GET /bundles` | Bundles and limits. |
| `POST /bundles` | Required `title` 1–190, optional `description` ≤ 2000. 201 with detail. |
| `GET /bundles/:id` | `{bundle, members}`. |
| `PUT /bundles/:id` | `title`, `description`, `expected_updated_at`. |
| `PUT /bundles/:id/items` | Required `items` (≤ 100 objects with `type` `course`/`curriculum` and `id` ≥ 1), `expected_updated_at`. Members must exist and be unique; a published bundle cannot be emptied (409). |
| `POST /bundles/:id/publish` | Boolean `published` (default true), `expected_updated_at`. |
| `DELETE /bundles/:id` | Boolean `force` (needed when learners follow it), `expected_updated_at`. |
| `GET /bundles/targets` | Required `type` `course`/`curriculum`, `search` ≤ 200; at most 20 rows. |

Every bundle write now runs under the curriculum lock and compares `expected_updated_at` there
(409 `ohmylms_bundle_stale`), including publish and delete, which previously had no check. The
screen lists bundles, creates them, edits details, orders/adds/removes members (saved explicitly),
publishes/unpublishes and deletes with a follower warning. Stale saves keep local edits behind
"Reload bundle and keep my edits". Learner-facing bundle pages are unchanged.

## Compare syllabuses (`#/content-hub/curriculum/compare`)

`features/curriculum/ComparePage` with `?tab=groups|roadmaps|shared|audit`; the legacy
`?roadmap=<id>` bookmark redirects to that roadmap's syllabus editor. Routes are registered with
typed arguments in `ComparisonController::register_compare_routes()`; malformed input answers 400
(`rest_invalid_param` / `rest_missing_callback_param`) before any domain code runs. All except the
learner `GET /audit` require `Curriculum\Access::admin` (401 anonymous, 403 others).

| Route | Contract |
| --- | --- |
| `GET /syllabus-comparison/syllabuses` | `items[]` (now with `item_type` `syllabus`/`roadmap`), `selected` (subject => IDs) and a 16-character `revision`. |
| `PUT /syllabus-comparison/configuration` | Required `subject` 1–100, `syllabus_ids` (≤ 10 unique positive integers; existing syllabuses) and `expected_revision`. Compared inside the curriculum lock: 409 `ohmylms_comparison_stale` (`data.revision`), nothing written. An empty list removes the subject's group. |
| `GET /syllabus-comparison/shared-skills` | `search`, `subject` ≤ 100, `domain` ≤ 40, `grade` 0–100, `roadmap_id` ≥ 0, `page` 1–10000; 30 per page. |
| `GET /syllabus-comparison/shared-skills/:id` | Skill with `mappings` and a 64-character `revision`. |
| `POST /syllabus-comparison/shared-skills/save` | `id` ≥ 0, required `name` 1–190, `description` ≤ 2000, `topic`/`subject` ≤ 100, `domain` ≤ 40, `grade_from`/`grade_to` 0–100, `roadmap_id` (integer or null), `expected_revision` (64 hex; required by the service for updates: 409 `ohmylms_shared_stale`). |
| `POST /syllabus-comparison/roadmaps/preview` | Required `syllabus_ids` (1–10 unique). |
| `POST /syllabus-comparison/roadmaps` | Required `name` 1–190; `subject` ≤ 100, `grade` ≤ 50, `syllabus_ids` 1–10, `skill_ids` ≤ 200. |
| `GET /syllabus-comparison/roadmaps/:id` | Name, sources, ordered skills, folders, `revision`. |
| `PUT /syllabus-comparison/roadmaps/:id` | Required `name`, `syllabus_ids` (1–10), `skill_ids` (≤ 200), `expected_revision`; optional `folders` (objects `{id ≥ 0, name 1–190, skill_ids}`). Stale: 409 `ohmylms_roadmap_stale`. |
| `GET /syllabus-comparison/audit/:student_id` | One learner's subject audit (404 `ohmylms_student_missing`). `GET /syllabus-comparison/audit` stays the signed-in learner's own. |

The screen: **groups** lists subjects and their sources, saves with the loaded revision and on a
stale refusal offers "Reload groups and keep my edits"; **roadmaps** previews the shared skills of
chosen sources before creating a draft, and edits name, sources and step order (up, down, remove,
add from sources; folders are kept and new steps join the first) with the same keep-edits reload;
**shared skills** searches, shows the mapped objectives and edits against the revision; **audit**
shows one learner's shared mastery and per-syllabus coverage by user ID.

Not rebuilt: combined mapping groups (`/mapping-groups*`), AI runs and suggestions (`/ai/*`),
shared content links and domain/level placement stay in the legacy workspace and syllabus editor;
those routes are unchanged.

## Verification

`OHMYLMS_CURRICULUM_WORKSPACE_TEST=1` runs `tests/php/curriculum-workspace-rest-integration.php`
on the Local site: icon validation, delete strategy/confirmation/stale/promote/branch, editor
denial, skill map validation and legacy empty IDs, profile schema rejections without writes,
stale save/default, removal, bundle schema rejections, member validation, stale update/publish/
delete, published-bundle emptying and editor denial. Fixtures are tagged and removed in `finally`;
the subject profile option is restored. `features/curriculum/*.test.tsx` cover the screens.

`OHMYLMS_CURRICULUM_COMPARE_TEST=1` runs `tests/php/curriculum-compare-rest-integration.php`:
route registration, editor/subscriber/anonymous refusal, schema rejections, group save and stale
refusal without writes, group removal, shared skill search/detail/stale/save, roadmap preview,
creation, stale and current saves, and the learner audit. The comparison option is restored.
