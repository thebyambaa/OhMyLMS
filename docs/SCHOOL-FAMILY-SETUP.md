# OhMyLMS school and family setup

Rewritten October 19, 2026 for the learner hub. There is **no front-end portal**: teachers, school staff,
parents and learners all work inside the hub page (`[ohmylms_student_hub]`, slug `profile`), where **Switch
role** moves between the Learner, Teacher, Parent and School workspaces that an account is eligible for.
Platform administrators manage accounts, schools and classes in wp-admin. The old portal address
(`/?ohmylms_portal=1`) and the old blocks and shortcodes only forward to the hub; see
[learner-experience/LEGACY-RETIREMENT-PLAN.md](learner-experience/LEGACY-RETIREMENT-PLAN.md).

The same plugin includes all school and family features. No Pro/free split applies.

## Where things are

| Who | Where | What |
| --- | --- | --- |
| Platform administrator | **OhMyLMS → Account Hub** (`#/accounthub`) with the tabs Home, Students, Teachers, Parents, All accounts, Classes and Schools | Directories with search and pagination, school and class editors, hub settings (Home tab: "Customize the learner home"), one-click previews as a student, teacher, parent or school administrator, and the View as dialog |
| School administrator and staff | Hub **School workspace** | Overview, Classes, Reports, Gradebook, People, Invitations, School settings, and account settings |
| Teacher | Hub **Teacher workspace** | My classes (active and archived), Assignments, Roster, Reports, Class settings, Library, account |
| Parent | Hub **Parent workspace** | Children from a school and home-learning children (the `family` section) |
| Learner | Hub learner workspace | Library, My learning, Classes, Achievements, History, Membership, Settings |

Hub users are kept out of wp-admin after they sign in (`Student\LoginExperience`). Users who can edit
content or manage the site are unaffected. The WordPress administrator's old shortcuts remain: the
legacy `admin.php?page=ohmylms-schools` address forwards to the Account Hub tab for administrators and to
the School workspace for everyone else.

## Create a class without a school

An administrator can assign the **OhMyLMS Teacher** role to an account without inviting it to a school,
and anyone who registers as a teacher gets it. In the Teacher workspace choose **Create a class**, enter a
name and optionally a subject and grade; no school or academic year is needed. A class is a student
roster: students keep their own course access and learning records. Independent classes are listed
apart from school classes and can be archived and restored by their creator or a platform administrator;
other teachers cannot list, inspect or archive them. In the class the teacher searches the students on
the school roster to add them, or creates a private single-use invitation link for a student's email
address (the student signs in and accepts it; no email is sent).

## Set up a school

1. **A teacher can create their own school** in the Teacher workspace ("Create your school"): they enter a
   name and an IANA time zone, such as `Asia/Ulaanbaatar`, and become the school's administrator. A platform
   administrator can also create schools in the Account Hub (Schools tab). A teacher's independent classes
   stay as they are.
2. In the School workspace open **Classes** and add an academic year (label, start and end dates), then
   create classes for the year.
3. Open **Invitations**, choose **Invite as** (school administrator, teacher, student, or parent for a
   named student), enter the recipient's email where needed and create the link. The link is shown once,
   is single use and expires after 48 hours; nothing is emailed by the plugin. The recipient signs in with
   the invited email and opens the link (`?ohmylms_invite=<token>`); new teachers can register first, and the
   invitation grants the school permission.
4. In **People**, add school-managed students (name and school student ID), import students from CSV, or
   invite existing student accounts rather than duplicating them. An invitation for an existing account can
   also place the student in a class.
5. For a new school-managed child create a **password reset link** (People). Share it privately with the
   child or an authorized guardian. The child sets a password, receives their generated username and
   signs in. Staff can never read a password.
6. Open a class to add students or co-teachers from the school roster.

School administrators manage only their own school and teachers only their assigned classes. Neither role
receives WordPress administrator or general content-editing permissions.

## Parents and students

