# Curriculum and course structure authoring

The authored preview now offers `#/content-hub/curriculum`, `#/content-hub/courses`
and `#/content-hub/courses/:id`. These screens import TypeScript modules and shared
app services directly; they do not load recovered factories or runtime adapters.
The preview itself remains administrator-only. This is the curriculum hierarchy
and course-outline batch, not complete Curriculum, Course or Lesson editor parity.

## Ownership

| Boundary | Owner |
| --- | --- |
| Curriculum tree, create/edit, parent moves | `assets/src/app/features/curriculum` |
| Course list/create, units and lesson placements | `assets/src/app/features/course-structure` |
| Cross-feature course placement UI | Curriculum's public `CoursePlacement` export |
| Cancellation, response validation, unsaved navigation | `assets/src/app/hooks`, `assets/src/app/lib` |
| Bounded course summaries | `Learning/CourseCatalog`, `Rest/V1/CourseCatalogController` |
| Curriculum persistence and structural validation | Existing `Curriculum/Items`, `Placement`, `CurriculumController` |
| Outline validation, revision locks and transactions | Existing `CourseStructureController`, `Learning/CourseStructure`, `OutlineEditor`, `CourseProgram` |

## Course catalogue

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
| `GET courses/:id/structure/resources?type=lesson&search=...&page=...` | Bounded, editable lesson picker |

The structure client uses `structure_revision` when present, falling back to `revision`.
It sends that token in the body and `X-OhMyLMS-Structure-Revision` header. A 409 never
retries automatically or overwrites local edits. Reloading a dirty outline requires an
explicit discard decision. Unit UUIDs become persistent IDs on save. Existing activity
identities, outcomes, evidence settings, question-bank selections and other program fields
are carried through the client payload. The server remains authoritative about normalization.
In particular, the current backend deliberately flattens retired lesson folders: block lessons
belong directly to units. The new UI does not create those folders.

Authors can add/rename/reorder units and attach/reorder/detach reusable lessons within a unit.
Detachment removes a placement from the draft, never the shared lesson or learner history.
Existing quizzes, assignments, practice items and skills remain in the submitted program;
this batch does not provide their editing interfaces. Outline saves never send `activate`
or call a publish endpoint. Curriculum placement changes save immediately and independently
of the outline, as stated beside the controls.

## Verification and limits

- `tests/php/course-authoring-rest-integration.php`, enabled by `OHMYLMS_AUTHORING_TEST=1`,
  boots the Local WordPress site with `HTTP_HOST=800.local`. It creates tagged fixtures,
  checks save/reopen, placement, validation, stale revisions, draft-only behavior and denied
  users, then removes only its fixtures in `finally`. It does not reset the database.
- App tests cover transport validation, identity/data preservation, unit/lesson ordering,
  failed saves, explicit detachment, cancellation, duplicate submission prevention and
  capability-gated navigation. Existing membership guard tests exercise the shared guard.
- Live browser checks and final counts are recorded in the roadmap's current batch section.

Remaining: syllabus skill-group/CSV/comparison workflows, deleting curriculum branches or
units, moving lessons across units, new lesson content authoring, advanced course settings,
assessment authoring, publication UI and extension editor panels. Course placement currently
uses the existing replace-list contract; it has no new optimistic concurrency token. The shared
navigation guard covers link clicks and page unload; it does not block browser hash-history
navigation. No speed-up is claimed without measurements.
