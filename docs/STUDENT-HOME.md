# Student Home

The unified student workspace uses one published WordPress page containing
`[ohmylms_student_hub]`. On the next administrator visit the plugin provisions
Student Home without modifying or deleting existing student pages. The public
page lives at `/profile/` (it was `/student-home/` before October 2026; the old
address redirects with its query string). Configure it under **OhMyLMS → Account
Hub → Home**, under *Customize the learner home*; the former
`admin.php?page=ohmylms-student-home` address redirects there. The canonical page fills the viewport; embedding
the shortcode in another page retains that page's theme template.

The one page serves several workspaces, chosen with `hub_role` and shown through **Switch role** to accounts
that are eligible: the learner workspace described here (the default), the Teacher workspace (independent
classes, roster, reports), the Parent workspace (school-linked and home-learning children) and the School
workspace (classes, people, reports, gradebook). They have their own documents:
[SCHOOL-FAMILY-SETUP.md](SCHOOL-FAMILY-SETUP.md) and [learner-experience/](learner-experience/). The learner
sections are My learning, Browse subjects, Classes, Achievements, History, Membership and Settings.

**Signing in.** Signed-out visitors see a chooser (Learner, Parent, Teacher) with an inline sign-in form that
posts to `wp-login.php` and returns to the hub (`Student\LoginExperience`), a sign-up form for each role, and
Google or Microsoft buttons when those integrations are configured. Hub users are kept out of wp-admin after
sign-in.

**Learner details.** **Settings** is a stack of cards: Basics (first name, last name, display name and the
optional learner details phone number, province (Ulaanbaatar, the 21 aimags or outside Mongolia), school name
and grade 1 to 12 or other), About you, Email address, Password, Appearance, Notifications and Connections
(teachers, parents / guardians and Leave school). The learner details are stored through `Student\LearnerProfile` (user meta `ohmylms_profile_province`,
`ohmylms_profile_school_name`, `ohmylms_profile_grade`; the phone reuses the student phone field) and are never
asked at registration.

Administrators can rename, hide and reorder sections, set the default section,
choose sidebar or top navigation, set the title/accent, show/hide commerce and
theme controls, and show/hide/reorder Home cards. Reordering supports drag and
drop and keyboard-accessible move buttons. Save before using the desktop/mobile
preview. Account always remains reachable. If a hidden section is selected as
the default, the first enabled section becomes the default.

Student sections use `hub_section` and `hub_view` query parameters on the same
page. Navigation is server-rendered, supports browser history/bookmarks, and
works without JavaScript. Individual lesson, quiz, assignment and practice
players continue to enforce their existing enrollment and assessment policies.
My work aggregates published quizzes and assignments from enrolled curricula;
it does not invent deadlines or create new assignments. Learning paths use the
program version associated with the learner's enrollment. Progress includes
course progress, skill mastery when enabled, and existing achievements.

My classes is independent of self-paced learning. Its class switcher lists only
active student memberships at active schools, shows distributed assignments via
the existing school service, and displays already-approved teacher and guardian
connections. Accepting a pasted invitation link/code uses the existing email-bound,
expiring invitation service. No invitation email is sent by this change, and it
does not introduce reusable short class codes or a counselor role/permission model.
The learner hub never grants school access just because someone knows a class ID.

The signed-in Khan Academy reference was inspected on October 15, 2026. That
account's learner sidebar groups Classes, Tutor Me, Achievements, History, Browse
Subjects and Settings. Class selection, the independent subject library, account
connections, and the teacher roster's invitation choices informed this layout.
The implementation keeps independent course discovery available to learners who
have not joined a class. No reference-account roster data is copied into OhMyLMS.

The three fresh membership/connection reads intentionally avoid a cross-request
cache so that revocations apply immediately. PHPCS reports six direct-database
and caching warnings for these prepared queries; no rule is disabled for them.

The old dashboard, profile and My Courses shortcodes remain registered but now print a link to the matching
hub section, and their generated pages are retired to the Trash with redirects that survive (see
[learner-experience/LEGACY-RETIREMENT-PLAN.md](learner-experience/LEGACY-RETIREMENT-PLAN.md)). Configured
Dashboard/Profile/My Courses bookmarks and account endpoint helpers resolve to hub sections while the hub is
enabled and its page is published. POST handlers and logout remain in the existing account pipeline. Disabling
the hub link setting restores original navigation and the legacy renderers. Invoice rendering checks
the current customer's ownership before displaying an order.

Navigation visibility is presentation, not authorization. Student data comes
from the current logged-in user; hiding a tab does not grant or revoke access
to the underlying service. Commerce and gamification permissions are unchanged.

## Compatibility exception

`includes/Student/Hub.php` follows the repository's Composer PSR-4 mapping for
`OhMyLMS\Student\Hub`. The PHPCS ruleset excludes **only filename checks for
this file**, since renaming it to `class-hub.php` would break that autoload
mapping. All other WordPress checks remain enabled.

## Validation

Run `tests/php/student-hub-integration.php` with `OHMYLMS_TEST_CREDENTIALS`
pointing to the private credentials file for the disposable
`ohmylms_source_test` database. The script refuses other databases and restores
its settings and fixtures. Provider credentials are not needed.

Validated in the disposable WordPress site (first release, 50 checks; the suite has grown since and is
recorded in the checkpoints of [CONTINUE-LEARNER-HUB.md](../CONTINUE-LEARNER-HUB.md)): 50 integration checks covering
installation, settings capability/nonce enforcement, layout persistence, legacy
URLs/pagination, guest isolation, invoice ownership, enrolled work, school class
memberships, approved connections and immediate revocation. The existing JavaScript
suite passed 414 tests with one skipped. The source check and production build
passed; the build retains two existing math-runtime size warnings.

Browser checks covered saved settings, card move buttons, course tabs, course
discovery, invitation entry, the full-page layout and a 390px mobile preview with
no horizontal overflow. New production PHP has no PHPCS errors and six warnings
described above. The integration fixture has 30 direct-database/local-file warnings.
The full repository standards command still fails on existing violations outside
this change; no baseline or broad suppression was added.
