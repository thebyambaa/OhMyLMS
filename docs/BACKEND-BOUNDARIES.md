# Backend boundaries (roadmap step 8)

Goal: PHP modules separate REST controllers, business rules and persistence, and one feature reaches
another feature's tables, options or post meta only through that feature's service. This document is the
audit of `includes/Rest/V1` (81 controllers) plus the REST controllers in domain folders, the first
refactors, and the ordered plan for the rest. Audit date: 31 October 2026.

## Rules

- A controller owns routes, argument schemas, permission callbacks, request-to-input mapping, response
  shaping, pagination headers and the mapping of service errors to HTTP. It does not call `$wpdb`, and it
  does not read or write another feature's options or meta.
- A domain service (`includes/<Domain>/`) owns business rules, transactions (`Utility\Transaction`),
  stale-write checks and hooks it fires. A repository/read model owns SQL. Small domains may keep both in one
  final class with static methods, as `Analytics\Repository`, `Bundles\Bundles` and `Curriculum\Items` do.
- Cross-domain reads call the owning service. Domain code never depends on a REST controller.
- Moves keep REST contracts, error codes, permissions, transactions and stale-write checks identical, and
  are verified by the domain's REST integration suite with the same number of checks.

## Summary

Counted on `includes/Rest/V1` (`$wpdb->get_*|query|insert|update|delete|replace` calls):

| | HEAD (`95e900ad`) | After this change |
| --- | --- | --- |
| Direct `$wpdb` calls in controllers | 152 | 109 |
| Controllers with direct `$wpdb` | 31 | 26 |
| Controllers with no `$wpdb`, option or meta access | — | 42 of 81 |
| Domain classes that depend on a REST controller | 2 | 1 (`Schools\Service`) |

Status values in the tables: **Separated** (no persistence in the controller), **Partial** (some reads or
options remain, or a cross-domain read remains), **Controller SQL** (business queries live in the
controller), **Legacy** (retire rather than refactor), **Other owner** (another workstream is editing it).

## Moved in this change

| From | To | What |
| --- | --- | --- |
| `QuizReportsController` | `Quiz\AttemptReports` (new) | Quiz report figures, totals and summary; pass counts |
| `QuizReportsController` | `Quiz\QuizSettings::pass_marks()` | `_quiz_settings` pass-mark reads (it is the only mapper of that meta) |
| `QuizReportsController`, `ContentHubOverviewController` | `Quiz\GradingPolicy::gradable_quiz_ids()` | One report scope rule (null = every quiz) |
| `QuizReportsController`, `QuizController`, `AssignmentsController` | `Learning\CourseStructure::first_courses()`, `::content_in_course_sql()` | Content → course lookups on the chapter/content relationship tables |
| `ContentHubOverviewController` | `ContentHub\ContentCounts` (new) | Post counts by type/status and recent items |
| `ContentHubOverviewController` | `Quiz\AttemptReports::count_to_grade()`, `Skills\Provenance::archive_counts()`, `Curriculum\Items::counts()`, `Bundles\Bundles::status_counts()` | Cross-domain counts now go through each owner |
| `StudentController` | `Student\Directory` (new) | Directory SQL, blocked flag, block/unblock of enrolment and membership rows plus its hooks |
| `StudentProController` | `Student\Enrollments` (new) | Enrolment list/count/lookup, progress counts, manual enrol (duplicate and race check, hook) and unenrol (progress clean-up, hook) |
| `StudentReportController` | `Student\Enrollments`, `Quiz\AttemptReports`, `Assignment\Submissions` (new), `Performance\Snapshot` | Each summary and page through the owning domain; `_total_points` read once per page |
| `QuizController` | `Quiz\AttemptReports::max_marks()` | Attempt max-marks query |
| `Analytics\Reminders` | `Student\Directory::is_blocked()` | Removed a domain → REST controller dependency |

Controllers keep capability checks, the "cannot block yourself or an administrator" rule, course and
student existence checks, and HTTP status mapping. Services return `WP_Error` with the same codes and
statuses the controllers returned before (`ohmylms_student_already_enrolled` 409, `_enrollment_create_failed`
500, `_enrollment_delete_failed` 500, `_student_update_failed` 500). None of the moved code used
`Utility\Transaction` or a stale-write token before, and none does now.

## Audit by domain

### Quizzes, attempts and quiz reports

