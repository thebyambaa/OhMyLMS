# Legacy retirement: what was retired and what still forwards

Rewritten October 19, 2026. The original inventory and proposal (October 16, 2026) is replaced by
this record of the outcome, checked against the code. The decision behind it, from the owner: **there
is no front-end LMS portal.** Learners, teachers, parents and school staff use the learner hub
(`[ohmylms_student_hub]`, hub page slug `profile`); platform administrators use wp-admin. The standalone
React school/parent app and its front-end screens were removed.

## Rules that governed the retirement

- Retire an old page, shortcode or route only after its replacement was verified.
- Preserve custom content, working redirects and learning records. Pages are trashed (recoverable),
  never deleted, unless they were test fixtures.
- Old public shortcode tags stay registered as link adapters so embedded copies never print raw
  text.

## Generated pages

The three generated student pages (dashboard, profile, my courses) are retired by
`Student\LegacyPages::retire()` only when they are unmodified (`[ohmylms_dashboard]`, `[ohmylms_profile]`
or `[ohmylms_my_courses]` as the whole content, no menu entry, no child page, no custom metadata). Each
retired page ID, its former URL and endpoint are kept in the ledger option `ohmylms_hub_retired_pages`;
`LegacyPages::redirect()` sends GET requests for those URLs to the matching hub section, so the
redirect works even though the page is in the Trash. `LegacyPages::restore()` untrashes them and
restores the configured page IDs when the hub is turned off.

`ohmylms_default_pages` is filtered by `LegacyPages::default_pages()`, so `Install::create_pages()` and
the REST `restore_pages` action never recreate a retired page. Checkout and the membership page are
not part of the hub and are untouched.

The hub itself moved from `/student-home/` to `/profile/` (`Hub::SLUG`, `Hub::migrate_slug()`); the old
address redirects with its whole query string (`LegacyPages::renamed_hub_target()`), so invitation
links and bookmarks keep working. Retired-page redirects never match the hub's own address.

## Shortcodes and blocks

| Tag | Behaviour now |
| --- | --- |
| `ohmylms_dashboard`, `ohmylms_profile`, `ohmylms_my_profile`, `ohmylms_my_courses` | Render a link to the matching hub section (`LegacyPages::link()`, `Shortcodes/Shortcodes.php`). With the hub disabled (no hub URL) the legacy renderers (`ShortCodeDashboard`, `ShortCodeProfile`, `ShortCodeMyProfile`, `ShortCodeMyCourses`) are the rollback path. |
| `ohmylms_student_registration`, `_teacher_registration`, `_parent_registration`, `_sign_in`, `_school_dashboard`, `_teacher_dashboard`, `_parent_dashboard`, `_student_assignments` and the matching `ohmylms/*` blocks (no block exists for student assignments) | `Schools\Views::render()` returns "Continue to your learning hub", linking to the right workspace (`Views::workspace_url()`: parent, teacher, school, learner Classes, otherwise the hub home). The block editor preview describes this. Platform admins still get the React management screen inside wp-admin. |
| `ohmylms_checkout`, `_course_list`, `_membership_plan`, `_offer_button`, `_buy_now`, `_quiz`, `_quiz_review`, `_question`, `_skills`, `_practice`, `_learning`, `_bundles`, `_subject_skills`, `_activity`, `_student_hub` | Active, unchanged. |

## The old portal URL (`/?ohmylms_portal=1&view=...`)

The portal has no renderer any more. `LegacyPages::redirect_portal()` and `Schools\Views::portal()` forward
GET requests; other methods get HTTP 405, and an unavailable hub gets 503.

- Invitation links (`ohmylms_invite=<64 hex>`) keep their token and open in the hub; school-file
  downloads (`ohmylms_school_file`) are served after a nonce, resource-scope and upload-path check.
- `parent-dashboard`, `teacher-dashboard` and `student-assignments` go to the parent workspace, the
  teacher workspace and the learner Classes view, keeping a `class` selection as `hub_class`.
- `school-dashboard` (and an empty view) goes to the hub School workspace for school staff and to the
  wp-admin Account Hub (`#/accounthub`, `school`, `class` and `tab` kept) for platform administrators;
  signed-out visitors go to the hub sign-in chooser.
- View as previews land in the hub workspace of the previewed role.

## What stays in wp-admin

The React school app (`assets/src/features/schools/`) only mounts as `admin-management` inside
wp-admin for platform administrators: the Account Hub tabs (Home, Students, Teachers, Parents, All
accounts, Classes, Schools), the hidden `ohmylms-schools` editor page, the one-click role previews
(`PreviewHome.jsx`) and the View as dialog. `admin.php?page=ohmylms-schools` forwards to the Account Hub
tab for administrators and to the hub School workspace for everyone else.

## Self-registration

Student, teacher and parent self-registration all live in the hub sign-in chooser, one form per role,
submitting to `ohmylms_parent_signup` (`Student\ParentActions`). The role is allow-listed to student,
teacher or parent and anything else becomes parent. Roles created are `ohmylms_student`,
`ohmylms_teacher` and `ohmylms_parent`. The sign-in itself is an inline form on the same screen that
posts to `wp-login.php` and returns to the hub (`Student\LoginExperience`); hub users are kept out of
wp-admin. The public registration REST routes stay because the hub uses them.

## Verification record

Probes and live checks from October 16, 2026 (portal forwarding, teacher/parent/student redirects,
ledger intact, role allow-list, invitation tokens preserved) are summarised in
[CONTINUE-LEARNER-HUB.md](../../CONTINUE-LEARNER-HUB.md) ("No portal" and "Frontend portal retirement
checkpoint"). The probe scripts lived in the git-ignored `test-results/` folder and are not in the
repository; the lasting automated coverage is `tests/php/view-as-unit.php`, the protected Student
Home suite and the legacy account blocks suite.

## Left alone on purpose

- Pages 6, 7, 8, 9, 10 and 36 on the development site belong to an older plugin (`cr-*`,
  `creator_lms_*` tags); they are site content, not plugin content.
- The legacy renderer classes are kept as the hub-disabled rollback path.
