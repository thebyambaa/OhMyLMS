# Curriculum and course structure authoring

The authored preview offers `#/content-hub/curriculum` and one course editor at
`#/courses` (list) and `#/courses/:id/:tab?` with the tabs Details (metadata and
thumbnail), Outline (units and activity placements), Settings (pricing, access,
availability, capacity, certificate) and Publish (reviewed publication). Every course
editor route reports the extension route `/course-edit/:id/:step?/:subStep?`, so editor
panels registered for the legacy builder render on all tabs. The old
`#/content-hub/courses`, `#/content-hub/courses/:id` and `#/content-hub/courses/:id/settings`
links redirect (React Router `Navigate`, replace) to `#/courses`, `#/courses/:id/outline`
and `#/courses/:id/settings`. These screens import TypeScript modules and shared app
services directly; they do not load recovered factories or runtime adapters.

## Merged course editor

| Tab | Source | Server contract |
| --- | --- | --- |
| Details | `features/courses/CourseForm` (`section="details"`) | `PUT /courses/:id` ([courses.md](courses.md)) |
| Outline | `features/course-structure` `CourseOutline`, loaded lazily through its `public.ts` | `GET/PUT /courses/:id/structure`, unit delete below |
| Settings | `CourseForm` (`section="settings"`) and `CertificatePanel` | `PUT /courses/:id`; `GET/PUT /authoring/courses/:id/certificate` ([course-settings.md](course-settings.md)) |
| Publish | `features/course-structure` `PublicationPanel` | `GET/POST /authoring/courses/:id/publication` |

Details and Settings save the whole course; validation errors for fields of the other tab
are listed in the error summary. Publish and the certificate panel require
`manage_options` (as their endpoints do); authors see Details, Outline and Settings.
Switching tabs with unsaved edits uses the shared unsaved-changes confirmation.

## Ownership

| Boundary | Owner |
| --- | --- |
| Curriculum tree, create/edit, parent moves | `assets/src/app/features/curriculum` |
| Course list/create, editor shell, details and settings forms | `assets/src/app/features/courses` |
| Units, activity placements, publication panel, old-route redirects | `assets/src/app/features/course-structure` |
| Cross-feature course placement UI | Curriculum's public `CoursePlacement` export |
| Cancellation, response validation, unsaved navigation | `assets/src/app/hooks`, `assets/src/app/lib` |
| Bounded course summaries | `Learning/CourseCatalog`, `Rest/V1/CourseCatalogController` |
| Curriculum persistence and structural validation | Existing `Curriculum/Items`, `Placement`, `CurriculumController` |
| Outline validation, revision locks and transactions | Existing `CourseStructureController`, `Learning/CourseStructure`, `OutlineEditor`, `CourseProgram` |

## Course catalogue

The merged `#/courses` list uses the courses API ([courses.md](courses.md)). The bounded
authoring catalogue below remains available to API clients and is no longer used by a screen.

`GET /ohmylms/v1/authoring/courses?search=...&page=1` accepts at most 100 search
characters and a positive page up to 100000. Page size is fixed at 20. Returns
`{items: [{id, title, status}], total, pages}`. The query uses stable title/ID ordering.
Authors without `edit_others_posts` see their own courses; every returned row is
also checked with `Catalog::can_edit`. No student, pricing or payment data is included.

`POST /ohmylms/v1/authoring/courses` takes exactly `{title: string}` (1–190 characters,
nonblank after sanitization). It returns a summary with HTTP 201. The current user owns
the new draft; callers cannot set publication, author, price or arbitrary metadata.
Both routes require the registered course type's `create_posts` capability. Cookie
authentication uses WordPress's REST nonce middleware; the server checks permission
independently of navigation visibility.

## Reused curriculum and structure contracts

| Route | Behavior in the new app |
| --- | --- |
| `GET curriculum/tree` | Flat hierarchy with names, types, syllabus flags and associated course IDs; existing storage caps at 5000 items and depth 10 |
| `POST curriculum/items` | Create a named item under the chosen parent; optional syllabus/code/version/description |
| `PUT curriculum/items/:id` | Edit fields with the read `expected_updated_at` token; parent moves are a separate operation |
| `POST curriculum/items/:id/move` | Move a saved item; excludes descendants from UI choices and enforces cycle/depth rules on the server |
| `GET/PUT courses/:id/organization` | Read or replace curriculum placement, without sending bundle changes |
| `GET courses/:id/structure` | Complete authorized outline snapshot and read revision |
| `PUT courses/:id/structure` | Complete units/program draft plus original read token, in the existing named lock and transaction |
| `GET courses/:id/structure/resources?type=lesson\|quiz\|assignment&search=...&page=...` | Bounded picker of editable resources not yet placed in the saved draft |
| `DELETE courses/:id/structure/units/:unit` | Delete one saved unit with the read token (header or `revision`); returns the fresh outline |
| `POST quizzes`, `POST assignments` | Existing APIs; `{title}` creates a draft that the outline then places |

