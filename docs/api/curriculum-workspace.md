# Curriculum workspace API

Typed screens in `admin.php?page=ohmylms-app` that replace legacy Content Hub curriculum screens.
Each screen's extension route is its legacy route. All routes are under `ohmylms/v1` and require
the curriculum administrator permission (`Curriculum\Access::admin`), enforced on the server,
except the read-only skill map and its overlay routes, which also serve scoped staff (see "Skill map"
and "Learner overlays").

| Screen | App route / extension route | Feature |
| --- | --- | --- |
| Curriculum (fields, icons, moves, deletion) | `/curriculum` (the old `/content-hub/curriculum` redirects) | `features/curriculum/CurriculumWorkspace` |
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

The screen shows a domain × starting-level table, syllabus coverage, unplaced skills, link checks,
a selected skill's prerequisites and dependents, and the learner overlay below. The pairwise
comparison tab stays in the syllabus editor's comparison dialog. The map's own `student_id`
argument (pooled `Performance\Guidance` levels) is kept for the MCP `get_skill_map` tool; the screen
uses the overlay routes instead.

**Who may read it.** Both routes (`ComparisonController::skill_map_permission()`) serve curriculum
administrators and the users the overlay serves (`Dashboard\SkillMapOverlay::can_view()`: site
administrators, and school administrators and teachers with a dashboard scope); others get 401/403
`ohmylms_skill_map_cannot_view` (503 `ohmylms_curriculum_unavailable` before the curriculum tables exist).
The map itself (shared skills, links, checks, syllabus coverage) is not learner data. A `student_id` must
be one of the caller's learners (`SkillMapOverlay::visible_learners()`): outside their scopes, or missing,
staff get the same 403 `ohmylms_skill_overlay_forbidden`; administrators keep 404
`ohmylms_skill_map_student` for a missing user. Every write of the map (skill links, mappings, placement,
subject profiles, the skill workspace) stays with curriculum administrators.

**Navigation.** The Curriculum page (`ohmylms-curriculum`) holds the Curriculum workspace tab, which stays `manage_options`, and
the Tools tab, whose `skill-map` section carries the staff capability `ohmylms_view_dashboard`. `AdminNavigation::visible_tabs()` shows school staff a site tab
through its staff sections only, so a teacher's Content Hub has Gradebook and the Curriculum page has a Tools tab holding
just **Skill map** (its default section; other `&section=` values fall back to it). The app feature
`curriculum-skill-map` requires `ohmylms_view_dashboard`; the other curriculum features keep
`manage_options`.

## Learner overlays on the skill map

The legacy map had a "Show a learner" search (any user, through `/wp/v2/users`) that coloured each
skill by that learner's level, added a legend and showed strength per domain; it needed a site
administrator. The rebuilt overlay also takes a class or school, follows the dashboard scopes, and
uses the five levels of the dashboard mastery heatmap (`Dashboard\Widgets\MasteryLevels` over
`student_skill_state`: Mastered from 95 %, Proficient from 80 %, Familiar from 50 %, Attempted
below that with evidence, Not started without; the `ohmylms_dashboard_mastery_thresholds` filter
applies).

Both routes are registered by `Rest\V1\SkillMapOverlayController`; the rules live in
`Dashboard\SkillMapOverlay` and the reads in `Dashboard\MasteryRepository` (`learner_percentages`,
`level_figures`). Permission: curriculum administrators (`Curriculum\Access::can_manage`, the
`ohmylms_can_manage_curriculum` filter) or users with dashboard access (`ohmylms_view_dashboard`:
site administrators, school administrators and teachers with a scope); others get 401/403
`ohmylms_skill_overlay_cannot_view`. Site and curriculum administrators see every learner; school
administrators and teachers only the learners of their scopes (`Dashboard\ScopeResolver`). Answers
carry `Cache-Control: private, no-store`.

