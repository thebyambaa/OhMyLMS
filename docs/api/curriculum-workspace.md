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

## Comparison groups and roadmaps (not rebuilt)

`#/content-hub/curriculum/compare` beyond the pairwise dialog (combined mapping groups, AI runs
and suggestions, roadmap drafts) remains in the legacy workspace. Its routes
(`/syllabus-comparison/mapping-groups*`, `/roadmaps*`, `/ai/*`) are unchanged and were not
hardened in this batch; they still validate in their domain services only. Rebuilding them needs
a typed contract for mapping-group payloads and roadmap steps, revision checks for group saves,
and explicit confirmation for AI acceptance.

## Verification

`OHMYLMS_CURRICULUM_WORKSPACE_TEST=1` runs `tests/php/curriculum-workspace-rest-integration.php`
on the Local site: icon validation, delete strategy/confirmation/stale/promote/branch, editor
denial, skill map validation and legacy empty IDs, profile schema rejections without writes,
stale save/default, removal, bundle schema rejections, member validation, stale update/publish/
delete, published-bundle emptying and editor denial. Fixtures are tagged and removed in `finally`;
the subject profile option is restored. `features/curriculum/*.test.tsx` cover the screens.
