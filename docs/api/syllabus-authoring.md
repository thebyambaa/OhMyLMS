# Authored syllabus editor

Product intent, entity relationships and current-versus-retained behavior: [Learning Architecture](../LEARNING-ARCHITECTURE.md).

The typed preview opens at `admin.php?page=ohmylms-app#/content-hub/curriculum/syllabus/:id`.
Curriculum syllabus rows link to it. It owns skill-group fields, order and deletion, new/library skill
assignment, skill fields and placement order/moves, the source details and profile, CSV review/apply,
mirrored course creation/sync, its skill requirements and reviewed publication, individual shared-skill mappings, pairwise
syllabus comparison, and the entry to [independent course generation](../COURSE-FROM-SYLLABUS.md#screen). It uses WordPress controls
and existing styles. The recovered runtime it was built beside was deleted at the cutover ([CUTOVER.md](../CUTOVER.md)).

## Boundaries

- `assets/src/app/features/syllabus`: validated models, transport, page owner, group list,
  details/move/library/source dialogs, category styles, requirement controls, CSV review, mapping
  and comparison components, and the course-from-syllabus workflow (`generation.ts`,
  `CourseFromSyllabusDialog`), which `public.ts` exports for the course list. Publication reuses
  `course-structure/public.ts`'s `PublicationPanel`.
- `hooks/useOperation`: one owned mutation at a time, cancellation on unmount and late-response
  suppression. The feature also uses the shared request and unsaved-details hooks.
- Curriculum publicly exports its tree reader; Skills publicly exports its bounded library
  search. The new feature does not reach into another feature's private components.
- `assets/src/features/curriculum/csv.mjs` remains the single pure CSV parser/mapping implementation.
  `csv.d.mts` supplies its TypeScript boundary. The new wrapper enforces file/row/column bounds and
  incomplete-quote checks. No recovered UI, numeric factory or `readRuntime()` is imported.
- `Rest/V1/SyllabusEditorController` validates commands and delegates to `Curriculum/Syllabus`
  and `SyllabusCourse`. Existing public REST paths and extension hooks are retained.

## API

`GET /ohmylms/v1/authoring/syllabuses/:id` returns `{syllabus, contents, course, settings,
requirements, roadmap_sources, ...}`. `syllabus` carries the item's `code`, `version`,
`description` and `item_type`; `settings` is the profile (`subject`, `grade`, `language`,
`categories`); `requirements` lists `{term_id, required, target}` for each syllabus skill that is an
outcome of the mirrored course draft (empty without a course); `course` includes its learning
`mode`. Each content item has ordered `groups`; each group has ordered library `skills`. GET does
not create or synchronize a course. A non-syllabus/missing item returns an error.

`POST /ohmylms/v1/authoring/syllabuses/:id/commands` takes a JSON object with `operation` and
the fields below. It rejects unknown fields, wrong types and payloads above 5 MB before writing.
Names are nonempty strings up to 190 characters; skill codes up to 40, group codes up to 60,
notes up to 2000, IDs positive integers, positions 0–5000. Domain validation runs again.

| Operation | Fields |
| --- | --- |
| `add_group` | `item_id`, `name`, optional `code`, `description` |
| `edit_group` | `group_id`, `name`, optional `code`, `description` |
| `move_group` | `group_id`, destination `item_id`, `position` |
| `delete_group` | `group_id`, boolean `confirm` (required when the group holds skills; otherwise 409 `ohmylms_syllabus_needs_confirmation` with the skill count) |
| `edit_source` | Any of `name`, `code` (≤ 60), `version` (≤ 60), `description`; profile `subject`, `grade`, `language` (≤ 100 each), `categories` (≤ 50 strings ≤ 60), `category_styles` (≤ 50 entries `category: {icon?, color?}`, colour `#rrggbb`) |
| `set_requirements` | `skills` (1–5000 objects: `term_id`, optional boolean `required`, optional `target` `proficient`/`mastered`) |
| `create_skill` | `group_id`, `name`, optional `code`, `description` |
| `edit_skill` | `term_id`, `name`, optional `code`, `description` |
| `attach_skill` | `group_id`, existing library `term_id` |
| `move_skill` | source `group_id`, `term_id`, destination group `destination`, `position` |
| `detach_skill` | `group_id`, `term_id` |
| `sync_course` | No additional fields |
| `import` | `rows` (1–5000 objects), boolean `dry_run`, reviewed `review_revision` when applying |
| `sheet_edit` | `changes` (1–500 objects: `kind` `item`/`group`/`skill`, `id`, `field` `name`/`code`, `previous`, `value`, each text ≤ 400) |

All routes require the existing curriculum administrator permission, enforced on the server.
Cookie requests use the shared nonce-aware WordPress transport. Domain services reject groups
or destinations outside this syllabus; a library skill may be placed once per syllabus.
Editing a skill changes its library record wherever used, as stated in the details dialog.
Detachment asks for confirmation and preserves the skill, content and learner evidence.

Deleting a group always asks first. The dialog states how many skills the group holds and that
they stay in the skill library with their questions, lessons and evidence; only their placements
are removed. It sends `confirm` only for a group it saw holding skills, so a group that gained
skills meanwhile is refused with the server's count. The mirrored course drops the group's unit
(unless the course still uses it) and its outcomes on the next synchronization.

**Source and profile** edits the syllabus's name, code, version/edition and description, and the
profile stored with the syllabus course (subject, grade/level, language, skill categories one per
line). Only changed fields are sent. `edit_source` saves both under the curriculum lock in one
transaction: a refused profile change (no course, 404; a category still used by skills, 409)
leaves the source fields unchanged. Without a course the profile fields are read-only. For a
roadmap the dialog lists its sources read-only and links to Compare, where they are edited.

Each listed category has an icon (from `assets/src/features/curriculum/icons.json`, the catalogue
`Curriculum\Icons` validates; empty uses the default) and a colour (text field plus native picker).
**Use the default style** removes a category's entry. Styles for categories no longer listed are
dropped, invalid colours block saving, and the changed map is sent whole as `category_styles`.
`SyllabusSettings::clean` validates it again (unknown icon or colour 400, a style for a category
that is not listed 400 `ohmylms_syllabus_category_unknown`) and stores colours in lowercase,
defaulting a missing colour.

**Course requirements.** A mirrored course cannot publish without a completion requirement
(skill-based and blended courses need a required skill). With a course, each skill shows
**Required for the course** and **Target** (Proficient/Mastered); each group shows its required
count with **Require every skill in this group** / **Make every skill in this group optional**;
the page shows the total and warns while nothing is required. `set_requirements` rejects skills
not placed in this syllabus (400) and needs the course (404). It delegates to
`SyllabusCourse::set_requirements`, which changes only the requirement and target in the draft;
synchronization keeps them.

**Publishing the mirrored course.** With a course, the page states whether a program is published
and renders the course editor's `PublicationPanel` (lazily, through `course-structure/public.ts`).
It previews the saved draft with `GET /authoring/courses/{course}/publication`, which lists the
course's readiness issues (including the syllabus rules: at least one skill, a completion
requirement, a required skill outside traditional mode, and questions for required skills), and
publishes the reviewed revision with `POST …/publication` (`manage_options` and the course
`publish_posts` capability; a stale revision is 409). Publication is held while `course_error`
reports a failed synchronization; after publishing the page rereads the syllabus. The older
`…/syllabus/publish` route has no typed caller.