| Route | Contract |
| --- | --- |
| `GET /skill-map/overlay` | Required `subject` (1–190) and `target`: `learner:<id>`, `class:<id>` or `school:<id>` (anything else, `all` included, is 400 `rest_invalid_param`). Returns `subject`, `target` (`key`, `type`, `id`, `label`, `school` for a class, `learners`, `counted`), `skills` (only skills with evidence: `id`, `percent`, `level`, `assessed`, `distribution` = learners per level), `counts` (skills of the map per level, highest first), `total`, `levels` (the heatmap legend: `id`, `label`, `min`), `links` and `limits.learners` (1000). |
| `GET /skill-map/overlay/targets` | Optional `search` (≤ 100). Returns `groups` (the user's school and class scopes, never `all`, matching the search case-insensitively: `key`, `type`, `label`, `school`), `groups_total`, `learners` (only when the search has at least `min_search` = 2 characters: `id` and display `name`, at most `limit` = 20, by name; staff only find learners of their scopes). |

- **Learner**: `percent` is the learner's rounded score, `level` its heatmap level and `distribution`
  holds one learner. A staff member asking for a learner outside their scopes, or for an ID that does
  not exist, gets the same 403 `ohmylms_skill_overlay_forbidden`; administrators get 404
  `ohmylms_skill_overlay_not_found` for a missing user.
- **Class or school**: resolved like the dashboard `scope` argument (403
  `ohmylms_dashboard_forbidden_scope` outside the user's scopes; 404 `ohmylms_dashboard_scope_not_found`
  for administrators). `percent` is the average of the counted learners with learners without
  evidence as 0, exactly as a heatmap cell; at most 1000 learners are counted (`counted` < `learners`).
- **Links** (app routes, or null when the user cannot open them): `transcript`
  (`/content-hub/gradebook/student?scope=…&learner=…`, in `all` for site administrators or the first
  scope holding the learner, for users who may open the Gradebook), `report`
  (`/students/<id>/report`, site administrators) and, for groups, `gradebook`
  (`/content-hub/gradebook/mastery?scope=…`).
- No e-mail addresses or logins are returned; administrators' learner search also matches e-mail.

The screen: a "Show learner mastery" toggle (nothing is requested until it is on), a search for
classes, schools and learners, then each skill shows a patterned swatch (solid, stripes, dots,
hatching, dashed outline, as on the heatmap) and its level and percentage in text; each domain
shows how many of its skills are Proficient or above; the legend counts skills per level; the
selected skill gives the learner's level or the group's learners per level; links open the
transcript, student report or gradebook mastery. The chosen target is in the address
(`&overlay=learner:12`). School administrators and teachers open the same screen; their targets and
learners are those of their scopes.

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

Combined mapping groups, AI runs and suggestions, domain/level placement,
and provenance review are Curriculum › Tools sections (`/content-hub/skills/<section>`);
this screen's former `?tab=mappings|ai|content|placement|provenance` open them. See
[skill workspace](skill-workspace.md). The
`/mapping-groups*`, `/ai/*` and `/shared-skills/:id/content` routes now have typed arguments.

## Verification

`OHMYLMS_CURRICULUM_WORKSPACE_TEST=1` runs `tests/php/curriculum-workspace-rest-integration.php`
on the Local site: icon validation, delete strategy/confirmation/stale/promote/branch, editor
denial, skill map validation and legacy empty IDs, profile schema rejections without writes,
stale save/default, removal, bundle schema rejections, member validation, stale update/publish/
delete, published-bundle emptying and editor denial. Fixtures are tagged and removed in `finally`;
the subject profile option is restored. `features/curriculum/*.test.tsx` cover the screens.

`OHMYLMS_SKILL_OVERLAY_TEST=1` runs `tests/php/skill-map-overlay-integration.php` (119 checks) on
the Local site: route registration and argument rejection, anonymous/learner refusal, learner and
class/school payloads (levels, rounding, rows without evidence, averages, distributions, counts,
legend, links), administrators, a curriculum administrator without scopes, school administrators
and teachers kept to their scopes (out-of-scope and missing learners answer alike), the target
search (scoped, names only, bounded), no e-mails, and the map page for staff: teachers, school
administrators and administrators read the subjects and the map, anonymous users and learners are
refused, a teacher's `student_id` limited to their class (another class and a missing learner answer
alike; administrators still get 404), the link and subject profile writes refused to a teacher, and the
navigation (a teacher's Curriculum Tools tab holds only the skill map and opens on it; learners get no tab;
administrators keep every section). Fixtures reuse `tests/php/support/mastery-fixture.php` and are
removed in `finally`. `features/curriculum/skill-map-overlay.test.ts`, `SkillMapPage.test.tsx` and
`WorkspaceScreens.test.tsx` (the map opened with only `ohmylms_view_dashboard`) cover the parser, the
overlay and the staff screen.

`OHMYLMS_CURRICULUM_COMPARE_TEST=1` runs `tests/php/curriculum-compare-rest-integration.php`:
route registration, editor/subscriber/anonymous refusal, schema rejections, group save and stale
refusal without writes, group removal, shared skill search/detail/stale/save, roadmap preview,
creation, stale and current saves, and the learner audit. The comparison option is restored.
