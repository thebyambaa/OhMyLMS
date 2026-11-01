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
- Every route is registered by a `WP_REST_Controller` subclass, in `includes/Rest/V1` or in the feature's
  own `Rest/` folder, listed in `includes/Rest/Api.php` or registered by the feature bootstrap. Admin pages,
  blocks, hooks, gateways and services do not call `register_rest_route()`
  (`tools/architecture-rules/rest-controllers.mjs`, [ENFORCEMENT.md](ENFORCEMENT.md) section i).
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

After the controller persistence pass (below; counted against HEAD `023ce4b7`): `$wpdb` calls 105 → 28, lines
using `$wpdb` 253 → 53 (the measure of the ratchet, [ENFORCEMENT.md](ENFORCEMENT.md) section j), controllers
using `$wpdb` 28 → 15 of 96 files. `QuestionBank\Usage` no longer reads `QuizQuestionsController` constants.
The second part of the pass moved the remaining 15 files, including the advisory locks of plan step 9: `$wpdb`
calls 28 → 0, lines 53 → 0. The ratchet now also counts the feature controllers (4 files, 34 lines left).
The third part (1 November 2026) moved `WPFusionTriggersController` 13 → 0 and the e-commerce `OrdersController`
10 → 0 and removed their budgets.
The fourth part (1 November 2026) moved `GradebookController` 7 → 0 and `ZoomWebhookController` 4 → 0, so no
controller has a budget, and put every advisory lock behind `Utility\Lock` (plan step 9).

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
| `QuizController` | `QuizSettings`, `GradingPolicy`, `Review`, `AttemptReports`, `Questions`, `Assessment\AttemptReport`, `Assessment\GradeEvents`, `QuestionBank\*` | none | course via `CourseStructure`; question counts via `Quiz\Questions`; grade events via `GradeEvents::count_for()` | Separated |
| `AttemptController` | `Assessment\*`, `Quiz\*` | none (attempt row via `Quiz\AttemptReports::row()`) | — | Separated |
| `AssessmentController` | `Assessment\*` | none (revision list via `RevisionPublisher::history()`) | — | Separated |
| `QuizQuestionsController`, `QuestionsController`, `QuestionBankController` (banks and grants only; the legacy `QuestionController` and `/question-bank` routes were removed 31 October 2026) | `QuestionBank\*`, `Assessment\Pools`, `Quiz\Questions` | QuizQuestions none (placements, pins, marks and the locked revision check in `Quiz\Questions`); Questions 5 meta | — | QuizQuestions Separated; others Other owner (audit only) |
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
no status service for it yet. Its enrolment update goes through `Student\Enrollments::update()`.

**Enrolment storage (Phase C part 1, [enrolments.md](api/enrolments.md)).** `Student\Enrollments` now owns
every enrolment write in the plugin (`create`, `update`, `delete`, `resync`): `CourseController`, checkout,
upsell, order completion, refunds, membership sync and cancellation, free-sale expiry, class sync, exam start,
course completion, preview reset, blocking, deletion hooks and the three LMS importers. Each writes
`ohmylms_user_enrollment` as before plus its projection in `ohmylms_enrolments` with the source. Its readers
(students API, student report, course counts, learning report, session attendees, skill report scope,
gradebook) read the new table once `EnrolmentMigration` is complete and the legacy table otherwise. The
remaining direct readers are listed in enrolments.md; they stay correct because legacy storage stays
authoritative.

### Courses, structure, learning and sessions

| Controller | Service | Persistence left in controller | Cross-domain access | Status |
| --- | --- | --- | --- | --- |
| `CourseController` | `Course\CourseHelper`, `Data\Course`, `Student\Enrollments` | 12 meta, 1 option | enrolment counts and writes through `Student\Enrollments` | Partial |
| `LearningController` | `Learning\*`, `Student\Enrollments` | none (lock and progress insert in `CompletionPolicy::complete_lesson()`) | enrolled users through `Student\Enrollments` | Separated |
| `ChapterController` | `Data\Chapter`, `Learning\CourseStructure` | none (`CourseStructure::remove_with_progress()`) | — | Separated |
| `AIController` | `Data`, `Learning\CourseStructure` | 1 meta (chapter delete in `CourseStructure::remove_course_chapters()`) | — | Partial |
| `SessionsController` | `Integrations`, `Student\Enrollments` | 22 meta | attendee roster through `Student\Enrollments::course_roster()` | Partial |
| `CourseStructureController` | `Learning\CourseStructure` | 3 term/post meta | — | Partial |
| `CourseStructureToolsController`, `CourseSettingsController`, `CourseCatalogController`, `CourseOrganizationController`, `ContentHubController`, `AuthoringController`, `LessonAuthoringController`, `LessonController` | `Learning\*`, `Curriculum\*` | none | — | Separated |
| `ContentHubOverviewController` | `ContentHub\ContentCounts` and the owners above | none | all through owners | Separated |