**Extensions.** Each skill renders the `syllabus.skill.resources` slot with `{skill, syllabus}`
context ([extensions](../EXTENSIONS.md)).

Successful mutations return the refreshed outline and attempt course-draft synchronization.
A synchronization failure is reported separately as `course_error`: the syllabus change remains
saved, and the explicit update-course action retries. Course creation is repeatable and reuses
the syllabus's associated course. These commands never publish a course or a program version;
publication is the separate reviewed action below.

## CSV review

An administrator chooses UTF-8 CSV/TSV or pastes CSV text. Comma, semicolon and tab delimiters,
quoted cells/newlines, column mapping, optional headers and optional topic/group fill-down are
supported. Input is limited to 5 MB, 5000 data rows and 50 columns. The Skill role must occur
once; duplicate mapped roles are rejected.

Review sends `dry_run: true`; its report contains row errors/warnings, planned create/update
counts and a `review_revision`. No import is applied at that step. Changing source, mapping,
header handling or fill-down invalidates the review. Apply sends the frozen reviewed rows and
revision. Under the curriculum advisory lock, the server checks a hash of the current outline
and those rows, then delegates to the existing transactional importer. A changed syllabus or
row set returns 409 and requires review again. The importer still replans and validates before
writing. Unrelated third-party writers are not made transactional by this boundary.