### Deleting units

The unit delete runs in the same named lock, revision check and transaction as outline
saves (`CourseStructureController` dispatch, `CourseStructure::change`) and delegates to
`Catalog::delete_chapter`. That domain rule refuses a unit that still holds native
(legacy builder) placements (409 `ohmylms_chapter_in_use`) or that the published program
uses (409 `ohmylms_chapter_published`). Otherwise draft activities and skill targets of the
unit stay in the draft program as unplaced items (`chapter_id: 0`), its lesson groups are
removed, and the unit post is deleted. Lessons, quizzes, assignments and learner history
are never deleted. A stale token answers 409 `ohmylms_program_stale`; a failure after the
first write rolls back the whole delete. The UI confirms first, requires a saved outline
for saved units, and removes never-saved units locally (their items become unplaced).

### Activities

Authors attach lessons and add quizzes or assignments to a unit, either by picking an
existing editable resource or by creating a named draft through the quiz or assignment API.
Creation happens immediately; the placement is saved only with the outline draft.
Activities of every type can be reordered within a unit and moved to another unit; a move
keeps the placement identity and skills, drops the lesson-group link (groups belong to one
unit) and inserts the activity before the target unit's terminal assessment.

The structure client uses `structure_revision` when present, falling back to `revision`.
It sends that token in the body and `X-OhMyLMS-Structure-Revision` header. A 409 never
retries automatically or overwrites local edits. Reloading a dirty outline requires an
explicit discard decision. Unit UUIDs become persistent IDs on save. Existing activity
identities, outcomes, evidence settings, question-bank selections and other program fields
are carried through the client payload. The server remains authoritative about normalization.
In particular, the current backend deliberately flattens retired lesson folders: block lessons
belong directly to units. The new UI does not create those folders.

Authors can add/rename/reorder/delete units and place, reorder, move and detach lessons,
quizzes and assignments. Detachment removes a placement from the draft, never the shared
resource or learner history. Practice items and skills remain in the submitted program.
Quiz and assignment content is edited in their own editors, linked from each placement.
Outline saves never send `activate`
or call a publish endpoint. Curriculum placement changes save immediately and independently
of the outline, as stated beside the controls.

## Verification and limits

- `tests/php/course-authoring-rest-integration.php`, enabled by `OHMYLMS_AUTHORING_TEST=1`,
  boots the Local WordPress site with `HTTP_HOST=800.local`. It creates tagged fixtures,
  checks save/reopen, placement, validation, stale revisions, draft-only behavior and denied
  users, quiz/assignment placements, cross-unit moves and reordering, and unit deletion
  (stale token, refusal while in use, rollback after a forced failure, unplaced activities,
  denied author), then removes only its fixtures in `finally`. It does not reset the database.
- App tests cover transport validation, identity/data preservation, unit/lesson ordering,
  failed saves, explicit detachment, cancellation, duplicate submission prevention and
  capability-gated navigation. Existing membership guard tests exercise the shared guard.
- Live browser checks and final counts are recorded in the roadmap's current batch section.

The follow-up [syllabus authoring batch](syllabus-authoring.md) now provides skill groups,
CSV review/apply and individual mapping/comparison in the typed preview.

The follow-up [course settings batch](course-settings.md) adds metadata, pricing/access,
learning mode/mastery controls, readiness preview and confirmed publication. Routine
outline saves remain draft-only.

Remaining: full syllabus workspace parity, deleting curriculum branches, drag-and-drop
ordering (buttons and selects are used), lesson groups in the outline UI, and assessment
authoring inside the course editor. Course placement currently
uses the existing replace-list contract; it has no new optimistic concurrency token. The shared
navigation guard covers link clicks and page unload; it does not block browser hash-history
navigation. No speed-up is claimed without measurements.

The [typed lesson editor](lesson-authoring.md) now creates/edits built-in lessons,
block content and resource links. Unit placements link directly to it; creation and
content saving remain independent of course placement and program publication.
