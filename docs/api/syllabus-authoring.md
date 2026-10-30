# Authored syllabus editor

The typed preview opens at `admin.php?page=ohmylms-app#/content-hub/curriculum/syllabus/:id`.
Curriculum syllabus rows link to it. It owns skill-group fields and order, new/library skill
assignment, skill fields and placement order/moves, CSV review/apply, course creation/sync,
individual shared-skill mappings and pairwise syllabus comparison. It uses WordPress controls
and existing styles. The main admin and recovered runtime remain in place.

## Boundaries

- `assets/src/app/features/syllabus`: validated models, transport, page owner, group list,
  details/move/library dialogs, CSV review, mapping and comparison components.
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

`GET /ohmylms/v1/authoring/syllabuses/:id` returns `{syllabus, contents, course, ...}`.
Each content item has ordered `groups`; each group has ordered library `skills`. GET does not
create or synchronize a course. A non-syllabus/missing item returns an error.

`POST /ohmylms/v1/authoring/syllabuses/:id/commands` takes a JSON object with `operation` and
the fields below. It rejects unknown fields, wrong types and payloads above 5 MB before writing.
Names are nonempty strings up to 190 characters; skill codes up to 40, group codes up to 60,
notes up to 2000, IDs positive integers, positions 0–5000. Domain validation runs again.

| Operation | Fields |
| --- | --- |
| `add_group` | `item_id`, `name`, optional `code`, `description` |
| `edit_group` | `group_id`, `name`, optional `code`, `description` |
| `move_group` | `group_id`, destination `item_id`, `position` |
| `create_skill` | `group_id`, `name`, optional `code`, `description` |
| `edit_skill` | `term_id`, `name`, optional `code`, `description` |
| `attach_skill` | `group_id`, existing library `term_id` |
| `move_skill` | source `group_id`, `term_id`, destination group `destination`, `position` |
| `detach_skill` | `group_id`, `term_id` |
| `sync_course` | No additional fields |
| `import` | `rows` (1–5000 objects), boolean `dry_run`, reviewed `review_revision` when applying |

All routes require the existing curriculum administrator permission, enforced on the server.
Cookie requests use the shared nonce-aware WordPress transport. Domain services reject groups
or destinations outside this syllabus; a library skill may be placed once per syllabus.
Editing a skill changes its library record wherever used, as stated in the details dialog.
Detachment asks for confirmation and preserves the skill, content and learner evidence.

Successful mutations return the refreshed outline and attempt course-draft synchronization.
A synchronization failure is reported separately as `course_error`: the syllabus change remains
saved, and the explicit update-course action retries. Course creation is repeatable and reuses
the syllabus's associated course. These actions never publish a course or a program version.

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
stale review rejection, import apply, course draft mirroring and revision-checked mappings.
App tests cover parsers, CSV, details failure recovery, confirmation, cancellation and stale
mapping behavior. Current counts and live checks are recorded in the modularization roadmap.

This is not complete legacy workspace parity: mind map/spreadsheet layouts, group/branch
deletion, bulk/combined mapping editing, AI proposals, source configuration, rich skill
objectives/translations, skill-owned resources, course requirement controls, publication and
extension panels remain separate work. Simple group/skill field edits use the existing
field-write contract; they do not introduce a new optimistic revision protocol. No performance
improvement is claimed without measurement.