| Controller | Service | Persistence left in controller | Cross-domain access | Status |
| --- | --- | --- | --- | --- |
| `QuizReportsController` | `Quiz\AttemptReports`, `GradingPolicy`, `QuizSettings` | none | course via `Learning\CourseStructure` | Separated |
| `QuizController` | `QuizSettings`, `GradingPolicy`, `Review`, `Assessment\AttemptReport`, `QuestionBank\*` | 9: list attempt/question counts, attempts page and detail (joins `users`), per-attempt marks, `grade_events` count | course via `CourseStructure`; reads `quiz_questions_relationship` and Assessment `grade_events` directly | Partial |
| `AttemptController` | `Assessment\*`, `Quiz\*` | 2 raw `ohmylms_quiz_attempts` row reads | — | Controller SQL |
| `AssessmentController` | `Assessment\*` | 2: `quiz_revisions`, `attempt_context` | Assessment tables (owner `Assessment\RevisionPublisher`) | Controller SQL |
| `QuizQuestionsController`, `QuestionsController`, `QuestionController`, `QuestionBankController` | `QuestionBank\*`, `Assessment\Pools` | QuizQuestions 5 `$wpdb` and direct `postmeta`; Questions 5 meta; QuestionBank 4 meta | — | Other owner (audit only) |
| `PreviewController`, `TemplateController`, `QuestionImportController`, `QuestionBankToolsController` | `Assessment`, `QuestionBank`, `Extensions` | none | — | Separated |

`Quiz\GradingPolicy::course_id()` and `quiz_ids_for_author()` still query the course-structure tables
directly. `course_id()` takes an arbitrary first row (`LIMIT 1`) where the reports take the lowest course
ID; decide which rule grading should use before routing it through `CourseStructure`.

### Students and enrolments

| Controller | Service | Persistence left in controller | Cross-domain access | Status |
| --- | --- | --- | --- | --- |
| `StudentController` | `Student\Directory` | one `_ohmylms_last_login` user-meta read | — | Separated |
| `StudentProController` | `Student\Enrollments`, `Student\Directory` | none | course totals via `Data\Course` | Separated |
| `StudentReportController` | `Student\Enrollments`, `Quiz\AttemptReports`, `Assignment\Submissions`, `Performance\Snapshot` | none | all through owners | Separated |
| `CourseRosterController` | `Analytics\Repository` | none | — | Separated |

`Student\Directory::set_blocked()` still updates `ohmylms_user_membership` itself; the Membership domain has
no status service for it yet.

### Courses, structure, learning and sessions

| Controller | Service | Persistence left in controller | Cross-domain access | Status |
| --- | --- | --- | --- | --- |
| `CourseController` | `Course\CourseHelper`, `Data\Course` | 8 `$wpdb` (enrolment counts, manual enrolment insert/update/delete, by-email enrolment), 12 meta, 1 option | writes `ohmylms_user_enrollment`, duplicating `Student\Enrollments` | Controller SQL |
| `LearningController` | `Learning\*` | 6: advisory lock, progress insert, enrolled users | enrolment and progress tables | Controller SQL |
| `ChapterController` | `Data\Chapter` | 2 deletes (`content_relationship`, `user_progress`) | structure and progress tables (`CourseStructure::detach()` exists) | Controller SQL |
| `AIController` | `Data` | 1 relationship delete, 1 meta | structure table | Controller SQL |
| `SessionsController` | `Integrations` | 2 `$wpdb` (roster over enrolment + progress), 22 meta | enrolment and progress tables | Partial |
| `SessionController` (legacy `session`, via `ApiHook`) | — | 53 meta, 12 date-format options | — | Legacy |
| `CourseStructureController` | `Learning\CourseStructure` | 3 term/post meta | — | Partial |
| `CourseStructureToolsController`, `CourseSettingsController`, `CourseCatalogController`, `CourseOrganizationController`, `ContentHubController`, `AuthoringController`, `LessonAuthoringController`, `LessonController` | `Learning\*`, `Curriculum\*` | none | — | Separated |
| `ContentHubOverviewController` | `ContentHub\ContentCounts` and the owners above | none | all through owners | Separated |

### Assignments

| Controller | Service | Persistence left in controller | Cross-domain access | Status |
| --- | --- | --- | --- | --- |
| `AssignmentsController` | `Data\Assignment`, `Learning\Placements` | 7 `$wpdb` (list counts, submissions page/detail), 3 meta | course via `CourseStructure` | Partial |
| `AssignmentController` (pro, via `ApiHook`) | `Data` | 1 `$wpdb`, 4 meta; grading pipeline | `Schools\Service::grade()` calls it with a constructed `WP_REST_Request` | Controller SQL, inverted dependency |

