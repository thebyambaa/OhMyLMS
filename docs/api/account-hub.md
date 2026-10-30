# Account Hub REST API (`ohmylms/v1/account-hub`)

Contract version 1, first slice: schools, academic years, classes, class rosters, teachers and parents for the
new admin app (`#/account-hub`). Students stay on their own screen (`#/students`, [students.md](students.md));
the Student Home (hub) editor stays a PHP page. Server code: `includes/Rest/V1/AccountHubController.php`. Every
write goes through the existing services in `includes/Schools/` (`Service`, `ClassManagement`, `Access`), which
re-check relationship scope; the controller adds capability checks, validation, paging, minimal fields and
stale-save detection. The legacy `ohmylms/v1/school/*` routes are unchanged and still serve the hub workspaces.

## Audit before

Storage is additive tables (`ohmylms_schools`, `school_memberships`, `academic_years`, `classes`,
`class_memberships`, `guardian_links`, `school_invitations`, `school_student_profiles`, `school_audit_log`, …;
schema option `ohmylms_school_schema`). Roles `ohmylms_school_admin`, `ohmylms_teacher` and `ohmylms_parent` carry
only `read`; scope always comes from membership rows (`Access`). Who may manage what today: platform
administrators (`manage_options`) everything; an active `school_admin` membership its school; a teacher the
classes they teach (roster of those classes, assignments); a parent the children of active guardian links.

| Severity | Finding (legacy `ohmylms/v1/school/*`) |
| --- | --- |
| High | **No capability boundary at the REST layer.** One permission callback for every route is `is_user_logged_in()`; authorization happens inside each service by throwing. Any subscriber reaches the dispatcher, and every failure is the same `ohmylms_school_error` (403/400/404/409/500) with an untranslated service message. |
| High | **PII in the account directory.** `GET /school/users` returns email, `user_login`, registration date, last login, avatar, ban flag, roles and edit URL for every account to anyone with `list_users`; the teacher/parent filter first loads every matching id (`number => -1`). The legacy Teachers/Parents tabs downloaded every page and searched client-side. |
| Medium | **Roster fields.** School and class rosters return `user_login` and the school student ID to staff, including teachers of a class. |
| Medium | **No totals.** Lists are fixed `LIMIT 50 OFFSET` without `X-WP-Total`; the admin UI looped until a short page, and loaded classes school by school (N+1). The class list runs a correlated audit-log subquery per row for `created_at`. |
| Medium | **Missing edits.** `Service::update_school` and `ClassManagement::edit` exist (hub actions use them) but have no REST route; no record carries `updated_at`, so concurrent edits silently overwrite each other. |
| Medium | **School creation.** `POST /school/schools` also lets any teacher create one school and become its administrator (a hub feature). |
| Low | Removing a member, archiving a class and revoking a guardian link set `inactive`/`archived`; nothing is deleted (kept). There is no school archive or delete in the code base; `Access::school()` treats a non-active school as missing even for administrators. |
| OK | Prepared SQL with `%i`; relationship checks read current rows; cookie requests need the core REST nonce (register/accept check it explicitly); invitation tokens are 64-hex, stored as SHA-256, single use, 48 hours; **guardian links are created only when the invited, signed-in parent accepts** (never from a name search). `school/view-as/*` requires the nonce and `manage_options` and is not part of this API. |

## Permissions and scope

- Anonymous: `401`. Logged in without `manage_options` and without an active `school_admin` membership in an
  active school (subscribers, students, teachers, parents): `403`. Codes `ohmylms_account_hub_cannot_view`
  (reads) and `_cannot_edit` (writes).
- Platform administrators see and manage every school, class, teacher and parent, including independent
  (school-less) classes. Only they may create schools here (`_cannot_create`); no one becomes the new school's
  administrator implicitly.
- School administrators see and manage only their schools; anything else answers `403
  ohmylms_account_hub_forbidden`. Lists are filtered in SQL. Independent classes are hidden from them.
- Unknown ids answer `404` (`_school_not_found`, `_class_not_found`, `_parent_not_found`, …) after the
  permission callback, so anonymous and forbidden callers never learn whether an id exists.