- Student, teacher and parent sign-up are all forms in the hub sign-in chooser (one per role). Sign-up needs
  an email and a password of 12 to 128 characters and does not create an enrollment, order, school
  membership or child link. The sign-in itself is an inline form on the same screen. A Google button
  appears when Google sign-in is configured.
- After sign-up learners can add optional details in **Settings**: first name, last name, phone, province,
  school name and grade (`Student\LearnerProfile`; they are not asked at registration).
- School-managed child accounts need a name and a school student ID but no personal email.
- School staff send a **Parent / guardian** invitation naming the student and the recipient's email; the
  signed-in recipient must match that email to accept it. Links are school-specific and revocation takes
  effect on the next request. In the Parent workspace the guardian sees the linked child's distributed
  school work and completion, and the home-learning report for independent family links.
- Independent families work without a school: a parent can add a managed child, invite an existing learner,
  set grade and course choices, and view a home-learning report (see
  [learner-experience/PARENT-CHILD-P0.md](learner-experience/PARENT-CHILD-P0.md) and
  [learner-experience/HOME-LEARNING-REPORTS.md](learner-experience/HOME-LEARNING-REPORTS.md)).
- Learners open **Classes** to see distributed work and their teachers; approved guardians and teachers also appear in the learner's Connections.

One person can have more than one responsibility, including teacher and parent. Removing someone from a
class or school, and year rollover, keep their WordPress account and learning records.

## Assigning and grading work

An assignment distributes an existing published course or activity to the current roster of a class. Recipients
must already have access; an assignment does not enroll anyone or bypass content or payment rules. The due date
uses the school's time zone, and the form asks whether earlier completion counts. New class members do not receive
earlier assignments; create a new distribution when needed. Completion comes from the existing LMS completion
events, so there is no second grading system.

Where it happens today:

- The **Assignments** tab of the Teacher workspace and **Reports** in the School workspace list the distributed
  work with completion and overdue counts for each class. **Review submissions** (School workspace → Reports)
  shows written submissions with the student's files; teachers grade and give feedback there. Access needs both a
  current class relationship and an explicit distribution of that activity, and file downloads check the same.
- **Creating** an assignment ("Assign work") is in the Account Hub class editor in wp-admin (Class work) and
  through the REST route `POST ohmylms/v1/account-hub/classes/{id}/assignments` (platform and school
  administrators). The hub does not have a create-assignment form for teachers yet; confirm this gap before
  relying on teachers to assign work from the hub.
- **Gradebook** (School workspace) shows quiz and assignment grades per student with weighted totals and a full
  class CSV download. Manual grade overrides stay in the platform Account Hub course gradebook and are separate
  from school assessment grades.

## Rosters, reporting and school years

- **CSV import** (People): paste up to 200 students and 100 KB, columns `name,external_student_id`. You see a preview
  first; existing school IDs are skipped and names never merge accounts. Errors are listed per row. Imported students use
  the same private password-reset process.
- **Reports** show assigned and completed work, overdue counts and stored skill summaries by class, and export CSV with
  names escaped against spreadsheet formula injection.
- **Academic years** roll over in the Account Hub (school › Year rollover): after a preview, each class of the old year is
  archived and, by a grade map (`7` → `8`; an empty grade means the class leaves), a next-year class is created with the
  promoted students; course enrolments that a class roster granted follow them, those of leaving classes are cancelled.
  Applying the same preview twice changes nothing more. The hub School workspace rollover still copies classes with empty
  rosters. Managed-student recovery is available in People.
- **Schools** can be archived (reversible; closed to staff, students and families) and, once archived, deleted
  permanently by a site administrator after typing the school name. Deleting removes the school's classes, rosters,
  memberships, guardian links, invitations and distributed work and cancels roster-granted enrolments; it never deletes
  a user account.
- A **sample school** (two classes, six made-up students, a teacher, a school administrator and a parent) can be added
  from the Account Hub Schools list for exploration and removed again with one click.
- Schools, class membership and guardian access use separate tables, so removing a roster member never deletes purchases
  or learning records.