### Account Hub and schools

| Controller | Service | Persistence left in controller | Cross-domain access | Status |
| --- | --- | --- | --- | --- |
| `AccountHubController` | `Schools\Service`, `ClassManagement`, `Access` | 27 `$wpdb`: paged reads of schools, years, classes, memberships, guardian links, invitations | `users`, `usermeta` | Controller SQL (largest) |
| `AccountHubRosterController` | `Schools\*` | 9 `$wpdb` (roster, profiles, invitations) | `users`, `usermeta`, `_ohmylms_managed_school` | Controller SQL |
| `AccountHubUsersController` | `Schools\*` | 2 `$wpdb` (`FOR UPDATE` on `users`), 6 user meta | reads `_ohmylms_banned_student` itself (owner `Student\Directory::is_blocked()`) | Partial |
| `Schools\Controller` (legacy `school/*`) | `Schools\Service` | 4 `$wpdb`, 2 meta | structure tables | Partial |
| `SkillReportController` | `Skills`, `Performance`, `Schools` | 3 `$wpdb` | `class_memberships` (Schools), enrolment (Student), `skill_evidence` (Assessment) | Controller SQL |

### Curriculum, syllabus, skills and bundles

| Controller | Service | Persistence left in controller | Cross-domain access | Status |
| --- | --- | --- | --- | --- |
| `CurriculumController`, `SyllabusController`, `SubjectController`, `SkillMappingController`, `TagController`, `MyBundlesController`, `PlacementController`, `PracticeController`, `SkillAssessmentToolsController` | `Curriculum\*`, `Bundles`, `Practice` | none | — | Separated |
| `SyllabusEditorController`, `SkillAuthoringController` | `Curriculum\Syllabus*`, `Skills\*` | `GET_LOCK`/`RELEASE_LOCK` advisory locks; SkillAuthoring 7 term meta | — | Partial |
| `ComparisonController` | `Curriculum\Comparison`, `SkillMappings` | 7 `$wpdb` (locks, mapping-group reads/deletes), 3 options | — | Controller SQL |
| `BundleController` | `Bundles\Bundles` | 1 search over `curriculum_items` | Curriculum table (owner `Curriculum\Items`) | Controller SQL (small) |
| `CategoryController` | `Curriculum\*` | 1 count query | — | Controller SQL (small) |
| `SkillController` | `Skills\*` | 6 term meta | — | Partial |

### Commerce

| Controller | Service | Persistence left in controller | Cross-domain access | Status |
| --- | --- | --- | --- | --- |
| `SubscriptionsController` | commerce helpers | 2 `$wpdb`, 21 post meta, 5 options (per-subscription lock) | — | Controller SQL |
| `MembershipController` (via `ApiHook`) | `DataStores\MembershipStore` | 4 `$wpdb` on `user_membership` | — | Controller SQL |
| `PaymentGatewaySettingsController` | `Settings\*` | none | — | Separated |

### Settings, setup, gamification, integrations and platform

| Controller | Service | Persistence left in controller | Cross-domain access | Status |
| --- | --- | --- | --- | --- |
| `SettingsController` | `Settings\SettingsService` | 1 transient clean-up query, default-page options | reads `ohmylms_integrations` (Integrations) | Partial |
| `IntegrationsController` | `Integrations\*` | owns the `ohmylms_integrations` and `ohmylms_content_protection` options | `AppController` and `SettingsController` read the option directly | Partial |
| `AppController` | — | 2 options (currency, `ohmylms_integrations`) | Integrations option | Partial |
| `EngagementController` | `Engagement\GamificationRegistry`, `GamificationStore` | `SELECT … FOR UPDATE` on `options`, 2 options | — | Partial |
| `Engagement\Rest\GamificationController` | — | 1 `$wpdb` | — | Partial |
| `Engagement\Rest\BadgesController`, `LevelsController` | `Engagement\*` | none | — | Separated |
| `MigrationController` | `Migrations\*` | 1 `$wpdb`, 1 meta | — | Controller SQL (small) |
| `CertificateController` | `CertificateStore`, `Certificates\*` | 1 `get_col` on `certificate_relationship` | — | Partial |
| `AuthController`, `MicrosoftAuthController`, `MailMintAutomationController`, `PluginInstallerController` | `Services\*` | 1 option or meta each | — | Partial (small) |
| `AnalyticsController`, `DashboardController`, `DashboardProController` | `Analytics\Repository`, `Cache`, `DateRange` | none | — | Separated |
| `EmailController`, `WebhookController`, `ExamsController`, `ExamImportsController`, `SetupWizardController`, `SiteSetupController`, `DesignSettingsController`, `HubIconsController`, `PluginController`, `NotificationController`, `UserController`, `XApi\SettingsController` | domain services | none (Exams 1 meta) | — | Separated |
| `DesignSystemController` | `Design\DesignSystem` | — | — | Other owner |