- The admin screens require `manage_options` (the hub keeps school staff out of wp-admin); the school
  administrator scope serves the API and future hub reuse.

## Routes

All paths are below `/wp-json/ohmylms/v1/account-hub`. Lists take `page` (>= 1), `per_page` (1-100, default 20)
and `search` (name; teachers and parents also match email for `list_users` users), answer `X-WP-Total` and
`X-WP-TotalPages`, and answer `400 ohmylms_account_hub_invalid_page` past the last page.

| Method | Path | Purpose | Success |
| --- | --- | --- | --- |
| GET | `/schools` | Schools in scope, by name | 200 + totals |
| POST | `/schools` | Create (`name` required, `timezone` default `UTC`); administrators only | 201 + `Location` |
| GET | `/schools/{id}` | School with `years` | 200 |
| PUT / PATCH / POST | `/schools/{id}` | Partial update of `name`, `timezone`; send `version` | 200 |
| POST | `/schools/{id}/years` | Add an academic year (`label`, `starts_at`, `ends_at`) | 201, school with years |
| GET | `/schools/{id}/classes` | The school's classes (`status=all|active|archived`) | 200 + totals |
| POST | `/schools/{id}/classes` | Create (`name`, `academic_year_id` of an active year, `subject`, `grade`) | 201 + `Location` |
| GET | `/schools/{id}/students` | Roster students (`id`, `display_name`), for pickers | 200 + totals |
| GET | `/classes` | Classes in scope (`status`, `school_id`; `0` = independent) | 200 + totals |
| GET | `/classes/{id}` | One class | 200 |
| PUT / PATCH / POST | `/classes/{id}` | Partial update of `name`, `subject`, `grade`; send `version` | 200 |
| POST | `/classes/{id}/archive`, `/restore` | Archive (never delete) or restore (needs an active year) | 200 |
| GET | `/classes/{id}/members` | Active members, teachers first (`role` filter) | 200 + totals |
| POST | `/classes/{id}/members` | Add `{ user_id, role }`; the person must be on the school roster in that role | 201 |
| DELETE | `/classes/{id}/members/{user_id}?role=` | Remove (membership becomes inactive) | 200 `{ removed: true }` |
| GET | `/classes/{id}/candidates?role=` | Roster people not yet in the class (search) | 200 + totals |
| GET | `/teachers` | Teacher-role accounts and school teachers (`school_id` filter) | 200 + totals |
| GET | `/parents` | Parent-role accounts and active guardians (`school_id` filter) | 200 + totals |
| GET | `/parents/{id}/children` | `{ children, pending }` within scope (max 100 each) | 200 |
| POST | `/parents/{id}/children` | Invite the parent to link `{ school_id, student_id, send_email? }` | 201 invitation |
| DELETE | `/parents/{id}/children/{link}` | Revoke an active link (becomes inactive) | 200 `{ revoked: true }` |
| DELETE | `/parents/{id}/invitations/{invitation}` | Revoke a pending guardian invitation | 200 `{ revoked: true }` |

## Fields (PII minimised)

| Resource | Fields |
| --- | --- |
| School | `id, name, slug, timezone, status, created_at, class_count, student_count, teacher_count, version` (+ `years[] {id, label, starts_at, ends_at, status}` on one school) |
| Class | `id, name, subject, grade, status, school_id, school_name, academic_year_id, academic_year_label, student_count, teacher_count, version` |
| Member | `id` (membership), `user_id, display_name, role, joined_at` |
| Roster person / candidate | `id, display_name` |
| Teacher | `id, display_name, email, schools[] {id, name}, class_count` |
| Parent | `id, display_name, email, child_count` |
| Child link / pending | `id, school_id, school_name, student_id, student_name, linked_at` / `expires_at` |

`email` is `null` unless the caller has `list_users` (the legacy directories showed email only to them). No
list returns logins, last-login times, avatars, school student IDs, roles of other accounts or edit URLs. Times
are UTC ISO 8601 (`2026-10-30T09:15:00Z`).

## Stale saves