### Assignments

| Controller | Service | Persistence left in controller | Cross-domain access | Status |
| --- | --- | --- | --- | --- |
| `AssignmentsController` | `Data\Assignment`, `Learning\Placements`, `Assignment\Submissions` | 3 meta | course via `CourseStructure` | Partial |
| `AssignmentController` (no routes since 31 October 2026; only the grading pipeline `update_attempt_report()`) | `Data`, `Student\Enrollments` | 4 meta | `AssignmentsController` and `Schools\Service::grade()` call it with a constructed `WP_REST_Request` | Partial, inverted dependency |

### Account Hub and schools

| Controller | Service | Persistence left in controller | Cross-domain access | Status |
| --- | --- | --- | --- | --- |
| `AccountHubController` | `Schools\Service`, `ClassManagement`, `Access`, `Directory`, `Versions`, `Guardians` | none | `users`, `usermeta` read inside `Schools\Directory` | Separated |
| `AccountHubRosterController` | `Schools\Service`, `Lifecycle`, `Directory`, `RosterImport` | none | `users`, `usermeta`, `_ohmylms_managed_school` read inside `Schools\Directory` | Separated |
| `AccountHubClassWorkController` | `Schools\Service`, `Directory`, `Learning\CourseStructure` | none | — | Separated |
| `AccountHubUsersController` | `Schools\*` | 6 user meta (`FOR UPDATE` edit in `Schools\Versions::update_account()`) | reads `_ohmylms_banned_student` itself (owner `Student\Directory::is_blocked()`) | Partial |
| `SkillReportController` | `Skills`, `Performance`, `Schools`, `Student\Enrollments` | none | class roster via `Schools\Directory`, evidence via `Skills\Evidence::recent()`, enrolment through `Student\Enrollments` | Separated |

### Curriculum, syllabus, skills and bundles

| Controller | Service | Persistence left in controller | Cross-domain access | Status |
| --- | --- | --- | --- | --- |
| `CurriculumController`, `SyllabusController`, `SubjectController`, `SkillMappingController`, `TagController`, `MyBundlesController`, `PlacementController`, `PracticeController`, `SkillAssessmentToolsController` | `Curriculum\*`, `Bundles`, `Practice` | none | — | Separated |
| `SyllabusEditorController`, `SkillAuthoringController` | `Curriculum\Syllabus*`, `Skills\SkillEditor`, `Skills\*` | none (locks in `Syllabus::import_reviewed()` and `SkillEditor::update()`) | — | Separated |
| `ComparisonController` | `Curriculum\Comparison`, `SkillMappings` | 3 options (mapping locks and deletes in `SkillMappings`) | — | Partial |
| `BundleController` | `Bundles\Bundles`, `Curriculum\Items` | none (`Items::search()`) | — | Separated |
| `CategoryController` | `Curriculum\*` | none (`Links::course_counts()`) | — | Separated |
| `SkillController` | `Skills\*` | 6 term meta | — | Partial |

### Commerce

| Controller | Service | Persistence left in controller | Cross-domain access | Status |
| --- | --- | --- | --- | --- |
| `SubscriptionsController` | `Commerce\SubscriptionManager`, `Membership\Subscriptions` | 21 post meta, 5 options (per-subscription lock) | — | Partial |
| `MembershipController` (via `ApiHook`) | `DataStores\MembershipStore`, `Membership\Members` | none | — | Separated |
| `PaymentGatewaySettingsController` | `Settings\*` | none | — | Separated |

### Settings, setup, gamification, integrations and platform

| Controller | Service | Persistence left in controller | Cross-domain access | Status |
| --- | --- | --- | --- | --- |
| `SettingsController` | `Settings\SettingsService` | default-page options (transient clean-up in `SettingsService::clear_transients()`) | reads `ohmylms_integrations` (Integrations) | Partial |
| `IntegrationsController` | `Integrations\*` | owns the `ohmylms_integrations` and `ohmylms_content_protection` options | `AppController` and `SettingsController` read the option directly | Partial |
| `AppController` | — | 2 options (currency, `ohmylms_integrations`) | Integrations option | Partial |
| `EngagementController` | `Engagement\GamificationRegistry`, `GamificationStore` | 2 options (`FOR UPDATE` save in `GamificationStore::save_checked()`) | — | Partial |
| `Engagement\Rest\GamificationController` | `Engagement\Achievements` | none (`Achievements::earned_counts()`) | — | Separated |
| `Engagement\Rest\BadgesController`, `LevelsController` | `Engagement\*` | none | — | Separated |
| `MigrationController` | `Migrations\*` | 1 meta (lookup in `Migration::migrated_courses()`) | — | Partial (small) |
| `CertificateController` | `CertificateStore`, `Certificates\*`, `Learning\CourseCertificate` | none (`CourseCertificate::all()`) | — | Separated |
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