## Old blocks and shortcodes

The Gutenberg blocks OhMyLMS Student Registration, Teacher Registration, Parent Registration, Sign In, School
Dashboard, Teacher Dashboard and Parent Dashboard, and the shortcodes `[ohmylms_student_registration]`,
`[ohmylms_teacher_registration]`, `[ohmylms_parent_registration]`, `[ohmylms_sign_in]`,
`[ohmylms_school_dashboard]`, `[ohmylms_teacher_dashboard]`, `[ohmylms_parent_dashboard]` and
`[ohmylms_student_assignments]` (no block exists for the last one) remain registered so existing pages never show raw
tags. Each now prints a "Continue to your learning hub" link to the matching workspace. Insert `[ohmylms_student_hub]`
on a page for the hub itself.

## Google sign-in

Reuse the OhMyLMS Google Sign-In settings and callback URL. The button appears on the hub sign-in screen only when the
integration is enabled and configured. Existing email and password users sign in first and then choose **Connect
Google to this account**; a matching email alone cannot claim an existing account. Returning Google users are identified
by Google's stable subject ID. Local tests cover identity linking, browser-bound OAuth state and replay rejection; live
authorization needs real credentials and an allowed callback origin.

## Design rules that still apply

- WordPress roles give base capabilities; **relationship records decide the scope**. A role, a school ID or a hub role
  parameter never grants access by itself.
- Every endpoint, report, export, search and count checks capability **and** relationship scope in the database query, not
  after loading students. Caches include actor and scope and are invalidated when memberships change.
- Invitations and setup links are 64-hex tokens stored as SHA-256, single use, valid for 48 hours, never logged, and rechecked
  against the issuer on acceptance. Guardian links are never made by searching a child's name or matching an unverified email.
- Tables (prefixed `ohmylms_`, schema option `ohmylms_school_schema`): `schools`, `school_memberships`,
  `school_student_profiles`, `academic_years`, `classes`, `class_memberships`, `guardian_links`, `school_invitations`,
  `learning_assignments`, `assignment_recipients` and `school_audit_log`. Roles: `ohmylms_student`, `ohmylms_teacher`,
  `ohmylms_parent`, `ohmylms_school_admin`. Independent families have their own schema (`ohmylms_family_schema`).

## Development and rollback

PHP is in `includes/Schools/` (services), `includes/Rest/V1/AccountHub*Controller.php` (REST under
`ohmylms/v1/account-hub/`, see [api/account-hub.md](api/account-hub.md); only View as stays under `ohmylms/v1/school/`),
`includes/Family/` and the hub classes in `includes/Student/`; templates are in `templates/student-hub/`; the wp-admin
screens are the Account Hub in `assets/src/app/features/account-hub/`.
Run from the plugin directory:

```sh
npm run check
```

Isolated verification, with `OHMYLMS_TEST_CREDENTIALS` pointing at the disposable site's credentials file and mysqli and
mbstring enabled (the tests refuse any other database and clean up their fixtures):

```sh
php tests/php/schools-integration.php
php tests/php/student-hub-integration.php
php tests/php/teacher-class-integration.php
php tests/php/school-reports-integration.php
npm run test:browser -- schools.spec.cjs
```

To disable the school module without deleting data, define `OHMYLMS_SCHOOLS_ENABLED` as `false` in `wp-config.php`; the family
module has `OHMYLMS_FAMILY_ENABLED`. Leave the tables intact and back up the database before deploying elsewhere; roll code and
database back together.

## Current boundaries

School-funded entitlements and billing, SIS or Google Classroom synchronization, adaptive scoring, parent messaging and a
district hierarchy are not implemented. Rosters page in groups of 50, and invitation history, guardian links and reports have
bounded lists, so load-test against the intended school sizes before a large deployment. Guardian sharing covers
school-distributed work only. Retention, export and deletion policy and age requirements for child accounts must be settled
before a real-school rollout. No existing user is attached to a school automatically.