Schools and classes have no `updated_at` column, so each record carries an opaque `version` (16 hex
characters derived from its editable fields and status). Send the loaded `version` with an update; the row is
locked (`SELECT … FOR UPDATE` inside `OhMyLMS\Utility\Transaction`) and a different stored version answers
`409 ohmylms_account_hub_conflict` with `data.version`, writing nothing. Omitting `version` saves
unconditionally (scripts). Archived classes are read only (`409 _class_archived`).

## Parent-child linking

Linking keeps the existing rule that a guardian link is never made from a name search alone.
`POST /parents/{id}/children` creates a school-scoped, single-use guardian invitation addressed to that parent
account's own email (`Service::invite`, role `guardian`) and returns its URL once (`url`, `expires_at`,
`email_sent`; mail only with `send_email: true`). The link becomes active when the parent signs in with that
account and accepts it (`Service::accept`), and then appears under `children`. Until then it is listed under
`pending` and can be revoked. One pending invitation per parent and student (`409 _invitation_pending`); an
existing link answers `409 _already_linked`; the student must be on the school's roster (`422 _not_on_roster`).

## Examples

```http
PATCH /wp-json/ohmylms/v1/account-hub/schools/3
X-WP-Nonce: <wp_rest nonce>
Content-Type: application/json

{ "name": "North Academy", "version": "0123456789abcdef" }
```

```json
{ "code": "ohmylms_account_hub_conflict",
  "message": "This school was changed by someone else. Reload it before saving.",
  "data": { "status": 409, "version": "9f2c1d0e7a6b5c4d" } }
```

```http
POST /wp-json/ohmylms/v1/account-hub/classes/9/members
{ "user_id": 21, "role": "student" }

HTTP/1.1 201 Created
{ "id": 40, "user_id": 21, "display_name": "Ana Student", "role": "student", "joined_at": "2026-10-30T09:20:00Z" }
```

## Error codes

| HTTP | Code | When |
| --- | --- | --- |
| 400 | `rest_invalid_param`, `rest_missing_callback_param` | Schema validation (types, lengths, enums, `version` format, `per_page` > 100). |
| 400 | `ohmylms_account_hub_invalid_page` | Page past the end. |
| 401 / 403 | `ohmylms_account_hub_cannot_view` / `_cannot_edit` / `_cannot_create` | Not logged in, or not an administrator / school administrator. |
| 403 | `ohmylms_account_hub_forbidden` | A school administrator outside their schools. |
| 404 | `_school_not_found`, `_class_not_found`, `_parent_not_found`, `_member_not_found`, `_link_not_found`, `_invitation_not_found` | Unknown or out-of-relationship ids. |
| 409 | `_conflict`, `_class_archived`, `_class_active`, `_already_member`, `_already_linked`, `_invitation_pending`, `_duplicate` | Stale save or state conflict. |
| 422 | `_invalid_name`, `_invalid_timezone`, `_invalid_dates`, `_invalid_year`, `_not_on_roster`, `_independent_class`, `_parent_without_email`, `_rejected` | Validation and roster rules (`_rejected` carries a service message). |
| 500 | `_failed` | Database failure; nothing was changed. |

## UI

`assets/src/app/features/account-hub`: `#/account-hub` (tabs Schools, Classes, Teachers, Parents via `?tab=`, and
a link to `#/students`), `#/account-hub/schools/<id>` (details, academic years, the school's classes, add class)
and `#/account-hub/classes/<id>` (details, archive/restore, roster, add students or teachers by searching the
school roster). Extensions see these screens as the original `/accounthub` route.

## Not in this slice

School roster management (invitations, managed students, CSV import, removal from a school), assignments,
reports, gradebook, rollover, recovery links, View as and sample preview stay in the legacy Account Hub
(`#/accounthub`) and the hub School workspace. Schools cannot be archived (the code base has no school
archive). Adding students to an independent class stays the teacher's invitation link.

## Tests

`tests/php/account-hub-rest-integration.php` (set `OHMYLMS_ACCOUNT_HUB_TEST=1`; see the file header) and
`npx vitest run --configLoader runner features/account-hub`.