## REST controllers: engagement, extensions, gateways, gradebooks, view-as, integrations, xAPI, dashboard (31 October 2026)

Every route of these features is registered by a `WP_REST_Controller` subclass
(`tools/architecture-rules/rest-controllers.mjs`, allow-list key `restControllers`; none of these files is
listed). Paths, methods, arguments, permission rules, status and error codes and bodies are unchanged.

| Routes (`ohmylms/v1`) | Before | Controller now | Domain class keeps |
| --- | --- | --- | --- |
| `engagement/streak` | closures in `Engagement\StreakHooks::routes()` | `Engagement\Rest\StreakController` | `Streak` |
| `engagement/xp` | closures in `Engagement\XpHooks::routes()` | `Engagement\Rest\XpController` | `Xp` |
| `extensions` | closure in `Extensions\Bootstrap::init()` | `Extensions\Rest\ExtensionsController` | `Registry`, `Settings::manifest()` |
| `extension-settings/<type>/<id>` | `Extensions\Settings::init()` (`permission`, `read`, `save`) | `Extensions\Rest\ExtensionSettingsController` (permission rule, request mapping) | `Settings::supports()`, `stored()`, `update()`, `prepare()` |
| `tab-preferences/<scope>` | `Extensions\TabPreferences::register_routes()` | `Extensions\Rest\TabPreferencesController` | `TabPreferences::save()`, `read()`, `prepare()` |
| `paypal-webhook` (public) | `GatewayPaypal::register_webhook_endpoint()` | `Gateways\Paypal\Rest\WebhookController`, built by the gateway with itself | `GatewayPaypal::handle_webhook()` and its signature check |
| `courses/<id>/gradebook[/classes[/<id>]\|/grades]` | `Schools\Gradebook::register()`, `dispatch()` | `Schools\Rest\CourseGradebookController` (nonce check, dispatch, error mapping) | `Gradebook::read()`, `classes()`, `attach()`, `detach()`, `grade()` |
| `school/view-as/{options,default,start,return}` | `Schools\ViewAs::register()` | `Schools\Rest\ViewAsController` | `ViewAs::options()`, `default()`, `start()`, `return()`, `session()` |
| `gradebook/*`, `settings/grade-scale` | `Gradebook\Rest\GradebookController` (no parent) | same class, now `extends WP_REST_Controller` | `Gradebook\*` |
| `googlemeet/settings/*`, `wpfusion/auth/*`, `wpfusion/triggers/*`, `zoom/settings/credentials`, `zoom/webhook` (public) | the integration controllers (no parent) | same classes, now `extends WP_REST_Controller`; `instance()` kept | integration helpers; the Zoom HMAC check is unchanged |
| `xapi/settings`, `xapi/settings/<action>` | `XApi\SettingsController` (no parent, static `register_routes()`) | same class, `extends WP_REST_Controller`; `register_routes()` is an instance method, hooked through `XApi\Bootstrap::register_routes()` | `XApi\Settings`, `Processor`, `Outbox` |
| `dashboard/widgets/mastery-heatmap/learners`, `dashboard/widgets/review-sets/assign` | `Dashboard\Widgets\MasteryController`, `ReviewSetsController` (no parent) | same classes, now `extends WP_REST_Controller` | `MasteryRepository`, `ReviewSetsService` |

`gamification/badges` and `gamification/levels` already reached `WP_REST_Controller` through
`GamificationController` → `Abstracts\RestController` and are unchanged.

Bootstraps call the controllers: `Extensions\Bootstrap::register_routes()`, `Schools\Bootstrap::register_routes()`
and `XApi\Bootstrap::register_routes()`, plus `StreakHooks::routes()` and `XpHooks::routes()`. Each one is hooked
as a static callable, so WordPress de-duplicates it. This matters because `Schools\Bootstrap::init()` runs twice
(`includes/extensions.php`); a closure there would register every view-as and course gradebook route twice.
Removed public methods: `Extensions\Settings::permission()`, `read()`, `save()`,
`TabPreferences::register_routes()`, `Schools\Gradebook::register()`, `dispatch()`, `ViewAs::register()` and
`GatewayPaypal::register_webhook_endpoint()`. No other code called them.

At the time `GatewayPaypal` was a global class outside the PSR-4 map and the classmap, so
`Commerce\Gateways\Gateways::init()` never built it and `/paypal-webhook` was not registered; the route was
checked by loading the gateway by hand. It is now `OhMyLMS\Gateways\Paypal\GatewayPaypal` (PSR-4), listed once by
`Gateways::init()`, and the route registers on every request: GET 405, an unsigned POST 401. Its return handler
(`handle_paypal_return()`, previously never reached) now runs only for an enabled gateway and a PayPal order whose
key matches the return URL, and marks it paid only after `confirm_paypal_capture()` checks the amount with PayPal.

