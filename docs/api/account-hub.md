# Account Hub REST API (`ohmylms/v1/account-hub`)

Contract version 1, first slice: schools, academic years, classes, class rosters, teachers and parents for the
new admin app (`#/account-hub`). Students stay on their own screen (`#/students`, [students.md](students.md));
the Student Home (hub) editor stays a PHP page. Server code: `includes/Rest/V1/AccountHubController.php`. Every
write goes through the existing services in `includes/Schools/` (`Service`, `ClassManagement`, `Access`), which
re-check relationship scope; the controller adds capability checks, validation, paging, minimal fields and
stale-save detection. The legacy `ohmylms/v1/school/*` routes were retired on 31 October 2026 (see "Retired legacy
routes"); only `school/view-as/*` remains.

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
| GET | `/schools` | Schools in scope, by name (`status=all|active|archived`) | 200 + totals |
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
| School | `id, name, slug, timezone, status, created_at, class_count, student_count, teacher_count, sample, version` (+ `years[] {id, label, starts_at, ends_at, status}` on one school; `sample` is true for the removable sample school) |
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

## Roster intake

Server code: `includes/Rest/V1/AccountHubRosterController.php` (extends the Account Hub controller, same
permissions and scope). Writes go through `Service::invite`, `Service::revoke_invite`, `Service::create_student`
and `Lifecycle::recovery`, which re-check the school relationship. Writes into a school that is not active answer
`409 _school_inactive`. Responses that carry links or sign-in names send `Cache-Control: private, no-store`.

| Method | Path (below `/schools/{id}`) | Purpose | Success |
| --- | --- | --- | --- |
| GET | `/invitations` | Invitations, newest first (`status=pending` default, or `all`; `search` matches the email) | 200 + totals |
| POST | `/invitations` | `{ role: school_admin\|teacher\|student, email, class_id?, send_email? }` | 201 `{ id, role, url, expires_at, email_sent }` |
| DELETE | `/invitations/{invitation}` | Revoke an open invitation (kept as `revoked`) | 200 `{ revoked: true }` |
| GET | `/managed-students` | School-managed accounts (no email) with an active student membership; `search` matches name or school student ID | 200 + totals |
| POST | `/managed-students` | `{ name (1-190), external_student_id (1-100) }` | 201 student |
| POST | `/managed-students/{user_id}/setup-link` | New password-setup link; earlier unused links are revoked | 201 `{ id, student_id, url, expires_at }` |
| POST | `/roster-import` | `{ csv (≤ 100 000 bytes), dry_run (default true), review?, send_email? }` | 200 report |

Fields: invitation `id, role, email, email_hint, class_id, class_name, student_id, student_name, status
(pending|accepted|revoked|expired), expires_at, created_at`. `email` is `null` without `list_users`; `email_hint` is
always masked (`a***@example.org`). Token hashes and links are never listed; a link is returned once, at creation.
Managed student: `id, display_name, username, external_student_id, joined_at, setup_expires_at` — the sign-in name
and school student ID are school-issued identifiers the administrator hands to the student; no email exists.

Invitation rules: guardians use `/parents/{id}/children`, password setup uses the setup-link route (schema
`400` for other roles). An account with that email already on the roster in that role answers `409
_already_member`; an open invitation for the same email and role `409 _invitation_pending` (`data.invitation`).
`class_id` must be an active class of this school, teacher or student only (`422 _invalid_class`).

### CSV import

UTF-8 (a BOM is accepted) with a header row; columns `role` (`student` default, or `teacher`), `name`
(required), `email`, `external_student_id` (alias `student_id`). Other columns are ignored and listed in
`ignored`. At most 200 people. File errors answer `422 _csv_invalid` (no `name` column, duplicate columns,
invalid UTF-8), `_csv_empty` or `_csv_too_long`; the size limit is schema `400`.

Each row is planned against the current roster: without email a student becomes a managed account
(`create_student`, or `existing` when the school student ID is taken); with email the person is invited
(`invite`, or `existing` when already on the roster in that role, `pending` when an invitation is open). Errors
(`error` with a message): unknown role, missing name, invalid email, a teacher without email, a student without
email or school student ID, a repeated row.

The dry run writes nothing and answers `{ dry_run: true, review, counts, ignored, rows[] }`, each row `{ line, role,
name, email, external_student_id, action, error }`. `review` is an HMAC of the caller, the school and every planned
row. Applying (`dry_run: false`) requires it (`400 _review_required`); the plan is recomputed under a per-school
lock and a different plan — another file, a roster change, another reviewer — answers `409 _review_stale` without
writing. Only `create_student` and `invite` rows are written (`created` with `user_id` and `username`, `invited`
with its one-time `url`); a row that fails becomes `error` and the rest continue. Applying the same file again finds
everyone as `existing` or `pending`, so nothing is created twice; the old review token is stale by then.

## All accounts

Server code: `includes/Rest/V1/AccountHubUsersController.php` (extends the Account Hub controller for paging and
errors, with its own permissions). Screen: `#/account-hub?tab=users` (`UsersTab.tsx`, `AccountDialog.tsx`,
`accounts.ts`, `accountsApi.ts`). It replaces the legacy `#/accounthub?tab=users` tab (`PeopleDirectory.jsx` with
`AddModal.jsx`/`EditModal`), which used `GET/POST ohmylms/v1/school/users` and `GET/POST /school/users/{id}`; those
legacy routes are unchanged.

| Severity | Finding (legacy All accounts tab and `school/users`) |
| --- | --- |
| High | **No privilege boundary on edits.** `POST /school/users/{id}` changed the email of any account the caller could `edit_user`, so a delegated user manager (`edit_users` without `manage_options`) could set an administrator's email and send it a reset link: account takeover. It also changed the caller's own email without core's confirmation mail, and had no stale-save check. |
| Medium | **Directory PII and size.** Each row carried avatar URL (an email hash), edit URL, last login and roles; the tab downloaded every 50-row page (no totals) and searched, date-filtered and paged in the browser. |
| Medium | **Account creation trusted the role map.** Student/teacher/parent roles were assigned without checking the role was editable by the caller or held no capability beyond the caller's; an explicit username that was taken was silently changed to `name2`. |
| Low | `is_banned` cast any non-empty meta (including `no`) to "Blocked"; `last_login` was site-local time without a zone. |
| OK | Core REST nonce; listing needs `list_users`, creating `create_users`; roles were limited to student, teacher and parent; new accounts get a random password and the standard WordPress set-password mail. |

Permissions: anonymous `401`, otherwise `403` without the core capability: `list_users` for the list and options
(`_cannot_list_users`), `create_users` to create (`_cannot_create_users`), `edit_users` to open or save an account
(`_cannot_edit_users`). On top of core, an account can be opened or saved only when it is not the caller's own
(`403 _own_account`; use the profile page) and every capability it holds (role names aside) is also held by the
caller (`403 _cannot_manage_user`), so an administrator can never be edited by a lesser user manager. An account type
is offered and accepted only when its role is in `get_editable_roles()` for the caller, is not `administrator` and
holds no capability the caller lacks (`403 _type_not_allowed`). No route assigns or changes roles or capabilities;
an extra `role` parameter is ignored.

| Method | Path (below `/account-hub`) | Purpose | Success |
| --- | --- | --- | --- |
| GET | `/users` | Accounts by name; `search` (name, username, email), `role` (a site role slug), `registered` (`all`, `last_30_days`, `current_month`, `previous_month`, `current_year`, `last_12_months`, site time zone), `status` (`all`, `active`, `blocked`), `page`, `per_page` ≤ 100 | 200 + totals, `no-store` |
| GET | `/users/options` | `{ roles[] {slug, label}, types[] {value, label}, can_create }` | 200 |
| POST | `/users` | `{ type: student\|teacher\|parent, first_name, last_name?, email, username?, send_email (default true) }` | 201 account + `Location` |
| GET | `/users/{id}` | `{ id, username, display_name, first_name, last_name, email, roles, version }` | 200, `no-store` |
| PUT / PATCH / POST | `/users/{id}` | Partial update of `first_name`, `last_name`, `email`; `send_reset`; send `version` | 200 |

Listed account: `id, display_name, username, email, roles[] {slug, label}, registered_at, last_login, blocked,
can_edit` (times UTC ISO 8601; `can_edit` applies the rules above). No avatar, edit URL or other profile data.
Creating: first name 1-190, last name ≤ 190, email valid and unused (`409 _email_exists`, `data.field`); an explicit
username must be valid (`422 _invalid_username`) and free (`409 _username_exists`), otherwise it is derived from the
email with a numeric suffix. The display name is first plus last name; the person sets the password from the mailed
link. Saving locks the user row inside a transaction; a different `version` (hash of email, display name, first and
last name) answers `409 _conflict` with `data.version`, writing nothing; another account's email answers
`409 _email_exists`. `send_reset` mails a reset link after a successful save. Creates and edits are recorded in the
school audit log (`account_created_by_admin`, `account_updated_by_admin`).

Not carried over: the legacy row checkboxes had no bulk action; blocking and unblocking stay on the Students screen.

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
| 400 / 404 / 409 / 422 | `_review_required`; `_student_not_found`; `_school_inactive`, `_duplicate_student_id`, `_review_stale`, `_import_busy`; `_invalid_email`, `_invalid_class`, `_invalid_student`, `_csv_invalid`, `_csv_empty`, `_csv_too_long` | Roster intake (see above). |
| 500 | `_failed` | Database failure; nothing was changed. |

## UI

`assets/src/app/features/account-hub`: `#/account-hub` (tabs Schools, Classes, Teachers, Parents, All accounts via `?tab=`, and
a link to `#/students`), `#/account-hub/schools/<id>` (details, academic years, the school's classes, add class)
and `#/account-hub/classes/<id>` (details, archive/restore, roster, add students or teachers by searching the
school roster, class work and grading, link to the class's guardians), the school sub-screens
`#/account-hub/schools/<id>?panel=guardians|report|rollover` (archive/restore/delete sit on the details screen; the
Schools list has status views and the sample school action), and `#/account-hub/schools/<id>/roster` (invitations with revoke, managed students with
confirmed setup links, CSV review then import; linked from the school). Extensions see these screens as the
original `/accounthub` route. It replaces the legacy school view's Invitations, Import and managed-student
forms (`#/accounthub?tab=schools` → school → Invitations / Import / Roster).

## School workflows (31 October 2026)

Server code: `includes/Rest/V1/AccountHubSchoolLifecycleController.php`, `AccountHubGuardiansController.php`,
`AccountHubReportsController.php`, `AccountHubClassWorkController.php`, `AccountHubSampleSchoolController.php` and
`AccountHubInvitationAcceptController.php` (all extend or mirror the Account Hub controller). Domain rules live in
`includes/Schools/`: `SchoolRemoval`, `RosterEnrolments`, `Guardians`, `SchoolReport`, `Rollover`, `SamplePreview`,
`Signup`. Responses that carry links or counts of people send `Cache-Control: private, no-store`.

### Who may do what

| Workflow | Site administrator | School administrator (own active schools) | Teacher, parent, student, subscriber | Anonymous |
| --- | --- | --- | --- | --- |
| Archive, restore, delete a school | yes | `403 _cannot_remove` (they would lock themselves out) | 403 | 401 |
| Guardians, report, class work, rollover | yes | yes; other schools `403 _forbidden` | `403 _cannot_view` / `_cannot_edit` | 401 |
| Sample school | yes | `403 _cannot_sample` | 403 | 401 |
| Accept an invitation | any signed-in account with the REST nonce | | | 401 |

Archived schools are read only: every write into one answers `409 ohmylms_account_hub_school_inactive`, and school
administrators lose access to them (scope only counts active schools).

### School removal

| Method | Path (below `/schools/{id}`) | Purpose | Success |
| --- | --- | --- | --- |
| POST | `/archive` | Archive (reversible). `409 _school_archived` when already archived | 200 school |
| POST | `/restore` | Restore. `409 _school_active` when not archived | 200 school |
| GET | `/removal` | What deleting would remove, counted now: `{ id, name, status, can_delete, confirm, impact }` | 200 |
| DELETE | `` (the school) | Permanent delete; `confirm` must equal the school name (`422 _confirmation_mismatch`); only an archived school (`409 _school_active`) | 200 `{ deleted: true, id, removed }` |

`impact` (and `removed`): `classes, academic_years, students, teachers, school_admins, class_memberships,
teacher_links, guardian_links, invitations, assignments, course_links, roster_enrolments, managed_accounts`.
Archiving changes only the school status: staff, students and families lose access, invitations cannot be accepted,
nothing else changes. Deleting runs in one transaction: active course enrolments that a class roster of the school
granted (`ohmylms_enrolments.source = roster`, `class_id` one of the school's classes) are cancelled through
`Student\Enrollments::update()` with the source kept; then class and school memberships, student profiles, guardian
links, invitations, distributed work and its recipients, course-class links, classes, years and the school row are
deleted. **No user account is deleted**; managed accounts lose only their `_ohmylms_managed_school` marker. Completed
enrolments, other enrolments, progress, attempts, grades and the audit log stay (`school_deleted` is recorded).

### Guardians

| Method | Path (below `/schools/{id}`) | Purpose | Success |
| --- | --- | --- | --- |
| GET | `/guardians` | Active links and open invitations by student; `status=all|active|pending`, `class_id` (a class of the school, else `404 _class_not_found`), `search` (student or parent name) | 200 + totals |
| POST | `/guardians` | Invite a guardian: `{ student_id, parent_id }` (existing account; invitation to its own email) or `{ student_id, email }`, `send_email?` | 201 `{ kind, id, school_id, student_id, url, expires_at, email_sent }` |
| DELETE | `/guardians/{link}` | Revoke an active link (becomes inactive) | 200 `{ revoked: true }` |
| DELETE | `/guardian-invitations/{invitation}` | Revoke an open guardian invitation | 200 `{ revoked: true }` |

Row: `kind (link|invitation), id, status (active|pending), student {id, display_name}, parent {id, display_name}|null,
linked_at, expires_at, email_hint`. Only display names; `email_hint` is masked (`a***@example.org`) and only on
open invitations. A link is never made from a name or an email: both POST forms create a single-use guardian
invitation that becomes a link only when the parent accepts it while signed in with that address. Errors:
`400 _parent_required` (neither or both), `422 _not_on_roster`, `_invalid_email`, `_parent_without_email`,
`409 _already_linked`, `_invitation_pending` (`data.invitation`).

### School report

| Method | Path (below `/schools/{id}`) | Purpose | Success |
| --- | --- | --- | --- |
| GET | `/report?period=7|30|90` | Summary | 200 |
| GET | `/report/export?period=` | The same as CSV text `{ filename, content_type, csv }` | 200 |

`{ school, period {days, start, end}, summary, classes[], more_classes, mastery {skills[], average, assessed},
generated_at }`. `summary` and each class: `learners` (active student members; a class's roster), `active_learners`
(an enrolment, progress row, quiz attempt or assignment submission in the period), `enrolled`, `completed`,
`completion` (% of enrolled learners per course who completed it, null without enrolments); the summary adds
`classes`, `archived_classes`, `assigned`, `assigned_completed` (distributed work recipients); classes add `id, name,
grade, teachers, assigned, assigned_completed` (at most 50 active classes; `more_classes` counts the rest). Mastery:
the 8 skills most learners have evidence on, `{ id, name, code, assessed, average, below }` (below 50 %). Figures
come from `Dashboard\Scope::school()` / `classroom()` with `Dashboard\Repository::active_learners()`,
`Dashboard\Enrolments::courses_by_enrolment()` and `Dashboard\MasteryRepository`, so they match the Dashboard. No
names of learners, emails or ids of people leave the report. The CSV (UTF-8, CRLF, a row per class, a whole-school
row, then the skills) prefixes cells that start with `= + - @`, tab or CR with `'`.

### Year rollover

| Method | Path (below `/schools/{id}`) | Purpose | Success |
| --- | --- | --- | --- |
| POST | `/rollover/preview` | `{ from_year_id, to_year_id, grade_map: [{from, to}], copy_teachers }`; writes nothing | 200 plan + `review` |
| POST | `/rollover` | The same plus `review` | 200 result |

For every active class of the source year: the grade map says what its grade becomes (unlisted grades stay the
same; an empty `to` means the class leaves). A promoted class gets a successor in the target year (name with the
grade replaced, `7A` → `8A`; `classes.source_class_id` points back, so a successor is reused, never duplicated); its
active students (still on the school roster) join it, teachers too with `copy_teachers`; its course links are copied
(`course_classes`, `course_class_students`) and the students' active roster enrolments move to the new class through
`Student\Enrollments::update()` (source `roster`, `class_id` and `source_ref` the new class). Students of a leaving
class keep their account and school membership; their active roster enrolments are cancelled. Every source class
and the source year are archived. Plan: `{ from, to, classes[] {class_id, name, grade, action (promote|leave),
students, teachers, enrolments, links, target {id|null, name, grade, exists}|null}, totals, review }`; totals:
`classes, promoted_classes, leaving_classes, classes_created, students_promoted, students_leaving, teachers_copied,
enrolments_moved, enrolments_revoked, course_links`. Apply recomputes the plan under a per-school lock and the year
row locks; a different plan answers `409 _review_stale`, another running rollover `409 _rollover_busy`, an archived
source year `409 _state_conflict`, unusable years `422 _invalid_year`. Applying the same review again answers the
first result with `already_applied: true`.

### Class work

Replaces the class routes the deleted platform class editor used.

| Method | Path (below `/classes/{id}`) | Purpose | Success |
| --- | --- | --- | --- |
| GET | `/assignments` | Work assigned to the class with `recipients` and `completed` | 200 + totals |
| POST | `/assignments` | `{ course_id, content_id?, title?, due_at? (school time, YYYY-MM-DDTHH:MM), prior_completion? }`: every current student must already have course access (`422 _rejected`); nobody is enrolled | 201 `{ id, recipients }` |
| GET | `/courses?search=` | Published courses to assign | 200 + totals |
| GET | `/courses/{course}/activities` | Published activities of a course (≤ 200) | 200 |
| GET | `/submissions?page=` | Written submissions to the class's work (100 per page) `{ items[], has_more }` | 200 |
| POST | `/submissions/{attempt}` | `{ assignment_id, score, note? }` whole points up to the activity total | 200 `{ graded: true }` |

Submission: `id, assignment_id, title, student_name, content, score, status, note, total_points, file_url` (a nonce'd
download that re-checks the class relationship). Archived classes answer `409 _class_archived`; independent classes
`422 _independent_class`.

### Sample school

| Method | Path | Purpose | Success |
| --- | --- | --- | --- |
| GET | `/sample-school` | `{ exists, complete, school_id, classes[], people[] {key, id, display_name, role}, removal {allowed, remove[], skip[], blockers[]} }` | 200 |
| POST | `/sample-school` | Create or complete it (a lock prevents two sets) | 201 created, 200 when it existed |
| DELETE | `/sample-school` | Remove every marked record in one step | 200 `{ removed: true }`; `409 _sample_in_use` (`data.blockers`), `404 _sample_not_found` |

"Sample Preview School" (slug `sample-preview-school`) with one academic year, classes "Sample Grade 7A" and
"Sample Grade 8B", six managed students (three per class), a teacher of both classes, a school administrator and a
parent linked to the first student. Accounts carry user meta `_ohmylms_sample_preview`, use `@example.invalid`
addresses and random discarded passwords (only View as can use them). Removal touches only marked records and is
refused while real people, classes, years, invitations, assignments, course links or learner activity are attached.

### Invitation acceptance

`POST /invitations/accept` `{ token }` (64 hex): the signed-in recipient accepts a school, class or guardian
invitation (the learner hub's "Join with an invitation" form, `templates/student-hub/classes.php`). Needs the REST
nonce (`403 ohmylms_invitation_nonce`), 15 attempts per client in 15 minutes (`429 ohmylms_invitation_rate_limited`).
`Service::accept()` checks the account email, the active school and the issuer's permission (`422
ohmylms_invitation_rejected` with its message). Password-setup codes are refused (`422
ohmylms_invitation_password_setup`); they are used on the hub's own password page.

## Retired legacy routes

The platform school editor (`assets/src/features/schools`, the `schools` webpack entry and the hidden
`admin.php?page=ohmylms-schools` screen) and `includes/Schools/Controller.php` were deleted. Old
`page=ohmylms-schools` addresses redirect: `&school=N` to the school, `&class=N` to the class, `&tab=` to the
Account Hub tab (school staff to their hub workspace). Every `ohmylms/v1/school/*` route except View as is gone:

| Retired route | Replacement |
| --- | --- |
| `GET/POST /school/users`, `GET/POST /school/users/{id}` | `GET/POST /account-hub/users`, `GET/PUT /account-hub/users/{id}` |
| `GET /school/me` | none (the app reads capabilities from its bootstrap) |
| `POST /school/register` | hub sign-up form (`Student\ParentActions` → `Schools\Signup::create()`, nonce checked) |
| `POST /school/accept` | `POST /account-hub/invitations/accept` |
| `GET/POST /school/schools` | `GET/POST /account-hub/schools` |
| `GET/POST /school/classes` (independent) | `GET /account-hub/classes?school_id=0`; teachers create them in the hub Teacher workspace |
| `GET/POST /school/schools/{id}/years` | `GET /account-hub/schools/{id}` (`years`), `POST …/years` |
| `GET/POST /school/schools/{id}/classes` | `GET/POST /account-hub/schools/{id}/classes` |
| `GET /school/schools/{id}/roster`, `POST …/students`, `POST …/import`, `GET/POST …/invitations`, `DELETE …/invitations/{id}` | `/account-hub/schools/{id}/students`, `/managed-students`, `/roster-import`, `/invitations` |
| `GET /school/schools/{id}/guardians`, `DELETE …/guardians/{id}` | `GET /account-hub/schools/{id}/guardians`, `DELETE …/guardians/{link}` |
| `DELETE /school/schools/{id}/members/{id}` | `DELETE /account-hub/classes/{id}/members/{user}`; leaving a school stays in the hub School workspace |
| `GET /school/schools/{id}/report` | `GET /account-hub/schools/{id}/report` (+ `/export`) |
| `POST /school/schools/{id}/rollover` | `POST /account-hub/schools/{id}/rollover/preview` then `/rollover` |
| `GET/POST /school/classes/{id}/members`, `POST …/archive` | `/account-hub/classes/{id}/members`, `/archive`, `/restore` |
| `GET/POST /school/classes/{id}/assignments`, `GET …/submissions`, `POST /school/assignments/{id}/submissions/{attempt}`, `GET /school/courses`, `GET /school/courses/{id}/activities` | `/account-hub/classes/{id}/assignments`, `/submissions`, `/submissions/{attempt}`, `/courses`, `/courses/{course}/activities` |
| `GET /school/children`, `GET /school/work` | none over REST: the hub Parent and Learner workspaces read `Service::children()` / `Service::assignments()` server side |
| `GET /school/sample-preview`, `POST …/create`, `POST …/remove` | `GET/POST/DELETE /account-hub/sample-school` |

Kept: `/school/view-as/options|default|start|return` (`Schools\ViewAs`): `manage_options` and the REST nonce on
every call (`return` only inside a View as session); the View as banner uses `return`.

## Not in this slice

Gradebook is rebuilt separately. The hub School workspace keeps its own server-rendered forms
(`Student\SchoolActions`); its rollover still uses `Lifecycle::rollover()`, which copies classes with empty rosters
and does not move enrolments. Adding students to an independent class stays the teacher's invitation
link. The CSV import does not place people in classes.

## Tests

`tests/php/account-hub-rest-integration.php`, `tests/php/account-hub-roster-rest-integration.php`,
`tests/php/account-hub-users-rest-integration.php` and `tests/php/account-hub-schools-lifecycle-rest-integration.php`
(set `OHMYLMS_ACCOUNT_HUB_TEST=1`; see the file headers), `tests/php/sample-preview-unit.php` (no database) and
`npx vitest run --configLoader runner features/account-hub`. The school workflow suite (196 checks) covers the
permission matrix (anonymous, subscriber, teacher, school administrator, site administrator), guardians and
acceptance, the report and CSV escaping, class work, rollover (enrolments moved and revoked, idempotent apply),
archive/restore/delete (accounts kept, roster enrolments revoked) and the sample school, and that the retired
routes are gone. The users test creates tagged accounts and two tagged
roles, intercepts mail, and checks denied callers, paging, filters, minimal fields, creation rules, stale saves and
that a user manager cannot edit administrators or richer roles nor create a type whose role exceeds their own.