### Cross-domain hot spots outside controllers

Direct table references in `includes/` (files): `ohmylms_user_enrollment` 55, chapter/content relationship
tables 27, `ohmylms_quiz_attempts` 17, `ohmylms_user_progress` 17. Their owners are now
`Student\Enrollments` (enrolment rows), `Learning\CourseStructure` (structure) and `Quiz\AttemptReports` with
`Quiz\Submission` (attempts). Hooks, e-mails, integrations and migrations should move to those owners
gradually; migrations and installers may keep raw SQL.

## Ordered plan

1. **Enrolment writes in `CourseController`** (manual enrolment, by-email enrolment, unenrol) → extend
   `Student\Enrollments`. It duplicates logic just moved; suites: courses, students, student report.
2. **Enrolment and progress reads** in `LearningController`, `SessionsController`, `SkillReportController` →
   `Student\Enrollments` (enrolled user IDs, progress rows); class membership → a `Schools` reader.
3. **Account Hub** (`AccountHubController` 27, `AccountHubRosterController` 9, `AccountHubUsersController` 2)
   → `Schools` read models for the paged lists; writes stay in `Schools\Service`; the blocked flag via
   `Student\Directory::is_blocked()`. Keep the stale-save `version` checks in the service. Suites: the three
   account-hub suites.
4. **Assignment grading service**: extract `update_attempt_report()` from `AssignmentController` into an
   `Assignment` service and call it from `Schools\Service::grade()` (removes the last domain → REST
   dependency); move `AssignmentsController` reads onto `Assignment\Submissions`.
5. **Structure deletes** in `ChapterController` and `AIController` → `Learning\CourseStructure::detach()`;
   decide the `GradingPolicy::course_id()` rule and route it through `CourseStructure`.
6. **Quizzes**: `QuizController` list counts and attempt pages → `Quiz\AttemptReports`; `AttemptController` →
   attempt store; `AssessmentController` revisions → `Assessment\RevisionPublisher`; question-relationship
   counts → `QuestionBank` once the question-editor work lands.
7. **Commerce**: `SubscriptionsController` meta and lock option → a subscription service;
   `MembershipController` counts → `MembershipStore`, including the membership status update now in
   `Student\Directory::set_blocked()`.
8. **Integrations option**: one `Integrations` settings reader used by `AppController`, `SettingsController`
   and `IntegrationsController`.
9. **Advisory locks** (`GET_LOCK` in Comparison, SkillAuthoring, SyllabusEditor, Learning, CourseStructure)
   → one `Utility` lock helper.
10. Retire the legacy `SessionController`.
11. Enforce: extend `tools/check-architecture.mjs` to reject new `$wpdb` calls in `includes/Rest/**` with an
    explicit allowlist that shrinks as each step lands.

## Verification of this change (31 October 2026)

All 36 `tests/php/*-rest-integration.php` suites passed on `800.local` before the change. After it, 35 pass
with the same check counts. Touched domains: quiz reports 47, Content Hub overview 35, students 135, student
report 67, courses 284, assignments 180, analytics 216, account-hub users 88; skills catalogue, skill
authoring, curriculum workspace and course authoring also pass. The one failure is
`quizzes-rest-integration.php` at "pools do not change the question revision" (line 1107). The test is
unchanged; the failure comes from concurrent, uncommitted work in `QuizQuestionsController` that adds the
quiz layout (pools) to the revision. The HEAD version (`revision( $ids, $pins )`) passed all 233 checks in
the baseline run. The checks that cover this change's `QuizController` edits (course reference, attempt
`max_marks`) come earlier in the suite and pass. `php -l` passes on every changed file. PHPCS reports no new
findings: new files are clean, and edited files have the same counts as HEAD. Three pre-existing placeholder
warnings moved from the student controllers into the new services and are annotated there.
`node tools/check-architecture.mjs` passes.