Verification: a dump of `rest_get_server()->get_routes()` (path, methods, `__return_true` or not, argument
names, types, defaults and enums) is identical before and after for all 1,040 endpoints. The 70 endpoints
above match, and so does `/paypal-webhook` with the gateway loaded by hand: GET 405, an unsigned POST 401 and a
non-PayPal certificate 401, each with the same body. Suites: gamification REST 328, gamification rewards 27,
gradebook REST 240, gradebook, dashboard mastery 149, integrations REST 179, account-hub schools lifecycle 212,
memberships REST 205, checkout extras 52, enrolments migration 309, legacy routes 866 and settings secrets
pass. The unit runner passes 58 suites, including `xapi-addon-unit` 50, `tab-preferences-unit` and
`view-as-unit`; those three now load the controller and stub `WP_REST_Controller`. The streak suite needs
`ohmylms_streak_test` and `extensions-integration` needs `ohmylms_source_test`, so their routes were checked with
a read-only smoke script instead (31 checks, including logged-out 401s, `xp_off`, the extension-settings error
codes, view-as nonce rules and `Cache-Control`).

## REST controllers: AI, assessment, blocks, exam import, exams, MCP, practice, question bank (31 October 2026)

These routes were registered inline on `rest_api_init` by the domain classes. Each now has a controller
that extends `Abstracts\RestController` (so `WP_REST_Controller`) and holds the route, argument schema and
permission callback; every callback calls the domain method that answered before, so paths, methods,
arguments, permission rules, status and error codes and bodies are unchanged.

| Routes (`ohmylms/v1`) | Before | Controller now | Domain class keeps |
| --- | --- | --- | --- |
| `ai` (GET, POST), `ai/models`, `ai/test` | `AI\AdminPage::routes()` | `AI\Rest\SettingsController` | `AdminPage::can()`, `read()`, `save()`, `test()`; `Models::listing()` |
| `question-preview` | `Assessment\QuestionPreview::routes()` | `Assessment\Rest\QuestionPreviewController` | `QuestionPreview::permission()`, `create()`, `view()`, `document()` |
| `response-media` | `Assessment\ResponseMedia::routes()` (permission closure) | `Assessment\Rest\ResponseMediaController` | `ResponseMedia::upload()`, `limit()`, `looks_like()` |
| `question-player/choices`, `question-player/<post_id>/<key>` | `Blocks\QuestionPlayerBlock::routes()` | `Blocks\Rest\QuestionPlayerController` | `QuestionPlayerBlock::choices()`, `operate()`, `placement()` |
| `quiz-player/quizzes`, `quiz-player/quizzes/<id>/attempt` | `Blocks\QuizPlayerBlock::register_routes()` | `Blocks\Rest\QuizPlayerController` | `QuizPlayerBlock::choices()`, `mutate()` |
| `exam-imports` (GET, POST), `exam-imports/<id>` (GET, POST, DELETE) | `ExamImport\Jobs::routes()` | `ExamImport\Rest\JobsController` | `Jobs::permission()`, `create()`, `listing()`, `status()`, `retry()`, `delete()` |
| `exam-import-skills`, `exam-imports/rows`, `exam-imports/<id>/extract\|review\|commit\|quiz` | `ExamImport\Workflow::routes()` | `ExamImport\Rest\WorkflowController` | `Workflow::skills()`, `rows()`, `extract()`, `review()`, `commit()`, `quiz()` |
| `exam-imports/<id>/reconstruct`, `exam-imports/<id>/figures` (GET, POST), `exam-import-renderer` | `ExamImport\Figures::routes()`, which called `Reconstruction::routes()` | `ExamImport\Rest\FiguresController` | `Figures::listing()`, `create()`; `Reconstruction::generate()`; `PageRenderer::capability()` |
| `exams` (GET, POST), `exams/<id>/publish\|release` | `Exams\Library::routes()` | `Exams\Rest\LibraryController` | `Library::permission()`, `listing()`, `create()`, `publish()`, `release()` |
| `exams/compose`, `exams/<id>/settings\|questions` | `Exams\Composer::routes()` | `Exams\Rest\ComposerController` | `Composer::create()`, `settings()`, `add_questions()` |
| `mcp/settings`, `mcp/settings/<provider>` | `MCP\Server::register()` | `MCP\Rest\ServerController` | `Server::can_manage()`, `settings()`, `update_settings()`; the MCP Adapter still registers `mcp` itself |
| `student/dashboard-theme` (PUT) | closures in `Practice\Dashboard::init()` | `Practice\Rest\DashboardController` (reads `theme`, shapes `{ theme }`) | `Dashboard::save_theme()` (new: the theme check, `dashboard_theme` 400 and the user meta write) |
| `question-bank-organisation` | closure in `QuestionBank\Organisation::init()` | `QuestionBank\Rest\OrganisationController` | `Organisation::views()` |