An invalid apply or transport error retains the text/mapping for correction. Closing a prepared
import requires an explicit discard. An applied import refreshes the editor from the server.

## Retired layouts

The syllabus editor is one outline/detail workspace, as [Learning Architecture](../LEARNING-ARCHITECTURE.md) describes. The mind-map and spreadsheet layouts were removed from `SyllabusPage.tsx` on 1 November 2026; an old `?layout=mindmap` or `?layout=spreadsheet` address opens the outline. The `sheet_edit` command remains documented above for compatibility and API callers; no app screen sends it.

## Mapping and comparison

The mapping dialog reads only the selected objective's mappings and uses the existing bounded
shared-skill search (30 per page). It supports individual equivalent, broader, narrower and
related mappings, with notes. Every save/removal requires a separate confirmation and sends
the read `expected_revision` to the existing comparison endpoints. A stale mapping is not
retried automatically; explicit reload keeps the entered note. Combined coverage mappings
are shown read-only and remain managed as a complete group in the existing comparison workspace.

Pairwise comparison reads `/syllabus-comparison/compare?a=:id&b=:other` and shows shared mapped
coverage, first-only/second-only coverage, unmapped counts and objective labels. It uses the
server's existing relationship semantics; related-only mappings do not count as coverage.
Overlap is coverage overlap, not a learner score. No learner mastery/completion writes occur.

## Verification and remaining scope

`OHMYLMS_SYLLABUS_EDITOR_TEST=1` enables `tests/php/syllabus-editor-rest-integration.php`
on the Local installation. Tagged users, syllabuses, mirrored courses/chapters and terms are
removed in `finally`; no database reset occurs. Checks cover denied users, input validation,
group/skill ordering, library assignment, cross-syllabus move denial, preview without writes,
stale review rejection, import apply, course draft mirroring and revision-checked mappings,
requirement/target changes (schema and foreign-skill rejection, draft-only writes, kept across
synchronization, missing course), source and profile saves (validation, cleaning, course rename,
used-category refusal without partial writes, missing course), category styles (invalid icon,
colour or shape, unknown category, lowercase/default colours, clearing), group deletion
(confirmation, cross-syllabus denial, library skills kept, outcomes removed, empty groups) and the
mirrored course's publication (readiness reporting the missing required skill, stale revision 409,
refusal while not ready, authors denied). On 2 November 2026 it passed 97 checks. App tests cover
parsers, CSV, details failure recovery, confirmation, cancellation, stale mapping behavior, group
deletion, requirement controls, the source dialog and category styles, the publication panel
wiring, the extension slot and the course-from-syllabus workflow.

The retained `sheet_edit` command is checked in `tests/php/skill-workspace-rest-integration.php`; combined mappings and AI
proposals are in the [skill workspace](skill-workspace.md). Curriculum branch deletion is in
the typed Curriculum page and rich objectives, translations and resource counts are in the Skills
editor; see [curriculum workspace](curriculum-workspace.md) and [skills](skills.md).
The syllabus's course is created through `POST /courses` with `title`, as that route now requires. Simple group/skill field edits use the existing
field-write contract; they do not introduce a new optimistic revision protocol. No performance
improvement is claimed without measurement.
