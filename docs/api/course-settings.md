# Typed course settings and publication

The administrator preview links the structure editor to
`#/content-hub/courses/:id/settings`. Source lives in the course-structure feature;
settings transport, publication transport, mastery controls and screen components
are separate modules. No recovered runtime adapter is loaded by these screens.

## Settings

`GET /ohmylms/v1/authoring/courses/:id/settings` returns `course_id`, `revision`,
`title`, `description`, `author_id`, `image_id`, `status`, `price_type`,
`regular_price`, `sale_price`, `access_type`, `has_capacity`, and `capacity`.
Responses are private/no-store. The stored password is never returned.

`PUT` accepts all those fields except `course_id`, plus an optional write-only
`password`. Unknown or missing fields are rejected. Prices are decimal strings,
capacity and IDs are integers, and `has_capacity` is a boolean. Titles are nonblank
and at most 190 characters; description HTML is sanitized with `wp_kses_post` and
limited to 200 KB. Mathematical backslashes are retained through WordPress slashing.
Authors must have the course type's authoring capability. Images must be image
attachments the caller can edit; zero removes the image. Paid courses require a
positive regular price; sale price cannot exceed it. Existing sale dates remain
in effect. Password protected access requires an existing or supplied password;
an omitted/empty password preserves an existing secret.

Both endpoints require `manage_options` and per-course `edit_post`. Changing
author also checks the course type's `edit_others_posts`. Private/published status
requires its `publish_posts`. Cookie requests use the existing REST nonce client.
Settings may save draft, pending, or private status, or retain an already published
status. They cannot promote an unpublished course to published.

Reads and writes hold the shared course advisory lock. Saves additionally run inside
the existing course transaction and compare a SHA-256 fingerprint of the editable
settings, stored password, sale dates, and structure/program revision. A stale write
returns 409 and retains local UI edits. Explicit reload/discard is required to replace
dirty local state. The established course data store supplies pricing calculations
and hooks; unrelated metadata and existing sale schedules are retained.

## Learning controls

The structure editor now edits traditional/skill-based/blended mode, prior-mastery
recognition, evidence age, selected shared skills, required flags, proficiency/mastery
targets, and unit placement. Shared skills are searched through the Content Hub's
public API export. These changes use the existing full outline draft save/revision
contract. Question bank selections and activity settings are carried through.
Required targets remain subject to approved question pool/family readiness rules.

## Reviewed publication

`GET /ohmylms/v1/authoring/courses/:id/publication` returns the saved course title,
status, settings/structure revision, mode, outcome count, units and resource titles,
readiness messages, and published program ID. This is an authoring summary, not a
rendering of the learner frontend. The UI disables preview until the outline is saved.

`POST` accepts exactly `{revision: string, confirmed: true}`. It requires the above
editor permissions plus the course type's `publish_posts`, compares the reviewed
revision while locked, reruns existing program readiness, publishes an immutable
program version, and publishes the course post in one transaction. Existing enrolled
learners retain their pinned program version; no `apply_existing` option is accepted.
Shared draft resources are not implicitly published. A stale confirmation or an
unready program returns 409. Resource content remains governed by its existing shared
resource policy; this endpoint does not introduce resource versioning.

## Verification and remaining scope

`OHMYLMS_SETTINGS_TEST=1` enables `tests/php/course-settings-rest-integration.php` on
the named Local database. It creates and removes only its own tagged fixtures and
checks save/reopen, math escaping, HTML sanitization, effective pricing, password
secrecy, invalid input, denied users, stale reviews, readiness, confirmed publication,
replay rejection, and rollback when the final course status write is forced to fail.
App component/transport tests cover failed saves, explicit discard, the confirmation
flow, readiness and unsaved-state gating, and malformed/cross-course responses.

The current UI uses existing user/attachment IDs rather than author/media pickers.
Sale date editing, purchase points, cohort/scheduled availability, certificates,
communities, automations, extension panels, lesson/assessment content authoring and
full syllabus workspace parity remain. No old-menu cutover or learner frontend
TypeScript migration is claimed. The shared unsaved guard covers link clicks and
unload, but not browser hash-history navigation.