The controllers are listed in `includes/Rest/Api.php` after the V1 controllers, in the order the old hooks
ran, so they now also pass through the `ohmylms_rest_v1_controllers` filter. Removed public methods:
`AdminPage::routes()`, `QuestionPreview::routes()`, `ResponseMedia::init()` and `routes()`,
`QuestionPlayerBlock::routes()`, `QuizPlayerBlock::register_routes()`, `Jobs::routes()`, `Workflow::routes()`,
`Figures::routes()`, `Reconstruction::routes()`, `Library::routes()`, `Composer::init()` and `routes()`,
`MCP\Server::register()` and `Organisation::init()`. Their callers (`Assessment\Bootstrap::init()`,
`Library::init()`, `includes/extensions.php`) were updated; `tests/php/quiz-player-block.php` now builds the
controller. No other code called them.

Verification: `rest_get_server()->get_routes()` was dumped before and after (path, methods, `__return_true` or
not, a hash of the full argument schema, `accept_json`, `show_in_index`). The 36 endpoints on the 30 routes above
are identical apart from the callback names, and the rest of the 742-route table is unchanged. Because the
routes now register later (after the V1 controllers), a sample path for every `ohmylms/v1` route was also
resolved with `match_request_to_handler()` for GET, POST, PUT, PATCH and DELETE: all 2,760 resolve to the same
route and handler as before. A live smoke script (41 `rest_do_request()` calls as a guest and as an
administrator, outgoing HTTP blocked) returns the expected statuses and codes, for example `rest_forbidden` 401,
`dashboard_theme` 400, `ohmylms_media_missing` 400, `quiz_unavailable` 404 and `ai_models_network` 502.
Suites: exams REST 138, exam results release 112, exam figure proposals 74, question player 33, question types
REST 596, legacy routes 866 and settings secrets pass; the full integration runner passes 93 suites. The unit
runner passes 58 suites, including `quiz-player-block` (38 checks). `figure-assets`, `exam-import-http`,
`pdf-exam-http`, `mcp-integration`, `assessment-profiles`, `native-quiz-editor` and `student-dashboard` need
other databases or binaries and were not run.

## Controller persistence pass (31 October 2026)

Lines using `$wpdb` per controller (comments excluded), HEAD `023ce4b7` → after. No file in `includes/Rest/V1`
uses `$wpdb` any more, so none has a budget. The budgets in `tools/controller-persistence-budgets.json` are
for the feature controllers listed below the table.

| Controller | Before | After | Moved to |
| --- | --- | --- | --- |
| `AccountHubController` | 66 | 0 | `Schools\Directory` (new read model: scope, schools, years, classes, members, candidates, teachers, parents, children); `Schools\Versions` (new: stale-save versions and the `FOR UPDATE` + version check + write of schools and classes in one transaction); `Schools\Guardians::linked()` |
| `AccountHubRosterController` | 20 | 0 | `Schools\Directory` (invitations, managed students, setup links, pending invitation, student ID lookup); `Schools\RosterImport` (new: plan, review token and apply under the per-school `GET_LOCK`) |
| `AssignmentsController` | 25 | 0 | `Assignment\Submissions` (counts, list, detail); `Learning\CourseStructure::content_ids()`, `::chapter_placements()` |
| `QuizController` | 21 | 0 | `Quiz\AttemptReports` (counts, attempt list and detail, frozen marks); `Quiz\Questions::counts()`; `Assessment\GradeEvents::count_for()`; `CourseStructure::content_ids()` |
| `QuizQuestionsController` | 16 | 0 | `Quiz\Questions` (new: placements, pins, marks overrides, sections, revision, and `change()`: quiz row lock + revision check + write in one transaction) |
| `MembershipController` | 10 | 0 | `Membership\Members` (new: member list and counts) |
| `SettingsController` | 8 | 0 | `Settings\SettingsService::clear_transients()` |
| `SubscriptionsController` | 7 | 0 | `Membership\Subscriptions` (new: search and renewal order lookups) |
| `CohortsController` | 6 | 0 | `Cohorts\Cohorts::candidates()` |
| `LearningController` | 6 | 0 | `Learning\CompletionPolicy::complete_lesson()` (activity `GET_LOCK`, progress insert, award) |
| `AccountHubClassWorkController` | 5 | 0 | `Schools\Directory::class_work()`; `CourseStructure::published_items()` |
| `ChapterController` | 5 | 0 | `CourseStructure::remove_with_progress()` (the legacy route still clears learner progress; `detach()` keeps it) |
| `SkillReportController` | 5 | 0 | `Schools\Directory::class_student_ids()`; `Skills\Evidence::recent()` |
| `ComparisonController` | 11 | 0 | `Curriculum\SkillMappings::remove_current()` and `::remove_group()` (mapping `GET_LOCK`, revision checks, and the group delete in one transaction) |
| `SkillAuthoringController` | 4 | 0 | `Skills\SkillEditor` (new: editable state, revision, and `update()`: skill `GET_LOCK` + revision check + term and term-meta write; the response is built under the same lock) |
| `AttemptController` | 4 | 0 | `Quiz\AttemptReports::row()` |
| `SyllabusEditorController` | 4 | 0 | `Curriculum\Syllabus::import_reviewed()` (curriculum `GET_LOCK`, review revision, import) |
| `AccountHubSampleSchoolController` | 3 | 0 | `Schools\SamplePreview::create_once()` (sample-school `GET_LOCK` around the create; the response is built under the lock) |
| `AccountHubUsersController` | 3 | 0 | `Schools\Versions::account()`, `::update_account()` (`FOR UPDATE` on `users` + version check + `wp_update_user()` in one transaction) |
| `AIController` | 3 | 0 | `Learning\CourseStructure::remove_course_chapters()` |
| `AssessmentController` | 3 | 0 | `Assessment\RevisionPublisher::history()` |
| `AssignmentController` | 3 | 0 | `Student\Enrollments::legacy_row()` |
| `AssignmentDeliveryController` | 3 | 0 | `CourseStructure::placement_courses()`, `::content_in_courses()` |
| `BundleController` | 3 | 0 | `Curriculum\Items::search()` |
| `CertificateController` | 3 | 0 | `Learning\CourseCertificate::all()` |
| `CategoryController` | 2 | 0 | `Curriculum\Links::course_counts()` |
| `EngagementController` | 2 | 0 | `Engagement\GamificationStore::revision()`, `::save_checked()` (`FOR UPDATE` on the option row + revision check + save in one transaction) |
| `MigrationController` | 2 | 0 | `Migrations\Migration::migrated_courses()` (and `SOURCE_META`, which the controller constant now points to) |
| `Engagement\Rest\GamificationController` | 3 | 0 | `Engagement\Achievements::earned_counts()` |

Feature controllers that still use `$wpdb`, with their budgets: `Gradebook\Rest\GradebookController` 7 and
`Integrations\Zoom\…\ZoomWebhookController` 4 (`WPFusionTriggersController` 13 and the e-commerce
`OrdersController` 10 moved in the third part, below). In all, 141 controller files are counted (96 in
`includes/Rest/V1`); lines using `$wpdb` went from 90 in 20 files to 34 in 4, then to 11 in 2, then to 0 (fourth
part, below).

Locks and tokens moved with their writes: the school and class `FOR UPDATE` version checks, the quiz question
row lock and revision, the roster import lock and review token, and the lesson completion lock now each run
entirely inside the domain method, with the same error codes, statuses and data
(`ohmylms_account_hub_conflict`, `_class_archived`, `_import_busy`, `_review_stale`,
`ohmylms_quiz_questions_conflict`). SQL text, ordering and limits are unchanged, including the guardian
invitation check that takes the first open row (`Directory::open_guardian_invitation()`, unlike
`Guardians::pending()`, which takes the newest).

Suites, before and after, same check counts: account-hub 228, roster 89, schools lifecycle 212, users 88,
assignments 185, assignment delivery 102, quizzes 241, quiz questions authoring 61, quiz reports 47, quiz
import/export 20, gradebook 240, exams 138, memberships 205, cohorts 65, subscriptions 195, learner journey 22,
setup 184, skill authoring; settings passes (198). The skill report suites need the disposable site; its two
queries and the class-work outline were compared row by row with the old SQL on `800.local`, and the legacy
chapter delete was exercised against disposable rows.

Second part, before and after, same check counts: curriculum compare 77, skill workspace 122, syllabus editor 47,
paper view (attempt autosave and resume) 67, account-hub users 88, schools lifecycle (sample school) 212, quizzes
241, assignments 185, assignment delivery 102, certificates 207, issued certificates 107, taxonomies 122,
gamification 328, gamification rewards 27, points purchase 59, design migration 38; skill authoring and
curriculum workspace (bundle targets) pass with identical output. The mapping removals, the revision list and
the AI chapter clean-up have no suite on `800.local` (`comparison-integration` and `assessment-integration` need
the disposable site), so a throwaway script ran them against rows it created and removed: stale, grouped and
current single removes, missing-pair, stale-pair and current group removes (nothing removed until the last),
the revision list compared with the old SQL, the two placement queries compared with the old SQL, and the
chapter delete.

Third part (1 November 2026), lines using `$wpdb`, HEAD `9aba2492` → after; both budgets are removed:

| Controller | Before | After | Moved to |
| --- | --- | --- | --- |
| `Integrations\WPFusion\Includes\Rest\WPFusionTriggersController` | 13 | 0 | `Integrations\WPFusion\Includes\Triggers` (new: `for_crm()`, `find()`, `create()`, `update()`, `delete()` on the integrations table; `action_data` encoded on write and decoded on read) |
| e-commerce `Rest\V1\OrdersController` | 10 | 0 | `Commerce\DataStore\OrderStore::search_ids()` and `::post_status_counts()`, reached through `DataStores::load( 'order' )` |

SQL text, ordering (`created_at DESC`), the 1000-ID search limit, statuses (201, 404, 500), messages and the
`false` versus `0` checks of the trigger writes are unchanged. `TriggerHandler::process_triggers()` still reads
the integrations table itself (it is not a controller). Suites, before and after, same check counts:
commerce REST 211, checkout extras 53, points purchase 59, commerce interactivity 48, integrations REST 179.
The trigger routes have no suite, so a throwaway script ran the HEAD controller next to the new one on rows it
created and removed (45 checks: create with and without defaults, list, get, missing get, update, unchanged and
missing update, delete, missing delete, forced write failures, events, actions, and order status counts and
searches compared on the site's orders).

Fourth part (1 November 2026), lines using `$wpdb`, HEAD `9aba2492` → after; both budgets are removed, so
`tools/controller-persistence-budgets.json` lists no file:

| Controller | Before | After | Moved to |
| --- | --- | --- | --- |
| `Gradebook\Rest\GradebookController` | 7 | 0 | `Gradebook\Repository::picker_courses()` (context picker) and `::search_learners()` (transcript picker); a failed query still reads as no rows |
| `Integrations\Zoom\Includes\Rest\ZoomWebhookController` | 4 | 0 | `Integrations\Zoom\Includes\Services\RecordingAttachService::attach()` (new: replay pick, session lookup by `_zoom_meeting_data`, attach unless already attached); payload checks, the URL-validation handshake and the HMAC check stay in the controller, unchanged |

Plan step 9: `OhMyLMS\Utility\Lock` is the one place that writes `GET_LOCK`. `with( $name, $timeout, $work,
$busy )` releases in `finally` and returns `$busy` when the lock is not free (a Closure is called, so each
caller keeps its own error code, status and message; default `ohmylms_lock_busy`, 409); `acquire()` and
`release()` serve bodies that keep their own `try`/`finally`. `name()` keeps names that start with `ohmylms`
or `oml` (all current ones) and hashes names over 64 characters, so every lock name, timeout and busy answer is
unchanged. Callers: `with()` in `Achievements::locked()`, `Cohorts::add_member()`, `RuleRunner` (plan grant),
`Items::exclusive()`, `Syllabus::import_reviewed()`, `SkillMappings::remove_current()`, `PlacementBatch`,
`ContentLinks`, `AiAnalysis::review()`, `SkillQuizzes::link()`, `SkillEditor::update()`,
`SamplePreview::create_once()`, `CourseStructure::read()`, `Catalog::with_revision()`,
`LessonAuthoring::locked()`, `ExamImport\Jobs::locked()`, `FreeSale::expire()` and QPay
`PaymentService::locked()`; `acquire()`/`release()` in `SkillMappings::save()`, `::remove_group()`,
`::save_groups()`, `Comparison` (shared skill save), `MappingReconnect`, `SyllabusSheet`, `CourseLinks`,
`AiAnalysis::step()`, `CompletionPolicy::complete_lesson()`, `CourseProgram` (publish), `Quiz\Submission`,
`FreeSale::claim()`, `RosterImport::apply()`, `Rollover::apply()`, `AtRiskNudges`, `ReviewSetsService`,
`Skills\Evidence`, `XApi\Processor`, `Assessment\Migration` and `EnrolmentMigration`. The option-row locks
(`ohmylms_lock_<name>`) use `Lock::acquire_option()` / `release_option()`: `SubscriptionsController` (its
private copies are gone), `PointPurchase::refund()` and the e-commerce `Abstracts\RestController`, whose
`acquire_lock()` / `release_lock()` keep their signatures for the order and refund controllers.
[ENFORCEMENT.md](ENFORCEMENT.md) section p rejects `GET_LOCK(` outside the helper.

Suites, before and after, same check counts: account frontend 146, account-hub 228, roster 89, schools
lifecycle 212, users 88, at-risk 123, automations 77, cohorts 65, commerce REST 211, course authoring 49, course
structure 61, courses 284, curriculum compare 77, dashboard mastery 149, enrolments migration 309, exam results
112, exams 138, gamification 328, gamification rewards 27, gradebook 240, integrations REST 179, learner journey
57, lesson authoring 79, memberships 205, paper view 67, points purchase 59, question player 33, quiz questions
authoring 61, quizzes 241, sessions 49, settings 198, skill workspace 122, subscriptions 195, syllabus editor 47,
taxonomies 122; curriculum workspace, skill authoring and the gradebook suite (`gradebook-integration`) pass
with identical output. The unit runner passes 58 suites. New: `tests/php/lock-integration.php` (40 checks:
names, a second connection seeing the lock, nested takes, release on an exception, busy values, option locks).
The Zoom webhook has no suite, so a throwaway script sent the same 11 requests before and after (bad payload,
URL validation with and without a token, missing, bad and wrong signatures, a signed recording, an already
attached session, an unknown meeting, another event) and got identical responses and post meta.

## Ordered plan

1. ~~**Enrolment writes in `CourseController`**~~ → `Student\Enrollments` (done with the enrolments table,
   [enrolments.md](api/enrolments.md)).
2. **Enrolment and progress reads**: the enrolment reads in `LearningController`, `SessionsController` and
   `SkillReportController` now use `Student\Enrollments` (done). Left: progress rows (`LearningController`
   completion insert) and class membership → a `Schools` reader; the direct enrolment readers outside
   controllers listed in enrolments.md.
3. **Account Hub** (`AccountHubController` and `AccountHubRosterController` done 31 October 2026, see the
   controller persistence pass, with `AccountHubUsersController` and `AccountHubSampleSchoolController`)
   → `Schools` read models for the paged lists; writes stay in `Schools\Service`; the blocked flag via
   `Student\Directory::is_blocked()`. Keep the stale-save `version` checks in the service. Suites: the three
   account-hub suites.
4. **Assignment grading service**: extract `update_attempt_report()` from `AssignmentController` into an
   `Assignment` service and call it from `Schools\Service::grade()` (removes the last domain → REST
   dependency); move `AssignmentsController` reads onto `Assignment\Submissions` (reads done 31 October 2026).
5. **Structure deletes** in `ChapterController` and `AIController` → `Learning\CourseStructure` (done 31 October
   2026: `remove_with_progress()`, `remove_course_chapters()`); decide the `GradingPolicy::course_id()` rule and
   route it through `CourseStructure`.
6. **Quizzes**: ~~`QuizController` list counts and attempt pages → `Quiz\AttemptReports`~~ (done 31 October 2026,
   with `QuizQuestionsController` → `Quiz\Questions`); ~~`AttemptController` → attempt store;
   `AssessmentController` revisions → `Assessment\RevisionPublisher`~~ (done 31 October 2026:
   `AttemptReports::row()`, `RevisionPublisher::history()`); question-relationship counts → `QuestionBank` once
   the question-editor work lands.
7. **Commerce**: `SubscriptionsController` meta and lock option → a subscription service;
   `MembershipController` counts → `MembershipStore`, including the membership status update now in
   `Student\Directory::set_blocked()`.
8. **Integrations option**: one `Integrations` settings reader used by `AppController`, `SettingsController`
   and `IntegrationsController`.
9. ~~**Advisory locks** (`GET_LOCK` in Comparison, SkillAuthoring, SyllabusEditor, Learning, CourseStructure)
   → one `Utility` lock helper.~~ No controller takes a lock any more (31 October 2026): each lock runs with its
   write in `SkillMappings`, `SkillEditor`, `Syllabus::import_reviewed()`, `SamplePreview::create_once()` or
   `CompletionPolicy`, with the same names, timeouts and error codes. Done 1 November 2026: every caller uses
   `Utility\Lock`, and [ENFORCEMENT.md](ENFORCEMENT.md) section p rejects `GET_LOCK(` outside it (fourth part).
10. ~~Retire the legacy `SessionController`.~~ Done 31 October 2026 (with the legacy question, question-bank,
    quiz placement and assignment routes; see the deprecation tables in `docs/api`).
11. ~~Enforce: extend `tools/check-architecture.mjs` to reject new `$wpdb` calls in `includes/Rest/**` with an
    explicit allowlist that shrinks as each step lands.~~ Done 31 October 2026 for `includes/Rest/V1`:
    per-file budgets in `tools/controller-persistence-budgets.json` ([ENFORCEMENT.md](ENFORCEMENT.md) section j).
    Since the second part of the pass the rule also counts the feature `Rest/` folders and every other
    `WP_REST_Controller` subclass; none keeps a budget since the fourth part (see the controller persistence pass).

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
